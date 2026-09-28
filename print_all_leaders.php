<?php
session_start();
$allowed = isset($_SESSION['admin_id']) || isset($_SESSION['pastor_id']);
if (!$allowed) { header('Location: login.php'); exit(); }

require_once 'db_connect.php';
require_once 'role_departments.php';

// Always landscape as user requested
$page_size  = 'A4 landscape';
$print_date = date('d F Y, H:i');

/* ─── PASTOR INFO ──────────────────────────────────────────────────── */
$pastor = null;
if (isset($_SESSION['pastor_id'])) {
    $pid = (int)$_SESSION['pastor_id'];
    $pr  = $conn->query("SELECT first_name, last_name, phone, address, profile_picture FROM pastors WHERE id = $pid LIMIT 1");
} else {
    $pr  = $conn->query("SELECT first_name, last_name, phone, address, profile_picture FROM pastors WHERE is_approved=1 ORDER BY id DESC LIMIT 1");
}
if ($pr && $pr->num_rows > 0) $pastor = $pr->fetch_assoc();

$pastor_name    = $pastor ? ucfirst($pastor['first_name']) . ' ' . ucfirst($pastor['last_name']) : 'N/A';
$pastor_address = $pastor ? ($pastor['address'] ?? '') : '';
$pastor_phone   = $pastor ? ($pastor['phone'] ?? '') : '';

/* ─── EMBED HELPERS ────────────────────────────────────────────────── */
function embed_img($path) {
    if (empty($path)) return null;
    $full = __DIR__ . '/uploads/' . basename($path);
    if (!file_exists($full)) $full = __DIR__ . '/uploads/default_avatar.png';
    if (!file_exists($full)) return null;
    $mime = mime_content_type($full);
    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($full));
}

function embed_file($full_path) {
    if (!file_exists($full_path)) return null;
    $mime = mime_content_type($full_path);
    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($full_path));
}

$logo_b64   = embed_file(__DIR__ . '/church_logo.jpg');
$pastor_pic = embed_img($pastor['profile_picture'] ?? 'default_avatar.png');

/* ─── LEADER SECTIONS ──────────────────────────────────────────────── */
$leader_sections = [
    'general' => [
        'title'  => 'General Church Leaders',
        'accent' => '#10b981',
        'roles'  => ['senior church elder','general church secretary','vice church secretary','treasurer'],
        'labels' => ['treasurer' => 'Church Treasurer'],
    ],
    'youths' => [
        'title'  => 'Youth Department Leaders',
        'accent' => '#6366f1',
        'roles'  => ['youth chairperson','youth chairman','youth chairlady','vice youth chairperson','vice youth chairman','vice youth chairlady','youth secretary','vice youth secretary','youth treasurer','mama youth','baba youth','organizing secretary','vice organizing secretary','discipline master','vice discipline master','prayer coordinator','vice prayer coordinator','choir leader','vice choir leader','sport secretary','sports secretary','vice sport secretary','vice sports secretary','graduands secretary','vice graduands secretary'],
    ],
    'women' => [
        'title'  => "Women's Ministry Leaders",
        'accent' => '#ec4899',
        'roles'  => ['women chairlady','women chairperson','women chairman','vice women chairlady','vice women chairperson','vice women chairman','women secretary','vice women secretary','women treasurer','organizing secretary','vice organizing secretary','discipline master','vice discipline master','prayer coordinator','vice prayer coordinator','choir leader','vice choir leader'],
    ],
    'elders' => [
        'title'  => 'Elder Ministry Leaders',
        'accent' => '#f59e0b',
        'roles'  => ['elder chairman','elder chairperson','elder chairlady','vice elder chairman','vice elder chairperson','vice elder chairlady','elder secretary','vice elder secretary','elder treasurer','organizing secretary','vice organizing secretary','discipline master','vice discipline master','prayer coordinator','vice prayer coordinator','choir leader','vice choir leader'],
    ],
    'sunday_school' => [
        'title'  => 'Sunday School Leaders',
        'accent' => '#0ea5e9',
        'roles'  => ['sunday school patron','sunday school chairperson','sunday school chairman','sunday school chairlady','vice sunday school patron','vice sunday school chairperson','vice sunday school chairman','vice sunday school chairlady','sunday school secretary','vice sunday school secretary','sunday school treasurer','organizing secretary','vice organizing secretary','discipline master','vice discipline master','prayer coordinator','vice prayer coordinator','choir leader','vice choir leader','sport secretary','sports secretary','vice sport secretary','vice sports secretary','graduands secretary','vice graduands secretary'],
    ],
];

