<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $content = str_replace(
        "font-weight:<?= !empty(\$pm['is_pastor']) ? '900;color:#1e3a8a;text-transform:uppercase;font-size:0.85rem;' : '600;' ?>;",
        "text-transform:uppercase; font-weight:<?= !empty(\$pm['is_pastor']) ? '900;color:#1e3a8a;font-size:0.85rem;' : '600;' ?>;",
        $content
    );
    file_put_contents($file, $content);
    echo "Updated uppercase for all in $file\n";
}
?>
