<?php
$file = 'C:\\xampp\\htdocs\\munyari_church\\member_dashboard.php';
$content = file_get_contents($file);

$old = '                    <button onclick="window.print()" class="btn-primary" style="background:#10b981; padding:8px 16px; font-size:0.85rem;">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:6px; vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        Print List
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="print-table">';

$new = '                    <button onclick="printVillageMembers()" class="btn-primary" style="background:#10b981; padding:8px 16px; font-size:0.85rem;">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:6px; vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        Print List
                    </button>
                </div>
                <div class="table-responsive" id="villageMembersPrintArea">
                    <table class="print-table" style="width:100%; border-collapse:collapse;">';

$content = str_replace($old, $new, $content);

$old2 = '            <style>
                @media print {
                    body * { visibility: hidden; }
                    .print-table, .print-table * { visibility: visible; }
                    .print-table { position: absolute; left: 0; top: 0; width: 100%; border-collapse: collapse; }
                    .print-table th, .print-table td { border: 1px solid #000; padding: 8px; text-align: left; }
                }
            </style>';

$new2 = '            <script>
            function printVillageMembers() {
                var printDiv = document.getElementById(\'villageMembersPrintArea\');
                var villageName = "<?= htmlspecialchars([\'church_village\'] ?? \'Church\', ENT_QUOTES) ?>";
                var w = window.open(\'\', \'_blank\');
                w.document.write(\'<!DOCTYPE html><html><head><title>Church Village Members</title>\');
                w.document.write(\'<style>body{font-family:Arial,sans-serif;padding:20px;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#f3f4f6;} h2{text-align:center;color:#1e3a8a;} h3{text-align:center;color:#333;margin-top:0;} .print-header{text-align:center;margin-bottom:20px;padding-bottom:10px;border-bottom:2px solid #1e3a8a;}</style></head><body>\');
                w.document.write(\'<div class="print-header"><h2>E.A.P.C Munyari Church</h2><h3>\' + villageName + \' Village Members Directory</h3><p>Date: \' + new Date().toLocaleDateString() + \'</p></div>\');
                w.document.write(printDiv.innerHTML);
                w.document.write(\'<div style="position:fixed;bottom:10px;left:0;right:0;text-align:center;font-size:0.75rem;color:#777;font-style:italic;">Generated from E.A.P.C Munyari Portal</div>\');
                w.document.write(\'</body></html>\');
                w.document.close();
                w.focus();
                setTimeout(function(){ w.print(); }, 600);
            }
            </script>';

$content = str_replace($old2, $new2, $content);
file_put_contents($file, $content);
echo "Success\n";
?>
