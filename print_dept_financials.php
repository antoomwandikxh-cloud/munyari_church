<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['pastor_id']) && !isset($_SESSION['admin_id'])) {
    die("Unauthorized access.");
}

$dept = $_GET['dept'] ?? '';
$as_at_date = $_GET['date'] ?? date('Y-m-d');
$safe_date = $conn->real_escape_string($as_at_date);
$safe_dept = $conn->real_escape_string($dept);

if (empty($dept)) { die("Department is required."); }

// Fetch treasurer of the department
$treasurer_q = $conn->query("SELECT * FROM members WHERE department = '$safe_dept' AND LOWER(church_role) LIKE '%treasurer%' AND is_approved = 1 LIMIT 1");
$treasurer = $treasurer_q ? $treasurer_q->fetch_assoc() : null;


// Fetch chairman of the department — try Chairman first, fall back to Vice Chairman
$chairman = null;
$chairman_title = 'Chairman';

// 1. Try exact chairman / chairlady / patron (not vice)
$chairman_q = $conn->query("SELECT * FROM members WHERE department = '$safe_dept'
    AND LOWER(church_role) REGEXP '(^|,| )(youth chairperson|women chairlady|elder chairman|sunday school patron|building chairperson|chairperson|chairman|chairlady|patron)( |,|\$)'
    AND LOWER(church_role) NOT REGEXP 'vice'
    AND is_approved = 1 LIMIT 1");
if ($chairman_q) $chairman = $chairman_q->fetch_assoc();

// 2. Fall back to Vice Chairman / Vice Chairlady if no main chairman found
if (!$chairman) {
    $vice_q = $conn->query("SELECT * FROM members WHERE department = '$safe_dept'
        AND LOWER(church_role) LIKE '%vice%'
        AND LOWER(church_role) REGEXP 'chair|patron'
        AND is_approved = 1 LIMIT 1");
    if ($vice_q) {
        $chairman = $vice_q->fetch_assoc();
        if ($chairman) $chairman_title = 'Vice Chairman';
    }
}

// Derive actual role title from DB if found
if ($chairman && !empty($chairman['church_role'])) {
    // Extract the relevant role from comma-separated list
    $roles = array_map('trim', explode(',', $chairman['church_role']));
    foreach ($roles as $r) {
        if (stripos($r, 'chair') !== false || stripos($r, 'patron') !== false) {
            $chairman_title = ucwords(strtolower($r));
            break;
        }
    }
}


// Fetch records
$records_q = $conn->query("SELECT fr.*, m.first_name, m.last_name FROM financial_records fr JOIN members m ON fr.recorded_by = m.id WHERE fr.department = '$safe_dept' AND (DATE(fr.record_date) <= '$safe_date' OR (fr.record_date IS NULL AND DATE(fr.recorded_at) <= '$safe_date')) ORDER BY fr.record_date DESC, fr.recorded_at DESC");

// Logo
$logo_path = 'church_logo.jpg';
$logo_b64 = '';
if (file_exists($logo_path)) {
    $logo_b64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logo_path));
}

