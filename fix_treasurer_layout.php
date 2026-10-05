<?php
$old = 'style="background: var(--bg-main); border: 1px solid var(--border-color); padding: 20px; border-radius: 12px; display: flex; flex-direction: column; justify-content: space-between;">';

$new = 'style="background: var(--bg-main); border: 1px solid var(--border-color); padding: 20px; border-radius: 12px; display: flex; flex-direction: row; align-items: center; justify-content: space-between; gap: 14px;">';

foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $count = substr_count($content, $old);
    if ($count > 0) {
        $content = str_replace($old, $new, $content);
        file_put_contents($file, $content);
        echo "Updated $file ($count matches)\n";
    } else {
        echo "Could not find target in $file\n";
    }
}
?>
