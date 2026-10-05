<?php
require 'db_connect.php';
$res = $conn->query("SELECT first_name, last_name, church_role, is_village_leader FROM members WHERE church_village = 'Akoritho'");
while($r = $res->fetch_assoc()){
    echo $r['first_name'] . ' ' . $r['last_name'] . ' | Role: ' . $r['church_role'] . ' | Leader: ' . $r['is_village_leader'] . "\n";
}
?>
