<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $is_pastor = str_contains($file, 'pastor');
    $base = $is_pastor ? 'pastor_action.php' : 'admin_dashboard.php?tab=assign_roles&';
    
    // ========================================================
    // 1. Add the Unassign All + Redo buttons alongside the existing Remove Role button (admin)
    // ========================================================
    $old_admin_btn = "echo \"<td><a href='admin_dashboard.php?tab=assign_roles&action=remove_role&id=\" . \$member_data['id'] . \"&role=\" . urlencode(\$expected_role) . \"' onclick=\\\"return confirm('Remove this role from \" . htmlspecialchars(\$member_data['first_name']) . \"?');\\\" class='btn-sm' style='background: var(--danger); color: white; text-decoration:none; border:none; cursor:pointer;'>Remove Role</a></td>\";";

    $new_admin_btn = "echo \"<td><div style='display:flex;gap:6px;flex-wrap:wrap;align-items:center;'>\";
                                        echo \"<a href='admin_dashboard.php?tab=assign_roles&action=remove_role&id=\" . \$member_data['id'] . \"&role=\" . urlencode(\$expected_role) . \"' onclick=\\\"return confirm('Remove this role from \" . htmlspecialchars(\$member_data['first_name']) . \"?');\\\" class='btn-sm' style='background:var(--danger);color:white;text-decoration:none;border:none;cursor:pointer;'>Remove Role</a>\";
                                        echo \"<a href='admin_dashboard.php?tab=assign_roles&action=unassign_all&id=\" . \$member_data['id'] . \"' onclick=\\\"return confirm('Unassign ALL roles from \" . htmlspecialchars(\$member_data['first_name']) . \"? This will reset them to plain Member.');\\\" class='btn-sm' style='background:#f59e0b;color:white;text-decoration:none;border:none;cursor:pointer;'>⚠ Unassign All</a>\";
                                        if (!empty(\$_SESSION['role_undo_backup'][\$member_data['id']])) {
                                            echo \"<a href='admin_dashboard.php?tab=assign_roles&action=redo_roles&id=\" . \$member_data['id'] . \"' onclick=\\\"return confirm('Restore previous roles for \" . htmlspecialchars(\$member_data['first_name']) . \"?');\\\" class='btn-sm' style='background:#10b981;color:white;text-decoration:none;border:none;cursor:pointer;'>↩ Redo</a>\";
                                        }
                                        echo \"</div></td>\";";

    $count = substr_count($content, $old_admin_btn);
    if ($count > 0) {
        $content = str_replace($old_admin_btn, $new_admin_btn, $content);
        echo "Updated primary Remove Role btn in $file ($count matches)\n";
    }

    $old_admin_btn2 = "echo \"<td><a href='admin_dashboard.php?tab=assign_roles&action=remove_role&id=\" . \$member_data['id'] . \"&role=\" . urlencode(\$disp_role) . \"' onclick=\\\"return confirm('Remove this role from \" . htmlspecialchars(\$member_data['first_name']) . \"?');\\\" class='btn-sm' style='background: var(--danger); color: white; text-decoration:none; border:none; cursor:pointer;'>Remove Role</a></td>\";";

    $new_admin_btn2 = "echo \"<td><div style='display:flex;gap:6px;flex-wrap:wrap;align-items:center;'>\";
                            echo \"<a href='admin_dashboard.php?tab=assign_roles&action=remove_role&id=\" . \$member_data['id'] . \"&role=\" . urlencode(\$disp_role) . \"' onclick=\\\"return confirm('Remove this role from \" . htmlspecialchars(\$member_data['first_name']) . \"?');\\\" class='btn-sm' style='background:var(--danger);color:white;text-decoration:none;border:none;cursor:pointer;'>Remove Role</a>\";
                            echo \"<a href='admin_dashboard.php?tab=assign_roles&action=unassign_all&id=\" . \$member_data['id'] . \"' onclick=\\\"return confirm('Unassign ALL roles from \" . htmlspecialchars(\$member_data['first_name']) . \"? This will reset them to plain Member.');\\\" class='btn-sm' style='background:#f59e0b;color:white;text-decoration:none;border:none;cursor:pointer;'>⚠ Unassign All</a>\";
                            if (!empty(\$_SESSION['role_undo_backup'][\$member_data['id']])) {
                                echo \"<a href='admin_dashboard.php?tab=assign_roles&action=redo_roles&id=\" . \$member_data['id'] . \"' onclick=\\\"return confirm('Restore previous roles for \" . htmlspecialchars(\$member_data['first_name']) . \"?');\\\" class='btn-sm' style='background:#10b981;color:white;text-decoration:none;border:none;cursor:pointer;'>↩ Redo</a>\";
                            }
                            echo \"</div></td>\";";

    $count2 = substr_count($content, $old_admin_btn2);
    if ($count2 > 0) {
        $content = str_replace($old_admin_btn2, $new_admin_btn2, $content);
        echo "Updated secondary Remove Role btn in $file ($count2 matches)\n";
    }

    // For pastor dashboard – uses pastor_action.php
    $old_pastor_btn = "echo \"<td style='padding: 12px;'><a href='pastor_action.php?action=remove_role&id=\" . \$member_data['id'] . \"&role=\" . urlencode(\$expected_role) . \"' onclick=\\\"return confirm('Remove this role from \" . htmlspecialchars(\$member_data['first_name']) . \"?');\\\" class='btn-sm' style='background: var(--danger); color: white; text-decoration:none; border:none; cursor:pointer;'>Remove Role</a></td>\";";

    $new_pastor_btn = "echo \"<td style='padding:12px;'><div style='display:flex;gap:6px;flex-wrap:wrap;align-items:center;'>\";
                                            echo \"<a href='pastor_action.php?action=remove_role&id=\" . \$member_data['id'] . \"&role=\" . urlencode(\$expected_role) . \"' onclick=\\\"return confirm('Remove this role from \" . htmlspecialchars(\$member_data['first_name']) . \"?');\\\" class='btn-sm' style='background:var(--danger);color:white;text-decoration:none;border:none;cursor:pointer;'>Remove Role</a>\";
                                            echo \"<a href='pastor_action.php?action=unassign_all&id=\" . \$member_data['id'] . \"' onclick=\\\"return confirm('Unassign ALL roles from \" . htmlspecialchars(\$member_data['first_name']) . \"? This will reset them to plain Member.');\\\" class='btn-sm' style='background:#f59e0b;color:white;text-decoration:none;border:none;cursor:pointer;'>⚠ Unassign All</a>\";
                                            if (!empty(\$_SESSION['role_undo_backup'][\$member_data['id']])) {
                                                echo \"<a href='pastor_action.php?action=redo_roles&id=\" . \$member_data['id'] . \"' onclick=\\\"return confirm('Restore previous roles for \" . htmlspecialchars(\$member_data['first_name']) . \"?');\\\" class='btn-sm' style='background:#10b981;color:white;text-decoration:none;border:none;cursor:pointer;'>↩ Redo</a>\";
                                            }
                                            echo \"</div></td>\";";

    $count3 = substr_count($content, $old_pastor_btn);
    if ($count3 > 0) {
        $content = str_replace($old_pastor_btn, $new_pastor_btn, $content);
        echo "Updated pastor primary btn in $file ($count3 matches)\n";
    }

    $old_pastor_btn2 = "echo \"<td style='padding: 12px;'><a href='pastor_action.php?action=remove_role&id=\" . \$member_data['id'] . \"&role=\" . urlencode(\$disp_role) . \"' onclick=\\\"return confirm('Remove this role from \" . htmlspecialchars(\$member_data['first_name']) . \"?');\\\" class='btn-sm' style='background: var(--danger); color: white; text-decoration:none; border:none; cursor:pointer;'>Remove Role</a></td>\";";

    $new_pastor_btn2 = "echo \"<td style='padding:12px;'><div style='display:flex;gap:6px;flex-wrap:wrap;align-items:center;'>\";
                                echo \"<a href='pastor_action.php?action=remove_role&id=\" . \$member_data['id'] . \"&role=\" . urlencode(\$disp_role) . \"' onclick=\\\"return confirm('Remove this role from \" . htmlspecialchars(\$member_data['first_name']) . \"?');\\\" class='btn-sm' style='background:var(--danger);color:white;text-decoration:none;border:none;cursor:pointer;'>Remove Role</a>\";
                                echo \"<a href='pastor_action.php?action=unassign_all&id=\" . \$member_data['id'] . \"' onclick=\\\"return confirm('Unassign ALL roles from \" . htmlspecialchars(\$member_data['first_name']) . \"? This will reset them to plain Member.');\\\" class='btn-sm' style='background:#f59e0b;color:white;text-decoration:none;border:none;cursor:pointer;'>⚠ Unassign All</a>\";
                                if (!empty(\$_SESSION['role_undo_backup'][\$member_data['id']])) {
                                    echo \"<a href='pastor_action.php?action=redo_roles&id=\" . \$member_data['id'] . \"' onclick=\\\"return confirm('Restore previous roles for \" . htmlspecialchars(\$member_data['first_name']) . \"?');\\\" class='btn-sm' style='background:#10b981;color:white;text-decoration:none;border:none;cursor:pointer;'>↩ Redo</a>\";
                                }
                                echo \"</div></td>\";";

    $count4 = substr_count($content, $old_pastor_btn2);
    if ($count4 > 0) {
        $content = str_replace($old_pastor_btn2, $new_pastor_btn2, $content);
        echo "Updated pastor secondary btn in $file ($count4 matches)\n";
    }

    file_put_contents($file, $content);
}

