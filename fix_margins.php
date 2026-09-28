<?php
$files = [
    'print_all_leaders.php',
    'print_all_villages.php',
    'print_volunteers.php',
    'print_pastor_reports.php',
    'admin_dashboard.php',
    'pastor_dashboard.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);

    // Replace JS-injected @page margins inside document.write
    $content = preg_replace_callback('/(@page\s*\{.*?margin:)([^;}]+)(.*? \})/', function($m) {
        return $m[1] . '0' . $m[3];
    }, $content);

    // Ensure body has padding when printed if it doesn't already have it in the JS inline CSS
    // Wait, in JS, usually the body style is written just before or after.
    // It's safer to just inject a CSS rule for body { padding: 15mm; } alongside @page
    $content = preg_replace('/(@media print\s*\{.*?@page\s*\{.*?\}\s*)/', '$1 body{padding:15mm !important;} ', $content);

    file_put_contents($file, $content);
    echo "Processed $file\n";
}
?>
