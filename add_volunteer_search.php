<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];

$old_span = '<span class="badge" style="background:<?= $c_color ?>22; color:<?= $c_color ?>; font-size:0.95rem; padding:5px 14px;"><?= $c_count ?> Volunteer<?= $c_count != 1 ? \'s\' : \'\' ?></span>';

$new_span = '<div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                            <input type="text" placeholder="Search <?= htmlspecialchars($c_role) ?> volunteers..." style="padding:8px 12px; border:1px solid var(--border-color); border-radius:8px; width:220px; font-size:0.85rem;" onkeyup="filterDeptTable(this, \'vol_table_<?= md5($c_role) ?>\')">
                            ' . $old_span . '
                        </div>';

$old_div = "                    <?php if (\$c_count > 0): ?>\n                    <div class=\"table-responsive\">";
$new_div = "                    <?php if (\$c_count > 0): ?>\n                    <div class=\"table-responsive\" id=\"vol_table_<?= md5(\$c_role) ?>\">";


foreach ($files as $file) {
    $c = file_get_contents($file);
    $c = str_replace("\r\n", "\n", $c);
    
    $count1 = 0;
    $count2 = 0;
    
    $c = str_replace($old_span, $new_span, $c, $count1);
    
    // Sometimes the whitespace differs slightly, use preg_replace for the div to be safe
    $c = preg_replace('/<\?php if \(\$c_count > 0\): \?>\s*<div class="table-responsive">/', "<?php if (\$c_count > 0): ?>\n                    <div class=\"table-responsive\" id=\"vol_table_<?= md5(\$c_role) ?>\">", $c, -1, $count2);

    file_put_contents($file, $c);
    echo "Added search to volunteer roles in $file (replaced span: $count1, replaced div: $count2)\n";
}
?>
