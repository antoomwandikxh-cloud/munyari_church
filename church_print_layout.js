(function () {
    function flattenDepartmentRowspans(doc) {
        doc.querySelectorAll('table tbody').forEach(function (tbody) {
            var departmentCell = null;
            var rowsRemaining = 0;
            var rows = Array.from(tbody.rows);

            rows.forEach(function (row) {
                var firstCell = row.cells[0];
                if (!firstCell) return;

                if (firstCell.rowSpan > 1) {
                    rowsRemaining = firstCell.rowSpan - 1;
                    departmentCell = firstCell.cloneNode(true);
                    departmentCell.removeAttribute('rowspan');
                    firstCell.removeAttribute('rowspan');
                } else if (departmentCell && rowsRemaining > 0) {
                    var repeatedCell = departmentCell.cloneNode(true);
                    repeatedCell.removeAttribute('rowspan');
                    row.insertBefore(repeatedCell, firstCell);
                    rowsRemaining--;
                } else {
                    departmentCell = null;
                    rowsRemaining = 0;
                }
            });
        });
    }

    function positionLeaderProfiles(doc) {
        var profile = doc.querySelector('.print-leader-header');
        var reportHeader = doc.querySelector('.main-header, .header, .report-header, [data-print-report-title]');
        if (!profile || !reportHeader || !reportHeader.parentNode) return;

        if (profile.parentNode === reportHeader.parentNode && profile.nextElementSibling === reportHeader) return;
        reportHeader.parentNode.insertBefore(profile, reportHeader);
    }

    function prepareChurchPrintLayout(doc) {
        if (!doc || !doc.head || !doc.documentElement) return;

        flattenDepartmentRowspans(doc);
        positionLeaderProfiles(doc);
        if (doc.querySelector('[data-church-print-layout]')) return;

        var style = doc.createElement('style');
        style.setAttribute('data-church-print-layout', 'true');
        style.media = 'print';
        style.textContent = '@page{margin-bottom:16mm!important}' +
            'thead{display:table-header-group!important}tfoot{display:table-footer-group!important}' +
            'table{page-break-inside:auto!important;break-inside:auto!important}' +
            'tr,td,th{page-break-inside:avoid!important;break-inside:avoid!important}' +
            '.print-leader-header{display:flex!important;align-items:center!important;justify-content:center!important;flex-wrap:wrap!important;gap:12px 24px!important;margin:0 0 8px!important;padding:0!important;border:0!important;border-radius:0!important;background:transparent!important;box-shadow:none!important;page-break-inside:avoid!important;break-inside:avoid!important}' +
            '.print-leader-person{display:flex!important;flex-direction:column!important;align-items:center!important;justify-content:center!important;text-align:center!important;gap:2px!important;padding:0!important;margin:0!important;border:0!important;border-radius:0!important;background:transparent!important;box-shadow:none!important}' +
            '.print-leader-header img.print-leader-avatar{display:block!important;width:76px!important;height:76px!important;min-width:76px!important;max-width:76px!important;aspect-ratio:1/1!important;object-fit:cover!important;object-position:center!important;border:0!important;border-radius:50%!important;clip-path:circle(50%)!important;box-shadow:none!important;margin:0 auto 4px!important;padding:0!important}' +
            '.print-leader-header .print-leader-photo-frame{display:contents!important;border:0!important;border-radius:0!important;background:transparent!important;box-shadow:none!important;padding:0!important}';
        doc.head.appendChild(style);
    }

    window.prepareChurchPrintLayout = prepareChurchPrintLayout;

    var directPrintPage = document.currentScript && document.currentScript.dataset.directPrint === 'true';
    if (directPrintPage) {
        window.addEventListener('beforeprint', function () {
            prepareChurchPrintLayout(document);
        });
    }

    var nativeOpen = window.open;
    if (typeof nativeOpen === 'function') {
        try {
            window.open = function () {
                var printWindow = Reflect.apply(nativeOpen, this, arguments);
                if (printWindow) {
                    printWindow.addEventListener('beforeprint', function () {
                        prepareChurchPrintLayout(printWindow.document);
                    });
                }
                return printWindow;
            };
        } catch (error) {}
    }
})();
