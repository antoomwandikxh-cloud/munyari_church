const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const page = fs.readFileSync('member_dashboard.php', 'utf8');
const match = page.match(/<script>\s*\(\(\) => \{\s*const picker = document\.getElementById\('graduationMemberPicker'\);([\s\S]*?)<\/script>/);
assert.ok(match, 'Graduation roster picker script exists');

function element(extra = {}) {
    return {
        hidden: false,
        style: {},
        dataset: {},
        attributes: {},
        listeners: {},
        addEventListener(name, callback) { this.listeners[name] = callback; },
        setAttribute(name, value) { this.attributes[name] = value; },
        getAttribute(name) { return this.attributes[name] || null; },
        ...extra
    };
}

const search = element({ value: '', focus() {} });
const menu = element({ hidden: true });
const photo = element({ src: '', alt: '' });
const label = element({ textContent: '' });
const searchError = element({ textContent: '' });
const noResults = element({ hidden: true });
const options = ['Rosa Mumbi', 'David Muethia'].map((name, index) => {
    const profilePhoto = element({ src: `uploads/member-${index}.png`, currentSrc: `https://example.test/member-${index}.png` });
    return element({
        dataset: { memberName: name },
        style: { display: 'flex' },
        querySelector() { return profilePhoto; },
        profilePhoto
    });
});
const toggle = element({
    querySelector(selector) { return selector === '[data-selected-photo]' ? photo : label; }
});
const form = element();
const picker = element({
    querySelectorAll() { return options; },
    contains() { return true; }
});
const elements = {
    graduationMemberPicker: picker,
    graduationRosterForm: form,
    graduationMemberToggle: toggle,
    graduationMemberMenu: menu,
    graduationMemberSearch: search,
    graduationMemberId: element({ value: '' }),
    graduationMemberSearchError: searchError,
    graduationMemberNoResults: noResults
};
const document = {
    getElementById(id) { return elements[id] || null; },
    addEventListener() {}
};
const viewedPhotos = [];

vm.runInNewContext(`(() => { const picker = document.getElementById('graduationMemberPicker');${match[1]}`, {
    document,
    viewProfileImage(src) { viewedPhotos.push(src); },
    requestAnimationFrame(callback) { callback(); }
});

search.value = 'r';
search.listeners.input();
assert.equal(menu.hidden, false, 'typing immediately opens the member list');
assert.equal(options[0].style.display, 'flex', 'first-keypress match remains visible');
assert.equal(options[1].style.display, 'none', 'non-matching option is hidden despite inline flex styling');
let photoClickPrevented = false;
let photoClickStopped = false;
options[0].profilePhoto.listeners.click({
    preventDefault() { photoClickPrevented = true; },
    stopPropagation() { photoClickStopped = true; }
});
assert.equal(viewedPhotos[0], 'https://example.test/member-0.png', 'clicking a candidate profile photo opens the full-size preview');
assert.equal(photoClickPrevented && photoClickStopped, true, 'photo preview does not select the member at the same time');

search.value = 'r2';
search.listeners.input();
assert.equal(search.value, 'r', 'numbers are removed from the query');
assert.equal(searchError.hidden, false, 'invalid characters show the validation message');
assert.equal(options[0].style.display, 'flex', 'filter remains active after removing invalid characters');

search.value = 'not found';
search.listeners.input();
assert.equal(noResults.hidden, false, 'empty result state is shown when no names match');

console.log('Graduation member search tests passed.');
