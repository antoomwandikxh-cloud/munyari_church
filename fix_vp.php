<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // Pattern 1
    $p1 = '<div class="table-responsive" id="village_table_<?= str_replace(\' \', \'_\', $v) ?>">';
    $p1_full = $p1 . "\n                        <table>";
    $p1_full_win = $p1 . "\r\n                        <table>";
    
    $replacement1 = $p1 . "\n" .
        '                        <div class="print-leader-profile" style="display:none; text-align:center; margin-bottom:20px; background:#f8fafc; padding:15px; border-radius:10px; border:1px solid #e2e8f0;">' . "\n" .
        '                            <?php if (!empty($v_leaders[0])): ?>' . "\n" .
        '                                <img src="uploads/<?= htmlspecialchars($v_leaders[0][\'profile_picture\'] ?? \'default_avatar.png\') ?>" style="width:75px; height:75px; border-radius:50%; object-fit:cover; border:3px solid <?= $v_color ?>; margin-bottom:8px;">' . "\n" .
        '                                <div style="font-size:1.1rem; font-weight:800; color:#1e3a8a; text-transform:uppercase; margin-bottom:2px;"><?= htmlspecialchars($v_leaders[0][\'first_name\'] . \' \' . $v_leaders[0][\'last_name\']) ?></div>' . "\n" .
        '                                <div style="font-size:0.85rem; font-weight:600; color:<?= $v_color ?>; margin-bottom:4px;"><?= $v ?> Village Leader</div>' . "\n" .
        '                                <div style="font-size:0.75rem; color:#64748b; font-weight:600;"><?= htmlspecialchars($v_leaders[0][\'phone\']) ?></div>' . "\n" .
        '                            <?php else: ?>' . "\n" .
        '                                <img src="uploads/default_avatar.png" style="width:75px; height:75px; border-radius:50%; object-fit:cover; border:3px solid #cbd5e1; margin-bottom:8px; opacity:0.7; filter:grayscale(100%);">' . "\n" .
        '                                <div style="font-size:1.1rem; font-weight:800; color:#94a3b8; text-transform:uppercase; margin-bottom:2px;">(Not Assigned)</div>' . "\n" .
        '                                <div style="font-size:0.85rem; font-weight:600; color:#64748b; margin-bottom:4px;"><?= $v ?> Village Leader</div>' . "\n" .
        '                            <?php endif; ?>' . "\n" .
        '                        </div>' . "\n" .
        '                        <table>';

    $c = str_replace($p1_full, $replacement1, $c);
    $c = str_replace($p1_full_win, $replacement1, $c);

    // Pattern 2
    $p2 = "var tbodyHTML = container.querySelector('tbody') ? container.querySelector('tbody').outerHTML : '';";
    $replacement2 = $p2 . "\n        var leaderHTML = container.querySelector('.print-leader-profile') ? container.querySelector('.print-leader-profile').outerHTML.replace('display:none', 'display:block') : '';";
    
    $c = str_replace($p2, $replacement2, $c);

    // Pattern 3
    $p3 = "w.document.write('</div>');\n        w.document.write('<table>' + theadHTML + tbodyHTML + '</table>');";
    $p3_win = "w.document.write('</div>');\r\n        w.document.write('<table>' + theadHTML + tbodyHTML + '</table>');";
    $replacement3 = "w.document.write('</div>');\n        w.document.write(leaderHTML);\n        w.document.write('<table>' + theadHTML + tbodyHTML + '</table>');";

    $c = str_replace($p3, $replacement3, $c);
    $c = str_replace($p3_win, $replacement3, $c);

    file_put_contents($file, $c);
    echo "Fixed $file\n";
}
?>
