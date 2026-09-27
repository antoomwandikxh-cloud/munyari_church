<?php
$file = 'C:\\xampp\\htdocs\\munyari_church\\member_dashboard.php';
$content = file_get_contents($file);

$old = '<style>
                  @media print {
                      body * { visibility: hidden; }
                      .print-table, .print-table * { visibility: visible; }
                      .print-table { position: absolute; left: 0; top: 0; width: 100%; border-collapse: collapse; }
                      .print-table th, .print-table td { border: 1px solid #000; padding: 8px; text-align: left; }
                  }
              </style>';

$content = str_replace($old, '', $content);
file_put_contents($file, $content);
echo "Success\n";
?>
