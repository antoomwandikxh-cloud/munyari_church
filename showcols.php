<?php
require 'db_connect.php';
$res = $conn->query("SHOW COLUMNS FROM members");
while($r = $res->fetch_assoc()) echo $r['Field'] . "\n";
?>
