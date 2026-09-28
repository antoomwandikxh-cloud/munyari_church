<?php
session_start();
require_once 'db_connect.php';
require_once 'role_departments.php';

// Strip (subsidiary) for display
function clean_role_display($role) {
    return trim(preg_replace('/\s*\(subsidiary\)\s*/i', '', $role ?? ''));
}

if (!function_exists('dashboard_member_roles_detailed')) {
    function dashboard_member_roles_detailed($church_role_string) {
        $items = array_filter(array_map('trim', explode(',', str_replace('&', ',', $church_role_string ?? ''))));
        $roles = [];
        foreach ($items as $item) {
            $suffix = '';
            if (preg_match('/\((.+?)\)/', $item, $match)) {
                $suffix = trim($match[1]);
            }
            $roles[] = [
                'raw' => $item,
                'norm' => normalize_role_name($item),
                'suffix' => $suffix,
            ];
        }
        return $roles;
    }
}

if (!function_exists('dashboard_role_matches_leader_group')) {
    function dashboard_role_matches_leader_group($role, $member_department, $group_key) {
        $norm = $role['norm'] ?? '';
        $suffix = $role['suffix'] ?? '';
        if ($group_key === 'general') {
            return in_array($norm, ['general church secretary', 'vice church secretary', 'treasurer', 'senior church elder'], true);
        }
        if ($group_key === 'youths') {
            return str_contains($norm, 'youth') || in_array($norm, ['mama youth', 'baba youth'], true) || department_matches($suffix, 'Youths') || (department_matches($member_department, 'Youths') && is_subsidiary_department_role($norm));
        }
        if ($group_key === 'women') {
            return str_contains($norm, 'women') || department_matches($suffix, 'Womens Ministry') || (department_matches($member_department, 'Womens Ministry') && is_subsidiary_department_role($norm));
        }
        if ($group_key === 'elders') {
            return str_contains($norm, 'elder') || department_matches($suffix, 'Elders') || (department_matches($member_department, 'Elders') && is_subsidiary_department_role($norm));
        }
        if ($group_key === 'sunday_school') {
            return str_contains($norm, 'sunday school') || department_matches($suffix, 'Sunday School') || (department_matches($member_department, 'Sunday School') && is_subsidiary_department_role($norm));
        }
        return false;
    }
}

if (!function_exists('render_dashboard_leader_profiles_overview')) {
    function render_dashboard_leader_profiles_overview($conn) {
        $leader_sections = [
            'general' => [
                'title' => 'General Church Leaders',
                'accent' => '#10b981',
                'roles' => ['senior church elder', 'general church secretary', 'vice church secretary', 'treasurer'],
                'labels' => ['treasurer' => 'Church Treasurer'],
            ],
            'youths' => [
                'title' => 'Youth Leaders',
                'accent' => '#6366f1',
                'roles' => ['youth chairperson', 'youth chairman', 'youth chairlady', 'vice youth chairperson', 'vice youth chairman', 'vice youth chairlady', 'youth secretary', 'vice youth secretary', 'youth treasurer', 'mama youth', 'baba youth', 'organizing secretary', 'vice organizing secretary', 'discipline master', 'vice discipline master', 'prayer coordinator', 'vice prayer coordinator', 'choir leader', 'vice choir leader', 'sport secretary', 'sports secretary', 'vice sport secretary', 'vice sports secretary', 'graduands secretary', 'vice graduands secretary'],
            ],
            'women' => [
                'title' => 'Women Ministry Leaders',
                'accent' => '#ec4899',
                'roles' => ['women chairlady', 'women chairperson', 'women chairman', 'vice women chairlady', 'vice women chairperson', 'vice women chairman', 'women secretary', 'vice women secretary', 'women treasurer', 'organizing secretary', 'vice organizing secretary', 'discipline master', 'vice discipline master', 'prayer coordinator', 'vice prayer coordinator', 'choir leader', 'vice choir leader'],
            ],
            'elders' => [
                'title' => 'Elder Ministry Leaders',
                'accent' => '#f59e0b',
                'roles' => ['elder chairman', 'elder chairperson', 'elder chairlady', 'vice elder chairman', 'vice elder chairperson', 'vice elder chairlady', 'elder secretary', 'vice elder secretary', 'elder treasurer', 'organizing secretary', 'vice organizing secretary', 'discipline master', 'vice discipline master', 'prayer coordinator', 'vice prayer coordinator', 'choir leader', 'vice choir leader'],
            ],
            'sunday_school' => [
                'title' => 'Sunday School Leaders',
                'accent' => '#0ea5e9',
                'roles' => ['sunday school patron', 'sunday school chairperson', 'sunday school chairman', 'sunday school chairlady', 'vice sunday school patron', 'vice sunday school chairperson', 'vice sunday school chairman', 'vice sunday school chairlady', 'sunday school secretary', 'vice sunday school secretary', 'sunday school treasurer', 'organizing secretary', 'vice organizing secretary', 'discipline master', 'vice discipline master', 'prayer coordinator', 'vice prayer coordinator', 'choir leader', 'vice choir leader', 'sport secretary', 'sports secretary', 'vice sport secretary', 'vice sports secretary', 'graduands secretary', 'vice graduands secretary'],
            ],
        ];
        $leaders = [];
        $leader_result = $conn->query("SELECT id, first_name, last_name, gender, department, church_role, profile_picture, " . role_rank_case_sql('church_role') . " AS role_rank FROM members WHERE is_approved = 1 AND church_role IS NOT NULL AND TRIM(church_role) != '' AND LOWER(TRIM(church_role)) != 'member' ORDER BY role_rank ASC, first_name ASC, last_name ASC");
        if ($leader_result) {
            while ($row = $leader_result->fetch_assoc()) {
                $leaders[] = $row;
            }
        }
        ?>
        <div class="content-card" style="margin-top:24px;" id="leadersOverviewSection">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
                <div>
                    <p style="margin:0;color:var(--text-muted);font-size:0.78rem;text-transform:uppercase;font-weight:800;letter-spacing:0.05em;">Leadership Profiles</p>
                    <h2 style="margin:4px 0 0;color:var(--text-main);">Church Leaders Overview</h2>
                </div>
            </div>
            <?php foreach ($leader_sections as $section_key => $section): ?>
                <div style="margin-top:18px;">
                    <h3 style="margin:0 0 12px;color:var(--text-main);font-size:1.05rem;font-weight:850;border-left:4px solid <?= htmlspecialchars($section['accent']) ?>;padding-left:10px;"><?= htmlspecialchars($section['title']) ?></h3>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                        <?php
                        $shown = [];
                        $has_section_leaders = false;
                        foreach ($section['roles'] as $role_name):
                            foreach ($leaders as $leader):
                                foreach (dashboard_member_roles_detailed($leader['church_role'] ?? '') as $role):
                                    if ($role['norm'] !== $role_name || !dashboard_role_matches_leader_group($role, $leader['department'] ?? '', $section_key)) continue;
                                    $show_key = $leader['id'] . ':' . $section_key . ':' . $role_name;
                                    if (isset($shown[$show_key])) continue;
                                    $shown[$show_key] = true;
                                    $has_section_leaders = true;
                                    $role_label = $section['labels'][$role_name] ?? role_display_label(clean_role_display($role['norm']), $leader['department'] ?? '', $leader['gender'] ?? '');
                        ?>
                            <div class="leader-print-card"
                                 data-section="<?= htmlspecialchars($section['title']) ?>"
                                 data-section-color="<?= htmlspecialchars($section['accent']) ?>"
                                 data-name="<?= htmlspecialchars(ucfirst($leader['first_name']) . ' ' . ucfirst($leader['last_name'])) ?>"
                                 data-role="<?= htmlspecialchars($role_label) ?>"
                                 data-pic="uploads/<?= htmlspecialchars($leader['profile_picture'] ?? 'default_avatar.png') ?>"
                                 style="display:flex;align-items:center;gap:12px;padding:12px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-main);">
                                <img src="uploads/<?= htmlspecialchars($leader['profile_picture'] ?? 'default_avatar.png') ?>" alt="<?= htmlspecialchars($role_label) ?>" style="width:54px;height:54px;border-radius:50%;object-fit:cover;border:2px solid <?= htmlspecialchars($section['accent']) ?>;cursor:zoom-in;flex-shrink:0;" onclick="viewProfileImage(this.src);">
                                <div style="min-width:0;">
                                    <p style="margin:0 0 3px;color:var(--text-main);font-weight:750;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($leader['first_name'] . ' ' . $leader['last_name']) ?></p>
                                    <p style="margin:0;color:var(--text-muted);font-size:0.82rem;line-height:1.35;"><?= htmlspecialchars($role_label) ?></p>
                                </div>
                            </div>
                        <?php endforeach; endforeach; endforeach; ?>
                        <?php if (!$has_section_leaders): ?>
                            <p style="margin:0;color:var(--text-muted);">No leaders assigned yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
}
if (!function_exists('render_worship_leader_profiles_card')) {
    function render_worship_leader_profiles_card($conn) {
        $worship_role_labels = [
            'worship leader' => 'Worship Leader',
            'vice worship leader' => 'Vice Worship Leader',
        ];
        $worship_leaders = [];
        $worship_result = $conn->query("
            SELECT id, first_name, last_name, church_role, profile_picture
            FROM members
            WHERE is_approved = 1 AND LOWER(church_role) LIKE '%worship leader%'
            ORDER BY first_name ASC, last_name ASC
        ");
        if ($worship_result) {
            while ($row = $worship_result->fetch_assoc()) {
                $worship_leaders[] = $row;
            }
        }
        ?>
        <div class="content-card" style="margin-bottom:20px;border-left:4px solid #d946ef;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
                <div>
                    <p style="margin:0;color:var(--text-muted);font-size:0.78rem;text-transform:uppercase;font-weight:800;letter-spacing:0.05em;">Worship Leadership</p>
                    <h2 style="margin:4px 0 0;color:var(--text-main);">Worship Leader Profiles</h2>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                <?php
                $shown_worship_leaders = [];
                $has_worship_leaders = false;
                foreach ($worship_role_labels as $role_key => $role_label):
                    foreach ($worship_leaders as $worship_leader):
                        foreach (dashboard_member_roles_detailed($worship_leader['church_role'] ?? '') as $worship_role):
                            if (($worship_role['norm'] ?? '') !== $role_key) continue;
                            $shown_key = $worship_leader['id'] . ':' . $role_key;
                            if (isset($shown_worship_leaders[$shown_key])) continue;
                            $shown_worship_leaders[$shown_key] = true;
                            $has_worship_leaders = true;
                ?>
                    <div style="display:flex;align-items:center;gap:12px;padding:12px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-main);">
                        <img src="uploads/<?= htmlspecialchars($worship_leader['profile_picture'] ?? 'default_avatar.png') ?>" alt="<?= htmlspecialchars($role_label) ?>" style="width:58px;height:58px;border-radius:50%;object-fit:cover;border:2px solid #d946ef;cursor:zoom-in;flex-shrink:0;" onclick="viewProfileImage(this.src);">
                        <div style="min-width:0;">
                            <p style="margin:0 0 3px;color:var(--text-main);font-weight:750;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($worship_leader['first_name'] . ' ' . $worship_leader['last_name']) ?></p>
                            <p style="margin:0;color:var(--text-muted);font-size:0.82rem;line-height:1.35;"><?= htmlspecialchars($role_label) ?></p>
                        </div>
                    </div>
                <?php endforeach; endforeach; endforeach; ?>
                <?php if (!$has_worship_leaders): ?>
                    <p style="margin:0;color:var(--text-muted);">No worship leader has been assigned yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
if (!function_exists('render_building_leader_profiles_card')) {
    function render_building_leader_profiles_card($conn) {
        $building_role_labels = [
            'building chairperson' => 'Building Chairperson',
            'building chairman' => 'Building Chairman',
            'building chairlady' => 'Building Chairlady',
            'vice building chairperson' => 'Vice Building Chairperson',
            'vice building chairman' => 'Vice Building Chairman',
            'vice building chairlady' => 'Vice Building Chairlady',
            'building secretary' => 'Building Secretary',
            'vice building secretary' => 'Vice Building Secretary',
            'building treasurer' => 'Building Treasurer',
        ];
        $building_leaders = [];
        $building_result = $conn->query("
            SELECT id, first_name, last_name, gender, church_role, profile_picture
            FROM members
            WHERE is_approved = 1 AND LOWER(church_role) LIKE '%building%'
            ORDER BY " . role_rank_case_sql('church_role') . " ASC, first_name ASC, last_name ASC
        ");
        if ($building_result) {
            while ($row = $building_result->fetch_assoc()) {
                $building_leaders[] = $row;
            }
        }
        ?>
        <div class="content-card" style="margin-bottom:24px;border-left:4px solid #ef4444;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
                <div>
                    <p style="margin:0;color:var(--text-muted);font-size:0.78rem;text-transform:uppercase;font-weight:800;letter-spacing:0.05em;">Building Leadership</p>
                    <h2 style="margin:4px 0 0;color:var(--text-main);">Building Department Leader Profiles</h2>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                <?php
                $shown_building_leaders = [];
                $has_building_leaders = false;
                foreach ($building_role_labels as $role_key => $role_label):
                    foreach ($building_leaders as $building_leader):
                        foreach (dashboard_member_roles_detailed($building_leader['church_role'] ?? '') as $building_role):
                            if (($building_role['norm'] ?? '') !== $role_key) continue;
                            $shown_key = $building_leader['id'] . ':' . $role_key;
                            if (isset($shown_building_leaders[$shown_key])) continue;
                            $shown_building_leaders[$shown_key] = true;
                            $has_building_leaders = true;
                            if (in_array($role_key, ['building chairperson', 'building chairman', 'building chairlady', 'vice building chairperson', 'vice building chairman', 'vice building chairlady'], true)) {
                                $role_label = get_building_chairperson_title($building_leader['gender'] ?? '', $role_key);
                            }
                ?>
                    <div style="display:flex;align-items:center;gap:12px;padding:12px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-main);">
                        <img src="uploads/<?= htmlspecialchars($building_leader['profile_picture'] ?? 'default_avatar.png') ?>" alt="<?= htmlspecialchars($role_label) ?>" style="width:58px;height:58px;border-radius:50%;object-fit:cover;border:2px solid #ef4444;cursor:zoom-in;flex-shrink:0;" onclick="viewProfileImage(this.src);">
                        <div style="min-width:0;">
                            <p style="margin:0 0 3px;color:var(--text-main);font-weight:750;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($building_leader['first_name'] . ' ' . $building_leader['last_name']) ?></p>
                            <p style="margin:0;color:var(--text-muted);font-size:0.82rem;line-height:1.35;"><?= htmlspecialchars($role_label) ?></p>
                        </div>
                    </div>
                <?php endforeach; endforeach; endforeach; ?>
                <?php if (!$has_building_leaders): ?>
                    <p style="margin:0;color:var(--text-muted);">No building leaders have been assigned yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
require_once 'notification_badges.php';
if (!isset($_SESSION['pastor_id'])) { header("Location: login.php"); exit(); }

$pastor_id = $_SESSION['pastor_id'];
$pastor_name = $_SESSION['pastor_name'];
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

// ── Early PRG handler: assign_village_leader ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_village_leader'])) {
    $l_id   = (int)$_POST['leader_id'];
    $v_name = $conn->real_escape_string($_POST['village']);
    
    $check_existing = $conn->query("SELECT id FROM members WHERE church_village = '$v_name' AND is_village_leader = 1 LIMIT 1");
    if ($check_existing && $check_existing->num_rows > 0) {
        $r = basename($_SERVER['PHP_SELF']);
        header("Location: " . $r . "?tab=desired_roles&error=village_has_leader&vname=" . urlencode($v_name));
        exit();
    }
    
    $mem_q = $conn->query("SELECT church_role FROM members WHERE id = $l_id")->fetch_assoc();
    if ($mem_q) {
        $roles = array_values(array_filter(array_map('trim', explode(',', $mem_q['church_role'] ?? ''))));
        if (!in_array('Church Village Leader', $roles)) { $roles[] = 'Church Village Leader'; }
        $new_role = $conn->real_escape_string(implode(', ', $roles));
        $conn->query("UPDATE members SET church_village = '$v_name', is_village_leader = 1, church_role = '$new_role' WHERE id = $l_id");
        $nmsg = $conn->real_escape_string("You have been assigned as the Church Village Leader for $v_name.");
        $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($l_id, 'member', '$nmsg')");
    }
    $r = basename($_SERVER['PHP_SELF']);
    header("Location: " . $r . "?tab=desired_roles&success=village_leader_assigned&vname=" . urlencode($v_name));
    exit();
}
// ──────────────────────────────────────────────────────────────────────────


$pastor = $conn->query("SELECT * FROM pastors WHERE id = $pastor_id")->fetch_assoc();

// Ensure required tables exist
$conn->query("
    CREATE TABLE IF NOT EXISTS chats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        chat_type VARCHAR(50) NOT NULL,
        message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$conn->query("
    CREATE TABLE IF NOT EXISTS building_announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        building_id INT NOT NULL,
        message TEXT,
        image_path VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$conn->query("
    CREATE TABLE IF NOT EXISTS pastor_announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pastor_id INT NOT NULL,
        message TEXT NOT NULL,
        image_path VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$conn->query("
    CREATE TABLE IF NOT EXISTS pastor_leader_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dept_key VARCHAR(60) NOT NULL,
        pastor_id INT NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$conn->query("
    CREATE TABLE IF NOT EXISTS sunday_school_class_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        current_class VARCHAR(100) NULL,
        requested_class VARCHAR(100) NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'Pending',
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        reviewed_by_type VARCHAR(30) NULL,
        reviewed_by_id INT NULL,
        reviewed_at DATETIME NULL
    )
");
$conn->query("
    CREATE TABLE IF NOT EXISTS sunday_school_class_leaders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_name VARCHAR(100) NOT NULL,
        leader_id INT NOT NULL,
        assigned_by_type VARCHAR(30) NOT NULL,
        assigned_by_id INT NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'Pending',
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        reviewed_by_type VARCHAR(30) NULL,
        reviewed_by_id INT NULL,
        reviewed_at DATETIME NULL
    )
");
$conn->query("
    CREATE TABLE IF NOT EXISTS sunday_school_attendance_registers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_name VARCHAR(100) NOT NULL,
        teacher_id INT NOT NULL,
        attendance_date DATE NOT NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");
$conn->query("
    CREATE TABLE IF NOT EXISTS sunday_school_attendance_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        register_id INT NOT NULL,
        member_id INT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Absent',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

if (!function_exists('ss_next_class')) {
    function ss_next_class($class) {
        $progression = [
            'Little Angels' => 'Champions',
            'Champions' => 'Battalion',
            'Battalion' => 'Conquerors',
            'Conquerors' => 'Youths (Department)',
        ];
        return $progression[$class] ?? null;
    }
}

if (!function_exists('ss_class_list')) {
    function ss_class_list() {
        return ['Little Angels', 'Champions', 'Battalion', 'Conquerors'];
    }
}

if (!function_exists('ss_class_rank')) {
    function ss_class_rank($class) {
        $ranks = [
            'Little Angels' => 1,
            'Champions' => 2,
            'Battalion' => 3,
            'Conquerors' => 4,
        ];
        return $ranks[$class] ?? 0;
    }
}

if (!function_exists('ss_can_teach_class')) {
    function ss_can_teach_class($member_department, $member_class, $target_class) {
        if ($member_department !== 'Sunday School') {
            return true;
        }
        return ss_class_rank($member_class) > ss_class_rank($target_class);
    }
}

// Notifications
$unread_notifs = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $pastor_id AND user_type = 'pastor' AND is_read = 0")->fetch_assoc()['count'];
$notifications = $conn->query("SELECT * FROM notifications WHERE user_id = $pastor_id AND user_type = 'pastor' ORDER BY created_at DESC LIMIT 20");
$tab_badges = build_tab_notification_badges($conn, $pastor_id, 'pastor', $tab);

// Members Data
$members = $conn->query("SELECT * FROM members WHERE is_approved != -2 ORDER BY is_approved ASC, first_name ASC");
$approved_members = $conn->query("SELECT * FROM members WHERE is_approved = 1 ORDER BY first_name ASC");

// Appointments
$appointments = $conn->query("
    SELECT a.*, m.first_name, m.last_name 
    FROM appointments a 
    JOIN members m ON a.member_id = m.id 
    WHERE a.pastor_id = $pastor_id 
    ORDER BY a.appointment_date ASC
");

// Pastor Content
$current_daily_message = $conn->query("SELECT * FROM daily_messages WHERE pastor_id = $pastor_id ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
$highlights = $conn->query("SELECT * FROM church_highlights WHERE pastor_id = $pastor_id ORDER BY created_at DESC");

$has_username = !empty($pastor['username']);
$has_profile_pic = ($pastor['profile_picture'] !== 'default_avatar.png' && !empty($pastor['profile_picture']));
$has_village = !empty($pastor['church_village']);
$setup_completed = ($pastor['setup_completed'] == 1);

if (!$setup_completed && $has_username && $has_profile_pic && $has_village) {
    $conn->query("UPDATE pastors SET setup_completed = 1 WHERE id = $pastor_id");
    $pastor['setup_completed'] = 1;
    $setup_completed = true;
}
if (!$setup_completed && $tab !== 'settings') {
    header("Location: ?tab=settings");
    exit();
}

// Handle Username Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_username'])) {
    $nu = $conn->real_escape_string(trim($_POST['new_username']));
    if (strlen($nu) < 3) {
        header("Location: ?tab=settings&error=Username must be at least 3 characters"); exit();
    }
    // Check if taken
    $taken1 = $conn->query("SELECT id FROM members WHERE BINARY username = '$nu'")->num_rows;
    $taken2 = $conn->query("SELECT id FROM pastors WHERE BINARY username = '$nu' AND id != $pastor_id")->num_rows;
    if ($taken1 > 0 || $taken2 > 0) {
        header("Location: ?tab=settings&error=Username already taken"); exit();
    }
    $conn->query("UPDATE pastors SET username = '$nu' WHERE id = $pastor_id");
    header("Location: ?tab=settings&success=Username updated"); exit();
}

// Handle Profile Picture
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_picture'])) {
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed = ['jpg','jpeg','png','gif'];
        $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $new_name = 'pastor_' . $pastor_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], 'uploads/' . $new_name)) {
                $conn->query("UPDATE pastors SET profile_picture = '$new_name' WHERE id = $pastor_id");
                header("Location: ?tab=settings&success=Profile picture updated"); exit();
            }
        } else {
            header("Location: ?tab=settings&error=Invalid image format"); exit();
        }
    }
}

// Handle Church Village & Desired Role
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_church_village'])) {
    $cv  = $conn->real_escape_string(trim($_POST['church_village'] ?? ''));
    $drp = $conn->real_escape_string(trim($_POST['desired_role_pref'] ?? ''));
    $valid_villages = ['Akoritho', 'Philadelphia', 'Bethsaida'];
    if (!in_array($cv, $valid_villages)) {
        header("Location: ?tab=settings&error=Please select a valid church village");
        exit();
    }
    $valid_roles = ['Worshipper', 'Church Cleaner', 'Church Cooker', ''];
    if (!in_array($drp, $valid_roles)) { $drp = ''; }
    $conn->query("UPDATE pastors SET church_village = '$cv', desired_role_pref = '$drp' WHERE id = $pastor_id");
    header("Location: ?tab=settings&success=Village and Role saved");
    exit();
}

// Handle Sunday School member registration (must be before HTML output)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_ss_member'])) {
    $fn       = $conn->real_escape_string(trim($_POST['first_name'] ?? ''));
    $ln       = $conn->real_escape_string(trim($_POST['last_name'] ?? ''));
    $ph       = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
    $gn       = in_array($_POST['gender'] ?? '', ['Male','Female']) ? $_POST['gender'] : 'Male';
    $ad       = $conn->real_escape_string(trim($_POST['address'] ?? ''));
    $cl       = in_array($_POST['ss_class'] ?? '', ['Little Angels','Champions','Battalion','Conquerors']) ? $_POST['ss_class'] : '';
    $pw_plain = trim($_POST['password'] ?? '');
    $pw_conf  = trim($_POST['confirm_password'] ?? '');
    if (empty($fn) || empty($ln) || empty($cl) || empty($pw_plain) || empty($ad)) {
        header("Location: ?tab=manage_sunday_school&error=" . urlencode("First name, last name, address, class and password are required."));
        exit();
    }
    if ($pw_plain !== $pw_conf) {
        header("Location: ?tab=manage_sunday_school&error=" . urlencode("Passwords do not match."));
        exit();
    }
    if (!empty($ph)) {
        $dup = $conn->query("SELECT id FROM members WHERE phone = '$ph'")->num_rows;
        if ($dup > 0) {
            header("Location: ?tab=manage_sunday_school&error=" . urlencode("A member with that phone number already exists."));
            exit();
        }
    }
    $pw      = password_hash($pw_plain, PASSWORD_DEFAULT);
    $cl_safe = $conn->real_escape_string($cl);
    $conn->query("INSERT INTO members (first_name, last_name, phone, address, department, gender, password, sunday_school_class, is_approved, reg_date) VALUES ('$fn','$ln','$ph','$ad','Sunday School','$gn','$pw','$cl_safe',1,NOW())");
    $new_mid = $conn->insert_id;
    if ($new_mid) {
        $welcome = $conn->real_escape_string("Welcome to Sunday School! You have been registered in the $cl class.");
        $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($new_mid, 'member', '$welcome', 0, NOW())");
        $nmsg = $conn->real_escape_string("$fn $ln has been registered as a new Sunday School member in the $cl class.");
        $ar = $conn->query("SELECT id FROM admins");
        while ($a = $ar->fetch_assoc()) { $aid=(int)$a['id']; $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($aid,'admin','$nmsg',0,NOW())"); }
        $pr = $conn->query("SELECT id FROM pastors");
        while ($p = $pr->fetch_assoc()) { $pid=(int)$p['id']; $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($pid,'pastor','$nmsg',0,NOW())"); }
    }
    header("Location: ?tab=manage_sunday_school&success=" . urlencode("Sunday School member $fn $ln registered successfully!"));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_ss_class_leader'])) {
    $class_name = trim($_POST['class_name'] ?? '');
    $leader_id = (int)($_POST['leader_id'] ?? 0);
    if (!in_array($class_name, ss_class_list(), true)) {
        header("Location: ?tab=manage_sunday_school&error=" . urlencode("Please select a valid Sunday School class."));
        exit();
    }
    $leader = $conn->query("SELECT id, first_name, last_name, department, sunday_school_class FROM members WHERE id = $leader_id AND is_approved = 1 LIMIT 1")->fetch_assoc();
    if (!$leader) {
        header("Location: ?tab=manage_sunday_school&error=" . urlencode("Please select an approved church member."));
        exit();
    }
    if (!ss_can_teach_class($leader['department'] ?? '', $leader['sunday_school_class'] ?? '', $class_name)) {
        header("Location: ?tab=manage_sunday_school&error=" . urlencode("A Sunday School member can teach only classes below their own class."));
        exit();
    }
    $class_safe = $conn->real_escape_string($class_name);
    $conn->query("UPDATE sunday_school_class_leaders SET status = 'Replaced', reviewed_by_type = 'pastor', reviewed_by_id = $pastor_id, reviewed_at = NOW() WHERE class_name = '$class_safe' AND status = 'Active'");
    $conn->query("INSERT INTO sunday_school_class_leaders (class_name, leader_id, assigned_by_type, assigned_by_id, status, requested_at, reviewed_by_type, reviewed_by_id, reviewed_at) VALUES ('$class_safe', $leader_id, 'pastor', $pastor_id, 'Active', NOW(), 'pastor', $pastor_id, NOW())");
    $notice = $conn->real_escape_string("You have been assigned as Sunday School teacher for the $class_name class. Open Manage Sunday Classes.");
    $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($leader_id, 'member', '$notice', 0, NOW())");
    $leader_name = trim(($leader['first_name'] ?? '') . ' ' . ($leader['last_name'] ?? ''));
    header("Location: ?tab=manage_sunday_school&success=" . urlencode("$leader_name assigned as $class_name class teacher."));
    exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'transfer_youth' && isset($_GET['id']) && isset($_GET['dest'])) {
    $y_id = (int)$_GET['id'];
    $dest = $conn->real_escape_string($_GET['dest']);
    $conn->query("UPDATE members SET department = '$dest', church_role = 'Member' WHERE id = $y_id");
    $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($y_id, 'member', 'You have been transferred to the $dest department.', 0, NOW())");
    header("Location: ?tab=departments&success=" . urlencode("Member transferred to $dest"));
    exit();
}

if (isset($_GET['action']) && in_array($_GET['action'], ['approve_ss_class_request', 'reject_ss_class_request', 'promote_ss_member', 'approve_ss_leader_request', 'reject_ss_leader_request'], true)) {
    if (in_array($_GET['action'], ['approve_ss_leader_request', 'reject_ss_leader_request'], true)) {
        $request_id = (int)($_GET['id'] ?? 0);
        $request = $conn->query("
            SELECT scl.*, m.first_name, m.last_name, m.department, m.sunday_school_class
            FROM sunday_school_class_leaders scl
            JOIN members m ON scl.leader_id = m.id
            WHERE scl.id = $request_id AND scl.status = 'Pending'
            LIMIT 1
        ")->fetch_assoc();
        if (!$request) {
            header("Location: ?tab=manage_sunday_school&error=" . urlencode("Class leader request was not found or already reviewed."));
            exit();
        }
        $class_safe = $conn->real_escape_string($request['class_name']);
        $leader_id = (int)$request['leader_id'];
        $leader_name = trim(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? ''));
        if ($_GET['action'] === 'approve_ss_leader_request') {
            if (!ss_can_teach_class($request['department'] ?? '', $request['sunday_school_class'] ?? '', $request['class_name'])) {
                $conn->query("UPDATE sunday_school_class_leaders SET status = 'Rejected', reviewed_by_type = 'pastor', reviewed_by_id = $pastor_id, reviewed_at = NOW() WHERE id = $request_id");
                header("Location: ?tab=manage_sunday_school&error=" . urlencode("Request rejected because a Sunday School member can teach only classes below their own class."));
                exit();
            }
            $conn->query("UPDATE sunday_school_class_leaders SET status = 'Replaced', reviewed_by_type = 'pastor', reviewed_by_id = $pastor_id, reviewed_at = NOW() WHERE class_name = '$class_safe' AND status = 'Active'");
            $conn->query("UPDATE sunday_school_class_leaders SET status = 'Active', reviewed_by_type = 'pastor', reviewed_by_id = $pastor_id, reviewed_at = NOW() WHERE id = $request_id");
            $notice = $conn->real_escape_string("You have been assigned as Sunday School teacher for the {$request['class_name']} class. Open Manage Sunday Classes.");
            $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($leader_id, 'member', '$notice', 0, NOW())");
            header("Location: ?tab=manage_sunday_school&success=" . urlencode("$leader_name approved as {$request['class_name']} class teacher."));
            exit();
        }
        $conn->query("UPDATE sunday_school_class_leaders SET status = 'Rejected', reviewed_by_type = 'pastor', reviewed_by_id = $pastor_id, reviewed_at = NOW() WHERE id = $request_id");
        header("Location: ?tab=manage_sunday_school&success=" . urlencode("$leader_name class leader request rejected."));
        exit();
    }
    if ($_GET['action'] === 'promote_ss_member') {
        $target_member_id = (int)($_GET['id'] ?? 0);
        $target = $conn->query("SELECT id, first_name, last_name, sunday_school_class FROM members WHERE id = $target_member_id AND department = 'Sunday School' AND is_approved = 1 LIMIT 1")->fetch_assoc();
        if (!$target) {
            header("Location: ?tab=manage_sunday_school&error=" . urlencode("Sunday School member was not found."));
            exit();
        }

        $current_class = trim($target['sunday_school_class'] ?? '');
        $next_class = ss_next_class($current_class);
        if (!$next_class) {
            header("Location: ?tab=manage_sunday_school&error=" . urlencode("This member cannot be transferred further in the Sunday School class order."));
            exit();
        }

                if ($next_class === 'Youths (Department)') {
            $conn->query("UPDATE members SET department = 'Youths', sunday_school_class = NULL, church_role = 'Member' WHERE id = $target_member_id");
            $conn->query("UPDATE sunday_school_class_requests SET status = 'Rejected' WHERE member_id = $target_member_id AND status = 'Pending'");
            $notice = $conn->real_escape_string("You have graduated from Sunday School and have been transferred to the Youths department.");
            $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($target_member_id, 'member', '$notice', 0, NOW())");
        } else {
            $next_safe = $conn->real_escape_string($next_class);
            $conn->query("UPDATE members SET sunday_school_class = '$next_safe' WHERE id = $target_member_id");
            $conn->query("UPDATE sunday_school_class_requests SET status = CASE WHEN requested_class = '$next_safe' THEN 'Approved' ELSE 'Rejected' END, reviewed_by_type = 'pastor', reviewed_by_id = $pastor_id, reviewed_at = NOW() WHERE member_id = $target_member_id AND status = 'Pending'");
            $notice = $conn->real_escape_string("Sunday School class approval: your Sunday School class has been changed from $current_class to $next_class.");
            $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($target_member_id, 'member', '$notice', 0, NOW())");
        }
        $target_name = trim(($target['first_name'] ?? '') . ' ' . ($target['last_name'] ?? ''));
        header("Location: ?tab=manage_sunday_school&success=" . urlencode("$target_name transferred from $current_class to $next_class."));
        exit();
    }

    $request_id = (int)($_GET['id'] ?? 0);
    $request = $conn->query("
        SELECT r.*, m.first_name, m.last_name, m.sunday_school_class AS actual_current_class
        FROM sunday_school_class_requests r
        JOIN members m ON r.member_id = m.id
        WHERE r.id = $request_id AND r.status = 'Pending'
        LIMIT 1
    ")->fetch_assoc();

    if (!$request) {
        header("Location: ?tab=manage_sunday_school&error=" . urlencode("Class change request was not found or already reviewed."));
        exit();
    }

    $target_member_id = (int)$request['member_id'];
    $requested_class = $conn->real_escape_string($request['requested_class']);
    $member_name = trim(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? ''));

    if ($_GET['action'] === 'approve_ss_class_request') {
        $actual_current_class = trim($request['actual_current_class'] ?? '');
        if (ss_next_class($actual_current_class) !== $request['requested_class']) {
            header("Location: ?tab=manage_sunday_school&error=" . urlencode("This request does not follow the Sunday School class order."));
            exit();
        }
        $conn->query("UPDATE members SET sunday_school_class = '$requested_class' WHERE id = $target_member_id");
        $conn->query("UPDATE sunday_school_class_requests SET status = 'Approved', reviewed_by_type = 'pastor', reviewed_by_id = $pastor_id, reviewed_at = NOW() WHERE id = $request_id");
        $notice = $conn->real_escape_string("Sunday School class approval: your Sunday School class has been changed to {$request['requested_class']}.");
        $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($target_member_id, 'member', '$notice', 0, NOW())");
        header("Location: ?tab=manage_sunday_school&success=" . urlencode("$member_name class change approved."));
        exit();
    }

    $conn->query("UPDATE sunday_school_class_requests SET status = 'Rejected', reviewed_by_type = 'pastor', reviewed_by_id = $pastor_id, reviewed_at = NOW() WHERE id = $request_id");
    $notice = $conn->real_escape_string("Sunday School class approval: your Sunday School class change request to {$request['requested_class']} was rejected.");
    $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($target_member_id, 'member', '$notice', 0, NOW())");
    header("Location: ?tab=manage_sunday_school&success=" . urlencode("$member_name class change rejected."));
    exit();
}

// Handle Member Edit (by Pastor/Admin)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_member_submit'])) {
    $m_id    = (int)($_POST['edit_member_id'] ?? 0);
    $m_fname = trim($_POST['first_name'] ?? '');
    $m_lname = trim($_POST['last_name'] ?? '');
    $m_uname = trim($_POST['username'] ?? '');
    $m_phone = trim($_POST['phone'] ?? '');
    $m_addr  = trim($_POST['address'] ?? '');

    // Server-side validation: names must be letters and spaces only
    if (!preg_match('/^[A-Za-z\s]+$/', $m_fname) || !preg_match('/^[A-Za-z\s]+$/', $m_lname)) {
        header("Location: ?tab=manage_members&error=" . urlencode("Names must contain letters only."));
        exit();
    }
    // Phone must be exactly 10 digits
    if (!preg_match('/^\d{10}$/', $m_phone)) {
        header("Location: ?tab=manage_members&error=" . urlencode("Phone number must be exactly 10 digits."));
        exit();
    }
    if ($m_id < 1) {
        header("Location: ?tab=manage_members&error=" . urlencode("Invalid member ID."));
        exit();
    }

    $m_fname_s = $conn->real_escape_string($m_fname);
    $m_lname_s = $conn->real_escape_string($m_lname);
    $m_uname_s = $conn->real_escape_string($m_uname);
    $m_phone_s = $conn->real_escape_string($m_phone);
    $m_addr_s  = $conn->real_escape_string($m_addr);

    // Check duplicate username
    $chk = $conn->query("SELECT id FROM members WHERE username='$m_uname_s' AND id != $m_id LIMIT 1");
    if ($chk && $chk->num_rows > 0) {
        header("Location: ?tab=manage_members&error=" . urlencode("Username is already taken by another member."));
        exit();
    }

    $conn->query("UPDATE members SET first_name='$m_fname_s', last_name='$m_lname_s', username='$m_uname_s', phone='$m_phone_s', address='$m_addr_s' WHERE id=$m_id");
    header("Location: ?tab=manage_members&success=" . urlencode("Member details updated successfully."));
    exit();
}
// Handle Register Member
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_member'])) {

    $fn  = $conn->real_escape_string(trim($_POST['first_name']));
    $ln  = $conn->real_escape_string(trim($_POST['last_name']));
    $ph  = $conn->real_escape_string(trim($_POST['phone']));
    $ad  = $conn->real_escape_string(trim($_POST['address'] ?? ''));
    $dp_raw = trim($_POST['department'] ?? 'Youths');
    $dp  = $conn->real_escape_string($dp_raw);
    // Auto-derive mandatory gender from department where required.
    if ($dp_raw === 'Womens Ministry') {
        $gn = 'Female';
    } elseif ($dp_raw === 'Elders') {
        $gn = 'Male';
    } else {
        $gn = $conn->real_escape_string(trim($_POST['gender'] ?? 'Male'));
    }
    $pw  = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
    // Validate required fields
    if (empty($fn) || empty($ln) || empty($ph) || empty($_POST['password'])) {
        header("Location: pastor_dashboard.php?tab=manage_members&error=First name, last name, phone and password are required");
        exit();
    }
    if ($_POST['password'] !== ($_POST['confirm_password'] ?? '')) {
        header("Location: pastor_dashboard.php?tab=manage_members&error=Passwords do not match");
        exit();
    }
    
    // Check duplicate phone in both members and pastors
    $dup_member = $conn->query("SELECT id FROM members WHERE phone = '$ph'")->num_rows;
    $dup_pastor = $conn->query("SELECT id FROM pastors WHERE phone = '$ph'")->num_rows;
    if ($dup_member > 0 || $dup_pastor > 0) {
        header("Location: pastor_dashboard.php?tab=manage_members&error=" . urlencode("That number is already registered. Please use a different number."));
        exit();
    }
    
    $conn->query("INSERT INTO members (first_name, last_name, phone, address, department, gender, password, is_approved, reg_date) VALUES ('$fn','$ln','$ph','$ad','$dp','$gn','$pw',1,NOW())");
    $new_id = $conn->insert_id;
    
    // Send welcome notification to the new member
    $welcome_msg = $conn->real_escape_string("Welcome to Munyari Church, $fn $ln! Your account has been created and approved by the Pastor.");
    $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($new_id, 'member', '$welcome_msg')");
    
    header("Location: pastor_dashboard.php?tab=manage_members&success=Member $fn $ln registered successfully");
    exit();
}

// Handle Delete Member
if (isset($_GET['action']) && $_GET['action'] == 'delete_member' && isset($_GET['id'])) {
    $del_id = (int)$_GET['id'];
    $conn->query("DELETE FROM members WHERE id = $del_id");
    header("Location: pastor_dashboard.php?tab=manage_members&success=Member deleted");
    exit();
}

// Handle Deactivate Member
if (isset($_GET['action']) && $_GET['action'] == 'deactivate_member' && isset($_GET['id'])) {
    $dec_id = (int)$_GET['id'];
    $conn->query("UPDATE members SET is_approved = -1 WHERE id = $dec_id");
    header("Location: pastor_dashboard.php?tab=manage_members&success=Member deactivated");
    exit();
}

// Handle Activate Member
if (isset($_GET['action']) && $_GET['action'] == 'activate_member' && isset($_GET['id'])) {
    $act_id = (int)$_GET['id'];
    $conn->query("UPDATE members SET is_approved = 1, decline_reason = NULL WHERE id = $act_id");
    $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($act_id, 'member', 'Your account has been approved by the pastor. Welcome to Munyari Church!')");
    header("Location: pastor_dashboard.php?tab=manage_members&success=Member activated");
    exit();
}

// Handle Decline Member (new registration rejection with reason)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decline_member_submit'])) {
    $dec_id     = (int)($_POST['decline_member_id'] ?? 0);
    $dec_reason = trim($conn->real_escape_string($_POST['decline_reason'] ?? ''));
    if ($dec_id > 0 && $dec_reason !== '') {
        $conn->query("UPDATE members SET is_approved = -2, decline_reason = '$dec_reason' WHERE id = $dec_id AND is_approved = 0");
        $safe_reason = $conn->real_escape_string("Your registration has been declined by the pastor. Reason: $dec_reason");
        $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($dec_id, 'member', '$safe_reason')");
        
        $location = basename($_SERVER['PHP_SELF']);
        header("Location: " . $location . "?tab=manage_members&success=Member registration declined");
    } else {
        $location = basename($_SERVER['PHP_SELF']);
        header("Location: " . $location . "?tab=manage_members&error=Please provide a decline reason");
    }
    exit();
}

// Handle Send Message inline
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $member_id = (int)$_POST['member_id'];
    $msg = $conn->real_escape_string($_POST['message_content']);
    $conn->query("INSERT INTO member_messages (member_id, pastor_name, message) VALUES ($member_id, '$pastor_name', '$msg')");
    header("Location: pastor_dashboard.php?tab=messages&success=Message Sent");
    exit();
}

// Handle Mark All as Read
if (isset($_GET['action']) && $_GET['action'] == 'mark_all_read') {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $pastor_id AND user_type = 'pastor'");
    header("Location: pastor_dashboard.php");
    exit();
}

// Handle Delete All Notifications
if (isset($_GET['action']) && $_GET['action'] == 'delete_all_notifications') {
    $conn->query("DELETE FROM notifications WHERE user_id = $pastor_id AND user_type = 'pastor'");
    header("Location: pastor_dashboard.php");
    exit();
}

// Handle Password Change inline
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    if ($_POST['new_password'] === $_POST['confirm_password']) {
        $hashed = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $conn->query("UPDATE pastors SET password = '$hashed' WHERE id = $pastor_id");
        header("Location: pastor_dashboard.php?tab=settings&success=Password Updated");
        exit();
    } else {
        header("Location: pastor_dashboard.php?tab=settings&error=Passwords do not match");
        exit();
    }
}
// Handle Department Transfers
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['handle_transfer'])) {
    $member_id = (int)$_POST['member_id'];
    $action = $_POST['action']; // 'approve' or 'reject'
    
    if ($action === 'approve') {
        // We get the requested department from the DB to be safe
        $mem = $conn->query("SELECT pending_department, church_role FROM members WHERE id = $member_id")->fetch_assoc();
        if ($mem && $mem['pending_department']) {
            $new_dept = $conn->real_escape_string($mem['pending_department']);
            
            $current_role = $mem['church_role'];
            $role_update_sql = "";
            if (!empty($current_role) && $current_role !== 'Member') {
                if (!is_general_church_role($current_role)) {
                    // Reset to Member if it was a department-specific role
                    $role_update_sql = ", church_role = 'Member'";
                }
            }
            
            $conn->query("UPDATE members SET department = '$new_dept', pending_department = NULL $role_update_sql WHERE id = $member_id");
            header("Location: pastor_dashboard.php?tab=departments&success=Transfer approved successfully");
            exit();
        }
    } elseif ($action === 'reject') {
        $conn->query("UPDATE members SET pending_department = NULL WHERE id = $member_id");
        header("Location: pastor_dashboard.php?tab=departments&success=Transfer request rejected");
        exit();
    }
}

