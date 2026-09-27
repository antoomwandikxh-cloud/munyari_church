<?php
$files = [
    'admin_dashboard.php',
    'pastor_dashboard.php',
    'member_dashboard.php'
];

foreach ($files as $f) {
    $c = file_get_contents($f);
    
    // Fix regex patterns
    $c = str_replace('pattern="[A-Za-z0-9\s,.-]+"', 'pattern="[A-Za-z\s]+"', $c);
    $c = str_replace('pattern="[A-Za-z\s,.-]+"', 'pattern="[A-Za-z\s]+"', $c);
    
    file_put_contents($f, $c);
    echo "$f processed.\n";
}
?>
