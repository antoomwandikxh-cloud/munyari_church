document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registrationForm');
    const tabBadges = window.tabNotificationBadges || {};
    
    if (form) {
        form.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm_password = document.getElementById('confirm_password').value;
            
            if (password !== confirm_password) {
                alert('Passwords do not match!');
                e.preventDefault(); // Prevent form submission
            }
        });
    }

    Object.keys(tabBadges).forEach(function(tab) {
        const count = Number(tabBadges[tab]);
        if (!count) return;

        document.querySelectorAll('.sidebar-link[href*="tab=' + tab + '"]').forEach(function(link) {
            if (link.querySelector('.tab-notif-badge')) return;

            const badge = document.createElement('span');
            badge.className = 'tab-notif-badge';
            badge.textContent = count > 99 ? '99+' : count;
            badge.setAttribute('aria-label', count + ' unread notifications');
            link.appendChild(badge);
        });
    });
});

function seqUnlock(currentId, nextId) {
    const current = document.getElementById(currentId);
    const next    = document.getElementById(nextId);
    if (!current || !next) return;
    
    // Check if filled
    const filled = current.tagName === 'SELECT'
        ? current.value !== ''
        : current.value.trim().length > 0 && current.checkValidity();
        
    const wasDisabled = next.disabled;
    next.disabled = !filled;
    
    if (!filled && next.tagName === 'INPUT') next.value = '';
    
    // Badge UI logic
    if (typeof setFieldStatus_filling === 'function') {
        if (filled) {
            setFieldStatus_blur(current);
            if (wasDisabled && !next.disabled) {
                seqUnlock_badge_next(nextId, true);
            }
        } else {
            setFieldStatus_blur(current);
            seqUnlock_badge_next(nextId, false);
        }
    }
}

// Single unified badge helper functions
function getStatusBadge(el) {
    if (!el) return null;
    const group = el.closest('.form-group');
    if (!group) return null;
    const label = group.querySelector('label');
    if (!label) return null;
    
    // Remove old conflicting badges if they exist
    const oldBadges = label.querySelectorAll('.status-badge');
    oldBadges.forEach(b => b.remove());
    
    let badge = label.querySelector('.sb');
    if (!badge) {
        badge = document.createElement('span');
        badge.className = 'sb';
        badge.style.cssText = 'margin-left:8px; font-size:0.7rem; padding:2px 8px; border-radius:12px; font-weight:600; vertical-align:middle; display:none; transition:all 0.3s ease; border:1px solid transparent; letter-spacing:0.2px;';
        label.appendChild(badge);
    }
    return badge;
}

function setFieldStatus_filling(id) {
    const el = document.getElementById(id);
    const b = getStatusBadge(el);
    if (!b) return;
    if (el.value.trim().length > 0) {
        b.style.display = 'inline-block'; b.textContent = 'Filling...'; b.style.background = '#fef9c3'; b.style.color = '#a16207'; b.style.borderColor = '#fde047';
    } else {
        b.textContent = ''; b.style.display = 'none';
    }
}

function setFieldStatus_blur(el) {
    if (typeof el === 'string') el = document.getElementById(el);
    const b = getStatusBadge(el);
    if (!b) return;
    if (el.value.trim().length > 0 && el.checkValidity()) {
        b.textContent = '✓ Filled'; b.style.background = '#f0fdf4'; b.style.color = '#15803d'; b.style.borderColor = '#bbf7d0';
    } else if (!el.value.trim().length) {
        b.textContent = ''; b.style.display = 'none';
    }
}

function seqUnlock_badge_next(nextId, unlocked) {
    const el = document.getElementById(nextId);
    const b = getStatusBadge(el);
    if (!b) return;
    if (unlocked && el.value.trim() === '') {
        b.style.display = 'inline-block'; b.textContent = 'Activated'; b.style.background = '#eff6ff'; b.style.color = '#1d4ed8'; b.style.borderColor = '#bfdbfe';
    } else if (!unlocked) {
        b.textContent = ''; b.style.display = 'none';
    }
}

// Automatically attach listeners to all form-group inputs globally
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.form-group input, .form-group select').forEach(el => {
        if (['radio', 'checkbox', 'hidden', 'submit', 'button'].includes(el.type)) return;
        
        el.addEventListener('input', () => {
            setFieldStatus_filling(el.id);
        });
        
        el.addEventListener('blur', () => {
            setFieldStatus_blur(el);
        });
        
        el.addEventListener('change', () => {
            if (el.tagName === 'SELECT' && el.value !== '') {
                setFieldStatus_blur(el);
            }
        });
    });
});

