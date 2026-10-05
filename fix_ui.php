<?php
$c = file_get_contents('script.js');

// Update getStatusBadge cssText
$c = preg_replace(
    '/(b(?:adge)?\.style\.cssText\s*=\s*\')[^\']*(\';)/i', 
    '$1margin-left:8px; font-size:0.7rem; padding:2px 8px; border-radius:12px; font-weight:600; vertical-align:middle; display:inline-block; transition:all 0.3s ease; border:1px solid transparent; letter-spacing:0.2px;$2', 
    $c
);

// Update FILLING
$c = preg_replace('/b\.textContent = \'FILLING\';\s*b\.style\.background = \'#fef08a\';\s*b\.style\.color = \'#854d0e\';/i', 'b.textContent = \'Filling...\'; b.style.background = \'#fef9c3\'; b.style.color = \'#a16207\'; b.style.borderColor = \'#fde047\';', $c);

// Update FILLED
$c = preg_replace('/b\.textContent = \'\\\\u2713 FILLED\';\s*b\.style\.background = \'#dcfce7\';\s*b\.style\.color = \'#166534\';/i', 'b.textContent = \'✓ Filled\'; b.style.background = \'#f0fdf4\'; b.style.color = \'#15803d\'; b.style.borderColor = \'#bbf7d0\';', $c);

// Update ACTIVATED
$c = preg_replace('/b\.textContent = \'ACTIVATED\';\s*b\.style\.background = \'#dbeafe\';\s*b\.style\.color = \'#1e40af\';/i', 'b.textContent = \'Activated\'; b.style.background = \'#eff6ff\'; b.style.color = \'#1d4ed8\'; b.style.borderColor = \'#bfdbfe\';', $c);

file_put_contents('script.js', $c);
echo "Updated script.js\n";
?>
