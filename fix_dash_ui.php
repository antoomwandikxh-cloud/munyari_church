<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];

$helpers = "
function getStatusBadge(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return null;
    const group = input.closest('.form-group');
    if (!group) return null;
    let label = group.querySelector('label');
    if (!label) return null;
    
    let badge = label.querySelector('.status-badge');
    if (!badge) {
        badge = document.createElement('span');
        badge.className = 'status-badge';
        badge.style.marginLeft = '8px';
        badge.style.fontSize = '0.7rem';
        badge.style.padding = '2px 6px';
        badge.style.borderRadius = '4px';
        badge.style.fontWeight = '800';
        badge.style.textTransform = 'uppercase';
        badge.style.letterSpacing = '0.5px';
        badge.style.transition = 'all 0.3s ease';
        label.appendChild(badge);
    }
    return badge;
}

function setFieldStatus(inputId, status) {
    const badge = getStatusBadge(inputId);
    if (!badge) return;
    
    if (status === 'FILLING') {
        badge.textContent = 'FILLING';
        badge.style.backgroundColor = '#fef08a'; 
        badge.style.color = '#854d0e';
    } else if (status === 'FILLED') {
        badge.textContent = 'FILLED';
        badge.style.backgroundColor = '#dcfce7'; 
        badge.style.color = '#166534';
    } else if (status === 'ACTIVATED') {
        badge.textContent = 'ACTIVATED';
        badge.style.backgroundColor = '#dbeafe'; 
        badge.style.color = '#1e40af';
    } else if (status === 'EMPTY') {
        badge.textContent = '';
        badge.style.backgroundColor = 'transparent';
        badge.style.color = 'transparent';
    }
}
";

$old_seq = "function seqUnlock(currentId, nextId) {
                    const current = document.getElementById(currentId);
                    const next    = document.getElementById(nextId);
                    if (!current || !next) return;
                    const filled = current.tagName === 'SELECT'
                        ? current.value !== ''
                        : current.value.trim().length > 0 && current.checkValidity();
                    next.disabled = !filled;
                    if (!filled && next.tagName === 'INPUT') next.value = '';
                }";

$old_seq2 = "function seqUnlock(currentId, nextId) {
        const current = document.getElementById(currentId);
        const next    = document.getElementById(nextId);
        if (!current || !next) return;
        const filled = current.tagName === 'SELECT'
            ? current.value !== ''
            : current.value.trim().length > 0 && current.checkValidity();
        next.disabled = !filled;
        if (!filled && next.tagName === 'INPUT') next.value = '';
    }";
    
$old_seq3 = "function seqUnlock(currentId, nextId) {
                        const current = document.getElementById(currentId);
                        const next    = document.getElementById(nextId);
                        if (!current || !next) return;
                        const filled = current.tagName === 'SELECT'
                            ? current.value !== ''
                            : current.value.trim().length > 0 && current.checkValidity();
                        next.disabled = !filled;
                        if (!filled && next.tagName === 'INPUT') next.value = '';
                    }";


foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // First, let's inject the event listeners to ALL inputs on the page on DOMContentLoaded
    $event_listeners = "
document.addEventListener('DOMContentLoaded', () => {
    const inputs = document.querySelectorAll('input, select');
    inputs.forEach(input => {
        if (!input.closest('.form-group')) return;
        if (input.type === 'radio' || input.type === 'checkbox' || input.type === 'hidden') return;
        
        input.addEventListener('input', () => {
            if (input.value.trim().length > 0) {
                if(typeof setFieldStatus === 'function') setFieldStatus(input.id, 'FILLING');
            } else {
                if(typeof setFieldStatus === 'function') setFieldStatus(input.id, 'EMPTY');
            }
        });
        
        input.addEventListener('blur', () => {
            if (input.value.trim().length > 0 && input.checkValidity()) {
                if(typeof setFieldStatus === 'function') setFieldStatus(input.id, 'FILLED');
            }
        });
    });
});
";
    if (strpos($c, "document.querySelectorAll('input, select')") === false) {
        $c = str_replace("</script>\n</body>", $event_listeners . "\n</script>\n</body>", $c);
        if (strpos($c, "getStatusBadge") === false) {
             $c = str_replace("</script>\n</body>", $helpers . "\n</script>\n</body>", $c);
        }
    }

    // Now regex replace all variants of seqUnlock
    $pattern = '/function seqUnlock\(currentId, nextId\) \{[\s\S]*?if \(\!filled && next\.tagName === \'INPUT\'\) next\.value = \'\';\s*\}/';
    
    $new_seq = "function seqUnlock(currentId, nextId) {
    const current = document.getElementById(currentId);
    const next    = document.getElementById(nextId);
    if (!current || !next) return;
    
    const filled = current.tagName === 'SELECT'
        ? current.value !== ''
        : current.value.trim().length > 0 && current.checkValidity();
        
    const wasDisabled = next.disabled;
    next.disabled = !filled;
    
    if (!filled && next.tagName === 'INPUT') next.value = '';
    
    if (filled) {
        if (wasDisabled && !next.disabled) {
            if (typeof setFieldStatus === 'function' && next.value.trim() === '') {
                setFieldStatus(nextId, 'ACTIVATED');
            }
        }
    } else {
        if (typeof setFieldStatus === 'function') {
            setFieldStatus(nextId, 'EMPTY');
        }
    }
}";
    
    $c = preg_replace($pattern, $new_seq, $c);
    
    file_put_contents($file, $c);
    echo "Fixed $file\n";
}
?>
