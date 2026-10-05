<?php
foreach (['admin_dashboard.php', 'pastor_dashboard.php'] as $file) {
    $content = file_get_contents($file);
    
    // 1. Add the .print-leader-profile inside the village_table_... div
    // We'll search for <div class="table-responsive" id="village_table_... ">
    // The actual text is: <div class="table-responsive" id="village_table_<?= str_replace(' ', '_', $v) ?>">
    $old1 = "<div class=\"table-responsive\" id=\"village_table_<?= str_replace(' ', '_', \$v) ?>\">\r\n                        <table>";
    $new1 = "<div class=\"table-responsive\" id=\"village_table_<?= str_replace(' ', '_', \$v) ?>\">\r\n" .
            "                        <div class=\"print-leader-profile\" style=\"display:none; text-align:center; margin-bottom:20px;\">\r\n" .
            "                            <?php if (!empty(\$v_leaders[0])): ?>\r\n" .
            "                                <img src=\"uploads/<?= htmlspecialchars(\$v_leaders[0]['profile_picture'] ?? 'default_avatar.png') ?>\" style=\"width:75px; height:75px; border-radius:50%; object-fit:cover; border:3px solid <?= \$v_color ?>; margin-bottom:8px;\">\r\n" .
            "                                <div style=\"font-size:1.1rem; font-weight:800; color:#1e3a8a; text-transform:uppercase; margin-bottom:2px;\"><?= htmlspecialchars(\$v_leaders[0]['first_name'] . ' ' . \$v_leaders[0]['last_name']) ?></div>\r\n" .
            "                                <div style=\"font-size:0.85rem; font-weight:600; color:<?= \$v_color ?>; margin-bottom:4px;\"><?= \$v ?> Village Leader</div>\r\n" .
            "                                <div style=\"font-size:0.75rem; color:#64748b; font-weight:600;\"><?= htmlspecialchars(\$v_leaders[0]['phone']) ?></div>\r\n" .
            "                            <?php else: ?>\r\n" .
            "                                <img src=\"uploads/default_avatar.png\" style=\"width:75px; height:75px; border-radius:50%; object-fit:cover; border:3px solid #cbd5e1; margin-bottom:8px; opacity:0.7; filter:grayscale(100%);\">\r\n" .
            "                                <div style=\"font-size:1.1rem; font-weight:800; color:#94a3b8; text-transform:uppercase; margin-bottom:2px;\">(Not Assigned)</div>\r\n" .
            "                                <div style=\"font-size:0.85rem; font-weight:600; color:#64748b; margin-bottom:4px;\"><?= \$v ?> Village Leader</div>\r\n" .
            "                            <?php endif; ?>\r\n" .
            "                        </div>\r\n" .
            "                        <table>";

    // 2. Modify printVillageTable JS function
    $old2 = "var theadHTML = container.querySelector('thead') ? container.querySelector('thead').outerHTML : '';\r\n        var tbodyHTML = container.querySelector('tbody') ? container.querySelector('tbody').outerHTML : '';";
    $new2 = "var theadHTML = container.querySelector('thead') ? container.querySelector('thead').outerHTML : '';\r\n        var tbodyHTML = container.querySelector('tbody') ? container.querySelector('tbody').outerHTML : '';\r\n        var leaderHTML = container.querySelector('.print-leader-profile') ? container.querySelector('.print-leader-profile').outerHTML.replace('display:none', 'display:block') : '';";

    $old3 = "w.document.write('</div>');\r\n        w.document.write('<table>' + theadHTML + tbodyHTML + '</table>');";
    $new3 = "w.document.write('</div>');\r\n        w.document.write(leaderHTML);\r\n        w.document.write('<table>' + theadHTML + tbodyHTML + '</table>');";

    if (strpos($content, $old1) !== false) {
        $content = str_replace($old1, $new1, $content);
        $content = str_replace($old2, $new2, $content);
        $content = str_replace($old3, $new3, $content);
        file_put_contents($file, $content);
        echo "Fixed $file\n";
    } else {
        echo "Pattern not found in $file\n";
        
        // try without \r
        $old1b = str_replace("\r\n", "\n", $old1);
        $new1b = str_replace("\r\n", "\n", $new1);
        if (strpos($content, $old1b) !== false) {
            $content = str_replace($old1b, $new1b, $content);
            $content = str_replace(str_replace("\r\n", "\n", $old2), str_replace("\r\n", "\n", $new2), $content);
            $content = str_replace(str_replace("\r\n", "\n", $old3), str_replace("\r\n", "\n", $new3), $content);
            file_put_contents($file, $content);
            echo "Fixed $file (LF only)\n";
        }
    }
}
?>
