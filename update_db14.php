<?php
require_once 'db_connect.php';

$conn->query("ALTER TABLE members ADD COLUMN gender ENUM('Male', 'Female') DEFAULT 'Male'");
echo "Added gender column to members table.\n";
?>
