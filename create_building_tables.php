<?php
require_once 'db_connect.php';

$sql1 = "CREATE TABLE IF NOT EXISTS building_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chairperson_id INT(6) UNSIGNED NOT NULL,
    date_scheduled DATE NOT NULL,
    materials_needed TEXT NOT NULL,
    progress_status VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chairperson_id) REFERENCES members(id) ON DELETE CASCADE
)";
if ($conn->query($sql1)) { echo "building_progress table created.\n"; } else { echo "Error: " . $conn->error . "\n"; }

$sql2 = "CREATE TABLE IF NOT EXISTS building_leadership_chat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT(6) UNSIGNED NOT NULL,
    sender_type ENUM('member', 'pastor') NOT NULL DEFAULT 'member',
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql2)) { echo "building_leadership_chat table created.\n"; } else { echo "Error: " . $conn->error . "\n"; }

$sql3 = "CREATE TABLE IF NOT EXISTS building_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    author_id INT(6) UNSIGNED NOT NULL,
    post_content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES members(id) ON DELETE CASCADE
)";
if ($conn->query($sql3)) { echo "building_posts table created.\n"; } else { echo "Error: " . $conn->error . "\n"; }

// Seed roles updates: Rename 'Chairman' to 'Chairperson' where they exist in DB
$conn->query("UPDATE members SET church_role = REPLACE(church_role, 'Building Chairman', 'Building Chairperson') WHERE church_role LIKE '%Building Chairman%'");

echo "Database updates complete.\n";
?>
