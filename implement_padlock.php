<?php
$file = 'admin_dashboard.php';
$content = file_get_contents($file);

// 1. Modify the role options rendering
$old_opt = <<<'PHP'
                                        foreach ($roles as $expected_role) {
                                            if ($role_is_available($expected_role, $context_department)) {
                                                $val = htmlspecialchars($expected_role);
                                                $allowed = htmlspecialchars(json_encode(role_assignment_departments($expected_role, $context_department)));
                                                $assignment_department = htmlspecialchars($context_department);
                                                $label = htmlspecialchars($expected_role);
                                                $options_html .= "<option value=\"$val\" data-allowed='$allowed' data-assignment-department=\"$assignment_department\">$label</option>";
                                            }
                                        }
PHP;
$new_opt = <<<'PHP'
                                        foreach ($roles as $expected_role) {
                                            $val = htmlspecialchars($expected_role);
                                            $allowed = htmlspecialchars(json_encode(role_assignment_departments($expected_role, $context_department)));
                                            $assignment_department = htmlspecialchars($context_department);
                                            $label = htmlspecialchars($expected_role);
                                            if ($role_is_available($expected_role, $context_department)) {
                                                $options_html .= "<option value=\"$val\" data-allowed='$allowed' data-assignment-department=\"$assignment_department\">$label</option>";
                                            } else {
                                                $options_html .= "<option value=\"$val\" data-taken=\"true\" style=\"color:var(--text-muted);\" data-allowed='$allowed' data-assignment-department=\"$assignment_department\">&#128274; $label (Taken)</option>";
                                            }
                                        }
PHP;
$content = str_replace(str_replace("\r", "", $old_opt), str_replace("\r", "", $new_opt), $content);

// 2. Modify JS logic
$old_js = "                                roleSelect.addEventListener('change', function() {\n                                    const selected = roleSelect.options[roleSelect.selectedIndex];";
$new_js = "                                roleSelect.addEventListener('change', function() {\n                                    const selected = roleSelect.options[roleSelect.selectedIndex];\n                                    if (selected && selected.dataset.taken === 'true') {\n                                        alert('🔒 This role is already taken!\\n\\nYou will now be scrolled to the Currently Assigned Roles table so you can remove the current leader before assigning a new one.');\n                                        const currentRolesTable = document.getElementById('currentlyAssignedRolesTable');\n                                        if (currentRolesTable) {\n                                            currentRolesTable.scrollIntoView({behavior: 'smooth'});\n                                            const highlightRole = selected.value.toLowerCase();\n                                            const rows = currentRolesTable.querySelectorAll('tbody tr');\n                                            rows.forEach(row => {\n                                                if (row.innerText.toLowerCase().includes(highlightRole)) {\n                                                    row.style.transition = 'background-color 0.5s';\n                                                    row.style.backgroundColor = '#fef3c7';\n                                                    setTimeout(() => row.style.backgroundColor = '', 3000);\n                                                }\n                                            });\n                                        }\n                                        roleSelect.value = '';\n                                        memberSelect.innerHTML = '<option value=\"\">-- Select Member --</option>';\n                                        memberSelect.disabled = true;\n                                        return;\n                                    }";
$content = str_replace(str_replace("\r", "", $old_js), str_replace("\r", "", $new_js), $content);

// 3. Update the redirect in remove_role
$old_redirect = "header(\"Location: admin_dashboard.php?tab=assign_roles&success=Role removed from member\");";
$new_redirect = "header(\"Location: admin_dashboard.php?tab=assign_roles&success=\" . urlencode(\"Role removed successfully\") . \"&reassign=\" . urlencode(\$role_to_remove) . \"#assignRoleSection\");";
$content = str_replace($old_redirect, $new_redirect, $content);

// 4. Add id="assignRoleSection" to the assign role div, and id="currentlyAssignedRolesTable" to the table
// Let's first make sure we can find the assign role div
$old_div = "<!-- Assign Role Form -->\n                    <div class=\"content-card\" style=\"flex: 1; min-width: 300px;\">";
$new_div = "<!-- Assign Role Form -->\n                    <div id=\"assignRoleSection\" class=\"content-card\" style=\"flex: 1; min-width: 300px;\">";
$content = str_replace($old_div, $new_div, $content);

// 5. Add id to the table
$old_table = "<div class=\"table-responsive\" style=\"margin-top: 20px;\">\n                    <table class=\"table\">";
$new_table = "<div class=\"table-responsive\" style=\"margin-top: 20px;\">\n                    <table id=\"currentlyAssignedRolesTable\" class=\"table\">";
$content = str_replace($old_table, $new_table, $content);

file_put_contents($file, $content);
echo "Updated admin_dashboard.php\n";
?>
