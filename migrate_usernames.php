<?php
// migrate_usernames.php
@ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/db_connect.php';

function generateUniqueUsername($conn, $base_name) {
    // Keep only alphanumeric and underscores
    $base = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower(trim($base_name)));
    if (empty($base)) $base = 'user';
    
    $username = $base;
    $counter = 1;
    
    while (true) {
        // Check uniqueness in both tables
        $chk1 = $conn->query("SELECT id FROM members WHERE username = '$username'");
        $chk2 = $conn->query("SELECT id FROM pastors WHERE username = '$username'");
        $chk3 = $conn->query("SELECT id FROM admins WHERE username = '$username'");
        
        if ($chk1->num_rows == 0 && $chk2->num_rows == 0 && $chk3->num_rows == 0) {
            return $username;
        }
        $username = $base . $counter;
        $counter++;
    }
}

echo "Migrating Members...\n";
$res = $conn->query("SELECT id, first_name FROM members WHERE username IS NULL OR username = ''");
while ($row = $res->fetch_assoc()) {
    $uname = generateUniqueUsername($conn, $row['first_name']);
    $conn->query("UPDATE members SET username = '$uname' WHERE id = " . $row['id']);
    echo "Member ID {$row['id']} set to $uname\n";
}

echo "\nMigrating Pastors...\n";
$res = $conn->query("SELECT id, first_name FROM pastors WHERE username IS NULL OR username = ''");
while ($row = $res->fetch_assoc()) {
    $uname = generateUniqueUsername($conn, $row['first_name']);
    $conn->query("UPDATE pastors SET username = '$uname' WHERE id = " . $row['id']);
    echo "Pastor ID {$row['id']} set to $uname\n";
}

echo "\nMigration Complete!\n";
?>
