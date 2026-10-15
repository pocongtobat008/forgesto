<?php
/**
 * Temporary Migration Endpoint
 * URL: http://sto.izal.my.id/migrate_columns.php
 * Hapus file ini setelah migration selesai!
 */

header('Content-Type: text/plain; charset=utf-8');

// Security check - hanya bisa diakses dari localhost
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    http_response_code(403);
    die("Access denied. Run from localhost only.\n");
}

chdir(__DIR__);
require_once 'config/database.php';
require_once 'core/Database.php';

try {
    $db = new Core\Database();
    
    echo "=== STOCKFORGE MIGRATION: Add Missing Columns ===\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n\n";
    
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
        try {
            $db->exec($migration['sql']);
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
    }
    
    echo "\n=== SUMMARY ===\n";
    echo "Success: {$success}\n";
    echo "Skipped: {$skipped}\n";
    echo "Failed: {$failed}\n";
    echo "\n✓ Migration selesai!\n";
    echo "\nHAPUS FILE INI SEKARANG: rm " . __FILE__ . "\n";
    
} catch (Exception $e) {
    http_response_code(500);
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
}
