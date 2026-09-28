<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];

$old = <<<'EOT'
                        <button onclick="printAllLeaders('portrait')" style="background:#1e3a8a;color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:0.82rem;font-weight:600;display:flex;align-items:center;gap:6px;">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            Print Leaders (Portrait)
                        </button>
                        <button onclick="printAllLeaders('landscape')" style="background:#6366f1;color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:0.82rem;font-weight:600;display:flex;align-items:center;gap:6px;">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            Print Leaders (Landscape)
                        </button>
EOT;

$new = <<<'EOT'
                        <a href="print_all_leaders.php?orientation=portrait" target="_blank" style="background:#1e3a8a;color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:0.82rem;font-weight:600;display:flex;align-items:center;gap:6px;text-decoration:none;">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            Print Leaders (Portrait)
                        </a>
                        <a href="print_all_leaders.php?orientation=landscape" target="_blank" style="background:#6366f1;color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:0.82rem;font-weight:600;display:flex;align-items:center;gap:6px;text-decoration:none;">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            Print Leaders (Landscape)
                        </a>
EOT;

foreach ($files as $f) {
    $content = file_get_contents($f);
    $content = str_replace("\r", "", $content);
    $old_n = str_replace("\r", "", $old);
    if (strpos($content, $old_n) !== false) {
        $content = str_replace($old_n, $new, $content);
        file_put_contents($f, $content);
        echo "Updated buttons in $f\n";
    } else {
        echo "Could not find buttons in $f\n";
    }
}
?>
