<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];

// New seqUnlock body - calls global setFieldStatus from script.js 
$pattern = '/function seqUnlock\s*\(currentId,\s*nextId\)\s*\{([\s\S]*?)^\s*\}/m';

$replacement_template = 'function seqUnlock(currentId, nextId) {
    var cur = document.getElementById(currentId), nxt = document.getElementById(nextId);
    if (!cur || !nxt) return;
    var filled = cur.tagName==="SELECT" ? cur.value!=="" : cur.value.trim().length>0 && cur.checkValidity();
    var wasDis = nxt.disabled;
    nxt.disabled = !filled;
    if (!filled && nxt.tagName==="INPUT") nxt.value = "";
    if (typeof setFieldStatus === "function") {
        if (filled) {
            setFieldStatus(currentId, "FILLED");
            if (wasDis && !nxt.disabled && nxt.value.trim()==="") setFieldStatus(nextId, "ACTIVATED");
        } else { setFieldStatus(nextId, "EMPTY"); }
    }
}';

foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // Replace seqUnlock bodies  
    $c = preg_replace_callback($pattern, function($m) use ($replacement_template) {
        preg_match('/^([ \t]*)function/', $m[0], $ind);
        $indent = $ind[1] ?? '';
        $lines = explode("\n", $replacement_template);
        return $indent . implode("\n" . $indent, $lines);
    }, $c);
    
    // For pastor_dashboard.php - ensure script.js is loaded (add just before </head> if missing)
    if ($file === 'pastor_dashboard.php' && strpos($c, 'script.js') === false) {
        // Find </head> - specifically the real one (not inside JS strings)
        $realHeadPos = strpos($c, '</head>');
        if ($realHeadPos !== false) {
            $c = substr_replace($c, "    <script src=\"script.js\"></script>\n</head>", $realHeadPos, strlen('</head>'));
        }
    }
    
    file_put_contents($file, $c);
    $seq_count = substr_count($c, 'function seqUnlock');
    echo "Done $file — seqUnlock: $seq_count\n";
}
?>
