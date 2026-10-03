<?php
$file = 'pastor_dashboard.php';
$content = file_get_contents($file);

// Find the <select name="desired_role_pref" ...> ... </select> blocks and replace them
$pattern = '/<select name="desired_role_pref" class="form-control">\s*<option value="">-- No specific role --<\/option>.*?<\/select>/is';

$replacement = <<<PHP
<select name="desired_role_pref" class="form-control">
                                <option value="">-- No specific role --</option>
                                <?php
                                \$cr_q = \$conn->query("SELECT role_name FROM custom_desired_roles ORDER BY id ASC");
                                if (\$cr_q) {
                                    while(\$cr = \$cr_q->fetch_assoc()) {
                                        \$r = \$cr['role_name'];
                                        \$sel = (\$pastor['desired_role_pref'] == \$r) ? 'selected' : '';
                                        echo "<option value=\"".htmlspecialchars(\$r)."\" \$sel>".htmlspecialchars(\$r)."</option>";
                                    }
                                }
                                ?>
                            </select>
PHP;

$content = preg_replace($pattern, $replacement, $content);
file_put_contents($file, $content);
echo "Updated HTML dropdowns in $file\n";
?>
