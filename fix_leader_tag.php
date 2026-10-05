<?php
$old_td = '<td style="padding:5px 8px;border:1px solid #ddd;text-transform:uppercase;<?php
                                        if (!empty($pm[\'is_pastor\'])) { echo \'font-weight:900;color:#1e3a8a;font-size:0.85rem;\'; }
                                        elseif ($is_dept_leader) { echo \'font-weight:800;color:#1e1a3a;\'; }
                                        else { echo \'font-weight:600;\'; }
                                    ?>">
                                        <?= htmlspecialchars($pm[\'first_name\'].\' \'.$pm[\'last_name\']) ?>
                                        <?= !empty($pm[\'is_pastor\']) ? \' <span style="color:#2563eb;">(PASTOR)</span>\' : \'\' ?>
                                    </td>';

$new_td = '<td style="padding:5px 8px;border:1px solid #ddd;text-transform:uppercase;<?php
                                        if (!empty($pm[\'is_pastor\'])) { echo \'font-weight:900;color:#1e3a8a;font-size:0.85rem;\'; }
                                        elseif ($is_dept_leader) { echo \'font-weight:800;color:#1e1a3a;\'; }
                                        else { echo \'font-weight:600;\'; }
                                    ?>">
                                        <?= htmlspecialchars($pm[\'first_name\'].\' \'.$pm[\'last_name\']) ?>
                                        <?php if (!empty($pm[\'is_pastor\'])): ?>
                                            <span style="color:#2563eb; font-weight:900;">(PASTOR)</span>
                                        <?php elseif ($is_dept_leader): 
                                            // Extract the leadership role from their comma-separated roles
                                            $leader_label = \'LEADER\';
                                            $r_parts = explode(\',\', strtolower($pm[\'church_role\'] ?? \'\'));
                                            foreach($r_parts as $rp) {
                                                $rp = trim($rp);
                                                if (strpos($rp, \'chair\') !== false || strpos($rp, \'patron\') !== false || strpos($rp, \'secretary\') !== false || strpos($rp, \'treasurer\') !== false || strpos($rp, \'village leader\') !== false) {
                                                    $leader_label = strtoupper($rp);
                                                    break;
                                                }
                                            }
                                        ?>
                                            <br><span style="color:#2563eb; font-size:0.65rem; font-weight:700;">(<?= htmlspecialchars($leader_label) ?>)</span>
                                        <?php endif; ?>
                                    </td>';

foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $count = substr_count($content, $old_td);
    $content = str_replace($old_td, $new_td, $content);
    file_put_contents($file, $content);
    echo "Updated $file ($count matches)\n";
}
?>
