<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Pembuat ID karyawan (login) untuk role Observer & Auditor.
 *
 * ID mengikuti pola prefix + nomor urut, mis. WE100007.
 *
 * Nomor berikutnya diambil dari ujung deret berurutan yang paling panjang
 * (mis. WE100001..WE100006 -> WE100007), bukan dari nilai terbesar. Dengan
 * begitu satu ID lama yang nomornya jauh (mis. WE2015072) tidak membuat
 * nomor baru meloncat ke 2015073.
 */
class EmployeeIdService
{
    /**
     * Deret ID berikutnya.
     *
     * @return list<string>
     */
    public function nextCodes(string $prefix, int $qty, ?int $start = null): array
    {
        $prefix = strtoupper(trim($prefix));
        $qty    = max(1, min(50, $qty));

        if ($start !== null && $start > 0) {
            $width = $this->dominantWidth($prefix);
        } else {
            [$start, $width] = $this->nextSequence($prefix);
        }

        $codes = [];
        for ($i = 0; $i < $qty; $i++) {
            $codes[] = $prefix . str_pad((string) ($start + $i), $width, '0', STR_PAD_LEFT);
        }

        return $codes;
    }

    /**
     * Nomor urut berikutnya + lebar digit yang dipakai.
     *
     * @return array{0:int,1:int}
     */
    private function nextSequence(string $prefix): array
    {
        $width = $this->dominantWidth($prefix);

        $numbers = [];
        foreach ($this->existingCodes($prefix) as $code) {
            $rest = substr($code, strlen($prefix));

            if ($rest !== '' && ctype_digit($rest) && strlen($rest) === $width) {
                $numbers[] = (int) $rest;
            }
        }

        if ($numbers === []) {
            return [1, $width];
        }

        sort($numbers);
        $numbers = array_values(array_unique($numbers));

        // Cari deret berurutan terpanjang.
        $bestEnd   = $numbers[0];
        $bestLen   = 1;
        $runStart  = $numbers[0];
        $runLen    = 1;

        for ($i = 1, $n = count($numbers); $i < $n; $i++) {
            if ($numbers[$i] === $numbers[$i - 1] + 1) {
                $runLen++;
            } else {
                $runStart = $numbers[$i];
                $runLen   = 1;
            }

            if ($runLen > $bestLen) {
                $bestLen = $runLen;
                $bestEnd = $numbers[$i];
            }
        }

        return [$bestEnd + 1, $width];
    }

    /** Lebar digit yang paling banyak muncul; default 6 (mis. WE100007). */
    private function dominantWidth(string $prefix): int
    {
        $count = [];

        foreach ($this->existingCodes($prefix) as $code) {
            $rest = substr($code, strlen($prefix));

            if ($rest !== '' && ctype_digit($rest)) {
                $count[strlen($rest)] = ($count[strlen($rest)] ?? 0) + 1;
            }
        }

        if ($count === []) {
            return 6;
        }

        arsort($count);

        return (int) array_key_first($count);
    }

    /**
     * Semua ID yang sudah terpakai dengan prefix tertentu (users + employees).
     *
     * @return list<string>
     */
    private function existingCodes(string $prefix): array
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT username AS code FROM users WHERE username LIKE ?
             UNION
             SELECT employee_id AS code FROM employees WHERE employee_id LIKE ?'
        );
        $stmt->execute([$prefix . '%', $prefix . '%']);

        return array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }
}
