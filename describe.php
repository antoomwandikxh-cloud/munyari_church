<?php
require "db_connect.php";
$res = $conn->query("DESCRIBE members");
echo "MEMBERS:\n";
while($r = $res->fetch_assoc()) { echo $r["Field"] . "\n"; }
$res = $conn->query("DESCRIBE pastors");
echo "\nPASTORS:\n";
while($r = $res->fetch_assoc()) { echo $r["Field"] . "\n"; }
$res = $conn->query("DESCRIBE admins");
echo "\nADMINS:\n";
while($r = $res->fetch_assoc()) { echo $r["Field"] . "\n"; }
?>
