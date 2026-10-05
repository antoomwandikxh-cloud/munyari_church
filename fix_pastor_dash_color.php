<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);

    $old = '<td><span class="badge" style="<?= $is_pastor ? \'background:rgba(245,158,11,0.15); color:#d97706;\' : \'background:rgba(37,99,235,0.1); color:var(--primary);\' ?>"><?= htmlspecialchars($is_pastor ? \'Pastor\' : ($row[\'church_role\'] ?: \'Member\')) ?></span></td>';
    $new = '<td><span class="badge" style="background:rgba(37,99,235,0.1); color:var(--primary);"><?= htmlspecialchars($is_pastor ? \'Pastor\' : ($row[\'church_role\'] ?: \'Member\')) ?></span></td>';

    $content = str_replace($old, $new, $content);
    file_put_contents($file, $content);
    echo "Fixed $file\n";
}
?>