// Handle Daily Message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_daily_message'])) {
    $quote = $conn->real_escape_string($_POST['quote']);
    $video_name = null;
    if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['mp4', 'webm', 'ogg'])) {
            $video_name = 'daily_vid_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['video_file']['tmp_name'], 'uploads/' . $video_name);
        }
    }
    $conn->query("DELETE FROM daily_messages WHERE pastor_id = $pastor_id");
    $conn->query("INSERT INTO daily_messages (pastor_id, quote, video_file) VALUES ($pastor_id, '$quote', " . ($video_name ? "'$video_name'" : "NULL") . ")");
    
    // Notify all members
    $members_result = $conn->query("SELECT id FROM members");
    while($mem = $members_result->fetch_assoc()) {
        $m_id = $mem['id'];
        $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($m_id, 'member', 'Pastor $pastor_name has posted a new Daily Quote!')");
    }

    header("Location: pastor_dashboard.php?tab=inspiration_highlights&success=Daily Quote Posted");
    exit();
}

// Handle Upload Highlight
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_highlight'])) {
    $caption = $conn->real_escape_string($_POST['caption']);
    if (isset($_FILES['highlight_image']) && $_FILES['highlight_image']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['highlight_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm', 'ogg'])) {
            $img_name = 'highlight_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['highlight_image']['tmp_name'], 'uploads/' . $img_name)) {
                $conn->query("INSERT INTO church_highlights (pastor_id, image_file, caption) VALUES ($pastor_id, '$img_name', '$caption')");
                
                // Notify all members
                $members_result = $conn->query("SELECT id FROM members");
                while($mem = $members_result->fetch_assoc()) {
                    $m_id = $mem['id'];
                    $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($m_id, 'member', 'Pastor $pastor_name has uploaded a new Church Highlight!')");
                }

                header("Location: pastor_dashboard.php?tab=inspiration_highlights&success=Highlight Uploaded");
                exit();
            }
        }
    }
    header("Location: pastor_dashboard.php?tab=inspiration_highlights&error=Upload failed or invalid format");
    exit();
}

// Handle Delete Highlight
if (isset($_GET['action']) && $_GET['action'] == 'delete_highlight' && isset($_GET['id'])) {
    $hid = (int)$_GET['id'];
    $h = $conn->query("SELECT image_file FROM church_highlights WHERE id = $hid AND pastor_id = $pastor_id")->fetch_assoc();
    if ($h) {
        @unlink('uploads/' . $h['image_file']);
        $conn->query("DELETE FROM church_highlights WHERE id = $hid");
        header("Location: pastor_dashboard.php?tab=inspiration_highlights&success=Highlight Deleted");
        exit();
    }
}

// Handle pastor sending building leadership chat
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pastor_building_chat'])) {
    $chat_msg = $conn->real_escape_string(trim($_POST['pastor_chat_message']));
    if (!empty($chat_msg)) {
        $conn->query("INSERT INTO building_leadership_chat (sender_id, sender_type, message) VALUES ($pastor_id, 'pastor', '$chat_msg')");
        $pastor_name = $conn->real_escape_string($pastor['first_name'] . ' ' . $pastor['last_name']);
        $notif_msg = $conn->real_escape_string("Pastor $pastor_name sent a message in Building Leadership Chat");
        $bld_leaders = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND (
            LOWER(TRIM(church_role)) LIKE '%building chairperson%' OR LOWER(TRIM(church_role)) LIKE '%building chairman%' OR
            LOWER(TRIM(church_role)) LIKE '%building secretary%' OR LOWER(TRIM(church_role)) LIKE '%building treasurer%'
        )");
        while ($bl = $bld_leaders->fetch_assoc()) {
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$bl['id']}, 'member', '$notif_msg')");
        }
        header("Location: pastor_dashboard.php?tab=building_monitoring&sent=1");
        exit();
    }
}

// Handle Pastor sending a message into a department leadership chat
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pastor_leader_chat'])) {
    $dept_key = $conn->real_escape_string(preg_replace('/[^a-z_]/', '', strtolower(trim($_POST['dept_key'] ?? ''))));
    $plm_msg  = $conn->real_escape_string(trim($_POST['pastor_leader_message'] ?? ''));
    if (!empty($dept_key) && !empty($plm_msg)) {
        $conn->query("INSERT INTO pastor_leader_messages (dept_key, pastor_id, message) VALUES ('$dept_key', $pastor_id, '$plm_msg')");
        $pastor_display = $conn->real_escape_string($pastor['first_name'] . ' ' . $pastor['last_name']);
        $notif_text = $conn->real_escape_string("Pastor $pastor_display sent a message in the " . ucwords(str_replace('_', ' ', $dept_key)) . " leadership chat");
        // Notify relevant leaders based on the department key
        $leaders = null;
        if ($dept_key === 'youths') {
            $leaders = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND department = 'Youths' AND church_role != 'Worshipper'");
        } elseif ($dept_key === 'womens_ministry') {
            $leaders = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND department = 'Womens Ministry' AND church_role != 'Worshipper'");
        } elseif ($dept_key === 'sunday_school') {
            $leaders = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND department = 'Sunday School' AND church_role != 'Worshipper'");
        } elseif ($dept_key === 'elders') {
            $leaders = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND church_role = 'Elder'");
        } elseif ($dept_key === 'building_construction') {
            $leaders = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND church_role IN ('Building Leader', 'Vice Building Leader')");
        } elseif ($dept_key === 'worship_leaders') {
            $leaders = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND church_role IN ('Worship Leader', 'Vice Worship Leader')");
        } elseif ($dept_key === 'general_church') {
            $leaders = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND department = 'General Church' AND church_role != 'Worshipper'");
        }
        
        if ($leaders && $leaders->num_rows > 0) {
            while ($l = $leaders->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$l['id']}, 'member', '$notif_text')");
            }
        }
    }
    $leader_group = $_GET['leader_group'] ?? $dept_key;
    header("Location: pastor_dashboard.php?tab=general_leadership&leader_group=" . urlencode($leader_group) . "&sent=1");
    exit();
}

// Handle Pastor posting a General Church announcement
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_pastor_announcement'])) {
    $ann_msg = $conn->real_escape_string(trim($_POST['pastor_announcement_message'] ?? ''));
    if (!empty($ann_msg)) {
        // Save in department_announcements as 'General Church' — using pastor_id as a negative to distinguish, 
        // but we need a member record. We'll put it in a separate table or store pastor_id in a dedicated column.
        // Use pastor_announcements table (auto-create)
        $conn->query("
            CREATE TABLE IF NOT EXISTS pastor_announcements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pastor_id INT NOT NULL,
                message TEXT NOT NULL,
                image_path VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        // Handle image upload
        $img_path = null;
        if (!empty($_FILES['pastor_ann_image']['name']) && $_FILES['pastor_ann_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/announcements/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['pastor_ann_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $fname = 'pastor_ann_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['pastor_ann_image']['tmp_name'], $upload_dir . $fname)) {
                    $img_path = 'uploads/announcements/' . $fname;
                }
            }
        }
        $img_sql = $img_path ? "'" . $conn->real_escape_string($img_path) . "'" : "NULL";
        $conn->query("INSERT INTO pastor_announcements (pastor_id, message, image_path) VALUES ($pastor_id, '$ann_msg', $img_sql)");
        
        $pastor_display_name = $conn->real_escape_string($pastor['first_name'] . ' ' . $pastor['last_name']);
        $preview = substr($ann_msg, 0, 80) . (strlen($ann_msg) > 80 ? '...' : '');
        $notif_text = $conn->real_escape_string("General Church Announcement from Pastor $pastor_display_name: $preview");
        
        // Notify ALL members
        $all_members = $conn->query("SELECT id FROM members WHERE is_approved = 1");
        while ($am = $all_members->fetch_assoc()) {
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$am['id']}, 'member', '$notif_text')");
        }
    }
    header("Location: pastor_dashboard.php?tab=general_announcements&success=Announcement posted to entire church");
    exit();
}

