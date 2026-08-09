<?php
// Append to pastor_action.php.recovered
$action = file_get_contents('pastor_action.php.recovered');
$action .= <<<EOF
    elseif (\$action === 'remove_role' && isset(\$_GET['id']) && isset(\$_GET['role'])) {
        \$member_id = (int)\$_GET['id'];
        \$role_to_remove = urldecode(\$_GET['role']);
        \$member_data = \$conn->query("SELECT church_role FROM members WHERE id = \$member_id")->fetch_assoc();
        \$old_role = \$member_data['church_role'] ?? '';
        
        \$roles_arr = array_filter(array_map('trim', explode(',', str_replace('&', ',', \$old_role))));
        \$new_roles = [];
        foreach (\$roles_arr as \$r) {
            if (normalize_role_name(\$r) !== normalize_role_name(\$role_to_remove)) {
                \$new_roles[] = \$r;
            }
        }
        
        if (empty(\$new_roles)) {
            \$conn->query("UPDATE members SET church_role = 'Member' WHERE id = \$member_id");
        } else {
            \$combined = \$conn->real_escape_string(implode(', ', \$new_roles));
            \$conn->query("UPDATE members SET church_role = '\$combined' WHERE id = \$member_id");
        }
        
        \$msg = \$conn->real_escape_string("Your role '\$role_to_remove' has been removed.");
        \$conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES (\$member_id, 'member', '\$msg')");
        
        header("Location: pastor_dashboard.php?tab=assign_roles&success=Role removed from member");
        exit();
    }
    
    // Manage Appointments
    elseif (\$action === 'approve_appointment' && isset(\$_GET['id'])) {
        \$id = (int)\$_GET['id'];
        \$conn->query("UPDATE appointments SET status = 'Approved' WHERE id = \$id");
        
        \$appt = \$conn->query("SELECT member_id, appointment_date FROM appointments WHERE id = \$id")->fetch_assoc();
        if (\$appt) {
            \$member_id = \$appt['member_id'];
            \$date_fmt = date('M j, Y g:i A', strtotime(\$appt['appointment_date']));
            \$msg = \$conn->real_escape_string("Your appointment request for \$date_fmt has been Approved.");
            \$conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES (\$member_id, 'member', '\$msg')");
        }
        
        header("Location: pastor_dashboard.php?tab=appointments&success=Appointment approved successfully");
        exit();
    }
    elseif (\$action === 'reject_appointment' && isset(\$_GET['id'])) {
        \$id = (int)\$_GET['id'];
        \$conn->query("UPDATE appointments SET status = 'Declined' WHERE id = \$id");
        
        \$appt = \$conn->query("SELECT member_id, appointment_date FROM appointments WHERE id = \$id")->fetch_assoc();
        if (\$appt) {
            \$member_id = \$appt['member_id'];
            \$date_fmt = date('M j, Y g:i A', strtotime(\$appt['appointment_date']));
            \$msg = \$conn->real_escape_string("Your appointment request for \$date_fmt has been Declined.");
            \$conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES (\$member_id, 'member', '\$msg')");
        }
        
        header("Location: pastor_dashboard.php?tab=appointments&success=Appointment rejected");
        exit();
    }
    // Handle subsidiary appointments
    elseif (\$action === 'approve_subsidiary' && isset(\$_GET['id'])) {
        \$id = (int)\$_GET['id'];
        \$member = \$conn->query("SELECT * FROM members WHERE id = \$id AND pending_role IS NOT NULL AND pending_role != ''")->fetch_assoc();
        if (\$member) {
            \$new_role = \$conn->real_escape_string(\$member['pending_role']);
            \$dept_sql = role_department_sql(\$conn, \$new_role);
            \$conn->query("UPDATE members SET church_role = '\$new_role', pending_role = NULL\$dept_sql WHERE id = \$id");
            
            \$msg = \$conn->real_escape_string("Your leadership appointment to '\$new_role' has been approved.");
            \$conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES (\$id, 'member', '\$msg')");
            header("Location: pastor_dashboard.php?tab=assign_roles&success=Appointment approved successfully");
            exit();
        }
        header("Location: pastor_dashboard.php?tab=assign_roles&error=Appointment not found");
        exit();
    }
    elseif (\$action === 'reject_subsidiary' && isset(\$_GET['id'])) {
        \$id = (int)\$_GET['id'];
        \$conn->query("UPDATE members SET pending_role = NULL WHERE id = \$id");
        
        \$msg = \$conn->real_escape_string("Your leadership appointment was rejected.");
        \$conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES (\$id, 'member', '\$msg')");
        header("Location: pastor_dashboard.php?tab=assign_roles&success=Appointment rejected");
        exit();
    }
}

header("Location: pastor_dashboard.php");
exit();
?>
EOF;
file_put_contents('pastor_action.php', $action);
echo "pastor_action.php restored!\n";
?>
