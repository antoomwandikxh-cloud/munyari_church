<?php
session_start();
require_once 'db_connect.php';
require_once 'role_departments.php';

if (!isset($_SESSION['pastor_id'])) {
    header("Location: login.php");
    exit();
}

$pastor_id = $_SESSION['pastor_id'];

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // Manage Members Actions
    if ($action === 'approve' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $conn->query("UPDATE members SET is_approved = 1 WHERE id = $id");
        $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($id, 'member', 'Your account has been approved by the pastor!')");
        header("Location: pastor_dashboard.php?tab=manage_members&success=Member approved");
        exit();
    }
    elseif ($action === 'deactivate' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $conn->query("UPDATE members SET is_approved = 0 WHERE id = $id");
        header("Location: pastor_dashboard.php?tab=manage_members&success=Member deactivated");
        exit();
    }
    elseif ($action === 'remove' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $conn->query("DELETE FROM member_messages WHERE member_id = $id");
        $conn->query("DELETE FROM appointments WHERE member_id = $id");
        $conn->query("DELETE FROM notifications WHERE user_id = $id AND user_type = 'member'");
        $conn->query("DELETE FROM members WHERE id = $id");
        header("Location: pastor_dashboard.php?tab=manage_members&success=Member permanently removed");
        exit();
    }
    
    // Assign Role Action (with duplicate check)
    elseif ($action === 'assign_role' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $member_id = (int)$_POST['member_id'];
        $raw_role = trim($_POST['role'] ?? '');
        $assignment_department = trim($_POST['assignment_department'] ?? '');
        $role = $conn->real_escape_string($raw_role);
        $new_normalized = normalize_role_name($raw_role);

        $allowed_departments = role_assignment_departments($raw_role, $assignment_department);

        // Roles that can be held by multiple people at the same time (unlimited)
        $unlimited_roles = ['usher'];
        $is_unlimited = in_array($new_normalized, $unlimited_roles, true);

        // Check if role is already assigned (search inside combined roles too)
        if (!$is_unlimited) {
            // Find all members who have this role (including combined roles e.g. "Mama Youth, Women Secretary")
            $check_q = $conn->query("SELECT id, church_role FROM members WHERE church_role IS NOT NULL AND church_role != '' AND id != $member_id AND is_approved = 1");
            $already_exists = false;
            if ($check_q) {
                while ($cr = $check_q->fetch_assoc()) {
                    $parts = array_filter(array_map('trim', explode(',', str_replace('&', ',', $cr['church_role']))));
                    foreach ($parts as $p) {
                        if (normalize_role_name($p) === $new_normalized) {
                            // For subsidiary roles, scope is per-department
                            if (is_subsidiary_department_role($raw_role) && !empty($assignment_department)) {
                                $dept_of = $conn->query("SELECT department FROM members WHERE id = {$cr['id']}")->fetch_assoc()['department'] ?? '';
                                if (department_matches($dept_of, $assignment_department)) {
                                    $already_exists = true;
                                }
                            } elseif (!is_subsidiary_department_role($raw_role)) {
                                $already_exists = true;
                            }
                            break;
                        }
                    }
                    if ($already_exists) break;
                }
            }
            if ($already_exists) {
                $scope_text = is_subsidiary_department_role($raw_role) && !empty($assignment_department) ? " in $assignment_department" : "";
                header("Location: pastor_dashboard.php?tab=assign_roles&error=" . urlencode("This role is already assigned to another member$scope_text"));
                exit();
            }
        }

        $member_data = $conn->query("SELECT department, church_role FROM members WHERE id = $member_id AND is_approved = 1")->fetch_assoc();
        $member_department = $member_data['department'] ?? '';
        $can_receive = $member_data && (empty($allowed_departments) || array_filter($allowed_departments, function($department) use ($member_department) {
            return department_matches($member_department, $department);
        }));
        if (!$can_receive) {
            header("Location: pastor_dashboard.php?tab=assign_roles&error=This role can only be assigned to members from the allowed department(s)");
            exit();
        }

        $existing_role = trim($member_data['church_role'] ?? '');

        $role_to_set = $role;
        $dept_sql    = "";

        // Determine if assigning to a Mama/Baba Youth (they keep their home dept)
        $existing_roles_arr = array_filter(array_map('trim', explode(',', str_replace('&', ',', $existing_role))));
        $member_is_youth_advisor = false;
        foreach ($existing_roles_arr as $er) {
            if (is_youth_advisor_role(normalize_role_name($er))) {
                $member_is_youth_advisor = true;
                break;
            }
        }
        // Also check if the NEW role being assigned is mama/baba youth
        $assigning_youth_advisor = is_youth_advisor_role($new_normalized);

        if (!empty($existing_role) && normalize_role_name($existing_role) !== 'member') {
            $already_has = false;
            foreach ($existing_roles_arr as $r) {
                if (normalize_role_name($r) === $new_normalized) {
                    $already_has = true;
                    break;
                }
            }

            if (!$already_has) {
                // Combine roles
                $role_to_set = $conn->real_escape_string($existing_role . ', ' . $raw_role);
                // Only update department if:
                // - member has no dept yet, AND
                // - not a youth advisor (mama/baba youth keep home dept), AND
                // - the new role has a specific dept mapping
                if ((empty($member_department) || $member_department === 'None') && !$member_is_youth_advisor && !$assigning_youth_advisor) {
                    $dept_sql = role_department_sql($conn, $raw_role);
                }
                // If assigning mama/baba youth to someone with no dept, set their home dept (Women's/Elders)
                if ($assigning_youth_advisor && (empty($member_department) || $member_department === 'None')) {
                    $home_dept = ($new_normalized === 'mama youth') ? 'Womens Ministry' : 'Elders';
                    $esc_home = $conn->real_escape_string($home_dept);
                    $dept_sql = ", department = '$esc_home'";
                }
            }
            // else: already has role, no change to dept
        } else {
            // Fresh assignment (no existing role or just 'Member')
            if ($assigning_youth_advisor) {
                // Set home department: Women's Ministry for Mama Youth, Elders for Baba Youth
                $home_dept = ($new_normalized === 'mama youth') ? 'Womens Ministry' : 'Elders';
                if (empty($member_department) || $member_department === 'None' || !department_matches($member_department, $home_dept)) {
                    $esc_home = $conn->real_escape_string($home_dept);
                    $dept_sql = ", department = '$esc_home'";
                }
            } else {
                $dept_sql = role_department_sql($conn, $raw_role);
            }
        }

        $conn->query("UPDATE members SET church_role = '$role_to_set'$dept_sql WHERE id = $member_id");
        $scope_text = !empty($assignment_department) && is_subsidiary_department_role($raw_role) ? " under $assignment_department" : "";

        // Gender-aware notification for Youth Chairperson (covers 'Youth Chairman' and 'Youth Chairperson')
        if (is_youth_chairperson_role($raw_role)) {
            $member_data = $conn->query("SELECT first_name, gender FROM members WHERE id = $member_id")->fetch_assoc();
            $member_first_name = $member_data['first_name'] ?? 'Member';
            $member_gender = $member_data['gender'] ?? '';
            $gendered_title = get_youth_chairperson_title($member_gender, $raw_role);
            $msg = $conn->real_escape_string("Welcome $member_first_name as $gendered_title in the Youth Ministry");
        } elseif (is_building_chairperson_role($raw_role)) {
            $member_data = $conn->query("SELECT first_name, gender FROM members WHERE id = $member_id")->fetch_assoc();
            $member_first_name = $member_data['first_name'] ?? 'Member';
            $member_gender = $member_data['gender'] ?? '';
            $gendered_title = get_building_chairperson_title($member_gender, $raw_role);
            $msg = $conn->real_escape_string("Welcome $member_first_name as $gendered_title in the Building & Construction Department");
        } else {
            $msg = $conn->real_escape_string("You have been assigned a new role: $raw_role$scope_text.");
        }
        $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($member_id, 'member', '$msg')");

        header("Location: pastor_dashboard.php?tab=assign_roles&success=Role assigned successfully");
        exit();
    }
    elseif ($action === 'remove_role' && isset($_GET['id']) && isset($_GET['role'])) {
        $member_id = (int)$_GET['id'];
        $role_to_remove = urldecode($_GET['role']);
        $member_data = $conn->query("SELECT church_role FROM members WHERE id = $member_id")->fetch_assoc();
        $old_role = $member_data['church_role'] ?? '';
        
        $roles_arr = array_filter(array_map('trim', explode(',', str_replace('&', ',', $old_role))));
        $new_roles = [];
        foreach ($roles_arr as $r) {
            if (normalize_role_name($r) !== normalize_role_name($role_to_remove)) {
                $new_roles[] = $r;
            }
        }
        
        if (empty($new_roles)) {
            $conn->query("UPDATE members SET church_role = 'Member' WHERE id = $member_id");
        } else {
            $combined = $conn->real_escape_string(implode(', ', $new_roles));
            $conn->query("UPDATE members SET church_role = '$combined' WHERE id = $member_id");
        }
        
        $msg = $conn->real_escape_string("Your role '$role_to_remove' has been removed.");
        $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($member_id, 'member', '$msg')");
        
        header("Location: pastor_dashboard.php?tab=assign_roles&success=Role removed from member");
        exit();
    }
    
    // Manage Appointments
    elseif ($action === 'approve_appointment' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $conn->query("UPDATE appointments SET status = 'Approved' WHERE id = $id");
        
        $appt = $conn->query("SELECT member_id, appointment_date FROM appointments WHERE id = $id")->fetch_assoc();
        if ($appt) {
            $member_id = $appt['member_id'];
            $date_fmt = date('M j, Y g:i A', strtotime($appt['appointment_date']));
            $msg = $conn->real_escape_string("Your appointment request for $date_fmt has been Approved.");
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($member_id, 'member', '$msg')");
        }
        
        header("Location: pastor_dashboard.php?tab=appointments&success=Appointment approved successfully");
        exit();
    }
    elseif ($action === 'reject_appointment' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $conn->query("UPDATE appointments SET status = 'Declined' WHERE id = $id");
        
        $appt = $conn->query("SELECT member_id, appointment_date FROM appointments WHERE id = $id")->fetch_assoc();
        if ($appt) {
            $member_id = $appt['member_id'];
            $date_fmt = date('M j, Y g:i A', strtotime($appt['appointment_date']));
            $msg = $conn->real_escape_string("Your appointment request for $date_fmt has been Declined.");
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($member_id, 'member', '$msg')");
        }
        
        header("Location: pastor_dashboard.php?tab=appointments&success=Appointment rejected");
        exit();
    }
    // Handle subsidiary appointments
    elseif ($action === 'approve_subsidiary' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $member = $conn->query("SELECT * FROM members WHERE id = $id AND pending_role IS NOT NULL AND pending_role != ''")->fetch_assoc();
        if ($member) {
            $new_role = $conn->real_escape_string($member['pending_role']);
            $dept_sql = role_department_sql($conn, $new_role);
            $conn->query("UPDATE members SET church_role = '$new_role', pending_role = NULL$dept_sql WHERE id = $id");
            
            $msg = $conn->real_escape_string("Your leadership appointment to '$new_role' has been approved.");
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($id, 'member', '$msg')");
            header("Location: pastor_dashboard.php?tab=assign_roles&success=Appointment approved successfully");
            exit();
        }
        header("Location: pastor_dashboard.php?tab=assign_roles&error=Appointment not found");
        exit();
    }
    elseif ($action === 'reject_subsidiary' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $conn->query("UPDATE members SET pending_role = NULL WHERE id = $id");
        
        $msg = $conn->real_escape_string("Your leadership appointment was rejected.");
        $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($id, 'member', '$msg')");
        header("Location: pastor_dashboard.php?tab=assign_roles&success=Appointment rejected");
        exit();
    }
}

header("Location: pastor_dashboard.php");
exit();
?>