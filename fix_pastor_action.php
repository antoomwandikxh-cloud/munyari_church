<?php
$file = 'pastor_action.php';
$content = file_get_contents($file);
$old = "    case 'unassign_all':
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

    case 'redo_roles':
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
        exit();";

$new = "    elseif (\$action === 'unassign_all') {
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

// Use regex to handle any whitespace differences
$content = str_replace($old, $new, $content);
file_put_contents($file, $content);
echo "Done. Matches: " . (strpos($content, "elseif (\$action === 'unassign_all')") !== false ? "YES" : "NO") . "\n";
?>
