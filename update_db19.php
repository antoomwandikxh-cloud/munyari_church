<?php
require_once 'db_connect.php';

$sql = "CREATE TABLE IF NOT EXISTS graduation_list (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT(6) UNSIGNED NOT NULL,
    department VARCHAR(50) NOT NULL,
    graduation_year INT(4) NOT NULL,
    added_by INT(6) UNSIGNED NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table graduation_list created successfully.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

$conn->close();
?>
