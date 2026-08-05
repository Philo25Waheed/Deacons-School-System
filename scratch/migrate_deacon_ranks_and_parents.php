<?php

// Migration: Add Address, Father/Mother Info, and Deacon Rank to Users
require_once __DIR__.'/../config/database.php';

try {
    $db = getDB();

    $columns = [
        'ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `address` VARCHAR(255) DEFAULT NULL;',
        'ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `father_name` VARCHAR(150) DEFAULT NULL;',
        'ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `father_phone` VARCHAR(20) DEFAULT NULL;',
        'ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `mother_name` VARCHAR(150) DEFAULT NULL;',
        'ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `mother_phone` VARCHAR(20) DEFAULT NULL;',
        "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `deacon_rank` VARCHAR(100) DEFAULT 'إبصالتس (مرتل)';",
    ];

    foreach ($columns as $q) {
        try {
            $db->exec($q);
        } catch (Exception $e) {
            // Ignore if column exists
        }
    }

    echo "Columns added successfully!\n";

} catch (Exception $e) {
    echo 'Migration Error: '.$e->getMessage()."\n";
}
