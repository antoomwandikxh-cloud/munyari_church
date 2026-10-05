<?php
require 'db_connect.php';
// Simulate what happens in admin_dashboard
$v = 'Akoritho';
$v_color = 'red';
$v_members_res = $conn->query("SELECT first_name, last_name, department, church_role, phone, desired_role_pref, profile_picture, church_village, is_village_leader, 'Member' AS person_type FROM members WHERE church_village='$v' ORDER BY is_village_leader DESC");
$v_leaders = [];
while ($row = $v_members_res->fetch_assoc()) {
    if (!empty($row['is_village_leader'])) {
        $v_leaders[] = $row;
    }
}
echo "Leader count: " . count($v_leaders) . "\n";
if(!empty($v_leaders[0])) {
    echo "Leader name: " . $v_leaders[0]['first_name'] . ' ' . $v_leaders[0]['last_name'] . "\n";
    echo "Pic: " . $v_leaders[0]['profile_picture'] . "\n";
}
?>
