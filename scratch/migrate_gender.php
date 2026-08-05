<?php

// Migration: Add gender column to users
require_once __DIR__.'/../config/database.php';

try {
    $db = getDB();
    $db->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `gender` ENUM('male', 'female') DEFAULT 'male';");
    echo "Gender column added successfully!\n";
} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
}
