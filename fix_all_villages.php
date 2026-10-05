<?php
$file = 'print_volunteers.php';
$bytes = file_get_contents($file);

// The mdash + All Villages (E2-80-94-20-41-6C-6C-20-56-69-6C-6C-61-67-65-73)
$find    = "\xE2\x80\x94 All Villages";
$replace = "";
$bytes = str_replace($find, $replace, $bytes, $count1);

// Also handle plain dash version
$bytes = str_replace("- All Villages", "", $bytes, $count2);
$bytes = str_replace(" &mdash; All Villages", "", $bytes, $count3);

// Fix the signature block — replace old inline div with the proper .sig-section
// Check first if .sig-section class is already in the HTML output
if (strpos($bytes, 'class="sig-section"') === false) {
    echo "sig-section not found, not replacing sig block\n";
} else {
    echo "sig-section already in place\n";
}

file_put_contents($file, $bytes);
echo "Done. Removed 'All Villages' (mdash count: $count1, dash: $count2, html: $count3)\n";

// Verify
$check = file_get_contents($file);
if (strpos($check, 'All Villages') === false) {
    echo "'All Villages' fully removed!\n";
} else {
    echo "WARNING: 'All Villages' still present!\n";
}
?>
