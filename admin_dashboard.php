<?php
session_start();
require_once 'db_connect.php';
require_once 'notification_badges.php';
require_once 'role_departments.php';
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
$admin_id = $_SESSION['admin_id'];
$tab_badges = build_tab_notification_badges($conn, $admin_id, 'admin', $tab);
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

function render_announcement_image($image_path) {
    if (empty($image_path)) {
        return '';
    }
    $safe_path = htmlspecialchars($image_path);
    return '<a href="' . $safe_path . '" target="_blank" style="display:block;margin-top:12px;">'
        . '<img src="' . $safe_path . '" alt="Announcement image" style="width:100%;max-height:360px;object-fit:cover;border-radius:10px;border:1px solid var(--border-color);">'
        . '</a>';
}

$total_members = $conn->query("SELECT COUNT(*) as count FROM members WHERE is_approved = 1")->fetch_assoc()['count'];
$total_pastors = $conn->query("SELECT COUNT(*) as count FROM pastors")->fetch_assoc()['count'];
$pending_pastors = $conn->query("SELECT COUNT(*) as count FROM pastors WHERE is_approved = 0")->fetch_assoc()['count'];

$members = $conn->query("SELECT * FROM members ORDER BY reg_date DESC");
$pastors = $conn->query("SELECT * FROM pastors ORDER BY is_approved ASC, reg_date DESC");
$audit_logs = $conn->query("SELECT * FROM audit_logs ORDER BY created_at DESC");

// Church Content
$all_daily_messages = $conn->query("SELECT dm.*, p.first_name, p.last_name FROM daily_messages dm JOIN pastors p ON dm.pastor_id = p.id ORDER BY dm.created_at DESC");
$all_highlights = $conn->query("SELECT ch.*, p.first_name, p.last_name FROM church_highlights ch JOIN pastors p ON ch.pastor_id = p.id ORDER BY ch.created_at DESC");

// Handle Register Member
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_member'])) {
    $fn = $conn->real_escape_string(trim($_POST['first_name']));
    $ln = $conn->real_escape_string(trim($_POST['last_name']));
    $ph = $conn->real_escape_string(trim($_POST['phone']));
    $ad = $conn->real_escape_string(trim($_POST['address'] ?? ''));
    $dp = $conn->real_escape_string(trim($_POST['department'] ?? 'None'));
    $gn = $conn->real_escape_string(trim($_POST['gender'] ?? 'Male'));
    $pw = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
    if (empty($fn) || empty($ln) || empty($ph) || empty($_POST['password'])) {
        header("Location: admin_dashboard.php?tab=members&error=First name, last name, phone and password are required");
        exit();
    }
    $dup = $conn->query("SELECT id FROM members WHERE phone = '$ph'")->num_rows;
    if ($dup > 0) {
        header("Location: admin_dashboard.php?tab=members&error=A member with that phone already exists");
        exit();
    }
    $conn->query("INSERT INTO members (first_name, last_name, phone, address, department, gender, password, is_approved, reg_date) VALUES ('$fn','$ln','$ph','$ad','$dp','$gn','$pw',1,NOW())");
    $new_id = $conn->insert_id;
    $welcome_msg = $conn->real_escape_string("Welcome to Munyari Church, $fn $ln! Your account has been created and approved by the Admin.");
    $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($new_id, 'member', '$welcome_msg')");
    header("Location: admin_dashboard.php?tab=members&success=Member $fn $ln registered successfully");
    exit();
}

// Handle Delete Member
if (isset($_GET['action']) && $_GET['action'] == 'delete_member' && isset($_GET['id'])) {
    $del_id = (int)$_GET['id'];
    $conn->query("DELETE FROM members WHERE id = $del_id");
    header("Location: admin_dashboard.php?tab=members&success=Member deleted successfully");
    exit();
}

