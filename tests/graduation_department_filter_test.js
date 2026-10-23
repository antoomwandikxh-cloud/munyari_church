const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const page = fs.readFileSync('pastor_dashboard.php', 'utf8');
const scriptMatch = page.match(/<script id="graduationDepartmentFilterScript">([\s\S]*?)<\/script>/);
assert.ok(scriptMatch, 'Pastor Graduation Info has a department filter script');
const filterKeys = Array.from(page.matchAll(/data-graduation-department-filter="([^"]+)"/g), match => match[1]);
assert.deepEqual(filterKeys, ['all', 'youths', 'sunday_school'], 'Graduation Info shows All, Youths, and Sunday School filter buttons');
assert.match(page, /data-graduation-kind="roster" data-graduation-department=/, 'graduation roster rows carry their department for filtering');
assert.match(page, /data-graduation-kind="announcement" data-graduation-department=/, 'graduation announcements carry their department for filtering');
assert.match(page, /<div id="pastorGraduationRosterPanel" class="content-card"[\s\S]{0,400}<h2 style="margin: 0;">Graduation Roster/, 'the roster collapse target wraps the Graduation Roster panel');
assert.match(page, /<div id="pastorGraduationAnnouncementsPanel" class="content-card">\s*<h2>Graduation Announcements/, 'the announcements collapse target wraps the Graduation Announcements panel');

const buttonCounts = {
    all: { textContent: '' },
    youths: { textContent: '' },
    sunday_school: { textContent: '' }
};
const buttons = ['all', 'youths', 'sunday_school'].map(key => ({
    dataset: {
        graduationDepartmentFilter: key,
        graduationDepartmentLabel: ({ all: 'All Departments', youths: 'Youths', sunday_school: 'Sunday School' })[key]
    },
    style: {},
    attributes: { 'aria-pressed': key === 'all' ? 'true' : 'false' },
    listeners: {},
    getAttribute(name) { return this.attributes[name] || null; },
    setAttribute(name, value) { this.attributes[name] = value; },
    addEventListener(name, callback) { this.listeners[name] = callback; },
    click() { this.listeners.click.call(this); }
}));
const records = [
    { dataset: { graduationDepartment: 'youths', graduationKind: 'roster' }, style: {} },
    { dataset: { graduationDepartment: 'youths', graduationKind: 'announcement' }, style: {} },
    { dataset: { graduationDepartment: 'sunday_school', graduationKind: 'roster' }, style: {} },
    { dataset: { graduationDepartment: 'sunday_school', graduationKind: 'announcement' }, style: {} },
    { dataset: { graduationDepartment: 'elders', graduationKind: 'announcement' }, style: {} }
];
const rosterDefaultEmpty = { parentElement: { style: {} } };
const announcementsDefaultEmpty = { style: {} };
const rosterEmpty = { style: {}, textContent: '' };
const announcementEmpty = { style: {}, textContent: '' };
const status = { style: {}, textContent: '' };
const rosterTotal = { textContent: '', dataset: { churchwideTotal: '2' } };
const rosterWrap = { style: {} };
const rosterPanel = { style: {} };
const announcementsPanel = { style: {} };
const filterRoot = {
    querySelectorAll() { return buttons; },
    querySelector(selector) {
        const match = selector.match(/data-graduation-department-count="([^"]+)"/);
        return match ? buttonCounts[match[1]] : null;
    }
};
const elements = {
    'graduation-department-filter': filterRoot,
    'graduation-department-filter-status': status,
    'graduation-roster-filter-empty': rosterEmpty,
    'graduation-announcements-filter-empty': announcementEmpty,
    graduationRosterDefaultEmpty: rosterDefaultEmpty,
    graduationAnnouncementsDefaultEmpty: announcementsDefaultEmpty,
    graduationRosterTotal: rosterTotal,
    pastorGraduationRosterWrap: rosterWrap,
    pastorGraduationRosterPanel: rosterPanel,
    pastorGraduationAnnouncementsPanel: announcementsPanel
};
const document = {
    getElementById(id) { return elements[id] || null; },
    querySelectorAll() { return records; }
};

vm.runInNewContext(scriptMatch[1], { document });

assert.equal(buttonCounts.all.textContent, '3', 'All Departments count includes all graduation announcements');
assert.equal(buttonCounts.youths.textContent, '1', 'Youths count includes only graduation announcements');
assert.equal(buttonCounts.sunday_school.textContent, '1', 'Sunday School count includes only graduation announcements');
buttons[1].click();
assert.deepEqual(records.map(record => record.style.display || ''), ['', '', 'none', 'none', 'none'], 'Youths button filters all graduation content');
assert.equal(rosterTotal.textContent, 'Total Youths: 1', 'roster total updates to the selected department');
assert.equal(buttons[1].getAttribute('aria-pressed'), 'true', 'active department is announced accessibly');
buttons[1].click();
assert.deepEqual(records.map(record => record.style.display || ''), ['none', 'none', 'none', 'none', 'none'], 'clicking the active department closes the graduation lists');
assert.equal(rosterWrap.style.display, 'none', 'closing a department hides the graduation roster table');
assert.equal(rosterPanel.style.display, 'none', 'closing a department hides the graduation roster panel');
assert.equal(announcementsPanel.style.display, 'none', 'closing a department hides the graduation announcements panel');
assert.match(status.textContent, /lists are closed/i, 'closing a department explains how to reopen a list');
buttons[2].click();
assert.deepEqual(records.map(record => record.style.display || ''), ['none', 'none', '', '', 'none'], 'Sunday School button filters graduation content to Sunday School');
assert.equal(rosterTotal.textContent, 'Total Sunday School: 1', 'roster total updates for Sunday School');
assert.equal(rosterPanel.style.display, '', 'choosing a department reopens the graduation roster panel');
assert.equal(announcementsPanel.style.display, '', 'choosing a department reopens the graduation announcements panel');
buttons[0].click();
assert.deepEqual(records.map(record => record.style.display || ''), ['', '', '', '', ''], 'All Departments button restores every department');
assert.equal(rosterTotal.textContent, 'Total Church-wide: 2', 'All Departments restores the original church-wide roster total');

console.log('Graduation department filter tests passed.');
