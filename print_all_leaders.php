<?php
session_start();
$allowed = isset($_SESSION['admin_id']) || isset($_SESSION['pastor_id']);
if (!$allowed) { header('Location: login.php'); exit(); }

require_once 'db_connect.php';
require_once 'role_departments.php';

$print_date = date('d F Y, H:i');

/* ── PASTOR INFO ───────────────────────────────────────────────────── */
$pastor = null;
if (isset($_SESSION['pastor_id'])) {
    $pid = (int)$_SESSION['pastor_id'];
    $pr  = $conn->query("SELECT first_name, last_name, phone, address, profile_picture FROM pastors WHERE id=$pid LIMIT 1");
} else {
    $pr  = $conn->query("SELECT first_name, last_name, phone, address, profile_picture FROM pastors WHERE is_approved=1 ORDER BY id DESC LIMIT 1");
}
if ($pr && $pr->num_rows > 0) $pastor = $pr->fetch_assoc();

$pastor_name    = $pastor ? ucfirst($pastor['first_name']).' '.ucfirst($pastor['last_name']) : 'N/A';
$pastor_phone   = $pastor['phone']   ?? '';
$pastor_address = $pastor['address'] ?? '';

/* ── IMAGE EMBEDDING ───────────────────────────────────────────────── */
function b64_img($rel_path) {
    $full = __DIR__.'/uploads/'.basename($rel_path ?: 'default_avatar.png');
    if (!file_exists($full)) $full = __DIR__.'/uploads/default_avatar.png';
    if (!file_exists($full)) return null;
    return 'data:'.mime_content_type($full).';base64,'.base64_encode(file_get_contents($full));
}
function b64_file($path) {
    if (!file_exists($path)) return null;
    return 'data:'.mime_content_type($path).';base64,'.base64_encode(file_get_contents($path));
}

$logo_src      = b64_file(__DIR__.'/church_logo.jpg');
$pastor_pic    = b64_img($pastor['profile_picture'] ?? '');

/* ── SECTION DEFINITIONS ───────────────────────────────────────────── */
$leader_sections = [
    'general'      => ['title'=>'General Church Leaders',    'accent'=>'#10b981',
        'roles'=>['senior church elder','general church secretary','vice church secretary','treasurer'],
        'labels'=>['treasurer'=>'Church Treasurer']],
    'youths'       => ['title'=>'Youth Department Leaders',  'accent'=>'#6366f1',
        'roles'=>['youth chairperson','youth chairman','youth chairlady','vice youth chairperson','vice youth chairman','vice youth chairlady','youth secretary','vice youth secretary','youth treasurer','mama youth','baba youth','organizing secretary','vice organizing secretary','discipline master','vice discipline master','prayer coordinator','vice prayer coordinator','choir leader','vice choir leader','sport secretary','sports secretary','vice sport secretary','vice sports secretary','graduands secretary','vice graduands secretary']],
    'women'        => ['title'=>"Women's Ministry Leaders",  'accent'=>'#ec4899',
        'roles'=>['women chairlady','women chairperson','women chairman','vice women chairlady','vice women chairperson','vice women chairman','women secretary','vice women secretary','women treasurer','organizing secretary','vice organizing secretary','discipline master','vice discipline master','prayer coordinator','vice prayer coordinator','choir leader','vice choir leader']],
    'elders'       => ['title'=>'Elder Ministry Leaders',    'accent'=>'#f59e0b',
        'roles'=>['elder chairman','elder chairperson','elder chairlady','vice elder chairman','vice elder chairperson','vice elder chairlady','elder secretary','vice elder secretary','elder treasurer','organizing secretary','vice organizing secretary','discipline master','vice discipline master','prayer coordinator','vice prayer coordinator','choir leader','vice choir leader']],
    'sunday_school'=> ['title'=>'Sunday School Leaders',     'accent'=>'#0ea5e9',
        'roles'=>['sunday school patron','sunday school chairperson','sunday school chairman','sunday school chairlady','vice sunday school patron','vice sunday school chairperson','vice sunday school chairman','vice sunday school chairlady','sunday school secretary','vice sunday school secretary','sunday school treasurer','organizing secretary','vice organizing secretary','discipline master','vice discipline master','prayer coordinator','vice prayer coordinator','choir leader','vice choir leader','sport secretary','sports secretary','vice sport secretary','vice sports secretary','graduands secretary','vice graduands secretary','teacher','sunday school teacher','teachers of sunday school']],
    'building'     => ['title'=>'Building Department Leaders','accent'=>'#8b5cf6',
        'roles'=>['building chairperson','vice building chairperson','building secretary','vice building secretary','building treasurer']],
    'worship'      => ['title'=>'Worship Ministry Leaders',  'accent'=>'#d946ef',
        'roles'=>['worship leader','vice worship leader']],
    'ushers'       => ['title'=>'Ushering Ministry Leaders', 'accent'=>'#14b8a6',
        'roles'=>['head usher','usher']],
    'village'      => ['title'=>'Church Village Leaders',    'accent'=>'#f43f5e',
        'roles'=>['church village leader']],
];

