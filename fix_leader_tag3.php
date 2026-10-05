<?php
$old_td = '                                        <?php elseif ($is_dept_leader): 
                                            // Extract the HIGHEST leadership role (prioritize department roles over village leader)
                                            $leader_label = \'LEADER\';
                                            $r_parts = explode(\',\', strtolower($pm[\'church_role\'] ?? \'\'));
                                            
                                            $found_role = null;
                                            $village_role = null;
                                            
                                            foreach($r_parts as $rp) {
                                                $rp = trim($rp);
                                                if (strpos($rp, \'chair\') !== false || strpos($rp, \'patron\') !== false || (strpos($rp, \'secretary\') !== false && strpos($rp, \'organizing\') === false && strpos($rp, \'village\') === false) || strpos($rp, \'treasurer\') !== false) {
                                                    $found_role = strtoupper($rp);
                                                    break; // Found a top department role, stop looking
                                                } elseif (strpos($rp, \'village leader\') !== false) {
                                                    $village_role = strtoupper($rp); // Keep it just in case there\'s no dept role
                                                }
                                            }
                                            
                                            // Use department role if found, otherwise fallback to village leader
                                            $leader_label = $found_role ?: ($village_role ?: \'LEADER\');
                                        ?>
                                            <br><span style="color:#2563eb; font-size:0.55rem; font-weight:700;">(<?= htmlspecialchars($leader_label) ?>)</span>
                                        <?php endif; ?>';

$new_td = '                                        <?php elseif ($is_dept_leader): 
                                            $leader_label = null;
                                            $r_parts = explode(\',\', strtolower($pm[\'church_role\'] ?? \'\'));
                                            
                                            // Only display brackets for topmost leaders (chairperson/chairman/chairlady or patron)
                                            // Make sure NOT to include vice chairs
                                            foreach($r_parts as $rp) {
                                                $rp = trim($rp);
                                                if (strpos($rp, \'vice\') === false && (strpos($rp, \'chair\') !== false || strpos($rp, \'patron\') !== false)) {
                                                    $leader_label = strtoupper($rp);
                                                    break;
                                                }
                                            }
                                            
                                            if ($leader_label):
                                        ?>
                                            <br><span style="color:#2563eb; font-size:0.55rem; font-weight:700;">(<?= htmlspecialchars($leader_label) ?>)</span>
                                        <?php endif; ?>
                                        <?php endif; ?>';

foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $count = substr_count($content, $old_td);
    $content = str_replace($old_td, $new_td, $content);
    file_put_contents($file, $content);
    echo "Updated $file ($count matches)\n";
}
?>
