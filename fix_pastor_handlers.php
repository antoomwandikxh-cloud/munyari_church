<?php
$file = 'pastor_action.php';
$content = file_get_contents($file);

$old_handlers = "
    elseif (\$action === 'unassign_all') {
        \$member_id = (int)(\$_GET['id'] ?? 0);
        if (\$member_id > 0) {
            \$md = \$conn->query(\"SELECT first_name, last_name, church_role, department FROM members WHERE id=\$member_id\")->fetch_assoc();
            \$_SESSION['role_undo_backup'][\$member_id] = ['role' => \$md['church_role'] ?? '', 'department' => \$md['department'] ?? ''];
            \$conn->query(\"UPDATE members SET church_role = 'Member' WHERE id=\$member_id\");
            \$msg = \$conn->real_escape_string(\"All your roles have been removed by Pastor.\");
            \$conn->query(\"INSERT INTO notifications (user_id, user_type, message) VALUES (\$member_id, 'member', '\$msg')\");
        }
        header(\"Location: pastor_dashboard.php?tab=assign_roles&success=\" . urlencode(\"All roles removed\") . \"#assignRoleSection\");
        exit();
    }

    elseif (\$action === 'redo_roles') {
        \$member_id = (int)(\$_GET['id'] ?? 0);
        \$backup = \$_SESSION['role_undo_backup'][\$member_id] ?? null;
        if (\$backup && !empty(\$backup['role'])) {
            \$conn->query(\"UPDATE members SET church_role = '\" . \$conn->real_escape_string(\$backup['role']) . \"', department = '\" . \$conn->real_escape_string(\$backup['department']) . \"' WHERE id=\$member_id\");
            unset(\$_SESSION['role_undo_backup'][\$member_id]);
            \$msg = \$conn->real_escape_string(\"Your roles have been restored by Pastor.\");
            \$conn->query(\"INSERT INTO notifications (user_id, user_type, message) VALUES (\$member_id, 'member', '\$msg')\");
            header(\"Location: pastor_dashboard.php?tab=assign_roles&success=\" . urlencode(\"Roles restored\") . \"#assignRoleSection\");
        } else {
            header(\"Location: pastor_dashboard.php?tab=assign_roles&error=\" . urlencode(\"No backup to restore\") . \"#assignRoleSection\");
        }
        exit();
    }";

$new_handlers = "
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
                \$msg = \$conn->real_escape_string(\"All your roles have been removed by Pastor in a global reset.\");
                \$conn->query(\"INSERT INTO notifications (user_id, user_type, message) VALUES (\$mid, 'member', '\$msg')\");
            }
        }
        header(\"Location: pastor_dashboard.php?tab=assign_roles&success=\" . urlencode(\"All assigned roles have been globally removed.\") . \"#assignRoleSection\");
        exit();
    }

    elseif (\$action === 'redo_roles_global') {
        \$backup = \$_SESSION['global_role_undo_backup'] ?? [];
        if (!empty(\$backup)) {
            foreach (\$backup as \$mid => \$data) {
                \$mid = (int)\$mid;
                \$restored_role = \$conn->real_escape_string(\$data['role']);
                \$restored_dept = \$conn->real_escape_string(\$data['department']);
                \$conn->query(\"UPDATE members SET church_role = '\$restored_role', department = '\$restored_dept' WHERE id = \$mid\");
                \$msg = \$conn->real_escape_string(\"Your roles have been restored by Pastor following a global reset.\");
                \$conn->query(\"INSERT INTO notifications (user_id, user_type, message) VALUES (\$mid, 'member', '\$msg')\");
            }
            unset(\$_SESSION['global_role_undo_backup']);
            header(\"Location: pastor_dashboard.php?tab=assign_roles&success=\" . urlencode(\"All previously deleted roles have been restored.\") . \"#assignRoleSection\");
        } else {
            header(\"Location: pastor_dashboard.php?tab=assign_roles&error=\" . urlencode(\"No backup found to restore\") . \"#assignRoleSection\");
        }
        exit();
    }";

$content = str_replace($old_handlers, $new_handlers, $content);
file_put_contents($file, $content);
echo "Replaced handlers in pastor_action.php\n";

?>
