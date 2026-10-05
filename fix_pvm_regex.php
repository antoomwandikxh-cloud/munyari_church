<?php
$f = 'C:\xampp\htdocs\munyari_church\print_village_members.php';
$c = file_get_contents($f);

$pattern = "/\\\$v_mems_q = \\\$conn->query\(\"(.*?)\"\);/s";
$replacement = "\$v_mems_q = \$conn->query(\"
    SELECT id, first_name, last_name, phone, address, department, church_role, profile_picture, is_village_leader, \" . role_rank_case_sql('church_role') . \" AS role_rank
    FROM members
    WHERE is_approved = 1 AND TRIM(church_village) = '\$safe_village'
    ORDER BY is_village_leader DESC, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, role_rank ASC, first_name ASC
\");";

$c = preg_replace($pattern, $replacement, $c, 1);
file_put_contents($f, $c);
echo "Fixed regex in print_village_members.php\n";
?>
