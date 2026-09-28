<?php
$files = ['pastor_dashboard.php', 'admin_dashboard.php'];
foreach ($files as $f) {
    $content = file_get_contents($f);
    
    $old = <<<'EOT'
    // Leader photo row
    w.document.write('<div style="display:flex;align-items:center;justify-content:center;gap:18px;margin-bottom:10px;">');
    w.document.write('<img src="' + leaderPic + '" alt="Leader" style="width:70px;height:70px;border-radius:50%;object-fit:cover;border:3px solid #1e3a8a;">');
    w.document.write('<div><div style="font-size:0.75rem;color:#555;text-transform:uppercase;">' + leaderRole + '</div>');
    w.document.write('<div style="font-size:1.2rem;font-weight:700;color:#1e3a8a;">' + leaderName + '</div></div></div>');
EOT;
    $old = str_replace("\r", "", $old);
    
    $new = <<<'EOT'
    // Leader photo row
    w.document.write('<div style="display:flex;align-items:center;justify-content:center;gap:40px;margin-bottom:10px;">');
    
    // Main Leader
    w.document.write('<div style="display:flex;align-items:center;gap:15px;">');
    w.document.write('<img src="' + leaderPic + '" alt="Leader" style="width:70px;height:70px;border-radius:50%;object-fit:cover;border:3px solid #1e3a8a;">');
    w.document.write('<div><div style="font-size:0.75rem;color:#555;text-transform:uppercase;">' + leaderRole + '</div>');
    w.document.write('<div style="font-size:1.2rem;font-weight:700;color:#1e3a8a;">' + leaderName + '</div></div></div>');
    
    // Vice Leader (if present and not N/A)
    if (viceName && viceName !== 'N/A') {
        w.document.write('<div style="display:flex;align-items:center;gap:15px;">');
        w.document.write('<img src="' + vicePic + '" alt="Vice Leader" style="width:70px;height:70px;border-radius:50%;object-fit:cover;border:3px solid #6366f1;">');
        w.document.write('<div><div style="font-size:0.75rem;color:#555;text-transform:uppercase;">' + viceRole + '</div>');
        w.document.write('<div style="font-size:1.2rem;font-weight:700;color:#1e3a8a;">' + viceName + '</div></div></div>');
    }
    w.document.write('</div>');
EOT;
    
    $content = str_replace("\r", "", $content);
    if (strpos($content, $old) !== false) {
        $content = str_replace($old, $new, $content);
        file_put_contents($f, $content);
        echo "Replaced photo logic in $f\n";
    } else {
        echo "Could not find text in $f\n";
    }
}
?>
