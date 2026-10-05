<?php
$file = 'print_volunteers.php';
$c = file_get_contents($file);

// 1. Remove "- All Villages"
$c = str_replace(' - All Villages', '', $c);
$c = str_replace(' ?" All Villages', '', $c); // In case of weird encoding

// 2. Fix the SQL order by
// Current SQL contains: ORDER BY church_village, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, first_name
$c = preg_replace(
    "/ORDER BY church_village,\s*CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC,\s*first_name/i",
    "ORDER BY CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, first_name ASC, last_name ASC",
    $c
);

// 3. Update CSS for signatures and stamp
$new_css = <<<CSS
        /* Signatures */
        .sig-section { display: flex; justify-content: space-between; margin-top: 50px; page-break-inside: avoid; }
        .sig-box { width: 45%; }
        .sig-title { font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; margin-bottom: 3px; }
        .sig-name  { font-size: 13px; font-weight: 700; text-transform: uppercase; text-align: center; margin-bottom: 18px; }
        .sig-row   { display: flex; align-items: flex-end; margin-bottom: 14px; }
        .sig-label { width: 70px; font-size: 13px; font-weight: 600; color: #333; text-align:left; }
        .sig-line  { flex: 1; border-bottom: 1px solid #000; height: 20px; }
        .sig-line-dashed { flex: 1; border-bottom: 1px dashed #555; height: 20px; }

        /* Stamp */
        .stamp-circle { width: 140px; height: 140px; border: 2px dashed #aaa; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; color: #aaa; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; line-height: 2; margin: 0 auto; }
CSS;

// Let's add the CSS just before </style>
if (strpos($c, '.sig-section') === false) {
    $c = str_replace('</style>', $new_css . "\n</style>", $c);
}

// 4. Update the HTML block
$old_sig_block_start = '<div style="margin-top: 40px; margin-bottom: 30px; display: flex; justify-content: space-around; text-align: center; font-size: 0.9rem; page-break-inside: avoid;">';

$new_sig_block = <<<HTML
<div class="sig-section">
    <div class="sig-box" style="display: flex; justify-content: center; align-items: center;">
        <div class="stamp-circle">
            Official<br>Church<br>Stamp
        </div>
    </div>
    <div class="sig-box">
        <div class="sig-title">Church Pastor</div>
        <div class="sig-name">__________________________</div>
        <div class="sig-row"><span class="sig-label">Name :</span><div class="sig-line"></div></div>
        <div class="sig-row"><span class="sig-label">Signature :</span><div class="sig-line"></div></div>
        <div class="sig-row"><span class="sig-label">Date :</span><div class="sig-line-dashed"></div></div>
    </div>
</div>

<div class="footer">
HTML;

// Find everything from my old signature block to `<div class="footer">` and replace it
$c = preg_replace('/<div style="margin-top: 40px; margin-bottom: 30px; display: flex; justify-content: space-around; text-align: center; font-size: 0\.9rem; page-break-inside: avoid;">.*?<div class="footer">/s', $new_sig_block, $c);

file_put_contents($file, $c);
echo "Applied requested changes to $file\n";
?>
