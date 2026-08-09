<?php
require_once 'db_connect.php';

// Add password column to members if it doesn't exist
$sql = "SHOW COLUMNS FROM members LIKE 'password'";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE members ADD COLUMN password VARCHAR(255) NOT NULL AFTER address");
    echo "Added password column to members.<br>";
}

// Create member_messages table
$sql_msg = "CREATE TABLE IF NOT EXISTS member_messages (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT(6) UNSIGNED,
    pastor_name VARCHAR(100),
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql_msg) === TRUE) {
    echo "Table member_messages created successfully.<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Create financial_records table
$sql_fin = "CREATE TABLE IF NOT EXISTS financial_records (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pastor_id INT(6) UNSIGNED,
    department VARCHAR(100),
    amount DECIMAL(10,2),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql_fin) === TRUE) {
    echo "Table financial_records created successfully.<br>";
} else {
    echo "Error creating financial_records table: " . $conn->error . "<br>";
}

$conn->close();
?>
