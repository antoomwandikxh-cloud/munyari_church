<?php
$file = 'pastor_dashboard.php';
$lines = file($file);

// Line 1934 (index 1933) = Address th, Line 1935 (index 1934) = Department th
// Insert Church Village after Address (after index 1933)
$insertAfter = 1933;
$newLine = '                                 <th style="padding:7px 8px;border:1px solid #aaa;">Church Village</th>' . "\r\n";

array_splice($lines, $insertAfter + 1, 0, [$newLine]);
file_put_contents($file, implode('', $lines));

echo "Done.\n";
echo "Line 1934: " . trim($lines[1933]) . "\n";
echo "Line 1935: " . trim($lines[1934]) . "\n";
echo "Line 1936: " . trim($lines[1935]) . "\n";
?>
