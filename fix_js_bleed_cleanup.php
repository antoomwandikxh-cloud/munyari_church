<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);
    $c = str_replace("</body></body>\n</html>');", "</body></html>');", $c);
    file_put_contents($file, $c);
}
?>