/* ─── LOAD ALL LEADERS (include subsidiary) ────────────────────────── */
$leaders = [];
$res = $conn->query("
    SELECT id, first_name, last_name, gender, department, church_role,
           profile_picture, phone, address, church_village
    FROM members
    WHERE is_approved = 1
      AND church_role IS NOT NULL
      AND TRIM(church_role) != ''
      AND LOWER(TRIM(church_role)) != 'member'
    ORDER BY first_name ASC, last_name ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) $leaders[] = $row;
}

/* ─── HELPERS ──────────────────────────────────────────────────────── */
function normalize_role_str2($r) {
    return strtolower(trim(preg_replace('/\s+/', ' ', $r ?? '')));
}

function parse_member_roles2($church_role_string) {
    $parts = array_filter(array_map('trim', explode(',', str_replace('&', ',', $church_role_string ?? ''))));
    $out = [];
    foreach ($parts as $part) {
        $clean = trim(preg_replace('/\s*\(.*?\)\s*/', '', $part));
        if ($clean !== '') {
            $out[] = ['raw' => $part, 'norm' => normalize_role_str2($clean)];
        }
    }
    return $out;
}

function role_matches_group2($role_norm, $member_dept, $section_key) {
    $d = strtolower(trim($member_dept));
    switch ($section_key) {
        case 'youths':        return $d === 'youths';
        case 'women':         return in_array($d, ['womens ministry', "women's ministry", 'women ministry']);
        case 'elders':        return $d === 'elders';
        case 'sunday_school': return $d === 'sunday school';
        case 'general':       return true;
        default:              return true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>All Church Leaders</title>
<style>
  * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; box-sizing: border-box; margin: 0; padding: 0; }
  @media print { @page { size: A4 landscape; margin: 8mm 10mm 16mm 10mm; } .no-print { display: none !important; } }
  body { font-family: Arial, sans-serif; background: #fff; padding: 10px; padding-bottom: 70px; font-size: 0.8rem; }

  /* Watermark */
  .watermark {
    position: fixed; top: 50%; left: 50%;
    transform: translate(-50%, -50%) rotate(-45deg);
    font-size: 72px !important; color: rgba(30,58,138,0.18) !important;
    font-weight: bold; white-space: nowrap; z-index: 999999 !important;
    opacity: 1 !important; pointer-events: none; letter-spacing: 4px;
    text-transform: uppercase; mix-blend-mode: multiply;
  }

  /* Footer */
  .footer {
    position: fixed; bottom: 0; left: 0; right: 0;
    text-align: center; font-size: 9px; color: #777; font-style: italic;
    background: rgba(255,255,255,0.95); padding: 4px 0; z-index: 10;
    border-top: 1px solid #e5e7eb;
  }

  /* Church header */
  .church-header {
    text-align: center; border-bottom: 3px solid #1e3a8a;
    padding-bottom: 10px; position: relative; margin-bottom: 14px; min-height: 90px;
  }
  .church-header .logo { position: absolute; top: 0; width: 68px; height: 68px; object-fit: contain; }
  .church-header .logo-left  { left: 10px; }
  .church-header .logo-right { right: 10px; }
  .church-header h2 { margin: 0; color: #1e3a8a; padding-top: 8px; font-size: 1.15rem; font-weight: 900; }
  .church-header h3 { margin: 3px 0 2px; color: #1e3a8a; font-size: 0.95rem; font-weight: 700; }
  .church-header p  { margin: 1px 0; font-size: 0.73rem; color: #555; }

  /* Pastor strip */
  .pastor-strip {
    display: flex; align-items: center; gap: 16px;
    background: linear-gradient(135deg,#1e3a8a,#2563eb);
    color: #fff; border-radius: 10px; padding: 12px 18px; margin-bottom: 16px;
  }
  .pastor-strip img {
    width: 72px; height: 72px; border-radius: 50%; object-fit: cover;
    border: 3px solid rgba(255,255,255,0.7); flex-shrink: 0;
  }
  .pastor-strip .info h4 { font-size: 1rem; font-weight: 800; margin-bottom: 3px; }
  .pastor-strip .info span { font-size: 0.76rem; opacity: 0.88; display: block; }

  /* Section title */
  .section-title {
    display: inline-block; padding: 4px 14px; border-radius: 6px;
    font-size: 0.78rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.07em; color: #fff; margin: 14px 0 8px;
  }

  /* Leader grid — 4 columns in landscape */
  .leader-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
    margin-bottom: 4px;
  }

  /* Leader card */
  .leader-card {
    display: flex; align-items: center; gap: 9px;
    padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 8px;
    background: #fff;
  }
  .leader-card img {
    width: 48px; height: 48px; border-radius: 50%;
    object-fit: cover; flex-shrink: 0;
  }
  .leader-card .lc-name { font-weight: 700; font-size: 0.79rem; color: #1e3a8a; }
  .leader-card .lc-role { font-size: 0.69rem; color: #4b5563; margin-top: 1px; }
  .leader-card .lc-meta { font-size: 0.67rem; color: #6b7280; margin-top: 2px; line-height: 1.4; }

  /* Signature block */
  .sig-block {
    display: flex; justify-content: space-between; align-items: flex-end;
    margin-top: 40px; padding: 0 20px; page-break-inside: avoid;
  }
  .sig-col { text-align: center; }
  .sig-label { font-size: 0.79rem; font-weight: 700; text-transform: uppercase; color: #1e3a8a; margin-bottom: 4px; }
  .sig-name  { font-size: 0.72rem; color: #333; margin-bottom: 8px; }
  .sig-line  { display: inline-block; border-bottom: 1px solid #000; width: 180px; height: 13px; }
  .sig-date  { display: flex; align-items: flex-end; gap: 6px; margin-top: 8px; font-size: 0.79rem; }
  .stamp {
    border: 2px dashed #aaa; width: 90px; height: 90px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #ccc; font-size: 0.62rem; text-transform: uppercase;
    line-height: 1.5; text-align: center;
  }

  /* No-print bar */
  .no-print { display: flex; gap: 10px; margin-bottom: 14px; }
</style>
</head>
<body>

<div class="watermark">E.A.P.C MUNYARI CHURCH</div>

<!-- Action buttons (hidden on print) -->
<div class="no-print">
  <button onclick="window.print()" style="background:#1e3a8a;color:#fff;border:none;padding:9px 22px;border-radius:8px;cursor:pointer;font-size:0.9rem;font-weight:600;">🖨️ Print</button>
  <button onclick="window.close()" style="background:#6b7280;color:#fff;border:none;padding:9px 22px;border-radius:8px;cursor:pointer;font-size:0.9rem;">✕ Close</button>
</div>

<!-- Church header with logos -->
<div class="church-header">
  <?php if ($logo_b64): ?>
    <img src="<?= $logo_b64 ?>" class="logo logo-left"  alt="Logo">
    <img src="<?= $logo_b64 ?>" class="logo logo-right" alt="Logo">
  <?php endif; ?>
  <h2>E.A.P.C MUNYARI CHURCH</h2>
  <h3>CHURCH LEADERS REGISTER</h3>
  <p>Printed on: <?= htmlspecialchars($print_date) ?></p>
</div>

<!-- Pastor profile strip -->
<div class="pastor-strip">
  <?php if ($pastor_pic): ?>
    <img src="<?= $pastor_pic ?>" alt="Pastor">
  <?php else: ?>
    <div style="width:72px;height:72px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:2rem;">👤</div>
  <?php endif; ?>
  <div class="info">
    <h4>Pastor: <?= htmlspecialchars($pastor_name) ?></h4>
    <?php if ($pastor_phone): ?><span>📞 <?= htmlspecialchars($pastor_phone) ?></span><?php endif; ?>
    <?php if ($pastor_address): ?><span>📍 <?= htmlspecialchars($pastor_address) ?></span><?php endif; ?>
  </div>
</div>

<?php
$total_printed = 0;
foreach ($leader_sections as $section_key => $section):
    $shown = [];
    $section_leaders = [];

    foreach ($section['roles'] as $role_name) {
        foreach ($leaders as $leader) {
            foreach (parse_member_roles2($leader['church_role'] ?? '') as $role) {
                if ($role['norm'] !== $role_name) continue;
                if (!role_matches_group2($role['norm'], $leader['department'] ?? '', $section_key)) continue;
                $show_key = $leader['id'] . ':' . $section_key . ':' . $role_name;
                if (isset($shown[$show_key])) continue;
                $shown[$show_key] = true;

                $label = $section['labels'][$role_name] ?? role_display_label(
                    trim(preg_replace('/\s*\(.*?\)\s*/', '', $role['raw'])),
                    $leader['department'] ?? '',
                    $leader['gender'] ?? ''
                );

                // Build residence string: address OR church_village
                $residence = trim($leader['address'] ?? '');
                if (empty($residence)) $residence = trim($leader['church_village'] ?? '');

                $section_leaders[] = [
                    'name'      => ucfirst($leader['first_name']) . ' ' . ucfirst($leader['last_name']),
                    'role'      => $label,
                    'pic'       => embed_img($leader['profile_picture'] ?? ''),
                    'phone'     => $leader['phone'] ?? '',
                    'residence' => $residence,
                    'accent'    => $section['accent'],
                ];
                $total_printed++;
            }
        }
    }

    if (empty($section_leaders)) continue;
?>
  <div class="section-title" style="background:<?= htmlspecialchars($section['accent']) ?>;">
    <?= htmlspecialchars($section['title']) ?> (<?= count($section_leaders) ?>)
  </div>
  <div class="leader-grid">
    <?php foreach ($section_leaders as $lc): ?>
    <div class="leader-card">
      <?php if ($lc['pic']): ?>
        <img src="<?= $lc['pic'] ?>" style="border:2px solid <?= htmlspecialchars($lc['accent']) ?>;" alt="">
      <?php else: ?>
        <div style="width:48px;height:48px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;">👤</div>
      <?php endif; ?>
      <div style="min-width:0;">
        <div class="lc-name"><?= htmlspecialchars($lc['name']) ?></div>
        <div class="lc-role"><?= htmlspecialchars($lc['role']) ?></div>
        <div class="lc-meta">
          <?php if ($lc['phone']): ?>📞 <?= htmlspecialchars($lc['phone']) ?><br><?php endif; ?>
          <?php if ($lc['residence']): ?>📍 <?= htmlspecialchars($lc['residence']) ?><?php endif; ?>
        </div>
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
    <div style="display:flex;align-items:flex-end;gap:8px;">
      <span style="font-style:italic;font-size:0.79rem;">Sign:</span>
      <span class="sig-line"></span>
    </div>
    <div class="sig-date">
      <span>Date:</span>
      <span style="display:inline-block;border-bottom:1px dotted #000;width:180px;height:13px;"></span>
    </div>
  </div>
  <div class="sig-col">
    <div class="stamp">Official<br>Stamp</div>
  </div>
</div>

<div class="footer">Generated from E.A.P.C Munyari Portal | Printed on: <?= htmlspecialchars($print_date) ?></div>

<script>
window.addEventListener('load', function() { window.print(); });
</script>
</body>
</html>
