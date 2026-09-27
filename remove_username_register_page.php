<?php
$file = 'C:\\xampp\\htdocs\\munyari_church\\register_page.php';
$content = file_get_contents($file);

// 1. Remove the username input field
$username_html = '                <div class="form-group" style="margin-bottom:15px;">
                    <label for="username">Username (Letters, numbers, underscores only)</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="e.g. peter_1" pattern="[A-Za-z0-9_]+" oninput="validateInput(this,\'username\'); checkUsernameAsync(this,\'phone\')" disabled required>
                </div>';

$content = str_replace($username_html, '', $content);
$content = str_replace(trim($username_html), '', $content); // in case indentation differs

// 2. Fix the sequence unlock on last_name
$content = str_replace("seqUnlock('last_name','username')", "seqUnlock('last_name','phone')", $content);

file_put_contents($file, $content);
echo "Successfully removed username from register_page.php";
?>
