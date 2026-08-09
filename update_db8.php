<?php
require_once 'db_connect.php';

echo "Updating database schema...\n";

// Create daily_messages table
$sql1 = "CREATE TABLE IF NOT EXISTS daily_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pastor_id INT NOT NULL,
    quote TEXT,
    video_file VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql1)) {
    echo "Successfully created daily_messages table.\n";
} else {
    echo "Error creating daily_messages table: " . $conn->error . "\n";
}

// Create church_highlights table
$sql2 = "CREATE TABLE IF NOT EXISTS church_highlights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pastor_id INT NOT NULL,
    image_file VARCHAR(255) NOT NULL,
    caption VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql2)) {
    echo "Successfully created church_highlights table.\n";
} else {
    echo "Error creating church_highlights table: " . $conn->error . "\n";
}

echo "Database update complete.\n";
?>