// Now add the same unassign_all and redo handlers to pastor_action.php
$pastor_action = 'pastor_action.php';
if (file_exists($pastor_action)) {
    $pact = file_get_contents($pastor_action);
    // Check if already added
    if (strpos($pact, 'unassign_all') === false) {
        // Find remove_role handler end
        $pos = strpos($pact, "'remove_role'");
        if ($pos !== false) {
            $exit_pos = strpos($pact, 'exit();', $pos);
            $close_pos = strpos($pact, '}', $exit_pos);
            if ($close_pos !== false) {
                $insert = '

    case \'unassign_all\':
        $member_id = (int)($_GET[\'id\'] ?? 0);
        if ($member_id > 0) {
            $md = $conn->query("SELECT first_name, last_name, church_role, department FROM members WHERE id=$member_id")->fetch_assoc();
            $_SESSION[\'role_undo_backup\'][$member_id] = [\'role\' => $md[\'church_role\'] ?? \'\', \'department\' => $md[\'department\'] ?? \'\'];
            $conn->query("UPDATE members SET church_role = \'Member\' WHERE id=$member_id");
            $msg = $conn->real_escape_string("All your roles have been removed by Pastor.");
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($member_id, \'member\', \'$msg\')");
        }
        header("Location: pastor_dashboard.php?tab=assign_roles&success=" . urlencode("All roles removed") . "#assignRoleSection");
        exit();

    case \'redo_roles\':
        $member_id = (int)($_GET[\'id\'] ?? 0);
        $backup = $_SESSION[\'role_undo_backup\'][$member_id] ?? null;
        if ($backup && !empty($backup[\'role\'])) {
            $conn->query("UPDATE members SET church_role = \'" . $conn->real_escape_string($backup[\'role\']) . "\', department = \'" . $conn->real_escape_string($backup[\'department\']) . "\' WHERE id=$member_id");
            unset($_SESSION[\'role_undo_backup\'][$member_id]);
            $msg = $conn->real_escape_string("Your roles have been restored by Pastor.");
            $conn->query("INSERT INTO notifications (user_id, user_type, message) VALUES ($member_id, \'member\', \'$msg\')");
            header("Location: pastor_dashboard.php?tab=assign_roles&success=" . urlencode("Roles restored") . "#assignRoleSection");
        } else {
            header("Location: pastor_dashboard.php?tab=assign_roles&error=" . urlencode("No backup to restore") . "#assignRoleSection");
        }
        exit();';
                $pact = substr($pact, 0, $close_pos + 1) . $insert . substr($pact, $close_pos + 1);
                file_put_contents($pastor_action, $pact);
                echo "Added handlers to pastor_action.php\n";
            }
        } else {
            echo "Could not find remove_role in pastor_action.php\n";
        }
    } else {
        echo "Handlers already exist in pastor_action.php\n";
    }
}
?>
