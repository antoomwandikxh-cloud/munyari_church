<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = '';
$step = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_user = $_POST['db_user'] ?? 'root';
    $db_pass = $_POST['db_pass'] ?? '';
    $db_name = $_POST['db_name'] ?? 'munyari_church';
    
    $conn = new mysqli($db_host, $db_user, $db_pass);
    
    if ($conn->connect_error) {
        $message = '<div class="alert error">Connection failed: ' . $conn->connect_error . '</div>';
    } else {
        $sql = "CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        if ($conn->query($sql) === TRUE) {
            $conn->select_db($db_name);
            
            $sql_file = __DIR__ . '/database.sql';
            if (file_exists($sql_file)) {
                $templine = '';
                $lines = file($sql_file);
                $error_count = 0;
                
                $conn->query("SET FOREIGN_KEY_CHECKS = 0");
                
                foreach ($lines as $line) {
                    if (substr($line, 0, 2) == '--' || $line == '') continue;
                    $templine .= $line;
                    if (substr(trim($line), -1, 1) == ';') {
                        if (!$conn->query($templine)) {
                            $error_count++;
                            error_log("Error performing query: " . $conn->error);
                        }
                        $templine = '';
                    }
                }
                
                $conn->query("SET FOREIGN_KEY_CHECKS = 1");

                // ── Seed default admin if table is empty ──────────────────
                $adminCheck = $conn->query("SELECT COUNT(*) as cnt FROM admins");
                $adminRow   = $adminCheck ? $adminCheck->fetch_assoc() : ['cnt' => 1];
                if ((int)$adminRow['cnt'] === 0) {
                    $default_hash = password_hash('admin123', PASSWORD_DEFAULT);
                    $conn->query("INSERT INTO admins (username, password, profile_picture)
                                  VALUES ('admin', '$default_hash', 'default_avatar.png')");
                }
                // ──────────────────────────────────────────────────────────

                if ($error_count == 0) {
                    $message = '<div class="alert success"><strong>✅ Installation Complete!</strong><br><br>
                    Database and all tables were created successfully.<br><br>
                    <b>Default Admin Account:</b><br>
                    👤 Username: <code>admin</code><br>
                    🔑 Password: <code>admin123</code><br><br>
                    <span style="color:#166534;">Please log in and change the password immediately after first login.</span></div>';
                    $step = 2;
                } else {
                    $message = '<div class="alert warning">Database created, but ' . $error_count . ' errors occurred during table import. It might still work, but check error logs.</div>';

                    $step = 2;
                }
            } else {
                $message = '<div class="alert warning">Database created, but <b>database.sql</b> file was not found. Please ensure it is in the same folder as install.php.</div>';
            }
        } else {
            $message = '<div class="alert error">Error creating database: ' . $conn->error . '</div>';
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Installer - Munyari Church</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f3f4f6; color: #1f2937; margin: 0; padding: 40px 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); max-width: 500px; width: 100%; }
        h1 { margin-top: 0; color: #111827; font-size: 1.5rem; text-align: center; }
        p { color: #6b7280; font-size: 0.95rem; line-height: 1.5; text-align: center; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; }
        input { width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; box-sizing: border-box; font-size: 1rem; transition: all 0.3s; }
        input:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .btn { width: 100%; background: #2563eb; color: white; border: none; padding: 14px; font-size: 1rem; font-weight: 600; border-radius: 8px; cursor: pointer; transition: background 0.3s; }
        .btn:hover { background: #1d4ed8; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.95rem; line-height: 1.5; }
        .success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .warning { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
        .btn-success { background: #10b981; margin-top: 20px; text-decoration: none; display: block; text-align: center; }
        .btn-success:hover { background: #059669; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 System Installer</h1>
        
        <?php echo $message; ?>
        
        <?php if ($step === 1): ?>
            <p>This will automatically create the <b>munyari_church</b> database and import all tables and data into your XAMPP localhost environment.</p>
            
            <form method="POST">
                <div class="form-group">
                    <label>Database Host</label>
                    <input type="text" name="db_host" value="localhost" required>
                </div>
                <div class="form-group">
                    <label>Database Username</label>
                    <input type="text" name="db_user" value="root" required>
                </div>
                <div class="form-group">
                    <label>Database Password</label>
                    <input type="password" name="db_pass" placeholder="Leave blank for default XAMPP">
                </div>
                <div class="form-group">
                    <label>Database Name</label>
                    <input type="text" name="db_name" value="munyari_church" required>
                </div>
                <button type="submit" class="btn">Install Database</button>
            </form>
        <?php else: ?>
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:22px;margin-bottom:20px;text-align:left;">
                <p style="margin:0 0 14px;font-weight:700;font-size:1rem;color:#166534;">🎉 Installation Complete!</p>
                <p style="margin:0 0 14px;font-size:0.9rem;color:#4b5563;">Use the credentials below to log in as Admin. The admin must then approve the Pastor account before members can be activated.</p>
                <table style="width:100%;border-collapse:collapse;font-size:0.92rem;">
                    <tr>
                        <td style="padding:8px 12px;background:#dcfce7;border-radius:6px 6px 0 0;font-weight:600;color:#15803d;border-bottom:1px solid #bbf7d0;">👤 Username</td>
                        <td style="padding:8px 12px;background:#dcfce7;border-radius:6px 6px 0 0;border-bottom:1px solid #bbf7d0;"><code style="font-size:1rem;font-weight:700;letter-spacing:1px;">admin</code></td>
                    </tr>
                    <tr>
                        <td style="padding:8px 12px;background:#f0fdf4;border-radius:0 0 6px 6px;font-weight:600;color:#15803d;">🔑 Password</td>
                        <td style="padding:8px 12px;background:#f0fdf4;border-radius:0 0 6px 6px;"><code style="font-size:1rem;font-weight:700;letter-spacing:1px;">admin123</code></td>
                    </tr>
                </table>
                <p style="margin:14px 0 0;font-size:0.82rem;color:#dc2626;font-weight:500;">⚠️ Please change the password immediately after logging in for the first time.</p>
            </div>
            <a href="login.php" class="btn" style="background:#10b981;">Go to Login &rarr;</a>
            <a href="index.php" class="btn" style="margin-top:10px;background:#4b5563;">Go to Homepage</a>
        <?php endif; ?>
    </div>
</body>
</html>