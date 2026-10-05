<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);

    // Revert Admin buttons
    $old_admin1 = 'echo "<td><div style=\'display:flex;gap:6px;flex-wrap:wrap;align-items:center;\'>";
                                        echo "<a href=\'admin_dashboard.php?tab=assign_roles&action=unassign_all&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Delete ALL roles from " . htmlspecialchars($member_data[\'first_name\']) . "? They will become a plain Member. This can be undone with the Redo button.\');\" class=\'btn-sm\' style=\'background:#ef4444;color:white;text-decoration:none;border:none;cursor:pointer;font-weight:700;\'>🗑 Delete All</a>";
                                        if (!empty($_SESSION[\'role_undo_backup\'][$member_data[\'id\']])) {
                                            echo "<a href=\'admin_dashboard.php?tab=assign_roles&action=redo_roles&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Restore previous roles for " . htmlspecialchars($member_data[\'first_name\']) . "?\');\" class=\'btn-sm\' style=\'background:#10b981;color:white;text-decoration:none;border:none;cursor:pointer;font-weight:700;\'>↩ Redo</a>";
                                        }
                                        echo "</div></td>";';
    $new_admin1 = 'echo "<td><a href=\'admin_dashboard.php?tab=assign_roles&action=remove_role&id=" . $member_data[\'id\'] . "&role=" . urlencode($expected_role) . "\' onclick=\"return confirm(\'Remove this role from " . htmlspecialchars($member_data[\'first_name\']) . "?\');\" class=\'btn-sm\' style=\'background: var(--danger); color: white; text-decoration:none; border:none; cursor:pointer;\'>Remove Role</a></td>";';

    $old_admin2 = 'echo "<td><div style=\'display:flex;gap:6px;flex-wrap:wrap;align-items:center;\'>";
                            echo "<a href=\'admin_dashboard.php?tab=assign_roles&action=unassign_all&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Delete ALL roles from " . htmlspecialchars($member_data[\'first_name\']) . "? They will become a plain Member. This can be undone with the Redo button.\');\" class=\'btn-sm\' style=\'background:#ef4444;color:white;text-decoration:none;border:none;cursor:pointer;font-weight:700;\'>🗑 Delete All</a>";
                            if (!empty($_SESSION[\'role_undo_backup\'][$member_data[\'id\']])) {
                                echo "<a href=\'admin_dashboard.php?tab=assign_roles&action=redo_roles&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Restore previous roles for " . htmlspecialchars($member_data[\'first_name\']) . "?\');\" class=\'btn-sm\' style=\'background:#10b981;color:white;text-decoration:none;border:none;cursor:pointer;font-weight:700;\'>↩ Redo</a>";
                            }
                            echo "</div></td>";';
    $new_admin2 = 'echo "<td><a href=\'admin_dashboard.php?tab=assign_roles&action=remove_role&id=" . $member_data[\'id\'] . "&role=" . urlencode($disp_role) . "\' onclick=\"return confirm(\'Remove this role from " . htmlspecialchars($member_data[\'first_name\']) . "?\');\" class=\'btn-sm\' style=\'background: var(--danger); color: white; text-decoration:none; border:none; cursor:pointer;\'>Remove Role</a></td>";';


    // Revert Pastor buttons
    $old_pastor1 = 'echo "<td style=\'padding:12px;\'><div style=\'display:flex;gap:6px;flex-wrap:wrap;align-items:center;\'>";
                                            echo "<a href=\'pastor_action.php?action=unassign_all&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Delete ALL roles from " . htmlspecialchars($member_data[\'first_name\']) . "? They will become a plain Member. This can be undone with the Redo button.\');\" class=\'btn-sm\' style=\'background:#ef4444;color:white;text-decoration:none;border:none;cursor:pointer;font-weight:700;\'>🗑 Delete All</a>";
                                            if (!empty($_SESSION[\'role_undo_backup\'][$member_data[\'id\']])) {
                                                echo "<a href=\'pastor_action.php?action=redo_roles&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Restore previous roles for " . htmlspecialchars($member_data[\'first_name\']) . "?\');\" class=\'btn-sm\' style=\'background:#10b981;color:white;text-decoration:none;border:none;cursor:pointer;font-weight:700;\'>↩ Redo</a>";
                                            }
                                            echo "</div></td>";';
    $new_pastor1 = 'echo "<td style=\'padding: 12px;\'><a href=\'pastor_action.php?action=remove_role&id=" . $member_data[\'id\'] . "&role=" . urlencode($expected_role) . "\' onclick=\"return confirm(\'Remove this role from " . htmlspecialchars($member_data[\'first_name\']) . "?\');\" class=\'btn-sm\' style=\'background: var(--danger); color: white; text-decoration:none; border:none; cursor:pointer;\'>Remove Role</a></td>";';

    $old_pastor2 = 'echo "<td style=\'padding:12px;\'><div style=\'display:flex;gap:6px;flex-wrap:wrap;align-items:center;\'>";
                                echo "<a href=\'pastor_action.php?action=unassign_all&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Delete ALL roles from " . htmlspecialchars($member_data[\'first_name\']) . "? They will become a plain Member. This can be undone with the Redo button.\');\" class=\'btn-sm\' style=\'background:#ef4444;color:white;text-decoration:none;border:none;cursor:pointer;font-weight:700;\'>🗑 Delete All</a>";
                                if (!empty($_SESSION[\'role_undo_backup\'][$member_data[\'id\']])) {
                                    echo "<a href=\'pastor_action.php?action=redo_roles&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Restore previous roles for " . htmlspecialchars($member_data[\'first_name\']) . "?\');\" class=\'btn-sm\' style=\'background:#10b981;color:white;text-decoration:none;border:none;cursor:pointer;font-weight:700;\'>↩ Redo</a>";
                                }
                                echo "</div></td>";';
    $new_pastor2 = 'echo "<td style=\'padding: 12px;\'><a href=\'pastor_action.php?action=remove_role&id=" . $member_data[\'id\'] . "&role=" . urlencode($disp_role) . "\' onclick=\"return confirm(\'Remove this role from " . htmlspecialchars($member_data[\'first_name\']) . "?\');\" class=\'btn-sm\' style=\'background: var(--danger); color: white; text-decoration:none; border:none; cursor:pointer;\'>Remove Role</a></td>";';

    $c1 = substr_count($content, $old_admin1); $content = str_replace($old_admin1, $new_admin1, $content);
    $c2 = substr_count($content, $old_admin2); $content = str_replace($old_admin2, $new_admin2, $content);
    $c3 = substr_count($content, $old_pastor1); $content = str_replace($old_pastor1, $new_pastor1, $content);
    $c4 = substr_count($content, $old_pastor2); $content = str_replace($old_pastor2, $new_pastor2, $content);

    file_put_contents($file, $content);
    echo "Reverted buttons in $file: a1=$c1, a2=$c2, p1=$c3, p2=$c4\n";
}
?>
