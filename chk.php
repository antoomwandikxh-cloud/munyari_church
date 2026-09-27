<?php
$file = "C:\\xampp\\htdocs\\munyari_church\\member_dashboard.php";
$content = file_get_contents($file);

$old = <<<HTML
                        \$label_color = \$rank === 1 ? '#f59e0b' : (\$rank === 2 ? '#10b981' : 'var(--text-muted)');
                    <?php 
                        // Only close the previous grid if we actually opened one (i.e. not the first iteration)
                        static \$first_section = true;
                        if (!\$first_section): ?>
                    </div>
                    <?php endif; \$first_section = false; ?>
                    <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:<?= \$label_color ?>; padding:10px 0 6px; border-top:1px solid var(--border-color); margin-top:<?= \$rank > 1 ? '20px' : '0' ?>;">
HTML;

$new = <<<HTML
                        \$label_color = \$rank === 1 ? '#f59e0b' : (\$rank === 2 ? '#10b981' : 'var(--text-muted)');
                        
                        // Only close the previous grid if we actually opened one (i.e. not the first iteration)
                        static \$first_section = true;
                        if (!\$first_section): ?>
                    </div>
                    <?php endif; \$first_section = false; ?>
                    <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:<?= \$label_color ?>; padding:10px 0 6px; border-top:1px solid var(--border-color); margin-top:<?= \$rank > 1 ? '20px' : '0' ?>;">
HTML;

$content = str_replace($old, $new, $content);
file_put_contents($file, $content);
echo "Fixed PHP tag syntax error.\n";
?>
