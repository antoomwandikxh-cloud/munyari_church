<?php
$print_func = <<<'EOT'
function printAllLeaders(orientation) {
    if (!orientation) orientation = 'portrait';
    var pageSize = orientation === 'landscape' ? 'A4 landscape' : 'A4 portrait';

    // Fetch church logo and pastor name from existing helpers
    var logoUrl  = document.getElementById('deptPrintLogo')   ? document.getElementById('deptPrintLogo').getAttribute('data-src')    : 'church_logo.jpg';
    var pastorEl = document.getElementById('deptPrintPastorName');
    var pastorName = pastorEl ? pastorEl.innerText.trim() : '';
    var printDate  = document.getElementById('deptPrintDate') ? document.getElementById('deptPrintDate').innerText.trim() : new Date().toLocaleDateString();

    // Collect all leader cards from the "Church Leaders Overview" section
    var cards = document.querySelectorAll('#leadersOverviewSection .leader-print-card');

    // Build the HTML
    var w = window.open('', '_blank');
    if (!w) { alert('Popup blocked! Please allow popups.'); return; }

    w.document.write('<!doctype html><html><head>');
    w.document.write('<title>All Church Leaders</title>');
    w.document.write('<base href="' + window.location.href + '">');
    w.document.write('<style>');
    w.document.write('*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;color-adjust:exact!important;}');
    w.document.write('@media print{@page{size:' + pageSize + ';margin:10mm 12mm 15mm 12mm;}}');
    w.document.write('body{font-family:Arial,sans-serif;margin:0;padding:10px;padding-bottom:60px;}');
    w.document.write('.watermark{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-45deg);font-size:60px!important;color:rgba(30,58,138,0.25)!important;font-weight:bold;white-space:nowrap;z-index:999999!important;opacity:1!important;pointer-events:none;letter-spacing:4px;text-transform:uppercase;mix-blend-mode:multiply;}');
    w.document.write('.footer{position:fixed;bottom:0;left:0;right:0;text-align:center;font-size:10px;color:#777;font-style:italic;background:rgba(255,255,255,0.9);padding:5px 0;z-index:10;}');
    w.document.write('.section-title{font-size:0.85rem;font-weight:800;text-transform:uppercase;letter-spacing:0.06em;padding:6px 12px;border-radius:6px;color:#fff;margin:18px 0 10px;display:inline-block;}');
    w.document.write('.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-bottom:12px;}');
    w.document.write('.card{display:flex;align-items:center;gap:10px;padding:10px;border:1px solid #d1d5db;border-radius:8px;}');
    w.document.write('.avatar{width:50px;height:50px;border-radius:50%;object-fit:cover;flex-shrink:0;}');
    w.document.write('.name{font-weight:700;font-size:0.85rem;color:#1e3a8a;}');
    w.document.write('.role{font-size:0.75rem;color:#555;margin-top:2px;}');
    w.document.write('</style></head><body>');

    w.document.write('<div class="watermark">E.A.P.C MUNYARI CHURCH</div>');

    // Header
    w.document.write('<div style="text-align:center;border-bottom:2px solid #1e3a8a;padding-bottom:10px;position:relative;margin-bottom:16px;min-height:85px;">');
    w.document.write('<img src="' + logoUrl + '" style="position:absolute;left:20px;top:0;width:70px;height:70px;object-fit:contain;">');
    w.document.write('<img src="' + logoUrl + '" style="position:absolute;right:20px;top:0;width:70px;height:70px;object-fit:contain;">');
    w.document.write('<h2 style="margin:0;color:#1e3a8a;padding-top:8px;">E.A.P.C MUNYARI CHURCH</h2>');
    w.document.write('<h3 style="margin:4px 0;color:#1e3a8a;">CHURCH LEADERS REGISTER</h3>');
    w.document.write('<p style="margin:2px 0;font-size:0.78rem;color:#555;">Printed on: ' + printDate + '</p>');
    w.document.write('</div>');

    // Leader sections
    if (cards.length === 0) {
        w.document.write('<p style="text-align:center;color:#888;">No leaders found.</p>');
    } else {
        var currentSection = '';
        var gridOpen = false;
        cards.forEach(function(card) {
            var section = card.getAttribute('data-section') || '';
            var sectionColor = card.getAttribute('data-section-color') || '#1e3a8a';
            var name = card.getAttribute('data-name') || '';
            var role = card.getAttribute('data-role') || '';
            var pic  = card.getAttribute('data-pic')  || 'uploads/default_avatar.png';

            if (section !== currentSection) {
                if (gridOpen) w.document.write('</div>');
                w.document.write('<div class="section-title" style="background:' + sectionColor + ';">' + section + '</div>');
                w.document.write('<div class="grid">');
                gridOpen = true;
                currentSection = section;
            }
            w.document.write('<div class="card">');
            w.document.write('<img class="avatar" src="' + pic + '" onerror="this.src=\'uploads/default_avatar.png\'" style="border:2px solid ' + sectionColor + ';">');
            w.document.write('<div><div class="name">' + name + '</div><div class="role">' + role + '</div></div>');
            w.document.write('</div>');
        });
        if (gridOpen) w.document.write('</div>');
    }

    // Signature
    w.document.write('<div style="display:flex;justify-content:space-between;align-items:flex-end;margin-top:60px;padding:0 20px;page-break-inside:avoid;">');
    w.document.write('<div style="text-align:center;"><div style="font-size:0.82rem;font-weight:700;text-transform:uppercase;color:#1e3a8a;">Church Pastor</div>');
    w.document.write('<div style="font-size:0.75rem;color:#333;margin-bottom:8px;">' + pastorName + '</div>');
    w.document.write('<div style="display:flex;align-items:flex-end;gap:8px;"><span style="font-style:italic;">Sign:</span><span style="display:inline-block;border-bottom:1px solid #000;width:200px;height:14px;"></span></div>');
    w.document.write('<div style="display:flex;align-items:flex-end;gap:8px;margin-top:10px;"><span>Date:</span><span style="display:inline-block;border-bottom:1px dotted #000;width:200px;height:14px;"></span></div></div>');
    w.document.write('<div style="text-align:center;"><div style="border:2px dashed #aaa;width:100px;height:100px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:0.7rem;text-transform:uppercase;line-height:1.4;text-align:center;">Official<br>Stamp</div></div>');
    w.document.write('</div>');

    w.document.write('<div class="footer">Generated from E.A.P.C Munyari Portal | Printed on: ' + printDate + '</div>');
    w.document.write('</body></html>');
    w.document.close();
    setTimeout(function(){ w.print(); }, 600);
}
EOT;

$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $f) {
    $content = file_get_contents($f);
    $content = str_replace("\r", "", $content);
    $search = 'function printDepartment(';
    if (strpos($content, $search) !== false) {
        $content = str_replace($search, $print_func . "\n\nfunction printDepartment(", $content);
        file_put_contents($f, $content);
        echo "Inserted printAllLeaders in $f\n";
    } else {
        echo "Could not find printDepartment in $f\n";
    }
}
?>
