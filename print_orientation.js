(function () {
    let orientationDialog;

    function getDialog() {
        if (orientationDialog) return orientationDialog;

        orientationDialog = document.createElement('dialog');
        orientationDialog.className = 'print-orientation-dialog';
        orientationDialog.setAttribute('aria-labelledby', 'printOrientationTitle');
        orientationDialog.innerHTML = `
            <form method="dialog" class="print-orientation-panel">
                <h2 id="printOrientationTitle">Choose print orientation</h2>
                <p>Select the page layout for this printout.</p>
                <div class="print-orientation-options">
                    <button type="button" data-orientation="portrait">Portrait</button>
                    <button type="button" data-orientation="landscape">Landscape</button>
                </div>
                <button type="button" class="print-orientation-cancel">Cancel</button>
            </form>`;
        document.body.appendChild(orientationDialog);

        const style = document.createElement('style');
        style.textContent = `
            .print-orientation-dialog{position:fixed!important;left:50%!important;top:50%!important;right:auto!important;bottom:auto!important;transform:translate(-50%,-50%)!important;margin:0!important;box-sizing:border-box;width:min(92vw,360px);max-width:calc(100vw - 24px);max-height:calc(100dvh - 32px);overflow:auto;padding:0;border:1px solid #cbd5e1;border-radius:8px;color:var(--text-main,#172033);background:var(--card-bg,#fff);box-shadow:0 18px 50px rgba(15,23,42,.28)}
            .print-orientation-dialog::backdrop{background:rgba(15,23,42,.55)}
            .print-orientation-panel{padding:20px;font-family:Arial,Helvetica,sans-serif}
            .print-orientation-panel h2{margin:0;font-size:18px;color:inherit}
            .print-orientation-panel p{margin:7px 0 18px;color:var(--text-muted,#64748b);font-size:14px}
            .print-orientation-options{display:grid;grid-template-columns:1fr 1fr;gap:10px}
            .print-orientation-options button,.print-orientation-cancel{min-height:40px;padding:8px 12px;border:1px solid #cbd5e1;border-radius:6px;background:var(--bg-main,#f8fafc);color:var(--text-main,#172033);font:600 14px Arial,Helvetica,sans-serif;cursor:pointer}
            .print-orientation-options button:hover{border-color:#1e3a8a;background:#eff6ff;color:#1e3a8a}
            .print-orientation-cancel{display:block;margin:12px 0 0 auto;background:transparent}
        `;
        document.head.appendChild(style);

        orientationDialog.addEventListener('click', function (event) {
            const button = event.target.closest('[data-orientation]');
            if (!button) return;
            const callback = orientationDialog._onChoose;
            orientationDialog._onChoose = null;
            orientationDialog.close();
            if (callback) callback(button.dataset.orientation);
        });
        orientationDialog.querySelector('.print-orientation-cancel').addEventListener('click', function () {
            orientationDialog._onChoose = null;
            orientationDialog.close();
        });
        return orientationDialog;
    }

    window.choosePrintOrientation = function (onChoose) {
        if (typeof onChoose !== 'function') return;
        const dialog = getDialog();
        dialog._onChoose = onChoose;
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else {
            const orientation = window.confirm('Choose print orientation: OK for Portrait, Cancel for Landscape.')
                ? 'portrait'
                : 'landscape';
            dialog._onChoose = null;
            onChoose(orientation);
        }
    };

    window.printCurrentPageWithOrientation = function () {
        window.choosePrintOrientation(function (orientation) {
            let pageStyle = document.getElementById('selectedPrintPageOrientation');
            if (!pageStyle) {
                pageStyle = document.createElement('style');
                pageStyle.id = 'selectedPrintPageOrientation';
                document.head.appendChild(pageStyle);
            }
            pageStyle.textContent = `@media print{@page{size:A4 ${orientation};}}`;
            window.__printOrientationSelected = true;
            window.addEventListener('afterprint', function () {
                window.focus();
            }, { once: true });
            window.print();
        });
    };

    const nativePrint = window.print.bind(window);
    window.print = function () {
        if (window.__printOrientationSelected) return nativePrint();

        const query = new URLSearchParams(window.location.search);
        const requested = query.get('orientation') || query.get('mode');
        const applyAndPrint = orientation => {
            let pageStyle = document.getElementById('selectedPrintPageOrientation');
            if (!pageStyle) {
                pageStyle = document.createElement('style');
                pageStyle.id = 'selectedPrintPageOrientation';
                document.head.appendChild(pageStyle);
            }
            pageStyle.textContent = `@media print{@page{size:A4 ${orientation};}}`;
            window.__printOrientationSelected = true;
            window.addEventListener('afterprint', function () {
                window.focus();
            }, { once: true });
            nativePrint();
        };

        if (requested === 'portrait' || requested === 'landscape') applyAndPrint(requested);
        else window.choosePrintOrientation(applyAndPrint);
    };

    window.openPrintPageWithOrientation = function (pageUrl, parameterName) {
        window.choosePrintOrientation(function (orientation) {
            const url = new URL(pageUrl, window.location.href);
            url.searchParams.set(parameterName || 'orientation', orientation);
            url.searchParams.set('print_now', '1');
            const printWindow = window.open(url.href, '_blank');
            if (!printWindow) window.alert('Allow pop-ups for this site to print or save the report as PDF.');
        });
    };

    function closePrintWindowAfterDialog() {
        if (!window.opener || window.opener.closed) return;
        window.addEventListener('afterprint', function () {
            const opener = window.opener;
            try {
                if (opener && !opener.closed) opener.focus();
            } catch (error) {
                // Focus can be restricted by the browser, but closing still returns to the opener.
            }
            if (!window.closed) window.close();
        }, { once: true });
    }

    closePrintWindowAfterDialog();

    // Dashboard print routines build reports in blank popup windows. Close those
    // only after the native print dialog finishes so focus returns to the dashboard.
    const nativeOpen = window.open.bind(window);
    window.open = function (url, target, features) {
        const child = nativeOpen(url, target, features);
        if (child && (!url || url === 'about:blank')) {
            try {
                const closeAfterPrint = function () {
                    child.addEventListener('afterprint', function () {
                        try {
                            if (!child.closed) child.close();
                        } catch (error) {
                            // The browser may already have closed the print window.
                        }
                        window.focus();
                    }, { once: true });
                };
                closeAfterPrint();

                const childDocument = child.document;
                const nativeDocumentClose = childDocument.close.bind(childDocument);
                childDocument.close = function () {
                    const result = nativeDocumentClose();
                    closeAfterPrint();
                    return result;
                };
            } catch (error) {
                // Keep printing available if a browser blocks popup window access.
            }
        }
        return child;
    };

    function cleanPrintLabel(element) {
        const originalText = element.textContent;
        element.innerHTML = element.innerHTML
            .replace(/\s*[-(]?\s*(portrait|landscape)\s*\)?/ig, '')
            .replace(/\(\s*\)/g, '')
            .replace(/\bmode\b/ig, '')
            .trim();
        if (!/\bprint\b/i.test(element.textContent)) element.append(' Print');
        if (!originalText.trim()) element.textContent = 'Print';
    }

    function normalizeOrientationButtons() {
        document.querySelectorAll('button[onclick], a[onclick]').forEach(element => {
            if (/\b(?:window\.)?print\s*\(\s*\)/i.test(element.getAttribute('onclick') || '')) {
                element.setAttribute('onclick', 'printCurrentPageWithOrientation(); return false;');
            }
        });

        const candidates = Array.from(document.querySelectorAll('button, a')).map(element => {
            const onclick = element.getAttribute('onclick') || '';
            const href = element.getAttribute('href') || '';
            const url = href && href !== '#' ? new URL(href, window.location.href) : null;
            const queryKey = url && ['orientation', 'mode'].find(key => ['portrait', 'landscape'].includes(url.searchParams.get(key)));
            const orientationInCall = /['"](portrait|landscape)['"]/i.test(onclick);
            if (url && href.startsWith('?') && /switch\s+to/i.test(element.textContent)) {
                element.hidden = true;
                return null;
            }
            if (!queryKey && !orientationInCall) return null;
            if (url && !/^print_[^/]+\.php$/i.test(url.pathname.split('/').pop())) return null;

            let signature;
            if (queryKey) {
                url.searchParams.delete(queryKey);
                url.searchParams.delete('print_now');
                signature = `link:${url.pathname}?${url.searchParams.toString()}:${queryKey}`;
            } else {
                signature = `call:${onclick.replace(/(['"])(portrait|landscape)\1/ig, 'null')}`;
            }
            return { element, queryKey, url, signature, parent: element.parentElement };
        }).filter(Boolean);

        const grouped = new Map();
        candidates.forEach(candidate => {
            let parentGroups = grouped.get(candidate.parent);
            if (!parentGroups) grouped.set(candidate.parent, parentGroups = new Map());
            const group = parentGroups.get(candidate.signature) || [];
            group.push(candidate);
            parentGroups.set(candidate.signature, group);
        });

        grouped.forEach(parentGroups => parentGroups.forEach(group => {
            const keep = group.find(item => /portrait/i.test(item.element.getAttribute('onclick') || item.element.getAttribute('href') || item.element.textContent)) || group[0];
            group.forEach(item => {
                if (item !== keep) {
                    item.element.style.display = 'none';
                    item.element.hidden = true;
                    return;
                }
                if (item.queryKey) {
                    const pageUrl = item.url.pathname + (item.url.search ? item.url.search : '');
                    item.element.removeAttribute('target');
                    item.element.href = '#';
                    item.element.onclick = function (event) {
                        event.preventDefault();
                        window.openPrintPageWithOrientation(pageUrl, item.queryKey);
                    };
                } else {
                    const onclick = item.element.getAttribute('onclick') || '';
                    if (/setOriAndPrint\s*\(/i.test(onclick)) {
                        item.element.setAttribute('onclick', 'printCurrentPageWithOrientation(); return false;');
                    } else {
                        item.element.setAttribute('onclick', onclick.replace(/(['"])(portrait|landscape)\1/ig, 'null'));
                    }
                }
                cleanPrintLabel(item.element);
            });
        }));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', normalizeOrientationButtons, { once: true });
    } else {
        normalizeOrientationButtons();
    }
})();
