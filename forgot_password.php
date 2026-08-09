<?php
session_start();
require_once 'db_connect.php';

$message = "";
$step = 1;
$role = $username = $phone = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['verify'])) {
        $role = $_POST['role'];
        $username = $conn->real_escape_string($_POST['username']);
        $phone = $conn->real_escape_string($_POST['phone']);
        
        $found = false;
        if ($role == 'admin') {
            $result = $conn->query("SELECT id FROM admins WHERE username = '$username'");
            if ($result->num_rows > 0) $found = true;
        } elseif ($role == 'pastor') {
            $result = $conn->query("SELECT id FROM pastors WHERE first_name = '$username' AND phone = '$phone'");
            if ($result->num_rows > 0) $found = true;
        } elseif ($role == 'member') {
            $result = $conn->query("SELECT id FROM members WHERE first_name = '$username' AND phone = '$phone'");
            if ($result->num_rows > 0) $found = true;
        }
        
        if ($found) {
            $step = 2;
            $_SESSION['reset_role'] = $role;
            $_SESSION['reset_user'] = $username;
        } else {
            $message = "<div class='alert alert-error'>Details not found! Please check your information.</div>";
        }
    } 
    elseif (isset($_POST['reset_password'])) {
        $new_pass = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        
        if ($new_pass === $confirm) {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $r_role = $_SESSION['reset_role'];
            $r_user = $_SESSION['reset_user'];
            
            if ($r_role == 'admin') $conn->query("UPDATE admins SET password = '$hashed' WHERE username = '$r_user'");
            elseif ($r_role == 'pastor') $conn->query("UPDATE pastors SET password = '$hashed' WHERE first_name = '$r_user'");
            elseif ($r_role == 'member') $conn->query("UPDATE members SET password = '$hashed' WHERE first_name = '$r_user'");
            
            session_destroy();
            
            echo "<!DOCTYPE html><html lang='en'><head><meta name='viewport' content='width=device-width, initial-scale=1.0'><link rel='stylesheet' href='style.css'></head><body><div class='auth-wrapper'><div class='auth-card'>";
            echo "<div class='auth-header'><h2>Success</h2></div><div class='alert alert-success'>Password reset successful!</div>";
            echo "<a href='login.php' class='btn-submit' style='margin-top:20px;'>Go to Sign In</a></div></div></body></html>";
            exit();
        } else {
            $message = "<div class='alert alert-error'>Passwords do not match!</div>";
            $step = 2;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Munyari Church</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <h2>Reset Password</h2>
                <p>Recover access to your account</p>
            </div>
            
            <?= $message ?>
            
            <?php if ($step == 1): ?>
            <form action="forgot_password.php" method="POST">
                <div class="form-group">
                    <label>Account Type</label>
                    <select name="role" class="form-control" required>
                        <option value="member">Member</option>
                        <option value="pastor">Pastor</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>First Name (or Admin Username)</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Phone Number (Enter anything for Admin)</label>
                    <input type="text" name="phone" class="form-control" required>
                </div>
                <button type="submit" name="verify" class="btn-submit">Verify Identity</button>
            </form>
            
            <?php elseif ($step == 2): ?>
            <form action="forgot_password.php" method="POST">
                <div class="alert alert-success" style="font-size: 0.9rem;">Identity verified! Enter your new password.</div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" name="reset_password" class="btn-submit">Reset Password</button>
            </form>
            <?php endif; ?>
            
            <div class="auth-links">
                <a href="login.php">&larr; Back to Sign In</a>
            </div>
        </div>
    </div>
</body>
</html>
