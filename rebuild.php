<?php
/**
 * CLEAN REBUILD for pastor_dashboard.php
 * Assembles from:
 *   - pastor_dashboard.php.recovered  (lines 0-1440, with corrections)
 *   - scratch/assign_roles.php         (clean assign_roles tab)
 *   - restore_heredoc_clean.txt        (usher_monitoring body + closing tabs)
 */

$orig = file('pastor_dashboard.php.recovered', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
// Note: re-read WITHOUT SKIP_EMPTY_LINES for accurate line numbers
$orig = file('pastor_dashboard.php.recovered', FILE_IGNORE_NEW_LINES);

$out = [];

// ─── PART 1: PHP header block, sidebar, all CSS (lines 0-236) ──────────────
for ($i = 0; $i <= 236; $i++) $out[] = $orig[$i];

// ─── PART 2: main-content div + topbar (lines 348-380) ─────────────────────
for ($i = 348; $i <= 380; $i++) $out[] = $orig[$i];

// ─── PART 3: dashboard tab block (lines 253-271) ───────────────────────────
for ($i = 253; $i <= 271; $i++) $out[] = $orig[$i];

// ─── PART 4: notifications tab (lines 272-289) ─────────────────────────────
for ($i = 272; $i <= 289; $i++) $out[] = $orig[$i];

// ─── PART 5: manage_members header (lines 290-295) ─────────────────────────
for ($i = 290; $i <= 295; $i++) $out[] = $orig[$i];

// Inject clean member registration form
$out[] = '                <div class="content-card" style="margin-bottom: 30px;">';
$out[] = '                    <h2 style="margin-bottom: 20px;">Register New Member</h2>';
$out[] = '                    <form method="POST" action="?tab=manage_members">';
$out[] = '                        <input type="hidden" name="register_member" value="1">';
$out[] = '                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">';
$out[] = '                            <div class="form-group"><label>First Name</label><input type="text" name="first_name" class="form-control" required></div>';
$out[] = '                            <div class="form-group"><label>Last Name</label><input type="text" name="last_name" class="form-control" required></div>';
$out[] = '                            <div class="form-group"><label>Phone Number</label><input type="text" name="phone" class="form-control" required></div>';
$out[] = '                            <div class="form-group"><label>Area / Address</label><input type="text" name="address" class="form-control"></div>';
$out[] = '                            <div class="form-group"><label>Initial Password</label><input type="password" name="password" class="form-control" required></div>';
$out[] = '                        </div>';
$out[] = '                        <button type="submit" class="btn" style="margin-top: 20px;">Register Member</button>';
$out[] = '                    </form>';
$out[] = '                </div>';
$out[] = '                <div class="content-card">';
$out[] = '                    <h2 style="margin-bottom: 20px;">Member Directory</h2>';
$out[] = '                    <div class="table-responsive"><table>';
$out[] = '                        <thead><tr><th>Name</th><th>Phone</th><th>Address</th><th>Status</th><th>Actions</th></tr></thead>';
$out[] = '                        <tbody>';
$out[] = '                            <?php $members->data_seek(0); while($m = $members->fetch_assoc()): ?>';
$out[] = '                            <tr>';
$out[] = '                                <td><?= htmlspecialchars($m[\'first_name\'] . \' \' . $m[\'last_name\']) ?></td>';
$out[] = '                                <td><?= htmlspecialchars($m[\'phone\']) ?></td>';
$out[] = '                                <td><?= htmlspecialchars($m[\'address\'] ?? \'-\') ?></td>';
$out[] = '                                <td><?= $m[\'is_approved\'] ? \'<span class="badge" style="background:var(--success);color:white;">Active</span>\' : \'<span class="badge" style="background:#f59e0b;color:white;">Pending</span>\' ?></td>';
$out[] = '                                <td><a href="?tab=manage_members&action=delete_member&id=<?= $m[\'id\'] ?>" onclick="return confirm(\'Delete this member?\')" style="background:var(--danger);color:white;padding:4px 10px;border-radius:5px;text-decoration:none;font-size:0.85rem;">Delete</a></td>';
$out[] = '                            </tr>';
$out[] = '                            <?php endwhile; ?>';
$out[] = '                        </tbody>';
$out[] = '                    </table></div>';
$out[] = '                </div>';

// ─── PART 6: departments tab (lines 443-528) ────────────────────────────────
for ($i = 443; $i <= 518; $i++) $out[] = $orig[$i];
for ($i = 523; $i <= 528; $i++) $out[] = $orig[$i];

// ─── PART 7: financials tab (lines 529-563 + clean financial records) ───────
for ($i = 529; $i <= 563; $i++) $out[] = $orig[$i];
$out[] = '                        <?php endforeach; ?>';
$out[] = '                    </div>';
$out[] = '                </div>';
$out[] = '                <div class="content-card">';
$out[] = '                    <h2 style="margin-bottom: 15px;">Financial Records</h2>';
$out[] = '                    <?php';
$out[] = '                    $fin_records = $conn->query("SELECT fr.*, m.first_name, m.last_name FROM financial_records fr JOIN members m ON fr.member_id = m.id ORDER BY fr.created_at DESC LIMIT 50");';
$out[] = '                    if ($fin_records && $fin_records->num_rows > 0):';
$out[] = '                    ?>';
$out[] = '                    <div class="table-responsive"><table>';
$out[] = '                        <thead><tr><th>Member</th><th>Department</th><th>Amount</th><th>Description</th><th>Date</th></tr></thead>';
$out[] = '                        <tbody>';
$out[] = '                            <?php while($fr = $fin_records->fetch_assoc()): ?>';
$out[] = '                            <tr>';
$out[] = '                                <td><?= htmlspecialchars($fr[\'first_name\'] . \' \' . $fr[\'last_name\']) ?></td>';
$out[] = '                                <td><?= htmlspecialchars($fr[\'department\'] ?? \'-\') ?></td>';
$out[] = '                                <td style="color:var(--success);font-weight:600;">$<?= number_format($fr[\'amount\'], 2) ?></td>';
$out[] = '                                <td><?= htmlspecialchars($fr[\'description\'] ?? \'-\') ?></td>';
$out[] = '                                <td><?= date(\'M j, Y\', strtotime($fr[\'created_at\'])) ?></td>';
$out[] = '                            </tr>';
$out[] = '                            <?php endwhile; ?>';
$out[] = '                        </tbody>';
$out[] = '                    </table></div>';
$out[] = '                    <?php else: ?>';
$out[] = '                        <p style="color:var(--text-muted);">No financial records yet.</p>';
$out[] = '                    <?php endif; ?>';
$out[] = '                </div>';

// ─── PART 8: assign_roles (from scratch file) ───────────────────────────────
$assign_roles = file('scratch/assign_roles.php', FILE_IGNORE_NEW_LINES);
for ($i = 1; $i < count($assign_roles); $i++) $out[] = $assign_roles[$i];

// ─── PART 9: prayer_board as elseif (lines 1362-1440, NO final endif) ───────
// Line 1362 = PHP if ($tab == 'prayer_board'):  → convert to elseif
// Line 1440 = '            </div>' (last line before the closing endif on 1441)
for ($i = 1362; $i <= 1440; $i++) {
    $line = $orig[$i];
    if (strpos($line, "if (\$tab == 'prayer_board')") !== false) {
        $line = str_replace('if ($tab ==', 'elseif ($tab ==', $line);
    }
    $out[] = $line;
}

// ─── PART 10: usher_monitoring + remaining tabs from restore_heredoc_clean.txt
// That file contains: usher_monitoring body + building/appointments/messages/settings + closing HTML
// We need to open the usher_monitoring block first, then append the rest
$out[] = '            <?php elseif ($tab == \'usher_monitoring\'): ?>';
$out[] = '                <div class="page-header">';
$out[] = '                    <h1>🛡 Usher Monitoring</h1>';
$out[] = '                    <p>Monitor announcements and chat messages within the Usher department.</p>';
$out[] = '                </div>';
$out[] = '                <div style="display: flex; gap: 20px; flex-wrap: wrap;">';
$out[] = '                    <!-- Announcements Column -->';
$out[] = '                    <div style="flex: 1; min-width: 300px;">';
$out[] = '                        <div class="content-card" style="margin-bottom: 24px;">';
$out[] = '                            <h2>📢 Usher Announcements</h2>';

$heredoc_lines = file('restore_heredoc_clean.txt', FILE_IGNORE_NEW_LINES);
foreach ($heredoc_lines as $l) {
    $out[] = $l;
}

file_put_contents('pastor_dashboard.php', implode("\n", $out));
echo "Done! Lines written: " . count($out) . "\n";
?>
