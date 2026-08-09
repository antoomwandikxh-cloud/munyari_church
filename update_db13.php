<?php
require_once 'db_connect.php';

$new_roles = [
    'General Church Secretary', 'Vice Church Secretary', 'Treasurer', 'Church Elder',
    'Youth Treasurer', 'Women Treasurer', 'Elder Treasurer', 'Sunday School Treasurer', 'Mens Ministry Treasurer'
];

foreach ($new_roles as $role) {
    $role_esc = $conn->real_escape_string($role);
    $check = $conn->query("SELECT * FROM church_roles WHERE role_name = '$role_esc'");
    if ($check->num_rows == 0) {
        $conn->query("INSERT INTO church_roles (role_name) VALUES ('$role_esc')");
        echo "Inserted role: $role\n";
    }
}

echo "Role seeding complete.\n";
?>
