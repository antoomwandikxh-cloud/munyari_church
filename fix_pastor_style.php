<?php
$old_span = '<span class="print-only-name" style="display:none; text-transform:uppercase; <?= $role_r <= 1 ? \'font-weight:800; color:#1e1a3a;\' : \'font-weight:600;\' ?>">';
$new_span = '<span class="print-only-name" style="display:none; text-transform:uppercase; <?= $role_r == 0 ? \'font-weight:900; color:#1e3a8a; font-size:0.85rem;\' : ($role_r == 1 ? \'font-weight:800; color:#1e1a3a;\' : \'font-weight:600;\') ?>">';

foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $count = substr_count($content, $old_span);
    if ($count > 0) {
        $content = str_replace($old_span, $new_span, $content);
        file_put_contents($file, $content);
        echo "Updated Pastor print style in $file ($count matches)\n";
    } else {
        echo "Could not find target in $file\n";
    }
}
?>
