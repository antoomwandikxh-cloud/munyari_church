<?php
require_once 'db_connect.php';

$sql1 = "CREATE TABLE IF NOT EXISTS fine_templates (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department VARCHAR(255) NOT NULL,
    fine_name VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$sql2 = "CREATE TABLE IF NOT EXISTS member_fines (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT(6) UNSIGNED NOT NULL,
    discipline_master_id INT(6) UNSIGNED NOT NULL,
    department VARCHAR(255) NOT NULL,
    fine_reason VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('Unpaid', 'Paid') DEFAULT 'Unpaid',
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL DEFAULT NULL
)";

$sql3 = "CREATE TABLE IF NOT EXISTS member_reports (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reported_member_id INT(6) UNSIGNED NOT NULL,
    reported_by_id INT(6) UNSIGNED NOT NULL,
    department VARCHAR(255) NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('Pending Chairman', 'Forwarded to Pastor', 'Resolved') DEFAULT 'Pending Chairman',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$success = true;

if ($conn->query($sql1) !== TRUE) { echo "Error fine_templates: " . $conn->error . "<br>"; $success = false; }
if ($conn->query($sql2) !== TRUE) { echo "Error member_fines: " . $conn->error . "<br>"; $success = false; }
if ($conn->query($sql3) !== TRUE) { echo "Error member_reports: " . $conn->error . "<br>"; $success = false; }

if ($success) {
    echo "Database update 18 complete! Tables fine_templates, member_fines, and member_reports created.";
}

$conn->close();
?>
