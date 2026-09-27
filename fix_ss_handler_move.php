<?php
// ─── FIX: Move register_ss_member handler to TOP of file (before any HTML output) ───

$good_handler = <<<'PHP'

// Handle Sunday School member registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_ss_member'])) {
    $fn       = $conn->real_escape_string(trim($_POST['first_name'] ?? ''));
    $ln       = $conn->real_escape_string(trim($_POST['last_name'] ?? ''));
    $ph       = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
    $gn       = in_array($_POST['gender'] ?? '', ['Male','Female']) ? $_POST['gender'] : 'Male';
    $ad       = $conn->real_escape_string(trim($_POST['address'] ?? ''));
    $cl       = in_array($_POST['ss_class'] ?? '', ['Battalion','Conquerors','Little Angels']) ? $_POST['ss_class'] : '';
    $pw_plain = trim($_POST['password'] ?? '');
    $pw_conf  = trim($_POST['confirm_password'] ?? '');

    if (empty($fn) || empty($ln) || empty($cl) || empty($pw_plain) || empty($ad)) {
        header("Location: ?tab=manage_sunday_school&error=" . urlencode("First name, last name, address, class and password are required."));
        exit();
    }
    if ($pw_plain !== $pw_conf) {
        header("Location: ?tab=manage_sunday_school&error=" . urlencode("Passwords do not match."));
        exit();
    }
    if (!empty($ph)) {
        $dup = $conn->query("SELECT id FROM members WHERE phone = '$ph'")->num_rows;
        if ($dup > 0) {
            header("Location: ?tab=manage_sunday_school&error=" . urlencode("A member with that phone number already exists."));
            exit();
        }
    }
    $pw      = password_hash($pw_plain, PASSWORD_DEFAULT);
    $cl_safe = $conn->real_escape_string($cl);
    $conn->query("INSERT INTO members (first_name, last_name, phone, address, department, gender, password, sunday_school_class, is_approved, reg_date)
                  VALUES ('$fn','$ln','$ph','$ad','Sunday School','$gn','$pw','$cl_safe',1,NOW())");
    $new_member_id = $conn->insert_id;
    if ($new_member_id) {
        // Notify the new member
        $msg = $conn->real_escape_string("Welcome to Sunday School! You have been registered in the $cl class.");
        $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($new_member_id, 'member', '$msg', 0, NOW())");
        // Notify all admins and pastors
        $notif_msg = $conn->real_escape_string("$fn $ln has been registered as a new Sunday School member in the $cl class.");
        $admins = $conn->query("SELECT id FROM admins");
        while ($a = $admins->fetch_assoc()) {
            $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ({$a['id']}, 'admin', '$notif_msg', 0, NOW())");
        }
        $pastors = $conn->query("SELECT id FROM pastors");
        while ($p = $pastors->fetch_assoc()) {
            $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ({$p['id']}, 'pastor', '$notif_msg', 0, NOW())");
        }
    }
    header("Location: ?tab=manage_sunday_school&success=" . urlencode("Sunday School member $fn $ln registered successfully!"));
    exit();
}

PHP;

// ── Regex to remove the MISPLACED handler anywhere in the file ──
$bad_pattern = '/\n?\<\?php\s*\n\/\/ Handle Sunday School member registration\nif \(\$_SERVER\[\'REQUEST_METHOD\'\] == \'POST\' && isset\(\$_POST\[\'register_ss_member\'\]\)\).*?exit\(\);\n\}\n\?>/s';

// Also try to remove untagged versions
$bad_pattern2 = '/\n\/\/ Handle Sunday School member registration\nif \(\$_SERVER\[\'REQUEST_METHOD\'\] == \'POST\' && isset\(\$_POST\[\'register_ss_member\'\]\)\).*?exit\(\);\n\}/s';

foreach (['pastor_dashboard.php', 'admin_dashboard.php'] as $fname) {
    $file    = "C:\\xampp\\htdocs\\munyari_church\\$fname";
    $content = file_get_contents($file);

    // Remove ALL existing occurrences of the handler (tagged and untagged)
    $content = preg_replace($bad_pattern,  '', $content);
    $content = preg_replace($bad_pattern2, '', $content);

    // Insert good handler right after last line of the top POST handlers block
    // — find the last occurrence of "exit();\n}\n\n// " pattern near top (before DOCTYPE)
    $html_start = strpos($content, '<!DOCTYPE html>');
    if ($html_start === false) {
        // Try another marker
        $html_start = strpos($content, '<html');
    }

    // Find the insertion point: right before the line "// ─── end of POST handlers" 
    // or just before first "?>", whichever closes the top PHP block
    $top_php = substr($content, 0, $html_start);
    $last_php_close = strrpos($top_php, '?>');

    if ($last_php_close !== false) {
        // Insert our handler just before the ?> that closes the PHP block
        $content = substr_replace($content, $good_handler, $last_php_close, 0);
        echo "$fname: Handler moved to top PHP block.\n";
    } else {
        echo "$fname: WARNING - could not find ?> to insert before.\n";
    }

    file_put_contents($file, $content);
}

// Syntax check
echo "\nSyntax checks:\n";
foreach (['pastor_dashboard.php', 'admin_dashboard.php'] as $fname) {
    $file = "C:\\xampp\\htdocs\\munyari_church\\$fname";
    exec("C:\\xampp\\php\\php.exe -l \"$file\" 2>&1", $out, $code);
    echo "$fname: " . implode('', $out) . "\n";
    $out = [];
}
echo "\nDone!\n";
?>
