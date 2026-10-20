(function () {
    function rememberSidebarPosition() {
        var sidebar = document.querySelector('.sidebar');
        var nav = sidebar && sidebar.querySelector('.sidebar-nav');
        if (!sidebar || !nav) return;

        try {
            sessionStorage.setItem('dashboardSidebarPosition:' + window.location.pathname, JSON.stringify({
                sidebarTop: sidebar.scrollTop,
                navTop: nav.scrollTop
            }));
        } catch (e) {}
    }

    function restoreSidebarPosition() {
        var key = 'dashboardSidebarPosition:' + window.location.pathname;
        var saved;
        try {
            saved = sessionStorage.getItem(key);
            if (saved === null) return;
            sessionStorage.removeItem(key);
            saved = JSON.parse(saved);
        } catch (e) {
            return;
        }

        var sidebar = document.querySelector('.sidebar');
        var nav = sidebar && sidebar.querySelector('.sidebar-nav');
        if (!sidebar || !nav || !saved) return;

        requestAnimationFrame(function () {
            sidebar.scrollTop = Number(saved.sidebarTop) || 0;
            nav.scrollTop = Number(saved.navTop) || 0;
        });
    }

    document.addEventListener('click', function (event) {
        if (!(event.target instanceof Element)) return;
        var link = event.target.closest('.sidebar-link[href*="tab="]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        rememberSidebarPosition();
    }, true);

    restoreSidebarPosition();

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
