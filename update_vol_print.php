<?php
$file = 'print_volunteers.php';
$c = file_get_contents($file);

// 1. Change CSS for .sec-badge
$c = str_replace('display: inline-block; font-size: 0.9rem; font-weight: 800;', 'display: block; text-align: center; font-size: 1.1rem; font-weight: 800;', $c);

// 2. Change the text inside the sec-badge (remove Volunteer)
$c = preg_replace('/(<?= \$count \?>) Volunteer(<\?= \$count != 1 \? \'s\' : \'\' \?>)/', '$1 Member$2', $c);

// 3. Change empty state text
$c = str_replace('No volunteers for', 'No members for', $c);

// 4. Add the stamp and signature section before footer
$sig_block = <<<HTML
<div style="margin-top: 40px; margin-bottom: 30px; display: flex; justify-content: space-around; text-align: center; font-size: 0.9rem; page-break-inside: avoid;">
    <div>
        <p style="margin-bottom: 20px; font-weight: bold; color: var(--text-main);">Official Church Stamp:</p>
        <div style="width: 110px; height: 110px; border: 2px dashed #cbd5e1; border-radius: 50%; margin: 0 auto; display: flex; align-items: center; justify-content: center; color: #94a3b8; text-transform: uppercase; font-size: 0.75rem; font-weight: 600; letter-spacing: 1px;">Stamp Here</div>
    </div>
    <div>
        <p style="margin-bottom: 70px; font-weight: bold; color: var(--text-main);">Pastor's Signature:</p>
        <div style="width: 220px; border-bottom: 2px solid #333; margin: 0 auto;"></div>
        <p style="margin-top: 8px; color: #555; font-style: italic;">Sign & Date</p>
    </div>
</div>

<div class="footer">
HTML;

$c = str_replace('<div class="footer">', $sig_block, $c);

file_put_contents($file, $c);
echo "Updated $file\n";
?>
