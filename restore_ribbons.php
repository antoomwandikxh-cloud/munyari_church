<?php
// Restore ribbons in on-screen loop but SKIP group 99 ("Members" label)
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    $content = file_get_contents($file);

    // Replace the current stripped ribbon area (just a comment + ?>) with the full ribbon block
    // but skipping group 99
    $old = "                                 // (group ribbons only in printout)\r\n                              ?>\r\n";
    $new = "                                if (\$grp !== \$ui_last_grp && \$grp !== 99):\r\n                                    \$gl = \$ui_group_labels[\$grp] ?? 'Other';\r\n                                    \$gc = \$ui_group_colors[\$grp] ?? 'var(--border-color)';\r\n                                    \$ui_last_grp = \$grp;\r\n                                ?>\r\n                                <tr>\r\n                                    <td colspan=\"9\" style=\"background: <?= \$gc ?>; color: white; padding: 8px 15px; font-weight: 700; font-size: 0.82rem; letter-spacing: 0.5px; border-bottom: 2px solid white; text-align:center;\">\r\n                                        <?= htmlspecialchars(\$gl) ?>\r\n                                    </td>\r\n                                </tr>\r\n                                <?php\r\n                                endif;\r\n                                if (\$grp === 99) \$ui_last_grp = 99;\r\n                                ?>\r\n";

    if (strpos($content, $old) !== false) {
        $content = str_replace($old, $new, $content);
        file_put_contents($file, $content);
        echo "Fixed $file\n";
    } else {
        echo "Pattern not found in $file\n";
    }
}
?>
