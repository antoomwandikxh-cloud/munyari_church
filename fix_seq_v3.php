<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];

// The new clean seqUnlock function
$new_func = <<<'JSFUNC'
function seqUnlock(currentId, nextId) {
    const current = document.getElementById(currentId);
    const next    = document.getElementById(nextId);
    if (!current || !next) return;
    const filled = current.tagName === 'SELECT'
        ? current.value !== ''
        : current.value.trim().length > 0 && current.checkValidity();
    const wasDis = next.disabled;
    next.disabled = !filled;
    if (!filled && next.tagName !== 'SELECT') next.value = '';

    // --- FILLING badge on current field while typing (set by oninput directly) ---
    // --- FILLED badge on current field when done ---
    const cGrp = current.closest('.form-group');
    const cLbl = cGrp ? cGrp.querySelector('label') : null;
    if (cLbl) {
        let cb = cLbl.querySelector('.sb');
        if (!cb) { cb = document.createElement('span'); cb.className='sb'; cb.style.cssText='margin-left:8px;font-size:0.68rem;padding:2px 7px;border-radius:4px;font-weight:800;text-transform:uppercase;vertical-align:middle;display:inline-block;transition:all 0.2s;'; cLbl.appendChild(cb); }
        if (filled) { cb.textContent='\u2713 FILLED'; cb.style.background='#dcfce7'; cb.style.color='#166534'; }
        else { cb.textContent=''; cb.style.background='transparent'; cb.style.color='transparent'; }
    }
    // --- ACTIVATED badge on next field ---
    const nGrp = next.closest('.form-group');
    const nLbl = nGrp ? nGrp.querySelector('label') : null;
    if (nLbl) {
        let nb = nLbl.querySelector('.sb');
        if (!nb) { nb = document.createElement('span'); nb.className='sb'; nb.style.cssText='margin-left:8px;font-size:0.68rem;padding:2px 7px;border-radius:4px;font-weight:800;text-transform:uppercase;vertical-align:middle;display:inline-block;transition:all 0.2s;'; nLbl.appendChild(nb); }
        if (filled && wasDis) { nb.textContent='ACTIVATED'; nb.style.background='#dbeafe'; nb.style.color='#1e40af'; }
        else if (!filled) { nb.textContent=''; nb.style.background='transparent'; nb.style.color='transparent'; }
    }
}
JSFUNC;

// oninput FILLING snippet to inject at start of every oninput that has seqUnlock
$filling_snippet = "setFieldStatus_filling(this.id); ";

foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // Remove ALL old seqUnlock function definitions using a broad pattern
    $c = preg_replace('/function seqUnlock\s*\(currentId,\s*nextId\)\s*\{[\s\S]*?\/\/ -----------------------------------------\s*\}/m', $new_func, $c, 1);
    // Second occurrence (different indentation)
    $c = preg_replace('/function seqUnlock\s*\(currentId,\s*nextId\)\s*\{[\s\S]*?\/\/ -----------------------------------------\s*\}/m', $new_func, $c, 1);
    
    // Inject FILLING into every oninput that calls seqUnlock but doesn't have setFieldStatus yet
    $c = preg_replace_callback(
        '/oninput="([^"]*seqUnlock[^"]*)"/i',
        function($m) {
            $v = $m[1];
            if (strpos($v, 'setFieldStatus_filling') === false) {
                $v = "setFieldStatus_filling(this.id); " . $v;
            }
            return 'oninput="' . $v . '"';
        },
        $c
    );
    
    // Add onblur FILLED for inputs with seqUnlock in oninput
    $c = preg_replace_callback(
        '/<input([^>]*oninput="[^"]*seqUnlock[^"]*"[^>]*)>/i',
        function($m) {
            $attrs = $m[1];
            if (strpos($attrs, 'onblur') === false) {
                $attrs .= " onblur=\"setFieldStatus_blur(this)\"";
            }
            return '<input' . $attrs . '>';
        },
        $c
    );
    
    // Also for selects with onchange containing seqUnlock
    $c = preg_replace_callback(
        '/onchange="([^"]*seqUnlock[^"]*)"/i',
        function($m) {
            $v = $m[1];
            if (strpos($v, 'setFieldStatus_filling') === false) {
                $v .= " setFieldStatus_blur(document.getElementById(currentId||this.id));";
            }
            return 'onchange="' . $v . '"';
        },
        $c
    );
    
    file_put_contents($file, $c);
    $seq_count = substr_count($c, 'function seqUnlock');
    echo "Done $file (seqUnlock: $seq_count)\n";
}
?>
