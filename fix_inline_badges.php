<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // Add setFieldStatus calls to every oninput that already calls seqUnlock
    // Pattern: oninput="...seqUnlock(...)"  =>  oninput="...seqUnlock(...); setFieldStatus(this.id,'FILLING');"
    // Also add onblur for FILLED
    
    // We'll inject setFieldStatus into every input/select that has seqUnlock in oninput/onchange
    
    // 1. For text/password inputs with oninput containing seqUnlock:
    //    Add setFieldStatus(this.id,'FILLING') at start of oninput
    //    Add onblur="if(this.value.trim().length>0&&this.checkValidity())setFieldStatus(this.id,'FILLED');"
    
    // Match: oninput="[...]seqUnlock('[a-z_]+','[a-z_]+')" 
    // Insert setFieldStatus(this.id,'FILLING') at the beginning
    $c = preg_replace_callback(
        '/oninput="([^"]*seqUnlock\([^)]+\)[^"]*)"/i',
        function($m) {
            $existing = $m[1];
            // Add FILLING at start if not already there
            if (strpos($existing, 'setFieldStatus') === false) {
                $existing = "setFieldStatus(this.id,'FILLING'); " . $existing;
            }
            return 'oninput="' . $existing . '"';
        },
        $c
    );
    
    // 2. For selects with onchange containing seqUnlock:
    //    Add setFieldStatus(this.id,'FILLED') 
    $c = preg_replace_callback(
        '/onchange="([^"]*seqUnlock\([^)]+\)[^"]*)"/i',
        function($m) {
            $existing = $m[1];
            if (strpos($existing, 'setFieldStatus') === false) {
                $existing = $existing . " setFieldStatus(this.id,'FILLED');";
            }
            return 'onchange="' . $existing . '"';
        },
        $c
    );
    
    // 3. Add onblur FILLED for inputs that have seqUnlock in oninput but no onblur yet
    //    We'll do this by finding inputs that have the status call in oninput but no onblur
    $c = preg_replace_callback(
        '/<input([^>]*oninput="[^"]*seqUnlock[^"]*"[^>]*)>/i',
        function($m) {
            $attrs = $m[1];
            // Only add onblur if not already there
            if (strpos($attrs, 'onblur') === false) {
                $attrs .= " onblur=\"if(this.value.trim().length>0&&this.checkValidity()){setFieldStatus(this.id,'FILLED');}else if(!this.value.trim().length){setFieldStatus(this.id,'EMPTY');}\"";
            }
            return '<input' . $attrs . '>';
        },
        $c
    );
    
    file_put_contents($file, $c);
    echo "Done: $file\n";
}
?>
