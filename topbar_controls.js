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
