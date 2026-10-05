<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Find the closing of remove_role block and insert after it
    $needle = '$conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($member_id, \'member\', \'$msg\')")';
    if (strpos($content, $needle) === false) {
        echo "Needle not found in $file\n";
        continue;
    }
    
    $dasboard = str_contains($file, 'admin') ? 'admin_dashboard.php' : 'pastor_dashboard.php';
    
    $new_handlers = '

    // === UNASSIGN ALL ROLES ===
    elseif ($action === \'unassign_all\' && isset($_GET[\'id\'])) {
        $member_id = (int)$_GET[\'id\'];
        $member_data = $conn->query("SELECT first_name, last_name, church_role, department FROM members WHERE id = $member_id")->fetch_assoc();
        $old_role = $member_data[\'church_role\'] ?? \'\';
        
        // Save backup in session for redo
        $_SESSION[\'role_undo_backup\'][$member_id] = [
            \'role\'       => $old_role,
            \'department\' => $member_data[\'department\'] ?? \'\',
        ];
        
        $conn->query("UPDATE members SET church_role = \'Member\' WHERE id = $member_id");
        $msg = $conn->real_escape_string("All your roles ({$old_role}) have been removed by Admin.");
        $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($member_id, \'member\', \'$msg\')");
        header("Location: ' . $dasboard . '?tab=assign_roles&success=" . urlencode("All roles removed for " . ($member_data[\'first_name\'] ?? \'member\')) . "#assignRoleSection");
        exit();
    }
    
    // === REDO (RESTORE) ROLES ===
    elseif ($action === \'redo_roles\' && isset($_GET[\'id\'])) {
        $member_id = (int)$_GET[\'id\'];
        $backup    = $_SESSION[\'role_undo_backup\'][$member_id] ?? null;
        
        if ($backup && !empty($backup[\'role\'])) {
            $restored_role = $conn->real_escape_string($backup[\'role\']);
            $restored_dept = $conn->real_escape_string($backup[\'department\']);
            $conn->query("UPDATE members SET church_role = \'$restored_role\', department = \'$restored_dept\' WHERE id = $member_id");
            unset($_SESSION[\'role_undo_backup\'][$member_id]);
            $msg = $conn->real_escape_string("Your roles ({$backup[\'role\']}) have been restored by Admin.");
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($member_id, \'member\', \'$msg\')");
            header("Location: ' . $dasboard . '?tab=assign_roles&success=" . urlencode("Roles restored successfully") . "#assignRoleSection");
        } else {
            header("Location: ' . $dasboard . '?tab=assign_roles&error=" . urlencode("No backup found to restore") . "#assignRoleSection");
        }
        exit();
    }';
    
    // Find the end of remove_role elseif block (after exit(); just before next elseif)
    // We insert after the first occurrence of "exit();" following remove_role
    $pos = strpos($content, "elseif (\$action === 'remove_role'");
    if ($pos !== false) {
        $exit_pos = strpos($content, 'exit();', $pos);
        if ($exit_pos !== false) {
            $close_pos = strpos($content, '}', $exit_pos);
            if ($close_pos !== false) {
                $content = substr($content, 0, $close_pos + 1) . $new_handlers . substr($content, $close_pos + 1);
                file_put_contents($file, $content);
                echo "Added handlers to $file\n";
            }
        }
    }
}
?>
