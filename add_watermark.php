<?php
$file = "C:\\xampp\\htdocs\\munyari_church\\member_dashboard.php";
$content = file_get_contents($file);

// Add watermark to printSsRoster
$old_roster_html_start = "let html = `<!doctype html><html><head><title>\${escapeHtml(className)} Roster</title>";
$new_roster_html_start = "let html = `<!doctype html><html><head><title>\${escapeHtml(className)} Roster</title>";
// We need to inject the watermark after <body>
$old_roster_body = "</style>\n                          </head><body>\n                          ";
$new_roster_body = "</style>\n                          </head><body>\n                          <div style=\"position:fixed; top:50%; left:50%; transform:translate(-50%,-50%) rotate(-45deg); font-size:4.5rem; color:rgba(0,0,0,0.04); font-weight:bold; white-space:nowrap; z-index:-1; pointer-events:none;\">E.A.P.C MUNYARI CHURCH</div>\n                          ";

$content = str_replace($old_roster_body, $new_roster_body, $content);
file_put_contents($file, $content);
echo "Watermarks added to print functions.\n";
?>
