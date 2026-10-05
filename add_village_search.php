<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];

$old_span = '<span class="badge" style="background:<?= $v_color ?>22; color:<?= $v_color ?>; font-weight:700; font-size:1rem; padding:6px 14px;"><?= $v_total ?> Members</span>';

$new_html = '<input type="text" placeholder="Search <?= htmlspecialchars($v) ?> members..." style="padding:8px 12px; border:1px solid var(--border-color); border-radius:8px; width:220px; font-size:0.85rem;" onkeyup="filterDeptTable(this, \'village_table_<?= str_replace(\' \', \'_\', $v) ?>\')">
                            ' . $old_span;

foreach ($files as $file) {
    $c = file_get_contents($file);
    $count = 0;
    $c = str_replace($old_span, $new_html, $c, $count);
    
    file_put_contents($file, $c);
    echo "Added search to villages in $file (replaced: $count)\n";
}
?>
