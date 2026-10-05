<?php
// The new name cell logic - adds is_dept_leader check after $row_bg line
$new_vars_block = '                                    $roles_str = implode(\', \', array_filter(array_map(\'trim\', explode(\',\', $pm[\'church_role\'] ?? \'Member\'))));
                                    $row_bg = ($print_row % 2 === 0) ? \'#eff6ff\' : \'#ffffff\';
                                    // Detect department leader (chairman/chairlady/chairperson/patron but NOT pastor)
                                    $role_lower = strtolower($pm[\'church_role\'] ?? \'\');
                                    $is_dept_leader = !empty($pm[\'church_role\']) && !empty($pm[\'is_approved\']) && (
                                        strpos($role_lower, \'chairman\') !== false ||
                                        strpos($role_lower, \'chairlady\') !== false ||
                                        strpos($role_lower, \'chairperson\') !== false ||
                                        strpos($role_lower, \'patron\') !== false ||
                                        strpos($role_lower, \'secretary\') !== false ||
                                        strpos($role_lower, \'treasurer\') !== false ||
                                        strpos($role_lower, \'village leader\') !== false
                                    ) && empty($pm[\'is_pastor\']);';

$old_vars_block = '                                    $roles_str = implode(\', \', array_filter(array_map(\'trim\', explode(\',\', $pm[\'church_role\'] ?? \'Member\'))));
                                    $row_bg = ($print_row % 2 === 0) ? \'#eff6ff\' : \'#ffffff\';';

// New name cell with 3-tier boldness
$new_name_cell = '<td style="padding:5px 8px;border:1px solid #ddd;text-transform:uppercase;<?php
                                        if (!empty($pm[\'is_pastor\'])) { echo \'font-weight:900;color:#1e3a8a;font-size:0.85rem;\'; }
                                        elseif ($is_dept_leader) { echo \'font-weight:800;color:#1e1a3a;\'; }
                                        else { echo \'font-weight:600;\'; }
                                    ?>">
                                        <?= htmlspecialchars($pm[\'first_name\'].\' \'.$pm[\'last_name\']) ?>
                                        <?= !empty($pm[\'is_pastor\']) ? \' <span style="color:#2563eb;">(PASTOR)</span>\' : \'\' ?>
                                    </td>';

$old_name_cell = '<td style="padding:5px 8px;border:1px solid #ddd;text-transform:uppercase; font-weight:<?= !empty($pm[\'is_pastor\']) ? \'900;color:#1e3a8a;font-size:0.85rem;\' : \'600;\' ?>;">
                                        <?= htmlspecialchars($pm[\'first_name\'].\' \'.$pm[\'last_name\']) ?>
                                        <?= !empty($pm[\'is_pastor\']) ? \' <span style="color:#2563eb;">(PASTOR)</span>\' : \'\' ?>
                                    </td>';

foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    $content = file_get_contents($file);
    
    $content = str_replace($old_vars_block, $new_vars_block, $content);
    $content = str_replace($old_name_cell, $new_name_cell, $content);
    
    file_put_contents($file, $content);
    echo "Updated $file | vars_match:" . substr_count($content, 'is_dept_leader') . "\n";
}
?>
