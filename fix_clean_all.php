<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // Remove all inline definitions of seqUnlock so they use the one in script.js
    $c = preg_replace('/function seqUnlock\s*\(currentId,\s*nextId\)\s*\{[\s\S]*?(?:if \(\!filled && next\.tagName === \'INPUT\'\) next\.value = \'\';|if \(\!filled\) next\.value = \'\';)\s*\}/m', '/* seqUnlock now in script.js */', $c);
    
    // Ensure script.js is included before closing head
    if ($file === 'pastor_dashboard.php') {
        if (strpos($c, 'script.js') === false) {
            $c = preg_replace('/<\/head>/i', "    <script src=\"script.js\"></script>\n</head>", $c, 1);
        }
    }
    
    // In admin_dashboard.php there's a reference to activated-badge in the old seqUnlock we need to make sure is fully gone
    // We already restored so it shouldn't have the activated-badge code.
    
    // Update the Sunday School button in pastor_dashboard.php to be distinct
    if ($file === 'pastor_dashboard.php') {
        $c = str_replace('<button type="submit" class="btn-submit" style="margin-top:10px; display:flex; align-items:center; gap:8px; width:auto; padding:0 28px;">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                            Register Member
                        </button>', '<button type="submit" class="btn-submit" style="margin-top:10px; display:flex; align-items:center; gap:8px; width:auto; padding:0 28px;">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                            Register Sunday School Member
                        </button>', $c);
    }
    
    file_put_contents($file, $c);
    echo "Cleaned $file\n";
}
?>
