<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['pastor_id']) && !isset($_SESSION['admin_id'])) {
    die("Unauthorized access.");
}

$mode = isset($_GET['mode']) && $_GET['mode'] === 'landscape' ? 'landscape' : 'portrait';
$print_date = date('F j, Y \a\t g:i A');

function img_b64(string $filename): string {
    $paths = ['uploads/' . basename($filename), $filename];
    foreach ($paths as $p) {
        if ($p && file_exists($p)) {
            $ext  = strtolower(pathinfo($p, PATHINFO_EXTENSION));
            $mime = $ext === 'png' ? 'image/png' : ($ext === 'gif' ? 'image/gif' : 'image/jpeg');
            return "data:$mime;base64," . base64_encode(file_get_contents($p));
        }
    }
    if (file_exists('uploads/default_avatar.png')) {
        return "data:image/png;base64," . base64_encode(file_get_contents('uploads/default_avatar.png'));
    }
    return '';
}

$logo_b64 = img_b64('church_logo.jpg');

// Fetch active pastor
$pastor_q    = $conn->query("SELECT first_name, last_name FROM pastors WHERE is_approved = 1 ORDER BY id ASC LIMIT 1");
$pastor_row  = $pastor_q ? $pastor_q->fetch_assoc() : null;
$pastor_name = $pastor_row ? strtoupper($pastor_row['first_name'] . ' ' . $pastor_row['last_name']) : 'Not Assigned';

// Fetch Worship Leader (Head) - not vice
$wl_q = $conn->query("SELECT first_name, last_name, profile_picture, church_role FROM members WHERE LOWER(church_role) LIKE '%worship leader%' AND LOWER(church_role) NOT LIKE '%vice%' AND is_approved=1 ORDER BY id ASC LIMIT 1");
$worship_leader = $wl_q ? $wl_q->fetch_assoc() : null;

// Fetch Vice Worship Leader
$vwl_q = $conn->query("SELECT first_name, last_name, profile_picture, church_role FROM members WHERE LOWER(church_role) LIKE '%vice worship leader%' AND is_approved=1 ORDER BY id ASC LIMIT 1");
$vice_worship_leader = $vwl_q ? $vwl_q->fetch_assoc() : null;

