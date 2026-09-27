<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['pastor_id']) && !isset($_SESSION['admin_id'])) {
    die("Unauthorized access.");
}

$as_at_date = $_GET['date'] ?? date('Y-m-d');
$safe_date  = $conn->real_escape_string($as_at_date);

// Fetch totals per department up to the chosen date
$totals = [];
$q = $conn->query("SELECT department, SUM(amount) AS total FROM financial_records
    WHERE DATE(record_date) <= '$safe_date'
       OR (record_date IS NULL AND DATE(recorded_at) <= '$safe_date')
    GROUP BY department");
if ($q) {
    while ($row = $q->fetch_assoc()) {
        $d = $row['department'];
        if (stripos($d, 'Building') !== false || stripos($d, 'construction') !== false) $d = 'Building';
        elseif (stripos($d, 'Sunday') !== false) $d = 'Sunday School';
        elseif (stripos($d, 'Youth') !== false) $d = 'Youths';
        elseif (stripos($d, 'Women') !== false) $d = 'Womens Ministry';
        elseif (stripos($d, 'Elder') !== false) $d = 'Elders';
        elseif (stripos($d, 'General') !== false) $d = 'General Church';
        else $d = 'General Church'; // Roll up any completely unknown ones into General Church so no money is lost
        
        $totals[$d] = ($totals[$d] ?? 0) + (float)$row['total'];
    }
}

// Exactly the departments the user requested
$depts = [
    'General Church' => 'General Church Department',
    'Elders'         => 'Elders Department',
    'Womens Ministry'=> "Women's Ministry Department",
    'Youths'         => 'Youths Department',
    'Sunday School'  => 'Sunday School Department',
    'Building'       => 'Building Department'
];

// Fetch all treasurers
$treasurers_info = [];
foreach ($depts as $dk => $label) {
    $q = $conn->query("SELECT first_name, last_name, profile_picture FROM members WHERE department='$dk' AND LOWER(church_role) LIKE '%treasurer%' AND is_approved=1 LIMIT 1");
    if ($q && $r = $q->fetch_assoc()) {
        $treasurers_info[$dk] = [
            'name' => ucfirst($r['first_name']) . ' ' . ucfirst($r['last_name']),
            'pic'  => !empty($r['profile_picture']) ? 'uploads/' . basename($r['profile_picture']) : 'uploads/default_avatar.png'
        ];
    } else {
        $treasurers_info[$dk] = [
            'name' => 'Not Assigned',
            'pic'  => 'uploads/default_avatar.png'
        ];
    }
}
$gc_tres_name = ($treasurers_info['General Church']['name'] !== 'Not Assigned') ? $treasurers_info['General Church']['name'] : '________________________';

// Fetch Pastor name
$pastor_q = $conn->query("SELECT first_name, last_name FROM pastors WHERE is_approved=1 LIMIT 1");
$pastor_name = ($pastor_q && $r = $pastor_q->fetch_assoc()) ? ucfirst($r['first_name']).' '.ucfirst($r['last_name']) : '________________________';

// Logo
$logo_b64 = '';
if (file_exists('church_logo.jpg')) {
    $logo_b64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents('church_logo.jpg'));
}

