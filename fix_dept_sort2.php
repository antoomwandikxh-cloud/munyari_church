<?php
$case_sql = "CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC";

foreach (['print_all_villages.php', 'print_volunteers.php', 'admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    $old = "ORDER BY church_village, first_name";
    $new = "ORDER BY church_village, $case_sql, first_name";
    
    $c = 0;
    if (strpos($content, $old) !== false) {
        $content = str_replace($old, $new, $content);
        $c++;
        file_put_contents($file, $content);
        echo "Fixed $file ($c replacements)\n";
    }
}
?>
