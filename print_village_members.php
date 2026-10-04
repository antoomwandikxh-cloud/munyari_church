<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['pastor_id']) && !isset($_SESSION['admin_id']) && !isset($_SESSION['member_id'])) {
    die("Unauthorized access.");
}

$village = $_GET['village'] ?? '';
if (empty($village)) { die("Village is required."); }
$safe_village = $conn->real_escape_string($village);

// Authorization check
$is_authorized = false;
if (isset($_SESSION['pastor_id']) || isset($_SESSION['admin_id'])) {
    $is_authorized = true;
} elseif (isset($_SESSION['member_id'])) {
    $mid = (int)$_SESSION['member_id'];
    $mq = $conn->query("SELECT is_village_leader, church_village FROM members WHERE id = $mid");
    if ($mq && $mq->num_rows > 0) {
        $mx = $mq->fetch_assoc();
        if ($mx['is_village_leader'] == 1 && trim($mx['church_village']) === trim($village)) {
            $is_authorized = true;
        }
    }
}
if (!$is_authorized) { die("Unauthorized access."); }

// ── Fetch Pastor ─────────────────────────────────────────────────────────────
$pastor_q = $conn->query("SELECT first_name, last_name, profile_picture FROM pastors WHERE is_approved = 1 LIMIT 1");
$pastor   = $pastor_q ? $pastor_q->fetch_assoc() : null;
$pastor_name = $pastor ? strtoupper($pastor['first_name'] . ' ' . $pastor['last_name']) : 'Not Assigned';


// ── Fetch Village Leader ─────────────────────────────────────────────────────
$leader_q = $conn->query("SELECT * FROM members WHERE church_village = '$safe_village' AND is_village_leader = 1 AND is_approved = 1 LIMIT 1");
$leader   = $leader_q ? $leader_q->fetch_assoc() : null;

$l_pic_b64 = '';
if ($leader && !empty($leader['profile_picture'])) {
    $lp = 'uploads/' . basename($leader['profile_picture']);
    if (file_exists($lp)) {
        $ext = strtolower(pathinfo($lp, PATHINFO_EXTENSION));
        $mime = ($ext === 'png') ? 'image/png' : (($ext === 'gif') ? 'image/gif' : 'image/jpeg');
        $l_pic_b64 = "data:$mime;base64," . base64_encode(file_get_contents($lp));
    }
}

