<?php
$old_php_main = '                                            if (!empty($leader_labels)):
                                                $leader_label = implode(\', \', $leader_labels);';

$new_php_main = '                                            if (!empty($leader_labels)):
                                                $leader_label = $leader_labels[0]; // Only show the SINGLE most relevant role';

$old_php_dept = '                                                            $leader_label = !empty($leader_labels) ? implode(\', \', $leader_labels) : strtoupper($dept . \' LEADER\');';

$new_php_dept = '                                                            $leader_label = !empty($leader_labels) ? $leader_labels[0] : strtoupper($dept . \' LEADER\');';

foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    $count1 = substr_count($content, $old_php_main);
    if ($count1 > 0) $content = str_replace($old_php_main, $new_php_main, $content);
    
    $count2 = substr_count($content, $old_php_dept);
    if ($count2 > 0) $content = str_replace($old_php_dept, $new_php_dept, $content);
    
    file_put_contents($file, $content);
    echo "Updated bracket slicing in $file ($count1 main matches, $count2 dept matches)\n";
}
?>
