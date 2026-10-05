<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);

    $old_order = "ORDER BY is_village_leader DESC, department, first_name";
    $new_order = "ORDER BY is_village_leader DESC, (church_role IS NOT NULL AND TRIM(church_role) != '' AND church_role != 'Member') DESC, department, first_name";

    $content = str_replace($old_order, $new_order, $content);
    file_put_contents($file, $content);
    echo "Fixed $file\n";
}
?>
