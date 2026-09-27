<?php
$register_file = 'C:\\xampp\\htdocs\\munyari_church\\register_page.php';
$register = file_get_contents($register_file);

$phone_icon = '<span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #f59e0b; pointer-events: none;"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg></span>';

$old_phone = '<input type="tel" id="phone" name="phone" class="form-control" placeholder="10-digit number" pattern="\d{10}" maxlength="10" oninput="validateInput(this,\'phone\'); checkPhoneAsync(this,\'address\')" disabled required>';
$new_phone = '<div style="position: relative;">' . "\n                            " . $phone_icon . "\n                            " . str_replace('class="form-control"', 'class="form-control" style="padding-left: 40px;"', $old_phone) . "\n                        " . '</div>';

$register = str_replace($old_phone, $new_phone, $register);

file_put_contents($register_file, $register);

echo "Successfully added phone icon";
?>
