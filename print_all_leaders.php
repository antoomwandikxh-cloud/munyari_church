<?php
session_start();
// Allow both admin and pastor sessions
$allowed = isset($_SESSION['admin_id']) || isset($_SESSION['pastor_id']);
if (!$allowed) { header('Location: login.php'); exit(); }

require_once 'db.php';
require_once 'role_departments.php';

$orientation = (isset($_GET['orientation']) && $_GET['orientation'] === 'landscape') ? 'landscape' : 'portrait';
$page_size   = $orientation === 'landscape' ? 'A4 landscape' : 'A4 portrait';
$print_date  = date('d F Y, H:i');

// Pastor name
$pastor_name = 'N/A';
if (isset($_SESSION['pastor_id'])) {
    $pid = (int)$_SESSION['pastor_id'];
    $pr  = $conn->query("SELECT first_name, last_name FROM pastors WHERE id = $pid LIMIT 1");
    if ($pr && $pr->num_rows > 0) {
        $p = $pr->fetch_assoc();
        $pastor_name = ucfirst($p['first_name']) . ' ' . ucfirst($p['last_name']);
    }
} elseif (isset($_SESSION['admin_id'])) {
    // Try to get the currently active pastor
    $pr = $conn->query("SELECT first_name, last_name FROM pastors WHERE is_approved=1 ORDER BY id DESC LIMIT 1");
    if ($pr && $pr->num_rows > 0) {
        $p = $pr->fetch_assoc();
        $pastor_name = ucfirst($p['first_name']) . ' ' . ucfirst($p['last_name']);
    }
}

// Logo (base64 for reliable embedding in print)
$logo_path = __DIR__ . '/church_logo.jpg';
$logo_b64  = '';
if (file_exists($logo_path)) {
    $logo_b64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logo_path));
}

// Leader sections definition
$leader_sections = [
    'general' => [
        'title'  => 'General Church Leaders',
        'accent' => '#10b981',
        'roles'  => ['senior church elder', 'general church secretary', 'vice church secretary', 'treasurer'],
        'labels' => ['treasurer' => 'Church Treasurer'],
    ],
    'youths' => [
        'title'  => 'Youth Leaders',
        'accent' => '#6366f1',
        'roles'  => ['youth chairperson', 'youth chairman', 'youth chairlady', 'vice youth chairperson', 'vice youth chairman', 'vice youth chairlady', 'youth secretary', 'vice youth secretary', 'youth treasurer', 'mama youth', 'baba youth', 'organizing secretary', 'discipline master', 'prayer coordinator', 'choir leader', 'sport secretary', 'sports secretary', 'graduands secretary'],
    ],
    'women' => [
        'title'  => 'Women Ministry Leaders',
        'accent' => '#ec4899',
        'roles'  => ['women chairlady', 'women chairperson', 'women chairman', 'vice women chairlady', 'vice women chairperson', 'vice women chairman', 'women secretary', 'vice women secretary', 'women treasurer', 'organizing secretary', 'discipline master', 'prayer coordinator', 'choir leader'],
    ],
    'elders' => [
        'title'  => 'Elder Ministry Leaders',
        'accent' => '#f59e0b',
        'roles'  => ['elder chairman', 'elder chairperson', 'elder chairlady', 'vice elder chairman', 'vice elder chairperson', 'vice elder chairlady', 'elder secretary', 'vice elder secretary', 'elder treasurer', 'organizing secretary', 'discipline master', 'prayer coordinator', 'choir leader'],
    ],
    'sunday_school' => [
        'title'  => 'Sunday School Leaders',
        'accent' => '#0ea5e9',
        'roles'  => ['sunday school patron', 'sunday school chairperson', 'sunday school chairman', 'sunday school chairlady', 'vice sunday school patron', 'vice sunday school chairperson', 'vice sunday school chairman', 'vice sunday school chairlady', 'sunday school secretary', 'vice sunday school secretary', 'sunday school treasurer', 'organizing secretary', 'discipline master', 'prayer coordinator', 'choir leader', 'sport secretary', 'sports secretary', 'graduands secretary'],
    ],
];

// Load all leaders from DB
$leaders = [];
$res = $conn->query("
    SELECT id, first_name, last_name, gender, department, church_role, profile_picture
    FROM members
    WHERE is_approved = 1
      AND church_role IS NOT NULL
      AND TRIM(church_role) != ''
      AND LOWER(TRIM(church_role)) != 'member'
    ORDER BY first_name ASC, last_name ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $leaders[] = $row;
    }
}

// Helper: normalize role string
function normalize_role_str($r) {
    return strtolower(trim(preg_replace('/\s+/', ' ', $r ?? '')));
}

