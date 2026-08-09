<?php
require_once 'db_connect.php';

echo "Updating database schema for departments and elder announcements...\n";

// 1. Add department column to members (already done)

// 2. Create elder_announcements table
$sql2 = "CREATE TABLE IF NOT EXISTS elder_announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    elder_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql2) === TRUE) {
    echo "Successfully created elder_announcements table.\n";
} else {
    echo "Error creating elder_announcements table: " . $conn->error . "\n";
}

echo "Database update complete.\n";
?>
