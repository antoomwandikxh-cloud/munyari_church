<?php
$files = ['register_page.php', 'admin_dashboard.php', 'pastor_dashboard.php'];

$js_addition = <<<JS
        if (filled) {
            next.disabled = false;
            // Add activated badge to next field's label
            const label = document.querySelector('label[for="' + nextId + '"]');
            if (label && !label.querySelector('.activated-badge')) {
                const badge = document.createElement('span');
                badge.className = 'activated-badge';
                badge.style.color = '#10b981';
                badge.style.fontSize = '0.75rem';
                badge.style.fontWeight = 'bold';
                badge.style.marginLeft = '8px';
                badge.style.animation = 'fadeIn 0.3s ease-in-out';
                badge.innerHTML = '&#10004; Activated';
                label.appendChild(badge);
            }
        } else {
            next.disabled = true;
            next.value = '';
            // Remove badge
            const label = document.querySelector('label[for="' + nextId + '"]');
            if (label) {
                const badge = label.querySelector('.activated-badge');
                if (badge) badge.remove();
            }
            // Trigger input/change on next to lock anything down the chain
            const evt = new Event(next.tagName === 'SELECT' ? 'change' : 'input');
            next.dispatchEvent(evt);
        }
JS;

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Replace the inner block of seqUnlock
    // Original:
    // if (filled) {
    //     next.disabled = false;
    // } else {
    //     next.disabled = true;
    //     next.value = '';
    //     // Trigger input/change...
    //     const evt = new Event...
    //     next.dispatchEvent(evt);
    // }
    
    // Use regex to replace
    $pattern = '/if\s*\(\s*filled\s*\)\s*\{\s*next\.disabled\s*=\s*false;\s*\}\s*else\s*\{\s*next\.disabled\s*=\s*true;\s*next\.value\s*=\s*(?:""|\'\'|``);\s*(?:\/\/[^\n]*\n\s*)*const\s+evt\s*=\s*new\s+Event\([^)]+\);\s*next\.dispatchEvent\(evt\);\s*\}/';
    
    if (preg_match($pattern, $content)) {
        $content = preg_replace($pattern, $js_addition, $content);
        file_put_contents($file, $content);
        echo "Updated $file\n";
    } else {
        echo "Pattern not found in $file\n";
    }
}
?>
