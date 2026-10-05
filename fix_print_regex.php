<?php
$file = 'print_all_villages.php';
$content = file_get_contents($file);

// Find and remove the <div class="lr"> line
$content = preg_replace('/<div class="lr">.*<\/div>\s*/i', '', $content);

// Center ribbon and replace "Participant" with "Member"
// Actually, let's just do a regex replace to be safe
$content = preg_replace('/<div class="sec-badge"(.*?)>(.*?)Participant(s?)</i', '<div style="text-align:center;"><div class="sec-badge"$1>$2Member$3<', $content);

file_put_contents($file, $content);
echo "Fixed print_all_villages.php using regex\n";
?>
