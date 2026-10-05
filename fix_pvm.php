<?php
$f = 'C:\xampp\htdocs\munyari_church\print_village_members.php';
$c = file_get_contents($f);

// 1. Swap leader block and header block
$header_start = strpos($c, '<!-- Header with logos -->');
$leader_start = strpos($c, '<!-- Village Leader block -->');
$table_start = strpos($c, '<!-- Members Table -->');

if ($header_start !== false && $leader_start !== false && $table_start !== false) {
    $header_block = substr($c, $header_start, $leader_start - $header_start);
    $leader_block = substr($c, $leader_start, $table_start - $leader_start);
    
    // Create new leader block with default avatar and smaller size
    $new_leader_block = <<<HTML
    <!-- Village Leader block -->
    <div class="leader-block" style="text-align:center; margin:0 auto 10px; background:#f8fafc; padding:6px 12px; border-radius:6px; border:1px solid #e2e8f0; width:fit-content; min-width:200px;">
        <img src="<?= \$l_pic_b64 ?: \$default_pic_b64 ?>" alt="Village Leader" style="width:55px; height:55px; border-radius:50%; object-fit:cover; border:2px solid #1e3a8a; margin-bottom:4px; <?= empty(\$l_pic_b64) ? 'opacity:0.7; filter:grayscale(100%); border-color:#cbd5e1;' : '' ?>">
        <div class="lb-name" style="font-size:1.1rem; font-weight:800; color:#1e3a8a; text-transform:uppercase; margin-bottom:2px;"><?= \$leader ? htmlspecialchars(\$leader['first_name'] . ' ' . \$leader['last_name']) : 'NOT ASSIGNED' ?></div>
        <div class="lb-role" style="font-size:0.85rem; font-weight:600; color:#64748b; margin-bottom:4px;"><?= htmlspecialchars(\$village) ?> Village Leader</div>
    </div>
    
HTML;

    // Combine them in the reverse order
    $c = substr($c, 0, $header_start) . $new_leader_block . "\n" . $header_block . substr($c, $table_start);
    file_put_contents($f, $c);
    echo "Swapped and updated blocks in print_village_members.php\n";
} else {
    echo "Could not find blocks\n";
}
?>
