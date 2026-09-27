<?php
// ============================================================
// FIX 1: print_village_members.php - remove pastor photo, keep name only
// ============================================================
$pfile = "C:\\xampp\\htdocs\\munyari_church\\print_village_members.php";
$pc = file_get_contents($pfile);

// Remove pastor profile picture block in sig section
$pastor_pic_block = '            <?php if ($pastor_pic_b64): ?>
            <div style="text-align:center; margin-bottom:10px;">
                <img src="<?= $pastor_pic_b64 ?>" alt="Pastor" style="width:50px;height:50px;border-radius:50%;object-fit:cover;border:2px solid #1e3a8a;">
            </div>
            <?php endif; ?>';
$pc = str_replace($pastor_pic_block, '', $pc);

// Also remove the $pastor_pic_b64 variable from the PHP header
$pastor_pic_php = '
// Pastor pic as base64
$pastor_pic_b64 = \'\';
if ($pastor && !empty($pastor[\'profile_picture\'])) {
    $pp = \'uploads/\' . basename($pastor[\'profile_picture\']);
    if (file_exists($pp)) {
        $ext = strtolower(pathinfo($pp, PATHINFO_EXTENSION));
        $mime = ($ext === \'png\') ? \'image/png\' : (($ext === \'gif\') ? \'image/gif\' : \'image/jpeg\');
        $pastor_pic_b64 = "data:$mime;base64," . base64_encode(file_get_contents($pp));
    }
}';
$pc = str_replace($pastor_pic_php, '', $pc);

file_put_contents($pfile, $pc);
echo "FIX 1 (pastor pic removed from print): " . (strpos($pc, 'pastor_pic_b64') === false ? "OK" : "Partial") . "\n";


// ============================================================
// FIX 2 & 3: member_dashboard.php
// ============================================================
$mfile = "C:\\xampp\\htdocs\\munyari_church\\member_dashboard.php";
$mc = file_get_contents($mfile);

// --- FIX 2: Replace the old members table with one that has photos + sorting ---
$old_table = '<!-- Members List -->
            <div class="content-card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                    <h2 style="margin:0; color:var(--text-main); font-size:1.1rem;">Village Members (<?= htmlspecialchars($member[\'church_village\']) ?>)</h2>
                    <a href="print_village_members.php?village=<?= urlencode($member[\'church_village\']) ?>" target="_blank" class="btn-primary" style="background:#10b981; padding:8px 16px; font-size:0.85rem; text-decoration:none; display:inline-block;">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:6px; vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        Print Official List (New)
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="print-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Department</th>
                                <th>Roles</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $v = $conn->real_escape_string($member[\'church_village\']);
                            $v_mems = $conn->query("SELECT first_name, last_name, phone, department, church_role FROM members WHERE is_approved = 1 AND church_village = \'$v\' ORDER BY first_name");
                            if ($v_mems && $v_mems->num_rows > 0) {
                                while($m = $v_mems->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($m[\'first_name\'] . \' \' . $m[\'last_name\']) . "</td>";
                                    echo "<td>" . htmlspecialchars($m[\'phone\'] ?? \'\') . "</td>";
                                    echo "<td>" . htmlspecialchars($m[\'department\'] ?? \'\') . "</td>";
                                    echo "<td>" . htmlspecialchars($m[\'church_role\'] ?? \'\') . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan=\'4\'>No members found in this village.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>';

$new_table = '<!-- Members List -->
            <div class="content-card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                    <h2 style="margin:0; color:var(--text-main); font-size:1.1rem;">Village Members (<?= htmlspecialchars($member[\'church_village\']) ?>)</h2>
                    <a href="print_village_members.php?village=<?= urlencode($member[\'church_village\']) ?>" target="_blank" class="btn-primary" style="background:#10b981; padding:8px 16px; font-size:0.85rem; text-decoration:none; display:inline-block;">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:6px; vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        Print Official List
                    </a>
                </div>
                <div class="table-responsive">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#1e3a8a; color:#fff;">
                                <th style="padding:10px 8px; font-size:11px; text-transform:uppercase; text-align:left;">#</th>
                                <th style="padding:10px 8px; font-size:11px; text-transform:uppercase; text-align:left;">Photo</th>
                                <th style="padding:10px 8px; font-size:11px; text-transform:uppercase; text-align:left;">Name</th>
                                <th style="padding:10px 8px; font-size:11px; text-transform:uppercase; text-align:left;">Phone</th>
                                <th style="padding:10px 8px; font-size:11px; text-transform:uppercase; text-align:left;">Department</th>
                                <th style="padding:10px 8px; font-size:11px; text-transform:uppercase; text-align:left;">Role(s)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $v = $conn->real_escape_string($member[\'church_village\']);
                            $vm_q = $conn->query("
                                SELECT id, first_name, last_name, phone, department, church_role, profile_picture, is_village_leader,
                                CASE
                                    WHEN is_village_leader = 1 THEN 1
                                    WHEN church_role IS NOT NULL AND church_role != \'\' AND LOWER(church_role) != \'member\' THEN 2
                                    ELSE 99
                                END AS sort_rank
                                FROM members
                                WHERE is_approved = 1 AND church_village = \'$v\'
                                ORDER BY sort_rank ASC, first_name ASC
                            ");
                            if ($vm_q && $vm_q->num_rows > 0) {
                                $vi = 1;
                                while ($vm = $vm_q->fetch_assoc()) {
                                    $bg = ($vm[\'is_village_leader\'] == 1) ? \'background:#eff6ff;\' : (($vi % 2 === 0) ? \'background:#f9fafb;\' : \'background:#fff;\');
                                    $pic_src = \'uploads/\' . basename($vm[\'profile_picture\'] ?? \'default_avatar.png\');
                                    $badge = \'\';
                                    if ($vm[\'is_village_leader\'] == 1) {
                                        $badge = \'<span style="background:#1e3a8a;color:#fff;font-size:9px;padding:2px 6px;border-radius:20px;font-weight:700;text-transform:uppercase;margin-left:5px;">Leader</span>\';
                                    } elseif (!empty($vm[\'church_role\']) && strtolower(trim($vm[\'church_role\'])) !== \'member\') {
                                        $badge = \'<span style="background:#10b981;color:#fff;font-size:9px;padding:2px 6px;border-radius:20px;font-weight:700;text-transform:uppercase;margin-left:5px;">Role</span>\';
                                    }
                                    echo "<tr style=\'$bg\'>";
                                    echo "<td style=\'padding:8px; border:1px solid #e5e7eb; font-size:12px;\'>" . $vi . "</td>";
                                    echo "<td style=\'padding:6px 8px; border:1px solid #e5e7eb;\'>
                                            <img src=\'" . htmlspecialchars($pic_src) . "\'
                                                 onerror=\"this.src=\'uploads/default_avatar.png\'\"
                                                 style=\'width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid #1e3a8a;display:block;\'></td>";
                                    echo "<td style=\'padding:8px; border:1px solid #e5e7eb; font-weight:" . ($vm[\'is_village_leader\'] == 1 ? \'700\' : \'400\') . ";\'>" . htmlspecialchars(ucfirst($vm[\'first_name\']) . \' \' . ucfirst($vm[\'last_name\'])) . $badge . "</td>";
                                    echo "<td style=\'padding:8px; border:1px solid #e5e7eb; font-size:12px;\'>" . htmlspecialchars($vm[\'phone\'] ?? \'\') . "</td>";
                                    echo "<td style=\'padding:8px; border:1px solid #e5e7eb; font-size:12px;\'>" . htmlspecialchars($vm[\'department\'] ?? \'\') . "</td>";
                                    echo "<td style=\'padding:8px; border:1px solid #e5e7eb; font-size:12px;\'>" . htmlspecialchars($vm[\'church_role\'] ?? \'\') . "</td>";
                                    echo "</tr>";
                                    $vi++;
                                }
                            } else {
                                echo "<tr><td colspan=\'6\' style=\'text-align:center;padding:20px;color:#888;\'>No members found in this village.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>';

$mc = str_replace($old_table, $new_table, $mc);
echo "FIX 2 (photo table): " . (strpos($mc, 'sort_rank ASC') !== false ? "OK" : "FAILED") . "\n";


// --- FIX 3: Remove sidebar link from current position (near desired_roles) ---
// Remove FIRST occurrence (lines ~2527-2533)
$old_sidebar_first = '                <?php if ($member[\'is_village_leader\'] == 1): ?>
                <a href="?tab=manage_church_village" class="sidebar-link <?= $tab == \'manage_church_village\' ? \'active\' : \'\' ?>" style="position:relative;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Manage Church Village
                    <?php if (!empty($tab_badges[\'manage_church_village\'])): ?><span style="background:var(--danger);color:white;font-size:0.65rem;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:auto;"><?= $tab_badges[\'manage_church_village\'] ?></span><?php endif; ?>
                </a>
                <?php endif; ?>

                <?php if (($member[\'department\'] ?? \'\') === \'Sunday School\'):';

$replacement_first = '                <?php if (($member[\'department\'] ?? \'\') === \'Sunday School\':';

if (strpos($mc, $old_sidebar_first) !== false) {
    $mc = str_replace($old_sidebar_first, $replacement_first, $mc);
    echo "FIX 3a (removed sidebar from top): OK\n";
} else {
    echo "FIX 3a: Pattern not found, trying minimal removal\n";
}

// --- FIX 3b: Add the village panel AFTER "My Worship" section ends (before the endif for worship) ---
$worship_end = '                <?php endif; ?>

                <?php endif; // end has_church_village gate for nav links ?>';

$village_panel_block = '                <?php endif; ?>

                <?php if ($member[\'is_village_leader\'] == 1): ?>
                <!-- Church Village Panel -->
                <div class="sidebar-label" style="padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: #10b981; margin-top: 10px;">Church Village Panel</div>
                <a href="?tab=manage_church_village" class="sidebar-link <?= $tab == \'manage_church_village\' ? \'active\' : \'\' ?>" style="position:relative;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Manage Church Village
                    <?php if (!empty($tab_badges[\'manage_church_village\'])): ?><span style="background:var(--danger);color:white;font-size:0.65rem;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:auto;"><?= $tab_badges[\'manage_church_village\'] ?></span><?php endif; ?>
                </a>
                <?php endif; ?>

                <?php endif; // end has_church_village gate for nav links ?>';

if (strpos($mc, $worship_end) !== false) {
    $mc = str_replace($worship_end, $village_panel_block, $mc);
    echo "FIX 3b (added village panel near worship): OK\n";
} else {
    echo "FIX 3b: Could not find worship end anchor\n";
}

file_put_contents($mfile, $mc);
echo "Done. Saved member_dashboard.php\n";
?>
