<?php
require 'db_connect.php';
$res = $conn->query("SELECT first_name, last_name, church_role, department, is_approved FROM members WHERE is_approved=1 ORDER BY department, first_name");
while ($m = $res->fetch_assoc()) {
    echo "[{$m['department']}] {$m['first_name']} {$m['last_name']} | role: [{$m['church_role']}]\n";
}
?>
