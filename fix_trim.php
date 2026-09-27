<?php
$file = 'C:\\xampp\\htdocs\\munyari_church\\print_village_members.php';
$content = file_get_contents($file);
$content = str_replace(
    'if ($m[\'is_village_leader\'] == 1 && $m[\'church_village\'] === $village)',
    'if ($m[\'is_village_leader\'] == 1 && trim($m[\'church_village\']) === trim($village))',
    $content
);
file_put_contents($file, $content);
echo "Success\n";
?>
