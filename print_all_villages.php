<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['pastor_id']) && !isset($_SESSION['admin_id'])) {
    die("Unauthorized access.");
}

$villages       = ['Akoritho', 'Philadelphia', 'Bethsaida'];
$village_colors = ['Akoritho' => '#6366f1', 'Philadelphia' => '#0ea5e9', 'Bethsaida' => '#10b981'];
$print_date     = date('F j, Y \a\t g:i A');

/* ── Helper: file → base64 data-URI ─────────────────────────── */
function img_b64(string $filename): string {
    $paths = [
        'uploads/' . basename($filename),
        $filename,
    ];
    foreach ($paths as $p) {
        if ($p && file_exists($p)) {
            $ext  = strtolower(pathinfo($p, PATHINFO_EXTENSION));
            $mime = $ext === 'png' ? 'image/png' : ($ext === 'gif' ? 'image/gif' : 'image/jpeg');
            return "data:$mime;base64," . base64_encode(file_get_contents($p));
        }
    }
    // fallback default avatar
    if (file_exists('uploads/default_avatar.png')) {
        return "data:image/png;base64," . base64_encode(file_get_contents('uploads/default_avatar.png'));
    }
    return '';
}

/* ── Logo ────────────────────────────────────────────────────── */
$logo_b64 = img_b64('church_logo.jpg');

/* ── Fetch village leaders (members table) ───────────────────── */
$leaders = [];
foreach ($villages as $v) {
    $vs = $conn->real_escape_string($v);
    $q  = $conn->query(
        "SELECT first_name, last_name, church_role, profile_picture
         FROM members WHERE church_village='$vs' AND is_village_leader=1 LIMIT 1"
    );
    $leaders[$v] = ($q && $q->num_rows) ? $q->fetch_assoc() : null;
}

/* ── Fetch all pastors that have a village (they count as members) ── */
$pastor_rows = [];
$pq = $conn->query(
    "SELECT id, first_name, last_name, church_village, desired_role_pref,
            profile_picture, department, role AS church_role, phone
     FROM pastors
     WHERE church_village IS NOT NULL AND church_village != '' AND is_approved = 1"
);
if ($pq) {
    while ($p = $pq->fetch_assoc()) {
        $pastor_rows[$p['church_village']][] = $p;
    }
}

/* ── Cleaners + Cookers ──────────────────────────────────────── */
$cleaners_q = $conn->query(
    "SELECT first_name, last_name, church_village, department, church_role, phone, profile_picture, 'Member' AS person_type
     FROM members WHERE desired_role_pref='Church Cleaner' AND is_approved=1
     ORDER BY church_village, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, first_name"
);
$cookers_q = $conn->query(
    "SELECT first_name, last_name, church_village, department, church_role, phone, profile_picture, 'Member' AS person_type
     FROM members WHERE desired_role_pref='Church Cooker' AND is_approved=1
     ORDER BY church_village, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, first_name"
);