// Treasurer Pic — profile_picture is stored as a bare filename, look in uploads/
$t_pic_b64 = '';
$default_pic = 'default_avatar.png';
if ($treasurer && !empty($treasurer['profile_picture'])) {
    // Try bare path first, then uploads/ prefix
    $pic_path = file_exists($treasurer['profile_picture'])
        ? $treasurer['profile_picture']
        : 'uploads/' . basename($treasurer['profile_picture']);
    if (file_exists($pic_path)) {
        $ext = strtolower(pathinfo($pic_path, PATHINFO_EXTENSION));
        $mime = ($ext === 'png') ? 'image/png' : (($ext === 'gif') ? 'image/gif' : 'image/jpeg');
        $t_pic_b64 = "data:$mime;base64," . base64_encode(file_get_contents($pic_path));
    }
}
if (!$t_pic_b64 && file_exists($default_pic)) {
    $t_pic_b64 = 'data:image/png;base64,' . base64_encode(file_get_contents($default_pic));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($dept) ?> Financial Records</title>
    <style>
        @media print { 
            @page { size: A4 portrait; margin: 0; } 
            body { margin: 15mm; } 
            .no-print { display: none !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }
        }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; padding: 20px; color: #111; max-width: 800px; margin: 0 auto; position: relative; }
        .no-print { text-align: center; margin-bottom: 20px; }
        
        /* Watermark — matches member printout exactly */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 3.5rem;
            color: rgba(30, 58, 138, 0.18); text-shadow: 2px 2px 4px rgba(30,58,138,0.1); letter-spacing: 4px;
            font-weight: bold;
            white-space: nowrap;
            z-index: 9999;
            pointer-events: none;
            font-family: Arial, sans-serif;
        }

        .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid #1e3a8a; padding-bottom: 15px; margin-bottom: 30px; }
        .header img { width: 100px; height: auto; border-radius: 8px; }
        .header-text { text-align: center; flex: 1; padding: 0 20px; }
        .header-text h1 { margin: 0 0 5px; color: #1e3a8a; font-size: 22px; text-transform: uppercase; font-weight: 900; }
        .header-text h2 { margin: 0 0 5px; font-size: 18px; color: #333; }
        .header-text h3 { margin: 0; font-size: 14px; color: #555; }
        
        .treasurer-profile { text-align: center; margin-bottom: 20px; }
        .treasurer-profile img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #1e3a8a; }
        .treasurer-profile .name { font-weight: bold; font-size: 16px; margin-top: 5px; text-transform: uppercase; }
        .treasurer-profile .role { font-size: 12px; color: #666; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 40px; box-shadow: 0 0 0 1px #ccc; }
        th, td { padding: 10px 12px; border: 1px solid #ccc; text-align: left; font-size: 13px; }
        th { background: #1e3a8a; color: white; font-weight: bold; text-transform: uppercase; }
        .amt { text-align: right; font-weight: bold; font-size: 14px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        .total-row { background: #f3f4f6; font-size: 1.1em; }
        
        .auth-section { display: flex; justify-content: space-between; margin-top: 60px; position: relative; padding-bottom: 50px; }
        .sig { width: 35%; text-align: center; z-index: 2; }
        .sig-line { border-bottom: 1px solid #000; height: 40px; margin-bottom: 5px; }
        .date-line { border-bottom: 1px dashed #000; width: 100%; height: 25px; margin-top: 15px; }
        .sig-label { font-weight: bold; text-transform: uppercase; font-size: 12px; margin-bottom: 5px; }
        
        .stamp-box {
            position: absolute;
            bottom: 30px;
            right: 200px;
            width: 140px;
            height: 140px;
            border: 2px dashed #999;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #999;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            z-index: 1;
        }

        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 11px; color: #666; font-style: italic; border-top: 1px solid #eee; padding: 10px 0; background: rgba(255,255,255,0.9); }
    </style>
</head>
<body>
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

    <div class="header">
        <?php if($logo_b64): ?>
        <img src="<?= $logo_b64 ?>" alt="Logo">
        <?php endif; ?>
        <div class="header-text">
            <h1>E.A.P.C Munyari Church</h1>
            <h2><?= strtoupper(htmlspecialchars($dept)) ?> - FINANCIAL RECORDS</h2>
            <h3>AS AT: <?= date('F j, Y', strtotime($as_at_date)) ?></h3>
        </div>
        <?php if($logo_b64): ?>
        <img src="<?= $logo_b64 ?>" alt="Logo">
        <?php endif; ?>
    </div>

    <div class="treasurer-profile">
        <?php if($t_pic_b64): ?>
        <img src="<?= $t_pic_b64 ?>" alt="Treasurer">
        <?php endif; ?>
        <div class="name"><?= $treasurer ? htmlspecialchars(ucfirst($treasurer['first_name']) . ' ' . ucfirst($treasurer['last_name'])) : 'NOT ASSIGNED' ?></div>
        <div class="role"><?= htmlspecialchars($dept) ?> Treasurer</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Recorded By</th>
                <th>Description</th>
                <th class="amt">Amount (KSh)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total = 0;
            if ($records_q && $records_q->num_rows > 0) {
                while ($r = $records_q->fetch_assoc()) {
                    $total += $r['amount'];
                    $date = date('M j, Y', strtotime($r['record_date'] ?: $r['recorded_at']));
                    echo "<tr>";
                    echo "<td>$date</td>";
                    echo "<td>" . htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($r['description']) . "</td>";
                    echo "<td class='amt'>" . number_format($r['amount'], 2) . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='4' style='text-align:center;'>No records found for this period.</td></tr>";
            }
            ?>
            <tr class="total-row">
                <td colspan="3" style="text-align: right; padding-right: 20px;"><strong>TOTAL AMOUNT COLLECTED</strong></td>
                <td class="amt" style="color: #166534;">KSh <?= number_format($total, 2) ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Signatures Row -->
    <div style="display: flex; justify-content: space-between; margin-top: 60px;">

        <!-- Treasurer Signature -->
        <div style="width: 42%; text-align:left;">
            <div style="font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; text-align:center; margin-bottom: 4px;">Treasurer</div>
            <div style="font-weight: bold; font-size: 14px; text-transform: uppercase; text-align:center; margin-bottom: 20px;">
                <?= $treasurer ? htmlspecialchars(ucfirst($treasurer['first_name']) . ' ' . ucfirst($treasurer['last_name'])) : '___________________' ?>
            </div>
            
            <div style="display:flex; align-items:flex-end; margin-bottom:16px;">
                <span style="font-size:11px; color:#555; white-space:nowrap; margin-right:8px; padding-bottom:2px; font-weight:600;">Signature :</span>
                <div style="flex:1; border-bottom:1px solid #000; height:22px;"></div>
            </div>
            <div style="display:flex; align-items:flex-end;">
                <span style="font-size:11px; color:#555; white-space:nowrap; margin-right:8px; padding-bottom:2px; font-weight:600;">Date :</span>
                <div style="flex:1; border-bottom:1px dashed #555; height:22px;"></div>
            </div>
        </div>

        <!-- Chairman / Vice Chairman Signature -->
        <div style="width: 42%; text-align:left;">
            <div style="font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; text-align:center; margin-bottom: 4px;">
                <?= htmlspecialchars($chairman_title) ?>
            </div>
            <div style="font-weight: bold; font-size: 14px; text-transform: uppercase; text-align:center; margin-bottom: 20px;">
                <?= $chairman ? htmlspecialchars(ucfirst($chairman['first_name']) . ' ' . ucfirst($chairman['last_name'])) : '___________________' ?>
            </div>
            
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
    <div style="display: flex; justify-content: center; margin-top: 30px; margin-bottom: 20px;">
        <div style="width: 150px; height: 150px; border: 2px dashed #aaa; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; color: #aaa; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; line-height: 1.8;">
            Official<br>Church Stamp
        </div>
    </div>

    <div class="footer">
        Generated from the Official E.A.P.C Munyari Church Portal on <?= date('Y-m-d H:i') ?>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 600);
        };
    </script>
</body>
</html>






