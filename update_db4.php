<?php
require_once 'db_connect.php';

// Add church_role to members
$sql = "SHOW COLUMNS FROM members LIKE 'church_role'";
if ($conn->query($sql)->num_rows == 0) {
    $conn->query("ALTER TABLE members ADD COLUMN church_role VARCHAR(100) DEFAULT 'Member' AFTER address");
    echo "Added church_role column.<br>";
}

// Create appointments table
$sql_app = "CREATE TABLE IF NOT EXISTS appointments (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT(6) UNSIGNED,
    pastor_id INT(6) UNSIGNED,
    appointment_date DATETIME,
    reason TEXT,
    status ENUM('Pending', 'Approved', 'Declined', 'Completed') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql_app);

// Create notifications table
$sql_notif = "CREATE TABLE IF NOT EXISTS notifications (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED,
    user_type ENUM('member', 'pastor', 'admin'),
    message TEXT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql_notif);

echo "Database updated successfully.<br>";
$conn->close();
?>
