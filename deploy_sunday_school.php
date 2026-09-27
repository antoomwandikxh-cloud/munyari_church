<?php
// ============================================================
//  Deploy Manage Sunday School tab to Admin + Pastor dashboards
//  + Sunday School class setup step in Member dashboard
// ============================================================

// ─── TAB CONTENT (identical for admin & pastor, only URLs differ) ─────────────
function ss_tab_content(string $dash) : string {
    $url = $dash . '_dashboard.php';
    return <<<HTML

            <?php elseif (\$tab == 'manage_sunday_school'): ?>
                <?php
                // Determine if viewer is SS Patron or Vice
                \$is_ss_patron = false;
                if (\$dash === 'admin') {
                    \$is_ss_patron = true; // admin always has access
                } else {
                    // pastor context: check if pastor has SS patron role (via church leadership)
                    \$is_ss_patron = true;
                }
                \$classes = ['Battalion', 'Conquerors', 'Little Angels'];
                \$class_counts = [];
                foreach (\$classes as \$cl) {
                    \$cl_safe = \$conn->real_escape_string(\$cl);
                    \$res = \$conn->query("SELECT COUNT(*) as cnt FROM members WHERE department = 'Sunday School' AND sunday_school_class = '\$cl_safe' AND is_approved = 1");
                    \$class_counts[\$cl] = \$res ? \$res->fetch_assoc()['cnt'] : 0;
                }
                \$total_ss = \$conn->query("SELECT COUNT(*) as cnt FROM members WHERE department = 'Sunday School' AND is_approved = 1")->fetch_assoc()['cnt'];
                ?>
                <div class="page-header">
                    <h1>Manage Sunday School</h1>
                    <p>Overview of Sunday School classes and member registration.</p>
                </div>

                <!-- Stats row -->
                <div class="dashboard-grid" style="margin-bottom:30px;">
                    <div class="stat-card">
                        <h3>Total Sunday School</h3>
                        <div class="value" style="color: var(--primary);"><?= \$total_ss ?></div>
                    </div>
                    <?php foreach (\$classes as \$cl): ?>
                    <div class="stat-card">
                        <h3><?= htmlspecialchars(\$cl) ?></h3>
                        <div class="value" style="color: <?= \$cl === 'Battalion' ? '#f59e0b' : (\$cl === 'Conquerors' ? '#10b981' : '#6366f1') ?>;"><?= \$class_counts[\$cl] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Register New Sunday School Member -->
                <div class="content-card" style="margin-bottom:30px;">
                    <div style="display:flex; align-items:center; gap:14px; margin-bottom:20px;">
                        <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="22" height="22" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        </div>
                        <div>
                            <h2 style="margin:0;">Register New Sunday School Member</h2>
                            <p style="margin:0;font-size:0.85rem;color:var(--text-muted);">Account will be immediately active and placed in the selected class.</p>
                        </div>
                    </div>
                    <form method="POST" action="?tab=manage_sunday_school" id="ssRegForm" onsubmit="this.querySelector('button[type=submit]').disabled=true;">
                        <input type="hidden" name="register_ss_member" value="1">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group">
                                <label>First Name <span style="color:var(--danger);">*</span></label>
                                <input type="text" name="first_name" class="form-control" placeholder="e.g. John" pattern="[A-Za-z\s]+" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name <span style="color:var(--danger);">*</span></label>
                                <input type="text" name="last_name" class="form-control" placeholder="e.g. Kamau" pattern="[A-Za-z\s]+" required>
                            </div>
                            <div class="form-group">
                                <label>Phone Number <span style="color:var(--danger);">*</span></label>
                                <input type="tel" name="phone" class="form-control" placeholder="10-digit number" pattern="\d{10}" maxlength="10" required>
                            </div>
                            <div class="form-group">
                                <label>Sunday School Class <span style="color:var(--danger);">*</span></label>
                                <select name="ss_class" class="form-control" required>
                                    <option value="">-- Select Class --</option>
                                    <option value="Battalion">Battalion</option>
                                    <option value="Conquerors">Conquerors</option>
                                    <option value="Little Angels">Little Angels</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Gender <span style="color:var(--danger);">*</span></label>
                                <select name="gender" class="form-control" required>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Residential Area</label>
                                <input type="text" name="address" class="form-control" placeholder="e.g. Mugui">
                            </div>
                            <div class="form-group">
                                <label>Password <span style="color:var(--danger);">*</span></label>
                                <input type="password" name="password" class="form-control" placeholder="At least 6 characters" minlength="6" required>
                            </div>
                        </div>
                        <button type="submit" class="btn-submit" style="margin-top:10px; display:flex; align-items:center; gap:8px; width:auto; padding:0 28px;">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                            Register
                        </button>
                    </form>
                </div>

                <!-- Class Sections -->
                <?php foreach (\$classes as \$cl):
                    \$cl_safe = \$conn->real_escape_string(\$cl);
                    \$cl_color = \$cl === 'Battalion' ? '#f59e0b' : (\$cl === 'Conquerors' ? '#10b981' : '#6366f1');
                    \$cl_members = \$conn->query("SELECT id, first_name, last_name, phone, gender FROM members WHERE department = 'Sunday School' AND sunday_school_class = '\$cl_safe' AND is_approved = 1 ORDER BY first_name ASC");
                ?>
                <div class="content-card" style="margin-bottom:24px; border-left: 4px solid <?= \$cl_color ?>;">
                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                        <div style="width:38px;height:38px;border-radius:10px;background:<?= \$cl_color ?>;display:flex;align-items:center;justify-content:center;">
                            <svg width="20" height="20" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div>
                            <h2 style="margin:0; color:<?= \$cl_color ?>;"><?= htmlspecialchars(\$cl) ?></h2>
                            <span style="font-size:0.82rem;color:var(--text-muted);"><?= \$class_counts[\$cl] ?> member<?= \$class_counts[\$cl] != 1 ? 's' : '' ?></span>
                        </div>
                    </div>
                    <?php if (\$cl_members && \$cl_members->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Gender</th></tr></thead>
                            <tbody>
                            <?php \$i=1; while(\$sm = \$cl_members->fetch_assoc()): ?>
                                <tr>
                                    <td><?= \$i++ ?></td>
                                    <td style="font-weight:500;"><?= htmlspecialchars(\$sm['first_name'].' '.\$sm['last_name']) ?></td>
                                    <td><?= htmlspecialchars(\$sm['phone']) ?></td>
                                    <td><span class="badge" style="background:rgba(99,102,241,0.1);color:#6366f1;"><?= htmlspecialchars(\$sm['gender']) ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p style="color:var(--text-muted); font-style:italic; text-align:center; padding:20px 0;">No members in <?= htmlspecialchars(\$cl) ?> yet.</p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

HTML;
}

// ─── PHP HANDLER for register_ss_member ──────────────────────────────────────
function ss_handler(string $dash) : string {
    return <<<'PHP'

// Handle Sunday School member registration from Manage Sunday School tab
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_ss_member'])) {
    $fn   = $conn->real_escape_string(trim($_POST['first_name'] ?? ''));
    $ln   = $conn->real_escape_string(trim($_POST['last_name'] ?? ''));
    $ph   = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
    $gn   = in_array($_POST['gender'] ?? '', ['Male','Female']) ? $_POST['gender'] : 'Male';
    $ad   = $conn->real_escape_string(trim($_POST['address'] ?? ''));
    $cl   = in_array($_POST['ss_class'] ?? '', ['Battalion','Conquerors','Little Angels']) ? $_POST['ss_class'] : '';
    $pw   = password_hash(trim($_POST['password'] ?? ''), PASSWORD_DEFAULT);
    if (empty($fn) || empty($ln) || empty($ph) || empty($cl) || empty($_POST['password'])) {
        header("Location: ?tab=manage_sunday_school&error=" . urlencode("All required fields must be filled."));
        exit();
    }
    $dup = $conn->query("SELECT id FROM members WHERE phone = '$ph'")->num_rows;
    if ($dup > 0) {
        header("Location: ?tab=manage_sunday_school&error=" . urlencode("A member with that phone number already exists."));
        exit();
    }
    $cl_safe = $conn->real_escape_string($cl);
    $conn->query("INSERT INTO members (first_name, last_name, phone, address, department, gender, password, sunday_school_class, is_approved, reg_date) VALUES ('$fn','$ln','$ph','$ad','Sunday School','$gn','$pw','$cl_safe',1,NOW())");
    header("Location: ?tab=manage_sunday_school&success=" . urlencode("Sunday School member registered successfully!"));
    exit();
}

PHP;
}

