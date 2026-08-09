<?php
$servername = "localhost";
$username = "root";
$password = ""; // Default XAMPP password is empty
$dbname = "munyari_church";

// Set timezone to Kenya
date_default_timezone_set('Africa/Nairobi');

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->query("SET time_zone = '+03:00'"); // Africa/Nairobi offset

// Background lazy check for newly started prayers
$now_str = date('Y-m-d H:i:00');
$started_prayers = $conn->query("SELECT * FROM prayer_schedules WHERE notification_sent = 0 AND CONCAT(prayer_date, ' ', prayer_time) <= '$now_str'");

if ($started_prayers && $started_prayers->num_rows > 0) {
    while ($p = $started_prayers->fetch_assoc()) {
        $p_id = $p['id'];
        $p_dept = $p['department'];
        $p_title = $conn->real_escape_string($p['title']);
        $p_loc = $conn->real_escape_string($p['location']);

        $notif_msg = "🙏 The prayer session \"$p_title\" has just begun at $p_loc. Join us in prayer now!";

        // Notify dept members
        $dept_members = $conn->query("SELECT id FROM members WHERE department='$p_dept' AND is_approved=1");
        if ($dept_members) {
            while ($dm = $dept_members->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$dm['id']}, 'member', '$notif_msg')");
            }
        }
        
        // Notify Pastors
        $pastors = $conn->query("SELECT id FROM pastors");
        if ($pastors) {
            while ($pst = $pastors->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$pst['id']}, 'pastor', '$notif_msg')");
            }
        }

        // Notify Admins
        $admins = $conn->query("SELECT id FROM admins");
        if ($admins) {
            while ($adm = $admins->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$adm['id']}, 'admin', '$notif_msg')");
            }
        }

        // Mark as sent
        $conn->query("UPDATE prayer_schedules SET notification_sent = 1 WHERE id = $p_id");
    }
}

// Background lazy check for newly ended prayers
$ended_prayers = $conn->query("SELECT * FROM prayer_schedules WHERE end_notification_sent = 0 AND end_time IS NOT NULL AND CONCAT(prayer_date, ' ', end_time) <= '$now_str'");

if ($ended_prayers && $ended_prayers->num_rows > 0) {
    while ($ep = $ended_prayers->fetch_assoc()) {
        $ep_id = $ep['id'];
        $ep_dept = $ep['department'];
        $ep_title = $conn->real_escape_string($ep['title']);

        $notif_msg = "🏁 The prayer session \"$ep_title\" has ended. Thank you for participating!";

        // Notify dept members
        $dept_members = $conn->query("SELECT id FROM members WHERE department='$ep_dept' AND is_approved=1");
        if ($dept_members) {
            while ($dm = $dept_members->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$dm['id']}, 'member', '$notif_msg')");
            }
        }
        
        // Notify Pastors
        $pastors = $conn->query("SELECT id FROM pastors");
        if ($pastors) {
            while ($pst = $pastors->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$pst['id']}, 'pastor', '$notif_msg')");
            }
        }

        // Notify Admins
        $admins = $conn->query("SELECT id FROM admins");
        if ($admins) {
            while ($adm = $admins->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$adm['id']}, 'admin', '$notif_msg')");
            }
        }

        // Mark as sent and status as ended
        $conn->query("UPDATE prayer_schedules SET end_notification_sent = 1 WHERE id = $ep_id");
    }
}

// Background lazy check for newly started sport matches
$started_matches = $conn->query("SELECT * FROM sport_matches WHERE notification_sent = 0 AND CONCAT(match_date, ' ', match_time) <= '$now_str'");
if ($started_matches && $started_matches->num_rows > 0) {
    while ($sm = $started_matches->fetch_assoc()) {
        $sm_id = $sm['id'];
        $sm_dept = $sm['department'];
        $sm_title = $conn->real_escape_string($sm['title']);
        $sm_loc = $conn->real_escape_string($sm['location']);
        $notif_msg = "⚽ The match \"$sm_title\" has just kicked off at $sm_loc. Go team!";
        $dept_members = $conn->query("SELECT id FROM members WHERE department='$sm_dept' AND is_approved=1");
        if ($dept_members) { while ($dm = $dept_members->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$dm['id']}, 'member', '$notif_msg')"); } }
        $pastors = $conn->query("SELECT id FROM pastors");
        if ($pastors) { while ($pst = $pastors->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$pst['id']}, 'pastor', '$notif_msg')"); } }
        $admins = $conn->query("SELECT id FROM admins");
        if ($admins) { while ($adm = $admins->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$adm['id']}, 'admin', '$notif_msg')"); } }
        $conn->query("UPDATE sport_matches SET notification_sent = 1 WHERE id = $sm_id");
    }
}

// Background lazy check for newly ended sport matches
$ended_matches = $conn->query("SELECT * FROM sport_matches WHERE end_notification_sent = 0 AND end_time IS NOT NULL AND CONCAT(match_date, ' ', end_time) <= '$now_str'");
if ($ended_matches && $ended_matches->num_rows > 0) {
    while ($em = $ended_matches->fetch_assoc()) {
        $em_id = $em['id'];
        $em_dept = $em['department'];
        $em_title = $conn->real_escape_string($em['title']);
        $notif_msg = "🏁 The match \"$em_title\" has ended. Great game everyone!";
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
