<?php
session_start();
require_once 'db_connect.php';
require_once 'role_departments.php';
require_once 'voice_note_helper.php';
require_once 'organized_event_helper.php';
require_once 'notification_badges.php';

function member_has_role($church_role_string, $target_role) {
    $roles = array_map('normalize_role_name', explode(',', str_replace('&', ',', $church_role_string ?? '')));
    return in_array(normalize_role_name($target_role), $roles, true);
}

function get_member_roles($church_role_string) {
    return array_filter(array_map('normalize_role_name', explode(',', str_replace('&', ',', $church_role_string ?? ''))));
}

function member_can_manage_disciplinary_report($member, $department) {
    if (!department_matches($member['department'] ?? '', $department)) {
        return false;
    }
    foreach (get_member_roles($member['church_role'] ?? '') as $role) {
        if (preg_match('/\\bchair(?:man|lady|person)\\b/i', $role)) {
            return true;
        }
    }
    return false;
}

function member_can_manage_fine_department($member, $department) {
    foreach (preg_split('/\s*,\s*/', (string)($member['church_role'] ?? '')) as $assigned_role) {
        if (normalize_role_name($assigned_role) !== 'discipline master') {
            continue;
        }
        $role_department = trim((string)($member['department'] ?? ''));
        if (preg_match('/\(([^)]+)\)/', $assigned_role, $role_context)
            && strtolower(trim($role_context[1])) !== 'subsidiary') {
            $role_department = trim($role_context[1]);
        }
        if (department_matches($department, $role_department)) {
            return true;
        }
    }
    return false;
}

function member_action_insert_notification($conn, $user_id, $user_type, $message) {
    try {
        $statement = $conn->prepare("INSERT INTO notifications (user_id, user_type, message) VALUES (?, ?, ?)");
        if (!$statement) {
            error_log('Notification prepare failed: ' . $conn->error);
            return false;
        }

        $statement->bind_param('iss', $user_id, $user_type, $message);
        $sent = $statement->execute();
        if (!$sent) {
            error_log('Notification insert failed for ' . $user_type . ' recipient: ' . $statement->error);
        }
        $statement->close();
        return $sent;
    } catch (Throwable $error) {
        error_log('Notification delivery failed for ' . $user_type . ' recipient: ' . $error->getMessage());
        return false;
    }
}

function member_action_notify_discipline_masters_for_department($conn, $department, $message) {
    $department = trim((string)$department);
    if ($department === '') {
        return false;
    }

    $discipline_masters = $conn->query("SELECT id, department, church_role FROM members WHERE is_approved = 1 AND LOWER(church_role) LIKE '%discipline master%'");
    if (!$discipline_masters) {
        error_log('Could not load Discipline Master notification recipients: ' . $conn->error);
        return false;
    }

    $sent = false;
    while ($discipline_master = $discipline_masters->fetch_assoc()) {
        $leader_department_match = false;
        $role_matches = [];
        preg_match_all('/(?:^|[, &])\s*((?:vice\s+)?discipline master)\s*(?:\(([^)]+)\))?/i', (string)$discipline_master['church_role'], $role_matches, PREG_SET_ORDER);
        foreach ($role_matches as $role_match) {
            $leader_role = normalize_role_name($role_match[1] ?? '');
            if (!in_array($leader_role, ['discipline master', 'vice discipline master'], true)) {
                continue;
            }

            $leader_department = trim((string)($discipline_master['department'] ?? ''));
            $role_context = trim((string)($role_match[2] ?? ''));
            if ($role_context !== '' && strtolower($role_context) !== 'subsidiary') {
                $leader_department = $role_context;
            }
            if (department_matches($leader_department, $department)) {
                $leader_department_match = true;
                break;
            }
        }

        if ($leader_department_match && member_action_insert_notification($conn, (int)$discipline_master['id'], 'member', $message)) {
            $sent = true;
        }
    }

    return $sent;
}

function member_organizing_secretary_departments($member) {
    $departments = [];
    foreach (preg_split('/\s*,\s*/', str_replace('&', ',', $member['church_role'] ?? '')) as $assigned_role) {
        if (!in_array(normalize_role_name($assigned_role), ['organizing secretary', 'vice organizing secretary'], true)) {
            continue;
        }
        if (preg_match_all('/\(([^)]+)\)/', $assigned_role, $contexts) && !empty($contexts[1])) {
            $role_departments = array_values(array_filter(array_map('trim', $contexts[1]), function ($context) {
                return strtolower($context) !== 'subsidiary';
            }));
            $departments = array_merge($departments, $role_departments ?: [$member['department'] ?? '']);
        } else {
            $departments[] = $member['department'] ?? '';
        }
    }
    return array_values(array_unique(array_filter($departments)));
}

function delete_organized_event_file($relative_path, $directory) {
    if (empty($relative_path)) return;
    $base = realpath(__DIR__ . '/' . trim($directory, '/\\'));
    $file = realpath(__DIR__ . '/' . $directory . '/' . basename($relative_path));
    if ($base && $file && strpos($file, $base . DIRECTORY_SEPARATOR) === 0 && is_file($file)) {
        @unlink($file);
    }
}

if (!isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}

$member_id = $_SESSION['member_id'];

function ensure_financial_record_workflow_schema($conn) {
    $columns = [
        'is_sent_to_chair' => "ALTER TABLE financial_records ADD COLUMN is_sent_to_chair TINYINT(1) NOT NULL DEFAULT 0",
        'edit_reason' => "ALTER TABLE financial_records ADD COLUMN edit_reason VARCHAR(255) NULL",
        'is_chair_confirmed' => "ALTER TABLE financial_records ADD COLUMN is_chair_confirmed TINYINT(1) NOT NULL DEFAULT 0 AFTER is_sent_to_chair",
        'chair_confirmed_by' => "ALTER TABLE financial_records ADD COLUMN chair_confirmed_by INT(6) UNSIGNED NULL AFTER is_chair_confirmed",
        'chair_confirmed_at' => "ALTER TABLE financial_records ADD COLUMN chair_confirmed_at DATETIME NULL AFTER chair_confirmed_by",
        'chair_disbursement_note' => "ALTER TABLE financial_records ADD COLUMN chair_disbursement_note TEXT NULL AFTER chair_confirmed_at"
    ];

    foreach ($columns as $column => $sql) {
        $exists = $conn->query("SHOW COLUMNS FROM financial_records LIKE '$column'");
        if ($exists && $exists->num_rows == 0) {
            $conn->query($sql);
        }
    }
}

function ensure_member_fine_decline_schema($conn) {
    $exists = $conn->query("SHOW COLUMNS FROM member_fines LIKE 'payment_decline_reason'");
    if ($exists && $exists->num_rows === 0) {
        $conn->query("ALTER TABLE member_fines ADD COLUMN payment_decline_reason VARCHAR(500) NULL");
    }
}

function chair_roles_for_department($department) {
    if (department_matches($department, 'Youths')) {
        return ['youth chairperson', 'youth chairman', 'youth chairlady', 'vice youth chairperson', 'vice youth chairman', 'vice youth chairlady'];
    }
    if (department_matches($department, 'Womens Ministry')) {
        return ['women chairlady', 'women chairperson', 'women chairman', 'vice women chairlady', 'vice women chairperson', 'vice women chairman'];
    }
    if (department_matches($department, 'Elders')) {
        return ['elder chairman', 'elder chairperson', 'elder chairlady', 'vice elder chairman', 'vice elder chairperson', 'vice elder chairlady'];
    }
    if (department_matches($department, 'Sunday School')) {
        return ['sunday school patron', 'vice sunday school patron', 'sunday school chairperson', 'sunday school chairman', 'sunday school chairlady', 'vice sunday school chairperson', 'vice sunday school chairman', 'vice sunday school chairlady'];
    }
    if (department_matches($department, 'Building & Construction')) {
        return ['building chairperson', 'building chairman', 'building chairlady', 'vice building chairperson', 'vice building chairman', 'vice building chairlady'];
    }

    return [];
}

function member_is_chair_for_department($roles, $department) {
    return !empty(array_intersect($roles, chair_roles_for_department($department)));
}

