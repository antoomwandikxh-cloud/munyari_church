<?php
$file = 'print_all_villages.php';
$content = file_get_contents($file);

$old = '$all_rows = array_merge($pastors_this, $members_arr);';
$new = '
    $leader_arr = [];
    $reg_members = [];
    foreach ($members_arr as $m) {
        if (!empty($m[\'is_village_leader\'])) {
            $leader_arr[] = $m;
        } else {
            $reg_members[] = $m;
        }
    }
    $all_rows = array_merge($leader_arr, $pastors_this, $reg_members);
';

$content = str_replace($old, $new, $content);
file_put_contents($file, $content);
echo "Fixed print_all_villages.php\n";
?>
