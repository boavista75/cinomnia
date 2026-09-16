/**
 * Cinomnia — live title search suggestions while typing.
 */
(function () {
    'use strict';

    var input = document.getElementById('q');
    if (!input) {
        return;
    }

    var wrap = input.closest('.filters__search');
    var form = input.closest('form');
    if (!wrap) {
        return;
    }

    var apiUrl = wrap.getAttribute('data-search-url') || '';
    if (!apiUrl) {
        return;
    }

    var MIN_CHARS = 2;
    var DEBOUNCE_MS = 220;
    var panel = document.getElementById('search-suggest');
    var debounceTimer = null;
    var abortController = null;
    var requestSeq = 0;
    var cache = {};
    var items = [];
    var activeIndex = -1;

    if (!panel) {
        panel = document.createElement('div');
        panel.id = 'search-suggest';
        wrap.appendChild(panel);
    }

    panel.className = 'search-suggest';
    panel.setAttribute('role', 'listbox');
    panel.setAttribute('aria-label', 'Search suggestions');
    panel.hidden = true;

    input.setAttribute('autocomplete', 'off');
    input.setAttribute('autocorrect', 'off');
    input.setAttribute('autocapitalize', 'off');
    input.setAttribute('spellcheck', 'false');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', 'search-suggest');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-haspopup', 'listbox');

    function currentQuery() {
        return (input.value || '').replace(/\s+/g, ' ').trim();
    }

    function setBusy(busy) {
        wrap.classList.toggle('filters__search--busy', busy);
        input.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    function setOpen(open) {
        panel.hidden = !open;
        input.setAttribute('aria-expanded', open ? 'true' : 'false');
        wrap.classList.toggle('filters__search--open', open);

        if (!open) {
            activeIndex = -1;
            input.removeAttribute('aria-activedescendant');
        }
    }

    function optionId(index) {
        return 'search-suggest-' + index;
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function subtitle(item) {
        var parts = [];
        if (item.year) {
            parts.push(item.year);
        }
        if (item.label) {
            parts.push(item.label);
        }
        if (item.rating && item.rating !== 'N/A' && item.rating !== '0.0') {
            parts.push(item.rating);
        }
        if (item.currently_watching) {
            parts.push('Currently Watching');
        } else if (item.is_watched) {
            parts.push('Watched');
        } else if (item.want_to_watch) {
            parts.push('Want to Watch');
        }
        if (item.user_rating) {
            parts.push('You ' + item.user_rating);
        }
        return parts.join(' · ');
    }

    function setActive(index) {
        var options = panel.querySelectorAll('[role="option"]');
        if (!options.length) {
            activeIndex = -1;
            input.removeAttribute('aria-activedescendant');
            return;
        }

        if (index < -1) {
            index = options.length - 1;
        }
        if (index >= options.length) {
            index = -1;
        }

        activeIndex = index;

        options.forEach(function (option, i) {
            var isActive = i === activeIndex;
            option.classList.toggle('is-active', isActive);
            option.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        if (activeIndex >= 0) {
            var active = options[activeIndex];
            input.setAttribute('aria-activedescendant', active.id);
            if (typeof active.scrollIntoView === 'function') {
                active.scrollIntoView({ block: 'nearest' });
            }
        } else {
            input.removeAttribute('aria-activedescendant');
        }
    }

    function render(query, results) {
        items = Array.isArray(results) ? results : [];
        activeIndex = -1;

        if (!query || query.length < MIN_CHARS) {
            panel.innerHTML = '';
            setOpen(false);
            return;
        }

        var html = '';

        if (items.length === 0) {
            html += '<p class="search-suggest__empty">No titles match “' + escapeHtml(query) + '”</p>';
        } else {
            items.forEach(function (item, index) {
                html += '<a class="search-suggest__item" role="option" id="' + optionId(index) + '"'
                    + ' href="' + escapeHtml(item.url) + '"'
                    + ' data-index="' + index + '"'
                    + ' aria-selected="false">'
                    + '<img class="search-suggest__poster" src="' + escapeHtml(item.poster) + '"'
                    + ' alt="" width="46" height="69" loading="lazy">'
                    + '<span class="search-suggest__body">'
                    + '<span class="search-suggest__title">' + escapeHtml(item.title) + '</span>'
                    + '<span class="search-suggest__meta">' + escapeHtml(subtitle(item)) + '</span>'
                    + '</span>'
                    + '<span class="search-suggest__badge search-suggest__badge--' + escapeHtml(item.type) + '">'
                    + escapeHtml(item.label)
                    + '</span>'
                    + '</a>';
            });
        }

        html += '<button type="submit" class="search-suggest__all" id="search-suggest-all">'
            + 'See all results for “' + escapeHtml(query) + '”'
            + '</button>';

        panel.innerHTML = html;
        setOpen(true);
    }

    function fetchSuggestions(query) {
        if (cache[query]) {
            render(query, cache[query]);
            setBusy(false);
            return;
        }

        if (abortController) {
            abortController.abort();
        }

        abortController = window.AbortController ? new AbortController() : null;
        requestSeq += 1;
        var seq = requestSeq;
        setBusy(true);

        var url = apiUrl + (apiUrl.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(query);
        var options = {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        };

        if (abortController) {
            options.signal = abortController.signal;
        }

        fetch(url, options)
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (payload) {
                if (seq !== requestSeq || currentQuery() !== query) {
                    return;
                }

                var results = payload.data && Array.isArray(payload.data.results)
                    ? payload.data.results
                    : [];

                if (payload.ok && payload.data && payload.data.success) {
                    cache[query] = results;
                }

                render(query, results);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
                if (seq !== requestSeq) {
                    return;
                }
                render(query, []);
            })
            .finally(function () {
                if (seq === requestSeq) {
                    setBusy(false);
                }
            });
    }

    function scheduleSearch() {
        var query = currentQuery();

        if (debounceTimer !== null) {
            window.clearTimeout(debounceTimer);
            debounceTimer = null;
        }

        if (query.length < MIN_CHARS) {
            if (abortController) {
                abortController.abort();
            }
            setBusy(false);
            render(query, []);
            return;
        }

        debounceTimer = window.setTimeout(function () {
            debounceTimer = null;
            fetchSuggestions(query);
        }, DEBOUNCE_MS);
    }

    function goToActive() {
        if (activeIndex < 0 || !items[activeIndex] || !items[activeIndex].url) {
            return false;
        }

        if (window.CinomniaLoading && typeof window.CinomniaLoading.show === 'function') {
            window.CinomniaLoading.show('Opening title… this may take a moment.');
        }
        window.location.href = items[activeIndex].url;
        return true;
    }

    input.addEventListener('input', scheduleSearch);

    input.addEventListener('focus', function () {
        var query = currentQuery();
        if (query.length >= MIN_CHARS && (items.length > 0 || cache[query])) {
            render(query, items.length ? items : cache[query]);
        }
    });

    input.addEventListener('keydown', function (event) {
        var isOpen = !panel.hidden;

        if (event.key === 'ArrowDown') {
            if (!isOpen) {
                return;
            }
            event.preventDefault();
            setActive(activeIndex + 1);
            return;
        }

        if (event.key === 'ArrowUp') {
            if (!isOpen) {
                return;
            }
            event.preventDefault();
            setActive(activeIndex - 1);
            return;
        }

        if (event.key === 'Escape') {
            if (isOpen) {
                event.preventDefault();
                setOpen(false);
            }
            return;
        }

        if (event.key === 'Enter' && isOpen && activeIndex >= 0) {
            event.preventDefault();
            goToActive();
        }
    });

    panel.addEventListener('mousedown', function (event) {
        event.preventDefault();
    });

    panel.addEventListener('mousemove', function (event) {
        var option = event.target.closest ? event.target.closest('[role="option"]') : null;
        if (!option || !panel.contains(option)) {
            return;
        }
        var index = parseInt(option.getAttribute('data-index') || '-1', 10);
        if (index !== activeIndex) {
            setActive(index);
        }
    });

    document.addEventListener('click', function (event) {
        if (!wrap.contains(event.target)) {
            setOpen(false);
        }
    });

    if (form) {
        form.addEventListener('submit', function () {
            setOpen(false);
        });
    }

    if (currentQuery().length >= MIN_CHARS && document.activeElement === input) {
        scheduleSearch();
    }
})();
