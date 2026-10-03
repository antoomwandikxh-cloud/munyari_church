<?php
$files = ['member_dashboard.php', 'pastor_dashboard.php'];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Replace valid roles array with DB fetch
    $pattern = '/\$valid_roles = \[\'Worshipper\', \'Church Cleaner\', \'Church Cooker\', \'\'\];\s*if \(\!in_array\(\$drp, \$valid_roles\)\) \{ \$drp = \'\'; \}/is';
    
    $replacement = <<<PHP
\$valid_roles = [''];
    \$cr_q = \$conn->query("SELECT role_name FROM custom_desired_roles");
    if (\$cr_q) { while(\$cr = \$cr_q->fetch_assoc()){ \$valid_roles[] = \$cr['role_name']; } }
    if (!in_array(\$drp, \$valid_roles)) { \$drp = ''; }
PHP;

    $content = preg_replace($pattern, $replacement, $content);
    file_put_contents($file, $content);
    echo "Updated validation in $file\n";
}
?>