// ─── Sidebar link HTML ────────────────────────────────────────────────────────
$ss_sidebar_link = <<<'HTML'
                <a href="?tab=manage_sunday_school" class="sidebar-link <?= $tab == 'manage_sunday_school' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    Manage Sunday School
                </a>
HTML;


// ─── 1. ADMIN DASHBOARD ───────────────────────────────────────────────────────
$admin_file = 'C:\\xampp\\htdocs\\munyari_church\\admin_dashboard.php';
$admin = file_get_contents($admin_file);

// Add handler right before the if(tab=='dashboard') block
$admin_handler_insert = '<?php if ($tab == \'dashboard\'): ?>';
if (strpos($admin, ss_handler('admin')) === false) {
    $admin = str_replace($admin_handler_insert, ss_handler('admin') . $admin_handler_insert, $admin);
}

// Add tab content before the final endif
$admin_endif = '<?php endif; ?>';
$admin_endif_pos = strrpos($admin, $admin_endif);
if ($admin_endif_pos !== false && strpos($admin, 'manage_sunday_school') === false) {
    $admin = substr_replace($admin, ss_tab_content('admin') . '            ', $admin_endif_pos, 0);
}

// Add sidebar link after usher_monitoring link
$usher_link_end_admin = strpos($admin, '</a>', strpos($admin, '<a href="?tab=usher_monitoring"'));
if ($usher_link_end_admin !== false && strpos($admin, 'manage_sunday_school') === false) {
    $admin = substr_replace($admin, "\n" . $ss_sidebar_link, $usher_link_end_admin + 4, 0);
}