// Automatically mark notifications as read if tab is opened
if ($tab == 'notifications' && $unread_notifs > 0) {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $pastor_id AND user_type = 'pastor'");
    $unread_notifs = 0; // reset for display
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pastor Dashboard - Munyari Church</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard-layout">
        <div class="sidebar">
            <div class="sidebar-brand">Munyari Pastoral</div>
            <div class="sidebar-nav">
                <a href="?tab=dashboard" class="sidebar-link <?= $tab == 'dashboard' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Dashboard
                </a>
                <a href="?tab=manage_members" class="sidebar-link <?= $tab == 'manage_members' ? 'active' : '' ?>" style="position:relative;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Manage Members
                    <?php if (!empty($tab_badges['manage_members'])): ?><span style="background:var(--danger);color:white;font-size:0.65rem;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:auto;"><?= $tab_badges['manage_members'] ?></span><?php endif; ?>
                </a>
                <a href="?tab=departments" class="sidebar-link <?= $tab == 'departments' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    Departments
                </a>
                <a href="?tab=assign_roles" class="sidebar-link <?= $tab == 'assign_roles' ? 'active' : '' ?>" style="position:relative;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>
                    Assign Roles
                    <?php $_prc = (int)$conn->query("SELECT COUNT(*) as cnt FROM members WHERE pending_role IS NOT NULL AND pending_role != ''")->fetch_assoc()['cnt']; if ($_prc > 0): ?><span style="background:var(--danger);color:white;font-size:0.65rem;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:auto;"><?= $_prc ?></span><?php endif; ?>
                    <?php if (!empty($tab_badges['assign_roles'])): ?><span style="background:var(--danger);color:white;font-size:0.65rem;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:4px;"><?= $tab_badges['assign_roles'] ?></span><?php endif; ?>
                </a>
                <a href="?tab=financials" class="sidebar-link <?= $tab == 'financials' ? 'active' : '' ?>" style="position:relative;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Financial Records
                    <?php if (!empty($tab_badges['financials'])): ?><span style="background:var(--danger);color:white;font-size:0.65rem;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:auto;"><?= $tab_badges['financials'] ?></span><?php endif; ?>
                </a>
                <div class="sidebar-label" style="padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: var(--text-muted); margin-top: 10px;">Membership</div>
                <a href="?tab=desired_roles" class="sidebar-link <?= $tab == 'desired_roles' ? 'active' : '' ?>" style="position:relative;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Village &amp; Desired Roles
                    <?php if (!empty($tab_badges['desired_roles'])): ?><span style="background:var(--danger);color:white;font-size:0.65rem;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:auto;"><?= $tab_badges['desired_roles'] ?></span><?php endif; ?>
                </a>
                <a href="?tab=manage_worshippers" class="sidebar-link <?= $tab == 'manage_worshippers' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Manage Worshippers
                </a>
                <a href="?tab=appointments" class="sidebar-link <?= $tab == 'appointments' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Appointments
                </a>
                <a href="?tab=general_announcements" class="sidebar-link <?= $tab == 'general_announcements' ? 'active' : '' ?>" style="position:relative;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                    General Announcements
                    <?php if (!empty($tab_badges['general_announcements'])): ?><span style="background:var(--danger);color:white;font-size:0.65rem;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:auto;"><?= $tab_badges['general_announcements'] ?></span><?php endif; ?>
                </a>

                <a href="?tab=organized_events" class="sidebar-link <?= $tab == 'organized_events' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Organised Events
                </a>
                <a href="?tab=inspiration_highlights" class="sidebar-link <?= $tab == 'inspiration_highlights' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Daily Quote & Highlights
                </a>
                <a href="?tab=discipline_reports" class="sidebar-link <?= $tab == 'discipline_reports' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path></svg>
                    Discipline & Reports
                </a>
                <a href="?tab=graduation_info" class="sidebar-link <?= $tab == 'graduation_info' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path></svg>
                    Graduation Info
                </a>
                <a href="?tab=usher_monitoring" class="sidebar-link <?= $tab == 'usher_monitoring' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    Usher Monitoring
                </a>
                <a href="?tab=manage_sunday_school" class="sidebar-link <?= $tab == 'manage_sunday_school' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    Manage Sunday School
                </a>
                <a href="?tab=building_monitoring" class="sidebar-link <?= $tab == 'building_monitoring' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    Building Monitoring
                </a>
                <a href="?tab=prayer_board" class="sidebar-link <?= $tab == 'prayer_board' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                    Prayer Board
                </a>
                <a href="?tab=general_leadership" class="sidebar-link <?= $tab == 'general_leadership' ? 'active' : '' ?>" style="position:relative;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    General Leadership
                    <?php if (!empty($tab_badges['general_leadership'])): ?><span style="background:var(--danger);color:white;font-size:0.65rem;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:auto;"><?= $tab_badges['general_leadership'] ?></span><?php endif; ?>
                </a>
                <a href="?tab=choir_songs" class="sidebar-link <?= $tab == 'choir_songs' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path></svg>
                    Choir Songs
                </a>
                <a href="?tab=sport_board" class="sidebar-link <?= $tab == 'sport_board' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"></circle><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"></path></svg>
                    Sport Board
                </a>
            </div>
            
            <div>
                <a href="?tab=settings" class="sidebar-link <?= $tab == 'settings' ? 'active' : '' ?>" style="border-top: 1px solid rgba(255,255,255,0.05); margin-top: 5px;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Settings
                </a>
                <a href="logout.php" class="sidebar-logout" style="margin-top: 0;">Sign Out</a>
            </div>
        </div>
        <div class="main-content">
            <?php if (!$setup_completed): ?>
            <div style="background: linear-gradient(90deg, #6366f1, #8b5cf6); color:#fff; padding: 16px 40px 20px 40px; margin: -40px -40px 30px -40px; display:flex; flex-direction:column; gap:12px;">
                <div style="display:flex; align-items:flex-start; gap:12px;">
                    <span style="font-size:1.4rem; flex-shrink:0;">&#128272;</span>
                    <span style="font-size:0.95rem; font-weight:700; line-height:1.6;"><strong>Action Required:</strong> Please go to <strong>Account Settings</strong> to create your unique username, upload a profile picture, and select your village & desired roles to unlock all tabs.</span>
                </div>
                <div style="padding-left: 36px;">
                    <a href="?tab=settings" style="display:inline-block; background:rgba(255,255,255,0.25); color:#fff; border:1px solid rgba(255,255,255,0.6); padding: 8px 22px; border-radius: 8px; text-decoration:none; font-weight:700; font-size:0.9rem;">Go to Setup &rarr;</a>
                </div>
            </div>
            <?php endif; ?>
            <div class="topbar" style="justify-content: flex-end;">
                <button class="icon-btn mobile-menu-btn" onclick="document.querySelector('.sidebar').classList.toggle('open'); event.stopPropagation();" title="Toggle Menu">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <script>
                if (!window.mobileSidebarBound) {
                    window.mobileSidebarBound = true;
                    document.addEventListener('click', function(e) {
                        var sidebar = document.querySelector('.sidebar');
                        var btn = document.querySelector('.mobile-menu-btn');
                        if (sidebar && sidebar.classList.contains('open') && !sidebar.contains(e.target) && !(btn && btn.contains(e.target))) {
                            sidebar.classList.remove('open');
                        }
                    });
                    document.addEventListener('click', function(e) {
                        if (e.target.closest('.sidebar-link, .sidebar-logout')) {
                            var sidebar = document.querySelector('.sidebar');
                            if (sidebar) sidebar.classList.remove('open');
                        }
                    });
                }
                </script>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <!-- Profile photo + name -->
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <a href="?tab=settings" title="Go to Settings" style="text-decoration: none;">
                            <img src="uploads/<?= htmlspecialchars($pastor['profile_picture'] ?? 'default_avatar.png') ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary); transition: opacity 0.2s;" onmouseover="this.style.opacity=0.8" onmouseout="this.style.opacity=1">
                        </a>
                        <div style="line-height: 1.2;">
                            <span style="font-weight: 600; font-size: 0.95rem; color: var(--text-main); display: block;"><?= htmlspecialchars($pastor_name) ?></span>
                            <span style="font-size: 0.75rem; color: var(--text-muted); background: var(--border-color); padding: 2px 6px; border-radius: 4px;">Pastor</span>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div style="width: 1px; height: 36px; background: var(--border-color); margin: 0 5px;"></div>

                    <!-- Dark mode toggle -->
                    <button id="themeToggle" class="icon-btn" title="Toggle Dark/Light Theme">
                        <svg id="moonIcon" width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                        <svg id="sunIcon" style="display:none;" width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </button>

                    <!-- Notification bell with dropdown -->
                    <div style="position:relative;" id="notifBellWrap">
                        <button onclick="toggleNotifDropdown(event)" class="icon-btn notif-bell-btn <?= $unread_notifs > 0 ? 'bell-shake has-unread' : '' ?>" title="Notifications" style="position:relative;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" stroke="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2a1 1 0 0 1 1 1v.27A7 7 0 0 1 19 10v4.59l1.71 1.7A1 1 0 0 1 20 18h-4.18A4 4 0 0 1 8 18H4a1 1 0 0 1-.71-1.71L5 14.59V10A7 7 0 0 1 11 3.27V3a1 1 0 0 1 1-1zm0 20a2 2 0 0 0 2-2h-4a2 2 0 0 0 2 2z"/></svg>
                            <?php if($unread_notifs > 0): ?>
                                <span class="notif-badge" style="position:absolute; top:-6px; right:-6px;"><?= $unread_notifs ?></span>
                            <?php endif; ?>
                        </button>
                        <!-- Dropdown -->
                        <div id="notifDropdown" style="display:none; position:absolute; top:calc(100% + 10px); right:-10px; width:340px; max-width:calc(100vw - 32px); background:var(--bg-card); border:1px solid var(--border-color); border-radius:14px; box-shadow:0 12px 40px rgba(0,0,0,0.18); z-index:9999; overflow:hidden;">
                            <div style="padding:14px 18px; border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                                <strong style="color:var(--text-main); font-size:0.95rem;">Notifications</strong>
                                <?php if($unread_notifs > 0): ?>
                                    <span style="background:var(--danger); color:white; font-size:0.72rem; font-weight:700; padding:2px 8px; border-radius:20px;"><?= $unread_notifs ?> new</span>
                                <?php endif; ?>
                            </div>
                            <div style="max-height:320px; overflow-y:auto;">
                                <?php
                                // Re-fetch fresh for dropdown (up to 8)
                                $drop_notifs = $conn->query("SELECT * FROM notifications WHERE user_id = $pastor_id AND user_type = 'pastor' ORDER BY created_at DESC LIMIT 8");
                                if ($drop_notifs && $drop_notifs->num_rows > 0):
                                    while ($dn = $drop_notifs->fetch_assoc()):
                                        $dn_target = notification_target_tab($dn['message'], 'pastor', 'dashboard');
                                ?>
                                    <a href="mark_read_and_redirect.php?id=<?= $dn['id'] ?>&redirect=<?= urlencode($dn_target) ?>" style="display:block; padding:12px 18px; border-bottom:1px solid var(--border-color); text-decoration:none; background:<?= !$dn['is_read'] ? 'rgba(37,99,235,0.06)' : 'transparent' ?>; transition:background 0.15s;" onmouseover="this.style.background='rgba(37,99,235,0.1)'" onmouseout="this.style.background='<?= !$dn['is_read'] ? 'rgba(37,99,235,0.06)' : 'transparent' ?>'">
                                        <div style="display:flex; align-items:flex-start; gap:10px;">
                                            <div style="width:8px; height:8px; border-radius:50%; background:<?= !$dn['is_read'] ? 'var(--danger)' : 'var(--border-color)' ?>; margin-top:5px; flex-shrink:0;"></div>
                                            <div>
                                                <p style="margin:0; font-size:0.85rem; color:var(--text-main); line-height:1.4;"><?= htmlspecialchars($dn['message']) ?></p>
                                                <small style="color:var(--text-muted); font-size:0.75rem;"><?= date('M j, g:i A', strtotime($dn['created_at'])) ?></small>
                                                <?php if (!$n["is_read"]): ?>
                                    <small style="color: red; font-size: 0.75rem; margin-left: 8px;">unread</small>
                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                <?php endwhile; else: ?>
                                    <div style="padding:24px; text-align:center; color:var(--text-muted); font-size:0.9rem;">No notifications yet.</div>
                                <?php endif; ?>
                            </div>
                            <div style="display:flex; border-top:1px solid var(--border-color);">
                                <a href="?action=mark_all_read" style="flex:1; padding:12px; text-align:center; color:var(--text-muted); font-size:0.8rem; text-decoration:none; background:var(--bg-main);" onmouseover="this.style.background='rgba(37,99,235,0.06)'; this.style.color='var(--primary)';" onmouseout="this.style.background='var(--bg-main)'; this.style.color='var(--text-muted)';">
                                    Mark all as read
                                </a>
                                <div style="width:1px; background:var(--border-color);"></div>
                                <a href="?action=delete_all_notifications" onclick="return confirm('Delete all your notifications?')" style="flex:1; padding:12px; text-align:center; color:var(--danger); font-size:0.8rem; font-weight:700; text-decoration:none; background:var(--bg-main);" onmouseover="this.style.background='rgba(239,68,68,0.08)'" onmouseout="this.style.background='var(--bg-main)'">
                                    Delete all
                                </a>
                                <div style="width:1px; background:var(--border-color);"></div>
                                <a href="?tab=notifications" style="flex:1; padding:12px; text-align:center; color:var(--primary); font-size:0.875rem; font-weight:600; text-decoration:none; background:var(--bg-main);" onmouseover="this.style.background='rgba(37,99,235,0.06)'" onmouseout="this.style.background='var(--bg-main)'">
                                    View All →
                                </a>
                            </div>
                        </div>
                    </div>
                    <script>
                    function toggleNotifDropdown(e) {
                        e.stopPropagation();
                        const d = document.getElementById('notifDropdown');
                        d.style.display = d.style.display === 'none' ? 'block' : 'none';
                    }
                    document.addEventListener('click', function(e) {
                        const wrap = document.getElementById('notifBellWrap');
                        if (wrap && !wrap.contains(e.target)) {
                            document.getElementById('notifDropdown').style.display = 'none';
                        }
                    });
                    </script>
                </div>
                <a href="logout.php" class="icon-btn" title="Sign Out" style="color: var(--danger); border-color: transparent; background: rgba(239, 68, 68, 0.1);">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                </a>
            </div>
            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
            <?php elseif(isset($_GET['error'])): ?>
                <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
            <?php endif; ?>


<?php if ($tab == 'dashboard'): ?>
                <div class="page-header">
                    <h1>Welcome, Pastor <?= htmlspecialchars($pastor['first_name']) ?></h1>
                    <p>Your pastoral management dashboard.</p>
                </div>
                <div class="dashboard-grid">
                    <div class="stat-card">
                        <h3>Total Congregation</h3>
                        <div class="value" style="color: var(--primary);"><?= $approved_members->num_rows ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Pending Appointments</h3>
                        <?php 
                        $pending_apps = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE pastor_id = $pastor_id AND status='Pending'")->fetch_assoc()['count'];
                        ?>
                        <div class="value" style="color: var(--warning);"><?= $pending_apps ?></div>
                    </div>
                </div>
                <?php render_dashboard_leader_profiles_overview($conn); ?>

            <?php elseif ($tab == 'general_announcements'): ?>
                <div class="page-header">
                    <h1>General Church Announcements</h1>
                    <p>All announcements from secretaries, elders, building leaders, queries, and pastoral messages.</p>
                </div>

                <!-- Pastor Post Announcement Form -->
                <div class="content-card" style="margin-bottom:28px;border-left:4px solid var(--primary);">
                    <h2 style="font-size:1.1rem;margin-top:0;color:var(--primary);">📢 Post Announcement to Entire Church</h2>
                    <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:15px;">This will be sent to every member's Announcements tab and they will receive a notification.</p>
                    <form method="POST" action="pastor_dashboard.php?tab=general_announcements" enctype="multipart/form-data" onsubmit="this.querySelector('button[type=submit]').disabled=true; this.querySelector('button[type=submit]').innerHTML='Posting...';">
                        <input type="hidden" name="post_pastor_announcement" value="1">
                        <div class="form-group">
                            <textarea name="pastor_announcement_message" class="form-control" rows="4" placeholder="Write your announcement to the entire church..." required style="margin-bottom:10px;"></textarea>
                        </div>
                        <div class="form-group">
                            <label style="font-size:0.85rem;color:var(--text-muted);display:block;margin-bottom:8px;">Attach Image (optional)</label>
                            <input type="file" name="pastor_ann_image" accept="image/*" class="form-control" style="font-size:0.85rem;">
                        </div>
                        <button type="submit" class="btn-submit" style="background:var(--primary);">Send to Entire Church</button>
                    </form>
                </div>

                <!-- Announcement Queries from Secretaries -->
                <div class="content-card" style="margin-bottom:24px;border-left:4px solid #f59e0b;">
                    <h2 style="font-size:1.05rem;margin-top:0;color:#f59e0b;">📬 Announcement Queries (from Secretaries)</h2>
                    <?php
                    $ann_queries = $conn->query("
                        SELECT q.*, m.first_name, m.last_name, m.church_role
                        FROM secretary_announcement_queries q
                        JOIN members m ON q.secretary_id = m.id
                        ORDER BY q.created_at DESC LIMIT 50
                    ");
                    if ($ann_queries && $ann_queries->num_rows > 0):
                        while ($q = $ann_queries->fetch_assoc()):
                    ?>
                        <div style="padding:12px 0;border-bottom:1px solid var(--border-color);">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:4px;">
                                <div>
                                    <strong style="color:var(--text-main);"><?= htmlspecialchars($q['first_name'] . ' ' . $q['last_name']) ?></strong>
                                    <span style="font-size:0.8rem;color:var(--text-muted);"> — <?= htmlspecialchars(clean_role_display($q['church_role'])) ?> · <?= htmlspecialchars($q['department']) ?></span>
                                    <span style="font-size:0.75rem;background:rgba(245,158,11,0.12);color:#f59e0b;padding:2px 8px;border-radius:20px;margin-left:6px;"><?= htmlspecialchars($q['status']) ?></span>
                                </div>
                                <small style="color:var(--text-muted);font-size:0.75rem;"><?= date('M j, Y g:i A', strtotime($q['created_at'])) ?></small>
                            </div>
                            <p style="margin:8px 0 0;white-space:pre-wrap;font-size:0.9rem;"><?= htmlspecialchars($q['message']) ?></p>
                        </div>
                    <?php endwhile; else: ?>
                        <p style="color:var(--text-muted);margin:0;">No announcement queries yet.</p>
                    <?php endif; ?>
                </div>

                <!-- All Department & Secretary Announcements -->
                <div class="content-card" style="margin-bottom:24px;border-left:4px solid var(--secondary);">
                    <h2 style="font-size:1.05rem;margin-top:0;color:var(--secondary);">📋 Department Announcements (All Secretaries & Elders)</h2>
                    <?php
                    $dept_anns = $conn->query("
                        SELECT da.*, m.first_name, m.last_name, m.church_role
                        FROM department_announcements da
                        JOIN members m ON da.secretary_id = m.id
                        ORDER BY da.created_at DESC
                        LIMIT 80
                    ");
                    if ($dept_anns && $dept_anns->num_rows > 0):
                        while ($dann = $dept_anns->fetch_assoc()):
                    ?>
                        <div style="padding:14px 0;border-bottom:1px solid var(--border-color);">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:4px;">
                                <div>
                                    <strong style="color:var(--text-main);"><?= htmlspecialchars($dann['first_name'] . ' ' . $dann['last_name']) ?></strong>
                                    <span style="font-size:0.8rem;color:var(--text-muted);"> — <?= htmlspecialchars(clean_role_display($dann['church_role'])) ?></span>
                                    <span style="font-size:0.75rem;background:rgba(37,99,235,0.1);color:var(--primary);padding:2px 8px;border-radius:20px;margin-left:6px;"><?= htmlspecialchars($dann['department']) ?></span>
                                </div>
                                <small style="color:var(--text-muted);font-size:0.75rem;"><?= date('M j, Y g:i A', strtotime($dann['created_at'])) ?></small>
                            </div>
                            <p style="margin:8px 0 0;white-space:pre-wrap;font-size:0.9rem;"><?= htmlspecialchars($dann['message']) ?></p>
                            <?php if (!empty($dann['image_path'])): ?>
                                <a href="<?= htmlspecialchars($dann['image_path']) ?>" target="_blank" style="display:block;margin-top:10px;">
                                    <img src="<?= htmlspecialchars($dann['image_path']) ?>" alt="Announcement" style="width:100%;max-height:320px;object-fit:cover;border-radius:10px;border:1px solid var(--border-color);">
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; else: ?>
                        <p style="color:var(--text-muted);margin:0;">No department announcements yet.</p>
                    <?php endif; ?>
                </div>

                <!-- Past Pastor Announcements -->
                <div class="content-card" style="border-left:4px solid #10b981;">
                    <h2 style="font-size:1.05rem;margin-top:0;color:#10b981;">✉️ Your Past Announcements</h2>
                    <?php
                    $pastor_anns = $conn->query("
                        SELECT pa.*, p.first_name, p.last_name
                        FROM pastor_announcements pa
                        JOIN pastors p ON pa.pastor_id = p.id
                        ORDER BY pa.created_at DESC LIMIT 30
                    ");
                    if ($pastor_anns && $pastor_anns->num_rows > 0):
                        while ($pa = $pastor_anns->fetch_assoc()):
                    ?>
                        <div style="padding:12px 0;border-bottom:1px solid var(--border-color);">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:4px;">
                                <div>
                                    <strong>Pastor <?= htmlspecialchars($pa['first_name'] . ' ' . $pa['last_name']) ?></strong>
                                    <span style="font-size:0.75rem;background:rgba(16,185,129,0.12);color:#10b981;padding:2px 8px;border-radius:20px;margin-left:6px;">General Church</span>
                                </div>
                                <small style="color:var(--text-muted);font-size:0.75rem;"><?= date('M j, Y g:i A', strtotime($pa['created_at'])) ?></small>
                            </div>
                            <p style="margin:8px 0 0;white-space:pre-wrap;font-size:0.9rem;"><?= htmlspecialchars($pa['message']) ?></p>
                            <?php if (!empty($pa['image_path'])): ?>
                                <a href="<?= htmlspecialchars($pa['image_path']) ?>" target="_blank" style="display:block;margin-top:10px;">
                                    <img src="<?= htmlspecialchars($pa['image_path']) ?>" alt="Announcement" style="width:100%;max-height:320px;object-fit:cover;border-radius:10px;border:1px solid var(--border-color);">
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; else: ?>
                        <p style="color:var(--text-muted);margin:0;">No pastoral announcements posted yet.</p>
                    <?php endif; ?>
                </div>


            <?php elseif ($tab == 'notifications'): ?>
                <?php $pastor_notifs = $conn->query("SELECT * FROM notifications WHERE user_id = $pastor_id AND user_type = 'pastor' ORDER BY created_at DESC"); ?>
                <div class="page-header">
                    <h1>All Notifications</h1>
                    <p>Your complete pastoral notification history.</p>
                </div>
                <div class="content-card">
                    <?php if($pastor_notifs && $pastor_notifs->num_rows > 0): ?>
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <?php while($n = $pastor_notifs->fetch_assoc()): ?>
                                <?php $dn_target = notification_target_tab($n['message'], 'pastor', 'dashboard'); ?>
                                <a href="mark_read_and_redirect.php?id=<?= $n['id'] ?>&redirect=<?= urlencode($dn_target) ?>" style="display:block; padding:12px 18px; border-bottom:1px solid var(--border-color); text-decoration:none;">
                                    <div style="font-size:0.9rem; color:var(--text-main); font-weight:500;"><?= htmlspecialchars($n['message']) ?></div>
                                    <small style="color:var(--text-muted); font-size:0.75rem;"><?= date('M j, Y g:i A', strtotime($n['created_at'])) ?></small>
                                                <?php if (!$n["is_read"]): ?>
                                    <small style="color: red; font-size: 0.75rem; margin-left: 8px;">unread</small>
                                <?php endif; ?>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--text-muted);">No notifications found.</p>
                    <?php endif; ?>
                </div>
            <?php elseif ($tab == 'manage_members'): ?>
                <div class="page-header">
                    <h1>Manage Members</h1>
                    <p>Register new members directly, or manage existing ones.</p>
                </div>

                <div class="content-card" style="margin-bottom: 30px;">
                    <div style="display:flex; align-items:center; gap:14px; margin-bottom:24px;">
                        <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--primary),#6366f1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="22" height="22" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        </div>
                        <div>
                            <h2 style="margin:0;">Register New Member</h2>
                            <p style="margin:0;font-size:0.85rem;color:var(--text-muted);">The member account will be immediately approved and active.</p>
                        </div>
                    </div>
                    <?php if (!empty($_GET['success'])): ?>
                        <div style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);color:var(--success);padding:12px 16px;border-radius:10px;margin-bottom:18px;">✅ <?= htmlspecialchars($_GET['success']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($_GET['error'])): ?>
                        <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:var(--danger);padding:12px 16px;border-radius:10px;margin-bottom:18px;">❌ <?= htmlspecialchars($_GET['error']) ?></div>
                    <?php endif; ?>
                    <form method="POST" action="?tab=manage_members" id="pastorRegForm" onsubmit="this.querySelector('button[type=submit]').disabled=true;">
                        <input type="hidden" name="register_member" value="1">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <!-- Personal Details -->
                            <div class="form-group">
                                <label>First Name <span style="color:var(--danger);">*</span></label>
                                <input type="text" id="p_first_name" name="first_name" class="form-control" placeholder="e.g. Peter" pattern="[A-Za-z\s]+" oninput="validateInput(this,'name'); seqUnlock('p_first_name','p_last_name')" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name <span style="color:var(--danger);">*</span></label>
                                <input type="text" id="p_last_name" name="last_name" class="form-control" placeholder="e.g. Ntoiti" pattern="[A-Za-z\s]+" oninput="validateInput(this,'name'); seqUnlock('p_last_name','p_phone')" disabled required>
                            </div>

                            <div class="form-group">
                                <label>Phone Number <span style="color:var(--danger);">*</span></label>
                                <input type="tel" id="p_phone" name="phone" class="form-control" placeholder="10-digit number" pattern="\d{10}" maxlength="10" oninput="validateInput(this,'phone'); checkPhoneAsync(this,'pRegDept')" disabled required>
                            </div>
                             <div class="form-group">
                                 <label>Department <span style="color:var(--danger);">*</span></label>
                                 <div style="position:relative;">
                                     <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #8b5cf6; pointer-events: none; z-index:1;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg></span>
                                     <select name="department" id="pRegDept" class="form-control" style="padding-left:36px;" required onchange="pRegAutoGender(); seqUnlock('pRegDept','pRegGender')" disabled>
                                         <option value="">Select department...</option>
                                         <option value="Youths">Youths</option>
                                         <option value="Elders">Elders</option>
                                         <option value="Sunday School">Sunday School</option>
                                         <option value="Womens Ministry">Women's Ministry</option>
                                     </select>
                                 </div>
                             </div>
                             <div class="form-group">
                                 <label>Gender <span style="color:var(--danger);">*</span></label>
                                 <div style="position:relative;">
                                     <select name="gender" id="pRegGender" class="form-control" style="padding-left:36px;" required onchange="seqUnlock('pRegGender','p_address')" disabled>
                                         <option value="Male">Male</option>
                                         <option value="Female">Female</option>
                                     </select>
                                 </div>
                                 <small id="pRegGenderHint" style="color:var(--text-muted);font-size:0.78rem;">Select department first. Women auto-fill Female; Elders auto-fill Male.</small>
                             </div>
                            <div class="form-group" style="grid-column:1/-1;">
                                <label>Residential Address</label>
                                <input type="text" id="p_address" name="address" class="form-control" placeholder="e.g. Mugui" pattern="[A-Za-z0-9\s,.-]+" oninput="validateInput(this,'name'); seqUnlock('p_address','pastorRegPwd')" disabled>
                            </div>
                            <!-- Login Credentials -->
                            <div class="form-group">
                                <label>Login Password <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <input type="password" name="password" id="pastorRegPwd" class="form-control" placeholder="At least 6 characters" required style="padding-right:46px;" minlength="6" oninput="seqUnlock('pastorRegPwd','pastorRegPwdConfirm')" disabled>
                                    <span onclick="var i=document.getElementById('pastorRegPwd');i.type=i.type==='password'?'text':'password'" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--text-muted);">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Confirm Password <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <input type="password" name="confirm_password" id="pastorRegPwdConfirm" class="form-control" placeholder="Re-enter password" required style="padding-right:46px;" oninput="checkPastorPwd()" disabled>
                                    <span onclick="var i=document.getElementById('pastorRegPwdConfirm');i.type=i.type==='password'?'text':'password'" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--text-muted);">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </span>
                                </div>
                                <small id="pwdMatchHint" style="font-size:0.8rem;margin-top:4px;display:none;"></small>
                            </div>
                        </div>
                        <div style="margin-top:20px; display:flex; gap:12px; align-items:center;">
                            <button type="submit" class="btn-submit" style="width:auto;padding:12px 32px;">✅ Register Member</button>
                            <button type="reset" style="background:none;border:1px solid var(--border-color);color:var(--text-muted);padding:11px 20px;border-radius:10px;cursor:pointer;font-size:0.9rem;">Clear Form</button>
                        </div>
                    </form>
                    <script>
                    async function checkUsernameAsync(input, nextId) {
                        if(input.value.length >= 3) {
                            try {
                                let res = await fetch('check_username.php?username=' + input.value);
                                let data = await res.json();
                                let errorMsg = input.nextElementSibling;
                                if (!errorMsg || !errorMsg.classList.contains('err-msg')) {
                                    errorMsg = document.createElement('span');
                                    errorMsg.className = 'err-msg';
                                    errorMsg.style.color = '#ef4444';
                                    errorMsg.style.fontSize = '0.85rem';
                                    errorMsg.style.display = 'block';
                                    errorMsg.style.marginTop = '4px';
                                    errorMsg.style.fontWeight = 'bold';
                                    input.parentNode.appendChild(errorMsg);
                                }
                                
                                if(data.exists) {
                                    errorMsg.innerText = 'This username is already taken!';
                                    input.setCustomValidity('Taken');
                                } else {
                                    errorMsg.innerText = 'Username is available!';
                                    errorMsg.style.color = '#10b981';
                                    input.setCustomValidity('');
                                    setTimeout(() => { if(errorMsg.innerText === 'Username is available!') errorMsg.innerText = ''; }, 3000);
                                }
                            } catch(e) {}
                        } else {
                            input.setCustomValidity(input.value.length > 0 ? 'Too short' : '');
                            let errorMsg = input.nextElementSibling;
                            if(errorMsg && errorMsg.classList.contains('err-msg')) errorMsg.innerText = '';
                        }
                        seqUnlock(input.id, nextId);
                    }

                    async function checkPhoneAsync(input, nextId) {
                        if(input.value.length === 10) {
                            try {
                                let res = await fetch('check_phone.php?phone=' + input.value);
                                let data = await res.json();
                                if(data.exists) {
                                    let errorMsg = input.nextElementSibling;
                                    if (!errorMsg || !errorMsg.classList.contains('err-msg')) {
                                        errorMsg = document.createElement('span');
                                        errorMsg.className = 'err-msg';
                                        errorMsg.style.color = '#ef4444';
                                        errorMsg.style.fontSize = '0.85rem';
                                        errorMsg.style.display = 'block';
                                        errorMsg.style.marginTop = '4px';
                                        errorMsg.style.fontWeight = 'bold';
                                        input.parentNode.appendChild(errorMsg);
                                    }
                                    errorMsg.innerText = 'This number is already registered!';
                                    input.setCustomValidity('Invalid');
                                    input.value = '';
                                    setTimeout(() => errorMsg.innerText = '', 4000);
                                    return;
                                }
                            } catch(e) {}
                        }
                        seqUnlock(input.id, nextId);
                    }

                    function seqUnlock(currentId, nextId) {
                        const current = document.getElementById(currentId);
                        const next    = document.getElementById(nextId);
                        if (!current || !next) return;
                        const filled = current.tagName === 'SELECT'
                            ? current.value !== ''
                            : current.value.trim().length > 0 && current.checkValidity();
                        next.disabled = !filled;
                        if (!filled) next.value = '';
                    }

                    function validateInput(input, type) {
                        let errorMsg = input.nextElementSibling;
                        if (!errorMsg || !errorMsg.classList.contains('err-msg')) {
                            errorMsg = document.createElement('span');
                            errorMsg.className = 'err-msg';
                            errorMsg.style.color = '#ef4444';
                            errorMsg.style.fontSize = '0.8rem';
                            errorMsg.style.display = 'block';
                            errorMsg.style.marginTop = '4px';
                            input.parentNode.appendChild(errorMsg);
                        }
                        if (type === 'name') {
                            if (/[^A-Za-z\s,.-]/.test(input.value)) {
                                errorMsg.innerText = 'Only characters allowed - numbers are not permitted.';
                                errorMsg.style.color = '#ef4444';
                                input.setCustomValidity('Invalid');
                                setTimeout(() => { input.value = input.value.replace(/[^A-Za-z\s,.-]/g, ''); }, 800);
                            } else {
                                errorMsg.innerText = '';
                                input.setCustomValidity('');
                            }
                        } else if (type === 'username') {
                            if (/[^A-Za-z0-9_]/.test(input.value)) {
                                errorMsg.innerText = 'Only letters, numbers, and underscores are allowed.';
                                errorMsg.style.color = '#ef4444';
                                input.setCustomValidity('Invalid');
                                setTimeout(() => { input.value = input.value.replace(/[^A-Za-z0-9_]/g, ''); }, 800);
                            } else {
                                if(input.value.length > 0 && input.value.length < 3) {
                                    errorMsg.innerText = 'Username must be at least 3 characters.';
                                    errorMsg.style.color = '#ef4444';
                                    input.setCustomValidity('Invalid');
                                } else if (!input.validity.customError || input.validationMessage === 'Too short' || input.validationMessage === 'Taken') {
                                    if (errorMsg.innerText === 'Username must be at least 3 characters.') errorMsg.innerText = '';
                                }
                            }
                        } else if (type === 'phone') {
                            if (/[^0-9]/.test(input.value)) {
                                errorMsg.innerText = 'Only numbers allowed - characters are not permitted.';
                                input.setCustomValidity('Invalid');
                                setTimeout(() => { input.value = input.value.replace(/[^0-9]/g, ''); }, 800);
                            } else if (input.value.length > 0 && input.value.length !== 10) {
                                errorMsg.innerText = 'Phone number must be exactly 10 digits.';
                                input.setCustomValidity('Invalid');
                            } else {
                                errorMsg.innerText = '';
                                input.setCustomValidity('');
                            }
                        }
                    }

                    function pRegAutoGender() {
                        var dept = document.getElementById('pRegDept').value;
                        var gender = document.getElementById('pRegGender');
                        var hint = document.getElementById('pRegGenderHint');
                        if (dept === 'Womens Ministry') {
                            gender.value = 'Female';
                            Array.from(gender.options).forEach(function(opt) { opt.disabled = opt.value !== 'Female'; });
                            if (hint) hint.textContent = 'Women Ministry members are registered as Female.';
                        } else if (dept === 'Elders') {
                            gender.value = 'Male';
                            Array.from(gender.options).forEach(function(opt) { opt.disabled = opt.value !== 'Male'; });
                            if (hint) hint.textContent = 'Elders members are registered as Male.';
                        } else if (dept === 'Youths' || dept === 'Sunday School') {
                            // Don't force — keep current or default Male
                            Array.from(gender.options).forEach(function(opt) { opt.disabled = false; });
                            if (!gender.value) gender.value = 'Male';
                            if (hint) hint.textContent = "Select the member's gender.";
                        }
                    }
                    pRegAutoGender();
                    function checkPastorPwd() {
                        var p1 = document.getElementById('pastorRegPwd').value;
                        var p2 = document.getElementById('pastorRegPwdConfirm').value;
                        var hint = document.getElementById('pwdMatchHint');
                        if (p2.length > 0) {
                            hint.style.display = 'block';
                            if (p1 === p2) {
                                hint.textContent = '✓ Passwords match';
                                hint.style.color = 'var(--success)';
                            } else {
                                hint.textContent = '✗ Passwords do not match';
                                hint.style.color = 'var(--danger)';
                            }
                        } else {
                            hint.style.display = 'none';
                        }
                    }
                    document.getElementById('pastorRegForm').addEventListener('submit', function(e) {
                        var p1 = document.getElementById('pastorRegPwd').value;
                        var p2 = document.getElementById('pastorRegPwdConfirm').value;
                        if (p1 !== p2) {
                            e.preventDefault();
                            alert('Passwords do not match. Please check and try again.');
                        }
                    });
                    </script>
                </div>
                <?php
                if (!function_exists('get_print_sort_order')) {
                    function get_print_sort_order($role_raw, $dept) {
                        $r = strtolower(trim($role_raw ?? ''));
                        $d = strtolower(trim($dept ?? ''));
                        if (strpos($r, 'general church') !== false || strpos($r, 'pastor') !== false) return [0, 0];
                        if (strpos($d, 'elder') !== false || strpos($r, 'elder') !== false) {
                            if (preg_match('/^(elder chairman|elder chairperson|elder chairlady)$/', $r)) return [1, 0];
                            if (strpos($r, 'vice elder chair') !== false) return [1, 1];
                            if (strpos($r, 'elder secretary') !== false && strpos($r, 'vice') === false) return [1, 2];
                            if (strpos($r, 'vice elder secretary') !== false) return [1, 3];
                            if (strpos($r, 'elder treasurer') !== false) return [1, 4];
                            return [1, 5];
                        }
                        if (strpos($d, 'women') !== false || strpos($r, 'women') !== false) {
                            if (preg_match('/vice women (chairlady|chairperson|chairman)/', $r)) return [2, 0];
                            if (preg_match('/^women (chairlady|chairperson|chairman)$/', $r)) return [2, 1];
                            if (strpos($r, 'women secretary') !== false && strpos($r, 'vice') === false) return [2, 2];
                            if (strpos($r, 'vice women secretary') !== false) return [2, 3];
                            if (strpos($r, 'women treasurer') !== false) return [2, 4];
                            return [2, 5];
                        }
                        if (strpos($d, 'youth') !== false || strpos($r, 'youth') !== false) {
                            if (preg_match('/^(youth chairman|youth chairperson|youth chairlady)$/', $r)) return [3, 0];
                            if (strpos($r, 'vice youth chair') !== false) return [3, 1];
                            if (strpos($r, 'youth secretary') !== false && strpos($r, 'vice') === false) return [3, 2];
                            if (strpos($r, 'vice youth secretary') !== false) return [3, 3];
                            if (strpos($r, 'youth treasurer') !== false) return [3, 4];
                            if (strpos($r, 'mama youth') !== false || strpos($r, 'baba youth') !== false) return [3, 5];
                            return [3, 6];
                        }
                        if (strpos($d, 'sunday school') !== false || strpos($r, 'sunday school') !== false) {
                            if (strpos($r, 'sunday school patron') !== false && strpos($r, 'vice') === false) return [4, 0];
                            if (strpos($r, 'vice sunday school patron') !== false) return [4, 1];
                            if (preg_match('/sunday school (chairman|chairperson|chairlady)/', $r) && strpos($r, 'vice') === false) return [4, 2];
                            if (preg_match('/vice sunday school (chairman|chairperson|chairlady)/', $r)) return [4, 3];
                            if (strpos($r, 'sunday school secretary') !== false && strpos($r, 'vice') === false) return [4, 4];
                            if (strpos($r, 'vice sunday school secretary') !== false) return [4, 5];
                            if (strpos($r, 'sunday school treasurer') !== false) return [4, 6];
                            if (strpos($r, 'sunday school teacher') !== false) return [5, 0];
                            return [6, 0];
                        }
                        if (strpos($r, 'building') !== false) {
                            if (preg_match('/^(building chairman|building chairperson|building chairlady)$/', $r)) return [7, 0];
                            if (strpos($r, 'vice building chair') !== false) return [7, 1];
                            if (strpos($r, 'building secretary') !== false && strpos($r, 'vice') === false) return [7, 2];
                            if (strpos($r, 'vice building secretary') !== false) return [7, 3];
                            if (strpos($r, 'building treasurer') !== false) return [7, 4];
                            return [7, 5];
                        }
                        if ($r !== 'member' && $r !== '') return [8, 0];
                        return [99, 0];
                    }
                }
                $members->data_seek(0);
                $all_print_members = [];
                while ($pm = $members->fetch_assoc()) { $all_print_members[] = $pm; }

                // Add pastors to the list as well
                $pastors_q = $conn->query("SELECT * FROM pastors WHERE is_approved = 1");
                if ($pastors_q) {
                    while ($pst = $pastors_q->fetch_assoc()) {
                        $pst['department'] = 'General Church';
                        $pst['church_role'] = 'General Church Pastor';
                        // address column holds the pastor's actual residence area (e.g. Nkondi)
                        $pst['is_pastor'] = true;
                        $all_print_members[] = $pst;
                    }
                }

                usort($all_print_members, function($a, $b) {
                    $ra = get_print_sort_order($a['church_role'] ?? '', $a['department'] ?? '');
                    $rb = get_print_sort_order($b['church_role'] ?? '', $b['department'] ?? '');
                    if ($ra[0] !== $rb[0]) return $ra[0] - $rb[0];
                    if ($ra[1] !== $rb[1]) return $ra[1] - $rb[1];
                    return strcmp($a['first_name'].$a['last_name'], $b['first_name'].$b['last_name']);
                });
                ?>
                <div class="content-card">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
                        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <h2 style="margin:0;">Member Directory</h2>
                            <span style="background:linear-gradient(135deg,#1e3a8a,#6366f1);color:white;font-size:0.85rem;font-weight:800;padding:5px 14px;border-radius:20px;letter-spacing:0.5px;">
                                Total: <?= count($all_print_members) ?> Members
                            </span>
                        </div>
                        <input type="text" id="memberSearch" placeholder="Search by name or phone..." style="padding:10px 15px; border:1px solid var(--border-color); border-radius:8px; width:100%; max-width:280px; flex-grow:1;" onkeyup="filterMembersTable()">
                        <div style="display:flex;gap:10px;">
                            <button onclick="printMemberDirectory('landscape')" style="display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#2563eb,#6366f1);color:white;border:none;padding:10px 20px;border-radius:10px;cursor:pointer;font-size:0.9rem;font-weight:600;box-shadow:0 2px 8px rgba(37,99,235,0.3);">
                                Print Landscape
                            </button>
                            <button onclick="printMemberDirectory('portrait')" style="display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#4f46e5,#4338ca);color:white;border:none;padding:10px 20px;border-radius:10px;cursor:pointer;font-size:0.9rem;font-weight:600;box-shadow:0 2px 8px rgba(37,99,235,0.3);">
                                Print Portrait
                            </button>
                        </div>
                    </div>

                                        <!-- Hidden printable member list -->
                    <div id="printableMemberDir" style="display:none;">
                        <?php
                        $p_pic = $pastor['profile_picture'] ?? 'default_avatar.png';
                        $p_name = strtoupper(htmlspecialchars(($pastor['first_name'] ?? '') . ' ' . ($pastor['last_name'] ?? '')));
                        ?>
                        <!-- Watermark (Fixed, repeats automatically) -->
                        <div class="print-watermark">E.A.P.C MUNYARI CHURCH</div>
                        
                        <table style="width:100%;border-collapse:collapse;font-size:0.78rem;">
                            <thead>
                                <!-- Thead row containing pastor & logo: Repeats on every printed page -->
                                <tr>
                                    <th colspan="8" style="border:none; padding: 0; font-weight:normal; background:white;">
                                        <div style="display:flex;align-items:center;justify-content:center;gap:18px;margin-bottom:10px;">
                                            <img src="uploads/<?= htmlspecialchars($p_pic) ?>" alt="Pastor" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid #1e3a8a;">
                                            <div>
                                                <div style="font-size:0.75rem;color:#555;text-transform:uppercase;letter-spacing:1px;">Church Pastor</div>
                                                <div style="font-size:1.2rem;font-weight:700;color:#1e3a8a;"><?= strtoupper($p_name ?: 'N/A') ?></div>
                                            </div>
                                        </div>
                                        <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:2px solid #1e3a8a;padding-bottom:12px;margin-bottom:6px;gap:10px;">
                                            <img src="church_logo.jpg" style="width:65px;height:65px;object-fit:contain;flex-shrink:0;">
                                            <div style="text-align:center;flex:1;min-width:0;">
                                                <h2 style="margin:0;font-size:1.2rem;color:#1e3a8a;">E.A.P.C MUNYARI CHURCH</h2>
                                                <h3 style="margin:2px 0;font-size:1rem;color:#1e3a8a;">MEMBERS TRACK RECORD (TOTAL: <?= count($all_print_members) ?>)</h3>
                                                <p style="margin:2px 0;font-size:0.78rem;color:#555;">Printed on: <?= date('F j, Y g:i A') ?></p>
                                            </div>
                                            <img src="church_logo.jpg" style="width:65px;height:65px;object-fit:contain;flex-shrink:0;">
                                        </div>
                                    </th>
                                </tr>
                                <tr style="background:#1e3a8a;color:white;">
                                <th style="padding:7px 8px;border:1px solid #aaa;">#</th>
                                <th style="padding:7px 8px;border:1px solid #aaa;">Photo</th>
                                <th style="padding:7px 8px;border:1px solid #aaa;">Full Name</th>
                                <th style="padding:7px 8px;border:1px solid #aaa;">Phone</th>
                                <th style="padding:7px 8px;border:1px solid #aaa;">Address</th>
                                <th style="padding:7px 8px;border:1px solid #aaa;">Department</th>
                                <th style="padding:7px 8px;border:1px solid #aaa;">Role(s)</th>
                                <th style="padding:7px 8px;border:1px solid #aaa;">Status</th>
                            </tr></thead>
                            <tbody>
                                <?php
                                $print_row = 1; $last_grp = -1;
                                $group_labels = [0=>'General Church Leaders',1=>'Elders Department',2=>"Women's Ministry",3=>'Youths Department',4=>'Sunday School — Main Leaders',5=>'Sunday School Teachers',6=>'Sunday School — Subsidiary Leaders',7=>'Building Department',8=>'Other Roles',99=>'Members'];
                                $group_colors = [0=>'#1e3a8a',1=>'#6d28d9',2=>'#be185d',3=>'#b45309',4=>'#0e7490',5=>'#047857',6=>'#0369a1',7=>'#92400e',8=>'#4b5563',99=>'#374151'];
                                foreach ($all_print_members as $pm):
                                    $s = get_print_sort_order($pm['church_role'] ?? '', $pm['department'] ?? '');
                                    $grp = $s[0];
                                    if ($grp !== $last_grp): $gl = $group_labels[$grp] ?? 'Other'; $gc = $group_colors[$grp] ?? '#374151'; $last_grp = $grp; ?>
                                <tr><td colspan="8" style="padding:6px 10px;background:<?= $gc ?>;color:white;font-weight:700;font-size:0.74rem;letter-spacing:0.5px;border:1px solid <?= $gc ?>;text-align:center;"> <?= htmlspecialchars($gl) ?> </td></tr>
                                <?php endif;
                                    $status_str = $pm['is_approved']==1?'Active':($pm['is_approved']==-1?'Deactivated':'Pending');
                                    $status_color = $pm['is_approved']==1?'#15803d':($pm['is_approved']==-1?'#dc2626':'#d97706');
                                    $roles_str = implode(', ', array_filter(array_map('trim', explode(',', $pm['church_role'] ?? 'Member'))));
                                    $row_bg = ($print_row % 2 === 0) ? '#eff6ff' : '#ffffff'; ?>
                                <tr style="background:<?= $row_bg ?>;">
                                    <td style="padding:5px 8px;border:1px solid #ddd;"><?= $print_row++ ?></td>
                                    <td style="padding:4px 8px;border:1px solid #ddd;"><img src="uploads/<?= htmlspecialchars($pm['profile_picture'] ?? 'default_avatar.png') ?>" style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid #c7d2fe;display:block;" alt=""></td>
                                    <td style="padding:5px 8px;border:1px solid #ddd;font-weight:600;"><?= htmlspecialchars($pm['first_name'].' '.$pm['last_name']) ?></td>
                                    <td style="padding:5px 8px;border:1px solid #ddd;"><?= htmlspecialchars($pm['phone'] ?? '-') ?></td>
                                    <td style="padding:5px 8px;border:1px solid #ddd;"><?= htmlspecialchars($pm['address'] ?? '-') ?></td>
                                    <td style="padding:5px 8px;border:1px solid #ddd;"><?= htmlspecialchars($pm['department'] ?? 'General Church') ?></td>
                                    <td style="padding:5px 8px;border:1px solid #ddd;"><?= htmlspecialchars($roles_str ?: 'Member') ?></td>
                                    <td style="padding:5px 8px;border:1px solid #ddd;color:<?= $status_color ?>;font-weight:600;">
                                        <img src="data:image/svg+xml;base64,<?= base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 12"><circle cx="6" cy="6" r="6" fill="'.$status_color.'"/></svg>') ?>" style="width:11px;height:11px;vertical-align:middle;margin-right:4px;" alt=""> <?= $status_str ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p style="margin-top:14px;font-size:0.75rem;color:#888;text-align:center;">Total members: <?= count($all_print_members) ?></p>
                        
                        <!-- Signature and Stamp -->
                        <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-top:80px; padding:0 40px; page-break-inside:avoid;">
                            <div style="text-align:center;">
                                <div style="font-size:0.85rem; font-weight:bold; text-transform:uppercase; color:#1e3a8a;">Church Pastor</div>
                                <div style="font-size:0.75rem; font-weight:bold; color:#333; margin-top:2px; margin-bottom:10px;"><?= htmlspecialchars(strtoupper($pastor_name)) ?></div>
                                <div style="font-size:0.9rem; margin-bottom:8px; display:flex; align-items:flex-end; justify-content:center; gap:8px;">
                                    <span style="font-style:italic;color:#333;">Sign:</span>
                                    <span style="display:inline-block; border-bottom:1px solid #000; width:220px; height:14px;"></span>
                                </div>
                                <div style="font-size:0.85rem; margin-top:12px; display:flex; align-items:flex-end; justify-content:center; gap:8px;">
                                    <span style="color:#333;">Date:</span>
                                    <span style="display:inline-block; border-bottom:1px dotted #000; width:220px; height:14px;"></span></div>
                            </div>
                            <div style="text-align:center;">
                                <div style="border:2px dashed #aaa; width:140px; height:140px; border-radius:50%; margin:0 auto 10px; display:flex; align-items:center; justify-content:center; color:#ccc; font-size:0.85rem; text-transform:uppercase; letter-spacing:1px;">Official<br>Stamp</div>
                            </div>
                        </div>
                    </div>
                    <script>
                    
function filterMembersTable() {
    let input = document.getElementById("memberSearch");
    if (!input) return;
    let filter = input.value.toLowerCase();
    // Get the visible member table (the one with id="memberMainTable"), not the hidden print div
    let table = document.getElementById("memberMainTable");
    if (!table) {
        // fallback: get all .table-responsive tables and use the last one (visible one)
        let tables = input.closest(".content-card").querySelectorAll(".table-responsive table");
        table = tables[tables.length - 1];
    }
    if (!table) return;
    let trs = table.querySelectorAll("tbody tr");

    let currentHeader = null;
    let headerHasVisibleRows = false;

    for (let i = 0; i < trs.length; i++) {
        let tr = trs[i];
        if (tr.querySelector("td[colspan]")) {
            if (currentHeader && !headerHasVisibleRows) {
                currentHeader.style.display = "none";
            }
            currentHeader = tr;
            headerHasVisibleRows = false;
            tr.style.display = "";
        } else {
            // Table columns: Profile(1), Name(2), Username(3), Phone(4), Address(5), Dept(6), Role(7), Status(8), Actions(9)
            let nameTd  = tr.querySelector("td:nth-child(2)");
            let phoneTd = tr.querySelector("td:nth-child(4)");

            if (nameTd || phoneTd) {
                let nameTxt  = nameTd  ? nameTd.textContent.toLowerCase()  : "";
                let phoneTxt = phoneTd ? phoneTd.textContent.toLowerCase() : "";

                if (nameTxt.includes(filter) || phoneTxt.includes(filter)) {
                    tr.style.display = "";
                    headerHasVisibleRows = true;
                } else {
                    tr.style.display = "none";
                }
            }
        }
    }
    if (currentHeader && !headerHasVisibleRows) {
        currentHeader.style.display = "none";
    }
}




