<?php
$file = 'admin_dashboard.php';
$content = file_get_contents($file);

// Replace the per-member unassign_all and redo_roles with global ones
$old_handlers = "
    // === UNASSIGN ALL ROLES ===
    elseif (\$action === 'unassign_all' && isset(\$_GET['id'])) {
        \$member_id = (int)\$_GET['id'];
        \$member_data = \$conn->query(\"SELECT first_name, last_name, church_role, department FROM members WHERE id = \$member_id\")->fetch_assoc();
        \$old_role = \$member_data['church_role'] ?? '';
        
        // Save backup in session for redo
        \$_SESSION['role_undo_backup'][\$member_id] = [
            'role'       => \$old_role,
            'department' => \$member_data['department'] ?? '',
        ];
        
        \$conn->query(\"UPDATE members SET church_role = 'Member' WHERE id = \$member_id\");
        \$msg = \$conn->real_escape_string(\"All your roles ({\$old_role}) have been removed by Admin.\");
        \$conn->query(\"INSERT INTO notifications (user_id, user_type, message) VALUES (\$member_id, 'member', '\$msg')\");
        header(\"Location: admin_dashboard.php?tab=assign_roles&success=\" . urlencode(\"All roles removed for \" . (\$member_data['first_name'] ?? 'member')) . \"#assignRoleSection\");
        exit();
    }
    
    // === REDO (RESTORE) ROLES ===
    elseif (\$action === 'redo_roles' && isset(\$_GET['id'])) {
        \$member_id = (int)\$_GET['id'];
        \$backup    = \$_SESSION['role_undo_backup'][\$member_id] ?? null;
        
        if (\$backup && !empty(\$backup['role'])) {
            \$restored_role = \$conn->real_escape_string(\$backup['role']);
            \$restored_dept = \$conn->real_escape_string(\$backup['department']);
            \$conn->query(\"UPDATE members SET church_role = '\$restored_role', department = '\$restored_dept' WHERE id = \$member_id\");
            unset(\$_SESSION['role_undo_backup'][\$member_id]);
            \$msg = \$conn->real_escape_string(\"Your roles ({\$backup['role']}) have been restored by Admin.\");
            \$conn->query(\"INSERT INTO notifications (user_id, user_type, message) VALUES (\$member_id, 'member', '\$msg')\");
            header(\"Location: admin_dashboard.php?tab=assign_roles&success=\" . urlencode(\"Roles restored successfully\") . \"#assignRoleSection\");
        } else {
            header(\"Location: admin_dashboard.php?tab=assign_roles&error=\" . urlencode(\"No backup found to restore\") . \"#assignRoleSection\");
        }
        exit();
    }";

$new_handlers = "
    // === UNASSIGN ALL ROLES GLOBALLY ===
    elseif (\$action === 'unassign_all_global') {
        \$members = \$conn->query(\"SELECT id, first_name, last_name, church_role, department FROM members WHERE church_role != 'Member' AND church_role IS NOT NULL AND church_role != ''\");
        \$_SESSION['global_role_undo_backup'] = [];
        if (\$members && \$members->num_rows > 0) {
            while (\$md = \$members->fetch_assoc()) {
                \$_SESSION['global_role_undo_backup'][\$md['id']] = [
                    'role'       => \$md['church_role'],
                    'department' => \$md['department']
                ];
                \$mid = (int)\$md['id'];
                \$conn->query(\"UPDATE members SET church_role = 'Member' WHERE id = \$mid\");
                \$msg = \$conn->real_escape_string(\"All your roles have been removed by Admin in a global reset.\");
                \$conn->query(\"INSERT INTO notifications (user_id, user_type, message) VALUES (\$mid, 'member', '\$msg')\");
            }
        }
        header(\"Location: admin_dashboard.php?tab=assign_roles&success=\" . urlencode(\"All assigned roles have been globally removed.\") . \"#assignRoleSection\");
        exit();
    }
    
    // === REDO (RESTORE) ALL ROLES GLOBALLY ===
    elseif (\$action === 'redo_roles_global') {
        \$backup = \$_SESSION['global_role_undo_backup'] ?? [];
        if (!empty(\$backup)) {
            foreach (\$backup as \$mid => \$data) {
                \$mid = (int)\$mid;
                \$restored_role = \$conn->real_escape_string(\$data['role']);
                \$restored_dept = \$conn->real_escape_string(\$data['department']);
                \$conn->query(\"UPDATE members SET church_role = '\$restored_role', department = '\$restored_dept' WHERE id = \$mid\");
                \$msg = \$conn->real_escape_string(\"Your roles have been restored by Admin following a global reset.\");
                \$conn->query(\"INSERT INTO notifications (user_id, user_type, message) VALUES (\$mid, 'member', '\$msg')\");
            }
            unset(\$_SESSION['global_role_undo_backup']);
            header(\"Location: admin_dashboard.php?tab=assign_roles&success=\" . urlencode(\"All previously deleted roles have been restored.\") . \"#assignRoleSection\");
        } else {
            header(\"Location: admin_dashboard.php?tab=assign_roles&error=\" . urlencode(\"No backup found to restore\") . \"#assignRoleSection\");
        }
        exit();
    }";

$content = str_replace($old_handlers, $new_handlers, $content);
file_put_contents($file, $content);
echo "Replaced handlers in admin_dashboard.php\n";

?>
