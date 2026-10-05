<?php
// ── Database connection ────────────────────────────────────────────────────────
// Load hosting credentials if the config file exists, otherwise use XAMPP defaults
if (file_exists(__DIR__ . '/db_config.php')) {
    require_once __DIR__ . '/db_config.php';
    $servername = defined('DB_HOST') ? DB_HOST : 'localhost';
    $username   = defined('DB_USER') ? DB_USER : 'root';
    $password   = defined('DB_PASS') ? DB_PASS : '';
    $dbname     = defined('DB_NAME') ? DB_NAME : 'munyari_church';
} else {
    // XAMPP local defaults
    $servername = 'localhost';
    $username   = 'root';
    $password   = '';
    $dbname     = 'munyari_church';
}

date_default_timezone_set('Africa/Nairobi');

// Suppress notices/warnings that can break HTTP headers on strict hosts
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
@ini_set('display_errors', 0);

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    error_log('DB Connection failed: ' . $conn->connect_error);
    $err_msg  = $conn->connect_error;
    $err_code = $conn->connect_errno;

    // For AJAX / API requests return JSON
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($is_ajax) {
        http_response_code(503);
        die(json_encode(['error' => 'Database unavailable: ' . $err_msg]));
    }

    // Human-readable messages per error code
    if ($err_code == 1049) {
        $title    = 'Database Not Found';
        $subtitle = "The database <b>&ldquo;$dbname&rdquo;</b> does not exist on this server.";
        $tip      = "It looks like you just moved the system to a new computer or hosting environment.";
        $action   = '<a href="install.php" style="display:inline-block;margin-top:18px;background:#2563eb;color:#fff;padding:12px 28px;border-radius:8px;font-weight:700;text-decoration:none;font-size:1rem;">&#9881; Run the Installer</a>';
    } elseif ($err_code == 1045) {
        $title    = 'Access Denied';
        $subtitle = "The username or password used to connect to the database is incorrect.";
        $tip      = "Open <b>db_config.php</b> (or <b>db_connect.php</b>) and verify the database username and password.";
        $action   = '';
    } elseif ($err_code == 2002 || $err_code == 2003) {
        $title    = 'Database Server is Offline';
        $subtitle = "Cannot reach the database server at <b>$servername</b>.";
        $tip      = "Please start XAMPP and make sure the <b>MySQL</b> service is running.";
        $action   = '';
    } else {
        $title    = 'Database Connection Error';
        $subtitle = "An unexpected error occurred while connecting to the database.";
        $tip      = '';
        $action   = '<a href="install.php" style="display:inline-block;margin-top:18px;background:#2563eb;color:#fff;padding:12px 28px;border-radius:8px;font-weight:700;text-decoration:none;font-size:1rem;">&#9881; Run the Installer</a>';
    }

    http_response_code(503);
    die('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>System Error</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",Tahoma,Geneva,Verdana,sans-serif;background:#f3f4f6;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px}
.box{background:#fff;padding:40px;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.07);max-width:520px;width:100%;text-align:center;border-top:5px solid #ef4444}
.icon{margin-bottom:18px;color:#ef4444}
h1{color:#111827;font-size:1.4rem;margin-bottom:10px}
.sub{color:#dc2626;font-size:1rem;font-weight:500;line-height:1.5;margin-bottom:10px}
.tip{color:#6b7280;font-size:.9rem;line-height:1.6;margin-top:12px}
.tech{margin-top:24px;padding:14px;background:#fef2f2;border:1px dashed #fca5a5;border-radius:8px;color:#991b1b;font-family:monospace;text-align:left;font-size:.82rem;word-break:break-all;line-height:1.6}
</style></head>
<body><div class="box">
<div class="icon"><svg width="56" height="56" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg></div>
<h1>' . $title . '</h1>
<p class="sub">' . $subtitle . '</p>
' . ($tip ? '<p class="tip">' . $tip . '</p>' : '') . '
' . $action . '
<div class="tech"><b>Technical Details:</b><br>Error Code: ' . $err_code . '<br>Message: ' . htmlspecialchars($err_msg) . '</div>
</div></body></html>');
}

$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+03:00'");

// ── Background: prayer started notifications ───────────────────────────────────
$now_str = date('Y-m-d H:i:00');
$started_prayers = $conn->query("SELECT * FROM prayer_schedules WHERE notification_sent = 0 AND CONCAT(prayer_date, ' ', prayer_time) <= '$now_str'");
if ($started_prayers && $started_prayers->num_rows > 0) {
    while ($p = $started_prayers->fetch_assoc()) {
        $p_id    = $p['id'];
        $p_dept  = $p['department'];
        $p_title = $conn->real_escape_string($p['title']);
        $p_loc   = $conn->real_escape_string($p['location']);
        $notif_msg = $conn->real_escape_string("The prayer session \"$p_title\" has just begun at $p_loc. Join us in prayer now!");
        $dept_members = $conn->query("SELECT id FROM members WHERE department='$p_dept' AND is_approved=1");
        if ($dept_members) { while ($dm = $dept_members->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$dm['id']}, 'member', '$notif_msg')"); } }
        $pastors = $conn->query("SELECT id FROM pastors");
        if ($pastors) { while ($pst = $pastors->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$pst['id']}, 'pastor', '$notif_msg')"); } }
        $admins = $conn->query("SELECT id FROM admins");
        if ($admins) { while ($adm = $admins->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$adm['id']}, 'admin', '$notif_msg')"); } }
        $conn->query("UPDATE prayer_schedules SET notification_sent = 1 WHERE id = $p_id");
    }
}

// ── Background: prayer ended notifications ─────────────────────────────────────
$ended_prayers = $conn->query("SELECT * FROM prayer_schedules WHERE end_notification_sent = 0 AND end_time IS NOT NULL AND CONCAT(prayer_date, ' ', end_time) <= '$now_str'");
if ($ended_prayers && $ended_prayers->num_rows > 0) {
    while ($ep = $ended_prayers->fetch_assoc()) {
        $ep_id    = $ep['id'];
        $ep_dept  = $ep['department'];
        $ep_title = $conn->real_escape_string($ep['title']);
        $notif_msg = $conn->real_escape_string("The prayer session \"$ep_title\" has ended. Thank you for participating!");
        $dept_members = $conn->query("SELECT id FROM members WHERE department='$ep_dept' AND is_approved=1");
        if ($dept_members) { while ($dm = $dept_members->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$dm['id']}, 'member', '$notif_msg')"); } }
        $pastors = $conn->query("SELECT id FROM pastors");
        if ($pastors) { while ($pst = $pastors->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$pst['id']}, 'pastor', '$notif_msg')"); } }
        $admins = $conn->query("SELECT id FROM admins");
        if ($admins) { while ($adm = $admins->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$adm['id']}, 'admin', '$notif_msg')"); } }
        $conn->query("UPDATE prayer_schedules SET end_notification_sent = 1 WHERE id = $ep_id");
    }
}

// ── Background: sport match started notifications ──────────────────────────────
$started_matches = $conn->query("SELECT * FROM sport_matches WHERE notification_sent = 0 AND CONCAT(match_date, ' ', match_time) <= '$now_str'");
if ($started_matches && $started_matches->num_rows > 0) {
    while ($sm = $started_matches->fetch_assoc()) {
        $sm_id    = $sm['id'];
        $sm_dept  = $sm['department'];
        $sm_title = $conn->real_escape_string($sm['title']);
        $sm_loc   = $conn->real_escape_string($sm['location']);
        $notif_msg = $conn->real_escape_string("The match \"$sm_title\" has just kicked off at $sm_loc. Go team!");
        $dept_members = $conn->query("SELECT id FROM members WHERE department='$sm_dept' AND is_approved=1");
        if ($dept_members) { while ($dm = $dept_members->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$dm['id']}, 'member', '$notif_msg')"); } }
        $pastors = $conn->query("SELECT id FROM pastors");
        if ($pastors) { while ($pst = $pastors->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$pst['id']}, 'pastor', '$notif_msg')"); } }
        $admins = $conn->query("SELECT id FROM admins");
        if ($admins) { while ($adm = $admins->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$adm['id']}, 'admin', '$notif_msg')"); } }
        $conn->query("UPDATE sport_matches SET notification_sent = 1 WHERE id = $sm_id");
    }
}

// ── Background: sport match ended notifications ────────────────────────────────
$ended_matches = $conn->query("SELECT * FROM sport_matches WHERE end_notification_sent = 0 AND end_time IS NOT NULL AND CONCAT(match_date, ' ', end_time) <= '$now_str'");
if ($ended_matches && $ended_matches->num_rows > 0) {
    while ($em = $ended_matches->fetch_assoc()) {
        $em_id    = $em['id'];
        $em_dept  = $em['department'];
        $em_title = $conn->real_escape_string($em['title']);
        $notif_msg = $conn->real_escape_string("The match \"$em_title\" has ended. Great game everyone!");
        $dept_members = $conn->query("SELECT id FROM members WHERE department='$em_dept' AND is_approved=1");
        if ($dept_members) { while ($dm = $dept_members->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$dm['id']}, 'member', '$notif_msg')"); } }
        $pastors = $conn->query("SELECT id FROM pastors");
        if ($pastors) { while ($pst = $pastors->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$pst['id']}, 'pastor', '$notif_msg')"); } }
        $admins = $conn->query("SELECT id FROM admins");
        if ($admins) { while ($adm = $admins->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$adm['id']}, 'admin', '$notif_msg')"); } }
        $conn->query("UPDATE sport_matches SET end_notification_sent = 1 WHERE id = $em_id");
    }
}
?>