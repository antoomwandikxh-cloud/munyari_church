<?php
session_start();
require_once 'db_connect.php';
require_once 'role_departments.php';
require_once 'notification_badges.php';
if (!isset($_SESSION['pastor_id'])) { header("Location: login.php"); exit(); }

$pastor_id = $_SESSION['pastor_id'];
$pastor_name = $_SESSION['pastor_name'];
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

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

// Notifications
$unread_notifs = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $pastor_id AND user_type = 'pastor' AND is_read = 0")->fetch_assoc()['count'];
$notifications = $conn->query("SELECT * FROM notifications WHERE user_id = $pastor_id AND user_type = 'pastor' ORDER BY created_at DESC LIMIT 20");
$tab_badges = build_tab_notification_badges($conn, $pastor_id, 'pastor', $tab);

// Members Data
$members = $conn->query("SELECT * FROM members ORDER BY is_approved ASC, first_name ASC");
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


// Handle Register Member
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_member'])) {
    $fn  = $conn->real_escape_string(trim($_POST['first_name']));
    $ln  = $conn->real_escape_string(trim($_POST['last_name']));
    $ph  = $conn->real_escape_string(trim($_POST['phone']));
    $ad  = $conn->real_escape_string(trim($_POST['address'] ?? ''));
    $dp  = $conn->real_escape_string(trim($_POST['department'] ?? 'None'));
    $gn  = $conn->real_escape_string(trim($_POST['gender'] ?? 'Male'));
    $pw  = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
    // Validate required fields
    if (empty($fn) || empty($ln) || empty($ph) || empty($_POST['password'])) {
        header("Location: pastor_dashboard.php?tab=manage_members&error=First name, last name, phone and password are required");
        exit();
    }
    // Check duplicate phone
    $dup = $conn->query("SELECT id FROM members WHERE phone = '$ph'")->num_rows;
    if ($dup > 0) {
        header("Location: pastor_dashboard.php?tab=manage_members&error=A member with that phone number already exists");
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
    $conn->query("UPDATE members SET is_approved = 1 WHERE id = $act_id");
    header("Location: pastor_dashboard.php?tab=manage_members&success=Member activated");
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
        $mem = $conn->query("SELECT pending_department FROM members WHERE id = $member_id")->fetch_assoc();
        if ($mem && $mem['pending_department']) {
            $new_dept = $conn->real_escape_string($mem['pending_department']);
            $conn->query("UPDATE members SET department = '$new_dept', pending_department = NULL WHERE id = $member_id");
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

                <a href="?tab=manage_members" class="sidebar-link <?= $tab == 'manage_members' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Manage Members
                </a>
                <a href="?tab=departments" class="sidebar-link <?= $tab == 'departments' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    Departments
                </a>
                <a href="?tab=assign_roles" class="sidebar-link <?= $tab == 'assign_roles' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>
                    Assign Roles
                </a>
                <a href="?tab=financials" class="sidebar-link <?= $tab == 'financials' ? 'active' : '' ?>" style="position:relative;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Financial Records
                    <?php if (!empty($tab_badges['financials'])): ?><span style="background:var(--danger);color:white;font-size:0.65rem;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:auto;"><?= $tab_badges['financials'] ?></span><?php endif; ?>
                </a>
                
                <div class="sidebar-label" style="padding: 10px 20px; font-size: 12px; text-transform: uppercase; color: var(--text-muted); margin-top: 10px;">Membership</div>
                <a href="?tab=desired_roles" class="sidebar-link <?= $tab == 'desired_roles' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Village & Desired Roles
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
            <div class="topbar" style="justify-content: flex-end;">
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
                        <button onclick="toggleNotifDropdown(event)" class="icon-btn <?= $unread_notifs > 0 ? 'bell-shake' : '' ?>" title="Notifications" style="<?= $unread_notifs > 0 ? 'color: var(--warning); border-color: var(--warning);' : '' ?>; position:relative;">
                            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                            <?php if($unread_notifs > 0): ?>
                                <span class="notif-badge" style="position:absolute; top:-6px; right:-6px;"><?= $unread_notifs ?></span>
                            <?php endif; ?>
                        </button>
                        <!-- Dropdown -->
                        <div id="notifDropdown" style="display:none; position:absolute; top:calc(100% + 10px); right:0; width:340px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:14px; box-shadow:0 12px 40px rgba(0,0,0,0.18); z-index:9999; overflow:hidden;">
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
                                    <a href="?tab=<?= htmlspecialchars($dn_target) ?>" style="display:block; padding:12px 18px; border-bottom:1px solid var(--border-color); text-decoration:none; background:<?= !$dn['is_read'] ? 'rgba(37,99,235,0.06)' : 'transparent' ?>; transition:background 0.15s;" onmouseover="this.style.background='rgba(37,99,235,0.1)'" onmouseout="this.style.background='<?= !$dn['is_read'] ? 'rgba(37,99,235,0.06)' : 'transparent' ?>'">
                                        <div style="display:flex; align-items:flex-start; gap:10px;">
                                            <div style="width:8px; height:8px; border-radius:50%; background:<?= !$dn['is_read'] ? 'var(--primary)' : 'var(--border-color)' ?>; margin-top:5px; flex-shrink:0;"></div>
                                            <div>
                                                <p style="margin:0; font-size:0.85rem; color:var(--text-main); line-height:1.4;"><?= htmlspecialchars($dn['message']) ?></p>
                                                <small style="color:var(--text-muted); font-size:0.75rem;"><?= date('M j, g:i A', strtotime($dn['created_at'])) ?></small>
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
                                <a href="?tab=appointments" style="flex:1; padding:12px; text-align:center; color:var(--primary); font-size:0.875rem; font-weight:600; text-decoration:none; background:var(--bg-main);" onmouseover="this.style.background='rgba(37,99,235,0.06)'" onmouseout="this.style.background='var(--bg-main)'">
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
                                    <span style="font-size:0.8rem;color:var(--text-muted);"> — <?= htmlspecialchars($q['church_role']) ?> · <?= htmlspecialchars($q['department']) ?></span>
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
                                    <span style="font-size:0.8rem;color:var(--text-muted);"> — <?= htmlspecialchars($dann['church_role']) ?></span>
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
                                <input type="text" name="first_name" class="form-control" placeholder="e.g. Chipo" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name <span style="color:var(--danger);">*</span></label>
                                <input type="text" name="last_name" class="form-control" placeholder="e.g. Moyo" required>
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
                            <!-- Login Credentials -->
                            <div class="form-group">
                                <label>Login Password <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <input type="password" name="password" id="pastorRegPwd" class="form-control" placeholder="Set a secure login password" required style="padding-right:46px;">
                                    <span onclick="var i=document.getElementById('pastorRegPwd');i.type=i.type==='password'?'text':'password'" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--text-muted);">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Confirm Password <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <input type="password" name="confirm_password" id="pastorRegPwdConfirm" class="form-control" placeholder="Re-enter password" required style="padding-right:46px;" oninput="checkPastorPwd()">
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
                <div class="content-card">
                    <h2 style="margin-bottom: 20px;">Member Directory</h2>
                    <div class="table-responsive"><table>
                        <thead><tr><th>Name</th><th>Phone</th><th>Address</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php $members->data_seek(0); while($m = $members->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?></td>
                                <td><?= htmlspecialchars($m['phone']) ?></td>
                                <td><?= htmlspecialchars($m['address'] ?? '-') ?></td>
                                <td>
                                    <?php if ($m['is_approved'] == 1): ?>
                                        <span class="badge" style="background:var(--success);color:white;">Active</span>
                                    <?php elseif ($m['is_approved'] == -1): ?>
                                        <span class="badge" style="background:var(--danger);color:white;">Deactivated</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#f59e0b;color:white;">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($m['is_approved'] != -1): ?>
                                    <a href="?tab=manage_members&action=deactivate_member&id=<?= $m['id'] ?>" onclick="return confirm('Deactivate this member? They will not be able to log in.')" style="background:#f59e0b;color:white;padding:4px 10px;border-radius:5px;text-decoration:none;font-size:0.85rem;margin-right:5px;">Deactivate</a>
                                    <?php else: ?>
                                    <a href="?tab=manage_members&action=activate_member&id=<?= $m['id'] ?>" onclick="return confirm('Activate this member?')" style="background:var(--success);color:white;padding:4px 10px;border-radius:5px;text-decoration:none;font-size:0.85rem;margin-right:5px;">Activate</a>
                                    <?php endif; ?>
                                    <a href="?tab=manage_members&action=delete_member&id=<?= $m['id'] ?>" onclick="return confirm('Delete this member?')" style="background:var(--danger);color:white;padding:4px 10px;border-radius:5px;text-decoration:none;font-size:0.85rem;">Delete</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table></div>
                </div>
            <?php elseif ($tab == 'departments'): ?>
                <div class="page-header">
                    <h1>Church Departments</h1>
                    <p>Overview of active departments and their members.</p>
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

            <?php elseif ($tab == 'desired_roles'): ?>
                <div class="page-header">
                    <h1>Village & Desired Roles</h1>
                    <p>Members categorized by church village, department, and chosen service roles.</p>
                </div>
                
                <?php
                $villages = ['Akoritho', 'Philadelphia', 'Bethsaida'];
                $village_colors = ['Akoritho' => '#6366f1', 'Philadelphia' => '#0ea5e9', 'Bethsaida' => '#10b981'];
                ?>
                
                <!-- ═══ SECTION 1: Members by Village + Department ═══ -->
                <h2 style="font-size:1.1rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:16px; display:flex; align-items:center; gap:10px;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Church Villages — Members & Departments
                </h2>
                
                <?php foreach ($villages as $v): 
                    $v_color = $village_colors[$v];
                    $v_members = $conn->query("SELECT first_name, last_name, department, church_role, phone, desired_role_pref FROM members WHERE church_village='$v' ORDER BY department, first_name");
                    $v_count = $v_members ? $v_members->num_rows : 0;
                ?>
                <div class="content-card" style="margin-bottom:28px; border-top:4px solid <?= $v_color ?>;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                        <h2 style="margin:0; color:<?= $v_color ?>; display:flex; align-items:center; gap:10px; font-size:1.2rem;">
                            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            <?= $v ?> Village
                        </h2>
                        <span class="badge" style="background:<?= $v_color ?>22; color:<?= $v_color ?>; font-weight:700; font-size:1rem; padding:6px 14px;"><?= $v_count ?> Members</span>
                    </div>
                    
                    <?php if ($v_count > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Full Name</th>
                                    <th>Department</th>
                                    <th>Church Role</th>
                                    <th>Desired Service</th>
                                    <th>Phone</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php $i = 1; while($vm = $v_members->fetch_assoc()): 
                                $dsr = $vm['desired_role_pref'] ?? '';
                                $dsr_color = $dsr === 'Worshipper' ? '#8b5cf6' : ($dsr === 'Church Cleaner' ? '#0ea5e9' : ($dsr === 'Church Cooker' ? '#f59e0b' : '#94a3b8'));
                            ?>
                            <tr>
                                <td style="color:var(--text-muted);"><?= $i++ ?></td>
                                <td style="font-weight:600;"><?= htmlspecialchars($vm['first_name'] . ' ' . $vm['last_name']) ?></td>
                                <td><?= htmlspecialchars($vm['department'] ?: 'General Church') ?></td>
                                <td><span class="badge" style="background:rgba(37,99,235,0.1); color:var(--primary);"><?= htmlspecialchars($vm['church_role'] ?: 'Member') ?></span></td>
                                <td><?php if ($dsr): ?><span class="badge" style="background:<?= $dsr_color ?>22; color:<?= $dsr_color ?>;"><?= htmlspecialchars($dsr) ?></span><?php else: ?><span style="color:var(--text-muted); font-size:0.85rem;">—</span><?php endif; ?></td>
                                <td style="color:var(--text-muted); font-size:0.85rem;"><?= htmlspecialchars($vm['phone'] ?? '—') ?></td>
                            </tr>
                            <?php endwhile; ?>
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
                <h2 style="font-size:1.1rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:16px; display:flex; align-items:center; gap:10px;">
                    <span style="font-size:1.3rem;">🧹</span> Church Cleaners — All Villages
                </h2>
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

            <?php elseif ($tab == 'financials'): ?>
                <div class="page-header">
                    <h1>Financial Records Tracking</h1>
                    <p>Overview of all monies collected by department treasurers.</p>
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
                    <h2 style="margin-bottom: 15px;">Financial Records</h2>
                    <?php
                    $fin_records = $conn->query("SELECT fr.*, m.first_name, m.last_name FROM financial_records fr JOIN members m ON fr.recorded_by = m.id ORDER BY fr.recorded_at DESC LIMIT 50");
                    if ($fin_records && $fin_records->num_rows > 0):
                    ?>
                    <div class="table-responsive"><table>
                        <thead><tr><th>Recorded By</th><th>Department</th><th>Amount (KSh)</th><th>Description</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php while($fr = $fin_records->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($fr['first_name'] . ' ' . $fr['last_name']) ?></td>
                                <td><span class="badge" style="background:rgba(99,102,241,0.12);color:#6366f1;"><?= htmlspecialchars($fr['department'] ?? '-') ?></span></td>
                                <td style="font-weight:600;color:#10b981;">KSh <?= number_format($fr['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($fr['description'] ?? '-') ?></td>
                                <td style="font-size:0.85em;color:var(--text-muted);"><?= date('M j, Y', strtotime($fr['record_date'] ?: $fr['recorded_at'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table></div>
                    <?php else: ?>
                        <p style="color:var(--text-muted);">No financial records yet.</p>
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
                    'General Church' => [
                        'General Church Secretary', 'Vice Church Secretary', 'Treasurer', 'Senior Church Elder'
                    ],
                    'Other Ministry Leaders' => [
                        'Head Usher', 'Usher', 'Building Chairperson', 'Vice Building Chairperson', 'Building Secretary', 'Vice Building Secretary', 'Building Treasurer', 'Worship Leader', 'Vice Worship Leader'
                    ],
                    'Youth Ministry' => array_merge([
                        'Youth Chairperson', 'Vice Youth Chairperson', 'Youth Secretary', 'Vice Youth Secretary', 'Youth Treasurer', 'Mama Youth', 'Baba Youth'
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
                                        <td style="font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($pr['pending_role']) ?></td>
                                        <td>
                                            <div style="display: flex; gap: 10px;">
                                                <a href="pastor_action.php?action=approve_appointment&id=<?= $pr['id'] ?>" class="btn-action btn-approve" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">Approve</a>
                                                <a href="pastor_action.php?action=reject_appointment&id=<?= $pr['id'] ?>" class="btn-action btn-reject" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;" onclick="return confirm('Reject this appointment?');">Reject</a>
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
                    <p>Manage your account credentials.</p>
                </div>
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
</body>
</html>