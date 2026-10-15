<?php

/**
 * StockForge - SKRIP DIAGNOSTIK SEMENTARA.
 *
 * Buka:   http://localhost/SiStock/diag.php
 * Setelah selesai: HAPUS file ini.
 *
 * Tujuan: memisahkan masalah Apache/PHP/MySQL dari masalah kode aplikasi.
 * Skrip ini sengaja TIDAK memuat bootstrap/route StockForge, dan mengirim
 * output langkah demi langkah (flush), sehingga kalau ada bagian yang
 * menggantung, Anda bisa melihat langkah terakhir yang berhasil.
 */

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('default_socket_timeout', '3');
error_reporting(E_ALL);

// Agar session bisa diuji walau sudah ada output (tanpa perlu kirim header).
ini_set('session.cache_limiter', '');
ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
ini_set('session.use_trans_sid', '0');

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

$GLOBALS['__t0'] = microtime(true);

function step(string $label, string $detail = ''): void
{
    $ms = number_format((microtime(true) - $GLOBALS['__t0']) * 1000, 0, '.', '');
    printf("%-32s %s  [+%s ms]\n", $label, $detail, $ms);
    flush();
}

echo "=========================================\n";
echo " StockForge - Diagnostik Lingkungan\n";
echo "=========================================\n\n";

step('1. PHP berjalan', PHP_VERSION . ' (' . PHP_SAPI . ')');
step('2. max_execution_time', (string) ini_get('max_execution_time') . ' detik');
step('3. memory_limit', (string) ini_get('memory_limit'));
step('4. default_socket_timeout', (string) ini_get('default_socket_timeout') . ' detik');
step('4b. mysqlnd.net_read_timeout', (string) ini_get('mysqlnd.net_read_timeout') . ' detik');
step('5. SCRIPT_NAME', (string) ($_SERVER['SCRIPT_NAME'] ?? '-'));
step('6. REQUEST_URI', (string) ($_SERVER['REQUEST_URI'] ?? '-'));

echo "\n-- Ekstensi --\n";
foreach (['pdo', 'pdo_mysql', 'mbstring', 'json', 'zip', 'simplexml', 'xmlreader', 'openssl'] as $ext) {
    step('ekstensi ' . $ext, extension_loaded($ext) ? 'ADA' : 'TIDAK ADA');
}

echo "\n-- Xdebug (penyebab umum halaman menggantung) --\n";
if (extension_loaded('xdebug')) {
    step('xdebug', 'ADA  <-- periksa mode');
    step('  xdebug.mode', (string) ini_get('xdebug.mode'));
    step('  xdebug.start_with_request', (string) ini_get('xdebug.start_with_request'));
} else {
    step('xdebug', 'tidak terpasang');
}

echo "\n-- Session --\n";
$savePath = (string) ini_get('session.save_path');
step('session.save_path', $savePath === '' ? '(default sistem)' : $savePath);
if ($savePath !== '' && is_dir($savePath)) {
    step('  writable?', is_writable($savePath) ? 'YA' : 'TIDAK');
}

if (session_status() === PHP_SESSION_ACTIVE) {
    step('session_start', 'sudah aktif');
} else {
    $t = microtime(true);
    $ok = session_start();
    $ms = number_format((microtime(true) - $t) * 1000, 0, '.', '');
    step('session_start()', ($ok ? 'berhasil' : 'GAGAL') . " (+{$ms} ms)");
}

echo "\n-- Database (timeout 3 detik) --\n";
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_DATABASE') ?: 'stockforge';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';

step('host:port', $host . ':' . $port);
step('database', $dbName);
step('username', $user);

// Probe TCP mentah dulu: memisahkan "MySQL belum jalan" dari "handshake macet".
echo "\n-- Cek port MySQL (TCP, timeout 3 detik) --\n";
$t = microtime(true);
$errno = 0;
$errstr = '';
$probe = @fsockopen($host, (int) $port, $errno, $errstr, 3);
$probeMs = number_format((microtime(true) - $t) * 1000, 0, '.', '');

if ($probe !== false) {
    step('port terbuka', "YA (+{$probeMs} ms)");

    // Server MySQL selalu mengirim "greeting packet" begitu koneksi TCP terbuka.
    // Kalau tidak ada yang datang, berarti yang menjawab bukan MySQL, atau
    // MySQL sedang macet (koneksi diterima tapi protokol tidak jalan).
    stream_set_timeout($probe, 3);
    $greeting = (string) fread($probe, 128);

    if ($greeting === '') {
        step('greeting MySQL', 'TIDAK DATANG (timeout)');
        echo "\n>> Port 3306 menerima koneksi, tapi tidak mengirim paket MySQL.\n";
        echo ">> Kemungkinan: service MySQL macet, atau proses lain memakai port 3306.\n";
    } else {
        $serverVersion = '';
        $pos = strpos($greeting, "\x0a");
        if ($pos !== false) {
            $serverVersion = (string) preg_replace('/[^\x20-\x7E]/', '', substr($greeting, $pos + 1, 60));
        }
        step('greeting MySQL', 'DITERIMA: ' . trim($serverVersion));
    }

    fclose($probe);
} else {
    step('port terbuka', "TIDAK (+{$probeMs} ms)");
    echo "\n>> fsockopen: errno={$errno} {$errstr}\n";
    echo ">> MySQL kemungkinan besar BELUM dijalankan. Start 'MySQL' di XAMPP Control Panel.\n";
}

$pdo = null;

if (! extension_loaded('pdo_mysql')) {
    step('koneksi MySQL', 'dilewati: pdo_mysql tidak tersedia');
} else {
    $t = microtime(true);
    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT          => 3,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        step('koneksi MySQL', 'BERHASIL (+' . number_format((microtime(true) - $t) * 1000, 0, '.', '') . ' ms)');
    } catch (Throwable $e) {
        step('koneksi MySQL', 'GAGAL (+' . number_format((microtime(true) - $t) * 1000, 0, '.', '') . ' ms)');
        echo "\n>> " . $e->getMessage() . "\n";
    }
}

if ($pdo instanceof PDO) {
    try {
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        step('jumlah tabel', (string) count($tables));

        $needed = ['groups', 'users', 'items', 'user_groups', 'audits',
                   'discrepancies', 'notifications', 'activity_logs',
                   'locations', 'devices', 'sto', 'import_logs'];
        $missing = array_values(array_diff($needed, $tables));
        step('tabel penting', $missing === [] ? 'lengkap' : 'HILANG: ' . implode(', ', $missing));

        if (in_array('users', $tables, true)) {
            $n = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            step('SELECT COUNT(*) users', (string) $n . ' baris');
        }
    } catch (Throwable $e) {
        step('cek tabel', 'GAGAL');
        echo "\n>> " . $e->getMessage() . "\n";
    }
}

echo "\n=========================================\n";
step('SELESAI', 'total ' . number_format((microtime(true) - $GLOBALS['__t0']) * 1000, 0, '.', '') . ' ms');
echo "=========================================\n";
echo "\nKirimkan seluruh teks di atas, lalu HAPUS file diag.php ini.\n";
