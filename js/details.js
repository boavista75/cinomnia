/**
 * Cinomnia — Details page (trailer, rating, watched, want to watch, custom lists)
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------ */
    /* Shared modal helpers                                                */
    /* ------------------------------------------------------------------ */

    function openModal(modal) {
        if (!modal) return;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('modal-open');
        document.body.classList.add('modal-open');
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');

        if (!document.querySelector('.modal:not([hidden])')) {
            document.documentElement.classList.remove('modal-open');
            document.body.classList.remove('modal-open');
        }
    }

    function bindModalClose(modal, name) {
        if (!modal) return;

        modal.querySelectorAll('[data-close-modal="' + name + '"]').forEach(function (el) {
            el.addEventListener('click', function () {
                closeModal(modal);
            });
        });
    }

    function trackSlow(promise) {
        if (window.CinomniaLoading && typeof window.CinomniaLoading.around === 'function') {
            return window.CinomniaLoading.around(promise);
        }

        return promise;
    }

    /* ------------------------------------------------------------------ */
    /* Trailer modal                                                       */
    /* ------------------------------------------------------------------ */

    var trailerModal = document.getElementById('trailer-modal');
    var playBtn      = document.getElementById('play-trailer-btn');
    var trailerFrame = document.getElementById('trailer-iframe');

    function stopTrailer() {
        trailerFrame = document.getElementById('trailer-iframe');
        if (!trailerFrame) return;

        try {
            trailerFrame.contentWindow.postMessage(JSON.stringify({
                event: 'command',
                func: 'stopVideo',
                args: []
            }), '*');
            trailerFrame.contentWindow.postMessage(JSON.stringify({
                event: 'command',
                func: 'pauseVideo',
                args: []
            }), '*');
        } catch (err) {
            /* YouTube frame may already be gone. */
        }

        trailerFrame.src = 'about:blank';

        var blank = trailerFrame.cloneNode(false);
        blank.removeAttribute('src');
        blank.src = 'about:blank';
        if (trailerFrame.parentNode) {
            trailerFrame.parentNode.replaceChild(blank, trailerFrame);
        }
        trailerFrame = blank;
    }

    function closeTrailer() {
        stopTrailer();
        closeModal(trailerModal);
    }

    if (trailerModal && trailerFrame) {
        trailerModal.querySelectorAll('[data-close-modal="trailer"]').forEach(function (el) {
            el.addEventListener('click', closeTrailer);
        });

        if (playBtn) {
            playBtn.addEventListener('click', function () {
                var key = playBtn.getAttribute('data-trailer-key');
                if (!key) return;

                trailerFrame = document.getElementById('trailer-iframe');
                if (!trailerFrame) return;

                trailerFrame.src = 'https://www.youtube.com/embed/' + encodeURIComponent(key)
                    + '?autoplay=1&rel=0&modestbranding=1&playsinline=1&enablejsapi=1&origin='
                    + encodeURIComponent(window.location.origin);
                openModal(trailerModal);
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (trailerModal && !trailerModal.hidden) {
                    closeTrailer();
                }
                var listModal = document.getElementById('list-modal');
                if (listModal && !listModal.hidden) {
                    closeModal(listModal);
                }
            }
        });

        window.addEventListener('pagehide', stopTrailer);
    }

    /* ------------------------------------------------------------------ */
    /* User panel (rating, watched, lists)                                 */
    /* ------------------------------------------------------------------ */

    var panel = document.getElementById('detail-user-panel');
    var configEl = document.getElementById('detail-user-config');
    var csrfInput = document.getElementById('detail-csrf-token');

    if (panel && configEl) {
    var config;

    try {
        config = JSON.parse(configEl.textContent || '{}');
    } catch (err) {
        config = null;
    }

    if (config) {
    function getCsrfToken() {
        return csrfInput && csrfInput.value ? csrfInput.value : '';
    }

    var state = {
        userRating: config.userRating || null,
        isWatched: !!config.isWatched,
        isWantToWatch: !!config.isWantToWatch,
        isCurrentlyWatching: !!config.isCurrentlyWatching,
        lists: config.lists || [],
        listIdsWithItem: config.listIdsWithItem || []
    };

    var watchedBtn    = document.getElementById('watched-toggle-btn');
    var wantToWatchBtn = document.getElementById('want-to-watch-btn');
    var currentlyWatchingBtn = document.getElementById('currently-watching-btn');
    var addToListBtn  = document.getElementById('add-to-list-btn');
    var scorePicker   = document.getElementById('score-picker');
    var ratingValue   = document.getElementById('user-rating-value');
    var ratingWord    = document.getElementById('user-rating-word');
    var scoreDisplay  = document.querySelector('.detail-score__display');
    var panelToast    = document.getElementById('user-panel-toast');
    var listModal     = document.getElementById('list-modal');
    var listEmpty     = document.getElementById('list-modal-empty');
    var listExisting  = document.getElementById('list-modal-existing');
    var listChecklist = document.getElementById('list-modal-checklist');
    var listFeedback  = document.getElementById('list-modal-feedback');
    var listSaveBtn   = document.getElementById('list-modal-save-btn');
    var createFirstForm = document.getElementById('list-modal-create-first-form');
    var createNewForm   = document.getElementById('list-modal-create-form');

    function showToast(el, message, isError) {
        if (!el) return;

        el.textContent = message;
        el.classList.toggle('is-error', !!isError);
        el.classList.toggle('is-success', !isError);
        el.removeAttribute('hidden');
        el.setAttribute('aria-hidden', 'false');

        window.clearTimeout(el._toastTimer);
        window.clearTimeout(el._toastHideTimer);
        el.classList.remove('is-visible');
        void el.offsetWidth;
        el.classList.add('is-visible');

        el._toastTimer = window.setTimeout(function () {
            el.classList.remove('is-visible');
            el._toastHideTimer = window.setTimeout(function () {
                el.setAttribute('aria-hidden', 'true');
            }, 300);
        }, 2400);
    }

    function apiRequest(action, extra) {
        var csrfToken = getCsrfToken();

        if (!csrfToken) {
            return Promise.resolve({
                success: false,
                message: 'Missing security token. Please refresh the page.'
            });
        }

        var body = new URLSearchParams();
        body.set('action', action);
        body.set('csrf_token', csrfToken);
        body.set('tmdb_id', String(config.tmdbId));
        body.set('media_type', config.mediaType);
        body.set('title', config.title || '');
        body.set('poster_path', config.posterPath || '');

        if (extra) {
            Object.keys(extra).forEach(function (key) {
                body.set(key, extra[key]);
            });
        }

        return trackSlow(fetch(config.apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-CSRF-Token': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString(),
            credentials: 'include'
        }).then(function (res) {
            return res.json();
        }));
    }

    function scoreWord(rating) {
        if (!rating) return 'Not rated';
        if (rating <= 3) return 'Poor';
        if (rating <= 5) return 'Okay';
        if (rating <= 7) return 'Good';
        if (rating <= 9) return 'Great';
        return 'Excellent';
    }

    function scoreTone(rating) {
        if (!rating) return 'empty';
        if (rating <= 3) return 'low';
        if (rating <= 5) return 'mid';
        if (rating <= 7) return 'good';
        if (rating <= 9) return 'great';
        return 'top';
    }

    function renderScorePicker(rating, preview) {
        if (!scorePicker) return;

        scorePicker.querySelectorAll('.score-picker__btn').forEach(function (btn) {
            var value = parseInt(btn.getAttribute('data-value'), 10);
            var isActive = rating !== null && value === rating;
            var isPreview = preview !== undefined && value === preview && value !== rating;

            btn.classList.toggle('is-active', isActive);
            btn.classList.toggle('is-preview', isPreview);
            btn.setAttribute('aria-checked', isActive ? 'true' : 'false');
        });
    }

    function updateRatingDisplay(rating) {
        if (ratingValue) {
            ratingValue.textContent = rating ? String(rating) : '—';
        }
        if (ratingWord) {
            ratingWord.textContent = scoreWord(rating);
        }
        if (scoreDisplay) {
            scoreDisplay.className = 'detail-score__display detail-score__display--' + scoreTone(rating);
        }
        renderScorePicker(rating);
        updatePosterOverlay();
    }

    function applyLibraryState(data) {
        if (Object.prototype.hasOwnProperty.call(data, 'is_watched')) {
            state.isWatched = !!data.is_watched;
        }
        if (Object.prototype.hasOwnProperty.call(data, 'in_want_to_watch')) {
            state.isWantToWatch = !!data.in_want_to_watch;
        }
        if (Object.prototype.hasOwnProperty.call(data, 'in_currently_watching')) {
            state.isCurrentlyWatching = !!data.in_currently_watching;
        }

        updateWatchedDisplay(state.isWatched);
        updateWantToWatchDisplay(state.isWantToWatch);
        updateCurrentlyWatchingDisplay(state.isCurrentlyWatching);
        updatePosterOverlay();
    }

    function updateWatchedDisplay(isWatched) {
        if (watchedBtn) {
            watchedBtn.classList.toggle('is-active', isWatched);
            watchedBtn.classList.toggle('is-watched', isWatched);
            watchedBtn.setAttribute('aria-pressed', isWatched ? 'true' : 'false');
        }
    }

    function updateWantToWatchDisplay(inWantToWatch) {
        if (wantToWatchBtn) {
            wantToWatchBtn.classList.toggle('is-active', inWantToWatch);
            wantToWatchBtn.classList.toggle('is-want', inWantToWatch);
            wantToWatchBtn.setAttribute('aria-pressed', inWantToWatch ? 'true' : 'false');
        }
    }

    function updateCurrentlyWatchingDisplay(isWatching) {
        if (!currentlyWatchingBtn) {
            return;
        }

        currentlyWatchingBtn.classList.toggle('is-active', isWatching);
        currentlyWatchingBtn.classList.toggle('is-watching', isWatching);
        currentlyWatchingBtn.setAttribute('aria-pressed', isWatching ? 'true' : 'false');
    }

    function updatePosterOverlay() {
        var wrap = document.querySelector('.detail-hero__poster-wrap');
        if (!wrap) {
            return;
        }

        var overlay = wrap.querySelector('.library-overlay');
        var rating = state.userRating ? parseInt(state.userRating, 10) : null;
        var watching = !!state.isCurrentlyWatching;
        var watched = !!state.isWatched && !watching;
        var want = !!state.isWantToWatch && !watched && !watching;

        if (!rating && !watched && !want && !watching) {
            if (overlay) {
                overlay.remove();
            }
            return;
        }

        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'library-overlay';
            wrap.appendChild(overlay);
        }

        var html = '';
        if (watching || watched || want) {
            var statusClass = watching ? 'watching' : (watched ? 'watched' : 'want');
            var statusLabel = watching ? 'Currently' : (watched ? 'Watched' : 'Want');
            html += '<span class="library-overlay__status library-overlay__status--'
                + statusClass + '">' + statusLabel + '</span>';
        } else {
            html += '<span></span>';
        }

        if (rating) {
            html += '<span class="library-overlay__score" aria-label="Your score: '
                + rating + ' out of 10">'
                + '<svg class="library-overlay__score-star" viewBox="0 0 24 24" aria-hidden="true">'
                + '<path fill="currentColor" d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>'
                + '</svg>'
                + rating
                + '</span>';
        }

        overlay.innerHTML = html;
    }

    function renderListChecklist() {
        if (!listChecklist) return;

        listChecklist.innerHTML = '';

        state.lists.forEach(function (list) {
            var li = document.createElement('li');
            li.className = 'list-modal__checklist-item';

            var inList = state.listIdsWithItem.indexOf(list.id) !== -1;
            var id = 'list-check-' + list.id;

            li.innerHTML =
                '<label class="list-modal__check-label' + (inList ? ' is-disabled' : '') + '" for="' + id + '">' +
                    '<input type="checkbox" class="list-modal__checkbox" id="' + id + '" ' +
                        'value="' + list.id + '"' + (inList ? ' disabled checked' : '') + '>' +
                    '<span class="list-modal__check-box" aria-hidden="true"></span>' +
                    '<span class="list-modal__check-text">' + escapeHtml(list.name) + '</span>' +
                    (inList ? '<span class="list-modal__check-badge">Added</span>' : '') +
                '</label>';

            listChecklist.appendChild(li);
        });
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function openListModal() {
        if (!listModal) return;

        var hasLists = state.lists.length > 0;

        if (listEmpty) {
            listEmpty.hidden = hasLists;
        }
        if (listExisting) {
            listExisting.hidden = !hasLists;
        }
        if (listFeedback) {
            listFeedback.hidden = true;
        }

        if (hasLists) {
            renderListChecklist();
        }

        openModal(listModal);
    }

    function refreshListsFromServer() {
        return apiRequest('get_interaction').then(function (data) {
            if (!data.success) return;

            if (data.lists) {
                state.lists = data.lists;
            }
            if (data.list_ids) {
                state.listIdsWithItem = data.list_ids;
            }
        });
    }

    /* Numeric score picker */
    if (scorePicker) {
        scorePicker.querySelectorAll('.score-picker__btn').forEach(function (btn) {
            btn.addEventListener('mouseenter', function () {
                renderScorePicker(state.userRating, parseInt(btn.getAttribute('data-value'), 10));
            });

            btn.addEventListener('mouseleave', function () {
                renderScorePicker(state.userRating);
            });

            btn.addEventListener('click', function () {
                var value = parseInt(btn.getAttribute('data-value'), 10);
                var clearing = state.userRating === value;
                var action = clearing ? 'clear_rating' : 'set_rating';
                var extra = clearing ? {} : { rating: String(value) };

                scorePicker.classList.add('is-saving');

                apiRequest(action, extra).then(function (data) {
                    scorePicker.classList.remove('is-saving');

                    if (data.success) {
                        state.userRating = clearing ? null : value;
                        updateRatingDisplay(state.userRating);
                        showToast(panelToast, data.message, false);
                    } else {
                        showToast(panelToast, data.message || 'Could not save rating.', true);
                    }
                }).catch(function () {
                    scorePicker.classList.remove('is-saving');
                    showToast(panelToast, 'Network error. Please try again.', true);
                });
            });
        });
    }

    /* Watched toggle */
    if (watchedBtn) {
        watchedBtn.addEventListener('click', function () {
            watchedBtn.disabled = true;

            apiRequest('toggle_watched').then(function (data) {
                watchedBtn.disabled = false;

                if (data.success) {
                    applyLibraryState(data);
                    showToast(panelToast, data.message, false);
                } else {
                    showToast(panelToast, data.message || 'Could not update watched status.', true);
                }
            }).catch(function () {
                watchedBtn.disabled = false;
                showToast(panelToast, 'Network error. Please try again.', true);
            });
        });
    }

    /* Want to Watch toggle */
    if (wantToWatchBtn) {
        wantToWatchBtn.addEventListener('click', function () {
            wantToWatchBtn.disabled = true;

            apiRequest('toggle_want_to_watch').then(function (data) {
                wantToWatchBtn.disabled = false;

                if (data.success) {
                    applyLibraryState(data);
                    showToast(panelToast, data.message, false);
                } else {
                    showToast(panelToast, data.message || 'Could not update Want to Watch.', true);
                }
            }).catch(function () {
                wantToWatchBtn.disabled = false;
                showToast(panelToast, 'Network error. Please try again.', true);
            });
        });
    }

    /* Currently Watching toggle (TV only) */
    if (currentlyWatchingBtn) {
        currentlyWatchingBtn.addEventListener('click', function () {
            currentlyWatchingBtn.disabled = true;

            apiRequest('toggle_currently_watching').then(function (data) {
                currentlyWatchingBtn.disabled = false;

                if (data.success) {
                    applyLibraryState(data);
                    showToast(panelToast, data.message, false);
                } else {
                    showToast(panelToast, data.message || 'Could not update Currently Watching.', true);
                }
            }).catch(function () {
                currentlyWatchingBtn.disabled = false;
                showToast(panelToast, 'Network error. Please try again.', true);
            });
        });
    }

    /* Add to list modal */
    if (addToListBtn) {
        addToListBtn.addEventListener('click', openListModal);
    }

    if (listModal) {
        bindModalClose(listModal, 'list');
    }

    function handleCreateList(name, addAfterCreate) {
        if (!name.trim()) return Promise.resolve();

        if (listFeedback) {
            listFeedback.hidden = false;
            listFeedback.textContent = 'Creating list…';
            listFeedback.className = 'list-modal__feedback';
        }

        return apiRequest('create_list_and_add', { name: name.trim() }).then(function (data) {
            if (!data.success) {
                if (listFeedback) {
                    listFeedback.textContent = data.message || 'Could not create list.';
                    listFeedback.classList.add('is-error');
                }
                return;
            }

            return refreshListsFromServer().then(function () {
                if (listFeedback) {
                    listFeedback.textContent = data.message;
                    listFeedback.classList.add('is-success');
                }

                showToast(panelToast, data.message, false);

                if (addAfterCreate) {
                    window.setTimeout(function () {
                        closeModal(listModal);
                    }, 600);
                } else {
                    openListModal();
                }
            });
        });
    }

    if (createFirstForm) {
        createFirstForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var input = document.getElementById('list-modal-first-name');
            handleCreateList(input ? input.value : '', true);
        });
    }

    if (createNewForm) {
        createNewForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var input = document.getElementById('list-modal-new-name');
            handleCreateList(input ? input.value : '', true).then(function () {
                if (input) input.value = '';
            });
        });
    }

    if (listSaveBtn) {
        listSaveBtn.addEventListener('click', function () {
            var selected = [];

            if (listChecklist) {
                listChecklist.querySelectorAll('.list-modal__checkbox:checked:not(:disabled)').forEach(function (cb) {
                    selected.push(cb.value);
                });
            }

            if (selected.length === 0) {
                if (listFeedback) {
                    listFeedback.hidden = false;
                    listFeedback.textContent = 'Select at least one list.';
                    listFeedback.className = 'list-modal__feedback is-error';
                }
                return;
            }

            listSaveBtn.disabled = true;

            apiRequest('add_to_lists', { list_ids: selected.join(',') }).then(function (data) {
                listSaveBtn.disabled = false;

                if (listFeedback) {
                    listFeedback.hidden = false;
                    listFeedback.textContent = data.message || '';
                    listFeedback.className = 'list-modal__feedback ' + (data.success ? 'is-success' : 'is-error');
                }

                if (data.success) {
                    state.listIdsWithItem = state.listIdsWithItem.concat(data.added || [], data.skipped || []);
                    showToast(panelToast, data.message, false);

                    window.setTimeout(function () {
                        closeModal(listModal);
                    }, 700);
                }
            }).catch(function () {
                listSaveBtn.disabled = false;
                if (listFeedback) {
                    listFeedback.hidden = false;
                    listFeedback.textContent = 'Network error. Please try again.';
                    listFeedback.className = 'list-modal__feedback is-error';
                }
            });
        });
    }
    } /* config */
    } /* panel */

    /* ------------------------------------------------------------------ */
    /* Notes                                                               */
    /* ------------------------------------------------------------------ */

    var notesSection = document.getElementById('detail-notes');
    var pageConfigEl = document.getElementById('detail-page-config');
    var noteForm     = document.getElementById('note-form');
    var noteBody     = document.getElementById('note-body');
    var noteView     = document.getElementById('note-view');
    var noteDisplay  = document.getElementById('note-display');

    if (notesSection && pageConfigEl && noteForm && noteBody) {
        var pageConfig;

        try {
            pageConfig = JSON.parse(pageConfigEl.textContent || '{}');
        } catch (err) {
            pageConfig = null;
        }

        if (pageConfig) {
            var noteCharCount = document.getElementById('note-char-count');
            var noteSubmit    = document.getElementById('note-submit-btn');
            var noteCancel    = document.getElementById('note-cancel-btn');
            var noteEdit      = document.getElementById('note-edit-btn');
            var noteFeedback  = document.getElementById('note-feedback');
            var noteSavedAt   = document.getElementById('note-saved-at');
            var noteMaxChars  = pageConfig.noteMaxChars || 8000;
            var savedDraft    = noteBody.value;

            function getNoteCsrf() {
                var el = document.getElementById('detail-csrf-token');
                return el && el.value ? el.value : '';
            }

            function updateNoteCount() {
                if (noteCharCount) {
                    noteCharCount.textContent = noteBody.value.length + ' / ' + noteMaxChars;
                }
            }

            function setSavedAt(updatedAt) {
                if (!noteSavedAt) return;
                noteSavedAt.textContent = updatedAt ? 'Last saved ' + updatedAt : '';
            }

            function showNoteFeedback(message, isError) {
                if (!noteFeedback) return;
                noteFeedback.textContent = message;
                noteFeedback.hidden = false;
                noteFeedback.className = 'detail-notes__feedback' + (isError ? ' is-error' : ' is-success');
                window.clearTimeout(noteFeedback._timer);
                noteFeedback._timer = window.setTimeout(function () {
                    noteFeedback.hidden = true;
                }, 3500);
            }

            function showEditor(canCancel) {
                if (noteView) noteView.hidden = true;
                noteForm.hidden = false;
                if (noteCancel) noteCancel.hidden = !canCancel;
                noteBody.focus();
            }

            function showSavedNote(body, updatedAt) {
                savedDraft = body || '';
                noteBody.value = savedDraft;
                updateNoteCount();
                setSavedAt(updatedAt);

                if (noteDisplay) {
                    noteDisplay.textContent = savedDraft;
                }

                if (savedDraft === '') {
                    if (noteView) noteView.hidden = true;
                    noteForm.hidden = false;
                    if (noteCancel) noteCancel.hidden = true;
                    return;
                }

                noteForm.hidden = true;
                if (noteView) noteView.hidden = false;
                if (noteCancel) noteCancel.hidden = false;
            }

            function saveNote() {
                var csrfToken = getNoteCsrf();

                if (!csrfToken) {
                    showNoteFeedback('Missing security token. Please refresh the page.', true);
                    return Promise.resolve();
                }

                if (noteSubmit) noteSubmit.disabled = true;

                var body = new URLSearchParams();
                body.set('action', 'save_note');
                body.set('csrf_token', csrfToken);
                body.set('tmdb_id', String(pageConfig.tmdbId));
                body.set('media_type', pageConfig.mediaType);
                body.set('body', noteBody.value);

                return trackSlow(fetch(pageConfig.notesApiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-CSRF-Token': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body.toString(),
                    credentials: 'include'
                })).then(function (res) {
                    return res.json();
                }).then(function (data) {
                    if (noteSubmit) noteSubmit.disabled = false;

                    if (data.success) {
                        showSavedNote(data.body || '', data.updated_at || null);
                        showNoteFeedback(data.message || 'Notes saved.', false);
                    } else {
                        showNoteFeedback(data.message || 'Could not save notes.', true);
                    }
                }).catch(function () {
                    if (noteSubmit) noteSubmit.disabled = false;
                    showNoteFeedback('Network error. Please try again.', true);
                });
            }

            noteBody.addEventListener('input', updateNoteCount);

            noteForm.addEventListener('submit', function (e) {
                e.preventDefault();
                saveNote();
            });

            if (noteEdit) {
                noteEdit.addEventListener('click', function () {
                    noteBody.value = savedDraft;
                    updateNoteCount();
                    showEditor(savedDraft !== '');
                });
            }

            if (noteCancel) {
                noteCancel.addEventListener('click', function () {
                    noteBody.value = savedDraft;
                    updateNoteCount();

                    if (savedDraft === '') {
                        if (noteView) noteView.hidden = true;
                        noteForm.hidden = false;
                        noteCancel.hidden = true;
                        return;
                    }

                    if (noteDisplay) {
                        noteDisplay.textContent = savedDraft;
                    }
                    noteForm.hidden = true;
                    if (noteView) noteView.hidden = false;
                });
            }

            updateNoteCount();
        }
    }
})();
