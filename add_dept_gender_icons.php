<?php
$dept_icon = '<span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #8b5cf6; pointer-events: none; z-index:1;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg></span>';
$gender_icon = '<span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #ec4899; pointer-events: none; z-index:1;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="9" r="5" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 14v7m-3-3h6"/><circle cx="17.5" cy="5.5" r="3.5" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 2l-3.5 3.5M21 2h-3m0 0v3"/></svg></span>';

$select_style_dept = 'style="padding-left: 36px; appearance: none; -webkit-appearance: none;"';
$select_style_gender = 'style="padding-left: 36px; appearance: none; -webkit-appearance: none;"';

// ======= 1. REGISTER PAGE =======
$file = 'C:\\xampp\\htdocs\\munyari_church\\register_page.php';
$content = file_get_contents($file);

// Wrap department select
$old = '<select name="department" id="department" class="form-control" onchange="seqUnlock(\'department\',\'password\')" disabled required>';
$new = '<div style="position:relative;">' . "\n                        " . $dept_icon . "\n                        " . str_replace('class="form-control"', 'class="form-control" style="padding-left:36px;"', $old);
$content = str_replace($old, $new, $content);
// Close the select then close the wrapper div
$old_close = '</select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Gender</label>';
$new_close = '</select>' . "\n                        </div>" . "\n                    </div>
                    <div class=\"form-group\" style=\"margin-bottom:0;\">
                        <label>Gender</label>";
$content = str_replace($old_close, $new_close, $content);

// Wrap gender radio group label - male
$old_male_label = '<label style="display: flex; align-items: center; gap: 6px; cursor: pointer; padding: 10px 20px; border: 2px solid var(--border-color); border-radius: var(--radius-md); flex: 1; justify-content: center; transition: var(--transition);" id="gender_male_label">';
$new_male_label = '<label style="display: flex; align-items: center; gap: 6px; cursor: pointer; padding: 10px 20px; border: 2px solid var(--border-color); border-radius: var(--radius-md); flex: 1; justify-content: center; transition: var(--transition); color: #3b82f6;" id="gender_male_label">';
$content = str_replace($old_male_label, $new_male_label, $content);

// Wrap gender radio group label - female
$old_female_label = '<label style="display: flex; align-items: center; gap: 6px; cursor: pointer; padding: 10px 20px; border: 2px solid var(--border-color); border-radius: var(--radius-md); flex: 1; justify-content: center; transition: var(--transition);" id="gender_female_label">';
$new_female_label = '<label style="display: flex; align-items: center; gap: 6px; cursor: pointer; padding: 10px 20px; border: 2px solid var(--border-color); border-radius: var(--radius-md); flex: 1; justify-content: center; transition: var(--transition); color: #ec4899;" id="gender_female_label">';
$content = str_replace($old_female_label, $new_female_label, $content);

file_put_contents($file, $content);
echo "register_page.php done\n";


// ======= 2. ADMIN DASHBOARD =======
$file = 'C:\\xampp\\htdocs\\munyari_church\\admin_dashboard.php';
$content = file_get_contents($file);

// Department select
$old_dept = '<select name="department" id="a_dept" class="form-control" disabled required>';
$new_dept = '<div style="position:relative;">' . "\n                                    " . $dept_icon . "\n                                    " . str_replace('class="form-control"', 'class="form-control" style="padding-left:36px;"', $old_dept);
$content = str_replace($old_dept, $new_dept, $content);
// find the close of department select before gender
$old_dept_close = '</select>
                            </div>
                            <div class="form-group">
                                <label>Gender <span style="color:var(--danger);">*</span></label>
                                <select name="gender" id="a_gender"';
$new_dept_close = '</select>' . "\n                                    </div>" . "\n                            </div>
                            <div class=\"form-group\">
                                <label>Gender <span style=\"color:var(--danger);\">*</span></label>
                                <div style=\"position:relative;\">" . "\n                                    " . $gender_icon . "\n                                    " . '<select name="gender" id="a_gender"';
$content = str_replace($old_dept_close, $new_dept_close, $content);

// Close gender select wrapper
$old_gender_close = '<select name="gender" id="a_gender" class="form-control" onchange="seqUnlock(\'a_gender\',\'a_address\')" disabled required>';
// find the full gender block end
$content = str_replace(
    'class="form-control" onchange="seqUnlock(\'a_gender\',\'a_address\')" disabled required>',
    'class="form-control" style="padding-left:36px;" onchange="seqUnlock(\'a_gender\',\'a_address\')" disabled required>',
    $content
);
// close the wrapper div after </select> near gender hint
$content = str_replace(
    '<small id="a_gender_hint" style="color:var(--text-muted);font-size:0.78rem;">Select department first. Women auto-fill Female; Elders auto-fill Male.</small>
                            </div>',
    '</div>' . "\n                                <small id=\"a_gender_hint\" style=\"color:var(--text-muted);font-size:0.78rem;\">Select department first. Women auto-fill Female; Elders auto-fill Male.</small>\n                            </div>",
    $content
);

file_put_contents($file, $content);
echo "admin_dashboard.php done\n";


// ======= 3. PASTOR DASHBOARD =======
$file = 'C:\\xampp\\htdocs\\munyari_church\\pastor_dashboard.php';
$content = file_get_contents($file);

// Department
$old_dept_p = '<select name="department" id="pRegDept" class="form-control" required onchange="pRegAutoGender(); seqUnlock(\'pRegDept\',\'pRegGender\')" disabled>';
$new_dept_p = '<div style="position:relative;">' . "\n                                 " . $dept_icon . "\n                                 " . str_replace('class="form-control"', 'class="form-control" style="padding-left:36px;"', $old_dept_p);
$content = str_replace($old_dept_p, $new_dept_p, $content);

// Close dept select, open gender wrapper
$old_pastor_dept_close = '</select>
                             </div>
                             <div class="form-group">
                                 <label>Gender <span style="color:var(--danger);">*</span></label>
                                 <select name="gender" id="pRegGender"';
$new_pastor_dept_close = '</select>' . "\n                                 </div>" . "\n                             </div>
                             <div class=\"form-group\">
                                 <label>Gender <span style=\"color:var(--danger);\">*</span></label>
                                 <div style=\"position:relative;\">\n                                     " . $gender_icon . "\n                                     " . '<select name="gender" id="pRegGender"';
$content = str_replace($old_pastor_dept_close, $new_pastor_dept_close, $content);

// Add padding-left to gender select
$content = str_replace(
    'id="pRegGender" class="form-control" required onchange="seqUnlock(\'pRegGender\',\'p_address\')" disabled>',
    'id="pRegGender" class="form-control" style="padding-left:36px;" required onchange="seqUnlock(\'pRegGender\',\'p_address\')" disabled>',
    $content
);
// Close gender wrapper div
$content = str_replace(
    '<small id="pRegGenderHint" style="color:var(--text-muted);font-size:0.78rem;">Select department first. Women auto-fill Female; Elders auto-fill Male.</small>
                             </div>',
    '</div>' . "\n                                 <small id=\"pRegGenderHint\" style=\"color:var(--text-muted);font-size:0.78rem;\">Select department first. Women auto-fill Female; Elders auto-fill Male.</small>\n                             </div>",
    $content
);

file_put_contents($file, $content);
echo "pastor_dashboard.php done\n";

echo "All files updated!";
?>