// Also include pastors who chose cleaner/cooker
$pastor_cleaners_q = $conn->query(
    "SELECT first_name, last_name, church_village, department, role AS church_role, phone, profile_picture, 'Pastor' AS person_type
     FROM pastors WHERE desired_role_pref='Church Cleaner' AND is_approved=1"
);
$pastor_cookers_q = $conn->query(
    "SELECT first_name, last_name, church_village, department, role AS church_role, phone, profile_picture, 'Pastor' AS person_type
     FROM pastors WHERE desired_role_pref='Church Cooker' AND is_approved=1"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>E.A.P.C Munyari — All Church Villages</title>
<?php
$print_mode = isset($_GET['mode']) && $_GET['mode'] === 'landscape' ? 'landscape' : 'portrait';
?>
<style>
* { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; box-sizing: border-box; }
@media print {
    @page { size: A4 <?= $print_mode ?>; margin: 0; }
    .no-print  { display: none !important; }
    .page-break { page-break-before: always; }
}
body { font-family: Arial, sans-serif; margin: 0; padding: 14px; color: #111; font-size: 12px; }

/* Watermark */
.watermark {
    position: fixed; top: 50%; left: 50%;
    transform: translate(-50%,-50%) rotate(-45deg);
    font-size: 3.0rem; color: rgba(30,58,138,0.15);
    font-weight: bold; white-space: nowrap;
    z-index: 999999; pointer-events: none;
    letter-spacing: 4px; text-transform: uppercase;
    mix-blend-mode: multiply;
}

/* Top header */
.main-header {
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 3px solid #1e3a8a; padding-bottom: 10px; margin-bottom: 14px; gap: 10px;
}
.main-header img.logo { width: 60px; height: 60px; object-fit: contain; flex-shrink: 0; }
.main-header .ct { text-align: center; flex: 1; min-width: 0; }
.main-header h1 { margin: 0; font-size: 1.05rem; color: #1e3a8a; text-transform: uppercase; }
.main-header h2 { margin: 2px 0; font-size: 0.88rem; color: #1e3a8a; }
.main-header p  { margin: 1px 0; font-size: 0.72rem; color: #555; }

/* Village leaders bar */
.leaders-bar {
    display: flex; justify-content: center; gap: 24px;
    margin-bottom: 16px; padding: 10px 16px;
    background: #f0f4ff; border-radius: 10px;
    border: 1px solid #c7d2fe;
}
.lcard { text-align: center; }
.lcard img, .lcard .avatar-ph {
    width: 62px; height: 62px; border-radius: 50%; object-fit: cover;
    border: 3px solid #1e3a8a; display: block; margin: 0 auto 4px;
}
.lcard .avatar-ph {
    background: #dbeafe; display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; line-height: 1;
}
.lcard .ln  { font-size: 0.75rem; font-weight: 800; color: #1e3a8a; text-transform: uppercase; }
.lcard .lv  { font-size: 0.68rem; font-weight: 600; margin-top: 1px; }
.lcard .lr  { font-size: 0.64rem; color: #888; font-style: italic; }

/* Section badge */
.sec-badge {
    display: inline-block; font-size: 0.82rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: 0.8px;
    color: white; padding: 5px 14px; border-radius: 6px;
    margin: 14px 0 8px;
}

/* Table */
table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
thead tr th {
    background: #1e3a8a; color: white;
    padding: 5px 7px; font-size: 0.71rem; text-align: left;
}
tbody tr td { padding: 4px 7px; border-bottom: 1px solid #e2e8f0; font-size: 0.73rem; vertical-align: middle; }
tbody tr:nth-child(even) td { background: #f8fafc; }

/* Leader row highlight */
.lrow td { background: #dbeafe !important; font-weight: 700 !important; }
/* Pastor row */
.prow td { background: #fef9c3 !important; }

/* Profile photo cell */
.photo-cell img, .photo-cell .ph {
    width: 34px; height: 34px; border-radius: 50%; object-fit: cover;
    border: 1px solid #c7d2fe; display: block;
}
.photo-cell .ph {
    background: #e0e7ff; display: flex; align-items: center;
    justify-content: center; font-size: 0.9rem; border: 1px solid #c7d2fe;
}

.badge { display: inline-block; padding: 1px 6px; border-radius: 10px; font-size: 0.65rem; font-weight: 700; }

/* Footer */
.footer {
    position: fixed; bottom: 0; left: 0; right: 0;
    text-align: center; font-size: 9px; color: #777; font-style: italic;
    border-top: 1px solid #e5e7eb; padding: 4px 0;
    background: rgba(255,255,255,0.95);
}

/* No-print toolbar */
.no-print {
    display: flex; justify-content: center; gap: 12px;
    padding: 10px; background: #f1f5f9; margin-bottom: 14px; border-radius: 8px;
}
.no-print button, .no-print a {
    padding: 9px 20px; border: none; border-radius: 8px; cursor: pointer;
    font-size: 13px; font-weight: bold; color: white; text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px; font-family: Arial, sans-serif;
}
</style>
</head>
<body>
<div class="watermark">E.A.P.C MUNYARI CHURCH</div>

<!-- toolbar (hidden on print) -->
<div class="no-print">
    <?php if ($print_mode === 'landscape'): ?>
        <a href="?mode=portrait" style="background:#475569;">📄 Switch to Portrait Mode</a>
    <?php else: ?>
        <a href="?mode=landscape" style="background:#475569;">🖥️ Switch to Landscape Mode</a>
    <?php endif; ?>
    <button onclick="window.print()" style="background:linear-gradient(135deg,#2563eb,#6366f1);">🖨 Print / Save PDF</button>
    <button onclick="window.close()" style="background:#dc2626;">✕ Close</button>
</div>

<!-- ═══ MAIN HEADER ═══ -->
<div class="main-header">
    <?php if ($logo_b64): ?><img class="logo" src="<?= $logo_b64 ?>" alt="Logo"><?php endif; ?>
    <div class="ct">
        <h1>E.A.P.C Munyari Church</h1>
        <h2>Church Villages — Members Only</h2>
        <p>Printed on: <?= $print_date ?></p>
    </div>
    <?php if ($logo_b64): ?><img class="logo" src="<?= $logo_b64 ?>" alt="Logo"><?php endif; ?>
</div>

<!-- ═══ VILLAGE LEADERS PROFILE BAR ═══ -->
<div class="leaders-bar">
    <?php foreach ($villages as $v):
        $l  = $leaders[$v];
        $vc = $village_colors[$v];
    ?>
    <div class="lcard">
        <?php if ($l):
            $pb = img_b64($l['profile_picture'] ?? '');
        ?>
        <?php if ($pb): ?>
            <img src="<?= $pb ?>" alt="">
        <?php else: ?>
            <img src="<?= img_b64('default_avatar.png') ?>" alt="" style="opacity:0.8; filter: grayscale(50%);">
        <?php endif; ?>
        <div class="ln"><?= htmlspecialchars($l['first_name'] . ' ' . $l['last_name']) ?></div>
        <div class="lv" style="color:<?= $vc ?>;"><?= $v ?> — Village Leader</div>
        <?php else: ?>
        <img src="<?= img_b64('default_avatar.png') ?>" alt="" style="opacity:0.8; filter: grayscale(50%);">
        <div class="ln" style="color:#aaa;">No Leader Yet</div>
        <div class="lv" style="color:<?= $vc ?>;"><?= $v ?> Village</div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php
/* ═══════════════════════════════════════════════════════
   SECTION 1 — Each Village: Leader → Members → Pastors
   ═══════════════════════════════════════════════════════ */
foreach ($villages as $v):
    $vc  = $village_colors[$v];
    $vs  = $conn->real_escape_string($v);

    // All approved members of this village, leader first
    $mq  = $conn->query(
        "SELECT first_name, last_name, department, church_role, phone,
                desired_role_pref, is_village_leader, profile_picture,
                'member' AS ptype
         FROM members
         WHERE church_village='$vs' AND is_approved=1
         ORDER BY is_village_leader DESC, (church_role IS NOT NULL AND TRIM(church_role) != '' AND church_role != 'Member') DESC, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, first_name ASC"
    );
    $members_arr = [];
    if ($mq) { while ($r = $mq->fetch_assoc()) $members_arr[] = $r; }

    // Pastors in this village
    $pastors_this = $pastor_rows[$v] ?? [];
    foreach ($pastors_this as &$pr) { $pr['ptype'] = 'pastor'; $pr['is_village_leader'] = 0; }
    unset($pr);

    // Merge: members first (leader already at top), pastors after
    
    $leader_arr = [];
    $reg_members = [];
    foreach ($members_arr as $m) {
        if (!empty($m['is_village_leader'])) {
            $leader_arr[] = $m;
        } else {
            $reg_members[] = $m;
        }
    }
    $all_rows = array_merge($leader_arr, $pastors_this, $reg_members);

    $total    = count($all_rows);
?>
<div style="margin-bottom:18px;">
    <div style="text-align:center;"><div class="sec-badge" style="background:<?= $vc ?>;"><?= htmlspecialchars($v) ?> Village — <?= $total ?> Member<?= $total != 1 ? 's' : '' ?></div></div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Photo</th>
                <th>Full Name</th>
                <th>Phone</th>
                <th>Church Role</th>
                <th>Chosen Service</th>
                <th>Department</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($total > 0):
            $i = 1;
            foreach ($all_rows as $row):
                $is_leader  = !empty($row['is_village_leader']);
                $is_pastor  = ($row['ptype'] === 'pastor');
                $dsr        = $row['desired_role_pref'] ?? '';
                $dsr_c      = ['Worshipper'=>'#8b5cf6','Church Cleaner'=>'#0ea5e9','Church Cooker'=>'#f59e0b'][$dsr] ?? '#94a3b8';
                $pic_b64    = img_b64($row['profile_picture'] ?? '');
                $row_class  = $is_leader ? 'lrow' : ($is_pastor ? 'prow' : '');
        ?>
            <tr class="<?= $row_class ?>">
                <td style="color:#888;"><?= $i++ ?></td>
                <td class="photo-cell">
                    <?php if ($pic_b64): ?>
                        <img src="<?= $pic_b64 ?>" alt="">
                    <?php else: ?>
                        <img src="<?= img_b64('default_avatar.png') ?>" alt="" style="opacity:0.8; filter: grayscale(50%);">
                    <?php endif; ?>
                </td>
                <td style="font-weight:<?= $is_leader || $is_pastor ? '800' : '600' ?>;">
                    <span style="text-transform:uppercase;"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></span>
                    <?php if ($is_leader): ?>
                        <span class="badge" style="background:<?= $vc ?>22;color:<?= $vc ?>;">Village Leader</span>
                    <?php elseif ($is_pastor): ?>
                        <span style="color:#2563eb; font-weight:900;">(PASTOR)</span>
                    <?php endif; ?>
                </td>
                <td style="color:#666; font-weight:600;"><?= htmlspecialchars($row['phone'] ?? '—') ?></td>
                <td><span class="badge" style="background:#e0e7ff; color:#1e3a8a; padding:3px 8px; border-radius:12px; font-weight:700; font-size:0.75rem;"><?= htmlspecialchars($is_pastor ? 'Pastor' : ($row['church_role'] ?: 'Member')) ?></span></td>
                <td>
                    <?php if ($dsr): ?>
                        <span class="badge" style="background:<?= $dsr_c ?>22;color:<?= $dsr_c ?>;"><?= htmlspecialchars($dsr) ?></span>
                    <?php else: ?>
                        <span style="color:#bbb;">—</span>
                    <?php endif; ?>
                </td>
                <td><span class="badge" style="background:#f1f5f9; color:#475569; padding:2px 6px; border-radius:4px; font-weight:600; font-size:0.75rem;"><?= htmlspecialchars($row['department'] ?: 'General Church') ?></span></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="7" style="text-align:center;color:#aaa;padding:12px;">No members in <?= htmlspecialchars($v) ?> Village yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>



<div style="display:flex; justify-content:space-between; align-items:flex-end; margin-top:60px; padding:0 20px; page-break-inside:avoid; gap: 20px;">
    
    <div style="text-align:center; flex:1;">
        <div style="font-size:0.85rem; font-weight:bold; text-transform:uppercase; color:#1e3a8a; margin-bottom:15px;">Church Secretary</div>
        <div style="font-size:0.9rem; margin-bottom:8px; display:flex; align-items:flex-end; justify-content:center; gap:8px;">
            <span style="font-style:italic;color:#333;">Sign:</span>
            <span style="display:inline-block; border-bottom:1px solid #000; width:150px; height:14px;"></span>
        </div>
        <div style="font-size:0.85rem; margin-top:12px; display:flex; align-items:flex-end; justify-content:center; gap:8px;">
            <span style="color:#333;">Date:</span>
            <span style="display:inline-block; border-bottom:1px dotted #000; width:150px; height:14px;"></span>
        </div>
    </div>

    <div style="text-align:center; flex:1;">
        <div style="font-size:0.85rem; font-weight:bold; text-transform:uppercase; color:#1e3a8a; margin-bottom:15px;">Church Chairman</div>
        <div style="font-size:0.9rem; margin-bottom:8px; display:flex; align-items:flex-end; justify-content:center; gap:8px;">
            <span style="font-style:italic;color:#333;">Sign:</span>
            <span style="display:inline-block; border-bottom:1px solid #000; width:150px; height:14px;"></span>
        </div>
        <div style="font-size:0.85rem; margin-top:12px; display:flex; align-items:flex-end; justify-content:center; gap:8px;">
            <span style="color:#333;">Date:</span>
            <span style="display:inline-block; border-bottom:1px dotted #000; width:150px; height:14px;"></span>
        </div>
    </div>

    <div style="text-align:center; flex:1;">
        <div style="font-size:0.85rem; font-weight:bold; text-transform:uppercase; color:#1e3a8a; margin-bottom:15px;">Church Pastor</div>
        <div style="font-size:0.9rem; margin-bottom:8px; display:flex; align-items:flex-end; justify-content:center; gap:8px;">
            <span style="font-style:italic;color:#333;">Sign:</span>
            <span style="display:inline-block; border-bottom:1px solid #000; width:150px; height:14px;"></span>
        </div>
        <div style="font-size:0.85rem; margin-top:12px; display:flex; align-items:flex-end; justify-content:center; gap:8px;">
            <span style="color:#333;">Date:</span>
            <span style="display:inline-block; border-bottom:1px dotted #000; width:150px; height:14px;"></span>
        </div>
    </div>

    <div style="text-align:center; margin-left: 20px;">
        <div style="border:2px dashed #aaa; width:120px; height:120px; border-radius:50%; margin:0 auto; display:flex; align-items:center; justify-content:center; color:#ccc; font-size:0.8rem; text-transform:uppercase; letter-spacing:1px; line-height:1.4;">Official<br>Stamp</div>
    </div>

</div>

<div class="footer">
    Generated from E.A.P.C Munyari Church Portal &nbsp;|&nbsp; Printed on: <?= $print_date ?>
</div>
<script>
window.onload = function() { setTimeout(function() { window.print(); }, 700); };
</script>
</body>
</html>
