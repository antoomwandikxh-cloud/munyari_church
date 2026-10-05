<?php
$f = 'C:\xampp\htdocs\munyari_church\print_village_members.php';
$c = file_get_contents($f);

// 1. Fix header: Logo | LEADER CENTRE | Logo
$old_header = '<!-- Header with logos -->
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

$new_header = '<!-- Header with logos -->
    <div class="header">
        <?php if ($logo_b64): ?><img src="<?= $logo_b64 ?>" alt="Church Logo"><?php endif; ?>
        <div class="header-text">
            <div style="text-align:center; margin-bottom:6px;">
                <img src="<?= $l_pic_b64 ?: $default_pic_b64 ?>" alt="Village Leader" style="width:72px; height:72px; border-radius:50%; object-fit:cover; border:3px solid #1e3a8a; display:inline-block; <?= empty($l_pic_b64) ? \'filter:grayscale(60%);\' : \'\' ?>">
            </div>
            <h1>E.A.P.C Munyari Church</h1>
            <h2><?= strtoupper(htmlspecialchars($village)) ?> Village - Members Directory</h2>
            <div style="font-size:11px; font-weight:800; color:#1e3a8a; text-transform:uppercase; margin:2px 0;"><?= $leader ? htmlspecialchars($leader[\'first_name\'] . \' \' . $leader[\'last_name\']) : \'NOT ASSIGNED\' ?> &mdash; <?= htmlspecialchars($village) ?> Village Leader</div>
            <h3>Printed on: <?= date(\'l, F j, Y\') ?></h3>
            <?php if ($pastor): ?>
            <h3 style="margin-top:2px; font-weight:600; color:#1e3a8a;">Pastor: <?= htmlspecialchars($pastor_name) ?></h3>
            <?php endif; ?>
        </div>
        <?php if ($logo_b64): ?><img src="<?= $logo_b64 ?>" alt="Church Logo"><?php endif; ?>
    </div>';

if (strpos($c, $old_header) !== false) {
    $c = str_replace($old_header, $new_header, $c);
    echo "Header fixed\n";
} else {
    echo "Header not matched - trying LF\n";
    $old_header_lf = str_replace("\r\n","\n",$old_header);
    if (strpos($c, $old_header_lf) !== false) {
        $c = str_replace($old_header_lf, $new_header, $c);
        echo "Header fixed (LF)\n";
    } else {
        echo "Header pattern not found!\n";
    }
}

// 2. Add address to SELECT query
$old_q = 'SELECT id, first_name, last_name, phone, department, church_role, profile_picture, is_village_leader,';
$new_q = 'SELECT id, first_name, last_name, phone, address, department, church_role, profile_picture, is_village_leader,';
$c = str_replace($old_q, $new_q, $c);

// 3. Add Residence column header after Status
$old_th = '<th>Status</th>
            </tr>';
$new_th = '<th>Status</th>
                <th>Residence</th>
            </tr>';
$c = str_replace($old_th, $new_th, $c);

// 4. Add Residence cell in the table rows - find the Status td and add after it
// Find the pattern for the Status cell
$old_status_td = '</td>
            </tr>
        <?php endforeach; endwhile; ?>';
// This might vary; better to find the last </td> before </tr> in the row loop
// Let me find the badge-member/badge-role td 
$old_end = '<td><span class="badge badge-member">Member</span></td>
            </tr>';
if (strpos($c, $old_end) !== false) {
    // Insert residence column at the end of each row
    // Actually easier to search for the closing of the row
}

// Better approach: find the Status cell patterns
$old_ldr_end = '<td><span class="badge badge-leader">Village Leader</span></td>
            </tr>';
$new_ldr_end = '<td><span class="badge badge-leader">Village Leader</span></td>
                <td style="color:#555; font-size:11px;"><?= htmlspecialchars($row[\'address\'] ?? \'-\') ?></td>
            </tr>';

$old_role_end = '<td><span class="badge badge-role">Has Role</span></td>
            </tr>';
$new_role_end = '<td><span class="badge badge-role">Has Role</span></td>
                <td style="color:#555; font-size:11px;"><?= htmlspecialchars($row[\'address\'] ?? \'-\') ?></td>
            </tr>';

$old_mem_end = '<td><span class="badge badge-member">Member</span></td>
            </tr>';
$new_mem_end = '<td><span class="badge badge-member">Member</span></td>
                <td style="color:#555; font-size:11px;"><?= htmlspecialchars($row[\'address\'] ?? \'-\') ?></td>
            </tr>';

$c = str_replace($old_ldr_end, $new_ldr_end, $c);
$c = str_replace($old_role_end, $new_role_end, $c);
$c = str_replace($old_mem_end, $new_mem_end, $c);

file_put_contents($f, $c);
echo "Done\n";
?>
