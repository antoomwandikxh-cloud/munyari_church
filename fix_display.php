<?php
$c = file_get_contents('script.js');

// For getStatusBadge, we'll initialize with display:none so it doesn't take space when empty
$c = preg_replace(
    '/(margin-left:8px; font-size:0.7rem; padding:2px 8px; border-radius:12px; font-weight:600; vertical-align:middle;) display:inline-block; (transition:all 0.3s ease; border:1px solid transparent; letter-spacing:0.2px;)/i', 
    '$1 display:none; $2', 
    $c
);

// Modify active states to explicitly set display:inline-block
$c = str_replace('b.textContent = \'Filling...\';', 'b.style.display = \'inline-block\'; b.textContent = \'Filling...\';', $c);
$c = str_replace('b.textContent = \'\u2713 Filled\';', 'b.style.display = \'inline-block\'; b.textContent = \'\u2713 Filled\';', $c);
$c = str_replace('b.textContent = \'Activated\';', 'b.style.display = \'inline-block\'; b.textContent = \'Activated\';', $c);

// Modify empty states to explicitly set display:none
$c = preg_replace('/b\.textContent = \'\';\s*b\.style\.background = \'transparent\';/i', 'b.textContent = \'\'; b.style.display = \'none\';', $c);

file_put_contents('script.js', $c);
echo "Updated script.js with display none/inline-block logic\n";
?>