// Assign Role Actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'assign_role' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $member_id = (int)$_POST['member_id'];
        $raw_role = trim($_POST['role'] ?? '');
        $assignment_department = trim($_POST['assignment_department'] ?? '');
        $role = $conn->real_escape_string($raw_role);
        
        $allowed_departments = role_assignment_departments($raw_role, $assignment_department);

        // Roles that can be held by multiple people at the same time
        $multi_assignment_roles = ['head usher', 'usher'];
        $is_multi_role = in_array(strtolower(trim($raw_role)), $multi_assignment_roles);

        if (!$is_multi_role) {
            if (is_subsidiary_department_role($raw_role) && !empty($assignment_department)) {
                $department_filter = department_match_sql($conn, 'department', $assignment_department);
                $check = $conn->query("SELECT id FROM members WHERE church_role = '$role' AND $department_filter AND id != $member_id");
            } else {
                $check = $conn->query("SELECT id FROM members WHERE church_role = '$role' AND id != $member_id");
            }
            if ($check->num_rows > 0) {
                $scope_text = is_subsidiary_department_role($raw_role) && !empty($assignment_department) ? " in $assignment_department" : "";
                header("Location: admin_dashboard.php?tab=assign_roles&error=" . urlencode("This role is already assigned to another member$scope_text"));
                exit();
            }
        }

        $member_data = $conn->query("SELECT department FROM members WHERE id = $member_id AND is_approved = 1")->fetch_assoc();
        $member_department = $member_data['department'] ?? '';
        $can_receive = $member_data && (empty($allowed_departments) || array_filter($allowed_departments, function($department) use ($member_department) {
            return department_matches($member_department, $department);
        }));
        if (!$can_receive) {
            header("Location: admin_dashboard.php?tab=assign_roles&error=This role can only be assigned to members from the allowed department(s)");
            exit();
        }
        
        $existing_member_data = $conn->query("SELECT church_role, department FROM members WHERE id = $member_id AND is_approved = 1")->fetch_assoc();
        $existing_role = trim($existing_member_data['church_role'] ?? '');
        $member_department = $existing_member_data['department'] ?? '';
        
        $role_to_set = $role;
        $dept_sql = "";
        
        if (!empty($existing_role) && normalize_role_name($existing_role) !== 'member') {
            $existing_roles_array = array_filter(array_map('trim', explode(',', str_replace('&', ',', $existing_role))));
            $new_normalized = normalize_role_name($raw_role);
            
            $already_has = false;
            foreach ($existing_roles_array as $r) {
                if (normalize_role_name($r) === $new_normalized) {
                    $already_has = true;
                }
            }
            
            if (!$already_has) {
                $role_to_set = $conn->real_escape_string($existing_role . ', ' . $raw_role);
                if (empty($member_department) || $member_department === 'None') {
                    $dept_sql = role_department_sql($conn, $raw_role);
                }
            } else {
                $dept_sql = role_department_sql($conn, $raw_role);
            }
        } else {
            $dept_sql = role_department_sql($conn, $raw_role);
        }
        
        $conn->query("UPDATE members SET church_role = '$role_to_set'$dept_sql WHERE id = $member_id");
        $scope_text = !empty($assignment_department) && is_subsidiary_department_role($raw_role) ? " under $assignment_department" : "";
        $msg = $conn->real_escape_string("You have been assigned a new role: $raw_role$scope_text by Admin.");
        $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($member_id, 'member', '$msg')");
        
        header("Location: admin_dashboard.php?tab=assign_roles&success=Role assigned successfully");
        exit();
    }
    
    elseif ($action === 'remove_role' && isset($_GET['id'])) {
        $member_id = (int)$_GET['id'];
        $role_to_remove = trim($_GET['role'] ?? '');
        $member_data = $conn->query("SELECT church_role FROM members WHERE id = $member_id")->fetch_assoc();
        $old_role = $member_data['church_role'] ?? '';
        
        if (!empty($role_to_remove)) {
            $roles_array = array_filter(array_map('trim', explode(',', str_replace('&', ',', $old_role))));
            $new_roles = [];
            foreach ($roles_array as $r) {
                if (strtolower($r) !== strtolower($role_to_remove)) {
                    $new_roles[] = $r;
                }
            }
            if (empty($new_roles)) {
                $conn->query("UPDATE members SET church_role = 'Member' WHERE id = $member_id");
            } else {
                $combined = $conn->real_escape_string(implode(', ', $new_roles));
                $conn->query("UPDATE members SET church_role = '$combined' WHERE id = $member_id");
            }
            $msg = "Your role ($role_to_remove) has been removed by Admin.";
        } else {
            $conn->query("UPDATE members SET church_role = 'Member' WHERE id = $member_id");
            $msg = "Your roles ($old_role) have been removed by Admin.";
        }
        $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($member_id, 'member', '$msg')");
        
        header("Location: admin_dashboard.php?tab=assign_roles&success=Role removed from member");
        exit();
    }
    
    elseif ($action === 'create_role' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $new_role = $conn->real_escape_string(trim($_POST['new_role']));
        if (!empty($new_role)) {
            $conn->query("INSERT IGNORE INTO church_roles (role_name) VALUES ('$new_role')");
        }
        header("Location: admin_dashboard.php?tab=assign_roles&success=New custom role created");
        exit();
    }
    
    elseif ($action === 'delete_role' && isset($_GET['role_id'])) {
        $role_id = (int)$_GET['role_id'];
        $conn->query("DELETE FROM church_roles WHERE id = $role_id");
        header("Location: admin_dashboard.php?tab=assign_roles&success=Role removed successfully");
        exit();
    }
    
    elseif ($action === 'approve_appointment' && isset($_GET['id'])) {
        $member_id = (int)$_GET['id'];
        $member_data = $conn->query("SELECT pending_role, first_name, last_name FROM members WHERE id = $member_id")->fetch_assoc();
        
        if ($member_data && !empty($member_data['pending_role'])) {
            $new_role = $member_data['pending_role'];
            $dept_sql = role_department_sql($conn, $new_role);
            $conn->query("UPDATE members SET church_role = '$new_role', pending_role = NULL$dept_sql WHERE id = $member_id");
            
            $msg = "Your appointment to the role of $new_role has been approved by Admin.";
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($member_id, 'member', '$msg')");
            
            header("Location: admin_dashboard.php?tab=assign_roles&success=Appointment approved successfully");
            exit();
        }
        header("Location: admin_dashboard.php?tab=assign_roles&error=Appointment not found");
        exit();
    }
    
    elseif ($action === 'reject_appointment' && isset($_GET['id'])) {
        $member_id = (int)$_GET['id'];
        $member_data = $conn->query("SELECT pending_role FROM members WHERE id = $member_id")->fetch_assoc();
        
        if ($member_data && !empty($member_data['pending_role'])) {
            $conn->query("UPDATE members SET pending_role = NULL WHERE id = $member_id");
            header("Location: admin_dashboard.php?tab=assign_roles&success=Appointment rejected");
            exit();
        }
        header("Location: admin_dashboard.php?tab=assign_roles&error=Appointment not found");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Munyari Church</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard-layout">
        <div class="sidebar">
            <div class="sidebar-brand">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                Munyari Admin
            </div>
            <div class="sidebar-nav">
                <a href="?tab=dashboard" class="sidebar-link <?= $tab == 'dashboard' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Dashboard Overview
                </a>
                <a href="?tab=members" class="sidebar-link <?= $tab == 'members' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    Manage Members
                </a>
                <a href="?tab=departments" class="sidebar-link <?= $tab == 'departments' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    Departments
                </a>
                <a href="?tab=pastors" class="sidebar-link <?= $tab == 'pastors' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    Manage Pastors
                </a>
                <a href="?tab=assign_roles" class="sidebar-link <?= $tab == 'assign_roles' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Assign Roles
                </a>
                <a href="?tab=content" class="sidebar-link <?= $tab == 'content' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Church Content
                </a>
                <a href="?tab=organized_events" class="sidebar-link <?= $tab == 'organized_events' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Organised Events
                </a>
                <a href="?tab=general_leadership" class="sidebar-link <?= $tab == 'general_leadership' ? 'active' : '' ?>" style="position:relative;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M9 20H4v-2a3 3 0 015.356-1.857M15 11a4 4 0 10-6 0 4 4 0 006 0zm6-1a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    General Leadership
                    <?php if (!empty($tab_badges['general_leadership'])): ?><span style="background:var(--danger);color:white;font-size:0.65rem;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:auto;"><?= $tab_badges['general_leadership'] ?></span><?php endif; ?>
                </a>
                <a href="?tab=discipline_reports" class="sidebar-link <?= $tab == 'discipline_reports' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path></svg>
                    Discipline & Reports
                </a>
                <a href="?tab=graduation_info" class="sidebar-link <?= $tab == 'graduation_info' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path></svg>
                    Graduation Info
                </a>
                <a href="?tab=prayer_board" class="sidebar-link <?= $tab == 'prayer_board' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                    Prayer Board
                </a>
                <a href="?tab=sport_board" class="sidebar-link <?= $tab == 'sport_board' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Sport Board
                </a>
                <a href="?tab=choir_songs" class="sidebar-link <?= $tab == 'choir_songs' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 18V5l12-2v13"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 18a3 3 0 11-6 0 3 3 0 016 0zm12-2a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Choir Songs
                </a>
                <a href="?tab=desired_roles" class="sidebar-link <?= $tab == 'desired_roles' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Village & Desired Roles
                </a>
                <a href="?tab=manage_worshippers" class="sidebar-link <?= $tab == 'manage_worshippers' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Manage Worshippers
                </a>
                <a href="?tab=usher_monitoring" class="sidebar-link <?= $tab == 'usher_monitoring' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    Usher Monitoring
                </a>
                <a href="?tab=audit" class="sidebar-link <?= $tab == 'audit' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Audit Trail
                </a>
                <a href="?tab=settings" class="sidebar-link <?= $tab == 'settings' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    System Settings
                </a>
            </div>
        </div>

        <div class="main-content">
            <div class="topbar" style="justify-content: flex-end;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <!-- Profile photo + name -->
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <a href="?tab=settings" title="Go to Settings" style="text-decoration: none;">
                            <img src="uploads/default_avatar.png" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary); transition: opacity 0.2s;" onmouseover="this.style.opacity=0.8" onmouseout="this.style.opacity=1">
                        </a>
                        <div style="line-height: 1.2;">
                            <span style="font-weight: 600; font-size: 0.95rem; color: var(--text-main); display: block;">Admin</span>
                            <span style="font-size: 0.75rem; color: var(--text-muted); background: var(--border-color); padding: 2px 6px; border-radius: 4px;">System</span>
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
                </div>
            </div>
            
            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
            <?php elseif(isset($_GET['error'])): ?>
                <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
            <?php elseif(isset($_GET['approved'])): ?>
                <div class="alert alert-success">Account approved successfully!</div>
            <?php elseif(isset($_GET['deactivated'])): ?>
                <div class="alert alert-error" style="background: rgba(245, 158, 11, 0.1); color: #d97706; border-color: rgba(245, 158, 11, 0.2);">Account deactivated successfully.</div>
            <?php elseif(isset($_GET['removed'])): ?>
                <div class="alert alert-error">Account removed permanently.</div>
            <?php endif; ?>

            <?php if ($tab == 'dashboard'): ?>
                <div class="page-header">
                    <h1>Dashboard Overview</h1>
                    <p>Welcome back! Here is what's happening today.</p>
                </div>
                
                <div class="dashboard-grid">
                    <div class="stat-card">
                        <h3>Approved Members</h3>
                        <div class="value" style="color: var(--primary);"><?= $total_members ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Active Pastors</h3>
                        <div class="value" style="color: var(--success);"><?= $total_pastors ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Pending Pastors</h3>
                        <div class="value" style="color: var(--danger);"><?= $pending_pastors ?></div>
                    </div>
                </div>
                
                <div class="content-card">
                    <h2>Quick Actions</h2>
                    <a href="register_pastor.php" class="btn" style="width: auto; display: inline-block; padding: 12px 24px;">Register Pastor Directly</a>
                </div>

            <?php elseif ($tab == 'members'): ?>
                <div class="page-header">
                    <h1>Manage Members</h1>
                    <p>Register new congregation members or remove existing ones.</p>
                </div>

                <!-- Register New Member Form -->
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
                    <form method="POST" action="?tab=members" id="adminRegForm" onsubmit="this.querySelector('button[type=submit]').disabled=true;">
                        <input type="hidden" name="register_member" value="1">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group">
                                <label>First Name <span style="color:var(--danger);">*</span></label>
                                <input type="text" name="first_name" class="form-control" placeholder="e.g. John" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name <span style="color:var(--danger);">*</span></label>
                                <input type="text" name="last_name" class="form-control" placeholder="e.g. Doe" required>
                            </div>
                            <div class="form-group">
                                <label>Phone Number <span style="color:var(--danger);">*</span></label>
                                <input type="tel" name="phone" class="form-control" placeholder="e.g. 0771234567" required>
                            </div>
                            <div class="form-group">
                                <label>Gender <span style="color:var(--danger);">*</span></label>
                                <select name="gender" class="form-control" required>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Department</label>
                                <select name="department" class="form-control">
                                    <option value="Youths">Youths</option>
                                    <option value="Elders">Elders</option>
                                    <option value="Sunday School">Sunday School</option>
                                    <option value="Womens Ministry">Women's Ministry</option>
                                </select>
                            </div>
                            <div class="form-group" style="grid-column:1/-1;">
                                <label>Residential Address</label>
                                <input type="text" name="address" class="form-control" placeholder="e.g. 23 Borrowdale Road, Harare">
                            </div>
                            <div class="form-group">
                                <label>Login Password <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <input type="password" name="password" id="adminRegPwd" class="form-control" placeholder="Set a secure login password" required style="padding-right:46px;">
                                    <span onclick="var i=document.getElementById('adminRegPwd');i.type=i.type==='password'?'text':'password'" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--text-muted);">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Confirm Password <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <input type="password" name="confirm_password" id="adminRegPwdConfirm" class="form-control" placeholder="Re-enter password" required style="padding-right:46px;" oninput="checkAdminPwd()">
                                    <span onclick="var i=document.getElementById('adminRegPwdConfirm');i.type=i.type==='password'?'text':'password'" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--text-muted);">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </span>
                                </div>
                                <small id="adminPwdMatchHint" style="font-size:0.8rem;margin-top:4px;display:none;"></small>
                            </div>
                        </div>
                        <div style="margin-top:20px; display:flex; gap:12px; align-items:center;">
                            <button type="submit" class="btn-submit" style="width:auto;padding:12px 32px;">✅ Register Member</button>
                            <button type="reset" style="background:none;border:1px solid var(--border-color);color:var(--text-muted);padding:11px 20px;border-radius:10px;cursor:pointer;font-size:0.9rem;">Clear Form</button>
                        </div>
                    </form>
                    <script>
                    function checkAdminPwd() {
                        var p1 = document.getElementById('adminRegPwd').value;
                        var p2 = document.getElementById('adminRegPwdConfirm').value;
                        var hint = document.getElementById('adminPwdMatchHint');
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
                    document.getElementById('adminRegForm').addEventListener('submit', function(e) {
                        var p1 = document.getElementById('adminRegPwd').value;
                        var p2 = document.getElementById('adminRegPwdConfirm').value;
                        if (p1 !== p2) {
                            e.preventDefault();
                            alert('Passwords do not match. Please check and try again.');
                        }
                    });
                    </script>
                </div>

                <!-- Members Table -->
                <div class="content-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0;">All Members</h2>
                        <span class="badge" style="background: var(--primary); color: white; font-size: 14px; padding: 5px 12px;">Total: <?= $members->num_rows ?></span>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr><th>Profile</th><th>Name</th><th>Phone</th><th>Area</th><th>Department</th><th>Role</th><th>Status</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php while($m = $members->fetch_assoc()): ?>
                                <tr>
                                    <td><img src="uploads/<?= htmlspecialchars($m['profile_picture'] ?? 'default_avatar.png') ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color);"></td>
                                    <td style="font-weight: 500;"><?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?></td>
                                    <td><?= htmlspecialchars($m['phone']) ?></td>
                                    <td><?= htmlspecialchars($m['address']) ?></td>
                                    <td><span class="badge" style="background:var(--border-color);color:var(--text-main);"><?= htmlspecialchars($m['department'] ?? 'General Church') ?></span></td>
                                    <td><span class="badge" style="background: var(--border-color); color: var(--text-main);"><?= htmlspecialchars($m['church_role'] ?? 'Member') ?></span></td>
                                    <td><span class="badge <?= $m['is_approved'] ? 'approved' : 'pending' ?>"><?= $m['is_approved'] ? 'Active' : 'Pending' ?></span></td>
                                    <td>
                                        <a href="?tab=members&action=delete_member&id=<?= $m['id'] ?>" onclick="return confirm('Permanently delete this member? This cannot be undone.');" class="btn-sm" style="background: var(--danger); color: white; text-decoration:none;">Delete</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($tab == 'departments'): ?>
                <div class="page-header">
                    <h1>Church Departments</h1>
                    <p>Overview of active departments and their members.</p>
                </div>
                
                <?php
                // Fetch department counts
                $dept_counts = [];
                $depts_result = $conn->query("SELECT department, COUNT(*) as count FROM members WHERE is_approved=1 GROUP BY department");
                while ($row = $depts_result->fetch_assoc()) {
                    $dept_counts[$row['department']] = $row['count'];
                }
                $departments = ['Youths', 'Elders', 'Sunday School', 'Womens Ministry'];
                ?>
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
                    $dept_members = $conn->query("SELECT * FROM members WHERE is_approved=1 AND department='$dept_esc' ORDER BY first_name ASC");
                    if ($dept_members->num_rows > 0):
                    ?>
                        <div class="content-card" style="margin-top: 30px;">
                            <h2 style="margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;"><?= htmlspecialchars($dept) ?></h2>
                            <div class="table-responsive">
                                <table>
                                    <thead><tr><th>Name</th><th>Phone</th><th>Role</th></tr></thead>
                                    <tbody>
                                        <?php while($dm = $dept_members->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($dm['first_name'] . ' ' . $dm['last_name']) ?></td>
                                                <td><?= htmlspecialchars($dm['phone']) ?></td>
                                                <td><?= htmlspecialchars($dm['church_role'] ?? 'Member') ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

            <?php elseif ($tab == 'pastors'): ?>
                <div class="page-header">
                    <h1>Pastor Management</h1>
                    <p>Review and approve pastor registrations.</p>
                </div>
                
                <div class="content-card">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr><th>Name</th><th>Phone</th><th>Role</th><th>Status</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                <?php while($p = $pastors->fetch_assoc()): ?>
                                <tr>
                                    <td style="font-weight: 500;"><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
                                    <td><?= htmlspecialchars($p['phone']) ?></td>
                                    <td><span class="badge" style="background: rgba(37,99,235,0.15); color: var(--primary);"><?= htmlspecialchars($p['role']) ?></span></td>
                                    <td>
                                        <span class="badge <?= $p['is_approved'] ? 'approved' : 'pending' ?>">
                                            <?= $p['is_approved'] ? 'Approved' : 'Pending' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <?php if(!$p['is_approved']): ?>
                                                <a href="admin_action.php?id=<?= $p['id'] ?>&type=pastor&action=approve" class="btn-sm btn-success" style="text-decoration:none;">Approve</a>
                                            <?php else: ?>
                                                <a href="admin_action.php?id=<?= $p['id'] ?>&type=pastor&action=deactivate" class="btn-sm" style="background: var(--warning); color: white; text-decoration:none;">Deactivate</a>
                                            <?php endif; ?>
                                            
                                            <a href="admin_action.php?id=<?= $p['id'] ?>&type=pastor&action=remove" onclick="return confirm('Are you sure you want to permanently remove this pastor?');" class="btn-sm" style="background: var(--danger); color: white; text-decoration:none;">Remove</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($tab == 'assign_roles'): ?>
                <?php 
                // We need $approved_members inside this block since the form uses it.
                $approved_members = $conn->query("SELECT * FROM members WHERE is_approved = 1 ORDER BY first_name ASC, last_name ASC");
                $required_roles = [
                    'General Church Secretary', 'Vice Church Secretary', 'Treasurer', 'Senior Church Elder',
                    'Head Usher', 'Usher', 'Building Chairperson', 'Vice Building Chairperson', 'Building Secretary', 'Vice Building Secretary', 'Building Treasurer',
                    'Youth Chairman', 'Vice Youth Chairman', 'Youth Secretary', 'Vice Youth Secretary', 'Youth Treasurer', 'Mama Youth', 'Baba Youth',
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
                    'General Church' => [
                        'General Church Secretary', 'Vice Church Secretary', 'Treasurer', 'Senior Church Elder'
                    ],
                    'Other Ministry Leaders' => [
                        'Head Usher', 'Usher', 'Building Chairperson', 'Vice Building Chairperson', 'Building Secretary', 'Vice Building Secretary', 'Building Treasurer'
                    ],
                    'Youth Ministry' => array_merge([
                        'Youth Chairman', 'Vice Youth Chairman', 'Youth Secretary', 'Vice Youth Secretary', 'Youth Treasurer', 'Mama Youth', 'Baba Youth'
                    ], $youth_sunday_subsidiary_roles),
                    'Womens Ministry' => array_merge([
                        'Women Chairlady', 'Vice Women Chairlady', 'Women Secretary', 'Vice Women Secretary', 'Women Treasurer'
                    ], $common_subsidiary_roles),
                    'Elders' => array_merge([
                        'Elder Chairman', 'Vice Elder Chairman', 'Elder Secretary', 'Vice Elder Secretary', 'Elder Treasurer'
                    ], $common_subsidiary_roles),
                    'Sunday School' => array_merge([
                        'Sunday School Patron', 'Vice Sunday School Patron', 'Sunday School Secretary', 'Vice Sunday School Secretary', 'Sunday School Treasurer'
                    ], $youth_sunday_subsidiary_roles)
                ];
                $hierarchy_department_names = [
                    'Youth Ministry' => 'Youths',
                    'Womens Ministry' => 'Womens Ministry',
                    'Elders' => 'Elders',
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
                $assigned_lookup_result = $conn->query("SELECT church_role, department FROM members WHERE church_role IS NOT NULL AND church_role != ''");
                if ($assigned_lookup_result) {
                    while ($assigned_lookup_row = $assigned_lookup_result->fetch_assoc()) {
                        $assigned_members_for_availability[] = $assigned_lookup_row;
                        if (!is_subsidiary_department_role($assigned_lookup_row['church_role'] ?? '')) {
                            $assigned_global_roles[normalize_role_name($assigned_lookup_row['church_role'])] = true;
                        }
                    }
                }

                $role_is_available = function($role_name, $context_department = '') use ($assigned_members_for_availability, $assigned_global_roles) {
                    $normalized_role = normalize_role_name($role_name);
                    if (is_subsidiary_department_role($role_name) && !empty($context_department)) {
                        foreach ($assigned_members_for_availability as $assigned_row) {
                            if (
                                normalize_role_name($assigned_row['church_role'] ?? '') === $normalized_role &&
                                department_matches($assigned_row['department'] ?? '', $context_department)
                            ) {
                                return false;
                            }
                        }
                        return true;
                    }

                    return empty($assigned_global_roles[$normalized_role]);
                };
                ?>
                <div class="page-header">
                    <h1>Assign Roles</h1>
                    <p>Appoint approved members to church positions or create new roles.</p>
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
                                        <td style="font-weight: 600; color: var(--text-main);"><?= htmlspecialchars(role_display_label($pr['pending_role'], $pr['department'] ?? null)) ?></td>
                                        <td>
                                            <div style="display: flex; gap: 10px;">
                                                <a href="admin_dashboard.php?tab=assign_roles&action=approve_appointment&id=<?= $pr['id'] ?>" class="btn-action btn-approve" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">Approve</a>
                                                <a href="admin_dashboard.php?tab=assign_roles&action=reject_appointment&id=<?= $pr['id'] ?>" class="btn-action btn-reject" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;" onclick="return confirm('Reject this appointment?');">Reject</a>
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
                    <div class="content-card" style="flex: 1; min-width: 300px;">
                        <h2>Assign to Member</h2>
                        <form method="POST" action="admin_dashboard.php?tab=assign_roles&action=assign_role">
                            <div class="form-group">
                                <label>First Select Role</label>
                                <select name="role" id="adminAssignRoleSelect" class="form-control" required>
                                    <option value="">-- Select Role --</option>
                                    <?php 
                                    foreach ($hierarchy as $dept => $roles) {
                                        $context_department = $hierarchy_department_names[$dept] ?? '';
                                        $options_html = '';
                                        foreach ($roles as $expected_role) {
                                            if ($role_is_available($expected_role, $context_department)) {
                                                $val = htmlspecialchars($expected_role);
                                                $allowed = htmlspecialchars(json_encode(role_assignment_departments($expected_role, $context_department)));
                                                $assignment_department = htmlspecialchars($context_department);
                                                $label = htmlspecialchars($expected_role);
                                                $options_html .= "<option value=\"$val\" data-allowed='$allowed' data-assignment-department=\"$assignment_department\">$label</option>";
                                            }
                                        }
                                        if ($options_html !== '') {
                                            echo "<optgroup label=\"" . htmlspecialchars($dept) . "\">$options_html</optgroup>";
                                        }
                                    }
                                    ?>
                                </select>
                                <input type="hidden" name="assignment_department" id="adminAssignRoleDepartment" value="">
                                <small id="adminAssignRoleHint" style="display:block; margin-top:6px; color: var(--text-muted);">Choose a role first to open the correct members.</small>
                            </div>
                            <div class="form-group">
                                <label>Then Select Member</label>
                                <select name="member_id" id="adminAssignMemberSelect" class="form-control" required disabled>
                                    <option value="">-- Select a role first --</option>
                                    <?php 
                                    if ($approved_members) {
                                        $approved_members->data_seek(0);
                                        while($m = $approved_members->fetch_assoc()): ?>
                                            <option value="<?= $m['id'] ?>" data-department="<?= htmlspecialchars($m['department'] ?? '') ?>">
                                                <?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?> - <?= htmlspecialchars($m['department'] ?? 'No Department') ?> (Current: <?= htmlspecialchars(role_display_label($m['church_role'] ?? 'Member', $m['department'] ?? null)) ?>)
                                            </option>
                                        <?php endwhile; 
                                    } ?>
                                </select>
                            </div>
                            <button type="submit" class="btn-submit">Assign Role</button>
                        </form>
                        <script>
                            (function() {
                                const roleSelect = document.getElementById('adminAssignRoleSelect');
                                const memberSelect = document.getElementById('adminAssignMemberSelect');
                                const departmentInput = document.getElementById('adminAssignRoleDepartment');
                                const hint = document.getElementById('adminAssignRoleHint');
                                if (!roleSelect || !memberSelect) return;

                                const originalOptions = Array.from(memberSelect.querySelectorAll('option[data-department]')).map(option => option.cloneNode(true));
                                const normalize = value => (value || '').toLowerCase().replace(/'/g, '').trim();
                                const aliases = {
                                    'womens ministry': ['womens ministry', 'women ministry', 'women'],
                                    'youths': ['youths', 'youth ministry', 'youth'],
                                    'elders': ['elders', 'elder ministry'],
                                    'sunday school': ['sunday school']
                                };
                                const matchesDepartment = (memberDepartment, allowedDepartment) => {
                                    const memberNorm = normalize(memberDepartment);
                                    const allowedNorm = normalize(allowedDepartment);
                                    const allowedAliases = aliases[allowedNorm] || [allowedNorm];
                                    return allowedAliases.includes(memberNorm);
                                };

                                roleSelect.addEventListener('change', function() {
                                    const selected = roleSelect.options[roleSelect.selectedIndex];
                                    const allowed = selected && selected.dataset.allowed ? JSON.parse(selected.dataset.allowed) : [];
                                    const assignmentDepartment = selected && selected.dataset.assignmentDepartment ? selected.dataset.assignmentDepartment : '';
                                    if (departmentInput) departmentInput.value = assignmentDepartment;
                                    memberSelect.innerHTML = '<option value="">-- Select Member --</option>';

                                    const filtered = originalOptions.filter(option => {
                                        if (!allowed.length) return true;
                                        return allowed.some(department => matchesDepartment(option.dataset.department, department));
                                    });

                                    filtered.forEach(option => memberSelect.appendChild(option.cloneNode(true)));
                                    memberSelect.disabled = !roleSelect.value;
                                    hint.textContent = !roleSelect.value
                                        ? 'Choose a role first to open the correct members.'
                                        : (allowed.length ? 'Showing members from: ' + allowed.join(', ') : 'This role can be assigned to members from the entire church.');
                                });
                            })();
                        </script>
                    </div>
                    
                    <!-- Create Custom Role Form -->
                    <div class="content-card" style="flex: 1; min-width: 300px;">
                        <h2>Manage Roles</h2>
                        <form method="POST" action="admin_dashboard.php?tab=assign_roles&action=create_role" style="margin-bottom: 20px;">
                            <div class="form-group">
                                <label>New Role Name</label>
                                <div style="display:flex; gap:10px;">
                                    <input type="text" name="new_role" class="form-control" placeholder="e.g. Vice Youth Chairman" required style="margin-bottom:0;">
                                    <button type="submit" class="btn" style="background:var(--secondary); color:white; border:none; border-radius:var(--radius-md); font-weight:600; cursor:pointer; padding: 0 20px;">Save</button>
                                </div>
                            </div>
                        </form>
                        
                        <div style="border-top: 1px solid var(--border-color); padding-top: 15px;">
                            <h3 style="font-size: 0.95rem; color: var(--text-muted); margin-bottom: 15px;">All Roles Overview</h3>
                            <?php 
                            // Organize all roles into hierarchy
                            $all_categorized = [];
                            $all_uncategorized = [];
                            while($r = $all_roles_list->fetch_assoc()) {
                                $r_name = trim($r['role_name']);
                                $placed = false;
                                foreach($hierarchy as $dept => $roles) {
                                    foreach($roles as $expected_role) {
                                        if (strtolower($r_name) === strtolower($expected_role)) {
                                            $all_categorized[$dept][$expected_role] = $r;
                                            $placed = true;
                                            break 2;
                                        }
                                    }
                                }
                                if (!$placed) {
                                    $all_uncategorized[] = $r;
                                }
                            }

                            foreach ($hierarchy as $dept => $roles) {
                                if (!empty($all_categorized[$dept])) {
                                    echo "<div style='margin-bottom: 15px;'>";
                                    echo "<div style='font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;'>$dept</div>";
                                    echo "<div style='display: flex; flex-wrap: wrap; gap: 8px;'>";
                                    foreach ($roles as $expected_role) {
                                        if (isset($all_categorized[$dept][$expected_role])) {
                                            $r = $all_categorized[$dept][$expected_role];
                                            echo "<div style='background: var(--bg-main); border: 1px solid var(--border-color); padding: 4px 10px; border-radius: 20px; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: var(--text-main);'>";
                                        echo htmlspecialchars(role_display_label($r['role_name']));
                                            echo "<a href='admin_dashboard.php?tab=assign_roles&action=delete_role&role_id=" . $r['id'] . "' onclick=\"return confirm('Are you sure you want to remove this role from the system?');\" style='color: var(--danger); text-decoration: none; font-weight: bold; font-size: 1rem; line-height: 1;'>&times;</a>";
                                            echo "</div>";
                                        }
                                    }
                                    echo "</div></div>";
                                }
                            }

                            if (!empty($all_uncategorized)) {
                                echo "<div style='margin-bottom: 15px;'>";
                                echo "<div style='font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px;'>Other Roles</div>";
                                echo "<div style='display: flex; flex-wrap: wrap; gap: 8px;'>";
                                foreach ($all_uncategorized as $r) {
                                    echo "<div style='background: var(--bg-main); border: 1px solid var(--border-color); padding: 4px 10px; border-radius: 20px; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: var(--text-main);'>";
                                    echo htmlspecialchars(role_display_label($r['role_name']));
                                    echo "<a href='admin_dashboard.php?tab=assign_roles&action=delete_role&role_id=" . $r['id'] . "' onclick=\"return confirm('Are you sure you want to remove this role from the system?');\" style='color: var(--danger); text-decoration: none; font-weight: bold; font-size: 1rem; line-height: 1;'>&times;</a>";
                                    echo "</div>";
                                }
                                echo "</div></div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Currently Assigned Roles (Hierarchical) -->
                <div class="content-card" style="margin-top: 20px;">
                    <h2 style="margin-bottom: 20px;">Currently Assigned Roles</h2>
                    <?php 
                    $assigned_roles_q = $conn->query("SELECT id, first_name, last_name, department, church_role FROM members WHERE church_role IS NOT NULL AND church_role != ''");
                    
                    // Organize fetched members into the hierarchy structure
                    $categorized_members = [];
                    $uncategorized = [];
                    
                    while($ar = $assigned_roles_q->fetch_assoc()) {
                        $member_roles = array_filter(array_map('trim', explode(',', str_replace('&', ',', $ar['church_role'] ?? ''))));
                        foreach ($member_roles as $role_clean) {
                            $placed = false;
                            foreach($hierarchy as $dept => $roles) {
                                $expected_department = $hierarchy_department_names[$dept] ?? '';
                                $member_department = trim($ar['department'] ?? '');
                                $is_youth_advisor_for_youth = $dept === 'Youth Ministry' && is_youth_advisor_role($role_clean);
                                if ($expected_department && !$is_youth_advisor_for_youth) {
                                    $member_aliases = array_map('strtolower', department_aliases($member_department));
                                    $expected_aliases = array_map('strtolower', department_aliases($expected_department));
                                    if (empty(array_intersect($member_aliases, $expected_aliases))) {
                                        continue;
                                    }
                                }
                                foreach($roles as $expected_role) {
                                    if (strtolower($role_clean) === strtolower($expected_role)) {
                                        $temp_ar = $ar;
                                        $temp_ar['displayed_role'] = $expected_role;
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
                            echo "<div style='margin-bottom: 30px;'>";
                            echo "<h3 style='font-size: 1.1rem; color: var(--primary); margin-bottom: 15px; border-bottom: 2px solid var(--border-color); padding-bottom: 5px;'>$dept</h3>";
                            echo "<div class='table-responsive'><table>";
                            echo "<thead><tr><th style='width: 40%;'>Assigned Role</th><th>Member</th><th>Action</th></tr></thead><tbody>";
                            
                            foreach ($roles as $expected_role) {
                                if (!empty($categorized_members[$dept][$expected_role])) {
                                    foreach ($categorized_members[$dept][$expected_role] as $member_data) {
                                        $role_label = role_display_label($expected_role, $member_data['department'] ?? null);
                                        echo "<tr>";
                                        echo "<td><span class='badge' style='background: var(--primary); color: white; font-weight: bold;'>" . htmlspecialchars($role_label) . "</span></td>";
                                        echo "<td style='font-weight: 500;'>" . htmlspecialchars($member_data['first_name'] . ' ' . $member_data['last_name']) . "</td>";
                                        echo "<td><a href='admin_dashboard.php?tab=assign_roles&action=remove_role&id=" . $member_data['id'] . "&role=" . urlencode($expected_role) . "' onclick=\"return confirm('Remove this role from " . htmlspecialchars($member_data['first_name']) . "?');\" class='btn-sm' style='background: var(--danger); color: white; text-decoration:none; border:none; cursor:pointer;'>Remove Role</a></td>";
                                        echo "</tr>";
                                    }
                                }
                            }
                            echo "</tbody></table></div></div>";
                        }
                    }

                    // Render uncategorized roles (like custom roles or ones not in the strict hierarchy)
                    if (!empty($uncategorized)) {
                        $has_any_roles = true;
                        echo "<div style='margin-bottom: 30px;'>";
                        echo "<h3 style='font-size: 1.1rem; color: var(--text-muted); margin-bottom: 15px; border-bottom: 2px solid var(--border-color); padding-bottom: 5px;'>Other Roles</h3>";
                        echo "<div class='table-responsive'><table>";
                        echo "<thead><tr><th style='width: 40%;'>Assigned Role</th><th>Member</th><th>Action</th></tr></thead><tbody>";
                        foreach ($uncategorized as $member_data) {
                            $disp_role = $member_data['displayed_role'] ?? $member_data['church_role'];
                            echo "<tr>";
                            echo "<td><span class='badge' style='background: var(--text-muted); color: white;'>" . htmlspecialchars(role_display_label($disp_role, $member_data['department'] ?? null)) . "</span></td>";
                            echo "<td style='font-weight: 500;'>" . htmlspecialchars($member_data['first_name'] . ' ' . $member_data['last_name']) . "</td>";
                            echo "<td><a href='admin_dashboard.php?tab=assign_roles&action=remove_role&id=" . $member_data['id'] . "&role=" . urlencode($disp_role) . "' onclick=\"return confirm('Remove this role from " . htmlspecialchars($member_data['first_name']) . "?');\" class='btn-sm' style='background: var(--danger); color: white; text-decoration:none; border:none; cursor:pointer;'>Remove Role</a></td>";
                            echo "</tr>";
                        }
                        echo "</tbody></table></div></div>";
                    }

                    if (!$has_any_roles) {
                        echo "<p style='color: var(--text-muted); padding: 15px 0;'>No roles are currently assigned.</p>";
                    }
                    ?>
                </div>

            <?php elseif ($tab == 'audit'): ?>
                <div class="page-header">
                    <h1>Audit Trail</h1>
                    <p>System activity and logs.</p>
                </div>
                <div class="content-card">
                    <?php if ($audit_logs->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>User Type</th>
                                        <th>Name</th>
                                        <th>Action</th>
                                        <th>Details</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($log = $audit_logs->fetch_assoc()): ?>
                                    <tr>
                                        <td style="white-space: nowrap;"><?= date('M j, Y h:i A', strtotime($log['created_at'])) ?></td>
                                        <td><span class="badge" style="background: var(--border-color); color: var(--text-main);"><?= htmlspecialchars($log['user_type']) ?></span></td>
                                        <td style="font-weight: 500;"><?= htmlspecialchars($log['user_name']) ?></td>
                                        <td><?= htmlspecialchars($log['action']) ?></td>
                                        <td><?= htmlspecialchars($log['details']) ?></td>
                                        <td><small style="color: var(--text-muted);"><?= htmlspecialchars($log['ip_address']) ?></small></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success">Audit logging is active but currently empty.</div>
                    <?php endif; ?>
                </div>

            <?php elseif ($tab == 'content'): ?>
                <div class="page-header">
                    <h1>Church Content</h1>
                    <p>Review all content uploaded by Pastors.</p>
                </div>

                <div class="content-card" style="margin-bottom: 30px;">
                    <h2>Daily Quotes</h2>
                    <?php if($all_daily_messages->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Pastor</th>
                                        <th>Quote</th>
                                        <th>Video</th>
                                        <th>Date Posted</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($dm = $all_daily_messages->fetch_assoc()): ?>
                                    <tr>
                                        <td style="font-weight: 500;"><?= htmlspecialchars($dm['first_name'] . ' ' . $dm['last_name']) ?></td>
                                        <td><?= nl2br(htmlspecialchars(substr($dm['quote'], 0, 100))) ?><?= strlen($dm['quote']) > 100 ? '...' : '' ?></td>
                                        <td>
                                            <?php if($dm['video_file']): ?>
                                                <a href="uploads/<?= htmlspecialchars($dm['video_file']) ?>" target="_blank" style="color: var(--primary);">View Video</a>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('M j, Y h:i A', strtotime($dm['created_at'])) ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--text-muted);">No daily quotes have been posted.</p>
                    <?php endif; ?>
                </div>

                <div class="content-card">
                    <h2>Church Highlights</h2>
                    <?php if($all_highlights->num_rows > 0): ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
                            <?php while($h = $all_highlights->fetch_assoc()): 
                                $ext = strtolower(pathinfo($h['image_file'], PATHINFO_EXTENSION));
                                $isVideo = in_array($ext, ['mp4', 'webm', 'ogg']);
                            ?>
                                <div style="border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; background: var(--bg-card);">
                                    <?php if($isVideo): ?>
                                        <video controls style="width: 100%; height: 150px; object-fit: cover; background: #000;">
                                            <source src="uploads/<?= htmlspecialchars($h['image_file']) ?>" type="video/<?= $ext === 'ogg' ? 'ogg' : $ext ?>">
                                        </video>
                                    <?php else: ?>
                                        <img src="uploads/<?= htmlspecialchars($h['image_file']) ?>" alt="Highlight" style="width: 100%; height: 150px; object-fit: cover;">
                                    <?php endif; ?>
                                    <div style="padding: 10px;">
                                        <p style="font-weight: 500; margin-bottom: 5px;"><?= htmlspecialchars($h['first_name'] . ' ' . $h['last_name']) ?></p>
                                        <p style="font-size: 0.9rem; margin-bottom: 10px; color: var(--text-main);"><?= htmlspecialchars($h['caption']) ?></p>
                                        <p style="font-size: 0.75rem; color: var(--text-muted);"><?= date('M j, Y h:i A', strtotime($h['created_at'])) ?></p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--text-muted);">No highlights have been uploaded.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($tab == 'general_leadership'): ?>
                <div class="page-header">
                    <h1>General Leadership</h1>
                    <p>Monitor General Church announcements and leadership communication.</p>
                </div>

                <div class="content-card" style="margin-bottom: 24px;">
                    <h2>General Church Announcements</h2>
                    <?php
                    $general_announcements = $conn->query("
                        SELECT da.*, m.first_name, m.last_name, m.church_role
                        FROM department_announcements da
                        JOIN members m ON da.secretary_id = m.id
                        WHERE da.department = 'General Church'
                        ORDER BY da.created_at DESC
                        LIMIT 50
                    ");
                    ?>
                    <?php if ($general_announcements && $general_announcements->num_rows > 0): ?>
                        <?php while ($ann = $general_announcements->fetch_assoc()): ?>
                            <div style="padding: 14px 0; border-bottom: 1px solid var(--border-color);">
                                <strong><?= htmlspecialchars($ann['first_name'] . ' ' . $ann['last_name']) ?></strong>
                                <span style="color: var(--text-muted); font-size: 0.85rem;"> - <?= htmlspecialchars($ann['church_role']) ?> - <?= date('M j, Y g:i A', strtotime($ann['created_at'])) ?></span>
                                <p style="margin: 8px 0 0; white-space: pre-wrap;"><?= htmlspecialchars($ann['message']) ?></p>
                                <?= render_announcement_image($ann['image_path'] ?? '') ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: var(--text-muted);">No General Church announcements yet.</p>
                    <?php endif; ?>
                </div>

                <?php foreach (['general_church' => 'General Leaders Chat'] as $chat_key => $chat_title): ?>
                    <div class="content-card" style="margin-bottom: 24px;">
                        <h2><?= htmlspecialchars($chat_title) ?></h2>
                        <?php
                        $safe_chat_key = $conn->real_escape_string($chat_key);
                        $chat_messages = $conn->query("
                            SELECT lm.*, m.first_name, m.last_name, m.church_role, m.department
                            FROM leader_messages lm
                            JOIN members m ON lm.sender_id = m.id
                            WHERE lm.dept_key = '$safe_chat_key'
                            ORDER BY lm.created_at DESC
                            LIMIT 80
                        ");
                        ?>
                        <?php if ($chat_messages && $chat_messages->num_rows > 0): ?>
                            <?php while ($msg = $chat_messages->fetch_assoc()): ?>
                                <div style="padding: 12px 0; border-bottom: 1px solid var(--border-color);">
                                    <strong><?= htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']) ?></strong>
                                    <span style="color: var(--text-muted); font-size: 0.85rem;"> - <?= htmlspecialchars($msg['church_role']) ?> - <?= htmlspecialchars($msg['department'] ?? 'General Church') ?> - <?= date('M j, Y g:i A', strtotime($msg['created_at'])) ?></span>
                                    <p style="margin: 8px 0 0; white-space: pre-wrap;"><?= htmlspecialchars($msg['message']) ?></p>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="color: var(--text-muted);">No messages yet.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div class="content-card" style="margin-bottom: 24px;">
                    <h2>Announcement Queries</h2>
                    <?php
                    $announcement_queries = $conn->query("
                        SELECT q.*, m.first_name, m.last_name, m.church_role
                        FROM secretary_announcement_queries q
                        JOIN members m ON q.secretary_id = m.id
                        ORDER BY q.created_at DESC
                        LIMIT 80
                    ");
                    ?>
                    <?php if ($announcement_queries && $announcement_queries->num_rows > 0): ?>
                        <?php while ($q = $announcement_queries->fetch_assoc()): ?>
                            <div style="padding: 12px 0; border-bottom: 1px solid var(--border-color);">
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
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: var(--text-muted);">No announcement queries yet.</p>
                    <?php endif; ?>
                </div>

                <div class="content-card" style="margin-bottom: 24px;">
                    <h2>General Secretary Messages</h2>
                    <?php
                    $secretary_direct_messages = $conn->query("
                        SELECT dm.*, sender.first_name AS sender_first_name, sender.last_name AS sender_last_name, sender.church_role AS sender_role,
                               recipient.first_name AS recipient_first_name, recipient.last_name AS recipient_last_name, recipient.church_role AS recipient_role, recipient.department AS recipient_department
                        FROM secretary_direct_messages dm
                        JOIN members sender ON dm.sender_id = sender.id
                        JOIN members recipient ON dm.recipient_id = recipient.id
                        ORDER BY dm.created_at DESC
                        LIMIT 80
                    ");
                    ?>
                    <?php if ($secretary_direct_messages && $secretary_direct_messages->num_rows > 0): ?>
                        <?php while ($msg = $secretary_direct_messages->fetch_assoc()): ?>
                            <div style="padding: 12px 0; border-bottom: 1px solid var(--border-color);">
                                <strong><?= htmlspecialchars($msg['sender_first_name'] . ' ' . $msg['sender_last_name']) ?></strong>
                                <span style="color: var(--text-muted); font-size: 0.85rem;"> to <?= htmlspecialchars($msg['recipient_first_name'] . ' ' . $msg['recipient_last_name']) ?> - <?= htmlspecialchars($msg['recipient_department'] ?? 'No Department') ?> - <?= date('M j, Y g:i A', strtotime($msg['created_at'])) ?></span>
                                <p style="margin: 8px 0 0; white-space: pre-wrap;"><?= htmlspecialchars($msg['message']) ?></p>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: var(--text-muted);">No General Secretary messages yet.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($tab == 'settings'): ?>
                <div class="page-header">
                    <h1>System Settings</h1>
                    <p>Global configurations.</p>
                </div>
                <div class="content-card">
                    <p style="color: var(--text-muted);">More configuration options coming soon.</p>
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
                                            <td style="font-weight: 500;"><?= htmlspecialchars($fr['first_name'] . ' ' . $fr['last_name']) ?></td>
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
                                            <?php
                                            $target_dt = $sm['match_date'] . ' ' . $sm['match_time'];
                                            $end_dt = !empty($sm['end_time']) ? $sm['match_date'] . ' ' . $sm['end_time'] : '';
                                            ?>
                                            <span class="prayer-countdown" data-target="<?= $target_dt ?>" <?= $end_dt ? 'data-end-target="'.$end_dt.'"' : '' ?> style="background:rgba(245,158,11,0.1);color:#f59e0b;font-weight:600;font-family:monospace;font-size:13px;padding:3px 10px;border-radius:12px;">Calculating...</span>
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
            <?php endif; ?>

            <?php elseif ($tab == 'desired_roles'): ?>
                <div class="page-header">
                    <h1>Village & Desired Roles</h1>
                    <p>View all members categorized by their church village and preferred roles.</p>
                </div>
                
                <?php
                $villages = ['Akoritho', 'Philadelphia', 'Bethsaida'];
                $roles = ['Worshipper', 'Church Cleaner', 'Church Cooker'];
                
                foreach ($villages as $v):
                    // Count total in village
                    $v_count = $conn->query("SELECT COUNT(*) as c FROM members WHERE church_village = '$v'")->fetch_assoc()['c'];
                ?>
                <div class="content-card" style="margin-bottom:30px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid var(--border-color); padding-bottom:15px; margin-bottom:20px;">
                        <h2 style="margin:0; color:var(--primary); display:flex; align-items:center; gap:10px;">
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                            <?= $v ?> Village
                        </h2>
                        <span class="badge" style="background:var(--bg-lighter); font-size:1rem; padding:6px 12px;"><?= $v_count ?> Members</span>
                    </div>
                    
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:20px;">
                        <?php 
                        // Loop roles + none
                        $role_groups = $roles;
                        $role_groups[] = 'No Preference';
                        
                        foreach ($role_groups as $rg): 
                            if ($rg === 'No Preference') {
                                $rg_members = $conn->query("SELECT * FROM members WHERE church_village = '$v' AND (desired_role_pref IS NULL OR desired_role_pref = '') ORDER BY first_name");
                            } else {
                                $rg_members = $conn->query("SELECT * FROM members WHERE church_village = '$v' AND desired_role_pref = '$rg' ORDER BY first_name");
                            }
                            $c = $rg_members->num_rows;
                        ?>
                        <div style="background:var(--bg-lighter); border:1px solid var(--border-color); border-radius:12px; overflow:hidden;">
                            <div style="padding:12px 16px; background:rgba(37,99,235,0.05); border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                                <h3 style="margin:0; font-size:1rem; color:var(--text-main);"><?= $rg ?></h3>
                                <span class="badge" style="background:white;"><?= $c ?></span>
                            </div>
                            <div style="padding:16px; max-height:250px; overflow-y:auto;">
                                <?php if ($c > 0): ?>
                                    <ul style="list-style:none; padding:0; margin:0;">
                                        <?php while($m = $rg_members->fetch_assoc()): ?>
                                        <li style="padding:8px 0; border-bottom:1px dashed var(--border-color); display:flex; align-items:center; gap:10px;">
                                            <div style="width:30px; height:30px; border-radius:50%; background:var(--primary); color:white; display:flex; align-items:center; justify-content:center; font-size:0.8rem; font-weight:600;">
                                                <?= strtoupper(substr($m['first_name'],0,1) . substr($m['last_name'],0,1)) ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:600; font-size:0.9rem;"><?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?></div>
                                                <div style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($m['phone']) ?></div>
                                            </div>
                                        </li>
                                        <?php endwhile; ?>
                                    </ul>
                                <?php else: ?>
                                    <p style="color:var(--text-muted); font-size:0.85rem; margin:0; text-align:center; padding:10px 0;">No members in this group.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

            <?php elseif ($tab == 'manage_worshippers'): ?>
                <div class="page-header">
                    <h1>Worship Department Overview</h1>
                    <p>Monitor the total number of registered worshippers and their assigned tasks.</p>
                </div>
                
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

            <?php elseif ($tab == 'choir_songs'): ?>
                <div class="page-header">
                    <h1>Choir Songs Monitor</h1>
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
                                <thead><tr><th>Department</th><th>Song Type</th><th>Song Title</th><th>Day</th><th>Choir Leader</th><th>Notes</th></tr></thead>
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
                                    <?= render_announcement_image($ca['image_path'] ?? '') ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--text-muted);">No choir announcements posted yet.</p>
                    <?php endif; ?>
                </div>
            <?php elseif ($tab == 'usher_monitoring'): ?>
                <div class="page-header">
                    <h1>📢 Usher Monitoring</h1>
                    <p>Monitor announcements and chat messages within the Usher department.</p>
                </div>

                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <!-- Announcements Column -->
                    <div style="flex: 1; min-width: 300px;">
                        <div class="content-card" style="margin-bottom: 24px;">
                            <h2>📢 Usher Announcements</h2>
                            <?php
                            $all_usher_anns = $conn->query("SELECT ua.*, m.first_name, m.last_name, m.department FROM usher_announcements ua JOIN members m ON ua.usher_id = m.id ORDER BY ua.created_at DESC");
                            if ($all_usher_anns && $all_usher_anns->num_rows > 0): ?>
                                <div style="display: flex; flex-direction: column; gap: 15px;">
                                <?php while($ua = $all_usher_anns->fetch_assoc()): ?>
                                    <div style="padding: 18px; border-left: 4px solid var(--primary); background: var(--bg-main); border-radius: 0 10px 10px 0;">
                                        <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 8px;">
                                            <strong style="color: var(--text-main);"><?= htmlspecialchars($ua['title']) ?></strong>
                                            <small style="color: var(--text-muted);"><?= date('M j, Y g:i A', strtotime($ua['created_at'])) ?> — <?= htmlspecialchars($ua['first_name'] . ' ' . $ua['last_name']) ?> (<?= htmlspecialchars($ua['department'] ?? 'General') ?>)</small>
                                        </div>
                                        <p style="margin: 0; color: var(--text-muted); line-height: 1.6;"><?= nl2br(htmlspecialchars($ua['message'])) ?></p>
                                    </div>
                                <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <p style="color: var(--text-muted);">No announcements posted yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Chat Column -->
                    <div style="flex: 1; min-width: 300px;">
                        <div class="content-card" style="margin-bottom: 24px;">
                            <h2>💬 Usher Chat</h2>
                            <?php
                            $all_usher_msgs = $conn->query("SELECT uc.*, m.first_name, m.last_name, m.church_role, m.department FROM usher_chat uc JOIN members m ON uc.sender_id = m.id ORDER BY uc.created_at DESC LIMIT 100");
                            if ($all_usher_msgs && $all_usher_msgs->num_rows > 0): ?>
                                <div style="display: flex; flex-direction: column; gap: 15px; max-height: 500px; overflow-y: auto; padding-right: 5px;">
                                <?php while($uc = $all_usher_msgs->fetch_assoc()): ?>
                                    <div style="padding: 12px 0; border-bottom: 1px solid var(--border-color);">
                                        <strong><?= htmlspecialchars($uc['first_name'] . ' ' . $uc['last_name']) ?></strong>
                                        <span style="color: var(--text-muted); font-size: 0.85rem;"> - <?= htmlspecialchars($uc['church_role']) ?> - <?= htmlspecialchars($uc['department'] ?? 'General') ?> - <?= date('M j, Y g:i A', strtotime($uc['created_at'])) ?></span>
                                        <p style="margin: 8px 0 0; white-space: pre-wrap;"><?= htmlspecialchars($uc['message']) ?></p>
                                    </div>
                                <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <p style="color: var(--text-muted);">No chat messages yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    const themeToggle = document.getElementById('themeToggle');
    const moonIcon = document.getElementById('moonIcon');
    const sunIcon = document.getElementById('sunIcon');
    const htmlEl = document.documentElement;
    
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

    // Match Countdown Timer (reuses same engine as prayer)
    function updateCountdowns() {
        document.querySelectorAll('.prayer-countdown').forEach(el => {
            const targetStr = el.getAttribute('data-target');
            const endStr = el.getAttribute('data-end-target');
            if (!targetStr) return;
            const targetTime = new Date(targetStr.replace(' ', 'T') + '+03:00').getTime();
            const now = new Date().getTime();
            const diff = targetTime - now;
            if (diff <= 0) {
                if (endStr) {
                    const endTime = new Date(endStr.replace(' ', 'T') + '+03:00').getTime();
                    if (now >= endTime) {
                        el.innerHTML = '🏁 Ended'; el.style.color = '#6b7280'; el.style.background = 'rgba(107,114,128,0.1)'; return;
                    } else {
                        const remainDiff = endTime - now;
                        const h = Math.floor(remainDiff / (1000*60*60));
                        const m = Math.floor((remainDiff % (1000*60*60)) / (1000*60));
                        let r = ''; if (h > 0) r += h + 'h '; r += m + 'm';
                        el.innerHTML = `🟢 Live Now (Ends in ${r})`; el.style.color = '#10b981'; el.style.background = 'rgba(16,185,129,0.1)'; return;
                    }
                }
                el.innerHTML = '🟢 Live Now'; el.style.color = '#10b981'; el.style.background = 'rgba(16,185,129,0.1)';
            } else {
                const h = Math.floor(diff / (1000*60*60));
                const m = Math.floor((diff % (1000*60*60)) / (1000*60));
                const s = Math.floor((diff % (1000*60)) / 1000);
                let t = ''; if (h > 0) t += h.toString().padStart(2,'0') + 'h '; t += m.toString().padStart(2,'0') + 'm ' + s.toString().padStart(2,'0') + 's';
                el.innerHTML = '⏱ Starts in: ' + t; el.style.color = '#f59e0b'; el.style.background = 'rgba(245,158,11,0.1)';
            }
        });
    }
    if (document.querySelector('.prayer-countdown')) { updateCountdowns(); setInterval(updateCountdowns, 1000); }
    </script>
    <script>
        window.tabNotificationBadges = <?= json_encode($tab_badges) ?>;
    </script>
    <script src="script.js"></script>
</body>
</html>
