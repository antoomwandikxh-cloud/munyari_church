<?php
$file = 'admin_dashboard.php';
$content = file_get_contents($file);

$old_block = <<<'PHP'
                <?php
                // Fetch totals per department
                $dept_totals = [];
                $totals_query = $conn->query("SELECT department, COALESCE(SUM(amount), 0) as total FROM financial_records GROUP BY department");
                if ($totals_query) {
                    while ($row = $totals_query->fetch_assoc()) {
                        $dept_totals[$row['department']] = $row['total'];
                    }
                }
                
                $overall_total = array_sum($dept_totals);
                ?>
                <div class="content-card" style="margin-bottom: 30px;">
                    <h2 style="margin-bottom: 15px;">Department Summaries</h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div style="background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <div style="font-size: 0.9rem; opacity: 0.9;">Overall Total</div>
                            <div style="font-size: 1.8rem; font-weight: 700;">KSh <?= number_format($overall_total, 2) ?></div>
                        </div>
                        <?php
                        $target_depts = [
                            'General Church' => 'General Church',
                            'Youths' => 'Youth Ministry',
                            'Womens Ministry' => "Women's Ministry",
                            'Elders' => 'Elders',
                            'Sunday School' => 'Sunday School',
                            'Building' => 'Building Dept'
                        ];
                        $all_totals = array_sum($dept_totals);
                        $overall_total = $all_totals;
                        foreach ($target_depts as $key => $label):
                            $amt = $dept_totals[$key] ?? 0;
                        ?>
                        <div style="background: var(--bg-main); border: 1px solid var(--border-color); padding: 20px; border-radius: 12px;">
                            <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;"><?= htmlspecialchars($label) ?></div>
                            <div style="font-size: 1.4rem; font-weight: 700; color: var(--text-main); margin-top: 5px;">KSh <?= number_format($amt, 2) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
PHP;

$new_block = <<<'PHP'
                <?php
                // Fetch totals per department
                $dept_totals = [];
                $totals_query = $conn->query("SELECT department, COALESCE(SUM(amount), 0) as total FROM financial_records GROUP BY department");
                if ($totals_query) {
                    while ($row = $totals_query->fetch_assoc()) {
                        $dept_totals[$row['department']] = $row['total'];
                    }
                }
                
                // Fetch treasurers
                $treasurers = [];
                $t_query = $conn->query("SELECT first_name, last_name, profile_picture, church_role FROM members WHERE LOWER(church_role) LIKE '%treasurer%'");
                if ($t_query) {
                    while ($row = $t_query->fetch_assoc()) {
                        $roles = explode(',', str_replace('&', ',', $row['church_role']));
                        foreach ($roles as $r) {
                            $r = strtolower(trim(preg_replace('/\s*\(subsidiary\)\s*/i', '', $r)));
                            if (strpos($r, 'treasurer') !== false) {
                                $treasurers[$r] = [
                                    'name' => $row['first_name'] . ' ' . $row['last_name'],
                                    'pic' => empty($row['profile_picture']) ? 'default_avatar.png' : $row['profile_picture']
                                ];
                            }
                        }
                    }
                }
                
                $treasurer_role_map = [
                    'General Church' => 'treasurer',
                    'Youths' => 'youth treasurer',
                    'Womens Ministry' => 'women treasurer',
                    'Elders' => 'elder treasurer',
                    'Sunday School' => 'sunday school treasurer',
                    'Building' => 'building treasurer'
                ];
                
                $overall_total = array_sum($dept_totals);
                ?>
                <div class="content-card" style="margin-bottom: 30px;">
                    <h2 style="margin-bottom: 15px;">Department Summaries</h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">
                        <div style="background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: flex; flex-direction: column; justify-content: space-between;">
                            <div>
                                <div style="font-size: 0.9rem; opacity: 0.9;">Overall Total</div>
                                <div style="font-size: 1.8rem; font-weight: 700;">KSh <?= number_format($overall_total, 2) ?></div>
                            </div>
                        </div>
                        <?php
                        $target_depts = [
                            'General Church' => 'General Church',
                            'Youths' => 'Youth Ministry',
                            'Womens Ministry' => "Women's Ministry",
                            'Elders' => 'Elders',
                            'Sunday School' => 'Sunday School',
                            'Building' => 'Building Dept'
                        ];
                        foreach ($target_depts as $key => $label):
                            $amt = $dept_totals[$key] ?? 0;
                            $t_role = $treasurer_role_map[$key];
                            $t_info = $treasurers[$t_role] ?? null;
                        ?>
                        <div style="background: var(--bg-main); border: 1px solid var(--border-color); padding: 18px; border-radius: 12px; display: flex; flex-direction: column; justify-content: space-between;">
                            <div>
                                <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 600;"><?= htmlspecialchars($label) ?></div>
                                <div style="font-size: 1.4rem; font-weight: 700; color: var(--text-main); margin-top: 2px;">KSh <?= number_format($amt, 2) ?></div>
                            </div>
                            <div style="margin-top: 15px; padding-top: 12px; border-top: 1px dashed var(--border-color); display: flex; align-items: center; gap: 10px;">
                                <?php if ($t_info): ?>
                                    <img src="uploads/<?= htmlspecialchars($t_info['pic']) ?>" alt="Treasurer" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border-color);">
                                    <div style="line-height: 1.2;">
                                        <div style="font-size: 0.8rem; color: var(--text-muted);">Treasurer</div>
                                        <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($t_info['name']) ?></div>
                                    </div>
                                <?php else: ?>
                                    <img src="uploads/default_avatar.png" alt="No Treasurer" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px dashed #ccc; opacity: 0.6;">
                                    <div style="line-height: 1.2;">
                                        <div style="font-size: 0.8rem; color: var(--text-muted);">Treasurer</div>
                                        <div style="font-size: 0.85rem; font-weight: 600; color: #f43f5e;">Not Assigned</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
PHP;

$content = str_replace(str_replace("\r", "", $old_block), str_replace("\r", "", $new_block), $content);
file_put_contents($file, $content);
echo "Updated admin_dashboard.php\n";
?>