const registrationPrerequisites = {
    last_name: 'first_name',
    phone: 'last_name',
    address: 'phone',
    department: 'address',
    password: 'department',
    confirm_password: 'password',
    a_last_name: 'a_first_name',
    a_phone: 'a_last_name',
    a_dept: 'a_phone',
    a_gender: 'a_dept',
    a_address: 'a_gender',
    adminRegPwd: 'a_address',
    adminRegPwdConfirm: 'adminRegPwd',
    p_last_name: 'p_first_name',
    p_phone: 'p_last_name',
    pRegDept: 'p_phone',
    pRegGender: 'pRegDept',
    p_address: 'pRegGender',
    pastorRegPwd: 'p_address',
    pastorRegPwdConfirm: 'pastorRegPwd',
    ss_last_name: 'ss_first_name',
    ss_phone: 'ss_last_name',
    ss_class: 'ss_last_name',
    ss_gender: 'ss_class',
    ss_address: 'ss_gender',
    ss_password: 'ss_address',
    ss_confirm_password: 'ss_password'
};

function registrationPrerequisiteId(field) {
    if (field.id === 'password' && document.getElementById('type_pastor')?.checked) return 'role';
    return registrationPrerequisites[field.id] || '';
}

function registrationFieldLabel(field) {
    if (!field) return 'the previous field';
    const group = field.closest('.form-group');
    const label = group?.querySelector('label');
    return (label?.textContent || field.name || 'the previous field')
        .replace(/\*/g, '')
        .replace(/\(Optional\)/gi, '')
        .replace(/:\s*$/, '')
        .trim();
}

function syncRegistrationLockPrompts() {
    document.querySelectorAll('[data-registration-lock-for]').forEach(overlay => {
        const field = document.getElementById(overlay.dataset.registrationLockFor);
        if (!field) return;
        overlay.hidden = !field.disabled;
        overlay.tabIndex = field.disabled ? 0 : -1;
        overlay.setAttribute('aria-hidden', field.disabled ? 'false' : 'true');
    });
}

function promptRegistrationPrerequisite(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field || !field.disabled) return;
    const prerequisite = document.getElementById(registrationPrerequisiteId(field));
    window.alert(`Please fill in the ${registrationFieldLabel(prerequisite)} first.`);
    prerequisite?.focus();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[id], select[id]').forEach(field => {
        if (!registrationPrerequisiteId(field) || !field.parentElement) return;

        const shell = field.parentElement;
        if (getComputedStyle(shell).position === 'static') shell.style.position = 'relative';
        const overlay = document.createElement('div');
        overlay.dataset.registrationLockFor = field.id;
        overlay.className = 'registration-lock-prompt';
        overlay.setAttribute('role', 'button');
        overlay.setAttribute('aria-label', `Locked field. Complete ${registrationFieldLabel(document.getElementById(registrationPrerequisiteId(field)))} first.`);
        overlay.style.cssText = 'position:absolute;inset:0;z-index:5;cursor:not-allowed;background:transparent;';
        overlay.addEventListener('click', () => promptRegistrationPrerequisite(field.id));
        overlay.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                promptRegistrationPrerequisite(field.id);
            }
        });
        shell.appendChild(overlay);
    });

    syncRegistrationLockPrompts();
    document.addEventListener('input', syncRegistrationLockPrompts);
    document.addEventListener('change', syncRegistrationLockPrompts);
});

function validateInput(input, type) {
    let errorMsg = input.parentNode.querySelector('.err-msg');
    if (!errorMsg) {
        errorMsg = document.createElement('span');
        errorMsg.className = 'err-msg';
        errorMsg.style.color = '#ef4444';
        errorMsg.style.fontSize = '0.8rem';
        errorMsg.style.display = 'block';
        errorMsg.style.marginTop = '4px';
        errorMsg.style.fontWeight = '600';
        input.parentNode.appendChild(errorMsg);
    }
    if (type === 'name') {
        if (/[^A-Za-z\s,.-]/.test(input.value)) {
            errorMsg.innerText = 'Only letters allowed (no numbers or symbols).';
            input.setCustomValidity('Invalid');
            setTimeout(() => { input.value = input.value.replace(/[^A-Za-z\s,.-]/g, ''); }, 300);
        } else {
            errorMsg.innerText = '';
            input.setCustomValidity('');
        }
    } else if (type === 'phone') {
        const registeredPhones = window.registeredPhones || [];
        if (/[^\d]/.test(input.value)) {
            errorMsg.innerText = 'Only digits allowed (no characters).';
            input.setCustomValidity('Invalid');
            setTimeout(() => { input.value = input.value.replace(/[^\d]/g, ''); }, 300);
        } else if (input.value.length > 0 && input.value.length < 10) {
            errorMsg.innerText = 'Must be exactly 10 digits.';
            input.setCustomValidity('Invalid');
        } else if (input.value.length === 10 && registeredPhones.includes(input.value)) {
            errorMsg.innerText = 'This number is already registered.';
            input.setCustomValidity('Invalid');
        } else {
            errorMsg.innerText = '';
            input.setCustomValidity('');
        }
    }
}

