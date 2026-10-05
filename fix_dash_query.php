<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // Original line:
    // $v_members_res = $conn->query("SELECT first_name, last_name, department, church_role, phone, desired_role_pref, profile_picture, church_village, is_village_leader, 'Member' AS person_type FROM members WHERE church_village='$v' ORDER BY is_village_leader DESC, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, first_name ASC");

    // Let's use preg_replace for safety
    $pattern = "/SELECT first_name, last_name, department, church_role, phone, desired_role_pref, profile_picture, church_village, is_village_leader, 'Member' AS person_type FROM members WHERE church_village='\\\$v' ORDER BY is_village_leader DESC, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, first_name ASC/s";
    
    $replacement = "SELECT first_name, last_name, department, church_role, phone, desired_role_pref, profile_picture, church_village, is_village_leader, 'Member' AS person_type, \" . role_rank_case_sql('church_role') . \" AS role_rank FROM members WHERE church_village='\$v' ORDER BY is_village_leader DESC, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, role_rank ASC, first_name ASC";
    
    $c = preg_replace($pattern, $replacement, $c);
    
    file_put_contents($file, $c);
    echo "Fixed query in $file\n";
}
?>
