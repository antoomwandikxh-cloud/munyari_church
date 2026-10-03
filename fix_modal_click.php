<?php
$file = 'admin_dashboard.php';
$content = file_get_contents($file);

$old_js = <<<'JS'
                                        document.body.appendChild(modal);
                                        
                                        document.getElementById('btnScrollTaken').onclick = function() {
                                            document.body.removeChild(modal);
JS;

$new_js = <<<'JS'
                                        document.body.appendChild(modal);
                                        
                                        const btn = modal.querySelector('button');
                                        if (btn) {
                                            btn.onclick = function() {
                                                if (document.body.contains(modal)) {
                                                    document.body.removeChild(modal);
                                                }
JS;

$content = str_replace(str_replace("\r", "", $old_js), str_replace("\r", "", $new_js), $content);
file_put_contents($file, $content);
echo "Fixed button click attachment\n";
?>
