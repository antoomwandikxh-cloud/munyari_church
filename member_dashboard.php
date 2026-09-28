<?php
session_start();
require_once 'db_connect.php';
require_once 'notification_badges.php';
require_once 'role_departments.php';
if (!isset($_SESSION['member_id'])) { header("Location: login.php"); exit(); }

$member_id = $_SESSION['member_id'];
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
    CREATE TABLE IF NOT EXISTS sunday_school_class_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_name VARCHAR(100) NOT NULL,
        teacher_id INT NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
// Handle Sunday School member registration (Sunday School Patron and Vice)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_ss_member'])) {
    $role_check = $conn->query("SELECT church_role FROM members WHERE id = $member_id")->fetch_assoc();
    $role_lc    = strtolower(trim($role_check['church_role'] ?? ''));
    if (strpos($role_lc, 'sunday school patron') !== false) {
        $fn       = $conn->real_escape_string(trim($_POST['first_name'] ?? ''));
        $ln       = $conn->real_escape_string(trim($_POST['last_name'] ?? ''));
        $ph       = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
        $gn       = in_array($_POST['gender'] ?? '', ['Male','Female']) ? $_POST['gender'] : 'Male';
        $ad       = $conn->real_escape_string(trim($_POST['address'] ?? ''));
        $cl       = in_array($_POST['ss_class'] ?? '', ['Little Angels','Champions','Battalion','Conquerors']) ? $_POST['ss_class'] : '';
        $pw_plain = trim($_POST['password'] ?? '');
        $pw_conf  = trim($_POST['confirm_password'] ?? '');
        if (empty($fn) || empty($ln) || empty($cl) || empty($pw_plain) || empty($ad)) {
            header("Location: ?tab=manage_department&error=" . urlencode("First name, last name, address, class and password are required."));
            exit();
        }
        if ($pw_plain !== $pw_conf) {
            header("Location: ?tab=manage_department&error=" . urlencode("Passwords do not match."));
            exit();
        }
        if (!empty($ph)) {
            $dup = $conn->query("SELECT id FROM members WHERE phone = '$ph'")->num_rows;
            if ($dup > 0) {
                header("Location: ?tab=manage_department&error=" . urlencode("A member with that phone number already exists."));
                exit();
            }
        }
        $pw      = password_hash($pw_plain, PASSWORD_DEFAULT);
        $cl_safe = $conn->real_escape_string($cl);
        $conn->query("INSERT INTO members (first_name, last_name, phone, address, department, gender, password, sunday_school_class, is_approved, reg_date) VALUES ('$fn','$ln','$ph','$ad','Sunday School','$gn','$pw','$cl_safe',1,NOW())");
        $new_mid = $conn->insert_id;
        if ($new_mid) {
            $welcome = $conn->real_escape_string("Welcome to Sunday School! You have been registered in the $cl class.");
            $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($new_mid,'member','$welcome',0,NOW())");
            $nmsg = $conn->real_escape_string("$fn $ln has been registered as a new Sunday School member in the $cl class.");
            $ar = $conn->query("SELECT id FROM admins");
            while ($a = $ar->fetch_assoc()) { $aid=(int)$a['id']; $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($aid,'admin','$nmsg',0,NOW())"); }
            $pr = $conn->query("SELECT id FROM pastors");
            while ($p = $pr->fetch_assoc()) { $pid=(int)$p['id']; $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($pid,'pastor','$nmsg',0,NOW())"); }
        }
        header("Location: ?tab=manage_department&success=" . urlencode("Sunday School member $fn $ln registered successfully!"));
        exit();
    }
}
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

$member = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
// Only auto-sync department for roles that truly OWN a department (Youth*, Women*, Elder*).
// Sunday School roles are cross-church — they must never overwrite the member's home dept.
$_first_role = trim(explode(',', str_replace('&', ',', $member['church_role'] ?? ''))[0]);
$role_department = department_for_role($_first_role); // returns null for Sunday School roles
if ($role_department && !is_sunday_school_leadership_role($_first_role) && ($member['department'] ?? '') !== $role_department) {
    $esc_role_department = $conn->real_escape_string($role_department);
    $conn->query("UPDATE members SET department = '$esc_role_department' WHERE id = $member_id");
    $member['department'] = $role_department;
}

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

if (!function_exists('render_ss_teacher_card')) {
    function render_ss_teacher_card($conn, $class_name) {
        $class_safe = $conn->real_escape_string($class_name);
        $teacher = $conn->query("
            SELECT m.id, m.first_name, m.last_name, m.profile_picture, m.department
            FROM sunday_school_class_leaders scl
            JOIN members m ON scl.leader_id = m.id
            WHERE scl.class_name = '$class_safe' AND scl.status = 'Active'
            ORDER BY scl.reviewed_at DESC, scl.requested_at DESC
            LIMIT 1
        ");
        $teacher_row = ($teacher && $teacher->num_rows > 0) ? $teacher->fetch_assoc() : null;
        ?>
        <div class="content-card" style="margin-bottom:20px;border-left:4px solid #0ea5e9;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <p style="margin:0;color:var(--text-muted);font-size:0.78rem;text-transform:uppercase;font-weight:800;">Sunday School Teacher</p>
                    <h2 style="margin:4px 0 0;"><?= htmlspecialchars($class_name) ?> Class</h2>
                </div>
                <?php if ($teacher_row): ?>
                    <div style="display:flex;align-items:center;gap:12px;padding:10px 12px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-main);">
                        <img src="uploads/<?= htmlspecialchars($teacher_row['profile_picture'] ?? 'default_avatar.png') ?>" alt="Teacher" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid #0ea5e9;cursor:zoom-in;" onclick="viewProfileImage(this.src);">
                        <div>
                            <div style="font-weight:800;color:var(--text-main);"><?= htmlspecialchars($teacher_row['first_name'] . ' ' . $teacher_row['last_name']) ?></div>
                            <div style="font-size:0.82rem;color:var(--text-muted);"><?= htmlspecialchars($teacher_row['department'] ?? 'Church Member') ?></div>
                        </div>
                    </div>
                <?php else: ?>
                    <span class="badge" style="background:rgba(100,100,100,0.1);color:var(--text-muted);">No teacher assigned yet</span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('render_ss_class_messages_card')) {
    function render_ss_class_messages_card($conn, $class_name) {
        $class_safe = $conn->real_escape_string($class_name);
        $messages = $conn->query("
            SELECT scm.message, scm.created_at, m.first_name, m.last_name, m.profile_picture
            FROM sunday_school_class_messages scm
            JOIN members m ON scm.teacher_id = m.id
            WHERE scm.class_name = '$class_safe'
            ORDER BY scm.created_at DESC
            LIMIT 20
        ");
        ?>
        <div class="content-card" style="max-width:760px;margin-bottom:24px;border-left:4px solid #22c55e;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
                <div>
                    <p style="margin:0;color:var(--text-muted);font-size:0.78rem;text-transform:uppercase;font-weight:800;">Teacher Messages</p>
                    <h2 style="margin:4px 0 0;"><?= htmlspecialchars($class_name) ?> Class Updates</h2>
                </div>
            </div>
            <?php if ($messages && $messages->num_rows > 0): ?>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <?php while($msg = $messages->fetch_assoc()): ?>
                        <div style="display:flex;gap:12px;align-items:flex-start;padding:12px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-main);">
                            <img src="uploads/<?= htmlspecialchars($msg['profile_picture'] ?? 'default_avatar.png') ?>" alt="Teacher" style="width:42px;height:42px;border-radius:50%;object-fit:cover;border:2px solid #22c55e;cursor:zoom-in;flex-shrink:0;" onclick="viewProfileImage(this.src);">
                            <div style="min-width:0;flex:1;">
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:4px;">
                                    <strong style="color:var(--text-main);"><?= htmlspecialchars(trim($msg['first_name'] . ' ' . $msg['last_name'])) ?></strong>
                                    <small style="color:var(--text-muted);"><?= date('M j, Y g:i A', strtotime($msg['created_at'])) ?></small>
                                </div>
                                <p style="margin:0;color:var(--text-main);line-height:1.55;white-space:pre-wrap;"><?= htmlspecialchars($msg['message']) ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p style="margin:0;color:var(--text-muted);">No teacher messages have been posted for this class yet.</p>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (isset($_GET['action']) && in_array($_GET['action'], ['approve_ss_class_request', 'reject_ss_class_request', 'promote_ss_member'], true)) {
    $role_lc = strtolower(trim($member['church_role'] ?? ''));
    $can_review_ss_class = strpos($role_lc, 'sunday school patron') !== false;
    if (!$can_review_ss_class) {
        header("Location: member_dashboard.php?tab=manage_department&error=" . urlencode("Only the Sunday School patron or vice patron can review Sunday School class transfers."));
        exit();
    }

    if ($_GET['action'] === 'promote_ss_member') {
        $target_member_id = (int)($_GET['id'] ?? 0);
        $target = $conn->query("SELECT id, first_name, last_name, sunday_school_class FROM members WHERE id = $target_member_id AND department = 'Sunday School' AND is_approved = 1 LIMIT 1")->fetch_assoc();
        if (!$target) {
            header("Location: member_dashboard.php?tab=manage_department&error=" . urlencode("Sunday School member was not found."));
            exit();
        }

        $current_class = trim($target['sunday_school_class'] ?? '');
        $next_class = ss_next_class($current_class);
        if (!$next_class) {
            header("Location: member_dashboard.php?tab=manage_department&error=" . urlencode("This member cannot be transferred further in the Sunday School class order."));
            exit();
        }

        $next_safe = $conn->real_escape_string($next_class);
        $conn->query("UPDATE members SET sunday_school_class = '$next_safe' WHERE id = $target_member_id");
        $conn->query("UPDATE sunday_school_class_requests SET status = CASE WHEN requested_class = '$next_safe' THEN 'Approved' ELSE 'Rejected' END, reviewed_by_type = 'member', reviewed_by_id = $member_id, reviewed_at = NOW() WHERE member_id = $target_member_id AND status = 'Pending'");
        $notice = $conn->real_escape_string("Sunday School class approval: your Sunday School class has been changed from $current_class to $next_class.");
        $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($target_member_id, 'member', '$notice', 0, NOW())");
        $target_name = trim(($target['first_name'] ?? '') . ' ' . ($target['last_name'] ?? ''));
        header("Location: member_dashboard.php?tab=manage_department&success=" . urlencode("$target_name transferred from $current_class to $next_class."));
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
        header("Location: member_dashboard.php?tab=manage_department&error=" . urlencode("Class change request was not found or already reviewed."));
        exit();
    }

    $target_member_id = (int)$request['member_id'];
    $requested_class = $conn->real_escape_string($request['requested_class']);
    $reviewer_type = 'member';
    $reviewer_id = $member_id;
    $member_name = trim(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? ''));

    if ($_GET['action'] === 'approve_ss_class_request') {
        $actual_current_class = trim($request['actual_current_class'] ?? '');
        if (ss_next_class($actual_current_class) !== $request['requested_class']) {
            header("Location: member_dashboard.php?tab=manage_department&error=" . urlencode("This request does not follow the Sunday School class order."));
            exit();
        }
        $conn->query("UPDATE members SET sunday_school_class = '$requested_class' WHERE id = $target_member_id");
        $conn->query("UPDATE sunday_school_class_requests SET status = 'Approved', reviewed_by_type = '$reviewer_type', reviewed_by_id = $reviewer_id, reviewed_at = NOW() WHERE id = $request_id");
        $notice = $conn->real_escape_string("Sunday School class approval: your Sunday School class has been changed to {$request['requested_class']}.");
        $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($target_member_id, 'member', '$notice', 0, NOW())");
        header("Location: member_dashboard.php?tab=manage_department&success=" . urlencode("$member_name class change approved."));
        exit();
    }

    $conn->query("UPDATE sunday_school_class_requests SET status = 'Rejected', reviewed_by_type = '$reviewer_type', reviewed_by_id = $reviewer_id, reviewed_at = NOW() WHERE id = $request_id");
    $notice = $conn->real_escape_string("Sunday School class approval: your Sunday School class change request to {$request['requested_class']} was rejected.");
    $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($target_member_id, 'member', '$notice', 0, NOW())");
    header("Location: member_dashboard.php?tab=manage_department&success=" . urlencode("$member_name class change rejected."));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_ss_class_leader'])) {
    $role_lc = strtolower(trim($member['church_role'] ?? ''));
    $can_request_ss_leader = strpos($role_lc, 'sunday school patron') !== false;
    if (!$can_request_ss_leader) {
        header("Location: member_dashboard.php?tab=manage_department&error=" . urlencode("Only the Sunday School patron or vice patron can request class leaders."));
        exit();
    }

    $class_name = trim($_POST['class_name'] ?? '');
    $leader_id = (int)($_POST['leader_id'] ?? 0);
    if (!in_array($class_name, ss_class_list(), true)) {
        header("Location: member_dashboard.php?tab=manage_department&error=" . urlencode("Please select a valid Sunday School class."));
        exit();
    }

    $leader = $conn->query("SELECT id, first_name, last_name, department, sunday_school_class FROM members WHERE id = $leader_id AND is_approved = 1 LIMIT 1")->fetch_assoc();
    if (!$leader) {
        header("Location: member_dashboard.php?tab=manage_department&error=" . urlencode("Please select an approved church member."));
        exit();
    }
    if (!ss_can_teach_class($leader['department'] ?? '', $leader['sunday_school_class'] ?? '', $class_name)) {
        header("Location: member_dashboard.php?tab=manage_department&error=" . urlencode("A Sunday School member can teach only classes below their own class."));
        exit();
    }

    $class_safe = $conn->real_escape_string($class_name);
    $pending_same = $conn->query("SELECT id FROM sunday_school_class_leaders WHERE class_name = '$class_safe' AND leader_id = $leader_id AND status = 'Pending' LIMIT 1");
    if ($pending_same && $pending_same->num_rows > 0) {
        header("Location: member_dashboard.php?tab=manage_department&error=" . urlencode("That class leader request is already pending approval."));
        exit();
    }

    $conn->query("INSERT INTO sunday_school_class_leaders (class_name, leader_id, assigned_by_type, assigned_by_id, status, requested_at) VALUES ('$class_safe', $leader_id, 'member', $member_id, 'Pending', NOW())");
    $requester_name = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
    $leader_name = trim(($leader['first_name'] ?? '') . ' ' . ($leader['last_name'] ?? ''));
    $notice = $conn->real_escape_string("Sunday School class leader approval request: $requester_name requested $leader_name as Sunday School teacher for $class_name.");
    $pastors_to_notify = $conn->query("SELECT id FROM pastors");
    if ($pastors_to_notify) {
        while ($p = $pastors_to_notify->fetch_assoc()) {
            $pid = (int)$p['id'];
            $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($pid, 'pastor', '$notice', 0, NOW())");
        }
    }
    $admins_to_notify = $conn->query("SELECT id FROM admins");
    if ($admins_to_notify) {
        while ($a = $admins_to_notify->fetch_assoc()) {
            $aid = (int)$a['id'];
            $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($aid, 'admin', '$notice', 0, NOW())");
        }
    }
    header("Location: member_dashboard.php?tab=manage_department&success=" . urlencode("Class leader request sent for pastor/admin approval."));
    exit();
}
// Build per-role welcome lines in the new format
$member_home_department = $member['department'] ?? 'None';
$raw_roles_welcome = array_filter(array_map('trim', explode(',', str_replace('&', ',', $member['church_role'] ?? ''))));

// Helper: strip "(subsidiary)" for display purposes only
function clean_role_display($role) {
    return trim(preg_replace('/\s*\(subsidiary\)\s*/i', '', $role ?? ''));
}

if (!function_exists('get_member_roles')) {
    function get_member_roles($church_role_string) {
        return array_filter(array_map('normalize_role_name', explode(',', str_replace('&', ',', $church_role_string ?? ''))));
    }
}

function leadership_hierarchy_groups($department) {
    $groups = [
        ['label' => 'Chairperson', 'patterns' => []],
        ['label' => 'Vice Chairperson', 'patterns' => []],
        ['label' => 'Secretary', 'patterns' => []],
        ['label' => 'Vice Secretary', 'patterns' => []],
        ['label' => 'Treasurer', 'patterns' => []],
        ['label' => 'Subsidiary Leaders', 'patterns' => [
            'organizing secretary', 'vice organizing secretary',
            'discipline master', 'vice discipline master',
            'prayer coordinator', 'vice prayer coordinator',
            'choir leader', 'vice choir leader'
        ]]
    ];

    if (department_matches($department, 'Youths')) {
        $groups[0]['patterns'] = ['youth chairperson', 'youth chairman', 'youth chairlady'];
        $groups[1]['patterns'] = ['vice youth chairperson', 'vice youth chairman', 'vice youth chairlady'];
        $groups[2]['patterns'] = ['youth secretary'];
        $groups[3]['patterns'] = ['vice youth secretary'];
        $groups[4]['patterns'] = ['youth treasurer'];
        $groups[5]['patterns'] = array_merge(['mama youth', 'baba youth', 'sport secretary', 'sports secretary', 'vice sport secretary', 'vice sports secretary', 'graduands secretary', 'vice graduands secretary'], $groups[5]['patterns']);
    } elseif (department_matches($department, 'Womens Ministry')) {
        $groups[0]['patterns'] = ['women chairlady', 'women chairperson', 'women chairman'];
        $groups[1]['patterns'] = ['vice women chairlady', 'vice women chairperson', 'vice women chairman'];
        $groups[2]['patterns'] = ['women secretary'];
        $groups[3]['patterns'] = ['vice women secretary'];
        $groups[4]['patterns'] = ['women treasurer'];
    } elseif (department_matches($department, 'Elders')) {
        $groups[0]['patterns'] = ['elder chairman', 'elder chairperson', 'elder chairlady'];
        $groups[1]['patterns'] = ['vice elder chairman', 'vice elder chairperson', 'vice elder chairlady'];
        $groups[2]['patterns'] = ['elder secretary'];
        $groups[3]['patterns'] = ['vice elder secretary'];
        $groups[4]['patterns'] = ['elder treasurer'];
    } elseif (department_matches($department, 'Sunday School')) {
        $groups[0]['patterns'] = ['sunday school patron', 'sunday school chairperson', 'sunday school chairman', 'sunday school chairlady'];
        $groups[1]['patterns'] = ['vice sunday school patron', 'vice sunday school chairperson', 'vice sunday school chairman', 'vice sunday school chairlady'];
        $groups[2]['patterns'] = ['sunday school secretary'];
        $groups[3]['patterns'] = ['vice sunday school secretary'];
        $groups[4]['patterns'] = ['sunday school treasurer'];
        $groups[5]['patterns'] = array_merge(['sport secretary', 'sports secretary', 'vice sport secretary', 'vice sports secretary', 'graduands secretary', 'vice graduands secretary'], $groups[5]['patterns']);
    }

    return $groups;
}

function leadership_hierarchy_department_sql($conn, $department, $group_index) {
    if (department_matches($department, 'Sunday School')) {
        if ($group_index <= 4) {
            return '1=1';
        }
        return '(' . department_match_sql($conn, 'department', 'Sunday School') . " OR LOWER(church_role) LIKE '%(sunday school)%')";
    }

    if (department_matches($department, 'Youths')) {
        return '(' . department_match_sql($conn, 'department', $department) . " OR LOWER(church_role) LIKE '%mama youth%' OR LOWER(church_role) LIKE '%baba youth%' OR LOWER(church_role) LIKE '%(youths)%' OR LOWER(church_role) LIKE '%(youth ministry)%')";
    }

    return '(' . department_match_sql($conn, 'department', $department) . " OR LOWER(church_role) LIKE '%(" . $conn->real_escape_string(strtolower($department)) . ")%')";
}

function leadership_hierarchy_title($department) {
    if (department_matches($department, 'Youths')) {
        return 'Youth Leaders';
    }
    if (department_matches($department, 'Womens Ministry')) {
        return 'Women Ministry Leaders';
    }
    if (department_matches($department, 'Elders')) {
        return 'Elder Ministry Leaders';
    }
    if (department_matches($department, 'Sunday School')) {
        return 'Sunday School Leaders';
    }
    return trim($department . ' Leaders');
}

function render_leadership_hierarchy_card($conn, $hierarchy_dept) {
    $hierarchy_dept = $hierarchy_dept ?: 'Church';
    $church_pastor = $conn->query("SELECT first_name, last_name, profile_picture FROM pastors WHERE is_approved = 1 LIMIT 1")->fetch_assoc();
    $leader_role_groups = leadership_hierarchy_groups($hierarchy_dept);
    $leader_section_title = leadership_hierarchy_title($hierarchy_dept);
    $general_church_role_labels = [
        'senior church elder' => 'Senior Church Elder',
        'general church secretary' => 'General Church Secretary',
        'vice church secretary' => 'Vice Church Secretary',
        'treasurer' => 'Church Treasurer',
    ];
    $general_role_parts = [];
    foreach (array_keys($general_church_role_labels) as $general_role_pattern) {
        $general_role_parts[] = "LOWER(church_role) LIKE '%" . $conn->real_escape_string($general_role_pattern) . "%'";
    }
    $general_church_rows = [];
    $general_church_result = $conn->query("
        SELECT id, first_name, last_name, gender, department, church_role, profile_picture
        FROM members
        WHERE is_approved = 1 AND (" . implode(' OR ', $general_role_parts) . ")
        ORDER BY first_name ASC, last_name ASC
    ");
    if ($general_church_result) {
        while ($general_row = $general_church_result->fetch_assoc()) {
            $general_church_rows[] = $general_row;
        }
    }
    ?>
    <div class="content-card" style="margin-bottom: 22px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:18px;">
            <div>
                <p style="margin:0;font-size:0.78rem;color:var(--text-muted);text-transform:uppercase;font-weight:800;letter-spacing:0.05em;">Leadership Hierarchy</p>
                <h2 style="margin:4px 0 0;color:var(--text-main);"><?= htmlspecialchars($hierarchy_dept) ?> Leadership</h2>
            </div>
        </div>
        <?php if ($church_pastor): ?>
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:14px;padding:20px;border:1px solid var(--border-color);border-radius:12px;background:var(--bg-main);margin:0 auto 20px;max-width:760px;">
            <img src="uploads/<?= htmlspecialchars($church_pastor['profile_picture'] ?? 'default_avatar.png') ?>" alt="Pastor" style="width:96px;height:96px;border-radius:50%;object-fit:cover;border:3px solid var(--primary);cursor:zoom-in;flex-shrink:0;" onclick="viewProfileImage(this.src);">
            <div>
                <p style="margin:0;font-size:0.82rem;color:var(--primary);text-transform:uppercase;font-weight:800;">Church Pastor</p>
                <h3 style="margin:5px 0 0;color:var(--text-main);font-size:1.35rem;">Pastor <?= htmlspecialchars($church_pastor['first_name'] . ' ' . $church_pastor['last_name']) ?></h3>
            </div>
            <?php if (!empty($general_church_rows)): ?>
            <div style="width:100%;border-top:1px solid var(--border-color);padding-top:14px;margin-top:2px;">
                <p style="margin:0 0 10px;color:var(--text-main);font-size:0.95rem;font-weight:850;">General Church Leaders</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px;">
                <?php
                $shown_general_leaders = [];
                foreach ($general_church_role_labels as $general_role => $general_label):
                    foreach ($general_church_rows as $general_leader):
                        $general_leader_roles = get_member_roles($general_leader['church_role'] ?? '');
                        if (!in_array($general_role, $general_leader_roles, true)) continue;
                        if (isset($shown_general_leaders[$general_leader['id'] . ':' . $general_role])) continue;
                        $shown_general_leaders[$general_leader['id'] . ':' . $general_role] = true;
                ?>
                    <div style="display:flex;align-items:center;gap:11px;padding:12px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-card);text-align:left;">
                        <img src="uploads/<?= htmlspecialchars($general_leader['profile_picture'] ?? 'default_avatar.png') ?>" alt="<?= htmlspecialchars($general_label) ?>" style="width:58px;height:58px;border-radius:50%;object-fit:cover;border:2px solid var(--border-color);cursor:zoom-in;flex-shrink:0;" onclick="viewProfileImage(this.src);">
                        <div style="min-width:0;">
                            <p style="margin:0 0 3px;color:var(--text-main);font-weight:750;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($general_leader['first_name'] . ' ' . $general_leader['last_name']) ?></p>
                            <p style="margin:0;color:var(--text-muted);font-size:0.82rem;line-height:1.35;"><?= htmlspecialchars($general_label) ?></p>
                        </div>
                    </div>
                <?php endforeach; endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div style="display:flex;align-items:center;gap:10px;margin:6px 0 14px;padding-top:4px;">
            <div style="height:1px;background:var(--border-color);flex:1;"></div>
            <h3 style="margin:0;color:var(--text-main);font-size:1.05rem;font-weight:850;text-align:center;"><?= htmlspecialchars($leader_section_title) ?></h3>
            <div style="height:1px;background:var(--border-color);flex:1;"></div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;">
            <?php
            $shown_leaders = [];
            $has_leaders = false;
            foreach ($leader_role_groups as $group_index => $role_group):
                if (empty($role_group['patterns'])) continue;
                $role_parts = [];
                foreach ($role_group['patterns'] as $pattern) {
                    $safe_pattern = $conn->real_escape_string($pattern);
                    $role_parts[] = "LOWER(church_role) LIKE '%$safe_pattern%'";
                }
                $role_sql = '(' . implode(' OR ', $role_parts) . ')';
                $dept_sql = leadership_hierarchy_department_sql($conn, $hierarchy_dept, $group_index);
                $leaders = $conn->query("
                    SELECT id, first_name, last_name, gender, department, church_role, profile_picture, " . role_rank_case_sql('church_role') . " AS role_rank
                    FROM members
                    WHERE is_approved = 1 AND $dept_sql AND $role_sql
                    ORDER BY role_rank ASC, first_name ASC, last_name ASC
                ");
                if (!$leaders || $leaders->num_rows == 0) continue;
                while ($leader = $leaders->fetch_assoc()):
                    $leader_roles = get_member_roles($leader['church_role'] ?? '');
                    $matched_role = '';
                    foreach ($leader_roles as $leader_role_candidate) {
                        if (in_array($leader_role_candidate, $role_group['patterns'], true)) {
                            $matched_role = $leader_role_candidate;
                            break;
                        }
                    }
                    if ($matched_role === '') continue;
                    if (isset($shown_leaders[$leader['id'] . ':' . $role_group['label']])) continue;
                    $shown_leaders[$leader['id'] . ':' . $role_group['label']] = true;
                    $has_leaders = true;
                    $leader_label = role_display_label(clean_role_display($matched_role), $leader['department'] ?? '', $leader['gender'] ?? '');
                    $leader_size = $group_index === 0 ? 68 : ($group_index === 1 ? 58 : ($group_index <= 4 ? 52 : 44));
            ?>
                <div style="display:flex;align-items:center;gap:12px;padding:13px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-main);">
                    <img src="uploads/<?= htmlspecialchars($leader['profile_picture'] ?? 'default_avatar.png') ?>" alt="<?= htmlspecialchars($leader_label) ?>" style="width:<?= $leader_size ?>px;height:<?= $leader_size ?>px;border-radius:50%;object-fit:cover;border:2px solid <?= $group_index === 0 ? 'var(--primary)' : 'var(--border-color)' ?>;cursor:zoom-in;flex-shrink:0;" onclick="viewProfileImage(this.src);">
                    <div style="min-width:0;">
                        <p style="margin:0 0 3px;color:var(--text-main);font-weight:750;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($leader['first_name'] . ' ' . $leader['last_name']) ?></p>
                        <p style="margin:0;color:var(--text-muted);font-size:0.82rem;line-height:1.35;"><?= htmlspecialchars($leader_label) ?></p>
                    </div>
                </div>
            <?php endwhile; endforeach; ?>
            <?php if (!$has_leaders): ?>
                <p style="margin:0;color:var(--text-muted);">No department leaders have been assigned yet.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

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
    $building_leader_rows = [];
    $building_leader_result = $conn->query("
        SELECT id, first_name, last_name, gender, department, church_role, profile_picture, " . role_rank_case_sql('church_role') . " AS role_rank
        FROM members
        WHERE is_approved = 1 AND LOWER(church_role) LIKE '%building%'
        ORDER BY role_rank ASC, first_name ASC, last_name ASC
    ");
    if ($building_leader_result) {
        while ($building_row = $building_leader_result->fetch_assoc()) {
            $building_leader_rows[] = $building_row;
        }
    }
    ?>
    <div class="content-card" style="margin-bottom:22px;border-left:4px solid #ef4444;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
            <div>
                <p style="margin:0;color:var(--text-muted);font-size:0.78rem;text-transform:uppercase;font-weight:800;">Church Projects</p>
                <h2 style="margin:4px 0 0;">Building & Construction Leaders</h2>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;">
            <?php
            $shown_building_leaders = [];
            $has_building_leaders = false;
            foreach ($building_role_labels as $building_role_key => $building_role_label):
                foreach ($building_leader_rows as $building_leader):
                    foreach (get_member_roles($building_leader['church_role'] ?? '') as $building_member_role):
                        if ($building_member_role !== $building_role_key) continue;
                        $shown_key = $building_leader['id'] . ':' . $building_role_key;
                        if (isset($shown_building_leaders[$shown_key])) continue;
                        $shown_building_leaders[$shown_key] = true;
                        $has_building_leaders = true;
                        $display_building_role = $building_role_label;
                        if (in_array($building_role_key, ['building chairperson', 'building chairman', 'building chairlady', 'vice building chairperson', 'vice building chairman', 'vice building chairlady'], true)) {
                            $display_building_role = get_building_chairperson_title($building_leader['gender'] ?? '', $building_role_key);
                        }
            ?>
                <div style="display:flex;align-items:center;gap:12px;padding:13px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-main);">
                    <img src="uploads/<?= htmlspecialchars($building_leader['profile_picture'] ?? 'default_avatar.png') ?>" alt="<?= htmlspecialchars($display_building_role) ?>" style="width:52px;height:52px;border-radius:50%;object-fit:cover;border:2px solid #ef4444;cursor:zoom-in;flex-shrink:0;" onclick="viewProfileImage(this.src);">
                    <div style="min-width:0;">
                        <p style="margin:0 0 3px;color:var(--text-main);font-weight:750;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($building_leader['first_name'] . ' ' . $building_leader['last_name']) ?></p>
                        <p style="margin:0;color:var(--text-muted);font-size:0.82rem;line-height:1.35;"><?= htmlspecialchars($display_building_role) ?></p>
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
                    foreach (get_member_roles($worship_leader['church_role'] ?? '') as $worship_role):
                        if ($worship_role !== $role_key) continue;
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


$welcome_role_lines = []; // Each entry: ['role' => ..., 'scope' => ...]
$has_home_dept_role = false;
$is_youth_advisor = false;

foreach ($raw_roles_welcome as $role_item) {
    $norm = normalize_role_name($role_item);
    if (is_general_church_role($norm)) {
        $welcome_role_lines[] = [
            'role'  => ucwords(strtolower(clean_role_display($role_item))),
            'scope' => 'General Church',
        ];
    } elseif (is_youth_advisor_role($norm)) {
        $is_youth_advisor = true;
        $welcome_role_lines[] = [
            'role'  => ucwords(strtolower(clean_role_display($role_item))),
            'scope' => 'Youth Department',
        ];
        if (strtolower($member_home_department) === 'youths' || strtolower($member_home_department) === 'youth ministry') {
            $has_home_dept_role = true;
        }
    } else {
        $dept_for_r = department_for_role($norm);
        
        // Check if this role has an explicit (Sunday School) suffix
        $role_explicit_dept = '';
        if (preg_match('/\((.+?)\)/i', $role_item, $_wm)) {
            $role_explicit_dept = trim($_wm[1]);
        }
        
        // Determine scope label:
        // 1) If role has explicit "(Sunday School)" suffix → SS Department
        // 2) If role is a Sunday School leadership role (is_sunday_school_leadership_role) → SS Department
        // 3) If dept_for_r is known → use that
        // 4) Fall back to member's home department
        if (!empty($role_explicit_dept) && strtolower($role_explicit_dept) === 'sunday school') {
            $scope_label = 'Sunday School Department';
        } elseif (function_exists('is_sunday_school_leadership_role') && is_sunday_school_leadership_role($norm)) {
            $scope_label = 'Sunday School Department';
        } elseif ($dept_for_r) {
            $scope_label = ucwords(strtolower($dept_for_r)) . ' Department';
        } else {
            $scope_label = ucwords(strtolower($member_home_department)) . ' Department';
        }
        
        if ($dept_for_r === null || strtolower($dept_for_r) === strtolower($member_home_department)) {
            $has_home_dept_role = true;
        }
        // Gender-aware display for Youth Chairperson
        if ($norm === 'youth chairperson') {
            $display_role = get_youth_chairperson_title($member['gender'] ?? '');
        } else {
            $display_role = ucwords(strtolower(clean_role_display($role_item)));
        }
        $welcome_role_lines[] = [
            'role'  => $display_role,
            'scope' => $scope_label,
        ];
    }
}

// If they have no home department role, but they have a home department, add a "Member" line for it
if (!$has_home_dept_role && $member_home_department !== 'None' && strtolower(trim($member['church_role'] ?? '')) !== 'member' && !empty(trim($member['church_role'] ?? ''))) {
    $welcome_role_lines[] = [
        'role' => 'Member',
        'scope' => ucwords(strtolower($member_home_department)) . ' Department'
    ];
}

// Keep legacy variable for any other code that may reference it
$welcome_role_scope = !empty($welcome_role_lines) ? $welcome_role_lines[0]['scope'] : ucwords(strtolower($member_home_department)) . ' Department';
$is_youth_advisor_scope = $is_youth_advisor; // Use the properly calculated flag
$youth_advisor_scopes = $is_youth_advisor_scope
    ? ['Youths' => 'Youth Department', $member_home_department => $member_home_department . ' Department']
    : [$member_home_department => $member_home_department . ' Department'];
$tab_badges = build_tab_notification_badges($conn, $member_id, 'member', $tab);
$ss_teacher_classes = [];
$ss_teacher_q = $conn->query("SELECT class_name FROM sunday_school_class_leaders WHERE leader_id = $member_id AND status = 'Active' ORDER BY FIELD(class_name, 'Little Angels', 'Champions', 'Battalion', 'Conquerors')");
if ($ss_teacher_q) {
    while ($tc = $ss_teacher_q->fetch_assoc()) {
        $ss_teacher_classes[] = $tc['class_name'];
    }
}
$is_ss_class_teacher = !empty($ss_teacher_classes);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_ss_class_message'])) {
    $class_name = trim($_POST['class_name'] ?? '');
    $message_raw = trim($_POST['class_message'] ?? '');
    if (!in_array($class_name, $ss_teacher_classes, true)) {
        header("Location: member_dashboard.php?tab=manage_sunday_classes&error=" . urlencode("You can send messages only to classes assigned to you."));
        exit();
    }
    if ($message_raw === '') {
        header("Location: member_dashboard.php?tab=manage_sunday_classes&error=" . urlencode("Please write a message before sending."));
        exit();
    }
    $class_safe = $conn->real_escape_string($class_name);
    $message_safe = $conn->real_escape_string($message_raw);
    $conn->query("INSERT INTO sunday_school_class_messages (class_name, teacher_id, message, created_at) VALUES ('$class_safe', $member_id, '$message_safe', NOW())");
    $teacher_name = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
    $preview = substr($message_raw, 0, 90);
    $notice = $conn->real_escape_string("Sunday School teacher message for $class_name from $teacher_name: $preview");
    $students = $conn->query("SELECT id FROM members WHERE department = 'Sunday School' AND sunday_school_class = '$class_safe' AND is_approved = 1");
    if ($students) {
        while ($student = $students->fetch_assoc()) {
            $student_id = (int)$student['id'];
            $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($student_id, 'member', '$notice', 0, NOW())");
        }
    }
    header("Location: member_dashboard.php?tab=manage_sunday_classes&success=" . urlencode("Message sent to $class_name members."));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_ss_attendance'])) {
    $class_name = trim($_POST['attendance_class_name'] ?? '');
    $attendance_date = trim($_POST['attendance_date'] ?? date('Y-m-d'));
    $notes_raw = trim($_POST['attendance_notes'] ?? '');
    $present_map = $_POST['attendance_present'] ?? [];

    if (!in_array($class_name, $ss_teacher_classes, true)) {
        header("Location: member_dashboard.php?tab=manage_sunday_classes&error=" . urlencode("You can take attendance only for classes assigned to you."));
        exit();
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendance_date)) {
        header("Location: member_dashboard.php?tab=manage_sunday_classes&error=" . urlencode("Please select a valid attendance date."));
        exit();
    }

    $class_safe = $conn->real_escape_string($class_name);
    $date_safe = $conn->real_escape_string($attendance_date);
    $notes_safe = $conn->real_escape_string($notes_raw);
    $conn->query("INSERT INTO sunday_school_attendance_registers (class_name, teacher_id, attendance_date, notes, created_at) VALUES ('$class_safe', $member_id, '$date_safe', '$notes_safe', NOW())");
    $register_id = (int)$conn->insert_id;

    $class_members = $conn->query("SELECT id FROM members WHERE department = 'Sunday School' AND sunday_school_class = '$class_safe' AND is_approved = 1");
    $present_count = 0;
    $total_count = 0;
    if ($class_members && $register_id > 0) {
        while ($class_member = $class_members->fetch_assoc()) {
            $student_id = (int)$class_member['id'];
            $status = isset($present_map[$student_id]) ? 'Present' : 'Absent';
            if ($status === 'Present') $present_count++;
            $total_count++;
            $status_safe = $conn->real_escape_string($status);
            $conn->query("INSERT INTO sunday_school_attendance_entries (register_id, member_id, status, created_at) VALUES ($register_id, $student_id, '$status_safe', NOW())");
        }
    }

    $teacher_name = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
    $notice = $conn->real_escape_string("Sunday School attendance register submitted for $class_name by $teacher_name on $attendance_date. Present: $present_count of $total_count. Open Manage Sunday School.");
    $pastors = $conn->query("SELECT id FROM pastors");
    if ($pastors) {
        while ($pastor = $pastors->fetch_assoc()) {
            $pid = (int)$pastor['id'];
            $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($pid, 'pastor', '$notice', 0, NOW())");
        }
    }
    $admins = $conn->query("SELECT id FROM admins");
    if ($admins) {
        while ($admin = $admins->fetch_assoc()) {
            $aid = (int)$admin['id'];
            $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($aid, 'admin', '$notice', 0, NOW())");
        }
    }

    header("Location: member_dashboard.php?tab=manage_sunday_classes&success=" . urlencode("Attendance register saved and sent to pastor/admin."));
    exit();
}

$financial_workflow_columns = [
    'is_sent_to_chair' => "ALTER TABLE financial_records ADD COLUMN is_sent_to_chair TINYINT(1) NOT NULL DEFAULT 0",
    'edit_reason' => "ALTER TABLE financial_records ADD COLUMN edit_reason VARCHAR(255) NULL",
    'is_chair_confirmed' => "ALTER TABLE financial_records ADD COLUMN is_chair_confirmed TINYINT(1) NOT NULL DEFAULT 0 AFTER is_sent_to_chair",
    'chair_confirmed_by' => "ALTER TABLE financial_records ADD COLUMN chair_confirmed_by INT(6) UNSIGNED NULL AFTER is_chair_confirmed",
    'chair_confirmed_at' => "ALTER TABLE financial_records ADD COLUMN chair_confirmed_at DATETIME NULL AFTER chair_confirmed_by",
    'chair_disbursement_note' => "ALTER TABLE financial_records ADD COLUMN chair_disbursement_note TEXT NULL AFTER chair_confirmed_at"
];
foreach ($financial_workflow_columns as $financial_workflow_column => $financial_workflow_sql) {
    $financial_workflow_exists = $conn->query("SHOW COLUMNS FROM financial_records LIKE '$financial_workflow_column'");
    if ($financial_workflow_exists && $financial_workflow_exists->num_rows == 0) {
        $conn->query($financial_workflow_sql);
    }
}

$daily_expiry_col = $conn->query("SHOW COLUMNS FROM daily_messages LIKE 'expires_at'");
if ($daily_expiry_col && $daily_expiry_col->num_rows == 0) {
    $conn->query("ALTER TABLE daily_messages ADD COLUMN expires_at DATETIME NULL AFTER video_file");
}
$highlight_expiry_col = $conn->query("SHOW COLUMNS FROM church_highlights LIKE 'expires_at'");
if ($highlight_expiry_col && $highlight_expiry_col->num_rows == 0) {
    $conn->query("ALTER TABLE church_highlights ADD COLUMN expires_at DATETIME NULL AFTER caption");
}
$conn->query("
    CREATE TABLE IF NOT EXISTS secretary_announcement_queries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        secretary_id INT NOT NULL,
        department VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");
$conn->query("
    CREATE TABLE IF NOT EXISTS secretary_direct_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        query_id INT NULL,
        sender_id INT NOT NULL,
        recipient_id INT NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");
$direct_query_col = $conn->query("SHOW COLUMNS FROM secretary_direct_messages LIKE 'query_id'");
if ($direct_query_col && $direct_query_col->num_rows == 0) {
    $conn->query("ALTER TABLE secretary_direct_messages ADD COLUMN query_id INT NULL AFTER id");
}
$dept_announcement_image_col = $conn->query("SHOW COLUMNS FROM department_announcements LIKE 'image_path'");
if ($dept_announcement_image_col && $dept_announcement_image_col->num_rows == 0) {
    $conn->query("ALTER TABLE department_announcements ADD COLUMN image_path VARCHAR(255) NULL AFTER message");
}
$elder_announcement_image_col = $conn->query("SHOW COLUMNS FROM elder_announcements LIKE 'image_path'");
if ($elder_announcement_image_col && $elder_announcement_image_col->num_rows == 0) {
    $conn->query("ALTER TABLE elder_announcements ADD COLUMN image_path VARCHAR(255) NULL AFTER message");
}
$usher_announcement_image_col = $conn->query("SHOW COLUMNS FROM usher_announcements LIKE 'image_path'");
if ($usher_announcement_image_col && $usher_announcement_image_col->num_rows == 0) {
    $conn->query("ALTER TABLE usher_announcements ADD COLUMN image_path VARCHAR(255) NULL AFTER message");
}
$building_post_image_col = $conn->query("SHOW COLUMNS FROM building_posts LIKE 'image_path'");
if ($building_post_image_col && $building_post_image_col->num_rows == 0) {
    $conn->query("ALTER TABLE building_posts ADD COLUMN image_path VARCHAR(255) NULL AFTER post_content");
}
$conn->query("
    CREATE TABLE IF NOT EXISTS choir_song_plans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department VARCHAR(100) NOT NULL,
        choir_leader_id INT NOT NULL,
        song_type VARCHAR(120) NOT NULL,
        song_title VARCHAR(255) NOT NULL,
        song_date DATE NOT NULL,
        song_time TIME NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");
$conn->query("
    CREATE TABLE IF NOT EXISTS choir_announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department VARCHAR(100) NOT NULL,
        choir_leader_id INT NOT NULL,
        message TEXT NOT NULL,
        image_path VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");
$conn->query("
    CREATE TABLE IF NOT EXISTS usher_announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usher_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");
$conn->query("
    CREATE TABLE IF NOT EXISTS usher_chat (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

function save_announcement_image($field_names) {
    foreach ((array)$field_names as $field_name) {
        if (!empty($_FILES[$field_name]['name']) && $_FILES[$field_name]['error'] === UPLOAD_ERR_OK) {
            break;
        }
        $field_name = null;
    }

    if (!$field_name) {
        return null;
    }

    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $tmp_path = $_FILES[$field_name]['tmp_name'];
    $mime_type = function_exists('mime_content_type') ? mime_content_type($tmp_path) : ($_FILES[$field_name]['type'] ?? '');
    if (!isset($allowed_types[$mime_type])) {
        return null;
    }

    $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'announcements';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $filename = 'announcement_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed_types[$mime_type];
    $target_path = $upload_dir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmp_path, $target_path)) {
        return null;
    }

    return 'uploads/announcements/' . $filename;
}

function render_announcement_image($image_path) {
    if (empty($image_path)) {
        return '';
    }
    $safe_path = htmlspecialchars($image_path);
    return '<a href="' . $safe_path . '" target="_blank" style="display:block;margin-top:12px;">'
        . '<img src="' . $safe_path . '" alt="Announcement image" style="width:100%;max-height:360px;object-fit:cover;border-radius:10px;border:1px solid var(--border-color);">'
        . '</a>';
}

// Notifications
$unread_notifs = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $member_id AND user_type = 'member' AND is_read = 0")->fetch_assoc()['count'];
$notifications = $conn->query("SELECT * FROM notifications WHERE user_id = $member_id AND user_type = 'member' ORDER BY created_at DESC LIMIT 20");

// Member Content
$daily_message = $conn->query("SELECT dm.*, p.first_name, p.last_name FROM daily_messages dm JOIN pastors p ON dm.pastor_id = p.id WHERE dm.expires_at IS NULL OR dm.expires_at > NOW() ORDER BY dm.created_at DESC LIMIT 1")->fetch_assoc();
$highlights = $conn->query("SELECT ch.*, p.first_name, p.last_name FROM church_highlights ch JOIN pastors p ON ch.pastor_id = p.id WHERE ch.expires_at IS NULL OR ch.expires_at > NOW() ORDER BY ch.created_at DESC");

// Elder Announcements
$all_elder_announcements = $conn->query("
    SELECT ea.*, m.first_name, m.last_name
    FROM elder_announcements ea
    JOIN members m ON ea.elder_id = m.id
    ORDER BY ea.created_at DESC
");

// Usher Announcements
$all_usher_announcements = $conn->query("
    SELECT ua.*, m.first_name, m.last_name
    FROM usher_announcements ua
    JOIN members m ON ua.usher_id = m.id
    ORDER BY ua.created_at DESC
");

// Fetch department announcements if member is in a department
$dept_announcements = null;
if (!empty($member['department']) && $member['department'] !== 'None') {
    $my_dept = $conn->real_escape_string($member['department']);
    $dept_announcements = $conn->query("
        SELECT da.*, m.first_name, m.last_name, m.church_role
        FROM department_announcements da
        JOIN members m ON da.secretary_id = m.id
        WHERE da.department = '$my_dept'
        ORDER BY da.created_at DESC
    ");
}
$general_church_announcements = $conn->query("
    SELECT da.*, m.first_name, m.last_name, m.church_role
    FROM department_announcements da
    JOIN members m ON da.secretary_id = m.id
    WHERE da.department = 'General Church'
    ORDER BY da.created_at DESC
");

// Leadership Mapping (Case-insensitive)
// Both chairman and vice roles map to the same department
$leader_departments = [
    'youth chairperson' => 'Youths',
    'youth chairman' => 'Youths',
    'youth chairlady' => 'Youths',
    'vice youth chairperson' => 'Youths',
    'vice youth chairman' => 'Youths',
    'vice youth chairlady' => 'Youths',
    'women chairlady' => 'Womens Ministry',
    'women chairperson' => 'Womens Ministry',
    'women chairman' => 'Womens Ministry',
    'vice women chairlady' => 'Womens Ministry',
    'vice women chairperson' => 'Womens Ministry',
    'vice women chairman' => 'Womens Ministry',
    'elder chairman' => 'Elders',
    'elder chairperson' => 'Elders',
    'elder chairlady' => 'Elders',
    'vice elder chairman' => 'Elders',
    'vice elder chairperson' => 'Elders',
    'vice elder chairlady' => 'Elders',
    'sunday school patron' => 'Sunday School',
    'sunday school chairperson' => 'Sunday School',
    'sunday school chairman' => 'Sunday School',
    'sunday school chairlady' => 'Sunday School',
    'vice sunday school patron' => 'Sunday School',
    'vice sunday school chairperson' => 'Sunday School',
    'vice sunday school chairman' => 'Sunday School',
    'vice sunday school chairlady' => 'Sunday School'
];

$secretary_departments = [
    'youth secretary' => 'Youths',
    'vice youth secretary' => 'Youths',
    'graduands secretary' => 'Youths',
    'women secretary' => 'Womens Ministry',
    'vice women secretary' => 'Womens Ministry',
    'elder secretary' => 'Elders',
    'vice elder secretary' => 'Elders',
    'sunday school secretary' => 'Sunday School',
    'vice sunday school secretary' => 'Sunday School'
];

$treasurer_departments = [
    'treasurer' => 'General Church',
    'youth treasurer' => 'Youths',
    'women treasurer' => 'Womens Ministry',
    'elder treasurer' => 'Elders',
    'sunday school treasurer' => 'Sunday School',
    'building treasurer' => 'Building & Construction'
];

$is_leader = false;
$is_secretary = false;
$is_treasurer = false;
$is_organizing_secretary = false;
$is_discipline_master = false;
$is_graduands_secretary = false;
$is_prayer_coordinator = false;
$is_sport_secretary = false;
$is_choir_leader = false;
$is_head_usher = false;
$is_general_secretary = false;
$is_general_church_leader = false;
$is_secretary_council_member = false;
$is_youth_advisor = false;
$is_building_leader = false;
$is_building_chairperson = false;
$is_building_secretary = false;
$is_worship_leader = false;
$is_vice_worship_leader = false;
$is_building_treasurer = false;
$treasurer_depts = [];
$active_treasurer_dept = '';
$managed_dept = '';
$dept_key = '';
$raw_church_roles = array_filter(array_map('trim', explode(',', str_replace('&', ',', $member['church_role'] ?? ''))));
$member_roles_normalized = array_map('normalize_role_name', $raw_church_roles);

// Pre-determine if member is a youth advisor (order-independent)
foreach ($member_roles_normalized as $r) {
    if (is_youth_advisor_role($r)) {
        $is_youth_advisor = true;
    }
}

$leadership_managed_dept = ''; // dept set by chairman/secretary role — highest priority
$leadership_dept_key     = '';
$subsidiary_managed_dept = ''; // dept set by choir/prayer/sport/organizing roles — lower priority
$subsidiary_dept_key     = '';

// Track all departments where member serves as a subsidiary leader (allows multi-dept service)
$discipline_master_depts    = [];
$choir_leader_depts         = [];
$prayer_coordinator_depts   = [];
$organizing_secretary_depts = [];
$sport_secretary_depts      = [];

// Track Sunday School cross-church leadership roles held by member
// (a member stays in their home dept but can also serve Sunday School)
$ss_leader_depts      = [];   // 'Sunday School' when patron/vice-patron also held
$ss_secretary_depts   = [];   // 'Sunday School' when SS-secretary/vice also held
$ss_treasurer_depts   = [];   // 'Sunday School' when SS-treasurer also held

foreach ($raw_church_roles as $raw_role) {
    $current_role = normalize_role_name($raw_role);
    if (empty($current_role)) continue;

    if (is_general_church_secretary_role($current_role)) {
        $is_general_secretary = true;
    }
    if (is_general_church_leader_role($current_role)) {
        $is_general_church_leader = true;
    }
    if (is_general_church_secretary_role($current_role) || is_department_secretary_role($current_role)) {
        $is_secretary_council_member = true;
    }
    if (is_youth_advisor_role($current_role)) {
        $is_youth_advisor = true;
    }

    // Extract any bracketed department suffix from the raw role, e.g. "(Sunday School)"
    $_role_suffix_dept = '';
    if (preg_match('/\((.+?)\)/', $raw_role, $_sfx_match)) {
        $_role_suffix_dept = trim($_sfx_match[1]);
    }
    $_is_ss_suffixed = (strtolower($_role_suffix_dept) === 'sunday school');

    if (array_key_exists($current_role, $leader_departments)) {
        $dept_of_role = $leader_departments[$current_role];
        if ($dept_of_role === 'Sunday School') {
            // Cross-church patron role — track separately, don't override home leadership dept
            $is_leader = true;
            if (!in_array('Sunday School', $ss_leader_depts)) $ss_leader_depts[] = 'Sunday School';
        } else {
            $is_leader = true;
            $leadership_managed_dept = $dept_of_role;
            $leadership_dept_key     = strtolower(str_replace(' ', '_', $dept_of_role));
        }
    } elseif ($_is_ss_suffixed && in_array($current_role, ['sunday school patron', 'vice sunday school patron'], true)) {
        // Stored as "Sunday School Patron (Sunday School)" — cross-church patron
        $is_leader = true;
        if (!in_array('Sunday School', $ss_leader_depts)) $ss_leader_depts[] = 'Sunday School';
    } elseif (array_key_exists($current_role, $secretary_departments)) {
        $dept_of_role = $secretary_departments[$current_role];
        if ($dept_of_role === 'Sunday School') {
            // Cross-church secretary role — track separately, don't override home secretary dept
            $is_secretary = true;
            $is_secretary_council_member = true;
            if (!in_array('Sunday School', $ss_secretary_depts)) $ss_secretary_depts[] = 'Sunday School';
        } else {
            $is_secretary = true;
            // Only set leadership_managed_dept from secretary if not already set by a chairman role
            if (empty($leadership_managed_dept)) {
                $leadership_managed_dept = $dept_of_role;
                $leadership_dept_key     = strtolower(str_replace(' ', '_', $dept_of_role));
            }
        }
    } elseif ($_is_ss_suffixed && in_array($current_role, ['sunday school secretary', 'vice sunday school secretary'], true)) {
        // Stored as "Sunday School Secretary (Sunday School)" — cross-church secretary
        $is_secretary = true;
        $is_secretary_council_member = true;
        if (!in_array('Sunday School', $ss_secretary_depts)) $ss_secretary_depts[] = 'Sunday School';
    }
    if (array_key_exists($current_role, $treasurer_departments)) {
        $is_treasurer = true;
        $t_dept = $treasurer_departments[$current_role];
        if ($t_dept === 'Sunday School') {
            // Cross-church treasurer — track separately
            if (!in_array('Sunday School', $ss_treasurer_depts)) $ss_treasurer_depts[] = 'Sunday School';
            if (!in_array('Sunday School', $treasurer_depts)) $treasurer_depts[] = 'Sunday School';
        } else {
            if (!in_array($t_dept, $treasurer_depts)) $treasurer_depts[] = $t_dept;
        }
    } elseif ($_is_ss_suffixed && $current_role === 'sunday school treasurer') {
        $is_treasurer = true;
        if (!in_array('Sunday School', $ss_treasurer_depts)) $ss_treasurer_depts[] = 'Sunday School';
        if (!in_array('Sunday School', $treasurer_depts)) $treasurer_depts[] = 'Sunday School';
    }
    // Subsidiary roles: write to $subsidiary_managed_dept only — never override leadership dept
    // Extract department context from bracketed suffix e.g. "Discipline Master (Youth)" → Youth
    $role_context_dept = '';
    if (preg_match('/\((.+?)\)/', $raw_role, $ctx_match)) {
        $role_context_dept = trim($ctx_match[1]);
    }
    if (empty($role_context_dept)) {
        $role_context_dept = $member['department'] ?? '';
    }

    if (in_array($current_role, ['organizing secretary (subsidiary)', 'organizing secretary'], true)) {
        $is_organizing_secretary = true;
        $is_secretary = true;
        if (!in_array($role_context_dept, $organizing_secretary_depts) && !empty($role_context_dept)) {
            $organizing_secretary_depts[] = $role_context_dept;
        }
        $subsidiary_managed_dept = $role_context_dept;
        $subsidiary_dept_key     = strtolower(str_replace(' ', '_', $subsidiary_managed_dept));
    }
    if (in_array($current_role, ['discipline master (subsidiary)', 'discipline master'], true)) {
        $is_discipline_master = true;
        if (!in_array($role_context_dept, $discipline_master_depts) && !empty($role_context_dept)) {
            $discipline_master_depts[] = $role_context_dept;
        }
    }
    if (in_array($current_role, ['graduands secretary (subsidiary)', 'graduands secretary'], true) && department_allows_graduation_and_sport($member['department'] ?? '')) {
        $is_graduands_secretary = true;
    }
    if (in_array($current_role, ['prayer coordinator (subsidiary)', 'prayer coordinator'], true)) {
        $is_prayer_coordinator = true;
        if (!in_array($role_context_dept, $prayer_coordinator_depts) && !empty($role_context_dept)) {
            $prayer_coordinator_depts[] = $role_context_dept;
        }
        $subsidiary_managed_dept = $role_context_dept;
        $subsidiary_dept_key     = strtolower(str_replace(' ', '_', $subsidiary_managed_dept));
    }
    if (in_array($current_role, ['sport secretary (subsidiary)', 'sports secretary (subsidiary)', 'sport secretary', 'sports secretary'], true) && department_allows_graduation_and_sport($member['department'] ?? '')) {
        $is_sport_secretary = true;
        if (!in_array($role_context_dept, $sport_secretary_depts) && !empty($role_context_dept)) {
            $sport_secretary_depts[] = $role_context_dept;
        }
        $subsidiary_managed_dept = $role_context_dept;
        $subsidiary_dept_key     = strtolower(str_replace(' ', '_', $subsidiary_managed_dept));
    }
    if (in_array($current_role, ['choir leader (subsidiary)', 'choir leader'], true)) {
        $is_choir_leader = true;
        if (!in_array($role_context_dept, $choir_leader_depts) && !empty($role_context_dept)) {
            $choir_leader_depts[] = $role_context_dept;
        }
        $subsidiary_managed_dept = $role_context_dept;
        $subsidiary_dept_key     = strtolower(str_replace(' ', '_', $subsidiary_managed_dept));
    }
    if ($current_role === 'head usher') {
        $is_head_usher = true;
    }
    if (is_building_leadership_role($current_role)) {
        $is_building_leader = true;
    }
    if (is_building_chairperson_role($current_role)) {
        $is_building_chairperson = true;
    }
    if (normalize_role_name($current_role) === 'worship leader') {
        $is_worship_leader = true;
    }
    if (normalize_role_name($current_role) === 'vice worship leader') {
        $is_vice_worship_leader = true;
    }
    if (normalize_role_name($current_role) === 'building secretary' || normalize_role_name($current_role) === 'vice building secretary') {
        $is_building_secretary = true;
    }
    if (normalize_role_name($current_role) === 'building treasurer') {
        $is_building_treasurer = true;
        $is_treasurer = true;
        if (!in_array('Building & Construction', $treasurer_depts)) {
            $treasurer_depts[] = 'Building & Construction';
        }
    }
}

// Global Dashboard Department Switcher Logic
$involved_departments = [];
if (!empty($member['department']) && $member['department'] !== 'None') {
    $involved_departments[] = $member['department'];
}
$involved_departments = array_merge($involved_departments, $ss_leader_depts, $ss_secretary_depts, $ss_treasurer_depts, $organizing_secretary_depts, $discipline_master_depts, $prayer_coordinator_depts, $sport_secretary_depts, $choir_leader_depts);
if (!empty($leadership_managed_dept)) {
    $involved_departments[] = $leadership_managed_dept;
}
$involved_departments = array_values(array_unique(array_filter($involved_departments)));

if (isset($_GET['switch_dashboard_dept'])) {
    $_SESSION['dashboard_dept'] = trim($_GET['switch_dashboard_dept']);
    header("Location: ?tab=" . urlencode($tab));
    exit();
}

$active_dashboard_dept = $_SESSION['dashboard_dept'] ?? ($involved_departments[0] ?? $member['department']);
if (!empty($involved_departments) && !in_array($active_dashboard_dept, $involved_departments)) {
    $active_dashboard_dept = $involved_departments[0];
}

// Override legacy managed_dept variables with the global context
$managed_dept = $active_dashboard_dept;
$dept_key     = strtolower(str_replace(' ', '_', $managed_dept));

if (!empty($treasurer_depts)) {
    $active_treasurer_dept = $_GET['treasurer_dept'] ?? (in_array($active_dashboard_dept, $treasurer_depts) ? $active_dashboard_dept : $treasurer_depts[0]);
}

// Context-aware role flags: each is true ONLY if the member holds that role FOR the active dashboard dept.
// These drive sidebar panel visibility so switching context hides irrelevant panels.
$ctx_is_leader = $is_leader && (
    (strtolower($active_dashboard_dept) === strtolower($leadership_managed_dept)) ||
    in_array($active_dashboard_dept, $ss_leader_depts)
);
$chairperson_roles_by_dept = [
    'Youths' => ['youth chairperson', 'youth chairman', 'youth chairlady', 'vice youth chairperson', 'vice youth chairman', 'vice youth chairlady'],
    'Womens Ministry' => ['women chairlady', 'women chairperson', 'women chairman', 'vice women chairlady', 'vice women chairperson', 'vice women chairman'],
    'Elders' => ['elder chairman', 'elder chairperson', 'elder chairlady', 'vice elder chairman', 'vice elder chairperson', 'vice elder chairlady'],
    'Sunday School' => ['sunday school patron', 'vice sunday school patron', 'sunday school chairperson', 'sunday school chairman', 'sunday school chairlady', 'vice sunday school chairperson', 'vice sunday school chairman', 'vice sunday school chairlady'],
    'Building & Construction' => ['building chairperson', 'building chairman', 'building chairlady', 'vice building chairperson', 'vice building chairman', 'vice building chairlady']
];
$ctx_is_chairperson = false;
foreach ($chairperson_roles_by_dept as $chair_dept => $chair_roles) {
    if (department_matches($active_dashboard_dept, $chair_dept) && !empty(array_intersect($member_roles_normalized, $chair_roles))) {
        $ctx_is_chairperson = true;
        break;
    }
}
$ctx_is_secretary = $is_secretary && (
    (strtolower($active_dashboard_dept) === strtolower($leadership_managed_dept)) ||
    in_array($active_dashboard_dept, $ss_secretary_depts)
);
$ctx_is_treasurer = $is_treasurer && in_array($active_dashboard_dept, $treasurer_depts);
$ctx_is_organizing_secretary = $is_organizing_secretary && in_array($active_dashboard_dept, $organizing_secretary_depts);
$ctx_is_discipline_master     = $is_discipline_master    && in_array($active_dashboard_dept, $discipline_master_depts);
$ctx_is_choir_leader          = $is_choir_leader         && in_array($active_dashboard_dept, $choir_leader_depts);
$ctx_is_prayer_coordinator    = $is_prayer_coordinator   && in_array($active_dashboard_dept, $prayer_coordinator_depts);
$ctx_is_sport_secretary       = $is_sport_secretary      && in_array($active_dashboard_dept, $sport_secretary_depts);
// Graduation secretary is department-specific — only show if active dept supports it
$ctx_is_graduands_secretary   = $is_graduands_secretary  && department_allows_graduation_and_sport($active_dashboard_dept);

$general_leader_roles = ['general church secretary', 'vice church secretary', 'treasurer', 'senior church elder'];
$general_leader_partners = [];
if ($is_general_church_leader) {
    $role_conditions = [];
    foreach ($general_leader_roles as $r) {
        $role_conditions[] = "LOWER(church_role) LIKE '%" . $conn->real_escape_string($r) . "%'";
    }
    $role_sql = implode(' OR ', $role_conditions);
    $partners_result = $conn->query("SELECT id, first_name, last_name, church_role FROM members WHERE ($role_sql) AND id != $member_id AND is_approved = 1 ORDER BY first_name ASC");
    if ($partners_result) {
        while ($p = $partners_result->fetch_assoc()) {
            $general_leader_partners[] = $p;
        }
    }
}

$secretary_council_roles = array_merge(['general church secretary', 'vice church secretary'], array_keys($secretary_departments));
$secretary_council_partners = [];
if ($is_secretary_council_member) {
    $role_conditions = [];
    foreach ($secretary_council_roles as $r) {
        $role_conditions[] = "LOWER(church_role) LIKE '%" . $conn->real_escape_string($r) . "%'";
    }
    $role_sql = implode(' OR ', $role_conditions);
    $partners_result = $conn->query("SELECT id, first_name, last_name, church_role, department FROM members WHERE ($role_sql) AND id != $member_id AND is_approved = 1 ORDER BY department ASC, first_name ASC");
    if ($partners_result) {
        while ($p = $partners_result->fetch_assoc()) {
            $secretary_council_partners[] = $p;
        }
    }
}

// Find ALL other leaders in the same department for chat (chairmen + secretaries + treasurers)
$chat_partners = [];
if ($is_leader || $is_secretary || $is_prayer_coordinator || $is_sport_secretary || $is_choir_leader || $is_youth_advisor) {
    // Combine both maps to find ALL leadership roles in the same department
    $all_dept_roles = [];
    foreach ($leader_departments as $role => $dept) {
        if ($dept === $managed_dept) $all_dept_roles[] = $role;
    }
    foreach ($secretary_departments as $role => $dept) {
        if ($dept === $managed_dept) $all_dept_roles[] = $role;
    }
    // Also include special roles: organizing secretary, discipline master, graduands secretary, prayer coordinator
    $special_roles = ['organizing secretary (subsidiary)', 'discipline master (subsidiary)', 'graduands secretary (subsidiary)', 'prayer coordinator (subsidiary)', 'sport secretary (subsidiary)', 'sports secretary (subsidiary)', 'choir leader (subsidiary)'];
    if ($managed_dept === 'Youths') {
        $special_roles[] = 'mama youth';
        $special_roles[] = 'baba youth';
    }
    foreach ($special_roles as $sr) {
        if (!in_array($sr, $all_dept_roles)) $all_dept_roles[] = $sr;
    }
    // Build SQL to find all other leaders in same department
    $role_conditions = [];
    foreach ($all_dept_roles as $r) {
        $role_conditions[] = "LOWER(TRIM(church_role)) LIKE '%" . $conn->real_escape_string($r) . "%'";
    }
    $esc_managed_dept = $conn->real_escape_string($managed_dept);
    $role_sql = implode(' OR ', $role_conditions);
    $dept_condition = $managed_dept === 'Youths'
        ? "(department='$esc_managed_dept' OR LOWER(TRIM(church_role)) LIKE '%mama youth%' OR LOWER(TRIM(church_role)) LIKE '%baba youth%')"
        : "department='$esc_managed_dept'";
    $partners_result = $conn->query("SELECT id, first_name, last_name, church_role FROM members WHERE $dept_condition AND ($role_sql) AND id != $member_id AND is_approved = 1");
    if ($partners_result && $partners_result->num_rows > 0) {
        while ($p = $partners_result->fetch_assoc()) {
            $chat_partners[] = $p;
        }
    }
    // Keep backward compat
    $chat_partner = !empty($chat_partners) ? $chat_partners[0] : null;
}

// ─── Desired Roles / Church Village onboarding handler ───────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_church_village'])) {
    $cv  = $conn->real_escape_string(trim($_POST['church_village'] ?? ''));
    $drp = $conn->real_escape_string(trim($_POST['desired_role_pref'] ?? ''));
    $valid_villages = ['Akoritho', 'Philadelphia', 'Bethsaida'];
    if (!in_array($cv, $valid_villages)) {
        header("Location: member_dashboard.php?tab=desired_roles&error=Please select a valid church village");
        exit();
    }
    $valid_roles = ['Worshipper', 'Church Cleaner', 'Church Cooker', ''];
    if (!in_array($drp, $valid_roles)) { $drp = ''; }
    $drp_sql = $drp ? "'$drp'" : "NULL";

    if (empty($member['church_village'])) {
        // Initial setup
        $conn->query("UPDATE members SET church_village = '$cv', desired_role_pref = $drp_sql WHERE id = $member_id");
        // Reload member
        $member = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        // Notify worship leaders/pastor/admin if new worshipper
        if ($drp === 'Worshipper') {
            $wl_name  = $conn->real_escape_string($member['first_name'] . ' ' . $member['last_name']);
            $wl_notif = $conn->real_escape_string("New worshipper $wl_name from $cv village has joined the worship team.");
            $wleaders = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND LOWER(TRIM(church_role)) IN ('worship leader','vice worship leader')");
            if ($wleaders) { while ($wl = $wleaders->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id,user_type,message) VALUES ({$wl['id']},'member','$wl_notif')"); } }
            $pastors_q = $conn->query("SELECT id FROM pastors WHERE is_approved = 1");
            if ($pastors_q) { while ($p = $pastors_q->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id,user_type,message) VALUES ({$p['id']},'pastor','$wl_notif')"); } }
            $admins_q = $conn->query("SELECT id FROM admins");
            if ($admins_q) { while ($a = $admins_q->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id,user_type,message) VALUES ({$a['id']},'admin','$wl_notif')"); } }
        }

        // Notify Village Leader, Pastor, Admin about new village member
        $new_mem_name = $conn->real_escape_string($member['first_name'] . ' ' . $member['last_name']);
        $vn_notif = $conn->real_escape_string("A new member has been added in $cv church village.");
        $pastors_q = $conn->query("SELECT id FROM pastors WHERE is_approved = 1");
        if ($pastors_q) { while ($p = $pastors_q->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id,user_type,message) VALUES ({$p['id']},'pastor','$vn_notif')"); } }
        $admins_q = $conn->query("SELECT id FROM admins");
        if ($admins_q) { while ($a = $admins_q->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id,user_type,message) VALUES ({$a['id']},'admin','$vn_notif')"); } }
        $vls_q = $conn->query("SELECT id FROM members WHERE is_village_leader = 1 AND church_village = '$cv'");
        if ($vls_q) { while ($vl = $vls_q->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id,user_type,message) VALUES ({$vl['id']},'member','$vn_notif')"); } }

        header("Location: member_dashboard.php?tab=desired_roles&success=Your church village has been saved! All tabs are now unlocked.");
        exit();
    } else {
        // Update request - goes to pending
        $conn->query("UPDATE members SET pending_church_village = '$cv', pending_desired_role_pref = $drp_sql WHERE id = $member_id");
        $update_msg = $conn->real_escape_string("Member " . $member['first_name'] . " " . $member['last_name'] . " requested to update their church village to $cv.");
        $pastors_q = $conn->query("SELECT id FROM pastors WHERE is_approved = 1");
        if ($pastors_q) { while ($p = $pastors_q->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id,user_type,message) VALUES ({$p['id']},'pastor','$update_msg')"); } }
        header("Location: member_dashboard.php?tab=desired_roles&success=Your update request has been sent to the Pastor for approval.");
        exit();
    }
}
$has_church_village = !empty($member['church_village']);

// Handle sending leadership chat message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_leader_msg']) && ($is_leader || $is_secretary || $is_prayer_coordinator || $is_sport_secretary || $is_choir_leader || $is_youth_advisor) && !empty($dept_key)) {
    $msg = $conn->real_escape_string(trim($_POST['leader_message']));
    if (!empty($msg)) {
        $conn->query("INSERT INTO leader_messages (dept_key, sender_id, message) VALUES ('$dept_key', $member_id, '$msg')");

        $sender_name = $conn->real_escape_string($member['first_name'] . ' ' . $member['last_name']);
        $notif_msg = $conn->real_escape_string("New message in " . $managed_dept . " Leaders Chat from $sender_name");

        // Notify chat partners
        $esc_managed_dept = $conn->real_escape_string($managed_dept);
        $role_conditions = [];
        $all_dept_roles = array_merge(array_keys(array_filter($leader_departments, fn($d) => $d === $managed_dept)), array_keys(array_filter($secretary_departments, fn($d) => $d === $managed_dept)), ['prayer coordinator (subsidiary)', 'sport secretary (subsidiary)', 'sports secretary (subsidiary)', 'choir leader (subsidiary)', 'organizing secretary (subsidiary)', 'mama youth', 'baba youth']);
        foreach ($all_dept_roles as $r) {
            $role_conditions[] = "LOWER(TRIM(church_role)) LIKE '%" . $conn->real_escape_string($r) . "%'";
        }
        $role_sql = implode(' OR ', $role_conditions);
        $dept_condition = $managed_dept === 'Youths'
            ? "(department='$esc_managed_dept' OR LOWER(TRIM(church_role)) LIKE '%mama youth%' OR LOWER(TRIM(church_role)) LIKE '%baba youth%')"
            : "department='$esc_managed_dept'";

        $partners = $conn->query("SELECT id FROM members WHERE $dept_condition AND ($role_sql) AND id != $member_id AND is_approved = 1");
        if ($partners) {
            while ($p = $partners->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$p['id']}, 'member', '$notif_msg')");
            }
        }
        header("Location: member_dashboard.php?tab=leader_chat&sent=1");
        exit();
    }
}

// Worship Leadership Chat & Task Allocation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_worship_chat']) && ($is_worship_leader || $is_vice_worship_leader)) {
    $msg = $conn->real_escape_string(trim($_POST['chat_message']));
    if (!empty($msg)) {
        $conn->query("INSERT INTO worship_leadership_chat (sender_id, message) VALUES ($member_id, '$msg')");
        // Notification for the other worship leader
        $worship_leaders = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND LOWER(TRIM(church_role)) IN ('worship leader', 'vice worship leader') AND id != $member_id");
        $sender_name = $conn->real_escape_string($member['first_name'] . ' ' . $member['last_name']);
        $notif_msg = "Worship Leader $sender_name sent a message in Worship Leadership Chat";
        while ($wl = $worship_leaders->fetch_assoc()) {
            $lid = $wl['id'];
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($lid, 'member', '$notif_msg')");
        }
        // Notification for pastor
        $pastors = $conn->query("SELECT id FROM pastors WHERE is_approved = 1");
        $notif_msg_pastor = "Worship Leader $sender_name sent a message in Worship Leadership Chat";
        while ($p = $pastors->fetch_assoc()) {
            $pid = $p['id'];
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($pid, 'pastor', '$notif_msg_pastor')");
        }
        header("Location: member_dashboard.php?tab=worship_leadership_chat&success=Message sent");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_worship_task']) && ($is_worship_leader || $is_vice_worship_leader)) {
    $target_member = intval($_POST['worshipper_id']);
    $task_name = $conn->real_escape_string(trim($_POST['task_name']));
    if (!empty($task_name) && $target_member > 0) {
        $conn->query("INSERT INTO worship_tasks (member_id, task_name, assigned_by) VALUES ($target_member, '$task_name', $member_id)");
        header("Location: member_dashboard.php?tab=manage_worshippers&success=Task assigned");
        exit();
    }
}

// Worshipper marks own task as Done
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_task_done'])) {
    $task_id = intval($_POST['task_id']);
    // Only allow the assigned member to mark it done
    $conn->query("UPDATE worship_tasks SET status='Done' WHERE id=$task_id AND member_id=$member_id");
    header("Location: member_dashboard.php?tab=my_worship_tasks&success=Task marked as done");
    exit();
}

// Worship Announcement: Post to worshippers
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_worship_announcement']) && ($is_worship_leader || $is_vice_worship_leader)) {
    $ann_msg = $conn->real_escape_string(trim($_POST['announcement_message']));
    $image_path = null;
    if (!empty($_FILES['announcement_image']['name'])) {
        $upload_dir = __DIR__ . '/uploads/announcements/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['announcement_image']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg','jpeg','png','gif','webp'];
        if (in_array($ext, $allowed_exts) && $_FILES['announcement_image']['size'] < 5242880) {
            $filename = 'worship_ann_' . $member_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['announcement_image']['tmp_name'], $upload_dir . $filename)) {
                $image_path = $conn->real_escape_string('uploads/announcements/' . $filename);
            }
        }
    }
    if (!empty($ann_msg)) {
        $img_sql = $image_path ? "'$image_path'" : "NULL";
        $conn->query("INSERT INTO worship_announcements (leader_id, message, image_path) VALUES ($member_id, '$ann_msg', $img_sql)");
        // Notify all worshippers in the department
        $sender_name = $conn->real_escape_string($member['first_name'] . ' ' . $member['last_name']);
        $notif_msg = $conn->real_escape_string("Worship Leader $sender_name posted an announcement to worshippers");
        $w_members = $conn->query("SELECT id FROM members WHERE is_approved=1 AND LOWER(department) LIKE '%worship%' AND id != $member_id");
        while ($wm = $w_members->fetch_assoc()) {
            $wid = $wm['id'];
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($wid, 'member', '$notif_msg')");
        }
        header("Location: member_dashboard.php?tab=manage_worshippers&success=Announcement posted to worshippers");
        exit();
    }
}

// Worship Announcement Query: Send to General Church Secretary
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['query_worship_announcement']) && ($is_worship_leader || $is_vice_worship_leader)) {
    $query_msg = $conn->real_escape_string(trim($_POST['query_message']));
    $image_path = null;
    if (!empty($_FILES['query_image']['name'])) {
        $upload_dir = __DIR__ . '/uploads/announcements/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['query_image']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg','jpeg','png','gif','webp'];
        if (in_array($ext, $allowed_exts) && $_FILES['query_image']['size'] < 5242880) {
            $filename = 'worship_query_' . $member_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['query_image']['tmp_name'], $upload_dir . $filename)) {
                $image_path = $conn->real_escape_string('uploads/announcements/' . $filename);
            }
        }
    }
    if (!empty($query_msg)) {
        $dept = $conn->real_escape_string($member['department'] ?? 'Worship Ministry');
        $conn->query("INSERT INTO secretary_announcement_queries (secretary_id, department, message) VALUES ($member_id, '$dept', '$query_msg')");
        // Notify general church secretaries
        $sender_name = $conn->real_escape_string($member['first_name'] . ' ' . $member['last_name']);
        $notif = $conn->real_escape_string("Worship Leader $sender_name submitted an announcement query to the General Church Secretary");
        $secs = $conn->query("SELECT id FROM members WHERE is_approved=1 AND LOWER(TRIM(church_role)) IN ('general church secretary','vice church secretary')");
        while ($s = $secs->fetch_assoc()) {
            $sid = $s['id'];
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($sid, 'member', '$notif')");
        }
        // Notify pastors too
        $pastors_n = $conn->query("SELECT id FROM pastors WHERE is_approved=1");
        while ($pn = $pastors_n->fetch_assoc()) {
            $pid = $pn['id'];
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($pid, 'pastor', '$notif')");
        }
        // Notify admins too
        $admins_n = $conn->query("SELECT id FROM admins");
        while ($an = $admins_n->fetch_assoc()) {
            $aid = $an['id'];
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($aid, 'admin', '$notif')");
        }
        header("Location: member_dashboard.php?tab=manage_worshippers&success=Query submitted to General Church Secretary");
        exit();
    }
}

// Building Leadership Chat: Send message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_building_chat']) && $is_building_leader) {
    $msg = $conn->real_escape_string(trim($_POST['chat_message']));
    if (!empty($msg)) {
        $conn->query("INSERT INTO building_leadership_chat (sender_id, sender_type, message) VALUES ($member_id, 'member', '$msg')");
        $sender_name = $conn->real_escape_string($member['first_name'] . ' ' . $member['last_name']);
        $notif_msg = $conn->real_escape_string("New Building Leadership Chat message from $sender_name");
        // Notify all other building leadership members
        $bld_leaders = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND id != $member_id AND (
            LOWER(TRIM(church_role)) LIKE '%building chairperson%' OR
            LOWER(TRIM(church_role)) LIKE '%building chairman%' OR
            LOWER(TRIM(church_role)) LIKE '%building secretary%' OR
            LOWER(TRIM(church_role)) LIKE '%building treasurer%'
        )");
        while ($bl = $bld_leaders->fetch_assoc()) {
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$bl['id']}, 'member', '$notif_msg')");
        }
        // Notify pastors
        $pastors_r = $conn->query("SELECT id FROM pastors WHERE is_approved = 1");
        while ($pr = $pastors_r->fetch_assoc()) {
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$pr['id']}, 'pastor', '$notif_msg')");
        }
        header("Location: member_dashboard.php?tab=building_leadership_chat&sent=1");
        exit();
    }
}

// Building Chairperson: Add progress update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_building_progress']) && $is_building_chairperson) {
    $date_sched = $conn->real_escape_string(trim($_POST['date_scheduled']));
    $materials  = $conn->real_escape_string(trim($_POST['materials_needed']));
    $progress   = $conn->real_escape_string(trim($_POST['progress_status']));
    if (!empty($date_sched) && !empty($materials) && !empty($progress)) {
        $conn->query("INSERT INTO building_progress (chairperson_id, date_scheduled, materials_needed, progress_status) VALUES ($member_id, '$date_sched', '$materials', '$progress')");
        $sender_name = $conn->real_escape_string($member['first_name'] . ' ' . $member['last_name']);
        $notif_msg = $conn->real_escape_string("Building construction update by $sender_name: $progress");
        // Notify all pastors and building leaders
        $pastors_r = $conn->query("SELECT id FROM pastors WHERE is_approved = 1");
        while ($pr = $pastors_r->fetch_assoc()) {
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$pr['id']}, 'pastor', '$notif_msg')");
        }
        $bld_leaders = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND id != $member_id AND (
            LOWER(TRIM(church_role)) LIKE '%building chairperson%' OR LOWER(TRIM(church_role)) LIKE '%building chairman%' OR
            LOWER(TRIM(church_role)) LIKE '%building secretary%' OR LOWER(TRIM(church_role)) LIKE '%building treasurer%'
        )");
        while ($bl = $bld_leaders->fetch_assoc()) {
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$bl['id']}, 'member', '$notif_msg')");
        }
    }
    header("Location: member_dashboard.php?tab=church_buildings&success=Progress updated");
    exit();
}

// Building Chairperson: Post a public building update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_building_post']) && $is_building_chairperson) {
    $content = $conn->real_escape_string(trim($_POST['post_content']));
    if (!empty($content)) {
        $image_path = save_announcement_image(['building_post_camera', 'building_post_upload']);
        $image_sql = $image_path ? "'" . $conn->real_escape_string($image_path) . "'" : "NULL";
        $conn->query("INSERT INTO building_posts (author_id, post_content, image_path) VALUES ($member_id, '$content', $image_sql)");
        $sender_name = $conn->real_escape_string($member['first_name'] . ' ' . $member['last_name']);
        $notif_msg = $conn->real_escape_string("New Church Building update posted by $sender_name");
        // Notify all members and pastors
        $all_members = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND id != $member_id");
        while ($am = $all_members->fetch_assoc()) {
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$am['id']}, 'member', '$notif_msg')");
        }
        $pastors_r = $conn->query("SELECT id FROM pastors WHERE is_approved = 1");
        while ($pr = $pastors_r->fetch_assoc()) {
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$pr['id']}, 'pastor', '$notif_msg')");
        }
    }
    header("Location: member_dashboard.php?tab=church_buildings&success=Update posted");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_usher_announcement']) && $is_head_usher) {
    $title = $conn->real_escape_string(trim($_POST['usher_title']));
    $msg   = $conn->real_escape_string(trim($_POST['usher_message']));

    $image_path = null;
    if (isset($_FILES['usher_image']) && $_FILES['usher_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'announcements';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', basename($_FILES['usher_image']['name']));
        $target_file = $upload_dir . DIRECTORY_SEPARATOR . $filename;
        if (move_uploaded_file($_FILES['usher_image']['tmp_name'], $target_file)) {
            $image_path = $conn->real_escape_string('uploads/announcements/' . $filename);
        }
    }

    if (!empty($title) && !empty($msg)) {
        $img_col = $image_path ? ", image_path" : "";
        $img_val = $image_path ? ", '$image_path'" : "";
        $conn->query("INSERT INTO usher_announcements (usher_id, title, message$img_col) VALUES ($member_id, '$title', '$msg'$img_val)");

        // Notify ALL approved members
        $all_members = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND id != $member_id");
        $usher_name = $conn->real_escape_string($member['first_name'] . ' ' . $member['last_name']);
        $notif_title = $conn->real_escape_string("Church Usher Announcement: $title");
        while ($am = $all_members->fetch_assoc()) {
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$am['id']}, 'member', '$notif_title')");
        }
        // Notify all pastors
        $pastors = $conn->query("SELECT id FROM pastors WHERE is_approved = 1");
        while ($p = $pastors->fetch_assoc()) {
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$p['id']}, 'pastor', '$notif_title')");
        }
        // Notify all admins
        $admins = $conn->query("SELECT id FROM admins");
        while ($a = $admins->fetch_assoc()) {
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$a['id']}, 'admin', '$notif_title')");
        }
    }
    header("Location: member_dashboard.php?tab=usher_panel&success=Announcement posted");
    exit();
}

// Head Usher: Message General Church Secretary
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_secretary_message']) && $is_head_usher) {
    $msg = $conn->real_escape_string(trim($_POST['secretary_message']));
    if (!empty($msg)) {
        $conn->query("INSERT INTO secretary_announcement_queries (secretary_id, department, message) VALUES ($member_id, 'Usher', '$msg')");

        $usher_name = $conn->real_escape_string($member['first_name'] . ' ' . $member['last_name']);
        $notif_msg = $conn->real_escape_string("New message to Secretary from Head Usher $usher_name");

        $gen_secs = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND LOWER(TRIM(church_role)) LIKE '%general church secretary%'");
        while ($s = $gen_secs->fetch_assoc()) {
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$s['id']}, 'member', '$notif_msg')");
        }
    }
    header("Location: member_dashboard.php?tab=usher_panel&success=Message sent to General Church Secretary");
    exit();
}

// Head Usher: Send usher chat message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_usher_chat']) && $is_head_usher) {
    $msg = $conn->real_escape_string(trim($_POST['usher_chat_message']));
    if (!empty($msg)) {
        $conn->query("INSERT INTO usher_chat (sender_id, message) VALUES ($member_id, '$msg')");

        // Notify other ushers, pastors, and admins
        $usher_name = $conn->real_escape_string($member['first_name'] . ' ' . $member['last_name']);
        $notif_msg = $conn->real_escape_string("New Usher Chat message from Head Usher $usher_name");

        $ushers = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND id != $member_id AND (LOWER(TRIM(church_role)) LIKE '%head usher%' OR LOWER(TRIM(church_role)) LIKE '%usher%')");
        while ($u = $ushers->fetch_assoc()) {
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$u['id']}, 'member', '$notif_msg')");
        }
        $pastors = $conn->query("SELECT id FROM pastors WHERE is_approved = 1");
        while ($p = $pastors->fetch_assoc()) {
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$p['id']}, 'pastor', '$notif_msg')");
        }
        $admins = $conn->query("SELECT id FROM admins");
        while ($a = $admins->fetch_assoc()) {
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$a['id']}, 'admin', '$notif_msg')");
        }
    }
    header("Location: member_dashboard.php?tab=usher_panel&sent=1");
    exit();
}

// Usher (non-head): Send usher chat message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_usher_chat']) && in_array('usher', $member_roles_normalized, true)) {
    $msg = $conn->real_escape_string(trim($_POST['usher_chat_message']));
    if (!empty($msg)) {
        $conn->query("INSERT INTO usher_chat (sender_id, message) VALUES ($member_id, '$msg')");

        // Notify other ushers, pastors, and admins
        $usher_name = $conn->real_escape_string($member['first_name'] . ' ' . $member['last_name']);
        $notif_msg = $conn->real_escape_string("New Usher Chat message from Usher $usher_name");

        $ushers = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND id != $member_id AND (LOWER(TRIM(church_role)) LIKE '%head usher%' OR LOWER(TRIM(church_role)) LIKE '%usher%')");
        while ($u = $ushers->fetch_assoc()) {
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$u['id']}, 'member', '$notif_msg')");
        }
        $pastors = $conn->query("SELECT id FROM pastors WHERE is_approved = 1");
        while ($p = $pastors->fetch_assoc()) {
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$p['id']}, 'pastor', '$notif_msg')");
        }
        $admins = $conn->query("SELECT id FROM admins");
        while ($a = $admins->fetch_assoc()) {
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$a['id']}, 'admin', '$notif_msg')");
        }
    }
    header("Location: member_dashboard.php?tab=usher_chat&sent=1");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_general_leader_msg']) && $is_general_church_leader) {
    $msg = $conn->real_escape_string(trim($_POST['general_leader_message']));
    if (!empty($msg)) {
        $conn->query("INSERT INTO leader_messages (dept_key, sender_id, message) VALUES ('general_church', $member_id, '$msg')");
        $notice = $conn->real_escape_string("New General Church Leadership chat message from " . $member['first_name']);

        $role_conditions = [];
        foreach ($general_leader_roles as $r) {
            $role_conditions[] = "LOWER(TRIM(church_role)) = '" . $conn->real_escape_string($r) . "'";
        }
        $role_sql = implode(' OR ', $role_conditions);
        $leaders = $conn->query("SELECT id FROM members WHERE ($role_sql) AND id != $member_id AND is_approved = 1");
        if ($leaders) {
            while ($leader = $leaders->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$leader['id']}, 'member', '$notice')");
            }
        }
        $pastors = $conn->query("SELECT id FROM pastors");
        if ($pastors) {
            while ($p = $pastors->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$p['id']}, 'pastor', '$notice')");
            }
        }
        $admins = $conn->query("SELECT id FROM admins");
        if ($admins) {
            while ($a = $admins->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$a['id']}, 'admin', '$notice')");
            }
        }
        header("Location: member_dashboard.php?tab=general_leader_chat&sent=1");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_secretary_query']) && (($is_secretary && !$is_general_secretary) || $is_building_secretary)) {
    $raw_msg = trim($_POST['secretary_query_message']);
    $msg = $conn->real_escape_string($raw_msg);
    if (!empty($msg)) {
        $effective_dept = $is_building_secretary ? 'Building & Construction' : $managed_dept;
        $esc_department = $conn->real_escape_string($effective_dept);
        $conn->query("INSERT INTO secretary_announcement_queries (secretary_id, department, message) VALUES ($member_id, '$esc_department', '$msg')");
        $preview = substr($raw_msg, 0, 80) . (strlen($raw_msg) > 80 ? '...' : '');
        $notice = $conn->real_escape_string("New announcement query from $effective_dept Secretary " . $member['first_name'] . ": " . $preview);

        $general_secretaries = $conn->query("SELECT id FROM members WHERE LOWER(TRIM(church_role)) IN ('general church secretary', 'vice church secretary') AND is_approved = 1");
        if ($general_secretaries) {
            while ($gs = $general_secretaries->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$gs['id']}, 'member', '$notice')");
            }
        }
        $pastors = $conn->query("SELECT id FROM pastors");
        if ($pastors) {
            while ($p = $pastors->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$p['id']}, 'pastor', '$notice')");
            }
        }
        $admins = $conn->query("SELECT id FROM admins");
        if ($admins) {
            while ($a = $admins->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$a['id']}, 'admin', '$notice')");
            }
        }
        $redirect_tab = $is_building_secretary ? 'manage_announcement' : 'query_announcements';
        header("Location: member_dashboard.php?tab=$redirect_tab&success=Announcement query sent");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_secretary_direct_message']) && $is_general_secretary) {
    $recipient_id = (int)($_POST['recipient_id'] ?? 0);
    $query_id = (int)($_POST['query_id'] ?? 0);
    $raw_msg = trim($_POST['secretary_direct_message']);
    $msg = $conn->real_escape_string($raw_msg);
    if ($recipient_id > 0 && !empty($msg)) {
        // Validate the recipient exists and is approved — no role restriction, they could be any leader
        $recipient = $conn->query("SELECT id, first_name, department FROM members WHERE id = $recipient_id AND is_approved = 1")->fetch_assoc();
        if (!$recipient) {
            header("Location: member_dashboard.php?tab=query_announcements&error=Invalid recipient selected");
            exit();
        }
        if ($query_id > 0) {
            // Verify the query belongs to this recipient
            $query_check = $conn->query("SELECT id FROM secretary_announcement_queries WHERE id = $query_id AND secretary_id = $recipient_id")->fetch_assoc();
            if (!$query_check) {
                header("Location: member_dashboard.php?tab=query_announcements&error=Select a valid announcement query");
                exit();
            }
        }
        $query_sql = $query_id > 0 ? $query_id : "NULL";
        $conn->query("INSERT INTO secretary_direct_messages (query_id, sender_id, recipient_id, message) VALUES ($query_sql, $member_id, $recipient_id, '$msg')");
        if ($query_id > 0) {
            $conn->query("UPDATE secretary_announcement_queries SET status = 'Replied' WHERE id = $query_id");
        }
        $preview = substr($raw_msg, 0, 80) . (strlen($raw_msg) > 80 ? '...' : '');
        $notice = $conn->real_escape_string(($query_id > 0 ? "Reply to your announcement query" : "Announcement Queried message") . " from General Church Secretary " . $member['first_name'] . ": " . $preview);
        $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($recipient_id, 'member', '$notice')");
        $pastors = $conn->query("SELECT id FROM pastors");
        if ($pastors) {
            while ($p = $pastors->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$p['id']}, 'pastor', '$notice')");
            }
        }
        $admins = $conn->query("SELECT id FROM admins");
        if ($admins) {
            while ($a = $admins->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$a['id']}, 'admin', '$notice')");
            }
        }
        header("Location: member_dashboard.php?tab=query_announcements&success=Message sent to " . urlencode($recipient['first_name']));
        exit();
    }
}

// Handle Posting Department Announcement (Secretaries)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_dept_announcement']) && ($is_secretary || $is_general_secretary || $is_building_secretary)) {
    $msg = $conn->real_escape_string(trim($_POST['message']));
    if (!empty($msg)) {
        $announcement_dept = ($is_general_secretary || $is_building_secretary) ? 'General Church' : ($_POST['active_dept'] ?? $managed_dept);
        $esc_dept = $conn->real_escape_string($announcement_dept);
        $image_path = save_announcement_image(['announcement_camera', 'announcement_upload']);
        $image_sql = $image_path ? "'" . $conn->real_escape_string($image_path) . "'" : "NULL";
        $conn->query("INSERT INTO department_announcements (department, secretary_id, message, image_path) VALUES ('$esc_dept', $member_id, '$msg', $image_sql)");

        $preview_msg = substr($msg, 0, 80) . (strlen($msg) > 80 ? '...' : '');
        $notification_text = $conn->real_escape_string("New " . $announcement_dept . " Announcement: " . $preview_msg);

        $member_filter = $is_general_secretary ? "is_approved = 1 AND id != $member_id" : "department = '$esc_dept' AND is_approved = 1 AND id != $member_id";
        $dept_members = $conn->query("SELECT id FROM members WHERE $member_filter");
        if ($dept_members) {
            while($m = $dept_members->fetch_assoc()) {
                $mid = $m['id'];
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($mid, 'member', '$notification_text')");
            }
        }

        // Notify pastors
        $pastors = $conn->query("SELECT id FROM pastors");
        if ($pastors) {
            while($p = $pastors->fetch_assoc()) {
                $pid = $p['id'];
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($pid, 'pastor', '$notification_text')");
            }
        }

        // Notify admins
        $admins = $conn->query("SELECT id FROM admins");
        if ($admins) {
            while($a = $admins->fetch_assoc()) {
                $aid = $a['id'];
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($aid, 'admin', '$notification_text')");
            }
        }
        $redirect_url = "member_dashboard.php?tab=" . urlencode($tab) . "&success=Announcement posted successfully";
        if (isset($_POST['active_dept'])) {
            $redirect_url .= "&sec_dept=" . urlencode($_POST['active_dept']);
        }
        header("Location: " . $redirect_url);
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_youth_advisor_announcement']) && $is_youth_advisor) {
    $msg = $conn->real_escape_string(trim($_POST['message']));
    if (!empty($msg)) {
        $image_path = save_announcement_image(['announcement_camera', 'announcement_upload']);
        $image_sql = $image_path ? "'" . $conn->real_escape_string($image_path) . "'" : "NULL";
        $conn->query("INSERT INTO department_announcements (department, secretary_id, message, image_path) VALUES ('Youths', $member_id, '$msg', $image_sql)");
        $preview_msg = substr(trim($_POST['message']), 0, 80) . (strlen(trim($_POST['message'])) > 80 ? '...' : '');
        $notification_text = $conn->real_escape_string("New Youth Announcement from " . $member['church_role'] . " " . $member['first_name'] . ": " . $preview_msg);

        $youth_filter = department_match_sql($conn, 'department', 'Youths');
        $youth_members = $conn->query("SELECT id FROM members WHERE $youth_filter AND is_approved = 1 AND id != $member_id");
        if ($youth_members) {
            while ($ym = $youth_members->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$ym['id']}, 'member', '$notification_text')");
            }
        }
        $youth_leaders = $conn->query("SELECT id FROM members WHERE LOWER(TRIM(church_role)) IN ('youth chairperson', 'vice youth chairperson', 'youth secretary', 'vice youth secretary', 'youth treasurer', 'mama youth', 'baba youth') AND id != $member_id AND is_approved = 1");
        if ($youth_leaders) {
            while ($yl = $youth_leaders->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$yl['id']}, 'member', '$notification_text')");
            }
        }
        $pastors = $conn->query("SELECT id FROM pastors");
        if ($pastors) {
            while ($p = $pastors->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$p['id']}, 'pastor', '$notification_text')");
            }
        }
        $admins = $conn->query("SELECT id FROM admins");
        if ($admins) {
            while ($a = $admins->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$a['id']}, 'admin', '$notification_text')");
            }
        }
        header("Location: member_dashboard.php?tab=convey_youth_announcement&success=Youth announcement posted successfully");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_choir_announcement']) && $is_choir_leader) {
    $raw_msg = trim($_POST['message']);
    if (!empty($raw_msg)) {
        $active_dept = $_POST['active_dept'] ?? $member['department'];
        $dept = $conn->real_escape_string($active_dept);
        $msg = $conn->real_escape_string($raw_msg);
        $image_path = save_announcement_image(['announcement_camera', 'announcement_upload']);
        $image_sql = $image_path ? "'" . $conn->real_escape_string($image_path) . "'" : "NULL";
        $conn->query("INSERT INTO choir_announcements (department, choir_leader_id, message, image_path) VALUES ('$dept', $member_id, '$msg', $image_sql)");

        $preview_msg = substr($raw_msg, 0, 80) . (strlen($raw_msg) > 80 ? '...' : '');
        $notification_text = $conn->real_escape_string("New choir song announcement for " . $active_dept . ": " . $preview_msg);
        $dept_filter = department_match_sql($conn, 'department', $active_dept);
        $dept_members = $conn->query("SELECT id FROM members WHERE $dept_filter AND is_approved = 1 AND id != $member_id");
        if ($dept_members) {
            while ($dm = $dept_members->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$dm['id']}, 'member', '$notification_text')");
            }
        }
        $pastors = $conn->query("SELECT id FROM pastors");
        if ($pastors) {
            while ($p = $pastors->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$p['id']}, 'pastor', '$notification_text')");
            }
        }
        $admins = $conn->query("SELECT id FROM admins");
        if ($admins) {
            while ($a = $admins->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$a['id']}, 'admin', '$notification_text')");
            }
        }
        header("Location: member_dashboard.php?tab=manage_songs&choir_dept=" . urlencode($active_dept) . "&success=Choir announcement posted");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_song_plan']) && $is_choir_leader) {
    $song_title = trim($_POST['song_title'] ?? '');
    $song_type = trim($_POST['song_type'] ?? '');
    $song_date = trim($_POST['song_date'] ?? '');
    if ($song_title !== '' && $song_type !== '' && $song_date !== '') {
        $active_dept = $_POST['active_dept'] ?? $member['department'];
        $dept = $conn->real_escape_string($active_dept);
        $safe_title = $conn->real_escape_string($song_title);
        $safe_type = $conn->real_escape_string($song_type);
        $safe_date = $conn->real_escape_string($song_date);
        $song_time = !empty($_POST['song_time']) ? "'" . $conn->real_escape_string($_POST['song_time']) . "'" : "NULL";
        $notes = $conn->real_escape_string(trim($_POST['notes'] ?? ''));
        $conn->query("INSERT INTO choir_song_plans (department, choir_leader_id, song_type, song_title, song_date, song_time, notes) VALUES ('$dept', $member_id, '$safe_type', '$safe_title', '$safe_date', $song_time, '$notes')");

        $time_text = !empty($_POST['song_time']) ? " at " . date('g:i A', strtotime($_POST['song_time'])) : '';
        $notification_text = $conn->real_escape_string("New choir song plan for " . $active_dept . ": $song_type - $song_title on " . date('M j, Y', strtotime($song_date)) . $time_text);
        $dept_filter = department_match_sql($conn, 'department', $active_dept);
        $dept_members = $conn->query("SELECT id FROM members WHERE $dept_filter AND is_approved = 1 AND id != $member_id");
        if ($dept_members) {
            while ($dm = $dept_members->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$dm['id']}, 'member', '$notification_text')");
            }
        }
        $pastors = $conn->query("SELECT id FROM pastors");
        if ($pastors) {
            while ($p = $pastors->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$p['id']}, 'pastor', '$notification_text')");
            }
        }
        $admins = $conn->query("SELECT id FROM admins");
        if ($admins) {
            while ($a = $admins->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$a['id']}, 'admin', '$notification_text')");
            }
        }
        header("Location: member_dashboard.php?tab=manage_songs&choir_dept=" . urlencode($active_dept) . "&success=Song plan added");
        exit();
    }
}

// Handle Posting Elder Announcement
$can_post_elder_announcement = false;
foreach ($raw_church_roles as $r) {
    $nr = normalize_role_name($r);
    if ($nr === 'senior church elder' || $nr === 'elder chairman') {
        $can_post_elder_announcement = true;
        break;
    }
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_announcement']) && $can_post_elder_announcement) {
    $msg = $conn->real_escape_string(trim($_POST['message']));
    if (!empty($msg)) {
        $image_path = save_announcement_image(['announcement_camera', 'announcement_upload']);
        $image_sql = $image_path ? "'" . $conn->real_escape_string($image_path) . "'" : "NULL";
        $conn->query("INSERT INTO elder_announcements (elder_id, message, image_path) VALUES ($member_id, '$msg', $image_sql)");
        $preview_msg = substr(trim($_POST['message']), 0, 80) . (strlen(trim($_POST['message'])) > 80 ? '...' : '');
        $notification_text = $conn->real_escape_string("New Senior Church Elder Announcement: " . $preview_msg);
        $members_to_notify = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND id != $member_id");
        if ($members_to_notify) {
            while ($m = $members_to_notify->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$m['id']}, 'member', '$notification_text')");
            }
        }
        $pastors = $conn->query("SELECT id FROM pastors");
        if ($pastors) {
            while ($p = $pastors->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$p['id']}, 'pastor', '$notification_text')");
            }
        }
        $admins = $conn->query("SELECT id FROM admins");
        if ($admins) {
            while ($a = $admins->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$a['id']}, 'admin', '$notification_text')");
            }
        }
        header("Location: member_dashboard.php?tab=elder_announcements&success=Announcement posted successfully");
        exit();
    }
}


// Appointments
$appointments = $conn->query("
    SELECT a.*, p.first_name, p.last_name
    FROM appointments a
    JOIN pastors p ON a.pastor_id = p.id
    WHERE a.member_id = $member_id
    ORDER BY a.appointment_date DESC
");

$pastors = $conn->query("SELECT * FROM pastors WHERE is_approved = 1");

// Handle Mark All as Read
if (isset($_GET['action']) && $_GET['action'] == 'mark_all_read') {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $member_id AND user_type = 'member'");
    header("Location: member_dashboard.php?tab=" . ($_GET['tab'] ?? 'dashboard'));
    exit();
}

// Handle Delete All Notifications
if (isset($_GET['action']) && $_GET['action'] == 'delete_all_notifications') {
    $conn->query("DELETE FROM notifications WHERE user_id = $member_id AND user_type = 'member'");
    header("Location: member_dashboard.php?tab=" . ($_GET['tab'] ?? 'dashboard'));
    exit();
}

// Handle Password Change inline
// Handle Sunday School class selection during setup
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_ss_class'])) {
    $allowed_classes = ['Little Angels', 'Champions', 'Battalion', 'Conquerors'];
    $ss_class = trim($_POST['ss_class'] ?? '');
    $is_ss_member = ($member['department'] ?? '') === 'Sunday School';

    if (!$is_ss_member) {
        header("Location: member_dashboard.php?tab=manage_classes&error=" . urlencode("Only Sunday School members can select a Sunday School class."));
        exit();
    }

    if (in_array($ss_class, $allowed_classes, true)) {
        $current_class = trim($member['sunday_school_class'] ?? '');
        $ss_safe = $conn->real_escape_string($ss_class);

        if ($current_class === '') {
            $conn->query("UPDATE members SET sunday_school_class = '$ss_safe' WHERE id = $member_id");
            $member['sunday_school_class'] = $ss_class;
            $latest = $conn->query("SELECT profile_picture, username FROM members WHERE id = $member_id")->fetch_assoc();
            if (!empty($latest['username']) && !empty($latest['profile_picture']) && $latest['profile_picture'] !== 'default_avatar.png') {
                $conn->query("UPDATE members SET setup_completed = 1 WHERE id = $member_id");
            }
            header("Location: member_dashboard.php?tab=manage_classes&success=" . urlencode("Sunday School class saved and locked."));
            exit();
        }

        if ($current_class === $ss_class) {
            header("Location: member_dashboard.php?tab=manage_classes&success=" . urlencode("You are already in the $ss_class class."));
            exit();
        }

        $next_class = ss_next_class($current_class);
        if (!$next_class || $ss_class !== $next_class) {
            $message = $next_class
                ? "Class transfers must follow this order: $current_class to $next_class."
                : "Conquerors is the highest Sunday School class, so no further transfer is available.";
            header("Location: member_dashboard.php?tab=manage_classes&error=" . urlencode($message));
            exit();
        }

        $pending_exists = $conn->query("SELECT id FROM sunday_school_class_requests WHERE member_id = $member_id AND status = 'Pending' LIMIT 1");
        if ($pending_exists && $pending_exists->num_rows > 0) {
            header("Location: member_dashboard.php?tab=manage_classes&error=" . urlencode("You already have a pending class change request."));
            exit();
        }

        $current_safe = $conn->real_escape_string($current_class);
        $conn->query("INSERT INTO sunday_school_class_requests (member_id, current_class, requested_class, status, requested_at) VALUES ($member_id, '$current_safe', '$ss_safe', 'Pending', NOW())");
        $member_name = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
        $notice = $conn->real_escape_string("Sunday School class change request: $member_name wants to move from $current_class to $ss_class.");

        $patrons = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND LOWER(church_role) LIKE '%sunday school patron%'");
        if ($patrons) {
            while ($p = $patrons->fetch_assoc()) {
                $pid = (int)$p['id'];
                $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($pid, 'member', '$notice', 0, NOW())");
            }
        }
        $pastors_to_notify = $conn->query("SELECT id FROM pastors");
        if ($pastors_to_notify) {
            while ($p = $pastors_to_notify->fetch_assoc()) {
                $pid = (int)$p['id'];
                $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($pid, 'pastor', '$notice', 0, NOW())");
            }
        }
        $admins_to_notify = $conn->query("SELECT id FROM admins");
        if ($admins_to_notify) {
            while ($a = $admins_to_notify->fetch_assoc()) {
                $aid = (int)$a['id'];
                $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($aid, 'admin', '$notice', 0, NOW())");
            }
        }

        header("Location: member_dashboard.php?tab=manage_classes&success=" . urlencode("Class change request sent for approval."));
        exit();
    } else {
        header("Location: member_dashboard.php?tab=manage_classes&error=" . urlencode("Please select a valid Sunday School class."));
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    if (strlen($new_password) < 6) {
        header("Location: member_dashboard.php?tab=settings&error=Password must be at least 6 characters");
        exit();
    }
    if ($new_password === $confirm_password) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $conn->query("UPDATE members SET password = '$hashed' WHERE id = $member_id");
        header("Location: member_dashboard.php?tab=settings&success=Password Updated");
        exit();
    } else {
        header("Location: member_dashboard.php?tab=settings&error=Passwords do not match");
        exit();
    }
}

// Handle Contact Details Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_contact_details'])) {
    $phone_raw = trim($_POST['phone'] ?? '');
    $address_raw = trim($_POST['address'] ?? '');

    if (!preg_match('/^\d{10}$/', $phone_raw)) {
        header("Location: member_dashboard.php?tab=settings&error=" . urlencode("Phone number must be exactly 10 digits and contain numbers only."));
        exit();
    }

    if ($address_raw === '') {
        header("Location: member_dashboard.php?tab=settings&error=" . urlencode("Resident area is required."));
        exit();
    }
    if (!preg_match("/^[A-Za-z\s'-]+$/", $address_raw)) {
        header("Location: member_dashboard.php?tab=settings&error=" . urlencode("Resident area should contain letters only. Numbers are not allowed."));
        exit();
    }

    $phone = $conn->real_escape_string($phone_raw);
    $address = $conn->real_escape_string($address_raw);
    $phone_check = $conn->query("SELECT id FROM members WHERE phone = '$phone' AND id != $member_id UNION SELECT id FROM pastors WHERE phone = '$phone'");
    if ($phone_check && $phone_check->num_rows > 0) {
        header("Location: member_dashboard.php?tab=settings&error=" . urlencode("That phone number is already used by another account."));
        exit();
    }

    $conn->query("UPDATE members SET phone = '$phone', address = '$address' WHERE id = $member_id");
    $member = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
    header("Location: member_dashboard.php?tab=settings&success=" . urlencode("Contact details updated successfully."));
    exit();
}

// Handle Department Request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_department'])) {
    $current_dept = $member['department'];
    $requested_dept_raw = trim($_POST['department'] ?? '');
    if ($current_dept == 'Youths') {
        $transfer_gender = trim($member['gender'] ?? '');
        if ($transfer_gender === 'Male') {
            $requested_dept_raw = 'Elders';
        } elseif ($transfer_gender === 'Female') {
            $requested_dept_raw = 'Womens Ministry';
        } else {
            header("Location: member_dashboard.php?tab=settings&error=" . urlencode("Your registered gender is missing. Please contact the pastor or admin to correct it before requesting transfer."));
            exit();
        }
    }

    if ($current_dept == 'Sunday School' && trim($member['sunday_school_class'] ?? '') !== 'Conquerors') {
        header("Location: member_dashboard.php?tab=settings&error=" . urlencode("You can request transfer to Youths only after reaching the Conquerors class."));
        exit();
    }

    $requested_dept = $conn->real_escape_string($requested_dept_raw);
    $allowed_depts = [];

    if ($current_dept == 'Youths') {
        $allowed_depts = ['Elders', 'Womens Ministry'];
    } elseif ($current_dept == 'Sunday School') {
        $allowed_depts = ['Youths'];
    }

    if (!in_array($requested_dept_raw, $allowed_depts, true)) {
        header("Location: member_dashboard.php?tab=settings&error=Invalid department transfer request.");
        exit();
    }

    if ($requested_dept_raw !== $current_dept) {
        $conn->query("UPDATE members SET pending_department = '$requested_dept' WHERE id = $member_id");
        header("Location: member_dashboard.php?tab=settings&success=Department transfer requested. Waiting for pastor approval.");
        exit();
    } else {
        header("Location: member_dashboard.php?tab=settings&error=You are already in that department.");
        exit();
    }
}

// Handle Profile Picture Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_picture'])) {
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_picture']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $new_name = 'member_' . $member_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], 'uploads/' . $new_name)) {
                $conn->query("UPDATE members SET profile_picture = '$new_name' WHERE id = $member_id");
                // Check if setup can now be completed (username already set?)
                $latest = $conn->query("SELECT username, profile_picture, department, sunday_school_class FROM members WHERE id = $member_id")->fetch_assoc();
                $is_ss_l = ($latest['department'] ?? '') === 'Sunday School';
                $ss_ok_l = !$is_ss_l || !empty($latest['sunday_school_class']);
                if (!empty($latest['username']) && !empty($latest['profile_picture']) && $latest['profile_picture'] !== 'default_avatar.png' && $ss_ok_l) {
                    $conn->query("UPDATE members SET setup_completed = 1 WHERE id = $member_id");
                }
                header("Location: member_dashboard.php?tab=settings&success=Profile picture updated");
                exit();
            }
        }
        header("Location: member_dashboard.php?tab=settings&error=Invalid file type or upload failed");
        exit();
    }
}

// Handle Username Creation / Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_username'])) {
    $new_username_raw = trim($_POST['new_username']);
    $new_username = $conn->real_escape_string($new_username_raw);
    if (strlen($new_username_raw) < 3) {
        header("Location: member_dashboard.php?tab=settings&error=Username must be at least 3 characters");
        exit();
    }
    if (!preg_match('/^[a-zA-Z0-9_.]+$/', $new_username_raw)) {
        header("Location: member_dashboard.php?tab=settings&error=Username may only contain letters, numbers, dots and underscores");
        exit();
    }
    $username_plain = strtolower(preg_replace('/[^a-z0-9]/', '', $new_username_raw));
    $first_plain = strtolower(preg_replace('/[^a-z0-9]/', '', $member['first_name'] ?? ''));
    $last_plain = strtolower(preg_replace('/[^a-z0-9]/', '', $member['last_name'] ?? ''));
    $full_plain = $first_plain . $last_plain;
    $reverse_full_plain = $last_plain . $first_plain;
    if ($username_plain !== '' && in_array($username_plain, array_filter([$first_plain, $last_plain, $full_plain, $reverse_full_plain]), true)) {
        header("Location: member_dashboard.php?tab=settings&error=" . urlencode("Please use a different username. Your username cannot be the same as your name."));
        exit();
    }
    // Check uniqueness
    $u_check = $conn->query("SELECT id FROM members WHERE BINARY username = '$new_username' AND id != $member_id UNION SELECT id FROM pastors WHERE BINARY username = '$new_username' UNION SELECT id FROM admins WHERE BINARY username = '$new_username'");
    if ($u_check && $u_check->num_rows > 0) {
        header("Location: member_dashboard.php?tab=settings&error=That username is already taken. Please choose another.");
        exit();
    }
    $conn->query("UPDATE members SET username = '$new_username' WHERE id = $member_id");
    // Check if profile picture also uploaded
    $latest = $conn->query("SELECT profile_picture, username, department, sunday_school_class FROM members WHERE id = $member_id")->fetch_assoc();
    $is_ss_l = ($latest['department'] ?? '') === 'Sunday School';
    $ss_ok_l = !$is_ss_l || !empty($latest['sunday_school_class']);
    if (!empty($latest['profile_picture']) && $latest['profile_picture'] !== 'default_avatar.png' && $ss_ok_l) {
        $conn->query("UPDATE members SET setup_completed = 1 WHERE id = $member_id");
    }
    // Reload member data
    $member = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
    header("Location: member_dashboard.php?tab=settings&success=Username saved successfully!");
    exit();
}

$messages_query = $conn->query("SELECT * FROM member_messages WHERE member_id = $member_id ORDER BY created_at DESC");

// Per-notification mark-as-read is handled via mark_read_and_redirect.php on click

// ── Setup completion state ────────────────────────────────────────────────────
$member = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
$setup_completed = (bool)($member['setup_completed'] ?? false);
$has_profile_pic = !empty($member['profile_picture']) && $member['profile_picture'] !== 'default_avatar.png';
$has_username    = !empty($member['username']);
// SS members also need class before setup completes
$is_ss_member_setup = ($member['department'] ?? '') === 'Sunday School';
$has_ss_class_setup = !empty($member['sunday_school_class']);
if ($has_profile_pic && $has_username && (!$is_ss_member_setup || $has_ss_class_setup) && !$setup_completed) {
    $conn->query("UPDATE members SET setup_completed = 1 WHERE id = $member_id");
    $setup_completed = true;
}
// Sunday School members must choose a class before the rest of the dashboard unlocks.
if ($is_ss_member_setup && !$has_ss_class_setup && $tab !== 'manage_classes') {
    $tab = 'manage_classes';
} elseif (!$setup_completed && !in_array($tab, ['settings', 'desired_roles', 'village_church', 'manage_classes', 'notifications'])) {
    $tab = 'settings';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            document.documentElement.setAttribute('data-theme', savedTheme);
        }
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - Munyari Church</title>
    <link rel="stylesheet" href="style.css">
    <?php
    $phones_result = $conn->query("SELECT phone FROM members WHERE phone IS NOT NULL AND phone != ''");
    $phones = [];
    if ($phones_result) {
        while ($row = $phones_result->fetch_assoc()) {
            $phones[] = $row['phone'];
        }
    }
    echo "<script>window.registeredPhones = " . json_encode($phones) . ";</script>";
    ?>
</head>
<body>
    <div class="dashboard-layout">
        <div class="sidebar">
            <div class="sidebar-brand">Munyari Church</div>
            <?php if (count($involved_departments) > 1): ?>
            <div style="padding: 15px 20px; background: rgba(0,0,0,0.05); border-bottom: 1px solid var(--border-color);">
                <label style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: bold; margin-bottom: 5px; display: block;">Dashboard Context</label>
                <form method="GET" style="margin:0;">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                    <select name="switch_dashboard_dept" class="form-control" style="font-size: 0.9rem; padding: 6px; background: var(--bg-card); color: var(--text-main); border-color: var(--border-color);" onchange="this.form.submit()">
                        <?php foreach ($involved_departments as $dept): ?>
                            <option value="<?= htmlspecialchars($dept) ?>" <?= $active_dashboard_dept === $dept ? 'selected' : '' ?>><?= htmlspecialchars($dept) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <?php endif; ?>
            <div class="sidebar-nav">
                <a href="?tab=dashboard" class="sidebar-link <?= $tab == 'dashboard' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    My Dashboard
                </a>

                
                <a href="?tab=desired_roles" class="sidebar-link <?= $tab == 'desired_roles' ? 'active' : '' ?>" style="position:relative; <?= !$has_church_village ? 'background:linear-gradient(135deg,rgba(99,102,241,0.18),rgba(37,99,235,0.1)); border-left:3px solid #6366f1;' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Church Village & Role
                    <?php if (!$has_church_village): ?><span style="background:var(--danger);color:white;font-size:0.6rem;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:auto;animation:pulse 1.5s infinite;">NEW</span><?php endif; ?>
                </a>

                <?php if (($member['department'] ?? '') === 'Sunday School'): ?>
                <a href="?tab=manage_classes" class="sidebar-link <?= $tab == 'manage_classes' ? 'active' : '' ?>" style="position:relative; <?= empty($member['sunday_school_class']) ? 'background:linear-gradient(135deg,rgba(245,158,11,0.18),rgba(245,158,11,0.08)); border-left:3px solid #f59e0b;' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422A12.083 12.083 0 0118.825 17 11.952 11.952 0 0012 20.055 11.952 11.952 0 005.175 17a12.083 12.083 0 01.665-6.422L12 14z"></path></svg>
                    Manage Classes
                    <?php if (empty($member['sunday_school_class'])): ?><span style="background:var(--danger);color:white;font-size:0.6rem;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:auto;animation:pulse 1.5s infinite;">REQUIRED</span><?php endif; ?>
                </a>
                <?php endif; ?>

                <?php if ($has_church_village): ?>
                <a href="?tab=contact_pastor" class="sidebar-link <?= $tab == 'contact_pastor' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Contact Pastor
                </a>
                <a href="?tab=inspiration_highlights" class="sidebar-link <?= $tab == 'inspiration_highlights' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Daily Quote & Highlights
                </a>

                <div class="sidebar-label" style="padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: var(--text-muted); margin-top: 10px;">Community</div>
                <a href="?tab=announcements" class="sidebar-link <?= $tab == 'announcements' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                    Announcements
                </a>
                <?php if (!$is_building_leader): ?>
                <a href="?tab=church_buildings" class="sidebar-link <?= $tab == 'church_buildings' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    Church Buildings
                </a>
                <?php endif; ?>
                <?php if (!empty($member['department']) && $member['department'] !== 'None' && !$is_choir_leader): ?>
                <a href="?tab=choir_songs" class="sidebar-link <?= $tab == 'choir_songs' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 18V5l12-2v13"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 18a3 3 0 11-6 0 3 3 0 016 0zm12-2a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Choir Songs
                </a>
                <?php endif; ?>
                <?php if ((department_allows_graduation_and_sport($member['department'] ?? '') || $is_youth_advisor_scope) && !$is_graduands_secretary): ?>
                    <a href="?tab=graduation_info" class="sidebar-link <?= $tab == 'graduation_info' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path></svg>
                        Graduation Info
                    </a>
                <?php endif; ?>
                <?php if (!$is_prayer_coordinator): ?>
                <a href="?tab=prayer_board" class="sidebar-link <?= $tab == 'prayer_board' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                    Prayer Board
                </a>
                <?php endif; ?>

                <?php if (!empty($member['department']) && $member['department'] !== 'None'): ?>
                <?php if (!$is_organizing_secretary): ?>
                <a href="?tab=organized_events" class="sidebar-link <?= $tab == 'organized_events' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Organised Events
                </a>
                <?php endif; ?>
                <?php if ((department_allows_graduation_and_sport($member['department'] ?? '') || $is_youth_advisor_scope) && !$is_sport_secretary): ?>
                    <a href="?tab=dept_sport" class="sidebar-link <?= $tab == 'dept_sport' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Department Sport
                    </a>
                <?php endif; ?>
                <a href="?tab=my_fines" class="sidebar-link <?= $tab == 'my_fines' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path></svg>
                    My Fines
                </a>
                <?php endif; ?>

                <?php if (in_array('senior Senior Church Elder', $member_roles_normalized, true) || $is_general_secretary || $is_general_church_leader || $is_secretary_council_member): ?>
                <div class="sidebar-label" style="padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: var(--warning); margin-top: 10px;">General Church Panel</div>
                <?php if (in_array('senior Senior Church Elder', $member_roles_normalized, true)): ?>
                <a href="?tab=elder_announcements" class="sidebar-link <?= $tab == 'elder_announcements' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                    Elder Announcement
                </a>
                <?php endif; ?>
                <?php if ($is_general_secretary): ?>
                <a href="?tab=dept_announcements" class="sidebar-link <?= $tab == 'dept_announcements' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                    General Announcement
                </a>
                <?php endif; ?>
                <?php if ($is_general_church_leader): ?>
                <a href="?tab=general_leader_chat" class="sidebar-link <?= $tab == 'general_leader_chat' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path></svg>
                    General Leaders Chat
                </a>
                <?php endif; ?>
                <?php if ($is_secretary_council_member): ?>
                <a href="?tab=query_announcements" class="sidebar-link <?= $tab == 'query_announcements' || $tab == 'secretary_council_chat' ? 'active' : '' ?>" style="position:relative;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                    <?= $is_general_secretary ? 'Announcement Queried' : 'Query Announcement' ?>
                    <?php if (!empty($tab_badges['query_announcements'])): ?><span style="background:var(--danger);color:white;font-size:0.65rem;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:auto;"><?= $tab_badges['query_announcements'] ?></span><?php endif; ?>
                </a>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Leadership Panel -->
                <?php if ($ctx_is_leader && $managed_dept !== 'Sunday School'): ?>
                    <div class="sidebar-label" style="padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: var(--primary); margin-top: 10px;">Leadership</div>
                    <a href="?tab=manage_department" class="sidebar-link <?= $tab == 'manage_department' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        Manage <?= htmlspecialchars($managed_dept) ?>
                        <?php if (!empty($tab_badges['manage_department'])): ?><span class="tab-notif-badge"><?= $tab_badges['manage_department'] > 99 ? '99+' : $tab_badges['manage_department'] ?></span><?php endif; ?>
                    </a>
                    <?php if ($ctx_is_chairperson): ?>
                    <a href="?tab=received_financials" class="sidebar-link <?= $tab == 'received_financials' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="2" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></rect><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 10h10M7 14h6"></path></svg>
                        Received Financial Records
                        <?php if (!empty($tab_badges['received_financials'])): ?><span class="tab-notif-badge"><?= $tab_badges['received_financials'] > 99 ? '99+' : $tab_badges['received_financials'] ?></span><?php endif; ?>
                    </a>
                    <?php endif; ?>
                    <a href="?tab=appoint_leaders" class="sidebar-link <?= $tab == 'appoint_leaders' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                        Appoint Leaders
                    </a>
                    <a href="?tab=leader_chat" class="sidebar-link <?= $tab == 'leader_chat' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l.586-.586z"></path></svg>
                        <?= htmlspecialchars($managed_dept ?? 'Youths') ?> Leaders Chat
                    </a>
                <?php endif; ?>

                <!-- Secretary Panel -->
                <?php if ($ctx_is_secretary): ?>
                    <div class="sidebar-label" style="padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: var(--primary); margin-top: 10px;">Secretary Panel</div>
                    <a href="?tab=dept_announcements" class="sidebar-link <?= $tab == 'dept_announcements' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                        <?= htmlspecialchars($managed_dept) ?> Announcement
                    </a>
                    <?php if (!$ctx_is_leader): ?>
                    <a href="?tab=leader_chat" class="sidebar-link <?= $tab == 'leader_chat' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l.586-.586z"></path></svg>
                        <?= htmlspecialchars($managed_dept ?? 'Youths') ?> Leaders Chat
                    </a>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Organizing Secretary Panel -->
                <?php if ($ctx_is_organizing_secretary): ?>
                    <div class="sidebar-label" style="padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: var(--primary); margin-top: 10px;">Organising Secretary</div>
                    <a href="?tab=organized_events" class="sidebar-link <?= $tab == 'organized_events' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Organised Events
                    </a>
                <?php endif; ?>

                <!-- Discipline Panel -->
                <?php if ($ctx_is_discipline_master): ?>
                    <div class="sidebar-label" style="padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: var(--primary); margin-top: 10px;">Discipline Panel</div>
                    <a href="?tab=discipline" class="sidebar-link <?= $tab == 'discipline' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path></svg>
                        Discipline & Fines
                    </a>
                    <?php if (!$ctx_is_leader && !$ctx_is_secretary): ?>
                    <a href="?tab=leader_chat" class="sidebar-link <?= $tab == 'leader_chat' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l.586-.586z"></path></svg>
                        <?= htmlspecialchars($managed_dept ?? 'Youths') ?> Leaders Chat
                    </a>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Graduation Panel -->
                <?php if ($is_graduands_secretary): ?>
                    <div class="sidebar-label" style="padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: var(--primary); margin-top: 10px;">Graduation Panel</div>
                    <a href="?tab=graduation_panel" class="sidebar-link <?= $tab == 'graduation_panel' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"></path></svg>
                        Graduation Panel
                    </a>
                    <?php if (!$is_leader && !$is_secretary): ?>
                    <a href="?tab=leader_chat" class="sidebar-link <?= $tab == 'leader_chat' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l.586-.586z"></path></svg>
                        <?= htmlspecialchars($managed_dept ?? 'Youths') ?> Leaders Chat
                    </a>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($ctx_is_prayer_coordinator): ?>
                    <div class="sidebar-label" style="padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: var(--primary); margin-top: 10px;">Prayer Panel</div>
                    <a href="?tab=prayer_panel" class="sidebar-link <?= $tab == 'prayer_panel' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                        Prayer Panel
                    </a>
                    <?php if (!$ctx_is_leader && !$ctx_is_secretary): ?>
                    <a href="?tab=leader_chat" class="sidebar-link <?= $tab == 'leader_chat' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l.586-.586z"></path></svg>
                        <?= htmlspecialchars($managed_dept ?? 'Youths') ?> Leaders Chat
                    </a>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Sport Panel -->
                <?php if ($ctx_is_sport_secretary): ?>
                    <div class="sidebar-label" style="padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: var(--primary); margin-top: 10px;">Sport Panel</div>
                    <a href="?tab=manage_sport" class="sidebar-link <?= $tab == 'manage_sport' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Manage Sport
                    </a>
                    <?php if (!$ctx_is_leader && !$ctx_is_secretary): ?>
                    <a href="?tab=leader_chat" class="sidebar-link <?= $tab == 'leader_chat' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l.586-.586z"></path></svg>
                        <?= htmlspecialchars($managed_dept ?? 'Youths') ?> Leaders Chat
                    </a>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Choir Panel -->
                <?php if ($ctx_is_choir_leader): ?>
                    <div class="sidebar-label" style="padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: var(--primary); margin-top: 10px;">Choir Panel</div>
                    <a href="?tab=manage_songs" class="sidebar-link <?= $tab == 'manage_songs' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 18V5l12-2v13"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 18a3 3 0 11-6 0 3 3 0 016 0zm12-2a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        Manage Songs
                    </a>
                    <?php if (!$ctx_is_leader && !$ctx_is_secretary): ?>
                    <a href="?tab=leader_chat" class="sidebar-link <?= $tab == 'leader_chat' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l.586-.586z"></path></svg>
                        <?= htmlspecialchars($managed_dept ?? 'Youths') ?> Leaders Chat
                    </a>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Youth Advisory Panel -->
                <?php if ($is_youth_advisor): ?>
                    <div class="sidebar-label" style="padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: var(--primary); margin-top: 10px;">Youth Advisory Panel</div>
                    <a href="?tab=convey_youth_announcement" class="sidebar-link <?= $tab == 'convey_youth_announcement' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                        Convey Announcement
                    </a>
                    <a href="?tab=monitor_youth_from_home" class="sidebar-link <?= $tab == 'monitor_youth_from_home' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        Monitor Youths
                    </a>
                    <?php if (!$is_leader && !$is_secretary): ?>
                    <a href="?tab=leader_chat" class="sidebar-link <?= $tab == 'leader_chat' ? 'active' : '' ?>">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l.586-.586z"></path></svg>
                        <?= htmlspecialchars($managed_dept ?? 'Youths') ?> Leaders Chat
                    </a>
                    <?php endif; ?>
                <?php endif; ?>


                <?php if ($is_head_usher || in_array('usher', $member_roles_normalized, true)): ?>
                <div class="sidebar-label" style="padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: #f59e0b; margin-top: 10px;">Usher Panel</div>
                <?php if ($is_head_usher): ?>
                <a href="?tab=usher_panel" class="sidebar-link <?= $tab == 'usher_panel' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                    Usher Announcements
                </a>
                <?php endif; ?>
                <a href="?tab=usher_chat" class="sidebar-link <?= $tab == 'usher_chat' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path></svg>
                    Usher Chat
                </a>
                <?php endif; ?>

                <?php if ($is_treasurer): ?>
                <div class="sidebar-label" style="padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: #10b981; margin-top: 10px;">Treasurer Panel</div>
                <a href="?tab=financials" class="sidebar-link <?= $tab == 'financials' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="2" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></rect><circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></circle><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9h.01M18 15h.01"></path></svg>
                    Financial Records
                </a>
                <?php endif; ?>

                <?php if (!empty($member['department']) && $member['department'] !== 'None' && !$is_treasurer && !$ctx_is_chairperson): ?>
                <a href="?tab=my_financials" class="sidebar-link <?= $tab == 'my_financials' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Financial Records
                    <?php if (!empty($tab_badges['my_financials'])): ?><span class="tab-notif-badge"><?= $tab_badges['my_financials'] > 99 ? '99+' : $tab_badges['my_financials'] ?></span><?php endif; ?>
                </a>
                <?php endif; ?>

                <!-- Construction Panel -->
                <?php if ($is_building_leader): ?>
                <div class="sidebar-label" style="padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: #8b5cf6; margin-top: 10px;">Construction Panel</div>
                <?php if ($is_building_chairperson): ?>
                <a href="?tab=church_buildings" class="sidebar-link <?= ($tab == 'church_buildings' || $tab == 'manage_building') ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    Manage Building
                </a>
                <?php endif; ?>
                <?php if ($is_building_secretary): ?>
                <a href="?tab=manage_announcement" class="sidebar-link <?= $tab == 'manage_announcement' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                    Manage Announcement
                </a>
                <?php endif; ?>
                <a href="?tab=building_leadership_chat" class="sidebar-link <?= $tab == 'building_leadership_chat' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path></svg>
                    Leadership Chat
                </a>
                <?php endif; ?>

                <!-- Village Panel -->
                <?php if ($member['is_village_leader'] == 1): ?>
                <div class="sidebar-label" style="padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: #f59e0b; margin-top: 10px;">Church Village Panel</div>
                <a href="?tab=manage_church_village" class="sidebar-link <?= $tab == 'manage_church_village' ? 'active' : '' ?>" style="position:relative;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Manage Church Village
                    <?php if (!empty($tab_badges['manage_church_village'])): ?><span class="tab-notif-badge"><?= $tab_badges['manage_church_village'] ?></span><?php endif; ?>
                </a>
                <?php endif; ?>

                <!-- Sunday School Panel -->
                <?php if ((in_array('Sunday School', $ss_leader_depts) || $is_ss_class_teacher)): ?>
                <div class="sidebar-label" style="padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: #0ea5e9; margin-top: 10px;">Sunday School Panel</div>
                <?php if (in_array('Sunday School', $ss_leader_depts)): ?>
                <a href="?tab=manage_department&leader_dept=Sunday+School" class="sidebar-link <?= ($tab == 'manage_department' && $managed_dept == 'Sunday School') ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path></svg>
                    Manage Sunday School
                    <?php if (!empty($tab_badges['manage_department']) && $managed_dept == 'Sunday School'): ?><span class="tab-notif-badge"><?= $tab_badges['manage_department'] > 99 ? '99+' : $tab_badges['manage_department'] ?></span><?php endif; ?>
                </a>
                <a href="?tab=appoint_leaders&leader_dept=Sunday+School" class="sidebar-link <?= ($tab == 'appoint_leaders' && $managed_dept == 'Sunday School') ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                    Appoint Leaders
                </a>
                <a href="?tab=leader_chat&leader_dept=Sunday+School" class="sidebar-link <?= ($tab == 'leader_chat' && $managed_dept == 'Sunday School') ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l.586-.586z"></path></svg>
                    Sunday School Leaders Chat
                </a>
                <?php endif; ?>
                <?php if ($is_ss_class_teacher): ?>
                <a href="?tab=manage_sunday_classes" class="sidebar-link <?= $tab == 'manage_sunday_classes' ? 'active' : '' ?>" style="position:relative;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5S19.832 5.477 21 6.253v13C19.832 18.477 18.246 18 16.5 18s-3.332.477-4.5 1.253"></path></svg>
                    Manage Sunday Classes
                    <?php if (!empty($tab_badges['manage_sunday_classes'])): ?><span class="tab-notif-badge"><?= $tab_badges['manage_sunday_classes'] > 99 ? '99+' : $tab_badges['manage_sunday_classes'] ?></span><?php endif; ?>
                </a>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Worship Panel -->
                <?php if ($is_worship_leader || $is_vice_worship_leader): ?>
                <div class="sidebar-label" style="padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: #d946ef; margin-top: 10px;">Worship Panel</div>
                <a href="?tab=manage_worshippers" class="sidebar-link <?= $tab == 'manage_worshippers' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Manage Worshippers
                </a>
                <a href="?tab=worship_leadership_chat" class="sidebar-link <?= $tab == 'worship_leadership_chat' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l.586-.586z"></path></svg>
                    Worship Leaders Chat
                    <?php if (!empty($tab_badges['worship_leadership_chat'])): ?><span style="background:var(--danger);color:white;font-size:0.65rem;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:auto;"><?= $tab_badges['worship_leadership_chat'] ?></span><?php endif; ?>
                </a>
                <?php endif; ?>

                <?php
                // Show My Worship Tasks for members who chose Worshipper as desired role
                $is_worshipper_member = ($member['desired_role_pref'] ?? '') === 'Worshipper';
                if ($is_worshipper_member && !$is_worship_leader && !$is_vice_worship_leader):
                    $my_pending_tasks = $conn->query("SELECT COUNT(*) as c FROM worship_tasks WHERE member_id=$member_id AND status='Pending'")->fetch_assoc()['c'];
                ?>
                <div class="sidebar-label" style="padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: #d946ef; margin-top: 10px;">My Worship</div>
                <a href="?tab=my_worship_tasks" class="sidebar-link <?= $tab == 'my_worship_tasks' ? 'active' : '' ?>" style="position:relative;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    My Worship Tasks
                    <?php if ($my_pending_tasks > 0): ?><span style="background:var(--danger);color:white;font-size:0.65rem;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:auto;"><?= $my_pending_tasks ?></span><?php endif; ?>
                </a>
                <?php endif; ?>

                <?php endif; // end has_church_village gate for nav links ?>
            </div>

            <div>
                <a href="?tab=settings" class="sidebar-link <?= $tab == 'settings' ? 'active' : '' ?>" style="border-top: 1px solid rgba(255,255,255,0.05); margin-top: 5px;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Account Settings
                </a>
            </div>
        </div>

        <div class="main-content">
            <?php if (!$setup_completed): ?>
            <div style="background: linear-gradient(90deg, #6366f1, #8b5cf6); color:#fff; padding: 16px 40px 20px 40px; margin: -40px -40px 30px -40px; display:flex; flex-direction:column; gap:12px;">
                <div style="display:flex; align-items:flex-start; gap:12px;">
                    <span style="font-size:1.4rem; flex-shrink:0;">&#128272;</span>
                    <span style="font-size:0.95rem; font-weight:700; line-height:1.6;"><strong>Action Required:</strong> Please go to <strong>Account Settings</strong> to create your unique username and upload a profile picture. Also click <strong>Church Village & Role</strong><?= ($member['department'] ?? '') === 'Sunday School' ? ' and <strong>Manage Classes</strong>' : '' ?> to finish setup and unlock all tabs.</span>
                </div>
                <div style="padding-left: 36px;">
                    <a href="?tab=settings" style="display:inline-block; background:rgba(255,255,255,0.25); color:#fff; border:1px solid rgba(255,255,255,0.6); padding: 8px 22px; border-radius: 8px; text-decoration:none; font-weight:700; font-size:0.9rem;">Go to Setup &rarr;</a>
                </div>
            </div>
            <?php endif; ?>
            <div class="topbar" style="justify-content: space-between;">
                <!-- Mobile Menu Button -->
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

                <div style="display: flex; align-items: center; gap: 12px; margin-left: auto;">
                    <!-- Profile photo + name -->
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <a href="?tab=settings" title="Go to Account Settings">
                            <img src="uploads/<?= htmlspecialchars($member['profile_picture'] ?? 'default_avatar.png') ?>" alt="Profile" style="width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary); transition: opacity 0.2s; cursor: zoom-in;" onmouseover="this.style.opacity=0.8" onmouseout="this.style.opacity=1" onclick="event.preventDefault(); viewProfileImage(this.src);">
                        </a>
                        <div style="line-height: 1.2;">
                            <span style="font-weight: 600; font-size: 0.95rem; color: var(--text-main); display: block;"><?= htmlspecialchars($member['first_name']) ?></span>
                            <span style="font-size: 0.75rem; color: var(--text-muted); background: var(--border-color); padding: 2px 6px; border-radius: 4px;"><?= htmlspecialchars(role_display_label($member['church_role'] ?? 'Member', $member['department'] ?? null, $member['gender'] ?? null)) ?></span>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div style="width: 1px; height: 36px; background: var(--border-color);"></div>

                    <!-- Dark mode toggle -->
                    <button id="themeToggle" class="icon-btn" title="Toggle Dark/Light Theme">
                        <svg id="moonIcon" width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                        <svg id="sunIcon" style="display:none;" width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </button>

                    <!-- Logout -->
                    <a href="logout.php" class="icon-btn" title="Sign Out" style="color: var(--danger); border-color: transparent; background: rgba(239, 68, 68, 0.1);">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    </a>

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
                                // Fetch recent notifications for dropdown
                                $drop_notifs = $conn->query("SELECT * FROM notifications WHERE user_id = $member_id AND user_type = 'member' ORDER BY created_at DESC LIMIT 8");
                                if ($drop_notifs && $drop_notifs->num_rows > 0):
                                    while ($dn = $drop_notifs->fetch_assoc()):
                                        $target_tab = notification_target_tab($dn['message'], 'member', 'dashboard');
                                        if ($is_choir_leader && $target_tab === 'choir_songs') {
                                            $target_tab = 'manage_songs';
                                        }
                                ?>
                                    <a href="mark_read_and_redirect.php?id=<?= $dn['id'] ?>&redirect=<?= urlencode($target_tab) ?>" style="display:block; padding:12px 18px; border-bottom:1px solid var(--border-color); text-decoration:none; background:<?= !$dn['is_read'] ? 'rgba(37,99,235,0.06)' : 'transparent' ?>; transition:background 0.15s;" onmouseover="this.style.background='rgba(37,99,235,0.1)'" onmouseout="this.style.background='<?= !$dn['is_read'] ? 'rgba(37,99,235,0.06)' : 'transparent' ?>'">
                                        <div style="display:flex; align-items:flex-start; gap:10px;">
                                            <div style="width:8px; height:8px; border-radius:50%; background:<?= !$dn['is_read'] ? 'var(--danger)' : 'var(--border-color)' ?>; margin-top:5px; flex-shrink:0;"></div>
                                            <div>
                                                <p style="margin:0; font-size:0.85rem; color:var(--text-main); line-height:1.4;"><?= htmlspecialchars($dn['message']) ?></p>
                                                <small style="color:var(--text-muted); font-size:0.75rem;"><?= date('M j, g:i A', strtotime($dn['created_at'])) ?></small>
                                                <?php if (!$dn['is_read']): ?>
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
                                <a href="?action=mark_all_read&tab=<?= htmlspecialchars($tab) ?>" style="flex:1; padding:12px; text-align:center; color:var(--text-muted); font-size:0.8rem; text-decoration:none; background:var(--bg-main);" onmouseover="this.style.background='rgba(37,99,235,0.06)'; this.style.color='var(--primary)';" onmouseout="this.style.background='var(--bg-main)'; this.style.color='var(--text-muted)';">Mark all as read</a>
                                <div style="width:1px; background:var(--border-color);"></div>
                                <a href="?action=delete_all_notifications&tab=<?= htmlspecialchars($tab) ?>" onclick="return confirm('Delete all your notifications?')" style="flex:1; padding:12px; text-align:center; color:var(--danger); font-size:0.8rem; font-weight:700; text-decoration:none; background:var(--bg-main);" onmouseover="this.style.background='rgba(239,68,68,0.08)'" onmouseout="this.style.background='var(--bg-main)'">Delete all</a>
                                <div style="width:1px; background:var(--border-color);"></div>
                                <a href="?tab=notifications" style="flex:1; padding:12px; text-align:center; color:var(--primary); font-size:0.875rem; font-weight:600; text-decoration:none; background:var(--bg-main);" onmouseover="this.style.background='rgba(37,99,235,0.06)'" onmouseout="this.style.background='var(--bg-main)'">View All &rarr;</a>
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
                            const d = document.getElementById('notifDropdown');
                            if(d) d.style.display = 'none';
                        }
                    });
                    </script>

                </div>
            </div>
            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
            <?php elseif(isset($_GET['error'])): ?>
                <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
            <?php endif; ?>

            <?php if ($tab == 'dashboard'): ?>
                <div class="page-header">
                    <?php if (!empty($welcome_role_lines)): ?>
                        <h1>Welcome <?= htmlspecialchars(ucwords(strtolower($member['first_name']))) ?> to Munyari Church 🙏</h1>
                        <div style="margin-top: 10px; display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-family: 'Inter', 'Segoe UI', sans-serif; font-size: 1rem; font-weight: 700; color: #111; letter-spacing: 0.01em;">
                        <?php foreach ($welcome_role_lines as $i => $wrl): ?>
                            <?php if ($i > 0): ?>
                                <span style="color: #555; font-weight: 400; font-size: 1.2rem; line-height: 1;">·</span>
                            <?php endif; ?>
                            <span style="color: #111; font-weight: 800;"><?= htmlspecialchars($wrl['role']) ?></span>
                            <span style="color: #444; font-weight: 500; font-size: 0.9rem; font-style: italic;">Under</span>
                            <span style="color: #111; font-weight: 700;"><?= htmlspecialchars($wrl['scope']) ?></span>
                        <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <h1>Welcome <?= htmlspecialchars(ucwords(strtolower($member['first_name']))) ?> to Munyari Church 🙏</h1>
                        <p>Welcome to your personal portal. Here is what's happening in your department.</p>
                        <?php if (!empty($member_home_department) && $member_home_department !== 'None'): ?>
                        <div style="margin-top: 10px; display: flex; align-items: center; gap: 6px; font-family: 'Inter', 'Segoe UI', sans-serif; font-size: 1rem; font-weight: 700; color: #111;">
                            <span style="color: #444; font-weight: 500; font-size: 0.9rem; font-style: italic;">Member Of</span>
                            <span style="color: #111; font-weight: 800;"><?= htmlspecialchars(ucwords(strtolower($member_home_department))) ?></span>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if (!$has_church_village): ?>
                <div class="content-card" style="border:2px solid #6366f1; background:linear-gradient(135deg,rgba(99,102,241,0.08),rgba(37,99,235,0.04)); margin-bottom:20px;">
                    <div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;">
                        <div style="width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#6366f1,#3b82f6);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div>
                            <h3 style="margin:0; color:#6366f1; font-size:1.05rem;">⚠️ One More Step to Get Started!</h3>
                            <p style="margin:4px 0 0; font-size:0.88rem; color:var(--text-muted);">Please select your church village to unlock all features of your dashboard.</p>
                        </div>
                    </div>

                <a href="?tab=desired_roles" style="display:inline-flex; align-items:center; gap:8px; background:linear-gradient(135deg,#6366f1,#3b82f6); color:white; padding:11px 24px; border-radius:10px; text-decoration:none; font-weight:600; font-size:0.9rem;">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                        Choose Your Church Village &rarr;
                    </a>
                <?php endif; ?>
                <?php render_leadership_hierarchy_card($conn, $active_dashboard_dept ?: ($member['department'] ?? '')); ?>
                <?php if (($member['department'] ?? '') === 'Sunday School' && !empty($member['sunday_school_class'])): ?>
                    <?php render_ss_teacher_card($conn, $member['sunday_school_class']); ?>
                <?php endif; ?>
                <?php render_building_leader_profiles_card($conn); ?>

                <div class="content-card" style="max-width: 600px;">
                    <h2>Membership Details</h2>
                    <p style="font-size: 1.1rem; color: var(--text-main); margin-top: 15px;">
                        <strong>Current Role:</strong> <span class="badge" style="background: rgba(37,99,235,0.15); color: var(--primary);"><?= htmlspecialchars($member['church_role'] ?? 'Member') ?></span>
                    </p>
                    <hr style="border:0; border-top:1px solid var(--border-color); margin: 20px 0;">

                    <?php if ($member['is_approved']): ?>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div style="width: 50px; height: 50px; background: rgba(16,185,129,0.1); border-radius: 50%; display: flex; align-items:center; justify-content:center; color: #10b981;">
                                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                            <div>
                                <h3 style="color: #059669; font-size: 1.2rem;">Approved & Active</h3>
                                <p style="color: var(--text-muted); margin-top: 5px;">You are a fully approved member of Munyari Church.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="content-card" style="max-width: 700px;">
                    <h3>Contact Information</h3>
                    <p style="margin-bottom:10px;"><strong>Phone:</strong> <?= htmlspecialchars($member['phone']) ?></p>
                    <p style="margin-bottom:10px;"><strong>Address:</strong> <?= htmlspecialchars($member['address']) ?></p>
                    <p style="margin-bottom:10px;"><strong>Department:</strong> <?= htmlspecialchars($member['department'] ?? 'None') ?></p>
                    <p><strong>Member Since:</strong> <?= date('F j, Y', strtotime($member['reg_date'])) ?></p>
                </div>

            <?php elseif ($tab == 'announcements'): ?>
                <div class="page-header">
                    <h1>Community Announcements</h1>
                    <p>Read the latest announcements from the Senior Church Elders and your Department Leaders.</p>
                </div>

                <div class="content-card" style="max-width: 800px; margin: 0 auto; background: transparent; box-shadow: none; padding: 0;">
                    <!-- General Church Secretary Announcements -->
                    <?php if ($general_church_announcements && $general_church_announcements->num_rows > 0): ?>
                        <h2 style="font-size: 1.1rem; color: var(--text-muted); margin-bottom: 15px; text-transform: uppercase;">General Church</h2>
                        <?php while ($gann = $general_church_announcements->fetch_assoc()): ?>
                            <div class="content-card" style="margin-bottom: 20px; border-left: 4px solid var(--primary);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                    <div>
                                        <h3 style="color: var(--primary); margin: 0;"><?= htmlspecialchars($gann['first_name'] . ' ' . $gann['last_name']) ?></h3>
                                        <span style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($gann['church_role']) ?></span>
                                    </div>
                                    <small style="color: var(--text-muted);"><?= date('M j, Y g:i A', strtotime($gann['created_at'])) ?></small>
                                </div>
                                <p style="line-height: 1.6; white-space: pre-wrap;"><?= htmlspecialchars($gann['message']) ?></p>
                                <?= render_announcement_image($gann['image_path'] ?? '') ?>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>

                    <!-- Department Announcements -->
                    <?php if ($dept_announcements && $dept_announcements->num_rows > 0): ?>
                        <h2 style="font-size: 1.1rem; color: var(--text-muted); margin-bottom: 15px; text-transform: uppercase;"><?= htmlspecialchars($member['department']) ?> Department</h2>
                        <?php while ($dann = $dept_announcements->fetch_assoc()): ?>
                            <div class="content-card" style="margin-bottom: 20px; border-left: 4px solid var(--secondary);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                    <div>
                                        <h3 style="color: var(--secondary); margin: 0;"><?= htmlspecialchars($dann['first_name'] . ' ' . $dann['last_name']) ?></h3>
                                        <span style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars(clean_role_display($dann['church_role'])) ?></span>
                                    </div>
                                    <small style="color: var(--text-muted);"><?= date('M j, Y g:i A', strtotime($dann['created_at'])) ?></small>
                                </div>
                                <p style="line-height: 1.6; white-space: pre-wrap;"><?= htmlspecialchars($dann['message']) ?></p>
                                <?= render_announcement_image($dann['image_path'] ?? '') ?>
                            </div>
                        <?php endwhile; ?>
                        <!-- Reset pointer so other tabs can use it if needed -->
                        <?php $dept_announcements->data_seek(0); ?>
                    <?php endif; ?>

                    <!-- Elder Announcements -->
                    <?php if ($all_elder_announcements && $all_elder_announcements->num_rows > 0): ?>
                        <h2 style="font-size: 1.1rem; color: var(--text-muted); margin-bottom: 15px; margin-top: 20px; text-transform: uppercase;">Elder Announcements</h2>
                        <?php while ($eann = $all_elder_announcements->fetch_assoc()): ?>
                            <div class="content-card" style="margin-bottom: 20px; border-left: 4px solid var(--warning);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                    <div>
                                        <h3 style="color: var(--warning); margin: 0;"><?= htmlspecialchars($eann['first_name'] . ' ' . $eann['last_name']) ?></h3>
                                        <span style="font-size: 0.8rem; color: var(--text-muted);">Senior Church Elder</span>
                                    </div>
                                    <small style="color: var(--text-muted);"><?= date('M j, Y g:i A', strtotime($eann['created_at'])) ?></small>
                                </div>
                                <p style="line-height: 1.6; white-space: pre-wrap;"><?= htmlspecialchars($eann['message']) ?></p>
                                <?= render_announcement_image($eann['image_path'] ?? '') ?>
                            </div>
                        <?php endwhile; ?>
                        <?php $all_elder_announcements->data_seek(0); ?>
                    <?php endif; ?>

                    <!-- Usher Announcements -->
                    <?php if ($all_usher_announcements && $all_usher_announcements->num_rows > 0): ?>
                        <h2 style="font-size: 1.1rem; color: var(--text-muted); margin-bottom: 15px; margin-top: 20px; text-transform: uppercase;">Usher Announcements</h2>
                        <?php while ($uann = $all_usher_announcements->fetch_assoc()): ?>
                            <div class="content-card" style="margin-bottom: 20px; border-left: 4px solid #10b981;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                    <div>
                                        <h3 style="color: #10b981; margin: 0;"><?= htmlspecialchars($uann['title']) ?></h3>
                                        <span style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($uann['first_name'] . ' ' . $uann['last_name']) ?> (Head Usher)</span>
                                    </div>
                                    <small style="color: var(--text-muted);"><?= date('M j, Y g:i A', strtotime($uann['created_at'])) ?></small>
                                </div>
                                <p style="line-height: 1.6; white-space: pre-wrap;"><?= htmlspecialchars($uann['message']) ?></p>
                                <?= render_announcement_image($uann['image_path'] ?? '') ?>
                            </div>
                        <?php endwhile; ?>
                        <?php $all_usher_announcements->data_seek(0); ?>
                    <?php endif; ?>

                    <?php if ($is_youth_advisor_scope): ?>
                        <?php
                        $youth_advisor_announcements = $conn->query("
                            SELECT da.*, m.first_name, m.last_name, m.church_role
                            FROM department_announcements da
                            JOIN members m ON da.secretary_id = m.id
                            WHERE da.department = 'Youths'
                            ORDER BY da.created_at DESC
                        ");
                        ?>
                        <?php if ($youth_advisor_announcements && $youth_advisor_announcements->num_rows > 0): ?>
                            <h2 style="font-size: 1.1rem; color: var(--text-muted); margin-bottom: 15px; margin-top: 20px; text-transform: uppercase;">Youth Department</h2>
                            <?php while ($yann = $youth_advisor_announcements->fetch_assoc()): ?>
                                <div class="content-card" style="margin-bottom: 20px; border-left: 4px solid var(--primary);">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                        <div>
                                            <h3 style="color: var(--primary); margin: 0;"><?= htmlspecialchars($yann['first_name'] . ' ' . $yann['last_name']) ?></h3>
                                            <span style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($yann['church_role']) ?></span>
                                        </div>
                                        <small style="color: var(--text-muted);"><?= date('M j, Y g:i A', strtotime($yann['created_at'])) ?></small>
                                    </div>
                                    <p style="line-height: 1.6; white-space: pre-wrap;"><?= htmlspecialchars($yann['message']) ?></p>
                                    <?= render_announcement_image($yann['image_path'] ?? '') ?>
                                </div>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Elder Announcements -->
                    <h2 style="font-size: 1.1rem; color: var(--text-muted); margin-bottom: 15px; margin-top: 20px; text-transform: uppercase;">General Church Announcements</h2>
                    <?php if ($all_elder_announcements->num_rows > 0): ?>
                        <?php while ($ann = $all_elder_announcements->fetch_assoc()): ?>
                            <div class="content-card" style="margin-bottom: 20px; border-left: 4px solid var(--primary);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                    <h3 style="color: var(--primary);">Elder <?= htmlspecialchars($ann['first_name'] . ' ' . $ann['last_name']) ?></h3>
                                    <small style="color: var(--text-muted);"><?= date('M j, Y g:i A', strtotime($ann['created_at'])) ?></small>
                                </div>
                                <p style="line-height: 1.6; white-space: pre-wrap;"><?= htmlspecialchars($ann['message']) ?></p>
                                <?= render_announcement_image($ann['image_path'] ?? '') ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="content-card text-center" style="padding: 40px;">
                            <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--text-muted); margin: 0 auto 15px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                            <p style="color: var(--text-muted);">No general announcements have been posted yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pastor General Announcements shown to all members -->
                <?php
                $conn->query("CREATE TABLE IF NOT EXISTS pastor_announcements (id INT AUTO_INCREMENT PRIMARY KEY, pastor_id INT NOT NULL, message TEXT NOT NULL, image_path VARCHAR(255) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
                $pastor_gen_anns = $conn->query("SELECT pa.*, p.first_name, p.last_name FROM pastor_announcements pa JOIN pastors p ON pa.pastor_id = p.id ORDER BY pa.created_at DESC LIMIT 20");
                if ($pastor_gen_anns && $pastor_gen_anns->num_rows > 0): ?>
                <div style="max-width:800px;margin:24px auto 0;">
                    <h2 style="font-size:1.1rem;color:var(--text-muted);margin-bottom:15px;text-transform:uppercase;">📢 From The Pastor</h2>
                    <?php while ($pa = $pastor_gen_anns->fetch_assoc()): ?>
                        <div class="content-card" style="margin-bottom:20px;border-left:4px solid #10b981;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                                <div>
                                    <h3 style="color:#10b981;margin:0;">Pastor <?= htmlspecialchars($pa['first_name'] . ' ' . $pa['last_name']) ?></h3>
                                    <span style="font-size:0.8rem;color:var(--text-muted);">General Church Announcement</span>
                                </div>
                                <small style="color:var(--text-muted);"><?= date('M j, Y g:i A', strtotime($pa['created_at'])) ?></small>
                            </div>
                            <p style="line-height:1.6;white-space:pre-wrap;"><?= htmlspecialchars($pa['message']) ?></p>
                            <?php if (!empty($pa['image_path'])): ?>
                                <a href="<?= htmlspecialchars($pa['image_path']) ?>" target="_blank" style="display:block;margin-top:12px;">
                                    <img src="<?= htmlspecialchars($pa['image_path']) ?>" alt="Announcement" style="width:100%;max-height:360px;object-fit:cover;border-radius:10px;border:1px solid var(--border-color);">
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
                <?php endif; ?>


            <?php elseif ($tab == 'choir_songs' && !empty($member['department']) && $member['department'] !== 'None'): ?>
                <?php
                $choir_dept = $conn->real_escape_string($member['department']);
                $dept_condition = "department='$choir_dept'";
                $ca_dept_condition = "ca.department='$choir_dept'";
                if ($is_youth_advisor_scope && strtolower($choir_dept) !== 'youths' && strtolower($choir_dept) !== 'youth ministry') {
                    $dept_condition = "(department='$choir_dept' OR department='Youths' OR department='Youth Ministry')";
                    $ca_dept_condition = "(ca.department='$choir_dept' OR ca.department='Youths' OR ca.department='Youth Ministry')";
                }

                $choir_song_plans = $conn->query("SELECT * FROM choir_song_plans WHERE $dept_condition ORDER BY song_date ASC, song_time ASC, created_at DESC LIMIT 30");
                $choir_updates = $conn->query("
                    SELECT ca.*, m.first_name, m.last_name
                    FROM choir_announcements ca
                    JOIN members m ON ca.choir_leader_id = m.id
                    WHERE $ca_dept_condition
                    ORDER BY ca.created_at DESC
                    LIMIT 30
                ");
                ?>
                <div class="page-header">
                    <h1><?= htmlspecialchars($member['department']) ?> Choir Songs</h1>
                    <p>Song plans and choir announcements from your department Choir Leader.</p>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:24px;align-items:start;">
                    <div class="content-card">
                        <h2>Upcoming Songs</h2>
                        <?php if ($choir_song_plans && $choir_song_plans->num_rows > 0): ?>
                            <div style="display:flex;flex-direction:column;gap:12px;margin-top:12px;">
                                <?php while($song = $choir_song_plans->fetch_assoc()): ?>
                                    <div style="padding:15px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-main);">
                                        <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;">
                                            <div>
                                                <strong style="color:var(--text-main);"><?= htmlspecialchars($song['song_title']) ?></strong>
                                                <?php if ($is_youth_advisor_scope): ?>
                                                    <span class="badge" style="background:var(--bg-main);color:<?= strtolower($song['department']) === 'youths' ? 'var(--primary)' : 'var(--text-muted)' ?>;border:1px solid <?= strtolower($song['department']) === 'youths' ? 'var(--primary)' : 'var(--border-color)' ?>;margin-left:6px;font-size:0.7rem;padding:2px 6px;"><?= htmlspecialchars($song['department']) ?></span>
                                                <?php endif; ?>
                                                <div style="color:var(--text-muted);font-size:0.85rem;margin-top:4px;"><?= htmlspecialchars($song['song_type']) ?></div>
                                            </div>
                                            <span class="badge" style="background:rgba(37,99,235,0.12);color:var(--primary);"><?= date('M j, Y', strtotime($song['song_date'])) ?><?= $song['song_time'] ? ' ' . date('g:i A', strtotime($song['song_time'])) : '' ?></span>
                                        </div>
                                        <?php if (!empty($song['notes'])): ?>
                                            <p style="margin:10px 0 0;color:var(--text-muted);white-space:pre-wrap;"><?= htmlspecialchars($song['notes']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p style="color:var(--text-muted);">No songs have been planned yet.</p>
                        <?php endif; ?>
                    </div>

                    <div class="content-card">
                        <h2>Choir Announcements</h2>
                        <?php if ($choir_updates && $choir_updates->num_rows > 0): ?>
                            <?php while($cu = $choir_updates->fetch_assoc()): ?>
                                <div style="padding:14px 0;border-bottom:1px solid var(--border-color);">
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;gap:12px;">
                                        <div>
                                            <strong><?= htmlspecialchars($cu['first_name'] . ' ' . $cu['last_name']) ?></strong>
                                            <?php if ($is_youth_advisor_scope): ?>
                                                <span class="badge" style="background:var(--bg-main);color:<?= strtolower($cu['department']) === 'youths' ? 'var(--primary)' : 'var(--text-muted)' ?>;border:1px solid <?= strtolower($cu['department']) === 'youths' ? 'var(--primary)' : 'var(--border-color)' ?>;margin-left:6px;font-size:0.7rem;padding:2px 6px;"><?= htmlspecialchars($cu['department']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <small style="color:var(--text-muted);"><?= date('M j, Y g:i A', strtotime($cu['created_at'])) ?></small>
                                    </div>
                                    <p style="margin:0;line-height:1.6;white-space:pre-wrap;"><?= htmlspecialchars($cu['message']) ?></p>
                                    <?= render_announcement_image($cu['image_path'] ?? '') ?>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="color:var(--text-muted);">No choir announcements posted yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($tab == 'received_financials' && $is_leader): ?>
                <?php
                $_chair_finance_depts = [];
                foreach ($chairperson_roles_by_dept as $chair_dept => $chair_roles) {
                    if (!empty(array_intersect($member_roles_normalized, $chair_roles))) {
                        $_chair_finance_depts[] = $chair_dept;
                    }
                }
                $active_received_dept = $_GET['leader_dept'] ?? (in_array($active_dashboard_dept, $_chair_finance_depts) ? $active_dashboard_dept : ($_chair_finance_depts[0] ?? ''));
                $can_view_received_financials = false;
                foreach ($_chair_finance_depts as $_chair_finance_dept) {
                    if (department_matches($active_received_dept, $_chair_finance_dept)) {
                        $can_view_received_financials = true;
                        break;
                    }
                }
                ?>

                <?php if (!$can_view_received_financials): ?>
                    <div class="page-header">
                        <h1>Received Financial Records</h1>
                        <p>You do not have chairperson access to received financial records.</p>
                    </div>
                <?php else: ?>
                    <div class="page-header">
                        <h1>Received Financial Records</h1>
                        <p>Transactions sent by the treasurer for <strong><?= htmlspecialchars($active_received_dept) ?></strong>.</p>
                    </div>

                    <?php if (isset($_GET['success'])): ?><div class="alert alert-success" style="margin-bottom:16px;"><?= htmlspecialchars($_GET['success']) ?></div><?php endif; ?>
                    <?php if (isset($_GET['error'])): ?><div class="alert alert-danger" style="margin-bottom:16px;"><?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>

                    <?php if (count($_chair_finance_depts) > 1): ?>
                    <div class="content-card" style="margin-bottom: 20px;">
                        <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                            <input type="hidden" name="tab" value="received_financials">
                            <label style="font-weight:600;margin:0;">Viewing Records For:</label>
                            <select name="leader_dept" class="form-control" style="max-width:300px;margin:0;" onchange="this.form.submit()">
                                <?php foreach ($_chair_finance_depts as $finance_dept): ?>
                                    <option value="<?= htmlspecialchars($finance_dept) ?>" <?= department_matches($active_received_dept, $finance_dept) ? 'selected' : '' ?>><?= htmlspecialchars($finance_dept) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <?php endif; ?>

                    <div class="content-card" style="border-top: 4px solid var(--primary);">
                        <h2 style="margin-bottom: 15px;">Received Treasurer Records</h2>
                        <p style="color: var(--text-muted); margin-bottom: 20px;">Confirm and disburse each complete transaction sent by your department treasurer.</p>

                        <?php
                        $received_records = $conn->query("
                            SELECT dft.*, fr.id AS financial_record_id, fr.is_chair_confirmed, fr.chair_confirmed_at, fr.chair_disbursement_note, fr.description AS record_description, fr.record_date, cb.first_name AS confirmed_first, cb.last_name AS confirmed_last, m.first_name, m.last_name
                            FROM department_funds_transfer dft
                            JOIN financial_records fr ON dft.financial_record_id = fr.id
                            JOIN members m ON fr.recorded_by = m.id
                            LEFT JOIN members cb ON fr.chair_confirmed_by = cb.id
                            WHERE " . department_match_sql($conn, 'fr.department', $active_received_dept) . "
                              AND dft.transfer_type='To Chairperson'
                            ORDER BY fr.is_chair_confirmed ASC, dft.created_at DESC
                        ");
                        if ($received_records && $received_records->num_rows > 0):
                        ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date Sent</th>
                                        <th>Treasurer</th>
                                        <th>Source / Description</th>
                                        <th>Amount (KSh)</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($rr = $received_records->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= date('M j, Y', strtotime($rr['created_at'])) ?></td>
                                        <td><?= htmlspecialchars($rr['first_name'] . ' ' . $rr['last_name']) ?></td>
                                        <td>
                                            <div><?= htmlspecialchars($rr['record_description'] ?: $rr['description']) ?></div>
                                            <?php if (!empty($rr['chair_disbursement_note'])): ?>
                                                <small style="color: var(--text-muted);">Chair note: <?= htmlspecialchars($rr['chair_disbursement_note']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-weight: 700; color: #10b981;">KSh <?= number_format($rr['amount'], 2) ?></td>
                                        <td>
                                            <?php if (!empty($rr['is_chair_confirmed'])): ?>
                                                <span class="badge" style="background: rgba(16,185,129,0.12); color: #10b981;">Confirmed & Disbursed</span>
                                                <?php if (!empty($rr['chair_confirmed_at'])): ?>
                                                    <div style="font-size:0.8rem;color:var(--text-muted);margin-top:4px;">By <?= htmlspecialchars(trim(($rr['confirmed_first'] ?? '') . ' ' . ($rr['confirmed_last'] ?? ''))) ?> on <?= date('M j, Y', strtotime($rr['chair_confirmed_at'])) ?></div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge" style="background: rgba(245,158,11,0.12); color: #f59e0b;">Pending Confirmation</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (empty($rr['is_chair_confirmed'])): ?>
                                                <form method="POST" action="member_action.php?action=disperse_funds" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0;">
                                                    <input type="hidden" name="department" value="<?= htmlspecialchars($active_received_dept) ?>">
                                                    <input type="hidden" name="financial_record_id" value="<?= (int)$rr['financial_record_id'] ?>">
                                                    <input type="text" name="description" class="form-control" placeholder="Optional note" style="min-width:180px;margin:0;">
                                                    <button type="submit" class="btn-submit" style="margin:0;padding:7px 12px;font-size:0.85rem;" onclick="return confirm('Confirm and disburse this whole transaction of KSh <?= number_format($rr['amount'], 2) ?>?');">Confirm & Disburse</button>
                                                </form>
                                            <?php else: ?>
                                                <span style="color:var(--text-muted);font-size:0.9rem;">Completed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                            <p style="color: var(--text-muted);">No financial records have been received from the treasurer yet.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($tab == 'manage_department' && $is_leader): ?>
                <?php
                // Build list of all departments this leader can manage
                $_leader_all_depts = [];
                if (!empty($leadership_managed_dept)) $_leader_all_depts[] = $leadership_managed_dept;
                foreach ($ss_leader_depts as $ssd) {
                    if (!in_array($ssd, $_leader_all_depts)) $_leader_all_depts[] = $ssd;
                }
                $active_leader_dept = $_GET['leader_dept'] ?? (in_array($active_dashboard_dept, $_leader_all_depts) ? $active_dashboard_dept : ($_leader_all_depts[0] ?? $managed_dept));
                $managed_dept_display = $active_leader_dept ?: $managed_dept;
                ?>
                <div class="page-header">
                    <h1><?= htmlspecialchars($managed_dept_display) ?> Department</h1>
                    <?php if (count($_leader_all_depts) > 1): ?>
                    <p>You lead multiple departments. Select one to manage:</p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;margin-bottom:15px;">
                        <?php foreach ($_leader_all_depts as $ld): ?>
                            <a href="?tab=manage_department&leader_dept=<?= urlencode($ld) ?>"
                               class="btn-sm<?= (strtolower($ld) === strtolower($managed_dept_display)) ? ' btn-success' : '' ?>"
                               style="text-decoration:none;<?= (strtolower($ld) !== strtolower($managed_dept_display)) ? 'background:var(--bg-card);border:1px solid var(--border-color);color:var(--text-main);' : '' ?>">
                               <?= htmlspecialchars($ld) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p>Overview of all members registered under your department.</p>
                    <?php endif; ?>
                </div>

                <?php render_leadership_hierarchy_card($conn, $managed_dept_display); ?>

                <?php
                $managed_dept = $managed_dept_display;
                $esc_dept = $conn->real_escape_string($managed_dept);
                $member_where = $managed_dept === 'Youths'
                    ? "(department = '$esc_dept' OR LOWER(church_role) LIKE '%mama youth%' OR LOWER(church_role) LIKE '%baba youth%')"
                    : "department = '$esc_dept'";
                $dept_members = $conn->query("
                    SELECT *, " . role_rank_case_sql('church_role') . " AS role_rank
                    FROM members
                    WHERE $member_where AND is_approved = 1
                    ORDER BY role_rank ASC, first_name ASC
                ");
                ?>
                
                <?php
                if ($dept_members) $dept_members->data_seek(0);
                ?>

<?php if ($managed_dept === 'Sunday School'): ?>
                <?php
                $classes = ['Little Angels', 'Champions', 'Battalion', 'Conquerors'];
                $class_counts = [];
                foreach ($classes as $cl_name) {
                    $cl_safe_q = $conn->real_escape_string($cl_name);
                    $res = $conn->query("SELECT COUNT(*) as cnt FROM members WHERE department = 'Sunday School' AND sunday_school_class = '$cl_safe_q' AND is_approved = 1");
                    $class_counts[$cl_name] = $res ? $res->fetch_assoc()['cnt'] : 0;
                }
                $total_ss = $conn->query("SELECT COUNT(*) as cnt FROM members WHERE department = 'Sunday School' AND is_approved = 1")->fetch_assoc()['cnt'];
                ?>
                <?php if (isset($_GET['success'])): ?><div class="alert alert-success" style="margin-bottom:16px;"><?= htmlspecialchars($_GET['success']) ?></div><?php endif; ?>
                <?php if (isset($_GET['error'])): ?><div class="alert alert-danger" style="margin-bottom:16px;"><?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>
                <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                    <div>
                        <h1>Manage Sunday School</h1>
                        <p>Register new Sunday School members and view class rosters.</p>
                    </div>
                    <a href="#assignSsClassLeader" class="btn-submit" style="width:auto;height:auto;padding:9px 14px;text-decoration:none;font-size:0.86rem;margin:0;">Assign Leader</a>
                </div>
                <div id="assignSsClassLeader" class="content-card" style="margin-bottom:24px;border-left:4px solid #0ea5e9;">
                    <h2 style="margin-bottom:8px;">Request Sunday School Class Leader</h2>
                    <p style="margin:0 0 14px;color:var(--text-muted);font-size:0.9rem;">Your request will wait for pastor or admin approval before the teacher becomes active.</p>
                    <form method="POST" action="?tab=manage_department" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;align-items:end;">
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
                        <button type="submit" class="btn-submit" style="width:auto;margin:0;">Send Request</button>
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
                $my_pending_leader_requests = $conn->query("
                    SELECT scl.*, m.first_name, m.last_name, m.profile_picture
                    FROM sunday_school_class_leaders scl
                    JOIN members m ON scl.leader_id = m.id
                    WHERE scl.assigned_by_type = 'member' AND scl.assigned_by_id = $member_id AND scl.status = 'Pending'
                    ORDER BY scl.requested_at ASC
                ");
                ?>
                <?php if ($my_pending_leader_requests && $my_pending_leader_requests->num_rows > 0): ?>
                <div class="content-card" style="margin-bottom:24px;border-left:4px solid #f59e0b;">
                    <h2 style="margin-bottom:14px;">Your Pending Leader Requests</h2>
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>Requested Leader</th><th>Class</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php while($lr = $my_pending_leader_requests->fetch_assoc()): ?>
                                <tr>
                                    <td style="display:flex;align-items:center;gap:10px;font-weight:650;"><img src="uploads/<?= htmlspecialchars($lr['profile_picture'] ?? 'default_avatar.png') ?>" alt="Profile" style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:1px solid var(--border-color);"><?= htmlspecialchars($lr['first_name'] . ' ' . $lr['last_name']) ?></td>
                                    <td><span class="badge approved"><?= htmlspecialchars($lr['class_name']) ?></span></td>
                                    <td><span class="badge" style="background:rgba(245,158,11,0.12);color:#f59e0b;">Pending pastor/admin approval</span></td>
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
                            <p style="margin:4px 0 0;color:var(--text-muted);font-size:0.9rem;">Review Sunday School members who want to move from one class to another.</p>
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
                                        <a href="member_dashboard.php?tab=manage_department&action=approve_ss_class_request&id=<?= (int)$req['id'] ?>" class="btn-action btn-approve" style="text-decoration:none;" onclick="return confirm('Approve this class change?')">Approve</a>
                                        <a href="member_dashboard.php?tab=manage_department&action=reject_ss_class_request&id=<?= (int)$req['id'] ?>" class="btn-action btn-reject" style="text-decoration:none;" onclick="return confirm('Reject this class change?')">Reject</a>
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
                <div class="dashboard-grid" style="margin-bottom:30px;">
                    <div class="stat-card">
                        <h3>Total Sunday School</h3>
                        <div class="value" style="color:var(--primary);"><?= $total_ss ?></div>
                    </div>
                    <?php foreach ($classes as $cl_name): ?>
                    <div class="stat-card">
                        <h3><?= htmlspecialchars($cl_name) ?></h3>
                        <div class="value" style="color:<?= $cl_name === 'Battalion' ? '#f59e0b' : ($cl_name === 'Conquerors' ? '#10b981' : ($cl_name === 'Champions' ? '#ef4444' : '#6366f1')) ?>;"><?= $class_counts[$cl_name] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="content-card" style="margin-bottom:30px;">
                    <h2 style="margin-bottom:20px;">Register New Sunday School Member</h2>
                    <script>
                    function seqUnlock(currentId, nextId) {
                        const current = document.getElementById(currentId);
                        const next    = document.getElementById(nextId);
                        if (!current || !next) return;
                        const filled = current.tagName === 'SELECT'
                            ? current.value !== '
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
                                setTimeout(() => { input.value = input.value.replace(/[^A-Za-z\s,.-]/g, '); }, 300);
                            } else {
                                errorMsg.innerText = '';
                                input.setCustomValidity(');
                            }
                        } else if (type === 'phone') {
                            if (/[^\d]/.test(input.value)) {
                                errorMsg.innerText = 'Only digits allowed.';
                                input.setCustomValidity('Invalid');
                                setTimeout(() => { input.value = input.value.replace(/[^\d]/g, '); }, 300);
                            } else {
                                errorMsg.innerText = '';
                                input.setCustomValidity(');
                            }
                        }
                    }
                    </script>
                    <form method="POST" action="?tab=manage_department" id="ssRegForm" onsubmit="this.querySelector('button[type=submit]').disabled=true;">
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
                                <label>Phone Number <span style="color:var(--text-muted); font-weight:normal; font-size:0.8rem;">(Optional)</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#f59e0b; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg></span>
                                    <input type="tel" id="ss_phone" name="phone" class="form-control" placeholder="10-digit number (Optional)" pattern="\d{10}" maxlength="10" oninput="validateInput(this,'phone');" style="padding-left:36px;" disabled>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Login Password <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#ef4444; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></span>
                                    <input type="password" name="password" id="ss_password" class="form-control" placeholder="At least 6 characters" required style="padding-left:36px; padding-right:46px;" minlength="6" oninput="seqUnlock('ss_password','ss_submitBtn')" disabled>
                                    <span onclick="var i=document.getElementById('ss_password');i.type=i.type==='password'?'text':'password'" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--text-muted);">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <button type="submit" id="ss_submitBtn" class="btn-submit" style="margin-top:10px; display:flex; align-items:center; gap:8px; width:auto; padding:0 28px;" disabled>
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                            Register Member
                        </button>
                    </form>
                </div>
                <?php foreach ($classes as $cl_name):
                    $cl_safe_q  = $conn->real_escape_string($cl_name);
                    $cl_color   = $cl_name === 'Battalion' ? '#f59e0b' : ($cl_name === 'Conquerors' ? '#10b981' : ($cl_name === 'Champions' ? '#ef4444' : '#6366f1'));
                    $cl_members = $conn->query("SELECT id, first_name, last_name, phone, gender FROM members WHERE department = 'Sunday School' AND sunday_school_class = '$cl_safe_q' AND is_approved = 1 ORDER BY first_name ASC");
                ?>
                <div class="content-card" style="margin-bottom:24px; border-left:4px solid <?= $cl_color ?>;">
                    <h2 style="color:<?= $cl_color ?>; margin-bottom:12px;"><?= htmlspecialchars($cl_name) ?> <span style="font-size:0.85rem;font-weight:400;color:var(--text-muted);">(<?= $class_counts[$cl_name] ?> member<?= $class_counts[$cl_name] != 1 ? 's' : '' ?>)</span></h2>
                    <?php
                    $teacher_q = $conn->query("SELECT m.first_name, m.last_name, m.profile_picture FROM sunday_school_class_leaders scl JOIN members m ON scl.leader_id = m.id WHERE scl.class_name = '$cl_safe_q' AND scl.status = 'Active' ORDER BY scl.reviewed_at DESC, scl.requested_at DESC LIMIT 1");
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
                        <table><thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Gender</th><th>Transfer</th></tr></thead>
                        <tbody>
                        <?php $i=1; while($sm = $cl_members->fetch_assoc()): ?>
                            <?php $next_class = ss_next_class($cl_name); ?>
                            <tr><td><?= $i++ ?></td><td><?= htmlspecialchars($sm['first_name'].' '.$sm['last_name']) ?></td><td><?= htmlspecialchars($sm['phone'] ?? '—') ?></td><td><span class="badge" style="background:rgba(99,102,241,0.1);color:#6366f1;"><?= htmlspecialchars($sm['gender']) ?></span></td><td><?php if ($next_class): ?><a href="member_dashboard.php?tab=manage_department&action=promote_ss_member&id=<?= (int)$sm['id'] ?>" class="btn-action btn-approve" style="text-decoration:none;" onclick="return confirm('Transfer this member from <?= htmlspecialchars($cl_name, ENT_QUOTES) ?> to <?= htmlspecialchars($next_class, ENT_QUOTES) ?>?')">To <?= htmlspecialchars($next_class) ?></a><?php else: ?><span class="badge" style="background:rgba(239,68,68,0.12);color:#ef4444;">Highest class</span><?php endif; ?></td></tr>
                        <?php endwhile; ?>
                        </tbody></table>
                    </div>
                    <?php else: ?>
                        <p style="color:var(--text-muted);font-style:italic;text-align:center;padding:20px 0;">No members in <?= htmlspecialchars($cl_name) ?> yet.</p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            
<?php endif; ?>
                <div class="content-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap:wrap; gap:12px;">
                        <div>
                            <h2 style="margin: 0;">Registered Members</h2>
                            <span class="badge" style="background: var(--primary); color: white; font-size: 14px; padding: 5px 12px; margin-top:5px; display:inline-block;">Total: <?= $dept_members->num_rows ?></span>
                        </div>
                        <input type="text" id="deptMemberSearch" placeholder="Search by name or phone..." style="padding:10px 15px; border:1px solid var(--border-color); border-radius:8px; width:100%; max-width:280px;" onkeyup="filterDeptMembers()">
                    </div>
                    <script>
                    function filterDeptMembers() {
                        let input = document.getElementById("deptMemberSearch");
                        if (!input) return;
                        let filter = input.value.toLowerCase();
                        let table = input.closest(".content-card").querySelector(".table-responsive table");
                        if (!table) return;
                        let trs = table.querySelectorAll("tbody tr");
                        
                        for (let i = 0; i < trs.length; i++) {
                            let tr = trs[i];
                            let nameTd = tr.querySelector("td:nth-child(2)"); 
                            let phoneTd = tr.querySelector("td:nth-child(4)"); 
                            
                            if (nameTd || phoneTd) {
                                let nameTxt = nameTd ? nameTd.textContent.toLowerCase() : "";
                                let phoneTxt = phoneTd ? phoneTd.textContent.toLowerCase() : "";
                                
                                if (nameTxt.includes(filter) || phoneTxt.includes(filter)) {
                                    tr.style.display = "";
                                } else {
                                    tr.style.display = "none";
                                }
                            }
                        }
                    }
                    </script>

                    <?php if ($dept_members->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table>
                                <thead><tr><th>Profile</th><th>Name</th><th>Role</th><th>Phone</th><th>Area</th><th>Member Since</th></tr></thead>
                                <tbody>
                                    <?php while($dm = $dept_members->fetch_assoc()): ?>
                                        <tr>
                                            <td><img src="uploads/<?= htmlspecialchars($dm['profile_picture'] ?? 'default_avatar.png') ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); cursor: zoom-in;" onclick="viewProfileImage(this.src);"></td>
                                            <td style="font-weight: 500;"><?= htmlspecialchars($dm['first_name'] . ' ' . $dm['last_name']) ?></td>
                                            <td>
                                                <?php
                                                    $display_role = trim($dm['church_role'] ?? '');
                                                    $is_basic_member = $display_role === '' || strtolower($display_role) === 'member';
                                                ?>
                                                <span class="badge <?= $is_basic_member ? '' : 'approved' ?>" style="<?= $is_basic_member ? 'background:var(--border-color);color:var(--text-main);' : '' ?>">
                                                    <?= htmlspecialchars($is_basic_member ? 'Member' : $display_role) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($dm['phone']) ?></td>
                                            <td><?= htmlspecialchars($dm['address']) ?></td>
                                            <td><span style="color: var(--text-muted); font-size: 0.9em;"><?= date('M j, Y', strtotime($dm['reg_date'])) ?></span></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center" style="padding: 40px; color: var(--text-muted);">
                            <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin: 0 auto 15px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            <p>No members are currently registered in this department.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Customize Fines Moved to Discipline Master -->

                <!-- Member Reports -->
                <div class="content-card" style="margin-top: 30px; border-top: 4px solid var(--danger);">
                    <h2 style="color: var(--danger);">Disciplinary Reports</h2>
                    <p style="color: var(--text-muted); margin-bottom: 15px;">Reports filed by the Discipline Master.</p>

                    <?php
                    $reports = $conn->query("SELECT mr.*, m.first_name, m.last_name, dm.first_name as dm_first, dm.last_name as dm_last
                                            FROM member_reports mr
                                            JOIN members m ON mr.reported_member_id = m.id
                                            JOIN members dm ON mr.reported_by_id = dm.id
                                            WHERE mr.department = '$esc_dept' ORDER BY mr.created_at DESC");
                    if ($reports && $reports->num_rows > 0):
                    ?>
                        <div class="table-responsive">
                            <table>
                                <thead><tr><th>Date</th><th>Reported Member</th><th>Reason</th><th>Status</th><th>Action</th></tr></thead>
                                <tbody>
                                    <?php while($rp = $reports->fetch_assoc()): ?>
                                        <tr>
                                            <td style="white-space: nowrap; font-size: 0.85em; color: var(--text-muted);"><?= date('M j, Y', strtotime($rp['created_at'])) ?></td>
                                            <td style="font-weight: 500;"><?= htmlspecialchars($rp['first_name'] . ' ' . $rp['last_name']) ?></td>
                                            <td><?= htmlspecialchars($rp['reason']) ?></td>
                                            <td>
                                                <?php if ($rp['status'] == 'Pending Chairman'): ?>
                                                    <span class="badge" style="background: rgba(245,158,11,0.1); color: #f59e0b;">Pending Review</span>
                                                <?php else: ?>
                                                    <span class="badge" style="background: rgba(37,99,235,0.1); color: var(--primary);"><?= $rp['status'] ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($rp['status'] == 'Pending Chairman'): ?>
                                                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                                        <a href="member_action.php?action=resolve_report&id=<?= $rp['id'] ?>" class="btn-action" style="background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid #10b981; padding: 6px 12px; font-size: 0.85em;" onclick="return confirm('Mark this issue as resolved locally?');">Resolved</a>
                                                        <a href="member_action.php?action=forward_report&id=<?= $rp['id'] ?>" class="btn-action btn-approve" style="background: var(--danger); color: white; padding: 6px 12px; font-size: 0.85em;" onclick="return confirm('Forward to Pastor?');">Forward</a>
                                                    </div>
                                                <?php elseif ($rp['status'] == 'Resolved locally'): ?>
                                                    <span style="color: #10b981; font-size: 0.85em; font-weight: 500;"><svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-right: 4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Resolved</span>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted); font-size: 0.85em;">Forwarded to Pastor</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--text-muted); font-size: 0.9em;">No member reports at this time.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($tab == 'general_leader_chat' && $is_general_church_leader): ?>
                <div class="page-header">
                    <h1>General Church Leaders Chat</h1>
                    <p>Private communication for the General Church Secretary, Vice Secretary, Treasurer, and Senior Church Elder.</p>
                </div>

                <div class="content-card" style="margin-bottom: 20px;">
                    <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color);">
                        <?php foreach ($general_leader_partners as $cp): ?>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 14px;">
                                <?= strtoupper(substr($cp['first_name'], 0, 1)) ?>
                            </div>
                            <div style="line-height: 1.2;">
                                <strong style="font-size: 0.9rem;"><?= htmlspecialchars($cp['first_name'] . ' ' . $cp['last_name']) ?></strong>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($cp['church_role']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php $chat_msgs = $conn->query("SELECT lm.*, m.first_name, m.last_name, m.church_role FROM leader_messages lm JOIN members m ON lm.sender_id = m.id WHERE lm.dept_key = 'general_church' ORDER BY lm.created_at ASC"); ?>
                    <div style="max-height: 400px; overflow-y: auto; padding: 15px; background: var(--bg-main); border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 15px;">
                        <?php if ($chat_msgs && $chat_msgs->num_rows > 0): ?>
                            <?php while($cm = $chat_msgs->fetch_assoc()): $is_mine = ($cm['sender_id'] == $member_id); ?>
                                <div style="display: flex; justify-content: <?= $is_mine ? 'flex-end' : 'flex-start' ?>; margin-bottom: 10px;">
                                    <div style="max-width: 75%; padding: 10px 14px; border-radius: 16px; background: <?= $is_mine ? 'var(--primary)' : 'var(--border-color)' ?>; color: <?= $is_mine ? 'white' : 'var(--text-main)' ?>;">
                                        <?php if (!$is_mine): ?><div style="font-size: 0.75rem; font-weight: 600; margin-bottom: 4px; opacity: 0.8;"><?= htmlspecialchars($cm['first_name'] . ' - ' . $cm['church_role']) ?></div><?php endif; ?>
                                        <p style="margin: 0; line-height: 1.5; white-space: pre-wrap;"><?= htmlspecialchars($cm['message']) ?></p>
                                        <div style="font-size: 0.7rem; margin-top: 4px; opacity: 0.6; text-align: <?= $is_mine ? 'right' : 'left' ?>;"><?= date('M j, g:i A', strtotime($cm['created_at'])) ?></div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="text-align:center; color: var(--text-muted);">No messages yet.</p>
                        <?php endif; ?>
                    </div>

                    <form method="POST" action="?tab=general_leader_chat" style="display: flex; gap: 10px;">
                        <input type="hidden" name="send_general_leader_msg" value="1">
                        <input type="text" name="general_leader_message" class="form-control" placeholder="Type your message..." required style="margin-bottom: 0; flex: 1;">
                        <button type="submit" class="btn-submit" style="width: auto; padding: 10px 20px; margin: 0;">Send</button>
                    </form>
                </div>

            <?php elseif (($tab == 'query_announcements' || $tab == 'secretary_council_chat') && $is_secretary_council_member): ?>
                <div class="page-header">
                    <h1><?= $is_general_secretary ? 'Announcement Queried' : 'Query Announcement' ?></h1>
                    <p><?= $is_general_secretary ? 'Receive announcement queries from department secretaries and send messages to a specific secretary.' : 'Send announcement queries privately to the General Church Secretary.' ?></p>
                </div>

                <?php if ($is_general_secretary): ?>
                    <div class="content-card" style="margin-bottom: 24px;">
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px;">
                            <div style="width:36px;height:36px;border-radius:8px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <svg width="18" height="18" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                            </div>
                            <div>
                                <h2 style="margin:0;">Send Announcement to Department Secretary</h2>
                                <p style="margin:0; font-size:0.82rem; color:var(--text-muted);">Direct message to a department secretary regarding an announcement.</p>
                            </div>
                        </div>
                        <form method="POST" action="?tab=query_announcements">
                            <input type="hidden" name="send_secretary_direct_message" value="1">
                            <div class="form-group">
                                <label>Select Department Secretary</label>
                                <select name="recipient_id" class="form-control" required>
                                    <option value="">-- Select Department Secretary --</option>
                                    <?php
                                    $dept_secretary_role_conditions = [];
                                    foreach (array_keys($secretary_departments) as $r) {
                                        $dept_secretary_role_conditions[] = "LOWER(church_role) LIKE '%" . $conn->real_escape_string($r) . "%'";
                                    }
                                    $dept_secretary_role_sql = implode(' OR ', $dept_secretary_role_conditions);
                                    $dept_secretaries = $conn->query("SELECT id, first_name, last_name, church_role, department FROM members WHERE ($dept_secretary_role_sql) AND is_approved = 1 ORDER BY department ASC, first_name ASC");
                                    if ($dept_secretaries):
                                        while ($ds = $dept_secretaries->fetch_assoc()):
                                    ?>
                                        <option value="<?= $ds['id'] ?>"><?= htmlspecialchars($ds['first_name'] . ' ' . $ds['last_name'] . ' — ' . $ds['church_role'] . ' (' . ($ds['department'] ?? 'No Department') . ')') ?></option>
                                    <?php endwhile; endif; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Announcement Message / Instruction</label>
                                <textarea name="secretary_direct_message" class="form-control" rows="4" placeholder="Write the announcement instruction for this department secretary..." required></textarea>
                            </div>
                            <button type="submit" class="btn-submit">📢 Send to Secretary</button>
                        </form>
                    </div>

                    <div class="content-card" style="margin-bottom: 24px;">
                        <h2>Announcement Queries Received</h2>
                        <?php
                        $queries = $conn->query("
                            SELECT q.*, m.first_name, m.last_name, m.church_role
                            FROM secretary_announcement_queries q
                            JOIN members m ON q.secretary_id = m.id
                            ORDER BY q.created_at DESC
                            LIMIT 80
                        ");
                        ?>
                        <?php if ($queries && $queries->num_rows > 0): ?>
                            <?php while ($q = $queries->fetch_assoc()): ?>
                                <div style="padding: 14px 0; border-bottom: 1px solid var(--border-color);">
                                    <strong><?= htmlspecialchars($q['first_name'] . ' ' . $q['last_name']) ?></strong>
                                    <span style="color: var(--text-muted); font-size: 0.85rem;"> - <?= htmlspecialchars($q['department']) ?> - <?= htmlspecialchars($q['status']) ?> - <?= date('M j, Y g:i A', strtotime($q['created_at'])) ?></span>
                                    <p style="margin: 8px 0 0; white-space: pre-wrap;"><?= htmlspecialchars($q['message']) ?></p>
                                    <?php
                                    $query_reply_id = (int)$q['id'];
                                    $query_replies = $conn->query("
                                        SELECT dm.*, sender.first_name, sender.last_name, sender.church_role
                                        FROM secretary_direct_messages dm
                                        JOIN members sender ON dm.sender_id = sender.id
                                        WHERE dm.query_id = $query_reply_id
                                        ORDER BY dm.created_at ASC
                                    ");
                                    ?>
                                    <?php if ($query_replies && $query_replies->num_rows > 0): ?>
                                        <div style="margin-top: 12px; padding: 12px; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                                            <?php while ($reply = $query_replies->fetch_assoc()): ?>
                                                <p style="margin: 0 0 8px; white-space: pre-wrap;"><strong><?= htmlspecialchars($reply['first_name'] . ' ' . $reply['last_name']) ?>:</strong> <?= htmlspecialchars($reply['message']) ?></p>
                                            <?php endwhile; ?>
                                        </div>
                                    <?php endif; ?>
                                    <form method="POST" action="?tab=query_announcements" style="margin-top: 12px;">
                                        <input type="hidden" name="send_secretary_direct_message" value="1">
                                        <input type="hidden" name="query_id" value="<?= (int)$q['id'] ?>">
                                        <input type="hidden" name="recipient_id" value="<?= (int)$q['secretary_id'] ?>">
                                        <div class="form-group" style="margin-bottom: 10px;">
                                            <textarea name="secretary_direct_message" class="form-control" rows="3" placeholder="Reply to this announcement query..." required></textarea>
                                        </div>
                                        <button type="submit" class="btn-submit" style="width:auto; padding: 8px 16px; margin:0;">Reply to Query</button>
                                    </form>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="color: var(--text-muted);">No announcement queries have been submitted yet.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Send Query Form -->
                    <div class="content-card" style="margin-bottom: 24px;">
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px;">
                            <div style="width:36px;height:36px;border-radius:8px;background:linear-gradient(135deg,#6366f1,#4f46e5);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <svg width="18" height="18" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                            </div>
                            <div>
                                <h2 style="margin:0;">Query an Announcement</h2>
                                <p style="margin:0; font-size:0.82rem; color:var(--text-muted);">Submit your request to the General Church Secretary for a formal announcement.</p>
                            </div>
                        </div>
                        <form method="POST" action="?tab=query_announcements">
                            <input type="hidden" name="submit_secretary_query" value="1">
                            <div class="form-group">
                                <label>Your Department</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($managed_dept) ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label>Announcement Query</label>
                                <textarea name="secretary_query_message" class="form-control" rows="5" placeholder="Write the announcement query for the General Church Secretary..." required></textarea>
                            </div>
                            <button type="submit" class="btn-submit">Send Query</button>
                        </form>
                    </div>

                    <!-- Threaded queries with replies beneath each -->
                    <div class="content-card">
                        <h2 style="margin-bottom:20px;">Your Submitted Queries &amp; Replies</h2>
                        <?php
                        $my_queries = $conn->query("
                            SELECT *
                            FROM secretary_announcement_queries
                            WHERE secretary_id = $member_id
                            ORDER BY created_at DESC
                            LIMIT 50
                        ");
                        ?>
                        <?php if ($my_queries && $my_queries->num_rows > 0): ?>
                            <div style="display:flex; flex-direction:column; gap:20px;">
                            <?php while ($mq = $my_queries->fetch_assoc()): ?>
                                <?php
                                $status_color = $mq['status'] === 'Approved' ? 'var(--success)' : ($mq['status'] === 'Rejected' ? 'var(--danger)' : ($mq['status'] === 'Replied' ? '#6366f1' : 'var(--warning)'));
                                $status_bg    = $mq['status'] === 'Approved' ? 'rgba(16,185,129,0.1)' : ($mq['status'] === 'Rejected' ? 'rgba(239,68,68,0.1)' : ($mq['status'] === 'Replied' ? 'rgba(99,102,241,0.1)' : 'rgba(245,158,11,0.1)'));
                                $my_query_id  = (int)$mq['id'];
                                $my_query_replies = $conn->query("
                                    SELECT dm.*, sender.first_name, sender.last_name, sender.church_role
                                    FROM secretary_direct_messages dm
                                    JOIN members sender ON dm.sender_id = sender.id
                                    WHERE dm.query_id = $my_query_id AND dm.recipient_id = $member_id
                                    ORDER BY dm.created_at ASC
                                ");
                                ?>
                                <div style="border:1px solid var(--border-color); border-radius:14px; overflow:hidden;">
                                    <!-- Your query bubble -->
                                    <div style="padding:16px 18px; background:var(--bg-lighter);">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <div style="width:34px;height:34px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:700;color:white;">
                                                    <?= strtoupper(substr($member['first_name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <span style="font-weight:600;"><?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?></span>
                                                    <span style="font-size:0.75rem; color:var(--text-muted); margin-left:6px;"><?= htmlspecialchars($member['church_role'] ?? '') ?></span>
                                                </div>
                                            </div>
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <span style="font-size:0.75rem; color:var(--text-muted);"><?= date('M j, Y g:i A', strtotime($mq['created_at'])) ?></span>
                                                <span style="background:<?= $status_bg ?>; color:<?= $status_color ?>; padding:2px 10px; border-radius:20px; font-size:0.78rem; font-weight:700;"><?= htmlspecialchars($mq['status']) ?></span>
                                            </div>
                                        </div>
                                        <p style="margin:0; line-height:1.6; white-space:pre-wrap;"><?= nl2br(htmlspecialchars($mq['message'])) ?></p>
                                    </div>

                                    <?php if ($my_query_replies && $my_query_replies->num_rows > 0): ?>
                                        <!-- Secretary reply(ies) threaded beneath -->
                                        <div style="border-top:1px solid var(--border-color); background:var(--card-bg);">
                                            <?php while ($reply = $my_query_replies->fetch_assoc()): ?>
                                            <div style="padding:14px 18px; display:flex; gap:12px; align-items:flex-start; border-bottom:1px solid rgba(255,255,255,0.04);">
                                                <div style="flex-shrink:0; margin-top:2px;">
                                                    <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#4f46e5);display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:700;color:white;">
                                                        <?= strtoupper(substr($reply['first_name'], 0, 1)) ?>
                                                    </div>
                                                </div>
                                                <div style="flex:1;">
                                                    <div style="margin-bottom:6px;">
                                                        <strong style="font-size:0.9rem;"><?= htmlspecialchars($reply['first_name'] . ' ' . $reply['last_name']) ?></strong>
                                                        <span style="font-size:0.78rem; color:#6366f1; margin-left:6px; font-weight:600;">General Church Secretary</span>
                                                        <span style="font-size:0.72rem; color:var(--text-muted); margin-left:8px;"><?= date('M j, Y g:i A', strtotime($reply['created_at'])) ?></span>
                                                    </div>
                                                    <p style="margin:0; line-height:1.6; white-space:pre-wrap;"><?= nl2br(htmlspecialchars($reply['message'])) ?></p>
                                                </div>
                                            </div>
                                            <?php endwhile; ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="padding:12px 18px; border-top:1px solid var(--border-color); background:var(--card-bg);">
                                            <p style="margin:0; font-size:0.83rem; color:var(--text-muted); font-style:italic;">⏳ Awaiting reply from the General Church Secretary...</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p style="color:var(--text-muted); text-align:center; padding:20px 0;">You have not submitted any announcement queries yet.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>


            <?php elseif ($tab == 'convey_youth_announcement' && $is_youth_advisor): ?>
                <div class="page-header">
                    <h1>Convey Announcement to Youths</h1>
                    <p>Post an announcement to registered Youths while keeping your main department as <?= htmlspecialchars($member['department'] ?? 'None') ?>.</p>
                </div>

                <div class="content-card" style="max-width: 650px; margin-bottom: 24px;">
                    <form method="POST" action="?tab=convey_youth_announcement" enctype="multipart/form-data">
                        <input type="hidden" name="post_youth_advisor_announcement" value="1">
                        <div class="form-group">
                            <label>Youth Announcement</label>
                            <textarea name="message" class="form-control" rows="6" placeholder="Write the announcement for registered Youths..." required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Picture</label>
                            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                <label for="youth_announcement_camera" class="camera-upload-trigger" data-input="youth_announcement_camera" style="display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-main);color:var(--text-main);cursor:pointer;">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h2.2l1.4-2.1A2 2 0 0110.3 4h3.4a2 2 0 011.7.9L16.8 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    Take Picture
                                </label>
                                <label for="youth_announcement_upload" class="camera-upload-trigger" data-input="youth_announcement_upload" style="display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-main);color:var(--text-main);cursor:pointer;">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0-12l-4 4m4-4l4 4"></path></svg>
                                    Upload Picture
                                </label>
                            </div>
                            <input id="youth_announcement_camera" type="file" name="announcement_camera" accept="image/*" capture="environment" style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;">
                            <input id="youth_announcement_upload" type="file" name="announcement_upload" accept="image/*" style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;">
                            <small class="selected-upload-name" data-for="youth_announcement_camera,youth_announcement_upload" style="display:block;margin-top:6px;color:var(--text-muted);"></small>
                            <small style="display:block;margin-top:6px;color:var(--text-muted);">Take Picture opens the camera on supported phones. Upload Picture opens gallery or local storage.</small>
                        </div>
                        <button type="submit" class="btn-submit">Post to Registered Youths</button>
                    </form>
                </div>

                <div style="max-width: 650px;">
                    <h3 style="font-size: 1.1rem; color: var(--text-muted); margin-bottom: 15px;">Recent Youth Announcements</h3>
                    <?php
                    $youth_announcements = $conn->query("
                        SELECT da.*, m.first_name, m.last_name, m.church_role
                        FROM department_announcements da
                        JOIN members m ON da.secretary_id = m.id
                        WHERE da.department = 'Youths'
                        ORDER BY da.created_at DESC
                        LIMIT 20
                    ");
                    ?>
                    <?php if ($youth_announcements && $youth_announcements->num_rows > 0): ?>
                        <?php while ($ya = $youth_announcements->fetch_assoc()): ?>
                            <div class="content-card" style="margin-bottom: 15px; border-left: 4px solid var(--primary); padding: 15px;">
                                <div style="display:flex; justify-content:space-between; gap:12px; margin-bottom:8px;">
                                    <div>
                                        <strong><?= htmlspecialchars($ya['first_name'] . ' ' . $ya['last_name']) ?></strong>
                                        <div style="font-size:0.8rem; color:var(--text-muted);"><?= htmlspecialchars($ya['church_role']) ?></div>
                                    </div>
                                    <small style="color:var(--text-muted);"><?= date('M j, Y g:i A', strtotime($ya['created_at'])) ?></small>
                                </div>
                                <p style="white-space:pre-wrap; margin:0;"><?= htmlspecialchars($ya['message']) ?></p>
                                <?= render_announcement_image($ya['image_path'] ?? '') ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: var(--text-muted);">No Youth announcements have been posted yet.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($tab == 'manage_songs' && $is_choir_leader): ?>
                <?php 
                $active_choir_dept = $_GET['choir_dept'] ?? (in_array($active_dashboard_dept, $choir_leader_depts) ? $active_dashboard_dept : ($choir_leader_depts[0] ?? $active_dashboard_dept));
                $choir_dept = $conn->real_escape_string($active_choir_dept); 
                ?>
                <div class="page-header">
                    <h1>Manage Songs</h1>
                    <?php if (count($choir_leader_depts) > 1): ?>
                    <p>You serve as Choir Leader in multiple departments. Select one to manage:</p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;margin-bottom:15px;">
                        <?php foreach ($choir_leader_depts as $cd): ?>
                            <a href="?tab=manage_songs&choir_dept=<?= urlencode($cd) ?>"
                               class="btn-sm<?= (strtolower($cd) === strtolower($active_choir_dept)) ? ' btn-success' : '' ?>"
                               style="text-decoration:none;<?= (strtolower($cd) !== strtolower($active_choir_dept)) ? 'background:var(--bg-card);border:1px solid var(--border-color);color:var(--text-main);' : '' ?>">
                               <?= htmlspecialchars($cd) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p>Plan songs and share choir updates for the <strong><?= htmlspecialchars($active_choir_dept) ?></strong> department.</p>
                    <?php endif; ?>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:24px;align-items:start;">
                    <div class="content-card">
                        <h2>Post Song Announcement</h2>
                        <form method="POST" action="?tab=manage_songs&choir_dept=<?= urlencode($active_choir_dept) ?>" enctype="multipart/form-data">
                            <input type="hidden" name="active_dept" value="<?= htmlspecialchars($active_choir_dept) ?>">
                            <input type="hidden" name="post_choir_announcement" value="1">
                            <div class="form-group">
                                <label>Announcement Message</label>
                                <textarea name="message" class="form-control" rows="5" placeholder="Write a choir announcement..." required></textarea>
                            </div>
                            <div class="form-group">
                                <label>Picture</label>
                                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                    <label for="choir_announcement_camera" class="camera-upload-trigger" data-input="choir_announcement_camera" style="display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-main);color:var(--text-main);cursor:pointer;">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h2.2l1.4-2.1A2 2 0 0110.3 4h3.4a2 2 0 011.7.9L16.8 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        Take Picture
                                    </label>
                                    <label for="choir_announcement_upload" class="camera-upload-trigger" data-input="choir_announcement_upload" style="display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-main);color:var(--text-main);cursor:pointer;">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0-12l-4 4m4-4l4 4"></path></svg>
                                        Upload Picture
                                    </label>
                                </div>
                                <input id="choir_announcement_camera" type="file" name="announcement_camera" accept="image/*" capture="environment" style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;">
                                <input id="choir_announcement_upload" type="file" name="announcement_upload" accept="image/*" style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;">
                                <small class="selected-upload-name" data-for="choir_announcement_camera,choir_announcement_upload" style="display:block;margin-top:6px;color:var(--text-muted);"></small>
                                <small style="display:block;margin-top:6px;color:var(--text-muted);">Take Picture opens the camera on supported phones. Upload Picture opens gallery or local storage.</small>
                            </div>
                            <button type="submit" class="btn-submit">Post Announcement</button>
                        </form>
                    </div>

                    <div class="content-card">
                        <h2>Add Song for a Day</h2>
                        <form method="POST" action="?tab=manage_songs&choir_dept=<?= urlencode($active_choir_dept) ?>">
                            <input type="hidden" name="active_dept" value="<?= htmlspecialchars($active_choir_dept) ?>">
                            <input type="hidden" name="add_song_plan" value="1">
                            <div class="form-group">
                                <label>Song Type</label>
                                <select name="song_type" class="form-control" required>
                                    <option value="" disabled selected>-- Select Song Type --</option>
                                    <option value="Praise">Praise</option>
                                    <option value="Worship">Worship</option>
                                    <option value="Special Song">Special Song</option>
                                    <option value="Choir Presentation">Choir Presentation</option>
                                    <option value="Thanksgiving">Thanksgiving</option>
                                    <option value="Offering Song">Offering Song</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Song Title</label>
                                <input type="text" name="song_title" class="form-control" placeholder="Enter song title..." required>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <div class="form-group">
                                    <label>Specific Day</label>
                                    <input type="date" name="song_date" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Time</label>
                                    <input type="time" name="song_time" class="form-control">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Notes</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="Optional notes..."></textarea>
                            </div>
                            <button type="submit" class="btn-submit">Add Song Plan</button>
                        </form>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:24px;margin-top:24px;align-items:start;">
                    <div class="content-card">
                        <h2>Upcoming Songs</h2>
                        <?php $song_plans = $conn->query("SELECT * FROM choir_song_plans WHERE department='$choir_dept' ORDER BY song_date ASC, song_time ASC, created_at DESC LIMIT 30"); ?>
                        <?php if ($song_plans && $song_plans->num_rows > 0): ?>
                            <div style="display:flex;flex-direction:column;gap:12px;margin-top:12px;">
                                <?php while($song = $song_plans->fetch_assoc()): ?>
                                    <div style="padding:15px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-main);">
                                        <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;">
                                            <div>
                                                <strong style="color:var(--text-main);"><?= htmlspecialchars($song['song_title']) ?></strong>
                                                <div style="color:var(--text-muted);font-size:0.85rem;"><?= htmlspecialchars($song['song_type']) ?></div>
                                            </div>
                                            <span class="badge" style="background:rgba(37,99,235,0.12);color:var(--primary);"><?= date('M j, Y', strtotime($song['song_date'])) ?><?= $song['song_time'] ? ' ' . date('g:i A', strtotime($song['song_time'])) : '' ?></span>
                                        </div>
                                        <?php if (!empty($song['notes'])): ?>
                                            <p style="margin:10px 0 0;color:var(--text-muted);white-space:pre-wrap;"><?= htmlspecialchars($song['notes']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p style="color:var(--text-muted);">No songs have been planned yet.</p>
                        <?php endif; ?>
                    </div>

                    <div class="content-card">
                        <h2>Recent Choir Announcements</h2>
                        <?php
                        $choir_announcements = $conn->query("
                            SELECT ca.*, m.first_name, m.last_name
                            FROM choir_announcements ca
                            JOIN members m ON ca.choir_leader_id = m.id
                            WHERE ca.department='$choir_dept'
                            ORDER BY ca.created_at DESC
                            LIMIT 20
                        ");
                        ?>
                        <?php if ($choir_announcements && $choir_announcements->num_rows > 0): ?>
                            <?php while($ca = $choir_announcements->fetch_assoc()): ?>
                                <div style="padding:14px 0;border-bottom:1px solid var(--border-color);">
                                    <strong><?= htmlspecialchars($ca['first_name'] . ' ' . $ca['last_name']) ?></strong>
                                    <span style="color:var(--text-muted);font-size:0.85rem;"> - <?= date('M j, Y g:i A', strtotime($ca['created_at'])) ?></span>
                                    <p style="margin:8px 0 0;white-space:pre-wrap;"><?= htmlspecialchars($ca['message']) ?></p>
                                    <?= render_announcement_image($ca['image_path'] ?? '') ?>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="color:var(--text-muted);">No choir announcements posted yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($tab == 'leader_chat' && ($is_leader || $is_secretary || $is_prayer_coordinator || $is_sport_secretary || $is_choir_leader || $is_youth_advisor || $is_discipline_master || $is_graduands_secretary)): ?>
                <div class="page-header">
                    <h1>Leadership Chat — <?= htmlspecialchars($managed_dept) ?></h1>
                    <p>Private group chat for all <?= htmlspecialchars($managed_dept) ?> department leaders.</p>
                </div>

                <?php if (!empty($chat_partners)): ?>
                    <div class="content-card" style="margin-bottom: 20px;">
                        <!-- Online Members -->
                        <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color);">
                            <?php foreach ($chat_partners as $cp): ?>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 14px;">
                                    <?= strtoupper(substr($cp['first_name'], 0, 1)) ?>
                                </div>
                                <div style="line-height: 1.2;">
                                    <strong style="font-size: 0.9rem;"><?= htmlspecialchars($cp['first_name'] . ' ' . $cp['last_name']) ?></strong>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($cp['church_role']) ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Chat Messages -->
                        <?php
                        $chat_msgs = $conn->query("
                            SELECT lm.id, lm.dept_key, lm.sender_id, lm.message, lm.created_at, m.first_name, m.last_name, NULL as is_pastor
                            FROM leader_messages lm
                            JOIN members m ON lm.sender_id = m.id
                            WHERE lm.dept_key = '$dept_key'
                            UNION ALL
                            SELECT plm.id, plm.dept_key, plm.pastor_id as sender_id, plm.message, plm.created_at, p.first_name, p.last_name, 1 as is_pastor
                            FROM pastor_leader_messages plm
                            JOIN pastors p ON plm.pastor_id = p.id
                            WHERE plm.dept_key = '$dept_key'
                            ORDER BY created_at ASC
                        ");
                        // Fallback if pastor table missing somehow
                        if (!$chat_msgs) {
                            $chat_msgs = $conn->query("SELECT lm.id, lm.dept_key, lm.sender_id, lm.message, lm.created_at, m.first_name, m.last_name, NULL as is_pastor FROM leader_messages lm JOIN members m ON lm.sender_id = m.id WHERE lm.dept_key = '$dept_key' ORDER BY lm.created_at ASC");
                        }
                        ?>
                        <div id="chatBox" style="max-height: 400px; overflow-y: auto; padding: 15px; background: var(--bg-main); border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 15px;">
                            <?php if ($chat_msgs && $chat_msgs->num_rows > 0): ?>
                                <?php while($cm = $chat_msgs->fetch_assoc()):
                                    $is_pastor = !empty($cm['is_pastor']);
                                    $is_mine = (!$is_pastor && $cm['sender_id'] == $member_id);
                                    $bg = $is_mine ? 'var(--primary)' : ($is_pastor ? 'rgba(16,185,129,0.1)' : 'var(--border-color)');
                                    $color = $is_mine ? 'white' : 'var(--text-main)';
                                    $border = $is_pastor ? '2px solid #10b981' : 'none';
                                ?>
                                    <div style="display: flex; justify-content: <?= $is_mine ? 'flex-end' : 'flex-start' ?>; margin-bottom: 10px;">
                                        <div style="max-width: 75%; padding: 10px 14px; border-radius: 16px; background: <?= $bg ?>; color: <?= $color ?>; border: <?= $border ?>;">
                                            <?php if (!$is_mine): ?>
                                                <div style="font-size: 0.75rem; font-weight: 600; margin-bottom: 4px; opacity: 0.8;">
                                                    <?= $is_pastor ? '⛪ Pastor ' : '' ?><?= htmlspecialchars($cm['first_name'] . ($is_pastor ? ' ' . $cm['last_name'] : '')) ?>
                                                </div>
                                            <?php endif; ?>
                                            <p style="margin: 0; line-height: 1.5; white-space: pre-wrap;"><?= htmlspecialchars($cm['message']) ?></p>
                                            <div style="font-size: 0.7rem; margin-top: 4px; opacity: 0.6; text-align: <?= $is_mine ? 'right' : 'left' ?>;"><?= date('M j, g:i A', strtotime($cm['created_at'])) ?></div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 30px; color: var(--text-muted);">
                                    <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-bottom: 10px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                    <p>No messages yet. Start the conversation!</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Send Message -->
                        <form method="POST" action="?tab=leader_chat" style="display: flex; gap: 10px;">
                            <input type="hidden" name="send_leader_msg" value="1">
                            <input type="text" name="leader_message" class="form-control" placeholder="Type your message..." required style="margin-bottom: 0; flex: 1;">
                            <button type="submit" class="btn-submit" style="width: auto; padding: 10px 20px; margin: 0;">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="content-card text-center" style="padding: 40px;">
                        <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--text-muted); margin: 0 auto 15px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path></svg>
                        <p style="color: var(--text-muted);">No other leader has been assigned to the <strong><?= htmlspecialchars($managed_dept) ?></strong> department yet. Once another leader is appointed by the Pastor, you will be able to chat here.</p>
                    </div>
                <?php endif; ?>


            <?php elseif ($tab == 'monitor_youth_from_home' && $is_youth_advisor): ?>
                <div class="page-header">
                    <h1>🎓 Monitor Youth Department</h1>
                    <p>View Youth department members, leaders, and their latest announcements.</p>
                </div>

                <?php
                // Youth members list
                $youth_members_q = $conn->query("SELECT id, first_name, last_name, church_role, profile_picture FROM members WHERE (department='Youths' OR LOWER(TRIM(church_role)) IN ('mama youth','baba youth')) AND is_approved=1 ORDER BY COALESCE(church_role,'zzz') ASC, first_name ASC");
                ?>
                <div class="content-card" style="margin-bottom: 25px;">
                    <h2 style="margin-bottom: 15px;">Youth Members & Leaders</h2>
                    <?php if ($youth_members_q && $youth_members_q->num_rows > 0): ?>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php while ($ym = $youth_members_q->fetch_assoc()): ?>
                        <div style="display:flex; align-items:center; gap:14px; padding:12px 16px; background:var(--bg-main); border-radius:10px; border:1px solid var(--border-color);">
                            <?php $pic = !empty($ym['profile_picture']) ? 'uploads/' . htmlspecialchars($ym['profile_picture']) : null; ?>
                            <?php if ($pic): ?>
                                <img src="<?= $pic ?>" style="width:42px;height:42px;border-radius:50%;object-fit:cover;border:2px solid var(--primary);">
                            <?php else: ?>
                                <div style="width:42px;height:42px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:18px;"><?= strtoupper(substr($ym['first_name'],0,1)) ?></div>
                            <?php endif; ?>
                            <div style="flex:1;">
                                <strong style="color:var(--text-main);"><?= htmlspecialchars($ym['first_name'] . ' ' . $ym['last_name']) ?></strong>
                                <?php if (!empty($ym['church_role'])): ?>
                                <span class="badge" style="margin-left:8px;background:rgba(37,99,235,0.12);color:var(--primary);font-size:0.78rem;"><?= htmlspecialchars(role_display_label($ym['church_role'], 'Youths')) ?></span>
                                <?php else: ?>
                                <span style="margin-left:8px;font-size:0.82rem;color:var(--text-muted);">Member</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <p style="color:var(--text-muted);">No youth members found.</p>
                    <?php endif; ?>
                </div>

                <!-- Youth Announcements -->
                <div class="content-card">
                    <h2 style="margin-bottom: 15px;">📢 Recent Youth Announcements</h2>
                    <?php
                    $youth_ann_q = $conn->query("SELECT da.*, m.first_name, m.last_name, m.church_role FROM department_announcements da JOIN members m ON da.secretary_id = m.id WHERE da.department='Youths' ORDER BY da.created_at DESC LIMIT 15");
                    if ($youth_ann_q && $youth_ann_q->num_rows > 0):
                    ?>
                    <div style="display:flex;flex-direction:column;gap:15px;">
                    <?php while ($yan = $youth_ann_q->fetch_assoc()): ?>
                        <div style="padding:16px;border-left:4px solid var(--primary);background:var(--bg-main);border-radius:0 10px 10px 0;">
                            <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:8px;">
                                <div>
                                    <strong style="color:var(--text-main);"><?= htmlspecialchars($yan['first_name'] . ' ' . $yan['last_name']) ?></strong>
                                    <span class="badge" style="margin-left:8px;background:rgba(37,99,235,0.1);color:var(--primary);font-size:0.76rem;"><?= htmlspecialchars($yan['church_role'] ?? '') ?></span>
                                </div>
                                <small style="color:var(--text-muted);"><?= date('M j, Y g:i A', strtotime($yan['created_at'])) ?></small>
                            </div>
                            <p style="margin:0;white-space:pre-wrap;line-height:1.6;font-size:0.9rem;"><?= htmlspecialchars($yan['message']) ?></p>
                            <?= render_announcement_image($yan['image_path'] ?? '') ?>
                        </div>
                    <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <p style="color:var(--text-muted);">No youth announcements yet.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($tab == 'elder_announcements' && in_array('senior Senior Church Elder', $member_roles_normalized, true)): ?>
                <div class="page-header">
                    <h1>Post Announcement</h1>
                    <p>Share important news and updates with the entire congregation.</p>
                </div>

                <div class="content-card" style="max-width: 600px;">
                    <form method="POST" action="?tab=elder_announcements" enctype="multipart/form-data">
                        <input type="hidden" name="post_announcement" value="1">
                        <div class="form-group">
                            <label>Announcement Message</label>
                            <textarea name="message" class="form-control" rows="6" placeholder="Write your announcement here..." required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Picture</label>
                            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                <label for="elder_announcement_camera" class="camera-upload-trigger" data-input="elder_announcement_camera" style="display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-main);color:var(--text-main);cursor:pointer;">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h2.2l1.4-2.1A2 2 0 0110.3 4h3.4a2 2 0 011.7.9L16.8 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    Take Picture
                                </label>
                                <label for="elder_announcement_upload" class="camera-upload-trigger" data-input="elder_announcement_upload" style="display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-main);color:var(--text-main);cursor:pointer;">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0-12l-4 4m4-4l4 4"></path></svg>
                                    Upload Picture
                                </label>
                            </div>
                            <input id="elder_announcement_camera" type="file" name="announcement_camera" accept="image/*" capture="environment" style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;">
                            <input id="elder_announcement_upload" type="file" name="announcement_upload" accept="image/*" style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;">
                            <small class="selected-upload-name" data-for="elder_announcement_camera,elder_announcement_upload" style="display:block;margin-top:6px;color:var(--text-muted);"></small>
                            <small style="display:block;margin-top:6px;color:var(--text-muted);">Take Picture opens the camera on supported phones. Upload Picture opens gallery or local storage.</small>
                        </div>
                        <button type="submit" class="btn-submit">Post to Congregation</button>
                    </form>
                </div>

            <?php elseif (($tab == 'dept_announcements' && ($is_secretary || $is_general_secretary)) || ($tab == 'manage_announcement' && $is_building_secretary)): ?>
                <?php
                // Build list of all departments this secretary can manage
                $_sec_all_depts = [];
                if (!empty($leadership_managed_dept)) $_sec_all_depts[] = $leadership_managed_dept;
                foreach ($ss_secretary_depts as $ssd) {
                    if (!in_array($ssd, $_sec_all_depts)) $_sec_all_depts[] = $ssd;
                }
                $active_sec_dept = $_GET['sec_dept'] ?? (in_array($active_dashboard_dept, $_sec_all_depts) ? $active_dashboard_dept : ($_sec_all_depts[0] ?? $managed_dept));
                $managed_dept_display = $active_sec_dept ?: $managed_dept;
                $announcement_scope = ($is_general_secretary || $is_building_secretary) ? 'General Church' : $managed_dept_display;
                ?>
                <div class="page-header">
                    <h1><?= ($is_general_secretary || $is_building_secretary) ? 'Post General Church Announcement' : 'Post Department Announcement' ?></h1>
                    <?php if (count($_sec_all_depts) > 1 && !$is_general_secretary && !$is_building_secretary): ?>
                    <p>You manage announcements for multiple departments. Select one:</p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;margin-bottom:15px;">
                        <?php foreach ($_sec_all_depts as $sd): ?>
                            <a href="?tab=dept_announcements&sec_dept=<?= urlencode($sd) ?>"
                               class="btn-sm<?= (strtolower($sd) === strtolower($managed_dept_display)) ? ' btn-success' : '' ?>"
                               style="text-decoration:none;<?= (strtolower($sd) !== strtolower($managed_dept_display)) ? 'background:var(--bg-card);border:1px solid var(--border-color);color:var(--text-main);' : '' ?>">
                               <?= htmlspecialchars($sd) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p>Share important updates <?= ($is_general_secretary || $is_building_secretary) ? 'with the entire church' : 'strictly with the <strong>' . htmlspecialchars($managed_dept_display) . '</strong> department' ?>.</p>
                    <?php endif; ?>
                </div>

                <div class="content-card" style="max-width: 600px;">
                    <form method="POST" action="?tab=dept_announcements" enctype="multipart/form-data">
                        <input type="hidden" name="post_dept_announcement" value="1">
                        <input type="hidden" name="active_dept" value="<?= htmlspecialchars($managed_dept_display) ?>">
                        <div class="form-group">
                            <label>Announcement Message</label>
                            <textarea name="message" class="form-control" rows="6" placeholder="Write your announcement here..." required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Picture</label>
                            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                <label for="dept_announcement_camera" class="camera-upload-trigger" data-input="dept_announcement_camera" style="display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-main);color:var(--text-main);cursor:pointer;">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h2.2l1.4-2.1A2 2 0 0110.3 4h3.4a2 2 0 011.7.9L16.8 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    Take Picture
                                </label>
                                <label for="dept_announcement_upload" class="camera-upload-trigger" data-input="dept_announcement_upload" style="display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-main);color:var(--text-main);cursor:pointer;">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0-12l-4 4m4-4l4 4"></path></svg>
                                    Upload Picture
                                </label>
                            </div>
                            <input id="dept_announcement_camera" type="file" name="announcement_camera" accept="image/*" capture="environment" style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;">
                            <input id="dept_announcement_upload" type="file" name="announcement_upload" accept="image/*" style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;">
                            <small class="selected-upload-name" data-for="dept_announcement_camera,dept_announcement_upload" style="display:block;margin-top:6px;color:var(--text-muted);"></small>
                            <small style="display:block;margin-top:6px;color:var(--text-muted);">Take Picture opens the camera on supported phones. Upload Picture opens gallery or local storage.</small>
                        </div>
                        <button type="submit" class="btn-submit"><?= $is_general_secretary ? 'Post to Entire Church' : 'Post to Department' ?></button>
                    </form>
                </div>

                <?php if ($is_building_secretary): ?>
                <div class="content-card" style="max-width: 600px; margin-top: 30px; border: 1px solid var(--primary);">
                    <h2 style="font-size: 1.1rem; margin-top: 0; color: var(--primary);">Submit Announcement Query</h2>
                    <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 15px;">Send an announcement to the General Church Secretary for wider distribution.</p>
                    <form method="POST" action="member_dashboard.php?tab=manage_announcement">
                        <input type="hidden" name="submit_secretary_query" value="1">
                        <div class="form-group">
                            <textarea name="secretary_query_message" class="form-control" rows="4" placeholder="Write your announcement query here..." required></textarea>
                        </div>
                        <button type="submit" class="btn-submit" style="background: var(--primary);">Send to General Secretary</button>
                    </form>
                </div>
                <?php endif; ?>

                <div style="max-width: 600px; margin-top: 30px;">
                    <h3 style="font-size: 1.1rem; color: var(--text-muted); margin-bottom: 15px;">Previous Announcements</h3>
                    <?php
                    $esc_managed_dept = $conn->real_escape_string($announcement_scope);
                    $query_cond = "da.department = '$esc_managed_dept'";
                    if ($is_building_secretary) {
                        $query_cond = "da.secretary_id = $member_id";
                    }
                    $past_announcements = $conn->query("
                        SELECT da.*, m.first_name, m.last_name, m.church_role
                        FROM department_announcements da
                        JOIN members m ON da.secretary_id = m.id
                        WHERE $query_cond
                        ORDER BY da.created_at DESC
                    ");
                    if ($past_announcements && $past_announcements->num_rows > 0):
                        while ($dann = $past_announcements->fetch_assoc()):
                    ?>
                        <div class="content-card" style="margin-bottom: 15px; border-left: 4px solid var(--secondary); padding: 15px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                <div>
                                    <h4 style="color: var(--text-main); margin: 0; font-size: 0.95rem;"><?= htmlspecialchars($dann['first_name'] . ' ' . $dann['last_name']) ?></h4>
                                    <span style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars(clean_role_display($dann['church_role'])) ?></span>
                                </div>
                                <small style="color: var(--text-muted); font-size: 0.75rem;"><?= date('M j, Y g:i A', strtotime($dann['created_at'])) ?></small>
                            </div>
                            <p style="line-height: 1.5; font-size: 0.9rem; margin: 0; white-space: pre-wrap;"><?= htmlspecialchars($dann['message']) ?></p>
                            <?= render_announcement_image($dann['image_path'] ?? '') ?>
                        </div>
                    <?php
                        endwhile;
                    else:
                    ?>
                        <div class="content-card text-center" style="padding: 20px;">
                            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">No announcements have been posted for this department yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($tab == 'notifications'): ?>
                <div class="page-header">
                    <h1>Notifications</h1>
                    <p>Your recent church updates and role assignments.</p>
                </div>
                <div class="content-card">
                    <?php $notifications = $conn->query("SELECT * FROM notifications WHERE user_id = $member_id AND user_type = 'member' ORDER BY created_at DESC"); if($notifications && $notifications->num_rows > 0): ?>
                        <?php while($n = $notifications->fetch_assoc()): ?>
                            <?php
                            $target_tab = notification_target_tab($n['message'], 'member', 'dashboard');
                            if ($is_choir_leader && $target_tab === 'choir_songs') {
                                $target_tab = 'manage_songs';
                            }
                            ?>
                            <a href="mark_read_and_redirect.php?id=<?= $n['id'] ?>&redirect=<?= urlencode($target_tab) ?>" class="notification-item <?= !$n['is_read'] ? 'unread' : '' ?>">
                                <p><?= htmlspecialchars($n['message']) ?></p>
                                <div class="notification-meta">
                                    <small><?= date('M j, Y g:i A', strtotime($n['created_at'])) ?></small>
                                    <?php if (!$n['is_read']): ?>
                                        <small style="color: red; font-size: 0.75rem; margin-left: 8px;">unread</small>
                                    <?php endif; ?>
                                    <span>Open</span>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No new notifications.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($tab == 'contact_pastor'): ?>
                <div class="page-header">
                    <h1>Contact Pastor</h1>
                    <p>Schedule a meeting or view direct messages from your Pastor.</p>
                </div>

                <div style="display: flex; flex-wrap: wrap; gap: 30px; align-items: flex-start;">
                    <!-- Book Appointment Section -->
                    <div style="flex: 1; min-width: 300px;">
                        <div class="content-card" style="margin-bottom: 30px;">
                            <h2 style="font-size: 1.25rem; margin-top: 0; margin-bottom: 20px; color: var(--text-main);">Book Appointment</h2>
                            <form method="POST" action="member_action.php?action=book_appointment">
                                <div class="form-group">
                                    <label>Select Pastor</label>
                                    <select name="pastor_id" class="form-control" required>
                                        <option value="">-- Choose Pastor --</option>
                                        <?php while($p = $pastors->fetch_assoc()): ?>
                                            <option value="<?= $p['id'] ?>">Pastor <?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?> (<?= htmlspecialchars($p['role']) ?>)</option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Date and Time</label>
                                    <input type="datetime-local" name="datetime" class="form-control" min="<?= date('Y-m-d\TH:i') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Reason for Meeting</label>
                                    <textarea name="reason" class="form-control" rows="4" placeholder="Briefly describe what you'd like to discuss" required></textarea>
                                </div>
                                <button type="submit" class="btn-submit">Request Appointment</button>
                            </form>
                        </div>

                        <?php if($appointments->num_rows > 0): ?>
                        <div class="content-card">
                            <h2 style="font-size: 1.1rem; margin-top: 0; margin-bottom: 15px;">My Appointments</h2>
                            <div class="table-responsive">
                                <table>
                                    <thead><tr><th>Pastor</th><th>Date</th><th>Status</th><th>Time Remaining</th></tr></thead>
                                    <tbody>
                                        <?php while($a = $appointments->fetch_assoc()): ?>
                                        <tr>
                                            <td>Pastor <?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></td>
                                            <td><?= date('M j, Y g:i A', strtotime($a['appointment_date'])) ?></td>
                                            <td>
                                                <?php
                                                $bg = 'var(--text-muted)';
                                                if($a['status'] == 'Approved') $bg = 'var(--success)';
                                                if($a['status'] == 'Declined') $bg = 'var(--danger)';
                                                if($a['status'] == 'Pending') $bg = 'var(--warning)';
                                                ?>
                                                <span class="badge" style="background: <?= $bg ?>; color: white;"><?= $a['status'] ?></span>
                                            </td>
                                            <td>
                                                <?php if($a['status'] == 'Approved'): ?>
                                                    <span class="countdown-timer" data-time="<?= htmlspecialchars($a['appointment_date']) ?>" style="font-weight: bold; color: var(--primary);">Calculating...</span>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted);">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <script>
                        function updateCountdowns() {
                            const timers = document.querySelectorAll('.countdown-timer');
                            const now = new Date().getTime();

                            timers.forEach(timer => {
                                const targetTime = new Date(timer.getAttribute('data-time')).getTime();
                                const diff = targetTime - now;

                                if (diff <= 0) {
                                    timer.innerHTML = "<span style='color: var(--success);'>Time has arrived!</span>";
                                    return;
                                }

                                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                                const seconds = Math.floor((diff % (1000 * 60)) / 1000);

                                let display = "";
                                if (days > 0) display += days + "d ";
                                if (hours > 0 || days > 0) display += hours + "h ";
                                display += minutes + "m " + seconds + "s";

                                timer.innerText = display;
                            });
                        }
                        setInterval(updateCountdowns, 1000);
                        updateCountdowns(); // run immediately
                        </script>
                        <?php endif; ?>
                    </div>

                    <!-- Messages Section -->
                    <div style="flex: 1; min-width: 300px;">
                        <div class="content-card">
                            <h2 style="font-size: 1.25rem; margin-top: 0; margin-bottom: 20px; color: var(--text-main);">Messages from Pastor</h2>
                            <?php if($messages_query->num_rows > 0): ?>
                                <?php while($msg = $messages_query->fetch_assoc()): ?>
                                    <div class="message-item">
                                        <div class="message-header">
                                            <span class="message-author">Pastor <?= htmlspecialchars($msg['pastor_name']) ?></span>
                                            <span><?= date('M j, Y g:i A', strtotime($msg['created_at'])) ?></span>
                                        </div>
                                        <div class="message-body"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="alert alert-success" style="background:transparent; border-color:var(--border-color); color:var(--text-muted);">
                                    You have no new messages at this time.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php elseif ($tab == 'inspiration_highlights'): ?>
                <div class="page-header">
                    <h1>Daily Quote & Highlights</h1>
                    <p>Inspirational messages and memorable moments from our church.</p>
                </div>

                <div class="content-card" style="margin-bottom: 30px;">
                    <h2 style="font-size: 1.25rem; margin-top: 0; margin-bottom: 20px; color: var(--text-main);">Daily Quote</h2>
                    <?php if ($daily_message): ?>
                        <div style="text-align: center; max-width: 700px; margin: 0 auto;">
                            <svg width="40" height="40" fill="currentColor" style="color: var(--primary); opacity: 0.3; margin-bottom: -15px; position: relative; z-index: 1;" viewBox="0 0 24 24"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/></svg>
                            <h2 style="font-size: 1.8rem; font-style: italic; color: var(--text-main); line-height: 1.5; margin-bottom: 20px; position: relative; z-index: 2;">
                                "<?= nl2br(htmlspecialchars($daily_message['quote'])) ?>"
                            </h2>
                            <p style="color: var(--text-muted); margin-bottom: 30px;">
                                — Pastor <?= htmlspecialchars($daily_message['first_name'] . ' ' . $daily_message['last_name']) ?>
                                <small>(<?= date('M j, Y', strtotime($daily_message['created_at'])) ?>)</small>
                            </p>

                            <?php if ($daily_message['video_file']): ?>
                                <div style="border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                                    <video controls style="width: 100%; max-height: 400px; display: block; background: #000;">
                                        <source src="uploads/<?= htmlspecialchars($daily_message['video_file']) ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">No daily quote posted for today.</div>
                    <?php endif; ?>
                </div>

                <div class="content-card">
                    <h2 style="font-size: 1.25rem; margin-top: 0; margin-bottom: 20px; color: var(--text-main);">Church Highlights</h2>
                    <?php
                    if ($highlights->num_rows > 0):
                        $all_highlights = [];
                        mysqli_data_seek($highlights, 0);
                        while($h = $highlights->fetch_assoc()) {
                            $all_highlights[] = $h;
                        }

                        $photos = array_filter($all_highlights, function($h) {
                            $ext = strtolower(pathinfo($h['image_file'], PATHINFO_EXTENSION));
                            return in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
                        });

                        $videos = array_filter($all_highlights, function($h) {
                            $ext = strtolower(pathinfo($h['image_file'], PATHINFO_EXTENSION));
                            return in_array($ext, ['mp4', 'webm', 'ogg']);
                        });
                    ?>
                        <?php if(count($videos) > 0): ?>
                            <h3 style="margin-top: 10px; color: var(--primary);">Video Highlights</h3>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-top: 15px; margin-bottom: 30px;">
                                <?php foreach($videos as $h):
                                    $ext = strtolower(pathinfo($h['image_file'], PATHINFO_EXTENSION));
                                ?>
                                    <div style="border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; background: var(--bg-card); transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                                        <video controls style="width: 100%; height: 200px; object-fit: cover; background: #000;">
                                            <source src="uploads/<?= htmlspecialchars($h['image_file']) ?>" type="video/<?= $ext === 'ogg' ? 'ogg' : $ext ?>">
                                        </video>
                                        <div style="padding: 15px;">
                                            <?php if($h['caption']): ?>
                                                <p style="font-size: 1rem; color: var(--text-main); margin-bottom: 8px;"><?= htmlspecialchars($h['caption']) ?></p>
                                            <?php endif; ?>
                                            <p style="font-size: 0.8rem; color: var(--text-muted);">
                                                Posted by Pastor <?= htmlspecialchars($h['first_name']) ?> on <?= date('M j, Y', strtotime($h['created_at'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if(count($photos) > 0): ?>
                            <h3 style="margin-top: 10px; color: var(--primary);">Photo Highlights</h3>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-top: 15px;">
                                <?php foreach($photos as $h): ?>
                                    <div style="border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; background: var(--bg-card); transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                                        <img src="uploads/<?= htmlspecialchars($h['image_file']) ?>" alt="Highlight" style="width: 100%; height: 200px; object-fit: cover;">
                                        <div style="padding: 15px;">
                                            <?php if($h['caption']): ?>
                                                <p style="font-size: 1rem; color: var(--text-main); margin-bottom: 8px;"><?= htmlspecialchars($h['caption']) ?></p>
                                            <?php endif; ?>
                                            <p style="font-size: 0.8rem; color: var(--text-muted);">
                                                Posted by Pastor <?= htmlspecialchars($h['first_name']) ?> on <?= date('M j, Y', strtotime($h['created_at'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="alert alert-info">No highlights have been posted yet.</div>
                    <?php endif; ?>
                </div>

            <?php elseif ($tab == 'manage_worshippers' && ($is_worship_leader || $is_vice_worship_leader)): ?>
                <div class="page-header">
                    <h1>Manage Worshippers</h1>
                    <p>Assign tasks and coordinate the worship team.</p>
                </div>

                <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:20px;">
                    <?php
                    $worshippers_count = $conn->query("SELECT COUNT(*) as c FROM members WHERE LOWER(church_role) LIKE '%worshipper%' OR LOWER(department) LIKE '%worship%' OR desired_role_pref='Worshipper'")->fetch_assoc()['c'];
                    $total_tasks = $conn->query("SELECT COUNT(*) as c FROM worship_tasks")->fetch_assoc()['c'];
                    $pending_tasks = $conn->query("SELECT COUNT(*) as c FROM worship_tasks WHERE status='Pending'")->fetch_assoc()['c'];
                    $done_tasks = $conn->query("SELECT COUNT(*) as c FROM worship_tasks WHERE status='Done'")->fetch_assoc()['c'];
                    ?>
                    <div class="stat-card">
                        <h3>Total Worshippers</h3>
                        <div class="value" style="color:var(--primary);"><?= $worshippers_count ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Completed Tasks</h3>
                        <div class="value" style="color:var(--success);"><?= $done_tasks ?></div>
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

                <div class="content-card" style="margin-bottom:20px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
                        <div>
                            <p style="margin:0;color:var(--text-muted);font-size:0.78rem;text-transform:uppercase;font-weight:800;letter-spacing:0.05em;">Worship Team</p>
                            <h2 style="margin:4px 0 0;color:var(--text-main);">Worshippers Profiles</h2>
                        </div>
                    </div>
                    <?php
                    $worshippers_profiles = $conn->query("
                        SELECT id, first_name, last_name, phone, department, church_role, desired_role_pref, profile_picture
                        FROM members
                        WHERE is_approved = 1
                          AND (LOWER(church_role) LIKE '%worshipper%' OR LOWER(department) LIKE '%worship%' OR desired_role_pref = 'Worshipper')
                        ORDER BY first_name ASC, last_name ASC
                    ");
                    ?>
                    <?php if ($worshippers_profiles && $worshippers_profiles->num_rows > 0): ?>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                            <?php while($wp = $worshippers_profiles->fetch_assoc()): ?>
                                <div style="display:flex;align-items:center;gap:12px;padding:12px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-main);">
                                    <img src="uploads/<?= htmlspecialchars($wp['profile_picture'] ?? 'default_avatar.png') ?>" alt="Worshipper profile" style="width:58px;height:58px;border-radius:50%;object-fit:cover;border:2px solid #d946ef;cursor:zoom-in;flex-shrink:0;" onclick="viewProfileImage(this.src);">
                                    <div style="min-width:0;">
                                        <p style="margin:0 0 3px;color:var(--text-main);font-weight:750;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($wp['first_name'] . ' ' . $wp['last_name']) ?></p>
                                        <p style="margin:0 0 4px;color:var(--text-muted);font-size:0.82rem;line-height:1.35;"><?= htmlspecialchars($wp['church_role'] ?: ($wp['desired_role_pref'] ?: 'Worshipper')) ?></p>
                                        <p style="margin:0;color:var(--text-muted);font-size:0.78rem;line-height:1.35;"><?= htmlspecialchars($wp['department'] ?: 'General Church') ?><?= !empty($wp['phone']) ? ' · ' . htmlspecialchars($wp['phone']) : '' ?></p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p style="margin:0;color:var(--text-muted);">No worshippers have chosen the worship role yet.</p>
                    <?php endif; ?>
                </div>

                <div class="content-card" style="margin-bottom:20px;">
                    <h2>Allocate Task</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label>Worshipper</label>
                            <select name="worshipper_id" class="form-input" required>
                                <option value="">Select Worshipper</option>
                                <?php
                                $worshippers_q = $conn->query("SELECT id, first_name, last_name, church_role FROM members WHERE is_approved=1 AND (LOWER(church_role) LIKE '%worshipper%' OR LOWER(department) LIKE '%worship%' OR desired_role_pref = 'Worshipper') ORDER BY first_name ASC");
                                while($w = $worshippers_q->fetch_assoc()) {
                                    echo "<option value='".$w['id']."'>".htmlspecialchars($w['first_name'].' '.$w['last_name']." (".$w['church_role'].")")."</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Task Description (e.g., Take praise, Take worship, Coordinate)</label>
                            <input type="text" name="task_name" class="form-input" required>
                        </div>
                        <button type="submit" name="assign_worship_task" class="btn-submit">Assign Task</button>
                    </form>
                </div>

                <div class="content-card">
                    <h2>Task Log</h2>
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
                        <p style="text-align:center; color:var(--text-muted); padding:20px;">No tasks assigned yet.</p>
                    <?php endif; ?>
                </div>

                <!-- ===== POST ANNOUNCEMENT TO WORSHIPPERS ===== -->
                <div class="content-card" style="margin-bottom:20px;">
                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
                        <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#d946ef,#a21caf);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="20" height="20" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                        </div>
                        <div>
                            <h2 style="margin:0;">Post Announcement to Worshippers</h2>
                            <p style="margin:0; font-size:0.85rem; color:var(--text-muted);">This will notify all registered worshippers in your department.</p>
                        </div>
                    </div>
                    <?php if (!empty($_GET['success'])): ?>
                        <div style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);color:var(--success);padding:12px 16px;border-radius:10px;margin-bottom:16px;">
                            ✅ <?= htmlspecialchars($_GET['success']) ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Announcement Message</label>
                            <textarea name="announcement_message" class="form-input" rows="4" placeholder="Type your announcement to worshippers..." required style="resize:vertical;"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Attach Photo (optional)</label>
                            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                                <label for="ann_file_upload" style="cursor:pointer; display:flex; align-items:center; gap:8px; background:var(--bg-lighter); border:1px dashed var(--border-color); border-radius:10px; padding:10px 16px; font-size:0.9rem; color:var(--text-main); flex:1; min-width:180px;">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    Choose from Gallery
                                </label>
                                <input type="file" id="ann_file_upload" name="announcement_image" accept="image/*" style="display:none;" onchange="previewAnnImage(this)">
                                <label for="ann_camera_upload" style="cursor:pointer; display:flex; align-items:center; gap:8px; background:var(--bg-lighter); border:1px dashed var(--border-color); border-radius:10px; padding:10px 16px; font-size:0.9rem; color:var(--text-main); flex:1; min-width:180px;">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    Take a Photo
                                </label>
                                <input type="file" id="ann_camera_upload" name="announcement_image" accept="image/*" capture="environment" style="display:none;" onchange="previewAnnImage(this)">
                            </div>
                            <div id="ann_image_preview" style="margin-top:10px; display:none;">
                                <img id="ann_preview_img" src="" style="max-width:100%; max-height:200px; border-radius:10px; border:1px solid var(--border-color);">
                                <button type="button" onclick="clearAnnImage()" style="display:block; margin-top:6px; background:none; border:none; color:var(--danger); cursor:pointer; font-size:0.85rem;">✕ Remove image</button>
                            </div>
                        </div>
                        <button type="submit" name="post_worship_announcement" class="btn-submit" style="background:linear-gradient(135deg,#d946ef,#a21caf);">
                            📢 Post Announcement
                        </button>
                    </form>

                    <!-- Previous Announcements -->
                    <?php
                    $prev_anns = $conn->query("SELECT wa.*, m.first_name, m.last_name FROM worship_announcements wa JOIN members m ON wa.leader_id = m.id ORDER BY wa.created_at DESC LIMIT 10");
                    if ($prev_anns && $prev_anns->num_rows > 0): ?>
                    <hr style="margin:24px 0; border-color:var(--border-color);">
                    <h3 style="margin-bottom:14px;">Recent Announcements</h3>
                    <div style="display:flex; flex-direction:column; gap:14px;">
                        <?php while($ann = $prev_anns->fetch_assoc()): ?>
                        <div style="background:var(--bg-lighter); border-radius:12px; padding:16px;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                <strong style="color:var(--primary);"><?= htmlspecialchars($ann['first_name'] . ' ' . $ann['last_name']) ?></strong>
                                <span style="font-size:0.78rem; color:var(--text-muted);"><?= date('M j, Y g:i A', strtotime($ann['created_at'])) ?></span>
                            </div>
                            <p style="margin:0; line-height:1.6;"><?= nl2br(htmlspecialchars($ann['message'])) ?></p>
                            <?php if (!empty($ann['image_path'])): ?>
                            <img src="<?= htmlspecialchars($ann['image_path']) ?>" style="margin-top:10px; max-width:100%; max-height:250px; border-radius:8px; display:block;" alt="Announcement image">
                            <?php endif; ?>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ===== QUERY ANNOUNCEMENT TO GENERAL SECRETARY ===== -->
                <div class="content-card" style="margin-bottom:20px;">
                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
                        <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="20" height="20" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                        </div>
                        <div>
                            <h2 style="margin:0;">Query Announcement to General Secretary</h2>
                            <p style="margin:0; font-size:0.85rem; color:var(--text-muted);">Request the General Church Secretary to post a formal announcement on your behalf.</p>
                        </div>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Query / Request Message</label>
                            <textarea name="query_message" class="form-input" rows="4" placeholder="Describe what you need announced to the church..." required style="resize:vertical;"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Attach Supporting Photo (optional)</label>
                            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                                <label for="query_file_upload" style="cursor:pointer; display:flex; align-items:center; gap:8px; background:var(--bg-lighter); border:1px dashed var(--border-color); border-radius:10px; padding:10px 16px; font-size:0.9rem; color:var(--text-main); flex:1; min-width:180px;">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    Choose from Gallery
                                </label>
                                <input type="file" id="query_file_upload" name="query_image" accept="image/*" style="display:none;" onchange="previewQueryImage(this)">
                                <label for="query_camera_upload" style="cursor:pointer; display:flex; align-items:center; gap:8px; background:var(--bg-lighter); border:1px dashed var(--border-color); border-radius:10px; padding:10px 16px; font-size:0.9rem; color:var(--text-main); flex:1; min-width:180px;">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    Take a Photo
                                </label>
                                <input type="file" id="query_camera_upload" name="query_image" accept="image/*" capture="environment" style="display:none;" onchange="previewQueryImage(this)">
                            </div>
                            <div id="query_image_preview" style="margin-top:10px; display:none;">
                                <img id="query_preview_img" src="" style="max-width:100%; max-height:200px; border-radius:10px; border:1px solid var(--border-color);">
                                <button type="button" onclick="clearQueryImage()" style="display:block; margin-top:6px; background:none; border:none; color:var(--danger); cursor:pointer; font-size:0.85rem;">✕ Remove image</button>
                            </div>
                        </div>
                        <button type="submit" name="query_worship_announcement" class="btn-submit" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);">
                            📨 Submit Query to General Secretary
                        </button>
                    </form>

                    <!-- Previous Queries -->
                    <?php
                    $prev_queries = $conn->query("SELECT * FROM secretary_announcement_queries WHERE secretary_id = $member_id ORDER BY created_at DESC LIMIT 5");
                    if ($prev_queries && $prev_queries->num_rows > 0): ?>
                    <hr style="margin:24px 0; border-color:var(--border-color);">
                    <h3 style="margin-bottom:14px;">My Recent Queries</h3>
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <?php while($q = $prev_queries->fetch_assoc()): ?>
                        <div style="background:var(--bg-lighter); border-radius:10px; padding:14px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
                            <div style="flex:1;">
                                <p style="margin:0; line-height:1.5;"><?= nl2br(htmlspecialchars($q['message'])) ?></p>
                                <span style="font-size:0.75rem; color:var(--text-muted); margin-top:6px; display:block;"><?= date('M j, Y g:i A', strtotime($q['created_at'])) ?></span>

                                <?php
                                $qid = (int)$q['id'];
                                $q_replies = $conn->query("
                                    SELECT dm.*, sender.first_name, sender.last_name, sender.church_role
                                    FROM secretary_direct_messages dm
                                    JOIN members sender ON dm.sender_id = sender.id
                                    WHERE dm.query_id = $qid AND dm.recipient_id = $member_id
                                    ORDER BY dm.created_at ASC
                                ");
                                if ($q_replies && $q_replies->num_rows > 0):
                                    while($rep = $q_replies->fetch_assoc()):
                                ?>
                                    <div style="margin-top:14px; padding-top:14px; border-top:1px dashed var(--border-color); display:flex; gap:10px;">
                                        <div style="width:3px; background:var(--primary); border-radius:2px;"></div>
                                        <div style="flex:1;">
                                            <p style="margin:0; font-size:0.9rem; color:var(--text-main);"><strong>Reply from <?= htmlspecialchars($rep['first_name'] . ' ' . $rep['last_name']) ?> (<?= htmlspecialchars($rep['church_role']) ?>):</strong><br><?= nl2br(htmlspecialchars($rep['message'])) ?></p>
                                            <span style="font-size:0.75rem; color:var(--text-muted); display:block; margin-top:4px;"><?= date('M j, Y g:i A', strtotime($rep['created_at'])) ?></span>
                                        </div>
                                    </div>
                                <?php
                                    endwhile;
                                endif;
                                ?>
                            </div>
                            <span style="background:<?= $q['status'] === 'Approved' ? 'rgba(16,185,129,0.12)' : ($q['status'] === 'Rejected' ? 'rgba(239,68,68,0.1)' : 'rgba(245,158,11,0.1)') ?>; color:<?= $q['status'] === 'Approved' ? 'var(--success)' : ($q['status'] === 'Rejected' ? 'var(--danger)' : 'var(--warning)') ?>; padding:3px 12px; border-radius:20px; font-size:0.82rem; font-weight:600; white-space:nowrap;">
                                <?= htmlspecialchars($q['status']) ?>
                            </span>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <script>
                function previewAnnImage(input) {
                    if (input.files && input.files[0]) {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            document.getElementById('ann_preview_img').src = e.target.result;
                            document.getElementById('ann_image_preview').style.display = 'block';
                        };
                        reader.readAsDataURL(input.files[0]);
                    }
                }
                function clearAnnImage() {
                    document.getElementById('ann_file_upload').value = '';
                    document.getElementById('ann_camera_upload').value = '';
                    document.getElementById('ann_image_preview').style.display = 'none';
                    document.getElementById('ann_preview_img').src = '';
                }
                function previewQueryImage(input) {
                    if (input.files && input.files[0]) {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            document.getElementById('query_preview_img').src = e.target.result;
                            document.getElementById('query_image_preview').style.display = 'block';
                        };
                        reader.readAsDataURL(input.files[0]);
                    }
                }
                function clearQueryImage() {
                    document.getElementById('query_file_upload').value = '';
                    document.getElementById('query_camera_upload').value = '';
                    document.getElementById('query_image_preview').style.display = 'none';
                    document.getElementById('query_preview_img').src = '';
                }
                </script>


            <?php elseif ($tab == 'worship_leadership_chat' && ($is_worship_leader || $is_vice_worship_leader)): ?>
                <div class="page-header">
                    <h1>Worship Leaders Chat</h1>
                    <p>Private group chat for Worship Leaders and the Pastor.</p>
                </div>

                <div class="content-card" style="margin-bottom: 20px;">
                    <?php
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
                    ?>
                    <div style="background:var(--card-bg);border:1px solid rgba(255,255,255,0.05);border-radius:12px;padding:20px;height:400px;overflow-y:auto;display:flex;flex-direction:column;gap:15px;margin-bottom:20px;" id="worship_chat_box">
                        <?php if ($chat_msgs && $chat_msgs->num_rows > 0): ?>
                            <?php while($msg = $chat_msgs->fetch_assoc()): ?>
                                <?php $is_me = ($msg['sender_id'] == $member_id && !$msg['is_pastor']); ?>
                                <div style="display:flex; flex-direction:column; align-items:<?= $is_me ? 'flex-end' : 'flex-start' ?>;">
                                    <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:4px; margin-left:2px; margin-right:2px;">
                                        <?= htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']) ?>
                                        <span style="opacity:0.7; font-size:0.7rem; margin-left:4px;"><?= htmlspecialchars($msg['church_role'] ?? '') ?></span>
                                        <?php if ($msg['is_pastor']): ?>
                                            <span style="background:rgba(239,68,68,0.1);color:var(--danger);padding:1px 6px;border-radius:10px;font-weight:600;margin-left:4px;">Pastor</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="background:<?= $is_me ? 'var(--primary)' : 'var(--bg-lighter)' ?>; color:<?= $is_me ? 'white' : 'var(--text-main)' ?>; padding:10px 14px; border-radius:12px; <?= $is_me ? 'border-bottom-right-radius:2px;' : 'border-bottom-left-radius:2px;' ?> max-width:80%; line-height:1.4;">
                                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                    </div>
                                    <div style="font-size:0.65rem; color:var(--text-muted); margin-top:4px;">
                                        <?= date('M j, g:i A', strtotime($msg['created_at'])) ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div style="text-align:center; color:var(--text-muted); margin:auto;">No messages in Worship Leaders chat yet.</div>
                        <?php endif; ?>
                    </div>

                    <form method="POST" style="display:flex; gap:10px; align-items:center;">
                        <textarea name="chat_message" class="form-input" placeholder="Type your message..." required style="min-height:50px; height:50px; resize:none; margin:0; flex:1;"></textarea>
                        <button type="submit" name="send_worship_chat" class="btn-submit" style="white-space:nowrap; padding:0 25px; height:50px; margin:0; display:flex; align-items:center; gap:8px;">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                            Send
                        </button>
                    </form>
                </div>
                <script>
                    var worship_chat_box = document.getElementById('worship_chat_box');
                    if (worship_chat_box) {
                        worship_chat_box.scrollTop = worship_chat_box.scrollHeight;
                    }
                </script>

            <?php elseif ($tab == 'building_leadership_chat' && $is_building_leader): ?>
                <div class="page-header">
                    <h1>Building Leadership Chat</h1>
                    <p>Private group chat for all Building Construction department leaders.</p>
                </div>

                <div class="content-card" style="margin-bottom: 20px;">
                    <?php
                    $chat_msgs = $conn->query("
                        SELECT blc.id, 'building_construction' as dept_key, blc.sender_id, blc.message, blc.created_at, m.first_name, m.last_name, m.church_role, NULL as is_pastor
                        FROM building_leadership_chat blc
                        JOIN members m ON blc.sender_id = m.id
                        UNION ALL
                        SELECT plm.id, plm.dept_key, plm.pastor_id as sender_id, plm.message, plm.created_at, p.first_name, p.last_name, p.role as church_role, 1 as is_pastor
                        FROM pastor_leader_messages plm
                        JOIN pastors p ON plm.pastor_id = p.id
                        WHERE plm.dept_key = 'building_construction'
                        ORDER BY created_at ASC
                    ");
                    ?>
                    <div id="chatBox" style="max-height: 400px; overflow-y: auto; padding: 15px; background: var(--bg-main); border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 15px;">
                        <?php if ($chat_msgs && $chat_msgs->num_rows > 0): ?>
                            <?php while($cm = $chat_msgs->fetch_assoc()):
                                $is_pastor = !empty($cm['is_pastor']);
                                $is_mine = (!$is_pastor && $cm['sender_id'] == $member_id);
                                $bg = $is_mine ? 'var(--primary)' : ($is_pastor ? 'rgba(16,185,129,0.1)' : 'var(--border-color)');
                                $color = $is_mine ? 'white' : 'var(--text-main)';
                                $border = $is_pastor ? '2px solid #10b981' : 'none';
                            ?>
                                <div style="display: flex; justify-content: <?= $is_mine ? 'flex-end' : 'flex-start' ?>; margin-bottom: 10px;">
                                    <div style="max-width: 75%; padding: 10px 14px; border-radius: 16px; background: <?= $bg ?>; color: <?= $color ?>; border: <?= $border ?>;">
                                        <?php if (!$is_mine): ?>
                                            <div style="font-size: 0.75rem; font-weight: 600; margin-bottom: 4px; opacity: 0.8;">
                                                <?= $is_pastor ? '⛪ Pastor ' : '' ?><?= htmlspecialchars($cm['first_name'] . ($is_pastor ? ' ' . $cm['last_name'] : '')) ?>
                                            </div>
                                        <?php endif; ?>
                                        <p style="margin: 0; line-height: 1.5; white-space: pre-wrap;"><?= htmlspecialchars($cm['message']) ?></p>
                                        <div style="font-size: 0.7rem; margin-top: 4px; opacity: 0.6; text-align: <?= $is_mine ? 'right' : 'left' ?>;"><?= date('M j, g:i A', strtotime($cm['created_at'])) ?></div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 30px; color: var(--text-muted);">
                                <p>No messages yet. Start the conversation!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <form method="POST" action="?tab=building_leadership_chat" style="display: flex; gap: 10px;">
                        <input type="text" name="chat_message" class="form-control" placeholder="Type your message..." required style="margin-bottom: 0; flex: 1;">
                        <button type="submit" class="btn-submit" style="width: auto; padding: 10px 20px; margin: 0;">Send</button>
                    </form>
                </div>

            <?php elseif ($tab == 'manage_sunday_classes'): ?>
                <div class="page-header">
                    <h1>Manage Sunday Classes</h1>
                    <p>View the Sunday School classes assigned to you and the members in each class.</p>
                </div>
                <?php if (!$is_ss_class_teacher): ?>
                    <div class="content-card" style="max-width:620px;">
                        <h2>No Assigned Sunday School Class</h2>
                        <p style="margin:0;color:var(--text-muted);">This panel opens after the pastor or admin approves you as a Sunday School class teacher.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($ss_teacher_classes as $teacher_class): ?>
                        <?php
                        $teacher_class_safe = $conn->real_escape_string($teacher_class);
                        $teacher_students = $conn->query("
                            SELECT id, first_name, last_name, phone, gender, profile_picture, sunday_school_class
                            FROM members
                            WHERE department = 'Sunday School' AND sunday_school_class = '$teacher_class_safe' AND is_approved = 1
                            ORDER BY first_name ASC, last_name ASC
                        ");
                        $teacher_student_rows = [];
                        if ($teacher_students) {
                            while ($student_row = $teacher_students->fetch_assoc()) {
                                $teacher_student_rows[] = $student_row;
                            }
                        }
                        $student_count = count($teacher_student_rows);
                        $roster_dom_id = 'ssRoster_' . preg_replace('/[^A-Za-z0-9]/', '_', $teacher_class);
                        $table_dom_id = 'ssRosterTable_' . preg_replace('/[^A-Za-z0-9]/', '_', $teacher_class);
                        $attendance_dom_id = 'ssAttendance_' . preg_replace('/[^A-Za-z0-9]/', '_', $teacher_class);
                        $recent_class_messages = $conn->query("
                            SELECT message, created_at
                            FROM sunday_school_class_messages
                            WHERE class_name = '$teacher_class_safe' AND teacher_id = $member_id
                            ORDER BY created_at DESC
                            LIMIT 5
                        ");
                        $recent_attendance_registers = $conn->query("
                            SELECT r.*,
                                   SUM(CASE WHEN e.status = 'Present' THEN 1 ELSE 0 END) AS present_count,
                                   COUNT(e.id) AS total_count
                            FROM sunday_school_attendance_registers r
                            LEFT JOIN sunday_school_attendance_entries e ON e.register_id = r.id
                            WHERE r.class_name = '$teacher_class_safe' AND r.teacher_id = $member_id
                            GROUP BY r.id
                            ORDER BY r.attendance_date DESC, r.created_at DESC
                            LIMIT 5
                        ");
                        ?>
                        <div class="content-card" style="margin-bottom:24px;border-left:4px solid #0ea5e9;">
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
                                <div>
                                    <p style="margin:0;color:var(--text-muted);font-size:0.78rem;text-transform:uppercase;font-weight:800;">Assigned Class</p>
                                    <h2 style="margin:4px 0 0;"><?= htmlspecialchars($teacher_class) ?></h2>
                                </div>
                                <span class="badge" style="background:rgba(14,165,233,0.12);color:#0ea5e9;"><?= $student_count ?> member<?= $student_count == 1 ? '' : 's' ?></span>
                            </div>

                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-bottom:18px;">
                                <form method="POST" action="?tab=manage_sunday_classes" style="border:1px solid var(--border-color);border-radius:10px;padding:14px;background:var(--bg-main);">
                                    <input type="hidden" name="post_ss_class_message" value="1">
                                    <input type="hidden" name="class_name" value="<?= htmlspecialchars($teacher_class) ?>">
                                    <label style="display:block;font-weight:750;margin-bottom:8px;">Convey Message to <?= htmlspecialchars($teacher_class) ?> Members</label>
                                    <textarea name="class_message" class="form-control" rows="4" placeholder="Write a message for your class..." required style="resize:vertical;"></textarea>
                                    <button type="submit" class="btn-submit" style="width:auto;margin-top:10px;">Send Message</button>
                                </form>
                                <div style="border:1px solid var(--border-color);border-radius:10px;padding:14px;background:var(--bg-main);">
                                    <h3 style="margin:0 0 10px;font-size:1rem;">Recent Messages</h3>
                                    <?php if ($recent_class_messages && $recent_class_messages->num_rows > 0): ?>
                                        <div style="display:flex;flex-direction:column;gap:10px;max-height:180px;overflow:auto;">
                                            <?php while($rcm = $recent_class_messages->fetch_assoc()): ?>
                                                <div style="padding:10px;border-radius:8px;background:var(--bg-card);border:1px solid var(--border-color);">
                                                    <p style="margin:0;white-space:pre-wrap;font-size:0.9rem;line-height:1.45;"><?= htmlspecialchars($rcm['message']) ?></p>
                                                    <small style="color:var(--text-muted);"><?= date('M j, Y g:i A', strtotime($rcm['created_at'])) ?></small>
                                                </div>
                                            <?php endwhile; ?>
                                        </div>
                                    <?php else: ?>
                                        <p style="margin:0;color:var(--text-muted);font-style:italic;">No messages sent yet.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="content-card" style="box-shadow:none;border:1px solid var(--border-color);background:var(--bg-main);margin-bottom:18px;">
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
                                    <div>
                                        <h3 style="margin:0;font-size:1.05rem;">Sunday School Attendance Register</h3>
                                        <p style="margin:4px 0 0;color:var(--text-muted);font-size:0.9rem;">Mark members who attended, then save and send the register to pastor and admin.</p>
                                    </div>
                                </div>
                                <form method="POST" action="?tab=manage_sunday_classes">
                                    <input type="hidden" name="submit_ss_attendance" value="1">
                                    <input type="hidden" name="attendance_class_name" value="<?= htmlspecialchars($teacher_class) ?>">
                                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-bottom:14px;">
                                        <div class="form-group" style="margin:0;">
                                            <label>Attendance Date</label>
                                            <input type="date" name="attendance_date" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                                        </div>
                                        <div class="form-group" style="margin:0;">
                                            <label>Notes</label>
                                            <input type="text" name="attendance_notes" class="form-control" placeholder="Optional note for this register">
                                        </div>
                                    </div>
                                    <?php if (!empty($teacher_student_rows)): ?>
                                        <p style="margin:0 0 10px;color:var(--text-muted);font-size:0.88rem;">Ticked members will print as <strong>Present</strong>. Unticked members will print as <strong>Absent</strong>.</p>
                                        <div id="<?= htmlspecialchars($attendance_dom_id) ?>" class="table-responsive" style="margin-bottom:14px;">
                                            <table>
                                                <thead><tr><th>Tick if Present</th><th>Profile</th><th>Name</th><th>Gender</th><th>Phone</th></tr></thead>
                                                <tbody>
                                                <?php foreach ($teacher_student_rows as $student): ?>
                                                    <tr>
                                                        <td><input type="checkbox" name="attendance_present[<?= (int)$student['id'] ?>]" value="1" checked style="width:18px;height:18px;"></td>
                                                        <td><img src="uploads/<?= htmlspecialchars($student['profile_picture'] ?? 'default_avatar.png') ?>" alt="Profile" style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:1px solid var(--border-color);"></td>
                                                        <td style="font-weight:650;"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                                        <td><?= htmlspecialchars($student['gender'] ?: '-') ?></td>
                                                        <td><?= htmlspecialchars($student['phone'] ?: '-') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <button type="submit" class="btn-submit" style="width:auto;margin:0;">Save & Send Attendance</button>
                                        <script>
                                            document.querySelector("input[name='attendance_date']").addEventListener("change", function() {
                                                this.closest("form").querySelectorAll("input[type='checkbox'][name^='attendance_present']").forEach(cb => cb.checked = false);
                                            });
                                        </script>
                                    <?php else: ?>
                                        <p style="margin:0;color:var(--text-muted);font-style:italic;">No members are currently in this class.</p>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <div class="content-card" style="box-shadow:none;border:1px solid var(--border-color);background:var(--bg-main);margin-bottom:18px;">
                                <h3 style="margin:0 0 12px;font-size:1.05rem;">Recent Attendance Registers</h3>
                                <?php if ($recent_attendance_registers && $recent_attendance_registers->num_rows > 0): ?>
                                    <div style="display:flex;flex-direction:column;gap:12px;">
                                    <?php while($reg = $recent_attendance_registers->fetch_assoc()):
                                        $reg_id = (int)$reg['id'];
                                        $reg_entries = $conn->query("
                                            SELECT e.status, m.first_name, m.last_name, m.gender, m.phone, m.profile_picture
                                            FROM sunday_school_attendance_entries e
                                            JOIN members m ON e.member_id = m.id
                                            WHERE e.register_id = $reg_id
                                            ORDER BY m.first_name ASC, m.last_name ASC
                                        ");
                                    ?>
                                        <div style="border:1px solid var(--border-color);border-radius:10px;padding:12px;background:var(--bg-card);">
                                            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
                                                <div>
                                                    <strong><?= htmlspecialchars(date('M j, Y', strtotime($reg['attendance_date']))) ?></strong>
                                                    <p style="margin:3px 0 0;color:var(--text-muted);font-size:0.86rem;">Present: <?= (int)$reg['present_count'] ?> of <?= (int)$reg['total_count'] ?></p>
                                                </div>
                                                <button type="button" class="btn-submit" style="width:auto;height:auto;padding:8px 12px;margin:0;background:var(--bg-lighter);color:var(--text-main);border:1px solid var(--border-color);" onclick="printSsAttendance('ssAttendanceReport<?= $reg_id ?>')">Print / Save PDF</button>
                                            </div>
                                            <div id="ssAttendanceReport<?= $reg_id ?>" style="display:none;">
                                                <div data-report-title="Munyari EAPC" data-class-name="<?= htmlspecialchars($teacher_class) ?>" data-teacher-name="<?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>" data-teacher-photo="uploads/<?= htmlspecialchars($member['profile_picture'] ?? 'default_avatar.png') ?>" data-attendance-date="<?= htmlspecialchars(date('M j, Y', strtotime($reg['attendance_date']))) ?>" data-notes="<?= htmlspecialchars($reg['notes'] ?? '') ?>"></div>
                                                <?php if ($reg_entries && $reg_entries->num_rows > 0): ?>
                                                    <?php while($entry = $reg_entries->fetch_assoc()): ?>
                                                        <div class="attendance-print-row" data-name="<?= htmlspecialchars($entry['first_name'] . ' ' . $entry['last_name']) ?>" data-gender="<?= htmlspecialchars($entry['gender'] ?: '-') ?>" data-phone="<?= htmlspecialchars($entry['phone'] ?: '-') ?>" data-status="<?= htmlspecialchars($entry['status']) ?>" data-photo="uploads/<?= htmlspecialchars($entry['profile_picture'] ?? 'default_avatar.png') ?>"></div>
                                                    <?php endwhile; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <p style="margin:0;color:var(--text-muted);font-style:italic;">No saved attendance registers yet.</p>
                                <?php endif; ?>
                            </div>

                            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
                                <h3 style="margin:0;font-size:1rem;">Class Roster</h3>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                    <button type="button" class="btn-submit" style="width:auto;height:auto;padding:8px 12px;margin:0;background:var(--bg-lighter);color:var(--text-main);border:1px solid var(--border-color);" onclick="printSsRoster('<?= htmlspecialchars($roster_dom_id, ENT_QUOTES) ?>', '<?= htmlspecialchars($teacher_class, ENT_QUOTES) ?>')">Print</button>
                                    <button type="button" class="btn-submit" style="width:auto;height:auto;padding:8px 12px;margin:0;background:var(--bg-lighter);color:var(--text-main);border:1px solid var(--border-color);" onclick="printSsRoster('<?= htmlspecialchars($roster_dom_id, ENT_QUOTES) ?>', '<?= htmlspecialchars($teacher_class, ENT_QUOTES) ?>')">Save as PDF</button>
                                    <button type="button" class="btn-submit" style="width:auto;height:auto;padding:8px 12px;margin:0;" onclick="downloadSsRosterCsv('<?= htmlspecialchars($table_dom_id, ENT_QUOTES) ?>', '<?= htmlspecialchars($teacher_class, ENT_QUOTES) ?>')">Download CSV</button>
                                </div>
                            </div>

                            <div id="<?= htmlspecialchars($roster_dom_id) ?>">
                                <div class="ss-print-title" style="display:none;">
                                    <h2><?= htmlspecialchars($teacher_class) ?> Sunday School Class Roster</h2>
                                    <p>Teacher: <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?> | Printed: <?= date('M j, Y') ?></p>
                                </div>
                            <?php if (!empty($teacher_student_rows)): ?>
                            <div class="table-responsive">
                                <table id="<?= htmlspecialchars($table_dom_id) ?>">
                                    <thead><tr><th>Profile</th><th>Name</th><th>Phone</th><th>Gender</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($teacher_student_rows as $student): ?>
                                        <tr>
                                            <td><img src="uploads/<?= htmlspecialchars($student['profile_picture'] ?? 'default_avatar.png') ?>" alt="Profile" style="width:38px;height:38px;border-radius:50%;object-fit:cover;border:1px solid var(--border-color);cursor:zoom-in;" onclick="viewProfileImage(this.src);"></td>
                                            <td style="font-weight:650;"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                            <td><?= htmlspecialchars($student['phone'] ?: '-') ?></td>
                                            <td><span class="badge" style="background:rgba(99,102,241,0.1);color:#6366f1;"><?= htmlspecialchars($student['gender'] ?: '-') ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                                <p style="margin:0;color:var(--text-muted);font-style:italic;">No members are currently in this class.</p>
                            <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <script>
                    function printSsRoster(sectionId, className) {
                        const section = document.getElementById(sectionId);
                        if (!section) return;
                        const table = section.querySelector('table');
                        const students = table ? Array.from(table.querySelectorAll('tbody tr')).map(row => {
                            const cells = Array.from(row.querySelectorAll('td'));
                            const profile = row.querySelector('img');
                            return {
                                photo: profile ? profile.src : '',
                                name: cells[1] ? cells[1].innerText.trim() : '',
                                phone: cells[2] ? cells[2].innerText.trim() : '-',
                                gender: cells[3] ? cells[3].innerText.trim() : '-'
                            };
                        }) : [];
                        const escapeHtml = value => String(value || ').replace(/[&<>"']/g, char => ({
                            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
                        }[char]));
                        
                        const rosterHtml = students.length
                            ? students.map((student, index) => `
                                <tr>
                                    <td style="padding:5px 8px;border:1px solid #ddd;">${index + 1}</td>
                                    <td style="padding:4px 8px;border:1px solid #ddd;">${student.photo ? `<img src="${escapeHtml(student.photo)}" style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid #a5f3fc;display:block;" alt="">` : '}</td>
                                    <td style="padding:5px 8px;border:1px solid #ddd;font-weight:600;">${escapeHtml(student.name)}</td>
                                    <td style="padding:5px 8px;border:1px solid #ddd;">${escapeHtml(student.gender)}</td>
                                    <td style="padding:5px 8px;border:1px solid #ddd;">${escapeHtml(student.phone)}</td>
                                </tr>
                            `).join(')
                            : '<tr><td colspan="5" style="text-align:center;padding:20px;color:#666;">No members are currently in this class.</td></tr>';
                        
                        const printWindow = window.open(', '_blank');
                        if (!printWindow) return;
                        const teacherPhoto = new URL('uploads/<?= htmlspecialchars($member['profile_picture'] ?? 'default_avatar.png', ENT_QUOTES) ?>', window.location.href).href;
                        const logoUrl = new URL('church_logo.jpg', window.location.href).href;
                        
                        let html = `<!doctype html><html><head><title>${escapeHtml(className)} Roster</title>
                        <style>
                            body{font-family:Arial,sans-serif;padding:18px;} 
                            table{width:100%;border-collapse:collapse;} 
                            th,td{padding:5px 8px;border:1px solid #ccc;font-size:0.8rem;} 
                            @media print{@page{size:A4 portrait;margin:10mm;}}
                        </style>
                        </head><body>
                        
                        <div class="print-watermark" style="position:fixed; top:50%; left:50%; transform:translate(-50%,-50%) rotate(-45deg); font-size:4.5rem; color:rgba(0,0,0,0.04); font-weight:bold; white-space:nowrap; z-index:-1; pointer-events:none;">E.A.P.C MUNYARI CHURCH</div>
                        
                        <table style="width:100%;border-collapse:collapse;">
                            <thead>
                                <tr>
                                    <th colspan="5" style="border:none; padding: 0 0 16px 0; font-weight:normal; background:white;">
                                        <div style="display:flex;align-items:center;justify-content:center;gap:18px;margin-bottom:10px;">
                                            <img src="${escapeHtml(teacherPhoto)}" alt="Teacher" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid #0e7490;">
                                            <div>
                                                <div style="font-size:0.75rem;color:#555;text-transform:uppercase;letter-spacing:1px;">Class Teacher</div>
                                                <div style="font-size:1.2rem;font-weight:700;color:#0e7490;"><?= htmlspecialchars(strtoupper($member['first_name'] . ' ' . $member['last_name'])) ?></div>
                                            </div>
                                        </div>
                                        <div style="text-align:center;margin-bottom:6px;border-bottom:2px solid #0e7490;padding-bottom:12px; position:relative;">
                                            <img src="${escapeHtml(logoUrl)}" style="position:absolute; left:0; top:0; width:70px; height:auto; border-radius:50%;">
                                            <h2 style="margin:0;font-size:1.35rem;color:#0e7490;padding-top:10px;">E.A.P.C MUNYARI CHURCH &mdash; ${escapeHtml(className).toUpperCase()} CLASS MEMBERS</h2>
                                            <p style="margin:4px 0 0;font-size:0.82rem;color:#555;">Printed on: <?= date('F j, Y g:i A') ?></p>
                                        </div>
                                    </th>
                                </tr>
                                <tr style="background:#0e7490;color:white;">
                                    <th style="padding:7px 8px;border:1px solid #aaa;text-align:left;">#</th>
                                    <th style="padding:7px 8px;border:1px solid #aaa;text-align:left;">Photo</th>
                                    <th style="padding:7px 8px;border:1px solid #aaa;text-align:left;">Full Name</th>
                                    <th style="padding:7px 8px;border:1px solid #aaa;text-align:left;">Gender</th>
                                    <th style="padding:7px 8px;border:1px solid #aaa;text-align:left;">Phone</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rosterHtml}
                            </tbody>
                        </table>
                        
                        <div style="display:flex; justify-content:space-between; margin-top:60px; padding:0 40px; page-break-inside:avoid;">
                            <div style="text-align:center; align-self:flex-end;">
                                <div style="font-size:0.85rem; font-weight:bold; text-transform:uppercase; color:#0e7490;">Teacher\'s Signature</div>
                                <div style="font-size:0.75rem; font-weight:bold; color:#333; margin-top:2px; margin-bottom:10px;"><?= htmlspecialchars(strtoupper($member['first_name'] . ' ' . $member['last_name'])) ?></div>
                                <div style="font-size:0.9rem; margin-bottom:8px; display:flex; align-items:flex-end; justify-content:center; gap:8px;">
                                    <span style="font-style:italic;color:#333;">Sign:</span>
                                    <span style="display:inline-block; border-bottom:1px solid #000; width:220px; height:14px;"></span>
                                </div>
                                <div style="font-size:0.75rem; color:#555; margin-top:3px;">Date: .......................................</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="border:2px dashed #aaa; width:120px; height:120px; border-radius:50%; margin:0 auto 10px; display:flex; align-items:center; justify-content:center; color:#ccc; font-size:0.85rem; text-transform:uppercase; letter-spacing:1px;">Official<br>Stamp</div>
                            </div>
                        </div>
                        
                        </body></html>`;
                        printWindow.document.write(html);
                        printWindow.document.close();
                        printWindow.focus();
                        setTimeout(() => printWindow.print(), 600);
                    }
                    
                    function printSsAttendance(reportId) {
                        const report = document.getElementById(reportId);
                        if (!report) return;
                        const meta = report.querySelector('[data-report-title]');
                        const rows = Array.from(report.querySelectorAll('.attendance-print-row'));
                        const escapeHtml = value => String(value || ').replace(/[&<>"']/g, char => ({
                            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
                        }[char]));
                        const absUrl = value => value ? new URL(value, window.location.href).href : '';
                        
                        const rowHtml = rows.length
                            ? rows.map((row, index) => `
                                <tr>
                                    <td style="padding:5px 8px;border:1px solid #ddd;">${index + 1}</td>
                                    <td style="padding:4px 8px;border:1px solid #ddd;">${row.dataset.photo ? `<img src="${escapeHtml(absUrl(row.dataset.photo))}" style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid #a5f3fc;display:block;" alt="">` : '}</td>
                                    <td style="padding:5px 8px;border:1px solid #ddd;font-weight:600;${row.dataset.status === 'Absent' ? 'color:#999;' : '}">${escapeHtml(row.dataset.name)}</td>
                                    <td style="padding:5px 8px;border:1px solid #ddd;${row.dataset.status === 'Absent' ? 'color:#999;' : '}">${escapeHtml(row.dataset.gender)}</td>
                                    <td style="padding:5px 8px;border:1px solid #ddd;${row.dataset.status === 'Absent' ? 'color:#999;' : '}">${escapeHtml(row.dataset.phone)}</td>
                                    <td style="padding:5px 8px;border:1px solid #ddd;font-weight:700;${row.dataset.status === 'Absent' ? 'color:#dc2626;' : 'color:#15803d;'}">${escapeHtml(row.dataset.status)}</td>
                                </tr>
                            `).join(')
                            : '<tr><td colspan="6" style="text-align:center;padding:20px;color:#666;">No attendance entries were saved for this register.</td></tr>';
                            
                        const printWindow = window.open(', '_blank');
                        if (!printWindow) return;
                        const logoUrl = new URL('church_logo.jpg', window.location.href).href;
                        
                        let html = `<!doctype html><html><head><title>Sunday School Attendance</title>
                        <style>
                            body{font-family:Arial,sans-serif;padding:18px;} 
                            table{width:100%;border-collapse:collapse;} 
                            th,td{padding:5px 8px;border:1px solid #ccc;font-size:0.8rem;} 
                            @media print{@page{size:A4 portrait;margin:10mm;}}
                        </style>
                        </head><body>
                        
                        <div class="print-watermark" style="position:fixed; top:50%; left:50%; transform:translate(-50%,-50%) rotate(-45deg); font-size:4.5rem; color:rgba(0,0,0,0.04); font-weight:bold; white-space:nowrap; z-index:-1; pointer-events:none;">E.A.P.C MUNYARI CHURCH</div>
                        
                        <table style="width:100%;border-collapse:collapse;">
                            <thead>
                                <tr>
                                    <th colspan="6" style="border:none; padding: 0 0 16px 0; font-weight:normal; background:white;">
                                        <div style="display:flex;align-items:center;justify-content:center;gap:18px;margin-bottom:10px;">
                                            <img src="${escapeHtml(absUrl(meta.dataset.teacherPhoto))}" alt="Teacher" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid #0e7490;">
                                            <div>
                                                <div style="font-size:0.75rem;color:#555;text-transform:uppercase;letter-spacing:1px;">Class Teacher</div>
                                                <div style="font-size:1.2rem;font-weight:700;color:#0e7490;">${escapeHtml(meta.dataset.teacherName).toUpperCase()}</div>
                                            </div>
                                        </div>
                                        <div style="text-align:center;margin-bottom:6px;border-bottom:2px solid #0e7490;padding-bottom:12px; position:relative;">
                                            <img src="${escapeHtml(logoUrl)}" style="position:absolute; left:0; top:0; width:70px; height:auto; border-radius:50%;">
                                            <h2 style="margin:0;font-size:1.35rem;color:#0e7490;padding-top:10px;">E.A.P.C MUNYARI CHURCH &mdash; ${escapeHtml(meta.dataset.className).toUpperCase()} ATTENDANCE</h2>
                                            <p style="margin:4px 0 0;font-size:0.82rem;color:#555;">Date: ${escapeHtml(meta.dataset.attendanceDate)} ${meta.dataset.notes ? ' | Notes: ' + escapeHtml(meta.dataset.notes) : '}</p>
                                        </div>
                                    </th>
                                </tr>
                                <tr style="background:#0e7490;color:white;">
                                    <th style="padding:7px 8px;border:1px solid #aaa;text-align:left;">#</th>
                                    <th style="padding:7px 8px;border:1px solid #aaa;text-align:left;">Photo</th>
                                    <th style="padding:7px 8px;border:1px solid #aaa;text-align:left;">Full Name</th>
                                    <th style="padding:7px 8px;border:1px solid #aaa;text-align:left;">Gender</th>
                                    <th style="padding:7px 8px;border:1px solid #aaa;text-align:left;">Phone</th>
                                    <th style="padding:7px 8px;border:1px solid #aaa;text-align:left;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rowHtml}
                            </tbody>
                        </table>
                        
                        <div style="display:flex; justify-content:space-between; margin-top:60px; padding:0 40px; page-break-inside:avoid;">
                            <div style="text-align:center; align-self:flex-end;">
                                <div style="font-size:0.85rem; font-weight:bold; text-transform:uppercase; color:#0e7490;">Teacher\'s Signature</div>
                                <div style="font-size:0.75rem; font-weight:bold; color:#333; margin-top:2px; margin-bottom:10px;">${escapeHtml(meta.dataset.teacherName).toUpperCase()}</div>
                                <div style="font-size:0.9rem; margin-bottom:8px; display:flex; align-items:flex-end; justify-content:center; gap:8px;">
                                    <span style="font-style:italic;color:#333;">Sign:</span>
                                    <span style="display:inline-block; border-bottom:1px solid #000; width:220px; height:14px;"></span>
                                </div>
                                <div style="font-size:0.75rem; color:#555; margin-top:3px;">Date: .......................................</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="border:2px dashed #aaa; width:120px; height:120px; border-radius:50%; margin:0 auto 10px; display:flex; align-items:center; justify-content:center; color:#ccc; font-size:0.85rem; text-transform:uppercase; letter-spacing:1px;">Official<br>Stamp</div>
                            </div>
                        </div>
                        
                        </body></html>`;
                        printWindow.document.write(html);
                        printWindow.document.close();
                        printWindow.focus();
                        setTimeout(() => printWindow.print(), 600);
                    }
                    function downloadSsRosterCsv(tableId, className) {
                        const table = document.getElementById(tableId);
                        if (!table) return;
                        const rows = Array.from(table.querySelectorAll('tr')).map(row => {
                            const cells = Array.from(row.querySelectorAll('th,td')).slice(1);
                            return cells.map(cell => '"' + cell.innerText.replace(/"/g, '""').trim() + '"').join(',');
                        }).join('\n');
                        const blob = new Blob([rows], {type: 'text/csv;charset=utf-8;'});
                        const link = document.createElement('a');
                        link.href = URL.createObjectURL(blob);
                        link.download = className.replace(/\s+/g, '_').toLowerCase() + '_sunday_school_roster.csv';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        URL.revokeObjectURL(link.href);
                    }
                    </script>
                <?php endif; ?>

            <?php elseif ($tab == 'manage_classes'): ?>
                <?php
                $is_ss_member = ($member['department'] ?? '') === 'Sunday School';
                $has_ss_class = !empty($member['sunday_school_class']);
                $current_ss_class = trim($member['sunday_school_class'] ?? '');
                $next_ss_class = ss_next_class($current_ss_class);
                $ss_classes = $has_ss_class
                    ? array_values(array_filter([$current_ss_class, $next_ss_class]))
                    : ['Little Angels', 'Champions', 'Battalion', 'Conquerors'];
                $pending_ss_class_request = null;
                if ($is_ss_member) {
                    $pending_ss_q = $conn->query("SELECT * FROM sunday_school_class_requests WHERE member_id = $member_id AND status = 'Pending' ORDER BY requested_at DESC LIMIT 1");
                    $pending_ss_class_request = ($pending_ss_q && $pending_ss_q->num_rows > 0) ? $pending_ss_q->fetch_assoc() : null;
                }
                ?>
                <div class="page-header">
                    <h1>Manage Classes</h1>
                    <p>Select your first Sunday School class. After it is locked, any change is sent for approval.</p>
                    <?php if ($is_ss_member && !$has_ss_class): ?>
                    <div style="display:flex;align-items:center;gap:10px;margin-top:10px;background:rgba(245,158,11,0.1);border:1.5px solid #f59e0b;border-radius:10px;padding:10px 16px;flex-wrap:wrap;">
                        <span style="font-size:1.3rem;">🔒</span>
                        <span style="color:#f59e0b;font-weight:700;font-size:0.95rem;">You must choose your Sunday School class before other tabs can open.</span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (isset($_GET['success'])): ?><div class="alert alert-success" style="max-width:520px;margin-bottom:16px;"><?= htmlspecialchars($_GET['success']) ?></div><?php endif; ?>
                <?php if (isset($_GET['error'])): ?><div class="alert alert-danger" style="max-width:520px;margin-bottom:16px;"><?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>

                <?php if (!$is_ss_member): ?>
                    <div class="content-card" style="max-width:520px;">
                        <h2>Sunday School Classes</h2>
                        <p style="color:var(--text-muted);margin:0;">This tab is only for Sunday School members.</p>
                    </div>
                <?php else: ?>
                    <?php if ($has_ss_class): ?>
                        <?php render_ss_teacher_card($conn, $member['sunday_school_class']); ?>
                        <?php render_ss_class_messages_card($conn, $member['sunday_school_class']); ?>
                    <?php endif; ?>
                    <div class="content-card" style="max-width:520px;margin-bottom:24px;border:2px solid <?= $has_ss_class ? '#10b981' : '#f59e0b' ?>;position:relative;">
                        <div style="position:absolute;top:16px;right:16px;width:30px;height:30px;border-radius:50%;background:<?= $has_ss_class ? '#10b981' : '#f59e0b' ?>;display:flex;align-items:center;justify-content:center;font-size:1rem;color:white;font-weight:800;"><?= $has_ss_class ? '✓' : '!' ?></div>
                        <h2 style="color:<?= $has_ss_class ? '#10b981' : '#f59e0b' ?>;"><?= $has_ss_class ? 'Locked Sunday School Class' : 'Choose Your Class' ?></h2>
                        <?php if ($has_ss_class): ?>
                            <div class="alert alert-success" style="margin-top:10px;">Your class is locked as <strong><?= htmlspecialchars($member['sunday_school_class']) ?></strong>.</div>
                            <?php if ($pending_ss_class_request): ?>
                                <div class="alert alert-warning" style="margin-top:10px;">Pending approval: change from <strong><?= htmlspecialchars($pending_ss_class_request['current_class']) ?></strong> to <strong><?= htmlspecialchars($pending_ss_class_request['requested_class']) ?></strong>.</div>
                            <?php else: ?>
                                <p style="color:var(--text-muted);font-size:0.92rem;"><?= $next_ss_class ? 'The only allowed transfer is from ' . htmlspecialchars($current_ss_class) . ' to ' . htmlspecialchars($next_ss_class) . '. Your request will wait for approval.' : 'Conquerors is the highest Sunday School class.' ?></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p style="color:var(--text-muted);font-size:0.92rem;">Choose the class you belong to. This is required the first time you log in as a Sunday School member.</p>
                        <?php endif; ?>
                        <form method="POST" action="?tab=manage_classes" style="margin-top:14px;">
                            <input type="hidden" name="save_ss_class" value="1">
                            <div class="form-group">
                                <label>Your Sunday School Class</label>
                                <select name="ss_class" class="form-control" required <?= $pending_ss_class_request ? 'disabled' : '' ?>>
                                    <option value="">-- Select Class --</option>
                                    <?php foreach ($ss_classes as $ss_class_name): ?>
                                        <option value="<?= htmlspecialchars($ss_class_name) ?>" <?= ($member['sunday_school_class'] ?? '') === $ss_class_name ? 'selected' : '' ?>><?= htmlspecialchars($ss_class_name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn-submit" <?= ($pending_ss_class_request || ($has_ss_class && !$next_ss_class)) ? 'disabled' : '' ?> style="<?= $has_ss_class ? 'background:var(--bg-lighter);color:var(--text-main);border:1px solid var(--border-color);' : '' ?>"><?= $pending_ss_class_request ? 'Waiting For Approval' : ($has_ss_class ? ($next_ss_class ? 'Request Transfer' : 'Highest Class Reached') : 'Save Class & Continue') ?></button>
                        </form>
                    </div>
                    <?php if ($has_ss_class && !$setup_completed): ?>
                        <div class="alert alert-success" style="max-width:520px;">
                            Class saved. Continue setup in Account Settings if username or profile picture is still missing.
                            <a href="?tab=settings" style="margin-left:12px;font-weight:700;">Go to Settings &rarr;</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            <?php elseif ($tab == 'settings'): ?>
                <div class="page-header">
                    <h1>Account Settings</h1>
                    <p>Manage your profile, credentials and preferences.</p>
                    <?php if (!$setup_completed): ?>
                    <div style="display:flex; align-items:center; gap:10px; margin-top:10px; background:rgba(239,68,68,0.08); border:1.5px solid #ef4444; border-radius:10px; padding:10px 16px; flex-wrap:wrap;">
                        <span style="font-size:1.3rem;">&#128272;</span>
                        <span style="color:#ef4444; font-weight:700; font-size:0.95rem;"><strong>Action Required:</strong> Create your unique username and upload a profile picture below. Then, click <strong>Church Village & Role</strong><?= ($member['department'] ?? '') === 'Sunday School' ? ' and <strong>Manage Classes</strong>' : '' ?> to finish setup and unlock all tabs.</span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!$setup_completed): ?>
                <!-- ═══ MANDATORY FIRST-TIME SETUP ═══ -->

                <!-- STEP 1: Username -->
                <div class="content-card" style="max-width: 480px; margin-bottom: 24px; border: 2px solid <?= $has_username ? '#10b981' : '#6366f1' ?>; position:relative;">
                    <div style="position:absolute; top:16px; right:16px; width:28px; height:28px; border-radius:50%; background:<?= $has_username ? '#10b981' : '#e2e8f0' ?>; display:flex; align-items:center; justify-content:center; font-size:1rem;"><?= $has_username ? '✓' : '1' ?></div>
                    <h2 style="color:<?= $has_username ? '#10b981' : 'var(--primary)' ?>;">Step 1 — Create Your Username</h2>
                    <?php if ($has_username): ?>
                        <div class="alert alert-success" style="margin-top:10px;">Username set: <strong><?= htmlspecialchars($member['username']) ?></strong> — you can change it below if needed.</div>
                    <?php else: ?>
                        <p style="color:var(--text-muted); font-size:0.9rem;">Your username will be used to log in and recover your password. Choose something memorable — only letters, numbers, dots (.) and underscores (_) allowed.</p>
                    <?php endif; ?>
                    <form method="POST" action="?tab=settings" style="margin-top:14px;">
                        <input type="hidden" name="save_username" value="1">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="new_username" id="setupUsernameInput" class="form-control member-username-check" value="" placeholder="e.g. peter_2026" pattern="[a-zA-Z0-9_.]+" minlength="3" data-current-username="<?= htmlspecialchars($member['username'] ?? '') ?>" data-submit-id="setupUsernameSubmit" required>
                            <div style="margin-top:8px;padding:9px 11px;border-radius:8px;border:1px solid rgba(239,68,68,0.35);background:rgba(239,68,68,0.1);color:var(--danger);font-size:0.86rem;font-weight:600;">Your username must be unique. You will use it to log in to your account, so do not forget it.</div>
                            <small class="username-live-status" style="display:block;margin-top:4px;color:var(--text-muted);">Type a username to check if it is available.</small>
                        </div>
                        <button type="submit" id="setupUsernameSubmit" class="btn-submit" style="<?= $has_username ? 'background:var(--bg-lighter);color:var(--text-main);border:1px solid var(--border-color);' : '' ?>"><?= $has_username ? 'Change Username' : 'Save Username' ?></button>
                    </form>
                </div>

                <!-- STEP 2: Profile Picture -->
                <div class="content-card" style="max-width: 480px; margin-bottom: 30px; border: 2px solid <?= $has_profile_pic ? '#10b981' : '#6366f1' ?>; position:relative;">
                    <div style="position:absolute; top:16px; right:16px; width:28px; height:28px; border-radius:50%; background:<?= $has_profile_pic ? '#10b981' : '#e2e8f0' ?>; display:flex; align-items:center; justify-content:center; font-size:1rem;"><?= $has_profile_pic ? '✓' : '2' ?></div>
                    <h2 style="color:<?= $has_profile_pic ? '#10b981' : 'var(--primary)' ?>;">Step 2 — Upload a Profile Picture</h2>
                    <div style="display: flex; align-items: center; gap: 20px; margin: 14px 0;">
                        <img src="uploads/<?= htmlspecialchars($member['profile_picture'] ?? 'default_avatar.png') ?>" alt="Profile" style="width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 3px solid <?= $has_profile_pic ? '#10b981' : 'var(--border-color)' ?>; cursor: zoom-in;" onclick="viewProfileImage(this.src);">
                        <div>
                            <?php if ($has_profile_pic): ?>
                                <p style="font-weight:600; color:#10b981;">✓ Picture uploaded!</p>
                                <small style="color:var(--text-muted);">You can replace it anytime.</small>
                            <?php else: ?>
                                <p style="font-weight:500;">No picture yet</p>
                                <small style="color:var(--text-muted);">JPG or PNG, max 5MB.</small>
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


                <?php
                $is_ss_member = ($member['department'] ?? '') === 'Sunday School';
                $has_ss_class = !empty($member['sunday_school_class']);
                $current_ss_class = trim($member['sunday_school_class'] ?? '');
                $next_ss_class = ss_next_class($current_ss_class);
                $ss_classes = $has_ss_class
                    ? array_values(array_filter([$current_ss_class, $next_ss_class]))
                    : ['Little Angels', 'Champions', 'Battalion', 'Conquerors'];
                $pending_ss_class_request = null;
                if ($is_ss_member) {
                    $pending_ss_q = $conn->query("SELECT * FROM sunday_school_class_requests WHERE member_id = $member_id AND status = 'Pending' ORDER BY requested_at DESC LIMIT 1");
                    $pending_ss_class_request = ($pending_ss_q && $pending_ss_q->num_rows > 0) ? $pending_ss_q->fetch_assoc() : null;
                }
                ?>
                <?php if ($is_ss_member): ?>
                <!-- STEP: Sunday School Class Selection -->
                <div class="content-card" style="max-width: 480px; margin-bottom: 24px; border: 2px solid <?= $has_ss_class ? '#10b981' : '#f59e0b' ?>; position:relative;">
                    <div style="position:absolute; top:16px; right:16px; width:28px; height:28px; border-radius:50%; background:<?= $has_ss_class ? '#10b981' : '#f59e0b' ?>; display:flex; align-items:center; justify-content:center; font-size:1rem;"><?= $has_ss_class ? '✓' : '3' ?></div>
                    <h2 style="color:<?= $has_ss_class ? '#10b981' : '#f59e0b' ?>;">Step 3 - <?= $has_ss_class ? 'Locked Sunday School Class' : 'Choose Your Sunday School Class' ?></h2>
                    <?php if ($has_ss_class): ?>
                        <div class="alert alert-success" style="margin-top:10px;">Class locked: <strong><?= htmlspecialchars($member['sunday_school_class']) ?></strong>.</div>
                        <?php if ($pending_ss_class_request): ?>
                            <div class="alert alert-warning" style="margin-top:10px;">Pending approval: change from <strong><?= htmlspecialchars($pending_ss_class_request['current_class']) ?></strong> to <strong><?= htmlspecialchars($pending_ss_class_request['requested_class']) ?></strong>.</div>
                        <?php else: ?>
                            <p style="color:var(--text-muted); font-size:0.9rem;"><?= $next_ss_class ? 'The only allowed transfer is from ' . htmlspecialchars($current_ss_class) . ' to ' . htmlspecialchars($next_ss_class) . '.' : 'Conquerors is the highest Sunday School class.' ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="color:var(--text-muted); font-size:0.9rem;">As a Sunday School member, please select which class you belong to.</p>
                    <?php endif; ?>
                    <form method="POST" action="?tab=settings" style="margin-top:14px;">
                        <input type="hidden" name="save_ss_class" value="1">
                        <div class="form-group">
                            <label>Your Class</label>
                            <select name="ss_class" class="form-control" required <?= $pending_ss_class_request ? 'disabled' : '' ?>>
                                <option value="">-- Select Class --</option>
                                <?php foreach ($ss_classes as $ss_class_name): ?>
                                    <option value="<?= htmlspecialchars($ss_class_name) ?>" <?= ($member['sunday_school_class'] ?? '') === $ss_class_name ? 'selected' : '' ?>><?= htmlspecialchars($ss_class_name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-submit" <?= ($pending_ss_class_request || ($has_ss_class && !$next_ss_class)) ? 'disabled' : '' ?> style="<?= $has_ss_class ? 'background:var(--bg-lighter);color:var(--text-main);border:1px solid var(--border-color);' : '' ?>"><?= $pending_ss_class_request ? 'Waiting For Approval' : ($has_ss_class ? ($next_ss_class ? 'Request Transfer' : 'Highest Class Reached') : 'Save Class') ?></button>
                    </form>
                </div>
                <?php endif; ?>
                <?php if ($has_username && $has_profile_pic && (!$is_ss_member || ($is_ss_member && $has_ss_class))): ?>
                <div class="alert alert-success" style="max-width:480px; margin-bottom:20px;">
                    🎉 Setup complete! Refresh the page to unlock all tabs.
                    <a href="member_dashboard.php" style="margin-left:12px; font-weight:600;">Unlock Now &rarr;</a>
                </div>
                <?php endif; ?>

                <hr style="border:none; border-top: 1px solid var(--border-color); max-width:480px; margin: 10px 0 28px;">
                <?php endif; // end !setup_completed ?>

                <!-- PROFILE PICTURE (always visible after setup) -->
                <?php if ($setup_completed): ?>
                <div class="content-card" style="max-width: 450px; margin-bottom: 30px;">
                    <h2>Profile Picture</h2>
                    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
                        <img src="uploads/<?= htmlspecialchars($member['profile_picture'] ?? 'default_avatar.png') ?>" alt="Profile" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); cursor: zoom-in;" onclick="viewProfileImage(this.src);">
                        <div>
                            <p style="font-weight: 500;">Current Picture</p>
                            <small style="color: var(--text-muted);">JPG or PNG allowed.</small>
                        </div>
                    </div>
                    <form method="POST" action="?tab=settings" enctype="multipart/form-data">
                        <input type="hidden" name="upload_picture" value="1">
                        <div class="form-group">
                            <input type="file" name="profile_picture" class="form-control" accept="image/*" required>
                        </div>
                        <button type="submit" class="btn-submit">Replace Picture</button>
                    </form>
                </div>

                <!-- USERNAME (always visible after setup) -->
                <div class="content-card" style="max-width: 450px; margin-bottom: 30px;">
                    <h2>Your Username</h2>
                    <p style="color:var(--text-muted); margin-bottom:14px;">Your current username: <strong><?= htmlspecialchars($member['username'] ?? '—') ?></strong>. This is what you use to log in and reset your password.</p>
                    <form method="POST" action="?tab=settings">
                        <input type="hidden" name="save_username" value="1">
                        <div class="form-group">
                            <label>New Username</label>
                            <input type="text" name="new_username" id="accountUsernameInput" class="form-control member-username-check" value="" placeholder="e.g. peter_2026" pattern="[a-zA-Z0-9_.]+" minlength="3" data-current-username="<?= htmlspecialchars($member['username'] ?? '') ?>" data-submit-id="accountUsernameSubmit" required>
                            <div style="margin-top:8px;padding:9px 11px;border-radius:8px;border:1px solid rgba(239,68,68,0.35);background:rgba(239,68,68,0.1);color:var(--danger);font-size:0.86rem;font-weight:600;">Your username must be unique. You will use it to log in to your account, so do not forget it.</div>
                            <small class="username-live-status" style="display:block;margin-top:4px;color:var(--text-muted);">Type a username to check if it is available.</small>
                        </div>
                        <button type="submit" id="accountUsernameSubmit" class="btn-submit">Update Username</button>
                    </form>
                </div>
                <?php endif; // end setup_completed ?>

                <div class="content-card" style="max-width: 450px; margin-bottom: 30px;">
                    <h2>Contact Details</h2>
                    <p style="color:var(--text-muted); margin-bottom:14px;">Update your phone number and resident area.</p>
                    <form method="POST" action="?tab=settings">
                        <input type="hidden" name="update_contact_details" value="1">
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($member['phone'] ?? '') ?>" placeholder="10-digit number" pattern="\d{10}" maxlength="10" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,').slice(0,10);" required>
                            <small style="display:block;margin-top:4px;color:var(--text-muted);">Must be exactly 10 digits. Numbers only.</small>
                        </div>
                        <div class="form-group">
                            <label>Resident Area</label>
                            <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($member['address'] ?? '') ?>" placeholder="e.g. Mugui" pattern="[A-Za-z\s'-]+" oninput="this.value=this.value.replace(/[^A-Za-z\s'-]/g,');" required>
                            <small style="display:block;margin-top:4px;color:var(--text-muted);">Letters only. Numbers are not allowed.</small>
                        </div>
                        <button type="submit" class="btn-submit">Update Contact Details</button>
                    </form>
                </div>

                <div class="content-card" style="max-width: 450px;">
                    <h2>Change Password</h2>
                    <form method="POST" action="?tab=settings">
                        <input type="hidden" name="change_password" value="1">
                        <div class="form-group">
                            <label>New Password</label>
                            <div style="position:relative;">
                                <input type="password" name="new_password" id="memberNewPassword" class="form-control" minlength="6" required style="padding-right:46px;">
                                <button type="button" onclick="togglePasswordField('memberNewPassword')" aria-label="Show or hide new password" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;padding:4px;">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"></path><circle cx="12" cy="12" r="3" stroke-width="2"></circle></svg>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <div style="position:relative;">
                                <input type="password" name="confirm_password" id="memberConfirmPassword" class="form-control" minlength="6" required style="padding-right:46px;">
                                <button type="button" onclick="togglePasswordField('memberConfirmPassword')" aria-label="Show or hide confirm password" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;padding:4px;">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"></path><circle cx="12" cy="12" r="3" stroke-width="2"></circle></svg>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn-submit">Update Password</button>
                    </form>
                </div>

                <div class="content-card" style="max-width: 450px; margin-top: 30px;">
                    <h2>Change Department</h2>
                    <?php if ($member['pending_department']): ?>
                        <div class="alert alert-info" style="margin-top: 15px;">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-right: 5px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Your request to transfer to the <strong><?= htmlspecialchars($member['pending_department']) ?></strong> department is pending Pastor approval.
                        </div>
                    <?php else:
                        $current_dept = $member['department'];
                        $is_locked_sunday_school_transfer = $current_dept == 'Sunday School' && trim($member['sunday_school_class'] ?? '') !== 'Conquerors';
                        $depts = [];
                        if ($current_dept == 'Youths') {
                            $depts = ['Elders', 'Womens Ministry'];
                        } elseif ($current_dept == 'Sunday School' && !$is_locked_sunday_school_transfer) {
                            $depts = ['Youths'];
                        }
                    ?>
                        <?php if ($is_locked_sunday_school_transfer): ?>
                            <div class="alert alert-warning" style="margin-top:15px;line-height:1.55;display:flex;flex-direction:column;gap:8px;word-break:normal;overflow-wrap:anywhere;">
                                <div style="font-weight:700;color:#92400e;">Department transfer is locked</div>
                                <div>
                                    You can transfer to
                                    <span class="badge" style="background:rgba(99,102,241,0.12);color:#6366f1;margin:0 4px;">Youths</span>
                                    after reaching
                                    <span class="badge" style="background:rgba(239,68,68,0.12);color:#ef4444;margin:0 4px;">Conquerors</span>.
                                </div>
                                <div style="font-size:0.9rem;color:var(--text-muted);">
                                    Current class:
                                    <strong style="color:var(--text-main);"><?= htmlspecialchars($member['sunday_school_class'] ?: 'Not selected') ?></strong>
                                </div>
                            </div>
                        <?php elseif (empty($depts)): ?>
                            <div class="alert alert-warning" style="margin-top: 15px;">
                                Department transfers are not permitted from your current department (<strong><?= htmlspecialchars($current_dept ?? 'None') ?></strong>).
                            </div>
                        <?php else: ?>
                            <form method="POST" action="?tab=settings" style="margin-top: 15px;">
                                <input type="hidden" name="request_department" value="1">
                                <?php if ($current_dept == 'Youths'): ?>
                                <?php $auto_transfer_dept = ($member['gender'] ?? '') === 'Male' ? 'Elders' : ((($member['gender'] ?? '') === 'Female') ? 'Womens Ministry' : ''); ?>
                                <input type="hidden" name="department" value="<?= htmlspecialchars($auto_transfer_dept) ?>">
                                <div class="form-group">
                                    <label>Automatic Transfer Destination</label>
                                    <input type="text" class="form-control" value="<?= $auto_transfer_dept ? htmlspecialchars($auto_transfer_dept) : 'Gender missing' ?>" disabled>
                                    <small style="color: var(--text-muted); display: block; margin-top: 5px;">Based on your registered gender: <strong><?= htmlspecialchars($member['gender'] ?? 'Not set') ?></strong>.</small>
                                </div>
                                <?php else: ?>
                                <div class="form-group">
                                    <label>Request Transfer To</label>
                                    <select name="department" class="form-control" required>
                                        <option value="" disabled selected>Select new department...</option>
                                        <?php
                                        foreach ($depts as $d) {
                                            $val = htmlspecialchars($d);
                                            echo "<option value=\"$val\">$val</option>";
                                        }
                                        ?>
                                    </select>
                                    <small style="color: var(--text-muted); display: block; margin-top: 5px;">Your current department is: <strong><?= htmlspecialchars($current_dept ?? 'None') ?></strong></small>
                                </div>
                                <?php endif; ?>
                                <button type="submit" class="btn-submit">Request Transfer</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($tab == 'financials' && $is_treasurer): ?>
                <div class="page-header">
                    <h1>Financial Records</h1>
                    <p>Record and track money collected for <strong><?= htmlspecialchars($active_treasurer_dept) ?></strong>.</p>
                </div>

                <?php if (count($treasurer_depts) > 1): ?>
                <div class="content-card" style="margin-bottom: 20px;">
                    <form method="GET" style="display:flex; gap:10px; align-items:center;">
                        <input type="hidden" name="tab" value="financials">
                        <label style="font-weight:600;margin:0;">Viewing Financials For:</label>
                        <select name="treasurer_dept" class="form-control" style="max-width:300px;margin:0;" onchange="this.form.submit()">
                            <?php foreach ($treasurer_depts as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>" <?= $dept === $active_treasurer_dept ? 'selected' : '' ?>><?= htmlspecialchars($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <?php endif; ?>

                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <!-- Add Record Form -->
                    <div class="content-card" style="flex: 1; min-width: 300px;">
                        <h2 style="margin-bottom: 15px;">Add New Collection</h2>
                        <form method="POST" action="member_action.php?action=record_finance">
                            <input type="hidden" name="department" value="<?= htmlspecialchars($active_treasurer_dept) ?>">
                            <div class="form-group">
                                <label>Department</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($active_treasurer_dept) ?>" disabled style="background: var(--border-color); font-weight: 600;">
                            </div>
                            <div class="form-group">
                                <label>Amount Collected</label>
                                <input type="number" step="0.01" min="0" name="amount" class="form-control" placeholder="e.g. 1500.00" required>
                            </div>
                            <div class="form-group">
                                <label>Date of Collection</label>
                                <input type="date" name="record_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Description (optional)</label>
                                <input type="text" name="description" class="form-control" placeholder="e.g. Sunday offering, Tithe, Fundraiser">
                            </div>
                            <button type="submit" class="btn-submit">Save Record</button>
                        </form>
                    </div>

                    <!-- Summary Card -->
                    <div class="content-card" style="flex: 1; min-width: 280px;">
                        <h2 style="margin-bottom: 15px;">Collection Summary</h2>
                        <?php
                        $esc_dept = $conn->real_escape_string($active_treasurer_dept);
                        $total_all = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM financial_records WHERE department='$esc_dept'")->fetch_assoc()['total'];
                        $total_month = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM financial_records WHERE department='$esc_dept' AND MONTH(record_date)=MONTH(NOW()) AND YEAR(record_date)=YEAR(NOW())")->fetch_assoc()['total'];
                        $total_week = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM financial_records WHERE department='$esc_dept' AND YEARWEEK(record_date,1)=YEARWEEK(NOW(),1)")->fetch_assoc()['total'];
                        ?>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                            <div style="background: linear-gradient(135deg, #10b981, #059669); color:white; padding:20px; border-radius:12px; grid-column: span 2;">
                                <div style="font-size:0.85rem; opacity:0.9;">Total Collected</div>
                                <div style="font-size:2rem; font-weight:700;">KSh <?= number_format($total_all, 2) ?></div>
                            </div>
                            <div style="background: linear-gradient(135deg, #10b981, #059669); color:white; padding:20px; border-radius:12px;">
                                <div style="font-size:0.85rem; opacity:0.9;">This Month</div>
                                <div style="font-size:1.4rem; font-weight:700;">KSh <?= number_format($total_month, 2) ?></div>
                            </div>
                            <div style="background: linear-gradient(135deg, #f59e0b, #d97706); color:white; padding:20px; border-radius:12px;">
                                <div style="font-size:0.85rem; opacity:0.9;">This Week</div>
                                <div style="font-size:1.4rem; font-weight:700;">KSh <?= number_format($total_week, 2) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Financial Records -->
                <div class="content-card" style="margin-top: 30px;">
                    <h2 style="margin-bottom: 15px;">Recent Financial Records (<?= htmlspecialchars($active_treasurer_dept) ?>)</h2>
                    <?php
                    $dept_records = $conn->query("SELECT fr.*, m.first_name, m.last_name FROM financial_records fr JOIN members m ON fr.recorded_by = m.id WHERE fr.department='$esc_dept' ORDER BY fr.record_date DESC, fr.recorded_at DESC LIMIT 50");
                    if ($dept_records && $dept_records->num_rows > 0):
                    ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Source / Description</th>
                                    <th>Amount (KSh)</th>
                                    <th>Recorded By</th>
                                    <th>Change Reason</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($rec = $dept_records->fetch_assoc()): ?>
                                <tr>
                                    <td><?= date('M j, Y', strtotime($rec['record_date'])) ?></td>
                                    <td><?= htmlspecialchars($rec['description'] ?: 'No description') ?></td>
                                    <td style="font-weight: 600; color: #10b981;">KSh <?= number_format($rec['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($rec['first_name'] . ' ' . $rec['last_name']) ?></td>
                                    <td style="font-size: 0.85em; color: var(--text-muted);"><?= !empty($rec['edit_reason']) ? htmlspecialchars($rec['edit_reason']) : '<span style="color:var(--border-color);">—</span>' ?></td>
                                    <td style="display: flex; gap: 8px;">
                                        <button class="btn-submit" style="background: var(--primary); padding: 5px 10px; font-size: 0.85em; border: none; cursor: pointer; margin: 0;" onclick="openEditFinanceModal(<?= $rec['id'] ?>, <?= htmlspecialchars(json_encode($rec['amount'])) ?>, '<?= htmlspecialchars(addslashes($rec['description'])) ?>', '<?= htmlspecialchars($rec['record_date']) ?>', <?= !empty($rec['is_sent_to_chair']) ? 1 : 0 ?>)">Edit / Change</button>
                                        
                                        <?php if ($active_treasurer_dept !== 'General Church'): ?>
                                            <?php if (!empty($rec['is_chair_confirmed'])): ?>
                                                <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #10b981;">Confirmed & Disbursed</span>
                                            <?php elseif (empty($rec['is_sent_to_chair'])): ?>
                                                <form method="POST" action="member_action.php?action=send_record_to_chairperson" style="margin: 0;">
                                                    <input type="hidden" name="record_id" value="<?= $rec['id'] ?>">
                                                    <input type="hidden" name="department" value="<?= htmlspecialchars($active_treasurer_dept) ?>">
                                                    <button type="submit" class="btn-submit" style="background: var(--secondary); padding: 5px 10px; font-size: 0.85em; border: none; margin: 0;" onclick="return confirm('Send this KSh <?= number_format($rec['amount'], 2) ?> record to the Chairperson?');">Send to Chair</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">Sent ✅</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p style="color:var(--text-muted);">No financial records found for this department.</p>
                    <?php endif; ?>
                </div>

                <!-- Edit Finance Modal -->
                <div id="editFinanceModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
                    <div style="background:var(--bg-main); padding:25px; border-radius:12px; width:100%; max-width:420px; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
                        <h2 style="margin-top:0;">Edit Financial Record</h2>
                        <div id="editFinanceSentWarning" style="display:none; background:rgba(239,68,68,0.12); border:1px solid #ef4444; border-radius:8px; padding:10px; margin-bottom:15px; font-size:0.9rem; color:#ef4444;">
                            ⚠️ This record was already <strong>sent to the Chairperson</strong>. Saving will reset it to <strong>Unsent</strong> and cancel any related transfers.
                        </div>
                        <form method="POST" action="member_action.php?action=edit_finance">
                            <input type="hidden" name="record_id" id="edit_finance_id">
                            <input type="hidden" name="department" value="<?= htmlspecialchars($active_treasurer_dept) ?>">
                            <input type="hidden" name="is_sent" id="edit_finance_is_sent" value="0">
                            
                            <div class="form-group">
                                <label>Amount Collected</label>
                                <input type="number" step="0.01" min="0" name="amount" id="edit_finance_amount" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Date of Collection</label>
                                <input type="date" name="record_date" id="edit_finance_date" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Description / Source</label>
                                <input type="text" name="description" id="edit_finance_desc" class="form-control">
                            </div>
                            <div class="form-group">
                                <label style="color: #ef4444;">Reason for Change <span style="color:var(--danger);">*</span></label>
                                <input type="text" name="edit_reason" id="edit_finance_reason" class="form-control" placeholder="Why is this record being changed?" required>
                            </div>
                            
                            <div style="display:flex; gap:10px; margin-top:20px;">
                                <button type="submit" class="btn-submit" style="flex:1;">Save Changes</button>
                                <button type="button" class="btn-submit" style="flex:1; background:var(--text-muted);" onclick="closeEditFinanceModal()">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
                <script>
                    function openEditFinanceModal(id, amount, desc, date, isSent) {
                        document.getElementById('edit_finance_id').value = id;
                        document.getElementById('edit_finance_amount').value = amount;
                        document.getElementById('edit_finance_desc').value = desc;
                        document.getElementById('edit_finance_date').value = date;
                        document.getElementById('edit_finance_reason').value = '';
                        document.getElementById('edit_finance_is_sent').value = isSent || 0;
                        var warning = document.getElementById('editFinanceSentWarning');
                        warning.style.display = (isSent == 1) ? 'block' : 'none';
                        document.getElementById('editFinanceModal').style.display = 'flex';
                    }
                    function closeEditFinanceModal() {
                        document.getElementById('editFinanceModal').style.display = 'none';
                    }
                </script>

                <!-- Pending Fine Verifications -->
                <?php if ($active_treasurer_dept !== 'Building & Construction' && $active_treasurer_dept !== 'General Church'): ?>
                <div class="content-card" style="margin-top: 30px; border-left: 4px solid #f59e0b;">
                    <h2 style="color: #f59e0b; margin-bottom: 15px;">Pending Fine Payments</h2>
                    <p style="color: var(--text-muted); margin-bottom: 15px;">Members have marked these fines as paid. Please verify you have received the funds before clearing.</p>
                    <?php
                    $pending_fines = $conn->query("SELECT mf.*, m.first_name, m.last_name FROM member_fines mf JOIN members m ON mf.member_id = m.id WHERE mf.department='$esc_dept' AND mf.status = 'Pending Verification' ORDER BY mf.issued_at ASC");
                    if ($pending_fines && $pending_fines->num_rows > 0):
                    ?>
                        <div class="table-responsive">
                            <table>
                                <thead><tr><th>Member</th><th>Offense</th><th>Amount</th><th>Action</th></tr></thead>
                                <tbody>
                                    <?php while($pf = $pending_fines->fetch_assoc()): ?>
                                        <tr>
                                            <td style="font-weight: 500;"><?= htmlspecialchars($pf['first_name'] . ' ' . $pf['last_name']) ?></td>
                                            <td><?= htmlspecialchars($pf['fine_reason']) ?></td>
                                            <td style="font-weight: 600; color: #10b981;">KSh <?= number_format($pf['amount'], 2) ?></td>
                                            <td>
                                                <a href="member_action.php?action=mark_fine_paid&id=<?= $pf['id'] ?>" class="btn-submit" style="background: #10b981; padding: 6px 12px; font-size: 0.85em; text-decoration: none;" onclick="return confirm('Verify payment and clear this fine?');">Verify Payment</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--text-muted);">No pending fine payments to verify.</p>
                    <?php endif; ?>
                </div>

                <!-- Unpaid Fines Tracker -->
                <div class="content-card" style="margin-top: 30px; border-left: 4px solid var(--danger);">
                    <h2 style="color: var(--danger); margin-bottom: 15px;">Unpaid Fines & Reminders</h2>
                    <p style="color: var(--text-muted); margin-bottom: 15px;">Members who have not yet paid their fines. If a member fails to pay, you can request them to pay at the next meeting.</p>
                    <?php
                    $unpaid_fines = $conn->query("SELECT mf.*, m.first_name, m.last_name FROM member_fines mf JOIN members m ON mf.member_id = m.id WHERE mf.department='$esc_dept' AND mf.status = 'Unpaid' ORDER BY mf.issued_at ASC");
                    if ($unpaid_fines && $unpaid_fines->num_rows > 0):
                    ?>
                        <div class="table-responsive">
                            <table>
                                <thead><tr><th>Member</th><th>Offense</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead>
                                <tbody>
                                    <?php while($uf = $unpaid_fines->fetch_assoc()): ?>
                                        <tr>
                                            <td style="font-weight: 500;"><?= htmlspecialchars($uf['first_name'] . ' ' . $uf['last_name']) ?></td>
                                            <td><?= htmlspecialchars($uf['fine_reason']) ?></td>
                                            <td style="font-weight: 600; color: var(--danger);">KSh <?= number_format($uf['amount'], 2) ?></td>
                                            <td>
                                                <?php if ($uf['reminded_for_meeting'] == 1): ?>
                                                    <span class="badge" style="background: rgba(37,99,235,0.1); color: var(--primary);">Reminded for Next Mtg</span>
                                                <?php else: ?>
                                                    <span class="badge" style="background: rgba(239,68,68,0.1); color: var(--danger);">Unpaid</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($uf['reminded_for_meeting'] == 0): ?>
                                                    <a href="member_action.php?action=remind_fine_meeting&id=<?= $uf['id'] ?>" class="btn-sm" style="background: var(--primary); color: white; padding: 6px 12px; font-size: 0.85em; text-decoration: none;" onclick="return confirm('Send an official reminder requesting this member to pay at the next meeting?');">Remind for Next Meeting</a>
                                                <?php else: ?>
                                                    <span style="font-size: 0.85em; color: var(--text-muted);">Reminder Sent</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--text-muted);">No unpaid fines in this department.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Past Records Table -->
                <div class="content-card" style="margin-top: 30px;">
                    <h2 style="margin-bottom: 15px;">Past Records</h2>
                    <?php
                    $my_records = $conn->query("SELECT * FROM financial_records WHERE department='$esc_dept' ORDER BY record_date DESC, recorded_at DESC LIMIT 50");
                    ?>
                    <?php if ($my_records->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table>
                                <thead><tr><th>Date</th><th>Amount</th><th>Description</th><th>Recorded</th></tr></thead>
                                <tbody>
                                    <?php while($r = $my_records->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= date('M j, Y', strtotime($r['record_date'])) ?></td>
                                            <td style="font-weight:600; color: #10b981;">KSh <?= number_format($r['amount'], 2) ?></td>
                                            <td><?= htmlspecialchars($r['description'] ?? '—') ?></td>
                                            <td style="color: var(--text-muted); font-size:0.85rem;"><?= date('M j, g:i A', strtotime($r['recorded_at'])) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--text-muted);">No records yet. Start by adding your first collection above.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($tab == 'appoint_leaders' && $is_leader): ?>
                <?php
                // Build list of all departments this leader can manage
                $_leader_all_depts = [];
                if (!empty($leadership_managed_dept)) $_leader_all_depts[] = $leadership_managed_dept;
                foreach ($ss_leader_depts as $ssd) {
                    if (!in_array($ssd, $_leader_all_depts)) $_leader_all_depts[] = $ssd;
                }
                $active_leader_dept = $_GET['leader_dept'] ?? (in_array($active_dashboard_dept, $_leader_all_depts) ? $active_dashboard_dept : ($_leader_all_depts[0] ?? $managed_dept));
                $managed_dept_display = $active_leader_dept ?: $managed_dept;
                ?>
                <div class="page-header">
                    <h1>Appoint Subsidiary Leaders</h1>
                    <?php if (count($_leader_all_depts) > 1): ?>
                    <p>You lead multiple departments. Select one to manage:</p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;margin-bottom:15px;">
                        <?php foreach ($_leader_all_depts as $ld): ?>
                            <a href="?tab=appoint_leaders&leader_dept=<?= urlencode($ld) ?>"
                               class="btn-sm<?= (strtolower($ld) === strtolower($managed_dept_display)) ? ' btn-success' : '' ?>"
                               style="text-decoration:none;<?= (strtolower($ld) !== strtolower($managed_dept_display)) ? 'background:var(--bg-card);border:1px solid var(--border-color);color:var(--text-main);' : '' ?>">
                               <?= htmlspecialchars($ld) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p>Assign leadership roles to members within <strong><?= htmlspecialchars($managed_dept_display) ?></strong>. (Requires Pastor approval)</p>
                    <?php endif; ?>
                </div>

                <div class="content-card">
                    <h2>Propose New Leader</h2>
                    <?php
                    // Fetch members in this department without a role
                    $dept_filter = department_match_sql($conn, 'department', $managed_dept_display);
                    $no_leadership_role = "(church_role IS NULL OR church_role = ' OR LOWER(TRIM(church_role)) = 'member')";
                    $avail_members = $conn->query("SELECT * FROM members WHERE $dept_filter AND $no_leadership_role AND (pending_role IS NULL OR pending_role = ') AND is_approved = 1 AND id != $member_id ORDER BY first_name ASC");
                    $unavailable_members = $conn->query("SELECT first_name, last_name, church_role, pending_role, is_approved FROM members WHERE $dept_filter AND id != $member_id AND (is_approved = 0 OR NOT $no_leadership_role OR pending_role IS NOT NULL OR pending_role != ') ORDER BY first_name ASC");
                    ?>

                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
                    <?php elseif (isset($_GET['error'])): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="member_action.php?action=appoint_leader">
                        <input type="hidden" name="active_dept" value="<?= htmlspecialchars($managed_dept_display) ?>">
                        <div class="form-group">
                            <label>Select Member</label>
                            <select name="target_member_id" class="form-control" required>
                                <option value="" disabled selected>-- Choose a Member --</option>
                                <?php if ($avail_members && $avail_members->num_rows > 0): ?>
                                    <?php while($m = $avail_members->fetch_assoc()): ?>
                                        <option value="<?= $m['id'] ?>">
                                            <?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?>
                                            <?= $m['pending_role'] ? ' (Pending: ' . htmlspecialchars(clean_role_display($m['pending_role'])) . ')' : '' ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <option value="" disabled>No available members without roles.</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Role to Assign</label>
                            <select name="proposed_role" class="form-control" required>
                                <option value="" disabled selected>-- Select Role --</option>
                                <option value="Discipline Master (Subsidiary)">Discipline Master</option>
                                <option value="Organizing Secretary (Subsidiary)">Organizing Secretary</option>
                                <option value="Choir Leader (Subsidiary)">Choir Leader</option>
                                <?php if (department_allows_graduation_and_sport($managed_dept)): ?>
                                    <option value="Graduands Secretary (Subsidiary)">Graduands Secretary</option>
                                <?php endif; ?>
                                <option value="Prayer Coordinator (Subsidiary)">Prayer Coordinator</option>
                                <?php if (department_allows_graduation_and_sport($managed_dept)): ?>
                                    <option value="Sports Secretary (Subsidiary)">Sports Secretary</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-submit" <?= (!$avail_members || $avail_members->num_rows == 0) ? 'disabled' : '' ?>>Request Appointment</button>
                    </form>
                    <?php if ($unavailable_members && $unavailable_members->num_rows > 0): ?>
                        <div class="alert alert-info" style="margin-top: 20px; display: block;">
                            <strong>Not available for assignment yet:</strong>
                            <div style="margin-top: 8px; display: grid; gap: 6px;">
                                <?php while($um = $unavailable_members->fetch_assoc()): ?>
                                    <span>
                                        <?= htmlspecialchars($um['first_name'] . ' ' . $um['last_name']) ?> -
                                        <?php if (!$um['is_approved']): ?>
                                            pending pastor approval
                                        <?php elseif (!empty($um['church_role'])): ?>
                                            already has role: <?= htmlspecialchars($um['church_role']) ?>
                                        <?php elseif (!empty($um['pending_role'])): ?>
                                            pending role: <?= htmlspecialchars($um['pending_role']) ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($tab == 'organized_events' && !empty($member['department']) && $member['department'] !== 'None'): ?>
                <?php 
                $active_org_dept = $_GET['org_dept'] ?? (in_array($active_dashboard_dept, $organizing_secretary_depts) ? $active_dashboard_dept : ($organizing_secretary_depts[0] ?? $active_dashboard_dept));
                $current_event_scopes = [];
                if ($is_organizing_secretary) {
                    $current_event_scopes[$active_org_dept] = $active_org_dept . ' Department';
                } else {
                    foreach ($youth_advisor_scopes as $scope_dept => $scope_label) {
                        $current_event_scopes[$scope_dept] = $scope_label;
                    }
                }
                ?>
                <div class="page-header">
                    <h1><?= $is_youth_advisor_scope ? 'Youth & ' . htmlspecialchars($active_org_dept) . ' Organised Events' : htmlspecialchars($active_org_dept) . ' Organised Events' ?></h1>
                    <?php if (count($organizing_secretary_depts) > 1): ?>
                    <p>You serve as Organizing Secretary in multiple departments. Select one to manage:</p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;margin-bottom:15px;">
                        <?php foreach ($organizing_secretary_depts as $od): ?>
                            <a href="?tab=organized_events&org_dept=<?= urlencode($od) ?>"
                               class="btn-sm<?= (strtolower($od) === strtolower($active_org_dept)) ? ' btn-success' : '' ?>"
                               style="text-decoration:none;<?= (strtolower($od) !== strtolower($active_org_dept)) ? 'background:var(--bg-card);border:1px solid var(--border-color);color:var(--text-main);' : '' ?>">
                               <?= htmlspecialchars($od) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p>Meetings, announcements, and events organized for your active department scope.</p>
                    <?php endif; ?>
                </div>

                <?php if ($is_organizing_secretary): ?>
                <div class="content-card" style="margin-bottom: 30px; border-left: 4px solid var(--primary);">
                    <h2>Post a New Event</h2>
                    <form method="POST" action="member_action.php?action=post_organized_event" enctype="multipart/form-data">
                        <input type="hidden" name="active_dept" value="<?= htmlspecialchars($active_org_dept) ?>">
                        <div class="form-group">
                            <label>Event Title / Announcement</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Weekly Meeting" required>
                        </div>
                        <div class="form-group">
                            <label>Details / Message</label>
                            <textarea name="announcement" class="form-control" rows="4" placeholder="Provide details about the event..." required></textarea>
                        </div>
                        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                            <div class="form-group" style="flex: 1;">
                                <label>Google Meet Link (Optional)</label>
                                <input type="url" name="meet_link" class="form-control" placeholder="https://meet.google.com/...">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label>WhatsApp Link (Optional)</label>
                                <input type="url" name="whatsapp_link" class="form-control" placeholder="https://chat.whatsapp.com/...">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Upload Attendance / Document (Optional)</label>
                            <input type="file" name="attendance_file" class="form-control">
                        </div>
                        <button type="submit" class="btn-submit">Post Event</button>
                    </form>
                </div>
                <?php endif; ?>

                <?php foreach ($current_event_scopes as $scope_dept => $scope_label): ?>
                    <?php if ($scope_dept === 'None' || $scope_dept === '') continue; ?>
                    <div class="content-card" style="margin-bottom:24px;">
                        <h2><?= htmlspecialchars($scope_label) ?> Events</h2>
                        <?php
                        $esc_dept = $conn->real_escape_string($scope_dept);
                        $events = $conn->query("SELECT oe.*, m.first_name, m.last_name FROM organized_events oe JOIN members m ON oe.organizer_id = m.id WHERE oe.department = '$esc_dept' ORDER BY oe.created_at DESC");
                        if ($events && $events->num_rows > 0):
                            while($ev = $events->fetch_assoc()):
                        ?>
                            <div style="padding: 20px; border: 1px solid var(--border-color); border-radius: var(--radius-md); margin-bottom: 20px; background: rgba(255,255,255,0.02);">
                                <h3 style="margin-top: 0; color: var(--primary);"><?= htmlspecialchars($ev['title']) ?></h3>
                                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 15px;">Posted by <?= htmlspecialchars($ev['first_name'] . ' ' . $ev['last_name']) ?> on <?= date('M j, Y g:i A', strtotime($ev['created_at'])) ?></p>
                                <p style="white-space: pre-wrap; line-height: 1.6; margin-bottom: 20px;"><?= htmlspecialchars($ev['announcement']) ?></p>
                                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                                    <?php if (!empty($ev['meet_link'])): ?><a href="<?= htmlspecialchars($ev['meet_link']) ?>" target="_blank" class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; text-decoration: none; padding: 8px 12px;">Join Google Meet</a><?php endif; ?>
                                    <?php if (!empty($ev['whatsapp_link'])): ?><a href="<?= htmlspecialchars($ev['whatsapp_link']) ?>" target="_blank" class="badge" style="background: rgba(37, 99, 235, 0.15); color: var(--primary); text-decoration: none; padding: 8px 12px;">Join WhatsApp Group</a><?php endif; ?>
                                    <?php if (!empty($ev['attendance_file'])): ?><a href="uploads/<?= htmlspecialchars($ev['attendance_file']) ?>" target="_blank" class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; text-decoration: none; padding: 8px 12px;">View Attendance/Document</a><?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; else: ?>
                            <p style="color: var(--text-muted);">No events have been posted for this department yet.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($tab == 'discipline' && $is_discipline_master):
                $active_disc_dept = $_GET['disc_dept'] ?? (in_array($active_dashboard_dept, $discipline_master_depts) ? $active_dashboard_dept : ($discipline_master_depts[0] ?? $active_dashboard_dept));
            ?>
                <div class="page-header">
                    <h1>Discipline &amp; Fines</h1>
                    <?php if (count($discipline_master_depts) > 1): ?>
                    <p>You serve as Discipline Master in multiple departments. Select one to manage:</p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
                        <?php foreach ($discipline_master_depts as $dd): ?>
                            <a href="?tab=discipline&disc_dept=<?= urlencode($dd) ?>"
                               class="btn-sm<?= (strtolower($dd) === strtolower($active_disc_dept)) ? ' btn-success' : '' ?>"
                               style="text-decoration:none;<?= (strtolower($dd) !== strtolower($active_disc_dept)) ? 'background:var(--bg-card);border:1px solid var(--border-color);color:var(--text-main);' : '' ?>">
                               <?= htmlspecialchars($dd) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p>Issue fines and report members within the <strong><?= htmlspecialchars($active_disc_dept) ?></strong> department.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Customize Fines -->
                <div class="content-card" style="border-top: 4px solid var(--primary); margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                        <div style="background: rgba(37,99,235,0.1); padding: 12px; border-radius: 12px; color: var(--primary);">
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                        </div>
                        <div>
                            <h2 style="margin: 0; color: var(--text-main);">Customize Fine Rules</h2>
                            <p style="color: var(--text-muted); margin: 5px 0 0 0; font-size: 0.9em;">Define standard penalties that will be available when issuing fines to members.</p>
                        </div>
                    </div>

                    <div style="background: var(--bg-body); padding: 20px; border-radius: 12px; margin-top: 25px; border: 1px solid rgba(255,255,255,0.05);">
                        <form method="POST" action="member_action.php?action=add_fine_template" style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
                            <div class="form-group" style="flex: 2; margin-bottom: 0; min-width: 250px;">
                                <label style="font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);">Offense Description</label>
                                <input type="text" name="fine_name" class="form-control" placeholder="e.g., Making Noise, Late Arrival..." required style="margin-top: 5px;">
                            </div>
                            <div class="form-group" style="flex: 1; margin-bottom: 0; min-width: 150px;">
                                <label style="font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);">Penalty Amount (KSh)</label>
                                <input type="number" name="amount" class="form-control" placeholder="0.00" required style="margin-top: 5px;" min="1">
                            </div>
                            <button type="submit" class="btn-submit" style="margin: 0; padding: 12px 24px; white-space: nowrap; display: flex; align-items: center; gap: 8px;">
                                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                Save Rule
                            </button>
                        </form>
                    </div>

                    <?php
                    $dm_dept = $conn->real_escape_string($active_disc_dept);
                    $fines = $conn->query("SELECT * FROM fine_templates WHERE department = '$dm_dept' ORDER BY created_at DESC");
                    if ($fines && $fines->num_rows > 0):
                    ?>
                        <div class="table-responsive" style="margin-top: 25px;">
                            <table>
                                <thead><tr><th>Offense</th><th>Fine Amount</th><th style="text-align: right;">Action</th></tr></thead>
                                <tbody>
                                    <?php while($f = $fines->fetch_assoc()): ?>
                                        <tr>
                                            <td style="font-weight: 500;"><?= htmlspecialchars($f['fine_name']) ?></td>
                                            <td style="font-weight: 600; color: #10b981;">KSh <?= number_format($f['amount'], 2) ?></td>
                                            <td style="text-align: right;">
                                                <a href="member_action.php?action=delete_fine_template&id=<?= $f['id'] ?>" class="btn-action btn-reject" onclick="return confirm('Delete this fine rule?');" style="padding: 6px 12px; font-size: 0.85em;">Remove</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 30px; margin-top: 20px; background: rgba(0,0,0,0.02); border-radius: 8px; border: 1px dashed rgba(255,255,255,0.1);">
                            <p style="color: var(--text-muted); margin: 0;">No fine rules have been set up yet. Create your first rule above.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Issue a Fine -->
                <div class="content-card" style="border-top: 4px solid var(--warning); margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 25px;">
                        <div style="background: rgba(245,158,11,0.1); padding: 12px; border-radius: 12px; color: var(--warning);">
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        </div>
                        <div>
                            <h2 style="margin: 0; color: var(--text-main);">Issue a Disciplinary Fine</h2>
                            <p style="color: var(--text-muted); margin: 5px 0 0 0; font-size: 0.9em;">Select a member and an offense to apply a penalty. They will be notified instantly.</p>
                        </div>
                    </div>

                    <?php
                    $dm_members = $conn->query("SELECT id, first_name, last_name FROM members WHERE department = '$dm_dept' AND is_approved = 1 AND id != $member_id ORDER BY first_name ASC");
                    $dm_templates = $conn->query("SELECT * FROM fine_templates WHERE department = '$dm_dept' ORDER BY fine_name ASC");
                    ?>
                    <form method="POST" action="member_action.php?action=issue_fine" style="background: var(--bg-body); padding: 25px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 25px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);">Select Department Member</label>
                                <select name="member_id" class="form-control" required style="margin-top: 5px;">
                                    <option value="" disabled selected>-- Choose Member --</option>
                                    <?php while($dm = $dm_members->fetch_assoc()): ?>
                                        <option value="<?= $dm['id'] ?>"><?= htmlspecialchars($dm['first_name'] . ' ' . $dm['last_name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);">Select Offense</label>
                                <select name="fine_template_id" class="form-control" required style="margin-top: 5px;">
                                    <option value="" disabled selected>-- Choose Penalty Rule --</option>
                                    <?php if ($dm_templates && $dm_templates->num_rows > 0): ?>
                                        <?php while($ft = $dm_templates->fetch_assoc()): ?>
                                            <option value="<?= $ft['id'] ?>"><?= htmlspecialchars($ft['fine_name']) ?> (KSh <?= number_format($ft['amount'], 2) ?>)</option>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No fines defined yet. Create one above.</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn-submit" style="width: 100%; background: var(--warning); color: #fff; padding: 14px; font-size: 1.05em; display: flex; justify-content: center; gap: 10px;">
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            Confirm and Issue Fine
                        </button>
                    </form>
                </div>

                <?php if ($active_treasurer_dept !== 'General Church' && $active_treasurer_dept !== 'Building & Construction'): ?>
                <!-- Unpaid Fines -->
                <div class="content-card" style="margin-top: 30px;">
                    <h2>Unpaid Fines</h2>
                    <?php
                    $unpaid = $conn->query("SELECT mf.*, m.first_name, m.last_name FROM member_fines mf JOIN members m ON mf.member_id = m.id WHERE mf.department = '$dm_dept' AND mf.status IN ('Unpaid', 'Pending Verification') ORDER BY mf.issued_at DESC");
                    if ($unpaid && $unpaid->num_rows > 0):
                    ?>
                        <div class="table-responsive">
                            <table>
                                <thead><tr><th>Date</th><th>Member</th><th>Offense</th><th>Amount</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php while($uf = $unpaid->fetch_assoc()): ?>
                                        <tr>
                                            <td style="white-space: nowrap; font-size: 0.85em; color: var(--text-muted);"><?= date('M j, Y g:i A', strtotime($uf['issued_at'])) ?></td>
                                            <td style="font-weight: 500;"><?= htmlspecialchars($uf['first_name'] . ' ' . $uf['last_name']) ?></td>
                                            <td><?= htmlspecialchars($uf['fine_reason']) ?></td>
                                            <td style="font-weight: 600; color: var(--danger);">KSh <?= number_format($uf['amount'], 2) ?></td>
                                            <td>
                                                <?php if ($uf['status'] == 'Pending Verification'): ?>
                                                    <span class="badge" style="background: rgba(245,158,11,0.1); color: #f59e0b;">Pending Treasurer Verification</span>
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
                        <p style="color: var(--text-muted);">No unpaid fines at this time.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- All Fines History -->
                <div class="content-card" style="margin-top: 30px;">
                    <h2>Fines History</h2>
                    <?php
                    $all_fines = $conn->query("SELECT mf.*, m.first_name, m.last_name FROM member_fines mf JOIN members m ON mf.member_id = m.id WHERE mf.department = '$dm_dept' ORDER BY mf.issued_at DESC LIMIT 50");
                    if ($all_fines && $all_fines->num_rows > 0):
                    ?>
                        <div class="table-responsive">
                            <table>
                                <thead><tr><th>Date</th><th>Member</th><th>Offense</th><th>Amount</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php while($af = $all_fines->fetch_assoc()): ?>
                                        <tr>
                                            <td style="white-space: nowrap; font-size: 0.85em; color: var(--text-muted);"><?= date('M j, Y', strtotime($af['issued_at'])) ?></td>
                                            <td style="font-weight: 500;"><?= htmlspecialchars($af['first_name'] . ' ' . $af['last_name']) ?></td>
                                            <td><?= htmlspecialchars($af['fine_reason']) ?></td>
                                            <td style="font-weight: 600;">KSh <?= number_format($af['amount'], 2) ?></td>
                                            <td>
                                                <?php if ($af['status'] == 'Paid'): ?>
                                                    <span class="badge" style="background: rgba(16,185,129,0.1); color: #10b981;">Paid</span>
                                                <?php elseif ($af['status'] == 'Pending Verification'): ?>
                                                    <span class="badge" style="background: rgba(245,158,11,0.1); color: #f59e0b;">Pending Verification</span>
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

                <!-- Report a Member -->
                <div class="content-card" style="margin-top: 30px; border-left: 4px solid var(--danger);">
                    <h2 style="color: var(--danger);">Report a Member to <?= $dm_dept === 'Womens Ministry' ? 'Chairlady' : 'Chairman' ?></h2>
                    <?php
                    $dm_members2 = $conn->query("SELECT id, first_name, last_name FROM members WHERE department = '$dm_dept' AND is_approved = 1 AND id != $member_id ORDER BY first_name ASC");
                    ?>
                    <form method="POST" action="member_action.php?action=report_member">
                        <div class="form-group">
                            <label>Select Member</label>
                            <select name="member_id" class="form-control" required>
                                <option value="" disabled selected>-- Choose Member --</option>
                                <?php while($rm = $dm_members2->fetch_assoc()): ?>
                                    <option value="<?= $rm['id'] ?>"><?= htmlspecialchars($rm['first_name'] . ' ' . $rm['last_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Reason</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Describe the issue..." required></textarea>
                        </div>
                        <button type="submit" class="btn-submit" style="background: var(--danger);">Submit Report</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($tab == 'my_fines' && !empty($member['department']) && $member['department'] !== 'None'): ?>
                <div class="page-header">
                    <h1>My Fines</h1>
                    <p>View fines issued to you and the department rules.</p>
                </div>

                <!-- Department Fine Rules -->
                <div class="content-card" style="margin-bottom: 30px;">
                    <?php $fine_scope_index = 0; ?>
                    <?php foreach ($youth_advisor_scopes as $fine_dept => $fine_label): ?>
                        <?php
                        $esc_fine_dept = $conn->real_escape_string($fine_dept);
                        $rules = $conn->query("SELECT * FROM fine_templates WHERE department = '$esc_fine_dept' ORDER BY fine_name ASC");
                        ?>
                        <div style="<?= $fine_scope_index > 0 ? 'margin-top:24px;padding-top:20px;border-top:1px solid var(--border-color);' : '' ?>">
                            <h2><?= htmlspecialchars($fine_label) ?> Fine Rules</h2>
                            <?php if ($rules && $rules->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table>
                                        <thead><tr><th>Offense</th><th>Fine Amount</th></tr></thead>
                                        <tbody>
                                            <?php while($r = $rules->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($r['fine_name']) ?></td>
                                                    <td style="font-weight: 600;">KSh <?= number_format($r['amount'], 2) ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p style="color: var(--text-muted);">No fine rules have been defined for <?= htmlspecialchars($fine_label) ?>.</p>
                            <?php endif; ?>
                        </div>
                        <?php $fine_scope_index++; ?>
                    <?php endforeach; ?>
                </div>

                <!-- My Fines -->
                <div class="content-card">
                    <h2>Your Fines</h2>
                    <?php
                    $my_fines = $conn->query("SELECT * FROM member_fines WHERE member_id = $member_id ORDER BY issued_at DESC");
                    if ($my_fines && $my_fines->num_rows > 0):
                    ?>
                        <div class="table-responsive">
                            <table>
                                <thead><tr><th>Date</th><th>Offense</th><th>Amount</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php while($mf = $my_fines->fetch_assoc()): ?>
                                        <tr>
                                            <td style="white-space: nowrap; font-size: 0.85em; color: var(--text-muted);"><?= date('M j, Y g:i A', strtotime($mf['issued_at'])) ?></td>
                                            <td><?= htmlspecialchars($mf['fine_reason']) ?></td>
                                            <td style="font-weight: 600;">KSh <?= number_format($mf['amount'], 2) ?></td>
                                            <td>
                                                <?php if ($mf['status'] == 'Paid'): ?>
                                                    <span class="badge" style="background: rgba(16,185,129,0.1); color: #10b981;">Paid ✓</span>
                                                <?php elseif ($mf['status'] == 'Pending Verification'): ?>
                                                    <span class="badge" style="background: rgba(245,158,11,0.1); color: #f59e0b;">Pending Treasurer Verification</span>
                                                <?php else: ?>
                                                    <div style="display: flex; gap: 10px; align-items: center;">
                                                        <span class="badge" style="background: rgba(239,68,68,0.1); color: #ef4444;">Unpaid</span>
                                                        <a href="member_action.php?action=initiate_fine_payment&id=<?= $mf['id'] ?>" class="btn-submit" style="background: #10b981; margin: 0; padding: 6px 12px; font-size: 0.85em; text-decoration: none;" onclick="return confirm('Initiate payment for this fine?');">Pay Fine</a>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color: #10b981; font-weight: 500;">You have no fines. Keep up the great discipline! ✓</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($tab == 'graduation_panel' && $is_graduands_secretary): ?>
                <div class="page-header">
                    <h1>Graduation Panel</h1>
                    <p>Manage the list of graduating youths for the current year and make graduation announcements.</p>
                </div>

                <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 30px;">
                    <!-- Total Count Card -->
                    <?php
                    $current_year = date('Y');
                    $esc_dept = $conn->real_escape_string($member['department']);
                    $count_query = $conn->query("SELECT COUNT(*) as total FROM graduation_list WHERE department='$esc_dept' AND graduation_year=$current_year");
                    $total_graduands = $count_query->fetch_assoc()['total'];
                    ?>
                    <div class="content-card" style="flex: 1; min-width: 250px; background: linear-gradient(135deg, rgba(37,99,235,0.1), rgba(16,185,129,0.1)); border: 1px solid rgba(37,99,235,0.2); text-align: center; padding: 30px;">
                        <svg width="48" height="48" fill="none" stroke="var(--primary)" viewBox="0 0 24 24" style="margin-bottom: 15px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"></path></svg>
                        <h2 style="margin: 0; font-size: 2.5rem; color: var(--text-main);"><?= $total_graduands ?></h2>
                        <p style="color: var(--text-muted); font-size: 1.1rem; margin-top: 5px;">Graduating Youths (<?= $current_year ?>)</p>
                    </div>

                    <!-- Announcement Form -->
                    <div class="content-card" style="flex: 2; min-width: 350px;">
                        <h2>Make Graduation Announcement</h2>
                        <p style="color: var(--text-muted); margin-bottom: 15px;">This will be posted to the department board and sent directly to the Pastor and Admin.</p>
                        <form method="POST" action="member_action.php?action=graduation_announcement">
                            <div class="form-group">
                                <textarea name="message" class="form-control" rows="4" placeholder="Type your graduation announcement here..." required></textarea>
                            </div>
                            <button type="submit" class="btn-submit">Broadcast Announcement</button>
                        </form>
                    </div>
                </div>

                <!-- Add to Roster -->
                <div class="content-card">
                    <h2>Graduation Roster (<?= $current_year ?>)</h2>
                    <form method="POST" action="member_action.php?action=add_graduand" style="display: flex; gap: 15px; margin-bottom: 20px;">
                        <?php
                        // Fetch non-graduating members in department
                        $avail = $conn->query("SELECT m.id, m.first_name, m.last_name FROM members m
                            LEFT JOIN graduation_list gl ON m.id = gl.member_id AND gl.graduation_year = $current_year
                            WHERE m.department = '$esc_dept' AND m.is_approved = 1 AND gl.id IS NULL ORDER BY m.first_name ASC");
                        ?>
                        <select name="member_id" class="form-control" style="flex: 1;" required>
                            <option value="" disabled selected>-- Select Member to Add to Roster --</option>
                            <?php while($a = $avail->fetch_assoc()): ?>
                                <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <button type="submit" class="btn-submit" style="width: auto;" <?= ($avail->num_rows == 0) ? 'disabled' : '' ?>>Add to Graduation List</button>
                    </form>

                    <?php
                    $roster = $conn->query("SELECT gl.*, m.first_name, m.last_name FROM graduation_list gl JOIN members m ON gl.member_id = m.id WHERE gl.department = '$esc_dept' AND gl.graduation_year = $current_year ORDER BY m.first_name ASC");
                    if ($roster && $roster->num_rows > 0):
                    ?>
                        <div class="table-responsive">
                            <table>
                                <thead><tr><th>Name</th><th>Added Date</th><th>Action</th></tr></thead>
                                <tbody>
                                    <?php while($r = $roster->fetch_assoc()): ?>
                                        <tr>
                                            <td style="font-weight: 500;"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
                                            <td style="color: var(--text-muted);"><?= date('M j, Y', strtotime($r['added_at'])) ?></td>
                                            <td>
                                                <a href="member_action.php?action=remove_graduand&id=<?= $r['id'] ?>" class="btn-action" style="color: var(--danger); background: rgba(239,68,68,0.1); padding: 4px 8px; border-radius: 4px;" onclick="return confirm('Remove from graduation list?');">Remove</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--text-muted);">No youths have been added to the graduation roster for <?= $current_year ?>.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($tab == 'graduation_info' && !empty($member['department']) && $member['department'] !== 'None' && (department_allows_graduation_and_sport($member['department']) || $is_youth_advisor_scope)): ?>
                <?php
                $current_year = date('Y');
                $graduation_dept = $is_youth_advisor_scope ? 'Youths' : $member['department'];
                $esc_dept = $conn->real_escape_string($graduation_dept);
                ?>
                <div class="page-header">
                    <h1><?= htmlspecialchars($graduation_dept) ?> Graduation Info</h1>
                    <p>View the list of graduating youths and graduation announcements for <?= $current_year ?>.</p>
                </div>

                <div class="content-card" style="margin-bottom: 30px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0;">Graduation Roster (<?= $current_year ?>)</h2>
                        <?php
                        $count_query = $conn->query("SELECT COUNT(*) as total FROM graduation_list WHERE department='$esc_dept' AND graduation_year=$current_year");
                        $total_graduands = $count_query->fetch_assoc()['total'];
                        ?>
                        <span class="badge" style="background: var(--primary); color: white; font-size: 14px; padding: 5px 12px;">Total: <?= $total_graduands ?></span>
                    </div>

                    <?php
                    $roster = $conn->query("SELECT gl.*, m.first_name, m.last_name FROM graduation_list gl JOIN members m ON gl.member_id = m.id WHERE gl.department = '$esc_dept' AND gl.graduation_year = $current_year ORDER BY m.first_name ASC");
                    if ($roster && $roster->num_rows > 0):
                    ?>
                        <div class="table-responsive">
                            <table>
                                <thead><tr><th>Name</th><th>Added Date</th></tr></thead>
                                <tbody>
                                    <?php while($r = $roster->fetch_assoc()): ?>
                                        <tr>
                                            <td style="font-weight: 500;"><svg width="18" height="18" fill="none" stroke="var(--primary)" viewBox="0 0 24 24" style="vertical-align: text-bottom; margin-right: 8px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path></svg><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
                                            <td style="color: var(--text-muted);"><?= date('M j, Y', strtotime($r['added_at'])) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center" style="padding: 40px; color: var(--text-muted);">
                            <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin: 0 auto 15px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path></svg>
                            <p>No graduands have been registered yet for <?= $current_year ?>.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="content-card">
                    <h2>Graduation Announcements</h2>
                    <?php
                    $grad_ann = $conn->query("SELECT da.*, m.first_name, m.last_name FROM department_announcements da JOIN members m ON da.secretary_id = m.id WHERE da.department = '$esc_dept' AND da.message LIKE '[GRADUATION UPDATE]%' ORDER BY da.created_at DESC");
                    if ($grad_ann && $grad_ann->num_rows > 0):
                    ?>
                        <?php while($ga = $grad_ann->fetch_assoc()): ?>
                            <div style="padding: 15px; border-left: 4px solid var(--primary); background: var(--bg-main); margin-bottom: 15px; border-radius: 0 8px 8px 0;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                    <strong style="color: var(--primary);"><?= htmlspecialchars($ga['first_name'] . ' ' . $ga['last_name']) ?> (Graduands Secretary)</strong>
                                    <small style="color: var(--text-muted);"><?= date('M j, Y', strtotime($ga['created_at'])) ?></small>
                                </div>
                                <p style="margin: 0; line-height: 1.5;"><?= nl2br(htmlspecialchars(str_replace('[GRADUATION UPDATE] ', '', $ga['message']))) ?></p>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: var(--text-muted);">No graduation announcements have been made yet.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($tab == 'prayer_panel' && $is_prayer_coordinator): ?>
                <?php
                $active_prayer_dept = $_GET['prayer_dept'] ?? (in_array($active_dashboard_dept, $prayer_coordinator_depts) ? $active_dashboard_dept : ($prayer_coordinator_depts[0] ?? $active_dashboard_dept));
                $esc_dept = $conn->real_escape_string($active_prayer_dept);
                ?>
                <div class="page-header">
                    <h1>Prayer Coordination Panel</h1>
                    <?php if (count($prayer_coordinator_depts) > 1): ?>
                    <p>You serve as Prayer Coordinator in multiple departments. Select one to manage:</p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;margin-bottom:15px;">
                        <?php foreach ($prayer_coordinator_depts as $pd): ?>
                            <a href="?tab=prayer_panel&prayer_dept=<?= urlencode($pd) ?>"
                               class="btn-sm<?= (strtolower($pd) === strtolower($active_prayer_dept)) ? ' btn-success' : '' ?>"
                               style="text-decoration:none;<?= (strtolower($pd) !== strtolower($active_prayer_dept)) ? 'background:var(--bg-card);border:1px solid var(--border-color);color:var(--text-main);' : '' ?>">
                               <?= htmlspecialchars($pd) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p>Schedule prayer sessions and manage customized prayer items for the <strong><?= htmlspecialchars($active_prayer_dept) ?></strong> department.</p>
                    <?php endif; ?>
                </div>

                <!-- Stats Row -->
                <?php
                $sched_count = $conn->query("SELECT COUNT(*) as c FROM prayer_schedules WHERE department='$esc_dept' AND prayer_date >= CURDATE()")->fetch_assoc()['c'];
                $items_count = $conn->query("SELECT COUNT(*) as c FROM prayer_items WHERE department='$esc_dept' AND status='Active'")->fetch_assoc()['c'];
                ?>
                <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:30px;">
                    <div class="content-card" style="flex:1; min-width:200px; text-align:center; padding:25px; background:linear-gradient(135deg,rgba(79,70,229,0.1),rgba(16,185,129,0.1)); border:1px solid rgba(79,70,229,0.2);">
                        <p style="font-size:2.5rem;font-weight:700;margin:0;color:var(--primary);"><?= $sched_count ?></p>
                        <p style="color:var(--text-muted);margin:5px 0 0;">Upcoming Prayers</p>
                    </div>
                    <div class="content-card" style="flex:1; min-width:200px; text-align:center; padding:25px; background:linear-gradient(135deg,rgba(245,158,11,0.1),rgba(239,68,68,0.1)); border:1px solid rgba(245,158,11,0.2);">
                        <p style="font-size:2.5rem;font-weight:700;margin:0;color:var(--warning);"><?= $items_count ?></p>
                        <p style="color:var(--text-muted);margin:5px 0 0;">Active Prayer Items</p>
                    </div>
                </div>

                <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:30px;">
                    <!-- Schedule a Prayer Session -->
                    <div class="content-card" style="flex:1; min-width:320px;">
                        <h2>📅 Schedule a Prayer Session</h2>
                        <form method="POST" action="member_action.php?action=schedule_prayer">
                            <input type="hidden" name="active_dept" value="<?= htmlspecialchars($active_prayer_dept) ?>">
                            <div class="form-group">
                                <label>Prayer Title</label>
                                <input type="text" name="title" class="form-control" placeholder="e.g. Church Unity Prayer Meeting" required>
                            </div>
                            <div class="form-group">
                                <label>Description / Agenda</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="What will be covered in this session?"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" name="prayer_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div style="display:flex; gap:15px; margin-bottom:15px;">
                                <div class="form-group" style="flex:1; margin-bottom:0;">
                                    <label>Start Time</label>
                                    <input type="time" name="prayer_time" class="form-control" required>
                                </div>
                                <div class="form-group" style="flex:1; margin-bottom:0;">
                                    <label>End Time</label>
                                    <input type="time" name="end_time" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Location</label>
                                <input type="text" name="location" class="form-control" placeholder="e.g. Church Main Hall" value="Church Main Hall">
                            </div>
                            <button type="submit" class="btn-submit">Schedule & Notify Department</button>
                        </form>
                    </div>

                    <!-- Add Prayer Item -->
                    <div class="content-card" style="flex:1; min-width:320px;">
                        <h2>🙏 Add Custom Prayer Item</h2>
                        <form method="POST" action="member_action.php?action=add_prayer_item">
                            <input type="hidden" name="active_dept" value="<?= htmlspecialchars($active_prayer_dept) ?>">
                            <div class="form-group">
                                <label>Prayer Point / Topic</label>
                                <input type="text" name="title" class="form-control" placeholder="e.g. Family Unity" required>
                            </div>
                            <div class="form-group">
                                <label>Details / Scripture</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Provide context, scripture reference, or specific needs..."></textarea>
                            </div>
                            <div class="form-group">
                                <label>Category</label>
                                <select name="category" class="form-control">
                                    <option value="General">General</option>
                                    <option value="Family">Family</option>
                                    <option value="Church Unity">Church Unity</option>
                                    <option value="Healing">Healing</option>
                                    <option value="Finances">Finances</option>
                                    <option value="Missions">Missions</option>
                                    <option value="Leadership">Leadership</option>
                                    <option value="Youth">Youth</option>
                                    <option value="Community">Community</option>
                                    <option value="Nation">Nation</option>
                                </select>
                            </div>
                            <button type="submit" class="btn-submit">Add Prayer Item & Notify</button>
                        </form>
                    </div>
                </div>

                <!-- Upcoming Schedules -->
                <div class="content-card" style="margin-bottom:20px;">
                    <h2>Scheduled Prayer Sessions</h2>
                    <?php $schedules = $conn->query("SELECT * FROM prayer_schedules WHERE department='$esc_dept' AND prayer_date >= CURDATE() ORDER BY prayer_date ASC, prayer_time ASC"); ?>
                    <?php if ($schedules && $schedules->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table>
                                <thead><tr><th>Title</th><th>Date</th><th>Time</th><th>Location</th><th>Action</th></tr></thead>
                                <tbody>
                                    <?php while($s = $schedules->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($s['title']) ?></strong><?php if($s['description']): ?><br><small style="color:var(--text-muted);"><?= htmlspecialchars(substr($s['description'],0,60)) ?>...</small><?php endif; ?></td>
                                            <td><?= date('D, M j Y', strtotime($s['prayer_date'])) ?></td>
                                            <td><?= date('g:i A', strtotime($s['prayer_time'])) ?></td>
                                            <td style="color:var(--text-muted);"><?= htmlspecialchars($s['location']) ?></td>
                                            <td><a href="member_action.php?action=delete_prayer_schedule&id=<?= $s['id'] ?>" class="btn-action" style="color:var(--danger);background:rgba(239,68,68,0.1);padding:4px 8px;border-radius:4px;" onclick="return confirm('Delete this scheduled prayer?');">Delete</a></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--text-muted);">No upcoming prayer sessions scheduled yet.</p>
                    <?php endif; ?>
                </div>

                <!-- Active Prayer Items -->
                <div class="content-card">
                    <h2>Active Prayer Items</h2>
                    <?php $items = $conn->query("SELECT * FROM prayer_items WHERE department='$esc_dept' ORDER BY status ASC, created_at DESC"); ?>
                    <?php if ($items && $items->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table>
                                <thead><tr><th>Prayer Point</th><th>Category</th><th>Status</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php while($pi = $items->fetch_assoc()): ?>
                                        <tr style="<?= $pi['status'] == 'Answered' ? 'opacity:0.6;' : '' ?>">
                                            <td>
                                                <strong><?= htmlspecialchars($pi['title']) ?></strong>
                                                <?php if($pi['description']): ?><br><small style="color:var(--text-muted);"><?= nl2br(htmlspecialchars($pi['description'])) ?></small><?php endif; ?>
                                            </td>
                                            <td><span class="badge" style="background:rgba(79,70,229,0.1);color:var(--primary);"><?= htmlspecialchars($pi['category']) ?></span></td>
                                            <td>
                                                <?php if($pi['status'] == 'Active'): ?>
                                                    <span class="badge" style="background:rgba(16,185,129,0.1);color:#10b981;">🙏 Active</span>
                                                <?php else: ?>
                                                    <span class="badge" style="background:rgba(245,158,11,0.1);color:var(--warning);">✓ Answered</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="white-space:nowrap;">
                                                <?php if($pi['status'] == 'Active'): ?>
                                                    <a href="member_action.php?action=mark_prayer_answered&id=<?= $pi['id'] ?>" class="btn-action" style="color:#10b981;background:rgba(16,185,129,0.1);padding:4px 8px;border-radius:4px;margin-right:5px;" onclick="return confirm('Mark as answered/completed?');">Mark Answered</a>
                                                <?php endif; ?>
                                                <a href="member_action.php?action=delete_prayer_item&id=<?= $pi['id'] ?>" class="btn-action" style="color:var(--danger);background:rgba(239,68,68,0.1);padding:4px 8px;border-radius:4px;" onclick="return confirm('Remove this prayer item?');">Remove</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--text-muted);">No prayer items added yet. Use the form above to add your first prayer point.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($tab == 'prayer_board' && !empty($member['department']) && $member['department'] !== 'None'): ?>
                <?php
                $esc_dept = $conn->real_escape_string($member['department']);
                $ps_dept_condition = "ps.department='$esc_dept'";
                $pi_dept_condition = "pi.department='$esc_dept'";
                if ($is_youth_advisor_scope && strtolower($esc_dept) !== 'youths' && strtolower($esc_dept) !== 'youth ministry') {
                    $ps_dept_condition = "(ps.department='$esc_dept' OR ps.department='Youths' OR ps.department='Youth Ministry')";
                    $pi_dept_condition = "(pi.department='$esc_dept' OR pi.department='Youths' OR pi.department='Youth Ministry')";
                }
                ?>
                <div class="page-header">
                    <h1><?= htmlspecialchars($member['department']) ?> Prayer Board</h1>
                    <p>Upcoming prayer sessions and department prayer items for you to join and intercede.</p>
                </div>

                <!-- Upcoming Prayer Sessions -->
                <div class="content-card" style="margin-bottom:25px;">
                    <h2>📅 Upcoming Prayer Sessions</h2>
                    <?php $schedules = $conn->query("SELECT ps.*, m.first_name, m.last_name FROM prayer_schedules ps JOIN members m ON ps.coordinator_id = m.id WHERE $ps_dept_condition AND ps.prayer_date >= CURDATE() ORDER BY ps.prayer_date ASC, ps.prayer_time ASC"); ?>
                    <?php if ($schedules && $schedules->num_rows > 0): ?>
                        <div style="display:flex; flex-direction:column; gap:15px;">
                            <?php while($s = $schedules->fetch_assoc()): ?>
                                <div style="display:flex; gap:20px; align-items:flex-start; padding:18px; border:1px solid var(--border-color); border-radius:12px; background:var(--bg-main);">
                                    <div style="text-align:center; background:var(--primary); color:white; border-radius:10px; padding:10px 15px; min-width:60px;">
                                        <div style="font-size:1.5rem; font-weight:700;"><?= date('j', strtotime($s['prayer_date'])) ?></div>
                                        <div style="font-size:0.75rem; text-transform:uppercase;"><?= date('M', strtotime($s['prayer_date'])) ?></div>
                                    </div>
                                    <div style="flex:1;">
                                        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px; margin-bottom:5px;">
                                            <h3 style="margin:0;color:var(--text-main);"><?= htmlspecialchars($s['title']) ?></h3>
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
                                            <span style="color:var(--text-muted);font-size:0.85rem;">👤 Led by <?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--text-muted);">No upcoming prayer sessions have been scheduled yet.</p>
                    <?php endif; ?>
                </div>

                <!-- Active Prayer Items -->
                <div class="content-card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
                        <h2 style="margin:0;">🙏 Department Prayer Items</h2>
                    </div>
                    <p style="color:var(--text-muted);margin-bottom:20px;">These are the active prayer points set by the Prayer Coordinator. Please remember them in your personal prayers.</p>
                    <?php $items = $conn->query("SELECT pi.*, m.first_name, m.last_name FROM prayer_items pi JOIN members m ON pi.coordinator_id = m.id WHERE $pi_dept_condition AND pi.status='Active' ORDER BY pi.created_at DESC"); ?>
                    <?php if ($items && $items->num_rows > 0): ?>
                        <div style="display:flex; flex-direction:column; gap:12px;">
                            <?php while($pi = $items->fetch_assoc()): ?>
                                <div style="padding:18px; border-left:4px solid var(--primary); background:var(--bg-main); border-radius:0 10px 10px 0;">
                                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
                                        <div>
                                            <strong style="font-size:1.05rem;color:var(--text-main);">🙏 <?= htmlspecialchars($pi['title']) ?></strong>
                                            <span class="badge" style="margin-left:10px;background:rgba(79,70,229,0.1);color:var(--primary);"><?= htmlspecialchars($pi['category']) ?></span>
                                        </div>
                                        <small style="color:var(--text-muted);">Added <?= date('M j, Y', strtotime($pi['created_at'])) ?></small>
                                    </div>
                                    <?php if($pi['description']): ?><p style="margin:0;color:var(--text-muted);font-size:0.9rem;line-height:1.5;"><?= nl2br(htmlspecialchars($pi['description'])) ?></p><?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center;padding:40px;color:var(--text-muted);">
                            <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto 15px;display:block;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                            <p>No active prayer items have been posted yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($is_youth_advisor_scope): ?>
                    <?php $youth_dept = $conn->real_escape_string('Youths'); ?>
                    <div class="content-card" style="margin-top:24px; margin-bottom:25px;">
                        <h2>Youth Department Prayer Sessions</h2>
                        <?php $schedules = $conn->query("SELECT ps.*, m.first_name, m.last_name FROM prayer_schedules ps JOIN members m ON ps.coordinator_id = m.id WHERE ps.department='$youth_dept' AND ps.prayer_date >= CURDATE() ORDER BY ps.prayer_date ASC, ps.prayer_time ASC"); ?>
                        <?php if ($schedules && $schedules->num_rows > 0): ?>
                            <div style="display:flex; flex-direction:column; gap:15px;">
                                <?php while($s = $schedules->fetch_assoc()): ?>
                                    <div style="padding:18px; border:1px solid var(--border-color); border-radius:12px; background:var(--bg-main);">
                                        <h3 style="margin:0 0 6px;color:var(--text-main);"><?= htmlspecialchars($s['title']) ?></h3>
                                        <?php if($s['description']): ?><p style="color:var(--text-muted);margin:0 0 8px;font-size:0.9rem;"><?= nl2br(htmlspecialchars($s['description'])) ?></p><?php endif; ?>
                                        <span style="color:var(--text-muted);font-size:0.85rem;"><?= date('M j, Y', strtotime($s['prayer_date'])) ?> at <?= date('g:i A', strtotime($s['prayer_time'])) ?> - <?= htmlspecialchars($s['location']) ?></span>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p style="color:var(--text-muted);">No Youth prayer sessions have been scheduled yet.</p>
                        <?php endif; ?>
                    </div>
                    <div class="content-card">
                        <h2>Youth Department Prayer Items</h2>
                        <?php $items = $conn->query("SELECT pi.*, m.first_name, m.last_name FROM prayer_items pi JOIN members m ON pi.coordinator_id = m.id WHERE pi.department='$youth_dept' AND pi.status='Active' ORDER BY pi.created_at DESC"); ?>
                        <?php if ($items && $items->num_rows > 0): ?>
                            <div style="display:flex; flex-direction:column; gap:12px;">
                                <?php while($pi = $items->fetch_assoc()): ?>
                                    <div style="padding:18px; border-left:4px solid var(--primary); background:var(--bg-main); border-radius:0 10px 10px 0;">
                                        <strong style="font-size:1.05rem;color:var(--text-main);"><?= htmlspecialchars($pi['title']) ?></strong>
                                        <span class="badge" style="margin-left:10px;background:rgba(79,70,229,0.1);color:var(--primary);"><?= htmlspecialchars($pi['category']) ?></span>
                                        <?php if($pi['description']): ?><p style="margin:8px 0 0;color:var(--text-muted);font-size:0.9rem;line-height:1.5;"><?= nl2br(htmlspecialchars($pi['description'])) ?></p><?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p style="color:var(--text-muted);">No active Youth prayer items have been posted yet.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($tab == 'dept_sport' && !empty($member['department']) && $member['department'] !== 'None' && (department_allows_graduation_and_sport($member['department']) || $is_youth_advisor_scope)): ?>
                <?php
                $sport_dept = $is_youth_advisor_scope ? 'Youths' : $member['department'];
                $esc_dept = $conn->real_escape_string($sport_dept);
                ?>
                <div class="page-header">
                    <h1>⚽ Department Sport — <?= htmlspecialchars($sport_dept) ?></h1>
                    <p>Matches and sport announcements from your Sport Secretary.</p>
                </div>

                <div class="content-card" style="margin-bottom:25px;">
                    <h2>🏆 Department Matches</h2>
                    <?php
                    $dept_matches = $conn->query("SELECT sm.*, m.first_name, m.last_name FROM sport_matches sm JOIN members m ON sm.secretary_id = m.id WHERE sm.department='$esc_dept' ORDER BY sm.match_date DESC, sm.match_time DESC");
                    if ($dept_matches && $dept_matches->num_rows > 0):
                    ?>
                        <div style="display:flex;flex-direction:column;gap:15px;margin-top:15px;">
                            <?php while($sm = $dept_matches->fetch_assoc()): ?>
                                <div style="display:flex;gap:20px;align-items:flex-start;padding:18px;border:1px solid var(--border-color);border-radius:12px;background:var(--bg-main);">
                                    <div style="text-align:center;background:var(--primary);color:white;border-radius:10px;padding:10px 15px;min-width:60px;">
                                        <div style="font-size:1.5rem;font-weight:700;"><?= date('j', strtotime($sm['match_date'])) ?></div>
                                        <div style="font-size:0.75rem;text-transform:uppercase;"><?= date('M', strtotime($sm['match_date'])) ?></div>
                                    </div>
                                    <div style="flex:1;">
                                        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:5px;">
                                            <h3 style="margin:0;color:var(--text-main);"><?= htmlspecialchars($sm['title']) ?></h3>
                                            <?php
                                            $target_dt = $sm['match_date'] . ' ' . $sm['match_time'];
                                            $end_dt = !empty($sm['end_time']) ? $sm['match_date'] . ' ' . $sm['end_time'] : '';
                                            ?>
                                            <span class="badge prayer-countdown" data-target="<?= $target_dt ?>" <?= $end_dt ? 'data-end-target="'.$end_dt.'"' : '' ?> style="background:rgba(245,158,11,0.1);color:var(--warning);font-weight:600;font-family:monospace;font-size:13px;">Calculating...</span>
                                        </div>
                                        <?php if($sm['description']): ?><p style="color:var(--text-muted);margin:0 0 8px;font-size:0.9rem;"><?= nl2br(htmlspecialchars($sm['description'])) ?></p><?php endif; ?>
                                        <div style="display:flex;gap:15px;flex-wrap:wrap;">
                                            <span style="color:var(--text-muted);font-size:0.85rem;">⏰ <?= date('g:i A', strtotime($sm['match_time'])) ?><?= $sm['end_time'] ? ' – '.date('g:i A', strtotime($sm['end_time'])) : '' ?></span>
                                            <span style="color:var(--text-muted);font-size:0.85rem;">📍 <?= htmlspecialchars($sm['location']) ?></span>
                                            <span style="color:var(--text-muted);font-size:0.85rem;">👤 <?= htmlspecialchars($sm['first_name'] . ' ' . $sm['last_name']) ?></span>
                                        </div>
                                        <?php if (!empty($sm['score_result'])): ?>
                                            <div style="margin-top:15px;padding-top:15px;border-top:1px solid var(--border-color);display:flex;gap:15px;align-items:center;">
                                                <div style="flex:1;">
                                                    <strong style="color:var(--text-main);display:block;margin-bottom:5px;">Final Result:</strong>
                                                    <span style="background:var(--primary);color:white;padding:4px 10px;border-radius:20px;font-weight:bold;"><?= htmlspecialchars($sm['score_result']) ?></span>
                                                </div>
                                                <?php if ($sm['result_image']): ?>
                                                    <a href="uploads/<?= htmlspecialchars($sm['result_image']) ?>" target="_blank">
                                                        <img src="uploads/<?= htmlspecialchars($sm['result_image']) ?>" alt="Result" style="width:60px;height:60px;object-fit:cover;border-radius:8px;border:1px solid var(--border-color);">
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--text-muted);margin-top:15px;">No matches scheduled yet.</p>
                    <?php endif; ?>
                </div>

                <div class="content-card">
                    <h2>📢 Sport Announcements</h2>
                    <?php
                    $dept_sport_anncs = $conn->query("SELECT sa.*, m.first_name, m.last_name FROM sport_announcements sa JOIN members m ON sa.secretary_id = m.id WHERE sa.department='$esc_dept' ORDER BY sa.created_at DESC");
                    if ($dept_sport_anncs && $dept_sport_anncs->num_rows > 0):
                    ?>
                        <div style="display:flex;flex-direction:column;gap:12px;margin-top:15px;">
                            <?php while($sa = $dept_sport_anncs->fetch_assoc()): ?>
                                <div style="padding:18px;border-left:4px solid var(--primary);background:var(--bg-main);border-radius:0 10px 10px 0;">
                                    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:8px;">
                                        <strong style="color:var(--text-main);">📢 <?= htmlspecialchars($sa['first_name'] . ' ' . $sa['last_name']) ?></strong>
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
            <?php endif; ?>

            <?php if ($tab == 'manage_sport' && $is_sport_secretary): ?>
                <?php
                $active_sport_dept = $_GET['sport_dept'] ?? (in_array($active_dashboard_dept, $sport_secretary_depts) ? $active_dashboard_dept : ($sport_secretary_depts[0] ?? $active_dashboard_dept));
                $esc_dept = $conn->real_escape_string($active_sport_dept);
                ?>
                <div class="page-header">
                    <h1>⚽ Manage Sport — <?= htmlspecialchars($active_sport_dept) ?></h1>
                    <?php if (count($sport_secretary_depts) > 1): ?>
                    <p>You serve as Sport Secretary in multiple departments. Select one to manage:</p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;margin-bottom:15px;">
                        <?php foreach ($sport_secretary_depts as $sd): ?>
                            <a href="?tab=manage_sport&sport_dept=<?= urlencode($sd) ?>"
                               class="btn-sm<?= (strtolower($sd) === strtolower($active_sport_dept)) ? ' btn-success' : '' ?>"
                               style="text-decoration:none;<?= (strtolower($sd) !== strtolower($active_sport_dept)) ? 'background:var(--bg-card);border:1px solid var(--border-color);color:var(--text-main);' : '' ?>">
                               <?= htmlspecialchars($sd) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p>Schedule matches, post announcements, and keep your department updated on all sport activities.</p>
                    <?php endif; ?>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert-success" style="background:rgba(16,185,129,0.1);border:1px solid #10b981;border-radius:10px;padding:12px 18px;margin-bottom:20px;color:#10b981;"><?= htmlspecialchars($_GET['success']) ?></div>
                <?php endif; ?>

                <div style="display:flex; gap:25px; flex-wrap:wrap; align-items:flex-start; margin-bottom:30px;">
                    <!-- Schedule a Match -->
                    <div class="content-card" style="flex:1; min-width:320px;">
                        <h2>🏆 Schedule a Match</h2>
                        <form method="POST" action="member_action.php?action=schedule_match">
                            <input type="hidden" name="active_dept" value="<?= htmlspecialchars($active_sport_dept) ?>">
                            <div class="form-group">
                                <label>Match Title</label>
                                <input type="text" name="title" class="form-control" placeholder="e.g. Youths vs Elders Friendly" required>
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Match details, rules, teams..."></textarea>
                            </div>
                            <div class="form-group">
                                <label>Match Date</label>
                                <input type="date" name="match_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div style="display:flex; gap:15px; margin-bottom:15px;">
                                <div class="form-group" style="flex:1; margin-bottom:0;">
                                    <label>Start Time</label>
                                    <input type="time" name="match_time" class="form-control" required>
                                </div>
                                <div class="form-group" style="flex:1; margin-bottom:0;">
                                    <label>End Time</label>
                                    <input type="time" name="end_time" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Location / Venue</label>
                                <input type="text" name="location" class="form-control" placeholder="e.g. Church Grounds" value="Church Grounds">
                            </div>
                            <button type="submit" class="btn-submit">📅 Schedule & Notify Members</button>
                        </form>
                    </div>

                    <!-- Post Announcement -->
                    <div class="content-card" style="flex:1; min-width:320px;">
                        <h2>📢 Post Sport Announcement</h2>
                        <form method="POST" action="member_action.php?action=post_sport_announcement">
                            <input type="hidden" name="active_dept" value="<?= htmlspecialchars($active_sport_dept) ?>">
                            <div class="form-group">
                                <label>Announcement Message</label>
                                <textarea name="message" class="form-control" rows="5" placeholder="Share sport news, training updates, team selections..." required></textarea>
                            </div>
                            <button type="submit" class="btn-submit">📣 Post to Department</button>
                        </form>
                    </div>
                </div>

                <!-- Scheduled Matches -->
                <div class="content-card" style="margin-bottom:25px;">
                    <h2>📅 Scheduled Matches</h2>
                    <?php $matches = $conn->query("SELECT sm.*, m.first_name, m.last_name FROM sport_matches sm JOIN members m ON sm.secretary_id = m.id WHERE sm.department='$esc_dept' ORDER BY sm.match_date DESC, sm.match_time DESC"); ?>
                    <?php if ($matches && $matches->num_rows > 0): ?>
                        <div style="display:flex; flex-direction:column; gap:15px;">
                            <?php while($sm = $matches->fetch_assoc()): ?>
                                <div style="display:flex; gap:20px; align-items:flex-start; padding:18px; border:1px solid var(--border-color); border-radius:12px; background:var(--bg-main);">
                                    <div style="text-align:center; background:var(--primary); color:white; border-radius:10px; padding:10px 15px; min-width:60px;">
                                        <div style="font-size:1.5rem; font-weight:700;"><?= date('j', strtotime($sm['match_date'])) ?></div>
                                        <div style="font-size:0.75rem; text-transform:uppercase;"><?= date('M', strtotime($sm['match_date'])) ?></div>
                                    </div>
                                    <div style="flex:1;">
                                        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px; margin-bottom:5px;">
                                            <h3 style="margin:0;color:var(--text-main);"><?= htmlspecialchars($sm['title']) ?></h3>
                                            <?php
                                            $target_dt = $sm['match_date'] . ' ' . $sm['match_time'];
                                            $end_dt = !empty($sm['end_time']) ? $sm['match_date'] . ' ' . $sm['end_time'] : '';
                                            ?>
                                            <span class="badge prayer-countdown" data-target="<?= $target_dt ?>" <?= $end_dt ? 'data-end-target="'.$end_dt.'"' : '' ?> style="background:rgba(245,158,11,0.1);color:var(--warning);font-weight:600;font-family:monospace;font-size:13px;">Calculating...</span>
                                        </div>
                                        <?php if($sm['description']): ?><p style="color:var(--text-muted);margin:0 0 8px;font-size:0.9rem;"><?= nl2br(htmlspecialchars($sm['description'])) ?></p><?php endif; ?>
                                        <div style="display:flex;gap:15px;flex-wrap:wrap;">
                                            <span style="color:var(--text-muted);font-size:0.85rem;">⏰ <?= date('g:i A', strtotime($sm['match_time'])) ?><?= $sm['end_time'] ? ' – '.date('g:i A', strtotime($sm['end_time'])) : '' ?></span>
                                            <span style="color:var(--text-muted);font-size:0.85rem;">📍 <?= htmlspecialchars($sm['location']) ?></span>
                                        </div>
                                        <?php
                                        $now_ts = time();
                                        $end_ts = strtotime($sm['match_date'] . ' ' . (!empty($sm['end_time']) ? $sm['end_time'] : $sm['match_time'] . ' +2 hours'));
                                        if ($now_ts >= $end_ts):
                                        ?>
                                            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border-color);">
                                                <?php if (empty($sm['score_result'])): ?>
                                                    <form method="POST" action="member_action.php?action=upload_match_results" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:10px; background:var(--bg-card); padding:12px; border-radius:8px; border:1px solid var(--border-color);">
                                                        <strong style="color:var(--text-main);font-size:0.9rem;">Upload Match Results</strong>
                                                        <input type="hidden" name="match_id" value="<?= $sm['id'] ?>">
                                                        <input type="text" name="score_result" class="form-control" placeholder="Final Score (e.g. 3 - 1, Team A Won)" required style="padding:6px 10px;font-size:0.9rem;">
                                                        <input type="file" name="result_image" class="form-control" accept="image/*" style="padding:6px 10px;font-size:0.9rem;">
                                                        <button type="submit" class="btn btn-primary" style="padding:6px 12px;font-size:0.9rem;align-self:flex-start;">Upload Results</button>
                                                    </form>
                                                <?php else: ?>
                                                    <div style="display:flex; gap:15px; align-items:center;">
                                                        <div style="flex:1;">
                                                            <strong style="color:var(--text-main);display:block;margin-bottom:5px;">Final Result:</strong>
                                                            <span style="background:var(--primary);color:white;padding:4px 10px;border-radius:20px;font-weight:bold;"><?= htmlspecialchars($sm['score_result']) ?></span>
                                                        </div>
                                                        <?php if ($sm['result_image']): ?>
                                                            <a href="uploads/<?= htmlspecialchars($sm['result_image']) ?>" target="_blank">
                                                                <img src="uploads/<?= htmlspecialchars($sm['result_image']) ?>" alt="Result" style="width:60px;height:60px;object-fit:cover;border-radius:8px;border:1px solid var(--border-color);">
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display:flex;flex-direction:column;gap:10px;min-width:70px;">
                                        <a href="member_action.php?action=delete_sport_match&id=<?= $sm['id'] ?>" onclick="return confirm('Delete this match?')" style="color:var(--danger);font-size:0.8rem;padding:4px 10px;border:1px solid var(--danger);border-radius:6px;text-align:center;">Delete</a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--text-muted);">No matches scheduled yet. Use the form above to schedule your first match.</p>
                    <?php endif; ?>
                </div>

                <!-- Sport Announcements List -->
                <div class="content-card">
                    <h2>📋 Posted Announcements</h2>
                    <?php $sport_anncs = $conn->query("SELECT sa.*, m.first_name, m.last_name FROM sport_announcements sa JOIN members m ON sa.secretary_id = m.id WHERE sa.department='$esc_dept' ORDER BY sa.created_at DESC"); ?>
                    <?php if ($sport_anncs && $sport_anncs->num_rows > 0): ?>
                        <div style="display:flex;flex-direction:column;gap:15px;">
                            <?php while($sa = $sport_anncs->fetch_assoc()): ?>
                                <div style="padding:18px;border-left:4px solid var(--primary);background:var(--bg-main);border-radius:0 10px 10px 0;">
                                    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:8px;">
                                        <strong style="color:var(--text-main);">📢 <?= htmlspecialchars($sa['first_name'] . ' ' . $sa['last_name']) ?></strong>
                                        <small style="color:var(--text-muted);"><?= date('M j, Y g:i A', strtotime($sa['created_at'])) ?></small>
                                    </div>
                                    <p style="margin:0;color:var(--text-muted);line-height:1.6;"><?= nl2br(htmlspecialchars($sa['message'])) ?></p>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--text-muted);">No sport announcements posted yet.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

    <!-- ==================== USHER PANEL (HEAD USHER) ==================== -->
    <?php if ($tab == 'usher_panel' && $is_head_usher): ?>
    <style>.main-content { display: block !important; }</style>
    <?php endif; ?>

        <?php if ($tab == 'usher_panel' && $is_head_usher): ?>
            <div class="page-header">
                <h1>📢 Usher Announcements</h1>
                <p>Post church-wide announcements to all members and communicate with fellow ushers.</p>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert" style="background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid #10b981; padding: 12px 18px; border-radius: 10px; margin-bottom: 20px;">
                    ✅ <?= htmlspecialchars($_GET['success']) ?>
                </div>
            <?php endif; ?>

            <!-- Post Announcement Form -->
            <div class="content-card" style="border-left: 4px solid var(--primary); margin-bottom: 25px;">
                <h2 style="margin-bottom: 15px;">Post New Announcement</h2>
                <p style="color: var(--text-muted); margin-bottom: 15px; font-size: 0.9rem;">This announcement will be sent as a notification to <strong>all active church members</strong>.</p>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="post_usher_announcement" value="1">
                    <div class="form-group">
                        <label>Announcement Title</label>
                        <input type="text" name="usher_title" class="form-control" placeholder="e.g. Sunday Service Arrangement" required>
                    </div>
                    <div class="form-group">
                        <label>Details / Message</label>
                        <textarea name="usher_message" class="form-control" rows="4" placeholder="Provide details of the announcement..." required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Attach Image (Optional)</label>
                        <input type="file" name="usher_image" class="form-control" accept="image/*">
                    </div>
                    <button type="submit" class="btn-submit">📣 Post to All Members</button>
                </form>
            </div>

            <!-- Message to Secretary Form -->
            <div class="content-card" style="border-left: 4px solid var(--secondary); margin-bottom: 25px;">
                <h2 style="margin-bottom: 15px;">Message General Church Secretary</h2>
                <p style="color: var(--text-muted); margin-bottom: 15px; font-size: 0.9rem;">Send a direct message or query to the General Church Secretary.</p>
                <form method="POST">
                    <input type="hidden" name="send_secretary_message" value="1">
                    <div class="form-group">
                        <label>Your Message</label>
                        <textarea name="secretary_message" class="form-control" rows="3" placeholder="Write your message to the secretary..." required></textarea>
                    </div>
                    <button type="submit" class="btn-submit" style="background: var(--secondary);">✉️ Send Message</button>
                </form>
            </div>

            <!-- Past Announcements -->
            <div class="content-card">
                <h2 style="margin-bottom: 15px;">Past Announcements</h2>
                <?php
                $usher_anns = $conn->query("SELECT ua.*, m.first_name, m.last_name FROM usher_announcements ua JOIN members m ON ua.usher_id = m.id ORDER BY ua.created_at DESC");
                if ($usher_anns && $usher_anns->num_rows > 0): ?>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php while($ua = $usher_anns->fetch_assoc()): ?>
                        <div style="padding: 18px; border-left: 4px solid var(--primary); background: var(--bg-main); border-radius: 0 10px 10px 0;">
                            <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 8px;">
                                <strong style="color: var(--text-main);">📢 <?= htmlspecialchars($ua['title']) ?></strong>
                                <small style="color: var(--text-muted);"><?= date('M j, Y g:i A', strtotime($ua['created_at'])) ?> — <?= htmlspecialchars($ua['first_name'] . ' ' . $ua['last_name']) ?></small>
                            </div>
                            <?php if(!empty($ua['image_path'])): ?>
                                <img src="<?= htmlspecialchars($ua['image_path']) ?>" alt="Announcement Image" style="max-width: 100%; border-radius: 8px; margin-bottom: 10px;">
                            <?php endif; ?>
                            <p style="margin: 0; color: var(--text-muted); line-height: 1.6;"><?= nl2br(htmlspecialchars($ua['message'])) ?></p>
                        </div>
                    <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-muted);">No announcements posted yet.</p>
                <?php endif; ?>
            </div>

            <!-- Usher Chat (embedded in usher_panel for Head Usher) -->
            <div class="content-card" style="margin-top: 25px;">
                <h2 style="margin-bottom: 15px;">💬 Usher Chat</h2>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 15px;">Private chat between Head Ushers and Ushers.</p>
                <?php
                $usher_msgs = $conn->query("SELECT uc.*, m.first_name, m.last_name FROM usher_chat uc JOIN members m ON uc.sender_id = m.id ORDER BY uc.created_at ASC");
                if ($usher_msgs && $usher_msgs->num_rows > 0): ?>
                <div style="background: var(--bg-main); border-radius: 10px; padding: 20px; max-height: 400px; overflow-y: auto; margin-bottom: 20px; display: flex; flex-direction: column; gap: 14px;">
                    <?php while($uc = $usher_msgs->fetch_assoc()):
                        $is_me = ($uc['sender_id'] == $member_id); ?>
                        <div style="display: flex; flex-direction: column; align-items: <?= $is_me ? 'flex-end' : 'flex-start' ?>;">
                            <span style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 4px;"><?= htmlspecialchars($uc['first_name'] . ' ' . $uc['last_name']) ?> · <?= date('M j, g:i A', strtotime($uc['created_at'])) ?></span>
                            <div style="background: <?= $is_me ? 'var(--primary)' : 'var(--bg-card)' ?>; color: <?= $is_me ? 'white' : 'var(--text-main)' ?>; padding: 10px 16px; border-radius: <?= $is_me ? '18px 18px 4px 18px' : '18px 18px 18px 4px' ?>; max-width: 75%; word-break: break-word; border: 1px solid var(--border-color);">
                                <?= nl2br(htmlspecialchars($uc['message'])) ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <p style="color: var(--text-muted); margin-bottom: 15px;">No messages yet. Start the conversation!</p>
                <?php endif; ?>
                <form method="POST" style="display: flex; gap: 10px;">
                    <input type="hidden" name="send_usher_chat" value="1">
                    <input type="text" name="usher_chat_message" class="form-control" placeholder="Type a message..." required style="margin-bottom: 0;">
                    <button type="submit" class="btn-submit" style="white-space: nowrap; padding: 0 20px;">Send</button>
                </form>
            </div>

        <?php elseif ($tab == 'usher_chat' && ($is_head_usher || in_array('usher', $member_roles_normalized, true))): ?>
            <div class="page-header">
                <h1>💬 Usher Chat</h1>
                <p>Private communication channel for all ushers and Head Ushers.</p>
            </div>

            <!-- Usher Announcements (read-only for regular ushers) -->
            <?php if (!$is_head_usher): ?>
            <div class="content-card" style="margin-bottom: 25px;">
                <h2 style="margin-bottom: 15px;">📢 Usher Announcements</h2>
                <?php
                $usher_anns2 = $conn->query("SELECT ua.*, m.first_name, m.last_name FROM usher_announcements ua JOIN members m ON ua.usher_id = m.id ORDER BY ua.created_at DESC LIMIT 10");
                if ($usher_anns2 && $usher_anns2->num_rows > 0): ?>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php while($ua2 = $usher_anns2->fetch_assoc()): ?>
                        <div style="padding: 15px; border-left: 3px solid var(--primary); background: var(--bg-main); border-radius: 0 8px 8px 0;">
                            <strong style="color: var(--primary);"><?= htmlspecialchars($ua2['title']) ?></strong>
                            <small style="display: block; color: var(--text-muted); margin: 4px 0 8px;"><?= date('M j, Y g:i A', strtotime($ua2['created_at'])) ?></small>
                            <p style="margin: 0; color: var(--text-muted); font-size: 0.9rem;"><?= nl2br(htmlspecialchars($ua2['message'])) ?></p>
                        </div>
                    <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-muted);">No announcements from Head Usher yet.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Chat Area -->
            <div class="content-card">
                <h2 style="margin-bottom: 15px;">Chat</h2>
                <?php
                $usher_msgs2 = $conn->query("SELECT uc.*, m.first_name, m.last_name FROM usher_chat uc JOIN members m ON uc.sender_id = m.id ORDER BY uc.created_at ASC");
                if ($usher_msgs2 && $usher_msgs2->num_rows > 0): ?>
                <div style="background: var(--bg-main); border-radius: 10px; padding: 20px; max-height: 450px; overflow-y: auto; margin-bottom: 20px; display: flex; flex-direction: column; gap: 14px;" id="usherChatBox">
                    <?php while($uc2 = $usher_msgs2->fetch_assoc()):
                        $is_me2 = ($uc2['sender_id'] == $member_id); ?>
                        <div style="display: flex; flex-direction: column; align-items: <?= $is_me2 ? 'flex-end' : 'flex-start' ?>;">
                            <span style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 4px;"><?= htmlspecialchars($uc2['first_name'] . ' ' . $uc2['last_name']) ?> · <?= date('M j, g:i A', strtotime($uc2['created_at'])) ?></span>
                            <div style="background: <?= $is_me2 ? 'var(--primary)' : 'var(--bg-card)' ?>; color: <?= $is_me2 ? 'white' : 'var(--text-main)' ?>; padding: 10px 16px; border-radius: <?= $is_me2 ? '18px 18px 4px 18px' : '18px 18px 18px 4px' ?>; max-width: 75%; word-break: break-word; border: 1px solid var(--border-color);">
                                <?= nl2br(htmlspecialchars($uc2['message'])) ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <script>document.getElementById('usherChatBox').scrollTop = document.getElementById('usherChatBox').scrollHeight;</script>
                <?php else: ?>
                <p style="color: var(--text-muted); margin-bottom: 15px;">No messages yet. Be the first to say something!</p>
                <?php endif; ?>
                <form method="POST" style="display: flex; gap: 10px;">
                    <input type="hidden" name="send_usher_chat" value="1">
                    <input type="text" name="usher_chat_message" class="form-control" placeholder="Type a message to the usher team..." required style="margin-bottom: 0;">
                    <button type="submit" class="btn-submit" style="white-space: nowrap; padding: 0 20px;">Send</button>
                </form>
            </div>


        <?php elseif ($tab == 'my_financials' && !empty($member['department']) && $member['department'] !== 'None'): ?>
            <div class="page-header">
                <h1>Financial Records</h1>
                <p>Confirmed financial records from your department Chairperson.</p>
            </div>
            <?php
            $my_dept = $conn->real_escape_string($member['department']);
            $my_dept_records = $conn->query("
                SELECT fr.*, tr.first_name AS treasurer_first, tr.last_name AS treasurer_last, cb.first_name AS chair_first, cb.last_name AS chair_last
                FROM financial_records fr
                JOIN members tr ON fr.recorded_by = tr.id
                LEFT JOIN members cb ON fr.chair_confirmed_by = cb.id
                WHERE " . department_match_sql($conn, 'fr.department', $member['department']) . "
                  AND fr.is_sent_to_chair = 1
                  AND fr.is_chair_confirmed = 1
                ORDER BY fr.chair_confirmed_at DESC, fr.record_date DESC
            ");
            $my_total = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM financial_records fr WHERE " . department_match_sql($conn, 'fr.department', $member['department']) . " AND fr.is_sent_to_chair = 1 AND fr.is_chair_confirmed = 1")->fetch_assoc()['total'];
            ?>
            <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
                <div class="content-card" style="border-top: 4px solid #10b981;">
                    <div style="font-size: 0.85rem; color: var(--text-muted);">Total Confirmed Department Records</div>
                    <div style="font-size: 2rem; font-weight: 700; color: #10b981; margin-top: 5px;">KSh <?= number_format($my_total, 2) ?></div>
                </div>
                <div class="content-card">
                    <h2 style="margin-bottom: 15px;">Confirmed Department Records</h2>
                    <?php if ($my_dept_records && $my_dept_records->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Department</th>
                                    <th>Amount (KSh)</th>
                                    <th>Source / Note</th>
                                    <th>Treasurer</th>
                                    <th>Confirmed By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($dept_record = $my_dept_records->fetch_assoc()): ?>
                                <tr>
                                    <td><?= date('M j, Y', strtotime($dept_record['chair_confirmed_at'] ?: $dept_record['record_date'])) ?></td>
                                    <td><?= htmlspecialchars($dept_record['department']) ?></td>
                                    <td style="font-weight: 700; color: #10b981;">KSh <?= number_format($dept_record['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($dept_record['chair_disbursement_note'] ?: ($dept_record['description'] ?: 'No note')) ?></td>
                                    <td><?= htmlspecialchars($dept_record['treasurer_first'] . ' ' . $dept_record['treasurer_last']) ?></td>
                                    <td><?= htmlspecialchars(trim(($dept_record['chair_first'] ?? '') . ' ' . ($dept_record['chair_last'] ?? ''))) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p style="color: var(--text-muted);">No confirmed financial records are available for your department yet.</p>
                    <?php endif; ?>
                </div>
            </div>
                <?php elseif ($tab == 'desired_roles'): ?>
            <div class="page-header">
                <h1>Church Village & Roles</h1>
                <p>Welcome! Please tell us which church village you belong to, and optionally select a desired role to serve in.</p>
            </div>

            <?php if (!$has_church_village): ?>
            <div style="background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); color:var(--danger); padding:16px; border-radius:10px; margin-bottom:24px; display:flex; gap:12px; align-items:flex-start;">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div style="font-size:0.95rem; line-height:1.4;">
                    <strong>Action Required:</strong> You must select your Church Village before you can access the rest of your dashboard tabs.
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($member['pending_church_village'])): ?>
                <div style="background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.3);color:#b45309;padding:12px 16px;border-radius:10px;margin-bottom:18px;">
                    ?3 Your request to update your church village to <strong><?= htmlspecialchars($member['pending_church_village']) ?></strong> is pending Pastor approval.
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['success'])): ?>
                <div style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);color:#065f46;padding:12px 16px;border-radius:10px;margin-bottom:18px;">
                    <?= htmlspecialchars($_GET['success']) ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div style="background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);color:#991b1b;padding:12px 16px;border-radius:10px;margin-bottom:18px;">
                    <?= htmlspecialchars($_GET['error']) ?>
                </div>
            <?php endif; ?>

            <?php
            // Leader profile and announcements
            if ($has_church_village) {
                $cv = $conn->real_escape_string($member['church_village']);
                $v_leader_q = $conn->query("SELECT id, first_name, last_name, phone, profile_picture FROM members WHERE is_village_leader = 1 AND church_village = '$cv'");
                if ($v_leader_q && $v_leader_q->num_rows > 0) {
                    $vl = $v_leader_q->fetch_assoc();
                    ?>
                    <div class="content-card" style="margin-bottom:24px; border-left:4px solid var(--primary);">
                        <h2 style="margin-bottom:15px; font-size:1.1rem; color:var(--text-main);">Your Village Leader</h2>
                        <div style="display:flex; align-items:center; gap:16px;">
                            <img src="uploads/<?= htmlspecialchars($vl['profile_picture'] ?? 'default_avatar.png') ?>" style="width:60px; height:60px; border-radius:50%; object-fit:cover; border:2px solid var(--primary);">
                            <div>
                                <h3 style="margin:0; font-size:1rem; color:var(--text-main);"><?= htmlspecialchars($vl['first_name'] . ' ' . $vl['last_name']) ?></h3>
                                <p style="margin:4px 0 0; color:var(--text-muted); font-size:0.9rem;">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                    <?= htmlspecialchars($vl['phone'] ?? '') ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="content-card" style="margin-bottom:24px;">
                        <h2 style="margin-bottom:15px; font-size:1.1rem; color:var(--text-main);">Village Announcements</h2>
                        <?php
                        $vl_id = $vl['id'];
                        $announcements = $conn->query("SELECT message, created_at FROM village_announcements WHERE village = '$cv' ORDER BY created_at DESC LIMIT 5");
                        if ($announcements && $announcements->num_rows > 0) {
                            while ($ann = $announcements->fetch_assoc()) {
                                echo "<div style='padding:12px; background:var(--bg-lighter); border-radius:8px; margin-bottom:12px; border-left:3px solid #10b981;'>";
                                echo "<p style='margin:0 0 8px; font-size:0.95rem; color:var(--text-main);'>" . nl2br(htmlspecialchars($ann['message'])) . "</p>";
                                echo "<small style='color:var(--text-muted); font-size:0.8rem;'>Posted: " . date('M j, Y g:i A', strtotime($ann['created_at'])) . "</small>";
                                echo "</div>";
                            }
                        } else {
                            echo "<p style='color:var(--text-muted); margin:0;'>No announcements from your village leader yet.</p>";
                        }
                        ?>
                    </div>
                    <?php
                }
            }
            ?>

            <form method="POST" action="?tab=desired_roles" class="content-card" style="max-width:800px;">
                <input type="hidden" name="save_church_village" value="1">
                
                <div style="margin-bottom:30px;">
                    <h2 style="margin-bottom:15px; font-size:1.1rem;">Select Your Church Village <span style="color:var(--danger)">*</span></h2>
                    <select name="church_village" required style="width:100%; padding:12px; border:1px solid var(--border-color); border-radius:8px; background:var(--bg-lighter); color:var(--text-main);">
                        <option value="">-- Select Village --</option>
                        <?php
                        $villages = ['Akoritho', 'Philadelphia', 'Bethsaida'];
                        $curr_v = $member['church_village'] ?? '';
                        foreach ($villages as $v) {
                            $sel = ($curr_v === $v) ? 'selected' : '';
                            echo "<option value=\"$v\" $sel>$v</option>";
                        }
                        ?>
                    </select>
                </div>

                <div style="margin-bottom:30px;">
                    <h2 style="margin-bottom:15px; font-size:1.1rem;">Select Desired Role (Optional)</h2>
                    <select name="desired_role_pref" style="width:100%; padding:12px; border:1px solid var(--border-color); border-radius:8px; background:var(--bg-lighter); color:var(--text-main);">
                        <option value="">-- None --</option>
                        <?php
                        $roles = ['Worshipper', 'Church Cleaner', 'Church Cooker'];
                        $cr_q = $conn->query("SELECT role_name FROM custom_desired_roles ORDER BY id ASC");
                        if ($cr_q) { while($cr = $cr_q->fetch_assoc()){ $roles[] = $cr['role_name']; } }
                        $curr_r = $member['desired_role_pref'] ?? '';
                        foreach ($roles as $r) {
                            $sel = ($curr_r === $r) ? 'selected' : '';
                            echo "<option value=\"$r\" $sel>$r</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <button type="submit" class="btn-primary" style="padding:12px 24px;">Save Preferences</button>
            </form>

        <?php elseif ($tab == 'manage_church_village' && $member['is_village_leader'] == 1): ?>
            <div class="page-header">
                <h1>Manage Church Village</h1>
                <p>Welcome, <?= htmlspecialchars($member['first_name']) ?>! As the leader of <strong><?= htmlspecialchars($member['church_village']) ?></strong> village, you can manage your members and post announcements.</p>
            </div>
            
            <?php
            // Handle Posting Announcement
            if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_village_announcement'])) {
                $msg = $conn->real_escape_string($_POST['message']);
                $v = $conn->real_escape_string($member['church_village']);
                $conn->query("INSERT INTO village_announcements (village, leader_id, message) VALUES ('$v', $member_id, '$msg')");
                echo "<div style='background:rgba(16,185,129,0.1);color:var(--success);padding:12px 16px;border-radius:8px;margin-bottom:16px;'>Announcement posted to your village!</div>";
            }
            ?>
            
            <!-- Post Announcement Form -->
            <div class="content-card" style="margin-bottom:30px;">
                <h2 style="margin-bottom:15px; color:var(--text-main); font-size:1.1rem;">Post Announcement to <?= htmlspecialchars($member['church_village']) ?></h2>
                <form method="POST" action="?tab=manage_church_village">
                    <input type="hidden" name="post_village_announcement" value="1">
                    <textarea name="message" required rows="3" style="width:100%; padding:12px; border:1px solid var(--border-color); border-radius:8px; background:var(--bg-lighter); color:var(--text-main); margin-bottom:16px; font-family:inherit; resize:vertical;" placeholder="Write a message or announcement for your village members..."></textarea>
                    <button type="submit" class="btn-primary" style="padding:10px 20px;">Post Announcement</button>
                </form>
            </div>

            <!-- Members List -->
            <!-- Members Card Grid -->
            <!-- Members Table Grid -->
            <div class="content-card">
                <?php
                $v = $conn->real_escape_string($member['church_village']);
                
                // First get the leader explicitly for the horizontal badge
                $leader = null;
                $lq = $conn->query("SELECT first_name, last_name, profile_picture FROM members WHERE is_approved = 1 AND church_village = '$v' AND is_village_leader = 1 LIMIT 1");
                if ($lq && $lq->num_rows > 0) $leader = $lq->fetch_assoc();
                ?>
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 20px; flex-wrap:wrap; gap:12px;">
                    <div style="display:flex;align-items:center;gap:14px; flex-wrap:wrap;">
                        <h2 style="margin:0; color:var(--text-main); font-size:1.1rem;">
                            <?= htmlspecialchars($member['church_village']) ?> Village
                        </h2>
                        <?php if ($leader): 
                            $l_pic = !empty($leader['profile_picture']) ? 'uploads/'.htmlspecialchars($leader['profile_picture']) : 'uploads/default_avatar.png';
                        ?>
                        <div style="display:flex;align-items:center;gap:8px;padding:6px 12px;border:1px solid var(--border-color);border-radius:20px;background:var(--bg-main);">
                            <img src="<?= $l_pic ?>" style="width:28px;height:28px;border-radius:50%;object-fit:cover;" onerror="this.src='uploads/default_avatar.png'">
                            <span style="font-size:0.82rem;font-weight:600;">Village Leader: <?= htmlspecialchars(ucfirst($leader['first_name']) . ' ' . ucfirst($leader['last_name'])) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <a href="print_village_members.php?village=<?= urlencode($member['church_village']) ?>" target="_blank"
                       style="display:inline-flex; align-items:center; gap:6px; padding:8px 16px; background:#10b981; color:white; text-decoration:none; border-radius:8px; font-size:0.85rem; font-weight:600;">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Print Official List
                    </a>
                </div>

                <?php
                $v_mems = $conn->query("
                    SELECT id, first_name, last_name, phone, department, church_role, profile_picture, is_village_leader, address,
                    CASE
                        WHEN is_village_leader = 1 THEN 1
                        WHEN church_role IS NOT NULL AND church_role != '' AND LOWER(church_role) != 'member' THEN 2
                        ELSE 99
                    END AS sort_rank
                    FROM members
                    WHERE is_approved = 1 AND church_village = '$v'
                    ORDER BY sort_rank ASC, first_name ASC
                ");

                if ($v_mems && $v_mems->num_rows > 0):
                ?>
                <div class="table-responsive">
                    <table class="print-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Photo</th>
                                <th>Full Name</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Residence</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $i = 1;
                            while($vm = $v_mems->fetch_assoc()):
                                $rank = (int)$vm['sort_rank'];
                                $row_bg = $rank == 1 ? 'background:rgba(245,158,11,0.05);font-weight:700;'
                                        : ($rank == 2 ? 'background:rgba(16,185,129,0.05);font-weight:600;'
                                        : '');
                                
                                $pic = !empty($vm['profile_picture']) ? 'uploads/' . htmlspecialchars($vm['profile_picture']) : 'uploads/default_avatar.png';
                                $role = $rank == 1 ? 'Village Leader' : (!empty($vm['church_role']) ? htmlspecialchars($vm['church_role']) : htmlspecialchars($vm['department'] ?? 'Member'));
                            ?>
                            <tr style="<?= $row_bg ?>">
                                <td><?= $i++ ?></td>
                                <td><img src="<?= $pic ?>" style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:1px solid #ccc;cursor:zoom-in;" onerror="this.src='uploads/default_avatar.png'"></td>
                                <td><?= htmlspecialchars(ucfirst($vm['first_name']) . ' ' . ucfirst($vm['last_name'])) ?></td>
                                <td><?= htmlspecialchars($vm['phone'] ?? '-') ?></td>
                                <td><?= $role ?></td>
                                <td><?= htmlspecialchars($vm['address'] ?? '-') ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div style="text-align:center; padding:50px 20px; color:var(--text-muted);">
                    <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto 12px; display:block; opacity:0.3;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <p style="margin:0; font-size:0.95rem;">No members found in this village yet.</p>
                </div>
                <?php endif; ?>
            </div>

            <style>
                @media print {
                    body * { visibility: hidden; }
                    .print-table, .print-table * { visibility: visible; }
                    .print-table { position: absolute; left: 0; top: 0; width: 100%; border-collapse: collapse; }
                    .print-table th, .print-table td { border: 1px solid #000; padding: 8px; text-align: left; }
                }
            </style>
            </div>
        <?php elseif ($tab == 'my_worship_tasks'): ?>
            <div class="page-header">
                <h1>🎵 My Worship Tasks</h1>
                <p>View tasks assigned to you by the Worship Leader. Mark them as done when completed.</p>
            </div>
            <?php render_worship_leader_profiles_card($conn); ?>

            <?php if (isset($_GET['success'])): ?>
            <div style="background:rgba(16,185,129,0.12); border:1px solid rgba(16,185,129,0.4); color:#065f46; padding:14px 18px; border-radius:10px; margin-bottom:20px; font-weight:500;">
                ✅ <?= htmlspecialchars($_GET['success']) ?>
            </div>
            <?php endif; ?>

            <?php
            // Stats
            $wt_pending = $conn->query("SELECT COUNT(*) as c FROM worship_tasks WHERE member_id=$member_id AND status='Pending'")->fetch_assoc()['c'];
            $wt_done    = $conn->query("SELECT COUNT(*) as c FROM worship_tasks WHERE member_id=$member_id AND status='Done'")->fetch_assoc()['c'];
            ?>

            <div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:24px;">
                <div class="stat-card" style="flex:1; min-width:180px;">
                    <h3>Pending Tasks</h3>
                    <div class="value" style="color:var(--warning);"><?= $wt_pending ?></div>
                </div>
                <div class="stat-card" style="flex:1; min-width:180px;">
                    <h3>Completed Tasks</h3>
                    <div class="value" style="color:var(--success);"><?= $wt_done ?></div>
                </div>
            </div>

            <!-- Pending Tasks -->
            <div class="content-card" style="margin-bottom:24px;">
                <h2 style="display:flex; align-items:center; gap:10px; margin-bottom:20px;">
                    <span style="background:rgba(245,158,11,0.15); color:var(--warning); padding:6px 12px; border-radius:8px; font-size:0.9rem;">Pending</span>
                    Tasks Awaiting Your Action
                </h2>
                <?php
                $pending_tasks_q = $conn->query("SELECT wt.*, m.first_name as a_fn, m.last_name as a_ln
                                                  FROM worship_tasks wt
                                                  JOIN members m ON wt.assigned_by = m.id
                                                  WHERE wt.member_id=$member_id AND wt.status='Pending'
                                                  ORDER BY wt.assigned_at DESC");
                if ($pending_tasks_q && $pending_tasks_q->num_rows > 0):
                ?>
                <div style="display:flex; flex-direction:column; gap:14px;">
                    <?php while($pt = $pending_tasks_q->fetch_assoc()): ?>
                    <div style="background:var(--bg-lighter); border:1px solid var(--border-color); border-left:4px solid var(--warning); border-radius:10px; padding:16px 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                        <div>
                            <div style="font-weight:600; font-size:1rem; color:var(--text-main); margin-bottom:4px;"><?= htmlspecialchars($pt['task_name']) ?></div>
                            <div style="font-size:0.8rem; color:var(--text-muted);">
                                Assigned by <strong><?= htmlspecialchars($pt['a_fn'] . ' ' . $pt['a_ln']) ?></strong>
                                &bull; <?= date('M j, Y', strtotime($pt['assigned_at'])) ?>
                            </div>
                        </div>
                        <form method="POST" onsubmit="return confirm('Mark this task as Done?')">
                            <input type="hidden" name="task_id" value="<?= $pt['id'] ?>">
                            <button type="submit" name="mark_task_done" style="background:linear-gradient(135deg,#10b981,#059669); color:white; border:none; padding:10px 22px; border-radius:8px; cursor:pointer; font-weight:600; font-size:0.9rem; display:flex; align-items:center; gap:8px;">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                Mark Done
                            </button>
                        </form>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <p style="color:var(--text-muted); text-align:center; padding:20px 0;">🎉 No pending tasks. You are all caught up!</p>
                <?php endif; ?>
            </div>

            <!-- Completed Tasks -->
            <div class="content-card">
                <h2 style="display:flex; align-items:center; gap:10px; margin-bottom:20px;">
                    <span style="background:rgba(16,185,129,0.15); color:var(--success); padding:6px 12px; border-radius:8px; font-size:0.9rem;">Done</span>
                    Completed Tasks
                </h2>
                <?php
                $done_tasks_q = $conn->query("SELECT wt.*, m.first_name as a_fn, m.last_name as a_ln
                                               FROM worship_tasks wt
                                               JOIN members m ON wt.assigned_by = m.id
                                               WHERE wt.member_id=$member_id AND wt.status='Done'
                                               ORDER BY wt.assigned_at DESC LIMIT 20");
                if ($done_tasks_q && $done_tasks_q->num_rows > 0):
                ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Assigned By</th>
                                <th>Assigned On</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while($dt = $done_tasks_q->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($dt['task_name']) ?></td>
                            <td><?= htmlspecialchars($dt['a_fn'] . ' ' . $dt['a_ln']) ?></td>
                            <td><?= date('M j, Y', strtotime($dt['assigned_at'])) ?></td>
                            <td><span class="badge" style="background:rgba(16,185,129,0.15); color:var(--success);">✅ Done</span></td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p style="color:var(--text-muted); text-align:center; padding:20px 0;">No completed tasks yet.</p>
                <?php endif; ?>
            </div>

        <?php endif; ?>

        <?php require_once 'member_building_panels.php'; ?>

        </div>
    </div>

    <script>
    const themeToggle = document.getElementById('themeToggle');
    const moonIcon = document.getElementById('moonIcon');
    const sunIcon = document.getElementById('sunIcon');
    const htmlEl = document.documentElement;

    // Load saved theme
    if (localStorage.getItem('theme') === 'dark') {
        htmlEl.setAttribute('data-theme', 'dark');
        moonIcon.style.display = 'none';
        sunIcon.style.display = 'block';
    }

    themeToggle.addEventListener('click', () => {
        if (htmlEl.getAttribute('data-theme') === 'dark') {
            htmlEl.removeAttribute('data-theme');
            localStorage.setItem('theme', 'light');
            moonIcon.style.display = 'block';
            sunIcon.style.display = 'none';
        } else {
            htmlEl.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
            moonIcon.style.display = 'none';
            sunIcon.style.display = 'block';
        }
    });

    // Prayer Countdown Timer
    function updateCountdowns() {
        document.querySelectorAll('.prayer-countdown').forEach(el => {
            const targetStr = el.getAttribute('data-target');
            const endStr = el.getAttribute('data-end-target');
            if (!targetStr) return;

            // Parse target time (assuming Kenya time +0300)
            const targetTime = new Date(targetStr.replace(' ', 'T') + '+03:00').getTime();
            const now = new Date().getTime();
            const diff = targetTime - now;

            if (diff <= 0) {
                // It has started. Check if it has ended.
                if (endStr) {
                    const endTime = new Date(endStr.replace(' ', 'T') + '+03:00').getTime();
                    if (now >= endTime) {
                        el.innerHTML = '<span style="color:#6b7280;">🏁 Ended</span>';
                        el.style.background = 'rgba(107,114,128,0.1)';
                        return;
                    } else {
                        const remainDiff = endTime - now;
                        const h = Math.floor(remainDiff / (1000 * 60 * 60));
                        const m = Math.floor((remainDiff % (1000 * 60 * 60)) / (1000 * 60));
                        let remainStr = '';
                        if (h > 0) remainStr += h + 'h ';
                        remainStr += m + 'm';
                        el.innerHTML = `<span style="color:#10b981;">🟢 Live Now (Ends in ${remainStr})</span>`;
                        el.style.background = 'rgba(16,185,129,0.1)';
                        return;
                    }
                }
                el.innerHTML = '<span style="color:#10b981;">🟢 Live Now</span>';
                el.style.background = 'rgba(16,185,129,0.1)';
            } else {
                const h = Math.floor(diff / (1000 * 60 * 60));
                const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const s = Math.floor((diff % (1000 * 60)) / 1000);

                let timeStr = '';
                if (h > 0) timeStr += h.toString().padStart(2, '0') + 'h ';
                timeStr += m.toString().padStart(2, '0') + 'm ';
                timeStr += s.toString().padStart(2, '0') + 's';

                el.innerHTML = '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:4px;margin-top:-2px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Starts in: ' + timeStr;
            }
        });
    }

    if (document.querySelector('.prayer-countdown')) {
        updateCountdowns();
        setInterval(updateCountdowns, 1000);
    }

    document.querySelectorAll('.camera-upload-trigger').forEach(trigger => {
        trigger.addEventListener('click', event => {
            event.preventDefault();
            const input = document.getElementById(trigger.dataset.input);
            if (input) {
                input.click();
            }
        });
    });

    document.querySelectorAll('input[name="announcement_camera"], input[name="announcement_upload"]').forEach(input => {
        input.addEventListener('change', () => {
            const form = input.closest('form');
            if (form && input.files && input.files.length) {
                form.querySelectorAll('input[name="announcement_camera"], input[name="announcement_upload"]').forEach(other => {
                    if (other !== input) {
                        other.value = '';
                    }
                });
            }
            const label = Array.from(document.querySelectorAll('.selected-upload-name')).find(item => {
                return (item.dataset.for || ').split(',').includes(input.id);
            });
            if (label) {
                label.textContent = input.files && input.files.length ? `Selected: ${input.files[0].name}` : '';
            }
        });
    });
    </script>
    <script>
        window.tabNotificationBadges = <?= json_encode($tab_badges) ?>;
    </script>
    <script src="script.js"></script>
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
function togglePasswordField(fieldId) {
    const input = document.getElementById(fieldId);
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
}
document.querySelectorAll('.member-username-check').forEach(function(input) {
    const status = input.parentElement.querySelector('.username-live-status');
    const submit = document.getElementById(input.dataset.submitId || ');
    let timer = null;

    async function checkMemberUsername() {
        const value = input.value.trim();
        const current = (input.dataset.currentUsername || ').trim();
        if (!value) {
            if (status) {
                status.textContent = 'Type a username to check if it is available.';
                status.style.color = 'var(--text-muted)';
            }
            if (submit) submit.disabled = false;
            return;
        }
        if (!/^[a-zA-Z0-9_.]+$/.test(value)) {
            if (status) {
                status.textContent = 'Username may only contain letters, numbers, dots and underscores.';
                status.style.color = 'var(--danger)';
            }
            if (submit) submit.disabled = true;
            return;
        }
        if (value.length < 3) {
            if (status) {
                status.textContent = 'Username must be at least 3 characters.';
                status.style.color = 'var(--danger)';
            }
            if (submit) submit.disabled = true;
            return;
        }
        if (current && value === current) {
            if (status) {
                status.textContent = 'This is your current username.';
                status.style.color = 'var(--text-muted)';
            }
            if (submit) submit.disabled = false;
            return;
        }
        if (status) {
            status.textContent = 'Checking username...';
            status.style.color = 'var(--text-muted)';
        }
        try {
            const response = await fetch('check_username.php?username=' + encodeURIComponent(value), { cache: 'no-store' });
            const data = await response.json();
            if (data.exists) {
                if (status) {
                    status.textContent = 'This username is used. Try a different username.';
                    status.style.color = 'var(--danger)';
                }
                if (submit) submit.disabled = true;
            } else if (data.error) {
                if (status) {
                    status.textContent = data.error;
                    status.style.color = 'var(--danger)';
                }
                if (submit) submit.disabled = true;
            } else {
                if (status) {
                    status.textContent = 'This username is unique and available for login.';
                    status.style.color = 'var(--success)';
                }
                if (submit) submit.disabled = false;
            }
        } catch (error) {
            if (status) {
                status.textContent = 'Could not check username now. The system will check again when you save.';
                status.style.color = 'var(--danger)';
            }
            if (submit) submit.disabled = false;
        }
    }

    input.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(checkMemberUsername, 300);
    });
    checkMemberUsername();
});
// Close on click outside image
document.getElementById('imageViewerModal').addEventListener('click', function(e) {
    if (e.target === this) {
        this.style.display = 'none';
    }
});
</script>
</body>
</html>


























