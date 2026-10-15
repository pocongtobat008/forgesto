<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Models\BookPeriod;

/**
 * Close buku: tutup periode, buka periode lama read-only, mulai periode baru (reset hitung).
 * Hanya admin (manage_data).
 */
trait BookPeriodActions
{
    /** POST /audit/period/close */
    public function closePeriod(): never
    {
        $this->authorize('manage_data');
        $this->verifyCsrf();

        $m = new BookPeriod();
        $p = $m->active();
        if ($p === null) {
            $this->redirectWith('/tahun-buku', 'error', 'Belum ada periode aktif.');
        }
        if (($p['status'] ?? 'open') === 'closed') {
            $this->redirectWith('/tahun-buku', 'info', 'Periode ini sudah ditutup.');
        }

        $n = $m->close((int) $p['id'], (int) Auth::id());
        $this->redirectWith('/tahun-buku', 'success', "Periode {$p['audit_month']} ditutup. {$n} item disnapshot dan dikunci.");
    }

    /** POST /audit/period/{id}/view : lihat periode lama read-only. */
    public function viewPeriod(array $params): never
    {
        $this->authorize('manage_data');
        $this->verifyCsrf();

        $id = (int) $params['id'];
        $m  = new BookPeriod();
        $p  = $m->find($id);
        if ($p === null) {
            $this->redirectWith('/tahun-buku', 'error', 'Periode tidak ditemukan.');
        }
        BookPeriod::setViewPeriod($id);
        $this->redirectWith('/items', 'success', "Membuka Tahun Buku {$p['audit_month']} (Mode Read-Only).");
    }

    /** POST /audit/period/new : hanya bila periode aktif sudah ditutup. */
    public function startNewPeriod(): never
    {
        $this->authorize('manage_data');
        $this->verifyCsrf();

        $m   = new BookPeriod();
        $cur = $m->active();
        if ($cur !== null && ($cur['status'] ?? 'open') !== 'closed') {
            $this->redirectWith('/tahun-buku', 'error', 'Tutup dulu periode aktif sebelum membuka periode baru.');
        }

        $month = Request::string('audit_month');
        $start = Request::string('audit_start');
        $end   = Request::string('audit_end');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
            || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)
            || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)
            || $start > $end
            || substr($start, 0, 7) !== $month || substr($end, 0, 7) !== $month) {
            $this->redirectWith('/tahun-buku', 'error', 'Bulan atau tanggal periode baru tidak valid.');
        }

        $newId = $m->openNew($month, $start, $end, (int) Auth::id());
        $this->redirectWith('/tahun-buku', 'success', "Periode baru {$month} dibuka. Hitung direset ke 0 (id {$newId}).");
    }

    /** Guard titik tulis: tolak bila periode aktif sudah closed. */
    protected function guardOpenPeriod(string $back): void
    {
        $p = BookPeriod::current();
        if ($p !== null && (($p['status'] ?? 'open') === 'closed' || ! empty($p['is_archive_view']))) {
            $this->redirectWith($back, 'error', "Tahun Buku {$p['audit_month']} dalam mode read-only (periode ditutup). Data terkunci.");
        }
    }
}
