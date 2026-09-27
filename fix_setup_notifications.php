<?php
require_once 'C:\\xampp\\htdocs\\munyari_church\\db_connect.php';

// 1. Fix setup_completed logic in member_dashboard.php
$member_file = 'C:\\xampp\\htdocs\\munyari_church\\member_dashboard.php';
$member_content = file_get_contents($member_file);

// Replace profile picture upload setup_completed logic
$old_pic_logic = 'if (!empty($latest[\'username\']) && !empty($latest[\'profile_picture\']) && $latest[\'profile_picture\'] !== \'default_avatar.png\') {
                    $conn->query("UPDATE members SET setup_completed = 1 WHERE id = $member_id");
                }';
$new_pic_logic = '$is_ss = ($latest[\'department\'] ?? \'\') === \'Sunday School\';
                $has_class = !empty($latest[\'sunday_school_class\']);
                if (!empty($latest[\'username\']) && !empty($latest[\'profile_picture\']) && $latest[\'profile_picture\'] !== \'default_avatar.png\') {
                    if (!$is_ss || ($is_ss && $has_class)) {
                        $conn->query("UPDATE members SET setup_completed = 1 WHERE id = $member_id");
                    }
                }';
$member_content = str_replace($old_pic_logic, $new_pic_logic, $member_content);

// Ensure the SELECT for $latest includes department and sunday_school_class
$member_content = str_replace('SELECT username, profile_picture FROM members', 'SELECT username, profile_picture, department, sunday_school_class FROM members', $member_content);
$member_content = str_replace('SELECT profile_picture FROM members', 'SELECT profile_picture, username, department, sunday_school_class FROM members', $member_content);

// Replace username upload setup_completed logic
$old_uname_logic = 'if (!empty($latest[\'profile_picture\']) && $latest[\'profile_picture\'] !== \'default_avatar.png\') {
        $conn->query("UPDATE members SET setup_completed = 1 WHERE id = $member_id");
    }';
$new_uname_logic = '$is_ss = ($latest[\'department\'] ?? \'\') === \'Sunday School\';
    $has_class = !empty($latest[\'sunday_school_class\']);
    if (!empty($latest[\'profile_picture\']) && $latest[\'profile_picture\'] !== \'default_avatar.png\') {
        if (!$is_ss || ($is_ss && $has_class)) {
            $conn->query("UPDATE members SET setup_completed = 1 WHERE id = $member_id");
        }
    }';
$member_content = str_replace($old_uname_logic, $new_uname_logic, $member_content);

// Update save_ss_class logic to check for completion and send notification
$old_ss_logic = '$conn->query("UPDATE members SET sunday_school_class = \'$ss_safe\' WHERE id = $member_id");
    }
    header("Location: member_dashboard.php?tab=settings&success=Sunday School class saved!");';
$new_ss_logic = '$conn->query("UPDATE members SET sunday_school_class = \'$ss_safe\' WHERE id = $member_id");
        // Check if other steps are done
        $latest = $conn->query("SELECT username, profile_picture FROM members WHERE id = $member_id")->fetch_assoc();
        if (!empty($latest[\'username\']) && !empty($latest[\'profile_picture\']) && $latest[\'profile_picture\'] !== \'default_avatar.png\') {
            $conn->query("UPDATE members SET setup_completed = 1 WHERE id = $member_id");
        }
        
        // Notify Admins and Pastors
        $memberName = $conn->real_escape_string($member[\'first_name\'] . \' \' . $member[\'last_name\']);
        $msg = $conn->real_escape_string("$memberName has selected Sunday School class: $ss_class.");
        
        $admins = $conn->query("SELECT id FROM admins");
        while($a = $admins->fetch_assoc()) {
            $aid = $a[\'id\'];
            $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($aid, \'admin\', \'$msg\', 0, NOW())");
        }
        $pastors = $conn->query("SELECT id FROM pastors");
        while($p = $pastors->fetch_assoc()) {
            $pid = $p[\'id\'];
            $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($pid, \'pastor\', \'$msg\', 0, NOW())");
        }
    }
    header("Location: member_dashboard.php?tab=settings&success=Sunday School class saved!");';
$member_content = str_replace($old_ss_logic, $new_ss_logic, $member_content);

// Update global setup_completed check
$old_global_check = 'if ($has_profile_pic && $has_username && !$setup_completed) {
    $conn->query("UPDATE members SET setup_completed = 1 WHERE id = $member_id");
    $setup_completed = true;
}';
$new_global_check = '$is_ss_member = ($member[\'department\'] ?? \'\') === \'Sunday School\';
$has_ss_class = !empty($member[\'sunday_school_class\']);
if ($has_profile_pic && $has_username && !$setup_completed) {
    if (!$is_ss_member || ($is_ss_member && $has_ss_class)) {
        $conn->query("UPDATE members SET setup_completed = 1 WHERE id = $member_id");
        $setup_completed = true;
    }
}';
$member_content = str_replace($old_global_check, $new_global_check, $member_content);

// Update success alert condition
$member_content = str_replace('<?php if ($has_username && $has_profile_pic): ?>', '<?php if ($has_username && $has_profile_pic && (!$is_ss_member || ($is_ss_member && $has_ss_class))): ?>', $member_content);

file_put_contents($member_file, $member_content);


// 2. Add notification when patron/admin/pastor registers a SS member
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $f) {
    $path = "C:\\xampp\\htdocs\\munyari_church\\$f";
    $content = file_get_contents($path);
    
    $old_insert = '$conn->query("INSERT INTO members (first_name, last_name, phone, address, department, gender, password, sunday_school_class, is_approved, reg_date) VALUES (\'$fn\',\'$ln\',\'$ph\',\'$ad\',\'Sunday School\',\'$gn\',\'$pw\',\'$cl_safe\',1,NOW())");';
    
    $new_insert = '$conn->query("INSERT INTO members (first_name, last_name, phone, address, department, gender, password, sunday_school_class, is_approved, reg_date) VALUES (\'$fn\',\'$ln\',\'$ph\',\'$ad\',\'Sunday School\',\'$gn\',\'$pw\',\'$cl_safe\',1,NOW())");
    $new_member_id = $conn->insert_id;
    if ($new_member_id) {
        $msg = $conn->real_escape_string("Welcome to Sunday School! You have been registered in the class: $cl.");
        $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($new_member_id, \'member\', \'$msg\', 0, NOW())");
    }';
    
    $content = str_replace($old_insert, $new_insert, $content);
    file_put_contents($path, $content);
}

echo "Fixes applied.";
?>
