<?php
require_once 'db_connect.php';

echo "Seeding church roles...\n";

$roles = [
    'Youth Chairman',
    'Women Chairlady',
    'Elder Chairman',
    'Sunday School Patron'
];

foreach ($roles as $role) {
    $role_esc = $conn->real_escape_string($role);
    $check = $conn->query("SELECT * FROM church_roles WHERE role_name = '$role_esc'");
    if ($check->num_rows == 0) {
        $conn->query("INSERT INTO church_roles (role_name) VALUES ('$role_esc')");
        echo "Inserted role: $role\n";
    } else {
        echo "Role already exists: $role\n";
    }
}

echo "Database update complete.\n";
?>
