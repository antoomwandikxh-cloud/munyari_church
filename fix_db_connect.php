<?php
$c = file_get_contents('db_connect.php');

$new_error_handler = <<<'PHP'
if ($conn->connect_error) {
    // Determine the type of error
    $error_msg = $conn->connect_error;
    $error_code = $conn->connect_errno;
    
    // If it's an AJAX request (like the chat or async form submission), return JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        http_response_code(503);
        die(json_encode(['error' => 'Database connection failed: ' . $error_msg]));
    }
    
    // Otherwise, show a beautiful, helpful HTML error page
    $title = "Database Connection Error";
    $subtitle = "The system cannot connect to the database.";
    $action_html = "";
    
    if ($error_code == 1049) {
        $title = "Database Not Found";
        $subtitle = "The database '<b>$dbname</b>' does not exist on this server.";
        $action_html = "<p style='margin-top:20px; font-size:1rem; color:#4b5563;'>It looks like you just moved the system to a new computer or host.</p>
                        <a href='install.php' style='display:inline-block; margin-top:15px; background:#2563eb; color:white; padding:12px 24px; text-decoration:none; border-radius:8px; font-weight:600;'>Run the Installer Setup</a>";
    } elseif ($error_code == 2002 || $error_code == 1045) {
        $title = "Database Server Offline or Access Denied";
        $subtitle = "Could not connect to the database server using the provided username and password.";
        $action_html = "<p style='margin-top:20px; font-size:1rem; color:#4b5563;'>Please ensure XAMPP (MySQL) is running, or check your <b>db_config.php</b> credentials if you are hosting online.</p>";
    }
    
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>System Error</title>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f3f4f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
            .error-box { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; max-width: 500px; width: 100%; border-top: 5px solid #ef4444; }
            h1 { margin-top: 0; color: #111827; font-size: 1.5rem; }
            p.err-sub { color: #dc2626; font-weight: 500; font-size: 1.1rem; }
            .tech-details { margin-top: 25px; padding: 15px; background: #fef2f2; border: 1px dashed #fca5a5; border-radius: 8px; color: #991b1b; font-family: monospace; text-align: left; font-size: 0.9rem; word-break: break-all; }
        </style>
    </head>
    <body>
        <div class='error-box'>
            <svg style='width:64px; height:64px; color:#ef4444; margin-bottom:15px;' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'/></svg>
            <h1>$title</h1>
            <p class='err-sub'>$subtitle</p>
            $action_html
            <div class='tech-details'>
                <b>Technical Details:</b><br>
                Error Code: $error_code<br>
                Message: $error_msg
            </div>
        </div>
    </body>
    </html>";
    die();
}
PHP;

$c = preg_replace('/if \(\$conn->connect_error\) \{[\s\S]*?die\(json_encode\(\[\'error\' => \'Service temporarily unavailable\. Please try again later\.\'\]\)\);\n\}/m', $new_error_handler, $c);

file_put_contents('db_connect.php', $c);
echo "Updated db_connect.php\n";
?>
