<?php
session_start();
require_once 'db_connect.php';
if (isset($_SESSION['admin_id'])) { header("Location: admin_dashboard.php"); exit(); }
if (isset($_SESSION['pastor_id'])) { header("Location: pastor_dashboard.php"); exit(); }
if (isset($_SESSION['member_id'])) { header("Location: member_dashboard.php"); exit(); }

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $ip_address = $_SERVER['REMOTE_ADDR'];

    // 1. Check Admins
    $result_admin = $conn->query("SELECT * FROM admins WHERE username = '$username'");
    if ($result_admin->num_rows > 0) {
        $admin = $result_admin->fetch_assoc();
        if (password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $user_name = $conn->real_escape_string($admin['username']);
            $conn->query("INSERT INTO audit_logs (user_id, user_type, user_name, action, details, ip_address) VALUES ({$admin['id']}, 'Admin', '$user_name', 'Login', 'Admin logged into the system.', '$ip_address')");
            header("Location: admin_dashboard.php");
            exit();
        }
    }

    // 2. Check Pastors
    $result_pastor = $conn->query("SELECT * FROM pastors WHERE first_name = '$username'");
    if ($result_pastor->num_rows > 0) {
        while ($pastor = $result_pastor->fetch_assoc()) {
            if (password_verify($password, $pastor['password'])) {
                if (!$pastor['is_approved']) {
                    $error = "Your pastor account is pending admin approval. Please check back later.";
                } else {
                    $_SESSION['pastor_id'] = $pastor['id'];
                    $_SESSION['pastor_name'] = $pastor['first_name'];
                    $user_name = $conn->real_escape_string($pastor['first_name'] . ' ' . $pastor['last_name']);
                    $conn->query("INSERT INTO audit_logs (user_id, user_type, user_name, action, details, ip_address) VALUES ({$pastor['id']}, 'Pastor', '$user_name', 'Login', 'Pastor logged into the system.', '$ip_address')");
                    header("Location: pastor_dashboard.php");
                    exit();
                }
            }
        }
    }

    // 3. Member
    $result_member = $conn->query("SELECT * FROM members WHERE first_name = '$username'");
    if ($result_member->num_rows > 0) {
        while ($member = $result_member->fetch_assoc()) {
            if (password_verify($password, $member['password'])) {
                if ($member['is_approved'] == 0) {
                    $error = "Your account is pending pastor approval. Please check back later.";
                } elseif ($member['is_approved'] == -1) {
                    $error = "Your account has been deactivated. Please contact your pastor.";
                } else {
                    $_SESSION['member_id'] = $member['id'];
                    $_SESSION['member_name'] = $member['first_name'];
                    $user_name = $conn->real_escape_string($member['first_name'] . ' ' . $member['last_name']);
                    $conn->query("INSERT INTO audit_logs (user_id, user_type, user_name, action, details, ip_address) VALUES ({$member['id']}, 'Member', '$user_name', 'Login', 'Member logged into the system.', '$ip_address')");
                    header("Location: member_dashboard.php");
                    exit();
                }
            }
        }
    }

    if (empty($error)) {
        $error = "Invalid credentials. Please check your name and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Munyari Church</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Splash Screen -->
    <script>
        if (sessionStorage.getItem('splashShown')) {
            document.write('<style>#splashScreen { display: none !important; }</style>');
        }
    </script>
    <div class="splash-screen" id="splashScreen">
        <div class="splash-content">
            <img src="church_logo.jpg" alt="Munyari E.A.P.C Logo">
            <h1>Welcome to Munyari E.A.P.C Church</h1>
        </div>
    </div>
    <script>
        if (!sessionStorage.getItem('splashShown')) {
            sessionStorage.setItem('splashShown', 'true');
            setTimeout(() => {
                const splash = document.getElementById('splashScreen');
                if(splash) splash.remove();
            }, 3000);
        } else {
            const splash = document.getElementById('splashScreen');
            if(splash) splash.remove();
        }
    </script>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <img src="church_logo.jpg" alt="Munyari E.A.P.C Church" style="width: 180px; height: auto; margin: 0 auto 20px; display: block; border-radius: 12px;">
                <h2>Welcome Back</h2>
                <p>SIGN IN TO YOUR E.A.P.C MUNYARI CHURCH ACCOUNT</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>
            
            <form action="login.php" method="POST">
                <div class="form-group">
                    <label>First Name (or Username)</label>
                    <input type="text" name="username" class="form-control" placeholder="Enter your name" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <div style="position: relative;">
                        <input type="password" id="login_password" name="password" class="form-control" placeholder="Enter your password" required style="padding-right: 40px;">
                        <button type="button" onclick="togglePassword('login_password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-muted);">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-submit">Sign In</button>
            </form>
            
            <div class="auth-links">
                <p><a href="forgot_password.php">Forgot Password?</a></p>
                <p style="margin-top: 15px; color: var(--text-muted);">Don't have an account? <a href="index.php">Register here</a></p>
            </div>
        </div>
    </div>
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
            } else {
                input.type = 'password';
            }
        }
    </script>
</body>
</html>
