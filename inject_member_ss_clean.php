<?php
$file = 'C:\\xampp\\htdocs\\munyari_church\\member_dashboard.php';
$content = file_get_contents($file);

$ss_tab_content = <<<'HTML'
            <?php elseif ($tab == 'manage_sunday_school'): ?>
                <?php
                $classes = ['Battalion', 'Conquerors', 'Little Angels'];
                $class_counts = [];
                foreach ($classes as $cl) {
                    $cl_safe = $conn->real_escape_string($cl);
                    $res = $conn->query("SELECT COUNT(*) as cnt FROM members WHERE department = 'Sunday School' AND sunday_school_class = '$cl_safe' AND is_approved = 1");
                    $class_counts[$cl] = $res ? $res->fetch_assoc()['cnt'] : 0;
                }
                
                $all_ss_members = $conn->query("SELECT * FROM members WHERE department = 'Sunday School' AND is_approved = 1 ORDER BY first_name ASC");
                ?>
                <div class="page-header">
                    <h1>Manage Sunday School</h1>
                    <p>Register new members, view class lists, and manage disciplinary reports.</p>
                </div>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger" style="margin-bottom: 20px;">
                        <?= htmlspecialchars($_GET['error']) ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success" style="margin-bottom: 20px;">
                        <?= htmlspecialchars($_GET['success']) ?>
                    </div>
                <?php endif; ?>

                <div class="content-card" style="margin-bottom:30px;">
                    <h2 style="margin-bottom:20px;">Register New Sunday School Member</h2>
                    <form method="POST" action="?tab=manage_sunday_school" id="ssRegForm" onsubmit="this.querySelector('button[type=submit]').disabled=true;">
                        <input type="hidden" name="register_ss_member" value="1">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group">
                                <label>First Name <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#10b981; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></span>
                                    <input type="text" id="ss_first_name" name="first_name" class="form-control" placeholder="e.g. John" pattern="[A-Za-z\s]+" oninput="validateInput(this,'name'); seqUnlock('ss_first_name','ss_last_name')" style="padding-left:36px;" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Last Name <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#10b981; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></span>
                                    <input type="text" id="ss_last_name" name="last_name" class="form-control" placeholder="e.g. Kamau" pattern="[A-Za-z\s]+" oninput="validateInput(this,'name'); seqUnlock('ss_last_name','ss_class'); document.getElementById('ss_phone').disabled=false;" style="padding-left:36px;" disabled required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Sunday School Class <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#f59e0b; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg></span>
                                    <select id="ss_class" name="ss_class" class="form-control" onchange="seqUnlock('ss_class','ss_gender')" style="padding-left:36px;" disabled required>
                                        <option value="">-- Select Class --</option>
                                        <option value="Battalion">Battalion</option>
                                        <option value="Conquerors">Conquerors</option>
                                        <option value="Little Angels">Little Angels</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Gender <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#8b5cf6; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></span>
                                    <select id="ss_gender" name="gender" class="form-control" onchange="seqUnlock('ss_gender','ss_password')" style="padding-left:36px;" disabled required>
                                        <option value="">-- Select --</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Phone Number</label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#3b82f6; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg></span>
                                    <input type="tel" id="ss_phone" name="phone" class="form-control" placeholder="10 digits" pattern="\d{10}" maxlength="10" oninput="validateInput(this,'phone')" style="padding-left:36px;" disabled>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Residential Area</label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#ec4899; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg></span>
                                    <input type="text" id="ss_address" name="address" class="form-control" placeholder="e.g. Mugui" pattern="[A-Za-z\s]+" oninput="validateInput(this,'name')" style="padding-left:36px;">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Password <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#64748b; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></span>
                                    <input type="password" id="ss_password" name="password" class="form-control" placeholder="Min 6 chars" minlength="6" oninput="seqUnlock('ss_password','ss_submitBtn')" style="padding-left:36px; padding-right:40px;" disabled required>
                                    <button type="button" class="icon-btn" onclick="const pwd = document.getElementById('ss_password'); if (pwd.type === 'password') { pwd.type = 'text'; this.innerHTML = '<svg width=\'18\' height=\'18\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21\'></path></svg>'; } else { pwd.type = 'password'; this.innerHTML = '<svg width=\'18\' height=\'18\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M15 12a3 3 0 11-6 0 3 3 0 016 0z\'></path><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z\'></path></svg>'; }" style="position:absolute; right:5px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer;">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button type="submit" id="ss_submitBtn" class="btn-submit" style="margin-top:10px; display:flex; align-items:center; gap:8px; width:auto; padding:0 28px;" disabled>
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                            Register Member
                        </button>
                    </form>
                </div>

                <div class="content-card" style="margin-bottom:30px;">
                    <h2 style="margin-bottom:20px;">Sunday School Members</h2>
                    <?php if ($all_ss_members && $all_ss_members->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table>
                                <thead><tr><th>Profile</th><th>Name</th><th>Role</th><th>Class</th><th>Phone</th><th>Area</th><th>Since</th></tr></thead>
                                <tbody>
                                    <?php while($sm = $all_ss_members->fetch_assoc()):
                                        $sm_role = trim($sm['church_role'] ?? '');
                                        $sm_basic = $sm_role === '' || strtolower($sm_role) === 'member';
                                    ?>
                                    <tr>
                                        <td><img src="uploads/<?= htmlspecialchars($sm['profile_picture'] ?? 'default_avatar.png') ?>" alt="Profile" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid var(--border-color);cursor:zoom-in;" onclick="viewProfileImage(this.src);"></td>
                                        <td style="font-weight:500;"><?= htmlspecialchars($sm['first_name'] . ' ' . $sm['last_name']) ?></td>
                                        <td>
                                            <span class="badge <?= $sm_basic ? '' : 'approved' ?>" style="<?= $sm_basic ? 'background:var(--border-color);color:var(--text-main);' : '' ?>">
                                                <?= htmlspecialchars($sm_basic ? 'Member' : $sm_role) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($sm['sunday_school_class'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($sm['phone'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($sm['address'] ?? '—') ?></td>
                                        <td style="color:var(--text-muted);font-size:0.9em;"><?= date('M j, Y', strtotime($sm['reg_date'])) ?></td>
                                    </tr>
<?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center" style="padding:40px;color:var(--text-muted);">
                            <p>No members are currently registered in Sunday School.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Disciplinary Reports Section -->
                <?php
                $disc_q = $conn->query("SELECT dr.*, m.first_name, m.last_name, m.department, a.first_name as a_fn, a.last_name as a_ln 
                                        FROM disciplinary_reports dr
                                        JOIN members m ON dr.member_id = m.id
                                        JOIN admins a ON dr.admin_id = a.id
                                        WHERE m.department = 'Sunday School'
                                        ORDER BY dr.created_at DESC");
                ?>
                <div class="content-card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                        <h2 style="margin:0;">Disciplinary Reports (Sunday School)</h2>
                    </div>
                    <?php if ($disc_q && $disc_q->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Member Name</th>
                                    <th>Department</th>
                                    <th>Reported By</th>
                                    <th>Reason / Details</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php while($dr = $disc_q->fetch_assoc()): ?>
                                <tr>
                                    <td style="white-space:nowrap;"><?= date('M j, Y', strtotime($dr['created_at'])) ?></td>
                                    <td style="font-weight:500; color:var(--danger);"><?= htmlspecialchars($dr['first_name'].' '.$dr['last_name']) ?></td>
                                    <td><span class="badge" style="background:rgba(99,102,241,0.1); color:#6366f1;"><?= htmlspecialchars($dr['department']) ?></span></td>
                                    <td>Admin <?= htmlspecialchars($dr['a_fn'].' '.$dr['a_ln']) ?></td>
                                    <td><?= nl2br(htmlspecialchars($dr['reason'])) ?></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center" style="padding:40px; background:var(--bg-card); border-radius:12px; border:1px solid var(--border-color);">
                        <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--success); margin-bottom:12px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <p style="color:var(--text-muted); font-size:1.1rem;">No disciplinary reports found for Sunday School members.</p>
                    </div>
                    <?php endif; ?>
                </div>
HTML;

$search = "        <?php endif; ?>\n\n        <?php require_once 'member_building_panels.php'; ?>";
// Notice we use literal characters to avoid php closing the outer script
$replacement = $ss_tab_content . "\n        <" . "?php endif; ?" . ">\n\n        <" . "?php require_once 'member_building_panels.php'; ?" . ">";

if (strpos($content, '$tab == \'manage_sunday_school\'') === false) {
    if (strpos($content, $search) !== false) {
        $content = str_replace($search, $replacement, $content);
        echo "Injected member manage_sunday_school tab correctly!\n";
    } else {
        echo "Error: Could not find the exact insertion point in member_dashboard.php!\n";
    }
}

// 2. Inject Sidebar Link into Leadership Panel
$sidebar_link = <<<'HTML'
                    <?php if ($member['church_role'] === 'Sunday School Patron'): ?>
                    <a href="?tab=manage_sunday_school" class="sidebar-link <?= $tab == 'manage_sunday_school' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        Manage Sunday School
                    </a>
                    <?php endif; ?>
HTML;

if (strpos($content, 'tab=manage_sunday_school') === false) {
    $search_str = '<!-- End Leadership Panel -->';
    if (($pos = strpos($content, $search_str)) !== false) {
        $content = substr_replace($content, $sidebar_link . "\n                ", $pos, 0);
    }
}

file_put_contents($file, $content);

// Update admin_dashboard.php and pastor_dashboard.php to add the password toggle if they don't have it
$files = ['C:\\xampp\\htdocs\\munyari_church\\admin_dashboard.php', 'C:\\xampp\\htdocs\\munyari_church\\pastor_dashboard.php'];

$old_pwd_input = '<input type="password" id="ss_password" name="password" class="form-control" placeholder="Min 6 chars" minlength="6" oninput="seqUnlock(\'ss_password\',\'ss_submitBtn\')" style="padding-left:36px;" disabled required>';

$new_pwd_input = <<<'HTML'
<input type="password" id="ss_password" name="password" class="form-control" placeholder="Min 6 chars" minlength="6" oninput="seqUnlock('ss_password','ss_submitBtn')" style="padding-left:36px; padding-right:40px;" disabled required>
                                    <button type="button" class="icon-btn" onclick="const pwd = document.getElementById('ss_password'); if (pwd.type === 'password') { pwd.type = 'text'; this.innerHTML = '<svg width=\'18\' height=\'18\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21\'></path></svg>'; } else { pwd.type = 'password'; this.innerHTML = '<svg width=\'18\' height=\'18\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M15 12a3 3 0 11-6 0 3 3 0 016 0z\'></path><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z\'></path></svg>'; }" style="position:absolute; right:5px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer;">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </button>
HTML;

foreach ($files as $f) {
    $c = file_get_contents($f);
    if (strpos($c, '<button type="button" class="icon-btn" onclick="const pwd') === false) {
        $c = str_replace($old_pwd_input, $new_pwd_input, $c);
        file_put_contents($f, $c);
        echo basename($f) . ": Added password toggle.\n";
    }
}
?>
