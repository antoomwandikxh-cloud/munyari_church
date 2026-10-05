<?php
$file = 'print_all_villages.php';
$content = file_get_contents($file);

$old_order = "ORDER BY is_village_leader DESC, first_name ASC";
$new_order = "ORDER BY is_village_leader DESC, (church_role IS NOT NULL AND TRIM(church_role) != '' AND church_role != 'Member') DESC, first_name ASC";

$content = str_replace($old_order, $new_order, $content);
file_put_contents($file, $content);
echo "Fixed print_all_villages.php\n";
?>
