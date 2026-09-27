<?php
$file = "C:\\xampp\\htdocs\\munyari_church\\notification_badges.php";
$content = file_get_contents($file);

// Add village announcement routing after the existing village leader routing
$old = "    if (strpos(\$msg, 'assigned as the church village leader') !== false) {
        \$tabs[] = 'manage_church_village';
    }
    if (strpos(\$msg, 'a new member has been added in') !== false) {
        if (\$user_type === 'member') {
            \$tabs[] = 'manage_church_village';
        } else {
            \$tabs[] = 'desired_roles';
        }
        \$tabs[] = 'departments';
    }";

$new = "    if (strpos(\$msg, 'assigned as the church village leader') !== false) {
        \$tabs[] = 'manage_church_village';
    }
    if (strpos(\$msg, 'a new member has been added in') !== false) {
        if (\$user_type === 'member') {
            \$tabs[] = 'manage_church_village';
        } else {
            \$tabs[] = 'desired_roles';
        }
        \$tabs[] = 'departments';
    }
    // Village announcement notification -> desired_roles tab (where members see their village)
    if (strpos(\$msg, 'new village announcement') !== false && strpos(\$msg, 'village leader') !== false) {
        if (\$user_type === 'member') {
            \$tabs[] = 'desired_roles';
        }
    }";

// Only replace the FIRST occurrence
$pos = strpos($content, $old);
if ($pos !== false) {
    $content = substr_replace($content, $new, $pos, strlen($old));
    file_put_contents($file, $content);
    echo "Success - routing added\n";
} else {
    echo "Pattern not found - trying simpler approach\n";
    // Try adding it before the first occurrence of 'sunday school class change'
    $pos2 = strpos($content, "if (strpos(\$msg, 'sunday school class change request')");
    if ($pos2 !== false) {
        $insert = "    // Village announcement notification -> desired_roles tab (where members see their village)
    if (strpos(\$msg, 'new village announcement') !== false && strpos(\$msg, 'village leader') !== false) {
        if (\$user_type === 'member') {
            \$tabs[] = 'desired_roles';
        }
    }\n\n    ";
        $content = substr_replace($content, $insert, $pos2, 0);
        file_put_contents($file, $content);
        echo "Success - routing added via fallback\n";
    } else {
        echo "Neither pattern found\n";
    }
}
?>
