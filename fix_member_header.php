<?php
$files = ['pastor_dashboard.php', 'admin_dashboard.php'];

foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // 1. Fix `#printableMemberDir` header to use Flexbox (prevents clipping in Portrait) and add back the Date
    $old_member_hdr = '<div style="text-align:center;margin-bottom:6px;border-bottom:2px solid #1e3a8a;padding-bottom:12px;position:relative;min-height:85px;">' . "\r\n" .
                      '                                            <img src="church_logo.jpg" style="position:absolute;left:20px;top:0;width:70px;height:70px;object-fit:contain;">' . "\r\n" .
                      '                                            <img src="church_logo.jpg" style="position:absolute;right:20px;top:0;width:70px;height:70px;object-fit:contain;">' . "\r\n" .
                      '                                            <h2 style="margin:0;font-size:1.35rem;color:#1e3a8a;padding-top:10px;">E.A.P.C MUNYARI CHURCH MEMBERS TRACK RECORD</h2>' . "\r\n" .
                      '                                        </div>';

    $new_member_hdr = '<div style="display:flex;align-items:center;justify-content:space-between;border-bottom:2px solid #1e3a8a;padding-bottom:12px;margin-bottom:6px;gap:10px;">' . "\r\n" .
                      '                                            <img src="church_logo.jpg" style="width:65px;height:65px;object-fit:contain;flex-shrink:0;">' . "\r\n" .
                      '                                            <div style="text-align:center;flex:1;min-width:0;">' . "\r\n" .
                      '                                                <h2 style="margin:0;font-size:1.2rem;color:#1e3a8a;">E.A.P.C MUNYARI CHURCH</h2>' . "\r\n" .
                      '                                                <h3 style="margin:2px 0;font-size:1rem;color:#1e3a8a;">MEMBERS TRACK RECORD</h3>' . "\r\n" .
                      '                                                <p style="margin:2px 0;font-size:0.78rem;color:#555;">Printed on: <?= date(\'F j, Y g:i A\') ?></p>' . "\r\n" .
                      '                                            </div>' . "\r\n" .
                      '                                            <img src="church_logo.jpg" style="width:65px;height:65px;object-fit:contain;flex-shrink:0;">' . "\r\n" .
                      '                                        </div>';

    if (str_contains($c, $old_member_hdr)) {
        $c = str_replace($old_member_hdr, $new_member_hdr, $c);
    } else {
        echo "Member header pattern not found in $file\n";
    }

    // 2. Add Printed Date back to printDepartment header
    // In the flexbox header for department, we removed it. Let's add it back.
    // The current line is: w.document.write('<h3 style="margin:3px 0;color:#1e3a8a;font-size:1rem;">' + deptName.toUpperCase() + ' DEPARTMENT</h3>');
    $dept_target = "w.document.write('<h3 style=\"margin:3px 0;color:#1e3a8a;font-size:1rem;\">' + deptName.toUpperCase() + ' DEPARTMENT</h3>');\r\n        w.document.write('</div>');";
    $dept_replace = "w.document.write('<h3 style=\"margin:3px 0;color:#1e3a8a;font-size:1rem;\">' + deptName.toUpperCase() + ' DEPARTMENT</h3>');\r\n        w.document.write('<p style=\"margin:2px 0;font-size:0.78rem;color:#555;\">Printed on: ' + printDate + '</p>');\r\n        w.document.write('</div>');";
    
    // Check if it's already there
    if (!str_contains($c, "w.document.write('<p style=\"margin:2px 0;font-size:0.78rem;color:#555;\">Printed on: ' + printDate + '</p>');")) {
        // Try replacing
        $c = str_replace(
            "w.document.write('<h3 style=\"margin:3px 0;color:#1e3a8a;font-size:1rem;\">' + deptName.toUpperCase() + ' DEPARTMENT</h3>');\r\n    w.document.write('</div>');",
            "w.document.write('<h3 style=\"margin:3px 0;color:#1e3a8a;font-size:1rem;\">' + deptName.toUpperCase() + ' DEPARTMENT</h3>');\r\n    w.document.write('<p style=\"margin:2px 0;font-size:0.78rem;color:#555;\">Printed on: ' + printDate + '</p>');\r\n    w.document.write('</div>');",
            $c
        );
    }

    // 3. Add Printed Date back to printVillageTable header
    $village_target = "w.document.write('<h3 style=\"margin:3px 0;color:#1e3a8a;font-size:1rem;\">' + villageName.toUpperCase() + ' VILLAGE — MEMBERS LIST</h3>');\r\n        w.document.write('</div>');";
    $village_replace = "w.document.write('<h3 style=\"margin:3px 0;color:#1e3a8a;font-size:1rem;\">' + villageName.toUpperCase() + ' VILLAGE — MEMBERS LIST</h3>');\r\n        w.document.write('<p style=\"margin:2px 0;font-size:0.78rem;color:#555;\">Printed on: ' + new Date().toLocaleString() + '</p>');\r\n        w.document.write('</div>');";
    
    if (!str_contains($c, "w.document.write('<p style=\"margin:2px 0;font-size:0.78rem;color:#555;\">Printed on: ' + new Date().toLocaleString() + '</p>');")) {
        $c = str_replace(
            "w.document.write('<h3 style=\"margin:3px 0;color:#1e3a8a;font-size:1rem;\">' + villageName.toUpperCase() + ' VILLAGE — MEMBERS LIST</h3>');\r\n        w.document.write('</div>');",
            "w.document.write('<h3 style=\"margin:3px 0;color:#1e3a8a;font-size:1rem;\">' + villageName.toUpperCase() + ' VILLAGE — MEMBERS LIST</h3>');\r\n        w.document.write('<p style=\"margin:2px 0;font-size:0.78rem;color:#555;\">Printed on: ' + new Date().toLocaleString() + '</p>');\r\n        w.document.write('</div>');",
            $c
        );
    }

    file_put_contents($file, $c);
    echo "Fixed $file\n";
}
?>
