<?php
$f = 'C:\xampp\htdocs\munyari_church\print_village_members.php';
$c = file_get_contents($f);

// Add orientation support at top of PHP section
$old_village = '$village = trim($_GET[\'village\'] ?? \'\');';
$new_village = '$village = trim($_GET[\'village\'] ?? \'\');' . "\n" . '$orientation = ($_GET[\'orientation\'] ?? \'portrait\') === \'landscape\' ? \'landscape\' : \'portrait\';';
$c = str_replace($old_village, $new_village, $c);

// Update the @page CSS to use dynamic orientation
$old_page = '@page { size: A4 portrait; margin: 0; }';
$new_page = '@page { size: A4 <?= $orientation ?>; margin: 0; }';
$c = str_replace($old_page, $new_page, $c);

file_put_contents($f, $c);
echo "Done\n";
?>
