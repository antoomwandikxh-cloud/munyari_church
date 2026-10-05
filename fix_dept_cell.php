<?php
$old_td = '                                                <td style="text-transform:uppercase; <?= $role_r == 0 ? \'font-weight:800; color:#1e1a3a;\' : \'font-weight:600;\' ?>">
                                                    <?= htmlspecialchars($dm[\'first_name\'] . \' \' . $dm[\'last_name\']) ?>
                                                    <?php if ($role_r == 0): 
                                                        // It\'s the top leader! Extract the exact top role name (like YOUTH CHAIRMAN)
                                                        $leader_labels = [];
                                                        $r_parts = explode(\',\', strtolower($dm[\'church_role\'] ?? \'\'));
                                                        foreach($r_parts as $rp) {
                                                            $rp = trim($rp);
                                                            if (strpos($rp, \'vice\') === false && (strpos($rp, \'chair\') !== false || strpos($rp, \'patron\') !== false)) {
                                                                $leader_labels[] = strtoupper($rp);
                                                            }
                                                        }
                                                        // Default to the department name + LEADER if we couldn\'t parse it nicely
                                                        $leader_label = !empty($leader_labels) ? implode(\', \', $leader_labels) : strtoupper($dept . \' LEADER\');
                                                    ?>
                                                        <br><span style="color:#2563eb; font-size:0.55rem; font-weight:700;">(<?= htmlspecialchars($leader_label) ?>)</span>
                                                    <?php endif; ?>
                                                </td>';

$new_td = '                                                <td>
                                                    <!-- On-screen view (normal title case, no brackets) -->
                                                    <span class="screen-name"><?= htmlspecialchars($dm[\'first_name\'] . \' \' . $dm[\'last_name\']) ?></span>
                                                    
                                                    <!-- Print view (uppercase, bold if leader, blue brackets) -->
                                                    <span class="print-only-name" style="display:none; text-transform:uppercase; <?= $role_r <= 1 ? \'font-weight:800; color:#1e1a3a;\' : \'font-weight:600;\' ?>">
                                                        <?= htmlspecialchars($dm[\'first_name\'] . \' \' . $dm[\'last_name\']) ?>
                                                        <?php if ($role_r == 0): // Pastor ?>
                                                            <br><span style="color:#2563eb; font-size:0.55rem; font-weight:900;">(PASTOR)</span>
                                                        <?php elseif ($role_r == 1): // Department Chairperson
                                                            $leader_labels = [];
                                                            $r_parts = explode(\',\', strtolower($dm[\'church_role\'] ?? \'\'));
                                                            foreach($r_parts as $rp) {
                                                                $rp = trim($rp);
                                                                if (strpos($rp, \'vice\') === false && (strpos($rp, \'chair\') !== false || strpos($rp, \'patron\') !== false)) {
                                                                    $leader_labels[] = strtoupper($rp);
                                                                }
                                                            }
                                                            $leader_label = !empty($leader_labels) ? implode(\', \', $leader_labels) : strtoupper($dept . \' LEADER\');
                                                        ?>
                                                            <br><span style="color:#2563eb; font-size:0.55rem; font-weight:700;">(<?= htmlspecialchars($leader_label) ?>)</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>';

foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $count = substr_count($content, $old_td);
    if ($count > 0) {
        $content = str_replace($old_td, $new_td, $content);
        file_put_contents($file, $content);
        echo "Updated table cell in $file ($count matches)\n";
    } else {
        echo "Could not find target cell in $file\n";
    }
}
?>
