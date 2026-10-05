<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];

foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // Replace the button text
    $c = str_replace(">View in SS</a>", ">Manage Sunday School</a>", $c);
    
    file_put_contents($file, $c);
    echo "Replaced 'View in SS' with 'Manage Sunday School' in $file\n";
}
?>
