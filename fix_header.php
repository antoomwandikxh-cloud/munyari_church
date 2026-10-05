<?php
$f = 'C:\xampp\htdocs\munyari_church\print_village_members.php';
$c = file_get_contents($f);

// Remove the separate floating leader block (lines 203-210 area)
$old_leader_block = '        <!-- Village Leader block -->
    <div class="leader-block" style="text-align:center; margin:0 auto 10px; background:#f8fafc; padding:6px 12px; border-radius:6px; border:1px solid #e2e8f0; width:fit-content; min-width:200px;">
        <img src="<?= $l_pic_b64 ?: $default_pic_b64 ?>" alt="Village Leader" style="width:55px; height:55px; border-radius:50%; object-fit:cover; border:2px solid #1e3a8a; margin-bottom:4px; <?= empty($l_pic_b64) ? \'opacity:0.7; filter:grayscale(100%); border-color:#cbd5e1;\' : \'\' ?>">
        <div class="lb-name" style="font-size:1.1rem; font-weight:800; color:#1e3a8a; text-transform:uppercase; margin-bottom:2px;"><?= $leader ? htmlspecialchars($leader[\'first_name\'] . \' \' . $leader[\'last_name\']) : \'NOT ASSIGNED\' ?></div>
        <div class="lb-role" style="font-size:0.85rem; font-weight:600; color:#64748b; margin-bottom:4px;"><?= htmlspecialchars($village) ?> Village Leader</div>
    </div>
    
<!-- Header with logos -->
    <div class="header">
        <?php if ($logo_b64): ?><img src="<?= $logo_b64 ?>" alt="Church Logo"><?php endif; ?>
        <div class="header-text">
            <h1>E.A.P.C Munyari Church</h1>
            <h2><?= strtoupper(htmlspecialchars($village)) ?> Village - Members Directory</h2>
            <h3>Printed on: <?= date(\'l, F j, Y\') ?></h3>
            <?php if ($pastor): ?>
            <h3 style="margin-top:4px; font-weight:600; color:#1e3a8a;">Pastor: <?= htmlspecialchars($pastor_name) ?></h3>
            <?php endif; ?>
        </div>
        <?php if ($logo_b64): ?><img src="<?= $logo_b64 ?>" alt="Church Logo"><?php endif; ?>
    </div>';

// New header: Logo LEFT | Centre text | Leader photo RIGHT
$new_header = '<!-- Header with logos -->
    <div class="header">
        <?php if ($logo_b64): ?><img src="<?= $logo_b64 ?>" alt="Church Logo"><?php endif; ?>
        <div class="header-text">
            <h1>E.A.P.C Munyari Church</h1>
            <h2><?= strtoupper(htmlspecialchars($village)) ?> Village - Members Directory</h2>
            <h3>Printed on: <?= date(\'l, F j, Y\') ?></h3>
            <?php if ($pastor): ?>
            <h3 style="margin-top:4px; font-weight:600; color:#1e3a8a;">Pastor: <?= htmlspecialchars($pastor_name) ?></h3>
            <?php endif; ?>
        </div>
        <div style="text-align:center; flex-shrink:0;">
            <img src="<?= $l_pic_b64 ?: $default_pic_b64 ?>" alt="Village Leader" style="width:75px; height:75px; border-radius:50%; object-fit:cover; border:3px solid #1e3a8a; display:block; margin:0 auto 4px; <?= empty($l_pic_b64) ? \'filter:grayscale(60%);\' : \'\' ?>">
            <div style="font-size:9px; font-weight:800; color:#1e3a8a; text-transform:uppercase; margin-top:3px;"><?= $leader ? htmlspecialchars($leader[\'first_name\'] . \' \' . $leader[\'last_name\']) : \'NOT ASSIGNED\' ?></div>
            <div style="font-size:8px; color:#64748b; font-weight:600;"><?= htmlspecialchars($village) ?> Village Leader</div>
        </div>
    </div>';

if (strpos($c, $old_leader_block) !== false) {
    $c = str_replace($old_leader_block, $new_header, $c);
    file_put_contents($f, $c);
    echo "Fixed successfully\n";
} else {
    echo "Pattern not matched\n";
    // Try finding just the leader block part and replacing independently
    // Remove just the floating leader block
    $just_leader = '        <!-- Village Leader block -->
    <div class="leader-block" style="text-align:center; margin:0 auto 10px; background:#f8fafc; padding:6px 12px; border-radius:6px; border:1px solid #e2e8f0; width:fit-content; min-width:200px;">
        <img src="<?= $l_pic_b64 ?: $default_pic_b64 ?>" alt="Village Leader" style="width:55px; height:55px; border-radius:50%; object-fit:cover; border:2px solid #1e3a8a; margin-bottom:4px; <?= empty($l_pic_b64) ? \'opacity:0.7; filter:grayscale(100%); border-color:#cbd5e1;\' : \'\' ?>">
        <div class="lb-name" style="font-size:1.1rem; font-weight:800; color:#1e3a8a; text-transform:uppercase; margin-bottom:2px;"><?= $leader ? htmlspecialchars($leader[\'first_name\'] . \' \' . $leader[\'last_name\']) : \'NOT ASSIGNED\' ?></div>
        <div class="lb-role" style="font-size:0.85rem; font-weight:600; color:#64748b; margin-bottom:4px;"><?= htmlspecialchars($village) ?> Village Leader</div>
    </div>
    
<!-- Header with logos -->';

    $replacement_no_leader = '<!-- Header with logos -->';
    
    if (strpos($c, $just_leader) !== false) {
        $c = str_replace($just_leader, $replacement_no_leader, $c);
        // Now replace the second church logo in the header with leader photo
        $old_second_logo = '        <?php if ($logo_b64): ?><img src="<?= $logo_b64 ?>" alt="Church Logo"><?php endif; ?>
    </div>';
        $new_right = '        <div style="text-align:center; flex-shrink:0;">
            <img src="<?= $l_pic_b64 ?: $default_pic_b64 ?>" alt="Village Leader" style="width:75px; height:75px; border-radius:50%; object-fit:cover; border:3px solid #1e3a8a; display:block; margin:0 auto 4px; <?= empty($l_pic_b64) ? \'filter:grayscale(60%);\' : \'\' ?>">
            <div style="font-size:9px; font-weight:800; color:#1e3a8a; text-transform:uppercase; margin-top:3px;"><?= $leader ? htmlspecialchars($leader[\'first_name\'] . \' \' . $leader[\'last_name\']) : \'NOT ASSIGNED\' ?></div>
            <div style="font-size:8px; color:#64748b; font-weight:600;"><?= htmlspecialchars($village) ?> Village Leader</div>
        </div>
    </div>';
        $c = str_replace($old_second_logo, $new_right, $c);
        file_put_contents($f, $c);
        echo "Fixed with fallback method\n";
    } else {
        echo "Nothing matched\n";
    }
}
?>
