<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Models\AuditPeriod;

/** Pengaturan periode audit (bulan + tanggal mulai/selesai). Hanya admin. */
trait AuditPeriodActions
{
    /** POST /audit/period : simpan periode aktif baru. */
    public function savePeriod(): never
    {
        $this->authorize('manage_data');
        $this->verifyCsrf();

        $month = Request::string('audit_month');
        $start = Request::string('audit_start');
        $end   = Request::string('audit_end');
        $note  = trim(Request::string('note'));

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $this->redirectWith('/tahun-buku', 'error', 'Bulan audit tidak valid.');
        }
        foreach ([$start, $end] as $d) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || strtotime($d) === false) {
                $this->redirectWith('/tahun-buku', 'error', 'Tanggal audit tidak valid.');
            }
        }
        if ($start > $end) {
            $this->redirectWith('/tahun-buku', 'error', 'Tanggal selesai harus setelah tanggal mulai.');
        }
        if (substr($start, 0, 7) !== $month || substr($end, 0, 7) !== $month) {
            $this->redirectWith('/tahun-buku', 'error', 'Tanggal harus berada di bulan audit yang dipilih.');
        }

        (new AuditPeriod())->activate($month, $start, $end, $note !== '' ? mb_substr($note, 0, 255) : null, (int) \App\Core\Auth::id());

        $this->redirectWith('/tahun-buku', 'success', 'Periode audit disimpan.');
    }
}
