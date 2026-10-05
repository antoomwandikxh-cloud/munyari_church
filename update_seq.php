<?php
$f = 'C:\xampp\htdocs\munyari_church\script.js';
$c = file_get_contents($f);

$old_seq = "function seqUnlock(currentId, nextId) {
    const current = document.getElementById(currentId);
    const next    = document.getElementById(nextId);
    if (!current || !next) return;
    // Just check if field has content - don't call checkValidity() here
    // because validateInput() may have set a temporary custom validity
    // that would block unlocking even on valid input.
    const filled = current.tagName === 'SELECT'
        ? current.value !== ''
        : current.value.trim().length > 0 && current.checkValidity();
    next.disabled = !filled;
    // Only clear value for text/password inputs, not buttons or selects
    if (!filled && next.tagName === 'INPUT') next.value = '';
}";

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
    
    // UI Feedback logic
    if (filled) {
        // If this field is valid and unblocks the next, mark the next as ACTIVATED 
        // ONLY if it wasn't already filled.
        if (wasDisabled && !next.disabled) {
            if (typeof setFieldStatus === 'function' && next.value.trim() === '') {
                setFieldStatus(nextId, 'ACTIVATED');
            }
        }
    } else {
        // If we clear/disable the next one, remove its status
        if (typeof setFieldStatus === 'function') {
            setFieldStatus(nextId, 'EMPTY');
        }
    }
}";

$c = str_replace($old_seq, $new_seq, $c);
file_put_contents($f, $c);
echo "Updated seqUnlock in script.js\n";
?>