ensure_financial_record_workflow_schema($conn);
ensure_member_fine_decline_schema($conn);

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
        
        // Notify Pastor and Admin
        $m_res = $conn->query("SELECT first_name, last_name FROM members WHERE id = $member_id LIMIT 1");
        $m_data = $m_res ? $m_res->fetch_assoc() : null;
        $member_name = trim(($m_data['first_name'] ?? '') . ' ' . ($m_data['last_name'] ?? '')) ?: 'A member';
        $fmt_date = date('M j, Y g:i A', strtotime($datetime));
        $notif_msg = $conn->real_escape_string("New appointment booking: $member_name booked an appointment with the pastor for $fmt_date. Reason: $reason.");

        // Notify all Pastors
        $p_res = $conn->query("SELECT id FROM pastors WHERE is_approved = 1");
        if ($p_res) {
            while ($p = $p_res->fetch_assoc()) {
                $pid = (int)$p['id'];
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($pid, 'pastor', '$notif_msg')");
            }
        } elseif ($pastor_id > 0) {
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($pastor_id, 'pastor', '$notif_msg')");
        }

        // Notify all Admins
        $a_res = $conn->query("SELECT id FROM admins");
        if ($a_res) {
            while ($a = $a_res->fetch_assoc()) {
                $aid = (int)$a['id'];
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($aid, 'admin', '$notif_msg')");
            }
        }

        header("Location: member_dashboard.php?tab=contact_pastor&success=" . urlencode("Appointment requested successfully. Pastor and Admin have been notified."));
        exit();
    }
    
    // Reply to Pastor Message
    elseif ($action === 'reply_to_pastor' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $target_pastor_id = (int)($_POST['pastor_id'] ?? 0);
        $reply_to_id = !empty($_POST['reply_to_id']) ? (int)$_POST['reply_to_id'] : null;
        $reply_text = trim($_POST['message'] ?? '');

        if ($target_pastor_id > 0 && !empty($reply_text)) {
            // Ensure columns exist
            $conn->query("ALTER TABLE member_messages ADD COLUMN IF NOT EXISTS pastor_id INT(6) UNSIGNED NULL AFTER member_id");
            $conn->query("ALTER TABLE member_messages ADD COLUMN IF NOT EXISTS sender_type ENUM('pastor', 'member') NOT NULL DEFAULT 'pastor' AFTER pastor_name");
            $conn->query("ALTER TABLE member_messages ADD COLUMN IF NOT EXISTS reply_to_id INT(6) UNSIGNED NULL DEFAULT NULL AFTER message");

            // Fetch pastor details
            $p_res = $conn->query("SELECT first_name, last_name FROM pastors WHERE id = $target_pastor_id LIMIT 1");
            $p_row = $p_res ? $p_res->fetch_assoc() : null;
            $pastor_name = $p_row ? trim($p_row['first_name'] . ' ' . $p_row['last_name']) : 'Pastor';

            // Fetch member details
            $m_res = $conn->query("SELECT first_name, last_name, profile_picture FROM members WHERE id = $member_id LIMIT 1");
            $m_row = $m_res ? $m_res->fetch_assoc() : null;
            $member_name = $m_row ? trim($m_row['first_name'] . ' ' . $m_row['last_name']) : 'A member';

            $p_name_esc = $conn->real_escape_string($pastor_name);
            $reply_esc = $conn->real_escape_string($reply_text);
            $reply_to_sql = $reply_to_id ? $reply_to_id : "NULL";

            // Insert reply
            $conn->query("INSERT INTO member_messages (member_id, pastor_id, pastor_name, sender_type, message, reply_to_id) 
                          VALUES ($member_id, $target_pastor_id, '$p_name_esc', 'member', '$reply_esc', $reply_to_sql)");

            // Send notification to the pastor
            $snippet = mb_strlen($reply_text) > 60 ? mb_substr($reply_text, 0, 57) . '...' : $reply_text;
            $notif_text = $conn->real_escape_string("Reply from member $member_name: \"$snippet\". Please check your Appointments messages.");
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($target_pastor_id, 'pastor', '$notif_text')");

            header("Location: member_dashboard.php?tab=contact_pastor&success=" . urlencode("Your message has been sent to Pastor $pastor_name successfully.") . "#member_chat_section");
            exit();
        } else {
            header("Location: member_dashboard.php?tab=contact_pastor&error=" . urlencode("Could not send reply. Please enter a valid message."));
            exit();
        }
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
            $amount_input = trim((string)($_POST['amount'] ?? ''));
            if (!is_numeric($amount_input) || (float)$amount_input <= 0) {
                header("Location: member_dashboard.php?tab=financials&treasurer_dept=" . urlencode($submitted_dept) . "&error=" . urlencode('Enter an amount greater than zero.'));
                exit();
            }
            $amount = (float)$amount_input;
            $description = $conn->real_escape_string(trim($_POST['description'] ?? ''));
            $record_date = $conn->real_escape_string($_POST['record_date']);
            $esc_dept = $conn->real_escape_string($submitted_dept);
            $recorder_name = $conn->real_escape_string($member_query['first_name'] . ' ' . $member_query['last_name']);
            if (!$conn->query("INSERT INTO financial_records (pastor_id, department, amount, description, record_date, recorded_by, recorded_by_name) VALUES (0, '$esc_dept', $amount, '$description', '$record_date', $member_id, '$recorder_name')")) {
                header("Location: member_dashboard.php?tab=financials&treasurer_dept=" . urlencode($submitted_dept) . "&error=" . urlencode('The financial record could not be saved. Please try again.'));
                exit();
            }
            $financial_record_id = (int)$conn->insert_id;
            
            // Notify pastors and admins
            $member_name = $conn->real_escape_string($member_query['first_name'] . ' ' . $member_query['last_name']);
            $notif_text = "$submitted_dept Treasurer ($member_name) recorded a collection of KSh " . number_format($amount, 2) . " [financial-record-id:$financial_record_id]";
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

    // Edit Financials
    elseif ($action === 'edit_finance' && $_SERVER['REQUEST_METHOD'] == 'POST') {
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
        
        $dept_is_allowed = false;
        foreach ($allowed_depts as $allowed) {
            if (department_matches($submitted_dept, $allowed)) {
                $dept_is_allowed = true;
                break;
            }
        }
        $is_general_leader = false;
        $general_leader_roles = ['treasurer', 'general church secretary', 'vice church secretary', 'senior church elder'];
        foreach ($roles as $r) {
            if (in_array($r, $general_leader_roles, true)) {
                $is_general_leader = true;
                break;
            }
        }
        if ($dept_is_allowed || ($is_general_leader && !empty($submitted_dept))) {
            $record_id = (int)$_POST['record_id'];
            $amount_input = trim((string)($_POST['amount'] ?? ''));
            if (!is_numeric($amount_input) || (float)$amount_input <= 0) {
                header("Location: member_dashboard.php?tab=financials&treasurer_dept=" . urlencode($submitted_dept) . "&error=" . urlencode('Enter an amount greater than zero.'));
                exit();
            }
            $amount = (float)$amount_input;
            $description = $conn->real_escape_string(trim($_POST['description'] ?? ''));
            $record_date = $conn->real_escape_string($_POST['record_date']);
            $edit_reason = $conn->real_escape_string(trim($_POST['edit_reason'] ?? ''));
            
            // Check if it was sent to chair before updating
            $old_record = $conn->query("SELECT is_sent_to_chair FROM financial_records WHERE id = $record_id")->fetch_assoc();
            
            if (!empty($old_record['is_sent_to_chair'])) {
                $conn->query("UPDATE financial_records SET amount = $amount, description = '$description', record_date = '$record_date', edit_reason = '$edit_reason', is_sent_to_chair = 0, is_chair_confirmed = 0, chair_confirmed_by = NULL, chair_confirmed_at = NULL, chair_disbursement_note = NULL WHERE id = $record_id");
                
                // Delete transfers and disbursements linked to this record to "redo" it
                $conn->query("DELETE FROM department_funds_transfer WHERE financial_record_id = $record_id");
            } else {
                $conn->query("UPDATE financial_records SET amount = $amount, description = '$description', record_date = '$record_date', edit_reason = '$edit_reason' WHERE id = $record_id");
            }
            
            // Notify pastors and admins about the edit
            $member_name = $conn->real_escape_string($member_query['first_name'] . ' ' . $member_query['last_name']);
            $notif_text = "$submitted_dept Treasurer ($member_name) edited a financial record. Reason: $edit_reason. New Amount: KSh " . number_format($amount, 2);
            $notif_msg = $conn->real_escape_string($notif_text);
            $pastors_q = $conn->query("SELECT id FROM pastors");
            while($p = $pastors_q->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$p['id']}, 'pastor', '$notif_msg')");
            }
            $admins_q = $conn->query("SELECT id FROM admins");
            if ($admins_q) {
                while($a = $admins_q->fetch_assoc()) {
                    $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$a['id']}, 'admin', '$notif_msg')");
                }
            }
            
            header("Location: member_dashboard.php?tab=financials&treasurer_dept=" . urlencode($submitted_dept) . "&success=Financial record updated successfully. Reverted to unsent if previously sent.");
            exit();
        } else {
            header("Location: member_dashboard.php?tab=financials&error=Access denied to edit financial records for this department.");
            exit();
        }
    }

    // Send Specific Record to Chairperson
    elseif ($action === 'send_record_to_chairperson' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        $roles = get_member_roles($member_query['church_role'] ?? '');
        
        $treasurer_departments = [
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
        $dept_is_allowed = false;
        foreach ($allowed_depts as $allowed) {
            if (department_matches($submitted_dept, $allowed)) {
                $dept_is_allowed = true;
                break;
            }
        }
        
        if ($dept_is_allowed) {
            $record_id = (int)$_POST['record_id'];
            $esc_dept = $conn->real_escape_string($submitted_dept);
            
            $record = $conn->query("SELECT * FROM financial_records WHERE id = $record_id AND department = '$esc_dept' AND (is_sent_to_chair = 0 OR is_sent_to_chair IS NULL)")->fetch_assoc();
            
            if (!$record) {
                header("Location: member_dashboard.php?tab=financials&treasurer_dept=" . urlencode($submitted_dept) . "&error=Record not found or already sent.");
                exit();
            }
            
            $amount = floatval($record['amount']);
            $description = $conn->real_escape_string($record['description'] ?: 'Transfer of recorded funds');
            
            // Mark as sent
            $conn->query("UPDATE financial_records SET is_sent_to_chair = 1 WHERE id = $record_id");
            
            // Insert transfer (link it to the source record)
            $conn->query("INSERT INTO department_funds_transfer (department, amount, transfer_type, description, recorded_by, financial_record_id) VALUES ('$esc_dept', $amount, 'To Chairperson', '$description', $member_id, $record_id)");
            
            // Notify Chairperson
            $chair_roles = chair_roles_for_department($esc_dept);
            $finance_notification = "Financial record submitted for chairperson review in $submitted_dept. KSh " . number_format($amount, 2) . " from the Treasurer. Open Received Financial Records to confirm and disburse. Reason: " . ($record['description'] ?: 'Transfer of recorded funds') . " [financial-record-id:$record_id]";
            $finance_notification_sql = $conn->real_escape_string($finance_notification);
            $pastor_finance_notification = "Treasurer submitted a financial record of KSh " . number_format($amount, 2) . " for the $submitted_dept department. It is awaiting chairperson confirmation and has not yet been disbursed to members. Please review the transaction before the chairperson disburses it. Reason: " . ($record['description'] ?: 'Transfer of recorded funds') . " [financial-record-id:$record_id]";
            $pastor_finance_notification_sql = $conn->real_escape_string($pastor_finance_notification);
            
            if (!empty($chair_roles)) {
                $chairs_res = $conn->query("SELECT id, church_role FROM members WHERE is_approved=1");
                while ($c = $chairs_res->fetch_assoc()) {
                    $c_roles = get_member_roles($c['church_role']);
                    $is_chair = !empty(array_intersect($c_roles, $chair_roles));
                    if ($is_chair) {
                        $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ({$c['id']}, 'member', '$finance_notification_sql')");
                    }
                }
            }

            $pastors_res = $conn->query("SELECT id FROM pastors WHERE is_approved = 1");
            if ($pastors_res) {
                while ($pastor = $pastors_res->fetch_assoc()) {
                    $pastor_id = (int)$pastor['id'];
                    $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($pastor_id, 'pastor', '$pastor_finance_notification_sql')");
                }
            }
            
            header("Location: member_dashboard.php?tab=financials&treasurer_dept=" . urlencode($submitted_dept) . "&success=Record sent to Chairperson successfully");
            exit();
        } else {
            header("Location: member_dashboard.php?tab=financials&error=Access denied.");
            exit();
        }
    }

    // Chairperson Confirm / Disburse Whole Treasurer Transaction
    elseif ($action === 'disperse_funds' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $department = $conn->real_escape_string($_POST['department']);
        $financial_record_id = (int)($_POST['financial_record_id'] ?? 0);
        $description = $conn->real_escape_string(trim($_POST['description'] ?? ''));
        
        // Verify chairperson access
        $member_query = $conn->query("SELECT first_name, last_name, church_role FROM members WHERE id = $member_id")->fetch_assoc();
        $roles = get_member_roles($member_query['church_role'] ?? '');
        $is_chair = member_is_chair_for_department($roles, $department);
        
        if ($is_chair) {
            $record = null;
            if ($financial_record_id > 0) {
                $dept_match = department_match_sql($conn, 'fr.department', $department);
                $record = $conn->query("
                    SELECT fr.*, dft.id AS transfer_id
                    FROM financial_records fr
                    LEFT JOIN department_funds_transfer dft ON dft.financial_record_id = fr.id AND dft.transfer_type = 'To Chairperson'
                    WHERE fr.id = $financial_record_id
                      AND $dept_match
                      AND fr.is_sent_to_chair = 1
                      AND fr.is_chair_confirmed = 0
                    LIMIT 1
                ")->fetch_assoc();
            }

            if (!$record) {
                header("Location: member_dashboard.php?tab=received_financials&leader_dept=" . urlencode($department) . "&error=Financial transaction was not found or has not been sent by the treasurer.");
                exit();
            }

            $amount = (float)$record['amount'];
            $final_note = $description !== '' ? $description : ($record['description'] ?: 'Confirmed whole treasurer transaction');
            $final_note_sql = $conn->real_escape_string($final_note);
            $conn->query("UPDATE financial_records SET is_chair_confirmed = 1, chair_confirmed_by = $member_id, chair_confirmed_at = NOW(), chair_disbursement_note = '$final_note_sql' WHERE id = $financial_record_id");

            // Notify the treasurer/member who submitted the transaction and the approved members in that department.
            $chair_name = trim(($member_query['first_name'] ?? '') . ' ' . ($member_query['last_name'] ?? ''));
            $finance_focus_tag = "[financial-record-id:$financial_record_id]";
            $member_msg = $conn->real_escape_string("Financial record disbursed to members in {$record['department']}: KSh " . number_format($amount, 2) . " was confirmed by $chair_name. View Financial Records. $finance_focus_tag");
            $recorded_by = (int)$record['recorded_by'];
            $notified_members = [];
            if ($recorded_by > 0) {
                $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($recorded_by, 'member', '$member_msg')");
                $notified_members[$recorded_by] = true;
            }

            $department_member_where = department_match_sql($conn, 'department', $record['department']);
            if (department_matches($record['department'], 'Youths')) {
                $department_member_where = "($department_member_where OR LOWER(church_role) LIKE '%mama youth%' OR LOWER(church_role) LIKE '%baba youth%')";
            }
            $dept_members_notify = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND $department_member_where");
            if ($dept_members_notify) {
                while ($notify_member = $dept_members_notify->fetch_assoc()) {
                    $notify_member_id = (int)$notify_member['id'];
                    if ($notify_member_id > 0 && $notify_member_id !== $member_id && empty($notified_members[$notify_member_id])) {
                        $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($notify_member_id, 'member', '$member_msg')");
                        $notified_members[$notify_member_id] = true;
                    }
                }
            }

            $pastor_msg = $conn->real_escape_string("Financial record disbursed to members in {$record['department']}: KSh " . number_format($amount, 2) . " was confirmed by $chair_name and is now available for members to view. $finance_focus_tag");
            $pastors_to_notify = $conn->query("SELECT id FROM pastors WHERE is_approved = 1");
            if ($pastors_to_notify) {
                while ($pastor_to_notify = $pastors_to_notify->fetch_assoc()) {
                    $pastor_to_notify_id = (int)$pastor_to_notify['id'];
                    $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($pastor_to_notify_id, 'pastor', '$pastor_msg')");
                }
            }

            header("Location: member_dashboard.php?tab=received_financials&leader_dept=" . urlencode($department) . "&success=Financial transaction confirmed and disbursed successfully");
            exit();
        } else {
            header("Location: member_dashboard.php?tab=received_financials&error=Access denied.");
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

        
        $managed_dept = $_POST['active_dept'] ?? null;
        if (!$managed_dept) {
            foreach ($member_roles_list as $r) {
                if (array_key_exists($r, $leader_departments)) {
                    $managed_dept = $leader_departments[$r];
                    break;
                }
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
                $redirect = "member_dashboard.php?tab=appoint_leaders&error=You can only assign approved members in your own department who do not already have a role or pending role";
                if (isset($_POST['active_dept'])) $redirect .= "&leader_dept=" . urlencode($_POST['active_dept']);
                header("Location: $redirect");
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
            
            $redirect = "member_dashboard.php?tab=appoint_leaders&success=Appointment request sent to Pastor for approval";
            if (isset($_POST['active_dept'])) $redirect .= "&leader_dept=" . urlencode($_POST['active_dept']);
            header("Location: $redirect");
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
            $requested_department = trim($_POST['active_dept'] ?? ($member_query['department'] ?? ''));
            $department_is_allowed = false;
            foreach (member_organizing_secretary_departments($member_query) as $allowed_department) {
                if (department_matches($requested_department, $allowed_department)) {
                    $department_is_allowed = true;
                    break;
                }
            }
            if (!$department_is_allowed) {
                header("Location: member_dashboard.php?tab=organized_events&error=You are not assigned to organize events for that department");
                exit();
            }

            $department = $conn->real_escape_string($requested_department);
            $title_text = trim($_POST['title'] ?? '');
            $announcement_text = trim($_POST['announcement'] ?? '');
            $meet_link = $conn->real_escape_string(trim($_POST['meet_link'] ?? ''));
            $whatsapp_link = $conn->real_escape_string(trim($_POST['whatsapp_link'] ?? ''));
            $voice_upload = $_FILES['pastor_voice_note'] ?? null;
            $has_voice_input = !empty($_POST['pastor_voice_note_blob'])
                || (!empty($voice_upload) && ($voice_upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && !empty($voice_upload['size']));
            if ($announcement_text === '' && !$has_voice_input) {
                header("Location: member_dashboard.php?tab=organized_events&error=Add event details or record a voice note");
                exit();
            }
            $voice_note = save_voice_note($_POST['pastor_voice_note_blob'] ?? null, $_FILES['pastor_voice_note'] ?? null);
            if ($announcement_text === '' && $voice_note) {
                $announcement_text = 'Voice announcement from the Organising Secretary.';
            }
            if ($title_text === '' && $voice_note) {
                $title_text = 'Voice announcement - ' . date('M j, Y g:i A');
            }
            if ($title_text === '' || ($announcement_text === '' && !$voice_note)) {
                header("Location: member_dashboard.php?tab=organized_events&error=Add event details or record a voice note");
                exit();
            }
            $title = $conn->real_escape_string($title_text);
            $announcement = $conn->real_escape_string($announcement_text);
            $attendance_file = null;

            $audio_column = $conn->query("SHOW COLUMNS FROM organized_events LIKE 'audio_path'");
            if ($audio_column && $audio_column->num_rows === 0) {
                $conn->query("ALTER TABLE organized_events ADD COLUMN audio_path VARCHAR(255) NULL AFTER attendance_file");
            }

            if ($announcement_text === '' && !$voice_note) {
                header("Location: member_dashboard.php?tab=organized_events&error=Enter event details or record a voice note");
                exit();
            }
            
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
            
            $sql = "INSERT INTO organized_events (department, organizer_id, title, announcement, meet_link, whatsapp_link, attendance_file, audio_path)
                    VALUES ('$department', $member_id, '$title', '$announcement', '$meet_link', '$whatsapp_link', " . ($attendance_file ? "'$attendance_file'" : "NULL") . ", " . ($voice_note ? "'" . $conn->real_escape_string($voice_note) . "'" : "NULL") . ")";
            
            if ($conn->query($sql) === TRUE) {
                $event_id = (int)$conn->insert_id;
                $org_name = trim($member_query['first_name'] . ' ' . $member_query['last_name']);
                $audio_label = $voice_note ? ' (voice note included)' : '';
                $notification_text = "New department announcement from Organising Secretary $org_name: $title$audio_label";
                $event_row_key = 'organized-event-' . $event_id;
                $department_group = notification_department_group_key($requested_department);
                $member_notification = notification_with_route($notification_text, 'organized_events', '', $event_row_key);
                $pastor_notification = notification_with_route($notification_text, 'organized_events', $department_group, $event_row_key);
                $admin_notification = notification_with_route($notification_text, 'general_announcements', $department_group, $event_row_key);

                $member_department_filter = department_match_sql($conn, 'department', $requested_department);
                $dept_members = $conn->query("SELECT id FROM members WHERE $member_department_filter AND is_approved = 1 AND id != $member_id");
                if ($dept_members) {
                    while ($dm = $dept_members->fetch_assoc()) {
                        $did = (int)$dm['id'];
                        member_action_insert_notification($conn, $did, 'member', $member_notification);
                    }
                }

                foreach (['pastors' => 'pastor', 'admins' => 'admin'] as $table => $user_type) {
                    $leaders = $conn->query("SELECT id FROM $table");
                    if ($leaders) {
                        while ($leader = $leaders->fetch_assoc()) {
                            $leader_id = (int)$leader['id'];
                            $leader_notification = $user_type === 'pastor' ? $pastor_notification : $admin_notification;
                            member_action_insert_notification($conn, $leader_id, $user_type, $leader_notification);
                        }
                    }
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

    elseif ($action === 'delete_organized_event' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id LIMIT 1")->fetch_assoc();
        $event_id = (int)($_POST['event_id'] ?? 0);
        ensure_organized_event_attendance_schema($conn);
        $event_result = $conn->query("SELECT id, department, audio_path, attendance_file FROM organized_events WHERE id = $event_id LIMIT 1");
        $event = $event_result ? $event_result->fetch_assoc() : null;
        $authorized = false;
        if ($event && member_has_role($member_query['church_role'] ?? '', 'organizing secretary')) {
            foreach (member_organizing_secretary_departments($member_query) as $allowed_department) {
                if (department_matches($event['department'], $allowed_department)) {
                    $authorized = true;
                    break;
                }
            }
        }

        if ($authorized) {
            $event_department = $conn->real_escape_string($event['department']);
            if ($conn->query("DELETE FROM organized_events WHERE id = $event_id AND department = '$event_department'")) {
                $manual_photos = $conn->query("SELECT profile_picture FROM organized_event_manual_attendance WHERE event_id = $event_id");
                if ($manual_photos) {
                    while ($manual_photo = $manual_photos->fetch_assoc()) {
                        delete_organized_event_file($manual_photo['profile_picture'], 'uploads/organized_event_attendees');
                    }
                }
                $conn->query("DELETE FROM organized_event_attendance WHERE event_id = $event_id");
                $conn->query("DELETE FROM organized_event_manual_attendance WHERE event_id = $event_id");
                delete_organized_event_file($event['audio_path'], 'uploads/voice_notes');
                delete_organized_event_file($event['attendance_file'], 'uploads');
                header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($event['department']) . "&success=Event deleted successfully");
            } else {
                header("Location: member_dashboard.php?tab=organized_events&error=Could not delete event");
            }
        } else {
            header("Location: member_dashboard.php?tab=organized_events&error=You are not assigned to manage this department event");
        }
        exit();
    }

    elseif ($action === 'delete_organized_event_recording' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id LIMIT 1")->fetch_assoc();
        $event_id = (int)($_POST['event_id'] ?? 0);
        $event_result = $conn->query("SELECT id, department, audio_path FROM organized_events WHERE id = $event_id LIMIT 1");
        $event = $event_result ? $event_result->fetch_assoc() : null;
        $authorized = false;
        if ($event && member_has_role($member_query['church_role'] ?? '', 'organizing secretary')) {
            foreach (member_organizing_secretary_departments($member_query) as $allowed_department) {
                if (department_matches($event['department'], $allowed_department)) {
                    $authorized = true;
                    break;
                }
            }
        }

        if (!$authorized) {
            header("Location: member_dashboard.php?tab=organized_events&error=You are not assigned to manage this department event");
            exit();
        }

        $event_department = $conn->real_escape_string($event['department']);
        if ($conn->query("UPDATE organized_events SET audio_path = NULL WHERE id = $event_id AND department = '$event_department'")) {
            delete_organized_event_file($event['audio_path'], 'uploads/voice_notes');
            header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($event['department']) . "&success=Recording deleted and attendance register kept");
        } else {
            header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($event['department']) . "&error=Could not delete recording");
        }
        exit();
    }

    elseif ($action === 'create_organized_attendance_register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id LIMIT 1")->fetch_assoc();
        $department = trim($_POST['department'] ?? '');
        $authorized = false;
        if ($department !== '' && member_has_role($member_query['church_role'] ?? '', 'organizing secretary')) {
            foreach (member_organizing_secretary_departments($member_query) as $allowed_department) {
                if (department_matches($department, $allowed_department)) {
                    $authorized = true;
                    $department = $allowed_department;
                    break;
                }
            }
        }
        if (!$authorized || !ensure_organized_event_attendance_schema($conn)) {
            header("Location: member_dashboard.php?tab=organized_events&error=You are not assigned to create a register for this department");
            exit();
        }

        $create_register = $conn->prepare("INSERT INTO organized_attendance_registers (department, organizer_id, meeting_date, meeting_type) VALUES (?, ?, ?, 'Online')");
        if ($create_register) {
            $today = date('Y-m-d');
            $create_register->bind_param('sis', $department, $member_id, $today);
            $created = $create_register->execute();
            $created_register_id = (int)$create_register->insert_id;
            $create_register->close();
        } else {
            $created = false;
            $created_register_id = 0;
        }

        if ($created) {
            header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($department) . "&success=Attendance register created#attendance-register-" . $created_register_id);
        } else {
            header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($department) . "&error=Could not create attendance register");
        }
        exit();
    }

    elseif ($action === 'save_organized_attendance_register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id LIMIT 1")->fetch_assoc();
        $register_id = (int)($_POST['register_id'] ?? 0);
        if (!ensure_organized_event_attendance_schema($conn)) {
            header("Location: member_dashboard.php?tab=organized_events&error=Attendance register storage is unavailable");
            exit();
        }
        $register_result = $conn->query("SELECT id, department FROM organized_attendance_registers WHERE id = $register_id LIMIT 1");
        $register = $register_result ? $register_result->fetch_assoc() : null;
        $authorized = false;
        if ($register && member_has_role($member_query['church_role'] ?? '', 'organizing secretary')) {
            foreach (member_organizing_secretary_departments($member_query) as $allowed_department) {
                if (department_matches($register['department'], $allowed_department)) {
                    $authorized = true;
                    break;
                }
            }
        }
        if (!$authorized) {
            header("Location: member_dashboard.php?tab=organized_events&error=You are not assigned to manage this attendance register");
            exit();
        }

        $meeting_date = trim($_POST['meeting_date'] ?? '');
        $date_value = DateTime::createFromFormat('!Y-m-d', $meeting_date);
        $meeting_type = trim($_POST['meeting_type'] ?? '');
        if (!$date_value || $date_value->format('Y-m-d') !== $meeting_date || $meeting_type === '' || strlen($meeting_type) > 100) {
            header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($register['department']) . "&error=Enter a valid date and meeting heading");
            exit();
        }

        $posted_ids = isset($_POST['register_attendee_ids']) && is_array($_POST['register_attendee_ids']) ? $_POST['register_attendee_ids'] : [];
        $selected_ids = array_values(array_unique(array_filter(array_map('intval', $posted_ids), function ($id) { return $id > 0; })));
        $allowed_ids = [];
        if ($selected_ids) {
            $id_list = implode(',', $selected_ids);
            $department_filter = department_match_sql($conn, 'm.department', $register['department']);
            $eligible = $conn->query("SELECT m.id FROM members m WHERE m.id IN ($id_list) AND $department_filter AND m.is_approved = 1");
            if (!$eligible) {
                header("Location: member_dashboard.php?tab=organized_events&error=Could not verify selected members");
                exit();
            }
            while ($row = $eligible->fetch_assoc()) $allowed_ids[] = (int)$row['id'];
        }

        $manual_ids = isset($_POST['manual_register_attendee_ids']) && is_array($_POST['manual_register_attendee_ids'])
            ? array_values(array_unique(array_filter(array_map('intval', $_POST['manual_register_attendee_ids']), function ($id) { return $id > 0; })))
            : [];
        $manual_times = isset($_POST['manual_register_attendance_times']) && is_array($_POST['manual_register_attendance_times']) ? $_POST['manual_register_attendance_times'] : [];
        $member_apology_reasons = isset($_POST['apology_member_reasons']) && is_array($_POST['apology_member_reasons']) ? $_POST['apology_member_reasons'] : [];
        $manual_apology_reasons = isset($_POST['apology_manual_reasons']) && is_array($_POST['apology_manual_reasons']) ? $_POST['apology_manual_reasons'] : [];
        $apology_member_ids = isset($_POST['apology_member_ids']) && is_array($_POST['apology_member_ids'])
            ? array_values(array_unique(array_filter(array_map('intval', $_POST['apology_member_ids']), function ($id) { return $id > 0; })))
            : [];
        $apology_member_ids = array_values(array_diff($apology_member_ids, $allowed_ids));
        $apology_manual_ids = isset($_POST['apology_manual_ids']) && is_array($_POST['apology_manual_ids'])
            ? array_values(array_unique(array_filter(array_map('intval', $_POST['apology_manual_ids']), function ($id) { return $id > 0; })))
            : [];
        $apology_manual_ids = array_values(array_diff($apology_manual_ids, $manual_ids));
        $absence_records = ['members' => [], 'manual' => []];
        if ($apology_member_ids) {
            $apology_member_list = implode(',', $apology_member_ids);
            $department_filter = department_match_sql($conn, 'm.department', $register['department']);
            $eligible_apologies = $conn->query("SELECT m.id FROM members m WHERE m.id IN ($apology_member_list) AND $department_filter AND m.is_approved = 1");
            $eligible_apology_ids = [];
            if ($eligible_apologies) while ($row = $eligible_apologies->fetch_assoc()) $eligible_apology_ids[] = (int)$row['id'];
            if (!$eligible_apologies || count($eligible_apology_ids) !== count($apology_member_ids)) {
                header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($register['department']) . "&error=" . urlencode('Apology selections must be approved members of this department') . "#attendance-register-" . $register_id);
                exit();
            }
            foreach ($eligible_apology_ids as $apology_id) {
                $reason = trim($member_apology_reasons[$apology_id] ?? '');
                $reason_length = function_exists('mb_strlen') ? mb_strlen($reason, 'UTF-8') : strlen($reason);
                if ($reason === '' || $reason_length > 500) {
                    header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($register['department']) . "&error=" . urlencode('Enter a reason for every absent member marked with an apology (maximum 500 characters)') . "#attendance-register-" . $register_id);
                    exit();
                }
                $absence_records['members'][] = ['id' => $apology_id, 'reason' => $reason];
            }
        }
        if ($apology_manual_ids) {
            $apology_manual_list = implode(',', $apology_manual_ids);
            $eligible_manual_apologies = $conn->query("SELECT id FROM organized_attendance_register_manual_members WHERE id IN ($apology_manual_list) AND register_id = $register_id");
            $eligible_apology_ids = [];
            if ($eligible_manual_apologies) while ($row = $eligible_manual_apologies->fetch_assoc()) $eligible_apology_ids[] = (int)$row['id'];
            if (!$eligible_manual_apologies || count($eligible_apology_ids) !== count($apology_manual_ids)) {
                header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($register['department']) . "&error=" . urlencode('Apology selections must belong to this attendance register') . "#attendance-register-" . $register_id);
                exit();
            }
            foreach ($eligible_apology_ids as $apology_id) {
                $reason = trim($manual_apology_reasons[$apology_id] ?? '');
                $reason_length = function_exists('mb_strlen') ? mb_strlen($reason, 'UTF-8') : strlen($reason);
                if ($reason === '' || $reason_length > 500) {
                    header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($register['department']) . "&error=" . urlencode('Enter a reason for every absent member marked with an apology (maximum 500 characters)') . "#attendance-register-" . $register_id);
                    exit();
                }
                $absence_records['manual'][] = ['id' => $apology_id, 'reason' => $reason];
            }
        }

        $member_times = isset($_POST['register_attendance_times']) && is_array($_POST['register_attendance_times']) ? $_POST['register_attendance_times'] : [];
        $manual_fields = isset($_POST['manual_register_attendees']) && is_array($_POST['manual_register_attendees']) ? $_POST['manual_register_attendees'] : [];
        $manual_uploads = $_FILES['manual_register_photos'] ?? [];
        $new_manual_attendees = [];
        foreach ($manual_fields as $index => $manual) {
            if (!is_array($manual)) continue;
            $name = trim($manual['name'] ?? '');
            if ($name === '') continue;
            $gender = trim($manual['gender'] ?? '');
            $phone = trim($manual['phone'] ?? '');
            if (strlen($name) > 255 || strlen($gender) > 40 || strlen($phone) > 40) {
                foreach ($new_manual_attendees as $saved_manual) {
                    delete_organized_event_file($saved_manual['photo'], 'uploads/organized_event_attendees');
                }
                header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($register['department']) . "&error=Check the manual attendee name, gender, and phone lengths");
                exit();
            }
            if (!preg_match('/^[A-Za-z ]+$/', $name)) {
                foreach ($new_manual_attendees as $saved_manual) {
                    delete_organized_event_file($saved_manual['photo'], 'uploads/organized_event_attendees');
                }
                header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($register['department']) . "&error=Name can contain letters and spaces only");
                exit();
            }
            if (!preg_match('/^[0-9]{10}$/', $phone)) {
                foreach ($new_manual_attendees as $saved_manual) {
                    delete_organized_event_file($saved_manual['photo'], 'uploads/organized_event_attendees');
                }
                header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($register['department']) . "&error=Phone number must contain exactly 10 digits");
                exit();
            }

            $photo_upload = null;
            if (isset($manual_uploads['name'][$index])) {
                foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $file_key) {
                    $photo_upload[$file_key] = $manual_uploads[$file_key][$index] ?? null;
                }
            }
            $photo_path = save_organized_event_attendee_photo($photo_upload);
            if ($photo_path === false || $photo_path === null) {
                foreach ($new_manual_attendees as $saved_manual) {
                    delete_organized_event_file($saved_manual['photo'], 'uploads/organized_event_attendees');
                }
                header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($register['department']) . "&error=Add a valid profile photo under 5 MB for each manual attendee");
                exit();
            }
            $time = trim($manual['joined_at'] ?? '');
            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) $time = '';
            $new_manual_attendees[] = [
                'name' => $name,
                'gender' => $gender,
                'phone' => $phone,
                'photo' => $photo_path,
                'joined_at' => $time === '' ? null : $time . ':00'
            ];
        }

        $safe_meeting_date = $conn->real_escape_string($meeting_date);
        $safe_meeting_type = $conn->real_escape_string($meeting_type);
        $conn->begin_transaction();
        $saved = $conn->query("UPDATE organized_attendance_registers SET meeting_date = '$safe_meeting_date', meeting_type = '$safe_meeting_type' WHERE id = $register_id");
        if ($saved) $saved = $conn->query("DELETE FROM organized_attendance_register_members WHERE register_id = $register_id");
        if ($saved) $saved = $conn->query("DELETE FROM organized_attendance_register_absences WHERE register_id = $register_id");
        if ($saved && $allowed_ids) {
            $insert = $conn->prepare("INSERT INTO organized_attendance_register_members (register_id, member_id, marked_by, joined_at) VALUES (?, ?, ?, ?)");
            if ($insert) {
                foreach ($allowed_ids as $attendee_id) {
                    $joined_at = trim($member_times[$attendee_id] ?? '');
                    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $joined_at)) $joined_at = null;
                    else $joined_at .= ':00';
                    $insert->bind_param('iiis', $register_id, $attendee_id, $member_id, $joined_at);
                    if (!$insert->execute()) {
                        $saved = false;
                        break;
                    }
                }
                $insert->close();
            } else {
                $saved = false;
            }
        }

        if ($saved) {
            $saved = (bool)$conn->query("UPDATE organized_attendance_register_manual_members SET is_present = 0, joined_at = NULL WHERE register_id = $register_id");
        }
        if ($saved && $manual_ids) {
            $update_manual = $conn->prepare("UPDATE organized_attendance_register_manual_members SET is_present = 1, joined_at = ?, marked_by = ?, marked_at = CURRENT_TIMESTAMP WHERE id = ? AND register_id = ?");
            if ($update_manual) {
                foreach ($manual_ids as $manual_id) {
                    $joined_at = trim($manual_times[$manual_id] ?? '');
                    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $joined_at)) $joined_at = null;
                    else $joined_at .= ':00';
                    $update_manual->bind_param('siii', $joined_at, $member_id, $manual_id, $register_id);
                    if (!$update_manual->execute()) {
                        $saved = false;
                        break;
                    }
                }
                $update_manual->close();
            } else {
                $saved = false;
            }
        }
        if ($saved && $absence_records['members']) {
            $insert_member_absence = $conn->prepare("INSERT INTO organized_attendance_register_absences (register_id, member_id, apology_reason, marked_by) VALUES (?, ?, ?, ?)");
            if ($insert_member_absence) {
                foreach ($absence_records['members'] as $absence) {
                    $insert_member_absence->bind_param('iisi', $register_id, $absence['id'], $absence['reason'], $member_id);
                    if (!$insert_member_absence->execute()) {
                        $saved = false;
                        break;
                    }
                }
                $insert_member_absence->close();
            } else {
                $saved = false;
            }
        }
        if ($saved && $absence_records['manual']) {
            $insert_manual_absence = $conn->prepare("INSERT INTO organized_attendance_register_absences (register_id, manual_attendee_id, apology_reason, marked_by) VALUES (?, ?, ?, ?)");
            if ($insert_manual_absence) {
                foreach ($absence_records['manual'] as $absence) {
                    $insert_manual_absence->bind_param('iisi', $register_id, $absence['id'], $absence['reason'], $member_id);
                    if (!$insert_manual_absence->execute()) {
                        $saved = false;
                        break;
                    }
                }
                $insert_manual_absence->close();
            } else {
                $saved = false;
            }
        }
        if ($saved && $new_manual_attendees) {
            $insert_manual = $conn->prepare("INSERT INTO organized_attendance_register_manual_members (register_id, attendee_name, gender, phone, profile_picture, is_present, joined_at, marked_by) VALUES (?, ?, ?, ?, ?, 1, ?, ?)");
            if ($insert_manual) {
                foreach ($new_manual_attendees as $manual) {
                    $insert_manual->bind_param('isssssi', $register_id, $manual['name'], $manual['gender'], $manual['phone'], $manual['photo'], $manual['joined_at'], $member_id);
                    if (!$insert_manual->execute()) {
                        $saved = false;
                        break;
                    }
                }
                $insert_manual->close();
            } else {
                $saved = false;
            }
        }

        if ($saved) {
            $conn->commit();
            header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($register['department']) . "&success=Attendance register saved#attendance-register-" . $register_id);
        } else {
            $conn->rollback();
            foreach ($new_manual_attendees as $manual) {
                delete_organized_event_file($manual['photo'], 'uploads/organized_event_attendees');
            }
            header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($register['department']) . "&error=Could not save attendance register");
        }
        exit();
    }

    elseif ($action === 'update_organized_attendance_member' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id LIMIT 1")->fetch_assoc();
        $register_id = (int)($_POST['register_id'] ?? 0);
        $target_member_id = (int)($_POST['member_id'] ?? 0);
        $manual_attendee_id = (int)($_POST['manual_attendee_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if (!ensure_organized_event_attendance_schema($conn)) {
            header("Location: member_dashboard.php?tab=organized_events&error=Attendance register storage is unavailable");
            exit();
        }

        $register_result = $conn->query("SELECT id, department FROM organized_attendance_registers WHERE id = $register_id LIMIT 1");
        $register = $register_result ? $register_result->fetch_assoc() : null;
        $authorized = false;
        if ($register && member_has_role($member_query['church_role'] ?? '', 'organizing secretary')) {
            foreach (member_organizing_secretary_departments($member_query) as $allowed_department) {
                if (department_matches($register['department'], $allowed_department)) {
                    $authorized = true;
                    break;
                }
            }
        }
        if (!$authorized || ($target_member_id < 1 && $manual_attendee_id < 1)) {
            header("Location: member_dashboard.php?tab=organized_events&error=You are not assigned to edit this register member");
            exit();
        }
        if ($name === '' || strlen($name) > 255) {
            header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($register['department']) . "&error=Enter a valid member name#attendance-register-" . $register_id);
            exit();
        }
        if (!preg_match('/^[0-9]{10}$/', $phone)) {
            header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($register['department']) . "&error=Phone number must contain exactly 10 digits#attendance-register-" . $register_id);
            exit();
        }

        $old_profile_path = null;
        $original_name = '';
        if ($manual_attendee_id > 0) {
            $manual_result = $conn->query("SELECT id, attendee_name, profile_picture FROM organized_attendance_register_manual_members WHERE id = $manual_attendee_id AND register_id = $register_id LIMIT 1");
            $manual_attendee = $manual_result ? $manual_result->fetch_assoc() : null;
            if (!$manual_attendee) {
                header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($register['department']) . "&error=Manual attendee was not found in this register#attendance-register-" . $register_id);
                exit();
            }
            $original_name = trim($manual_attendee['attendee_name'] ?? '');
            $old_profile_path = $manual_attendee['profile_picture'] ?? null;
        } else {
            $member_filter = department_match_sql($conn, 'm.department', $register['department']);
            $eligible = $conn->query("SELECT m.id, m.first_name, m.last_name, rmo.name_override FROM members m
                LEFT JOIN organized_attendance_register_member_overrides rmo ON rmo.member_id = m.id AND rmo.register_id = $register_id
                WHERE m.id = $target_member_id AND $member_filter AND m.is_approved = 1 LIMIT 1");
            $eligible_member = $eligible ? $eligible->fetch_assoc() : null;
            if (!$eligible_member) {
                header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($register['department']) . "&error=That member is not an approved member of this department#attendance-register-" . $register_id);
                exit();
            }
            $original_name = trim($eligible_member['name_override'] ?? '');
            if ($original_name === '') $original_name = trim($eligible_member['first_name'] . ' ' . $eligible_member['last_name']);
        }
        if (!preg_match('/^[A-Za-z ]+$/', $name) && $name !== $original_name) {
            header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($register['department']) . "&error=Name can contain letters and spaces only#attendance-register-" . $register_id);
            exit();
        }

        $photo_upload = $_FILES['profile_photo'] ?? null;
        $new_photo_path = save_organized_event_attendee_photo($photo_upload);
        if ($new_photo_path === false) {
            header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($register['department']) . "&error=Choose a valid profile photo under 5 MB#attendance-register-" . $register_id);
            exit();
        }

        if ($manual_attendee_id > 0) {
            $save_details = $conn->prepare("UPDATE organized_attendance_register_manual_members SET attendee_name = ?, phone = ?, profile_picture = COALESCE(?, profile_picture) WHERE id = ? AND register_id = ?");
            if ($save_details) {
                $save_details->bind_param('sssii', $name, $phone, $new_photo_path, $manual_attendee_id, $register_id);
                $saved_override = $save_details->execute();
                $save_details->close();
            } else {
                $saved_override = false;
            }
        } else {
            $old_override_result = $conn->query("SELECT profile_picture_override FROM organized_attendance_register_member_overrides WHERE register_id = $register_id AND member_id = $target_member_id LIMIT 1");
            $old_override = $old_override_result ? $old_override_result->fetch_assoc() : null;
            $old_profile_path = $old_override['profile_picture_override'] ?? null;
            $save_override = $conn->prepare("INSERT INTO organized_attendance_register_member_overrides (register_id, member_id, name_override, phone_override, profile_picture_override, updated_by) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name_override = VALUES(name_override), phone_override = VALUES(phone_override), profile_picture_override = COALESCE(VALUES(profile_picture_override), profile_picture_override), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP");
            if ($save_override) {
                $save_override->bind_param('iisssi', $register_id, $target_member_id, $name, $phone, $new_photo_path, $member_id);
                $saved_override = $save_override->execute();
                $save_override->close();
            } else {
                $saved_override = false;
            }
        }

        if ($saved_override) {
            if ($new_photo_path && !empty($old_profile_path)) delete_organized_event_file($old_profile_path, 'uploads/organized_event_attendees');
            header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($register['department']) . "&success=Register member details updated#attendance-register-" . $register_id);
        } else {
            if ($new_photo_path) delete_organized_event_file($new_photo_path, 'uploads/organized_event_attendees');
            header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($register['department']) . "&error=Could not update register member details#attendance-register-" . $register_id);
        }
        exit();
    }

    elseif ($action === 'delete_organized_attendance_register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id LIMIT 1")->fetch_assoc();
        $register_id = (int)($_POST['register_id'] ?? 0);
        if (!ensure_organized_event_attendance_schema($conn)) {
            header("Location: member_dashboard.php?tab=organized_events&error=Attendance register storage is unavailable");
            exit();
        }
        $register_result = $conn->query("SELECT id, department FROM organized_attendance_registers WHERE id = $register_id LIMIT 1");
        $register = $register_result ? $register_result->fetch_assoc() : null;
        $authorized = false;
        if ($register && member_has_role($member_query['church_role'] ?? '', 'organizing secretary')) {
            foreach (member_organizing_secretary_departments($member_query) as $allowed_department) {
                if (department_matches($register['department'], $allowed_department)) {
                    $authorized = true;
                    break;
                }
            }
        }
        if (!$authorized) {
            header("Location: member_dashboard.php?tab=organized_events&error=You are not assigned to delete this attendance register");
            exit();
        }

        $conn->begin_transaction();
        $manual_photo_paths = [];
        $override_photo_paths = [];
        $manual_photo_rows = $conn->query("SELECT profile_picture FROM organized_attendance_register_manual_members WHERE register_id = $register_id");
        if ($manual_photo_rows) {
            while ($manual_photo = $manual_photo_rows->fetch_assoc()) {
                if (!empty($manual_photo['profile_picture'])) $manual_photo_paths[] = $manual_photo['profile_picture'];
            }
        }
        $deleted = (bool)$manual_photo_rows;
        $override_photo_rows = $deleted ? $conn->query("SELECT profile_picture_override FROM organized_attendance_register_member_overrides WHERE register_id = $register_id") : false;
        if ($override_photo_rows) {
            while ($override_photo = $override_photo_rows->fetch_assoc()) {
                if (!empty($override_photo['profile_picture_override'])) $override_photo_paths[] = $override_photo['profile_picture_override'];
            }
        }
        $deleted = $deleted && (bool)$override_photo_rows;
        if ($deleted) $deleted = $conn->query("DELETE FROM organized_attendance_register_manual_members WHERE register_id = $register_id");
        if ($deleted) $deleted = $conn->query("DELETE FROM organized_attendance_register_member_overrides WHERE register_id = $register_id");
        if ($deleted) $deleted = $conn->query("DELETE FROM organized_attendance_register_members WHERE register_id = $register_id");
        if ($deleted) $deleted = $conn->query("DELETE FROM organized_attendance_register_absences WHERE register_id = $register_id");
        if ($deleted) $deleted = $conn->query("DELETE FROM organized_attendance_registers WHERE id = $register_id");
        if ($deleted) {
            $conn->commit();
            foreach ($manual_photo_paths as $photo_path) {
                delete_organized_event_file($photo_path, 'uploads/organized_event_attendees');
            }
            foreach ($override_photo_paths as $photo_path) {
                delete_organized_event_file($photo_path, 'uploads/organized_event_attendees');
            }
            header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($register['department']) . "&success=Attendance register deleted");
        } else {
            $conn->rollback();
            header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($register['department']) . "&error=Could not delete attendance register");
        }
        exit();
    }

    elseif ($action === 'save_organized_event_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id LIMIT 1")->fetch_assoc();
        $event_id = (int)($_POST['event_id'] ?? 0);
        if (!ensure_organized_event_attendance_schema($conn)) {
            header("Location: member_dashboard.php?tab=organized_events&error=Attendance storage is unavailable");
            exit();
        }
        $event_result = $conn->query("SELECT id, department FROM organized_events WHERE id = $event_id LIMIT 1");
        $event = $event_result ? $event_result->fetch_assoc() : null;
        $authorized = false;
        if ($event && member_has_role($member_query['church_role'] ?? '', 'organizing secretary')) {
            foreach (member_organizing_secretary_departments($member_query) as $allowed_department) {
                if (department_matches($event['department'], $allowed_department)) {
                    $authorized = true;
                    break;
                }
            }
        }

        if (!$authorized) {
            header("Location: member_dashboard.php?tab=organized_events&error=You are not assigned to record attendance for this event");
            exit();
        }

        $meeting_date = trim($_POST['meeting_date'] ?? '');
        $date_value = DateTime::createFromFormat('!Y-m-d', $meeting_date);
        if (!$date_value || $date_value->format('Y-m-d') !== $meeting_date) {
            header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($event['department']) . "&error=Choose the scheduled meeting date");
            exit();
        }
        $meeting_type = trim($_POST['meeting_type'] ?? '');
        if ($meeting_type === '' || strlen($meeting_type) > 100) {
            header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($event['department']) . "&error=Enter a meeting heading up to 100 characters");
            exit();
        }

        $posted_ids = isset($_POST['attendee_ids']) && is_array($_POST['attendee_ids']) ? $_POST['attendee_ids'] : [];
        $selected_ids = array_values(array_unique(array_filter(array_map('intval', $posted_ids), function ($id) {
            return $id > 0;
        })));
        $allowed_ids = [];
        if ($selected_ids) {
            $id_list = implode(',', $selected_ids);
            $department_filter = department_match_sql($conn, 'department', $event['department']);
            $eligible = $conn->query("SELECT id FROM members WHERE id IN ($id_list) AND $department_filter AND is_approved = 1");
            if (!$eligible) {
                header("Location: member_dashboard.php?tab=organized_events&error=Could not verify selected members");
                exit();
            }
            while ($row = $eligible->fetch_assoc()) $allowed_ids[] = (int)$row['id'];
        }

        $manual_fields = isset($_POST['manual_attendees']) && is_array($_POST['manual_attendees']) ? $_POST['manual_attendees'] : [];
        $manual_uploads = $_FILES['manual_photos'] ?? [];
        $new_manual_attendees = [];
        foreach ($manual_fields as $index => $manual) {
            if (!is_array($manual)) continue;
            $name = trim($manual['name'] ?? '');
            if ($name === '') continue;

            $photo_upload = null;
            if (isset($manual_uploads['name'][$index])) {
                foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $file_key) {
                    $photo_upload[$file_key] = $manual_uploads[$file_key][$index] ?? null;
                }
            }
            $photo_path = save_organized_event_attendee_photo($photo_upload);
            if ($photo_path === false) {
                foreach ($new_manual_attendees as $saved_manual) {
                    delete_organized_event_file($saved_manual['photo'], 'uploads/organized_event_attendees');
                }
                header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($event['department']) . "&error=Choose a valid profile photo under 5 MB");
                exit();
            }
            $time = trim($manual['joined_at'] ?? '');
            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) $time = '';
            $new_manual_attendees[] = [
                'name' => $name,
                'gender' => trim($manual['gender'] ?? ''),
                'phone' => trim($manual['phone'] ?? ''),
                'photo' => $photo_path,
                'joined_at' => $time === '' ? null : $time . ':00'
            ];
        }

        $manual_ids = isset($_POST['manual_attendee_ids']) && is_array($_POST['manual_attendee_ids'])
            ? array_values(array_unique(array_filter(array_map('intval', $_POST['manual_attendee_ids']), function ($id) { return $id > 0; })))
            : [];
        $manual_times = isset($_POST['manual_attendance_times']) && is_array($_POST['manual_attendance_times']) ? $_POST['manual_attendance_times'] : [];
        $member_times = isset($_POST['attendance_times']) && is_array($_POST['attendance_times']) ? $_POST['attendance_times'] : [];

        $conn->begin_transaction();
        $safe_meeting_date = $conn->real_escape_string($meeting_date);
        $safe_meeting_type = $conn->real_escape_string($meeting_type);
        $saved = $conn->query("UPDATE organized_events SET meeting_date = '$safe_meeting_date', meeting_type = '$safe_meeting_type' WHERE id = $event_id");
        if ($saved) $saved = $conn->query("DELETE FROM organized_event_attendance WHERE event_id = $event_id");
        if ($saved && $allowed_ids) {
            $insert = $conn->prepare("INSERT INTO organized_event_attendance (event_id, member_id, marked_by, joined_at) VALUES (?, ?, ?, ?)");
            if ($insert) {
                foreach ($allowed_ids as $attendee_id) {
                    $joined_at = trim($member_times[$attendee_id] ?? '');
                    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $joined_at)) $joined_at = null;
                    else $joined_at .= ':00';
                    $insert->bind_param('iiis', $event_id, $attendee_id, $member_id, $joined_at);
                    if (!$insert->execute()) {
                        $saved = false;
                        break;
                    }
                }
                $insert->close();
            } else {
                $saved = false;
            }
        }

        if ($saved) {
            $saved = (bool)$conn->query("UPDATE organized_event_manual_attendance SET is_present = 0, joined_at = NULL WHERE event_id = $event_id");
        }
        if ($saved && $manual_ids) {
            $update_manual = $conn->prepare("UPDATE organized_event_manual_attendance SET is_present = 1, joined_at = ?, marked_by = ?, marked_at = CURRENT_TIMESTAMP WHERE id = ? AND event_id = ?");
            if ($update_manual) {
                foreach ($manual_ids as $manual_id) {
                    $joined_at = trim($manual_times[$manual_id] ?? '');
                    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $joined_at)) $joined_at = null;
                    else $joined_at .= ':00';
                    $update_manual->bind_param('siii', $joined_at, $member_id, $manual_id, $event_id);
                    if (!$update_manual->execute()) {
                        $saved = false;
                        break;
                    }
                }
                $update_manual->close();
            } else {
                $saved = false;
            }
        }
        if ($saved && $new_manual_attendees) {
            $insert_manual = $conn->prepare("INSERT INTO organized_event_manual_attendance (event_id, attendee_name, gender, phone, profile_picture, is_present, joined_at, marked_by) VALUES (?, ?, ?, ?, ?, 1, ?, ?)");
            if ($insert_manual) {
                foreach ($new_manual_attendees as $manual) {
                    $insert_manual->bind_param('isssssi', $event_id, $manual['name'], $manual['gender'], $manual['phone'], $manual['photo'], $manual['joined_at'], $member_id);
                    if (!$insert_manual->execute()) {
                        $saved = false;
                        break;
                    }
                }
                $insert_manual->close();
            } else {
                $saved = false;
            }
        }

        if ($saved) {
            $conn->commit();
            header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($event['department']) . "&success=Meeting attendance saved");
        } else {
            $conn->rollback();
            foreach ($new_manual_attendees as $manual) {
                delete_organized_event_file($manual['photo'], 'uploads/organized_event_attendees');
            }
            header("Location: member_dashboard.php?tab=organized_events&org_dept=" . urlencode($event['department']) . "&error=Could not save meeting attendance");
        }
        exit();
    }
    
    // Discipline Master: Add Fine Template
    elseif ($action === 'add_fine_template' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        
        if (member_has_role($member_query['church_role'] ?? '', 'discipline master')) {
            $dept = isset($_POST['active_dept']) ? trim($_POST['active_dept']) : trim((string)$member_query['department']);
            if (!member_can_manage_fine_department($member_query, $dept)) {
                header("Location: member_dashboard.php?tab=discipline&error=" . urlencode('You can create fine rules only for your assigned department.'));
                exit();
            }

            $fine_name = trim((string)($_POST['fine_name'] ?? ''));
            $amount_input = trim((string)($_POST['amount'] ?? ''));
            $amount = is_numeric($amount_input) ? (float)$amount_input : 0;
            $fine_template_id = 0;

            if ($dept !== '' && $fine_name !== '' && is_finite($amount) && $amount > 0) {
                $insert = $conn->prepare("INSERT INTO fine_templates (department, fine_name, amount) VALUES (?, ?, ?)");
                if ($insert) {
                    $insert->bind_param('ssd', $dept, $fine_name, $amount);
                    if ($insert->execute()) {
                        $fine_template_id = (int)$insert->insert_id;
                    }
                    $insert->close();
                }
            }

            if ($fine_template_id > 0) {
                $amount_display = number_format($amount, 2);
                $focus_tag = "[focus-fine-rule-id:$fine_template_id]";
                $member_message = "New department fine rule: '$fine_name' is KSh $amount_display in $dept. Review this rule under My Fines. $focus_tag";
                $department_filter = department_match_sql($conn, 'department', $dept);
                $department_members = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND $department_filter");
                if ($department_members) {
                    while ($department_member = $department_members->fetch_assoc()) {
                        member_action_insert_notification($conn, (int)$department_member['id'], 'member', $member_message);
                    }
                }

                $staff_message = "Department fine rule added: '$fine_name' is KSh $amount_display in $dept. Review Department Fine Rules. $focus_tag";
                $admins = $conn->query("SELECT id FROM admins");
                if ($admins) {
                    while ($admin = $admins->fetch_assoc()) {
                        member_action_insert_notification($conn, (int)$admin['id'], 'admin', $staff_message);
                    }
                }
                $pastors = $conn->query("SELECT id FROM pastors WHERE is_approved = 1");
                if ($pastors) {
                    while ($pastor = $pastors->fetch_assoc()) {
                        member_action_insert_notification($conn, (int)$pastor['id'], 'pastor', $staff_message);
                    }
                }
                header("Location: member_dashboard.php?tab=discipline&disc_dept=" . urlencode($dept) . "&success=" . urlencode('Fine rule saved and notifications sent.'));
            } else {
                header("Location: member_dashboard.php?tab=discipline&disc_dept=" . urlencode($dept) . "&error=" . urlencode('Enter a fine name and an amount greater than zero.'));
            }
            exit();
        }
    }

    // Discipline Master: Edit Fine Template
    elseif ($action === 'update_fine_template' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $member_query = $conn->query("SELECT id, department, church_role FROM members WHERE id = $member_id")->fetch_assoc();
        $template_id = (int)($_POST['fine_template_id'] ?? 0);
        $fine_name = trim((string)($_POST['fine_name'] ?? ''));
        $amount_input = trim((string)($_POST['amount'] ?? ''));
        $amount = is_numeric($amount_input) ? (float)$amount_input : 0;
        $template = null;

        if ($template_id > 0) {
            $find_template = $conn->prepare("SELECT department FROM fine_templates WHERE id = ? LIMIT 1");
            if ($find_template) {
                $find_template->bind_param('i', $template_id);
                if ($find_template->execute()) {
                    $template = $find_template->get_result()->fetch_assoc();
                }
                $find_template->close();
            }
        }

        if (!$member_query || !member_has_role($member_query['church_role'] ?? '', 'discipline master')
            || !$template || !member_can_manage_fine_department($member_query, $template['department'])) {
            header("Location: member_dashboard.php?tab=discipline&error=" . urlencode('You can edit fine rules only in your assigned department.'));
            exit();
        }

        if ($fine_name === '' || !is_finite($amount) || $amount <= 0) {
            header("Location: member_dashboard.php?tab=discipline&disc_dept=" . urlencode($template['department']) . "&error=" . urlencode('Enter a fine name and an amount greater than zero.'));
            exit();
        }

        $update = $conn->prepare("UPDATE fine_templates SET fine_name = ?, amount = ? WHERE id = ?");
        $updated = false;
        if ($update) {
            $update->bind_param('sdi', $fine_name, $amount, $template_id);
            $updated = $update->execute();
            $update->close();
        }

        if ($updated) {
            $amount_display = number_format($amount, 2);
            $focus_tag = "[focus-fine-rule-id:$template_id]";
            $department = $template['department'];
            $department_filter = department_match_sql($conn, 'department', $department);
            $department_members = $conn->query("SELECT id FROM members WHERE is_approved = 1 AND $department_filter");
            if ($department_members) {
                $message = "Department fine rule updated: '$fine_name' is now KSh $amount_display in $department. Review this rule under My Fines. $focus_tag";
                while ($department_member = $department_members->fetch_assoc()) {
                    member_action_insert_notification($conn, (int)$department_member['id'], 'member', $message);
                }
            }

            $staff_message = "Department fine rule updated: '$fine_name' is now KSh $amount_display in $department. Review Department Fine Rules. $focus_tag";
            $admins = $conn->query("SELECT id FROM admins");
            if ($admins) {
                while ($admin = $admins->fetch_assoc()) {
                    member_action_insert_notification($conn, (int)$admin['id'], 'admin', $staff_message);
                }
            }
            $pastors = $conn->query("SELECT id FROM pastors WHERE is_approved = 1");
            if ($pastors) {
                while ($pastor = $pastors->fetch_assoc()) {
                    member_action_insert_notification($conn, (int)$pastor['id'], 'pastor', $staff_message);
                }
            }
            header("Location: member_dashboard.php?tab=discipline&disc_dept=" . urlencode($department) . "&success=" . urlencode('Fine rule updated and notifications sent.'));
        } else {
            header("Location: member_dashboard.php?tab=discipline&disc_dept=" . urlencode($template['department']) . "&error=" . urlencode('Could not update the fine rule. Please try again.'));
        }
        exit();
    }
    
    // Discipline Master: Delete Fine Template
    elseif ($action === 'delete_fine_template' && isset($_GET['id'])) {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        if (member_has_role($member_query['church_role'] ?? '', 'discipline master')) {
            $id = (int)$_GET['id'];
            $active_dept = $_GET['active_dept'] ?? $member_query['department'];
            $conn->query("DELETE FROM fine_templates WHERE id = $id");
            header("Location: member_dashboard.php?tab=discipline&disc_dept=" . urlencode($active_dept) . "&success=Fine rule removed");
            exit();
        }
    }
    
    // Discipline Master: Issue Fine
    elseif ($action === 'issue_fine' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        if (member_has_role($member_query['church_role'] ?? '', 'discipline master')) {
            $dept = isset($_POST['active_dept']) ? trim($_POST['active_dept']) : trim($member_query['department']);
            $target_id = (int)$_POST['member_id'];
            $template_id = (int)$_POST['fine_template_id'];
            
            $template = $conn->query("SELECT fine_name, amount FROM fine_templates WHERE id = $template_id")->fetch_assoc();
            $target_member_result = $conn->query("SELECT first_name, last_name FROM members WHERE id = $target_id LIMIT 1");
            $target_member = $target_member_result ? $target_member_result->fetch_assoc() : null;
            if ($template && $target_member) {
                $fine_reason = $template['fine_name'];
                $fine_amount = (float)$template['amount'];
                $fine_insert = $conn->prepare("INSERT INTO member_fines (member_id, discipline_master_id, department, fine_reason, amount) VALUES (?, ?, ?, ?, ?)");
                if ($fine_insert) {
                    $fine_insert->bind_param('iissd', $target_id, $member_id, $dept, $fine_reason, $fine_amount);
                    $fine_saved = $fine_insert->execute();
                    $fine_id = $fine_saved ? (int)$fine_insert->insert_id : 0;
                    $fine_insert->close();
                } else {
                    $fine_saved = false;
                    $fine_id = 0;
                }

                if ($fine_saved) {
                    $issuer_name = trim(($member_query['first_name'] ?? '') . ' ' . ($member_query['last_name'] ?? ''));
                    $target_name = trim($target_member['first_name'] . ' ' . $target_member['last_name']);
                    $amount_display = number_format($fine_amount, 2);
                    $all_notifications_sent = true;
                    $member_notification_message = "Fine issued: You were fined KSh $amount_display for '$fine_reason' by Discipline Master $issuer_name. Fine record #$fine_id. View the details under My Fines.";
                    if (!member_action_insert_notification($conn, $target_id, 'member', $member_notification_message)) {
                        $all_notifications_sent = false;
                    }

                    $staff_notification_message = "Fine issued to $target_name: KSh $amount_display for '$fine_reason' by Discipline Master $issuer_name. Review it under Discipline Reports.";
                    foreach (['pastors' => 'pastor', 'admins' => 'admin'] as $staff_table => $staff_type) {
                        try {
                            $staff_result = $conn->query("SELECT id FROM `$staff_table`");
                        } catch (Throwable $error) {
                            error_log('Could not load ' . $staff_type . ' notification recipients: ' . $error->getMessage());
                            $staff_result = false;
                        }

                        if (!$staff_result) {
                            if ($conn->error !== '') {
                                error_log('Could not load ' . $staff_type . ' notification recipients: ' . $conn->error);
                            }
                            $all_notifications_sent = false;
                            continue;
                        }

                        if ($staff_result->num_rows === 0) {
                            error_log('Fine notification not sent: no ' . $staff_type . ' accounts were found.');
                            $all_notifications_sent = false;
                            continue;
                        }

                        while ($staff = $staff_result->fetch_assoc()) {
                            if (!member_action_insert_notification($conn, (int)$staff['id'], $staff_type, $staff_notification_message)) {
                                $all_notifications_sent = false;
                            }
                        }
                    }

                    $success_message = $all_notifications_sent
                        ? 'Fine issued successfully'
                        : 'Fine issued, but one or more notifications could not be delivered. Please inform the pastor or admin.';
                    header("Location: member_dashboard.php?tab=discipline&disc_dept=" . urlencode($dept) . "&success=" . urlencode($success_message));
                    exit();
                }
            }

            header("Location: member_dashboard.php?tab=discipline&disc_dept=" . urlencode($dept) . "&error=" . urlencode('Fine could not be issued. Please verify the member and fine rule.'));
            exit();
        }
    }
    
    // Member: Initiate Fine Payment
    elseif ($action === 'initiate_fine_payment' && isset($_GET['id'])) {
        $fine_id = (int)$_GET['id'];
        
        // Ensure the fine belongs to the member
        $fine = $conn->query("SELECT * FROM member_fines WHERE id = $fine_id AND member_id = $member_id")->fetch_assoc();
        if ($fine && $fine['status'] == 'Unpaid') {
            $payment_started = $conn->query("UPDATE member_fines SET status = 'Pending Verification', payment_decline_reason = NULL WHERE id = $fine_id AND member_id = $member_id AND status = 'Unpaid'");
            if ($payment_started && $conn->affected_rows === 1) {
                $department = trim((string)$fine['department']);
                $department_sql = $conn->real_escape_string($department);
                $payer_result = $conn->query("SELECT first_name, last_name FROM members WHERE id = $member_id LIMIT 1");
                $payer = $payer_result ? $payer_result->fetch_assoc() : null;
                $payer_name = trim(($payer['first_name'] ?? '') . ' ' . ($payer['last_name'] ?? ''));
                $fine_reason = trim((string)$fine['fine_reason']);
                $fine_amount = number_format((float)$fine['amount'], 2);
                $message = "$payer_name initiated a fine payment in $department department. Fine: $fine_reason (KSh $fine_amount). Fine record #$fine_id. Please verify in Financial Records.";
                $treasurers = $conn->query("SELECT id FROM members
                    WHERE is_approved = 1 AND id <> $member_id
                      AND department = '$department_sql'
                      AND LOWER(church_role) LIKE '%treasurer%'");
                if ($treasurers) {
                    while ($treasurer = $treasurers->fetch_assoc()) {
                        member_action_insert_notification($conn, (int)$treasurer['id'], 'member', $message);
                    }
                }

                $fine_focus_tag = "[focus-fine-id:$fine_id]";
                $discipline_message = "Fine payment awaiting Treasurer verification: $payer_name says the fine for '$fine_reason' (KSh $fine_amount) in $department is paid. $fine_focus_tag";
                member_action_notify_discipline_masters_for_department($conn, $department, $discipline_message);
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
            $fine_result = $conn->query("SELECT mf.*, m.first_name, m.last_name
                FROM member_fines mf JOIN members m ON m.id = mf.member_id
                WHERE mf.id = $fine_id AND mf.status = 'Pending Verification'
                LIMIT 1");
            $fine = $fine_result ? $fine_result->fetch_assoc() : null;
            $verified = false;
            if ($fine) {
                $paid = $conn->query("UPDATE member_fines SET status = 'Paid', paid_at = CURRENT_TIMESTAMP WHERE id = $fine_id AND status = 'Pending Verification'");
                $verified = $paid && $conn->affected_rows === 1;
            }
            if ($verified) {
                $department = trim((string)$fine['department']);
                $fine_reason = trim((string)$fine['fine_reason']);
                $fine_amount = number_format((float)$fine['amount'], 2);
                $fine_focus_tag = "[focus-fine-id:$fine_id]";
                $member_message = "Your fine payment for $fine_reason (KSh $fine_amount) in $department has been verified and marked paid by the Treasurer. $fine_focus_tag";
                member_action_insert_notification($conn, (int)$fine['member_id'], 'member', $member_message);

                $member_name = trim($fine['first_name'] . ' ' . $fine['last_name']);
                $staff_message = "Fine payment verified: $member_name's fine for $fine_reason (KSh $fine_amount) in $department was marked Paid by the Treasurer. $fine_focus_tag";
                $admins = $conn->query("SELECT id FROM admins");
                if ($admins) {
                    while ($admin = $admins->fetch_assoc()) {
                        member_action_insert_notification($conn, (int)$admin['id'], 'admin', $staff_message);
                    }
                }
                $pastors = $conn->query("SELECT id FROM pastors WHERE is_approved = 1");
                if ($pastors) {
                    while ($pastor = $pastors->fetch_assoc()) {
                        member_action_insert_notification($conn, (int)$pastor['id'], 'pastor', $staff_message);
                    }
                }

                $discipline_message = "Discipline Master fine payment update: $member_name's fine for '$fine_reason' (KSh $fine_amount) in $department department was marked Paid by the Treasurer. $fine_focus_tag";
                member_action_notify_discipline_masters_for_department($conn, $department, $discipline_message);

            }
            $result_message = $verified
                ? 'Fine payment verified successfully'
                : 'This fine is no longer awaiting verification';
            $redirect_department = $verified ? $department : '';
            $redirect = 'member_dashboard.php?tab=financials';
            if ($redirect_department !== '') {
                $redirect .= '&treasurer_dept=' . urlencode($redirect_department);
            }
            $redirect .= '&' . ($verified ? 'success=' : 'error=') . urlencode($result_message);
            if ($verified) {
                $redirect .= '&verified_fine=' . $fine_id . '#paid-fine-' . $fine_id;
            }
            header("Location: $redirect");
            exit();
        }
    }

    // Treasurer: Decline a payment that could not be confirmed as received.
    elseif ($action === 'decline_fine_payment' && (isset($_POST['fine_id']) || isset($_GET['id']))) {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        $is_treasurer = false;
        foreach (get_member_roles($member_query['church_role'] ?? '') as $role) {
            if (strpos($role, 'treasurer') !== false) {
                $is_treasurer = true;
                break;
            }
        }

        if ($is_treasurer) {
            $fine_id = (int)($_POST['fine_id'] ?? $_GET['id']);
            $decline_reason = trim((string)($_POST['decline_reason'] ?? ''));
            $fine_result = $conn->query("SELECT mf.*, m.first_name, m.last_name
                FROM member_fines mf JOIN members m ON m.id = mf.member_id
                WHERE mf.id = $fine_id AND mf.status = 'Pending Verification'
                LIMIT 1");
            $fine = $fine_result ? $fine_result->fetch_assoc() : null;
            $declined = false;
            $reason_length = function_exists('mb_strlen') ? mb_strlen($decline_reason, 'UTF-8') : strlen($decline_reason);
            if ($fine && $decline_reason !== '' && $reason_length <= 500) {
                $update = $conn->prepare("UPDATE member_fines SET status = 'Unpaid', paid_at = NULL, payment_decline_reason = ? WHERE id = ? AND status = 'Pending Verification'");
                if ($update) {
                    $update->bind_param('si', $decline_reason, $fine_id);
                    $declined = $update->execute() && $update->affected_rows === 1;
                    $update->close();
                }
            }

            $department = trim((string)($fine['department'] ?? ''));
            $redirect = 'member_dashboard.php?tab=financials';
            if ($department !== '') {
                $redirect .= '&treasurer_dept=' . urlencode($department);
            }
            if ($declined) {
                $fine_reason = trim((string)$fine['fine_reason']);
                $fine_amount = number_format((float)$fine['amount'], 2);
                $fine_focus_tag = "[focus-fine-id:$fine_id]";
                $member_message = "Your payment for the fine '$fine_reason' (KSh $fine_amount) in $department was declined by the Treasurer. Reason: $decline_reason. The fine is unpaid again. Please contact the Treasurer if you already paid. $fine_focus_tag";
                member_action_insert_notification($conn, (int)$fine['member_id'], 'member', $member_message);
                $member_name = trim($fine['first_name'] . ' ' . $fine['last_name']);
                $discipline_message = "Discipline Master fine payment declined in $department: $member_name's fine for '$fine_reason' (KSh $fine_amount) was declined by the Treasurer. Reason: $decline_reason. The fine is Unpaid again. $fine_focus_tag";
                member_action_notify_discipline_masters_for_department($conn, $department, $discipline_message);
                $redirect .= '&success=' . urlencode('Payment declined. The fine has been returned to Unpaid.');
                $redirect .= '&declined_fine=' . $fine_id;
            } elseif ($fine && ($decline_reason === '' || $reason_length > 500)) {
                $redirect .= '&error=' . urlencode('Enter a decline reason of up to 500 characters.');
            } else {
                $redirect .= '&error=' . urlencode('This fine is no longer awaiting payment verification.');
            }

            header('Location: ' . $redirect);
            exit();
        }
    }
    
    // Discipline Master: Send a repeatable reminder for an unpaid fine.
    elseif ($action === 'remind_fine_meeting' && isset($_GET['id'])) {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        $discipline_departments = [];
        $raw_roles = preg_split('/\s*,\s*/', (string)($member_query['church_role'] ?? ''));
        foreach ($raw_roles as $raw_role) {
            if (!in_array(normalize_role_name($raw_role), ['discipline master', 'vice discipline master'], true)) {
                continue;
            }
            $role_department = trim((string)($member_query['department'] ?? ''));
            if (preg_match('/\(([^)]+)\)/', $raw_role, $role_context)
                && strtolower(trim($role_context[1])) !== 'subsidiary') {
                $role_department = trim($role_context[1]);
            }
            if ($role_department !== '') {
                $discipline_departments[] = $role_department;
            }
        }

        $fine_id = (int)$_GET['id'];
        $fine_stmt = $conn->prepare("SELECT member_id, fine_reason, amount, department, status FROM member_fines WHERE id = ? LIMIT 1");
        $fine = null;
        if ($fine_stmt) {
            $fine_stmt->bind_param('i', $fine_id);
            $fine_stmt->execute();
            $fine_result = $fine_stmt->get_result();
            $fine = $fine_result ? $fine_result->fetch_assoc() : null;
            $fine_stmt->close();
        }

        $department = trim((string)($fine['department'] ?? ''));
        $redirect = 'member_dashboard.php?tab=discipline';
        if ($department !== '') {
            $redirect .= '&disc_dept=' . urlencode($department);
        }
        if (!$fine) {
            header('Location: ' . $redirect . '&error=' . urlencode('This fine could not be found. No reminder was sent.'));
            exit();
        }

        $department_is_allowed = false;
        foreach ($discipline_departments as $discipline_department) {
            if (department_matches($department, $discipline_department)) {
                $department_is_allowed = true;
                break;
            }
        }

        if (!$department_is_allowed) {
            header('Location: ' . $redirect . '&error=' . urlencode("You are not authorized to remind members about this department's fines."));
            exit();
        }

        if (($fine['status'] ?? '') !== 'Unpaid') {
            header('Location: ' . $redirect . '&error=' . urlencode('This fine is no longer unpaid, so no reminder was sent.'));
            exit();
        }

        $update_stmt = $conn->prepare("UPDATE member_fines SET reminded_for_meeting = 1 WHERE id = ? AND status = 'Unpaid'");
        if ($update_stmt) {
            $update_stmt->bind_param('i', $fine_id);
            $update_stmt->execute();
            $update_stmt->close();
        }

        $amount_fmt = number_format((float)$fine['amount'], 2);
        $fine_reason = trim((string)$fine['fine_reason']);
        $message = "Discipline Master Reminder: Your fine for '$fine_reason' (KSh $amount_fmt) in $department is still unpaid. Please pay it as soon as possible. [focus-fine-id:$fine_id]";
        $notification_sent = member_action_insert_notification($conn, (int)$fine['member_id'], 'member', $message);
        if (!$notification_sent) {
            header('Location: ' . $redirect . '&error=' . urlencode('The reminder could not be sent. Please try again.'));
            exit();
        }

        header('Location: ' . $redirect . '&success=' . urlencode('Payment reminder sent successfully.'));
        exit();
    }
    
    // Discipline Master: Report Member
    elseif ($action === 'report_member' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        if (member_has_role($member_query['church_role'] ?? '', 'discipline master')) {
            $dept = isset($_POST['active_dept']) ? trim($_POST['active_dept']) : trim($member_query['department']);
            $target_id = (int)$_POST['member_id'];
            $reason = trim($_POST['reason'] ?? '');
            $target_dept_condition = department_match_sql($conn, 'department', $dept);
            $target = $conn->query("SELECT id, first_name, last_name FROM members WHERE id = $target_id AND is_approved = 1 AND $target_dept_condition LIMIT 1");
            $target_member = $target ? $target->fetch_assoc() : null;

            if (!$target_member || $reason === '') {
                header("Location: member_dashboard.php?tab=discipline&error=" . urlencode('Select an approved member in this department and provide a report reason.'));
                exit();
            }

            $report_insert = $conn->prepare("INSERT INTO member_reports (reported_member_id, reported_by_id, department, reason) VALUES (?, ?, ?, ?)");
            if (!$report_insert) {
                header("Location: member_dashboard.php?tab=discipline&error=" . urlencode('The disciplinary report could not be saved.'));
                exit();
            }
            $report_insert->bind_param('iiss', $target_id, $member_id, $dept, $reason);
            $report_saved = $report_insert->execute();
            $report_id = (int)$report_insert->insert_id;
            $report_insert->close();
            if (!$report_saved || $report_id < 1) {
                header("Location: member_dashboard.php?tab=discipline&error=" . urlencode('The disciplinary report could not be saved.'));
                exit();
            }

            $target_name = trim($target_member['first_name'] . ' ' . $target_member['last_name']);
            $department_condition = department_match_sql($conn, 'department', $dept);
            $leaders = $conn->query("SELECT id FROM members WHERE $department_condition AND (LOWER(church_role) LIKE '%chairman%' OR LOWER(church_role) LIKE '%chairlady%' OR LOWER(church_role) LIKE '%chairperson%')");
            if ($leaders) {
                while ($leader = $leaders->fetch_assoc()) {
                    $message = 'Disciplinary report pending chairman review: report #' . $report_id . ' for ' . $target_name . ' in ' . $dept . '. Reason: "' . $reason . '".';
                    member_action_insert_notification($conn, (int)$leader['id'], 'member', $message);
                }
            }

            $title = ($dept === 'Womens Ministry') ? 'Chairlady' : 'Chairman';
            header("Location: member_dashboard.php?tab=discipline&disc_dept=" . urlencode($dept) . "&success=Member reported to $title");
            exit();
        }
    }
    
    // Chairman: Forward Report to Pastor
    elseif ($action === 'forward_report' && isset($_GET['id'])) {
        $report_id = (int)$_GET['id'];
        $chairman = $conn->query("SELECT first_name, last_name, department, church_role FROM members WHERE id = $member_id LIMIT 1")->fetch_assoc();
        $report_stmt = $conn->prepare("SELECT mr.id, mr.reported_by_id, mr.department, mr.reason, mr.status, m.first_name, m.last_name FROM member_reports mr JOIN members m ON mr.reported_member_id = m.id WHERE mr.id = ? LIMIT 1");
        $report = null;
        if ($report_stmt) {
            $report_stmt->bind_param('i', $report_id);
            $report_stmt->execute();
            $found_id = $reported_by_id = $found_department = $found_reason = $found_status = $reported_first = $reported_last = null;
            $report_stmt->bind_result($found_id, $reported_by_id, $found_department, $found_reason, $found_status, $reported_first, $reported_last);
            if ($report_stmt->fetch()) {
                $report = [
                    'id' => $found_id,
                    'reported_by_id' => $reported_by_id,
                    'department' => $found_department,
                    'reason' => $found_reason,
                    'status' => $found_status,
                    'first_name' => $reported_first,
                    'last_name' => $reported_last
                ];
            }
            $report_stmt->close();
        }

        if (!$chairman || !$report || $report['status'] !== 'Pending Chairman' || !member_can_manage_disciplinary_report($chairman, $report['department'])) {
            header("Location: member_dashboard.php?tab=manage_department&error=" . urlencode('You cannot forward this report or it has already been reviewed.'));
            exit();
        }

        $update_report = $conn->prepare("UPDATE member_reports SET status = 'Forwarded to Pastor' WHERE id = ? AND status = 'Pending Chairman'");
        $forwarded = false;
        if ($update_report) {
            $update_report->bind_param('i', $report_id);
            $forwarded = $update_report->execute() && $update_report->affected_rows === 1;
            $update_report->close();
        }
        if (!$forwarded) {
            header("Location: member_dashboard.php?tab=manage_department&error=" . urlencode('This report was already forwarded or could not be updated.'));
            exit();
        }

        $reported_name = trim($report['first_name'] . ' ' . $report['last_name']);
        $chairman_name = trim(($chairman['first_name'] ?? '') . ' ' . ($chairman['last_name'] ?? ''));
        $discipline_master_message = 'Disciplinary report outcome: report #' . $report_id . ' for ' . $reported_name . ' in ' . $report['department'] . ' was forwarded to the Pastor by ' . $chairman_name . '.';
        member_action_insert_notification($conn, (int)$report['reported_by_id'], 'member', $discipline_master_message);
        $pastors = $conn->query("SELECT id FROM pastors");
        if ($pastors) {
            while ($pastor = $pastors->fetch_assoc()) {
                $message = 'Disciplinary report forwarded to Pastor: report #' . $report_id . ' for ' . $reported_name . ' in ' . $report['department'] . '. Reason: "' . $report['reason'] . '". Forwarded by ' . $chairman_name . '.';
                member_action_insert_notification($conn, (int)$pastor['id'], 'pastor', $message);
            }
        }

        header("Location: member_dashboard.php?tab=manage_department&success=Report forwarded to Pastor");
        exit();
    }
    
    // Chairman: Resolve Report Locally
    elseif ($action === 'resolve_report' && isset($_GET['id'])) {
        $report_id = (int)$_GET['id'];
        $chairman = $conn->query("SELECT first_name, last_name, department, church_role FROM members WHERE id = $member_id LIMIT 1")->fetch_assoc();
        $resolve_stmt = $conn->prepare("SELECT mr.id, mr.reported_by_id, mr.department, mr.status, m.first_name, m.last_name FROM member_reports mr JOIN members m ON mr.reported_member_id = m.id WHERE mr.id = ? LIMIT 1");
        $report = null;
        if ($resolve_stmt) {
            $resolve_stmt->bind_param('i', $report_id);
            $resolve_stmt->execute();
            $found_id = $reported_by_id = $found_department = $found_status = $reported_first = $reported_last = null;
            $resolve_stmt->bind_result($found_id, $reported_by_id, $found_department, $found_status, $reported_first, $reported_last);
            if ($resolve_stmt->fetch()) {
                $report = [
                    'id' => $found_id,
                    'reported_by_id' => $reported_by_id,
                    'department' => $found_department,
                    'status' => $found_status,
                    'first_name' => $reported_first,
                    'last_name' => $reported_last
                ];
            }
            $resolve_stmt->close();
        }

        if (!$chairman || !$report || $report['status'] !== 'Pending Chairman' || !member_can_manage_disciplinary_report($chairman, $report['department'])) {
            header("Location: member_dashboard.php?tab=manage_department&error=" . urlencode('You cannot resolve this report or it has already been reviewed.'));
            exit();
        }

        $update_report = $conn->prepare("UPDATE member_reports SET status = 'Resolved' WHERE id = ? AND status = 'Pending Chairman'");
        $resolved = false;
        if ($update_report) {
            $update_report->bind_param('i', $report_id);
            $resolved = $update_report->execute() && $update_report->affected_rows === 1;
            $update_report->close();
        }
        if (!$resolved) {
            header("Location: member_dashboard.php?tab=manage_department&error=" . urlencode('This report was already reviewed or could not be updated.'));
            exit();
        }

        $reported_name = trim($report['first_name'] . ' ' . $report['last_name']);
        $chairman_name = trim(($chairman['first_name'] ?? '') . ' ' . ($chairman['last_name'] ?? ''));
        $discipline_master_message = 'Disciplinary report outcome: report #' . $report_id . ' for ' . $reported_name . ' in ' . $report['department'] . ' was resolved with the member by ' . $chairman_name . '.';
        member_action_insert_notification($conn, (int)$report['reported_by_id'], 'member', $discipline_master_message);
        header("Location: member_dashboard.php?tab=manage_department&success=Report marked as resolved");
        exit();
    }
    
    // Graduands Secretary: Add to Graduation List
    elseif ($action === 'add_graduand' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $member_result = $conn->query("SELECT * FROM members WHERE id = $member_id LIMIT 1");
        $member_query = $member_result ? $member_result->fetch_assoc() : null;
        if (member_has_role($member_query['church_role'] ?? '', 'graduands secretary')) {
            $department = trim((string)($member_query['department'] ?? ''));
            $target_id = (int)($_POST['member_id'] ?? 0);
            $year = (int)date('Y');

            $target_stmt = $conn->prepare("SELECT id, first_name, last_name FROM members
                WHERE id = ? AND is_approved = 1 AND LOWER(TRIM(department)) = LOWER(TRIM(?)) LIMIT 1");
            if (!$target_stmt) {
                header("Location: member_dashboard.php?tab=graduation_panel&error=" . urlencode('Could not verify the selected member.'));
                exit();
            }
            $target_stmt->bind_param('is', $target_id, $department);
            $target_stmt->execute();
            $target_result = $target_stmt->get_result();
            $target = $target_result ? $target_result->fetch_assoc() : null;
            $target_stmt->close();
            if (!$target) {
                header("Location: member_dashboard.php?tab=graduation_panel&error=" . urlencode('Choose an approved member from your department.'));
                exit();
            }

            $existing_stmt = $conn->prepare("SELECT id FROM graduation_list WHERE member_id = ? AND graduation_year = ? LIMIT 1");
            if ($existing_stmt) {
                $existing_stmt->bind_param('ii', $target_id, $year);
                $existing_stmt->execute();
                $existing_result = $existing_stmt->get_result();
                $already_added = $existing_result && $existing_result->num_rows > 0;
                $existing_stmt->close();
                if ($already_added) {
                    header("Location: member_dashboard.php?tab=graduation_panel&error=" . urlencode('This member is already on the graduation roster.'));
                    exit();
                }
            }

            $insert_stmt = $conn->prepare("INSERT INTO graduation_list (member_id, department, graduation_year, added_by) VALUES (?, ?, ?, ?)");
            if (!$insert_stmt) {
                header("Location: member_dashboard.php?tab=graduation_panel&error=" . urlencode('Could not add this member to the graduation roster.'));
                exit();
            }
            $insert_stmt->bind_param('isii', $target_id, $department, $year, $member_id);
            $inserted = $insert_stmt->execute();
            $graduation_entry_id = (int)$conn->insert_id;
            $insert_stmt->close();
            if (!$inserted || $graduation_entry_id < 1) {
                header("Location: member_dashboard.php?tab=graduation_panel&error=" . urlencode('Could not add this member to the graduation roster.'));
                exit();
            }

            $target_name = trim($target['first_name'] . ' ' . $target['last_name']);
            $graduation_group = notification_department_group_key($department);
            $graduation_row = 'graduation-roster-' . $graduation_entry_id;
            $notification_stmt = $conn->prepare("INSERT INTO notifications (user_id, user_type, message) VALUES (?, ?, ?)");
            $send_graduation_notification = function ($recipient_id, $recipient_type, $message) use ($notification_stmt, $graduation_group, $graduation_row) {
                if (!$notification_stmt) return false;
                $recipient_id = (int)$recipient_id;
                $recipient_type = (string)$recipient_type;
                $routed_message = notification_with_route($message, 'graduation_info', $graduation_group, $graduation_row);
                $notification_stmt->bind_param('iss', $recipient_id, $recipient_type, $routed_message);
                return $notification_stmt->execute();
            };

            $department_stmt = $conn->prepare("SELECT id FROM members WHERE is_approved = 1 AND LOWER(TRIM(department)) = LOWER(TRIM(?))");
            if ($department_stmt) {
                $department_stmt->bind_param('s', $department);
                $department_stmt->execute();
                $department_members = $department_stmt->get_result();
                while ($department_member = $department_members->fetch_assoc()) {
                    $recipient_id = (int)$department_member['id'];
                    $member_message = $recipient_id === $target_id
                        ? "You have been added to the $department graduation roster for $year."
                        : "$target_name has been added to the $department graduation roster for $year.";
                    $send_graduation_notification($recipient_id, 'member', $member_message);
                }
                $department_stmt->close();
            }

            $oversight_message = "New graduand: $target_name has been added to the $department graduation roster for $year.";
            $pastors = $conn->query("SELECT id FROM pastors WHERE is_approved = 1");
            if ($pastors) {
                while ($pastor = $pastors->fetch_assoc()) {
                    $send_graduation_notification($pastor['id'], 'pastor', $oversight_message);
                }
            }
            $admins = $conn->query("SELECT id FROM admins");
            if ($admins) {
                while ($admin = $admins->fetch_assoc()) {
                    $send_graduation_notification($admin['id'], 'admin', $oversight_message);
                }
            }
            if ($notification_stmt) $notification_stmt->close();

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
            $dept = isset($_POST['active_dept']) ? $conn->real_escape_string(trim($_POST['active_dept'])) : $conn->real_escape_string($member_query['department']);
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

            header("Location: member_dashboard.php?tab=prayer_panel&prayer_dept=" . urlencode($dept) . "&success=Prayer session scheduled and department notified");
            exit();
        }
    }

    // Prayer Coordinator: Add Custom Prayer Item
    elseif ($action === 'add_prayer_item' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        if (member_has_role($member_query['church_role'] ?? '', 'prayer coordinator')) {
            $dept = isset($_POST['active_dept']) ? $conn->real_escape_string(trim($_POST['active_dept'])) : $conn->real_escape_string($member_query['department']);
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

            header("Location: member_dashboard.php?tab=prayer_panel&prayer_dept=" . urlencode($dept) . "&success=Prayer item added and department notified");
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
            $dept = isset($_POST['active_dept']) ? $conn->real_escape_string(trim($_POST['active_dept'])) : $conn->real_escape_string($member_query['department']);
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
            header("Location: member_dashboard.php?tab=manage_sport&sport_dept=" . urlencode($dept) . "&success=Match scheduled and members notified!");
            exit();
        }
    }

    // Sport Secretary: Post Announcement
    elseif ($action === 'post_sport_announcement' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $member_query = $conn->query("SELECT * FROM members WHERE id = $member_id")->fetch_assoc();
        if (member_has_role($member_query['church_role'] ?? '', 'sport secretary') || member_has_role($member_query['church_role'] ?? '', 'sports secretary')) {
            $dept = isset($_POST['active_dept']) ? $conn->real_escape_string(trim($_POST['active_dept'])) : $conn->real_escape_string($member_query['department']);
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
            header("Location: member_dashboard.php?tab=manage_sport&sport_dept=" . urlencode($dept) . "&success=Announcement posted successfully!");
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
