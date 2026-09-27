<?php
$login_file = 'C:\\xampp\\htdocs\\munyari_church\\login.php';
$login = file_get_contents($login_file);

// Replace header text
$login = str_replace('SIGN IN TO YOUR E.A.P.C MUNYARI CHURCH ACCOUNT', 'LOGIN TO YOUR E.A.P.C MUNYARI CHURCH ACCOUNT', $login);

// Replace button text
$login = str_replace('<button type="submit" class="btn-submit">Sign In</button>', '<button type="submit" class="btn-submit">Login</button>', $login);

file_put_contents($login_file, $login);

echo "Successfully updated Sign In to Login";
?>
