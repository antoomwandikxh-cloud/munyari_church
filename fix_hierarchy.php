<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);

    // We want to move the foreach ($v_pastor_rows as $pr) block BEFORE the while($vm = $v_members->fetch_assoc()) block
    // The pastor block is:
    /*
                            <?php foreach ($v_pastor_rows as $pr):
                                $dsr = $pr['desired_role_pref'] ?? '';
                                $dsr_color = $dsr === 'Worshipper' ? '#8b5cf6' : ($dsr === 'Church Cleaner' ? '#0ea5e9' : ($dsr === 'Church Cooker' ? '#f59e0b' : '#94a3b8'));
                                $pic = htmlspecialchars($pr['profile_picture'] ?? 'default_avatar.png');
                            ?>
                            <tr style="background:rgba(251,191,36,0.08);">
                                <td style="color:var(--text-muted);"><?= $i++ ?></td>
                                <td>
                                    <img src="uploads/<?= $pic ?>" alt="Photo"
                                         style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid #f59e0b;cursor:zoom-in;display:block;"
                                         onclick="viewProfileImage(this.src);"
                                         onerror="this.src='uploads/default_avatar.png';">
                                </td>
                                <td style="font-weight:700;">
                                    <?= htmlspecialchars($pr['first_name'] . ' ' . $pr['last_name']) ?>
                                    <span class="badge" style="background:#fef3c7;color:#92400e;margin-left:4px;border:1px solid #f59e0b;">Pastor</span>
                                </td>
                                <td><span class="badge" style="background:<?= $v_color ?>22;color:<?= $v_color ?>;font-weight:700;"><?= htmlspecialchars($pr['church_village'] ?: $v) ?></span></td>
                                <td><?= htmlspecialchars($pr['department'] ?: 'General Church') ?></td>
                                <td><span class="badge" style="background:rgba(245,158,11,0.15); color:#d97706;"><?= htmlspecialchars($pr['church_role'] ?: 'Pastor') ?></span></td>
                                <td><?php if ($dsr): ?><span class="badge" style="background:<?= $dsr_color ?>22; color:<?= $dsr_color ?>;font-weight:700;"><?= htmlspecialchars($dsr) ?></span><?php else: ?><span style="color:var(--text-muted); font-size:0.85rem;">—</span><?php endif; ?></td>
                                <td style="color:var(--text-muted); font-size:0.85rem;"><?= htmlspecialchars($pr['phone'] ?? '—') ?></td>
                            </tr>
                            <?php endforeach; ?>
    */

    // And the member block is:
    /*
                            <?php $i = 1; while($vm = $v_members->fetch_assoc()):
                                $dsr = $vm['desired_role_pref'] ?? '';
                                $dsr_color = $dsr === 'Worshipper' ? '#8b5cf6' : ($dsr === 'Church Cleaner' ? '#0ea5e9' : ($dsr === 'Church Cooker' ? '#f59e0b' : '#94a3b8'));
                                $is_leader = !empty($vm['is_village_leader']);
                                $pic = htmlspecialchars($vm['profile_picture'] ?? 'default_avatar.png');
                            ?>
                            <tr style="<?= $is_leader ? 'background:rgba(37,99,235,0.07);' : '' ?>">
                                <td style="color:var(--text-muted);"><?= $i++ ?><?= $is_leader ? ' 🏆' : '' ?></td>
                                <td>
                                    <img src="uploads/<?= $pic ?>" alt="Photo"
                                         style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid <?= $is_leader ? $v_color : 'var(--border-color)' ?>;cursor:zoom-in;display:block;"
                                         onclick="viewProfileImage(this.src);"
                                         onerror="this.src='uploads/default_avatar.png';">
                                </td>
                                <td style="font-weight:<?= $is_leader ? '700' : '600' ?>;">
                                    <?= htmlspecialchars($vm['first_name'] . ' ' . $vm['last_name']) ?>
                                    <?php if ($is_leader): ?><span class="badge" style="background:<?= $v_color ?>22;color:<?= $v_color ?>;margin-left:4px;">Leader</span><?php endif; ?>
                                </td>
                                <td><span class="badge" style="background:<?= $v_color ?>22;color:<?= $v_color ?>;font-weight:700;"><?= htmlspecialchars($vm['church_village'] ?: $v) ?></span></td>
                                <td><?= htmlspecialchars($vm['department'] ?: 'General Church') ?></td>
                                <td><span class="badge" style="background:rgba(37,99,235,0.1); color:var(--primary);"><?= htmlspecialchars($vm['church_role'] ?: 'Member') ?></span></td>
                                <td><?php if ($dsr): ?><span class="badge" style="background:<?= $dsr_color ?>22; color:<?= $dsr_color ?>;font-weight:700;"><?= htmlspecialchars($dsr) ?></span><?php else: ?><span style="color:var(--text-muted); font-size:0.85rem;">—</span><?php endif; ?></td>
                                <td style="color:var(--text-muted); font-size:0.85rem;"><?= htmlspecialchars($vm['phone'] ?? '—') ?></td>
                            </tr>
                            <?php endwhile; ?>
    */

    // Let's find the exact blocks.
    $start_member = strpos($content, '<?php $i = 1; while($vm = $v_members->fetch_assoc()):');
    $end_member = strpos($content, '<?php endwhile; ?>', $start_member);
    if ($end_member !== false) {
        $end_member += strlen('<?php endwhile; ?>');
    }

    $start_pastor = strpos($content, '<?php foreach ($v_pastor_rows as $pr):', $end_member);
    $end_pastor = strpos($content, '<?php endforeach; ?>', $start_pastor);
    if ($end_pastor !== false) {
        $end_pastor += strlen('<?php endforeach; ?>');
    }

    if ($start_member !== false && $end_member !== false && $start_pastor !== false && $end_pastor !== false) {
        $member_block = substr($content, $start_member, $end_member - $start_member);
        $pastor_block = substr($content, $start_pastor, $end_pastor - $start_pastor);

        // Make sure $i = 1 is moved above the pastor block
        $member_block_no_i = str_replace('<?php $i = 1; while($vm = $v_members->fetch_assoc()):', '<?php while($vm = $v_members->fetch_assoc()):', $member_block);
        $pastor_block_with_i = str_replace('<?php foreach ($v_pastor_rows as $pr):', '<?php $i = 1; foreach ($v_pastor_rows as $pr):', $pastor_block);

        $new_combined = $pastor_block_with_i . "\n                            " . $member_block_no_i;

        // Replace the whole section
        $full_old = substr($content, $start_member, $end_pastor - $start_member);
        $content = str_replace($full_old, $new_combined, $content);
        
        file_put_contents($file, $content);
        echo "Swapped blocks in $file\n";
    } else {
        echo "Could not find blocks in $file\n";
    }
}
?>