// ── Fetch Village Members sorted: leaders first, then by role rank, then name ─
// Sort logic:
//   1  = Village Leader
//   2  = any other named church role (worship leader, choir, etc.)
//   99 = plain member / no role
$v_mems_q = $conn->query("
    SELECT id, first_name, last_name, phone, department, church_role, profile_picture, is_village_leader,
    CASE
        WHEN is_village_leader = 1 THEN 1
        WHEN church_role IS NOT NULL AND church_role != '' AND LOWER(church_role) != 'member' THEN 2
        ELSE 99
    END AS sort_rank
    FROM members
    WHERE is_approved = 1 AND church_village = '$safe_village'
    ORDER BY sort_rank ASC, CASE department WHEN 'Elders' THEN 1 WHEN 'Womens Ministry' THEN 2 WHEN 'Youths' THEN 3 WHEN 'Sunday School' THEN 4 ELSE 5 END ASC, first_name ASC
");

// ── Logo ─────────────────────────────────────────────────────────────────────
$logo_path = 'church_logo.jpg';
$logo_b64  = '';
if (file_exists($logo_path)) {
    $logo_b64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logo_path));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Village Members – <?= htmlspecialchars($village) ?></title>
    <style>
        /* ── Base ── */
        html, body {
            background-color: #ffffff !important;
            color: #000000 !important;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            padding: 20px;
            max-width: 820px;
            margin: 0 auto;
            position: relative;
        }

        /* ── Diagonal Watermark ── */
        .watermark {
            position: fixed;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 3rem;
            font-weight: 900;
            color: rgba(0,0,0,0.12);
            white-space: nowrap;
            z-index: 9999; pointer-events: none;
            pointer-events: none;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        /* ── Print overrides ── */
        @media print { body { padding: 15mm !important; } 
            @page { size: A4 portrait; margin: 0; }
            body { margin: 12mm; padding: 0; max-width: 100%; }
            .no-print { display: none !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .watermark { color: rgba(0,0,0,0.12) !important; z-index: 9999 !important; pointer-events: none; }
        }

        /* ── Header ── */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid #1e3a8a;
            padding-bottom: 18px;
            margin-bottom: 24px;
            margin-top: 16px;
        }
        .header img { height: 85px; border-radius: 50%; }
        .header-text { text-align: center; flex: 1; padding: 0 16px; }
        .header-text h1 { margin: 0 0 4px; color: #1e3a8a; font-size: 24px; text-transform: uppercase; letter-spacing: 1px; }
        .header-text h2 { margin: 0 0 4px; color: #333; font-size: 16px; font-weight: 700; text-transform: uppercase; }
        .header-text h3 { margin: 0; color: #555; font-size: 12px; font-weight: 400; }

        /* ── Leader profile block ── */
        .leader-block {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 22px;
            padding: 16px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #f9fafb;
        }
        .leader-block img { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 3px solid #1e3a8a; margin-bottom: 8px; }
        .leader-block .lb-name { font-size: 15px; font-weight: 700; color: #1e3a8a; text-transform: uppercase; }
        .leader-block .lb-role { font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }

        /* ── Table ── */
        table { width: 100%; border-collapse: collapse; margin-bottom: 28px; font-size: 12px; }
        thead tr { background: #1e3a8a; color: #fff; }
        th { padding: 9px 8px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid #1e3a8a; }
        td { border: 1px solid #d1d5db; padding: 8px; vertical-align: middle; }
        tr:nth-child(even) td { background: #f9fafb; }
        .leader-row td { background: #eff6ff !important; font-weight: 600; }
        .role-row td { background: #f0fdf4 !important; }

        .member-photo { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 1.5px solid #1e3a8a; display: block; }
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-leader { background: #1e3a8a; color: #fff; }
        .badge-role   { background: #10b981; color: #fff; }
        .badge-member { background: #e5e7eb; color: #374151; }

        /* ── Signatures ── */
        .sig-section { display: flex; justify-content: space-between; margin-top: 50px; }
        .sig-box { width: 45%; }
        .sig-title { font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; margin-bottom: 3px; }
        .sig-name  { font-size: 13px; font-weight: 700; text-transform: uppercase; text-align: center; margin-bottom: 18px; }
        .sig-row   { display: flex; align-items: flex-end; margin-bottom: 14px; }
        .sig-label { font-size: 10px; color: #555; white-space: nowrap; margin-right: 8px; padding-bottom: 2px; font-weight: 600; }
        .sig-line  { flex: 1; border-bottom: 1px solid #000; height: 20px; }
        .sig-line-dashed { flex: 1; border-bottom: 1px dashed #555; height: 20px; }

        /* ── Stamp ── */
        .stamp-row { display: flex; justify-content: center; margin-top: 24px; margin-bottom: 16px; }
        .stamp-circle { width: 140px; height: 140px; border: 2px dashed #aaa; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; color: #aaa; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; line-height: 2; }

        /* ── Footer ── */
        .footer { text-align: center; font-size: 10px; color: #666; font-style: italic; border-top: 1px solid #eee; padding-top: 8px; margin-top: 20px; }
    </style>
</head>
<body>
    <!-- Diagonal Watermark -->
    <div class="watermark">E.A.P.C MUNYARI CHURCH</div>

    <!-- Print button (hidden in print) -->
    <div class="no-print" style="text-align:center; margin-bottom:20px;">
        <button onclick="window.print()" style="padding:10px 28px; background:#1e3a8a; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:15px; font-weight:700; letter-spacing:0.5px;">
            🖨 &#128436; Print / Save PDF
        </button>
    </div>

    <!-- Header with logos -->
    <div class="header">
        <?php if ($logo_b64): ?><img src="<?= $logo_b64 ?>" alt="Church Logo"><?php endif; ?>
        <div class="header-text">
            <h1>E.A.P.C Munyari Church</h1>
            <h2><?= strtoupper(htmlspecialchars($village)) ?> Village – Members Directory</h2>
            <h3>Printed on: <?= date('l, F j, Y') ?></h3>
            <?php if ($pastor): ?>
            <h3 style="margin-top:4px; font-weight:600; color:#1e3a8a;">Pastor: <?= htmlspecialchars($pastor_name) ?></h3>
            <?php endif; ?>
        </div>
        <?php if ($logo_b64): ?><img src="<?= $logo_b64 ?>" alt="Church Logo"><?php endif; ?>
    </div>

    <!-- Village Leader block -->
    <div class="leader-block">
        <?php if ($l_pic_b64): ?><img src="<?= $l_pic_b64 ?>" alt="Village Leader"><?php endif; ?>
        <div class="lb-name"><span style="text-transform:uppercase;"><?= $leader ? htmlspecialchars($leader['first_name'] . ' ' . $leader['last_name']) : 'NOT ASSIGNED' ?></span></div>
        <div class="lb-role"><?= htmlspecialchars($village) ?> Village Leader</div>
    </div>

    <!-- Members Table -->
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Photo</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Department</th>
                <th>Role(s)</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php
        if ($v_mems_q && $v_mems_q->num_rows > 0):
            $count = 1;
            while ($m = $v_mems_q->fetch_assoc()):
                // Member photo base64
                $mp_b64 = '';
                if (!empty($m['profile_picture'])) {
                    $mpath = 'uploads/' . basename($m['profile_picture']);
                    if (file_exists($mpath)) {
                        $ext  = strtolower(pathinfo($mpath, PATHINFO_EXTENSION));
                        $mime = ($ext === 'png') ? 'image/png' : (($ext === 'gif') ? 'image/gif' : 'image/jpeg');
                        $mp_b64 = "data:$mime;base64," . base64_encode(file_get_contents($mpath));
                    }
                }
                // Determine row class and badge
                if ($m['is_village_leader'] == 1) {
                    $row_class = 'leader-row';
                    $badge = '<span class="badge badge-leader">Village Leader</span>';
                } elseif (!empty($m['church_role']) && strtolower(trim($m['church_role'])) !== 'member') {
                    $row_class = 'role-row';
                    $badge = '<span class="badge badge-role">Leader</span>';
                } else {
                    $row_class = '';
                    $badge = '<span class="badge badge-member">Member</span>';
                }
        ?>
            <tr class="<?= $row_class ?>">
                <td><?= $count++ ?></td>
                <td>
                    <?php if ($mp_b64): ?>
                        <img class="member-photo" src="<?= $mp_b64 ?>" alt="">
                    <?php else: ?>
                        <div style="width:36px;height:36px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-size:14px;color:#9ca3af;">👤</div>
                    <?php endif; ?>
                </td>
                <td style="font-weight:600; text-transform:uppercase;"><?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?></td>
                <td><?= htmlspecialchars($m['phone'] ?? '') ?></td>
                <td><?= htmlspecialchars($m['department'] ?? '') ?></td>
                <td><?= htmlspecialchars($m['church_role'] ?? '') ?></td>
                <td><?= $badge ?></td>
            </tr>
        <?php
            endwhile;
        else:
        ?>
            <tr><td colspan="7" style="text-align:center; padding:20px; color:#888;">No members found in this village.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Signatures -->
    <div class="sig-section">

        <!-- Village Leader -->
        <div class="sig-box">
            <div class="sig-title">Village Leader</div>
            <div class="sig-name"><?= $leader ? htmlspecialchars(ucfirst($leader['first_name']) . ' ' . ucfirst($leader['last_name'])) : '____________________' ?></div>
            <div class="sig-row"><span class="sig-label">Name :</span><div class="sig-line"></div></div>
            <div class="sig-row"><span class="sig-label">Signature :</span><div class="sig-line"></div></div>
            <div class="sig-row"><span class="sig-label">Date :</span><div class="sig-line-dashed"></div></div>
        </div>

        <!-- Pastor -->
        <div class="sig-box">
            <div class="sig-title">Pastor</div>
            <div class="sig-name"><?= htmlspecialchars($pastor_name) ?></div>

            <div class="sig-row"><span class="sig-label">Name :</span><div class="sig-line"></div></div>
            <div class="sig-row"><span class="sig-label">Signature :</span><div class="sig-line"></div></div>
            <div class="sig-row"><span class="sig-label">Date :</span><div class="sig-line-dashed"></div></div>
        </div>

    </div>

    <!-- Official Stamp -->
    <div class="stamp-row">
        <div class="stamp-circle">
            Official<br>Church<br>Stamp
        </div>
    </div>

    <div class="footer">
        Generated from Munyari Church &nbsp;|&nbsp; <?= date('Y-m-d H:i') ?>
    </div>

    <script>
        window.onload = function () {
            setTimeout(function () { window.print(); }, 700);
        };
    </script>
</body>
</html>
