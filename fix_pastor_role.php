<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);

    $old = "<?= htmlspecialchars(\$row['church_role'] ?: (\$is_pastor ? 'Pastor' : 'Member')) ?>";
    $new = "<?= htmlspecialchars(\$is_pastor ? 'Pastor' : (\$row['church_role'] ?: 'Member')) ?>";
    
    $content = str_replace($old, $new, $content);
    file_put_contents($file, $content);
    echo "Fixed in $file\n";
}
?>
