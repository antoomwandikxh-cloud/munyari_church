<?php
$file = 'member_dashboard.php';
$content = file_get_contents($file);

// Find the block rendering desired_role_pref
$pattern = '/\$roles = \[\'Worshipper\', \'Church Cleaner\', \'Church Cooker\'\];/';
$replacement = '$roles = [];';

$content = preg_replace($pattern, $replacement, $content);

file_put_contents($file, $content);
echo "Updated $file\n";
?>