file_put_contents($admin_file, $admin);
echo "Admin dashboard updated.\n";


// ─── 2. PASTOR DASHBOARD ──────────────────────────────────────────────────────
$pastor_file = 'C:\\xampp\\htdocs\\munyari_church\\pastor_dashboard.php';
$pastor = file_get_contents($pastor_file);

// Add handler right before if(tab=='dashboard') in pastor
$pastor_handler_insert = '<?php if ($tab == \'dashboard\'): ?>';
if (strpos($pastor, ss_handler('pastor')) === false) {
    $pastor = str_replace($pastor_handler_insert, ss_handler('pastor') . $pastor_handler_insert, $pastor);
}

// Add tab content before the final endif
$pastor_endif_pos = strrpos($pastor, '<?php endif; ?>');
if ($pastor_endif_pos !== false && strpos($pastor, 'manage_sunday_school') === false) {
    $pastor = substr_replace($pastor, ss_tab_content('pastor') . '            ', $pastor_endif_pos, 0);
}

// Add sidebar link after usher_monitoring link in pastor
$usher_link_end_pastor = strpos($pastor, '</a>', strpos($pastor, '<a href="?tab=usher_monitoring"'));
if ($usher_link_end_pastor !== false && strpos($pastor, 'manage_sunday_school') === false) {
    $pastor = substr_replace($pastor, "\n" . $ss_sidebar_link, $usher_link_end_pastor + 4, 0);
}

file_put_contents($pastor_file, $pastor);
echo "Pastor dashboard updated.\n";


// ─── 3. MEMBER DASHBOARD ─ Sunday School class setup step ────────────────────
$member_file = 'C:\\xampp\\htdocs\\munyari_church\\member_dashboard.php';
$member = file_get_contents($member_file);

// Add POST handler for saving ss class
$ss_member_handler = <<<'PHP'

