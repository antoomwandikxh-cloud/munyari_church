<?php
require_once 'C:\\xampp\\htdocs\\munyari_church\\db_connect.php';

// 1. Add sunday_school_class column to members table
$conn->query("ALTER TABLE members ADD COLUMN IF NOT EXISTS sunday_school_class ENUM('Battalion','Conquerors','Little Angels') NULL DEFAULT NULL");
echo "Column added (or already exists).\n";

echo "Migration done.\n";
?>
