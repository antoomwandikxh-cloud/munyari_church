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

    // Completely nuke any margin inside @page
    $content = preg_replace('/(@page\s*\{[^}]*?)margin:\s*[^;\}]+;?/', '$1margin: 0;', $content);

    // If we have @page { margin: 0; } but no padding on body, add it to @media print
    // Or just append body { padding: 15mm; } to the end of the <style> block or string
    // Let's do it safely.

    file_put_contents($file, $content);
    echo "Processed $file\n";
}
?>
