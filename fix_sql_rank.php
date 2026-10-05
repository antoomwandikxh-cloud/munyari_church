<?php
$old_sql_1 = "WHEN LOWER(church_role) REGEXP '(^|, *)(youth |women |elder |sunday school )?(chairman|chairperson|chairlady)( *,|$)' THEN 1";
$new_sql_1 = "WHEN LOWER(church_role) REGEXP '(^|, *)(youths? |womens? |elder |sunday school )?(ministry )?(chairman|chairperson|chairlady)( *,|$)' THEN 1";

$old_sql_2 = "WHEN LOWER(church_role) REGEXP '(^|, *)vice (youth |women |elder |sunday school )?(chairman|chairperson|chairlady)( *,|$)' THEN 2";
$new_sql_2 = "WHEN LOWER(church_role) REGEXP '(^|, *)vice (youths? |womens? |elder |sunday school )?(ministry )?(chairman|chairperson|chairlady)( *,|$)' THEN 2";

foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $content = str_replace($old_sql_1, $new_sql_1, $content);
    $content = str_replace($old_sql_2, $new_sql_2, $content);
    file_put_contents($file, $content);
    echo "Updated SQL in $file\n";
}
?>
