<?php
session_start();
?>
<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Munyari Church - Register</title>
    <link rel="stylesheet" href="style.css?v=2">
</head>
<body>
    <!-- Splash Screen -->
    <script>
        if (sessionStorage.getItem('splashShown')) {
            document.write('<style>#splashScreen { display: none !important; }</style>');
        }
    </script>
    <div class="splash-screen" id="splashScreen">
        <div class="splash-content">
            <img src="church_logo.jpg" alt="Munyari E.A.P.C Logo">
            <h1>Welcome to Munyari E.A.P.C Church</h1>
        </div>
    </div>
    <script>
        if (!sessionStorage.getItem('splashShown')) {
            sessionStorage.setItem('splashShown', 'true');
            setTimeout(() => {
                const splash = document.getElementById('splashScreen');
                if(splash) splash.remove();
            }, 3000);
        } else {
            const splash = document.getElementById('splashScreen');
            if(splash) splash.remove();
        }
    </script>
    <div class="auth-wrapper">
        <div class="auth-card" style="max-width: 500px;">
            <div class="auth-header">
                <img src="church_logo.jpg" alt="Munyari E.A.P.C Church" style="width: 180px; height: auto; margin: 0 auto 20px; display: block; border-radius: 12px;">
                <h2>Join Munyari Church</h2>
                <p>Create your account</p>
            </div>
            
            <?php
            if (isset($_GET['success'])) {
                if ($_GET['success'] === 'pastor') {
                    echo '<div class="alert alert-success"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Registration successful! Your account is pending admin approval.</div>';
                } else {
                    echo '<div class="alert alert-success"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Registration successful! Your account is pending pastor approval.</div>';
                }
            }
            if (isset($_GET['error'])) {
                echo '<div class="alert alert-error">'.htmlspecialchars($_GET['error']).'</div>';
            }
            ?>

            <form id="registrationForm" action="register.php" method="POST" novalidate>
                
                <div class="toggle-switch-container">
                    <input type="radio" id="type_member" name="account_type" value="member" checked onchange="toggleFields()">
                    <label for="type_member" class="toggle-option">Register as Member</label>
                    
                    <input type="radio" id="type_pastor" name="account_type" value="pastor" onchange="toggleFields()">
                    <label for="type_pastor" class="toggle-option">Register as Pastor</label>
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="first_name">First Name</label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #10b981; pointer-events: none;"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg></span>
                            <input type="text" id="first_name" name="first_name" class="form-control" style="padding-left: 40px;" placeholder="e.g. Peter" pattern="[A-Za-z\s]+" oninput="validateInput(this,'name'); seqUnlock('first_name','last_name')" required>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="last_name">Last Name</label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #10b981; pointer-events: none;"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg></span>
                            <input type="text" id="last_name" name="last_name" class="form-control" style="padding-left: 40px;" placeholder="e.g. Ntoiti" pattern="[A-Za-z\s]+" oninput="validateInput(this,'name'); seqUnlock('last_name','phone')" disabled required>
                        </div>
                    </div>
                </div>
                

                
                <div class="form-row">
                    <div class="form-group" id="phone_group" style="margin-bottom:0;">
                        <label for="phone">Phone Number</label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #f59e0b; pointer-events: none;"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg></span>
                            <input type="tel" id="phone" name="phone" class="form-control" style="padding-left: 40px;" placeholder="10-digit number" pattern="\d{10}" maxlength="10" oninput="validateInput(this,'phone'); checkPhoneAsync(this,'address')" disabled required>
                        </div>
                    </div>
                    <div class="form-group" id="address_group" style="margin-bottom:0;">
                        <label for="address">Resident Area</label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #6366f1; pointer-events: none;"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg></span>
                            <input type="text" id="address" name="address" class="form-control" style="padding-left: 40px;" placeholder="e.g. Mugui" pattern="[A-Za-z\s]+" oninput="validateInput(this,'name'); seqUnlock('address','department')" disabled required>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" id="department_group" style="margin-bottom:0;">
                        <label for="department">Department</label>
                        <div style="position:relative;">
                        <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #8b5cf6; pointer-events: none; z-index:1;"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg></span>
                        <select name="department" id="department" class="form-control" style="padding-left:36px;" onchange="seqUnlock('department','password')" disabled required>
                            <option value="" disabled selected>-- Select Department --</option>
                            <option value="Youths">Youths</option>
                            <option value="Elders">Elders</option>
                            <option value="Sunday School">Sunday School</option>
                            <option value="Womens Ministry">Women's Ministry</option>
                        </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Gender</label>
                        <div style="display: flex; gap: 10px; margin-top: 5px;">
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; padding: 10px 20px; border: 2px solid var(--border-color); border-radius: var(--radius-md); flex: 1; justify-content: center; transition: var(--transition); color: #3b82f6;" id="gender_male_label">
                                <input type="radio" name="gender" value="Male" checked onchange="document.getElementById('gender_male_label').style.borderColor='var(--primary)'; document.getElementById('gender_female_label').style.borderColor='var(--border-color)';">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="10.5" cy="13.5" r="5.5" stroke-width="2"/><path stroke-width="2" d="M16 8l4-4m0 0h-4m4 0v4"/></svg>
                                Male
                            </label>
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; padding: 10px 20px; border: 2px solid var(--border-color); border-radius: var(--radius-md); flex: 1; justify-content: center; transition: var(--transition); color: #ec4899;" id="gender_female_label">
                                <input type="radio" name="gender" value="Female" onchange="document.getElementById('gender_female_label').style.borderColor='var(--primary)'; document.getElementById('gender_male_label').style.borderColor='var(--border-color)';">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="9" r="5" stroke-width="2"/><path stroke-width="2" d="M12 14v6m-3-3h6"/></svg>
                                Female
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" id="role_group" style="display: none; margin-bottom:0;">
                    <label for="role">Area of Resident</label>
                    <input type="text" id="role" name="role" class="form-control" placeholder="e.g. Mugui" pattern="[A-Za-z\s]+" oninput="validateInput(this, 'name')">
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="password">Password</label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #ef4444; pointer-events: none;"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg></span>
                            <input type="password" id="password" name="password" class="form-control" placeholder="At least 6 characters" minlength="6" oninput="seqUnlock('password','confirm_password');var cp=document.getElementById('confirm_password');if(cp.value)cp.dispatchEvent(new Event('input'))" disabled required style="padding-left: 40px; padding-right: 40px;">
                            <button type="button" onclick="togglePassword('password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-muted);">
                                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            </button>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="confirm_password">Confirm Password</label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #ef4444; pointer-events: none;"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg></span>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Repeat password" minlength="6" disabled required style="padding-left: 40px; padding-right: 40px;" oninput="var m=document.getElementById('pwdMatchHint');if(this.value===document.getElementById('password').value){m.style.display='block';m.style.color='#10b981';m.textContent='Passwords match';}else{m.style.display='block';m.style.color='#ef4444';m.textContent='Passwords do not match';}">
                            <button type="button" onclick="togglePassword('confirm_password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-muted);">
                                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            </button>
                        </div>
                        <small id="pwdMatchHint" style="font-size:0.8rem;margin-top:4px;display:none;"></small>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit" style="margin-top: 20px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                    Register Account
                </button>
            </form>
            
            <div class="auth-links">
                <p style="color: var(--text-muted);">Already have an account? <a href="login.php">Login</a></p>
            </div>
        </div>
    </div>
    <script src="script.js?v=1791214678"></script>
    <style>
    input:disabled, select:disabled {
        opacity: 0.45;
        cursor: not-allowed;
        background: var(--bg-lighter, #f1f5f9) !important;
        border-color: #cbd5e1 !important;
    }
    </style>
    <script>
    async function checkUsernameAsync(input, nextId) {
        if(input.value.length >= 3) {
            try {
                let res = await fetch('check_username.php?username=' + input.value);
                let data = await res.json();
                let errorMsg = input.nextElementSibling;
                if (!errorMsg || !errorMsg.classList.contains('err-msg')) {
                    errorMsg = document.createElement('span');
                    errorMsg.className = 'err-msg';
                    errorMsg.style.color = '#ef4444';
                    errorMsg.style.fontSize = '0.85rem';
                    errorMsg.style.display = 'block';
                    errorMsg.style.marginTop = '4px';
                    errorMsg.style.fontWeight = 'bold';
                    input.parentNode.appendChild(errorMsg);
                }
                
                if(data.exists) {
                    errorMsg.innerText = 'This username is already taken!';
                    input.setCustomValidity('Taken');
                } else {
                    errorMsg.innerText = 'Username is available!';
                    errorMsg.style.color = '#10b981';
                    input.setCustomValidity('');
                    setTimeout(() => { if(errorMsg.innerText === 'Username is available!') errorMsg.innerText = ''; }, 3000);
                }
            } catch(e) {}
        } else {
            input.setCustomValidity(input.value.length > 0 ? 'Too short' : '');
            let errorMsg = input.nextElementSibling;
            if(errorMsg && errorMsg.classList.contains('err-msg')) errorMsg.innerText = '';
        }
        seqUnlock(input.id, nextId);
    }

    async function checkPhoneAsync(input, nextId) {
        if(input.value.length === 10) {
            try {
                let res = await fetch('check_phone.php?phone=' + input.value);
                let data = await res.json();
                if(data.exists) {
                    let errorMsg = input.nextElementSibling;
                    if (!errorMsg || !errorMsg.classList.contains('err-msg')) {
                        errorMsg = document.createElement('span');
                        errorMsg.className = 'err-msg';
                        errorMsg.style.color = '#ef4444';
                        errorMsg.style.fontSize = '0.85rem';
                        errorMsg.style.display = 'block';
                        errorMsg.style.marginTop = '4px';
                        errorMsg.style.fontWeight = 'bold';
                        input.parentNode.appendChild(errorMsg);
                    }
                    errorMsg.innerText = 'This number is already registered!';
                    input.setCustomValidity('Invalid');
                    input.value = '';
                    setTimeout(() => errorMsg.innerText = '', 4000);
                    return;
                }
            } catch(e) {}
        }
        seqUnlock(input.id, nextId);
    }

    /* seqUnlock now in script.js */

    function enforceGender(deptValue) {
        const maleRadio = document.querySelector('input[name="gender"][value="Male"]');
        const femaleRadio = document.querySelector('input[name="gender"][value="Female"]');
        const maleLabel = document.getElementById('gender_male_label');
        const femaleLabel = document.getElementById('gender_female_label');
        
        if (!maleRadio || !femaleRadio) return;
        
        if (deptValue === "Womens Ministry") {
            femaleRadio.checked = true;
            maleRadio.disabled = true;
            femaleRadio.disabled = false;
            femaleLabel.style.borderColor = 'var(--primary)';
            maleLabel.style.borderColor = 'var(--border-color)';
            maleLabel.style.opacity = '0.5';
            femaleLabel.style.opacity = '1';
            maleLabel.style.cursor = 'not-allowed';
            femaleLabel.style.cursor = 'default';
        } else if (deptValue === "Elders") {
            maleRadio.checked = true;
            femaleRadio.disabled = true;
            maleRadio.disabled = false;
            maleLabel.style.borderColor = 'var(--primary)';
            femaleLabel.style.borderColor = 'var(--border-color)';
            femaleLabel.style.opacity = '0.5';
            maleLabel.style.opacity = '1';
            femaleLabel.style.cursor = 'not-allowed';
            maleLabel.style.cursor = 'default';
        } else {
            maleRadio.disabled = false;
            femaleRadio.disabled = false;
            maleLabel.style.opacity = '1';
            femaleLabel.style.opacity = '1';
            maleLabel.style.cursor = 'pointer';
            femaleLabel.style.cursor = 'pointer';
        }
    }
    
    document.getElementById('department').addEventListener('change', function() {
        seqUnlock('department', 'password');
        enforceGender(this.value);
    });

    function validateInput(input, type) {
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
            if (/[^A-Za-z\s]/.test(input.value)) {
                errorMsg.innerText = 'Only characters allowed. Numbers are not permitted.';
                errorMsg.style.color = '#ef4444';
                input.setCustomValidity('Invalid');
                setTimeout(() => input.value = input.value.replace(/[^A-Za-z\s]/g, ''), 800);
            } else {
                errorMsg.innerText = '';
                input.setCustomValidity('');
            }
        } else if (type === 'username') {
            if (/[^A-Za-z0-9_]/.test(input.value)) {
                errorMsg.innerText = 'Only letters, numbers, and underscores are allowed.';
                errorMsg.style.color = '#ef4444';
                input.setCustomValidity('Invalid');
                setTimeout(() => input.value = input.value.replace(/[^A-Za-z0-9_]/g, ''), 800);
            } else {
                // If length < 3, require at least 3 chars
                if(input.value.length > 0 && input.value.length < 3) {
                    errorMsg.innerText = 'Username must be at least 3 characters.';
                    errorMsg.style.color = '#ef4444';
                    input.setCustomValidity('Invalid');
                } else if (!input.validity.customError || input.validationMessage === 'Too short' || input.validationMessage === 'Taken') {
                    // let checkUsernameAsync handle the taken message
                    if (errorMsg.innerText === 'Username must be at least 3 characters.') errorMsg.innerText = '';
                }
            }
        } else if (type === 'phone') {
            if (/[^0-9]/.test(input.value)) {
                errorMsg.innerText = 'Only numbers allowed. Characters are not permitted.';
                input.setCustomValidity('Invalid');
                setTimeout(() => input.value = input.value.replace(/[^0-9]/g, ''), 800);
            } else if (input.value.length > 0 && input.value.length !== 10) {
                errorMsg.innerText = 'Phone number must be exactly 10 digits.';
                input.setCustomValidity('Invalid');
            } else {
                errorMsg.innerText = '';
                input.setCustomValidity('');
            }
        }
    }
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
        } else {
            input.type = 'password';
        }
    }

    function toggleFields() {
        const isPastor = document.getElementById('type_pastor').checked;
        const roleGroup = document.getElementById('role_group');
        const addressGroup = document.getElementById('address_group');
        const departmentGroup = document.getElementById('department_group');
        const phoneInput = document.getElementById('phone');
        const addressInput = document.getElementById('address');
        const deptInput = document.getElementById('department');
        const roleInput = document.getElementById('role');
        const passwordInput = document.getElementById('password');
        
        if (isPastor) {
            roleGroup.style.display = 'block';
            addressGroup.style.display = 'none';
            departmentGroup.style.display = 'none';
            
            // Re-route unlock chain: phone -> role -> password
            phoneInput.setAttribute('oninput', "validateInput(this,'phone'); seqUnlock('phone','role')");
            roleInput.setAttribute('oninput', "validateInput(this,'name'); seqUnlock('role','password')");
            
            // Adjust required fields
            addressInput.required = false;
            deptInput.required = false;
            roleInput.required = true;
            
            // If we switch and phone is already filled, unlock role
            seqUnlock('phone', 'role');
        } else {
            roleGroup.style.display = 'none';
            addressGroup.style.display = 'block';
            departmentGroup.style.display = 'block';
            
            // Re-route unlock chain: phone -> address -> department -> password
            phoneInput.setAttribute('oninput', "validateInput(this,'phone'); seqUnlock('phone','address')");
            roleInput.removeAttribute('oninput');
            
            // Adjust required fields
            addressInput.required = true;
            deptInput.required = true;
            roleInput.required = false;
            
            // If we switch and phone is already filled, unlock address
            seqUnlock('phone', 'address');
        }
    }
    </script>
</body>
</html>
