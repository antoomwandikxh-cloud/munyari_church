<?php
$oleBadgeFunc = '
function render_role_pills($church_role_raw, $is_pastor = false, $is_leader = false) {
    if ($is_pastor) {
        return \'<span class="role-pill rp-pastor">Pastor</span>\';
    }
    $aw = trim($church_role_raw ?? \'\');
    if ($aw === \'\' || strtolower($aw) === \'member\') {
        return \'<span class="role-pill rp-member">Member</span>\';
    }
    $parts = array_values(array_filter(array_map(\'trim\', explode(\',\', str_replace(\'&\', \',\', $aw)))));
    $out = \'\';
    foreach ($parts as $ole) {
        $l = strtolower($ole);
        if (strpos($l, \'village leader\') !== false) { $cls = \'rp-leader\'; }
        elseif (strpos($l, \'chairperson\') !== false || strpos($l, \'chair\') !== false) { $cls = \'rp-chair\'; }
        elseif (strpos($l, \'vice\') !== false) { $cls = \'rp-vice\'; }
        elseif (strpos($l, \'secretary\') !== false) { $cls = \'rp-secretary\'; }
        elseif (strpos($l, \'treasurer\') !== false) { $cls = \'rp-treasurer\'; }
        elseif (strpos($l, \'elder\') !== false || strpos($l, \'deacon\') !== false) { $cls = \'rp-elder\'; }
        elseif (strpos($l, \'worship\') !== false || strpos($l, \'praise\') !== false) { $cls = \'rp-worship\'; }
        elseif (strpos($l, \'usher\') !== false) { $cls = \'rp-usher\'; }
        elseif (strpos($l, \'pastor\') !== false) { $cls = \'rp-pastor\'; }
        else { $cls = \'rp-other\'; }
        $out .= \'<span class="role-pill \' . $cls . \'">\'  . htmlspecialchars($ole) . \'</span>\';
    }
    return \'<div class="role-pills-wrap">\' . $out . \'</div>\';
}
';

$oleCSS = '<style>
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

$iles = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($iles as $ile) {
    $c = file_get_contents($ile);
    if (strpos($c, 'function render_role_pills') === false) {
        $c = preg_replace('/<\?php\s*\n/', "<?php\n" . $oleBadgeFunc . "\n", $c, 1);
    }
    if (strpos($c, '.role-pill') === false) {
        $c = str_replace('</head>', $oleCSS . "\n</head>", $c);
    }
    $old = '<td><span class="badge" style="background:rgba(37,99,235,0.1); color:var(--primary);"><?= htmlspecialchars($is_pastor ? \'Pastor\' : ($ow[\'church_role\'] ?: \'Member\')) ?></span></td>';
    $
ew = '<td><?= render_role_pills($ow[\'church_role\'] ?? \'\', $is_pastor, $is_leader) ?></td>';
    $c = str_replace($old, $
ew, $c);
    file_put_contents($ile, $c);
    echo "Done: $ile\n";
}
?>
