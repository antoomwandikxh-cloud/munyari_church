<?php
$file = 'register_page.php';
$content = file_get_contents($file);

// Ensure we include custom_desired_roles from DB in register_page.php
// Wait, we need the DB connection inside register_page.php. It already has it at the top.
// Let's add the dropdown after gender.
$dropdown = <<<HTML
                    <div class="form-group" id="desired_role_group" style="margin-bottom:0;">
                        <label for="desired_role_pref">Desired Role (Optional)</label>
                        <div style="position:relative;">
                            <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #f59e0b; pointer-events: none; z-index:1;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg></span>
                            <select name="desired_role_pref" id="desired_role_pref" class="form-control" style="padding-left:36px;" onchange="seqUnlock('desired_role_pref','password')">
                                <option value="">-- No specific role --</option>
                                <?php
                                \$cr_q = \$conn->query("SELECT role_name FROM custom_desired_roles ORDER BY id ASC");
                                if (\$cr_q) {
                                    while(\$cr = \$cr_q->fetch_assoc()) {
                                        echo "<option value=\"".htmlspecialchars(\$cr['role_name'])."\">".htmlspecialchars(\$cr['role_name'])."</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
HTML;

$pattern = '/<div class="form-group" style="margin-bottom:0;">\s*<label>Gender<\/label>.*?<\/div>\s*<\/div>\s*<\/div>\s*<div class="form-group" id="role_group"/is';
$replacement = '<div class="form-group" style="margin-bottom:0;">
                        <label>Gender</label>' . preg_replace('/<div class="form-group" style="margin-bottom:0;">\s*<label>Gender<\/label>/', '', $content); // wait this is dangerous

// A safer way:
$content = str_replace(
    '</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" id="role_group"',
    '</label>
                        </div>
                    </div>' . "\n\n" . $dropdown . "\n" . '                </div>
                
                <div class="form-group" id="role_group"',
    $content
);

file_put_contents($file, $content);
echo "Added desired_role_pref to $file\n";
?>