// Smart number formatter — drop .00 on whole numbers, keep decimals otherwise
function fmt_amount($n) {
    return ($n == floor($n))
        ? number_format((int)$n)          // e.g. 5,555
        : number_format($n, 2);           // e.g. 5,555.50
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Records — E.A.P.C Munyari Church</title>
    <style>
        @media print {
            @page { size: A4 portrait; margin: 0; }
            body  { margin: 15mm; }
            .no-print { display: none !important; }
            * { -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important; }
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            padding: 20px; color: #111;
            max-width: 820px; margin: 0 auto;
        }
        .no-print { text-align: center; margin-bottom: 20px; }

        /* ── Watermark: matches member-tab printout exactly ── */
        .watermark {
            position: fixed;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 3.5rem;
            color: rgba(30, 58, 138, 0.18); text-shadow: 2px 2px 4px rgba(30,58,138,0.1); letter-spacing: 4px;
            font-weight: bold;
            white-space: nowrap;
            z-index: 9999;
            pointer-events: none;
            font-family: Arial, sans-serif;
        }

        /* ── Header ── */
        .header {
            display: flex; align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid #1e3a8a;
            padding-bottom: 14px; margin-bottom: 28px;
        }
        .header img { width: 90px; height: auto; border-radius: 6px; }
        .header-center { text-align: center; flex: 1; padding: 0 16px; }
        .header-center h1 {
            margin: 0 0 4px; color: #1e3a8a;
            font-size: 22px; text-transform: uppercase;
            font-weight: 900; letter-spacing: 1px;
        }
        .header-center h2 { margin: 0 0 4px; font-size: 16px; color: #333; }
        .header-center h3 { margin: 0; font-size: 13px; color: #555; }

        /* ── Table ── */
        table { width: 100%; border-collapse: collapse; margin-bottom: 36px; }
        th, td { padding: 11px 14px; border: 1px solid #ccc; font-size: 13.5px; }
        th { background: #1e3a8a; color: #fff; font-weight: bold;
             text-transform: uppercase; letter-spacing: 0.4px; }
        .dept-cell { font-weight: 500; }
        .amt {
            text-align: right; font-weight: 700;
            font-size: 15px; letter-spacing: 0.3px;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }
        .total-row td { background: #f0fdf4; font-size: 15px; }
        .total-row .amt { color: #166534; font-size: 17px; }

        /* ── Signatures ── */
        .sig-row {
            display: flex; justify-content: space-between;
            margin-top: 56px;
        }
        .sig-block { width: 40%; text-align: center; }
        .sig-role  { font-size: 10px; color: #888; text-transform: uppercase;
                     letter-spacing: 0.5px; margin-bottom: 3px; }
        .sig-name  { font-weight: 800; font-size: 13px; text-transform: uppercase;
                     margin-bottom: 5px; color: #111; }
        .sig-line  { border-bottom: 1px solid #111; height: 36px; margin-bottom: 3px; }
        .sig-label { font-size: 10px; color: #666; }
        .date-line { border-bottom: 1px dashed #666; height: 22px; margin-top: 10px; }

        /* ── Stamp centred below sigs ── */
        .stamp-wrap {
            display: flex; justify-content: center;
            margin-top: 28px; margin-bottom: 16px;
        }
        .stamp-circle {
            width: 140px; height: 140px;
            border: 2px dashed #bbb; border-radius: 50%;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            text-align: center; color: #bbb;
            font-size: 11px; text-transform: uppercase;
            letter-spacing: 1px; line-height: 1.7;
        }

        /* ── Footer ── */
        .footer {
            position: fixed; bottom: 0; left: 0; right: 0;
            text-align: center; font-size: 10.5px; color: #777;
            font-style: italic; border-top: 1px solid #e5e7eb;
            padding: 8px 0; background: rgba(255,255,255,0.9);
        }
    </style>
</head>
<body>

    <!-- Watermark — identical to member-tab printout -->
    <div class="watermark">E.A.P.C MUNYARI CHURCH</div>

    <div class="no-print" style="display:flex; justify-content:center; gap:10px;">
        <button onclick="setOriAndPrint('portrait')"
            style="padding:10px 22px;background:linear-gradient(135deg,#2563eb,#6366f1);color:#fff;border:none;
                   border-radius:8px;cursor:pointer;font-size:15px;font-weight:bold;box-shadow:0 2px 8px rgba(37,99,235,0.3);">
            🖨 Portrait Mode
        </button>
        <button onclick="setOriAndPrint('landscape')"
            style="padding:10px 22px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;border:none;
                   border-radius:8px;cursor:pointer;font-size:15px;font-weight:bold;box-shadow:0 2px 8px rgba(16,185,129,0.3);">
            🖨 Landscape Mode
        </button>
    </div>
    <script>
    function setOriAndPrint(ori) {
        let st = document.getElementById('printOriStyle');
        if (!st) {
            st = document.createElement('style');
            st.id = 'printOriStyle';
            document.head.appendChild(st);
        }
        st.innerHTML = '@media print { @page { size: A4 ' + ori + '; } .watermark { font-size: ' + (ori === ''landscape'' ? ''5.5rem'' : ''3.8rem'') + ' !important; } }';
        window.print();
    }
    </script>

    <!-- Header: logo | title | logo -->
    <div class="header">
        <?php if ($logo_b64): ?>
        <img src="<?= $logo_b64 ?>" alt="Logo">
        <?php endif; ?>

        <div class="header-center">
            <h1>E.A.P.C Munyari Church</h1>
            <h2>OFFICIAL FINANCIAL RECORDS SUMMARY</h2>
            <h3>AS AT: <?= date('F j, Y', strtotime($as_at_date)) ?></h3>
        </div>

        <?php if ($logo_b64): ?>
        <img src="<?= $logo_b64 ?>" alt="Logo">
        <?php endif; ?>
    </div>

    <!-- Treasurers Profile Bar -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; margin-bottom:30px; gap:10px;">
        <?php foreach ($depts as $dk => $label): 
            $t = $treasurers_info[$dk];
            $t_pic = file_exists($t['pic']) ? $t['pic'] : 'uploads/default_avatar.png';
            // Convert to base64 so it prints reliably
            if (file_exists($t_pic)) {
                $ext = strtolower(pathinfo($t_pic, PATHINFO_EXTENSION));
                $mime = ($ext === 'png') ? 'image/png' : (($ext === 'gif') ? 'image/gif' : 'image/jpeg');
                $t_pic_b64 = "data:$mime;base64," . base64_encode(file_get_contents($t_pic));
            } else {
                $t_pic_b64 = '';
            }
        ?>
        <div style="text-align:center; flex:1; min-width:90px;">
            <img src="<?= $t_pic_b64 ?>" style="width:64px; height:64px; border-radius:50%; object-fit:cover; border:2px solid #1e3a8a; margin-bottom:5px;">
            <div style="font-size:10px; color:#555; text-transform:uppercase; font-weight:bold;"><?= str_replace(' Department', '', $label) ?></div>
            <div style="font-size:11px; font-weight:800; color:#111; text-transform:uppercase; margin-top:2px;"><?= htmlspecialchars($t['name']) ?></div>
            <div style="font-size:10px; color:#888; font-style:italic;">Treasurer</div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Financial Table -->
    <table>
        <thead>
            <tr>
                <th>Department / Ministry</th>
                <th class="amt">Amount Collected (KSh)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $all_totals = 0;
            foreach ($depts as $db_key => $label):
                $amt = $totals[$db_key] ?? 0;
                $all_totals += $amt;
            ?>
            <tr>
                <td class="dept-cell"><?= htmlspecialchars($label) ?> Treasurer</td>
                <td class="amt">KSh <?= number_format($amt, 2) ?></td>
            </tr>
            <?php endforeach; ?>



            <tr class="total-row">
                <td style="text-align:right;padding-right:18px;font-weight:700;">GRAND TOTAL</td>
                <td class="amt">KSh <?= number_format($all_totals, 2) ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Signatures -->
    <div class="sig-row">

        <div class="sig-block" style="text-align:left;">
            <div class="sig-role" style="text-align:center; margin-bottom:10px;">General Church Treasurer</div>
            <div class="sig-name" style="text-align:center; margin-bottom:20px;"><?= htmlspecialchars($gc_tres_name) ?></div>
            
            <div style="display:flex; align-items:flex-end; margin-bottom:16px;">
                <span style="font-size:11px; color:#555; white-space:nowrap; margin-right:8px; padding-bottom:2px; font-weight:600;">Signature :</span>
                <div style="flex:1; border-bottom:1px solid #000; height:22px;"></div>
            </div>
            <div style="display:flex; align-items:flex-end;">
                <span style="font-size:11px; color:#555; white-space:nowrap; margin-right:8px; padding-bottom:2px; font-weight:600;">Date :</span>
                <div style="flex:1; border-bottom:1px dashed #555; height:22px;"></div>
            </div>
        </div>

        <div class="sig-block" style="text-align:left;">
            <div class="sig-role" style="text-align:center; margin-bottom:10px;">Church Pastor</div>
            <div class="sig-name" style="text-align:center; margin-bottom:20px;"><?= htmlspecialchars($pastor_name) ?></div>
            
            <div style="display:flex; align-items:flex-end; margin-bottom:16px;">
                <span style="font-size:11px; color:#555; white-space:nowrap; margin-right:8px; padding-bottom:2px; font-weight:600;">Signature :</span>
                <div style="flex:1; border-bottom:1px solid #000; height:22px;"></div>
            </div>
            <div style="display:flex; align-items:flex-end;">
                <span style="font-size:11px; color:#555; white-space:nowrap; margin-right:8px; padding-bottom:2px; font-weight:600;">Date :</span>
                <div style="flex:1; border-bottom:1px dashed #555; height:22px;"></div>
            </div>
        </div>

    </div>

    <!-- Official Stamp — centred below signatures -->
    <div class="stamp-wrap">
        <div class="stamp-circle">
            Official<br>Church Stamp
        </div>
    </div>

    <div class="footer">
        Generated from the Official E.A.P.C Munyari Church Portal on <?= date('Y-m-d H:i') ?>
    </div>

    <script>
        window.onload = function () {
            setTimeout(function () { window.print(); }, 600);
        };
    </script>
</body>
</html>









