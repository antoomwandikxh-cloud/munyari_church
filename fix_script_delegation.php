<?php
$c = file_get_contents('script.js');

// Replace the DOMContentLoaded block with event delegation
$new_events = <<<JS
// Automatically attach listeners using event delegation (works regardless of load time or dynamic tabs)
document.addEventListener('input', (e) => {
    if (e.target.matches('.form-group input, .form-group select')) {
        if (!['radio', 'checkbox', 'hidden', 'submit', 'button'].includes(e.target.type)) {
            setFieldStatus_filling(e.target.id);
        }
    }
});

document.addEventListener('focusout', (e) => {
    if (e.target.matches('.form-group input, .form-group select')) {
        if (!['radio', 'checkbox', 'hidden', 'submit', 'button'].includes(e.target.type)) {
            setFieldStatus_blur(e.target);
        }
    }
});

document.addEventListener('change', (e) => {
    if (e.target.matches('.form-group select')) {
        if (e.target.value !== '') {
            setFieldStatus_blur(e.target);
        }
    }
});
JS;

$c = preg_replace('/document\.addEventListener\(\'DOMContentLoaded\', \(\) => \{[\s\S]*?\}\);\s*$/', $new_events, $c);

file_put_contents('script.js', $c);
echo "Updated script.js to use event delegation\n";
?>
