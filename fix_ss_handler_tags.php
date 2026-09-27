<?php
$files = ['C:\\xampp\\htdocs\\munyari_church\\admin_dashboard.php', 'C:\\xampp\\htdocs\\munyari_church\\pastor_dashboard.php'];

foreach ($files as $file) {
    $content = file_get_contents($file);

    $bad_code = "            \n// Handle Sunday School member registration from Manage Sunday School tab\nif (\$_SERVER['REQUEST_METHOD'] == 'POST' && isset(\$_POST['register_ss_member'])) {\n    \$fn   = \$conn->real_escape_string(trim(\$_POST['first_name'] ?? ''));\n    \$ln   = \$conn->real_escape_string(trim(\$_POST['last_name'] ?? ''));\n    \$ph   = \$conn->real_escape_string(trim(\$_POST['phone'] ?? ''));\n    \$gn   = in_array(\$_POST['gender'] ?? '', ['Male','Female']) ? \$_POST['gender'] : 'Male';\n    \$ad   = \$conn->real_escape_string(trim(\$_POST['address'] ?? ''));\n    \$cl   = in_array(\$_POST['ss_class'] ?? '', ['Battalion','Conquerors','Little Angels']) ? \$_POST['ss_class'] : '';\n    \$pw   = password_hash(trim(\$_POST['password'] ?? ''), PASSWORD_DEFAULT);\n    if (empty(\$fn) || empty(\$ln) || empty(\$ph) || empty(\$cl) || empty(\$_POST['password'])) {\n        header(\"Location: ?tab=manage_sunday_school&error=\" . urlencode(\"All required fields must be filled.\"));\n        exit();\n    }\n    \$dup = \$conn->query(\"SELECT id FROM members WHERE phone = '\$ph'\")->num_rows;\n    if (\$dup > 0) {\n        header(\"Location: ?tab=manage_sunday_school&error=\" . urlencode(\"A member with that phone number already exists.\"));\n        exit();\n    }\n    \$cl_safe = \$conn->real_escape_string(\$cl);\n    \$conn->query(\"INSERT INTO members (first_name, last_name, phone, address, department, gender, password, sunday_school_class, is_approved, reg_date) VALUES ('\$fn','\$ln','\$ph','\$ad','Sunday School','\$gn','\$pw','\$cl_safe',1,NOW())\");\n    header(\"Location: ?tab=manage_sunday_school&success=\" . urlencode(\"Sunday School member registered successfully!\"));\n    exit();\n}\n";

    if (strpos($content, $bad_code) !== false) {
        $good_code = "\n<?php\n" . ltrim($bad_code) . "?>\n";
        $content = str_replace($bad_code, $good_code, $content);
        file_put_contents($file, $content);
        echo "Fixed $file\n";
    } else {
        echo "Could not find bad code in $file\n";
        
        // try a looser regex
        $pattern = '/\/\/ Handle Sunday School member registration from Manage Sunday School tab.*?exit\(\);\n\}/s';
        if (preg_match($pattern, $content, $matches)) {
            $matched_text = $matches[0];
            // Check if it's already inside <?php
            // Just replace it with wrapped version
            $good_code = "<?php\n" . $matched_text . "\n?>";
            $content = str_replace($matched_text, $good_code, $content);
            file_put_contents($file, $content);
            echo "Fixed $file using regex\n";
        }
    }
}
?>