// Fetch all worshippers sorted by department then alphabetically A-Z
$all_worshippers = $conn->query("
    SELECT id, first_name, last_name, phone, address, church_village, department, church_role, desired_role_pref, is_approved, profile_picture
    FROM members
    WHERE (LOWER(TRIM(church_role)) LIKE '%worshipper%'
       OR LOWER(TRIM(department)) LIKE '%worship%'
       OR desired_role_pref = 'Worshipper')
    AND is_approved = 1
    ORDER BY department ASC, first_name ASC, last_name ASC
");
$worshippers = [];
if ($all_worshippers) {
    while ($r = $all_worshippers->fetch_assoc()) $worshippers[] = $r;
}
$total = count($worshippers);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>E.A.P.C Munyari - Worship Department</title>
<style>
@page { size: <?= $mode === 'landscape' ? 'A4 landscape' : 'A4 portrait' ?>; margin: 15mm 12mm 18mm; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; font-size: 12px; color: #111; background: #fff; }

/* Watermark */
.watermark {
    position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg);
    font-size: 5rem; font-weight: 900; color: rgba(139,92,246,0.06);
    text-transform: uppercase; white-space: nowrap; pointer-events: none; z-index: 0;
    letter-spacing: 4px;
}

/* Header */
.main-header {
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 3px solid #8b5cf6; padding-bottom: 10px; margin-bottom: 14px;
}
.main-header .logo { width: 60px; height: 60px; object-fit: contain; }
.main-header .ct { text-align: center; flex: 1; }
.main-header .ct h1 { font-size: 1.1rem; color: #1e3a8a; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; }
.main-header .ct h2 { font-size: 0.95rem; color: #8b5cf6; font-weight: 700; }
.main-header .ct p { font-size: 0.7rem; color: #777; margin-top: 2px; }

/* Leadership banner */
.leaders-bar {
    display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;
    margin-bottom: 14px; padding: 10px 16px;
    background: transparent;
    border: none;
}
.leader-card {
    display: flex; align-items: center; gap: 10px;
    background: transparent; border-radius: 0; padding: 8px 16px;
    border: none; min-width: 200px;
}
.leader-card img { width: 44px; height: 44px; border-radius: 50% !important; object-fit: cover !important; border: none !important; overflow: hidden !important; -webkit-clip-path: circle(50% at 50% 50%) !important; clip-path: circle(50% at 50% 50%) !important; }
.leader-card .ph { width: 44px; height: 44px; border-radius: 50%; background: #ede9fe; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; border: none; flex-shrink: 0; }
.leader-card .lrole { font-size: 0.65rem; font-weight: 800; color: #8b5cf6; text-transform: uppercase; letter-spacing: 0.5px; }
.leader-card .lname { font-size: 0.88rem; font-weight: 700; color: #1e1b4b; }

/* Section ribbon */
.sec-badge {
    display: block; text-align: center; font-size: 1rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: 0.8px; color: white;
    padding: 6px 14px; border-radius: 6px; margin: 14px 0 8px;
    background: linear-gradient(135deg, #8b5cf6, #6d28d9);
}

/* Stats */
.stats-row { display: flex; gap: 10px; justify-content: center; margin-bottom: 12px; flex-wrap: wrap; }
.stat-pill { background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 20px; padding: 4px 14px; font-size: 0.78rem; font-weight: 700; color: #6d28d9; }

/* Table */
table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
thead tr th { background: #8b5cf6; color: white; padding: 5px 7px; font-size: 0.71rem; text-align: left; }
tbody tr td { padding: 4px 7px; border-bottom: 1px solid #e2e8f0; font-size: 0.73rem; vertical-align: middle; }
tbody tr:nth-child(even) td { background: #faf5ff; }
.photo-cell img { width: 32px; height: 32px; border-radius: 50% !important; object-fit: cover !important; display: block; border: none !important; overflow: hidden !important; -webkit-clip-path: circle(50% at 50% 50%) !important; clip-path: circle(50% at 50% 50%) !important; }
.badge { display: inline-block; padding: 1px 6px; border-radius: 10px; font-size: 0.65rem; font-weight: 700; }

/* Signatures */
.sig-section { display: flex; justify-content: space-between; margin-top: 50px; page-break-inside: avoid; }
.sig-box { width: 45%; }
.sig-title { font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; margin-bottom: 3px; }
.sig-name  { font-size: 13px; font-weight: 700; text-transform: uppercase; text-align: center; margin-bottom: 18px; }
.sig-row   { display: flex; align-items: flex-end; margin-bottom: 14px; }
.sig-label { width: 70px; font-size: 13px; font-weight: 600; color: #333; text-align: left; }
.sig-line  { flex: 1; border-bottom: 1px solid #000; height: 20px; }
.sig-line-dashed { flex: 1; border-bottom: 1px dashed #555; height: 20px; }
.stamp-circle { width: 140px; height: 140px; border: 2px dashed #aaa; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; color: #aaa; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; line-height: 2; margin: 0 auto; }

/* Footer */
.footer { text-align: center; font-size: 9px; color: #777; font-style: italic; border-top: 1px solid #e5e7eb; padding: 4px 0; margin-top: 20px; }

/* No-print toolbar */
.no-print { display: flex; justify-content: center; gap: 12px; padding: 10px; background: #f1f5f9; margin-bottom: 14px; border-radius: 8px; }
.no-print button, .no-print a { padding: 9px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: bold; color: white; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-family: Arial, sans-serif; }
@media print { .no-print { display: none !important; } }
/* Role pills */
.role-pill { display:inline-flex;align-items:center;font-size:0.68rem;font-weight:700;padding:2px 8px;border-radius:20px;white-space:nowrap;border:1px solid transparent;margin:1px; }
.rp-worship { background:#fdf2f8;color:#be185d;border-color:#fbcfe8; }
.rp-vice    { background:#fdf4ff;color:#9333ea;border-color:#f0abfc; }
.rp-member  { background:#f1f5f9;color:#64748b;border-color:#cbd5e1; }
.rp-other   { background:#f8fafc;color:#475569;border-color:#e2e8f0; }
.role-pills-wrap { display:flex;flex-wrap:wrap;gap:2px; }
.pref-pill { display:inline-block;font-size:0.67rem;font-weight:700;padding:2px 8px;border-radius:12px;background:#fef3c7;color:#92400e;border:1px solid #fde68a; }
</style>
</head>
<body>
<div class="watermark">E.A.P.C MUNYARI CHURCH</div>

<div class="no-print">
    <?php if ($mode === 'landscape'): ?>
        <a href="?mode=portrait" style="background:#475569;">Switch to Portrait</a>
    <?php else: ?>
        <a href="?mode=landscape" style="background:#475569;">Switch to Landscape</a>
    <?php endif; ?>
    <button onclick="window.print()" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);">&#128424; Print / Save PDF</button>
    <button onclick="window.close()" style="background:#dc2626;">&#10006; Close</button>
</div>


    
<!-- Worship Leaders Banner — always shown -->
<?php
$default_b64 = img_b64('uploads/default_avatar.png') ?: img_b64('default_avatar.png');
$wl_pic  = $worship_leader       ? img_b64($worship_leader['profile_picture'] ?? '')       : '';
$vwl_pic = $vice_worship_leader  ? img_b64($vice_worship_leader['profile_picture'] ?? '')  : '';
// Fallback to default avatar
if (!$wl_pic)  $wl_pic  = $default_b64;
if (!$vwl_pic) $vwl_pic = $default_b64;
$wl_name  = $worship_leader      ? ucfirst($worship_leader['first_name'])      . ' ' . ucfirst($worship_leader['last_name'])      : 'Not Assigned';
$vwl_name = $vice_worship_leader ? ucfirst($vice_worship_leader['first_name']) . ' ' . ucfirst($vice_worship_leader['last_name']) : 'Not Assigned';
?>
<div class="leaders-bar">
    <div class="leader-card">
        <?php if ($wl_pic): ?><div style="width:44px;height:44px;border-radius:50%;overflow:hidden;border:none;flex-shrink:0;"><img src="<?= $wl_pic ?>" alt="" style="width:100%;height:100%;object-fit:cover;border:none;"></div><?php else: ?><div class="ph">&#127925;</div><?php endif; ?>
        <div>
            <div class="lrole">Worship Leader</div>
            <div class="lname"><?= htmlspecialchars($wl_name) ?></div>
        </div>
    </div>
    <div class="leader-card">
        <?php if ($vwl_pic): ?><div style="width:44px;height:44px;border-radius:50%;overflow:hidden;border:none;flex-shrink:0;"><img src="<?= $vwl_pic ?>" alt="" style="width:100%;height:100%;object-fit:cover;border:none;"></div><?php else: ?><div class="ph">&#127926;</div><?php endif; ?>
        <div>
            <div class="lrole">Vice Worship Leader</div>
            <div class="lname"><?= htmlspecialchars($vwl_name) ?></div>
        </div>
    </div>
</div>

<div class="main-header">
    <?php if ($logo_b64): ?><img class="logo" src="<?= $logo_b64 ?>" alt="Logo"><?php endif; ?>
    <div class="ct">
        <h1>E.A.P.C Munyari Church</h1>
        <h2>Worship Department &mdash; Members List</h2>
        <p>Printed on: <?= $print_date ?></p>
    </div>

<?php if ($logo_b64): ?><img class="logo" src="<?= $logo_b64 ?>" alt="Logo"><?php endif; ?>
</div>

<!-- Stats -->
<div class="stats-row">
    <span class="stat-pill">Total Worshippers: <?= $total ?></span>
</div>

<div class="sec-badge">Worship Department &mdash; Members Directory</div>

<?php if ($total > 0): ?>
<table>
    <thead>
        <tr>
            <th>#</th><th>Photo</th><th>Full Name</th><th>Village</th>
            <th>Dept</th><th>Phone</th><th>Residence</th><th>Preference</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $i = 1;
    foreach ($worshippers as $ws):
        $pic_b64 = img_b64($ws['profile_picture'] ?? '');
    ?>
    <tr>
        <td style="color:#888;"><?= $i++ ?></td>
        <td class="photo-cell">
            <div style="width:32px;height:32px;border-radius:50%;overflow:hidden;border:none;display:inline-block;vertical-align:middle;background:#f5f3ff;">
                <?php if ($pic_b64): ?><img src="<?= $pic_b64 ?>" alt="" style="width:100%;height:100%;object-fit:cover;border:none;"><?php else: ?><img src="<?= img_b64('uploads/default_avatar.png') ?>" alt="" style="width:100%;height:100%;object-fit:cover;border:none;"><?php endif; ?>
            </div>
        </td>
        <td style="font-weight:700;text-transform:uppercase;"><?= htmlspecialchars($ws['first_name'] . ' ' . $ws['last_name']) ?></td>
        <td><span class="badge" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;"><?= htmlspecialchars($ws['church_village'] ?: '-') ?></span></td>
        <td><span class="badge" style="background:#ede9fe;color:#6d28d9;"><?= htmlspecialchars($ws['department'] ?: 'General') ?></span></td>
        <td style="color:#666;"><?= htmlspecialchars($ws['phone'] ?? '-') ?></td>
        <td style="color:#555;font-size:0.7rem;"><?= htmlspecialchars($ws['address'] ?? '-') ?></td>
        <td><span class="pref-pill"><?= htmlspecialchars($ws['desired_role_pref'] ?: '-') ?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p style="text-align:center;color:#aaa;padding:30px;">No worshippers registered yet.</p>
<?php endif; ?>

<!-- Signature Block: Pastor (left) | Stamp (center) | Head of Worship (right) -->
<?php
$signer_name  = $worship_leader ? strtoupper($worship_leader['first_name'] . ' ' . $worship_leader['last_name']) : 'NOT ASSIGNED';
$signer_title = 'Head of Worship';
?>
<div style="display:flex;justify-content:space-between;align-items:flex-end;margin-top:50px;page-break-inside:avoid;gap:10px;">

    <!-- LEFT: Pastor -->
    <div style="width:30%;text-align:center;">
        <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">Church Pastor</div>
        <div style="font-size:13px;font-weight:700;text-transform:uppercase;margin-bottom:18px;"><?= htmlspecialchars($pastor_name) ?></div>
        <div style="display:flex;align-items:flex-end;margin-bottom:14px;">
            <span style="font-size:10px;color:#888;white-space:nowrap;margin-right:6px;">Signature :</span>
            <div style="flex:1;border-bottom:1px solid #000;height:14px;"></div>
        </div>
        <div style="display:flex;align-items:flex-end;">
            <span style="font-size:10px;color:#888;white-space:nowrap;margin-right:6px;">Date :</span>
            <div style="flex:1;border-bottom:1px dotted #000;height:14px;"></div>
        </div>
    </div>

    <!-- CENTER: Stamp -->
    <div style="width:30%;display:flex;justify-content:center;align-items:center;">
        <div class="stamp-circle">Official<br>Church<br>Stamp</div>
    </div>

    <!-- RIGHT: Head of Worship -->
    <div style="width:30%;text-align:center;">
        <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;"><?= htmlspecialchars($signer_title) ?></div>
        <div style="font-size:13px;font-weight:700;text-transform:uppercase;margin-bottom:18px;"><?= htmlspecialchars($signer_name) ?></div>
        <div style="display:flex;align-items:flex-end;margin-bottom:14px;">
            <span style="font-size:10px;color:#888;white-space:nowrap;margin-right:6px;">Signature :</span>
            <div style="flex:1;border-bottom:1px solid #000;height:14px;"></div>
        </div>
        <div style="display:flex;align-items:flex-end;">
            <span style="font-size:10px;color:#888;white-space:nowrap;margin-right:6px;">Date :</span>
            <div style="flex:1;border-bottom:1px dotted #000;height:14px;"></div>
        </div>
    </div>

</div>

<div class="footer">
    Generated from E.A.P.C Munyari Church Portal &nbsp;|&nbsp; Printed on: <?= $print_date ?>
</div>
<script>window.onload = function() { setTimeout(function() { window.print(); }, 700); };</script>
</body>
</html>

