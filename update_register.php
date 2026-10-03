<?php
$file = 'register.php';
$content = file_get_contents($file);

$content = str_replace(
    "\$gender     = \$conn->real_escape_string(trim(\$_POST['gender'] ?? 'Male'));",
    "\$gender     = \$conn->real_escape_string(trim(\$_POST['gender'] ?? 'Male'));\n        \$desired_role = \$conn->real_escape_string(trim(\$_POST['desired_role_pref'] ?? ''));",
    $content
);

file_put_contents($file, $content);
echo "Updated $file\n";
?>