/* ── LOAD LEADERS ──────────────────────────────────────────────────── */
$leaders = [];
$res = $conn->query("
    SELECT id, first_name, last_name, gender, department, church_role,
           profile_picture, phone, address, church_village, is_village_leader
    FROM members
    WHERE is_approved=1 
      AND (
          (church_role IS NOT NULL AND TRIM(church_role)!='' AND LOWER(TRIM(church_role))!='member')
          OR is_village_leader = 1
      )
    ORDER BY first_name ASC, last_name ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        if ($row['is_village_leader'] == 1 && stripos($row['church_role']??'', 'church village leader') === false) {
            $row['church_role'] = trim(($row['church_role']??'') . ', Church Village Leader', ', ');
        }
        $leaders[] = $row;
    }
}

/* ── ROLE HELPERS ──────────────────────────────────────────────────── */
function norm_role($r){ return strtolower(trim(preg_replace('/\s+/',' ',$r??''))); }
function parse_roles($str){
    $out=[];
    foreach(array_filter(array_map('trim',explode(',',str_replace('&',',',$str??'')))) as $p){
        $clean=trim(preg_replace('/\s*\(.*?\)\s*/','', $p));
        if($clean!=='') $out[]=['raw'=>$p,'norm'=>norm_role($clean)];
    }
    return $out;
}
function matches_group($role_norm, $raw_role, $dept, $sk){
    $d = strtolower(trim($dept));
    $raw_lower = strtolower(trim($raw_role));
    
    // If the role explicitly has a department suffix like (Sunday School), force it to that section
    if (strpos($raw_lower, '(sunday school)') !== false) return $sk === 'sunday_school';
    if (strpos($raw_lower, '(youths)') !== false || strpos($raw_lower, '(youth)') !== false) return $sk === 'youths';
    if (strpos($raw_lower, '(women)') !== false || strpos($raw_lower, '(womens') !== false) return $sk === 'women';
    if (strpos($raw_lower, '(elders)') !== false || strpos($raw_lower, '(elder)') !== false) return $sk === 'elders';
    
    // Explicit unambiguous roles
    if (strpos($role_norm, 'sunday school') !== false || $role_norm === 'teacher') return $sk === 'sunday_school';
    if (strpos($role_norm, 'youth') !== false) return $sk === 'youths';
    if (strpos($role_norm, 'women') !== false) return $sk === 'women';
    if (strpos($role_norm, 'elder') !== false) return $sk === 'elders';

    return match($sk){
        'youths'       => $d==='youths',
        'women'        => in_array($d,['womens ministry',"women's ministry",'women ministry']),
        'elders'       => $d==='elders',
        'sunday_school'=> $d==='sunday school',
        default        => true,
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>All Church Leaders</title>
<style>
*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;color-adjust:exact!important;box-sizing:border-box;margin:0;padding:0;}
@media print{@page{size:A4 landscape;margin: 0mm !important;} body{padding:15mm !important;} .no-print{display:none!important;}}
body{font-family:Arial,sans-serif;background:#fff;padding:10px;padding-bottom:72px;font-size:0.78rem;}

/* Watermark */
.watermark{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-45deg);
font-size:72px!important;color:rgba(30,58,138,0.18)!important;font-weight:bold;white-space:nowrap;
z-index:999999!important;opacity:1!important;pointer-events:none;letter-spacing:4px;text-transform:uppercase;mix-blend-mode:multiply;}

/* Footer */
.footer{position:fixed;bottom:0;left:0;right:0;text-align:center;font-size:9px;color:#777;
font-style:italic;background:rgba(255,255,255,0.95);padding:4px 0;z-index:10;border-top:1px solid #e5e7eb;}

/* ── COMBINED HEADER ── */
.main-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:14px;
    border-bottom: 2px solid #1e3a8a;
    padding-bottom: 10px;
}

/* Side logo panels */
.hdr-logo-box{
    padding:10px 14px;
    display:flex;align-items:center;justify-content:center;
}
.hdr-logo-box img{width:70px;height:70px;object-fit:contain;}

/* Centre: church name + pastor */
.hdr-center{
    flex: 1;
    text-align:center;
    padding:8px 10px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.hdr-center h2{color:#1e3a8a;font-size:1.1rem;font-weight:900;margin-bottom:2px;}
.hdr-center h3{color:#1e3a8a;font-size:0.9rem;font-weight:700;margin-bottom:3px;}
.hdr-center p {font-size:0.7rem;color:#555;}

.pastor-profile {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-top: 6px;
    margin-bottom: 2px;
}
.pastor-profile img, .pastor-profile .avatar-placeholder {
    width: 64px; height: 64px; border-radius: 50%;
    object-fit: cover; border: 2.5px solid #1e3a8a;
}
.pastor-profile .avatar-placeholder {
    background: #e5e7eb; display: flex; align-items: center; justify-content: center; font-size: 1.8rem;
}
.pastor-profile .ptitle {
    font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.06em; color: #1e3a8a; font-weight: 700; margin-top: 4px;
}
.pastor-profile .pname {
    font-size: 0.88rem; font-weight: 800; color: #1e3a8a;
}
.pastor-profile .pmeta {
    font-size: 0.68rem; color: #555;
}

/* Section header */
.section-title{
    display:block;padding:6px 14px;
    font-size:0.8rem;font-weight:800;text-transform:uppercase;
    letter-spacing:0.07em;color:#fff;margin:12px 0 6px;
    border-radius:5px;
    text-align: center;
}

/* Table */
table{width:100%;border-collapse:collapse;margin-bottom:6px;font-size:0.74rem;}
thead tr{color:#fff;}
thead th{padding:6px 7px;text-align:left;font-weight:700;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.04em;}
tbody tr:nth-child(even){background:#f9fafb;}
tbody td{padding:6px 7px;border-bottom:1px solid #e5e7eb;vertical-align:middle;}
.td-photo img,.td-photo .avatar-ph{width:40px;height:40px;border-radius:50%;object-fit:cover;}
.td-photo .avatar-ph{background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-size:1.1rem;}
.td-name{font-weight:700;color:#1e3a8a;}
.td-role{color:#374151;}
.td-meta{color:#6b7280;}

/* Signature */
.sig-block{display:flex;justify-content:space-between;align-items:flex-end;margin-top:38px;padding:0 20px;page-break-inside:avoid;}
.sig-col{text-align:center;}
.sig-label{font-size:0.78rem;font-weight:700;text-transform:uppercase;color:#1e3a8a;margin-bottom:4px;}
.sig-name{font-size:0.71rem;color:#333;margin-bottom:8px;}
.sig-line{display:inline-block;border-bottom:1px solid #000;width:180px;height:12px;}
.sig-date{display:flex;align-items:flex-end;gap:6px;margin-top:8px;font-size:0.77rem;}
.stamp{border:2px dashed #aaa;width:90px;height:90px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:0.62rem;text-transform:uppercase;line-height:1.5;text-align:center;}
</style>
</head>
<body>

<div class="watermark">E.A.P.C MUNYARI CHURCH</div>

<!-- Action buttons -->
<div class="no-print" style="display:flex;gap:10px;margin-bottom:12px;">
  <button onclick="window.print()" style="background:#1e3a8a;color:#fff;border:none;padding:9px 22px;border-radius:8px;cursor:pointer;font-size:0.9rem;font-weight:600;">🖨️ Print</button>
  <button onclick="window.close()" style="background:#6b7280;color:#fff;border:none;padding:9px 22px;border-radius:8px;cursor:pointer;font-size:0.9rem;">✕ Close</button>
</div>

<!-- Combined header: Logo | Church Name + Pastor | Logo -->
<div class="main-header">
  <!-- Left Logo -->
  <div class="hdr-logo-box">
    <?php if($logo_src): ?><img src="<?=$logo_src?>" alt="Logo"><?php endif; ?>
  </div>

  <!-- Centre -->
  <div class="hdr-center">
    <h2>E.A.P.C MUNYARI CHURCH</h2>
    <h3>CHURCH LEADERS REGISTER</h3>
    
    <div class="pastor-profile">
        <?php if($pastor_pic): ?>
          <img src="<?=$pastor_pic?>" alt="Pastor">
        <?php else: ?>
          <div class="avatar-placeholder">👤</div>
        <?php endif; ?>
        <div class="ptitle">Church Pastor</div>
        <div class="pname"><?=htmlspecialchars($pastor_name)?></div>
        <div class="pmeta">
          <?php if($pastor_phone):?>📞 <?=htmlspecialchars($pastor_phone)?><?php endif;?>
          <?php if($pastor_phone && $pastor_address):?> | <?php endif;?>
          <?php if($pastor_address):?>📍 <?=htmlspecialchars($pastor_address)?><?php endif;?>
        </div>
    </div>
    
    <p style="margin-top:4px;">Printed on: <?=htmlspecialchars($print_date)?></p>
  </div>

  <!-- Right Logo -->
  <div class="hdr-logo-box">
    <?php if($logo_src): ?><img src="<?=$logo_src?>" alt="Logo"><?php endif; ?>
  </div>
</div>

<?php
$total = 0;
foreach($leader_sections as $sk => $section):
    $shown = [];
    $rows  = [];

    foreach($section['roles'] as $role_name){
        foreach($leaders as $leader){
            foreach(parse_roles($leader['church_role']??'') as $role){
                if($role['norm'] !== $role_name) continue;
                if(!matches_group($role['norm'], $role['raw'], $leader['department']??'', $sk)) continue;
                $key = $leader['id'].':'.$sk.':'.$role_name;
                if(isset($shown[$key])) continue;
                $shown[$key] = true;

                $label = $section['labels'][$role_name]
                    ?? role_display_label(
                        trim(preg_replace('/\s*\(.*?\)\s*/','',$role['raw'])),
                        $leader['department']??'',
                        $leader['gender']??''
                       );

                $residence = trim($leader['address']??'');
                if(empty($residence)) $residence = trim($leader['church_village']??'');

                $rows[] = [
                    'pic'       => b64_img($leader['profile_picture']??''),
                    'name'      => ucfirst($leader['first_name']).' '.ucfirst($leader['last_name']),
                    'role'      => $label,
                    'phone'     => $leader['phone']??'',
                    'village'   => $leader['church_village']??'',
                    'residence' => $leader['address']??'',
                    'accent'    => $section['accent'],
                ];
                $total++;
            }
        }
    }

    if(empty($rows)) continue;
?>
  <div class="section-title" style="background:<?=htmlspecialchars($section['accent'])?>;">
    ═══ <?=htmlspecialchars(strtoupper($section['title']))?> (<?=count($rows)?>) ═══
  </div>

  <table>
    <thead>
      <tr style="background:<?=htmlspecialchars($section['accent'])?>;">
        <th style="width:48px;">Photo</th>
        <th>Name</th>
        <th>Role / Position</th>
        <th>Phone Number</th>
        <th>Church Village</th>
        <th>Residence / Area</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($rows as $i => $r): ?>
      <tr>
        <td class="td-photo">
          <?php if($r['pic']): ?>
            <img src="<?=$r['pic']?>" style="border:2px solid <?=htmlspecialchars($r['accent'])?>;" alt="">
          <?php else: ?>
            <div class="avatar-ph">👤</div>
          <?php endif; ?>
        </td>
        <td class="td-name"><?=htmlspecialchars($r['name'])?></td>
        <td class="td-role"><?=htmlspecialchars($r['role'])?></td>
        <td class="td-meta"><?=htmlspecialchars($r['phone'])?></td>
        <td class="td-meta"><?=htmlspecialchars($r['village'])?></td>
        <td class="td-meta"><?=htmlspecialchars($r['residence'])?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endforeach; ?>

<?php if($total===0): ?>
  <p style="text-align:center;color:#888;margin-top:40px;">No leaders have been assigned yet.</p>
<?php endif; ?>

<!-- Signature block -->
<div class="sig-block">
  <div class="sig-col">
    <div class="sig-label">Church Pastor</div>
    <div class="sig-name"><?=htmlspecialchars($pastor_name)?></div>
    <div style="display:flex;align-items:flex-end;gap:8px;">
      <span style="font-style:italic;font-size:0.77rem;">Sign:</span>
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

<div class="footer">Generated from E.A.P.C Munyari Portal | Printed on: <?=htmlspecialchars($print_date)?></div>

<script>
window.addEventListener('load', function(){ window.print(); });
</script>
</body>
</html>
