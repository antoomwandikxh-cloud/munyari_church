<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php', 'print_all_villages.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // Restore the ORDER BY department logic
    $bad_q = "ORDER BY is_village_leader DESC, (church_role IS NOT NULL AND TRIM(church_role) != '' AND church_role != 'Member') DESC, first_name ASC";
    $good_q = "ORDER BY is_village_leader DESC, (church_role IS NOT NULL AND TRIM(church_role) != '' AND church_role != 'Member') DESC, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, first_name ASC";
    
    if (strpos($c, $bad_q) !== false) {
        $c = str_replace($bad_q, $good_q, $c);
        file_put_contents($file, $c);
        echo "Restored sorting in $file\n";
    }
}

$f2 = 'print_village_members.php';
$c2 = file_get_contents($f2);
$bad_q2 = "ORDER BY sort_rank ASC, first_name ASC";
$good_q2 = "ORDER BY sort_rank ASC, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, first_name ASC";
if (strpos($c2, $bad_q2) !== false) {
    $c2 = str_replace($bad_q2, $good_q2, $c2);
    file_put_contents($f2, $c2);
    echo "Restored sorting in $f2\n";
}
?>
