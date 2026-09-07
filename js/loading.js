/**
 * Cinomnia — global loading overlay for slow page loads and in-app waits.
 */
(function () {
    'use strict';

    var root   = document.documentElement;
    var loader = document.getElementById('app-loader');
    var textEl = document.getElementById('app-loader-text');
    var defaultText = 'Loading… this may take a moment.';
    var hideTimer = null;
    var showCount = 0;

    function setText(message) {
        if (!textEl) return;
        textEl.textContent = message || defaultText;
    }

    function show(message) {
        if (hideTimer !== null) {
            window.clearTimeout(hideTimer);
            hideTimer = null;
        }

        showCount += 1;
        setText(message);
        root.classList.remove('app-ready');

        if (loader) {
            loader.setAttribute('aria-busy', 'true');
        }
    }

    function hide() {
        showCount = Math.max(0, showCount - 1);
        if (showCount > 0) return;

        setText(defaultText);
        root.classList.add('app-ready');

        if (loader) {
            loader.setAttribute('aria-busy', 'false');
        }
    }

    function hideSoon() {
        if (hideTimer !== null) {
            window.clearTimeout(hideTimer);
        }
        hideTimer = window.setTimeout(function () {
            hideTimer = null;
            if (showCount > 0) {
                return;
            }
            showCount = 1;
            hide();
        }, 80);
    }

    function isModifiedClick(event) {
        return event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0;
    }

    function isInternalNavigation(anchor) {
        if (!anchor || !anchor.href) return false;
        if (anchor.target && anchor.target !== '_self') return false;
        if (anchor.hasAttribute('download')) return false;
        if (anchor.getAttribute('href') === '' || (anchor.getAttribute('href') || '').charAt(0) === '#') {
            return false;
        }

        try {
            var url = new URL(anchor.href, window.location.href);
            if (url.origin !== window.location.origin) return false;
            if (url.pathname === window.location.pathname && url.search === window.location.search) {
                return false;
            }
            return true;
        } catch (err) {
            return false;
        }
    }

    function messageForUrl(href) {
        try {
            var path = new URL(href, window.location.href).pathname;
            if (path.indexOf('details.php') !== -1) return 'Opening title… this may take a moment.';
            if (path.indexOf('lists.php') !== -1) return 'Loading your lists…';
            if (path.indexOf('login.php') !== -1) return 'Please wait…';
            return 'Loading… this may take a moment.';
        } catch (err) {
            return defaultText;
        }
    }

    document.addEventListener('click', function (event) {
        if (isModifiedClick(event) || event.defaultPrevented) return;

        var anchor = event.target && event.target.closest ? event.target.closest('a[href]') : null;
        if (!isInternalNavigation(anchor)) return;

        show(messageForUrl(anchor.href));
    }, true);

    document.addEventListener('submit', function (event) {
        if (event.defaultPrevented) return;
        show('Please wait… this may take a moment.');
    }, true);

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            showCount = 1;
            hide();
        }
    });

    window.CinomniaLoading = {
        show: show,
        hide: hide,
        around: function (promise, delayMs) {
            var delay = delayMs == null ? 450 : delayMs;
            var started = false;
            var timer = window.setTimeout(function () {
                started = true;
                show('Still working… please wait.');
            }, delay);

            return Promise.resolve(promise).finally(function () {
                window.clearTimeout(timer);
                if (started) {
                    hide();
                }
            });
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hideSoon);
    } else {
        hideSoon();
    }
})();
