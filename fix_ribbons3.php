<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);

    // Let's rewrite the ribbon logic in both the print popup and the on-screen table.
    
    // ON-SCREEN Ribbon Logic (around line 1685):
    $old_ui = "if (\$grp !== \$ui_last_grp && \$grp !== 99) { \$gl = \$ui_group_labels[\$grp] ?? 'Other'; \$gc = \$ui_group_colors[\$grp] ?? '#374151'; \$ui_last_grp = \$grp; echo '<tr><td colspan=\"9\" style=\"background:'.\$gc.';color:white;padding:10px 18px;font-weight:800;font-size:0.88rem;text-align:center;letter-spacing:1px;text-transform:uppercase;border-top:3px solid rgba(255,255,255,0.3);\">&#9654; '.htmlspecialchars(\$gl).' &#9664;</td></tr>'; } elseif (\$grp === 99) { \$ui_last_grp = 99; }";
    
    $new_ui = "
        \$dept = \$m['department'] ?? 'General Church';
        if (\$grp !== 99) {
            if (\$grp !== \$ui_last_grp) {
                \$gl = \$ui_group_labels[\$grp] ?? 'Other';
                \$gc = \$ui_group_colors[\$grp] ?? '#374151';
                \$ui_last_grp = \$grp;
                \$ui_last_dept = '';
                echo '<tr><td colspan=\"9\" style=\"background:'.\$gc.';color:white;padding:10px 18px;font-weight:800;font-size:0.88rem;text-align:center;letter-spacing:1px;text-transform:uppercase;border-top:3px solid rgba(255,255,255,0.3);\">&#9654; '.htmlspecialchars(\$gl).' &#9664;</td></tr>';
            }
        } else {
            if (\$grp !== \$ui_last_grp || \$dept !== \$ui_last_dept) {
                \$ui_last_grp = 99;
                \$ui_last_dept = \$dept;
                \$dept_colors = ['Elders'=>'#6d28d9', 'Womens Ministry'=>'#be185d', 'Youths'=>'#b45309', 'Sunday School'=>'#0e7490', 'Building'=>'#92400e'];
                \$gc = \$dept_colors[\$dept] ?? '#374151';
                \$gl = \$dept . ' Members';
                echo '<tr><td colspan=\"9\" style=\"background:'.\$gc.';color:white;padding:10px 18px;font-weight:800;font-size:0.88rem;text-align:center;letter-spacing:1px;text-transform:uppercase;border-top:3px solid rgba(255,255,255,0.3);\">&#9654; '.htmlspecialchars(\$gl).' &#9664;</td></tr>';
            }
        }
    ";
    
    // PRINT popup Ribbon Logic (around line 1466):
    $old_print = "if (\$grp !== \$last_grp) { \$last_grp = \$grp; if (\$grp !== 99) { \$gl = \$group_labels[\$grp] ?? 'Other'; \$gc = \$group_colors[\$grp] ?? '#374151'; echo '<tr><td colspan=\"9\" style=\"padding:8px 15px;background:' . \$gc . ';color:white;font-weight:700;font-size:0.82rem;letter-spacing:0.5px;border:1px solid ' . \$gc . ';text-align:center;\"> ' . htmlspecialchars(\$gl) . ' </td></tr>'; } }";
    
    $new_print = "
        \$dept = \$pm['department'] ?? 'General Church';
        if (\$grp !== 99) {
            if (\$grp !== \$last_grp) {
                \$last_grp = \$grp;
                \$last_dept = '';
                \$gl = \$group_labels[\$grp] ?? 'Other';
                \$gc = \$group_colors[\$grp] ?? '#374151';
                echo '<tr><td colspan=\"9\" style=\"padding:8px 15px;background:' . \$gc . ';color:white;font-weight:700;font-size:0.82rem;letter-spacing:0.5px;border:1px solid ' . \$gc . ';text-align:center;\"> ' . htmlspecialchars(\$gl) . ' </td></tr>';
            }
        } else {
            if (\$grp !== \$last_grp || \$dept !== \$last_dept) {
                \$last_grp = 99;
                \$last_dept = \$dept;
                \$dept_colors = ['Elders'=>'#6d28d9', 'Womens Ministry'=>'#be185d', 'Youths'=>'#b45309', 'Sunday School'=>'#0e7490', 'Building'=>'#92400e'];
                \$gc = \$dept_colors[\$dept] ?? '#374151';
                \$gl = \$dept . ' Members';
                echo '<tr><td colspan=\"9\" style=\"padding:8px 15px;background:' . \$gc . ';color:white;font-weight:700;font-size:0.82rem;letter-spacing:0.5px;border:1px solid ' . \$gc . ';text-align:center;\"> ' . htmlspecialchars(\$gl) . ' </td></tr>';
            }
        }
    ";

    $c = str_replace(trim($old_ui), trim($new_ui), $c);
    $c = str_replace(trim($old_print), trim($new_print), $c);
    
    file_put_contents($file, $c);
    echo "Fixed $file\n";
}
?>
