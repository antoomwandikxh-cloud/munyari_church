<?php
$files = ['pastor_dashboard.php', 'admin_dashboard.php'];

// The correct handler to insert at top
$good_handler = '
// Handle Sunday School member registration
if ($_SERVER[\'REQUEST_METHOD\'] == \'POST\' && isset($_POST[\'register_ss_member\'])) {
    $fn       = $conn->real_escape_string(trim($_POST[\'first_name\'] ?? \'\'));
    $ln       = $conn->real_escape_string(trim($_POST[\'last_name\'] ?? \'\'));
    $ph       = $conn->real_escape_string(trim($_POST[\'phone\'] ?? \'\'));
    $gn       = in_array($_POST[\'gender\'] ?? \'\', [\'Male\',\'Female\']) ? $_POST[\'gender\'] : \'Male\';
    $ad       = $conn->real_escape_string(trim($_POST[\'address\'] ?? \'\'));
    $cl       = in_array($_POST[\'ss_class\'] ?? \'\', [\'Battalion\',\'Conquerors\',\'Little Angels\']) ? $_POST[\'ss_class\'] : \'\';
    $pw_plain = trim($_POST[\'password\'] ?? \'\');
    $pw_conf  = trim($_POST[\'confirm_password\'] ?? \'\');

    if (empty($fn) || empty($ln) || empty($cl) || empty($pw_plain) || empty($ad)) {
        header("Location: ?tab=manage_sunday_school&error=" . urlencode("First name, last name, address, class and password are required."));
        exit();
    }
    if ($pw_plain !== $pw_conf) {
        header("Location: ?tab=manage_sunday_school&error=" . urlencode("Passwords do not match."));
        exit();
    }
    if (!empty($ph)) {
        $dup = $conn->query("SELECT id FROM members WHERE phone = \'$ph\'")->num_rows;
        if ($dup > 0) {
            header("Location: ?tab=manage_sunday_school&error=" . urlencode("A member with that phone number already exists."));
            exit();
        }
    }
    $pw      = password_hash($pw_plain, PASSWORD_DEFAULT);
    $cl_safe = $conn->real_escape_string($cl);
    $conn->query("INSERT INTO members (first_name, last_name, phone, address, department, gender, password, sunday_school_class, is_approved, reg_date) VALUES (\'$fn\',\'$ln\',\'$ph\',\'$ad\',\'Sunday School\',\'$gn\',\'$pw\',\'$cl_safe\',1,NOW())");
    $new_mid = $conn->insert_id;
    if ($new_mid) {
        $welcome = $conn->real_escape_string("Welcome to Sunday School! You have been registered in the $cl class.");
        $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($new_mid, \'member\', \'$welcome\', 0, NOW())");
        $nmsg = $conn->real_escape_string("$fn $ln has been registered as a new Sunday School member in the $cl class.");
        $ar = $conn->query("SELECT id FROM admins");
        while ($a = $ar->fetch_assoc()) { $aid = (int)$a[\'id\']; $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($aid, \'admin\', \'$nsg\', 0, NOW())"); }
        $pr = $conn->query("SELECT id FROM pastors");
        while ($p = $pr->fetch_assoc()) { $pid = (int)$p[\'id\']; $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($pid, \'pastor\', \'$nsg\', 0, NOW())"); }
    }
    header("Location: ?tab=manage_sunday_school&success=" . urlencode("Sunday School member $fn $ln registered successfully!"));
    exit();
}
';

foreach ($files as $fname) {
    $file = "C:\\xampp\\htdocs\\munyari_church\\$fname";
    $content = file_get_contents($file);

    // 1. Remove ALL existing SS handlers (tagged with <?php ?> or untagged)
    // Pattern: remove from "// Handle Sunday School" through closing }
    $content = preg_replace(
        '/<\?php\s*\n\/\/ Handle Sunday School member registration\nif.*?exit\(\);\n\}\n\?>/s',
        '',
        $content
    );
    $content = preg_replace(
        '/\n\/\/ Handle Sunday School member registration\nif.*?exit\(\);\n\}/s',
        '',
        $content
    );

    // 2. Find a safe insertion point at the TOP: right after the last top-level
    //    handler's closing brace, before the first ?>
    //    We'll insert before "// Handle Delete Member" or similar, or just before the closing ?>
    $html_start = strpos($content, '<!DOCTYPE html>');
    if ($html_start === false) $html_start = strpos($content, '<html');

    $top_php_block = substr($content, 0, $html_start);
    
    // Insert right before the last ?> that ends the top PHP block
    $last_close = strrpos($top_php_block, '?>');
    if ($last_close !== false) {
        $content = substr_replace($content, $good_handler . "\n", $last_close, 0);
        echo "$fname: Handler inserted at top.\n";
    } else {
        echo "$fname: Could not find insertion point.\n";
    }

    file_put_contents($file, $content);
}

// Syntax check
echo "\nSyntax check:\n";
foreach ($files as $fname) {
    $file = "C:\\xampp\\htdocs\\munyari_church\\$fname";
    $out = shell_exec("C:\\xampp\\php\\php.exe -l \"$file\" 2>&1");
    echo "$fname: $out\n";
}
echo "Done!\n";
?>
