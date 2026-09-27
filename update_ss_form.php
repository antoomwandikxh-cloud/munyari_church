<?php
$files = ['C:\\xampp\\htdocs\\munyari_church\\admin_dashboard.php', 'C:\\xampp\\htdocs\\munyari_church\\pastor_dashboard.php'];

$old_handler_pattern = '/\/\/ Handle Sunday School member registration.*?exit\(\);\n\}/s';
$new_handler = <<<'PHP'
// Handle Sunday School member registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_ss_member'])) {
    $fn   = $conn->real_escape_string(trim($_POST['first_name'] ?? ''));
    $ln   = $conn->real_escape_string(trim($_POST['last_name'] ?? ''));
    $ph   = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
    $gn   = in_array($_POST['gender'] ?? '', ['Male','Female']) ? $_POST['gender'] : 'Male';
    $ad   = $conn->real_escape_string(trim($_POST['address'] ?? ''));
    $cl   = in_array($_POST['ss_class'] ?? '', ['Battalion','Conquerors','Little Angels']) ? $_POST['ss_class'] : '';
    $pw_plain = trim($_POST['password'] ?? '');
    $pw_conf = trim($_POST['confirm_password'] ?? '');

    if (empty($fn) || empty($ln) || empty($cl) || empty($pw_plain)) {
        header("Location: ?tab=manage_sunday_school&error=" . urlencode("First name, last name, class and password are required."));
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
    
    $pw = password_hash($pw_plain, PASSWORD_DEFAULT);
    $cl_safe = $conn->real_escape_string($cl);
    
    $conn->query("INSERT INTO members (first_name, last_name, phone, address, department, gender, password, sunday_school_class, is_approved, reg_date) VALUES ('$fn','$ln','$ph','$ad','Sunday School','$gn','$pw','$cl_safe',1,NOW())");
    $new_member_id = $conn->insert_id;
    if ($new_member_id) {
        $msg = $conn->real_escape_string("Welcome to Sunday School! You have been registered in the class: $cl.");
        $conn->query("INSERT INTO notifications (user_id, user_type, message, is_read, created_at) VALUES ($new_member_id, 'member', '$msg', 0, NOW())");
    }
    header("Location: ?tab=manage_sunday_school&success=" . urlencode("Sunday School member registered successfully!"));
    exit();
}
PHP;

$old_form_pattern = '/<form method="POST" action="\?tab=manage_sunday_school" id="ssRegForm".*?<\/form>/s';
$new_form = <<<'HTML'
<form method="POST" action="?tab=manage_sunday_school" id="ssRegForm" onsubmit="this.querySelector('button[type=submit]').disabled=true;">
                        <input type="hidden" name="register_ss_member" value="1">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group">
                                <label>First Name <span style="color:var(--danger);">*</span></label>
                                <input type="text" id="ss_first_name" name="first_name" class="form-control" placeholder="e.g. John" pattern="[A-Za-z\s]+" oninput="validateInput(this,'name'); seqUnlock('ss_first_name','ss_last_name')" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name <span style="color:var(--danger);">*</span></label>
                                <input type="text" id="ss_last_name" name="last_name" class="form-control" placeholder="e.g. Kamau" pattern="[A-Za-z\s]+" oninput="validateInput(this,'name'); seqUnlock('ss_last_name','ss_class'); document.getElementById('ss_phone').disabled=false;" disabled required>
                            </div>
                            <div class="form-group">
                                <label>Phone Number <span style="color:var(--text-muted); font-weight:normal; font-size:0.8rem;">(Optional)</span></label>
                                <input type="tel" id="ss_phone" name="phone" class="form-control" placeholder="10-digit number" pattern="\d{10}" maxlength="10" oninput="validateInput(this,'phone');" disabled>
                            </div>
                            <div class="form-group">
                                <label>Sunday School Class <span style="color:var(--danger);">*</span></label>
                                <select name="ss_class" id="ss_class" class="form-control" required onchange="seqUnlock('ss_class','ss_gender')" disabled>
                                    <option value="">-- Select Class --</option>
                                    <option value="Battalion">Battalion</option>
                                    <option value="Conquerors">Conquerors</option>
                                    <option value="Little Angels">Little Angels</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Gender <span style="color:var(--danger);">*</span></label>
                                <select name="gender" id="ss_gender" class="form-control" required onchange="seqUnlock('ss_gender','ss_address')" disabled>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Residential Area <span style="color:var(--danger);">*</span></label>
                                <input type="text" id="ss_address" name="address" class="form-control" placeholder="e.g. Mugui" pattern="[A-Za-z0-9\s,.-]+" oninput="validateInput(this,'name'); seqUnlock('ss_address','ss_password')" required disabled>
                            </div>
                            <div class="form-group">
                                <label>Login Password <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <input type="password" name="password" id="ss_password" class="form-control" placeholder="At least 6 characters" required style="padding-right:46px;" minlength="6" oninput="seqUnlock('ss_password','ss_confirm_password')" disabled>
                                    <span onclick="var i=document.getElementById('ss_password');i.type=i.type==='password'?'text':'password'" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--text-muted);">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Confirm Password <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <input type="password" name="confirm_password" id="ss_confirm_password" class="form-control" placeholder="Re-enter password" required style="padding-right:46px;" oninput="var m=document.getElementById('ssPwdHint');if(this.value===document.getElementById('ss_password').value){m.style.display='block';m.style.color='var(--success)';m.textContent='Passwords match';}else{m.style.display='block';m.style.color='var(--danger)';m.textContent='Passwords do not match';}" disabled>
                                    <span onclick="var i=document.getElementById('ss_confirm_password');i.type=i.type==='password'?'text':'password'" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--text-muted);">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </span>
                                </div>
                                <small id="ssPwdHint" style="font-size:0.8rem;margin-top:4px;display:none;"></small>
                            </div>
                        </div>
                        <button type="submit" class="btn-submit" style="margin-top:10px; display:flex; align-items:center; gap:8px; width:auto; padding:0 28px;">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                            Register
                        </button>
                    </form>
HTML;

foreach ($files as $f) {
    $c = file_get_contents($f);
    $c = preg_replace($old_handler_pattern, $new_handler, $c);
    $c = preg_replace($old_form_pattern, $new_form, $c);
    file_put_contents($f, $c);
    echo "Updated " . basename($f) . "\n";
}
?>
