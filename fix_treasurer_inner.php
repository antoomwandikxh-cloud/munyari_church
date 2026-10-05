<?php
// Also fix the inner treasurer profile to be a tidy column itself so the amount stacks correctly
$old = '                                                        <div style="margin-top: 15px; padding-top: 12px; border-top: 1px dashed var(--border-color); display: flex; align-items: center; gap: 10px;">';

$new = '                                                        <div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0;">';

foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $count = substr_count($content, $old);
    if ($count > 0) {
        $content = str_replace($old, $new, $content);
        file_put_contents($file, $content);
        echo "Updated inner treasurer row in $file ($count matches)\n";
    } else {
        echo "Could not find target in $file\n";
    }
}
?>
