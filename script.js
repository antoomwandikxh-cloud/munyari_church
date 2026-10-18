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

function printDisciplinaryCases(button, orientation) {
    const card = button?.closest('.content-card');
    const sourceTable = card?.querySelector('.table-responsive table');
    if (!sourceTable) return;

    const printWindow = window.open('', '_blank', 'width=1100,height=800');
    if (!printWindow) {
        window.alert('Allow pop-ups for this site to print or save the report as PDF.');
        return;
    }

    const table = sourceTable.cloneNode(true);
    const header = table.querySelector('thead tr');
    if (header) {
        const actionIndex = Array.from(header.cells).findIndex(cell => cell.textContent.trim().toLowerCase() === 'action');
        if (actionIndex >= 0) {
            header.deleteCell(actionIndex);
            table.querySelectorAll('tbody tr').forEach(row => row.deleteCell(actionIndex));
        }
    }

    const fallbackPhoto = new URL('uploads/default_avatar.png', window.location.href).href;
    table.querySelectorAll('img').forEach(image => {
        image.src = image.src || fallbackPhoto;
        image.removeAttribute('onclick');
        image.removeAttribute('title');
        image.setAttribute('onerror', `this.onerror=null;this.src='${fallbackPhoto}'`);
    });

    const reportTitle = card.querySelector('h2')?.textContent.trim() || 'Disciplinary Case Report';
    const caseCount = table.querySelectorAll('tbody tr').length;
    const printedAt = new Date().toLocaleString();
    const logoUrl = new URL('church_logo.jpg', window.location.href).href;
    const pageOrientation = orientation === 'landscape' ? 'landscape' : 'portrait';
    const escapeText = value => String(value).replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    })[character]);

    printWindow.document.open();
    printWindow.document.write(`<!doctype html>
        <html><head><meta charset="utf-8"><title>${escapeText(reportTitle)}</title>
        <style>
            @page{size:A4 ${pageOrientation};margin:14mm 10mm 23mm}
            *{box-sizing:border-box}
            body{margin:0;color:#172033;font-family:Arial,Helvetica,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact}
            .watermark{position:fixed;z-index:0;top:48%;left:50%;transform:translate(-50%,-50%) rotate(-38deg);color:rgba(30,58,138,.12);font-size:42px;font-weight:900;letter-spacing:3px;white-space:nowrap;pointer-events:none}
            .report{position:relative;z-index:1}
            .brand{display:grid;grid-template-columns:64px 1fr 64px;align-items:center;gap:12px;text-align:center;border-bottom:2px solid #1e3a8a;padding:0 0 12px;margin-bottom:15px}
            .brand img{width:60px;height:60px;object-fit:contain}
            .brand h1{margin:0;color:#1e3a8a;font-size:20px;letter-spacing:1px}
            .brand h2{margin:5px 0 0;font-size:16px;color:#222}
            .meta{margin:0 0 12px;color:#444;font-size:12px;display:flex;justify-content:space-between;gap:12px}
            table{width:100%;border-collapse:collapse;font-size:11px;background:transparent}
            thead{display:table-header-group}
            tr{page-break-inside:avoid;break-inside:avoid}
            th,td{border:1px solid #aab3c2;padding:7px 6px;text-align:left;vertical-align:middle;overflow-wrap:anywhere}
            th{background:#e8eef8!important;color:#172033;font-weight:700}
            td img{display:block;width:38px;height:38px;object-fit:cover;border-radius:50%;border:1px solid #8792a5}
            .footer{position:fixed;z-index:2;left:0;right:0;bottom:-16mm;border-top:1px solid #aab3c2;padding-top:5px;text-align:center;color:#4b5563;font-size:9px;background:#fff}
            @media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
        </style></head><body>
        <div class="watermark">E.A.P.C MUNYARI CHURCH</div>
        <main class="report">
            <header class="brand"><img src="${escapeText(logoUrl)}" alt="Church logo"><div><h1>E.A.P.C MUNYARI CHURCH</h1><h2>${escapeText(reportTitle)}</h2></div><img src="${escapeText(logoUrl)}" alt="Church logo"></header>
            <p class="meta"><span>Total cases: ${caseCount}</span><span>Generated: ${escapeText(printedAt)}</span></p>
            ${table.outerHTML}
        </main>
        <footer class="footer">E.A.P.C Munyari Portal | ${escapeText(reportTitle)} | Printed on ${escapeText(printedAt)}</footer>
        </body></html>`);
    printWindow.document.close();

    const images = Array.from(printWindow.document.images);
    Promise.all(images.map(image => image.complete ? Promise.resolve() : new Promise(resolve => {
        image.onload = resolve;
        image.onerror = resolve;
    }))).then(() => {
        printWindow.focus();
        window.setTimeout(() => printWindow.print(), 250);
    });
}

