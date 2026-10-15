<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\View;

/**
 * Export PDF Marking Memo. Render HTML memo yang sama dengan preview,
 * lalu Chrome headless mencetaknya ke PDF. Setiap memo yang diunduh dicatat
 * di tabel memo_prints (dasar hitungan "memo tercetak").
 */
trait ItemMemoPdfActions
{
    /** GET /items/memo.pdf?ids=1,2,3  (maks 200 item per dokumen) */
    public function memoPdf(): void
    {
        $this->authorize('count_data');

        $ids = Request::string('all', '') === '1'
            ? array_slice($this->scopeIds(0), 0, 200)
            : $this->parseIds(Request::string('ids', ''), 200);
        if ($ids === []) {
            $this->redirectWith('/items/memo', 'error', 'Pilih item dulu untuk dicetak.');
        }

        $memos = $this->buildMemos($ids);
        if ($memos === []) {
            $this->redirectWith('/items/memo', 'error', 'Item tidak ditemukan.');
        }

        $html = View::render('items/memo', [
            'title'  => __('Marking Memo'),
            'periodLabel' => $this->periodLabel(),
            'memos'  => $memos,
            'pdf'    => true,
            'pager'  => null,
            'pdfUrl' => null,
        ], null);

        $pdf = $this->htmlToPdf($html);
        if ($pdf === null) {
            $this->redirectWith('/items/memo/preview?ids=' . implode(',', $ids), 'error', 'Gagal membuat PDF. Coba lagi.');
        }

        $this->recordPrints(array_column($memos, 'id'));

        header('Content-Type: application/pdf');
        $disp = Request::string('dl', '') === '1' ? 'attachment' : 'inline';
        header('Content-Disposition: ' . $disp . '; filename="marking-memo-' . date('Ymd-His') . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    /** Catat satu baris per memo yang dicetak. */
    private function recordPrints(array $itemIds): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO memo_prints (item_id, printed_by, created_at) VALUES (?, ?, NOW())'
        );
        $by = Auth::id();
        foreach ($itemIds as $id) {
            $stmt->execute([(int) $id, $by]);
        }
    }

    /**
     * HTML -> PDF lewat Chrome headless (flag yang sudah teruji di server).
     * Folder kerja di /tmp, profile sekali pakai, dibersihkan setelah selesai.
     */
    private function htmlToPdf(string $html): ?string
    {
        $dir = sys_get_temp_dir() . '/sfmemo_' . bin2hex(random_bytes(6));
        if (! mkdir($dir, 0700, true)) {
            return null;
        }
        $in  = $dir . '/memo.html';
        $out = $dir . '/memo.pdf';
        file_put_contents($in, $html);

        // HOME di PHP-FPM = /var/www (tidak bisa ditulis www-data) -> crashpad gagal.
        // Arahkan HOME/XDG dan dump crash ke folder kerja, dan tangkap stderr untuk diagnosa.
        $cmd = sprintf(
            'env HOME=%s XDG_CONFIG_HOME=%s XDG_CACHE_HOME=%s '
            . '/usr/bin/google-chrome --headless=new --no-sandbox --disable-gpu --disable-dev-shm-usage '
            . '--disable-crash-reporter --no-crashpad --crash-dumps-dir=%s --no-pdf-header-footer '
            . '--user-data-dir=%s --print-to-pdf=%s %s >%s 2>&1',
            escapeshellarg($dir),
            escapeshellarg($dir),
            escapeshellarg($dir),
            escapeshellarg($dir),
            escapeshellarg($dir . '/prof'),
            escapeshellarg($out),
            escapeshellarg('file://' . $in),
            escapeshellarg($dir . '/chrome.log')
        );
        exec('timeout 90 ' . $cmd, $o, $rc);
        if (! is_file($out) && is_file($dir . '/chrome.log')) {
            error_log('memo-pdf rc=' . $rc . ' ' . substr((string) file_get_contents($dir . '/chrome.log'), -400));
        }

        $pdf = (is_file($out) && filesize($out) > 0) ? (string) file_get_contents($out) : null;
        // bersihkan seluruh folder kerja (termasuk profile Chrome)
        exec('rm -rf ' . escapeshellarg($dir));

        return $pdf !== null && str_starts_with($pdf, '%PDF') ? $pdf : null;
    }
}
/* I18N5-CTRL */
