<?php
require_once 'db_connect.php';

// Add pending_role column
$result = $conn->query("SHOW COLUMNS FROM members LIKE 'pending_role'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE members ADD COLUMN pending_role VARCHAR(255) DEFAULT NULL");
    echo "Added pending_role column to members table.<br>";
}

echo "Database update 16 complete!";
$conn->close();
?>
