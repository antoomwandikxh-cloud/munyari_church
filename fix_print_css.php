<?php
$file = 'C:\\xampp\\htdocs\\munyari_church\\print_village_members.php';
$content = file_get_contents($file);

$old_css = '        body { font-family: \'Helvetica Neue\', Arial, sans-serif; padding: 0; margin: 0; color: #000; background: #fff; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; margin: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 80px; color: rgba(0,0,0,0.03); z-index: -1; white-space: nowrap; pointer-events: none; }
        }';

$new_css = '        html, body { background-color: #ffffff !important; color: #000000 !important; }
        body { font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; padding: 20px; color: #111; max-width: 800px; margin: 0 auto; position: relative; }
        @media print { 
            @page { size: A4 portrait; margin: 0; } 
            body { margin: 15mm; padding: 0; max-width: 100%; } 
            .no-print { display: none !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }
            .watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 80px; color: rgba(0,0,0,0.03) !important; z-index: -1; white-space: nowrap; pointer-events: none; }
        }';

$content = str_replace($old_css, $new_css, $content);
file_put_contents($file, $content);
echo "Success\n";
?>
