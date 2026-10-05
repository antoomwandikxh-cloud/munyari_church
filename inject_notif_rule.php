<?php
$f = 'C:\xampp\htdocs\munyari_church\notification_badges.php';
$c = file_get_contents($f);
$new_rule = "\n    if (strpos(\$msg, 'new worshipper') !== false && strpos(\$msg, 'has joined') !== false) { \$tabs[] = 'manage_worshippers'; }\n\n    return array_values(array_unique(\$tabs));\n}";
$c = str_replace("return array_values(array_unique(\$tabs));\n}", $new_rule, $c);
file_put_contents($f, $c);
echo "Updated notification_badges.php\n";
?>
