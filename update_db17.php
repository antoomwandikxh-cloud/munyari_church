<?php
require_once 'db_connect.php';

$sql = "CREATE TABLE IF NOT EXISTS organized_events (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department VARCHAR(255) NOT NULL,
    organizer_id INT(6) UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    announcement TEXT NOT NULL,
    meet_link VARCHAR(255) DEFAULT NULL,
    whatsapp_link VARCHAR(255) DEFAULT NULL,
    attendance_file VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table organized_events created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>
