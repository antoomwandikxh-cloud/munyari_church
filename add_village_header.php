<?php
// pastor_dashboard.php - add missing Church Village header between Address and Department
$file = 'pastor_dashboard.php';
$content = file_get_contents($file);

$content = str_replace(
    '<th style="padding:7px 8px;border:1px solid #aaa;">Address</th>
                                <th style="padding:7px 8px;border:1px solid #aaa;">Department</th>',
    '<th style="padding:7px 8px;border:1px solid #aaa;">Address</th>
                                <th style="padding:7px 8px;border:1px solid #aaa;">Church Village</th>
                                <th style="padding:7px 8px;border:1px solid #aaa;">Department</th>',
    $content
);

file_put_contents($file, $content);
echo "Added Church Village header to $file\n";
echo "Village header matches: " . substr_count($content, 'Church Village') . "\n";

// admin_dashboard.php - rename "Village" -> "Church Village" in printout header for consistency
$file2 = 'admin_dashboard.php';
$content2 = file_get_contents($file2);

$content2 = str_replace(
    '<th style="padding:7px 8px;border:1px solid #aaa;text-align:left;">Village</th>',
    '<th style="padding:7px 8px;border:1px solid #aaa;text-align:left;">Church Village</th>',
    $content2
);

file_put_contents($file2, $content2);
echo "Updated Village -> Church Village in $file2\n";
?>
