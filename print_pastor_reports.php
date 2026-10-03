<?php
session_start();
require_once 'db_connect.php';

// Check pastor auth
if (!isset($_SESSION['pastor_id'])) {
    die("Unauthorized.");
}

$report = $_GET['report'] ?? '';
$param = $_GET['param'] ?? '';

// Get Pastor info
$pastor_q = $conn->query("SELECT * FROM members WHERE department = 'Pastoral' LIMIT 1");
$pastor = $pastor_q ? $pastor_q->fetch_assoc() : null;

// Get Village Leaders
$v_leaders_q = $conn->query("SELECT * FROM members WHERE is_village_leader = 1 ORDER BY first_name ASC");
$v_leaders = [];
if ($v_leaders_q) {
    while($vl = $v_leaders_q->fetch_assoc()) {
        $v_leaders[] = $vl;
    }
}

// Get logo
$logo_b64 = '';
$logo_path = 'church_logo.jpg';
if (file_exists($logo_path)) {
    $ext = strtolower(pathinfo($logo_path, PATHINFO_EXTENSION));
    $mime = ($ext === 'png') ? 'image/png' : (($ext === 'gif') ? 'image/gif' : 'image/jpeg');
    $logo_b64 = "data:$mime;base64," . base64_encode(file_get_contents($logo_path));
}

$title = "CHURCH REPORT";
$members = [];

// Helper function for roles
function extract_role_for_print($role) {
    if (empty($role)) return "Member";
    return htmlspecialchars(trim(preg_replace('/\s*\(subsidiary\)\s*/i', '', $role)));
}

