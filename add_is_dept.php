<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    $content = file_get_contents($file);
    
    // Add $is_dept_leader definition right after $row_bg line in print loop
    $old = '$row_bg = ($print_row % 2 === 0) ? \'#eff6ff\' : \'#ffffff\'; ?>';
    $new = '$row_bg = ($print_row % 2 === 0) ? \'#eff6ff\' : \'#ffffff\';
                                    $role_lower = strtolower($pm[\'church_role\'] ?? \'\');
                                    $is_dept_leader = !empty($pm[\'church_role\']) && empty($pm[\'is_pastor\']) && (
                                        strpos($role_lower, \'chairman\') !== false ||
                                        strpos($role_lower, \'chairlady\') !== false ||
                                        strpos($role_lower, \'chairperson\') !== false ||
                                        strpos($role_lower, \'patron\') !== false ||
                                        strpos($role_lower, \'secretary\') !== false ||
                                        strpos($role_lower, \'treasurer\') !== false ||
                                        strpos($role_lower, \'village leader\') !== false
                                    ); ?>';
    
    $count = substr_count($content, $old);
    $content = str_replace($old, $new, $content);
    file_put_contents($file, $content);
    echo "Updated $file - replaced $count occurrence(s)\n";
}
?>
