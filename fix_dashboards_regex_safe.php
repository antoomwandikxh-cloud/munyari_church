<?php
$files = ['pastor_dashboard.php', 'admin_dashboard.php'];
$new = '<!-- ═══ SECTION 2: Church Cleaners ═══ -->
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
                    <h2 style="font-size:1.1rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin:0; display:flex; align-items:center; gap:10px;">
                        <span style="font-size:1.3rem;">🧹</span> Church Cleaners &amp; Cookers
                    </h2>
                    <a href="print_volunteers.php" target="_blank" style="display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#f59e0b,#ea580c);color:white;text-decoration:none;padding:10px 20px;border-radius:10px;font-size:0.9rem;font-weight:700;box-shadow:0 2px 8px rgba(245,158,11,0.3);">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Print Cleaners &amp; Cookers
                    </a>
                </div>';
                
foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $c = file_get_contents($file);
    
    // Find everything from <!-- ═══ SECTION 2: Church Cleaners ═══ --> to </h2>
    $pattern = '/<!-- ═══ SECTION 2: Church Cleaners ═══ -->\s*<h2[^>]*>.*?Church Cleaners — All Villages\s*<\/h2>/is';
    
    if (preg_match($pattern, $c)) {
        $c = preg_replace($pattern, $new, $c);
        file_put_contents($file, $c);
        echo "Replaced in $file\n";
    } else {
        echo "Not found in $file\n";
    }
}
?>
