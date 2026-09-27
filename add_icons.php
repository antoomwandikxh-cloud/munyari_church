<?php
$user_icon = '<span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none;"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg></span>';
$map_icon = '<span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none;"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg></span>';
$lock_icon = '<span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none;"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg></span>';


// 1. UPDATE LOGIN PAGE
$login_file = 'C:\\xampp\\htdocs\\munyari_church\\login.php';
$login = file_get_contents($login_file);

// Replace Username input
$old_user_input = '<input type="text" name="username" class="form-control" placeholder="Enter your name" required autocomplete="new-username" spellcheck="false">';
$new_user_input = '<div style="position: relative;">' . "\n                        " . $user_icon . "\n                        " . '<input type="text" name="username" class="form-control" placeholder="Enter your name" required autocomplete="new-username" spellcheck="false" style="padding-left: 40px;">' . "\n                    " . '</div>';
$login = str_replace($old_user_input, $new_user_input, $login);

// Replace Password input
$old_pass_input = '<input type="password" id="login_password" name="password" class="form-control" placeholder="Enter your password" required autocomplete="new-password" style="padding-right: 40px;">';
$new_pass_input = $lock_icon . "\n                        " . '<input type="password" id="login_password" name="password" class="form-control" placeholder="Enter your password" required autocomplete="new-password" style="padding-left: 40px; padding-right: 40px;">';
$login = str_replace($old_pass_input, $new_pass_input, $login);

file_put_contents($login_file, $login);


// 2. UPDATE REGISTER PAGE
$register_file = 'C:\\xampp\\htdocs\\munyari_church\\register_page.php';
$register = file_get_contents($register_file);

// First Name
$old_fn = '<input type="text" id="first_name" name="first_name" class="form-control" placeholder="e.g. Peter" pattern="[A-Za-z\s]+" oninput="validateInput(this,\'name\'); seqUnlock(\'first_name\',\'last_name\')" required>';
$new_fn = '<div style="position: relative;">' . "\n                            " . $user_icon . "\n                            " . str_replace('class="form-control"', 'class="form-control" style="padding-left: 40px;"', $old_fn) . "\n                        " . '</div>';
$register = str_replace($old_fn, $new_fn, $register);

// Last Name
$old_ln = '<input type="text" id="last_name" name="last_name" class="form-control" placeholder="e.g. Ntoiti" pattern="[A-Za-z\s]+" oninput="validateInput(this,\'name\'); seqUnlock(\'last_name\',\'phone\')" disabled required>';
$new_ln = '<div style="position: relative;">' . "\n                            " . $user_icon . "\n                            " . str_replace('class="form-control"', 'class="form-control" style="padding-left: 40px;"', $old_ln) . "\n                        " . '</div>';
$register = str_replace($old_ln, $new_ln, $register);

// Address (Resident Area)
$old_addr = '<input type="text" id="address" name="address" class="form-control" placeholder="e.g. Mugui" pattern="[A-Za-z\s]+" oninput="validateInput(this,\'name\'); seqUnlock(\'address\',\'department\')" disabled required>';
$new_addr = '<div style="position: relative;">' . "\n                            " . $map_icon . "\n                            " . str_replace('class="form-control"', 'class="form-control" style="padding-left: 40px;"', $old_addr) . "\n                        " . '</div>';
$register = str_replace($old_addr, $new_addr, $register);

// Password
$old_pass = '<input type="password" id="password" name="password" class="form-control" placeholder="At least 6 characters" minlength="6" oninput="seqUnlock(\'password\',\'confirm_password\')" disabled required style="padding-right: 40px;">';
$new_pass = $lock_icon . "\n                            " . '<input type="password" id="password" name="password" class="form-control" placeholder="At least 6 characters" minlength="6" oninput="seqUnlock(\'password\',\'confirm_password\')" disabled required style="padding-left: 40px; padding-right: 40px;">';
$register = str_replace($old_pass, $new_pass, $register);

// Confirm Password
$old_cpass = '<input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Repeat password" minlength="6" disabled required style="padding-right: 40px;">';
$new_cpass = $lock_icon . "\n                            " . '<input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Repeat password" minlength="6" disabled required style="padding-left: 40px; padding-right: 40px;">';
$register = str_replace($old_cpass, $new_cpass, $register);

file_put_contents($register_file, $register);

echo "Successfully added icons to login.php and register_page.php";
?>
