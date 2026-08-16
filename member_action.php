<?php
session_start();
require_once 'db_connect.php';
require_once 'role_departments.php';

function member_has_role($church_role_string, $target_role) {
    $roles = array_map('normalize_role_name', explode(',', str_replace('&', ',', $church_role_string ?? '')));
    return in_array(normalize_role_name($target_role), $roles, true);
}

function get_member_roles($church_role_string) {
    return array_filter(array_map('normalize_role_name', explode(',', str_replace('&', ',', $church_role_string ?? ''))));
}

if (!isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}

$member_id = $_SESSION['member_id'];

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // Book Appointment
    if ($action === 'book_appointment' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $pastor_id = (int)$_POST['pastor_id'];
        $datetime = $conn->real_escape_string($_POST['datetime']);
        $reason = $conn->real_escape_string($_POST['reason']);
        
        $current_datetime = date('Y-m-d\TH:i');
        if ($datetime < $current_datetime) {
            header("Location: member_dashboard.php?tab=contact_pastor&error=You cannot book an appointment in the past.");
            exit();
        }
        
        $conn->query("INSERT INTO appointments (member_id, pastor_id, appointment_date, reason, status) VALUES ($member_id, $pastor_id, '$datetime', '$reason', 'Pending')");
        
        header("Location: member_dashboard.php?tab=contact_pastor&success=Appointment requested successfully");
        exit();
    }
    
    // Mark notifications as read
    elseif ($action === 'read_notifications') {
        $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $member_id AND user_type = 'member'");
        header("Location: member_dashboard.php?tab=notifications");
        exit();
    }
    
    // Record Financials
    elseif ($action === 'record_finance' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        $roles = get_member_roles($member_query['church_role'] ?? '');
        
        $treasurer_departments = [
            'treasurer' => 'General Church',
            'youth treasurer' => 'Youths',
            'women treasurer' => 'Womens Ministry',
            'elder treasurer' => 'Elders',
            'sunday school treasurer' => 'Sunday School',
            'building treasurer' => 'Building & Construction'
        ];
        
        $allowed_depts = [];
        foreach ($roles as $r) {
            if (array_key_exists($r, $treasurer_departments)) {
                $allowed_depts[] = $treasurer_departments[$r];
            }
        }
        
        $submitted_dept = $_POST['department'] ?? '';
        
        // Use alias-aware matching so 'Youths' and 'Youth Ministry' both work
        $dept_is_allowed = false;
        foreach ($allowed_depts as $allowed) {
            if (department_matches($submitted_dept, $allowed)) {
                $dept_is_allowed = true;
                break;
            }
        }
        // Also allow if member is a general-church leader (they record for any dept)
        $is_general_leader = false;
        $general_leader_roles = ['treasurer', 'general church secretary', 'vice church secretary', 'senior church elder'];
        foreach ($roles as $r) {
            if (in_array($r, $general_leader_roles, true)) {
                $is_general_leader = true;
                break;
            }
        }
        if ($dept_is_allowed || ($is_general_leader && !empty($submitted_dept))) {
            $amount = floatval($_POST['amount']);
            $description = $conn->real_escape_string(trim($_POST['description'] ?? ''));
            $record_date = $conn->real_escape_string($_POST['record_date']);
            $esc_dept = $conn->real_escape_string($submitted_dept);
            $conn->query("INSERT INTO financial_records (pastor_id, department, amount, description, record_date, recorded_by) VALUES (0, '$esc_dept', $amount, '$description', '$record_date', $member_id)");
            
            // Notify pastors and admins
            $member_name = $conn->real_escape_string($member_query['first_name'] . ' ' . $member_query['last_name']);
            $notif_text = "$submitted_dept Treasurer ($member_name) recorded a collection of KSh " . number_format($amount, 2);
            $notif_msg = $conn->real_escape_string($notif_text);
            $pastors_q = $conn->query("SELECT id FROM pastors");
            while($p = $pastors_q->fetch_assoc()) {
                $pid = $p['id'];
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($pid, 'pastor', '$notif_msg')");
            }
            $admins_q = $conn->query("SELECT id FROM admins");
            if ($admins_q) {
                while($a = $admins_q->fetch_assoc()) {
                    $aid = $a['id'];
                    $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($aid, 'admin', '$notif_msg')");
                }
            }
            
            header("Location: member_dashboard.php?tab=financials&treasurer_dept=" . urlencode($submitted_dept) . "&success=Financial record saved successfully");
            exit();
        } else {
            // Debug info to understand what failed
            $debug = "Role: " . implode(', ', $roles) . " | AllowedDepts: " . implode(', ', $allowed_depts) . " | Submitted: $submitted_dept";
            header("Location: member_dashboard.php?tab=financials&error=" . urlencode("Access denied. " . $debug));
            exit();
        }
    }

    // Appoint Leader
    elseif ($action === 'appoint_leader' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $target_member_id = (int)$_POST['target_member_id'];
        $proposed_role = $conn->real_escape_string($_POST['proposed_role']);
        
        // Ensure the current member is actually a leader
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        $member_roles_list = get_member_roles($member_query['church_role'] ?? '');
        
        $leader_departments = [
            // Youths - all variants
            'youth chairman'           => 'Youths',
            'youth chairlady'          => 'Youths',
            'youth chairperson'        => 'Youths',
            'vice youth chairman'      => 'Youths',
            'vice youth chairlady'     => 'Youths',
            'vice youth chairperson'   => 'Youths',
            // Womens Ministry - all variants
            'women chairman'           => 'Womens Ministry',
            'women chairlady'          => 'Womens Ministry',
            'women chairperson'        => 'Womens Ministry',
            'vice women chairman'      => 'Womens Ministry',
            'vice women chairlady'     => 'Womens Ministry',
            'vice women chairperson'   => 'Womens Ministry',
            // Elders - all variants
            'elder chairman'           => 'Elders',
            'elder chairlady'          => 'Elders',
            'elder chairperson'        => 'Elders',
            'vice elder chairman'      => 'Elders',
            'vice elder chairlady'     => 'Elders',
            'vice elder chairperson'   => 'Elders',
            // Sunday School - all variants
            'sunday school patron'     => 'Sunday School',
            'sunday school chairman'   => 'Sunday School',
            'sunday school chairlady'  => 'Sunday School',
            'sunday school chairperson'=> 'Sunday School',
            'vice sunday school patron'=> 'Sunday School',
            'vice sunday school chairman'  => 'Sunday School',
            'vice sunday school chairlady' => 'Sunday School',
            'vice sunday school chairperson' => 'Sunday School',
        ];

        
        $managed_dept = null;
        foreach ($member_roles_list as $r) {
            if (array_key_exists($r, $leader_departments)) {
                $managed_dept = $leader_departments[$r];
                break;
            }
        }
        
        if ($managed_dept !== null) {
            if (!role_allowed_for_department($_POST['proposed_role'], $managed_dept)) {
                header("Location: member_dashboard.php?tab=appoint_leaders&error=Graduands Secretary and Sports Secretary are only available for Youths and Sunday School departments");
                exit();
            }
            $dept_filter = department_match_sql($conn, 'department', $managed_dept);
            $target_check = $conn->query("SELECT id, first_name, last_name FROM members WHERE id = $target_member_id AND $dept_filter AND is_approved = 1 AND (church_role IS NULL OR church_role = '' OR LOWER(TRIM(church_role)) = 'member') AND (pending_role IS NULL OR pending_role = '')")->fetch_assoc();

            if (!$target_check) {
                header("Location: member_dashboard.php?tab=appoint_leaders&error=You can only assign approved members in your own department who do not already have a role or pending role");
                exit();
            }
            
            // Set pending role for target member, tracking who proposed it
            $conn->query("UPDATE members SET pending_role = '$proposed_role', pending_proposed_by = $member_id WHERE id = $target_member_id");
            
            // Get target member name
            $target_name = $target_check['first_name'] . ' ' . $target_check['last_name'];
            
            // Notify pastors — use 'pending role approval' keyword so badge routes to assign_roles
            $chairman_name = $conn->real_escape_string($member_query['first_name'] . ' ' . $member_query['last_name']);
            $pastors_q = $conn->query("SELECT id FROM pastors");
            while($p = $pastors_q->fetch_assoc()) {
                $pid = $p['id'];
                $notif_msg = $conn->real_escape_string("$chairman_name ($managed_dept) has submitted a pending role approval request: $target_name → $proposed_role. Please review under Assign Roles.");
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($pid, 'pastor', '$notif_msg')");
            }
            
            header("Location: member_dashboard.php?tab=appoint_leaders&success=Appointment request sent to Pastor for approval");
            exit();
        } else {
            header("Location: member_dashboard.php?tab=appoint_leaders&error=Unauthorized access");
            exit();
        }
    }
    
    // Post Organized Event
    elseif ($action === 'post_organized_event' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        
        if (member_has_role($member_query['church_role'] ?? '', 'organizing secretary')) {
            $department = $conn->real_escape_string($member_query['department']);
            $title = $conn->real_escape_string($_POST['title']);
            $announcement = $conn->real_escape_string($_POST['announcement']);
            $meet_link = $conn->real_escape_string($_POST['meet_link']);
            $whatsapp_link = $conn->real_escape_string($_POST['whatsapp_link']);
            $attendance_file = null;
            
            if (isset($_FILES['attendance_file']) && $_FILES['attendance_file']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
                $filename = $_FILES['attendance_file']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    $new_filename = 'attendance_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    if (move_uploaded_file($_FILES['attendance_file']['tmp_name'], 'uploads/' . $new_filename)) {
                        $attendance_file = $new_filename;
                    }
                }
            }
            
            $sql = "INSERT INTO organized_events (department, organizer_id, title, announcement, meet_link, whatsapp_link, attendance_file) 
                    VALUES ('$department', $member_id, '$title', '$announcement', '$meet_link', '$whatsapp_link', " . ($attendance_file ? "'$attendance_file'" : "NULL") . ")";
            
            if ($conn->query($sql) === TRUE) {
                // Notify all active members in the department
                $org_name = htmlspecialchars($member_query['first_name'] . ' ' . $member_query['last_name']);
                $notif_msg = $conn->real_escape_string("New event posted by Organizing Secretary $org_name: $title");
                
                $dept_members = $conn->query("SELECT id FROM members WHERE department = '$department' AND is_approved = 1 AND id != $member_id");
                while ($dm = $dept_members->fetch_assoc()) {
                    $did = $dm['id'];
                    $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($did, 'member', '$notif_msg')");
                }
                
                header("Location: member_dashboard.php?tab=organized_events&success=Event posted successfully!");
            } else {
                header("Location: member_dashboard.php?tab=organized_events&error=Failed to post event");
            }
            exit();
        } else {
            header("Location: member_dashboard.php?error=Unauthorized access");
            exit();
        }
    }
    
    // Discipline Master: Add Fine Template
    elseif ($action === 'add_fine_template' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        
        if (member_has_role($member_query['church_role'] ?? '', 'discipline master')) {
            $dept = $conn->real_escape_string($member_query['department']);
            $fine_name = $conn->real_escape_string($_POST['fine_name']);
            $amount = floatval($_POST['amount']);
            
            $conn->query("INSERT INTO fine_templates (department, fine_name, amount) VALUES ('$dept', '$fine_name', $amount)");
            header("Location: member_dashboard.php?tab=discipline&success=Fine rule added");
            exit();
        }
    }
    
    // Discipline Master: Delete Fine Template
    elseif ($action === 'delete_fine_template' && isset($_GET['id'])) {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        if (member_has_role($member_query['church_role'] ?? '', 'discipline master')) {
            $id = (int)$_GET['id'];
            $conn->query("DELETE FROM fine_templates WHERE id = $id");
            header("Location: member_dashboard.php?tab=discipline&success=Fine rule removed");
            exit();
        }
    }
    
    // Discipline Master: Issue Fine
    elseif ($action === 'issue_fine' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        if (member_has_role($member_query['church_role'] ?? '', 'discipline master')) {
            $dept = $conn->real_escape_string($member_query['department']);
            $target_id = (int)$_POST['member_id'];
            $template_id = (int)$_POST['fine_template_id'];
            
            $template = $conn->query("SELECT fine_name, amount FROM fine_templates WHERE id = $template_id")->fetch_assoc();
            if ($template) {
                $reason = $conn->real_escape_string($template['fine_name']);
                $amount = $template['amount'];
                
                $conn->query("INSERT INTO member_fines (member_id, discipline_master_id, department, fine_reason, amount) 
                              VALUES ($target_id, $member_id, '$dept', '$reason', $amount)");
                
                // Notify Member
                $msg = $conn->real_escape_string("You have been fined KSh $amount for '$reason' by the Discipline Master. Please pay by the next meeting.");
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($target_id, 'member', '$msg')");
                
                header("Location: member_dashboard.php?tab=discipline&success=Fine issued successfully");
                exit();
            }
        }
    }
    
    // Member: Initiate Fine Payment
    elseif ($action === 'initiate_fine_payment' && isset($_GET['id'])) {
        $fine_id = (int)$_GET['id'];
        
        // Ensure the fine belongs to the member
        $fine = $conn->query("SELECT * FROM member_fines WHERE id = $fine_id AND member_id = $member_id")->fetch_assoc();
        if ($fine && $fine['status'] == 'Unpaid') {
            $conn->query("UPDATE member_fines SET status = 'Pending Verification' WHERE id = $fine_id");
            
            // Find treasurer and notify
            $dept = $conn->real_escape_string($fine['department']);
            $treasurers = $conn->query("SELECT id FROM members WHERE department = '$dept' AND church_role LIKE '%treasurer%'");
            while ($t = $treasurers->fetch_assoc()) {
                $msg = $conn->real_escape_string("A member in $dept has initiated a fine payment. Please verify in your Financial Records tab.");
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$t['id']}, 'member', '$msg')");
            }
            
            header("Location: member_dashboard.php?tab=my_fines&success=Payment initiated. Pending Treasurer verification.");
            exit();
        }
    }
    
    // Treasurer: Mark Fine Paid
    elseif ($action === 'mark_fine_paid' && isset($_GET['id'])) {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        $is_treasurer = false;
        foreach (get_member_roles($member_query['church_role'] ?? '') as $role) {
            if (strpos($role, 'treasurer') !== false) {
                $is_treasurer = true;
                break;
            }
        }
        if ($is_treasurer) {
            $fine_id = (int)$_GET['id'];
            $conn->query("UPDATE member_fines SET status = 'Paid', paid_at = CURRENT_TIMESTAMP WHERE id = $fine_id");
            
            $fine = $conn->query("SELECT member_id, fine_reason, amount, department FROM member_fines WHERE id = $fine_id")->fetch_assoc();
            if ($fine) {
                $msg = $conn->real_escape_string("Your fine for '{$fine['fine_reason']}' has been verified and cleared by the Treasurer.");
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$fine['member_id']}, 'member', '$msg')");
                
                // Add the fine amount to the financial records!
                $dept = $conn->real_escape_string($fine['department']);
                $desc = $conn->real_escape_string("Disciplinary Fine: " . $fine['fine_reason']);
                $amount = (float)$fine['amount'];
                $conn->query("INSERT INTO financial_records (department, amount, record_date, description) VALUES ('$dept', $amount, CURRENT_DATE(), '$desc')");
            }
            header("Location: member_dashboard.php?tab=financials&success=Fine payment verified and added to records");
            exit();
        }
    }
    
    // Treasurer: Remind Member to Pay Fine at Next Meeting
    elseif ($action === 'remind_fine_meeting' && isset($_GET['id'])) {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        $is_treasurer = false;
        foreach (get_member_roles($member_query['church_role'] ?? '') as $role) {
            if (strpos($role, 'treasurer') !== false) {
                $is_treasurer = true;
                break;
            }
        }
        if ($is_treasurer) {
            $fine_id = (int)$_GET['id'];
            
            // Mark as reminded
            $conn->query("UPDATE member_fines SET reminded_for_meeting = 1 WHERE id = $fine_id");
            
            // Notify the member
            $fine = $conn->query("SELECT member_id, fine_reason, amount FROM member_fines WHERE id = $fine_id")->fetch_assoc();
            if ($fine) {
                $amount_fmt = number_format($fine['amount'], 2);
                $msg = $conn->real_escape_string("Treasurer Reminder: You have an unpaid fine of KSh $amount_fmt for '{$fine['fine_reason']}'. You are requested to bring payment to the next department meeting.");
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$fine['member_id']}, 'member', '$msg')");
            }
            
            header("Location: member_dashboard.php?tab=financials&success=Reminder sent successfully");
            exit();
        }
    }
    
    // Discipline Master: Report Member
    elseif ($action === 'report_member' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        if (member_has_role($member_query['church_role'] ?? '', 'discipline master')) {
            $dept = $conn->real_escape_string($member_query['department']);
            $target_id = (int)$_POST['member_id'];
            $reason = $conn->real_escape_string($_POST['reason']);
            
            $conn->query("INSERT INTO member_reports (reported_member_id, reported_by_id, department, reason) 
                          VALUES ($target_id, $member_id, '$dept', '$reason')");
            
            // Find chairman/chairlady and notify
            $leaders = $conn->query("SELECT id FROM members WHERE department = '$dept' AND (church_role LIKE '%chairman%' OR church_role LIKE '%chairlady%')");
            while ($l = $leaders->fetch_assoc()) {
                $msg = "The Discipline Master has reported a member. Please review in your dashboard.";
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$l['id']}, 'member', '$msg')");
            }
            
            $title = ($dept === 'Womens Ministry') ? 'Chairlady' : 'Chairman';
            header("Location: member_dashboard.php?tab=discipline&success=Member reported to $title");
            exit();
        }
    }
    
    // Chairman: Forward Report to Pastor
    elseif ($action === 'forward_report' && isset($_GET['id'])) {
        $report_id = (int)$_GET['id'];
        $conn->query("UPDATE member_reports SET status = 'Forwarded to Pastor' WHERE id = $report_id");
        
        $pastors = $conn->query("SELECT id FROM pastors");
        while ($p = $pastors->fetch_assoc()) {
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$p['id']}, 'pastor', 'A chairman has forwarded a disciplinary report to you.')");
        }
        
        header("Location: member_dashboard.php?tab=manage_department&success=Report forwarded to Pastor");
        exit();
    }
    
    // Chairman: Resolve Report Locally
    elseif ($action === 'resolve_report' && isset($_GET['id'])) {
        $report_id = (int)$_GET['id'];
        $conn->query("UPDATE member_reports SET status = 'Resolved locally' WHERE id = $report_id");
        
        header("Location: member_dashboard.php?tab=manage_department&success=Report marked as resolved");
        exit();
    }
    
    // Graduands Secretary: Add to Graduation List
    elseif ($action === 'add_graduand' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        if (member_has_role($member_query['church_role'] ?? '', 'graduands secretary')) {
            $dept = $conn->real_escape_string($member_query['department']);
            $target_id = (int)$_POST['member_id'];
            $year = date('Y');
            
            $conn->query("INSERT INTO graduation_list (member_id, department, graduation_year, added_by) 
                          VALUES ($target_id, '$dept', $year, $member_id)");
                          
            // Notify target member
            $notif_msg = "Congratulations! You have been added to the graduation roster for $year.";
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($target_id, 'member', '$notif_msg')");
            
            // Fetch target member details for broadcasting
            $target = $conn->query("SELECT first_name, last_name FROM members WHERE id = $target_id")->fetch_assoc();
            $target_name = $conn->real_escape_string($target['first_name'] . ' ' . $target['last_name']);
            
            // Notify Chairman
            $chair_msg = "$target_name has been added to the graduation roster for $year.";
            $chairmen = $conn->query("SELECT id FROM members WHERE department = '$dept' AND church_role LIKE '%chairman%'");
            while ($c = $chairmen->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$c['id']}, 'member', '$chair_msg')");
            }
            
            // Notify all other department members
            $dept_msg = "$target_name is graduating this year ($year)!";
            $members = $conn->query("SELECT id FROM members WHERE department = '$dept' AND id != $target_id AND is_approved = 1 AND church_role NOT LIKE '%chairman%'");
            while ($m = $members->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$m['id']}, 'member', '$dept_msg')");
            }
                          
            header("Location: member_dashboard.php?tab=graduation_panel&success=Member added to graduation roster");
            exit();
        }
    }
    
    // Graduands Secretary: Remove from Graduation List
    elseif ($action === 'remove_graduand' && isset($_GET['id'])) {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        if (member_has_role($member_query['church_role'] ?? '', 'graduands secretary')) {
            $grad_id = (int)$_GET['id'];
            $conn->query("DELETE FROM graduation_list WHERE id = $grad_id");
            
            header("Location: member_dashboard.php?tab=graduation_panel&success=Member removed from roster");
            exit();
        }
    }
    
    // Graduands Secretary: Broadcast Announcement
    elseif ($action === 'graduation_announcement' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        if (member_has_role($member_query['church_role'] ?? '', 'graduands secretary')) {
            $dept = $conn->real_escape_string($member_query['department']);
            $raw_msg = $_POST['message'];
            
            // Post to department announcements
            $announcement_msg = $conn->real_escape_string("[GRADUATION UPDATE] " . $raw_msg);
            $conn->query("INSERT INTO department_announcements (department, secretary_id, message) VALUES ('$dept', $member_id, '$announcement_msg')");
            
            // Notify Pastors
            $preview = $conn->real_escape_string(substr($raw_msg, 0, 60) . '...');
            $notif_msg = "Graduation Update ($dept): $preview";
            
            $pastors = $conn->query("SELECT id FROM pastors");
            while ($p = $pastors->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$p['id']}, 'pastor', '$notif_msg')");
            }
            
            // Notify Admins
            $admins = $conn->query("SELECT id FROM admins");
            if ($admins) {
                while ($a = $admins->fetch_assoc()) {
                    $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$a['id']}, 'admin', '$notif_msg')");
                }
            }
            
            // Notify all department members
            $dept_members = $conn->query("SELECT id FROM members WHERE department = '$dept' AND is_approved = 1 AND id != $member_id");
            while ($dm = $dept_members->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$dm['id']}, 'member', '$notif_msg')");
            }
            
            header("Location: member_dashboard.php?tab=graduation_panel&success=Graduation announcement broadcasted successfully");
            exit();
        }
    }

    // Prayer Coordinator: Schedule a Prayer Session
    elseif ($action === 'schedule_prayer' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        if (member_has_role($member_query['church_role'] ?? '', 'prayer coordinator')) {
            $dept = $conn->real_escape_string($member_query['department']);
            $title = $conn->real_escape_string($_POST['title']);
            $description = $conn->real_escape_string($_POST['description'] ?? '');
            $prayer_date = $conn->real_escape_string($_POST['prayer_date']);
            $prayer_time = $conn->real_escape_string($_POST['prayer_time']);
            $end_time = isset($_POST['end_time']) && !empty($_POST['end_time']) ? "'" . $conn->real_escape_string($_POST['end_time']) . "'" : "NULL";
            $location = $conn->real_escape_string($_POST['location'] ?? 'Church Main Hall');

            $conn->query("INSERT INTO prayer_schedules (department, coordinator_id, title, description, prayer_date, prayer_time, end_time, location)
                          VALUES ('$dept', $member_id, '$title', '$description', '$prayer_date', '$prayer_time', $end_time, '$location')");

            // Format notification
            $notif_msg = "🙏 Prayer Session Scheduled: \"$title\" on " . date('M j, Y', strtotime($prayer_date)) . " at " . date('g:i A', strtotime($prayer_time)) . " — $location";

            // Notify all dept members
            $dept_members = $conn->query("SELECT id FROM members WHERE department='$dept' AND is_approved=1 AND id != $member_id");
            while ($dm = $dept_members->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$dm['id']}, 'member', '$notif_msg')");
            }
            // Notify Pastors
            $pastors = $conn->query("SELECT id FROM pastors");
            while ($p = $pastors->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$p['id']}, 'pastor', '$notif_msg')");
            }
            // Notify Admins
            $admins = $conn->query("SELECT id FROM admins");
            if ($admins) { while ($a = $admins->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$a['id']}, 'admin', '$notif_msg')"); } }

            header("Location: member_dashboard.php?tab=prayer_panel&success=Prayer session scheduled and department notified");
            exit();
        }
    }

    // Prayer Coordinator: Add Custom Prayer Item
    elseif ($action === 'add_prayer_item' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        if (member_has_role($member_query['church_role'] ?? '', 'prayer coordinator')) {
            $dept = $conn->real_escape_string($member_query['department']);
            $title = $conn->real_escape_string($_POST['title']);
            $description = $conn->real_escape_string($_POST['description'] ?? '');
            $category = $conn->real_escape_string($_POST['category'] ?? 'General');

            $conn->query("INSERT INTO prayer_items (department, coordinator_id, title, description, category)
                          VALUES ('$dept', $member_id, '$title', '$description', '$category')");

            $notif_msg = "🙏 New Prayer Item Added: \"$title\" (Category: $category) — Check your Prayer Board.";

            // Notify dept members
            $dept_members = $conn->query("SELECT id FROM members WHERE department='$dept' AND is_approved=1 AND id != $member_id");
            while ($dm = $dept_members->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$dm['id']}, 'member', '$notif_msg')");
            }
            // Notify Pastors
            $pastors = $conn->query("SELECT id FROM pastors");
            while ($p = $pastors->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$p['id']}, 'pastor', '$notif_msg')");
            }
            // Notify Admins
            $admins = $conn->query("SELECT id FROM admins");
            if ($admins) { while ($a = $admins->fetch_assoc()) { $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$a['id']}, 'admin', '$notif_msg')"); } }

            header("Location: member_dashboard.php?tab=prayer_panel&success=Prayer item added and department notified");
            exit();
        }
    }

    // Prayer Coordinator: Mark Prayer Item as Answered
    elseif ($action === 'mark_prayer_answered' && isset($_GET['id'])) {
        $member_query = $conn->query("SELECT church_role FROM members WHERE id = $member_id")->fetch_assoc();
        if (member_has_role($member_query['church_role'] ?? '', 'prayer coordinator')) {
            $item_id = (int)$_GET['id'];
            $conn->query("UPDATE prayer_items SET status='Answered' WHERE id=$item_id AND coordinator_id=$member_id");
            header("Location: member_dashboard.php?tab=prayer_panel&success=Prayer item marked as answered");
            exit();
        }
    }

    // Prayer Coordinator: Delete Prayer Item
    elseif ($action === 'delete_prayer_item' && isset($_GET['id'])) {
        $member_query = $conn->query("SELECT church_role FROM members WHERE id = $member_id")->fetch_assoc();
        if (member_has_role($member_query['church_role'] ?? '', 'prayer coordinator')) {
            $item_id = (int)$_GET['id'];
            $conn->query("DELETE FROM prayer_items WHERE id=$item_id AND coordinator_id=$member_id");
            header("Location: member_dashboard.php?tab=prayer_panel&success=Prayer item removed");
            exit();
        }
    }

    // Prayer Coordinator: Delete Prayer Schedule
    elseif ($action === 'delete_prayer_schedule' && isset($_GET['id'])) {
        $member_query = $conn->query("SELECT church_role FROM members WHERE id = $member_id")->fetch_assoc();
        if (member_has_role($member_query['church_role'] ?? '', 'prayer coordinator')) {
            $sched_id = (int)$_GET['id'];
            $conn->query("DELETE FROM prayer_schedules WHERE id=$sched_id AND coordinator_id=$member_id");
            header("Location: member_dashboard.php?tab=prayer_panel&success=Prayer session removed");
            exit();
        }
    }

    // Sport Secretary: Schedule a Match
    elseif ($action === 'schedule_match' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        if (member_has_role($member_query['church_role'] ?? '', 'sport secretary') || member_has_role($member_query['church_role'] ?? '', 'sports secretary')) {
            $dept = $conn->real_escape_string($member_query['department']);
            $title = $conn->real_escape_string($_POST['title']);
            $description = $conn->real_escape_string($_POST['description'] ?? '');
            $match_date = $conn->real_escape_string($_POST['match_date']);
            $match_time = $conn->real_escape_string($_POST['match_time']);
            $end_time = isset($_POST['end_time']) && !empty($_POST['end_time']) ? "'" . $conn->real_escape_string($_POST['end_time']) . "'" : "NULL";
            $location = $conn->real_escape_string($_POST['location'] ?? 'Church Grounds');

            $conn->query("INSERT INTO sport_matches (department, secretary_id, title, description, match_date, match_time, end_time, location)
                          VALUES ('$dept', $member_id, '$title', '$description', '$match_date', '$match_time', $end_time, '$location')");

            $notif_msg = "⚽ Match Scheduled: \"$title\" on " . date('M j, Y', strtotime($match_date)) . " at " . date('g:i A', strtotime($match_time)) . " — $location";

            // Notify dept members
            $dept_members = $conn->query("SELECT id FROM members WHERE department='$dept' AND is_approved=1 AND id != $member_id");
            while ($dm = $dept_members->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$dm['id']}, 'member', '$notif_msg')");
            }
            // Notify Pastors
            $pastors = $conn->query("SELECT id FROM pastors");
            while ($pst = $pastors->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$pst['id']}, 'pastor', '$notif_msg')");
            }
            // Notify Admins
            $admins = $conn->query("SELECT id FROM admins");
            while ($adm = $admins->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$adm['id']}, 'admin', '$notif_msg')");
            }
            header("Location: member_dashboard.php?tab=manage_sport&success=Match scheduled and members notified!");
            exit();
        }
    }

    // Sport Secretary: Post Announcement
    elseif ($action === 'post_sport_announcement' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        if (member_has_role($member_query['church_role'] ?? '', 'sport secretary') || member_has_role($member_query['church_role'] ?? '', 'sports secretary')) {
            $dept = $conn->real_escape_string($member_query['department']);
            $message = $conn->real_escape_string(trim($_POST['message']));
            if (!empty($message)) {
                $conn->query("INSERT INTO sport_announcements (department, secretary_id, message) VALUES ('$dept', $member_id, '$message')");
                $preview = substr($message, 0, 80) . (strlen($message) > 80 ? '...' : '');
                $notif_msg = "📢 Sport Announcement: " . $conn->real_escape_string($preview);
                $dept_members = $conn->query("SELECT id FROM members WHERE department='$dept' AND is_approved=1 AND id != $member_id");
                while ($dm = $dept_members->fetch_assoc()) {
                    $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$dm['id']}, 'member', '$notif_msg')");
                }
                $pastors = $conn->query("SELECT id FROM pastors");
                while ($pst = $pastors->fetch_assoc()) {
                    $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$pst['id']}, 'pastor', '$notif_msg')");
                }
                $admins = $conn->query("SELECT id FROM admins");
                while ($adm = $admins->fetch_assoc()) {
                    $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$adm['id']}, 'admin', '$notif_msg')");
                }
            }
            header("Location: member_dashboard.php?tab=manage_sport&success=Announcement posted successfully!");
            exit();
        }
    }

    // Sport Secretary: Delete Match
    elseif ($action === 'delete_sport_match' && isset($_GET['id'])) {
        $member_query = $conn->query("SELECT church_role FROM members WHERE id = $member_id")->fetch_assoc();
        if (member_has_role($member_query['church_role'] ?? '', 'sport secretary') || member_has_role($member_query['church_role'] ?? '', 'sports secretary')) {
            $match_id = (int)$_GET['id'];
            $conn->query("DELETE FROM sport_matches WHERE id=$match_id AND secretary_id=$member_id");
            exit();
        }
    }

    // Sport Secretary: Upload Match Results
    elseif ($action === 'upload_match_results' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['match_id'])) {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        if (member_has_role($member_query['church_role'] ?? '', 'sport secretary') || member_has_role($member_query['church_role'] ?? '', 'sports secretary')) {
            $match_id = (int)$_POST['match_id'];
            $score_result = $conn->real_escape_string($_POST['score_result']);
            $dept = $conn->real_escape_string($member_query['department']);
            
            // Check if match belongs to this secretary/department
            $match_check = $conn->query("SELECT * FROM sport_matches WHERE id = $match_id AND department = '$dept'")->fetch_assoc();
            if ($match_check) {
                $image_name = null;
                if (isset($_FILES['result_image']) && $_FILES['result_image']['error'] == 0) {
                    $ext = pathinfo($_FILES['result_image']['name'], PATHINFO_EXTENSION);
                    $allowed = ['jpg','jpeg','png','gif','webp'];
                    if (in_array(strtolower($ext), $allowed)) {
                        $image_name = 'match_res_' . time() . '_' . rand(100,999) . '.' . $ext;
                        move_uploaded_file($_FILES['result_image']['tmp_name'], 'uploads/' . $image_name);
                    }
                }
                
                $img_sql = $image_name ? ", result_image = '$image_name'" : "";
                $conn->query("UPDATE sport_matches SET score_result = '$score_result' $img_sql WHERE id = $match_id");
                
                // Notification
                $title = $conn->real_escape_string($match_check['title']);
                $notif_msg = "🏆 Match Results Posted! $title: $score_result";
                
                $dept_members = $conn->query("SELECT id FROM members WHERE department='$dept' AND is_approved=1 AND id != $member_id");
                while ($dm = $dept_members->fetch_assoc()) {
                    $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$dm['id']}, 'member', '$notif_msg')");
                }
                $pastors = $conn->query("SELECT id FROM pastors");
                while ($pst = $pastors->fetch_assoc()) {
                    $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$pst['id']}, 'pastor', '$notif_msg')");
                }
                
                header("Location: member_dashboard.php?tab=manage_sport&success=Match results uploaded successfully");
                exit();
            }
        }
    }
}

header("Location: member_dashboard.php");
exit();
?>
