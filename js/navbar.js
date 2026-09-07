/**
 * Cinomnia — theme switch + mobile navigation drawer.
 */
(function () {
    'use strict';

    var THEME_KEY = 'cinomnia-theme';

    function systemTheme() {
        return window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
    }

    function storedTheme() {
        try {
            var value = localStorage.getItem(THEME_KEY);
            return value === 'light' || value === 'dark' ? value : null;
        } catch (err) {
            return null;
        }
    }

    function currentTheme() {
        var attr = document.documentElement.getAttribute('data-theme');
        if (attr === 'light' || attr === 'dark') {
            return attr;
        }
        return storedTheme() || systemTheme();
    }

    function syncToggle(theme) {
        var toggle = document.getElementById('theme-toggle');
        if (!toggle) {
            return;
        }

        var isDark = theme === 'dark';
        toggle.setAttribute('aria-checked', isDark ? 'true' : 'false');
        toggle.setAttribute('aria-label', isDark ? 'Switch to light theme' : 'Switch to dark theme');
    }

    function applyTheme(theme, persist) {
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.style.colorScheme = theme;
        syncToggle(theme);

        if (persist) {
            try {
                localStorage.setItem(THEME_KEY, theme);
            } catch (err) {
                /* private mode / blocked storage */
            }
        }
    }

    applyTheme(currentTheme(), false);

    var themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            applyTheme(currentTheme() === 'dark' ? 'light' : 'dark', true);
        });
    }

    var media = window.matchMedia('(prefers-color-scheme: light)');
    var onSystemChange = function (event) {
        if (storedTheme()) {
            return;
        }
        applyTheme(event.matches ? 'light' : 'dark', false);
    };

    if (typeof media.addEventListener === 'function') {
        media.addEventListener('change', onSystemChange);
    } else if (typeof media.addListener === 'function') {
        media.addListener(onSystemChange);
    }

    var navbar = document.getElementById('site-navbar');
    if (!navbar) {
        return;
    }

    var toggle = document.getElementById('navbar-toggle');
    var menu = document.getElementById('navbar-menu');
    var backdrop = document.getElementById('navbar-backdrop');

    if (!toggle || !menu) {
        return;
    }

    function setOpen(open) {
        navbar.classList.toggle('site-nav--open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
        document.body.classList.toggle('navbar-open', open);

        if (backdrop) {
            if (open) {
                backdrop.hidden = false;
                backdrop.setAttribute('aria-hidden', 'false');
            } else {
                backdrop.hidden = true;
                backdrop.setAttribute('aria-hidden', 'true');
            }
        }
    }

    function isOpen() {
        return navbar.classList.contains('site-nav--open');
    }

    setOpen(false);

    toggle.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        setOpen(!isOpen());
    });

    if (backdrop) {
        backdrop.addEventListener('click', function () {
            setOpen(false);
        });
    }

    menu.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.matchMedia('(max-width: 900px)').matches) {
                setOpen(false);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && isOpen()) {
            setOpen(false);
            toggle.focus();
        }
    });

    window.addEventListener('resize', function () {
        if (window.matchMedia('(min-width: 901px)').matches && isOpen()) {
            setOpen(false);
        }
    });
})();
