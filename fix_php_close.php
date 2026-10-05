<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    $content = file_get_contents($file);
    $old = "                                 // (group ribbons only in printout)\r\n                             <tr>";
    $new = "                                 // (group ribbons only in printout)\r\n                             ?>\r\n                             <tr>";
    $content = str_replace($old, $new, $content);
    file_put_contents($file, $content);
    echo "Fixed $file\n";
}
?>
