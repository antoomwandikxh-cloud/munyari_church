<?php
// ---- Colour map for each role category ----
// We will add a PHP helper function + CSS, then replace the single-badge
// church_role td cell in both admin_dashboard.php and pastor_dashboard.php

$role_badge_func = <<<'PHPFUNC'

// Returns HTML for one or more role pills from a comma-separated church_role string
function render_role_pills($church_role_raw, $is_pastor = false, $is_leader = false, $v_color = '#6366f1') {
    if ($is_pastor) {
        return '<span class="role-pill" style="background:#fef3c7;color:#92400e;border:1px solid #f59e0b;">⛪ Pastor</span>';
    }
    $raw = trim($church_role_raw ?? '');
    if ($raw === '' || strtolower($raw) === 'member') {
        return '<span class="role-pill" style="background:#f1f5f9;color:#64748b;border:1px solid #cbd5e1;">Member</span>';
    }
    $parts = array_values(array_filter(array_map('trim', explode(',', str_replace('&', ',', $raw)))));
    $out = '';
    foreach ($parts as $role) {
        $rl = strtolower($role);
        if (strpos($rl,'village leader') !== false || $is_leader) {
            $bg='#eff6ff'; $col='#1d4ed8'; $border='#bfdbfe';
        } elseif (strpos($rl,'chairperson') !== false || strpos($rl,'chair person') !== false) {
            $bg='#f5f3ff'; $col='#6d28d9'; $border='#ddd6fe';
        } elseif (strpos($rl,'vice') !== false) {
            $bg='#fdf4ff'; $col='#9333ea'; $border='#f0abfc';
        } elseif (strpos($rl,'secretary') !== false) {
            $bg='#ecfdf5'; $col='#059669'; $border='#6ee7b7';
        } elseif (strpos($rl,'treasurer') !== false) {
            $bg='#fff7ed'; $col='#c2410c'; $border='#fdba74';
        } elseif (strpos($rl,'elder') !== false || strpos($rl,'deacon') !== false) {
            $bg='#f0fdf4'; $col='#15803d'; $border='#bbf7d0';
        } elseif (strpos($rl,'worship') !== false || strpos($rl,'praise') !== false) {
            $bg='#fdf2f8'; $col='#be185d'; $border='#fbcfe8';
        } elseif (strpos($rl,'usher') !== false) {
            $bg='#fefce8'; $col='#a16207'; $border='#fde047';
        } elseif (strpos($rl,'pastor') !== false) {
            $bg='#fef9c3'; $col='#854d0e'; $border='#fde68a';
        } else {
            $bg='#f8fafc'; $col='#475569'; $border='#e2e8f0';
        }
        $out .= '<span class="role-pill" style="background:'.$bg.';color:'.$col.';border:1px solid '.$border.';">'.htmlspecialchars($role).'</span>';
    }
    return '<div class="role-pills-wrap">'.$out.'</div>';
}

PHPFUNC;

$role_css = <<<'CSS'
<style>
.role-pills-wrap { display:flex; flex-wrap:wrap; gap:4px; align-items:center; }
.role-pill {
    display:inline-flex; align-items:center;
    font-size:0.72rem; font-weight:700;
    padding:3px 9px;
    border-radius:20px;
    white-space:nowrap;
    line-height:1.4;
    letter-spacing:0.2px;
}
</style>
CSS;

$files = ['admin_dashboard.php', 'pastor_dashboard.php'];

foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // 1. Inject the helper function after <?php opening (after the first <?php tag)
    if (strpos($c, 'function render_role_pills') === false) {
        $c = preg_replace('/<\?php\s*/m', "<?php\n" . $role_badge_func . "\n", $c, 1);
    }
    
    // 2. Inject the CSS just before </head>
    if (strpos($c, '.role-pill') === false) {
        $c = str_replace('</head>', $role_css . "\n</head>", $c);
    }
    
    // 3. Replace the single-badge church_role <td> cell:
    // The current HTML is:
    // <td><span class="badge" style="background:rgba(37,99,235,0.1); color:var(--primary);"><?= htmlspecialchars($is_pastor ? 'Pastor' : ($row['church_role'] ?: 'Member')) ?></span></td>
    
    $old_td = '<td><span class="badge" style="background:rgba(37,99,235,0.1); color:var(--primary);"><?= htmlspecialchars($is_pastor ? \'Pastor\' : ($row[\'church_role\'] ?: \'Member\')) ?></span></td>';
    $new_td = '<td><?= render_role_pills($row[\'church_role\'] ?? \'\', $is_pastor, $is_leader, $v_color) ?></td>';
    $c = str_replace($old_td, $new_td, $c);
    
    file_put_contents($file, $c);
    echo "Done: $file\n";
}
?>
