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

    function prepareChurchPrintLayout(doc) {
        if (!doc || !doc.head || !doc.documentElement) return;

        flattenDepartmentRowspans(doc);
        if (doc.querySelector('[data-church-print-layout]')) return;

        var style = doc.createElement('style');
        style.setAttribute('data-church-print-layout', 'true');
        style.media = 'print';
        style.textContent = '@page{margin-bottom:16mm!important}' +
            'thead{display:table-header-group!important}tfoot{display:table-footer-group!important}' +
            'table{page-break-inside:auto!important;break-inside:auto!important}' +
            'tr,td,th{page-break-inside:avoid!important;break-inside:avoid!important}';
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
