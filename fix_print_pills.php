<?php
$roleBadgeFunc = '
function render_role_pills($church_role_raw, $is_pastor = false, $is_leader = false) {
    if ($is_pastor) { return \'<span class="role-pill rp-pastor">&#9962; Pastor</span>\'; }
    $raw = trim($church_role_raw ?? \'\');
    if ($raw === \'\' || strtolower($raw) === \'member\') { return \'<span class="role-pill rp-member">Member</span>\'; }
    $parts = array_values(array_filter(array_map(\'trim\', explode(\',\', str_replace(\'&\', \',\', $raw)))));
    $out = \'\';
    foreach ($parts as $role) {
        $rl = strtolower($role);
        if (strpos($rl,\'village leader\')!==false){ $cls=\'rp-leader\'; }
        elseif(strpos($rl,\'chairperson\')!==false||strpos($rl,\'chair\')!==false){ $cls=\'rp-chair\'; }
        elseif(strpos($rl,\'vice\')!==false){ $cls=\'rp-vice\'; }
        elseif(strpos($rl,\'secretary\')!==false){ $cls=\'rp-secretary\'; }
        elseif(strpos($rl,\'treasurer\')!==false){ $cls=\'rp-treasurer\'; }
        elseif(strpos($rl,\'elder\')!==false||strpos($rl,\'deacon\')!==false){ $cls=\'rp-elder\'; }
        elseif(strpos($rl,\'worship\')!==false||strpos($rl,\'praise\')!==false){ $cls=\'rp-worship\'; }
        elseif(strpos($rl,\'usher\')!==false){ $cls=\'rp-usher\'; }
        elseif(strpos($rl,\'pastor\')!==false){ $cls=\'rp-pastor\'; }
        else{ $cls=\'rp-other\'; }
        $out .= \'<span class="role-pill \'.$cls.\'">\'  . htmlspecialchars($role) . \'</span>\';
    }
    return \'<div class="role-pills-wrap">\'.$out.\'</div>\';
}
';

$roleCSS = '<style>
.role-pills-wrap{display:flex;flex-wrap:wrap;gap:4px;align-items:center;}
.role-pill{display:inline-flex;align-items:center;font-size:0.72rem;font-weight:700;padding:3px 10px;border-radius:20px;white-space:nowrap;line-height:1.5;letter-spacing:0.2px;border:1px solid transparent;}
.rp-leader{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe;}
.rp-chair{background:#f5f3ff;color:#6d28d9;border-color:#ddd6fe;}
.rp-vice{background:#fdf4ff;color:#9333ea;border-color:#f0abfc;}
.rp-secretary{background:#ecfdf5;color:#059669;border-color:#6ee7b7;}
.rp-treasurer{background:#fff7ed;color:#c2410c;border-color:#fdba74;}
.rp-elder{background:#f0fdf4;color:#15803d;border-color:#bbf7d0;}
.rp-worship{background:#fdf2f8;color:#be185d;border-color:#fbcfe8;}
.rp-usher{background:#fefce8;color:#a16207;border-color:#fde047;}
.rp-pastor{background:#fef3c7;color:#92400e;border-color:#f59e0b;}
.rp-member{background:#f1f5f9;color:#64748b;border-color:#cbd5e1;}
.rp-other{background:#f8fafc;color:#475569;border-color:#e2e8f0;}
</style>';

$files = ['print_village_members.php', 'print_all_villages.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);
    if (strpos($c, 'function render_role_pills') === false) {
        $c = preg_replace('/<\?php\s*\n/', "<?php\n" . $roleBadgeFunc . "\n", $c, 1);
    }
    if (strpos($c, '.role-pill') === false) {
        $c = str_replace('</head>', $roleCSS . "\n</head>", $c);
    }
    file_put_contents($file, $c);
}

// Replace in print_village_members.php
$pvm = file_get_contents('print_village_members.php');
$pvm = str_replace('<td><?= htmlspecialchars($m[\'church_role\'] ?? \'\') ?></td>', '<td><?= render_role_pills($m[\'church_role\'] ?? \'\') ?></td>', $pvm);
file_put_contents('print_village_members.php', $pvm);

// Replace in print_all_villages.php
$pav = file_get_contents('print_all_villages.php');
$pav = str_replace('<td><span class="badge" style="background:#e0e7ff; color:#1e3a8a; padding:3px 8px; border-radius:12px; font-weight:700; font-size:0.75rem;"><?= htmlspecialchars($is_pastor ? \'Pastor\' : ($row[\'church_role\'] ?: \'Member\')) ?></span></td>', '<td><?= render_role_pills($row[\'church_role\'] ?? \'\', $is_pastor, $is_leader) ?></td>', $pav);
file_put_contents('print_all_villages.php', $pav);
echo "Injected pills to print files\n";
?>
