<?php
/**
 * StockForge - Migration: Tambah kolom untuk audit lengkap
 * URL: http://sto.izal.my.id/run_migration.php
 * HAPUS FILE INI SETELAH SELESAI!
 */

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

echo "=== STOCKFORGE MIGRATION ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Koneksi database (sama dengan diag.php)
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_DATABASE') ?: 'stockforge';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';

echo "Connecting to: {$user}@{$host}:{$port}/{$dbName}\n\n";

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    
    echo "✓ Database connected!\n\n";
    
    $migrations = [
        [
            'name' => 'items.sub_inventory',
            'sql' => "ALTER TABLE items ADD COLUMN sub_inventory VARCHAR(50) DEFAULT NULL COMMENT 'Sub Inventory' AFTER category"
        ],
        [
            'name' => 'locations.group_code',
            'sql' => "ALTER TABLE locations ADD COLUMN group_code VARCHAR(20) DEFAULT NULL COMMENT 'Group lokasi' AFTER code"
        ],
        [
            'name' => 'audits.observer_id',
            'sql' => "ALTER TABLE audits ADD COLUMN observer_id INT DEFAULT NULL COMMENT 'User ID Observer' AFTER auditor_id"
        ],
        [
            'name' => 'audits.device_name',
            'sql' => "ALTER TABLE audits ADD COLUMN device_name VARCHAR(100) DEFAULT NULL COMMENT 'Nama Tablet' AFTER observer_id"
        ],
        [
            'name' => 'fk_audits_observer',
            'sql' => "ALTER TABLE audits ADD CONSTRAINT fk_audits_observer FOREIGN KEY (observer_id) REFERENCES users(id) ON DELETE SET NULL"
        ],
        [
            'name' => 'idx_items_sub_inventory',
            'sql' => "CREATE INDEX idx_items_sub_inventory ON items(sub_inventory)"
        ],
        [
            'name' => 'idx_locations_group_code',
            'sql' => "CREATE INDEX idx_locations_group_code ON locations(group_code)"
        ],
        [
            'name' => 'idx_audits_observer',
            'sql' => "CREATE INDEX idx_audits_observer ON audits(observer_id)"
        ]
    ];
    
    $success = 0;
    $skipped = 0;
    $failed = 0;
    
    foreach ($migrations as $migration) {
        echo "[{$migration['name']}] ";
        flush();
        
        try {
            $pdo->exec($migration['sql']);
            echo "✓ SUCCESS\n";
            $success++;
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'Duplicate column') !== false || 
                strpos($msg, 'Duplicate key') !== false ||
                strpos($msg, 'already exists') !== false) {
                echo "⊘ SKIPPED (already exists)\n";
                $skipped++;
            } else {
                echo "✗ ERROR: {$msg}\n";
                $failed++;
            }
        }
        flush();
    }
    
    echo "\n=== SUMMARY ===\n";
    echo "Success: {$success}\n";
    echo "Skipped: {$skipped}\n";
    echo "Failed: {$failed}\n";
    
    if ($success > 0) {
        echo "\n✓ Migration berhasil!\n";
        echo "\nHAPUS FILE INI SEKARANG:\n";
        echo "rm /www/wwwroot/sto.izal.my.id/run_migration.php\n";
    }
    
} catch (PDOException $e) {
    echo "✗ FATAL ERROR: " . $e->getMessage() . "\n";
    http_response_code(500);
}
