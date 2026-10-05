<?php
require 'db_connect.php';
$village = 'Akoritho';
$safe_village = $conn->real_escape_string(trim($village));
$leader_q = $conn->query("SELECT * FROM members WHERE TRIM(church_village) = '$safe_village' AND is_village_leader = 1 AND is_approved = 1 LIMIT 1");
$leader = $leader_q ? $leader_q->fetch_assoc() : null;
echo "Leader: " . ($leader ? $leader['first_name'].' '.$leader['last_name'] : 'NULL') . "\n";
if ($leader) {
    echo "Pic field: " . $leader['profile_picture'] . "\n";
    $paths = [
        'uploads/'.$leader['profile_picture'],
        'uploads/'.basename($leader['profile_picture']),
        $leader['profile_picture'],
    ];
    foreach ($paths as $p) {
        echo "  Path '$p' exists: " . (file_exists($p) ? 'YES' : 'NO') . "\n";
    }
}
?>
