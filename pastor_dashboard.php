<?php
session_start();
require_once 'db_connect.php';
if (isset($_POST['add_custom_role'])) {
    $role_name = trim($_POST['custom_role_name']);
    if (!empty($role_name)) {
        $stmt = $conn->prepare("INSERT IGNORE INTO custom_desired_roles (role_name) VALUES (?)");
        $stmt->bind_param("s", $role_name);
        $stmt->execute();
        header("Location: " . basename($_SERVER['PHP_SELF']) . "?tab=desired_roles&success=" . urlencode("Custom role '$role_name' added successfully"));
        exit;
    }
}
if (isset($_GET['delete_custom_role'])) {
    $role_id = (int)$_GET['delete_custom_role'];
    $conn->query("DELETE FROM custom_desired_roles WHERE id = $role_id");
    header("Location: " . basename($_SERVER['PHP_SELF']) . "?tab=desired_roles&success=Custom role deleted");
    exit;
}
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
    $valid_roles = [''];
    $cr_q = $conn->query("SELECT role_name FROM custom_desired_roles");
    if ($cr_q) { while($cr = $cr_q->fetch_assoc()){ $valid_roles[] = $cr['role_name']; } }
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
                                <input type="text" id="p_first_name" name="first_name" class="form-control" placeholder="e.g. Peter" pattern="[A-Za-z\s]+" oninput="setFieldStatus_filling(this.id); validateInput(this,'name'); seqUnlock('p_first_name','p_last_name')" required onblur="setFieldStatus_blur(this)">
                            </div>
                            <div class="form-group">
                                <label>Last Name <span style="color:var(--danger);">*</span></label>
                                <input type="text" id="p_last_name" name="last_name" class="form-control" placeholder="e.g. Ntoiti" pattern="[A-Za-z\s]+" oninput="setFieldStatus_filling(this.id); validateInput(this,'name'); seqUnlock('p_last_name','p_phone')" disabled required onblur="setFieldStatus_blur(this)">
                            </div>

                            <div class="form-group">
                                <label>Phone Number <span style="color:var(--danger);">*</span></label>
                                <input type="tel" id="p_phone" name="phone" class="form-control" placeholder="10-digit number" pattern="\d{10}" maxlength="10" oninput="validateInput(this,'phone'); checkPhoneAsync(this,'pRegDept')" disabled required>
                            </div>
                             <div class="form-group">
                                 <label>Department <span style="color:var(--danger);">*</span></label>
                                 <div style="position:relative;">
                                     <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #8b5cf6; pointer-events: none; z-index:1;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg></span>
                                     <select name="department" id="pRegDept" class="form-control" style="padding-left:36px;" required onchange="pRegAutoGender(); seqUnlock('pRegDept','pRegGender'); seqUnlock('pRegGender','p_address'); setFieldStatus_blur(document.getElementById(currentId||this.id));" disabled>
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
                                     <select name="gender" id="pRegGender" class="form-control" style="padding-left:36px;" required onchange="seqUnlock('pRegGender','p_address') setFieldStatus_blur(document.getElementById(currentId||this.id));" disabled>
                                         <option value="Male">Male</option>
                                         <option value="Female">Female</option>
                                     </select>
                                 </div>
                                 <small id="pRegGenderHint" style="color:var(--text-muted);font-size:0.78rem;">Select department first. Women auto-fill Female; Elders auto-fill Male.</small>
                             </div>
                            <div class="form-group" style="grid-column:1/-1;">
                                <label>Residential Address</label>
                                <input type="text" id="p_address" name="address" class="form-control" placeholder="e.g. Mugui" pattern="[A-Za-z0-9\s,.-]+" oninput="setFieldStatus_filling(this.id); validateInput(this,'name'); seqUnlock('p_address','pastorRegPwd')" disabled onblur="setFieldStatus_blur(this)">
                            </div>
                            <!-- Login Credentials -->
                            <div class="form-group">
                                <label>Login Password <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <input type="password" name="password" id="pastorRegPwd" class="form-control" placeholder="At least 6 characters" required style="padding-right:46px;" minlength="6" oninput="setFieldStatus_filling(this.id); seqUnlock('pastorRegPwd','pastorRegPwdConfirm')" disabled onblur="setFieldStatus_blur(this)">
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

                    function setFieldStatus_filling(id) {
    var el = document.getElementById(id); if (!el) return;
    var g = el.closest('.form-group'); if (!g) return;
    var lbl = g.querySelector('label'); if (!lbl) return;
    var b = lbl.querySelector('.sb');
    if (!b) { b = document.createElement('span'); b.className='sb'; b.style.cssText='margin-left:8px;font-size:0.68rem;padding:2px 7px;border-radius:4px;font-weight:800;text-transform:uppercase;vertical-align:middle;display:inline-block;transition:all 0.2s;'; lbl.appendChild(b); }
    if (el.value.trim().length > 0) { b.textContent='FILLING'; b.style.background='#fef08a'; b.style.color='#854d0e'; }
    else { b.textContent=''; b.style.background='transparent'; b.style.color='transparent'; }
}
function setFieldStatus_blur(el) {
    if (!el) return;
    var g = el.closest('.form-group'); if (!g) return;
    var lbl = g.querySelector('label'); if (!lbl) return;
    var b = lbl.querySelector('.sb');
    if (!b) { b = document.createElement('span'); b.className='sb'; b.style.cssText='margin-left:8px;font-size:0.68rem;padding:2px 7px;border-radius:4px;font-weight:800;text-transform:uppercase;vertical-align:middle;display:inline-block;transition:all 0.2s;'; lbl.appendChild(b); }
    if (el.value.trim().length > 0 && el.checkValidity()) { b.textContent='\u2713 FILLED'; b.style.background='#dcfce7'; b.style.color='#166534'; }
    else if (!el.value.trim().length) { b.textContent=''; b.style.background='transparent'; b.style.color='transparent'; }
}
function seqUnlock(currentId, nextId) {
    const current = document.getElementById(currentId);
    const next    = document.getElementById(nextId);
    if (!current || !next) return;
    const filled = current.tagName === 'SELECT'
        ? current.value !== ''
        : current.value.trim().length > 0 && current.checkValidity();
    const wasDis = next.disabled;
    next.disabled = !filled;
    if (!filled && next.tagName !== 'SELECT') next.value = '';

    // --- FILLING badge on current field while typing (set by oninput directly) ---
    // --- FILLED badge on current field when done ---
    const cGrp = current.closest('.form-group');
    const cLbl = cGrp ? cGrp.querySelector('label') : null;
    if (cLbl) {
        let cb = cLbl.querySelector('.sb');
        if (!cb) { cb = document.createElement('span'); cb.className='sb'; cb.style.cssText='margin-left:8px;font-size:0.68rem;padding:2px 7px;border-radius:4px;font-weight:800;text-transform:uppercase;vertical-align:middle;display:inline-block;transition:all 0.2s;'; cLbl.appendChild(cb); }
        if (filled) { cb.textContent='\u2713 FILLED'; cb.style.background='#dcfce7'; cb.style.color='#166534'; }
        else { cb.textContent=''; cb.style.background='transparent'; cb.style.color='transparent'; }
    }
    // --- ACTIVATED badge on next field ---
    const nGrp = next.closest('.form-group');
    const nLbl = nGrp ? nGrp.querySelector('label') : null;
    if (nLbl) {
        let nb = nLbl.querySelector('.sb');
        if (!nb) { nb = document.createElement('span'); nb.className='sb'; nb.style.cssText='margin-left:8px;font-size:0.68rem;padding:2px 7px;border-radius:4px;font-weight:800;text-transform:uppercase;vertical-align:middle;display:inline-block;transition:all 0.2s;'; nLbl.appendChild(nb); }
        if (filled && wasDis) { nb.textContent='ACTIVATED'; nb.style.background='#dbeafe'; nb.style.color='#1e40af'; }
        else if (!filled) { nb.textContent=''; nb.style.background='transparent'; nb.style.color='transparent'; }
    }
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
                                    <input type="text" id="ss_first_name" name="first_name" class="form-control" placeholder="e.g. John" pattern="[A-Za-z\s]+" oninput="setFieldStatus_filling(this.id); validateInput(this,'name'); seqUnlock('ss_first_name','ss_last_name')" style="padding-left:36px;" required onblur="setFieldStatus_blur(this)">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Last Name <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#10b981; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></span>
                                    <input type="text" id="ss_last_name" name="last_name" class="form-control" placeholder="e.g. Kamau" pattern="[A-Za-z\s]+" oninput="setFieldStatus_filling(this.id); validateInput(this,'name'); seqUnlock('ss_last_name','ss_class'); document.getElementById('ss_phone').disabled=false;" style="padding-left:36px;" disabled required onblur="setFieldStatus_blur(this)">
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
                                    <select name="ss_class" id="ss_class" class="form-control" required onchange="seqUnlock('ss_class','ss_gender') setFieldStatus_blur(document.getElementById(currentId||this.id));" style="padding-left:36px;" disabled>
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
                                    <select name="gender" id="ss_gender" class="form-control" required onchange="seqUnlock('ss_gender','ss_address') setFieldStatus_blur(document.getElementById(currentId||this.id));" style="padding-left:36px;" disabled>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Residential Area <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#3b82f6; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg></span>
                                    <input type="text" id="ss_address" name="address" class="form-control" placeholder="e.g. Mugui" pattern="[A-Za-z0-9\s,.-]+" oninput="setFieldStatus_filling(this.id); validateInput(this,'name'); seqUnlock('ss_address','ss_password')" style="padding-left:36px;" required disabled onblur="setFieldStatus_blur(this)">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Login Password <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#ef4444; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></span>
                                    <input type="password" name="password" id="ss_password" class="form-control" placeholder="At least 6 characters" required style="padding-left:36px; padding-right:46px;" minlength="6" oninput="setFieldStatus_filling(this.id); seqUnlock('ss_password','ss_confirm_password')" disabled onblur="setFieldStatus_blur(this)">
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
    

function printRequiredLeadersTable(orientation) {
    if (!orientation) orientation = 'portrait';
    var card = document.getElementById('requiredLeadersCard');
    if (!card) { alert('Table not found.'); return; }
    
    var logoUrl = 'church_logo.jpg';
    if (document.getElementById('deptPrintLogo')) {
        logoUrl = document.getElementById('deptPrintLogo').getAttribute('data-src');
    }
    var printDate = new Date().toLocaleString();
    
    var theadRow = card.querySelector('thead') ? card.querySelector('thead').innerHTML : '';
    theadRow = theadRow.replace(/<tr>/i, '<tr style="background:#1e3a8a;color:white;-webkit-print-color-adjust:exact;">');
    var tbody = card.querySelector('tbody') ? card.querySelector('tbody').innerHTML : '';
    
    var wmSize = orientation === 'landscape' ? '3.2rem' : '2.0rem';
    var pageSize = orientation === 'landscape' ? 'A4 landscape' : 'A4 portrait';

    var w = window.open('', '_blank');
    if (!w) { alert('Popup blocked! Please allow popups.'); return; }

    w.document.write('<!doctype html><html><head>');
    w.document.write('<title>Required Church Leaders</title>');
    w.document.write('<base href="' + window.location.href + '">');
    w.document.write('<style>');
    w.document.write('*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;color-adjust:exact!important;}');
    w.document.write('@media print{@page{size:' + pageSize + ';margin: 0;} body{padding:15mm !important;} }');
    w.document.write('body{font-family:Arial,sans-serif;margin:0;padding:10px;padding-bottom:60px;}');
    w.document.write('table{width:100%;border-collapse:collapse;margin-top:20px;}');
    w.document.write('th{background:#1e3a8a;color:white;padding:10px 14px;font-size:0.85rem;text-align:left;}');
    w.document.write('td{padding:9px 14px;border:1px solid #ccc;font-size:0.85rem;vertical-align:middle;}');
    w.document.write('.badge{display:inline-block;padding:4px 10px;border-radius:12px;font-size:0.75rem;font-weight:700;}');
    w.document.write('.no-print{display:none!important;}');
    w.document.write('.screen-name{display:none!important;}');
    w.document.write('.print-only-name{display:inline!important;}');
    w.document.write('.watermark{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-45deg);font-size:' + wmSize + '!important;color:rgba(30,58,138,0.25)!important;font-weight:bold;white-space:nowrap;z-index:999999!important;opacity:1!important;pointer-events:none;letter-spacing:4px;text-transform:uppercase;mix-blend-mode:multiply;}');
    w.document.write('.footer{position:fixed;bottom:0;left:0;right:0;text-align:center;font-size:10px;color:#777;font-style:italic;background:rgba(255,255,255,0.9);padding:5px 0;z-index:10;}');
    w.document.write('.btn-sm, button { display:none !important; }');
    w.document.write('</style></head><body>');

    w.document.write('<div class="watermark">E.A.P.C MUNYARI CHURCH</div>');

    // Church header with dual logos
    w.document.write('<div style="text-align:center;border-bottom:2px solid #1e3a8a;padding-bottom:10px;position:relative;margin-bottom:14px;min-height:85px;">');
    w.document.write('<img src="' + logoUrl + '" style="position:absolute;left:20px;top:0;width:70px;height:70px;object-fit:contain;">');
    w.document.write('<img src="' + logoUrl + '" style="position:absolute;right:20px;top:0;width:70px;height:70px;object-fit:contain;">');
    w.document.write('<h2 style="margin:0;color:#1e3a8a;padding-top:8px;">E.A.P.C MUNYARI CHURCH</h2>');
    w.document.write('<h3 style="margin:5px 0;color:#1e3a8a;">REQUIRED / UNFILLED LEADERSHIP POSITIONS</h3>');
    w.document.write('<p style="margin:2px 0;font-size:0.78rem;color:#555;">Printed on: ' + printDate + '</p>');
    w.document.write('</div>');

    // Table
    w.document.write('<table><thead><tr>' + theadRow + '</tr></thead><tbody>' + tbody + '</tbody></table>');
    
    // Signature block
    w.document.write('<div style="display:flex;justify-content:space-between;align-items:flex-end;margin-top:60px;padding:0 20px;page-break-inside:avoid;">');
    w.document.write('<div style="text-align:center;"><div style="font-size:0.82rem;font-weight:700;text-transform:uppercase;color:#1e3a8a;">Church Pastor</div>');
    w.document.write('<div style="display:flex;align-items:flex-end;gap:8px;margin-top:20px;"><span style="font-style:italic;">Sign:</span><span style="display:inline-block;border-bottom:1px solid #000;width:200px;height:14px;"></span></div>');
    w.document.write('<div style="display:flex;align-items:flex-end;gap:8px;margin-top:10px;"><span>Date:</span><span style="display:inline-block;border-bottom:1px dotted #000;width:200px;height:14px;"></span></div></div>');
    
    w.document.write('<div style="text-align:center;"><div style="border:2px dashed #aaa;width:100px;height:100px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:0.7rem;text-transform:uppercase;line-height:1.4;text-align:center;">Official<br>Stamp</div></div>');
    w.document.write('</div>');

    w.document.write('<div class="footer">Generated from E.A.P.C Munyari Portal &nbsp;|&nbsp; Printed on: ' + printDate + '</div>');
    w.document.write('</body></html>');
    w.document.close();
    w.focus();
    setTimeout(function(){ w.print(); }, 600);
}
function printRequiredTable() {
    var card = document.getElementById('assignedRolesCard');
    if (!card) {
        alert('Table not found.');
        return;
    }
    var w = window.open('', '_blank', 'width=1100,height=800');
    if (!w) return;
    w.document.write('<!DOCTYPE html><html><head><title>Required Church Leaders Table</title>');
    w.document.write('<style>');
    w.document.write('@page { margin: 15mm; size: A4 landscape; }');
    w.document.write('body { font-family: Arial, sans-serif; font-size: 0.85rem; color: #111; }');
    w.document.write('h2 { color: #1e3a8a; font-size: 1.2rem; margin-bottom: 12px; }');
    w.document.write('.badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:0.78rem; font-weight:700; }');
    w.document.write('table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }');
    w.document.write('thead tr { background: #1e3a8a; color: white; }');
    w.document.write('thead th { padding: 9px 12px; text-align: left; font-size: 0.8rem; }');
    w.document.write('tbody tr:nth-child(even) { background: #eff6ff; }');
    w.document.write('tbody td { padding: 8px 12px; border-bottom: 1px solid #ddd; vertical-align: middle; }');
    w.document.write('h3 { color: #1e3a8a; font-size: 1rem; margin: 18px 0 6px; border-bottom: 2px solid #1e3a8a; padding-bottom: 4px; }');
    w.document.write('.dept-cell { font-weight:800; color:#1e3a8a; }');
    w.document.write('img { width:32px; height:32px; border-radius:50%; object-fit:cover; vertical-align:middle; margin-right:8px; border:1px solid #ccc; }');
    w.document.write('.btn-sm { display:none; }');
    w.document.write('</style></head><body>');
    w.document.write('<h2 style="text-align:center; border-bottom:2px solid #1e3a8a; padding-bottom:8px;">E.A.P.C MUNYARI CHURCH &mdash; Currently Assigned Leaders</h2>');
    w.document.write('<p style="text-align:center;color:#555;margin-bottom:16px;">Printed on: ' + new Date().toLocaleDateString('en-GB', {weekday:'long',year:'numeric',month:'long',day:'numeric'}) + '</p>');
    w.document.write(card.innerHTML);
    w.document.write('</body></html>');
    w.document.close();
    setTimeout(function(){ w.print(); }, 600);
}
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
        var leaderNode = container.querySelector('.print-leader-profile');
        var leaderHTML = '';
        if (leaderNode) {
            leaderHTML = '<div style="text-align:center; margin:0 auto 10px; background:#f8fafc; padding:6px 12px; border-radius:6px; border:1px solid #e2e8f0; width:fit-content; min-width:200px;">' + leaderNode.innerHTML + '</div>';
        }
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
