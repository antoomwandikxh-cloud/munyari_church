<?php
require_once 'db_connect.php';

$sql = "SHOW COLUMNS FROM pastors LIKE 'password'";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE pastors ADD COLUMN password VARCHAR(255) NOT NULL AFTER phone");
    // Set a default password for any existing pastors (e.g. 'pastor123')
    $default_hash = password_hash('pastor123', PASSWORD_DEFAULT);
    $conn->query("UPDATE pastors SET password = '$default_hash'");
    echo "Added password column to pastors.<br>";
} else {
    echo "Password column already exists.<br>";
}
$conn->close();
?>