if ($report === 'all_villages') {
    $title = "ALL CHURCH VILLAGES MEMBERS";
    $q = $conn->query("
        SELECT * FROM members 
        WHERE is_approved = 1 AND church_village IS NOT NULL AND church_village != '' 
        ORDER BY church_village ASC, CASE WHEN is_village_leader = 1 THEN 1 WHEN church_role IS NOT NULL AND church_role != '' AND LOWER(church_role) != 'member' THEN 2 ELSE 99 END ASC, first_name ASC
    ");
    while ($m = $q->fetch_assoc()) $members[] = $m;
} elseif ($report === 'single_village') {
    $v_safe = $conn->real_escape_string($param);
    $title = strtoupper($v_safe) . " VILLAGE MEMBERS";
    $q = $conn->query("
        SELECT *,
        CASE
            WHEN is_village_leader = 1 THEN 1
            WHEN church_role IS NOT NULL AND church_role != '' AND LOWER(church_role) != 'member' THEN 2
            ELSE 99
        END AS sort_rank
        FROM members 
        WHERE is_approved = 1 AND church_village = '$v_safe' 
        ORDER BY sort_rank ASC, first_name ASC
    ");
    while ($m = $q->fetch_assoc()) $members[] = $m;
} elseif ($report === 'cookers') {
    $title = 'CHURCH COOKERS';
    $q = $conn->query("SELECT * FROM members WHERE is_approved = 1 AND (LOWER(church_role) LIKE '%cook%' OR LOWER(department) LIKE '%cook%') ORDER BY first_name ASC");
    while ($m = $q->fetch_assoc()) $members[] = $m;
} elseif ($report === 'cleaners') {
    $title = 'CHURCH CLEANERS';
    $q = $conn->query("SELECT * FROM members WHERE is_approved = 1 AND (LOWER(church_role) LIKE '%clean%' OR LOWER(department) LIKE '%clean%') ORDER BY first_name ASC");
    while ($m = $q->fetch_assoc()) $members[] = $m;
} else {
    die("Invalid report type.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <style>
        :root {
            --border-color: #ccc;
            --text-main: #111;
        }
        body {
            font-family: Arial, sans-serif;
            padding: 18px;
            margin: 0;
            color: var(--text-main);
        }
        .print-watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 3rem;
            color: rgba(0,0,0,0.12);
            font-weight: 900;
            white-space: nowrap;
            z-index: 9999;
            pointer-events: none;
            text-transform: uppercase;
        }
        .report-header {
            text-align: center;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 12px;
            margin-bottom: 20px;
            position: relative;
            z-index: 10;
        }
        .report-header img {
            position: absolute;
            left: 0;
            top: 0;
            width: 70px;
            height: auto;
            border-radius: 50%;
        }
        .report-header h2 {
            margin: 0;
            font-size: 1.35rem;
            color: #1e3a8a;
            padding-top: 10px;
        }
        .report-header p {
            margin: 4px 0 0;
            font-size: 0.85rem;
            color: #555;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
            position: relative;
            z-index: 10;
            background: transparent;
        }
        th, td {
            padding: 6px 8px;
            border: 1px solid var(--border-color);
            text-align: left;
        }
        th {
            background: #1e3a8a !important;
            color: #ffffff !important;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        tr:nth-child(even) td {
            background: rgba(0,0,0,0.02);
        }
        .photo-cell img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #ccc;
        }
        .leader-row td {
            background: #eff6ff !important;
            font-weight: bold;
        }
        .print-footer {
            margin-top: 25px;
            font-size: 0.75rem;
            color: #777;
            text-align: center;
            font-style: italic;
        }
        @media print {
            @page { size: A4 landscape; margin: 0; }
            body { padding: 0; }
            .no-print { display: none !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }
            .print-watermark { color: rgba(0,0,0,0.12) !important; z-index: 9999 !important; }
        }
    </style>
</head>
<body>
    <div class="print-watermark">E.A.P.C MUNYARI CHURCH</div>
    
    <div class="no-print" style="text-align:center; margin-bottom:20px;">
        <button onclick="window.print()" style="padding:10px 24px; background:#1e3a8a; color:white; border:none; border-radius:6px; cursor:pointer; font-size:15px; font-weight:bold;">
            Print / Save as PDF
        </button>
    </div>

    <div class="report-header" style="<?= $report === 'all_villages' ? 'border-bottom:none; margin-bottom:10px;' : '' ?>">
        <?php if ($logo_b64): ?><img src="<?= $logo_b64 ?>" alt="Logo" style="position:<?= $report === 'all_villages' ? 'relative; display:block; margin:0 auto 10px auto;' : 'absolute' ?>"><?php endif; ?>
        <h2>E.A.P.C MUNYARI CHURCH &mdash; <?= htmlspecialchars($title) ?></h2>
        <p>Printed on: <?= date('F j, Y g:i A') ?></p>
        
        <?php if ($report === 'all_villages'): ?>
        <div style="margin-top:20px; display:flex; flex-direction:column; align-items:center; gap:15px; border-bottom:2px solid #1e3a8a; padding-bottom:15px;">
            <!-- Pastor -->
            <?php if ($pastor): ?>
            <div style="text-align:center;">
                <?php 
$p_pic = 'uploads/' . basename($pastor['profile_picture'] ?? 'default_avatar.png');
$p_b64 = '';
if (file_exists($p_pic)) {
    $ext = strtolower(pathinfo($p_pic, PATHINFO_EXTENSION));
    $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
    $p_b64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($p_pic));
}
?>
<img src="<?= $p_b64 ?>" style="width:70px; height:70px; border-radius:50%; object-fit:cover; border:3px solid #1e3a8a; position:static;">
                <div style="font-weight:bold; color:#1e3a8a; font-size:1rem; text-transform:uppercase; margin-top:5px;"><?= htmlspecialchars($pastor['first_name'] . ' ' . $pastor['last_name']) ?></div>
                <div style="font-size:0.8rem; color:#555; text-transform:uppercase; font-weight:bold;">Senior Pastor</div>
            </div>
            <?php endif; ?>
            
            <!-- Village Leaders -->
            <?php if (!empty($v_leaders)): ?>
            <div style="display:flex; justify-content:center; gap:30px; margin-top:10px; flex-wrap:wrap;">
                <?php foreach ($v_leaders as $vl): ?>
                <div style="text-align:center;">
                    <?php 
$vl_pic = 'uploads/' . basename($vl['profile_picture'] ?? 'default_avatar.png');
$vl_b64 = '';
if (file_exists($vl_pic)) {
    $ext = strtolower(pathinfo($vl_pic, PATHINFO_EXTENSION));
    $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
    $vl_b64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($vl_pic));
}
?>
<img src="<?= $vl_b64 ?>" style="width:55px; height:55px; border-radius:50%; object-fit:cover; border:2px solid #3b82f6; position:static;">
                    <div style="font-weight:bold; color:#111; font-size:0.85rem; text-transform:uppercase; margin-top:5px;"><?= htmlspecialchars($vl['first_name'] . ' ' . $vl['last_name']) ?></div>
                    <div style="font-size:0.75rem; color:#555;"><?= htmlspecialchars($vl['church_village']) ?> Leader</div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:40px;">#</th>
                <th style="width:60px;">Photo</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Village</th>
                <th>Department</th>
                <th>Role</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($members) > 0): ?>
                <?php 
                $i = 1; 
                $current_village = '';
                foreach ($members as $m): 
                    $is_leader = ($report === 'single_village' && $m['is_village_leader'] == 1);
                    $row_class = $is_leader ? 'leader-row' : '';
                    $pic = 'uploads/' . basename($m['profile_picture'] ?? 'default_avatar.png');
                    $m_b64 = '';
                    if (file_exists($pic)) {
                        $ext = strtolower(pathinfo($pic, PATHINFO_EXTENSION));
                        $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
                        $m_b64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($pic));
                    }
                    
                    if ($report === 'all_villages' && $m['church_village'] !== $current_village) {
                        $current_village = $m['church_village'];
                        echo '<tr><td colspan="7" style="background:#f1f5f9; font-weight:bold; color:#1e3a8a; text-transform:uppercase; text-align:center; padding:10px;">VILLAGE: ' . htmlspecialchars($current_village) . '</td></tr>';
                    }
                ?>
                <tr class="<?= $row_class ?>">
                    <td><?= $i++ ?></td>
                    <td class="photo-cell"><img src="<?= $m_b64 ?>"></td>
                    <td><span style="text-transform:uppercase;"><?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?></span><?= $is_leader ? ' <span style="color:#2563eb;font-weight:bold;">(LEADER)</span>' : '' ?></td>
                    <td><?= htmlspecialchars($m['phone'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($m['church_village'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($m['department'] ?? '-') ?></td>
                    <td><?= extract_role_for_print($m['church_role']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:20px; color:#666;">No members found for this report.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($report === 'all_villages'): ?>
    <div style="display:flex; justify-content:space-around; align-items:flex-end; margin-top:60px; padding:0 20px; page-break-inside:avoid;">
        <?php if ($pastor): ?>
        <div style="text-align:center; width:200px;">
            <div style="border-bottom:1px solid #000; height:20px; margin-bottom:5px;"></div>
            <div style="font-weight:bold; font-size:0.85rem; text-transform:uppercase;"><?= htmlspecialchars($pastor['first_name'] . ' ' . $pastor['last_name']) ?></div>
            <div style="font-size:0.75rem; color:#555;">Senior Pastor Sign/Stamp</div>
        </div>
        <?php endif; ?>
        
        <?php foreach (array_slice($v_leaders, 0, 3) as $vl): ?>
        <div style="text-align:center; width:180px;">
            <div style="border-bottom:1px solid #000; height:20px; margin-bottom:5px;"></div>
            <div style="font-weight:bold; font-size:0.85rem; text-transform:uppercase;"><?= htmlspecialchars($vl['first_name'] . ' ' . $vl['last_name']) ?></div>
            <div style="font-size:0.75rem; color:#555;"><?= htmlspecialchars($vl['church_village']) ?> Leader Sign</div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="print-footer">Generated from Munyari Church</div>

    <script>
        window.onload = function() {
            setTimeout(function() { window.print(); }, 800);
        };
    </script>
</body>
</html>
