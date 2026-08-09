<?php
require_once 'db_connect.php';

echo "Setting up Secretary features...\n";

// Create announcements table
$sql = "CREATE TABLE IF NOT EXISTS department_announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department VARCHAR(100) NOT NULL,
    secretary_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql) === TRUE) {
    echo "department_announcements table created successfully.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

// Seed Secretary Roles
$roles = [
    'Youth Secretary', 'Vice Youth Secretary',
    'Women Secretary', 'Vice Women Secretary',
    'Elder Secretary', 'Vice Elder Secretary',
    'Sunday School Secretary', 'Vice Sunday School Secretary',
    'Mens Ministry Secretary', 'Vice Mens Ministry Secretary'
];

foreach ($roles as $role) {
    $role_esc = $conn->real_escape_string($role);
    $check = $conn->query("SELECT * FROM church_roles WHERE role_name = '$role_esc'");
    if ($check->num_rows == 0) {
        $conn->query("INSERT INTO church_roles (role_name) VALUES ('$role_esc')");
        echo "Inserted role: $role\n";
    }
}

echo "Database update complete.\n";
?>
