<?php
$helpers = <<<'JS'
function setFieldStatus_filling(id) {
    var el = document.getElementById(id); if (!el) return;
    var g = el.closest('.form-group'); if (!g) return;
    var lbl = g.querySelector('label'); if (!lbl) return;
    var b = lbl.querySelector('.sb');
    if (!b) { b = document.createElement('span'); b.className='sb'; b.style.cssText='margin-left:8px;font-size:0.68rem;padding:2px 7px;border-radius:4px;font-weight:800;text-transform:uppercase;vertical-align:middle;display:inline-block;transition:all 0.2s;'; lbl.appendChild(b); }
    if (el.value.trim().length > 0) { b.textContent='FILLING'; b.style.background='#fef08a'; b.style.color='#854d0e'; }
    else { b.textContent=''; b.style.background='transparent'; b.style.color='transparent'; }
}
function setFieldStatus_blur(el) {
    if (!el) return;
    var g = el.closest('.form-group'); if (!g) return;
    var lbl = g.querySelector('label'); if (!lbl) return;
    var b = lbl.querySelector('.sb');
    if (!b) { b = document.createElement('span'); b.className='sb'; b.style.cssText='margin-left:8px;font-size:0.68rem;padding:2px 7px;border-radius:4px;font-weight:800;text-transform:uppercase;vertical-align:middle;display:inline-block;transition:all 0.2s;'; lbl.appendChild(b); }
    if (el.value.trim().length > 0 && el.checkValidity()) { b.textContent='\u2713 FILLED'; b.style.background='#dcfce7'; b.style.color='#166534'; }
    else if (!el.value.trim().length) { b.textContent=''; b.style.background='transparent'; b.style.color='transparent'; }
}
JS;

$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);
    // Inject helpers just before the seqUnlock function
    $c = str_replace('function seqUnlock(currentId, nextId) {', $helpers . "\nfunction seqUnlock(currentId, nextId) {", $c, $count);
    file_put_contents($file, $c);
    echo "Injected helpers in $file ($count replacement(s))\n";
}
?>
