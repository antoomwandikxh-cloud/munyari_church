<?php
$files = ['pastor_dashboard.php', 'admin_dashboard.php'];
foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $c = file_get_contents($file);
    
    // Fix emoji 
    $c = str_replace('ðŸ§¹', '🧹', $c);
    $c = str_replace('ðŸ½³', '🍳', $c); // Just in case
    
    // Fix em-dash
    $c = str_replace('â€”', '—', $c);
    $c = str_replace('â€“', '–', $c);
    
    // Fix box drawing characters
    $c = str_replace('â• â• â• ', '═══', $c);
    
    // Fix curly quotes if any
    $c = str_replace('â€˜', "‘", $c);
    $c = str_replace('â€™', "’", $c);
    $c = str_replace('â€œ', '“', $c);
    $c = str_replace('â€ ', '”', $c);
    
    // The user also mentioned "$ there is some funny characters"
    // Let's print out what we find near "Church Cleaners" just in case.
    
    file_put_contents($file, $c);
    echo "Fixed encoding in $file\n";
}
?>
