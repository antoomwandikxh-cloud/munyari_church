<?php
$file = 'C:\\xampp\\htdocs\\munyari_church\\print_village_members.php';
$content = file_get_contents($file);

$content = str_replace(
    'htmlspecialchars($m[\'department\'])',
    'htmlspecialchars($m[\'department\'] ?? \'\')',
    $content
);
$content = str_replace(
    'htmlspecialchars($m[\'church_role\'])',
    'htmlspecialchars($m[\'church_role\'] ?? \'\')',
    $content
);

file_put_contents($file, $content);
echo "Success\n";
?>
