<?php

declare(strict_types=1);

namespace App\Support;

/**
 * AdvancedSearch — helper bersama untuk fitur "Pencarian lanjutan":
 * normalisasi kondisi adv[i][col|op|val], validasi whitelist kolom,
 * dan pembentukan klausa SQL (AND) yang aman.
 *
 * Dipakai oleh model mana pun agar semua menu punya perilaku identik
 * dengan Data STO (satu fitur pencarian, bukan filter tersebar).
 */
final class AdvancedSearch
{
    public const OPS = ['contains', 'equals', 'starts', 'gt', 'lt', 'empty', 'in'];
    public const MAX_CONDITIONS = 12;
    public const MAX_VALUE_LEN  = 120;

    /**
     * Normalisasi input adv[i][col|op|val] menjadi daftar kondisi bersih.
     *
     * @param mixed $raw
     * @param array<string,string> $colMap whitelist key => ekspresi SQL/label
     * @param list<string> $numeric key yang diperlakukan numerik
     * @return list<array{col:string,op:string,val:string}>
     */
    public static function normalise(mixed $raw, array $colMap, array $numeric = []): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $cond) {
            if (! is_array($cond)) {
                continue;
            }
            $col = trim((string) ($cond['col'] ?? ''));
            $op  = trim((string) ($cond['op'] ?? ''));
            $val = trim((string) ($cond['val'] ?? ''));

            if ($col === '' || ! isset($colMap[$col])) {
                continue;
            }
            if (! in_array($op, self::OPS, true)) {
                continue;
            }
            if (mb_strlen($val) > self::MAX_VALUE_LEN) {
                continue;
            }
            if ($op !== 'empty' && $val === '') {
                continue;
            }
            if (in_array($op, ['gt', 'lt'], true) && ! is_numeric($val)) {
                continue;
            }
            if ($op === 'empty') {
                $val = '';
            }

            $out[] = ['col' => $col, 'op' => $op, 'val' => $val];
            if (count($out) >= self::MAX_CONDITIONS) {
                break;
            }
        }

        return $out;
    }

    /**
     * Bangun klausa WHERE (AND) dari kondisi adv.
     * $colMap berisi EKSPRESI SQL (mis. 'i.name' atau 'g.name'), bukan label.
     *
     * @param list<array{col:string,op:string,val:string}> $conds
     * @param array<string,string> $colMap
     * @param list<string> $numeric
     * @param list<mixed> $bindings ditulis by reference
     * @return list<string>
     */
    public static function clauses(array $conds, array $colMap, array $numeric, array &$bindings): array
    {
        $clauses = [];
        foreach ($conds as $c) {
            $expr = $colMap[$c['col']] ?? null;
            if ($expr === null) {
                continue;
            }
            $isNum = in_array($c['col'], $numeric, true);

            switch ($c['op']) {
                case 'contains':
                    $clauses[] = "{$expr} LIKE ?";
                    $bindings[] = '%' . $c['val'] . '%';
                    break;
                case 'starts':
                    $clauses[] = "{$expr} LIKE ?";
                    $bindings[] = $c['val'] . '%';
                    break;
                case 'equals':
                    $clauses[] = "{$expr} = ?";
                    $bindings[] = ($isNum && is_numeric($c['val'])) ? (float) $c['val'] : $c['val'];
                    break;
                case 'gt':
                    $clauses[] = "{$expr} > ?";
                    $bindings[] = (float) $c['val'];
                    break;
                case 'lt':
                    $clauses[] = "{$expr} < ?";
                    $bindings[] = (float) $c['val'];
                    break;
                case 'empty':
                    $clauses[] = "({$expr} IS NULL OR {$expr} = '')";
                    break;
                case 'in':
                    $list = array_values(array_filter(explode("\x1f", $c['val']), static fn ($v) => $v !== ''));
                    if ($list === []) {
                        break;
                    }
                    $clauses[] = "{$expr} IN (" . implode(',', array_fill(0, count($list), '?')) . ')';
                    foreach ($list as $v) {
                        $bindings[] = ($isNum && is_numeric($v)) ? (float) $v : $v;
                    }
                    break;
            }
        }

        return $clauses;
    }

    /** Ringkasan kondisi untuk ditampilkan sebagai chip. */
    public static function chips(array $conds, array $labels): string
    {
        if ($conds === []) {
            return '';
        }
        $out = '<span class="adv-chips">';
        foreach ($conds as $c) {
            $lbl = $labels[$c['col']] ?? $c['col'];
            $out .= '<span class="adv-chip">' . e((string) $lbl) . ' ' . e($c['op']) . ' <b>' . e($c['val']) . '</b></span>';
        }
        return $out . '</span>';
    }
}