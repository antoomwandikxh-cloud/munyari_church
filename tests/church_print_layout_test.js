const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const windowMock = {
    addEventListener() {},
    open() {}
};
const mainDocument = { currentScript: null };
vm.runInNewContext(fs.readFileSync('church_print_layout.js', 'utf8'), {
    window: windowMock,
    document: mainDocument
});

function makeCell(label, rowSpan = 1) {
    return {
        label,
        rowSpan,
        attributes: rowSpan > 1 ? { rowspan: String(rowSpan) } : {},
        cloneNode() {
            return makeCell(this.label, this.rowSpan);
        },
        removeAttribute(name) {
            delete this.attributes[name];
            if (name.toLowerCase() === 'rowspan') this.rowSpan = 1;
        }
    };
}

function makeRow(labels, firstCellRowSpan = 1) {
    const cells = labels.map((label, index) => makeCell(label, index === 0 ? firstCellRowSpan : 1));
    return {
        cells,
        insertBefore(newCell, referenceCell) {
            this.cells.splice(this.cells.indexOf(referenceCell), 0, newCell);
        }
    };
}

const tbody = {
    rows: [
        makeRow(['Youths', 'Chairperson', 'All approved youth members', 'Open'], 3),
        makeRow(['Vice Chairperson', 'All approved youth members', 'Open']),
        makeRow(['Secretary', 'All approved youth members', 'Open']),
        makeRow(['Women Ministry', 'Chairlady', 'Approved women members', 'Assigned'], 2),
        makeRow(['Vice Chairlady', 'Approved women members', 'Open']),
        makeRow(['General Church', 'Treasurer', 'Entire Church', 'Open'])
    ]
};
const printDocument = {
    documentElement: {},
    head: { appendChild(node) { this.style = node; } },
    querySelectorAll(selector) { return selector === 'table tbody' ? [tbody] : []; },
    querySelector() { return null; },
    createElement() {
        return { setAttribute(name, value) { this[name] = value; } };
    }
};

windowMock.prepareChurchPrintLayout(printDocument);
assert.deepEqual(tbody.rows.map(row => row.cells.map(cell => cell.label)), [
    ['Youths', 'Chairperson', 'All approved youth members', 'Open'],
    ['Youths', 'Vice Chairperson', 'All approved youth members', 'Open'],
    ['Youths', 'Secretary', 'All approved youth members', 'Open'],
    ['Women Ministry', 'Chairlady', 'Approved women members', 'Assigned'],
    ['Women Ministry', 'Vice Chairlady', 'Approved women members', 'Open'],
    ['General Church', 'Treasurer', 'Entire Church', 'Open']
]);
assert.ok(tbody.rows.every(row => row.cells[0].rowSpan === 1));
assert.match(printDocument.head.style.textContent, /margin-bottom:16mm/);

windowMock.prepareChurchPrintLayout(printDocument);
assert.equal(tbody.rows.every(row => row.cells.length === 4), true, 'preparation can run more than once without duplicating cells');
console.log('Church print layout tests passed.');
