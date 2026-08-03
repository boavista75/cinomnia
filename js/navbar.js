/**
 * Cinomnia — site navigation (mobile drawer toggle).
 */
(function () {
    'use strict';

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
            backdrop.hidden = !open;
        }
    }

    function isOpen() {
        return navbar.classList.contains('site-nav--open');
    }

    toggle.addEventListener('click', function () {
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
