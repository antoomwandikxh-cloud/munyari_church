<?php
$files = [
    'print_all_leaders.php',
    'print_all_villages.php',
    'print_volunteers.php',
    'print_pastor_reports.php',
    'print_village_members.php',
    'print_financials.php',
    'print_dept_financials.php',
    'admin_dashboard.php',
    'pastor_dashboard.php'
];
foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $content = str_replace('@page { margin: 0;', '@page { margin: 0mm !important;', $content);
    $content = str_replace('@page{size:A4 landscape;margin: 0;}', '@page{size:A4 landscape;margin: 0mm !important;}', $content);
    file_put_contents($file, $content);
}
?>
