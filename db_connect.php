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
    // Log the real error, show a safe message
    error_log('DB Connection failed: ' . $conn->connect_error);
    http_response_code(503);
    die(json_encode(['error' => 'Service temporarily unavailable. Please try again later.']));
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