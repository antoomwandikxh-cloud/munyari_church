<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);

    // Enhance the on-screen ribbon in Manage Members tab to be bolder/more visible
    $old = "echo '<tr><td colspan=\"9\" style=\"background:'.\$gc.';color:white;padding:8px 15px;font-weight:700;font-size:0.82rem;text-align:center;\">'.htmlspecialchars(\$gl).'</td></tr>';";
    $new = "echo '<tr><td colspan=\"9\" style=\"background:'.\$gc.';color:white;padding:10px 18px;font-weight:800;font-size:0.88rem;text-align:center;letter-spacing:1px;text-transform:uppercase;border-top:3px solid rgba(255,255,255,0.3);\">&#9654; '.htmlspecialchars(\$gl).' &#9664;</td></tr>';";

    if (strpos($c, $old) !== false) {
        $c = str_replace($old, $new, $c);
        file_put_contents($file, $c);
        echo "Enhanced on-screen ribbon in $file\n";
    } else {
        echo "Pattern not found for ribbon in $file\n";
    }
}
?>
