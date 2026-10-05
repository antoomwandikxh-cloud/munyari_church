<?php
$files = ['admin_dashboard.php', 'pastor_dashboard.php'];
foreach ($files as $file) {
    $c = file_get_contents($file);
    
    // In the existing seqUnlock body, after "next.disabled = !filled;" add the badge call
    // The pattern is universal across both files now
    $old = "next.disabled = !filled;\n                    if (!filled && next.tagName !== 'SELECT') next.value = '';";
    $new = "next.disabled = !filled;\n                    if (!filled && next.tagName !== 'SELECT') next.value = '';\n                    seqUnlock_badge_next(nextId, wasDis);";
    
    // Also need wasDis declared before
    $c = str_replace(
        "const filled = current.tagName === 'SELECT'\n                        ? current.value !== ''\n                        : current.value.trim().length > 0 && current.checkValidity();\n                    next.disabled = !filled;\n                    if (!filled && next.tagName !== 'SELECT') next.value = '';",
        "const filled = current.tagName === 'SELECT'\n                        ? current.value !== ''\n                        : current.value.trim().length > 0 && current.checkValidity();\n                    const wasDis = next.disabled;\n                    next.disabled = !filled;\n                    if (!filled && next.tagName !== 'SELECT') next.value = '';\n                    seqUnlock_badge_next(nextId, wasDis);",
        $c
    );
    
    // Second seqUnlock (different indentation)
    $c = str_replace(
        "const filled = current.tagName === 'SELECT'\n            ? current.value !== ''\n            : current.value.trim().length > 0 && current.checkValidity();\n        next.disabled = !filled;\n        if (!filled) next.value = '';",
        "const filled = current.tagName === 'SELECT'\n            ? current.value !== ''\n            : current.value.trim().length > 0 && current.checkValidity();\n        const wasDis = next.disabled;\n        next.disabled = !filled;\n        if (!filled) next.value = '';\n        seqUnlock_badge_next(nextId, wasDis);",
        $c
    );

    file_put_contents($file, $c);
    echo "Updated: $file\n";
}
?>
