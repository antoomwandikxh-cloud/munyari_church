<?php
$file = 'print_village_members.php';
$content = file_get_contents($file);

$case_sql = "CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC";

$old = "ORDER BY sort_rank ASC, first_name ASC";
$new = "ORDER BY sort_rank ASC, $case_sql, first_name ASC";

$content = str_replace($old, $new, $content);
file_put_contents($file, $content);
echo "Fixed print_village_members.php\n";
?>
