<?php
$files = ['C:\\xampp\\htdocs\\munyari_church\\admin_dashboard.php', 'C:\\xampp\\htdocs\\munyari_church\\pastor_dashboard.php'];

$js_fix = <<<'HTML'
                    </form>
                    <script>
                    if (typeof window.validateInput === 'undefined') {
                        window.validateInput = function(input, type) {
                            let errorMsg = input.nextElementSibling;
                            if (!errorMsg || !errorMsg.classList.contains('err-msg')) {
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
                                    errorMsg.innerText = 'Only characters allowed - numbers are not permitted.';
                                    errorMsg.style.color = '#ef4444';
                                    input.setCustomValidity('Invalid');
                                    setTimeout(() => { input.value = input.value.replace(/[^A-Za-z\s,.-]/g, ''); }, 800);
                                } else {
                                    errorMsg.innerText = '';
                                    input.setCustomValidity('');
                                }
                            } else if (type === 'phone') {
                                if (/[^\d]/.test(input.value)) {
                                    errorMsg.innerText = 'Only digits allowed.';
                                    errorMsg.style.color = '#ef4444';
                                    input.setCustomValidity('Invalid');
                                    setTimeout(() => { input.value = input.value.replace(/[^\d]/g, ''); }, 800);
                                } else {
                                    errorMsg.innerText = '';
                                    input.setCustomValidity('');
                                }
                            }
                        };
                    }
                    if (typeof window.seqUnlock === 'undefined') {
                        window.seqUnlock = function(currentId, nextId) {
                            const current = document.getElementById(currentId);
                            const next    = document.getElementById(nextId);
                            if (!current || !next) return;
                            const filled = current.tagName === 'SELECT'
                                ? current.value !== ''
                                : current.value.trim().length > 0 && current.checkValidity();
                            next.disabled = !filled;
                            if (!filled) next.value = '';
                        };
                    }
                    </script>
HTML;

foreach ($files as $f) {
    $c = file_get_contents($f);
    // Insert JS right after the ssRegForm closes
    $c = preg_replace('/<\/form>\s*<!-- Class Sections -->/', $js_fix . "\n                <!-- Class Sections -->", $c);
    file_put_contents($f, $c);
    echo "Fixed JS in " . basename($f) . "\n";
}
?>
