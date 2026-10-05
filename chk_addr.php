<?php
require 'db_connect.php';
$res = $conn->query("SELECT address FROM members WHERE church_village='Akoritho' LIMIT 3");
while($r = $res->fetch_assoc()) echo "Address: [" . $r['address'] . "]\n";
?>
