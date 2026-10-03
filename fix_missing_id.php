<?php
$file = 'pastor_dashboard.php';
$content = file_get_contents($file);
$content = str_replace(
    '<!-- NEW: Leaders Needed -->
                <div class="content-card" style="margin-bottom: 30px;">',
    '<!-- NEW: Leaders Needed -->
                <div class="content-card" style="margin-bottom: 30px;" id="requiredLeadersCard">',
    $content
);
file_put_contents($file, $content);
echo "Fixed ID in pastor_dashboard.php\n";
?>
