<?php
// Fix: The SS handler was injected inside the HTML output section (after alerts).
// It needs to be inside a proper <?php ?> block, placed BEFORE the HTML output begins.
// We'll move it to the top PHP section (before the HTML starts) and remove the misplaced one.

foreach (['admin', 'pastor'] as $dash) {
    $file = "C:\\xampp\\htdocs\\munyari_church\\{$dash}_dashboard.php";
    $content = file_get_contents($file);

    // The misplaced handler block (without PHP tags, sitting in HTML territory)
    $bad_handler = "\n// Handle Sunday School member registration from Manage Sunday School tab\nif (\$_SERVER['REQUEST_METHOD'] == 'POST' && isset(\$_POST['register_ss_member'])) {\n    \$fn   = \$conn->real_escape_string(trim(\$_POST['first_name'] ?? ''));\n    \$ln   = \$conn->real_escape_string(trim(\$_POST['last_name'] ?? ''));\n    \$ph   = \$conn->real_escape_string(trim(\$_POST['phone'] ?? ''));\n    \$gn   = in_array(\$_POST['gender'] ?? '', ['Male','Female']) ? \$_POST['gender'] : 'Male';\n    \$ad   = \$conn->real_escape_string(trim(\$_POST['address'] ?? ''));\n    \$cl   = in_array(\$_POST['ss_class'] ?? '', ['Battalion','Conquerors','Little Angels']) ? \$_POST['ss_class'] : '';\n    \$pw   = password_hash(trim(\$_POST['password'] ?? ''), PASSWORD_DEFAULT);\n    if (empty(\$fn) || empty(\$ln) || empty(\$ph) || empty(\$cl) || empty(\$_POST['password'])) {\n        header(\"Location: ?tab=manage_sunday_school&error=\" . urlencode(\"All required fields must be filled.\"));\n        exit();\n    }\n    \$dup = \$conn->query(\"SELECT id FROM members WHERE phone = '\$ph'\")->num_rows;\n    if (\$dup > 0) {\n        header(\"Location: ?tab=manage_sunday_school&error=\" . urlencode(\"A member with that phone number already exists.\"));\n        exit();\n    }\n    \$cl_safe = \$conn->real_escape_string(\$cl);\n    \$conn->query(\"INSERT INTO members (first_name, last_name, phone, address, department, gender, password, sunday_school_class, is_approved, reg_date) VALUES ('\$fn','\$ln','\$ph','\$ad','Sunday School','\$gn','\$pw','\$cl_safe',1,NOW())\");\n    header(\"Location: ?tab=manage_sunday_school&success=\" . urlencode(\"Sunday School member registered successfully!\"));\n    exit();\n}";

    // Remove the bad (untagged) occurrence
    if (strpos($content, $bad_handler) !== false) {
        $content = str_replace($bad_handler, '', $content);
        echo "$dash: Removed misplaced handler\n";
    } else {
        echo "$dash: Could not find exact bad handler — trying regex approach\n";
        // Fallback: remove any untagged block
        $content = preg_replace(
            '/\n\/\/ Handle Sunday School member registration from Manage Sunday School tab\nif \(\$_SERVER\[.REQUEST_METHOD.\].*?exit\(\);\n\}/s',
            '',
            $content
        );
        echo "$dash: Regex removal attempted\n";
    }

    // Now add the correct handler with proper PHP tags BEFORE the HTML starts
    // Find the insertion point: right before "?>" that ends the top PHP block
    // The top PHP block ends just before <!DOCTYPE html>
    $html_start = strpos($content, '<!DOCTYPE html>');
    if ($html_start !== false) {
        // Find the last ?> before <!DOCTYPE html>
        $php_close = strrpos(substr($content, 0, $html_start), '?>');
        if ($php_close !== false) {
            $good_handler = "\n// Handle Sunday School member registration\nif (\$_SERVER['REQUEST_METHOD'] == 'POST' && isset(\$_POST['register_ss_member'])) {\n    \$fn   = \$conn->real_escape_string(trim(\$_POST['first_name'] ?? ''));\n    \$ln   = \$conn->real_escape_string(trim(\$_POST['last_name'] ?? ''));\n    \$ph   = \$conn->real_escape_string(trim(\$_POST['phone'] ?? ''));\n    \$gn   = in_array(\$_POST['gender'] ?? '', ['Male','Female']) ? \$_POST['gender'] : 'Male';\n    \$ad   = \$conn->real_escape_string(trim(\$_POST['address'] ?? ''));\n    \$cl   = in_array(\$_POST['ss_class'] ?? '', ['Battalion','Conquerors','Little Angels']) ? \$_POST['ss_class'] : '';\n    \$pw   = password_hash(trim(\$_POST['password'] ?? ''), PASSWORD_DEFAULT);\n    if (empty(\$fn) || empty(\$ln) || empty(\$ph) || empty(\$cl) || empty(\$_POST['password'])) {\n        header(\"Location: ?tab=manage_sunday_school&error=\" . urlencode(\"All required fields must be filled.\"));\n        exit();\n    }\n    \$dup = \$conn->query(\"SELECT id FROM members WHERE phone = '\$ph'\")->num_rows;\n    if (\$dup > 0) {\n        header(\"Location: ?tab=manage_sunday_school&error=\" . urlencode(\"A member with that phone number already exists.\"));\n        exit();\n    }\n    \$cl_safe = \$conn->real_escape_string(\$cl);\n    \$conn->query(\"INSERT INTO members (first_name, last_name, phone, address, department, gender, password, sunday_school_class, is_approved, reg_date) VALUES ('\$fn','\$ln','\$ph','\$ad','Sunday School','\$gn','\$pw','\$cl_safe',1,NOW())\");\n    header(\"Location: ?tab=manage_sunday_school&success=\" . urlencode(\"Sunday School member registered successfully!\"));\n    exit();\n}\n";
            // Insert right before the last ?> before <!DOCTYPE
            $content = substr_replace($content, $good_handler, $php_close, 0);
            echo "$dash: Inserted good handler inside PHP block\n";
        }
    }

    file_put_contents($file, $content);
}

// Syntax check
foreach (['admin', 'pastor'] as $dash) {
    $file = "C:\\xampp\\htdocs\\munyari_church\\{$dash}_dashboard.php";
    exec("C:\\xampp\\php\\php.exe -l \"$file\" 2>&1", $out, $code);
    echo "$dash syntax: " . implode(' ', $out) . "\n";
    $out = [];
}
echo "Done!\n";
?>
