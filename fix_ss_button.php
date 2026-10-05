<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // The Sunday School registration form button has exactly this HTML structure
    $btn_old = '<button type="submit" class="btn-submit" style="margin-top:10px; display:flex; align-items:center; gap:8px; width:auto; padding:0 28px;">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                            Register Member
                        </button>';
                        
    $btn_new = '<button type="submit" class="btn-submit" style="margin-top:10px; display:flex; align-items:center; gap:8px; width:auto; padding:0 28px;">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                            Register Sunday School Member
                        </button>';
                        
    // Let's use preg_replace to be safe with whitespace
    // Specifically looking for the Sunday School section, which is around line 1800 in pastor and 5000 in admin
    // I'll just change the text of the button that is right after id="ss_password"
    
    $c = preg_replace(
        '/(id="ss_confirm_password"[\s\S]*?)Register Member(\s*<\/button>)/m',
        '$1Register Sunday School Member$2',
        $c
    );
    
    file_put_contents($file, $c);
    echo "Fixed Sunday School button text in $file\n";
}
?>
