<?php
// ─── SIDEBAR LINK ─────────────────────────────────────────────────────────────
$ss_sidebar_link = <<<'HTML'
                <a href="?tab=manage_sunday_school" class="sidebar-link <?= $tab == 'manage_sunday_school' ? 'active' : '' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    Manage Sunday School
                </a>
HTML;

foreach (['admin', 'pastor'] as $dash) {
    $file = "C:\\xampp\\htdocs\\munyari_church\\{$dash}_dashboard.php";
    $content = file_get_contents($file);

    // 2. Inject Sidebar Link - Check specifically for the link HTML, not just the word
    if (strpos($content, '>Manage Sunday School</a>') === false && strpos($content, 'Manage Sunday School') !== false) {
        // Wait, 'Manage Sunday School' exists in the handler comments, so just check for the precise link anchor
    }

    if (strpos($content, 'class="sidebar-link <?= $tab == \'manage_sunday_school\' ? \'active\' : \'\' ?>"') === false) {
        $usher_link_end = strpos($content, '</a>', strpos($content, '<a href="?tab=usher_monitoring"'));
        if ($usher_link_end !== false) {
            $content = substr_replace($content, "\n" . $ss_sidebar_link, $usher_link_end + 4, 0);
            echo "$dash: Sidebar injected.\n";
            file_put_contents($file, $content);
        }
    }
}
echo "Done!";
?>
