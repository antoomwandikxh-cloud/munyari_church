<?php
require_once 'db_connect.php';

echo "Updating database schema for audit logs...\n";

$sql = "CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_type VARCHAR(50) NOT NULL,
    user_name VARCHAR(100) NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "Successfully created audit_logs table.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

echo "Database update complete.\n";
?>
