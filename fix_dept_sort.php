<?php
foreach (['print_all_villages.php', 'admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);

    // There are slightly different old queries.
    // In admin/pastor: ORDER BY is_village_leader DESC, (church_role IS NOT NULL AND TRIM(church_role) != '' AND church_role != 'Member') DESC, department, first_name
    // In print: ORDER BY is_village_leader DESC, (church_role IS NOT NULL AND TRIM(church_role) != '' AND church_role != 'Member') DESC, first_name ASC
    
    $case_sql = "CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC";

    $old1 = "ORDER BY is_village_leader DESC, (church_role IS NOT NULL AND TRIM(church_role) != '' AND church_role != 'Member') DESC, department, first_name";
    $new1 = "ORDER BY is_village_leader DESC, (church_role IS NOT NULL AND TRIM(church_role) != '' AND church_role != 'Member') DESC, $case_sql, first_name ASC";
    
    $old2 = "ORDER BY is_village_leader DESC, (church_role IS NOT NULL AND TRIM(church_role) != '' AND church_role != 'Member') DESC, first_name ASC";
    $new2 = "ORDER BY is_village_leader DESC, (church_role IS NOT NULL AND TRIM(church_role) != '' AND church_role != 'Member') DESC, $case_sql, first_name ASC";
    
    $c = 0;
    if (strpos($content, $old1) !== false) {
        $content = str_replace($old1, $new1, $content);
        $c++;
    } elseif (strpos($content, $old2) !== false) {
        $content = str_replace($old2, $new2, $content);
        $c++;
    }

    file_put_contents($file, $content);
    echo "Fixed $file ($c replacements)\n";
}
?>
