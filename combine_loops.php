<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);

    // We need to find the queries around line 3564:
    /*
                    <?php foreach ($villages as $v): 
                        $v_color = $village_colors[$v];
                        $v_members = $conn->query("SELECT first_name, last_name, department, church_role, phone, desired_role_pref, profile_picture, church_village, is_village_leader, 'Member' AS person_type FROM members WHERE church_village='$v' ORDER BY is_village_leader DESC, department, first_name");
                        $v_count = $v_members ? $v_members->num_rows : 0;
                        $v_pastors = $conn->query("SELECT first_name, last_name, department, role AS church_role, phone, desired_role_pref, profile_picture, church_village, 0 AS is_village_leader, 'Pastor' AS person_type FROM pastors WHERE church_village='$v' AND is_approved=1 ORDER BY first_name");
                        $v_pastor_rows = [];
                        if ($v_pastors) { while ($pr = $v_pastors->fetch_assoc()) $v_pastor_rows[] = $pr; }
                        $v_total = $v_count + count($v_pastor_rows);
                    ?>
    */
    // And rewrite them into a single ordered array of rows, then we just loop through that array in the <tbody>!

    $start_q = strpos($content, '<?php foreach ($villages as $v):');
    $end_q = strpos($content, '?>', $start_q);
    if ($start_q !== false && $end_q !== false) {
        $old_q = substr($content, $start_q, $end_q - $start_q + 2);

        $new_q = '<?php foreach ($villages as $v): 
                    $v_color = $village_colors[$v] ?? \'#94a3b8\';
                    
                    // Fetch members
                    $v_members_res = $conn->query("SELECT first_name, last_name, department, church_role, phone, desired_role_pref, profile_picture, church_village, is_village_leader, \'Member\' AS person_type FROM members WHERE church_village=\'$v\' ORDER BY is_village_leader DESC, department, first_name");
                    $v_count = $v_members_res ? $v_members_res->num_rows : 0;
                    
                    $v_leaders = [];
                    $v_regs = [];
                    if ($v_members_res) {
                        while ($row = $v_members_res->fetch_assoc()) {
                            if (!empty($row[\'is_village_leader\'])) {
                                $v_leaders[] = $row;
                            } else {
                                $v_regs[] = $row;
                            }
                        }
                    }
                    
                    // Fetch pastors
                    $v_pastors_res = $conn->query("SELECT first_name, last_name, department, role AS church_role, phone, desired_role_pref, profile_picture, church_village, 0 AS is_village_leader, \'Pastor\' AS person_type FROM pastors WHERE church_village=\'$v\' AND is_approved=1 ORDER BY first_name");
                    $v_pastor_rows = [];
                    if ($v_pastors_res) {
                        while ($pr = $v_pastors_res->fetch_assoc()) {
                            $v_pastor_rows[] = $pr;
                        }
                    }
                    
                    $v_total = $v_count + count($v_pastor_rows);
                    $all_v_rows = array_merge($v_leaders, $v_pastor_rows, $v_regs);
                ?>';

        $content = str_replace($old_q, $new_q, $content);

        // Now replace the entire tbody loop
        $start_tbody = strpos($content, '<tbody>', $end_q);
        $end_tbody = strpos($content, '</tbody>', $start_tbody);
        if ($start_tbody !== false && $end_tbody !== false) {
            $old_tbody = substr($content, $start_tbody, $end_tbody - $start_tbody + 8);
            
            $new_tbody = '<tbody>
                            <?php $i = 1; foreach ($all_v_rows as $row):
                                $dsr = $row[\'desired_role_pref\'] ?? \'\';
                                $dsr_color = $dsr === \'Worshipper\' ? \'#8b5cf6\' : ($dsr === \'Church Cleaner\' ? \'#0ea5e9\' : ($dsr === \'Church Cooker\' ? \'#f59e0b\' : \'#94a3b8\'));
                                $is_leader = !empty($row[\'is_village_leader\']);
                                $is_pastor = ($row[\'person_type\'] === \'Pastor\');
                                $pic = htmlspecialchars($row[\'profile_picture\'] ?? \'default_avatar.png\');
                                
                                if ($is_pastor) {
                                    $bg_style = "background:rgba(251,191,36,0.08);";
                                    $border_color = "#f59e0b";
                                } elseif ($is_leader) {
                                    $bg_style = "background:rgba(37,99,235,0.07);";
                                    $border_color = $v_color;
                                } else {
                                    $bg_style = "";
                                    $border_color = "var(--border-color)";
                                }
                            ?>
                            <tr style="<?= $bg_style ?>">
                                <td style="color:var(--text-muted);"><?= $i++ ?><?= $is_leader ? \' 🏆\' : \'\' ?></td>
                                <td>
                                    <img src="uploads/<?= $pic ?>" alt="Photo"
                                         style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid <?= $border_color ?>;cursor:zoom-in;display:block;"
                                         onclick="viewProfileImage(this.src);"
                                         onerror="this.src=\'uploads/default_avatar.png\';">
                                </td>
                                <td style="font-weight:<?= ($is_leader || $is_pastor) ? \'700\' : \'600\' ?>;">
                                    <?= htmlspecialchars($row[\'first_name\'] . \' \' . $row[\'last_name\']) ?>
                                    <?php if ($is_leader): ?><span class="badge" style="background:<?= $v_color ?>22;color:<?= $v_color ?>;margin-left:4px;">Leader</span><?php endif; ?>
                                    <?php if ($is_pastor): ?><span class="badge" style="background:#fef3c7;color:#92400e;margin-left:4px;border:1px solid #f59e0b;">Pastor</span><?php endif; ?>
                                </td>
                                <td><span class="badge" style="background:<?= $v_color ?>22;color:<?= $v_color ?>;font-weight:700;"><?= htmlspecialchars($row[\'church_village\'] ?: $v) ?></span></td>
                                <td><?= htmlspecialchars($row[\'department\'] ?: \'General Church\') ?></td>
                                <td><span class="badge" style="<?= $is_pastor ? \'background:rgba(245,158,11,0.15); color:#d97706;\' : \'background:rgba(37,99,235,0.1); color:var(--primary);\' ?>"><?= htmlspecialchars($row[\'church_role\'] ?: ($is_pastor ? \'Pastor\' : \'Member\')) ?></span></td>
                                <td><?php if ($dsr): ?><span class="badge" style="background:<?= $dsr_color ?>22; color:<?= $dsr_color ?>;font-weight:700;"><?= htmlspecialchars($dsr) ?></span><?php else: ?><span style="color:var(--text-muted); font-size:0.85rem;">—</span><?php endif; ?></td>
                                <td style="color:var(--text-muted); font-size:0.85rem;"><?= htmlspecialchars($row[\'phone\'] ?? \'—\') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>';

            $content = str_replace($old_tbody, $new_tbody, $content);
            file_put_contents($file, $content);
            echo "Successfully combined and reordered loops in $file\n";
        }
    }
}
?>
