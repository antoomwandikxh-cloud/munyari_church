<?php
$register_file = 'C:\\xampp\\htdocs\\munyari_church\\register_page.php';
$login_file = 'C:\\xampp\\htdocs\\munyari_church\\login.php';

// 1. Update register_page.php link
$register = file_get_contents($register_file);
$register = str_replace(
    '<p style="color: var(--text-muted);">Already have an account? <a href="login.php">Sign In</a></p>',
    '<p style="color: var(--text-muted);">Already have an account? <a href="login.php">Login</a></p>',
    $register
);
file_put_contents($register_file, $register);

// 2. Add icon to login button in login.php
$login = file_get_contents($login_file);
$login_icon = '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>';
$old_btn = '<button type="submit" class="btn-submit">Login</button>';
$new_btn = '<button type="submit" class="btn-submit" style="display: flex; align-items: center; justify-content: center; gap: 8px;">' . "\n                    " . $login_icon . "\n                    Login\n                </button>";

$login = str_replace($old_btn, $new_btn, $login);
file_put_contents($login_file, $login);

echo "Successfully updated link and added login button icon";
?>
