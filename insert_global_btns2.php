<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);

    $is_pastor = str_contains($file, 'pastor');
    $base_link = $is_pastor ? 'pastor_action.php?action=' : 'admin_dashboard.php?tab=assign_roles&action=';

    $global_btns = '
                    <?php if (isset($has_any_roles) && $has_any_roles): ?>
                        <div style="margin-top: 30px; display:flex; gap:15px; justify-content:center; align-items:center; background:var(--bg-lighter); padding:20px; border-radius:8px; border:1px solid var(--border-color);">
                            <a href="' . $base_link . 'unassign_all_global" 
                               onclick="return confirm(\'WARNING: This will reset ALL assigned roles for EVERY member back to plain Member. This affects the entire church. Are you absolutely sure?\');" 
                               class="btn-sm" style="background:#ef4444;color:white;text-decoration:none;border:none;cursor:pointer;font-weight:700;padding:10px 20px;font-size:1rem;display:inline-flex;align-items:center;gap:8px;">
                               🗑 Delete All Roles Globally
                            </a>
                            <?php if (!empty($_SESSION[\'global_role_undo_backup\'])): ?>
                                <a href="' . $base_link . 'redo_roles_global" 
                                   onclick="return confirm(\'Restore all roles that were just deleted?\');" 
                                   class="btn-sm" style="background:#10b981;color:white;text-decoration:none;border:none;cursor:pointer;font-weight:700;padding:10px 20px;font-size:1rem;display:inline-flex;align-items:center;gap:8px;">
                                   ↩ Redo Deletion
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    ';

    $needle = "No roles are currently assigned.</p>\";\n                    }\n                    ?>";
    
    if (strpos($content, $needle) !== false) {
        $content = str_replace($needle, $needle . "\n" . $global_btns, $content);
        file_put_contents($file, $content);
        echo "Inserted global buttons in $file\n";
    } else {
        // Try alternate whitespace match
        $pos = strpos($content, "No roles are currently assigned.</p>");
        if ($pos !== false) {
            $end_php = strpos($content, "?>", $pos);
            if ($end_php !== false) {
                $content = substr($content, 0, $end_php + 2) . "\n" . $global_btns . substr($content, $end_php + 2);
                file_put_contents($file, $content);
                echo "Inserted global buttons in $file (fallback)\n";
            }
        }
    }
}
?>
