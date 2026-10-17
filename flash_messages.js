(function () {
    const dismissAfter = 5000;
    const fadeDuration = 250;

    function scheduleDismissal(element) {
        if (!(element instanceof HTMLElement) || element.dataset.flashDismissScheduled) return;
        element.dataset.flashDismissScheduled = 'true';
        window.setTimeout(() => {
            if (!element.isConnected) return;
            element.style.transition = `opacity ${fadeDuration}ms ease, transform ${fadeDuration}ms ease`;
            element.style.opacity = '0';
            element.style.transform = 'translateY(-6px)';
            window.setTimeout(() => element.remove(), fadeDuration);
        }, dismissAfter);
    }

    function scan(node) {
        if (!(node instanceof Element)) return;
        if (node.matches('.flash-success')) scheduleDismissal(node);
        node.querySelectorAll('.flash-success').forEach(scheduleDismissal);
    }

    document.querySelectorAll('.flash-success').forEach(scheduleDismissal);
    new MutationObserver(records => {
        records.forEach(record => record.addedNodes.forEach(scan));
    }).observe(document.body, { childList: true, subtree: true });

    const url = new URL(window.location.href);
    const hasFlashMessage = document.querySelector('.flash-success');
    const flashParams = ['success', 'approved', 'deactivated', 'removed'];
    if (hasFlashMessage && flashParams.some(param => url.searchParams.has(param))) {
        flashParams.forEach(param => url.searchParams.delete(param));
        window.history.replaceState({}, document.title, url.pathname + url.search + url.hash);
    }
})();
