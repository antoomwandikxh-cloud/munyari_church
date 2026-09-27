<?php
$file = 'C:\\xampp\\htdocs\\munyari_church\\member_dashboard.php';
$content = file_get_contents($file);

$pattern = '/<button onclick="window\.print\(\)".*?Print List\s*<\/button>/s';
$new = '<a href="print_village_members.php?village=<?= urlencode([\'church_village\']) ?>" target="_blank" class="btn-primary" style="background:#10b981; padding:8px 16px; font-size:0.85rem; text-decoration:none; display:inline-block;">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:6px; vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        Print List
                    </a>';

$content = preg_replace($pattern, $new, $content);
file_put_contents($file, $content);
echo "Success\n";
?>
