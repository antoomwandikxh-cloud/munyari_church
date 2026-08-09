<?php
$conn = new mysqli("localhost", "root", "", "munyari_church");

// 1. Revert and Update pastor_action.php
$content = file_get_contents("pastor_action.php");
// Remove the gender logic I added
$pattern = "/\/\/ Gender-aware title.*?\\$member_department/is";
$replacement = "\$member_department";
$content = preg_replace($pattern, $replacement, $content);
$content = str_ireplace("youth chairman", "youth chairperson", $content);
$content = str_ireplace("vice youth chairman", "vice youth chairperson", $content);
$content = str_ireplace("Youth Chairlady", "Youth Chairperson", $content);
$content = str_ireplace("Vice Youth Chairlady", "Vice Youth Chairperson", $content);
file_put_contents("pastor_action.php", $content);

// 2. Update role_departments.php
$content = file_get_contents("role_departments.php");
// Remove chairlady mappings
$content = preg_replace("/\s*\'youth chairlady\' => \'Youths\',/", "", $content);
$content = preg_replace("/\s*\'vice youth chairlady\' => \'Youths\',/", "", $content);
$content = preg_replace("/\s*WHEN \'youth chairlady\' THEN 1/", "", $content);
$content = preg_replace("/\s*WHEN \'vice youth chairlady\' THEN 2/", "", $content);
$content = str_ireplace("youth chairman", "youth chairperson", $content);
$content = str_ireplace("vice youth chairman", "vice youth chairperson", $content);
file_put_contents("role_departments.php", $content);

// 3. Update pastor_dashboard.php
$content = file_get_contents("pastor_dashboard.php");
// Revert JS hint
$js_pattern = "/memberSelect\.addEventListener\(\'change\', function\(\).*?\}\);\s*\}\)\(\);/is";
$js_repl = "})();";
$content = preg_replace($js_pattern, $js_repl, $content);
// Remove data-gender from member options
$content = preg_replace("/\s*data-gender=\"<\?= htmlspecialchars\(strtolower\(\\$m\[\'gender\'\] \?\? \'male\'\)\) \?>\"/", "", $content);
// Fix required roles arrays (remove chairlady)
$content = preg_replace("/\'Youth Chairlady\', /", "", $content);
$content = preg_replace("/\'Vice Youth Chairlady\', /", "", $content);
$content = str_ireplace("youth chairman", "youth chairperson", $content);
$content = str_ireplace("vice youth chairman", "vice youth chairperson", $content);
$content = str_ireplace("Department Chairman/Chairlady", "Department Chairperson", $content);
file_put_contents("pastor_dashboard.php", $content);

// 4. Update member_dashboard.php
$content = file_get_contents("member_dashboard.php");
// Remove chairlady from leader_departments
$content = preg_replace("/\s*\'youth chairlady\' => \'Youths\',/", "", $content);
$content = preg_replace("/\s*\'vice youth chairlady\' => \'Youths\',/", "", $content);
$content = str_ireplace("youth chairman", "youth chairperson", $content);
$content = str_ireplace("vice youth chairman", "vice youth chairperson", $content);
file_put_contents("member_dashboard.php", $content);

// Update Database
$conn->query("UPDATE church_roles SET role_name = 'Youth Chairperson' WHERE role_name IN ('Youth Chairman', 'Youth Chairlady')");
$conn->query("UPDATE church_roles SET role_name = 'Vice Youth Chairperson' WHERE role_name IN ('Vice Youth Chairman', 'Vice Youth Chairlady')");
$conn->query("UPDATE members SET church_role = REPLACE(church_role, 'Youth Chairman', 'Youth Chairperson')");
$conn->query("UPDATE members SET church_role = REPLACE(church_role, 'Youth Chairlady', 'Youth Chairperson')");
$conn->query("UPDATE members SET church_role = REPLACE(church_role, 'Vice Youth Chairman', 'Vice Youth Chairperson')");
$conn->query("UPDATE members SET church_role = REPLACE(church_role, 'Vice Youth Chairlady', 'Vice Youth Chairperson')");

echo "Done";
?>
