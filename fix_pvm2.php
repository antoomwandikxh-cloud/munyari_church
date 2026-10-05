<?php
$f = 'C:\xampp\htdocs\munyari_church\print_village_members.php';
$c = file_get_contents($f);

// Add default_pic_b64 definition right after l_pic_b64
$old = "\$l_pic_b64 = '';\r\nif (\$leader && !empty(\$leader['profile_picture'])) {";
$new = "\$l_pic_b64 = '';\r\n\$default_pic_b64 = '';\r\nif (file_exists('uploads/default_avatar.png')) {\r\n    \$default_pic_b64 = \"data:image/png;base64,\" . base64_encode(file_get_contents('uploads/default_avatar.png'));\r\n}\r\nif (\$leader && !empty(\$leader['profile_picture'])) {";

if (strpos($c, $old) !== false) {
    $c = str_replace($old, $new, $c);
} else {
    // try LF
    $old = "\$l_pic_b64 = '';\nif (\$leader && !empty(\$leader['profile_picture'])) {";
    $new = "\$l_pic_b64 = '';\n\$default_pic_b64 = '';\nif (file_exists('uploads/default_avatar.png')) {\n    \$default_pic_b64 = \"data:image/png;base64,\" . base64_encode(file_get_contents('uploads/default_avatar.png'));\n}\nif (\$leader && !empty(\$leader['profile_picture'])) {";
    $c = str_replace($old, $new, $c);
}

file_put_contents($f, $c);
echo "Added default_pic_b64 to print_village_members.php\n";
?>
