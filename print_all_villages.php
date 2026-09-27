<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['pastor_id']) && !isset($_SESSION['admin_id'])) {
    die("Unauthorized access.");
}

$villages = ['Akoritho', 'Philadelphia', 'Bethsaida'];
$village_colors = ['Akoritho' => '#6366f1', 'Philadelphia' => '#0ea5e9', 'Bethsaida' => '#10b981'];

// Helper: convert image file to base64
function img_to_b64($path) {
    if (!$path) return '';
    $candidates = [
        'uploads/' . basename($path),
        $path,
        'uploads/default_avatar.png'
    ];
    foreach ($candidates as $c) {
        if (file_exists($c)) {
            $ext = strtolower(pathinfo($c, PATHINFO_EXTENSION));
            $mime = $ext === 'png' ? 'image/png' : ($ext === 'gif' ? 'image/gif' : 'image/jpeg');
            return "data:$mime;base64," . base64_encode(file_get_contents($c));
        }
    }
    return '';
}

// Fetch all 3 village leaders with their photos
$leaders = [];
foreach ($villages as $v) {
    $vs = $conn->real_escape_string($v);
    $q = $conn->query("SELECT first_name, last_name, church_role, profile_picture FROM members WHERE church_village='$vs' AND is_village_leader=1 LIMIT 1");
    $leaders[$v] = $q ? $q->fetch_assoc() : null;
}

