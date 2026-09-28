<?php
$files = [
    'print_village_members.php',
    'print_financials.php',
    'print_dept_financials.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);

    $content = preg_replace('/(@page\s*\{[^}]*?)margin:\s*[^;\}]+;?/', '$1margin: 0;', $content);
    // ensure body has padding in print
    if (strpos($content, '@media print { body { padding:') === false) {
        $content = str_replace('@media print {', '@media print { body { padding: 15mm !important; } ', $content);
    }
    
    file_put_contents($file, $content);
    echo "Processed $file\n";
}
?>
