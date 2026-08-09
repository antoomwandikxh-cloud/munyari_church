<?php
require_once 'db_connect.php';

$sql = "CREATE TABLE IF NOT EXISTS church_roles (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(100) UNIQUE NOT NULL
)";
$conn->query($sql);

$default_roles = [
    'Standard Member',
    'Church Elder',
    'Secretary',
    'Treasurer',
    'Youth Chairman',
    'Worship Leader'
];

foreach ($default_roles as $role) {
    $conn->query("INSERT IGNORE INTO church_roles (role_name) VALUES ('$role')");
}

echo "Created church_roles table and inserted defaults.<br>";
$conn->close();
?>
