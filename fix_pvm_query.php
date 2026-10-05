<?php
require 'role_departments.php'; // Just checking it exists

$files = ['print_village_members.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // Replace SELECT
    $old_select = "SELECT id, first_name, last_name, phone, address, department, church_role, profile_picture, is_village_leader\n    FROM members\n    WHERE is_approved = 1 AND church_village = '\$safe_village'\n    ORDER BY is_village_leader DESC, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, first_name ASC";
    
    $new_select = "SELECT id, first_name, last_name, phone, address, department, church_role, profile_picture, is_village_leader, \" . role_rank_case_sql('church_role') . \" AS role_rank\n    FROM members\n    WHERE is_approved = 1 AND church_village = '\$safe_village'\n    ORDER BY is_village_leader DESC, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, role_rank ASC, first_name ASC";
    
    $c = str_replace($old_select, $new_select, $c);
    
    // Fallback if formatting varied:
    if (strpos($c, "AS role_rank") === false) {
        // Regex replace
        $c = preg_replace(
            "/SELECT id, first_name, last_name, phone, address, department, church_role, profile_picture, is_village_leader\s*FROM members\s*WHERE is_approved = 1 AND church_village = '\\\$safe_village'\s*ORDER BY is_village_leader DESC, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, first_name ASC/s",
            "SELECT id, first_name, last_name, phone, address, department, church_role, profile_picture, is_village_leader, \" . role_rank_case_sql('church_role') . \" AS role_rank FROM members WHERE is_approved = 1 AND church_village = '\$safe_village' ORDER BY is_village_leader DESC, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, role_rank ASC, first_name ASC",
            $c
        );
    }
    
    file_put_contents($file, $c);
    echo "Fixed $file\n";
}
?>
