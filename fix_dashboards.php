<?php
// Fix print_all_villages.php
$c = file_get_contents('print_all_villages.php');

// Remove everything from the page break down to the footer
$pattern = '/<!-- ═══ PAGE BREAK before Cleaners \+ Cookers ═══ -->.*?<div class="footer">/is';
if (preg_match($pattern, $c)) {
    $c = preg_replace($pattern, '<div class="footer">', $c);
    
    // Change the title
    $c = str_replace('Church Villages — Members, Cleaners &amp; Cookers', 'Church Villages — Members Only', $c);
    
    file_put_contents('print_all_villages.php', $c);
    echo "Fixed print_all_villages.php\n";
} else {
    echo "Could not find the page break pattern in print_all_villages.php\n";
}

// Add button to dashboards
$files = ['pastor_dashboard.php', 'admin_dashboard.php'];
foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $fc = file_get_contents($file);
    
    // Change top button text
    $fc = str_replace('Print All Villages, Cleaners &amp; Cookers', 'Print All Villages', $fc);
    
    // Find Section 2 and add button
    $cleaner_header = '<h2 style="font-size:1.1rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:16px; display:flex; align-items:center; gap:10px;">
                    <span style="font-size:1.3rem;">🧹</span> Church Cleaners — All Villages
                </h2>';
                
    $cleaner_header_new = '<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
                    <h2 style="font-size:1.1rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin:0; display:flex; align-items:center; gap:10px;">
                        <span style="font-size:1.3rem;">🧹</span> Church Cleaners — All Villages
                    </h2>
                    <a href="print_volunteers.php" target="_blank" style="display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#f59e0b,#ea580c);color:white;text-decoration:none;padding:10px 20px;border-radius:10px;font-size:0.9rem;font-weight:700;box-shadow:0 2px 8px rgba(245,158,11,0.3);">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Print Cleaners &amp; Cookers
                    </a>
                </div>';
                
    if (strpos($fc, $cleaner_header) !== false) {
        $fc = str_replace($cleaner_header, $cleaner_header_new, $fc);
        file_put_contents($file, $fc);
        echo "Updated $file\n";
    } else {
        echo "Could not find cleaner header in $file\n";
    }
}

?>
