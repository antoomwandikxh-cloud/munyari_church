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
    // Just check if field has content — don't call checkValidity() here
    // because validateInput() may have set a temporary custom validity
    // that would block unlocking even on valid input.
    const filled = current.tagName === 'SELECT'
        ? current.value !== ''
        : current.value.trim().length > 0 && current.checkValidity();
    next.disabled = !filled;
    // Only clear value for text/password inputs, not buttons or selects
    if (!filled && next.tagName === 'INPUT') next.value = '';
}

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

// Initialize real-time status feedback
document.addEventListener('DOMContentLoaded', () => {
    const inputs = document.querySelectorAll('input, select');
    
    inputs.forEach(input => {
        // Only target inputs in form-groups
        if (!input.closest('.form-group')) return;
        if (input.type === 'radio' || input.type === 'checkbox' || input.type === 'hidden') return;
        
        // Add onfocus/oninput to set FILLING
        input.addEventListener('input', () => {
            if (input.value.trim().length > 0) {
                setFieldStatus(input.id, 'FILLING');
            } else {
                setFieldStatus(input.id, 'EMPTY');
            }
        });
        
        // Add blur to set FILLED
        input.addEventListener('blur', () => {
            if (input.value.trim().length > 0 && input.checkValidity()) {
                setFieldStatus(input.id, 'FILLED');
            }
        });
    });
});

function getStatusBadge(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return null;
    const group = input.closest('.form-group');
    if (!group) return null;
    let label = group.querySelector('label');
    if (!label) return null;
    
    let badge = label.querySelector('.status-badge');
    if (!badge) {
        badge = document.createElement('span');
        badge.className = 'status-badge';
        badge.style.marginLeft = '8px';
        badge.style.fontSize = '0.7rem';
        badge.style.padding = '2px 6px';
        badge.style.borderRadius = '4px';
        badge.style.fontWeight = '800';
        badge.style.textTransform = 'uppercase';
        badge.style.letterSpacing = '0.5px';
        badge.style.transition = 'all 0.3s ease';
        label.appendChild(badge);
    }
    return badge;
}

function setFieldStatus(inputId, status) {
    const badge = getStatusBadge(inputId);
    if (!badge) return;
    
    if (status === 'FILLING') {
        badge.textContent = 'FILLING';
        badge.style.backgroundColor = '#fef08a'; 
        badge.style.color = '#854d0e';
    } else if (status === 'FILLED') {
        badge.textContent = 'FILLED';
        badge.style.backgroundColor = '#dcfce7'; 
        badge.style.color = '#166534';
    } else if (status === 'ACTIVATED') {
        badge.textContent = 'ACTIVATED';
        badge.style.backgroundColor = '#dbeafe'; 
        badge.style.color = '#1e40af';
    } else if (status === 'EMPTY') {
        badge.textContent = '';
        badge.style.backgroundColor = 'transparent';
        badge.style.color = 'transparent';
    }
}
