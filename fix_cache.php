<?php
$file = 'C:\\xampp\\htdocs\\munyari_church\\member_dashboard.php';
$content = file_get_contents($file);

$headers = "<?php
session_start();
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
require_once 'db_connect.php';
";

$content = preg_replace('/<\?php\s*session_start\(\);\s*require_once \'db_connect\.php\';/', $headers, $content);
file_put_contents($file, $content);
echo "Success\n";
?>
