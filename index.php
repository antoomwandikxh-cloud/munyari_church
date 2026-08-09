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
                echo '<div class="alert alert-success"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Registration successful! Your account is pending pastor approval.</div>';
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
                        <input type="text" id="first_name" name="first_name" class="form-control" placeholder="e.g. John" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" class="form-control" placeholder="e.g. Doe" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group" id="phone_group" style="margin-bottom:0;">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" placeholder="+1234567890" required>
                    </div>
                    <div class="form-group" id="address_group" style="margin-bottom:0;">
                        <label for="address">Resident Area</label>
                        <input type="text" id="address" name="address" class="form-control" placeholder="e.g. Downtown" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" id="department_group" style="margin-bottom:0;">
                        <label for="department">Department</label>
                        <select name="department" id="department" class="form-control" required>
                            <option value="" disabled selected>-- Select Department --</option>
                            <option value="Youths">Youths</option>
                            <option value="Elders">Elders</option>
                            <option value="Sunday School">Sunday School</option>
                            <option value="Womens Ministry">Women's Ministry</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Gender</label>
                        <div style="display: flex; gap: 10px; margin-top: 5px;">
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; padding: 10px 20px; border: 2px solid var(--border-color); border-radius: var(--radius-md); flex: 1; justify-content: center; transition: var(--transition);" id="gender_male_label">
                                <input type="radio" name="gender" value="Male" checked onchange="document.getElementById('gender_male_label').style.borderColor='var(--primary)'; document.getElementById('gender_female_label').style.borderColor='var(--border-color)';">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="10.5" cy="13.5" r="5.5" stroke-width="2"/><path stroke-width="2" d="M16 8l4-4m0 0h-4m4 0v4"/></svg>
                                Male
                            </label>
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; padding: 10px 20px; border: 2px solid var(--border-color); border-radius: var(--radius-md); flex: 1; justify-content: center; transition: var(--transition);" id="gender_female_label">
                                <input type="radio" name="gender" value="Female" onchange="document.getElementById('gender_female_label').style.borderColor='var(--primary)'; document.getElementById('gender_male_label').style.borderColor='var(--border-color)';">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="9" r="5" stroke-width="2"/><path stroke-width="2" d="M12 14v6m-3-3h6"/></svg>
                                Female
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" id="role_group" style="display: none; margin-bottom:0;">
                    <label for="role">Area of Resident</label>
                    <input type="text" id="role" name="role" class="form-control" placeholder="e.g. Downtown">
                </div>
                
                <div class="form-row">
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="password">Password</label>
                        <div style="position: relative;">
                            <input type="password" id="password" name="password" class="form-control" placeholder="Strong password" required style="padding-right: 40px;">
                            <button type="button" onclick="togglePassword('password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-muted);">
                                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            </button>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="confirm_password">Confirm Password</label>
                        <div style="position: relative;">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Repeat password" required style="padding-right: 40px;">
                            <button type="button" onclick="togglePassword('confirm_password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-muted);">
                                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit" style="margin-top: 20px;">Register Account</button>
            </form>
            
            <div class="auth-links">
                <p style="color: var(--text-muted);">Already have an account? <a href="login.php">Sign In</a></p>
            </div>
        </div>
    </div>
    <script src="script.js"></script>
    <script>
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
        
        if (isPastor) {
            roleGroup.style.display = 'block';
            addressGroup.style.display = 'none';
            departmentGroup.style.display = 'none';
        } else {
            roleGroup.style.display = 'none';
            addressGroup.style.display = 'block';
            departmentGroup.style.display = 'block';
        }
    }
    </script>
</body>
</html>
