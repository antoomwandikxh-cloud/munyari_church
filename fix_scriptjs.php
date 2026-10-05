<?php
$c = file_get_contents('script.js');

// Remove everything after the original validateInput function (around line 86)
// This removes all the broken badge logic we appended
$pos = strpos($c, '// Initialize real-time status feedback');
if ($pos !== false) {
    $c = substr($c, 0, $pos);
}

// Replace seqUnlock in script.js with the robust one that handles badges
$new_seqUnlock = <<<JS
function seqUnlock(currentId, nextId) {
    const current = document.getElementById(currentId);
    const next    = document.getElementById(nextId);
    if (!current || !next) return;
    
    // Check if filled
    const filled = current.tagName === 'SELECT'
        ? current.value !== ''
        : current.value.trim().length > 0 && current.checkValidity();
        
    const wasDisabled = next.disabled;
    next.disabled = !filled;
    
    if (!filled && next.tagName === 'INPUT') next.value = '';
    
    // Badge UI logic
    if (typeof setFieldStatus_filling === 'function') {
        if (filled) {
            setFieldStatus_blur(current);
            if (wasDisabled && !next.disabled) {
                seqUnlock_badge_next(nextId, true);
            }
        } else {
            setFieldStatus_blur(current);
            seqUnlock_badge_next(nextId, false);
        }
    }
}

// Single unified badge helper functions
function getStatusBadge(el) {
    if (!el) return null;
    const group = el.closest('.form-group');
    if (!group) return null;
    const label = group.querySelector('label');
    if (!label) return null;
    
    // Remove old conflicting badges if they exist
    const oldBadges = label.querySelectorAll('.status-badge');
    oldBadges.forEach(b => b.remove());
    
    let badge = label.querySelector('.sb');
    if (!badge) {
        badge = document.createElement('span');
        badge.className = 'sb';
        badge.style.cssText = 'margin-left:8px;font-size:0.68rem;padding:2px 7px;border-radius:4px;font-weight:800;text-transform:uppercase;vertical-align:middle;display:inline-block;transition:all 0.2s;';
        label.appendChild(badge);
    }
    return badge;
}

function setFieldStatus_filling(id) {
    const el = document.getElementById(id);
    const b = getStatusBadge(el);
    if (!b) return;
    if (el.value.trim().length > 0) {
        b.textContent = 'FILLING';
        b.style.background = '#fef08a';
        b.style.color = '#854d0e';
    } else {
        b.textContent = '';
        b.style.background = 'transparent';
    }
}

function setFieldStatus_blur(el) {
    if (typeof el === 'string') el = document.getElementById(el);
    const b = getStatusBadge(el);
    if (!b) return;
    if (el.value.trim().length > 0 && el.checkValidity()) {
        b.textContent = '\u2713 FILLED';
        b.style.background = '#dcfce7';
        b.style.color = '#166534';
    } else if (!el.value.trim().length) {
        b.textContent = '';
        b.style.background = 'transparent';
    }
}

function seqUnlock_badge_next(nextId, unlocked) {
    const el = document.getElementById(nextId);
    const b = getStatusBadge(el);
    if (!b) return;
    if (unlocked && el.value.trim() === '') {
        b.textContent = 'ACTIVATED';
        b.style.background = '#dbeafe';
        b.style.color = '#1e40af';
    } else if (!unlocked) {
        b.textContent = '';
        b.style.background = 'transparent';
    }
}

// Automatically attach listeners to all form-group inputs globally
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.form-group input, .form-group select').forEach(el => {
        if (['radio', 'checkbox', 'hidden', 'submit', 'button'].includes(el.type)) return;
        
        el.addEventListener('input', () => {
            setFieldStatus_filling(el.id);
        });
        
        el.addEventListener('blur', () => {
            setFieldStatus_blur(el);
        });
        
        el.addEventListener('change', () => {
            if (el.tagName === 'SELECT' && el.value !== '') {
                setFieldStatus_blur(el);
            }
        });
    });
});
JS;

// Replace the old seqUnlock
$c = preg_replace('/function seqUnlock\s*\(currentId,\s*nextId\)\s*\{[\s\S]*?if \(\!filled && next\.tagName === \'INPUT\'\) next\.value = \'\';\n\}/m', $new_seqUnlock, $c);

file_put_contents('script.js', $c);
echo "Cleaned and updated script.js\n";
?>
