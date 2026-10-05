<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);

    // -------- 1. Remove ribbon rows from ON-SCREEN loop --------
    // Match the full ribbon block including the TR and endif
    $content = preg_replace(
        '/if \(\$grp !== \$ui_last_grp\):\s*\$gl = \$ui_group_labels\[.*?\]\s*\?\?.*?;\s*\$gc = \$ui_group_colors\[.*?\]\s*\?\?.*?;\s*\$text_col = \'white\';\s*\$ui_last_grp = \$grp;\s*\?>\s*<tr>.*?<\/tr>\s*<\?php endif; \?>/s',
        '<?php // (group ribbons display only in printout, not on screen) ?>',
        $content
    );

    // -------- 2. Enhance usort to sort Members by department hierarchy --------
    $dept_rank_code = "['Elders'=>1,'Womens Ministry'=>2,'Youths'=>3,'Sunday School'=>4]";
    
    // Add dept sub-sort inside usort — insert before the final strcmp
    $old_strcmp = "return strcmp(\$a['first_name'].\$a['last_name'], \$b['first_name'].\$b['last_name']);
                });";
    $new_strcmp = "// Within plain Members (group 99), sub-sort by dept hierarchy then name
                    if (\$ra[0] === 99) {
                        \$drank = {$dept_rank_code};
                        \$da = \$drank[\$a['department'] ?? ''] ?? 5;
                        \$db = \$drank[\$b['department'] ?? ''] ?? 5;
                        if (\$da !== \$db) return \$da - \$db;
                    }
                    return strcmp(\$a['first_name'].\$a['last_name'], \$b['first_name'].\$b['last_name']);
                });";

    $content = str_replace($old_strcmp, $new_strcmp, $content);

    file_put_contents($file, $content);
    echo "Fixed: $file\n";
}
?>
