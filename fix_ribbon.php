<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);
    $changed = false;

    // Fix 1: PRINTOUT ribbon - background= missing colon
    // 'style="padding:6px 10px;background=' . $gc . ';color:white;...'
    $old = "style=\"padding:6px 10px;background=' . \$gc . ';color:white;font-weight:700;font-size:0.74rem;letter-spacing:0.5px;border:1px solid ' . \$gc . ';text-align:center;\"";
    $new = "style=\"padding:8px 15px;background:' . \$gc . ';color:white;font-weight:700;font-size:0.82rem;letter-spacing:0.5px;border:1px solid ' . \$gc . ';text-align:center;\"";
    if (strpos($c, $old) !== false) {
        $c = str_replace($old, $new, $c);
        $changed = true;
        echo "Fixed printout ribbon in $file\n";
    }

    // Fix 2: ON-SCREEN ribbon - verify it uses background: with colon  
    // 'style=\"background:'.$gc.';color:white;padding:8px 15px;font-weight:700;font-size:0.82rem;text-align:center;\"'
    // This should already be correct, but let us verify and ensure it's visible
    if (strpos($c, "background:'.\$gc.';color:white;padding:8px 15px") !== false) {
        echo "On-screen ribbon OK in $file\n";
    }

    if ($changed) {
        file_put_contents($file, $c);
    }
}
?>
