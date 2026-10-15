<?php
/**
 * Migration Runner - Load full StockForge bootstrap
 */

declare(strict_types=1);

// Load bootstrap
require_once __DIR__ . '/app/bootstrap.php';

use App\Core\Database;

echo "=== STOCKFORGE MIGRATION ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $pdo = Database::connection();
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
        
        try {
            $pdo->exec($migration['sql']);
            echo "✓\n";
            $success++;
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'Duplicate column') !== false || 
                strpos($msg, 'Duplicate key') !== false ||
                strpos($msg, 'already exists') !== false) {
                echo "⊘ (exists)\n";
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
    
    if ($success > 0) {
        echo "\n✓ Migration berhasil!\n";
    }
    
} catch (Exception $e) {
    echo "✗ FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
