<?php
$old = 'style="color:#ef4444; text-decoration:none; font-size:1.2rem; font-weight:bold; line-height:1;">&times;</a>';
$new = 'style="display:inline-flex;align-items:center;gap:4px;background:#ef4444;color:white;text-decoration:none;font-size:0.75rem;font-weight:700;padding:4px 10px;border-radius:6px;line-height:1;white-space:nowrap;">🗑 Remove</a>';
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
