<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['pastor_id']) && !isset($_SESSION['admin_id'])) {
    die("Unauthorized access.");
}

$print_date = date('F j, Y \a\t g:i A');
$village_colors = ['Akoritho' => '#6366f1', 'Philadelphia' => '#0ea5e9', 'Bethsaida' => '#10b981'];

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

/* ── Cleaners + Cookers ──────────────────────────────────────── */
$cleaners_q = $conn->query(
    "SELECT first_name, last_name, church_village, department, church_role, phone, profile_picture, 'Member' AS person_type
     FROM members WHERE desired_role_pref='Church Cleaner' AND is_approved=1
     ORDER BY church_village, first_name"
);
$cookers_q = $conn->query(
    "SELECT first_name, last_name, church_village, department, church_role, phone, profile_picture, 'Member' AS person_type
     FROM members WHERE desired_role_pref='Church Cooker' AND is_approved=1
     ORDER BY church_village, first_name"
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
<title>E.A.P.C Munyari — Church Service Volunteers</title>
<?php
$print_mode = isset($_GET['mode']) && $_GET['mode'] === 'landscape' ? 'landscape' : 'portrait';
?>
<style>
* { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; box-sizing: border-box; }
@media print {
    @page { size: A4 <?= $print_mode ?>; margin: 10mm 12mm 16mm 12mm; }
    .no-print  { display: none !important; }
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

<!-- ═══ COMBINED: Cleaners & Cookers Header ═══ -->
<div class="main-header" style="margin-top:0;">
    <?php if ($logo_b64): ?><img class="logo" src="<?= $logo_b64 ?>" alt="Logo"><?php endif; ?>
    <div class="ct">
        <h1>E.A.P.C Munyari Church</h1>
        <h2>Church Service Volunteers — Cleaners &amp; Cookers</h2>
        <p>Printed on: <?= $print_date ?></p>
    </div>
    <?php if ($logo_b64): ?><img class="logo" src="<?= $logo_b64 ?>" alt="Logo"><?php endif; ?>
</div>

<?php
/* ═══ SECTION 1 — Church Cleaners ═══ */
$all_cleaners = [];
if ($cleaners_q)       { while ($r = $cleaners_q->fetch_assoc())       $all_cleaners[] = $r; }
if ($pastor_cleaners_q){ while ($r = $pastor_cleaners_q->fetch_assoc()) $all_cleaners[] = $r; }
$cl_count = count($all_cleaners);
?>
<div style="margin-bottom:18px;">
    <div class="sec-badge" style="background:#0ea5e9;">🧹 Church Cleaners — All Villages (<?= $cl_count ?> Volunteer<?= $cl_count != 1 ? 's' : '' ?>)</div>
    <table>
        <thead>
            <tr><th>#</th><th>Photo</th><th>Full Name</th><th>Village</th><th>Department</th><th>Church Role</th><th>Phone</th></tr>
        </thead>
        <tbody>
        <?php if ($cl_count > 0):
            $i = 1;
            foreach ($all_cleaners as $cl):
                $vc     = $village_colors[$cl['church_village']] ?? '#94a3b8';
                $pic_b64 = img_b64($cl['profile_picture'] ?? '');
                $is_past = ($cl['person_type'] ?? '') === 'Pastor';
        ?>
            <tr <?= $is_past ? 'class="prow"' : '' ?>>
                <td><?= $i++ ?></td>
                <td class="photo-cell">
                    <?php if ($pic_b64): ?><img src="<?= $pic_b64 ?>" alt=""><?php else: ?><div class="ph">👤</div><?php endif; ?>
                </td>
                <td style="font-weight:600;">
                    <?= htmlspecialchars(ucfirst($cl['first_name']) . ' ' . ucfirst($cl['last_name'])) ?>
                    <?php if ($is_past): ?><span class="badge" style="background:#fef08a;color:#92400e;">Pastor</span><?php endif; ?>
                </td>
                <td><span class="badge" style="background:<?= $vc ?>22;color:<?= $vc ?>;"><?= htmlspecialchars($cl['church_village'] ?: '—') ?></span></td>
                <td><?= htmlspecialchars($cl['department'] ?: 'General Church') ?></td>
                <td><?= htmlspecialchars($cl['church_role'] ?: 'Member') ?></td>
                <td style="color:#666;"><?= htmlspecialchars($cl['phone'] ?? '—') ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="7" style="text-align:center;color:#aaa;padding:12px;">No Church Cleaners have volunteered yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
/* ═══ SECTION 2 — Church Cookers ═══ */
$all_cookers = [];
if ($cookers_q)       { while ($r = $cookers_q->fetch_assoc())       $all_cookers[] = $r; }
if ($pastor_cookers_q){ while ($r = $pastor_cookers_q->fetch_assoc()) $all_cookers[] = $r; }
$ck_count = count($all_cookers);
?>
<div style="margin-bottom:18px;">
    <div class="sec-badge" style="background:#f59e0b;">🍳 Church Cookers — All Villages (<?= $ck_count ?> Volunteer<?= $ck_count != 1 ? 's' : '' ?>)</div>
    <table>
        <thead>
            <tr><th>#</th><th>Photo</th><th>Full Name</th><th>Village</th><th>Department</th><th>Church Role</th><th>Phone</th></tr>
        </thead>
        <tbody>
        <?php if ($ck_count > 0):
            $i = 1;
            foreach ($all_cookers as $ck):
                $vc     = $village_colors[$ck['church_village']] ?? '#94a3b8';
                $pic_b64 = img_b64($ck['profile_picture'] ?? '');
                $is_past = ($ck['person_type'] ?? '') === 'Pastor';
        ?>
            <tr <?= $is_past ? 'class="prow"' : '' ?>>
                <td><?= $i++ ?></td>
                <td class="photo-cell">
                    <?php if ($pic_b64): ?><img src="<?= $pic_b64 ?>" alt=""><?php else: ?><div class="ph">👤</div><?php endif; ?>
                </td>
                <td style="font-weight:600;">
                    <?= htmlspecialchars(ucfirst($ck['first_name']) . ' ' . ucfirst($ck['last_name'])) ?>
                    <?php if ($is_past): ?><span class="badge" style="background:#fef08a;color:#92400e;">Pastor</span><?php endif; ?>
                </td>
                <td><span class="badge" style="background:<?= $vc ?>22;color:<?= $vc ?>;"><?= htmlspecialchars($ck['church_village'] ?: '—') ?></span></td>
                <td><?= htmlspecialchars($ck['department'] ?: 'General Church') ?></td>
                <td><?= htmlspecialchars($ck['church_role'] ?: 'Member') ?></td>
                <td style="color:#666;"><?= htmlspecialchars($ck['phone'] ?? '—') ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="7" style="text-align:center;color:#aaa;padding:12px;">No Church Cookers have volunteered yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="footer">
    Generated from E.A.P.C Munyari Church Portal &nbsp;|&nbsp; Printed on: <?= $print_date ?>
</div>
<script>
window.onload = function() { setTimeout(function() { window.print(); }, 700); };
</script>
</body>
</html>
