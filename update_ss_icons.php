<?php
$files = ['C:\\xampp\\htdocs\\munyari_church\\admin_dashboard.php', 'C:\\xampp\\htdocs\\munyari_church\\pastor_dashboard.php'];

$old_form_pattern = '/<form method="POST" action="\?tab=manage_sunday_school" id="ssRegForm".*?<\/form>/s';

$new_form = <<<'HTML'
<form method="POST" action="?tab=manage_sunday_school" id="ssRegForm" onsubmit="this.querySelector('button[type=submit]').disabled=true;">
                        <input type="hidden" name="register_ss_member" value="1">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group">
                                <label>First Name <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#10b981; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></span>
                                    <input type="text" id="ss_first_name" name="first_name" class="form-control" placeholder="e.g. John" pattern="[A-Za-z\s]+" oninput="validateInput(this,'name'); seqUnlock('ss_first_name','ss_last_name')" style="padding-left:36px;" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Last Name <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#10b981; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></span>
                                    <input type="text" id="ss_last_name" name="last_name" class="form-control" placeholder="e.g. Kamau" pattern="[A-Za-z\s]+" oninput="validateInput(this,'name'); seqUnlock('ss_last_name','ss_class'); document.getElementById('ss_phone').disabled=false;" style="padding-left:36px;" disabled required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Phone Number <span style="color:var(--text-muted); font-weight:normal; font-size:0.8rem;">(Optional)</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#f59e0b; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg></span>
                                    <input type="tel" id="ss_phone" name="phone" class="form-control" placeholder="10-digit number (Optional)" pattern="\d{10}" maxlength="10" oninput="validateInput(this,'phone');" style="padding-left:36px;" disabled>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Sunday School Class <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#8b5cf6; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg></span>
                                    <select name="ss_class" id="ss_class" class="form-control" required onchange="seqUnlock('ss_class','ss_gender')" style="padding-left:36px;" disabled>
                                        <option value="">-- Select Class --</option>
                                        <option value="Battalion">Battalion</option>
                                        <option value="Conquerors">Conquerors</option>
                                        <option value="Little Angels">Little Angels</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Gender <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#ec4899; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg></span>
                                    <select name="gender" id="ss_gender" class="form-control" required onchange="seqUnlock('ss_gender','ss_address')" style="padding-left:36px;" disabled>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Residential Area <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#3b82f6; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg></span>
                                    <input type="text" id="ss_address" name="address" class="form-control" placeholder="e.g. Mugui" pattern="[A-Za-z0-9\s,.-]+" oninput="validateInput(this,'name'); seqUnlock('ss_address','ss_password')" style="padding-left:36px;" required disabled>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Login Password <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#ef4444; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></span>
                                    <input type="password" name="password" id="ss_password" class="form-control" placeholder="At least 6 characters" required style="padding-left:36px; padding-right:46px;" minlength="6" oninput="seqUnlock('ss_password','ss_confirm_password')" disabled>
                                    <span onclick="var i=document.getElementById('ss_password');i.type=i.type==='password'?'text':'password'" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--text-muted);">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Confirm Password <span style="color:var(--danger);">*</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#ef4444; pointer-events:none;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></span>
                                    <input type="password" name="confirm_password" id="ss_confirm_password" class="form-control" placeholder="Re-enter password" required style="padding-left:36px; padding-right:46px;" oninput="var m=document.getElementById('ssPwdHint');if(this.value===document.getElementById('ss_password').value){m.style.display='block';m.style.color='var(--success)';m.textContent='Passwords match';}else{m.style.display='block';m.style.color='var(--danger)';m.textContent='Passwords do not match';}" disabled>
                                    <span onclick="var i=document.getElementById('ss_confirm_password');i.type=i.type==='password'?'text':'password'" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--text-muted);">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </span>
                                </div>
                                <small id="ssPwdHint" style="font-size:0.8rem;margin-top:4px;display:none;"></small>
                            </div>
                        </div>
                        <button type="submit" class="btn-submit" style="margin-top:10px; display:flex; align-items:center; gap:8px; width:auto; padding:0 28px;">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                            Register Member
                        </button>
                    </form>
HTML;

foreach ($files as $f) {
    $c = file_get_contents($f);
    $c = preg_replace($old_form_pattern, $new_form, $c);
    file_put_contents($f, $c);
    echo "Updated " . basename($f) . "\n";
}
?>
