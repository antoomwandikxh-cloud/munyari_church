<?php
$f = 'C:\xampp\htdocs\munyari_church\print_village_members.php';
$c = file_get_contents($f);

// Remove the sort_rank from SELECT and ORDER BY
$old_select = "SELECT id, first_name, last_name, phone, address, department, church_role, profile_picture, is_village_leader,
    CASE
        WHEN is_village_leader = 1 THEN 1
        WHEN church_role IS NOT NULL AND church_role != '' AND LOWER(church_role) != 'member' THEN 2
        ELSE 99
    END AS sort_rank
    FROM members
    WHERE is_approved = 1 AND church_village = '\$safe_village'
    ORDER BY sort_rank ASC, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, first_name ASC";

$new_select = "SELECT id, first_name, last_name, phone, address, department, church_role, profile_picture, is_village_leader
    FROM members
    WHERE is_approved = 1 AND church_village = '\$safe_village'
    ORDER BY is_village_leader DESC, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, first_name ASC";

// The line breaks might not perfectly match, let's use regex or str_replace for smaller chunks

// First replace the ORDER BY
$old_order = "ORDER BY sort_rank ASC, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, first_name ASC";
$new_order = "ORDER BY is_village_leader DESC, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, first_name ASC";
$c = str_replace($old_order, $new_order, $c);

file_put_contents($f, $c);
echo "Fixed print_village_members.php\n";
?>
