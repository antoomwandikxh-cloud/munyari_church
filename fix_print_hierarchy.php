<?php
$file = 'print_all_villages.php';
$content = file_get_contents($file);

// Find the line: $all_rows = array_merge($members_arr, $pastors_this);
$old = '$all_rows = array_merge($members_arr, $pastors_this);';
$new = '$all_rows = array_merge($pastors_this, $members_arr);';

$count = substr_count($content, $old);
if ($count > 0) {
    $content = str_replace($old, $new, $content);
    file_put_contents($file, $content);
    echo "Fixed print_all_villages.php hierarchy\n";
} else {
    echo "Could not find array_merge in print_all_villages.php\n";
}
?>
