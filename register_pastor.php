<?php
session_start();
require_once 'db_connect.php';
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fn = $conn->real_escape_string($_POST['first_name']);
    $ln = $conn->real_escape_string($_POST['last_name']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $role = $conn->real_escape_string($_POST['role']);
    $password = $_POST['password'];
    
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    
    $conn->query("INSERT INTO pastors (first_name, last_name, phone, role, password) VALUES ('$fn', '$ln', '$phone', '$role', '$hashed')");
    $message = "<div class='alert alert-success'>Pastor registered successfully!</div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Pastor - Munyari Church</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card" style="max-width: 500px;">
            <div style="margin-bottom: 20px;">
                <a href="admin_dashboard.php" style="color: var(--primary); text-decoration: none; font-weight: 500;">&larr; Back to Dashboard</a>
            </div>
            <div class="auth-header">
                <h2>Register New Pastor</h2>
                <p>Add a pastor to the system</p>
            </div>
            
            <?= $message ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" class="form-control" placeholder="e.g. James" required>
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" class="form-control" placeholder="e.g. Munyari" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="+1234567890" required>
                </div>
                <div class="form-group">
                    <label>Area of Resident</label>
                    <input type="text" name="role" class="form-control" placeholder="e.g. Downtown" required>
                </div>
                <div class="form-group">
                    <label>Login Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Create a password for the pastor" required>
                </div>
                <button type="submit" class="btn-submit">Register Pastor</button>
            </form>
        </div>
    </div>
</body>
</html>
