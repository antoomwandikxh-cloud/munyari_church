<?php
$conn = new mysqli('localhost', 'root', '', 'munyari_church');

// ─── TAB CONTENT ──────────────────────────────────────────────────────────────
function ss_tab_content($dash) {
    return <<<HTML

            <?php elseif (\$tab == 'manage_sunday_school'): ?>
                <?php
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

// ─── SIDEBAR LINK ─────────────────────────────────────────────────────────────
$ss_sidebar_link = <<<'HTML'
                <a href="?tab=manage_sunday_school" class="sidebar-link <?= $tab == 'manage_sunday_school' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    Manage Sunday School
                </a>
HTML;

foreach (['admin', 'pastor'] as $dash) {
    $file = "C:\\xampp\\htdocs\\munyari_church\\{$dash}_dashboard.php";
    $content = file_get_contents($file);

    // 1. Inject Tab Content
    if (strpos($content, '$tab == \'manage_sunday_school\'') === false) {
        $endif_pos = strrpos($content, '<?php endif; ?>');
        if ($endif_pos !== false) {
            $content = substr_replace($content, ss_tab_content($dash) . '            ', $endif_pos, 0);
            echo "$dash: Tab injected.\n";
        }
    }

    // 2. Inject Sidebar Link
    if (strpos($content, 'tab=manage_sunday_school') === false) {
        $usher_link_end = strpos($content, '</a>', strpos($content, '<a href="?tab=usher_monitoring"'));
        if ($usher_link_end !== false) {
            $content = substr_replace($content, "\n" . $ss_sidebar_link, $usher_link_end + 4, 0);
            echo "$dash: Sidebar injected.\n";
        }
    }

    file_put_contents($file, $content);
}

echo "Done injecting missing HTML elements!";
?>
