<?php
$file = 'register_page.php';
$content = file_get_contents($file);

$content = str_replace(
    'onchange="seqUnlock(\'desired_role_pref\',\'password\')">',
    'onchange="seqUnlock(\'desired_role_pref\',\'password\')" disabled>',
    $content
);

file_put_contents($file, $content);
echo "Added disabled to desired_role_pref in $file\n";
?>
