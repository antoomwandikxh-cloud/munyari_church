<?php
$files = [
    'C:\\xampp\\htdocs\\munyari_church\\admin_dashboard.php',
    'C:\\xampp\\htdocs\\munyari_church\\pastor_dashboard.php'
];

// Script block to inject BEFORE the ssRegForm
$js_block = <<<'JSBLOCK'
                <script>
                function seqUnlock(currentId, nextId) {
                    const current = document.getElementById(currentId);
                    const next    = document.getElementById(nextId);
                    if (!current || !next) return;
                    const filled = current.tagName === 'SELECT'
                        ? current.value !== ''
                        : current.value.trim().length > 0 && current.checkValidity();
                    next.disabled = !filled;
                    if (!filled && next.tagName !== 'SELECT') next.value = '';
                }
                function validateInput(input, type) {
                    let errorMsg = input.parentNode.querySelector('.err-msg');
                    if (!errorMsg) {
                        errorMsg = document.createElement('span');
                        errorMsg.className = 'err-msg';
                        errorMsg.style.color = '#ef4444';
                        errorMsg.style.fontSize = '0.8rem';
                        errorMsg.style.display = 'block';
                        errorMsg.style.marginTop = '4px';
                        input.parentNode.appendChild(errorMsg);
                    }
                    if (type === 'name') {
                        if (/[^A-Za-z\s,.-]/.test(input.value)) {
                            errorMsg.innerText = 'Only letters allowed.';
                            input.setCustomValidity('Invalid');
                            setTimeout(() => { input.value = input.value.replace(/[^A-Za-z\s,.-]/g, ''); }, 300);
                        } else {
                            errorMsg.innerText = '';
                            input.setCustomValidity('');
                        }
                    } else if (type === 'phone') {
                        if (/[^\d]/.test(input.value)) {
                            errorMsg.innerText = 'Only digits allowed.';
                            input.setCustomValidity('Invalid');
                            setTimeout(() => { input.value = input.value.replace(/[^\d]/g, ''); }, 300);
                        } else {
                            errorMsg.innerText = '';
                            input.setCustomValidity('');
                        }
                    }
                }
                </script>

JSBLOCK;

foreach ($files as $file) {
    $content = file_get_contents($file);

    // Remove any previously injected window.seqUnlock typeof blocks to avoid duplicates
    $content = preg_replace('/<script>\s*if \(typeof window\.validateInput.*?<\/script>/s', '', $content);

    // Find the ssRegForm and inject the script block before it
    $marker = '<form method="POST" action="?tab=manage_sunday_school" id="ssRegForm"';
    $pos = strpos($content, $marker);
    if ($pos !== false) {
        $content = substr_replace($content, $js_block, $pos, 0);
        echo basename($file) . ": Script injected before ssRegForm.\n";
    } else {
        echo basename($file) . ": WARNING - ssRegForm marker not found!\n";
    }

    file_put_contents($file, $content);
}

echo "\nSyntax check:\n";
foreach ($files as $file) {
    exec('C:\\xampp\\php\\php.exe -l "' . $file . '" 2>&1', $out, $code);
    echo basename($file) . ': ' . implode(' ', $out) . "\n";
    $out = [];
}
echo "Done!\n";
?>
