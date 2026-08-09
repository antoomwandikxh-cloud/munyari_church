<?php
require_once 'db_connect.php';

echo "Updating database schema for department transfer requests...\n";

// Add pending_department column to members
$sql = "ALTER TABLE members ADD COLUMN pending_department VARCHAR(50) DEFAULT NULL AFTER department";
if ($conn->query($sql) === TRUE) {
    echo "Successfully added 'pending_department' column to members table.\n";
} else {
    echo "Notice: (May already exist) " . $conn->error . "\n";
}

echo "Database update complete.\n";
?>
