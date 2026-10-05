<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $is_pastor = str_contains($file, 'pastor');
    $base_admin = 'admin_dashboard.php?tab=assign_roles&';
    $base_pastor = 'pastor_action.php?';

    // ── ADMIN: primary row (expected_role) ──
    $old1 = 'echo "<td><div style=\'display:flex;gap:6px;flex-wrap:wrap;align-items:center;\'>";
                                        echo "<a href=\'admin_dashboard.php?tab=assign_roles&action=remove_role&id=" . $member_data[\'id\'] . "&role=" . urlencode($expected_role) . "\' onclick=\"return confirm(\'Remove this role from " . htmlspecialchars($member_data[\'first_name\']) . "?\');\" class=\'btn-sm\' style=\'background:var(--danger);color:white;text-decoration:none;border:none;cursor:pointer;\'>Remove Role</a>";
                                        echo "<a href=\'admin_dashboard.php?tab=assign_roles&action=unassign_all&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Unassign ALL roles from " . htmlspecialchars($member_data[\'first_name\']) . "? This will reset them to plain Member.\');\" class=\'btn-sm\' style=\'background:#f59e0b;color:white;text-decoration:none;border:none;cursor:pointer;\'>⚠ Unassign All</a>";
                                        if (!empty($_SESSION[\'role_undo_backup\'][$member_data[\'id\']])) {
                                            echo "<a href=\'admin_dashboard.php?tab=assign_roles&action=redo_roles&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Restore previous roles for " . htmlspecialchars($member_data[\'first_name\']) . "?\');\" class=\'btn-sm\' style=\'background:#10b981;color:white;text-decoration:none;border:none;cursor:pointer;\'>↩ Redo</a>";
                                        }
                                        echo "</div></td>";';

    $new1 = 'echo "<td><div style=\'display:flex;gap:6px;flex-wrap:wrap;align-items:center;\'>";
                                        echo "<a href=\'admin_dashboard.php?tab=assign_roles&action=unassign_all&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Delete ALL roles from " . htmlspecialchars($member_data[\'first_name\']) . "? They will become a plain Member. This can be undone with the Redo button.\');\" class=\'btn-sm\' style=\'background:#ef4444;color:white;text-decoration:none;border:none;cursor:pointer;font-weight:700;\'>🗑 Delete All</a>";
                                        if (!empty($_SESSION[\'role_undo_backup\'][$member_data[\'id\']])) {
                                            echo "<a href=\'admin_dashboard.php?tab=assign_roles&action=redo_roles&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Restore previous roles for " . htmlspecialchars($member_data[\'first_name\']) . "?\');\" class=\'btn-sm\' style=\'background:#10b981;color:white;text-decoration:none;border:none;cursor:pointer;font-weight:700;\'>↩ Redo</a>";
                                        }
                                        echo "</div></td>";';

    // ── ADMIN: secondary row (disp_role) ──
    $old2 = 'echo "<td><div style=\'display:flex;gap:6px;flex-wrap:wrap;align-items:center;\'>";
                            echo "<a href=\'admin_dashboard.php?tab=assign_roles&action=remove_role&id=" . $member_data[\'id\'] . "&role=" . urlencode($disp_role) . "\' onclick=\"return confirm(\'Remove this role from " . htmlspecialchars($member_data[\'first_name\']) . "?\');\" class=\'btn-sm\' style=\'background:var(--danger);color:white;text-decoration:none;border:none;cursor:pointer;\'>Remove Role</a>";
                            echo "<a href=\'admin_dashboard.php?tab=assign_roles&action=unassign_all&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Unassign ALL roles from " . htmlspecialchars($member_data[\'first_name\']) . "? This will reset them to plain Member.\');\" class=\'btn-sm\' style=\'background:#f59e0b;color:white;text-decoration:none;border:none;cursor:pointer;\'>⚠ Unassign All</a>";
                            if (!empty($_SESSION[\'role_undo_backup\'][$member_data[\'id\']])) {
                                echo "<a href=\'admin_dashboard.php?tab=assign_roles&action=redo_roles&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Restore previous roles for " . htmlspecialchars($member_data[\'first_name\']) . "?\');\" class=\'btn-sm\' style=\'background:#10b981;color:white;text-decoration:none;border:none;cursor:pointer;\'>↩ Redo</a>";
                            }
                            echo "</div></td>";';

    $new2 = 'echo "<td><div style=\'display:flex;gap:6px;flex-wrap:wrap;align-items:center;\'>";
                            echo "<a href=\'admin_dashboard.php?tab=assign_roles&action=unassign_all&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Delete ALL roles from " . htmlspecialchars($member_data[\'first_name\']) . "? They will become a plain Member. This can be undone with the Redo button.\');\" class=\'btn-sm\' style=\'background:#ef4444;color:white;text-decoration:none;border:none;cursor:pointer;font-weight:700;\'>🗑 Delete All</a>";
                            if (!empty($_SESSION[\'role_undo_backup\'][$member_data[\'id\']])) {
                                echo "<a href=\'admin_dashboard.php?tab=assign_roles&action=redo_roles&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Restore previous roles for " . htmlspecialchars($member_data[\'first_name\']) . "?\');\" class=\'btn-sm\' style=\'background:#10b981;color:white;text-decoration:none;border:none;cursor:pointer;font-weight:700;\'>↩ Redo</a>";
                            }
                            echo "</div></td>";';

    // ── PASTOR: primary row (expected_role) ──
    $old3 = 'echo "<td style=\'padding:12px;\'><div style=\'display:flex;gap:6px;flex-wrap:wrap;align-items:center;\'>";
                                            echo "<a href=\'pastor_action.php?action=remove_role&id=" . $member_data[\'id\'] . "&role=" . urlencode($expected_role) . "\' onclick=\"return confirm(\'Remove this role from " . htmlspecialchars($member_data[\'first_name\']) . "?\');\" class=\'btn-sm\' style=\'background:var(--danger);color:white;text-decoration:none;border:none;cursor:pointer;\'>Remove Role</a>";
                                            echo "<a href=\'pastor_action.php?action=unassign_all&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Unassign ALL roles from " . htmlspecialchars($member_data[\'first_name\']) . "? This will reset them to plain Member.\');\" class=\'btn-sm\' style=\'background:#f59e0b;color:white;text-decoration:none;border:none;cursor:pointer;\'>⚠ Unassign All</a>";
                                            if (!empty($_SESSION[\'role_undo_backup\'][$member_data[\'id\']])) {
                                                echo "<a href=\'pastor_action.php?action=redo_roles&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Restore previous roles for " . htmlspecialchars($member_data[\'first_name\']) . "?\');\" class=\'btn-sm\' style=\'background:#10b981;color:white;text-decoration:none;border:none;cursor:pointer;\'>↩ Redo</a>";
                                            }
                                            echo "</div></td>";';

    $new3 = 'echo "<td style=\'padding:12px;\'><div style=\'display:flex;gap:6px;flex-wrap:wrap;align-items:center;\'>";
                                            echo "<a href=\'pastor_action.php?action=unassign_all&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Delete ALL roles from " . htmlspecialchars($member_data[\'first_name\']) . "? They will become a plain Member. This can be undone with the Redo button.\');\" class=\'btn-sm\' style=\'background:#ef4444;color:white;text-decoration:none;border:none;cursor:pointer;font-weight:700;\'>🗑 Delete All</a>";
                                            if (!empty($_SESSION[\'role_undo_backup\'][$member_data[\'id\']])) {
                                                echo "<a href=\'pastor_action.php?action=redo_roles&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Restore previous roles for " . htmlspecialchars($member_data[\'first_name\']) . "?\');\" class=\'btn-sm\' style=\'background:#10b981;color:white;text-decoration:none;border:none;cursor:pointer;font-weight:700;\'>↩ Redo</a>";
                                            }
                                            echo "</div></td>";';

    // ── PASTOR: secondary row (disp_role) ──
    $old4 = 'echo "<td style=\'padding:12px;\'><div style=\'display:flex;gap:6px;flex-wrap:wrap;align-items:center;\'>";
                                echo "<a href=\'pastor_action.php?action=remove_role&id=" . $member_data[\'id\'] . "&role=" . urlencode($disp_role) . "\' onclick=\"return confirm(\'Remove this role from " . htmlspecialchars($member_data[\'first_name\']) . "?\');\" class=\'btn-sm\' style=\'background:var(--danger);color:white;text-decoration:none;border:none;cursor:pointer;\'>Remove Role</a>";
                                echo "<a href=\'pastor_action.php?action=unassign_all&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Unassign ALL roles from " . htmlspecialchars($member_data[\'first_name\']) . "? This will reset them to plain Member.\');\" class=\'btn-sm\' style=\'background:#f59e0b;color:white;text-decoration:none;border:none;cursor:pointer;\'>⚠ Unassign All</a>";
                                if (!empty($_SESSION[\'role_undo_backup\'][$member_data[\'id\']])) {
                                    echo "<a href=\'pastor_action.php?action=redo_roles&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Restore previous roles for " . htmlspecialchars($member_data[\'first_name\']) . "?\');\" class=\'btn-sm\' style=\'background:#10b981;color:white;text-decoration:none;border:none;cursor:pointer;\'>↩ Redo</a>";
                                }
                                echo "</div></td>";';

    $new4 = 'echo "<td style=\'padding:12px;\'><div style=\'display:flex;gap:6px;flex-wrap:wrap;align-items:center;\'>";
                                echo "<a href=\'pastor_action.php?action=unassign_all&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Delete ALL roles from " . htmlspecialchars($member_data[\'first_name\']) . "? They will become a plain Member. This can be undone with the Redo button.\');\" class=\'btn-sm\' style=\'background:#ef4444;color:white;text-decoration:none;border:none;cursor:pointer;font-weight:700;\'>🗑 Delete All</a>";
                                if (!empty($_SESSION[\'role_undo_backup\'][$member_data[\'id\']])) {
                                    echo "<a href=\'pastor_action.php?action=redo_roles&id=" . $member_data[\'id\'] . "\' onclick=\"return confirm(\'Restore previous roles for " . htmlspecialchars($member_data[\'first_name\']) . "?\');\" class=\'btn-sm\' style=\'background:#10b981;color:white;text-decoration:none;border:none;cursor:pointer;font-weight:700;\'>↩ Redo</a>";
                                }
                                echo "</div></td>";';

    $c1 = substr_count($content, $old1); $content = str_replace($old1, $new1, $content);
    $c2 = substr_count($content, $old2); $content = str_replace($old2, $new2, $content);
    $c3 = substr_count($content, $old3); $content = str_replace($old3, $new3, $content);
    $c4 = substr_count($content, $old4); $content = str_replace($old4, $new4, $content);

    file_put_contents($file, $content);
    echo "Updated $file: old1=$c1 old2=$c2 old3=$c3 old4=$c4\n";
}
?>
