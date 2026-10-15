<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Periode audit aktif (bulan + tanggal mulai/selesai). */
class AuditPeriod extends Model
{
    protected string $table = 'audit_periods';

    protected array $fillable = [
        'audit_month', 'audit_start', 'audit_end', 'note', 'is_active', 'created_by',
    ];

    /** Periode aktif terbaru, atau null bila belum diatur. */
    public function active(): ?array
    {
        return BookPeriod::current();
    }

    public function dbActive(): ?array
    {
        return $this->first(
            'SELECT * FROM audit_periods WHERE is_active = 1 ORDER BY id DESC LIMIT 1'
        );
    }

    /** Nonaktifkan periode lama, lalu simpan periode baru sebagai aktif. */
    public function activate(string $month, string $start, string $end, ?string $note, int $userId): int
    {
        $this->run('UPDATE audit_periods SET is_active = 0 WHERE is_active = 1');

        return (int) $this->insert([
            'audit_month' => $month,
            'audit_start' => $start,
            'audit_end'   => $end,
            'note'        => $note,
            'is_active'   => 1,
            'created_by'  => $userId,
        ]);
    }
}
