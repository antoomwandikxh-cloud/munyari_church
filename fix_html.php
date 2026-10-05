<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // First, remove leaderHTML from its current position
    $old_bot = "w.document.write('</div>');\n        w.document.write(leaderHTML);\n        w.document.write('<table>' + theadHTML + tbodyHTML + '</table>');";
    $old_bot_win = "w.document.write('</div>');\r\n        w.document.write(leaderHTML);\r\n        w.document.write('<table>' + theadHTML + tbodyHTML + '</table>');";
    
    $new_bot = "w.document.write('</div>');\n        w.document.write('<table>' + theadHTML + tbodyHTML + '</table>');";
    $c = str_replace($old_bot, $new_bot, $c);
    $c = str_replace($old_bot_win, $new_bot, $c);

    // Second, add leaderHTML before the header
    $old_top = "w.document.write('<div class=\"watermark\">E.A.P.C MUNYARI CHURCH</div>');\n        w.document.write('<div style=\"display:flex;align-items:center;justify-content:space-between;border-bottom:2px solid #1e3a8a;padding-bottom:10px;margin-bottom:18px;gap:10px;\">');";
    $old_top_win = "w.document.write('<div class=\"watermark\">E.A.P.C MUNYARI CHURCH</div>');\r\n        w.document.write('<div style=\"display:flex;align-items:center;justify-content:space-between;border-bottom:2px solid #1e3a8a;padding-bottom:10px;margin-bottom:18px;gap:10px;\">');";
    
    $new_top = "w.document.write('<div class=\"watermark\">E.A.P.C MUNYARI CHURCH</div>');\n        w.document.write(leaderHTML);\n        w.document.write('<div style=\"display:flex;align-items:center;justify-content:space-between;border-bottom:2px solid #1e3a8a;padding-bottom:10px;margin-bottom:18px;gap:10px;\">');";
    
    $c = str_replace($old_top, $new_top, $c);
    $c = str_replace($old_top_win, $new_top, $c);

    file_put_contents($file, $c);
    echo "Moved leaderHTML in $file\n";
}
?>
