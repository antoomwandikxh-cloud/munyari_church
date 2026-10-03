<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    // Change (PASTOR) color to blue and make sure name is uppercase via CSS
    $content = str_replace(
        '<span style="color:#dc2626;">(PASTOR)</span>',
        '<span style="color:#2563eb;">(PASTOR)</span>',
        $content
    );
    file_put_contents($file, $content);
    echo "Updated (PASTOR) color in $file\n";
}
?>
