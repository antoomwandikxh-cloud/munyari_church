<?php
// Remove username from admin registration
$file = 'C:\\xampp\\htdocs\\munyari_church\\admin_dashboard.php';
$content = file_get_contents($file);

// Remove the input field
$old_input = '                            <div class="form-group">
                                <label>Username <span style="color:var(--danger);">*</span></label>
                                <input type="text" id="a_username" name="username" class="form-control" placeholder="e.g. peter_1" pattern="[A-Za-z0-9_]+" oninput="validateInput(this,\'username\'); checkUsernameAsync(this,\'a_phone\')" disabled required>
                            </div>';
$content = str_replace($old_input, '', $content);

// Update seqUnlock for last_name
$content = str_replace("seqUnlock('a_last_name','a_username')", "seqUnlock('a_last_name','a_phone')", $content);

// Update PHP handler
$old_php = '    $pw = password_hash(trim($_POST[\'password\']), PASSWORD_DEFAULT);
    $un = preg_replace(\'/[^A-Za-z0-9_]/\', \'\', trim($_POST[\'username\'] ?? \'\'));
    // Validate username
    if (empty($un) || strlen($un) < 3) {
        header("Location: admin_dashboard.php?tab=members&error=" . urlencode("Username must be at least 3 characters and contain only letters, numbers, and underscores."));
        exit();
    }
    $dup_un = $conn->query("SELECT id FROM members WHERE BINARY username = \'$un\'")->num_rows;
    if ($dup_un > 0) {
        header("Location: admin_dashboard.php?tab=members&error=" . urlencode("That username is already taken. Please choose a different one."));
        exit();
    }
    if (empty($fn) || empty($ln) || empty($ph) || empty($_POST[\'password\'])) {';

$new_php = '    $pw = password_hash(trim($_POST[\'password\']), PASSWORD_DEFAULT);
    if (empty($fn) || empty($ln) || empty($ph) || empty($_POST[\'password\'])) {';

$content = str_replace($old_php, $new_php, $content);

// Revert INSERT query
$old_insert = "INSERT INTO members (first_name, last_name, phone, address, department, gender, password, username, is_approved, reg_date) VALUES ('\$fn','\$ln','\$ph','\$ad','\$dp','\$gn','\$pw','\$un',1,NOW())";
$new_insert = "INSERT INTO members (first_name, last_name, phone, address, department, gender, password, is_approved, reg_date) VALUES ('\$fn','\$ln','\$ph','\$ad','\$dp','\$gn','\$pw',1,NOW())";

$content = str_replace($old_insert, $new_insert, $content);

file_put_contents($file, $content);


// Remove username from pastor registration
$file = 'C:\\xampp\\htdocs\\munyari_church\\pastor_dashboard.php';
$content = file_get_contents($file);

// Remove the input field
$old_input = '                            <div class="form-group">
                                <label>Username <span style="color:var(--danger);">*</span></label>
                                <input type="text" id="p_username" name="username" class="form-control" placeholder="e.g. peter_1" pattern="[A-Za-z0-9_]+" oninput="validateInput(this,\'username\'); checkUsernameAsync(this,\'p_phone\')" disabled required>
                            </div>';
$content = str_replace($old_input, '', $content);

// Update seqUnlock for last_name
$content = str_replace("seqUnlock('p_last_name','p_username')", "seqUnlock('p_last_name','p_phone')", $content);

// Update PHP handler
$old_php = '    $pw  = password_hash(trim($_POST[\'password\']), PASSWORD_DEFAULT);
    $un  = preg_replace(\'/[^A-Za-z0-9_]/\', \'\', trim($_POST[\'username\'] ?? \'\'));
    // Validate username
    if (empty($un) || strlen($un) < 3) {
        header("Location: pastor_dashboard.php?tab=manage_members&error=" . urlencode("Username must be at least 3 characters and contain only letters, numbers, and underscores."));
        exit();
    }
    $dup_un_m = $conn->query("SELECT id FROM members WHERE BINARY username = \'$un\'")->num_rows;
    if ($dup_un_m > 0) {
        header("Location: pastor_dashboard.php?tab=manage_members&error=" . urlencode("That username is already taken. Please choose a different one."));
        exit();
    }
    // Validate required fields
    if (empty($fn) || empty($ln) || empty($ph) || empty($_POST[\'password\'])) {';

$new_php = '    $pw  = password_hash(trim($_POST[\'password\']), PASSWORD_DEFAULT);
    // Validate required fields
    if (empty($fn) || empty($ln) || empty($ph) || empty($_POST[\'password\'])) {';
    
$content = str_replace($old_php, $new_php, $content);

// Revert INSERT query
$old_insert = "INSERT INTO members (first_name, last_name, phone, address, department, gender, password, username, is_approved, reg_date) VALUES ('\$fn','\$ln','\$ph','\$ad','\$dp','\$gn','\$pw','\$un',1,NOW())";
$new_insert = "INSERT INTO members (first_name, last_name, phone, address, department, gender, password, is_approved, reg_date) VALUES ('\$fn','\$ln','\$ph','\$ad','\$dp','\$gn','\$pw',1,NOW())";

$content = str_replace($old_insert, $new_insert, $content);

file_put_contents($file, $content);

echo "done";
?>
