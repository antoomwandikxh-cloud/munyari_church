const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('topbar_controls.js', 'utf8');
const storageKey = 'dashboardActionScrollRestore:v1';

class FakeElement {
    constructor(attributes = {}) {
        this.attributes = attributes;
        this.href = attributes.href || '';
        this.target = attributes.target || '';
        this.value = attributes.value || '';
        this.name = attributes.name || '';
    }
    closest(selector) {
        return selector === 'a[href]' && this.href ? this : null;
    }
    getAttribute(name) {
        return this.attributes[name] || null;
    }
    hasAttribute(name) {
        return Object.hasOwn(this.attributes, name);
    }
}

function bootDashboard({ storage = new Map(), pathname = '/munyari_church/admin_dashboard.php', windowY = 0, contentY = 0 } = {}) {
    const documentHandlers = {};
    const windowHandlers = {};
    const mainContent = { scrollTop: contentY };
    const location = {
        pathname,
        origin: 'http://localhost',
        href: 'http://localhost' + pathname + '?tab=manage_sunday_school'
    };
    const window = {
        location,
        origin: location.origin,
        scrollY: windowY,
        addEventListener(name, callback) { windowHandlers[name] = callback; },
        scrollTo(_x, y) { this.scrollY = y; },
        setTimeout(callback) { callback(); }
    };
    const document = {
        readyState: 'loading',
        documentElement: { scrollTop: 0 },
        addEventListener(name, callback) { documentHandlers[name] = callback; },
        querySelector(selector) {
            if (selector === '.main-content') return mainContent;
            return null;
        }
    };
    const sessionStorage = {
        getItem(key) { return storage.has(key) ? storage.get(key) : null; },
        setItem(key, value) { storage.set(key, value); },
        removeItem(key) { storage.delete(key); }
    };
    vm.runInNewContext(source, {
        Element: FakeElement,
        URL,
        Date,
        document,
        history: { scrollRestoration: 'auto' },
        requestAnimationFrame(callback) { callback(); },
        sessionStorage,
        window
    });
    return { documentHandlers, windowHandlers, mainContent, sessionStorage, window };
}

const actionPage = bootDashboard({ windowY: 615, contentY: 1270 });
const confirmedAction = new FakeElement({
    href: 'http://localhost/munyari_church/admin_dashboard.php?tab=manage_sunday_school&action=approve_ss_class_request',
    onclick: 'return confirm("Approve this request?")'
});
actionPage.documentHandlers.click({
    target: confirmedAction,
    defaultPrevented: false,
    button: 0,
    metaKey: false,
    ctrlKey: false,
    shiftKey: false,
    altKey: false
});
actionPage.windowHandlers.beforeunload();
assert.deepEqual(JSON.parse(actionPage.sessionStorage.getItem(storageKey)), {
    family: 'admin',
    windowY: 615,
    contentY: 1270,
    savedAt: JSON.parse(actionPage.sessionStorage.getItem(storageKey)).savedAt
});

const formAction = bootDashboard({ windowY: 404, contentY: 1808 });
formAction.documentHandlers.submit({
    target: {
        action: 'http://localhost/munyari_church/member_action.php?action=delete_event',
        target: '',
        getAttribute() { return ''; },
        querySelector() { return null; }
    },
    defaultPrevented: false,
    submitter: null
});
formAction.windowHandlers.pagehide();
assert.equal(JSON.parse(formAction.sessionStorage.getItem(storageKey)).contentY, 1808, 'POST action forms preserve inner dashboard scroll');

const roleRemoval = bootDashboard({ storage: new Map(), windowY: 500, contentY: 900 });
roleRemoval.documentHandlers.click({
    target: new FakeElement({ href: 'http://localhost/munyari_church/pastor_action.php?action=remove_role&id=42', onclick: 'return confirm("Remove role?")' }),
    defaultPrevented: false,
    button: 0,
    metaKey: false,
    ctrlKey: false,
    shiftKey: false,
    altKey: false
});
roleRemoval.windowHandlers.beforeunload();
assert.equal(roleRemoval.sessionStorage.getItem(storageKey), null, 'custom role-removal scroll flow is not overridden');

const cancelledStorage = new Map();
const cancelledAction = bootDashboard({ storage: cancelledStorage, windowY: 200, contentY: 300 });
cancelledAction.documentHandlers.click({
    target: new FakeElement({ href: 'http://localhost/munyari_church/admin_dashboard.php?tab=members', onclick: 'return confirm("Delete?")' }),
    defaultPrevented: true,
    button: 0,
    metaKey: false,
    ctrlKey: false,
    shiftKey: false,
    altKey: false
});
cancelledAction.windowHandlers.beforeunload();
assert.equal(cancelledStorage.has(storageKey), false, 'cancelled confirmations do not save a stale scroll position');

const restored = bootDashboard({
    storage: new Map([[storageKey, JSON.stringify({ family: 'admin', windowY: 615, contentY: 1270, savedAt: Date.now() })]])
});
assert.equal(restored.window.scrollY, 615, 'window scroll is restored after the dashboard action');
assert.equal(restored.mainContent.scrollTop, 1270, 'dashboard panel scroll is restored after the dashboard action');

for (const dashboard of ['admin_dashboard.php', 'pastor_dashboard.php', 'member_dashboard.php']) {
    const dashboardSource = fs.readFileSync(dashboard, 'utf8');
    assert.match(dashboardSource, /topbar_controls\.js\?v=20261020-action-scroll-1/, `${dashboard} loads the shared action-scroll helper`);
}

console.log('Dashboard action scroll tests passed.');
