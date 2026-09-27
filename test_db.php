<?php
require 'db_connect.php';
$m = $conn->query('SELECT id, is_village_leader, church_village FROM members WHERE is_village_leader = 1 LIMIT 1')->fetch_assoc();
print_r($m);
?>
