<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // For the on-screen Church Villages tab
    $old_q = "ORDER BY is_village_leader DESC, (church_role IS NOT NULL AND TRIM(church_role) != '' AND church_role != 'Member') DESC, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, first_name ASC";
    $new_q = "ORDER BY is_village_leader DESC, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, first_name ASC";
    
    if (strpos($c, $old_q) !== false) {
        $c = str_replace($old_q, $new_q, $c);
        file_put_contents($file, $c);
        echo "Fixed query in $file\n";
    }
}
?>
