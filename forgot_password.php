<?php
session_start();
require_once 'db_connect.php';

$message = "";
$step = 1;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // ── STEP 1: Verify identity ───────────────────────────────────────────────
    if (isset($_POST['verify'])) {
        $username_in = trim($_POST['username']);
        $secret_in   = trim($_POST['secret']);   // phone OR admin recovery code
        $username    = $conn->real_escape_string($username_in);
        $secret      = $conn->real_escape_string($secret_in);

        $found = false;

        // 1. Admin: username = "admin" + recovery code = "admin123munyari"
        if (strtolower($username_in) === 'admin') {
            if ($secret_in === 'admin123munyari') {
                $result = $conn->query("SELECT id FROM admins WHERE username = 'admin'");
                if ($result && $result->num_rows > 0) {
                    $found = true;
                    $_SESSION['reset_role'] = 'admin';
                    $_SESSION['reset_user'] = 'admin';
                }
            }
            if (!$found) {
                $message = "<div class='alert alert-error'>Invalid recovery code. Only the admin knows this code.</div>";
            }

        // 2. Pastor: username field + phone number
        } else {
            // Check Pastor by username (if they've set one) or first_name
            $pastor_res = $conn->query("SELECT id, first_name FROM pastors WHERE username = '$username' OR first_name = '$username'");
            if ($pastor_res && $pastor_res->num_rows > 0) {
                while ($pastor = $pastor_res->fetch_assoc()) {
                    $pid = $pastor['id'];
                    $phone_check = $conn->query("SELECT id FROM pastors WHERE id = $pid AND phone = '$secret'");
                    if ($phone_check && $phone_check->num_rows > 0) {
                        $found = true;
                        $_SESSION['reset_role'] = 'pastor';
                        $_SESSION['reset_user_id'] = $pid;
                        break;
                    }
                }
                if (!$found) {
                    $message = "<div class='alert alert-error'>Phone number does not match our records for that username.</div>";
                }
            } else {
                // Check Member by username (if set) or first_name
                $member_res = $conn->query("SELECT id FROM members WHERE (username = '$username' OR (username IS NULL AND first_name = '$username')) AND phone = '$secret'");
                if ($member_res && $member_res->num_rows > 0) {
                    $row = $member_res->fetch_assoc();
                    $found = true;
                    $_SESSION['reset_role'] = 'member';
                    $_SESSION['reset_user_id'] = $row['id'];
                } else {
                    $message = "<div class='alert alert-error'>Username or phone number not found. Please check your details.</div>";
                }
            }
        }

        if ($found) {
            $step = 2;
        }
    }

    // ── STEP 2: Save new password ─────────────────────────────────────────────
    elseif (isset($_POST['reset_password'])) {
        $new_pass = $_POST['new_password'];
        $confirm  = $_POST['confirm_password'];

        if (strlen($new_pass) < 6) {
            $message = "<div class='alert alert-error'>Password must be at least 6 characters.</div>";
            $step = 2;
        } elseif ($new_pass !== $confirm) {
            $message = "<div class='alert alert-error'>Passwords do not match!</div>";
            $step = 2;
        } else {
            $hashed  = password_hash($new_pass, PASSWORD_DEFAULT);
            $r_role  = $_SESSION['reset_role'];

            if ($r_role === 'admin') {
                $conn->query("UPDATE admins SET password = '$hashed' WHERE username = 'admin'");
            } elseif ($r_role === 'pastor') {
                $id = (int)$_SESSION['reset_user_id'];
                $conn->query("UPDATE pastors SET password = '$hashed' WHERE id = $id");
            } elseif ($r_role === 'member') {
                $id = (int)$_SESSION['reset_user_id'];
                $conn->query("UPDATE members SET password = '$hashed' WHERE id = $id");
            }

            session_destroy();
            echo "<!DOCTYPE html><html lang='en'><head><meta name='viewport' content='width=device-width, initial-scale=1.0'><link rel='stylesheet' href='style.css'></head><body><div class='auth-wrapper'><div class='auth-card'>";
            echo "<div class='auth-header'><img src='church_logo.jpg' alt='Munyari' style='width:120px;border-radius:12px;margin:0 auto 16px;display:block;'><h2>Password Reset!</h2></div>";
            echo "<div class='alert alert-success'>Your password has been changed successfully. You may now sign in.</div>";
            echo "<a href='login.php' class='btn-submit' style='margin-top:20px; display:block; text-align:center;'>Go to Sign In &rarr;</a>";
            echo "</div></div></body></html>";
            exit();
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
    <style>
        .step-info {
            background: rgba(99,102,241,0.08);
            border: 1px solid rgba(99,102,241,0.25);
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.6;
        }
        .step-info strong { color: var(--text-main); }
        .secret-hint { font-size: 0.8rem; color: var(--text-muted); margin-top: 4px; display: block; }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <img src="church_logo.jpg" alt="Munyari E.A.P.C Church" style="width: 120px; height: auto; margin: 0 auto 16px; display: block; border-radius: 12px;">
                <h2>Reset Password</h2>
                <p>Recover access to your account</p>
            </div>

            <?= $message ?>

            <?php if ($step == 1): ?>
            <div class="step-info">
                <strong>How it works:</strong><br>
                Enter your username and the phone number or secret code you registered with.
            </div>
            <form action="forgot_password.php" method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Your username or first name" required autocomplete="off">
                    <span class="secret-hint">Members: use the unique username you created in Settings. If you haven't set one yet, use your first name.</span>
                </div>
                <div class="form-group">
                    <label id="secretLabel">Phone Number</label>
                    <input type="text" name="secret" id="secretInput" class="form-control" placeholder="Your phone number" required autocomplete="off">
                    <span class="secret-hint" id="secretHint">Enter the phone number you registered with.</span>
                </div>
                <button type="submit" name="verify" class="btn-submit">Verify Identity &rarr;</button>
            </form>

            <?php elseif ($step == 2): ?>
            <div class="step-info">
                <strong>Identity verified!</strong> Enter your new password below.
            </div>
            <form action="forgot_password.php" method="POST">
                <div class="form-group">
                    <label>New Password</label>
                    <div style="position:relative;">
                        <input type="password" id="np" name="new_password" class="form-control" placeholder="At least 6 characters" minlength="6" required style="padding-right:40px;">
                        <button type="button" onclick="togglePass('np')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);">&#128065;</button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <div style="position:relative;">
                        <input type="password" id="cp" name="confirm_password" class="form-control" placeholder="Repeat new password" minlength="6" required style="padding-right:40px;">
                        <button type="button" onclick="togglePass('cp')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);">&#128065;</button>
                    </div>
                </div>
                <button type="submit" name="reset_password" class="btn-submit">Save New Password</button>
            </form>
            <?php endif; ?>

            <div class="auth-links" style="margin-top:20px;">
                <a href="login.php">&larr; Back to Sign In</a>
            </div>
        </div>
    </div>
    <script>

        function togglePass(id) {
            const el = document.getElementById(id);
            el.type = el.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>
