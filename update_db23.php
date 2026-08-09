<?php
require_once 'db_connect.php';

$roles = [
    'General Church Secretary',
    'Vice Church Secretary',
    'Treasurer',
    'Church Elder',
    'Head Usher',
    'Usher',
    'Building Chairman',
    'Vice Building Chairman',
    'Building Secretary',
    'Vice Building Secretary',
    'Building Treasurer',
    'Youth Chairman',
    'Vice Youth Chairman',
    'Youth Secretary',
    'Vice Youth Secretary',
    'Youth Treasurer',
    'Mama Youth',
    'Baba Youth',
    'Women Chairlady',
    'Vice Women Chairlady',
    'Women Secretary',
    'Vice Women Secretary',
    'Women Treasurer',
    'Elder Chairman',
    'Vice Elder Chairman',
    'Elder Secretary',
    'Vice Elder Secretary',
    'Elder Treasurer',
    'Sunday School Patron',
    'Vice Sunday School Patron',
    'Sunday School Secretary',
    'Vice Sunday School Secretary',
    'Sunday School Treasurer',
];

foreach ($roles as $role) {
    $safe_role = $conn->real_escape_string($role);
    $conn->query("INSERT IGNORE INTO church_roles (role_name) VALUES ('$safe_role')");
}

echo "Database update 23 complete. Full department role hierarchy seeded.";
?>
