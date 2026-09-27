<?php
$file = "pastor_dashboard.php";
$lines = file($file);

$new_lines = [];
$skip = false;
foreach ($lines as $i => $line) {
    if ($i == 167) { // 168th line, 0-indexed
        $new_lines[] = "    if (\$_POST['password'] !== (\$_POST['confirm_password'] ?? '')) {\n";
        $new_lines[] = "        header(\"Location: pastor_dashboard.php?tab=manage_members&error=Passwords do not match\");\n";
        $new_lines[] = "        exit();\n";
        $new_lines[] = "    }\n";
        $new_lines[] = "    \n";
        $new_lines[] = "    // Check duplicate phone in both members and pastors\n";
        $new_lines[] = "    \$dup_member = \$conn->query(\"SELECT id FROM members WHERE phone = '\$ph'\")->num_rows;\n";
        $new_lines[] = "    \$dup_pastor = \$conn->query(\"SELECT id FROM pastors WHERE phone = '\$ph'\")->num_rows;\n";
        $new_lines[] = "    if (\$dup_member > 0 || \$dup_pastor > 0) {\n";
        $new_lines[] = "        header(\"Location: pastor_dashboard.php?tab=manage_members&error=\" . urlencode(\"That number is already registered. Please use a different number.\"));\n";
        $new_lines[] = "        exit();\n";
        $new_lines[] = "    }\n";
        $new_lines[] = "    \n";
        $new_lines[] = "    \$conn->query(\"INSERT INTO members (first_name, last_name, phone, address, department, gender, password, is_approved, reg_date) VALUES ('\$fn','\$ln','\$ph','\$ad','\$dp','\$gn','\$pw',1,NOW())\");\n";
        $new_lines[] = "    \$new_id = \$conn->insert_id;\n";
        $new_lines[] = "    \n";
        $new_lines[] = "    // Send welcome notification to the new member\n";
        $new_lines[] = "    \$welcome_msg = \$conn->real_escape_string(\"Welcome to Munyari Church, \$fn \$ln! Your account has been created and approved by the Pastor.\");\n";
        $new_lines[] = "    \$conn->query(\"INSERT INTO notifications (user_id, user_type, message) VALUES (\$new_id, 'member', '\$welcome_msg')\");\n";
        $new_lines[] = "    \n";
        $new_lines[] = "    header(\"Location: pastor_dashboard.php?tab=manage_members&success=Member \$fn \$ln registered successfully\");\n";
        $new_lines[] = "    exit();\n";
        $new_lines[] = "}\n";
        $new_lines[] = "\n";
        $new_lines[] = "// Handle Delete Member\n";
        $new_lines[] = "if (isset(\$_GET['action']) && \$_GET['action'] == 'delete_member' && isset(\$_GET['id'])) {\n";
        $new_lines[] = "    \$del_id = (int)\$_GET['id'];\n";
        $new_lines[] = "    \$conn->query(\"DELETE FROM members WHERE id = \$del_id\");\n";
        $new_lines[] = "    header(\"Location: pastor_dashboard.php?tab=manage_members&success=Member deleted\");\n";
        $new_lines[] = "    exit();\n";
        $new_lines[] = "}\n";
        $new_lines[] = "\n";
        $new_lines[] = "// Handle Deactivate Member\n";
        $new_lines[] = "if (isset(\$_GET['action']) && \$_GET['action'] == 'deactivate_member' && isset(\$_GET['id'])) {\n";
        $new_lines[] = "    \$dec_id = (int)\$_GET['id'];\n";
        $new_lines[] = "    \$conn->query(\"UPDATE members SET is_approved = -1 WHERE id = \$dec_id\");\n";
        $new_lines[] = "    header(\"Location: pastor_dashboard.php?tab=manage_members&success=Member deactivated\");\n";
        $new_lines[] = "    exit();\n";
        $new_lines[] = "}\n";
    }
    
    if ($i >= 167 && $i <= 175) {
        continue;
    }
    $new_lines[] = $line;
}

file_put_contents($file, implode("", $new_lines));
echo "Done";
?>
