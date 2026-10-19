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
    const leaderName = card.dataset.printLeader || '';
    const leaderPhoto = card.dataset.printLeaderPhoto
        ? new URL(card.dataset.printLeaderPhoto, window.location.href).href
        : '';
    const leaderRole = card.dataset.printLeaderRole || 'Discipline Master';
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
            @page{size:A4 ${pageOrientation};margin:12mm 10mm 24mm}
            *{box-sizing:border-box}
            html,body{height:auto;min-height:0}
            body{margin:0;padding:10px;padding-bottom:60px;color:#172033;font-family:Arial,Helvetica,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact}
            .watermark{position:fixed;z-index:0;top:48%;left:50%;transform:translate(-50%,-50%) rotate(-38deg);color:rgba(30,58,138,.12);font-size:42px;font-weight:900;letter-spacing:3px;white-space:nowrap;pointer-events:none}
            .report{position:relative;z-index:1;break-after:auto;page-break-after:auto}
            .leader{display:flex;align-items:center;justify-content:center;gap:9px;margin:0 0 7px;break-inside:avoid}
            .leader img{width:56px;height:56px;object-fit:cover;border-radius:50%;border:2px solid #1e3a8a}
            .leader-name{font-size:12px;font-weight:700;color:#1e3a8a;text-transform:uppercase}
            .leader-role{font-size:10px;color:#4b5563;margin-top:2px}
            .brand{display:grid;grid-template-columns:42px 1fr 42px;align-items:center;gap:7px;text-align:center;border-bottom:2px solid #1e3a8a;padding:0 0 5px;margin-bottom:6px;break-inside:avoid}
            .brand img{width:38px;height:38px;object-fit:contain}
            .brand h1{margin:0;color:#1e3a8a;font-size:16px;letter-spacing:0}
            .brand h2{margin:3px 0 0;font-size:13px;color:#222}
            .meta{margin:0 0 6px;color:#444;font-size:9px;display:flex;justify-content:space-between;gap:8px}
            table{width:100%;border-collapse:collapse;font-size:8.5px;background:transparent;table-layout:auto}
            thead{display:table-header-group}
            tfoot{display:table-footer-group}
            tr,td,th{page-break-inside:avoid;break-inside:avoid}
            th,td{border:1px solid #aab3c2;padding:3px;text-align:left;vertical-align:middle;overflow-wrap:anywhere}
            th{background:#e8eef8!important;color:#172033;font-weight:700}
            td img{display:inline-block;width:30px;height:30px;object-fit:cover;border-radius:50%;border:1px solid #8792a5;vertical-align:middle}
            .footer{position:fixed;z-index:2;left:0;right:0;bottom:0;border-top:1px solid #aab3c2;padding:5px 0;text-align:center;color:#4b5563;font-size:8px;background:rgba(255,255,255,.95)}
            @media print{body{margin:0;padding:0;-webkit-print-color-adjust:exact;print-color-adjust:exact}thead{display:table-header-group}tfoot{display:table-footer-group}table{page-break-inside:auto}}
        </style></head><body>
        <div class="watermark">E.A.P.C MUNYARI CHURCH</div>
        <main class="report">
            ${leaderName || leaderPhoto ? `<section class="leader"><img src="${escapeText(leaderPhoto || new URL('uploads/default_avatar.png', window.location.href).href)}" alt="${escapeText(leaderRole)} profile"><div><div class="leader-name">${escapeText(leaderName || leaderRole)}</div><div class="leader-role">${escapeText(leaderRole)}</div></div></section>` : ''}
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

function printFineReport(button, orientation) {
    const card = button?.closest('.fine-print-card');
    const sourceTable = card?.querySelector('.table-responsive table');
    if (!card || !sourceTable) return;

    const printWindow = window.open('', '_blank', 'width=1100,height=800');
    if (!printWindow) {
        window.alert('Allow pop-ups for this site to print or save the report as PDF.');
        return;
    }

    const table = sourceTable.cloneNode(true);
    const reportType = card.dataset.printTitle || 'Fine Records';
    const leaderRole = card.dataset.printLeaderRole || 'Organising Secretary';
    const disciplineMasterUnpaidReport = reportType.toLowerCase() === 'unpaid fines'
        && leaderRole.toLowerCase() === 'discipline master';

    if (disciplineMasterUnpaidReport) {
        const headerCells = Array.from(table.querySelectorAll('thead th'));
        const removedColumnIndexes = headerCells
            .map((cell, index) => ({label: cell.textContent.trim().toLowerCase(), index}))
            .filter(column => ['offense', 'action'].includes(column.label))
            .map(column => column.index)
            .sort((first, second) => second - first);

        removedColumnIndexes.forEach(columnIndex => {
            table.querySelectorAll('tr').forEach(row => {
                if (row.children[columnIndex]) row.children[columnIndex].remove();
            });
        });

        table.querySelectorAll('td div').forEach(reason => {
            if (reason.textContent.toLowerCase().includes("treasurer's decline reason:")) reason.remove();
        });
        table.querySelectorAll('span').forEach(badge => {
            if (badge.textContent.trim().toLowerCase() === 'reminder sent') badge.remove();
        });

        const remainingHeaders = Array.from(table.querySelectorAll('thead th'));
        const amountColumnIndex = remainingHeaders.findIndex(cell => cell.textContent.trim().toLowerCase() === 'amount');
        if (amountColumnIndex >= 0) {
            table.querySelectorAll('tbody tr').forEach(row => {
                const amountCell = row.children[amountColumnIndex];
                if (amountCell) {
                    amountCell.style.color = '#dc2626';
                    amountCell.style.fontWeight = '700';
                }
            });
        }
    }

    table.querySelectorAll('img').forEach(image => {
        image.src = image.src || new URL('uploads/default_avatar.png', window.location.href).href;
        image.removeAttribute('onclick');
        image.removeAttribute('title');
        image.setAttribute('onerror', `this.onerror=null;this.src='${new URL('uploads/default_avatar.png', window.location.href).href}'`);
    });

    const department = card.dataset.printDepartment || '';
    const heading = `${department} Department - ${reportType}`;
    const leaderName = card.dataset.printLeader || 'Organising Secretary';
    const leaderPhoto = new URL(card.dataset.printLeaderPhoto || 'uploads/default_avatar.png', window.location.href).href;
    const logoUrl = new URL('church_logo.jpg', window.location.href).href;
    const printedAt = new Date().toLocaleString();
    const pageOrientation = orientation === 'landscape' ? 'landscape' : 'portrait';
    const caseCount = table.querySelectorAll('tbody tr').length;
    const escapeText = value => String(value).replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    })[character]);

    printWindow.document.open();
    printWindow.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${escapeText(heading)}</title><style>
        @page{size:A4 ${pageOrientation};margin:12mm 10mm 24mm}*{box-sizing:border-box}
        body{position:relative;font:10px Arial,Helvetica,sans-serif;color:#17202a;margin:0;padding:0;-webkit-print-color-adjust:exact;print-color-adjust:exact}
        .watermark{position:fixed;top:43%;left:50%;transform:translate(-50%,-50%) rotate(-38deg);font-size:40px;color:rgba(14,116,144,.16);font-weight:900;white-space:nowrap;z-index:0;pointer-events:none}
        .leader,.brand,.report-heading,.report-meta,table,.signature{position:relative;z-index:1}
        .leader{text-align:center;margin:0 auto 7px}.leader img{width:58px;height:58px;border-radius:50%;object-fit:cover;border:3px solid #0e7490}
        .leader-name{font-size:12px;font-weight:800;color:#2563eb;margin-top:3px;text-transform:uppercase}
        .brand{display:grid;grid-template-columns:46px 1fr 46px;align-items:center;gap:8px;text-align:center;border-bottom:2px solid #0e7490;padding:0 0 5px;margin-bottom:7px}
        .brand-logo{width:42px;height:42px;object-fit:contain}.brand h1{font-size:15px;line-height:1.2;color:#0e7490;text-transform:uppercase;margin:2px 0}
        .brand p{font-size:9px;color:#555;margin:2px 0}.report-heading{text-align:center;font-size:13px;font-weight:800;color:#17202a;text-transform:uppercase;margin:0 0 4px}
        .report-meta{text-align:center;margin:0 0 7px;color:#444;font-size:9px}
        table{width:100%;border-collapse:collapse;page-break-inside:auto}thead{display:table-header-group}tr,td,th{page-break-inside:avoid;break-inside:avoid}
        th,td{border:1px solid #888;padding:4px;text-align:left;vertical-align:middle;overflow-wrap:anywhere}th{background:#0e7490!important;color:#fff;font-size:9px;text-transform:uppercase}
        td img{display:block;width:30px;height:30px;border-radius:50%;object-fit:cover;border:1px solid #aaa}
        .signature{display:grid;grid-template-columns:minmax(0,1fr) 66px;align-items:center;gap:16px;width:min(100%,520px);margin:12px auto 0}
        .signature-details{display:flex;flex-direction:column;gap:5px}.signature-row{display:grid;grid-template-columns:165px minmax(70px,1fr);gap:8px;align-items:end;min-height:17px}
        .signature-label{font-weight:700}.signature-line{height:14px;border-bottom:1px solid #333}
        .stamp{width:64px;height:64px;justify-self:end;border:2px dashed #999;border-radius:50%;display:flex;align-items:center;justify-content:center;text-align:center;color:#777;font-size:9px}
        .discipline-unpaid-print .signature{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;width:100%;margin:80px auto 0;padding:0 40px;page-break-inside:avoid;break-inside:avoid}
        .discipline-unpaid-print .signature-details{gap:9px;text-align:center}.discipline-unpaid-print .signature-row{display:flex;justify-content:center;gap:8px;align-items:flex-end;min-height:18px}
        .discipline-unpaid-print .signature-line{display:inline-block;width:220px;height:14px}
        .discipline-unpaid-print .stamp{width:140px;height:140px;flex:0 0 140px;margin-left:auto;margin-bottom:10px;border-color:#aaa;color:#ccc;font-size:.85rem;letter-spacing:1px;text-transform:uppercase}
        .footer{position:fixed;bottom:0;left:0;right:0;z-index:2;border-top:1px solid #0e7490;padding:4px 0;text-align:center;font-size:8px;color:#53606b;background:rgba(255,255,255,.95)}
        @media print{body{margin:0;padding:0;-webkit-print-color-adjust:exact;print-color-adjust:exact}thead{display:table-header-group}tfoot{display:table-footer-group}}
    </style></head><body class="${disciplineMasterUnpaidReport ? 'discipline-unpaid-print' : ''}">
        <div class="watermark">E.A.P.C MUNYARI CHURCH</div>
        <div class="leader"><img src="${escapeText(leaderPhoto)}" alt="${escapeText(leaderRole)}"><div class="leader-name">${escapeText(leaderName)}</div><div style="font-size:10px;color:#555">${escapeText(leaderRole)} - ${escapeText(department)} Department</div></div>
        <div class="brand"><img class="brand-logo" src="${escapeText(logoUrl)}" alt="E.A.P.C Munyari logo"><h1>E.A.P.C MUNYARI CHURCH<br>${escapeText(heading)}</h1><img class="brand-logo" src="${escapeText(logoUrl)}" alt="E.A.P.C Munyari logo"></div>
        <div class="report-heading">${escapeText(reportType)}</div>
        <p class="report-meta">${escapeText(department)} Department | Total records: ${caseCount}<br>Generated: ${escapeText(printedAt)}</p>
        ${table.outerHTML}
        <div class="signature" style="${disciplineMasterUnpaidReport ? 'display:flex;justify-content:space-between;align-items:flex-end;gap:16px;width:100%;margin:80px auto 0;padding:0 40px;page-break-inside:avoid;break-inside:avoid' : ''}"><div class="signature-details" style="${disciplineMasterUnpaidReport ? 'display:flex;flex-direction:column;gap:9px;text-align:center' : ''}">
            <div class="signature-row" style="${disciplineMasterUnpaidReport ? 'display:flex;justify-content:center;gap:8px;align-items:flex-end;min-height:18px' : ''}"><span class="signature-label">${escapeText(leaderRole)} Signature</span><span class="signature-line" style="${disciplineMasterUnpaidReport ? 'display:inline-block;width:220px;height:14px' : ''}"></span></div>
            <div class="signature-row" style="${disciplineMasterUnpaidReport ? 'display:block;text-align:center' : ''}"><span class="signature-label">Name: ${escapeText(leaderName)}</span></div>
            <div class="signature-row" style="${disciplineMasterUnpaidReport ? 'display:flex;justify-content:center;gap:8px;align-items:flex-end;min-height:18px' : ''}"><span class="signature-label">Date</span><span class="signature-line" style="${disciplineMasterUnpaidReport ? 'display:inline-block;width:220px;height:14px' : ''}"></span></div>
        </div><div class="stamp" style="${disciplineMasterUnpaidReport ? 'box-sizing:border-box;border:2px dashed #aaa;width:140px;height:140px;min-width:140px;min-height:140px;flex:0 0 140px;margin:0 0 10px auto;border-radius:50%;display:flex;align-items:center;justify-content:center;text-align:center;color:#ccc;font-size:.85rem;letter-spacing:1px;text-transform:uppercase' : ''}">OFFICIAL<br>STAMP</div></div>
        <div class="footer">E.A.P.C Munyari Fine Records | ${escapeText(department)} Department | ${escapeText(reportType)} | Printed: ${escapeText(printedAt)}</div>
    </body></html>`);
    printWindow.document.close();

    const images = Array.from(printWindow.document.images);
    Promise.all(images.map(image => image.complete ? Promise.resolve() : new Promise(resolve => {
        image.onload = resolve;
        image.onerror = resolve;
    }))).then(() => {
        printWindow.focus();
        window.setTimeout(() => printWindow.print(), 300);
    });
}

