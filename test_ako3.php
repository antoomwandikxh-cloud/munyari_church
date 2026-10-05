<?php
require 'db_connect.php';
$village = 'Akoritho';
$safe_village = $conn->real_escape_string($village);
$leader_q = $conn->query("SELECT * FROM members WHERE TRIM(church_village) = '$safe_village' AND is_village_leader = 1 AND is_approved = 1 LIMIT 1");
if ($leader_q && $leader_q->num_rows > 0) {
    $leader = $leader_q->fetch_assoc();
    echo "Found: " . $leader['first_name'] . ' ' . $leader['last_name'];
} else {
    echo "NOT FOUND";
}
?>
