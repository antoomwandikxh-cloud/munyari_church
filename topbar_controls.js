(function () {
    function saveSidebarTarget(link) {
        try {
            sessionStorage.setItem('dashboardSidebarTarget:' + window.location.pathname, link.href);
        } catch (e) {}
    }

    function animateToActiveSidebarTab() {
        var key = 'dashboardSidebarTarget:' + window.location.pathname;
        var targetHref;
        try {
            targetHref = sessionStorage.getItem(key);
            if (targetHref === null) return;
            sessionStorage.removeItem(key);
        } catch (e) {
            return;
        }

        var sidebar = document.querySelector('.sidebar');
        var nav = sidebar && sidebar.querySelector('.sidebar-nav');
        if (!sidebar || !nav) return;

        var links = Array.prototype.slice.call(sidebar.querySelectorAll('.sidebar-link'));
        var activeLink = links.find(function (link) {
            return link.classList.contains('active') && link.href === targetHref;
        }) || links.find(function (link) { return link.classList.contains('active'); });
        if (!activeLink) return;

        sidebar.scrollTop = 0;
        nav.scrollTop = 0;

        function scrollActiveTab() {
            var scroller = activeLink.parentElement;
            while (scroller && scroller !== sidebar) {
                var overflowY = window.getComputedStyle(scroller).overflowY;
                if ((overflowY === 'auto' || overflowY === 'scroll') && scroller.scrollHeight > scroller.clientHeight + 1) break;
                scroller = scroller.parentElement;
            }
            if (!scroller || scroller === sidebar) {
                scroller = sidebar.scrollHeight > sidebar.clientHeight + 1 ? sidebar : nav;
            }

            var box = scroller.getBoundingClientRect();
            var linkBox = activeLink.getBoundingClientRect();
            var targetTop = scroller.scrollTop + linkBox.top - box.top - Math.max(12, (scroller.clientHeight - linkBox.height) / 3);
            targetTop = Math.max(0, Math.min(targetTop, scroller.scrollHeight - scroller.clientHeight));

            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                scroller.scrollTop = targetTop;
                return;
            }

            var startedAt = performance.now();
            var duration = 650;
            function step(now) {
                var progress = Math.min(1, (now - startedAt) / duration);
                var eased = 1 - Math.pow(1 - progress, 3);
                scroller.scrollTop = targetTop * eased;
                if (progress < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        }

        requestAnimationFrame(function () {
            requestAnimationFrame(scrollActiveTab);
        });
    }

    document.addEventListener('click', function (event) {
        if (!(event.target instanceof Element)) return;
        var link = event.target.closest('.sidebar-link[href*="tab="]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        saveSidebarTarget(link);
    }, true);

    animateToActiveSidebarTab();

    var actionScrollStorageKey = 'dashboardActionScrollRestore:v1';
    var pendingActionNavigation = false;

    function dashboardFamily(pathname) {
        var match = pathname.match(/\/(admin|pastor|member)_dashboard\.php$/i);
        return match ? match[1].toLowerCase() : '';
    }

    function actionUrlInfo(rawUrl) {
        try {
            var url = new URL(rawUrl, window.location.href);
            if (url.origin !== window.location.origin) return null;
            var action = (url.searchParams.get('action') || '').toLowerCase();
            if (['remove_role', 'unassign_all', 'redo_roles'].indexOf(action) !== -1) return { skip: true, action: action };
            var actionEndpoint = /\/(admin|pastor|member)_action\.php$/i.test(url.pathname);
            return action || actionEndpoint ? { action: action, url: url } : null;
        } catch (error) {
            return null;
        }
    }

    function saveActionScrollPosition() {
        var family = dashboardFamily(window.location.pathname);
        if (!family) return;
        var mainContent = document.querySelector('.main-content');
        try {
            sessionStorage.setItem(actionScrollStorageKey, JSON.stringify({
                family: family,
                windowY: window.scrollY || document.documentElement.scrollTop || 0,
                contentY: mainContent ? mainContent.scrollTop : 0,
                savedAt: Date.now()
            }));
        } catch (error) {}
    }

    function markActionNavigation(event) {
        if (event.defaultPrevented) return;
        pendingActionNavigation = true;
    }

    document.addEventListener('click', function (event) {
        if (!(event.target instanceof Element) || event.defaultPrevented || event.button !== 0
            || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        var link = event.target.closest('a[href]');
        if (!link || (link.target && link.target.toLowerCase() !== '_self') || link.hasAttribute('download')) return;
        var actionInfo = actionUrlInfo(link.href);
        if (actionInfo && actionInfo.skip) return;
        var confirmation = /\bconfirm\s*\(/i.test(link.getAttribute('onclick') || '');
        if (actionInfo || (confirmation && new URL(link.href, window.location.href).origin === window.location.origin)) {
            markActionNavigation(event);
        }
    });

    document.addEventListener('submit', function (event) {
        if (event.defaultPrevented) return;
        var form = event.target;
        if (!form || (form.target && form.target.toLowerCase() !== '_self')) return;
        var actionInfo = actionUrlInfo(form.action || window.location.href);
        if (actionInfo && actionInfo.skip) return;
        var confirmation = /\bconfirm\s*\(/i.test(form.getAttribute('onsubmit') || '');
        var actionField = form.querySelector('[name="action"]');
        var submitAction = event.submitter && event.submitter.name === 'action' ? event.submitter.value : '';
        if (actionInfo || confirmation || (actionField && actionField.value) || submitAction) {
            markActionNavigation(event);
        }
    });

    function persistActionScrollPosition() {
        if (!pendingActionNavigation) return;
        pendingActionNavigation = false;
        saveActionScrollPosition();
    }
    window.addEventListener('beforeunload', persistActionScrollPosition);
    window.addEventListener('pagehide', persistActionScrollPosition);

    (function restoreActionScrollPosition() {
        var saved;
        try {
            saved = JSON.parse(sessionStorage.getItem(actionScrollStorageKey) || 'null');
            if (saved) sessionStorage.removeItem(actionScrollStorageKey);
        } catch (error) {
            return;
        }
        if (!saved || saved.family !== dashboardFamily(window.location.pathname)
            || Date.now() - saved.savedAt > 10000) return;

        if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
        var mainContent = document.querySelector('.main-content');
        var successNotice = document.querySelector('.flash-success');
        if (successNotice) {
            successNotice.setAttribute('role', 'status');
            successNotice.setAttribute('aria-live', 'polite');
            successNotice.style.position = 'fixed';
            successNotice.style.top = '14px';
            successNotice.style.left = '50%';
            successNotice.style.width = 'min(520px, calc(100vw - 28px))';
            successNotice.style.maxWidth = 'calc(100vw - 28px)';
            successNotice.style.margin = '0';
            successNotice.style.boxSizing = 'border-box';
            successNotice.style.zIndex = '10000';
            successNotice.style.boxShadow = '0 10px 28px rgba(0, 0, 0, .22)';
            successNotice.style.transform = 'translateX(-50%)';
            window.setTimeout(function () {
                if (!successNotice.isConnected) return;
                successNotice.style.transition = 'opacity 220ms ease, transform 220ms ease';
                successNotice.style.opacity = '0';
                successNotice.style.transform = 'translate(-50%, -6px)';
                window.setTimeout(function () { successNotice.remove(); }, 250);
            }, 2750);
        }
        function restore() {
            window.scrollTo(0, Number(saved.windowY) || 0);
            if (mainContent) mainContent.scrollTop = Number(saved.contentY) || 0;
        }
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                window.setTimeout(restore, successNotice ? 420 : 80);
            });
        });
    })();

    function groupTopbarControls() {
        var topbar = document.querySelector('.topbar');
        if (!topbar || topbar.dataset.quickActionsReady === '1') return;

        var themeToggle = topbar.querySelector('#themeToggle');
        var notificationBell = topbar.querySelector('#notifBellWrap');
        var logoutLink = topbar.querySelector('a.icon-btn[href="logout.php"]');
        var profileImage = topbar.querySelector('img[alt="Profile"]');
        var profileLink = profileImage ? profileImage.closest('a') : null;
        var profileBlock = profileLink ? profileLink.parentElement : null;
        var profileCluster = profileBlock ? profileBlock.parentElement : null;

        if (profileCluster) {
            profileCluster.classList.add('topbar-profile-cluster');
            profileBlock.classList.add('topbar-profile');
            var profileMeta = profileBlock.children[1];
            if (profileMeta) profileMeta.classList.add('topbar-profile-meta');

            var divider = profileBlock.nextElementSibling;
            if (divider && divider.tagName === 'DIV') {
                divider.classList.add('topbar-action-divider');
            }
        }

        var actions = document.createElement('div');
        actions.className = 'topbar-quick-actions';
        if (themeToggle) actions.appendChild(themeToggle);
        if (notificationBell) actions.appendChild(notificationBell);
        if (logoutLink) actions.appendChild(logoutLink);
        topbar.appendChild(actions);
        topbar.dataset.quickActionsReady = '1';
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', groupTopbarControls, { once: true });
    } else {
        groupTopbarControls();
    }
})();
