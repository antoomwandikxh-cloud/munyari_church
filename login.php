<?php
session_start();
require_once 'db_connect.php';
if (isset($_SESSION['admin_id'])) { header("Location: admin_dashboard.php"); exit(); }
if (isset($_SESSION['pastor_id'])) { header("Location: pastor_dashboard.php"); exit(); }
if (isset($_SESSION['member_id'])) { header("Location: member_dashboard.php"); exit(); }

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_in = trim($_POST['username']);
    $password    = $_POST['password'];
    $username    = $conn->real_escape_string($username_in);

    $ip_address = $_SERVER['REMOTE_ADDR'];

    // 1. Check Admins
    $result_admin = $conn->query("SELECT * FROM admins WHERE BINARY username = '$username'");
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

    // 2. Check Pastors — by username (if set) or first_name
    $result_pastor = $conn->query("SELECT * FROM pastors WHERE BINARY username = '$username' OR (username IS NULL AND BINARY first_name = '$username')");
    if ($result_pastor->num_rows > 0) {
        while ($pastor = $result_pastor->fetch_assoc()) {
            if (password_verify($password, $pastor['password'])) {
                if (!$pastor['is_approved']) {
                    $error = "Your pastor account is pending admin approval. Please check back later.";
                    break;
                } else {
                    $_SESSION['pastor_id']   = $pastor['id'];
                    $_SESSION['pastor_name'] = $pastor['first_name'];
                    $user_name = $conn->real_escape_string($pastor['first_name'] . ' ' . $pastor['last_name']);
                    $conn->query("INSERT INTO audit_logs (user_id, user_type, user_name, action, details, ip_address) VALUES ({$pastor['id']}, 'Pastor', '$user_name', 'Login', 'Pastor logged into the system.', '$ip_address')");
                    header("Location: pastor_dashboard.php");
                    exit();
                }
            }
        }
    }

    // 3. Check Members — by username (if set) or first_name fallback
    $result_member = $conn->query("SELECT * FROM members WHERE BINARY username = '$username' OR (username IS NULL AND BINARY first_name = '$username')");
    if ($result_member->num_rows > 0) {
        while ($member = $result_member->fetch_assoc()) {
            if (password_verify($password, $member['password'])) {
                if ($member['is_approved'] == 0) {
                    $error = "Your account is pending pastor approval. Please check back later.";
                } elseif ($member['is_approved'] == -2) {
                    $attempts = (int)($member['decline_attempts'] ?? 0);
                    if ($attempts >= 2) {
                        $conn->query("DELETE FROM members WHERE id = " . $member['id']);
                        $error = "Your declined account has been removed from the system. Please re-register with valid details.";
                    } else {
                        $conn->query("UPDATE members SET decline_attempts = decline_attempts + 1 WHERE id = " . $member['id']);
                        $reason = $member['decline_reason'] ?? 'No reason provided.';
                        $error = "__DECLINED__|" . htmlspecialchars($member['first_name'], ENT_QUOTES) . "|" . htmlspecialchars($reason, ENT_QUOTES);
                    }
                } elseif ($member['is_approved'] == -1) {
                    $error = "Your account has been deactivated. Please contact your pastor.";
                } else {
                    $_SESSION['member_id']   = $member['id'];
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
        $error = "Invalid credentials. Please check your username and password.";
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
                <p>LOGIN TO YOUR E.A.P.C MUNYARI CHURCH ACCOUNT</p>
            </div>
            <?php if(isset($_GET['success'])): ?>
                <?php if($_GET['success'] == 'member'): ?>
                    <div class="alert alert-success">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Registration successful! Please wait for pastor approval before logging in.</span>
                    </div>
                <?php elseif($_GET['success'] == 'pastor'): ?>
                    <div class="alert alert-success">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Registration successful! Please wait for admin approval before logging in.</span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if($error): ?>
                <?php
                $is_declined = str_starts_with($error, '__DECLINED__|');
                if ($is_declined) {
                    $parts = explode('|', $error, 3);
                    $dec_name   = $parts[1] ?? 'Member';
                    $dec_reason = $parts[2] ?? 'No reason provided.';
                }
                ?>
                <?php if (!$is_declined): ?>
                <div class="alert alert-error">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span><?= $error ?></span>
                </div>
                <?php else: ?>
                <!-- Decline Reason Popup -->
                <div id="declinePopup" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:9999;display:flex;align-items:center;justify-content:center;">
                  <div style="background:white;border-radius:14px;padding:30px 32px;max-width:440px;width:90%;box-shadow:0 12px 40px rgba(0,0,0,0.25);text-align:center;position:relative;">
                    <div style="width:60px;height:60px;background:#fef2f2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                      <svg width="30" height="30" fill="none" stroke="#dc2626" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h2 style="margin:0 0 6px;color:#dc2626;font-size:1.2rem;">Registration Declined</h2>
                    <p style="margin:0 0 16px;color:#6b7280;font-size:0.9rem;">Hello <strong><?= htmlspecialchars($dec_name) ?></strong>, the pastor has reviewed your registration.</p>
                    <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:14px 16px;margin-bottom:20px;text-align:left;">
                      <div style="font-size:0.78rem;font-weight:700;text-transform:uppercase;color:#dc2626;letter-spacing:0.5px;margin-bottom:6px;">Reason</div>
                      <div style="font-size:0.92rem;color:#374151;line-height:1.5;"><?= htmlspecialchars($dec_reason) ?></div>
                    </div>
                    <p style="font-size:0.82rem;color:#9ca3af;margin-bottom:20px;">Please contact your pastor for more information or re-register with the correct details.</p>
                    <button onclick="window.location.href='register_page.php'" style="background:#dc2626;color:white;border:none;padding:10px 28px;border-radius:8px;cursor:pointer;font-size:0.95rem;font-weight:600;">OK, I understand</button>
                  </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <form action="login.php" method="POST" autocomplete="off">
                <div class="form-group">
                    <label>First Name (or Username)</label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #10b981; pointer-events: none;"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg></span>
                        <input type="text" name="username" class="form-control" placeholder="Enter your name" required autocomplete="new-username" spellcheck="false" style="padding-left: 40px;">
                    </div>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #ef4444; pointer-events: none;"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg></span>
                        <input type="password" id="login_password" name="password" class="form-control" placeholder="Enter your password" required autocomplete="new-password" style="padding-left: 40px; padding-right: 40px;">
                        <button type="button" onclick="togglePassword('login_password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-muted);">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-submit" style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                    Login
                </button>
            </form>
            
            <div class="auth-links">
                <p><a href="forgot_password.php">Forgot Password?</a></p>
                <p style="margin-top: 15px; color: var(--text-muted);">Don't have an account? <a href="register_page.php">Register here</a></p>
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