// Handle Sunday School class selection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_ss_class'])) {
    $allowed_classes = ['Battalion', 'Conquerors', 'Little Angels'];
    $ss_class = trim($_POST['ss_class'] ?? '');
    if (in_array($ss_class, $allowed_classes)) {
        $ss_safe = $conn->real_escape_string($ss_class);
        $conn->query("UPDATE members SET sunday_school_class = '$ss_safe' WHERE id = $member_id");
    }
    header("Location: member_dashboard.php?tab=settings&success=Sunday School class saved!");
    exit();
}

PHP;

// Insert after save_username handler
$member_insert_after = "    header(\"Location: member_dashboard.php?tab=settings\u0026success=Username saved successfully!\");
    exit();
}";
if (strpos($member, 'save_ss_class') === false) {
    $member = str_replace($member_insert_after, $member_insert_after . $ss_member_handler, $member);
}

// ─── Add SS class step card in the settings tab (inside !$setup_completed block)
// Insert AFTER the profile picture step and BEFORE the "Setup complete!" alert
$ss_class_step_card = <<<'PHP'

                <?php
                $is_ss_member = ($member['department'] ?? '') === 'Sunday School';
                $has_ss_class = !empty($member['sunday_school_class']);
                ?>
                <?php if ($is_ss_member): ?>
                <!-- STEP: Sunday School Class Selection -->
                <div class="content-card" style="max-width: 480px; margin-bottom: 24px; border: 2px solid <?= $has_ss_class ? '#10b981' : '#f59e0b' ?>; position:relative;">
                    <div style="position:absolute; top:16px; right:16px; width:28px; height:28px; border-radius:50%; background:<?= $has_ss_class ? '#10b981' : '#f59e0b' ?>; display:flex; align-items:center; justify-content:center; font-size:1rem;"><?= $has_ss_class ? '✓' : '3' ?></div>
                    <h2 style="color:<?= $has_ss_class ? '#10b981' : '#f59e0b' ?>;">Step 3 - Choose Your Sunday School Class</h2>
                    <?php if ($has_ss_class): ?>
                        <div class="alert alert-success" style="margin-top:10px;">Class set: <strong><?= htmlspecialchars($member['sunday_school_class']) ?></strong> — you can change it below.</div>
                    <?php else: ?>
                        <p style="color:var(--text-muted); font-size:0.9rem;">As a Sunday School member, please select which class you belong to.</p>
                    <?php endif; ?>
                    <form method="POST" action="?tab=settings" style="margin-top:14px;">
                        <input type="hidden" name="save_ss_class" value="1">
                        <div class="form-group">
                            <label>Your Class</label>
                            <select name="ss_class" class="form-control" required>
                                <option value="">-- Select Class --</option>
                                <option value="Battalion" <?= ($member['sunday_school_class'] ?? '') === 'Battalion' ? 'selected' : '' ?>>Battalion</option>
                                <option value="Conquerors" <?= ($member['sunday_school_class'] ?? '') === 'Conquerors' ? 'selected' : '' ?>>Conquerors</option>
                                <option value="Little Angels" <?= ($member['sunday_school_class'] ?? '') === 'Little Angels' ? 'selected' : '' ?>>Little Angels</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-submit" style="<?= $has_ss_class ? 'background:var(--bg-lighter);color:var(--text-main);border:1px solid var(--border-color);' : '' ?>"><?= $has_ss_class ? 'Change Class' : 'Save Class' ?></button>
                    </form>
                </div>
                <?php endif; ?>
PHP;

// Insert just before the "if ($has_username && $has_profile_pic)" complete alert
$insert_before_complete = '                <?php if ($has_username && $has_profile_pic): ?>';
if (strpos($member, 'save_ss_class') === false || strpos($member, $ss_class_step_card) === false) {
    $member = str_replace($insert_before_complete, $ss_class_step_card . "\n" . $insert_before_complete, $member);
}

file_put_contents($member_file, $member);
echo "Member dashboard updated.\n";
echo "All done!\n";
?>