// Logo
$logo_b64 = img_to_b64('church_logo.jpg');
$print_date = date('F j, Y \a\t g:i A');
?>
<!DOCTYPE html>
<html>
<head>
<title>E.A.P.C Munyari — All Church Villages</title>
<style>
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    @media print {
        @page { size: A4 portrait; margin: 10mm 12mm 15mm 12mm; }
        .no-print { display: none !important; }
        .page-break { page-break-before: always; }
    }
    body { font-family: Arial, sans-serif; margin: 0; padding: 15px; color: #111; font-size: 13px; }

    /* Watermark */
    .watermark {
        position: fixed; top: 50%; left: 50%;
        transform: translate(-50%, -50%) rotate(-45deg);
        font-size: 3.0rem; color: rgba(30,58,138,0.18);
        font-weight: bold; white-space: nowrap; z-index: 999999;
        pointer-events: none; letter-spacing: 4px;
        text-transform: uppercase; mix-blend-mode: multiply;
    }

    /* Top header */
    .main-header {
        display: flex; align-items: center; justify-content: space-between;
        border-bottom: 3px solid #1e3a8a; padding-bottom: 12px; margin-bottom: 16px;
        gap: 10px;
    }
    .main-header img.logo { width: 65px; height: 65px; object-fit: contain; flex-shrink: 0; }
    .main-header .center-text { text-align: center; flex: 1; min-width: 0; }
    .main-header h1 { margin: 0; font-size: 1.15rem; color: #1e3a8a; text-transform: uppercase; }
    .main-header h2 { margin: 3px 0; font-size: 0.95rem; color: #1e3a8a; }
    .main-header p  { margin: 2px 0; font-size: 0.75rem; color: #555; }

    /* Village leaders bar */
    .leaders-bar {
        display: flex; justify-content: center; gap: 30px;
        margin-bottom: 20px; padding: 12px;
        background: #f8fafc; border-radius: 10px;
        border: 1px solid #e2e8f0;
    }
    .leader-card { text-align: center; }
    .leader-card img {
        width: 64px; height: 64px; border-radius: 50%; object-fit: cover;
        border: 3px solid #1e3a8a; display: block; margin: 0 auto 5px;
    }
    .leader-card .no-photo {
        width: 64px; height: 64px; border-radius: 50%;
        background: #dbeafe; border: 3px solid #1e3a8a;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem; margin: 0 auto 5px;
    }
    .leader-card .leader-name { font-size: 0.82rem; font-weight: 800; color: #1e3a8a; text-transform: uppercase; }
    .leader-card .leader-village { font-size: 0.72rem; color: #6366f1; font-weight: 600; }
    .leader-card .leader-role { font-size: 0.68rem; color: #888; font-style: italic; }
    .leader-card .no-leader { font-size: 0.75rem; color: #aaa; font-style: italic; padding-top: 20px; }

    /* Section heading */
    .section-title {
        font-size: 0.9rem; font-weight: 800; text-transform: uppercase;
        letter-spacing: 1px; color: white; padding: 6px 14px;
        border-radius: 6px; margin: 18px 0 8px; display: inline-block;
    }

    /* Table */
    table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    thead tr th {
        background: #1e3a8a; color: white; padding: 6px 8px;
        font-size: 0.74rem; text-align: left;
    }
    tbody tr td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; font-size: 0.76rem; vertical-align: middle; }
    tbody tr:nth-child(even) td { background: #f8fafc; }
    .leader-row td { background: #dbeafe !important; font-weight: 700; }
    .badge { display: inline-block; padding: 2px 7px; border-radius: 10px; font-size: 0.68rem; font-weight: 700; }

    /* Footer */
    .footer {
        position: fixed; bottom: 0; left: 0; right: 0;
        text-align: center; font-size: 9.5px; color: #777;
        font-style: italic; border-top: 1px solid #e5e7eb;
        padding: 5px 0; background: rgba(255,255,255,0.95);
    }

    /* No-print button bar */
    .no-print {
        display: flex; justify-content: center; gap: 12px;
        padding: 12px; background: #f1f5f9; margin-bottom: 16px; border-radius: 8px;
    }
    .no-print button {
        padding: 10px 22px; border: none; border-radius: 8px; cursor: pointer;
        font-size: 14px; font-weight: bold; color: white;
    }
</style>
</head>
<body>
<div class="watermark">E.A.P.C MUNYARI CHURCH</div>

<!-- Print buttons -->
<div class="no-print">
    <button onclick="window.print()" style="background: linear-gradient(135deg,#2563eb,#6366f1);">🖨 Print / Save PDF</button>
    <button onclick="window.close()" style="background: #6b7280;">✕ Close</button>
</div>

<!-- Main Header -->
<div class="main-header">
    <?php if ($logo_b64): ?><img class="logo" src="<?= $logo_b64 ?>" alt="Logo"><?php endif; ?>
    <div class="center-text">
        <h1>E.A.P.C Munyari Church</h1>
        <h2>Church Villages — Members, Cleaners &amp; Cookers</h2>
        <p>Printed on: <?= $print_date ?></p>
    </div>
    <?php if ($logo_b64): ?><img class="logo" src="<?= $logo_b64 ?>" alt="Logo"><?php endif; ?>
</div>

<!-- Village Leaders Profile Bar -->
<div class="leaders-bar">
    <?php foreach ($villages as $v):
        $l = $leaders[$v];
        $vc = $village_colors[$v];
    ?>
    <div class="leader-card">
        <?php if ($l):
            $pic_b64 = img_to_b64($l['profile_picture'] ?? '');
        ?>
        <?php if ($pic_b64): ?>
            <img src="<?= $pic_b64 ?>" alt="<?= htmlspecialchars($l['first_name']) ?>">
        <?php else: ?>
            <div class="no-photo">👤</div>
        <?php endif; ?>
        <div class="leader-name"><?= htmlspecialchars($l['first_name'] . ' ' . $l['last_name']) ?></div>
        <div class="leader-village" style="color:<?= $vc ?>;"><?= $v ?> Village Leader</div>
        <div class="leader-role"><?= htmlspecialchars($l['church_role'] ?: 'Village Leader') ?></div>
        <?php else: ?>
        <div class="no-photo">?</div>
        <div class="leader-name" style="color:#aaa;">No Leader Assigned</div>
        <div class="leader-village" style="color:<?= $vc ?>;"><?= $v ?> Village</div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php
// ═══ SECTION 1: Each Village ═══
foreach ($villages as $v):
    $vc = $village_colors[$v];
    $vs = $conn->real_escape_string($v);
    $v_members = $conn->query(
        "SELECT first_name, last_name, department, church_role, phone, desired_role_pref, is_village_leader
         FROM members WHERE church_village='$vs' AND is_approved=1
         ORDER BY is_village_leader DESC, first_name"
    );
    $v_count = $v_members ? $v_members->num_rows : 0;
?>
<div style="margin-bottom: 20px;">
    <div class="section-title" style="background:<?= $vc ?>;"><?= $v ?> Village — <?= $v_count ?> Members</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Full Name</th>
                <th>Department</th>
                <th>Church Role</th>
                <th>Chosen Service</th>
                <th>Phone</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($v_count > 0):
            $i = 1;
            while ($vm = $v_members->fetch_assoc()):
                $dsr = $vm['desired_role_pref'] ?? '';
                $dsr_colors = [
                    'Worshipper'    => '#8b5cf6',
                    'Church Cleaner'=> '#0ea5e9',
                    'Church Cooker' => '#f59e0b',
                ];
                $dc = $dsr_colors[$dsr] ?? '#94a3b8';
                $is_leader = $vm['is_village_leader'] == 1;
        ?>
            <tr class="<?= $is_leader ? 'leader-row' : '' ?>">
                <td><?= $i++ ?></td>
                <td style="font-weight:<?= $is_leader ? '800' : '600' ?>;">
                    <?= htmlspecialchars($vm['first_name'] . ' ' . $vm['last_name']) ?>
                    <?php if ($is_leader): ?> <span class="badge" style="background:<?= $vc ?>22;color:<?= $vc ?>;">Village Leader</span><?php endif; ?>
                </td>
                <td><?= htmlspecialchars($vm['department'] ?: 'General Church') ?></td>
                <td><span class="badge" style="background:rgba(37,99,235,0.1);color:#1e3a8a;"><?= htmlspecialchars($vm['church_role'] ?: 'Member') ?></span></td>
                <td><?php if ($dsr): ?><span class="badge" style="background:<?= $dc ?>22;color:<?= $dc ?>;"><?= htmlspecialchars($dsr) ?></span><?php else: ?>—<?php endif; ?></td>
                <td style="color:#666;"><?= htmlspecialchars($vm['phone'] ?? '—') ?></td>
            </tr>
        <?php endwhile; else: ?>
            <tr><td colspan="6" style="text-align:center;color:#aaa;padding:12px;">No members in <?= $v ?> Village yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>

<?php
// ═══ SECTION 2: Church Cleaners ═══
$cleaners = $conn->query(
    "SELECT first_name, last_name, church_village, department, church_role, phone
     FROM members WHERE desired_role_pref='Church Cleaner' AND is_approved=1
     ORDER BY church_village, first_name"
);
$cleaners_count = $cleaners ? $cleaners->num_rows : 0;
?>
<div class="page-break"></div>
<div style="margin-bottom: 20px;">
    <div class="section-title" style="background:#0ea5e9;">🧹 Church Cleaners — All Villages (<?= $cleaners_count ?> Volunteers)</div>
    <table>
        <thead>
            <tr>
                <th>#</th><th>Full Name</th><th>Village</th><th>Department</th><th>Church Role</th><th>Phone</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($cleaners_count > 0):
            $i = 1;
            while ($cl = $cleaners->fetch_assoc()):
                $vc = $village_colors[$cl['church_village']] ?? '#94a3b8';
        ?>
            <tr>
                <td><?= $i++ ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($cl['first_name'] . ' ' . $cl['last_name']) ?></td>
                <td><span class="badge" style="background:<?= $vc ?>22;color:<?= $vc ?>;"><?= htmlspecialchars($cl['church_village'] ?: '—') ?></span></td>
                <td><?= htmlspecialchars($cl['department'] ?: 'General Church') ?></td>
                <td><?= htmlspecialchars($cl['church_role'] ?: 'Member') ?></td>
                <td style="color:#666;"><?= htmlspecialchars($cl['phone'] ?? '—') ?></td>
            </tr>
        <?php endwhile; else: ?>
            <tr><td colspan="6" style="text-align:center;color:#aaa;padding:12px;">No Church Cleaners yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// ═══ SECTION 3: Church Cookers ═══
$cookers = $conn->query(
    "SELECT first_name, last_name, church_village, department, church_role, phone
     FROM members WHERE desired_role_pref='Church Cooker' AND is_approved=1
     ORDER BY church_village, first_name"
);
$cookers_count = $cookers ? $cookers->num_rows : 0;
?>
<div style="margin-bottom: 20px;">
    <div class="section-title" style="background:#f59e0b;">🍳 Church Cookers — All Villages (<?= $cookers_count ?> Volunteers)</div>
    <table>
        <thead>
            <tr>
                <th>#</th><th>Full Name</th><th>Village</th><th>Department</th><th>Church Role</th><th>Phone</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($cookers_count > 0):
            $i = 1;
            while ($ck = $cookers->fetch_assoc()):
                $vc = $village_colors[$ck['church_village']] ?? '#94a3b8';
        ?>
            <tr>
                <td><?= $i++ ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($ck['first_name'] . ' ' . $ck['last_name']) ?></td>
                <td><span class="badge" style="background:<?= $vc ?>22;color:<?= $vc ?>;"><?= htmlspecialchars($ck['church_village'] ?: '—') ?></span></td>
                <td><?= htmlspecialchars($ck['department'] ?: 'General Church') ?></td>
                <td><?= htmlspecialchars($ck['church_role'] ?: 'Member') ?></td>
                <td style="color:#666;"><?= htmlspecialchars($ck['phone'] ?? '—') ?></td>
            </tr>
        <?php endwhile; else: ?>
            <tr><td colspan="6" style="text-align:center;color:#aaa;padding:12px;">No Church Cookers yet.</td></tr>
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
