const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const page = fs.readFileSync('pastor_dashboard.php', 'utf8');
const memberPage = fs.readFileSync('member_dashboard.php', 'utf8');
const sharedPrintHelpers = fs.readFileSync('script.js', 'utf8');
const scriptMatch = page.match(/<script id="graduationDepartmentFilterScript">([\s\S]*?)<\/script>/);
assert.ok(scriptMatch, 'Pastor Graduation Info has a department filter script');
const printStart = page.indexOf('function printPastorGraduationRoster(');
const printEnd = page.indexOf('</script>', printStart);
const printScript = page.slice(printStart, printEnd);
assert.ok(printStart >= 0 && printEnd > printStart, 'Pastor Graduation Info has its graduation print function');
assert.doesNotMatch(printScript, /leaderPhoto|alt="Church Pastor"/, 'graduation printouts omit the pastor profile header');
assert.match(printScript, /selectedDepartment === 'all' \? \['youths', 'sunday_school'\] : \[selectedDepartment\]/, 'combined print includes both department graduation secretaries');
assert.match(printScript, /class="print-leader-header secretary-profiles">\$\{secretaryProfilesMarkup\}/, 'secretary portraits are grouped at the top before the report header');
assert.match(printScript, /const secretarySignaturesMarkup = secretaryDepartmentKeys\.map/, 'signature blocks follow the same department selection as secretary portraits');
assert.match(printScript, /buildChurchPrintSignatureMarkup\(`\$\{secretaryRole\} - \$\{departmentLabel\}`, secretaryName, escapeHtml, true\)/, 'each graduation secretary receives a signature block with time');
assert.match(sharedPrintHelpers, /includeTime = false[\s\S]*?Time:/, 'shared signature helper adds a time line only when requested');
assert.match(printScript, /const signatureMarkup = `<div class="graduation-signatures">\$\{secretarySignaturesMarkup\}<\/div>`/, 'graduation print uses department secretary signatures instead of the pastor signature');
assert.match(printScript, /grid-template-columns:repeat\(\$\{signatureColumns\},minmax\(0,1fr\)\)/, 'two department signatures sit side-by-side and a department print uses one column');
assert.match(printScript, /\.graduation-signatures \.signature\{[^}]*flex-direction:row!important/, 'secretary signature details and stamp are arranged horizontally');
assert.match(printScript, /\.graduation-signatures \.signature-details span\[style\*=\"width:220px\"\]\{width:\$\{signatureColumns > 1 \? '140px' : '220px'\}!important\}/, 'signature lines fit beside the stamp in both combined and department prints');
assert.match(printScript, /\.signature \.stamp\{width:140px!important;height:140px!important;min-width:140px!important;min-height:140px!important/, 'Pastor graduation stamp matches the larger 140px print stamp');
assert.match(printScript, /\.graduation-signatures \.signature > div\[style\*=\"flex:0 0 140px\"\]\{flex:0 0 140px!important\}/, 'Pastor signature layout reserves full width for the larger stamp');
assert.doesNotMatch(printScript, /buildChurchPrintSignatureMarkup\('Pastor'/, 'graduation signature blocks are not signed as the pastor');
assert.match(memberPage, /function printGraduationRoster\(orientation\)[\s\S]*?\.signature \.stamp\{width:140px!important;height:140px!important;min-width:140px!important;min-height:140px!important/, 'Graduands Secretary print uses the larger 140px stamp');
assert.equal((printScript.match(/class="brand report-header"/g) || []).length, 1, 'each graduation print has one church report header');
assert.doesNotMatch(printScript, /department-report-header|department-secretary-header/, 'department sections do not repeat full headers or secretary portraits');
assert.ok(printScript.indexOf('secretaryProfilesMarkup}</div>') < printScript.indexOf('<div class="brand report-header"'), 'secretary portraits print above the single report header');
assert.match(page, /m\.sunday_school_class/, 'graduation roster query loads Sunday School class');
assert.match(page, /data-graduand-class=/, 'graduation rows expose Sunday School class for print grouping');
assert.match(printScript, /sundaySchoolClassOrder[\s\S]*class-ribbon-row[\s\S]*departmentBody/, 'Sunday School graduands print in class-separated ribbon groups');
assert.match(printScript, /class="graduate-photo"/, 'graduand profile photos remain in the print table');
assert.match(printScript, /Scope: <strong class="scope-department">\$\{escapeHtml\(scopeLabel\)\}<\/strong>/, 'Pastor graduation scope emphasizes the selected department');
assert.match(printScript, /\.scope-department\{font-weight:800;color:#000\}/, 'Pastor graduation scope label is bold black');
assert.match(memberPage, /function printGraduationRoster\(orientation\)[\s\S]*?Scope: <strong class="scope-department">\$\{escapeHtml\(departmentName\)\}<\/strong>/, 'Graduands Secretary print includes a bold department scope');
assert.match(memberPage, /\.scope-department\{font-weight:800;color:#000\}/, 'Graduands Secretary department scope label is bold black');
const filterKeys = Array.from(page.matchAll(/data-graduation-department-filter="([^"]+)"/g), match => match[1]);
assert.deepEqual(filterKeys, ['all', 'youths', 'sunday_school'], 'Graduation Info shows All, Youths, and Sunday School filter buttons');
assert.match(page, /data-graduation-kind="roster" data-graduation-department=/, 'graduation roster rows carry their department for filtering');
assert.match(page, /data-graduation-kind="announcement" data-graduation-department=/, 'graduation announcements carry their department for filtering');
assert.match(page, /data-graduation-department-filter="sunday_school"[\s\S]{0,800}onclick="printPastorGraduationRoster\('all'\)"/, 'combined print button sits beside the Sunday School filter');
assert.equal((page.match(/onclick="printPastorGraduationRoster\('all'\)"/g) || []).length, 1, 'combined print action is not duplicated in the roster panel');
assert.match(page, /id="graduationDepartmentPrintButton"[\s\S]{0,250}data-department=""/, 'the roster panel has a department-scoped print button');
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
const departmentPrintButton = { dataset: { department: '' }, style: { display: 'none' } };
const departmentPrintLabel = { textContent: '' };
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
    graduationDepartmentPrintButton: departmentPrintButton,
    graduationDepartmentPrintLabel: departmentPrintLabel,
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
assert.equal(departmentPrintButton.dataset.department, 'youths', 'department print action targets Youths when Youths is open');
assert.equal(departmentPrintButton.style.display, 'inline-flex', 'department print action is visible while a department is open');
assert.equal(departmentPrintLabel.textContent, 'Print Youths', 'department print action names the active department');
buttons[1].click();
assert.deepEqual(records.map(record => record.style.display || ''), ['none', 'none', 'none', 'none', 'none'], 'clicking the active department closes the graduation lists');
assert.equal(rosterWrap.style.display, 'none', 'closing a department hides the graduation roster table');
assert.equal(rosterPanel.style.display, 'none', 'closing a department hides the graduation roster panel');
assert.equal(announcementsPanel.style.display, 'none', 'closing a department hides the graduation announcements panel');
assert.equal(departmentPrintButton.style.display, 'none', 'closing a department hides its print action');
assert.match(status.textContent, /lists are closed/i, 'closing a department explains how to reopen a list');
buttons[2].click();
assert.deepEqual(records.map(record => record.style.display || ''), ['none', 'none', '', '', 'none'], 'Sunday School button filters graduation content to Sunday School');
assert.equal(rosterTotal.textContent, 'Total Sunday School: 1', 'roster total updates for Sunday School');
assert.equal(departmentPrintButton.dataset.department, 'sunday_school', 'department print action targets Sunday School when open');
assert.equal(departmentPrintLabel.textContent, 'Print Sunday School', 'department print action switches to the active department');
assert.equal(rosterPanel.style.display, '', 'choosing a department reopens the graduation roster panel');
assert.equal(announcementsPanel.style.display, '', 'choosing a department reopens the graduation announcements panel');
buttons[0].click();
assert.deepEqual(records.map(record => record.style.display || ''), ['', '', '', '', ''], 'All Departments button restores every department');
assert.equal(rosterTotal.textContent, 'Total Church-wide: 2', 'All Departments restores the original church-wide roster total');
assert.equal(departmentPrintButton.style.display, 'none', 'All Departments uses the combined print action');

console.log('Graduation department filter tests passed.');