// Helper: parse comma-separated roles from a member
function parse_member_roles($church_role_string) {
    $parts = array_filter(array_map('trim', explode(',', str_replace('&', ',', $church_role_string ?? ''))));
    $out = [];
    foreach ($parts as $part) {
        // Strip "(Sunday School)" or "(Subsidiary)" suffixes
        $clean = trim(preg_replace('/\s*\(.*?\)\s*/', '', $part));
        if ($clean !== '') {
            $out[] = [
                'raw'  => $part,
                'norm' => normalize_role_str($clean),
            ];
        }
    }
    return $out;
}

// Helper: does member belong to the correct department group for this role?
function role_matches_group($role_norm, $member_dept, $section_key) {
    $dept_lower = strtolower(trim($member_dept));
    switch ($section_key) {
        case 'youths':        return $dept_lower === 'youths';
        case 'women':         return in_array($dept_lower, ['womens ministry', "women's ministry", 'women ministry']);
        case 'elders':        return $dept_lower === 'elders';
        case 'sunday_school': return $dept_lower === 'sunday school';
        case 'general':       return true; // General roles apply to any member
        default:              return true;
    }
}

// Photo helper — embed as base64 for reliable printing
function photo_src($pic) {
    if (empty($pic)) $pic = 'default_avatar.png';
    $path = __DIR__ . '/uploads/' . basename($pic);
    if (file_exists($path)) {
        $mime = mime_content_type($path);
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }
    return 'uploads/' . basename($pic);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>All Church Leaders</title>
<style>
  * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; box-sizing: border-box; }
  @media print { @page { size: <?= $page_size ?>; margin: 10mm 12mm 18mm 12mm; } }
  body { font-family: Arial, sans-serif; margin: 0; padding: 12px; background: #fff; padding-bottom: 70px; }

  .watermark {
    position: fixed; top: 50%; left: 50%;
    transform: translate(-50%, -50%) rotate(-45deg);
    font-size: 70px !important; color: rgba(30,58,138,0.22) !important;
    font-weight: bold; white-space: nowrap; z-index: 999999 !important;
    opacity: 1 !important; pointer-events: none; letter-spacing: 4px;
    text-transform: uppercase; mix-blend-mode: multiply;
  }
  .footer {
    position: fixed; bottom: 0; left: 0; right: 0; text-align: center;
    font-size: 10px; color: #777; font-style: italic;
    background: rgba(255,255,255,0.92); padding: 5px 0; z-index: 10;
    border-top: 1px solid #e5e7eb;
  }

  /* Church header */
  .church-header {
    text-align: center; border-bottom: 2px solid #1e3a8a;
    padding-bottom: 10px; position: relative; margin-bottom: 20px; min-height: 90px;
  }
  .church-header .logo { position: absolute; top: 0; width: 70px; height: 70px; object-fit: contain; }
  .church-header .logo-left  { left: 20px; }
  .church-header .logo-right { right: 20px; }
  .church-header h2 { margin: 0; color: #1e3a8a; padding-top: 10px; font-size: 1.2rem; }
  .church-header h3 { margin: 4px 0 2px; color: #1e3a8a; font-size: 1rem; }
  .church-header p  { margin: 2px 0; font-size: 0.77rem; color: #555; }

  /* Section */
  .section-title {
    display: inline-block; padding: 5px 14px; border-radius: 6px;
    font-size: 0.82rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.07em; color: #fff; margin: 16px 0 10px;
  }
  /* Grid of leader cards */
  .leader-grid {
    display: grid;
    grid-template-columns: repeat(<?= $orientation === 'landscape' ? 4 : 3 ?>, 1fr);
    gap: 10px;
    margin-bottom: 6px;
  }
  .leader-card {
    display: flex; align-items: center; gap: 10px;
    padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;
  }
  .leader-avatar {
    width: 50px; height: 50px; border-radius: 50%;
    object-fit: cover; flex-shrink: 0;
  }
  .leader-name { font-weight: 700; font-size: 0.82rem; color: #1e3a8a; }
  .leader-role { font-size: 0.72rem; color: #555; margin-top: 2px; line-height: 1.35; }

  /* Signature */
  .sig-block {
    display: flex; justify-content: space-between; align-items: flex-end;
    margin-top: 50px; padding: 0 20px; page-break-inside: avoid;
  }
  .sig-col { text-align: center; }
  .sig-label { font-size: 0.82rem; font-weight: 700; text-transform: uppercase; color: #1e3a8a; }
  .sig-name  { font-size: 0.75rem; color: #333; margin-bottom: 8px; }
  .sig-line  { display: inline-block; border-bottom: 1px solid #000; width: 200px; height: 14px; }
  .sig-date  { display: flex; align-items: flex-end; gap: 8px; margin-top: 10px; font-size: 0.82rem; }
  .stamp {
    border: 2px dashed #aaa; width: 100px; height: 100px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #ccc; font-size: 0.65rem; text-transform: uppercase;
    line-height: 1.4; text-align: center;
  }

  /* No-print button bar */
  .no-print { }
  @media print { .no-print { display: none !important; } }
</style>
</head>
<body>

<div class="watermark">E.A.P.C MUNYARI CHURCH</div>

<!-- Print / Close buttons (hidden on print) -->
<div class="no-print" style="display:flex;gap:10px;margin-bottom:14px;">
  <button onclick="window.print()" style="background:#1e3a8a;color:#fff;border:none;padding:9px 20px;border-radius:8px;cursor:pointer;font-size:0.9rem;font-weight:600;">🖨️ Print</button>
  <button onclick="window.close()" style="background:#6b7280;color:#fff;border:none;padding:9px 20px;border-radius:8px;cursor:pointer;font-size:0.9rem;">✕ Close</button>
</div>

<!-- Church header -->
<div class="church-header">
  <?php if ($logo_b64): ?>
    <img src="<?= $logo_b64 ?>" class="logo logo-left" alt="Logo">
    <img src="<?= $logo_b64 ?>" class="logo logo-right" alt="Logo">
  <?php endif; ?>
  <h2>E.A.P.C MUNYARI CHURCH</h2>
  <h3>CHURCH LEADERS REGISTER</h3>
  <p>Printed on: <?= htmlspecialchars($print_date) ?></p>
</div>

<?php
$total_printed = 0;
foreach ($leader_sections as $section_key => $section):
    $shown = [];
    $section_leaders = []; // collect cards for this section

    foreach ($section['roles'] as $role_name) {
        foreach ($leaders as $leader) {
            foreach (parse_member_roles($leader['church_role'] ?? '') as $role) {
                if ($role['norm'] !== $role_name) continue;
                if (!role_matches_group($role['norm'], $leader['department'] ?? '', $section_key)) continue;
                $show_key = $leader['id'] . ':' . $section_key . ':' . $role_name;
                if (isset($shown[$show_key])) continue;
                $shown[$show_key] = true;

                $label = $section['labels'][$role_name] ?? role_display_label(
                    trim(preg_replace('/\s*\(.*?\)\s*/', '', $role['raw'])),
                    $leader['department'] ?? '',
                    $leader['gender'] ?? ''
                );

                $section_leaders[] = [
                    'name'  => ucfirst($leader['first_name']) . ' ' . ucfirst($leader['last_name']),
                    'role'  => $label,
                    'pic'   => photo_src($leader['profile_picture'] ?? ''),
                    'accent'=> $section['accent'],
                ];
                $total_printed++;
            }
        }
    }

    if (empty($section_leaders)) continue;
?>
  <div class="section-title" style="background:<?= htmlspecialchars($section['accent']) ?>;"><?= htmlspecialchars($section['title']) ?> (<?= count($section_leaders) ?>)</div>
  <div class="leader-grid">
    <?php foreach ($section_leaders as $lc): ?>
    <div class="leader-card">
      <img class="leader-avatar"
           src="<?= $lc['pic'] ?>"
           alt="<?= htmlspecialchars($lc['name']) ?>"
           style="border: 2px solid <?= htmlspecialchars($lc['accent']) ?>;">
      <div>
        <div class="leader-name"><?= htmlspecialchars($lc['name']) ?></div>
        <div class="leader-role"><?= htmlspecialchars($lc['role']) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>

<?php if ($total_printed === 0): ?>
  <p style="text-align:center;color:#888;margin-top:40px;">No leaders have been assigned yet.</p>
<?php endif; ?>

<!-- Signature block -->
<div class="sig-block">
  <div class="sig-col">
    <div class="sig-label">Church Pastor</div>
    <div class="sig-name"><?= htmlspecialchars($pastor_name) ?></div>
    <div style="display:flex;align-items:flex-end;gap:8px;"><span style="font-style:italic;font-size:0.82rem;">Sign:</span><span class="sig-line"></span></div>
    <div class="sig-date"><span>Date:</span><span style="display:inline-block;border-bottom:1px dotted #000;width:200px;height:14px;"></span></div>
  </div>
  <div class="sig-col">
    <div class="stamp">Official<br>Stamp</div>
  </div>
</div>

<div class="footer">Generated from E.A.P.C Munyari Portal | Printed on: <?= htmlspecialchars($print_date) ?></div>

<script>
// Auto-trigger print dialog
window.addEventListener('load', function() {
    window.print();
});
</script>
</body>
</html>