function printMemberDirectory(orientation) {
                        if (!orientation) orientation = 'landscape';
                        var printDiv = document.getElementById("printableMemberDir");
                        if (!printDiv) { alert("Print content not found."); return; }
                        var w = window.open('', '_blank');
                        if (!w) { alert("Popup blocked! Please allow popups."); return; }
                        var wmSize = orientation === 'landscape' ? '3.2rem' : '2.0rem';
                        var pageSize = orientation === 'landscape' ? 'A4 landscape' : 'A4 portrait';
                        w.document.write('<!doctype html><html><head><title>E.A.P.C MUNYARI CHURCH — MEMBERS</title>');
                        w.document.write('<base href="' + window.location.origin + window.location.pathname + '">');
                        w.document.write('<style>');
                        w.document.write('*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;color-adjust:exact!important;}');
                        w.document.write('@media print{@page{size:' + pageSize + ';margin: 0;}  body{padding:15mm !important;} thead{display:table-header-group;}}');
                        w.document.write('body{font-family:Arial,sans-serif;margin:0;padding:10px;padding-bottom:60px;}');
                        w.document.write('table{width:100%;border-collapse:collapse;}');
                        w.document.write('th{background:#1e3a8a;color:white;padding:7px 8px;font-size:0.78rem;text-align:left;}');
                        w.document.write('td{padding:5px 8px;border:1px solid #ccc;font-size:0.74rem;vertical-align:middle;}');
                        w.document.write('td img{width:34px;height:34px;border-radius:50%;object-fit:cover;}');
                        w.document.write('.badge{display:inline-block;padding:3px 7px;border-radius:10px;font-size:0.7rem;font-weight:600;}');
                        w.document.write('.no-print{display:none!important;}');
                        w.document.write('.print-watermark{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-45deg);font-size:' + wmSize + '!important;color:rgba(30,58,138,0.25)!important;font-weight:bold;white-space:nowrap;z-index:999999!important;opacity:1!important;pointer-events:none;letter-spacing:4px;text-transform:uppercase;mix-blend-mode:multiply;}');
                        w.document.write('.footer{position:fixed;bottom:0;left:0;right:0;text-align:center;font-size:10px;color:#777;font-style:italic;background:rgba(255,255,255,0.9);padding:5px 0;z-index:10;}');
                        w.document.write('</style></head><body>');
                        w.document.write(printDiv.innerHTML);
                        w.document.write('<div class="footer">Generated from E.A.P.C Munyari Portal &nbsp;|&nbsp; Printed on: ' + (typeof printDate !== 'undefined' ? printDate : new Date().toLocaleString()) + '</div>');
                        w.document.write('</body></html>');
                        w.document.close();
                        w.focus();
                        setTimeout(function(){ w.print(); }, 600);
                    }

                    </script>
                    <div class="table-responsive"><table id="memberMainTable">
                        <thead><tr><th>Profile</th><th>Name</th><th>Username</th><th>Phone</th><th>Address</th><th>Department</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php
                            $ui_last_grp = -1;
                            $ui_group_labels = [0=>'General Church Leaders',1=>'Elders Department',2=>"Women's Ministry",3=>'Youths Department',4=>'Sunday School — Main Leaders',5=>'Sunday School Teachers',6=>'Sunday School — Subsidiary Leaders',7=>'Building Department',8=>'Other Roles',99=>'Members'];
                            $ui_group_colors = [0=>'#1e3a8a',1=>'#6d28d9',2=>'#be185d',3=>'#b45309',4=>'#0e7490',5=>'#047857',6=>'#0369a1',7=>'#92400e',8=>'#4b5563',99=>'#374151'];
                            
                            foreach($all_print_members as $m): 
                                $s = get_print_sort_order($m['church_role'] ?? '', $m['department'] ?? '');
                                $grp = $s[0];
                                if ($grp !== $ui_last_grp):
                                    $gl = $ui_group_labels[$grp] ?? 'Other';
                                    $gc = $ui_group_colors[$grp] ?? 'var(--border-color)';
                                    $text_col = 'white';
                                    $ui_last_grp = $grp;
                            ?>
                            <tr>
                                <td colspan="9" style="background: <?= $gc ?>; color: <?= $text_col ?>; padding: 10px 15px; font-weight: 700; font-size: 0.9rem; letter-spacing: 0.5px; border-bottom: 2px solid white; text-align: center;">
                                    <?= htmlspecialchars($gl) ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td><img src="uploads/<?= htmlspecialchars($m['profile_picture'] ?? 'default_avatar.png') ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); cursor: zoom-in;" onclick="viewProfileImage(this.src);"></td>
                                <td style="font-weight: 500;"><?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?></td>
                                <td><?= htmlspecialchars($m['username'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($m['phone']) ?></td>
                                <td><?= htmlspecialchars($m['address'] ?? '-') ?></td>
                                <td><span class="badge" style="background:var(--border-color);color:var(--text-main);"><?= htmlspecialchars($m['department'] ?? 'General Church') ?></span></td>
                                <td style="white-space:normal;"><?php foreach(array_map('trim', explode(',', $m['church_role'] ?? 'Member')) as $role_part) { if(trim($role_part)==='') continue; echo '<span class="badge" style="background:var(--border-color);color:var(--text-main);margin:2px 2px 2px 0;display:inline-block;white-space:nowrap;">'.htmlspecialchars($role_part).'</span>'; } ?></td>
                                <td>
                                    <?php if ($m['is_approved'] == 1): ?>
                                        <span class="badge" style="color:#10b981;font-weight:bold;display:inline-flex;align-items:center;background:none;padding:0;border:none;"><img src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'><circle cx='6' cy='6' r='6' fill='%2310b981'/></svg>" style="width:12px;height:12px;margin-right:4px;vertical-align:middle;" alt="dot">Active</span>
                                    <?php elseif ($m['is_approved'] == -1): ?>
                                        <span class="badge" style="color:#ef4444;font-weight:bold;display:inline-flex;align-items:center;background:none;padding:0;border:none;"><img src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'><circle cx='6' cy='6' r='6' fill='%23ef4444'/></svg>" style="width:12px;height:12px;margin-right:4px;vertical-align:middle;" alt="dot">Deactivated</span>
                                    <?php else: ?>
                                        <span class="badge" style="color:#f59e0b;font-weight:bold;display:inline-flex;align-items:center;background:none;padding:0;border:none;"><img src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'><circle cx='6' cy='6' r='6' fill='%23f59e0b'/></svg>" style="width:12px;height:12px;margin-right:4px;vertical-align:middle;" alt="dot">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($m['is_pastor'])): ?>
                                        <span class="badge" style="background:linear-gradient(135deg,#1e3a8a,#6366f1);color:white;font-size:0.75rem;">Church Pastor</span>
                                    <?php else: ?>
                                        <?php if ($m['is_approved'] == 1): ?>
                                        <a href="?tab=manage_members&action=deactivate_member&id=<?= $m['id'] ?>" onclick="return confirm('Deactivate this member? They will not be able to log in.')" style="background:#f59e0b;color:white;padding:4px 10px;border-radius:5px;text-decoration:none;font-size:0.85rem;margin-right:5px;">Deactivate</a>
                                        <?php elseif ($m['is_approved'] == 0): ?>
                                        <a href="?tab=manage_members&action=activate_member&id=<?= $m['id'] ?>" onclick="return confirm('Approve this member?')" style="background:var(--success);color:white;padding:4px 10px;border-radius:5px;text-decoration:none;font-size:0.85rem;margin-right:5px;">Approve</a>
                                        <a href="#" onclick="openDeclineModal(<?= $m['id'] ?>, '<?= htmlspecialchars($m['first_name'].' '.$m['last_name'], ENT_QUOTES) ?>'); return false;" style="background:#dc2626;color:white;padding:4px 10px;border-radius:5px;text-decoration:none;font-size:0.85rem;margin-right:5px;">Decline</a>
                                        <?php else: ?>
                                        <a href="?tab=manage_members&action=activate_member&id=<?= $m['id'] ?>" onclick="return confirm('Activate this member?')" style="background:var(--success);color:white;padding:4px 10px;border-radius:5px;text-decoration:none;font-size:0.85rem;margin-right:5px;">Activate</a>
                                        <?php endif; ?>
                                        <a href="#" onclick="openEditMemberModal(<?= $m['id'] ?>, '<?= htmlspecialchars($m['first_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($m['last_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($m['username'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($m['phone'], ENT_QUOTES) ?>', '<?= htmlspecialchars($m['address'] ?? '', ENT_QUOTES) ?>'); return false;" style="background:#3b82f6;color:white;padding:4px 10px;border-radius:5px;text-decoration:none;font-size:0.85rem;margin-right:5px;display:inline-block;margin-bottom:4px;">Edit</a>
                                        <a href="?tab=manage_members&action=delete_member&id=<?= $m['id'] ?>" onclick="return confirm('Delete this member?')" style="background:var(--danger);color:white;padding:4px 10px;border-radius:5px;text-decoration:none;font-size:0.85rem;">Delete</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table></div>
                </div>
            <?php elseif ($tab == 'departments'): ?>
                <div class="page-header">
                    <h1>Church Departments</h1>
                    <p>Overview of active departments and their members.</p>
    <?php
    $dp_q2 = $conn->query("SELECT * FROM pastors WHERE is_approved = 1 LIMIT 1");
    $dp2   = $dp_q2 ? $dp_q2->fetch_assoc() : null;
    $dp_pic2  = $dp2['profile_picture'] ?? 'default_avatar.png';
    $dp_name2 = strtoupper(trim(($dp2['first_name'] ?? '') . ' ' . ($dp2['last_name'] ?? '')));
    ?>
    <span id="deptPrintPastorName" style="display:none;"><?= htmlspecialchars($dp_name2) ?></span>
    <span id="deptPrintPastorPic"  style="display:none;" data-src="uploads/<?= htmlspecialchars($dp_pic2) ?>"></span>
    <span id="deptPrintLogo"       style="display:none;" data-src="church_logo.jpg"></span>
    <span id="deptPrintDate"       style="display:none;"><?= date('F j, Y g:i A') ?></span>
                </div>
                
                <?php
                // Fetch pending requests
                $pending_transfers = $conn->query("SELECT * FROM members WHERE pending_department IS NOT NULL ORDER BY first_name ASC");
                if ($pending_transfers->num_rows > 0):
                ?>
                <div class="content-card" style="margin-bottom: 30px; border-left: 4px solid var(--warning);">
                    <h2 style="margin-bottom: 15px; color: var(--warning);">Pending Transfer Requests</h2>
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>Name</th><th>Current Dept</th><th>Requested Dept</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php while($pt = $pending_transfers->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($pt['first_name'] . ' ' . $pt['last_name']) ?></td>
                                    <td><?= htmlspecialchars($pt['department'] ?? 'None') ?></td>
                                    <td style="font-weight: 500; color: var(--primary);"><?= htmlspecialchars($pt['pending_department']) ?></td>
                                    <td>
                                        <form method="POST" action="?tab=departments" style="display:inline-flex; gap:8px;">
                                            <input type="hidden" name="handle_transfer" value="1">
                                            <input type="hidden" name="member_id" value="<?= $pt['id'] ?>">
                                            <button type="submit" name="action" value="approve" class="btn-sm btn-success" style="border:none; cursor:pointer;">Approve</button>
                                            <button type="submit" name="action" value="reject" class="btn-sm" style="border:none; background:var(--danger); color:white; cursor:pointer;">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php
                // Fetch department counts
                $dept_counts = [];
                $depts_result = $conn->query("SELECT department, COUNT(*) as count FROM members WHERE is_approved=1 GROUP BY department");
                while ($row = $depts_result->fetch_assoc()) {
                    $dept_counts[$row['department']] = $row['count'];
                }
                
                $pastors_res = $conn->query("SELECT department, gender FROM pastors WHERE is_approved=1");
                if ($pastors_res) {
                    while($pst = $pastors_res->fetch_assoc()) {
                        $eff_dept = $pst['department'];
                        if ($pst['gender'] === 'Female' && $eff_dept === 'Elders') {
                            $eff_dept = 'Womens Ministry';
                        }
                        if ($eff_dept) {
                            $dept_counts[$eff_dept] = ($dept_counts[$eff_dept] ?? 0) + 1;
                        }
                    }
                }
                $departments = ['Youths', 'Elders', 'Sunday School', 'Womens Ministry'];
                ?>
<script>
function printAllLeaders(orientation) {
    if (!orientation) orientation = 'portrait';
    var pageSize = orientation === 'landscape' ? 'A4 landscape' : 'A4 portrait';

    // Fetch church logo and pastor name from existing helpers
    var logoUrl  = document.getElementById('deptPrintLogo')   ? document.getElementById('deptPrintLogo').getAttribute('data-src')    : 'church_logo.jpg';
    var pastorEl = document.getElementById('deptPrintPastorName');
    var pastorName = pastorEl ? pastorEl.innerText.trim() : '';
    var printDate  = document.getElementById('deptPrintDate') ? document.getElementById('deptPrintDate').innerText.trim() : new Date().toLocaleDateString();

    // Collect all leader cards from the "Church Leaders Overview" section
    var cards = document.querySelectorAll('#leadersOverviewSection .leader-print-card');

    // Build the HTML
    var w = window.open('', '_blank');
    if (!w) { alert('Popup blocked! Please allow popups.'); return; }

    w.document.write('<!doctype html><html><head>');
    w.document.write('<title>All Church Leaders</title>');
    w.document.write('<base href="' + window.location.href + '">');
    w.document.write('<style>');
    w.document.write('*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;color-adjust:exact!important;}');
    w.document.write('@media print{@page{size:' + pageSize + ';margin: 0;} body{padding:15mm !important;} }');
    w.document.write('body{font-family:Arial,sans-serif;margin:0;padding:10px;padding-bottom:60px;}');
    w.document.write('.watermark{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-45deg);font-size:60px!important;color:rgba(30,58,138,0.25)!important;font-weight:bold;white-space:nowrap;z-index:999999!important;opacity:1!important;pointer-events:none;letter-spacing:4px;text-transform:uppercase;mix-blend-mode:multiply;}');
    w.document.write('.footer{position:fixed;bottom:0;left:0;right:0;text-align:center;font-size:10px;color:#777;font-style:italic;background:rgba(255,255,255,0.9);padding:5px 0;z-index:10;}');
    w.document.write('.section-title{font-size:0.85rem;font-weight:800;text-transform:uppercase;letter-spacing:0.06em;padding:6px 12px;border-radius:6px;color:#fff;margin:18px 0 10px;display:inline-block;}');
    w.document.write('.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-bottom:12px;}');
    w.document.write('.card{display:flex;align-items:center;gap:10px;padding:10px;border:1px solid #d1d5db;border-radius:8px;}');
    w.document.write('.avatar{width:50px;height:50px;border-radius:50%;object-fit:cover;flex-shrink:0;}');
    w.document.write('.name{font-weight:700;font-size:0.85rem;color:#1e3a8a;}');
    w.document.write('.role{font-size:0.75rem;color:#555;margin-top:2px;}');
    w.document.write('</style></head><body>');

    w.document.write('<div class="watermark">E.A.P.C MUNYARI CHURCH</div>');

    // Header
    w.document.write('<div style="text-align:center;border-bottom:2px solid #1e3a8a;padding-bottom:10px;position:relative;margin-bottom:16px;min-height:85px;">');
    w.document.write('<img src="' + logoUrl + '" style="position:absolute;left:20px;top:0;width:70px;height:70px;object-fit:contain;">');
    w.document.write('<img src="' + logoUrl + '" style="position:absolute;right:20px;top:0;width:70px;height:70px;object-fit:contain;">');
    w.document.write('<h2 style="margin:0;color:#1e3a8a;padding-top:8px;">E.A.P.C MUNYARI CHURCH</h2>');
    w.document.write('<h3 style="margin:4px 0;color:#1e3a8a;">CHURCH LEADERS REGISTER</h3>');
    w.document.write('<p style="margin:2px 0;font-size:0.78rem;color:#555;">Printed on: ' + printDate + '</p>');
    w.document.write('</div>');

    // Leader sections
    if (cards.length === 0) {
        w.document.write('<p style="text-align:center;color:#888;">No leaders found.</p>');
    } else {
        var currentSection = '';
        var gridOpen = false;
        cards.forEach(function(card) {
            var section = card.getAttribute('data-section') || '';
            var sectionColor = card.getAttribute('data-section-color') || '#1e3a8a';
            var name = card.getAttribute('data-name') || '';
            var role = card.getAttribute('data-role') || '';
            var pic  = card.getAttribute('data-pic')  || 'uploads/default_avatar.png';

            if (section !== currentSection) {
                if (gridOpen) w.document.write('</div>');
                w.document.write('<div class="section-title" style="background:' + sectionColor + ';">' + section + '</div>');
                w.document.write('<div class="grid">');
                gridOpen = true;
                currentSection = section;
            }
            w.document.write('<div class="card">');
            w.document.write('<img class="avatar" src="' + pic + '" onerror="this.src=\'uploads/default_avatar.png\'" style="border:2px solid ' + sectionColor + ';">');
            w.document.write('<div><div class="name">' + name + '</div><div class="role">' + role + '</div></div>');
            w.document.write('</div>');
        });
        if (gridOpen) w.document.write('</div>');
    }

    // Signature
    w.document.write('<div style="display:flex;justify-content:space-between;align-items:flex-end;margin-top:60px;padding:0 20px;page-break-inside:avoid;">');
    w.document.write('<div style="text-align:center;"><div style="font-size:0.82rem;font-weight:700;text-transform:uppercase;color:#1e3a8a;">Church Pastor</div>');
    w.document.write('<div style="font-size:0.75rem;color:#333;margin-bottom:8px;">' + pastorName + '</div>');
    w.document.write('<div style="display:flex;align-items:flex-end;gap:8px;"><span style="font-style:italic;">Sign:</span><span style="display:inline-block;border-bottom:1px solid #000;width:200px;height:14px;"></span></div>');
    w.document.write('<div style="display:flex;align-items:flex-end;gap:8px;margin-top:10px;"><span>Date:</span><span style="display:inline-block;border-bottom:1px dotted #000;width:200px;height:14px;"></span></div></div>');
    w.document.write('<div style="text-align:center;"><div style="border:2px dashed #aaa;width:100px;height:100px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:0.7rem;text-transform:uppercase;line-height:1.4;text-align:center;">Official<br>Stamp</div></div>');
    w.document.write('</div>');

    w.document.write('<div class="footer">Generated from E.A.P.C Munyari Portal | Printed on: ' + printDate + '</div>');
    w.document.write('</body></html>');
    w.document.close();
    setTimeout(function(){ w.print(); }, 600);
}

function printDepartment(deptName, containerId, leaderSpanId, orientation) {
    if (!orientation) orientation = 'landscape';
    var container = document.getElementById(containerId);
    if (!container) { alert('Could not find department table.'); return; }

    var pastorName = document.getElementById('deptPrintPastorName') ? document.getElementById('deptPrintPastorName').innerText.trim() : '';
    var logoUrl    = document.getElementById('deptPrintLogo') ? document.getElementById('deptPrintLogo').getAttribute('data-src') : 'church_logo.jpg';
    var printDate  = document.getElementById('deptPrintDate') ? document.getElementById('deptPrintDate').innerText.trim() : new Date().toLocaleString();
    var leaderSpan = leaderSpanId ? document.getElementById(leaderSpanId) : null;
    var leaderName = leaderSpan ? leaderSpan.getAttribute('data-name') : 'N/A';
    var viceName   = leaderSpan ? leaderSpan.getAttribute('data-vice') : 'N/A';
    var leaderPic  = leaderSpan ? leaderSpan.getAttribute('data-pic') : 'uploads/default_avatar.png';
    var leaderRole = leaderSpan ? leaderSpan.getAttribute('data-role') : 'Chairperson';
    var vicePic    = leaderSpan ? leaderSpan.getAttribute('data-vice-pic') : 'uploads/default_avatar.png';
    var viceRole   = leaderSpan ? leaderSpan.getAttribute('data-vice-role') : 'Vice Chairperson';
    
    var displayNames = (viceName && viceName !== 'N/A') ? (leaderName + ' / ' + viceName) : leaderName;
    var sigRoleTitle = (viceName && viceName !== 'N/A') ? (leaderRole + ' / VICE') : leaderRole;

    var theadRow = container.querySelector('thead') ? container.querySelector('thead').innerHTML : '';
    theadRow = theadRow.replace(/<tr>/i, '<tr style="background:#1e3a8a;color:white;-webkit-print-color-adjust:exact;">');
    var tbody = container.querySelector('tbody') ? container.querySelector('tbody').innerHTML : '';
    var rowCount = container.querySelectorAll('tbody tr').length;

    var wmSize = orientation === 'landscape' ? '3.2rem' : '2.0rem';
    var pageSize = orientation === 'landscape' ? 'A4 landscape' : 'A4 portrait';

    var w = window.open('', '_blank');
    if (!w) { alert('Popup blocked! Please allow popups.'); return; }

    w.document.write('<!doctype html><html><head>');
    w.document.write('<title>' + deptName + ' Department</title>');
    w.document.write('<base href="' + window.location.href + '">');
    w.document.write('<style>');
    w.document.write('*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;color-adjust:exact!important;}');
    w.document.write('@media print{@page{size:' + pageSize + ';margin: 0;} body{padding:15mm !important;} }');
    w.document.write('body{font-family:Arial,sans-serif;margin:0;padding:10px;padding-bottom:60px;}');
    w.document.write('table{width:100%;border-collapse:collapse;}');
    w.document.write('th{background:#1e3a8a;color:white;padding:7px 8px;font-size:0.8rem;text-align:left;}');
    w.document.write('td{padding:6px 8px;border:1px solid #ccc;font-size:0.79rem;vertical-align:middle;}');
    w.document.write('.badge{display:inline-block;padding:3px 8px;border-radius:10px;font-size:0.72rem;font-weight:600;}');
    w.document.write('.no-print{display:none!important;}');
    w.document.write('.watermark{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-45deg);font-size:' + wmSize + '!important;color:rgba(30,58,138,0.25)!important;font-weight:bold;white-space:nowrap;z-index:999999!important;opacity:1!important;pointer-events:none;letter-spacing:4px;text-transform:uppercase;mix-blend-mode:multiply;}');
    w.document.write('.footer{position:fixed;bottom:0;left:0;right:0;text-align:center;font-size:10px;color:#777;font-style:italic;background:rgba(255,255,255,0.9);padding:5px 0;z-index:10;}');
    w.document.write('</style></head><body>');

    w.document.write('<div class="watermark">E.A.P.C MUNYARI CHURCH</div>');

    // Leader photo row
    w.document.write('<div style="display:flex;align-items:center;justify-content:center;gap:40px;margin-bottom:10px;">');
    
    // Main Leader
    w.document.write('<div style="display:flex;align-items:center;gap:15px;">');
    w.document.write('<img src="' + leaderPic + '" alt="Leader" style="width:70px;height:70px;border-radius:50%;object-fit:cover;border:3px solid #1e3a8a;">');
    w.document.write('<div><div style="font-size:0.75rem;color:#555;text-transform:uppercase;">' + leaderRole + '</div>');
    w.document.write('<div style="font-size:1.2rem;font-weight:700;color:#1e3a8a;">' + leaderName + '</div></div></div>');
    
    // Vice Leader (if present and not N/A)
    if (viceName && viceName !== 'N/A') {
        w.document.write('<div style="display:flex;align-items:center;gap:15px;">');
        w.document.write('<img src="' + vicePic + '" alt="Vice Leader" style="width:70px;height:70px;border-radius:50%;object-fit:cover;border:3px solid #6366f1;">');
        w.document.write('<div><div style="font-size:0.75rem;color:#555;text-transform:uppercase;">' + viceRole + '</div>');
        w.document.write('<div style="font-size:1.2rem;font-weight:700;color:#1e3a8a;">' + viceName + '</div></div></div>');
    }
    w.document.write('</div>');

    // Church header with dual logos (NO circle on logos)
    w.document.write('<div style="text-align:center;border-bottom:2px solid #1e3a8a;padding-bottom:10px;position:relative;margin-bottom:14px;min-height:85px;">');
    w.document.write('<img src="' + logoUrl + '" style="position:absolute;left:20px;top:0;width:70px;height:70px;object-fit:contain;">');
    w.document.write('<img src="' + logoUrl + '" style="position:absolute;right:20px;top:0;width:70px;height:70px;object-fit:contain;">');
    w.document.write('<h2 style="margin:0;color:#1e3a8a;padding-top:8px;">E.A.P.C MUNYARI CHURCH</h2>');
    w.document.write('<h3 style="margin:3px 0;color:#1e3a8a;">' + deptName.toUpperCase() + ' DEPARTMENT (TOTAL: ' + rowCount + ')</h3>');
        w.document.write('<p style="margin:2px 0;font-size:0.78rem;color:#555;">Printed on: ' + printDate + '</p>');
w.document.write('</div>');

    // Table
    w.document.write('<table><thead><tr>' + theadRow + '</tr></thead><tbody>' + tbody + '</tbody></table>');

    // Signature block
    w.document.write('<div style="display:flex;justify-content:space-between;align-items:flex-end;margin-top:60px;padding:0 20px;page-break-inside:avoid;">');
    w.document.write('<div style="text-align:center;"><div style="font-size:0.82rem;font-weight:700;text-transform:uppercase;color:#1e3a8a;">Church Pastor</div>');
    w.document.write('<div style="font-size:0.75rem;color:#333;margin-bottom:8px;">' + pastorName + '</div>');
    w.document.write('<div style="display:flex;align-items:flex-end;gap:8px;"><span style="font-style:italic;">Sign:</span><span style="display:inline-block;border-bottom:1px solid #000;width:200px;height:14px;"></span></div>');
    w.document.write('<div style="display:flex;align-items:flex-end;gap:8px;margin-top:10px;"><span>Date:</span><span style="display:inline-block;border-bottom:1px dotted #000;width:200px;height:14px;"></span></div></div>');
    w.document.write('<div style="text-align:center;"><div style="font-size:0.82rem;font-weight:700;text-transform:uppercase;color:#1e3a8a;">' + sigRoleTitle.toUpperCase() + '</div>');
    w.document.write('<div style="font-size:0.75rem;color:#333;margin-bottom:8px;">' + displayNames + '</div>');
    w.document.write('<div style="display:flex;align-items:flex-end;gap:8px;"><span style="font-style:italic;">Sign:</span><span style="display:inline-block;border-bottom:1px solid #000;width:200px;height:14px;"></span></div>');
    w.document.write('<div style="display:flex;align-items:flex-end;gap:8px;margin-top:10px;"><span>Date:</span><span style="display:inline-block;border-bottom:1px dotted #000;width:200px;height:14px;"></span></div></div>');
    w.document.write('<div style="text-align:center;"><div style="border:2px dashed #aaa;width:100px;height:100px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:0.7rem;text-transform:uppercase;line-height:1.4;text-align:center;">Official<br>Stamp</div></div>');
    w.document.write('</div>');

    w.document.write('<div class="footer">Generated from E.A.P.C Munyari Portal &nbsp;|&nbsp; Printed on: ' + (typeof printDate !== 'undefined' ? printDate : new Date().toLocaleString()) + '</div>');
    w.document.write('</body></html>');
    w.document.close();
    w.focus();
    setTimeout(function(){ w.print(); }, 600);
}
</script>
                <div class="dashboard-grid">
                    <?php foreach ($departments as $dept): ?>
                        <div class="stat-card">
                            <h3 style="font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;"><?= htmlspecialchars($dept) ?></h3>
                            <div class="value" style="color: var(--primary);"><?= isset($dept_counts[$dept]) ? $dept_counts[$dept] : 0 ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                                <?php foreach ($departments as $dept): ?>
                    <?php
                    $dept_esc = $conn->real_escape_string($dept);

                    // Role rank: 1=chairman/chairlady/chairperson, 2=vice chair, 3=secretary/treasurer/subsidiary, 99=member
                    $dept_members_arr = [];
                    $dept_mem_res = $conn->query("
                        SELECT *,
                        CASE
                            WHEN LOWER(church_role) REGEXP '(^|, *)(youth |women |elder |sunday school )?(chairman|chairperson|chairlady)( *,|$)' THEN 1
                            WHEN LOWER(church_role) REGEXP '(^|, *)vice (youth |women |elder |sunday school )?(chairman|chairperson|chairlady)( *,|$)' THEN 2
                            WHEN LOWER(church_role) REGEXP 'secretary|treasurer|organiz|disciplin|choir|sport|graduand' THEN 3
                            WHEN LOWER(church_role) REGEXP 'mama youth|baba youth' THEN 3
                            WHEN church_role IS NULL OR church_role='' OR LOWER(church_role)='member' THEN 99
                            ELSE 5
                        END AS role_rank
                        FROM members
                        WHERE is_approved=1 AND department='$dept_esc'
                    ");
                    if ($dept_mem_res) {
                        while($row = $dept_mem_res->fetch_assoc()) {
                            $row['is_pastor'] = false;
                            $dept_members_arr[] = $row;
                        }
                    }
                    $pastors_res = $conn->query("SELECT * FROM pastors WHERE is_approved = 1");
                    if ($pastors_res) {
                        while($pst = $pastors_res->fetch_assoc()) {
                            $eff_dept = $pst['department'];
                            if ($pst['gender'] === 'Female' && $eff_dept === 'Elders') {
                                $eff_dept = 'Womens Ministry';
                            }
                            if ($eff_dept === $dept) {
                                $pst['is_pastor'] = true;
                                $pst['church_role'] = 'Church Pastor';
                                $pst['role_rank'] = 0;
                                // address already holds the pastor's actual residence (e.g. Nkondi)
                                $dept_members_arr[] = $pst;
                            }
                        }
                    }
                    usort($dept_members_arr, function($a, $b) {
                        if ($a['role_rank'] != $b['role_rank']) return $a['role_rank'] <=> $b['role_rank'];
                        return strcmp($a['first_name'], $b['first_name']);
                    });

                    // Find top leaders dynamically based on rank (1 = Chair, 2 = Vice, 3 = Sec, etc.)
                    $highest_leader = null;
                    $second_leader = null;
                    
                    foreach ($dept_members_arr as $dm) {
                        $rank = (int)($dm['role_rank'] ?? 99);
                        if ($rank > 0 && $rank <= 2) { // ONLY RANK 1 (Chair) or RANK 2 (Vice)
                            if (!$highest_leader) {
                                $highest_leader = $dm;
                            } elseif (!$second_leader) {
                                $second_leader = $dm;
                                break;
                            }
                        }
                    }

                    $leader_name = $highest_leader ? strtoupper(trim($highest_leader['first_name'].' '.$highest_leader['last_name'])) : 'N/A';
                    $leader_pic  = $highest_leader ? ($highest_leader['profile_picture'] ?? 'default_avatar.png') : 'default_avatar.png';
                    
                    $vice_name = $second_leader ? strtoupper(trim($second_leader['first_name'].' '.$second_leader['last_name'])) : 'N/A';
                    
                    $d = strtolower(trim($dept));
                    if ($d === 'youths') $leader_role = 'Youth Chairperson';
                    elseif ($d === 'womens ministry') $leader_role = 'Women Chairlady';
                    elseif ($d === 'elders') $leader_role = 'Elder Chairman';
                    elseif ($d === 'sunday school') $leader_role = 'Sunday School Patron';
                    else $leader_role = 'Chairperson';

                    if ($highest_leader && !empty($highest_leader['church_role'])) {
                        $best = get_best_role_from_string($highest_leader['church_role']);
                        if (strtolower($best) !== 'member') {
                            $leader_role = role_display_label($best, $dept, $highest_leader['gender'] ?? null);
                        }
                    }
                    
                    $vice_pic = $second_leader ? ($second_leader['profile_picture'] ?? 'default_avatar.png') : 'default_avatar.png';
                    
                    if ($d === 'youths') $vice_role = 'Vice Youth Chairperson';
                    elseif ($d === 'womens ministry') $vice_role = 'Vice Women Chairlady';
                    elseif ($d === 'elders') $vice_role = 'Vice Elder Chairman';
                    elseif ($d === 'sunday school') $vice_role = 'Vice Sunday School Patron';
                    else $vice_role = 'Vice Chairperson';

                    if ($second_leader && !empty($second_leader['church_role'])) {
                        $best_vice = get_best_role_from_string($second_leader['church_role']);
                        if (strtolower($best_vice) !== 'member') {
                            $vice_role = role_display_label($best_vice, $dept, $second_leader['gender'] ?? null);
                        }
                    }
                    
                    $leader_id   = 'deptLeader_'.str_replace(' ','',$dept);

                    if (count($dept_members_arr) > 0):
                    ?>
                        <!-- Hidden dept leader data for JS print -->
                        <span id="<?= $leader_id ?>"
                              data-name="<?= htmlspecialchars($leader_name) ?>"
                              data-vice="<?= htmlspecialchars($vice_name) ?>"
                              data-pic="uploads/<?= htmlspecialchars($leader_pic) ?>"
                              data-role="<?= htmlspecialchars($leader_role) ?>"
                              data-vice-pic="uploads/<?= htmlspecialchars($vice_pic) ?>"
                              data-vice-role="<?= htmlspecialchars($vice_role) ?>"
                              style="display:none;"></span>

                        <div class="content-card" style="margin-top: 30px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 20px;">
                                <div style="display:flex;align-items:center;gap:14px;">
                                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                    <h2 style="margin:0;"><?= htmlspecialchars($dept) ?></h2>
                                    <span style="background:linear-gradient(135deg,#1e3a8a,#6366f1);color:white;font-size:0.82rem;font-weight:800;padding:4px 12px;border-radius:20px;">
                                        <?= count($dept_members_arr) ?> Members
                                    </span>
                                    <?php if ($dept_leader): ?>
                                    <div style="display:flex;align-items:center;gap:8px;padding:6px 12px;border:1px solid var(--border-color);border-radius:20px;background:var(--bg-main);">
                                        <img src="uploads/<?= htmlspecialchars($leader_pic) ?>" style="width:28px;height:28px;border-radius:50%;object-fit:cover;">
                                        <span style="font-size:0.82rem;font-weight:600;"><?= htmlspecialchars(ucwords(strtolower($leader_role))) ?>: <?= htmlspecialchars($leader_name) ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                </div>
                                <div style="display:flex;gap:10px;">
                                    <button onclick="printDepartment('<?= htmlspecialchars($dept, ENT_QUOTES) ?>', 'dept_print_<?= str_replace(' ', '', $dept) ?>', '<?= $leader_id ?>', 'landscape')" class="btn-submit" style="width:auto; margin:0; padding:8px 16px; background:linear-gradient(135deg,#2563eb,#6366f1); border:none; box-shadow:0 2px 8px rgba(37,99,235,0.3); font-weight:600; display:inline-flex; align-items:center; gap:8px; color:white; cursor:pointer; border-radius:8px;">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                        Print Landscape
                                    </button>
                                    <button onclick="printDepartment('<?= htmlspecialchars($dept, ENT_QUOTES) ?>', 'dept_print_<?= str_replace(' ', '', $dept) ?>', '<?= $leader_id ?>', 'portrait')" class="btn-submit" style="width:auto; margin:0; padding:8px 16px; background:linear-gradient(135deg,#4f46e5,#4338ca); border:none; box-shadow:0 2px 8px rgba(37,99,235,0.3); font-weight:600; display:inline-flex; align-items:center; gap:8px; color:white; cursor:pointer; border-radius:8px;">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                        Print Portrait
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive" id="dept_print_<?= str_replace(' ', '', $dept) ?>">
                                <table>
                                    <thead><tr><th>#</th><th>Photo</th><th>Name</th><th>Phone</th><th>Role</th><th>Residence</th><?php if ($dept === 'Youths' || $dept === 'Sunday School') echo "<th class='no-print'>Actions</th>"; ?></tr></thead>
                                    <tbody>
                                        <?php $dpi=1; foreach($dept_members_arr as $dm):
                                            $role_r = (int)($dm['role_rank'] ?? 99);
                                            $row_bg = $role_r == 0 ? 'background:rgba(30,58,138,0.1);font-weight:700;'
                                                    : ($role_r == 1 ? 'background:rgba(37,99,235,0.07);font-weight:700;'
                                                    : ($role_r == 2 ? 'background:rgba(99,102,241,0.05);font-weight:600;'
                                                    : ($role_r <= 3 ? 'background:rgba(16,185,129,0.05);'
                                                    : '')));
                                        ?>
                                            <tr style="<?= $row_bg ?>">
                                                <td><?= $dpi++ ?></td>
                                                <td><img src="uploads/<?= htmlspecialchars($dm['profile_picture'] ?? 'default_avatar.png') ?>" style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:1px solid #ccc;cursor:zoom-in;" onclick="viewProfileImage(this.src);"></td>
                                                <td><?= htmlspecialchars($dm['first_name'] . ' ' . $dm['last_name']) ?></td>
                                                <td><?= htmlspecialchars($dm['phone'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($dm['church_role'] ?? 'Member') ?></td>
                                                <td><?= htmlspecialchars($dm['address'] ?? '-') ?></td>
                                                <?php if ($dept === 'Youths' || $dept === 'Sunday School'): ?>
                                                    <td class="no-print">
                                                        <?php if ($dm['is_pastor']): ?>
                                                            <span style="font-size:0.75rem; color:#888;">Pastor</span>
                                                        <?php elseif ($dept === 'Youths'): ?>
                                                            <?php 
                                                                $t_dest = (strtolower(trim($dm['gender'] ?? '')) === 'female') ? 'Womens Ministry' : 'Elders'; 
                                                            ?>
                                                            <a href="?tab=departments&action=transfer_youth&id=<?= $dm['id'] ?>&dest=<?= urlencode($t_dest) ?>" onclick="return confirm('Transfer this member to <?= $t_dest ?>?')" style="background:#0ea5e9;color:white;padding:4px 8px;border-radius:4px;text-decoration:none;font-size:0.75rem;">Transfer</a>
                                                        <?php else: ?>
                                                            <a href="?tab=manage_sunday_school&highlight_member_id=<?= $dm['id'] ?>" style="background:#0ea5e9;color:white;padding:4px 8px;border-radius:4px;text-decoration:none;font-size:0.75rem;">View in SS</a>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

            <?php elseif ($tab == 'desired_roles'): ?>
                <div class="page-header">
                    <h1>Village & Desired Roles</h1>
                    <p>Members categorized by church village, department, and chosen service roles.</p>
                </div>
                
                <?php
                // Handle Approval/Rejection of Village Updates
                if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['handle_village_update'])) {
                    $update_id = (int)$_POST['member_id'];
                    $action = $_POST['action'];
                    $mem = $conn->query("SELECT pending_church_village, pending_desired_role_pref FROM members WHERE id = $update_id")->fetch_assoc();
                    if ($mem && $mem['pending_church_village']) {
                        if ($action === 'approve') {
                            $new_v = $conn->real_escape_string($mem['pending_church_village']);
                            $new_drp = $mem['pending_desired_role_pref'] ? "'" . $conn->real_escape_string($mem['pending_desired_role_pref']) . "'" : "NULL";
                            $conn->query("UPDATE members SET church_village = '$new_v', desired_role_pref = $new_drp, pending_church_village = NULL, pending_desired_role_pref = NULL WHERE id = $update_id");
                            
                            $msg = $conn->real_escape_string("Your request to update your church village to $new_v has been approved.");
                            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($update_id, 'member', '$msg')");
                            echo "<div style='background:rgba(16,185,129,0.1);color:var(--success);padding:12px 16px;border-radius:8px;margin-bottom:16px;'>Update approved!</div>";
                        } else if ($action === 'reject') {
                            $conn->query("UPDATE members SET pending_church_village = NULL, pending_desired_role_pref = NULL WHERE id = $update_id");
                            $msg = $conn->real_escape_string("Your request to update your church village was rejected by the Pastor.");
                            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($update_id, 'member', '$msg')");
                            echo "<div style='background:rgba(239,68,68,0.1);color:var(--danger);padding:12px 16px;border-radius:8px;margin-bottom:16px;'>Update rejected.</div>";
                        }
                    }
                }
                

                ?>
                <!-- Assign Village Leader Form -->
                <div class="content-card" style="margin-bottom:30px; border-left:4px solid var(--primary);">
                    <h2 style="margin-bottom:15px; color:var(--text-main); font-size:1.1rem; display:flex; align-items:center; gap:8px;">
                        <svg width="20" height="20" fill="none" stroke="var(--primary)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        Assign Church Village Leader
                    </h2>
                    <form method="POST" action="?tab=desired_roles" style="display:flex; flex-wrap:wrap; gap:16px; align-items:flex-end;">
                        <input type="hidden" name="assign_village_leader" value="1">
                        <div style="flex:1; min-width:200px;">
                            <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-muted); margin-bottom:6px;">Select Church Village</label>
                            <select name="village" id="village_leader_sel" required style="width:100%; padding:10px 14px; border:1px solid var(--border-color); border-radius:8px; background:var(--bg-lighter); color:var(--text-main);" onchange="filterVillageLeaderMembers(this.value)">
                                <option value="">-- Choose Village --</option>
                                <option value="Akoritho">Akoritho</option>
                                <option value="Philadelphia">Philadelphia</option>
                                <option value="Bethsaida">Bethsaida</option>
                            </select>
                        </div>
                        <div style="flex:1; min-width:200px; position:relative;">
                            <label style="display:block; font-size:0.85rem; font-weight:600; color:var(--text-muted); margin-bottom:6px;">Select Member</label>
                            <div id="vlm_overlay" style="position:absolute;top:22px;left:0;right:0;bottom:0;z-index:10;cursor:pointer;" onclick="alert('⚠️ Please select a Church Village first!');document.getElementById('village_leader_sel').focus();"></div>
                            <select name="leader_id" id="village_leader_member" required style="width:100%; padding:10px 14px; border:1px solid var(--border-color); border-radius:8px; background:var(--bg-lighter); color:var(--text-main); opacity:0.5;">
                                <option value="">-- Choose Member --</option>
                                <?php
                                $mems = $conn->query("SELECT id, first_name, last_name, church_village FROM members WHERE is_approved = 1 AND church_village IN ('Akoritho', 'Philadelphia', 'Bethsaida') ORDER BY church_village, first_name");
                                if ($mems) { while ($m = $mems->fetch_assoc()) { echo '<option value="' . $m['id'] . '" data-village="' . $m['church_village'] . '" style="display:none;">' . htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) . '</option>'; } }
                                ?>
                            </select>
                        </div>
                        <div><button type="submit" class="btn-primary" style="padding:10px 20px;">Assign Leader</button></div>
                    </form>
                    <script>
                    function filterVillageLeaderMembers(v) {
                        var select = document.getElementById('village_leader_member');
                        var overlay = document.getElementById('vlm_overlay');
                        select.value = '';
                        if (v) {
                            overlay.style.display = 'none';
                            select.style.opacity = '1';
                        } else {
                            overlay.style.display = 'block';
                            select.style.opacity = '0.5';
                        }
                        select.querySelectorAll('option[data-village]').forEach(function(o) {
                            o.style.display = (o.getAttribute('data-village') === v) ? '' : 'none';
                        });
                    }
                    </script>
                </div>
                <?php
                // Fetch Pending Updates
                $pending_updates = $conn->query("SELECT id, first_name, last_name, church_village, pending_church_village, desired_role_pref, pending_desired_role_pref FROM members WHERE pending_church_village IS NOT NULL ORDER BY first_name ASC");
                if ($pending_updates && $pending_updates->num_rows > 0):
                ?>
                <div class="content-card" style="margin-bottom: 30px; border-left: 4px solid var(--warning);">
                    <h2 style="margin-bottom: 15px; color: var(--warning); display:flex; align-items:center; gap:8px;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Pending Village Updates
                    </h2>
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>Member</th><th>Current</th><th>Requested Update</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php while($pu = $pending_updates->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($pu['first_name'] . ' ' . $pu['last_name']) ?></td>
                                    <td>
                                        <span style="display:block;font-size:0.9rem;"><?= htmlspecialchars($pu['church_village'] ?? 'None') ?></span>
                                        <span style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($pu['desired_role_pref'] ?? 'No role') ?></span>
                                    </td>
                                    <td>
                                        <span style="display:block;font-size:0.9rem;font-weight:600;color:var(--primary);">&rarr; <?= htmlspecialchars($pu['pending_church_village']) ?></span>
                                        <span style="font-size:0.75rem;color:var(--text-muted);">&rarr; <?= htmlspecialchars($pu['pending_desired_role_pref'] ?? 'No role') ?></span>
                                    </td>
                                    <td>
                                        <form method="POST" action="?tab=desired_roles" style="display:inline-flex; gap:8px;">
                                            <input type="hidden" name="handle_village_update" value="1">
                                            <input type="hidden" name="member_id" value="<?= $pu['id'] ?>">
                                            <button type="submit" name="action" value="approve" class="btn-sm btn-success" style="border:none; cursor:pointer;">Approve</button>
                                            <button type="submit" name="action" value="reject" class="btn-sm" style="border:none; background:var(--danger); color:white; cursor:pointer;">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <?php
                $villages = ['Akoritho', 'Philadelphia', 'Bethsaida'];
                $village_colors = ['Akoritho' => '#6366f1', 'Philadelphia' => '#0ea5e9', 'Bethsaida' => '#10b981'];
                ?>
                
                <!-- ═══ SECTION 1: Members by Village + Department ═══ -->
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
                    <h2 style="font-size:1.1rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin:0; display:flex; align-items:center; gap:10px;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Church Villages &mdash; Members &amp; Departments
                    </h2>
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <span style="font-size:0.9rem; font-weight:800; color:var(--text-main); margin-right:4px;">Print All Villages:</span>
                        <a href="print_all_villages.php?mode=landscape" target="_blank" style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#2563eb,#6366f1);color:white;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:0.85rem;font-weight:700;text-decoration:none;box-shadow:0 2px 8px rgba(37,99,235,0.3);">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Print Landscape
                        </a>
                        <a href="print_all_villages.php?mode=portrait" target="_blank" style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#4f46e5,#4338ca);color:white;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:0.85rem;font-weight:700;text-decoration:none;box-shadow:0 2px 8px rgba(79,70,229,0.3);">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Print Portrait
                        </a>
                    </div>
                </div>
                
                <?php foreach ($villages as $v): 
                    $v_color = $village_colors[$v];
                    $v_members = $conn->query("SELECT first_name, last_name, department, church_role, phone, desired_role_pref, profile_picture, church_village, is_village_leader, 'Member' AS person_type FROM members WHERE church_village='$v' ORDER BY is_village_leader DESC, department, first_name");
                    $v_count = $v_members ? $v_members->num_rows : 0;
                    // Also fetch pastors who chose this village
                    $v_pastors = $conn->query("SELECT first_name, last_name, department, role AS church_role, phone, desired_role_pref, profile_picture, church_village, 0 AS is_village_leader, 'Pastor' AS person_type FROM pastors WHERE church_village='$v' AND is_approved=1 ORDER BY first_name");
                    $v_pastor_rows = [];
                    if ($v_pastors) { while ($pr = $v_pastors->fetch_assoc()) $v_pastor_rows[] = $pr; }
                    $v_total = $v_count + count($v_pastor_rows);
                ?>
                <div class="content-card" style="margin-bottom:28px; border-top:4px solid <?= $v_color ?>;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
                        <h2 style="margin:0; color:<?= $v_color ?>; display:flex; align-items:center; gap:10px; font-size:1.2rem;">
                            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            <?= $v ?> Village
                        </h2>
                        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                            <span class="badge" style="background:<?= $v_color ?>22; color:<?= $v_color ?>; font-weight:700; font-size:1rem; padding:6px 14px;"><?= $v_total ?> Members</span>
                            <button onclick="printVillageTable('village_table_<?= str_replace(' ', '_', $v) ?>', '<?= $v ?>', 'landscape')" style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#2563eb,#6366f1);color:white;border:none;padding:7px 14px;border-radius:8px;cursor:pointer;font-size:0.82rem;font-weight:600;">Print Landscape</button>
                            <button onclick="printVillageTable('village_table_<?= str_replace(' ', '_', $v) ?>', '<?= $v ?>', 'portrait')" style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#4f46e5,#4338ca);color:white;border:none;padding:7px 14px;border-radius:8px;cursor:pointer;font-size:0.82rem;font-weight:600;">Print Portrait</button>
                        </div>
                    </div>
                    
                    <?php if ($v_total > 0): ?>
                    <div class="table-responsive" id="village_table_<?= str_replace(' ', '_', $v) ?>">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Photo</th>
                                    <th>Full Name</th>
                                    <th>Village</th>
                                    <th>Department</th>
                                    <th>Church Role</th>
                                    <th>Service Role Chosen</th>
                                    <th>Phone</th>
                                </tr>
                            </thead>
                            <tbody>
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
                                <td><?= htmlspecialchars($pr['department'] ?: 'Pastoral') ?></td>
                                <td><span class="badge" style="background:rgba(37,99,235,0.1); color:var(--primary);"><?= htmlspecialchars($pr['church_role'] ?: 'Pastor') ?></span></td>
                                <td><?php if ($dsr): ?><span class="badge" style="background:<?= $dsr_color ?>22; color:<?= $dsr_color ?>;font-weight:700;"><?= htmlspecialchars($dsr) ?></span><?php else: ?><span style="color:var(--text-muted); font-size:0.85rem;">—</span><?php endif; ?></td>
                                <td style="color:var(--text-muted); font-size:0.85rem;"><?= htmlspecialchars($pr['phone'] ?? '—') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p style="color:var(--text-muted); text-align:center; padding:20px 0;">No members have selected <?= $v ?> as their village yet.</p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                
                <hr style="border:0; border-top:2px solid var(--border-color); margin:30px 0;">
                
                <!-- ═══ SECTION 2: Church Cleaners ═══ -->
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
                    <h2 style="font-size:1.1rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin:0; display:flex; align-items:center; gap:10px;">
                        <span style="font-size:1.3rem;">🧹</span> Church Cleaners &amp; Cookers
                    </h2>
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <span style="font-size:0.9rem; font-weight:800; color:var(--text-main); margin-right:4px;">Print Cleaners &amp; Cookers:</span>
                        <a href="print_volunteers.php?mode=landscape" target="_blank" style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#f59e0b,#ea580c);color:white;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:0.85rem;font-weight:700;text-decoration:none;box-shadow:0 2px 8px rgba(245,158,11,0.3);">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Print Landscape
                        </a>
                        <a href="print_volunteers.php?mode=portrait" target="_blank" style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#d97706,#b45309);color:white;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:0.85rem;font-weight:700;text-decoration:none;box-shadow:0 2px 8px rgba(217,119,6,0.3);">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            Print Portrait
                        </a>
                    </div>
                </div>
                <div class="content-card" style="margin-bottom:28px; border-top:4px solid #0ea5e9;">
                    <?php
                    $cleaners = $conn->query("SELECT first_name, last_name, church_village, department, church_role, phone FROM members WHERE desired_role_pref='Church Cleaner' ORDER BY church_village, first_name");
                    $cleaners_count = $cleaners ? $cleaners->num_rows : 0;
                    ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                        <h3 style="margin:0; color:#0ea5e9;">🧹 Church Cleaners</h3>
                        <span class="badge" style="background:#0ea5e922; color:#0ea5e9; font-size:1rem; padding:6px 14px;"><?= $cleaners_count ?> Volunteers</span>
                    </div>
                    <?php if ($cleaners_count > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Full Name</th>
                                    <th>Village</th>
                                    <th>Department</th>
                                    <th>Phone</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php $i = 1; while($cl = $cleaners->fetch_assoc()): 
                                $vc = $village_colors[$cl['church_village']] ?? '#94a3b8';
                            ?>
                            <tr>
                                <td style="color:var(--text-muted);"><?= $i++ ?></td>
                                <td style="font-weight:600;"><?= htmlspecialchars($cl['first_name'] . ' ' . $cl['last_name']) ?></td>
                                <td><span class="badge" style="background:<?= $vc ?>22; color:<?= $vc ?>;"><?= htmlspecialchars($cl['church_village'] ?: '—') ?></span></td>
                                <td><?= htmlspecialchars($cl['department'] ?: 'General Church') ?></td>
                                <td style="color:var(--text-muted); font-size:0.85rem;"><?= htmlspecialchars($cl['phone'] ?? '—') ?></td>
                            </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p style="color:var(--text-muted); text-align:center; padding:20px 0;">No members have volunteered as Church Cleaners yet.</p>
                    <?php endif; ?>
                </div>
                
                <!-- ═══ SECTION 3: Church Cookers ═══ -->
                <h2 style="font-size:1.1rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:16px; display:flex; align-items:center; gap:10px;">
                    <span style="font-size:1.3rem;">🍳</span> Church Cookers — All Villages
                </h2>
                <div class="content-card" style="margin-bottom:28px; border-top:4px solid #f59e0b;">
                    <?php
                    $cookers = $conn->query("SELECT first_name, last_name, church_village, department, church_role, phone FROM members WHERE desired_role_pref='Church Cooker' ORDER BY church_village, first_name");
                    $cookers_count = $cookers ? $cookers->num_rows : 0;
                    ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                        <h3 style="margin:0; color:#f59e0b;">🍳 Church Cookers</h3>
                        <span class="badge" style="background:#f59e0b22; color:#f59e0b; font-size:1rem; padding:6px 14px;"><?= $cookers_count ?> Volunteers</span>
                    </div>
                    <?php if ($cookers_count > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Full Name</th>
                                    <th>Village</th>
                                    <th>Department</th>
                                    <th>Phone</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php $i = 1; while($ck = $cookers->fetch_assoc()): 
                                $vc = $village_colors[$ck['church_village']] ?? '#94a3b8';
                            ?>
                            <tr>
                                <td style="color:var(--text-muted);"><?= $i++ ?></td>
                                <td style="font-weight:600;"><?= htmlspecialchars($ck['first_name'] . ' ' . $ck['last_name']) ?></td>
                                <td><span class="badge" style="background:<?= $vc ?>22; color:<?= $vc ?>;"><?= htmlspecialchars($ck['church_village'] ?: '—') ?></span></td>
                                <td><?= htmlspecialchars($ck['department'] ?: 'General Church') ?></td>
                                <td style="color:var(--text-muted); font-size:0.85rem;"><?= htmlspecialchars($ck['phone'] ?? '—') ?></td>
                            </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p style="color:var(--text-muted); text-align:center; padding:20px 0;">No members have volunteered as Church Cookers yet.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($tab == 'manage_worshippers'): ?>
                <div class="page-header">
                    <h1>Worship Department Overview</h1>
                    <p>Monitor the total number of registered worshippers and their assigned tasks.</p>
                </div>
                <?php render_worship_leader_profiles_card($conn); ?>
                
                <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:20px;">
                    <?php 
                    $worshippers_count = $conn->query("SELECT COUNT(*) as c FROM members WHERE LOWER(church_role) LIKE '%worshipper%' OR LOWER(department) LIKE '%worship%' OR desired_role_pref = 'Worshipper'")->fetch_assoc()['c'];
                    $total_tasks = $conn->query("SELECT COUNT(*) as c FROM worship_tasks")->fetch_assoc()['c'];
                    $pending_tasks = $conn->query("SELECT COUNT(*) as c FROM worship_tasks WHERE status='Pending'")->fetch_assoc()['c'];
                    ?>
                    <div class="stat-card">
                        <h3>Total Worshippers</h3>
                        <div class="value" style="color:var(--primary);"><?= $worshippers_count ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Total Tasks</h3>
                        <div class="value" style="color:var(--success);"><?= $total_tasks ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Pending Tasks</h3>
                        <div class="value" style="color:var(--warning);"><?= $pending_tasks ?></div>
                    </div>
                </div>

                <div class="content-card">
                    <h2>Worshipper Tasks</h2>
                    <?php
                    $tasks = $conn->query("SELECT wt.*, m1.first_name as w_fn, m1.last_name as w_ln, m2.first_name as a_fn, m2.last_name as a_ln 
                                           FROM worship_tasks wt 
                                           JOIN members m1 ON wt.member_id = m1.id 
                                           JOIN members m2 ON wt.assigned_by = m2.id 
                                           ORDER BY wt.assigned_at DESC");
                    ?>
                    <?php if ($tasks && $tasks->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Worshipper</th>
                                        <th>Task</th>
                                        <th>Assigned By</th>
                                        <th>Assigned At</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($t = $tasks->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($t['w_fn'] . ' ' . $t['w_ln']) ?></td>
                                            <td><?= htmlspecialchars($t['task_name']) ?></td>
                                            <td><?= htmlspecialchars($t['a_fn'] . ' ' . $t['a_ln']) ?></td>
                                            <td><?= date('M j, Y g:i A', strtotime($t['assigned_at'])) ?></td>
                                            <td>
                                                <span style="background: <?= $t['status'] === 'Completed' ? 'rgba(16,185,129,0.1)' : 'rgba(245,158,11,0.1)' ?>; color: <?= $t['status'] === 'Completed' ? 'var(--success)' : 'var(--warning)' ?>; padding: 2px 8px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">
                                                    <?= htmlspecialchars($t['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div style="padding:20px; text-align:center; color:var(--text-muted);">No tasks have been assigned to worshippers yet.</div>
                    <?php endif; ?>
                </div>

                <!-- Registered Worshippers List -->
                <div class="content-card" style="margin-top:20px;">
                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
                        <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#8b5cf6,#6d28d9);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="20" height="20" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div>
                            <h2 style="margin:0;">Registered Worshippers</h2>
                            <p style="margin:0;font-size:0.85rem;color:var(--text-muted);">All members who registered as Worshippers or have the Worshipper role.</p>
                        </div>
                    </div>
                    <?php
                    $all_worshippers = $conn->query("
                        SELECT id, first_name, last_name, phone, address, department, church_role, desired_role_pref, is_approved, profile_picture
                        FROM members
                        WHERE LOWER(TRIM(church_role)) LIKE '%worshipper%'
                           OR LOWER(TRIM(department)) LIKE '%worship%'
                           OR desired_role_pref = 'Worshipper'
                        ORDER BY first_name ASC
                    ");
                    ?>
                    <?php if ($all_worshippers && $all_worshippers->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Profile</th>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Department</th>
                                    <th>Role</th>
                                    <th>Preference</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($ws = $all_worshippers->fetch_assoc()): ?>
                                <tr>
                                    <td><img src="uploads/<?= htmlspecialchars($ws['profile_picture'] ?? 'default_avatar.png') ?>" alt="Profile" style="width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid var(--border-color);"></td>
                                    <td style="font-weight:500;"><?= htmlspecialchars($ws['first_name'] . ' ' . $ws['last_name']) ?></td>
                                    <td><?= htmlspecialchars($ws['phone']) ?></td>
                                    <td><span class="badge" style="background:var(--border-color);color:var(--text-main);"><?= htmlspecialchars($ws['department'] ?? 'General') ?></span></td>
                                    <td><span class="badge" style="background:rgba(139,92,246,0.12);color:#8b5cf6;"><?= htmlspecialchars($ws['church_role'] ?: 'Worshipper') ?></span></td>
                                    <td><?= htmlspecialchars($ws['desired_role_pref'] ?: '-') ?></td>
                                    <td>
                                        <?php if ($ws['is_approved'] == 1): ?>
                                            <span class="badge" style="color:#10b981;font-weight:bold;display:inline-flex;align-items:center;background:none;padding:0;border:none;"><img src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'><circle cx='6' cy='6' r='6' fill='%2310b981'/></svg>" style="width:12px;height:12px;margin-right:4px;vertical-align:middle;" alt="dot">Active</span>
                                        <?php elseif ($ws['is_approved'] == -1): ?>
                                            <span class="badge" style="color:#ef4444;font-weight:bold;display:inline-flex;align-items:center;background:none;padding:0;border:none;"><img src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'><circle cx='6' cy='6' r='6' fill='%23ef4444'/></svg>" style="width:12px;height:12px;margin-right:4px;vertical-align:middle;" alt="dot">Deactivated</span>
                                        <?php else: ?>
                                            <span class="badge" style="color:#f59e0b;font-weight:bold;display:inline-flex;align-items:center;background:none;padding:0;border:none;"><img src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'><circle cx='6' cy='6' r='6' fill='%23f59e0b'/></svg>" style="width:12px;height:12px;margin-right:4px;vertical-align:middle;" alt="dot">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div style="padding:20px;text-align:center;color:var(--text-muted);">No worshippers have been registered yet.</div>
                    <?php endif; ?>
                </div>


            <?php elseif ($tab == 'financials'): ?>
                <div class="page-header">
                    <h1>Financial Records Tracking</h1>
                    <p>Overview of all monies collected by department treasurers.</p>
                </div>

                
                <div class="content-card" style="margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <h2 style="margin: 0 0 5px;">Print / Export Records</h2>
                        <p style="margin: 0; color: var(--text-muted); font-size: 0.9rem;">Generate a PDF of all department finances up to a specific date.</p>
                    </div>
                    <form id="finPrintForm" action="print_financials.php" method="GET" target="_blank" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <label style="font-weight: 600; font-size: 0.9rem;">Department:</label>
                        <select name="dept" id="finPrintDept" class="form-control" style="width: auto; padding: 6px 12px; border: 1px solid #ccc; border-radius: 4px;" onchange="document.getElementById('finPrintForm').action = this.value ? 'print_dept_financials.php' : 'print_financials.php';">
                            <option value="">All Departments (Summary)</option>
                            <option value="General Church">General Church</option>
                            <option value="Youths">Youths</option>
                            <option value="Womens Ministry">Womens Ministry</option>
                            <option value="Elders">Elders</option>
                            <option value="Sunday School">Sunday School</option>
                            <option value="Building">Building</option>
                        </select>
                        <label style="font-weight: 600; font-size: 0.9rem; margin-left: 10px;">As At Date:</label>
                        <input type="date" name="date" class="form-control" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required style="width: auto; padding: 6px 12px; border: 1px solid #ccc; border-radius: 4px;">
                        <button type="submit" style="background: #1e3a8a; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: bold; display: flex; align-items: center; gap: 5px; margin-left: 10px;">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            Print PDF
                        </button>
                    </form>
                </div>

                <!-- Financial Summaries -->
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
                            'Sunday School' => 'Sunday School'
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
                <div class="content-card">
                                        <h2 style="margin-bottom: 5px;">Department Financial Records</h2>
                    <p style="margin-top:0; color:var(--text-muted); font-size:0.9rem; margin-bottom:20px;">Detailed finances per department, displaying amounts and who posted them.</p>
                    <?php
                    $target_depts = ['General Church', 'Youths', 'Womens Ministry', 'Elders', 'Sunday School', 'Building'];
                    $has_any_records = false;
                    foreach ($target_depts as $d):
                        $d_esc = $conn->real_escape_string($d);
                        $fin_records = $conn->query("SELECT fr.*, COALESCE(fr.recorded_by_name, CONCAT(m.first_name, ' ', m.last_name)) AS poster_name FROM financial_records fr LEFT JOIN members m ON fr.recorded_by = m.id WHERE fr.department = '$d_esc' ORDER BY fr.recorded_at DESC LIMIT 30");
                        if ($fin_records && $fin_records->num_rows > 0):
                            $has_any_records = true;
                    ?>
                    <h3 style="margin-top: 10px; color: #1e3a8a; border-bottom: 2px solid var(--border-color); padding-bottom: 8px; margin-bottom: 12px;"><?= $d ?> Finances</h3>
                    <div class="table-responsive" style="margin-bottom: 30px;"><table>
                        <thead><tr><th>Posted By</th><th>Amount (KSh)</th><th>Description</th><th>Date</th><th>Change Reason</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php while($fr = $fin_records->fetch_assoc()): ?>
                            <tr>
                                <td><span style="font-weight:600; color:var(--text-main);"><?= htmlspecialchars($fr['poster_name'] ?: 'Unknown') ?></span></td>
                                <td style="font-weight:700;color:#10b981;">KSh <?= number_format($fr['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($fr['description'] ?? '-') ?></td>
                                <td style="font-size:0.85em;color:var(--text-muted);"><?= date('M j, Y', strtotime($fr['record_date'] ?: $fr['recorded_at'])) ?></td>
                                <td style="font-size:0.85em;color:var(--text-muted);"><?= !empty($fr['edit_reason']) ? htmlspecialchars($fr['edit_reason']) : '<span style="color:var(--border-color);">-</span>' ?></td>
                                <td><?= !empty($fr['is_sent_to_chair']) ? '<span class="badge" style="background:rgba(16,185,129,0.12);color:#10b981;">Sent to Chair</span>' : '<span class="badge" style="background:rgba(100,100,100,0.1);color:var(--text-muted);">Not Sent</span>' ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table></div>
                    <?php 
                        endif;
                    endforeach; 
                    if (!$has_any_records):
                    ?>
                        <p style="color:var(--text-muted); padding:20px; text-align:center; background:var(--bg-card); border-radius:8px;">No financial records have been posted yet.</p>
                    <?php endif; ?>
                </div>
            <?php elseif ($tab == 'assign_roles'): ?>
                <?php
                // --- INITIALIZATION & HIERARCHY ---
                $required_roles = [
                    'General Church Secretary', 'Vice Church Secretary', 'Treasurer', 'Senior Church Elder',
                    'Head Usher', 'Usher', 'Building Chairperson', 'Vice Building Chairperson', 'Building Secretary', 'Vice Building Secretary', 'Building Treasurer',
                    'Worship Leader', 'Vice Worship Leader',
                    'Youth Chairperson', 'Vice Youth Chairperson', 'Youth Secretary', 'Vice Youth Secretary', 'Youth Treasurer', 'Mama Youth', 'Baba Youth',
                    'Women Chairlady', 'Vice Women Chairlady', 'Women Secretary', 'Vice Women Secretary', 'Women Treasurer',
                    'Elder Chairman', 'Vice Elder Chairman', 'Elder Secretary', 'Vice Elder Secretary', 'Elder Treasurer',
                    'Sunday School Patron', 'Vice Sunday School Patron', 'Sunday School Secretary', 'Vice Sunday School Secretary', 'Sunday School Treasurer'
                ];
                foreach ($required_roles as $required_role) {
                    $safe_required_role = $conn->real_escape_string($required_role);
                    $conn->query("INSERT IGNORE INTO church_roles (role_name) VALUES ('$safe_required_role')");
                }
                
                $church_roles = $conn->query("SELECT * FROM church_roles WHERE role_name NOT IN (SELECT church_role FROM members WHERE church_role IS NOT NULL AND church_role != '') ORDER BY role_name ASC");
                $all_roles_list = $conn->query("SELECT * FROM church_roles ORDER BY role_name ASC");

                // Define hierarchy structure
                $common_subsidiary_roles = ['Organizing Secretary', 'Discipline Master', 'Prayer Coordinator', 'Choir Leader'];
                $youth_sunday_subsidiary_roles = array_merge($common_subsidiary_roles, ['Graduands Secretary', 'Sports Secretary']);
                $hierarchy = [
                    'General Church Leaders' => [
                        'General Church Secretary', 'Vice Church Secretary', 'Treasurer', 'Senior Church Elder'
                    ],
                    'Elders Department' => array_merge([
                        'Elder Chairman', 'Vice Elder Chairman', 'Elder Secretary', 'Vice Elder Secretary', 'Elder Treasurer'
                    ], $common_subsidiary_roles),
                    'Women\'s Ministry' => array_merge([
                        'Women Chairlady', 'Vice Women Chairlady', 'Women Secretary', 'Vice Women Secretary', 'Women Treasurer'
                    ], $common_subsidiary_roles),
                    'Youths Department' => array_merge([
                        'Youth Chairperson', 'Vice Youth Chairperson', 'Youth Secretary', 'Vice Youth Secretary', 'Youth Treasurer', 'Mama Youth', 'Baba Youth'
                    ], $youth_sunday_subsidiary_roles),
                    'Sunday School' => array_merge([
                        'Sunday School Patron', 'Vice Sunday School Patron', 'Sunday School Secretary', 'Vice Sunday School Secretary', 'Sunday School Treasurer'
                    ], $youth_sunday_subsidiary_roles),
                    'Building Department' => [
                        'Building Chairperson', 'Vice Building Chairperson', 'Building Secretary', 'Vice Building Secretary', 'Building Treasurer'
                    ],
                    'Other Roles' => [
                        'Head Usher', 'Usher', 'Worship Leader', 'Vice Worship Leader'
                    ]
                ];
                $hierarchy_department_names = [
                    'Youths Department' => 'Youths',
                    'Women\'s Ministry' => 'Womens Ministry',
                    'Elders Department' => 'Elders',
                    'Sunday School' => 'Sunday School',
                ];

                $all_hierarchy_roles = [];
                foreach ($hierarchy as $roles) {
                    foreach ($roles as $role_name) {
                        $all_hierarchy_roles[normalize_role_name($role_name)] = $role_name;
                    }
                }
                foreach ($all_hierarchy_roles as $role_name) {
                    $safe_role_name = $conn->real_escape_string($role_name);
                    $conn->query("INSERT IGNORE INTO church_roles (role_name) VALUES ('$safe_role_name')");
                }

                $assigned_members_for_availability = [];
                $assigned_global_roles = [];
                $head_usher_already_assigned = false;
                $assigned_lookup_result = $conn->query("SELECT church_role, department FROM members WHERE church_role IS NOT NULL AND church_role != ''");
                if ($assigned_lookup_result) {
                    while ($assigned_lookup_row = $assigned_lookup_result->fetch_assoc()) {
                        $assigned_members_for_availability[] = $assigned_lookup_row;
                        // Split combined roles (e.g. "Head Usher, Vice Youth Secretary") and track each one
                        $individual_roles = array_filter(array_map('trim', explode(',', str_replace('&', ',', $assigned_lookup_row['church_role'] ?? ''))));
                        foreach ($individual_roles as $ind_role) {
                            $norm = normalize_role_name($ind_role);
                            $assigned_global_roles[$norm] = true;
                            if ($norm === 'head usher') {
                                $head_usher_already_assigned = true;
                            }
                        }
                    }
                }

                $role_is_available = function($role_name, $context_department = '') use ($assigned_members_for_availability, $assigned_global_roles, $head_usher_already_assigned) {
                    $normalized_role = normalize_role_name($role_name);

                    // Head Usher: strictly one — hide from dropdown if already assigned
                    if ($normalized_role === 'head usher') {
                        return !$head_usher_already_assigned;
                    }

                    // Usher: always available (unlimited appointments)
                    if ($normalized_role === 'usher') {
                        return true;
                    }

                    if (is_subsidiary_department_role($role_name) && !empty($context_department)) {
                        foreach ($assigned_members_for_availability as $assigned_row) {
                            // Check each individual role in potentially combined roles
                            $row_roles = array_filter(array_map('trim', explode(',', str_replace('&', ',', $assigned_row['church_role'] ?? ''))));
                            foreach ($row_roles as $row_role) {
                                if (
                                    normalize_role_name($row_role) === $normalized_role &&
                                    department_matches($assigned_row['department'] ?? '', $context_department)
                                ) {
                                    return false;
                                }
                            }
                        }
                        return true;
                    }

                    return empty($assigned_global_roles[$normalized_role]);
                };
                ?>
                <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                    <div>
                        <h1>Assign Roles</h1>
                        <p>Appoint approved members to church positions or create new roles.</p>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <a href="print_all_leaders.php?orientation=portrait" target="_blank" style="background:#1e3a8a;color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:0.82rem;font-weight:600;display:flex;align-items:center;gap:6px;text-decoration:none;">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            Print Leaders (Portrait)
                        </a>
                        <a href="print_all_leaders.php?orientation=landscape" target="_blank" style="background:#6366f1;color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:0.82rem;font-weight:600;display:flex;align-items:center;gap:6px;text-decoration:none;">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            Print Leaders (Landscape)
                        </a>
                    </div>
                </div>
                
                <!-- Pending Chairman Appointments -->
                <?php
                $pending_roles = $conn->query("SELECT id, first_name, last_name, department, pending_role FROM members WHERE pending_role IS NOT NULL AND pending_role != ''");
                if ($pending_roles && $pending_roles->num_rows > 0):
                ?>
                <div class="content-card" style="margin-bottom: 30px; border-left: 4px solid var(--primary);">
                    <h2 style="color: var(--primary);">Pending Subsidiary Appointments</h2>
                    <p style="color: var(--text-muted); margin-bottom: 15px;">These members have been nominated by their department chairmen for subsidiary roles.</p>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Department</th>
                                    <th>Proposed Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($pr = $pending_roles->fetch_assoc()): ?>
                                    <tr>
                                        <td style="font-weight: 500;"><?= htmlspecialchars($pr['first_name'] . ' ' . $pr['last_name']) ?></td>
                                        <td><span class="badge" style="background: rgba(16, 185, 129, 0.1); color: #10b981;"><?= htmlspecialchars($pr['department']) ?></span></td>
                                        <td style="font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($pr['pending_role']) ?></td>
                                        <td>
                                            <div style="display: flex; gap: 10px;">
                                                <a href="pastor_action.php?action=approve_subsidiary&id=<?= $pr['id'] ?>" class="btn-action btn-approve" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">Approve</a>
                                                <a href="pastor_action.php?action=reject_subsidiary&id=<?= $pr['id'] ?>" class="btn-action btn-reject" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;" onclick="return confirm('Reject this appointment?');">Reject</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <!-- Assign Role Form -->
                    <div class="content-card" style="flex: 1; min-width: 300px; margin-bottom: 20px;">
                        <h2>Assign to Member</h2>

                        <!-- Popup toast for taken role -->
                        <div id="headUsherToast" style="display:none; position:fixed; top:30px; left:50%; transform:translateX(-50%); z-index:9999; background:#1e293b; color:#fff; border-radius:14px; padding:18px 28px; box-shadow:0 8px 32px rgba(0,0,0,0.25); max-width:400px; width:90%; text-align:center; animation: fadeInDown 0.3s ease;">
                            <div style="font-size:2rem; margin-bottom:8px;">🔒</div>
                            <strong id="toastRoleTitle" style="font-size:1rem; display:block; margin-bottom:6px;">Role Already Assigned</strong>
                            <p id="toastRoleBody" style="font-size:0.88rem; color:#94a3b8; margin:0 0 14px;">This role is already taken. Remove the current holder first before reassigning.</p>
                            <button onclick="document.getElementById('headUsherToast').style.display='none'; document.getElementById('assignRoleSelect').value='';" style="background:var(--primary,#2563eb); color:#fff; border:none; border-radius:8px; padding:8px 22px; font-size:0.9rem; cursor:pointer; font-weight:600;">OK, Got It</button>
                        </div>
                        <style>@keyframes fadeInDown{from{opacity:0;transform:translateX(-50%) translateY(-18px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}</style>

                        <form method="POST" action="pastor_action.php?action=assign_role">
                            <div class="form-group">
                                <label>First Select Role</label>
                                <select name="role" id="assignRoleSelect" class="form-control" required>
                                    <option value="">-- Select Role --</option>
                                    <?php 
                                    foreach ($hierarchy as $dept => $roles) {
                                        $context_department = $hierarchy_department_names[$dept] ?? '';
                                        $options_html = '';
                                        foreach ($roles as $expected_role) {
                                            $is_taken = !$role_is_available($expected_role, $context_department);
                                            $val   = htmlspecialchars($expected_role);
                                            $allowed_json = htmlspecialchars(json_encode(role_assignment_departments($expected_role, $context_department)));
                                            $asgn_dept = htmlspecialchars($context_department);
                                            $label = htmlspecialchars($expected_role);
                                            if ($is_taken) {
                                                $options_html .= "<option value=\"$val\" data-allowed='$allowed_json' data-assignment-department=\"$asgn_dept\" data-taken=\"1\" style=\"color:#9ca3af;\">$label (Taken)</option>";
                                            } else {
                                                $options_html .= "<option value=\"$val\" data-allowed='$allowed_json' data-assignment-department=\"$asgn_dept\">$label</option>";
                                            }
                                        }
                                        if ($options_html !== '') {
                                            echo "<optgroup label=\"" . htmlspecialchars($dept) . "\">$options_html</optgroup>";
                                        }
                                    }
                                    ?>
                                </select>
                                <input type="hidden" name="assignment_department" id="assignRoleDepartment" value="">
                                <small id="assignRoleHint" style="display:block; margin-top:6px; color: var(--text-muted);">Choose a role first to open the correct members.</small>
                            </div>
                            <div class="form-group">
                                <label>Then Select Member</label>
                                <select name="member_id" id="assignMemberSelect" class="form-control" required disabled>
                                    <option value="">-- Select a role first --</option>
                                    <?php 
                                    $approved_members->data_seek(0);
                                    while($m = $approved_members->fetch_assoc()): ?>
                                        <option value="<?= $m['id'] ?>" data-department="<?= htmlspecialchars($m['department'] ?? '') ?>">
                                            <?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?> - <?= htmlspecialchars($m['department'] ?? 'No Department') ?> (Current: <?= htmlspecialchars(role_display_label($m['church_role'] ?? 'Member', $m['department'] ?? null)) ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn-submit">Assign Role</button>
                        </form>
                        <script>
                            (function() {
                                const roleSelect = document.getElementById('assignRoleSelect');
                                const memberSelect = document.getElementById('assignMemberSelect');
                                const departmentInput = document.getElementById('assignRoleDepartment');
                                const hint = document.getElementById('assignRoleHint');
                                if (!roleSelect || !memberSelect) return;
                                const originalOptions = Array.from(memberSelect.querySelectorAll('option[data-department]')).map(o => o.cloneNode(true));
                                
                                roleSelect.addEventListener('change', function() {
                                    const sel = this.options[this.selectedIndex];
                                    if (!sel || !sel.value) {
                                        memberSelect.disabled = true;
                                        memberSelect.innerHTML = '<option value="">-- Select a role first --</option>';
                                        hint.innerText = "Choose a role first to open the correct members.";
                                        hint.style.color = "var(--text-muted)";
                                        departmentInput.value = "";
                                        return;
                                    }
                                    
                                    if (sel && sel.dataset.taken === '1') {
                                        this.value = '';
                                        const tBody = document.getElementById('toastRoleBody');
                                        const rName = sel.text.replace(' (Taken)', '');
                                        tBody.innerText = `The role of ${rName} is already taken. Remove the current holder first before reassigning.`;
                                        document.getElementById('headUsherToast').style.display = 'block';
                                        
                                        memberSelect.disabled = true;
                                        memberSelect.innerHTML = '<option value="">-- Select a role first --</option>';
                                        departmentInput.value = "";
                                        return;
                                    }

                                    memberSelect.disabled = false;
                                    let allowedDepts = [];
                                    try {
                                        if (sel.dataset.allowed) {
                                            allowedDepts = JSON.parse(sel.dataset.allowed) || [];
                                        }
                                    } catch(e) {}
                                    
                                    departmentInput.value = sel.dataset.assignmentDepartment || '';

                                    memberSelect.innerHTML = '<option value="">-- Select Member --</option>';
                                    let count = 0;
                                    originalOptions.forEach(opt => {
                                        if (!opt.value) return; 
                                        const optDept = opt.dataset.department || '';
                                        let isAllowed = false;
                                        if (allowedDepts.length === 0) {
                                            isAllowed = true;
                                        } else {
                                            let allowedLower = allowedDepts.map(d => d.toLowerCase());
                                            let optLower = optDept.toLowerCase();
                                            if (allowedLower.includes(optLower)) isAllowed = true;
                                            if (allowedLower.includes('youths') && optLower === 'youth ministry') isAllowed = true;
                                            if (allowedLower.includes('womens ministry') && optLower === 'women ministry') isAllowed = true;
                                            if (allowedLower.includes('elders') && optLower === 'elders') isAllowed = true;
                                        }

                                        if (isAllowed) {
                                            memberSelect.appendChild(opt.cloneNode(true));
                                            count++;
                                        }
                                    });

                                    if (count === 0) {
                                        hint.innerText = `No eligible members found. Role requires: ${allowedDepts.length ? allowedDepts.join(', ') : 'Any'}`;
                                        hint.style.color = "var(--danger)";
                                    } else {
                                        hint.innerText = `Found ${count} eligible member(s) for this role.`;
                                        hint.style.color = "#10b981";
                                    }
                                });
                            })();
                        </script>
                    </div>


                </div>

                <!-- NEW: Leaders Needed -->
                <div class="content-card" style="margin-bottom: 30px;">
                    <h2 style="margin-bottom: 20px;">Required Leaders Table</h2>
                    <div class="table-responsive">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="width: 25%; background: var(--bg-main); text-align: left; padding: 12px; border-bottom: 2px solid var(--border-color);">Department</th>
                                    <th style="background: var(--bg-main); text-align: left; padding: 12px; border-bottom: 2px solid var(--border-color);">Leader Role</th>
                                    <th style="background: var(--bg-main); text-align: left; padding: 12px; border-bottom: 2px solid var(--border-color);">Allowed Members</th>
                                    <th style="background: var(--bg-main); text-align: left; padding: 12px; border-bottom: 2px solid var(--border-color);">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $assigned_role_rows = [];
                                $assigned_lookup = $conn->query("SELECT first_name, last_name, department, church_role FROM members WHERE church_role IS NOT NULL AND church_role != ''");
                                if ($assigned_lookup) {
                                    while ($assigned = $assigned_lookup->fetch_assoc()) {
                                        $assigned_role_rows[] = $assigned;
                                    }
                                }
                                ?>
                                <?php foreach ($hierarchy as $dept => $roles): ?>
                                    <?php 
                                    $first_role = true; 
                                    $rowspan = count($roles);
                                    foreach ($roles as $expected_role): 
                                    ?>
                                        <?php
                                        $context_department = $hierarchy_department_names[$dept] ?? '';
                                        $assigned_names = [];
                                        foreach ($assigned_role_rows as $assigned_role_row) {
                                            $member_roles_arr = array_filter(array_map('trim', explode(',', str_replace('&', ',', $assigned_role_row['church_role'] ?? ''))));
                                            $has_expected_role = false;
                                            foreach ($member_roles_arr as $m_role) {
                                                if (normalize_role_name($m_role) === normalize_role_name($expected_role)) {
                                                    $has_expected_role = true;
                                                    break;
                                                }
                                            }
                                            if (!$has_expected_role) {
                                                continue;
                                            }
                                            if (
                                                is_subsidiary_department_role($expected_role) &&
                                                !empty($context_department) &&
                                                !department_matches($assigned_role_row['department'] ?? '', $context_department)
                                            ) {
                                                continue;
                                            }
                                            $assigned_names[] = trim($assigned_role_row['first_name'] . ' ' . $assigned_role_row['last_name']);
                                        }
                                        $allowed_departments = role_assignment_departments($expected_role);
                                        $allowed_label = empty($allowed_departments) ? 'Entire Church' : implode(', ', $allowed_departments);
                                        if (is_subsidiary_department_role($expected_role) && !empty($context_department)) {
                                            $allowed_label = $context_department;
                                        }
                                        ?>
                                        <tr style="border-bottom: 1px solid var(--border-color);">
                                            <?php if ($first_role): ?>
                                                <td rowspan="<?= $rowspan ?>" style="font-weight: bold; background: var(--bg-main); border-right: 1px solid var(--border-color); vertical-align: top; padding: 15px; color: var(--primary);">
                                                    <div style="font-size: 1.05rem; margin-bottom: 6px;"><?= htmlspecialchars($dept) ?></div>
                                                    <span class="badge" style="background: rgba(37, 99, 235, 0.1); color: var(--primary); font-weight: 500;"><?= $rowspan ?> roles</span>
                                                </td>
                                                <?php $first_role = false; ?>
                                            <?php endif; ?>
                                            <td style="font-weight: 600; color: var(--text-main); padding: 12px;"><?= htmlspecialchars(role_display_label($expected_role)) ?></td>
                                            <td style="color: var(--text-muted); padding: 12px;"><?= htmlspecialchars($allowed_label) ?></td>
                                            <td style="padding: 12px;">
                                                <?php if (!empty($assigned_names)): ?>
                                                    <span class="badge" style="background: rgba(16,185,129,0.12); color: #10b981;">Assigned: <?= htmlspecialchars(implode(', ', $assigned_names)) ?></span>
                                                <?php else: ?>
                                                    <span class="badge" style="background: rgba(245,158,11,0.14); color: var(--warning);">Open</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="content-card">
                    <h2 style="margin-bottom: 20px;">Currently Assigned Roles</h2>
                    <?php 
                    $assigned_roles_q = $conn->query("SELECT id, first_name, last_name, department, church_role, profile_picture FROM members WHERE church_role IS NOT NULL AND church_role != '' AND LOWER(TRIM(church_role)) != 'member'");
                    
                    // Organize fetched members into the hierarchy structure
                    $categorized_members = [];
                    $uncategorized = [];
                    
                    while($ar = $assigned_roles_q->fetch_assoc()) {
                        $member_roles = array_filter(array_map('trim', explode(',', str_replace('&', ',', $ar['church_role'] ?? ''))));
                        foreach ($member_roles as $role_clean) {
                            $placed = false;
                            
                            $explicit_dept = '';
                            if (preg_match_all('/\((.*?)\)/', $role_clean, $matches)) {
                                foreach ($matches[1] as $match) {
                                    if (strtolower(trim($match)) !== 'subsidiary') {
                                        $explicit_dept = trim($match);
                                    }
                                }
                            }
                            $base_role_name = normalize_role_name($role_clean);

                            foreach($hierarchy as $dept => $roles) {
                                $expected_department = $hierarchy_department_names[$dept] ?? '';
                                $effective_department = $explicit_dept ?: trim($ar['department'] ?? '');
                                $is_youth_advisor_for_youth = $dept === 'Youth Ministry' && is_youth_advisor_role($base_role_name);
                                
                                if ($expected_department && !$is_youth_advisor_for_youth) {
                                    $member_aliases = array_map('strtolower', department_aliases($effective_department));
                                    $expected_aliases = array_map('strtolower', department_aliases($expected_department));
                                    if (empty(array_intersect($member_aliases, $expected_aliases))) {
                                        continue;
                                    }
                                }
                                foreach($roles as $expected_role) {
                                    if (strtolower($base_role_name) === strtolower($expected_role)) {
                                        $temp_ar = $ar;
                                        $temp_ar['displayed_role'] = $role_clean;
                                        $categorized_members[$dept][$expected_role][] = $temp_ar;
                                        $placed = true;
                                        break 2;
                                    }
                                }
                            }
                            if (!$placed) {
                                $temp_ar = $ar;
                                $temp_ar['displayed_role'] = $role_clean;
                                $uncategorized[] = $temp_ar;
                            }
                        }
                    }

                    $has_any_roles = false;
                    foreach ($hierarchy as $dept => $roles) {
                        if (!empty($categorized_members[$dept])) {
                            $has_any_roles = true;
                            break;
                        }
                    }
                    if (!empty($uncategorized)) $has_any_roles = true;

                    if ($has_any_roles) {
                        echo "<div class='table-responsive'>";
                        echo "<table style='width: 100%; border-collapse: collapse;'>";
                        echo "<thead><tr>";
                        echo "<th style='width: 25%; background: var(--bg-main); text-align: left; padding: 12px; border-bottom: 2px solid var(--border-color);'>Department</th>";
                        echo "<th style='background: var(--bg-main); text-align: left; padding: 12px; border-bottom: 2px solid var(--border-color);'>Assigned Role</th>";
                        echo "<th style='background: var(--bg-main); text-align: left; padding: 12px; border-bottom: 2px solid var(--border-color);'>Member</th>";
                        echo "<th style='background: var(--bg-main); text-align: left; padding: 12px; border-bottom: 2px solid var(--border-color);'>Action</th>";
                        echo "</tr></thead><tbody>";

                        foreach ($hierarchy as $dept => $roles) {
                            if (!empty($categorized_members[$dept])) {
                                // Calculate total rows for this department
                                $dept_row_count = 0;
                                foreach ($roles as $expected_role) {
                                    if (!empty($categorized_members[$dept][$expected_role])) {
                                        $dept_row_count += count($categorized_members[$dept][$expected_role]);
                                    }
                                }

                                $first_row = true;
                                foreach ($roles as $expected_role) {
                                    if (!empty($categorized_members[$dept][$expected_role])) {
                                        foreach ($categorized_members[$dept][$expected_role] as $member_data) {
                                            $role_label = role_display_label($expected_role, $member_data['department'] ?? null);
                                            echo "<tr style='border-bottom: 1px solid var(--border-color);'>";
                                            if ($first_row) {
                                                echo "<td rowspan='$dept_row_count' style='font-weight: bold; background: var(--bg-main); border-right: 1px solid var(--border-color); vertical-align: top; padding: 15px; color: var(--primary);'>";
                                                echo "<div style='font-size: 1.05rem; margin-bottom: 6px;'>" . htmlspecialchars($dept) . "</div>";
                                                echo "<span class='badge' style='background: rgba(37, 99, 235, 0.1); color: var(--primary); font-weight: 500;'>$dept_row_count assigned</span>";
                                                echo "</td>";
                                                $first_row = false;
                                            }
                                            echo "<td style='padding: 12px;'><span class='badge' style='background: var(--primary); color: white; font-weight: bold;'>" . htmlspecialchars($role_label) . "</span></td>";
                                            echo "<td style='font-weight: 500; padding: 12px;'>" . htmlspecialchars($member_data['first_name'] . ' ' . $member_data['last_name']) . "</td>";
                                            echo "<td style='padding: 12px;'><a href='pastor_action.php?action=remove_role&id=" . $member_data['id'] . "&role=" . urlencode($expected_role) . "' onclick=\"return confirm('Remove this role from " . htmlspecialchars($member_data['first_name']) . "?');\" class='btn-sm' style='background: var(--danger); color: white; text-decoration:none; border:none; cursor:pointer;'>Remove Role</a></td>";
                                            echo "</tr>";
                                        }
                                    }
                                }
                            }
                        }

                        // Render roles that are not part of the required leadership hierarchy
                        if (!empty($uncategorized)) {
                            $uncat_count = count($uncategorized);
                            $first_row = true;
                            foreach ($uncategorized as $member_data) {
                                $disp_role = $member_data['displayed_role'] ?? $member_data['church_role'];
                                $role_label = role_display_label($disp_role, $member_data['department'] ?? null);
                                echo "<tr style='border-bottom: 1px solid var(--border-color);'>";
                                if ($first_row) {
                                    echo "<td rowspan='$uncat_count' style='font-weight: bold; background: var(--bg-main); border-right: 1px solid var(--border-color); vertical-align: top; padding: 15px; color: var(--text-muted);'>";
                                    echo "<div style='font-size: 1.05rem; margin-bottom: 6px;'>Other Roles</div>";
                                    echo "<span class='badge' style='background: var(--border-color); color: var(--text-muted); font-weight: 500;'>$uncat_count assigned</span>";
                                    echo "</td>";
                                    $first_row = false;
                                }
                                echo "<td style='padding: 12px;'><span class='badge' style='background: var(--text-muted); color: white; font-weight: bold;'>" . htmlspecialchars($role_label) . "</span></td>";
                                echo "<td style='font-weight: 500; padding: 12px;'>" . htmlspecialchars($member_data['first_name'] . ' ' . $member_data['last_name']) . "</td>";
                                echo "<td style='padding: 12px;'><a href='pastor_action.php?action=remove_role&id=" . $member_data['id'] . "&role=" . urlencode($disp_role) . "' onclick=\"return confirm('Remove this role from " . htmlspecialchars($member_data['first_name']) . "?');\" class='btn-sm' style='background: var(--danger); color: white; text-decoration:none; border:none; cursor:pointer;'>Remove Role</a></td>";
                                echo "</tr>";
                            }
                        }

                        echo "</tbody></table></div>";
                    } else {
                        echo "<p style='color: var(--text-muted); text-align: center; padding: 30px 0;'>No roles have been assigned yet.</p>";
                    }
                    ?>
                </div>
            <?php elseif ($tab == 'general_leadership'): ?>
                <?php
                // Department groups and their leader chat dept_keys
                $dept_groups = [
                    'youths'          => ['label' => '🎓 Youth Department',       'dept_key' => 'youths',          'color' => '#3b82f6'],
                    'womens_ministry' => ['label' => '👩 Women\'s Ministry',       'dept_key' => 'womens_ministry', 'color' => '#ec4899'],
                    'elders'          => ['label' => '🕊️ Elders',                 'dept_key' => 'elders',          'color' => '#8b5cf6'],
                    'sunday_school'   => ['label' => '📚 Sunday School',           'dept_key' => 'sunday_school',   'color' => '#f59e0b'],
                    'general_church'  => ['label' => '⛪ General Church Leaders',  'dept_key' => 'general_church',  'color' => '#10b981'],
                    'building_construction' => ['label' => '🏗️ Building Dept',    'dept_key' => 'building_construction', 'color' => '#ef4444'],
                    'worship_leaders' => ['label' => '🎶 Worship Dept',           'dept_key' => 'worship_leaders', 'color' => '#d946ef'],
                ];
                $active_group = $_GET['leader_group'] ?? 'general_church';
                if (!array_key_exists($active_group, $dept_groups)) $active_group = 'general_church';
                $active_dept_key = $dept_groups[$active_group]['dept_key'];
                $active_color    = $dept_groups[$active_group]['color'];
                $active_label    = $dept_groups[$active_group]['label'];
                ?>
                <div class="page-header">
                    <h1>General Leadership Chats</h1>
                    <p>Monitor and participate in leadership conversations across all departments.</p>
                </div>

                <!-- Department Group Tabs -->
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:24px;">
                    <?php foreach ($dept_groups as $key => $grp): ?>
                        <a href="?tab=general_leadership&leader_group=<?= $key ?>"
                           style="padding:8px 16px;border-radius:30px;font-size:0.85rem;font-weight:600;text-decoration:none;
                                  background:<?= $active_group === $key ? $grp['color'] : 'var(--bg-card)' ?>;
                                  color:<?= $active_group === $key ? '#fff' : 'var(--text-muted)' ?>;
                                  border:1.5px solid <?= $active_group === $key ? $grp['color'] : 'var(--border-color)' ?>;
                                  transition:all 0.2s;">
                            <?= $grp['label'] ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Chat view for active group -->
                <div class="content-card" style="margin-bottom:24px;border-left:4px solid <?= $active_color ?>;">
                    <h2 style="font-size:1.05rem;margin-top:0;color:<?= $active_color ?>;"><?= $active_label ?> — Leadership Chat</h2>
                    <?php
                    $esc_dept_key = $conn->real_escape_string($active_dept_key);
                    if ($esc_dept_key === 'building_construction') {
                        $chat_msgs = $conn->query("
                            SELECT blc.id, 'building_construction' as dept_key, blc.sender_id, blc.message, blc.created_at, m.first_name, m.last_name, m.church_role, 'Building & Construction' as department, NULL as is_pastor 
                            FROM building_leadership_chat blc 
                            JOIN members m ON blc.sender_id = m.id 
                            UNION ALL 
                            SELECT plm.id, plm.dept_key, plm.pastor_id as sender_id, plm.message, plm.created_at, p.first_name, p.last_name, p.role as church_role, 'Pastoral Office' as department, 1 as is_pastor 
                            FROM pastor_leader_messages plm 
                            JOIN pastors p ON plm.pastor_id = p.id 
                            WHERE plm.dept_key = 'building_construction' 
                            ORDER BY created_at ASC
                        ");
                    } elseif ($esc_dept_key === 'worship_leaders') {
                        $chat_msgs = $conn->query("
                            SELECT wlc.id, 'worship_leaders' as dept_key, wlc.sender_id, wlc.message, wlc.created_at, m.first_name, m.last_name, m.church_role, 'Worship Dept' as department, NULL as is_pastor 
                            FROM worship_leadership_chat wlc 
                            JOIN members m ON wlc.sender_id = m.id 
                            UNION ALL 
                            SELECT plm.id, plm.dept_key, plm.pastor_id as sender_id, plm.message, plm.created_at, p.first_name, p.last_name, p.role as church_role, 'Pastoral Office' as department, 1 as is_pastor 
                            FROM pastor_leader_messages plm 
                            JOIN pastors p ON plm.pastor_id = p.id 
                            WHERE plm.dept_key = 'worship_leaders' 
                            ORDER BY created_at ASC
                        ");
                    } else {
                        $chat_msgs = $conn->query("
                            SELECT lm.*, m.first_name, m.last_name, m.church_role, m.department,
                                   NULL as is_pastor
                            FROM leader_messages lm
                            JOIN members m ON lm.sender_id = m.id
                            WHERE lm.dept_key = '$esc_dept_key'
                            UNION ALL
                            SELECT plm.id, plm.dept_key, plm.pastor_id as sender_id, plm.message, plm.created_at,
                                   p.first_name, p.last_name, p.role as church_role, 'Pastoral Office' as department,
                                   1 as is_pastor
                            FROM pastor_leader_messages plm
                            JOIN pastors p ON plm.pastor_id = p.id
                            WHERE plm.dept_key = '$esc_dept_key'
                            ORDER BY created_at ASC
                        ");
                        if (!$chat_msgs) {
                            // table may not exist yet — only show member messages
                            $chat_msgs = $conn->query("
                                SELECT lm.*, m.first_name, m.last_name, m.church_role, m.department, 0 as is_pastor
                                FROM leader_messages lm
                                JOIN members m ON lm.sender_id = m.id
                                WHERE lm.dept_key = '$esc_dept_key'
                                ORDER BY lm.created_at ASC
                            ");
                        }
                    }
                    if ($chat_msgs && $chat_msgs->num_rows > 0):
                    ?>
                    <div style="max-height:420px;overflow-y:auto;padding-right:6px;margin-bottom:16px;" id="leaderChatScroll">
                        <?php while ($msg = $chat_msgs->fetch_assoc()):
                            $is_pastor_msg = !empty($msg['is_pastor']);
                            $bg = $is_pastor_msg ? 'rgba(16,185,129,0.08)' : 'var(--bg-main)';
                            $border = $is_pastor_msg ? '#10b981' : $active_color;
                        ?>
                        <div style="margin-bottom:12px;padding:12px 14px;border-radius:10px;background:<?= $bg ?>;border-left:3px solid <?= $border ?>;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
                                <span style="font-weight:600;font-size:0.9rem;color:var(--text-main);">
                                    <?= $is_pastor_msg ? '⛪ ' : '' ?><?= htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']) ?>
                                    <span style="font-size:0.75rem;color:var(--text-muted);font-weight:400;"> — <?= htmlspecialchars($msg['church_role']) ?></span>
                                </span>
                                <small style="color:var(--text-muted);font-size:0.72rem;"><?= date('M j, g:i A', strtotime($msg['created_at'])) ?></small>
                            </div>
                            <p style="margin:0;font-size:0.9rem;white-space:pre-wrap;line-height:1.5;"><?= htmlspecialchars($msg['message']) ?></p>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                        <p style="color:var(--text-muted);font-style:italic;margin-bottom:16px;">No messages in this chat group yet.</p>
                    <?php endif; ?>

                    <!-- Pastor reply form -->
                    <form method="POST" action="pastor_dashboard.php?tab=general_leadership&leader_group=<?= htmlspecialchars($active_group) ?>" style="margin-top:8px;">
                        <input type="hidden" name="pastor_leader_chat" value="1">
                        <input type="hidden" name="dept_key" value="<?= htmlspecialchars($active_dept_key) ?>">
                        <div style="display:flex;flex-direction:column;gap:10px;">
                            <textarea name="pastor_leader_message" class="form-control" rows="6" placeholder="Type your message to <?= htmlspecialchars($active_label) ?> leaders..." required style="width:100%; resize:vertical; padding: 16px; border-radius: 12px; border: 2px solid var(--border-color); font-size: 1.05rem; transition: border-color 0.2s, box-shadow 0.2s;" onfocus="this.style.borderColor='<?= $active_color ?>'; this.style.boxShadow='0 0 0 3px <?= $active_color ?>33';" onblur="this.style.borderColor='var(--border-color)'; this.style.boxShadow='none';"></textarea>
                            <button type="submit" class="btn-submit" style="background:<?= $active_color ?>;align-self:flex-end;padding:10px 24px;">Send Message</button>
                        </div>
                    </form>
                </div>


            <?php elseif ($tab == 'choir_songs'): ?>
                <div class="page-header">
                    <h1>🎵 Choir Songs Monitor</h1>
                    <p>Song plans and choir announcements posted across all departments.</p>
                </div>

                <div class="content-card" style="margin-bottom:25px;">
                    <h2>Upcoming Songs (All Departments)</h2>
                    <?php
                    $all_song_plans = $conn->query("
                        SELECT csp.*, m.first_name, m.last_name
                        FROM choir_song_plans csp
                        JOIN members m ON csp.choir_leader_id = m.id
                        ORDER BY csp.department ASC, csp.song_date ASC, csp.song_time ASC
                    ");
                    ?>
                    <?php if ($all_song_plans && $all_song_plans->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table>
                                <thead><tr><th>Department</th><th>Song Type</th><th>Song Title</th><th>Date</th><th>Choir Leader</th><th>Notes</th></tr></thead>
                                <tbody>
                                    <?php while($song = $all_song_plans->fetch_assoc()): ?>
                                        <tr>
                                            <td><span class="badge" style="background:rgba(37,99,235,0.12);color:var(--primary);"><?= htmlspecialchars($song['department']) ?></span></td>
                                            <td><?= htmlspecialchars($song['song_type']) ?></td>
                                            <td style="font-weight:600;color:var(--text-main);"><?= htmlspecialchars($song['song_title']) ?></td>
                                            <td><?= date('M j, Y', strtotime($song['song_date'])) ?><?= $song['song_time'] ? ' ' . date('g:i A', strtotime($song['song_time'])) : '' ?></td>
                                            <td><?= htmlspecialchars($song['first_name'] . ' ' . $song['last_name']) ?></td>
                                            <td><?= htmlspecialchars($song['notes'] ?? '') ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--text-muted);">No choir song plans have been posted yet.</p>
                    <?php endif; ?>
                </div>

                <div class="content-card">
                    <h2>Choir Announcements (All Departments)</h2>
                    <?php
                    $all_choir_announcements = $conn->query("
                        SELECT ca.*, m.first_name, m.last_name
                        FROM choir_announcements ca
                        JOIN members m ON ca.choir_leader_id = m.id
                        ORDER BY ca.department ASC, ca.created_at DESC
                    ");
                    ?>
                    <?php if ($all_choir_announcements && $all_choir_announcements->num_rows > 0): ?>
                        <div style="display:flex;flex-direction:column;gap:12px;">
                            <?php while($ca = $all_choir_announcements->fetch_assoc()): ?>
                                <div style="padding:18px;border-left:4px solid var(--primary);background:var(--bg-main);border-radius:0 10px 10px 0;">
                                    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
                                        <div>
                                            <strong style="color:var(--text-main);"><?= htmlspecialchars($ca['first_name'] . ' ' . $ca['last_name']) ?></strong>
                                            <span class="badge" style="margin-left:8px;background:rgba(37,99,235,0.12);color:var(--primary);"><?= htmlspecialchars($ca['department']) ?></span>
                                        </div>
                                        <small style="color:var(--text-muted);"><?= date('M j, Y g:i A', strtotime($ca['created_at'])) ?></small>
                                    </div>
                                    <p style="margin:0;white-space:pre-wrap;line-height:1.6;"><?= htmlspecialchars($ca['message']) ?></p>
                                    <?= function_exists('render_announcement_image') ? render_announcement_image($ca['image_path'] ?? '') : '' ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--text-muted);">No choir announcements posted yet.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($tab == 'sport_board'): ?>
                <div class="page-header">
                    <h1>⚽ Sport Board</h1>
                    <p>All scheduled matches and sport announcements across every department.</p>
                </div>

                <div class="content-card" style="margin-bottom:25px;">
                    <h2>🏆 Scheduled Matches (All Departments)</h2>
                    <?php
                    $all_matches = $conn->query("SELECT sm.*, m.first_name, m.last_name FROM sport_matches sm JOIN members m ON sm.secretary_id = m.id ORDER BY sm.match_date ASC, sm.match_time ASC");
                    if ($all_matches && $all_matches->num_rows > 0):
                    ?>
                        <div style="display:flex;flex-direction:column;gap:15px;margin-top:15px;">
                            <?php while($sm = $all_matches->fetch_assoc()): ?>
                                <div style="display:flex;gap:20px;align-items:flex-start;padding:18px;border:1px solid var(--border-color);border-radius:12px;background:var(--bg-main);">
                                    <div style="text-align:center;background:var(--primary);color:white;border-radius:10px;padding:10px 15px;min-width:60px;">
                                        <div style="font-size:1.5rem;font-weight:700;"><?= date('j', strtotime($sm['match_date'])) ?></div>
                                        <div style="font-size:0.75rem;text-transform:uppercase;"><?= date('M', strtotime($sm['match_date'])) ?></div>
                                    </div>
                                    <div style="flex:1;">
                                        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:5px;">
                                            <div style="display:flex;align-items:center;gap:10px;">
                                                <h3 style="margin:0;color:var(--text-main);"><?= htmlspecialchars($sm['title']) ?></h3>
                                                <span style="background:rgba(79,70,229,0.1);color:var(--primary);font-size:0.75rem;padding:2px 8px;border-radius:12px;"><?= htmlspecialchars($sm['department']) ?></span>
                                            </div>
                                        </div>
                                        <?php if($sm['description']): ?><p style="color:var(--text-muted);margin:0 0 8px;font-size:0.9rem;"><?= nl2br(htmlspecialchars($sm['description'])) ?></p><?php endif; ?>
                                        <div style="display:flex;gap:15px;flex-wrap:wrap;">
                                            <span style="color:var(--text-muted);font-size:0.85rem;">⏰ <?= date('g:i A', strtotime($sm['match_time'])) ?><?= $sm['end_time'] ? ' – '.date('g:i A', strtotime($sm['end_time'])) : '' ?></span>
                                            <span style="color:var(--text-muted);font-size:0.85rem;">📍 <?= htmlspecialchars($sm['location']) ?></span>
                                            <span style="color:var(--text-muted);font-size:0.85rem;">👤 <?= htmlspecialchars($sm['first_name'] . ' ' . $sm['last_name']) ?></span>
                                        </div>
                                        <?php if (!empty($sm['score_result'])): ?>
                                            <div style="margin-top:15px;padding-top:15px;border-top:1px solid var(--border-color);">
                                                <strong style="color:var(--text-main);display:block;margin-bottom:5px;">Final Result:</strong>
                                                <span style="background:var(--primary);color:white;padding:4px 10px;border-radius:20px;font-weight:bold;"><?= htmlspecialchars($sm['score_result']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--text-muted);margin-top:15px;">No matches scheduled yet across any department.</p>
                    <?php endif; ?>
                </div>

                <div class="content-card">
                    <h2>📢 Sport Announcements (All Departments)</h2>
                    <?php
                    $all_sport_anncs = $conn->query("SELECT sa.*, m.first_name, m.last_name FROM sport_announcements sa JOIN members m ON sa.secretary_id = m.id ORDER BY sa.created_at DESC");
                    if ($all_sport_anncs && $all_sport_anncs->num_rows > 0):
                    ?>
                        <div style="display:flex;flex-direction:column;gap:12px;margin-top:15px;">
                            <?php while($sa = $all_sport_anncs->fetch_assoc()): ?>
                                <div style="padding:18px;border-left:4px solid var(--primary);background:var(--bg-main);border-radius:0 10px 10px 0;">
                                    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:8px;">
                                        <div style="display:flex;gap:10px;align-items:center;">
                                            <strong style="color:var(--text-main);">📢 <?= htmlspecialchars($sa['first_name'] . ' ' . $sa['last_name']) ?></strong>
                                            <span style="background:rgba(79,70,229,0.1);color:var(--primary);font-size:0.75rem;padding:2px 8px;border-radius:12px;"><?= htmlspecialchars($sa['department']) ?></span>
                                        </div>
                                        <small style="color:var(--text-muted);"><?= date('M j, Y g:i A', strtotime($sa['created_at'])) ?></small>
                                    </div>
                                    <p style="margin:0;color:var(--text-muted);line-height:1.6;"><?= nl2br(htmlspecialchars($sa['message'])) ?></p>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--text-muted);margin-top:15px;">No sport announcements posted yet.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($tab == 'prayer_board'): ?>
                <div class="page-header">
                    <h1>🙏 Global Prayer Board</h1>
                    <p>All prayer sessions and active prayer items across every department in the church.</p>
                </div>

                <!-- Upcoming Sessions (all depts) -->
                <div class="content-card" style="margin-bottom:25px;">
                    <h2>📅 Upcoming Prayer Sessions (All Departments)</h2>
                    <?php
                    $all_schedules = $conn->query("SELECT ps.*, m.first_name, m.last_name FROM prayer_schedules ps JOIN members m ON ps.coordinator_id = m.id WHERE ps.prayer_date >= CURDATE() ORDER BY ps.prayer_date ASC, ps.prayer_time ASC");
                    if ($all_schedules && $all_schedules->num_rows > 0):
                    ?>
                        <div style="display:flex; flex-direction:column; gap:15px;">
                            <?php while($s = $all_schedules->fetch_assoc()): ?>
                                <div style="display:flex; gap:20px; align-items:flex-start; padding:18px; border:1px solid var(--border-color); border-radius:12px; background:var(--bg-main);">
                                    <div style="text-align:center; background:var(--primary); color:white; border-radius:10px; padding:10px 15px; min-width:60px;">
                                        <div style="font-size:1.5rem; font-weight:700;"><?= date('j', strtotime($s['prayer_date'])) ?></div>
                                        <div style="font-size:0.75rem; text-transform:uppercase;"><?= date('M', strtotime($s['prayer_date'])) ?></div>
                                    </div>
                                    <div style="flex:1;">
                                        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px; margin-bottom:5px;">
                                            <div style="display:flex; align-items:center; gap:10px;">
                                                <h3 style="margin:0;color:var(--text-main);"><?= htmlspecialchars($s['title']) ?></h3>
                                                <span class="badge" style="background:rgba(79,70,229,0.1);color:var(--primary);"><?= htmlspecialchars($s['department']) ?></span>
                                            </div>
                                            <?php
                                            $target_datetime = $s['prayer_date'] . ' ' . $s['prayer_time'];
                                            $end_datetime = !empty($s['end_time']) ? $s['prayer_date'] . ' ' . $s['end_time'] : '';
                                            ?>
                                            <span class="badge prayer-countdown" data-target="<?= $target_datetime ?>" <?= $end_datetime ? 'data-end-target="'.$end_datetime.'"' : '' ?> style="background:rgba(245,158,11,0.1);color:var(--warning);font-weight:600;font-family:monospace;font-size:14px;">
                                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:4px;margin-top:-2px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                Calculating...
                                            </span>
                                        </div>
                                        <?php if($s['description']): ?><p style="color:var(--text-muted);margin:0 0 8px;font-size:0.9rem;"><?= nl2br(htmlspecialchars($s['description'])) ?></p><?php endif; ?>
                                        <div style="display:flex;gap:15px;flex-wrap:wrap;">
                                            <span style="color:var(--text-muted);font-size:0.85rem;">⏰ <?= date('g:i A', strtotime($s['prayer_time'])) ?></span>
                                            <span style="color:var(--text-muted);font-size:0.85rem;">📍 <?= htmlspecialchars($s['location']) ?></span>
                                            <span style="color:var(--text-muted);font-size:0.85rem;">👤 <?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--text-muted);">No upcoming prayer sessions have been scheduled across any department.</p>
                    <?php endif; ?>
                </div>

                <!-- Active Prayer Items (all depts) -->
                <div class="content-card">
                    <h2>🙏 Active Prayer Items (All Departments)</h2>
                    <?php
                    $all_items = $conn->query("SELECT pi.*, m.first_name, m.last_name FROM prayer_items pi JOIN members m ON pi.coordinator_id = m.id WHERE pi.status='Active' ORDER BY pi.department ASC, pi.created_at DESC");
                    if ($all_items && $all_items->num_rows > 0):
                    ?>
                        <div style="display:flex; flex-direction:column; gap:12px;">
                            <?php while($pi = $all_items->fetch_assoc()): ?>
                                <div style="padding:18px; border-left:4px solid var(--primary); background:var(--bg-main); border-radius:0 10px 10px 0;">
                                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
                                        <div>
                                            <strong style="font-size:1.05rem;color:var(--text-main);">🙏 <?= htmlspecialchars($pi['title']) ?></strong>
                                            <span class="badge" style="margin-left:8px;background:rgba(79,70,229,0.1);color:var(--primary);"><?= htmlspecialchars($pi['category']) ?></span>
                                            <span class="badge" style="margin-left:5px;background:rgba(16,185,129,0.1);color:#10b981;"><?= htmlspecialchars($pi['department']) ?></span>
                                        </div>
                                        <small style="color:var(--text-muted);">Added <?= date('M j, Y', strtotime($pi['created_at'])) ?> by <?= htmlspecialchars($pi['first_name'] . ' ' . $pi['last_name']) ?></small>
                                    </div>
                                    <?php if($pi['description']): ?><p style="margin:0;color:var(--text-muted);font-size:0.9rem;line-height:1.5;"><?= nl2br(htmlspecialchars($pi['description'])) ?></p><?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center;padding:40px;color:var(--text-muted);">
                            <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto 15px;display:block;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                            <p>No active prayer items posted across any department.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($tab == 'usher_monitoring'): ?>
                <div class="page-header">
                    <h1>🛡 Usher Monitoring</h1>
                    <p>Monitor announcements and chat messages within the Usher department.</p>
                </div>
                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <!-- Announcements Column -->
                    <div style="flex: 1; min-width: 300px;">
                        <div class="content-card" style="margin-bottom: 24px;">
                            <h2>📢 Usher Announcements</h2>
﻿                            <?php
                            // Usher Announcements Query
                            $all_usher_anns = $conn->query("SELECT ua.*, m.first_name, m.last_name, m.department FROM usher_announcements ua JOIN members m ON ua.usher_id = m.id ORDER BY ua.created_at DESC");
                            if ($all_usher_anns && $all_usher_anns->num_rows > 0): ?>
                                <div class="message-list">
                                    <?php while ($ua = $all_usher_anns->fetch_assoc()): ?>
                                        <div class="message-item">
                                            <div class="message-header">
                                                <span class="message-author">
                                                    <?= htmlspecialchars($ua['first_name'] . ' ' . $ua['last_name']) ?>
                                                    <span style="font-size:0.75rem; color:var(--text-muted); font-weight:normal;">(<?= htmlspecialchars($ua['department']) ?>)</span>
                                                </span>
                                                <span><?= date('M j, Y g:i A', strtotime($ua['created_at'])) ?></span>
                                            </div>
                                            <div class="message-body">
                                                <?= nl2br(htmlspecialchars($ua['message'])) ?>
                                                <?php if (!empty($ua['image_path'])): ?>
                                                    <div style="margin-top: 10px;">
                                                        <img src="uploads/<?= htmlspecialchars($ua['image_path']) ?>" alt="Announcement Image" style="max-width: 100%; border-radius: 8px;">
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <p style="color:var(--text-muted);">No usher announcements posted yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Chat Column -->
                    <div style="flex: 1; min-width: 300px;">
                        <div class="content-card" style="margin-bottom: 24px;">
                            <h2>dY" Usher Chat Logs</h2>
                            <div class="chat-container" style="max-height: 500px; overflow-y: auto; padding-right: 10px;">
                                <?php
                                $usher_chats = $conn->query("
                                    SELECT c.*, m.first_name, m.last_name, m.profile_picture, m.department, m.church_role 
                                    FROM chats c 
                                    JOIN members m ON c.sender_id = m.id 
                                    WHERE c.chat_type = 'usher' 
                                    ORDER BY c.created_at ASC
                                ");
                                if ($usher_chats && $usher_chats->num_rows > 0):
                                    while ($chat = $usher_chats->fetch_assoc()):
                                        $is_pastor = ($chat['church_role'] == 'Senior Pastor');
                                ?>
                                    <div class="chat-message" style="display: flex; gap: 12px; margin-bottom: 16px; <?= $is_pastor ? 'flex-direction: row-reverse;' : '' ?>">
                                        <img src="uploads/<?= htmlspecialchars($chat['profile_picture'] ?? 'default_avatar.png') ?>" alt="Profile" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                                        <div style="max-width: 75%; <?= $is_pastor ? 'text-align: right;' : '' ?>">
                                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">
                                                <?= htmlspecialchars($chat['first_name']) ?> <span style="opacity:0.7;">(<?= htmlspecialchars($chat['department']) ?>)</span> • <?= date('M j, g:i A', strtotime($chat['created_at'])) ?>
                                            </div>
                                            <div style="background: <?= $is_pastor ? 'var(--primary)' : 'var(--border-color)' ?>; color: <?= $is_pastor ? 'white' : 'var(--text-main)' ?>; padding: 10px 14px; border-radius: 12px; font-size: 0.95rem;">
                                                <?= nl2br(htmlspecialchars($chat['message'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                    <p style="color:var(--text-muted); text-align:center;">No usher chat messages yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($tab == 'building_monitoring'): ?>
                <div class="page-header">
                    <h1>🏗️ Building Monitoring</h1>
                    <p>Monitor and participate in Building & Construction department activities.</p>
                </div>
                <?php render_building_leader_profiles_card($conn); ?>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; flex-wrap: wrap;">

                    <!-- Construction Progress Log -->
                    <div class="content-card">
                        <h2>📋 Construction Progress Log</h2>
                        <?php
                        $progress_log = $conn->query("
                            SELECT bp.*, m.first_name, m.last_name, m.church_role, m.gender
                            FROM building_progress bp
                            JOIN members m ON bp.chairperson_id = m.id
                            ORDER BY bp.created_at DESC
                        ");
                        if ($progress_log && $progress_log->num_rows > 0):
                            while ($prog = $progress_log->fetch_assoc()):
                                $author_title = get_building_chairperson_title($prog['gender'], $prog['church_role']);
                        ?>
                            <div style="border:1px solid var(--border-color); border-radius:10px; padding:15px; margin-bottom:15px; background:var(--bg-main);">
                                <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                    <div>
                                        <span style="font-weight:bold; color:var(--primary);"><?= htmlspecialchars($prog['progress_status']) ?></span>
                                        <div style="font-size:0.8rem; color:var(--text-muted);"><?= htmlspecialchars($prog['first_name'] . ' ' . $prog['last_name']) ?> · <?= htmlspecialchars($author_title) ?></div>
                                    </div>
                                    <span style="font-size:0.8rem; color:var(--text-muted);"><?= date('M j, Y', strtotime($prog['date_scheduled'])) ?></span>
                                </div>
                                <p style="margin:0; font-size:0.9rem; color:var(--text-main);"><strong>Materials:</strong> <?= nl2br(htmlspecialchars($prog['materials_needed'])) ?></p>
                            </div>
                        <?php endwhile; else: ?>
                            <p style="color:var(--text-muted);">No progress logs yet.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Building Leadership Chat -->
                    <div class="content-card" style="display:flex; flex-direction:column;">
                        <h2>💬 Building Leadership Chat</h2>
                        <div style="flex:1; max-height:400px; overflow-y:auto; display:flex; flex-direction:column; gap:12px; margin-bottom:15px;" id="bldChatPastor">
                        <?php
                        $blc = $conn->query("
                            SELECT c.*,
                                   COALESCE(m.first_name, p.first_name) as fname,
                                   COALESCE(m.last_name, p.last_name) as lname,
                                   COALESCE(m.church_role, 'Pastor') as crole
                            FROM building_leadership_chat c
                            LEFT JOIN members m ON c.sender_id = m.id AND c.sender_type = 'member'
                            LEFT JOIN pastors p ON c.sender_id = p.id AND c.sender_type = 'pastor'
                            ORDER BY c.created_at ASC
                        ");
                        if ($blc && $blc->num_rows > 0):
                            while ($blmsg = $blc->fetch_assoc()):
                                $is_pastor_msg = ($blmsg['sender_type'] == 'pastor');
                        ?>
                            <div style="display:flex; flex-direction:column; align-items:<?= $is_pastor_msg ? 'flex-end' : 'flex-start' ?>;">
                                <span style="font-size:0.75rem; color:var(--text-muted); margin-bottom:4px;">
                                    <?= htmlspecialchars($blmsg['fname'] . ' ' . $blmsg['lname']) ?>
                                    <span style="background:var(--border-color); padding:2px 6px; border-radius:4px;"><?= htmlspecialchars($blmsg['crole']) ?></span>
                                </span>
                                <div style="background:<?= $is_pastor_msg ? 'var(--primary)' : 'var(--bg-main)' ?>; color:<?= $is_pastor_msg ? 'white' : 'var(--text-main)' ?>; padding:10px 15px; border-radius:<?= $is_pastor_msg ? '18px 18px 4px 18px' : '18px 18px 18px 4px' ?>; max-width:80%; border:1px solid var(--border-color);">
                                    <?= nl2br(htmlspecialchars($blmsg['message'])) ?>
                                </div>
                                <span style="font-size:0.7rem; color:var(--text-muted); margin-top:3px;"><?= date('M j, g:i A', strtotime($blmsg['created_at'])) ?></span>
                            </div>
                        <?php endwhile; else: ?>
                            <p style="color:var(--text-muted); text-align:center;">No messages yet.</p>
                        <?php endif; ?>
                        </div>
                        <script>const bpc = document.getElementById('bldChatPastor'); if(bpc) bpc.scrollTop = bpc.scrollHeight;</script>
                        <form method="POST" style="display:flex; gap:10px;">
                            <input type="hidden" name="pastor_building_chat" value="1">
                            <input type="text" name="pastor_chat_message" class="form-control" placeholder="Send a message to building leaders..." required style="margin-bottom:0;">
                            <button type="submit" class="btn btn-primary" style="white-space:nowrap; padding:0 20px;">Send</button>
                        </form>
                    </div>

                    <!-- Building Public Posts (member-facing posts) -->
                    <div class="content-card" style="grid-column: 1 / -1;">
                        <h2>📢 Church Building Public Posts</h2>
                        <?php
                        $public_posts = $conn->query("
                            SELECT bp.*, m.first_name, m.last_name, m.church_role, m.gender
                            FROM building_posts bp
                            JOIN members m ON bp.author_id = m.id
                            ORDER BY bp.created_at DESC
                        ");
                        if ($public_posts && $public_posts->num_rows > 0):
                            while ($post = $public_posts->fetch_assoc()):
                                $author_title = get_building_chairperson_title($post['gender'], $post['church_role']);
                        ?>
                            <div style="border:1px solid var(--border-color); border-radius:10px; padding:15px; margin-bottom:15px; background:var(--bg-main);">
                                <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                                    <div>
                                        <span style="font-weight:bold;"><?= htmlspecialchars($post['first_name'] . ' ' . $post['last_name']) ?></span>
                                        <span style="font-size:0.85rem; color:var(--primary); margin-left:8px;"><?= htmlspecialchars($author_title) ?></span>
                                    </div>
                                    <span style="font-size:0.8rem; color:var(--text-muted);"><?= date('M j, Y g:i A', strtotime($post['created_at'])) ?></span>
                                </div>
                                <p style="margin:0; color:var(--text-main); white-space:pre-wrap;"><?= htmlspecialchars($post['post_content']) ?></p>
                                <?php if (!empty($post['image_path'])): ?>
                                    <div style="margin-top:12px;">
                                        <img src="<?= htmlspecialchars($post['image_path']) ?>" alt="Building Post Image" style="max-width:100%; max-height:360px; border-radius:8px; border:1px solid var(--border-color); object-fit:contain;">
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; else: ?>
                            <p style="color:var(--text-muted);">No public building updates posted yet.</p>
                        <?php endif; ?>
                    </div>

                </div>


            <?php elseif ($tab == 'appointments'): ?>
                <div class="page-header">
                    <h1>Appointments</h1>
                    <p>Manage appointment requests from members.</p>
                </div>
                
                <div class="content-card">
                    <h2>Pending Appointments</h2>
                    <?php
                    $pending_appts = $conn->query("SELECT a.*, m.first_name, m.last_name, m.department FROM appointments a JOIN members m ON a.member_id = m.id WHERE a.pastor_id = $pastor_id AND a.status = 'Pending' ORDER BY a.appointment_date ASC");
                    if ($pending_appts && $pending_appts->num_rows > 0):
                    ?>
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>Member</th><th>Department</th><th>Date</th><th>Reason</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php while($a = $pending_appts->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></td>
                                    <td><?= htmlspecialchars($a['department']) ?></td>
                                    <td><?= date('M j, Y g:i A', strtotime($a['appointment_date'])) ?></td>
                                    <td><?= nl2br(htmlspecialchars($a['reason'])) ?></td>
                                    <td class="action-buttons">
                                        <a href="pastor_action.php?action=approve_appointment&id=<?= $a['id'] ?>" class="btn-action btn-approve" onclick="return confirm('Approve this appointment?')">Approve</a>
                                        <a href="pastor_action.php?action=reject_appointment&id=<?= $a['id'] ?>" class="btn-action btn-delete" onclick="return confirm('Decline this appointment?')">Decline</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p style="color:var(--text-muted);">No pending appointments.</p>
                    <?php endif; ?>
                </div>
                
                <div class="content-card">
                    <h2>Past/Handled Appointments</h2>
                    <?php
                    $handled_appts = $conn->query("SELECT a.*, m.first_name, m.last_name FROM appointments a JOIN members m ON a.member_id = m.id WHERE a.pastor_id = $pastor_id AND a.status != 'Pending' ORDER BY a.appointment_date DESC LIMIT 20");
                    if ($handled_appts && $handled_appts->num_rows > 0):
                    ?>
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>Member</th><th>Date</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php while($a = $handled_appts->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></td>
                                    <td><?= date('M j, Y', strtotime($a['appointment_date'])) ?></td>
                                    <td><span class="badge" style="background:<?= $a['status'] == 'Approved' ? 'rgba(16,185,129,0.1);color:#10b981;' : 'rgba(239,68,68,0.1);color:#ef4444;' ?>"><?= $a['status'] ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p style="color:var(--text-muted);">No past appointments.</p>
                    <?php endif; ?>
                </div>

                <!-- Embedded Messages Tab -->
                <div class="content-card" style="margin-top: 30px; max-width: 600px;">
                    <h2>Send Direct Message</h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Select Member</label>
                            <select name="member_id" class="form-control" required>
                                <option value="">-- Choose Member --</option>
                                <?php 
                                $members_query = $conn->query("SELECT id, first_name, last_name, department FROM members WHERE is_approved = 1 ORDER BY first_name");
                                while($m = $members_query->fetch_assoc()): ?>
                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name'] . ' (' . $m['department'] . ')') ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Message</label>
                            <textarea name="message" class="form-control" rows="5" required></textarea>
                        </div>
                        <button type="submit" name="send_message" class="btn-submit">Send Message</button>
                    </form>
                    
                    <?php
                    if (isset($_POST['send_message'])) {
                        $m_id = (int)$_POST['member_id'];
                        $msg = $conn->real_escape_string(trim($_POST['message']));
                        if (!empty($msg)) {
                            $conn->query("INSERT INTO messages (pastor_id, member_id, message) VALUES ($pastor_id, $m_id, '$msg')");
                            echo "<div class='alert alert-success' style='margin-top:20px;'>Message sent successfully!</div>";
                        }
                    }
                    ?>
                </div>

            <?php elseif ($tab == 'organized_events'): ?>
                <div class="page-header">
                    <h1>Departmental Organised Events</h1>
                    <p>View all meetings, announcements, and events scheduled by Organizing Secretaries across all departments.</p>
                </div>

                <div class="content-card">
                    <h2>Recent Events</h2>
                    <?php
                    $events = $conn->query("SELECT oe.*, m.first_name, m.last_name FROM organized_events oe JOIN members m ON oe.organizer_id = m.id ORDER BY oe.created_at DESC");
                    
                    if ($events && $events->num_rows > 0):
                        while($ev = $events->fetch_assoc()):
                    ?>
                        <div style="padding: 20px; border: 1px solid var(--border-color); border-radius: var(--radius-md); margin-bottom: 20px; background: rgba(255,255,255,0.02); position: relative;">
                            <span class="badge" style="position: absolute; top: 20px; right: 20px; background: rgba(16, 185, 129, 0.1); color: #10b981; font-size: 0.8rem;"><?= htmlspecialchars($ev['department']) ?></span>
                            
                            <h3 style="margin-top: 0; color: var(--primary); padding-right: 120px;"><?= htmlspecialchars($ev['title']) ?></h3>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 15px;">Posted by Organizing Secretary <?= htmlspecialchars($ev['first_name'] . ' ' . $ev['last_name']) ?> on <?= date('M j, Y g:i A', strtotime($ev['created_at'])) ?></p>
                            
                            <p style="white-space: pre-wrap; line-height: 1.6; margin-bottom: 20px;"><?= htmlspecialchars($ev['announcement']) ?></p>
                            
                            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                                <?php if (!empty($ev['meet_link'])): ?>
                                    <a href="<?= htmlspecialchars($ev['meet_link']) ?>" target="_blank" class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; text-decoration: none; padding: 8px 12px; display: inline-flex; align-items: center; gap: 5px;">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                        Join Google Meet
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($ev['whatsapp_link'])): ?>
                                    <a href="<?= htmlspecialchars($ev['whatsapp_link']) ?>" target="_blank" class="badge" style="background: rgba(37, 99, 235, 0.15); color: var(--primary); text-decoration: none; padding: 8px 12px; display: inline-flex; align-items: center; gap: 5px;">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                        Join WhatsApp Group
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($ev['attendance_file'])): ?>
                                    <a href="uploads/<?= htmlspecialchars($ev['attendance_file']) ?>" target="_blank" class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; text-decoration: none; padding: 8px 12px; display: inline-flex; align-items: center; gap: 5px;">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                        View Attendance/Document
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; else: ?>
                        <p style="color: var(--text-muted);">No departmental events have been posted by Organizing Secretaries yet.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($tab == 'discipline_reports'): ?>
                <div class="page-header">
                    <h1>Discipline & Reports Overview</h1>
                    <p>View all fines issued and disciplinary reports forwarded from Chairmen across all departments.</p>
                </div>

                <!-- Summary Cards -->
                <?php
                $total_fines = $conn->query("SELECT SUM(amount) as total FROM member_fines WHERE status = 'Paid'")->fetch_assoc();
                $unpaid_fines = $conn->query("SELECT SUM(amount) as total FROM member_fines WHERE status = 'Unpaid'")->fetch_assoc();
                $total_reports = $conn->query("SELECT COUNT(*) as count FROM member_reports WHERE status = 'Forwarded to Pastor'")->fetch_assoc();
                ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <div class="content-card" style="text-align: center;">
                        <p style="color: var(--text-muted); font-size: 0.85rem;">Total Fines Collected</p>
                        <h2 style="color: #10b981; margin: 5px 0;">KSh <?= number_format($total_fines['total'] ?? 0, 2) ?></h2>
                    </div>
                    <div class="content-card" style="text-align: center;">
                        <p style="color: var(--text-muted); font-size: 0.85rem;">Unpaid Fines</p>
                        <h2 style="color: #ef4444; margin: 5px 0;">KSh <?= number_format($unpaid_fines['total'] ?? 0, 2) ?></h2>
                    </div>
                    <div class="content-card" style="text-align: center;">
                        <p style="color: var(--text-muted); font-size: 0.85rem;">Forwarded Reports</p>
                        <h2 style="color: var(--primary); margin: 5px 0;"><?= $total_reports['count'] ?? 0 ?></h2>
                    </div>
                </div>

                <!-- All Fines -->
                <div class="content-card" style="margin-bottom: 30px;">
                    <h2>All Fines Issued</h2>
                    <?php
                    $all_fines_pastor = $conn->query("SELECT mf.*, m.first_name, m.last_name, dm.first_name as dm_first, dm.last_name as dm_last FROM member_fines mf JOIN members m ON mf.member_id = m.id JOIN members dm ON mf.discipline_master_id = dm.id ORDER BY mf.issued_at DESC LIMIT 50");
                    if ($all_fines_pastor && $all_fines_pastor->num_rows > 0):
                    ?>
                        <div class="table-responsive">
                            <table>
                                <thead><tr><th>Date & Time</th><th>Department</th><th>Member</th><th>Offense</th><th>Amount</th><th>Issued By</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php while($af = $all_fines_pastor->fetch_assoc()): ?>
                                        <tr>
                                            <td style="white-space: nowrap; font-size: 0.85em; color: var(--text-muted);"><?= date('M j, Y g:i A', strtotime($af['issued_at'])) ?></td>
                                            <td><span class="badge" style="background: rgba(16,185,129,0.1); color: #10b981;"><?= htmlspecialchars($af['department']) ?></span></td>
                                            <td style="font-weight: 500;"><?= htmlspecialchars($af['first_name'] . ' ' . $af['last_name']) ?></td>
                                            <td><?= htmlspecialchars($af['fine_reason']) ?></td>
                                            <td style="font-weight: 600;">KSh <?= number_format($af['amount'], 2) ?></td>
                                            <td style="font-size: 0.85em; color: var(--text-muted);"><?= htmlspecialchars($af['dm_first'] . ' ' . $af['dm_last']) ?></td>
                                            <td>
                                                <?php if ($af['status'] == 'Paid'): ?>
                                                    <span class="badge" style="background: rgba(16,185,129,0.1); color: #10b981;">Paid</span>
                                                <?php else: ?>
                                                    <span class="badge" style="background: rgba(239,68,68,0.1); color: #ef4444;">Unpaid</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--text-muted);">No fines have been issued yet.</p>
                    <?php endif; ?>
                </div>

                <!-- Forwarded Reports -->
                <div class="content-card" style="border-top: 4px solid var(--danger);">
                    <h2 style="color: var(--danger);">Forwarded Disciplinary Reports</h2>
                    <p style="color: var(--text-muted); margin-bottom: 15px;">Reports escalated by department Chairmen.</p>
                    <?php
                    $forwarded = $conn->query("SELECT mr.*, m.first_name, m.last_name, dm.first_name as dm_first, dm.last_name as dm_last FROM member_reports mr JOIN members m ON mr.reported_member_id = m.id JOIN members dm ON mr.reported_by_id = dm.id WHERE mr.status = 'Forwarded to Pastor' ORDER BY mr.created_at DESC");
                    if ($forwarded && $forwarded->num_rows > 0):
                    ?>
                        <div class="table-responsive">
                            <table>
                                <thead><tr><th>Date</th><th>Department</th><th>Reported Member</th><th>Reported By</th><th>Reason</th></tr></thead>
                                <tbody>
                                    <?php while($fr = $forwarded->fetch_assoc()): ?>
                                        <tr>
                                            <td style="white-space: nowrap; font-size: 0.85em; color: var(--text-muted);"><?= date('M j, Y', strtotime($fr['created_at'])) ?></td>
                                            <td><span class="badge" style="background: rgba(16,185,129,0.1); color: #10b981;"><?= htmlspecialchars($fr['department']) ?></span></td>
                                            <td style="font-weight: 500;"><?= htmlspecialchars($fr['poster_name'] ?: 'Unknown') ?></td>
                                            <td style="font-size: 0.85em; color: var(--text-muted);"><?= htmlspecialchars($fr['dm_first'] . ' ' . $fr['dm_last']) ?></td>
                                            <td><?= htmlspecialchars($fr['reason']) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--text-muted);">No reports have been forwarded by Chairmen yet.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($tab == 'graduation_info'): ?>
                <?php $current_year = date('Y'); ?>
                <div class="page-header">
                    <h1>Global Graduation Info</h1>
                    <p>View all graduating youths and announcements across the church for <?= $current_year ?>.</p>
                </div>

                <div class="content-card" style="margin-bottom: 30px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0;">Graduation Roster (<?= $current_year ?>)</h2>
                        <?php
                        $count_query = $conn->query("SELECT COUNT(*) as total FROM graduation_list WHERE graduation_year=$current_year");
                        $total_graduands = $count_query->fetch_assoc()['total'];
                        ?>
                        <span class="badge" style="background: var(--primary); color: white; font-size: 14px; padding: 5px 12px;">Total Church-wide: <?= $total_graduands ?></span>
                    </div>

                    <?php
                    $roster = $conn->query("SELECT gl.*, m.first_name, m.last_name FROM graduation_list gl JOIN members m ON gl.member_id = m.id WHERE gl.graduation_year = $current_year ORDER BY gl.department ASC, m.first_name ASC");
                    if ($roster && $roster->num_rows > 0):
                    ?>
                        <div class="table-responsive">
                            <table>
                                <thead><tr><th>Name</th><th>Department</th><th>Added Date</th></tr></thead>
                                <tbody>
                                    <?php while($r = $roster->fetch_assoc()): ?>
                                        <tr>
                                            <td style="font-weight: 500;"><svg width="18" height="18" fill="none" stroke="var(--primary)" viewBox="0 0 24 24" style="vertical-align: text-bottom; margin-right: 8px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path></svg><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
                                            <td><span class="badge" style="background: rgba(37,99,235,0.1); color: var(--primary);"><?= htmlspecialchars($r['department']) ?></span></td>
                                            <td style="color: var(--text-muted);"><?= date('M j, Y', strtotime($r['added_at'])) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center" style="padding: 40px; color: var(--text-muted);">
                            <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin: 0 auto 15px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path></svg>
                            <p>No graduands have been registered church-wide for <?= $current_year ?>.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="content-card">
                    <h2>Graduation Announcements</h2>
                    <?php
                    $grad_ann = $conn->query("SELECT da.*, m.first_name, m.last_name FROM department_announcements da JOIN members m ON da.secretary_id = m.id WHERE da.message LIKE '[GRADUATION UPDATE]%' ORDER BY da.created_at DESC");
                    if ($grad_ann && $grad_ann->num_rows > 0):
                    ?>
                        <?php while($ga = $grad_ann->fetch_assoc()): ?>
                            <div style="padding: 15px; border-left: 4px solid var(--primary); background: var(--bg-main); margin-bottom: 15px; border-radius: 0 8px 8px 0;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                    <strong style="color: var(--primary);"><?= htmlspecialchars($ga['first_name'] . ' ' . $ga['last_name']) ?> (<?= htmlspecialchars($ga['department']) ?> Graduands Secretary)</strong>
                                    <small style="color: var(--text-muted);"><?= date('M j, Y', strtotime($ga['created_at'])) ?></small>
                                </div>
                                <p style="margin: 0; line-height: 1.5;"><?= nl2br(htmlspecialchars(str_replace('[GRADUATION UPDATE] ', '', $ga['message']))) ?></p>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: var(--text-muted);">No graduation announcements have been made yet.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($tab == 'inspiration_highlights'): ?>
                <div class="page-header">
                    <h1>Daily Quote &amp; Highlights</h1>
                    <p>Post inspirational messages and memorable moments for the congregation.</p>
                </div>

                <?php /* --- Current Daily Quote Display --- */ ?>
                <div class="content-card" style="margin-bottom: 30px;">
                    <h2 style="margin-bottom: 15px;">Current Daily Quote</h2>
                    <?php if ($current_daily_message): ?>
                        <div style="text-align: center; max-width: 700px; margin: 0 auto; padding: 20px 0;">
                            <blockquote style="font-size: 1.4rem; font-style: italic; color: var(--text-main); line-height: 1.6; border-left: 4px solid var(--primary); padding-left: 20px; text-align: left; margin-bottom: 15px;">
                                &ldquo;<?= nl2br(htmlspecialchars($current_daily_message['quote'])) ?>&rdquo;
                            </blockquote>
                            <p style="color: var(--text-muted); font-size: 0.85rem;">Posted <?= date('M j, Y \a\t g:i A', strtotime($current_daily_message['created_at'])) ?></p>
                            <?php if ($current_daily_message['video_file']): ?>
                                <div style="margin-top: 15px; border-radius: 12px; overflow: hidden;">
                                    <video controls style="width: 100%; max-height: 350px; background: #000;">
                                        <source src="uploads/<?= htmlspecialchars($current_daily_message['video_file']) ?>" type="video/mp4">
                                    </video>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--text-muted);">No daily quote posted yet.</p>
                    <?php endif; ?>
                </div>

                <?php /* --- Post / Upload Forms --- */ ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <div class="content-card">
                        <h2>Post Daily Quote</h2>
                        <form method="POST" action="pastor_dashboard.php?tab=inspiration_highlights" enctype="multipart/form-data">
                            <input type="hidden" name="post_daily_message" value="1">
                            <div class="form-group">
                                <label>Inspirational Quote / Message</label>
                                <textarea name="quote" class="form-control" rows="4" required placeholder="Write a message of encouragement or scripture..."></textarea>
                            </div>
                            <div class="form-group">
                                <label>Optional Short Video (max 10MB)</label>
                                <input type="file" name="video_file" class="form-control" accept="video/mp4,video/webm,video/ogg">
                            </div>
                            <button type="submit" class="btn" style="background: var(--primary); margin-top: 10px;">Post to Entire Church</button>
                        </form>
                    </div>
                    <div class="content-card">
                        <h2>Upload Church Highlight</h2>
                        <form method="POST" action="pastor_dashboard.php?tab=inspiration_highlights" enctype="multipart/form-data">
                            <input type="hidden" name="upload_highlight" value="1">
                            <div class="form-group">
                                <label>Select Image / Video</label>
                                <input type="file" name="highlight_image" class="form-control" accept="image/*,video/mp4,video/webm" required>
                            </div>
                            <div class="form-group">
                                <label>Caption</label>
                                <input type="text" name="caption" class="form-control" required placeholder="e.g., Sunday Service Worship">
                            </div>
                            <button type="submit" class="btn" style="background: var(--primary); margin-top: 10px;">Upload Highlight</button>
                        </form>
                    </div>
                </div>

                <?php /* --- Gallery --- */ ?>
                <div class="content-card">
                    <h2 style="margin-bottom: 20px;">Church Highlights Gallery</h2>
                    <?php
                    if ($highlights->num_rows > 0):
                        $all_highlights = [];
                        mysqli_data_seek($highlights, 0);
                        while($h = $highlights->fetch_assoc()) { $all_highlights[] = $h; }
                        $photos = array_filter($all_highlights, function($h){ $ext = strtolower(pathinfo($h['image_file'], PATHINFO_EXTENSION)); return in_array($ext, ['jpg','jpeg','png','gif']); });
                        $videos = array_filter($all_highlights, function($h){ $ext = strtolower(pathinfo($h['image_file'], PATHINFO_EXTENSION)); return in_array($ext, ['mp4','webm','ogg']); });
                    ?>
                        <?php if(count($videos) > 0): ?>
                            <h3 style="margin-bottom: 15px; color: var(--primary);">🎥 Video Highlights</h3>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; margin-bottom: 30px;">
                                <?php foreach($videos as $h): $ext = strtolower(pathinfo($h['image_file'], PATHINFO_EXTENSION)); ?>
                                    <div style="border: 1px solid var(--border-color); border-radius: 10px; overflow: hidden; background: var(--card-bg); position: relative;">
                                        <video controls style="width: 100%; height: 200px; object-fit: cover; background: #000;">
                                            <source src="uploads/<?= htmlspecialchars($h['image_file']) ?>" type="video/<?= $ext ?>">
                                        </video>
                                        <div style="padding: 12px;">
                                            <?php if($h['caption']): ?><p style="font-weight: 500; color: var(--text-main); margin-bottom: 5px;"><?= htmlspecialchars($h['caption']) ?></p><?php endif; ?>
                                            <p style="font-size: 0.78rem; color: var(--text-muted);"><?= date('M j, Y', strtotime($h['created_at'])) ?></p>
                                        </div>
                                        <a href="pastor_dashboard.php?tab=inspiration_highlights&action=delete_highlight&id=<?= $h['id'] ?>" onclick="return confirm('Delete this highlight?')" style="position: absolute; top: 8px; right: 8px; background: rgba(239,68,68,0.85); color: white; padding: 4px 10px; border-radius: 5px; text-decoration: none; font-size: 0.78rem;">✕ Delete</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if(count($photos) > 0): ?>
                            <h3 style="margin-bottom: 15px; color: var(--primary);">📷 Photo Highlights</h3>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px;">
                                <?php foreach($photos as $h): ?>
                                    <div style="border: 1px solid var(--border-color); border-radius: 10px; overflow: hidden; background: var(--card-bg); position: relative; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                                        <img src="uploads/<?= htmlspecialchars($h['image_file']) ?>" alt="Highlight" style="width: 100%; height: 190px; object-fit: cover;">
                                        <div style="padding: 12px;">
                                            <?php if($h['caption']): ?><p style="font-weight: 500; color: var(--text-main); margin-bottom: 5px;"><?= htmlspecialchars($h['caption']) ?></p><?php endif; ?>
                                            <p style="font-size: 0.78rem; color: var(--text-muted);"><?= date('M j, Y', strtotime($h['created_at'])) ?></p>
                                        </div>
                                        <a href="pastor_dashboard.php?tab=inspiration_highlights&action=delete_highlight&id=<?= $h['id'] ?>" onclick="return confirm('Delete this highlight?')" style="position: absolute; top: 8px; right: 8px; background: rgba(239,68,68,0.85); color: white; padding: 4px 10px; border-radius: 5px; text-decoration: none; font-size: 0.78rem;">✕ Delete</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if(count($photos) == 0 && count($videos) == 0): ?>
                            <p style="color: var(--text-muted);">No highlights uploaded yet.</p>
                        <?php endif; ?>

                    <?php else: ?>
                        <p style="color: var(--text-muted);">No highlights have been posted yet.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($tab == 'settings'): ?>
                <div class="page-header">
                    <h1>Account Settings</h1>
                    <p>Manage your profile, credentials and preferences.</p>
    
                <?php if (!$setup_completed): ?>
                <!-- --- MANDATORY FIRST-TIME SETUP --- -->
                <!-- STEP 1: Username -->
                <div class="content-card" style="max-width: 480px; margin-bottom: 24px; border: 2px solid <?= $has_username ? '#10b981' : '#6366f1' ?>; position:relative;">
                    <div style="position:absolute; top:16px; right:16px; width:28px; height:28px; border-radius:50%; background:<?= $has_username ? '#10b981' : '#e2e8f0' ?>; display:flex; align-items:center; justify-content:center; font-size:1rem;"><?= $has_username ? '&#10003;' : '1' ?></div>
                    <h2 style="color:<?= $has_username ? '#10b981' : 'var(--primary)' ?>;">Step 1 � Create Your Username</h2>
                    <?php if ($has_username): ?>
                        <div class="alert alert-success" style="margin-top:10px;">Username set: <strong><?= htmlspecialchars($pastor['username']) ?></strong> � you can change it below if needed.</div>
                    <?php else: ?>
                        <p style="color:var(--text-muted); font-size:0.9rem;">Your username will be used to log in and recover your password.</p>
                    <?php endif; ?>
                    <form method="POST" action="?tab=settings" style="margin-top:14px;">
                        <input type="hidden" name="save_username" value="1">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="new_username" class="form-control" value="<?= htmlspecialchars($pastor['username'] ?? strtolower($pastor['first_name'])) ?>" placeholder="e.g. pastor_john" pattern="[a-zA-Z0-9_.]+" minlength="3" required>
                        </div>
                        <button type="submit" class="btn-submit" style="<?= $has_username ? 'background:var(--bg-lighter);color:var(--text-main);border:1px solid var(--border-color);' : '' ?>"><?= $has_username ? 'Change Username' : 'Save Username' ?></button>
                    </form>
                </div>

                <!-- STEP 2: Profile Picture -->
                <div class="content-card" style="max-width: 480px; margin-bottom: 24px; border: 2px solid <?= $has_profile_pic ? '#10b981' : '#6366f1' ?>; position:relative;">
                    <div style="position:absolute; top:16px; right:16px; width:28px; height:28px; border-radius:50%; background:<?= $has_profile_pic ? '#10b981' : '#e2e8f0' ?>; display:flex; align-items:center; justify-content:center; font-size:1rem;"><?= $has_profile_pic ? '&#10003;' : '2' ?></div>
                    <h2 style="color:<?= $has_profile_pic ? '#10b981' : 'var(--primary)' ?>;">Step 2 � Upload a Profile Picture</h2>
                    <div style="display: flex; align-items: center; gap: 20px; margin: 14px 0;">
                        <img src="uploads/<?= htmlspecialchars($pastor['profile_picture'] ?? 'default_avatar.png') ?>" alt="Profile" style="width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 3px solid <?= $has_profile_pic ? '#10b981' : 'var(--border-color)' ?>; cursor: zoom-in;" onclick="viewProfileImage(this.src);">
                        <div>
                            <?php if ($has_profile_pic): ?>
                                <p style="font-weight:600; color:#10b981;">&#10003; Picture uploaded!</p>
                            <?php else: ?>
                                <p style="font-weight:500;">No picture yet</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <form method="POST" action="?tab=settings" enctype="multipart/form-data">
                        <input type="hidden" name="upload_picture" value="1">
                        <div class="form-group">
                            <input type="file" name="profile_picture" class="form-control" accept="image/*" required>
                        </div>
                        <button type="submit" class="btn-submit" style="<?= $has_profile_pic ? 'background:var(--bg-lighter);color:var(--text-main);border:1px solid var(--border-color);' : '' ?>"><?= $has_profile_pic ? 'Replace Picture' : 'Upload Picture' ?></button>
                    </form>
                </div>

                <!-- STEP 3: Village & Roles -->
                <div class="content-card" style="max-width: 480px; margin-bottom: 30px; border: 2px solid <?= $has_village ? '#10b981' : '#6366f1' ?>; position:relative;">
                    <div style="position:absolute; top:16px; right:16px; width:28px; height:28px; border-radius:50%; background:<?= $has_village ? '#10b981' : '#e2e8f0' ?>; display:flex; align-items:center; justify-content:center; font-size:1rem;"><?= $has_village ? '✓' : '3' ?></div>
                    <h2 style="color:<?= $has_village ? '#10b981' : 'var(--primary)' ?>;">Step 3 — Select Your Church Village</h2>
                    <form method="POST" action="?tab=settings" style="margin-top:14px;">
                        <input type="hidden" name="save_church_village" value="1">
                        <div class="form-group">
                            <label>Church Village</label>
                            <select name="church_village" class="form-control" required>
                                <option value="" disabled <?= empty($pastor['church_village']) ? 'selected' : '' ?>>-- Select Village --</option>
                                <option value="Akoritho" <?= ($pastor['church_village']=='Akoritho') ? 'selected' : '' ?>>Akoritho</option>
                                <option value="Philadelphia" <?= ($pastor['church_village']=='Philadelphia') ? 'selected' : '' ?>>Philadelphia</option>
                                <option value="Bethsaida" <?= ($pastor['church_village']=='Bethsaida') ? 'selected' : '' ?>>Bethsaida</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Desired Role (Optional)</label>
                            <select name="desired_role_pref" class="form-control">
                                <option value="">-- No specific role --</option>
                                <option value="Worshipper" <?= ($pastor['desired_role_pref']=='Worshipper') ? 'selected' : '' ?>>Worshipper</option>
                                <option value="Church Cleaner" <?= ($pastor['desired_role_pref']=='Church Cleaner') ? 'selected' : '' ?>>Church Cleaner</option>
                                <option value="Church Cooker" <?= ($pastor['desired_role_pref']=='Church Cooker') ? 'selected' : '' ?>>Church Cooker</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-submit" style="<?= $has_village ? 'background:var(--bg-lighter);color:var(--text-main);border:1px solid var(--border-color);' : '' ?>"><?= $has_village ? 'Update Selection' : 'Save Selection' ?></button>
                    </form>
                </div>

                <?php if ($has_username && $has_profile_pic && $has_village): ?>
                <div class="alert alert-success" style="max-width:480px; margin-bottom:20px;">
                    🎉 Setup complete! Refresh the page to unlock all tabs.
                    <a href="pastor_dashboard.php" style="margin-left:12px; font-weight:600;">Unlock Now &rarr;</a>
                </div>
                <?php endif; ?>

                <hr style="border:none; border-top: 1px solid var(--border-color); max-width:480px; margin: 10px 0 28px;">
                <?php endif; // end !setup_completed ?>


                <?php if ($setup_completed): ?>
                <!-- PROFILE SETTINGS -->
                <div class="content-card" style="max-width: 450px; margin-bottom: 30px;">
                    <h2>Your Username</h2>
                    <p style="color:var(--text-muted); margin-bottom:14px;">Your current username: <strong><?= htmlspecialchars($pastor['username'] ?? '-') ?></strong>.</p>
                    <form method="POST" action="?tab=settings">
                        <input type="hidden" name="save_username" value="1">
                        <div class="form-group">
                            <label>New Username</label>
                            <input type="text" name="new_username" class="form-control" value="<?= htmlspecialchars($pastor['username'] ?? '') ?>" placeholder="e.g. pastor_john" pattern="[a-zA-Z0-9_.]+" minlength="3" required>
                        </div>
                        <button type="submit" class="btn-submit">Update Username</button>
                    </form>
                </div>

                <div class="content-card" style="max-width: 450px; margin-bottom: 30px;">
                    <h2>Profile Picture</h2>
                    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
                        <img src="uploads/<?= htmlspecialchars($pastor['profile_picture'] ?? 'default_avatar.png') ?>" alt="Profile" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); cursor: zoom-in;" onclick="viewProfileImage(this.src);">
                    </div>
                    <form method="POST" action="?tab=settings" enctype="multipart/form-data">
                        <input type="hidden" name="upload_picture" value="1">
                        <div class="form-group">
                            <input type="file" name="profile_picture" class="form-control" accept="image/*" required>
                        </div>
                        <button type="submit" class="btn-submit">Replace Picture</button>
                    </form>
                </div>

                <div class="content-card" style="max-width: 450px; margin-bottom: 30px;">
                    <h2>Church Village & Role</h2>
                    <form method="POST" action="?tab=settings" style="margin-top:14px;">
                        <input type="hidden" name="save_church_village" value="1">
                        <div class="form-group">
                            <label>Church Village</label>
                            <select name="church_village" class="form-control" required>
                                <option value="Akoritho" <?= ($pastor['church_village']=='Akoritho') ? 'selected' : '' ?>>Akoritho</option>
                                <option value="Philadelphia" <?= ($pastor['church_village']=='Philadelphia') ? 'selected' : '' ?>>Philadelphia</option>
                                <option value="Bethsaida" <?= ($pastor['church_village']=='Bethsaida') ? 'selected' : '' ?>>Bethsaida</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Desired Role (Optional)</label>
                            <select name="desired_role_pref" class="form-control">
                                <option value="">-- No specific role --</option>
                                <option value="Worshipper" <?= ($pastor['desired_role_pref']=='Worshipper') ? 'selected' : '' ?>>Worshipper</option>
                                <option value="Church Cleaner" <?= ($pastor['desired_role_pref']=='Church Cleaner') ? 'selected' : '' ?>>Church Cleaner</option>
                                <option value="Church Cooker" <?= ($pastor['desired_role_pref']=='Church Cooker') ? 'selected' : '' ?>>Church Cooker</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-submit">Update Selection</button>
                    </form>
                </div>
                <?php endif; ?>

                <div class="content-card" style="max-width: 450px;">
                    <h2>Change Password</h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" name="change_password" class="btn-submit">Update Password</button>
                    </form>
                    <?php
                    if (isset($_POST['change_password'])) {
                        $np = $_POST['new_password'];
                        $cp = $_POST['confirm_password'];
                        if ($np === $cp) {
                            $hash = password_hash($np, PASSWORD_DEFAULT);
                            $conn->query("UPDATE pastors SET password = '$hash' WHERE id = $pastor_id");
                            echo "<div class='alert alert-success' style='margin-top:20px;'>Password updated successfully.</div>";
                        } else {
                            echo "<div class='alert alert-danger' style='margin-top:20px;'>Passwords do not match.</div>";
                        }
                    }
                    ?>
                </div>
            
            <?php elseif ($tab == 'manage_sunday_school'): ?>
                <?php
                $classes = ['Little Angels', 'Champions', 'Battalion', 'Conquerors'];
                $class_counts = [];
                foreach ($classes as $cl) {
                    $cl_safe = $conn->real_escape_string($cl);
                    $res = $conn->query("SELECT COUNT(*) as cnt FROM members WHERE department = 'Sunday School' AND sunday_school_class = '$cl_safe' AND is_approved = 1");
                    $class_counts[$cl] = $res ? $res->fetch_assoc()['cnt'] : 0;
                }
                $total_ss = $conn->query("SELECT COUNT(*) as cnt FROM members WHERE department = 'Sunday School' AND is_approved = 1")->fetch_assoc()['cnt'];
                ?>
                <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                    <div>
                        <h1>Manage Sunday School</h1>
                        <p>Overview of Sunday School classes and member registration.</p>
                    </div>
                    <a href="#assignSsClassLeader" class="btn-submit" style="width:auto;height:auto;padding:9px 14px;text-decoration:none;font-size:0.86rem;margin:0;">Assign Leader</a>
                </div>
                <?php if (isset($_GET['success'])): ?><div class="alert alert-success" style="margin-bottom:16px;"><?= htmlspecialchars($_GET['success']) ?></div><?php endif; ?>
                <?php if (isset($_GET['error'])): ?><div class="alert alert-danger" style="margin-bottom:16px;"><?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>
                <div id="assignSsClassLeader" class="content-card" style="margin-bottom:24px;border-left:4px solid #0ea5e9;">
                    <h2 style="margin-bottom:14px;">Assign Sunday School Class Leader</h2>
                    <form method="POST" action="?tab=manage_sunday_school" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;align-items:end;">
                        <input type="hidden" name="assign_ss_class_leader" value="1">
                        <div class="form-group" style="margin:0;">
                            <label>Class</label>
                            <select name="class_name" id="ssTeacherClass" class="form-control" required onchange="filterSsTeacherOptions()">
                                <option value="">Select class...</option>
                                <?php foreach (ss_class_list() as $ss_class_name): ?>
                                    <option value="<?= htmlspecialchars($ss_class_name) ?>"><?= htmlspecialchars($ss_class_name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Leader From Entire Church</label>
                            <select name="leader_id" id="ssTeacherLeader" class="form-control" required>
                                <option value="">Select member...</option>
                                <?php
                                $leader_options = $conn->query("SELECT id, first_name, last_name, department, sunday_school_class FROM members WHERE is_approved = 1 ORDER BY first_name ASC, last_name ASC");
                                if ($leader_options) { while($lo = $leader_options->fetch_assoc()):
                                    $is_ss_option = ($lo['department'] ?? '') === 'Sunday School';
                                    $ss_rank_option = $is_ss_option ? ss_class_rank($lo['sunday_school_class'] ?? '') : 0;
                                    $class_label = $is_ss_option ? ' - Sunday School: ' . ($lo['sunday_school_class'] ?: 'No class') : ' - ' . ($lo['department'] ?: 'No Department');
                                ?>
                                    <option value="<?= (int)$lo['id'] ?>" data-is-ss="<?= $is_ss_option ? '1' : '0' ?>" data-ss-rank="<?= (int)$ss_rank_option ?>"><?= htmlspecialchars($lo['first_name'] . ' ' . $lo['last_name'] . $class_label) ?></option>
                                <?php endwhile; } ?>
                            </select>
                            <small style="color:var(--text-muted);display:block;margin-top:5px;">Sunday School members appear only when they are in a higher class than the class selected.</small>
                        </div>
                        <button type="submit" class="btn-submit" style="width:auto;margin:0;">Assign Leader</button>
                    </form>
                    <script>
                    function filterSsTeacherOptions() {
                        const classSelect = document.getElementById('ssTeacherClass');
                        const leaderSelect = document.getElementById('ssTeacherLeader');
                        if (!classSelect || !leaderSelect) return;
                        const ranks = {'Little Angels': 1, 'Champions': 2, 'Battalion': 3, 'Conquerors': 4};
                        const targetRank = ranks[classSelect.value] || 0;
                        Array.from(leaderSelect.options).forEach((option) => {
                            if (!option.value) return;
                            const isSundaySchool = option.dataset.isSs === '1';
                            const memberRank = parseInt(option.dataset.ssRank || '0', 10);
                            const show = !targetRank || !isSundaySchool || memberRank > targetRank;
                            option.hidden = !show;
                            option.disabled = !show;
                        });
                        if (leaderSelect.selectedOptions[0] && leaderSelect.selectedOptions[0].disabled) {
                            leaderSelect.value = '';
                        }
                    }
                    filterSsTeacherOptions();
                    </script>
                </div>
                <?php
                $pending_leader_requests = $conn->query("
                    SELECT scl.*, m.first_name, m.last_name, m.profile_picture, req.first_name AS requester_first, req.last_name AS requester_last
                    FROM sunday_school_class_leaders scl
                    JOIN members m ON scl.leader_id = m.id
                    LEFT JOIN members req ON scl.assigned_by_type = 'member' AND scl.assigned_by_id = req.id
                    WHERE scl.status = 'Pending'
                    ORDER BY scl.requested_at ASC
                ");
                ?>
                <?php if ($pending_leader_requests && $pending_leader_requests->num_rows > 0): ?>
                <div class="content-card" style="margin-bottom:24px;border-left:4px solid #f59e0b;">
                    <h2 style="margin-bottom:14px;">Pending Class Leader Approvals</h2>
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>Requested Leader</th><th>Class</th><th>Requested By</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php while($lr = $pending_leader_requests->fetch_assoc()): ?>
                                <tr>
                                    <td style="display:flex;align-items:center;gap:10px;font-weight:650;"><img src="uploads/<?= htmlspecialchars($lr['profile_picture'] ?? 'default_avatar.png') ?>" alt="Profile" style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:1px solid var(--border-color);"><?= htmlspecialchars($lr['first_name'] . ' ' . $lr['last_name']) ?></td>
                                    <td><span class="badge approved"><?= htmlspecialchars($lr['class_name']) ?></span></td>
                                    <td><?= htmlspecialchars(trim(($lr['requester_first'] ?? 'Pastor/Admin') . ' ' . ($lr['requester_last'] ?? ''))) ?></td>
                                    <td style="display:flex;gap:8px;flex-wrap:wrap;"><a href="?tab=manage_sunday_school&action=approve_ss_leader_request&id=<?= (int)$lr['id'] ?>" class="btn-action btn-approve" style="text-decoration:none;" onclick="return confirm('Approve this class leader?')">Approve</a><a href="?tab=manage_sunday_school&action=reject_ss_leader_request&id=<?= (int)$lr['id'] ?>" class="btn-action btn-reject" style="text-decoration:none;" onclick="return confirm('Reject this class leader request?')">Reject</a></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <?php
                $pending_class_requests = $conn->query("
                    SELECT r.*, m.first_name, m.last_name, m.profile_picture
                    FROM sunday_school_class_requests r
                    JOIN members m ON r.member_id = m.id
                    WHERE r.status = 'Pending'
                    ORDER BY r.requested_at ASC
                ");
                ?>
                <div class="content-card" style="margin-bottom:24px;border-left:4px solid #f59e0b;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
                        <div>
                            <h2 style="margin:0;">Pending Class Change Approvals</h2>
                            <p style="margin:4px 0 0;color:var(--text-muted);font-size:0.9rem;">Approve or reject Sunday School class changes requested by members.</p>
                        </div>
                        <span class="badge" style="background:rgba(245,158,11,0.12);color:#f59e0b;"><?= $pending_class_requests ? $pending_class_requests->num_rows : 0 ?> pending</span>
                    </div>
                    <?php if ($pending_class_requests && $pending_class_requests->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>Member</th><th>Current Class</th><th>Requested Class</th><th>Requested</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php while($req = $pending_class_requests->fetch_assoc()): ?>
                                <tr>
                                    <td style="font-weight:600;display:flex;align-items:center;gap:10px;">
                                        <img src="uploads/<?= htmlspecialchars($req['profile_picture'] ?? 'default_avatar.png') ?>" alt="Profile" style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:1px solid var(--border-color);">
                                        <?= htmlspecialchars(trim($req['first_name'] . ' ' . $req['last_name'])) ?>
                                    </td>
                                    <td><?= htmlspecialchars($req['current_class'] ?: 'Not set') ?></td>
                                    <td><span class="badge approved"><?= htmlspecialchars($req['requested_class']) ?></span></td>
                                    <td><?= date('M j, Y g:i A', strtotime($req['requested_at'])) ?></td>
                                    <td style="display:flex;gap:8px;flex-wrap:wrap;">
                                        <a href="?tab=manage_sunday_school&action=approve_ss_class_request&id=<?= (int)$req['id'] ?>" class="btn-action btn-approve" style="text-decoration:none;" onclick="return confirm('Approve this class change?')">Approve</a>
                                        <a href="?tab=manage_sunday_school&action=reject_ss_class_request&id=<?= (int)$req['id'] ?>" class="btn-action btn-reject" style="text-decoration:none;" onclick="return confirm('Reject this class change?')">Reject</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p style="margin:0;color:var(--text-muted);font-style:italic;">No pending class change requests.</p>
                    <?php endif; ?>
                </div>

                <?php
                $ss_attendance_reports = $conn->query("
                    SELECT r.*, t.first_name AS teacher_first, t.last_name AS teacher_last, t.profile_picture AS teacher_photo,
                           SUM(CASE WHEN e.status = 'Present' THEN 1 ELSE 0 END) AS present_count,
                           COUNT(e.id) AS total_count
                    FROM sunday_school_attendance_registers r
                    JOIN members t ON r.teacher_id = t.id
                    LEFT JOIN sunday_school_attendance_entries e ON e.register_id = r.id
                    GROUP BY r.id
                    ORDER BY r.attendance_date DESC, r.created_at DESC
                    LIMIT 12
                ");
                ?>
                <div class="content-card" style="margin-bottom:24px;border-left:4px solid #22c55e;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
                        <div>
                            <h2 style="margin:0;">Sunday School Attendance Registers</h2>
                            <p style="margin:4px 0 0;color:var(--text-muted);font-size:0.9rem;">Registers submitted by Sunday School class teachers.</p>
                        </div>
                    </div>
                    <?php if ($ss_attendance_reports && $ss_attendance_reports->num_rows > 0): ?>
                        <div style="display:flex;flex-direction:column;gap:14px;">
                        <?php while($report = $ss_attendance_reports->fetch_assoc()):
                            $report_id = (int)$report['id'];
                            $report_entries = $conn->query("
                                SELECT e.status, m.first_name, m.last_name, m.gender, m.phone, m.profile_picture
                                FROM sunday_school_attendance_entries e
                                JOIN members m ON e.member_id = m.id
                                WHERE e.register_id = $report_id
                                ORDER BY m.first_name ASC, m.last_name ASC
                            ");
                        ?>
                            <div style="border:1px solid var(--border-color);border-radius:10px;padding:14px;background:var(--bg-main);">
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                                    <div style="display:flex;align-items:center;gap:12px;">
                                        <img src="uploads/<?= htmlspecialchars($report['teacher_photo'] ?? 'default_avatar.png') ?>" alt="Teacher" style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid #22c55e;">
                                        <div>
                                            <strong><?= htmlspecialchars($report['class_name']) ?> - <?= htmlspecialchars(date('M j, Y', strtotime($report['attendance_date']))) ?></strong>
                                            <p style="margin:3px 0 0;color:var(--text-muted);font-size:0.86rem;">Teacher: <?= htmlspecialchars($report['teacher_first'] . ' ' . $report['teacher_last']) ?> | Present: <?= (int)$report['present_count'] ?> of <?= (int)$report['total_count'] ?></p>
                                        </div>
                                    </div>
                                    <button type="button" class="btn-submit" style="width:auto;height:auto;padding:8px 12px;margin:0;background:var(--bg-lighter);color:var(--text-main);border:1px solid var(--border-color);" onclick="printSundaySchoolAttendanceReport('pastorSsAttendance<?= $report_id ?>')">Print / Save PDF</button>
                                </div>
                                <div id="pastorSsAttendance<?= $report_id ?>" style="display:none;">
                                    <div data-class-name="<?= htmlspecialchars($report['class_name']) ?>" data-teacher-name="<?= htmlspecialchars($report['teacher_first'] . ' ' . $report['teacher_last']) ?>" data-teacher-photo="uploads/<?= htmlspecialchars($report['teacher_photo'] ?? 'default_avatar.png') ?>" data-attendance-date="<?= htmlspecialchars(date('M j, Y', strtotime($report['attendance_date']))) ?>" data-notes="<?= htmlspecialchars($report['notes'] ?? '') ?>"></div>
                                    <?php if ($report_entries && $report_entries->num_rows > 0): ?>
                                        <?php while($entry = $report_entries->fetch_assoc()): ?>
                                            <div class="attendance-print-row" data-name="<?= htmlspecialchars($entry['first_name'] . ' ' . $entry['last_name']) ?>" data-gender="<?= htmlspecialchars($entry['gender'] ?: '-') ?>" data-phone="<?= htmlspecialchars($entry['phone'] ?: '-') ?>" data-status="<?= htmlspecialchars($entry['status']) ?>" data-photo="uploads/<?= htmlspecialchars($entry['profile_picture'] ?? 'default_avatar.png') ?>"></div>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                        </div>
                        <script>
                        function printSundaySchoolAttendanceReport(reportId) {
                            const report = document.getElementById(reportId);
                            if (!report) return;
                            const meta = report.querySelector('[data-class-name]');
                            const rows = Array.from(report.querySelectorAll('.attendance-print-row'));
                            const escapeHtml = value => String(value || '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
                            const absUrl = value => value ? new URL(value, window.location.href).href : '';
                            const rowHtml = rows.length ? rows.map((row, index) => `<div class="student-row ${row.dataset.status === 'Absent' ? 'is-absent' : ''}"><span class="student-number">${index + 1}</span><span class="student-photo-cell">${row.dataset.photo ? `<img src="${escapeHtml(absUrl(row.dataset.photo))}" alt="">` : ''}</span><span class="student-name">${escapeHtml(row.dataset.name)}</span><span>${escapeHtml(row.dataset.gender)}</span><span>${escapeHtml(row.dataset.phone)}</span><span class="student-status">${escapeHtml(row.dataset.status)}</span></div>`).join('') : '<p class="empty-roster">No attendance entries were saved for this register.</p>';
                            const win = window.open('', '_blank');
                            if (!win) return;
                            win.document.write('<!doctype html><html><head><title>Sunday School Attendance</title><style>body{font-family:Arial,sans-serif;padding:28px;color:#111}.report-shell{max-width:900px;margin:0 auto}.report-header{text-align:center;border-bottom:2px solid #111;padding-bottom:14px;margin-bottom:22px}.report-header h1{margin:0;font-size:24px;letter-spacing:.08em;text-transform:uppercase}.report-header h2{margin:8px 0 12px;font-size:18px}.teacher-block{display:flex;align-items:center;justify-content:center;gap:12px;margin:10px 0}.teacher-block img{width:58px;height:58px;border-radius:50%;object-fit:cover;border:2px solid #2563eb}.teacher-name{margin:2px 0 0;color:#2563eb;font-weight:800;text-transform:uppercase;font-size:15px;letter-spacing:.03em}.teacher-label{margin:0;color:#555;font-size:12px;text-transform:uppercase;font-weight:700}.report-header p{margin:3px 0;color:#555;font-size:13px}.student-heading,.student-row{display:grid;grid-template-columns:44px minmax(86px,.5fr) minmax(210px,1.25fr) minmax(100px,.6fr) minmax(140px,.9fr) minmax(110px,.7fr);align-items:center;column-gap:12px;padding:9px 0;border-bottom:1px solid #ddd}.student-heading{font-weight:700;text-transform:uppercase;font-size:12px;letter-spacing:.04em;border-bottom:2px solid #111}.student-row{font-size:14px}.student-row.is-absent{color:#777}.student-number{font-weight:700;color:#555}.student-photo-cell img{width:42px;height:42px;border-radius:50%;object-fit:cover;border:1px solid #bbb}.student-name{font-weight:600}.student-status{font-weight:700}.empty-roster{text-align:center;color:#666;padding:30px 0}.print-footer{margin-top:26px;color:#555;font-size:12px;text-align:center}</style></head><body><main class="report-shell"><section class="report-header"><h1>Munyari EAPC</h1><h2>' + escapeHtml(meta.dataset.className) + ' Sunday School Attendance Register</h2><div class="teacher-block"><img src="' + escapeHtml(absUrl(meta.dataset.teacherPhoto)) + '" alt=""><div><p class="teacher-label">Teacher</p><p class="teacher-name">' + escapeHtml(meta.dataset.teacherName).toUpperCase() + '</p></div></div><p>Date: ' + escapeHtml(meta.dataset.attendanceDate) + '</p>' + (meta.dataset.notes ? '<p>Notes: ' + escapeHtml(meta.dataset.notes) + '</p>' : '') + '</section><section><div class="student-heading"><span>No.</span><span>Profile Photo</span><span>Name</span><span>Gender</span><span>Phone Number</span><span>Attendance Status</span></div>' + rowHtml + '</section><p class="print-footer">Generated from E.A.P.C Munyari Portal &nbsp;|&nbsp; Printed on: ' + new Date().toLocaleString() + '</p></main><!-- Edit Member Modal -->
<div id="editMemberModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:white;padding:24px;border-radius:8px;width:90%;max-width:500px;box-shadow:0 10px 25px rgba(0,0,0,0.2);">
        <h2 style="margin-top:0;color:#1e3a8a;border-bottom:2px solid #eee;padding-bottom:10px;">Edit Member</h2>
        <div id="editMemberError" style="display:none;background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;padding:8px 12px;border-radius:4px;margin-bottom:12px;font-size:0.85rem;"></div>
        <form method="POST" action="">
            <input type="hidden" name="edit_member_submit" value="1">
            <input type="hidden" name="edit_member_id" id="em_id">
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;margin-bottom:4px;font-size:0.85rem;color:#555;">First Name</label>
                    <input type="text" name="first_name" id="em_fname" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;">
                </div>
                <div>
                    <label style="display:block;margin-bottom:4px;font-size:0.85rem;color:#555;">Last Name</label>
                    <input type="text" name="last_name" id="em_lname" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;">
                </div>
            </div>
            
            <div style="margin-bottom:12px;">
                <label style="display:block;margin-bottom:4px;font-size:0.85rem;color:#555;">Username</label>
                <input type="text" name="username" id="em_uname" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;">
            </div>
            
            <div style="margin-bottom:12px;">
                <label style="display:block;margin-bottom:4px;font-size:0.85rem;color:#555;">Phone</label>
                <input type="text" name="phone" id="em_phone" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;">
            </div>
            
            <div style="margin-bottom:20px;">
                <label style="display:block;margin-bottom:4px;font-size:0.85rem;color:#555;">Residence / Address</label>
                <input type="text" name="address" id="em_addr" oninput="this.value = this.value.replace(/[^A-Za-z\s]/g, '');" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;">
            </div>
            
            <div style="display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" onclick="document.getElementById('editMemberModal').style.display='none'" style="padding:8px 16px;border:1px solid #ccc;background:#f9f9f9;border-radius:4px;cursor:pointer;">Cancel</button>
                <button type="submit" style="padding:8px 16px;border:none;background:#1e3a8a;color:white;border-radius:4px;cursor:pointer;font-weight:bold;">Save Changes</button>
            </div>
        </form>
    </div>
</div>
﻿<script>
function openEditMemberModal(id, fname, lname, uname, phone, addr) {
    document.getElementById("em_id").value    = id;
    document.getElementById("em_fname").value = fname;
    document.getElementById("em_lname").value = lname;
    document.getElementById("em_uname").value = uname;
    document.getElementById("em_phone").value = phone;
    document.getElementById("em_addr").value  = addr;
    document.getElementById("editMemberModal").style.display = "flex";
}

document.addEventListener("DOMContentLoaded", function () {
    // Names: letters and spaces only — block numbers/symbols live
    ["em_fname","em_lname"].forEach(function(fid) {
        var el = document.getElementById(fid);
        if (!el) return;
        el.addEventListener("input", function() {
            this.value = this.value.replace(/[^A-Za-z\s]/g, "");
        });
    });

    // Phone: digits only, max 10 characters
    var ph = document.getElementById("em_phone");
    if (ph) {
        ph.setAttribute("maxlength", "10");
        ph.addEventListener("input", function() {
            this.value = this.value.replace(/[^0-9]/g, "").slice(0, 10);
        });
    }

    // Block non-numeric keypress on phone field
    if (ph) {
        ph.addEventListener("keypress", function(e) {
            if (!/[0-9]/.test(e.key) && !["Backspace","Delete","Tab","ArrowLeft","ArrowRight"].includes(e.key)) {
                e.preventDefault();
            }
        });
    }

    // Submit validation
    var form = document.querySelector("#editMemberModal form");
    if (form) {
        form.addEventListener("submit", function(e) {
            var fname = document.getElementById("em_fname").value.trim();
            var lname = document.getElementById("em_lname").value.trim();
            var phone = document.getElementById("em_phone").value.trim();
            var err = "";
            if (!/^[A-Za-z\s]{2,}$/.test(fname))  err = "First name must contain letters only (minimum 2 characters).";
            else if (!/^[A-Za-z\s]{2,}$/.test(lname)) err = "Last name must contain letters only (minimum 2 characters).";
            else if (!/^\d{10}$/.test(phone))          err = "Phone number must be exactly 10 digits — no spaces or characters.";
            if (err) {
                e.preventDefault();
                var errDiv = document.getElementById("editMemberError");
                if (errDiv) { errDiv.textContent = err; errDiv.style.display = "block"; }
                else alert(err);
            }
        });
    }

    // Highlight SS member row when tab=manage_sunday_school&highlight_member_id=X
    var urlParams = new URLSearchParams(window.location.search);
    var highlightId = urlParams.get("highlight_member_id");
    if (highlightId) {
        var row = document.querySelector('[data-member-id="' + highlightId + '"]');
        if (row) {
            row.style.outline = "3px solid #f59e0b";
            row.style.background = "rgba(245,158,11,0.12)";
            setTimeout(function() { row.scrollIntoView({ behavior: "smooth", block: "center" }); }, 500);
        }
    }
});
</script>


</body></html>');
                            win.document.close();
                            win.focus();
                            win.print();
                        }
                        </script>
                    <?php else: ?>
                        <p style="margin:0;color:var(--text-muted);font-style:italic;">No attendance registers have been submitted yet.</p>
                    <?php endif; ?>
                </div>

                <!-- Stats row -->
                <div class="dashboard-grid" style="margin-bottom:30px;">
                    <div class="stat-card">
                        <h3>Total Sunday School</h3>
                        <div class="value" style="color: var(--primary);"><?= $total_ss ?></div>
                    </div>
                    <?php foreach ($classes as $cl): ?>
                    <div class="stat-card">
                        <h3><?= htmlspecialchars($cl) ?></h3>
                        <div class="value" style="color: <?= $cl === 'Battalion' ? '#f59e0b' : ($cl === 'Conquerors' ? '#10b981' : ($cl === 'Champions' ? '#ef4444' : '#6366f1')) ?>;"><?= $class_counts[$cl] ?></div>
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
                                    <script>
                function seqUnlock(currentId, nextId) {
                    const current = document.getElementById(currentId);
                    const next    = document.getElementById(nextId);
                    if (!current || !next) return;
                    const filled = current.tagName === 'SELECT'
                        ? current.value !== ''
                        : current.value.trim().length > 0 && current.checkValidity();
                    next.disabled = !filled;
                    if (!filled && next.tagName !== 'SELECT') next.value = '';
                }
                function validateInput(input, type) {
                    let errorMsg = input.parentNode.querySelector('.err-msg');
                    if (!errorMsg) {
                        errorMsg = document.createElement('span');
                        errorMsg.className = 'err-msg';
                        errorMsg.style.color = '#ef4444';
                        errorMsg.style.fontSize = '0.8rem';
                        errorMsg.style.display = 'block';
                        errorMsg.style.marginTop = '4px';
                        input.parentNode.appendChild(errorMsg);
                    }
                    if (type === 'name') {
                        if (/[^A-Za-z\s,.-]/.test(input.value)) {
                            errorMsg.innerText = 'Only letters allowed.';
                            input.setCustomValidity('Invalid');
                            setTimeout(() => { input.value = input.value.replace(/[^A-Za-z\s,.-]/g, ''); }, 300);
                        } else {
                            errorMsg.innerText = '';
                            input.setCustomValidity('');
                        }
                    } else if (type === 'phone') {
                        if (/[^\d]/.test(input.value)) {
                            errorMsg.innerText = 'Only digits allowed.';
                            input.setCustomValidity('Invalid');
                            setTimeout(() => { input.value = input.value.replace(/[^\d]/g, ''); }, 300);
                        } else {
                            errorMsg.innerText = '';
                            input.setCustomValidity('');
                        }
                    }
                }
                </script>
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
                                <label>Phone Number <span style="color:var(--text-muted); font-weight:normal; font-size:0.8rem;">(Optional)</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#f59e0b; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg></span>
                                    <input type="tel" id="ss_phone" name="phone" class="form-control" placeholder="10-digit number (Optional)" pattern="\d{10}" maxlength="10" oninput="validateInput(this,'phone');" style="padding-left:36px;" disabled>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Sunday School Class <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#8b5cf6; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg></span>
                                    <select name="ss_class" id="ss_class" class="form-control" required onchange="seqUnlock('ss_class','ss_gender')" style="padding-left:36px;" disabled>
                                        <option value="">-- Select Class --</option>
                                        <option value="Little Angels">Little Angels</option>
                                        <option value="Champions">Champions</option>
                                        <option value="Battalion">Battalion</option>
                                        <option value="Conquerors">Conquerors</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Gender <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#ec4899; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg></span>
                                    <select name="gender" id="ss_gender" class="form-control" required onchange="seqUnlock('ss_gender','ss_address')" style="padding-left:36px;" disabled>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Residential Area <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#3b82f6; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg></span>
                                    <input type="text" id="ss_address" name="address" class="form-control" placeholder="e.g. Mugui" pattern="[A-Za-z0-9\s,.-]+" oninput="validateInput(this,'name'); seqUnlock('ss_address','ss_password')" style="padding-left:36px;" required disabled>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Login Password <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#ef4444; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></span>
                                    <input type="password" name="password" id="ss_password" class="form-control" placeholder="At least 6 characters" required style="padding-left:36px; padding-right:46px;" minlength="6" oninput="seqUnlock('ss_password','ss_confirm_password')" disabled>
                                    <span onclick="var i=document.getElementById('ss_password');i.type=i.type==='password'?'text':'password'" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--text-muted);">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Confirm Password <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#ef4444; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></span>
                                    <input type="password" name="confirm_password" id="ss_confirm_password" class="form-control" placeholder="Re-enter password" required style="padding-left:36px; padding-right:46px;" oninput="var m=document.getElementById('ssPwdHint');if(this.value===document.getElementById('ss_password').value){m.style.display='block';m.style.color='var(--success)';m.textContent='Passwords match';}else{m.style.display='block';m.style.color='var(--danger)';m.textContent='Passwords do not match';}" disabled>
                                    <span onclick="var i=document.getElementById('ss_confirm_password');i.type=i.type==='password'?'text':'password'" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--text-muted);">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </span>
                                </div>
                                <small id="ssPwdHint" style="font-size:0.8rem;margin-top:4px;display:none;"></small>
                            </div>
                        </div>
                        <button type="submit" class="btn-submit" style="margin-top:10px; display:flex; align-items:center; gap:8px; width:auto; padding:0 28px;">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                            Register Member
                        </button>
                    </form>
                </div>

                <!-- Class Sections -->
                <?php foreach ($classes as $cl):
                    $cl_safe = $conn->real_escape_string($cl);
                    $cl_color = $cl === 'Battalion' ? '#f59e0b' : ($cl === 'Conquerors' ? '#10b981' : ($cl === 'Champions' ? '#ef4444' : '#6366f1'));
                    $cl_members = $conn->query("SELECT id, first_name, last_name, phone, gender FROM members WHERE department = 'Sunday School' AND sunday_school_class = '$cl_safe' AND is_approved = 1 ORDER BY first_name ASC");
                ?>
                <div class="content-card" style="margin-bottom:24px; border-left: 4px solid <?= $cl_color ?>;">
                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                        <div style="width:38px;height:38px;border-radius:10px;background:<?= $cl_color ?>;display:flex;align-items:center;justify-content:center;">
                            <svg width="20" height="20" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div>
                            <h2 style="margin:0; color:<?= $cl_color ?>;"><?= htmlspecialchars($cl) ?></h2>
                            <span style="font-size:0.82rem;color:var(--text-muted);"><?= $class_counts[$cl] ?> member<?= $class_counts[$cl] != 1 ? 's' : '' ?></span>
                        </div>
                    </div>
                    <?php
                    $teacher_q = $conn->query("SELECT m.first_name, m.last_name, m.profile_picture FROM sunday_school_class_leaders scl JOIN members m ON scl.leader_id = m.id WHERE scl.class_name = '$cl_safe' AND scl.status = 'Active' ORDER BY scl.reviewed_at DESC, scl.requested_at DESC LIMIT 1");
                    $teacher = ($teacher_q && $teacher_q->num_rows > 0) ? $teacher_q->fetch_assoc() : null;
                    ?>
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;padding:10px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-main);max-width:420px;">
                        <?php if ($teacher): ?>
                            <img src="uploads/<?= htmlspecialchars($teacher['profile_picture'] ?? 'default_avatar.png') ?>" alt="Teacher" style="width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid <?= $cl_color ?>;cursor:zoom-in;" onclick="viewProfileImage(this.src);">
                            <div><div style="font-weight:750;"><?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?></div><div style="font-size:0.82rem;color:var(--text-muted);">Class Teacher</div></div>
                        <?php else: ?>
                            <span style="color:var(--text-muted);font-size:0.9rem;">No teacher assigned for this class yet.</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($cl_members && $cl_members->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Gender</th><th>Transfer</th></tr></thead>
                            <tbody>
                            <?php $i=1; while($sm = $cl_members->fetch_assoc()): ?>
                                <?php $next_class = ss_next_class($cl); ?>
                                <tr data-member-id="<?= (int)$sm['id'] ?>">
                                    <td><?= $i++ ?></td>
                                    <td style="font-weight:500;"><?= htmlspecialchars($sm['first_name'].' '.$sm['last_name']) ?></td>
                                    <td><?= htmlspecialchars($sm['phone']) ?></td>
                                    <td><span class="badge" style="background:rgba(99,102,241,0.1);color:#6366f1;"><?= htmlspecialchars($sm['gender']) ?></span></td>
                                    <td><?php if ($next_class): ?><a href="?tab=manage_sunday_school&action=promote_ss_member&id=<?= (int)$sm['id'] ?>" class="btn-action btn-approve" style="text-decoration:none;" onclick="return confirm('Transfer this member from <?= htmlspecialchars($cl, ENT_QUOTES) ?> to <?= htmlspecialchars($next_class, ENT_QUOTES) ?>?')">To <?= htmlspecialchars($next_class) ?></a><?php else: ?><span class="badge" style="background:rgba(239,68,68,0.12);color:#ef4444;">Highest class</span><?php endif; ?></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p style="color:var(--text-muted); font-style:italic; text-align:center; padding:20px 0;">No members in <?= htmlspecialchars($cl) ?> yet.</p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Tab routing is handled purely server-side
        // Mobile sidebar toggle
        document.getElementById('mobileMenuBtn')?.addEventListener('click', function() {
            var sb = document.querySelector('.sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (sb && overlay) {
                sb.classList.toggle('active');
                if (sb.classList.contains('active')) {
                    overlay.classList.add('active');
                } else {
                    overlay.classList.remove('active');
                }
            }
        });
        
        document.getElementById('sidebarOverlay')?.addEventListener('click', function() {
            document.querySelector('.sidebar')?.classList.remove('active');
            this.classList.remove('active');
        });

        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const moonIcon = document.getElementById('moonIcon');
        const sunIcon = document.getElementById('sunIcon');
        const root = document.documentElement;

        const currentTheme = localStorage.getItem('theme') || 'light';
        if (currentTheme === 'dark') {
            root.setAttribute('data-theme', 'dark');
            moonIcon.style.display = 'none';
            sunIcon.style.display = 'block';
        }

        themeToggle.addEventListener('click', () => {
            let theme = 'light';
            if (!root.hasAttribute('data-theme')) {
                root.setAttribute('data-theme', 'dark');
                theme = 'dark';
                moonIcon.style.display = 'none';
                sunIcon.style.display = 'block';
            } else {
                root.removeAttribute('data-theme');
                moonIcon.style.display = 'block';
                sunIcon.style.display = 'none';
            }
            localStorage.setItem('theme', theme);
        });
    </script>
    <!-- Image Viewer Modal -->
    <div id="imageViewerModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:99999; align-items:center; justify-content:center; flex-direction:column;">
        <span onclick="document.getElementById('imageViewerModal').style.display='none'" style="position:absolute; top:20px; right:30px; font-size:40px; color:white; cursor:pointer; font-weight:bold; transition:color 0.2s;" onmouseover="this.style.color='#ff4444'" onmouseout="this.style.color='white'">&times;</span>
        <img id="imageViewerImg" src="" style="max-width:90%; max-height:90%; border-radius:8px; border:4px solid white; box-shadow:0 10px 25px rgba(0,0,0,0.5);">
    </div>
    <script>
    function viewProfileImage(src) {
        const modal = document.getElementById('imageViewerModal');
        const img = document.getElementById('imageViewerImg');
        img.src = src;
        modal.style.display = 'flex';
    }
    // Close on click outside image
    document.getElementById('imageViewerModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
    </script>
<!-- Edit Member Modal -->
<div id="editMemberModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:white;padding:24px;border-radius:8px;width:90%;max-width:500px;box-shadow:0 10px 25px rgba(0,0,0,0.2);">
        <h2 style="margin-top:0;color:#1e3a8a;border-bottom:2px solid #eee;padding-bottom:10px;">Edit Member</h2>
        <div id="editMemberError" style="display:none;background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;padding:8px 12px;border-radius:4px;margin-bottom:12px;font-size:0.85rem;"></div>
        <form method="POST" action="">
            <input type="hidden" name="edit_member_submit" value="1">
            <input type="hidden" name="edit_member_id" id="em_id">
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="display:block;margin-bottom:4px;font-size:0.85rem;color:#555;">First Name</label>
                    <input type="text" name="first_name" id="em_fname" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;">
                </div>
                <div>
                    <label style="display:block;margin-bottom:4px;font-size:0.85rem;color:#555;">Last Name</label>
                    <input type="text" name="last_name" id="em_lname" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;">
                </div>
            </div>
            
            <div style="margin-bottom:12px;">
                <label style="display:block;margin-bottom:4px;font-size:0.85rem;color:#555;">Username</label>
                <input type="text" name="username" id="em_uname" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;">
            </div>
            
            <div style="margin-bottom:12px;">
                <label style="display:block;margin-bottom:4px;font-size:0.85rem;color:#555;">Phone</label>
                <input type="text" name="phone" id="em_phone" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;">
            </div>
            
            <div style="margin-bottom:20px;">
                <label style="display:block;margin-bottom:4px;font-size:0.85rem;color:#555;">Residence / Address</label>
                <input type="text" name="address" id="em_addr" oninput="this.value = this.value.replace(/[^A-Za-z\s]/g, '');" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;">
            </div>
            
            <div style="display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" onclick="document.getElementById('editMemberModal').style.display='none'" style="padding:8px 16px;border:1px solid #ccc;background:#f9f9f9;border-radius:4px;cursor:pointer;">Cancel</button>
                <button type="submit" style="padding:8px 16px;border:none;background:#1e3a8a;color:white;border-radius:4px;cursor:pointer;font-weight:bold;">Save Changes</button>
            </div>
        </form>
    </div>
</div>
﻿<script>
function openEditMemberModal(id, fname, lname, uname, phone, addr) {
    document.getElementById("em_id").value    = id;
    document.getElementById("em_fname").value = fname;
    document.getElementById("em_lname").value = lname;
    document.getElementById("em_uname").value = uname;
    document.getElementById("em_phone").value = phone;
    document.getElementById("em_addr").value  = addr;
    document.getElementById("editMemberModal").style.display = "flex";
}

document.addEventListener("DOMContentLoaded", function () {
    // Names: letters and spaces only — block numbers/symbols live
    ["em_fname","em_lname"].forEach(function(fid) {
        var el = document.getElementById(fid);
        if (!el) return;
        el.addEventListener("input", function() {
            this.value = this.value.replace(/[^A-Za-z\s]/g, "");
        });
    });

    // Phone: digits only, max 10 characters
    var ph = document.getElementById("em_phone");
    if (ph) {
        ph.setAttribute("maxlength", "10");
        ph.addEventListener("input", function() {
            this.value = this.value.replace(/[^0-9]/g, "").slice(0, 10);
        });
    }

    // Block non-numeric keypress on phone field
    if (ph) {
        ph.addEventListener("keypress", function(e) {
            if (!/[0-9]/.test(e.key) && !["Backspace","Delete","Tab","ArrowLeft","ArrowRight"].includes(e.key)) {
                e.preventDefault();
            }
        });
    }

    // Submit validation
    var form = document.querySelector("#editMemberModal form");
    if (form) {
        form.addEventListener("submit", function(e) {
            var fname = document.getElementById("em_fname").value.trim();
            var lname = document.getElementById("em_lname").value.trim();
            var phone = document.getElementById("em_phone").value.trim();
            var err = "";
            if (!/^[A-Za-z\s]{2,}$/.test(fname))  err = "First name must contain letters only (minimum 2 characters).";
            else if (!/^[A-Za-z\s]{2,}$/.test(lname)) err = "Last name must contain letters only (minimum 2 characters).";
            else if (!/^\d{10}$/.test(phone))          err = "Phone number must be exactly 10 digits — no spaces or characters.";
            if (err) {
                e.preventDefault();
                var errDiv = document.getElementById("editMemberError");
                if (errDiv) { errDiv.textContent = err; errDiv.style.display = "block"; }
                else alert(err);
            }
        });
    }

    // Highlight SS member row when tab=manage_sunday_school&highlight_member_id=X
    var urlParams = new URLSearchParams(window.location.search);
    var highlightId = urlParams.get("highlight_member_id");
    if (highlightId) {
        var row = document.querySelector('[data-member-id="' + highlightId + '"]');
        if (row) {
            row.style.outline = "3px solid #f59e0b";
            row.style.background = "rgba(245,158,11,0.12)";
            setTimeout(function() { row.scrollIntoView({ behavior: "smooth", block: "center" }); }, 500);
        }
    }
});
</script>




<!-- Decline Member Modal -->
<div id="declineMemberModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:white;padding:28px 30px;border-radius:12px;width:90%;max-width:460px;box-shadow:0 10px 30px rgba(0,0,0,0.25);">
    <h2 style="margin:0 0 6px;color:#dc2626;font-size:1.15rem;">&#10060; Decline Registration</h2>
    <p id="declineModalName" style="margin:0 0 16px;color:#555;font-size:0.9rem;"></p>
    <form method="POST" action="">
      <input type="hidden" name="decline_member_submit" value="1">
      <input type="hidden" name="decline_member_id" id="declineMemberId">
      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:0.85rem;font-weight:600;color:#374151;margin-bottom:6px;">Reason for declining <span style="color:#dc2626;">*</span></label>
        <textarea name="decline_reason" id="declineReasonText" rows="4" required placeholder="e.g. Incomplete information, duplicate account, not a registered member..." style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;font-size:0.9rem;resize:vertical;box-sizing:border-box;"></textarea>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;">
        <button type="button" onclick="document.getElementById('declineMemberModal').style.display='none'" style="padding:8px 18px;border:1px solid #d1d5db;background:#f9fafb;border-radius:6px;cursor:pointer;font-size:0.9rem;">Cancel</button>
        <button type="submit" style="padding:8px 18px;border:none;background:#dc2626;color:white;border-radius:6px;cursor:pointer;font-size:0.9rem;font-weight:600;">Confirm Decline</button>
      </div>
    </form>
  </div>
</div>
<script>
function openDeclineModal(id, name) {
    document.getElementById("declineMemberId").value = id;
    document.getElementById("declineModalName").textContent = "Member: " + name;
    document.getElementById("declineReasonText").value = "";
    document.getElementById("declineMemberModal").style.display = "flex";
}
</script>

</body>
<!-- Decline Member Modal -->
<div id="declineMemberModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:white;padding:28px 30px;border-radius:12px;width:90%;max-width:460px;box-shadow:0 10px 30px rgba(0,0,0,0.25);">
    <h2 style="margin:0 0 6px;color:#dc2626;font-size:1.15rem;">&#10060; Decline Registration</h2>
    <p id="declineModalName" style="margin:0 0 16px;color:#555;font-size:0.9rem;"></p>
    <form method="POST" action="">
      <input type="hidden" name="decline_member_submit" value="1">
      <input type="hidden" name="decline_member_id" id="declineMemberId">
      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:0.85rem;font-weight:600;color:#374151;margin-bottom:6px;">Reason for declining <span style="color:#dc2626;">*</span></label>
        <textarea name="decline_reason" id="declineReasonText" rows="4" required placeholder="e.g. Incomplete information, duplicate account, not a registered member..." style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;font-size:0.9rem;resize:vertical;box-sizing:border-box;"></textarea>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;">
        <button type="button" onclick="document.getElementById('declineMemberModal').style.display='none'" style="padding:8px 18px;border:1px solid #d1d5db;background:#f9fafb;border-radius:6px;cursor:pointer;font-size:0.9rem;">Cancel</button>
        <button type="submit" style="padding:8px 18px;border:none;background:#dc2626;color:white;border-radius:6px;cursor:pointer;font-size:0.9rem;font-weight:600;">Confirm Decline</button>
      </div>
    </form>
  </div>
</div>
<script>
function openDeclineModal(id, name) {
    document.getElementById("declineMemberId").value = id;
    document.getElementById("declineModalName").textContent = "Member: " + name;
    document.getElementById("declineReasonText").value = "";
    document.getElementById("declineMemberModal").style.display = "flex";
}

        function printVillageTable(containerId, villageName, orientation) {
        if (!orientation) orientation = 'landscape';
        var container = document.getElementById(containerId);
        if (!container) { alert('Table not found.'); return; }
        var w = window.open('', '_blank');
        if (!w) { alert('Popup blocked!'); return; }
        var theadHTML = container.querySelector('thead') ? container.querySelector('thead').outerHTML : '';
        var tbodyHTML = container.querySelector('tbody') ? container.querySelector('tbody').outerHTML : '';
        var wmSize = orientation === 'landscape' ? '3.2rem' : '2.0rem';
        var pageSize = orientation === 'landscape' ? 'A4 landscape' : 'A4 portrait';
        w.document.write('<!doctype html><html><head><title>' + villageName + ' Village</title>');
        w.document.write('<base href="' + window.location.origin + window.location.pathname + '">');
        w.document.write('<style>');
        w.document.write('*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;color-adjust:exact!important;}');
        w.document.write('@media print{@page{size:' + pageSize + ';margin: 0;} body{padding:15mm !important;} }');
        w.document.write('body{font-family:Arial,sans-serif;margin:0;padding:10px;padding-bottom:60px;}');
        w.document.write('table{width:100%;border-collapse:collapse;}');
        w.document.write('th{background:#1e3a8a;color:white;padding:7px 8px;font-size:0.8rem;text-align:left;}');
        w.document.write('td{padding:6px 8px;border:1px solid #ccc;font-size:0.78rem;}');
        w.document.write('.badge{display:inline-block;padding:3px 8px;border-radius:10px;font-size:0.72rem;font-weight:600;}');
        w.document.write('.watermark{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-45deg);font-size:' + wmSize + '!important;color:rgba(30,58,138,0.25)!important;font-weight:bold;white-space:nowrap;z-index:999999!important;opacity:1!important;pointer-events:none;letter-spacing:4px;text-transform:uppercase;mix-blend-mode:multiply;}');
        w.document.write('.footer{position:fixed;bottom:0;left:0;right:0;text-align:center;font-size:10px;color:#777;font-style:italic;background:rgba(255,255,255,0.9);padding:5px 0;z-index:10;}');
        w.document.write('</style></head><body>');
        w.document.write('<div class="watermark">E.A.P.C MUNYARI CHURCH</div>');
        w.document.write('<div style="text-align:center;border-bottom:2px solid #1e3a8a;padding-bottom:10px;position:relative;margin-bottom:18px;min-height:85px;">');
        w.document.write('<img src="church_logo.jpg" style="position:absolute;left:20px;top:0;width:70px;height:70px;object-fit:contain;">');
        w.document.write('<img src="church_logo.jpg" style="position:absolute;right:20px;top:0;width:70px;height:70px;object-fit:contain;">');
        w.document.write('<h2 style="margin:0;color:#1e3a8a;padding-top:8px;">E.A.P.C MUNYARI CHURCH</h2>');
        w.document.write('<h3 style="margin:3px 0;color:#1e3a8a;">' + villageName.toUpperCase() + ' VILLAGE — MEMBERS LIST</h3>');
        w.document.write('<p style="margin:2px 0;font-size:0.78rem;color:#555;">Printed on: ' + new Date().toLocaleString() + '</p>');
w.document.write('</div>');
        w.document.write('<table>' + theadHTML + tbodyHTML + '</table>');
        w.document.write('<div class="footer">Generated from E.A.P.C Munyari Portal &nbsp;|&nbsp; Printed on: ' + (typeof printDate !== 'undefined' ? printDate : new Date().toLocaleString()) + '</div>');
        w.document.write('</body></html>');
        w.document.close();
        w.focus();
        setTimeout(function(){ w.print(); }, 500);
    }
</script>
</html>
