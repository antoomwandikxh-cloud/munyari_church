(function () {
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
