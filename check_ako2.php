<?php
require 'db_connect.php';
$res = $conn->query("SELECT first_name, last_name, is_approved FROM members WHERE church_village = 'Akoritho' AND is_village_leader = 1");
while($r = $res->fetch_assoc()){
    echo $r['first_name'] . ' ' . $r['last_name'] . ' | Approved: ' . $r['is_approved'] . "\n";
}
?>
