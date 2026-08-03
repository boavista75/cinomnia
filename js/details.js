/**
 * Cinomnia — Details page (trailer, rating, watched, custom lists)
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
        document.body.classList.add('modal-open');
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');

        if (!document.querySelector('.modal:not([hidden])')) {
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

    /* ------------------------------------------------------------------ */
    /* Trailer modal                                                       */
    /* ------------------------------------------------------------------ */

    var trailerModal = document.getElementById('trailer-modal');
    var playBtn      = document.getElementById('play-trailer-btn');
    var trailerFrame = document.getElementById('trailer-iframe');

    if (trailerModal && trailerFrame) {
        bindModalClose(trailerModal, 'trailer');

        if (playBtn) {
            playBtn.addEventListener('click', function () {
                var key = playBtn.getAttribute('data-trailer-key');
                if (key) {
                    trailerFrame.src = 'https://www.youtube.com/embed/' + encodeURIComponent(key) + '?autoplay=1&rel=0';
                    openModal(trailerModal);
                }
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (!trailerModal.hidden) {
                    trailerFrame.src = '';
                    closeModal(trailerModal);
                }
                var listModal = document.getElementById('list-modal');
                if (listModal && !listModal.hidden) {
                    closeModal(listModal);
                }
            }
        });
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
        lists: config.lists || [],
        listIdsWithItem: config.listIdsWithItem || []
    };

    var watchedBtn    = document.getElementById('watched-toggle-btn');
    var addToListBtn  = document.getElementById('add-to-list-btn');
    var starRating    = document.getElementById('star-rating');
    var ratingValue   = document.getElementById('user-rating-value');
    var ratingFeedback = document.getElementById('rating-feedback');
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
        el.hidden = false;
        el.classList.toggle('is-error', !!isError);
        el.classList.toggle('is-success', !isError);

        window.clearTimeout(el._toastTimer);
        el._toastTimer = window.setTimeout(function () {
            el.hidden = true;
        }, 3200);
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

        return fetch(config.apiUrl, {
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
        });
    }

    function renderStars(rating, preview) {
        if (!starRating) return;

        var value = preview !== undefined ? preview : rating;

        starRating.querySelectorAll('.star-rating__star').forEach(function (star) {
            var starValue = parseInt(star.getAttribute('data-value'), 10);
            star.classList.toggle('is-active', value !== null && value >= starValue);
            star.classList.toggle('is-preview', preview !== undefined && preview >= starValue);
        });

        starRating.setAttribute('aria-valuenow', value || 0);
    }

    function updateRatingDisplay(rating) {
        if (ratingValue) {
            ratingValue.textContent = rating ? rating + ' / 10' : 'Not rated';
        }
        renderStars(rating);
    }

    function updateWatchedDisplay(isWatched) {
        if (!watchedBtn) return;

        watchedBtn.classList.toggle('is-watched', isWatched);
        watchedBtn.setAttribute('aria-pressed', isWatched ? 'true' : 'false');

        var icon  = watchedBtn.querySelector('.detail-user-panel__watched-icon');
        var label = watchedBtn.querySelector('.detail-user-panel__watched-label');

        if (icon) {
            icon.innerHTML = isWatched ? '&#10003;' : '&#9675;';
        }
        if (label) {
            label.textContent = isWatched ? 'Watched' : 'Mark as Watched';
        }
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

    /* Star rating interactions */
    if (starRating) {
        var hoverRating = null;

        starRating.querySelectorAll('.star-rating__star').forEach(function (star) {
            star.addEventListener('mouseenter', function () {
                hoverRating = parseInt(star.getAttribute('data-value'), 10);
                renderStars(state.userRating, hoverRating);
            });

            star.addEventListener('mouseleave', function () {
                hoverRating = null;
                renderStars(state.userRating);
            });

            star.addEventListener('click', function () {
                var value = parseInt(star.getAttribute('data-value'), 10);

                starRating.classList.add('is-saving');
                if (ratingFeedback) {
                    ratingFeedback.textContent = 'Saving…';
                }

                apiRequest('set_rating', { rating: String(value) }).then(function (data) {
                    starRating.classList.remove('is-saving');

                    if (data.success) {
                        state.userRating = value;
                        updateRatingDisplay(value);
                        if (ratingFeedback) {
                            ratingFeedback.textContent = 'Rating saved!';
                        }
                        showToast(panelToast, data.message, false);
                    } else {
                        if (ratingFeedback) {
                            ratingFeedback.textContent = data.message || 'Could not save rating.';
                        }
                        showToast(panelToast, data.message || 'Could not save rating.', true);
                    }
                }).catch(function () {
                    starRating.classList.remove('is-saving');
                    if (ratingFeedback) {
                        ratingFeedback.textContent = 'Network error. Please try again.';
                    }
                });
            });
        });

        starRating.addEventListener('keydown', function (e) {
            var current = state.userRating || 0;

            if (e.key === 'ArrowRight' || e.key === 'ArrowUp') {
                e.preventDefault();
                renderStars(Math.min(10, current + 1), Math.min(10, current + 1));
            } else if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') {
                e.preventDefault();
                renderStars(Math.max(1, current - 1), Math.max(1, current - 1));
            }
        });
    }

    /* Watched toggle */
    if (watchedBtn) {
        watchedBtn.addEventListener('click', function () {
            watchedBtn.disabled = true;

            apiRequest('toggle_watched').then(function (data) {
                watchedBtn.disabled = false;

                if (data.success) {
                    state.isWatched = !!data.is_watched;
                    updateWatchedDisplay(state.isWatched);
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
    /* Comments                                                            */
    /* ------------------------------------------------------------------ */

    var commentsSection = document.getElementById('detail-comments');
    var pageConfigEl    = document.getElementById('detail-page-config');

    if (commentsSection && pageConfigEl) {
        var pageConfig;

        try {
            pageConfig = JSON.parse(pageConfigEl.textContent || '{}');
        } catch (err) {
            pageConfig = null;
        }

        if (pageConfig) {
            var commentsList    = document.getElementById('comments-list');
            var commentsEmpty   = document.getElementById('comments-empty');
            var commentsLoading = document.getElementById('comments-loading');
            var commentsFeedback = document.getElementById('comments-feedback');
            var commentForm     = document.getElementById('comment-form');
            var commentBody     = document.getElementById('comment-body');
            var commentCharCount = document.getElementById('comment-char-count');
            var commentSubmit   = document.getElementById('comment-submit-btn');

            function escapeHtml(str) {
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function getCommentCsrf() {
                var el = document.getElementById('detail-csrf-token');
                return el && el.value ? el.value : '';
            }

            function showCommentsFeedback(message, isError) {
                if (!commentsFeedback) return;
                commentsFeedback.textContent = message;
                commentsFeedback.hidden = false;
                commentsFeedback.className = 'detail-comments__feedback' + (isError ? ' is-error' : ' is-success');
                window.clearTimeout(commentsFeedback._timer);
                commentsFeedback._timer = window.setTimeout(function () {
                    commentsFeedback.hidden = true;
                }, 3500);
            }

            function commentsApi(action, extra, requireAuth) {
                var body = new URLSearchParams();
                body.set('action', action);
                body.set('tmdb_id', String(pageConfig.tmdbId));
                body.set('media_type', pageConfig.mediaType);

                if (requireAuth) {
                    var token = getCommentCsrf();
                    if (!token) {
                        return Promise.resolve({
                            success: false,
                            message: 'Please log in to continue.'
                        });
                    }
                    body.set('csrf_token', token);
                }

                if (extra) {
                    Object.keys(extra).forEach(function (key) {
                        body.set(key, extra[key]);
                    });
                }

                var headers = {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                };

                if (requireAuth) {
                    headers['X-CSRF-Token'] = getCommentCsrf();
                }

                return fetch(pageConfig.commentsApiUrl, {
                    method: 'POST',
                    headers: headers,
                    body: body.toString(),
                    credentials: 'include'
                }).then(function (res) {
                    return res.json();
                });
            }

            function formatDate(iso) {
                if (!iso) return '';
                var d = new Date(iso.replace(' ', 'T'));
                if (isNaN(d.getTime())) return iso;
                return d.toLocaleDateString(undefined, {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }

            function renderReactionButtons(comment, isReply) {
                var likeActive  = comment.user_reaction === 1 ? ' is-active' : '';
                var dislikeActive = comment.user_reaction === -1 ? ' is-active' : '';
                var disabled = pageConfig.isLoggedIn ? '' : ' disabled title="Log in to vote"';

                return '<div class="comment-item__reactions">' +
                    '<button type="button" class="comment-react comment-react--like' + likeActive + '"' +
                        ' data-comment-id="' + comment.id + '" data-reaction="1"' + disabled + '>' +
                        '<span class="comment-react__icon" aria-hidden="true">&#9650;</span>' +
                        '<span class="comment-react__count" data-like-count>' + comment.likes + '</span>' +
                    '</button>' +
                    '<button type="button" class="comment-react comment-react--dislike' + dislikeActive + '"' +
                        ' data-comment-id="' + comment.id + '" data-reaction="-1"' + disabled + '>' +
                        '<span class="comment-react__icon" aria-hidden="true">&#9660;</span>' +
                        '<span class="comment-react__count" data-dislike-count>' + comment.dislikes + '</span>' +
                    '</button>' +
                    (!isReply && pageConfig.isLoggedIn
                        ? '<button type="button" class="comment-reply-btn" data-reply-to="' + comment.id + '">Reply</button>'
                        : '') +
                '</div>';
            }

            function renderCommentItem(comment, isReply) {
                var item = document.createElement('li');
                item.className = 'comment-item' + (isReply ? ' comment-item--reply' : '');
                item.setAttribute('data-comment-id', String(comment.id));

                item.innerHTML =
                    '<div class="comment-item__avatar" aria-hidden="true">' +
                        escapeHtml((comment.username || '?').charAt(0).toUpperCase()) +
                    '</div>' +
                    '<div class="comment-item__body">' +
                        '<div class="comment-item__header">' +
                            '<strong class="comment-item__author">' + escapeHtml(comment.username) + '</strong>' +
                            '<time class="comment-item__time" datetime="' + escapeHtml(comment.created_at) + '">' +
                                escapeHtml(formatDate(comment.created_at)) +
                            '</time>' +
                        '</div>' +
                        '<p class="comment-item__text"></p>' +
                        renderReactionButtons(comment, isReply) +
                        '<form class="comment-reply-form" data-reply-form="' + comment.id + '" hidden>' +
                            '<textarea class="detail-comments__textarea detail-comments__textarea--reply" ' +
                                'rows="2" maxlength="2000" placeholder="Write a reply…" required></textarea>' +
                            '<div class="comment-reply-form__actions">' +
                                '<button type="button" class="btn btn--ghost btn--sm" data-cancel-reply>Cancel</button>' +
                                '<button type="submit" class="btn btn--accent btn--sm">Post Reply</button>' +
                            '</div>' +
                        '</form>' +
                    '</div>';

                item.querySelector('.comment-item__text').textContent = comment.body;

                return item;
            }

            function bindCommentEvents(container) {
                container.querySelectorAll('.comment-react:not([disabled])').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var commentId = btn.getAttribute('data-comment-id');
                        var reaction  = parseInt(btn.getAttribute('data-reaction'), 10);
                        var item      = btn.closest('.comment-item');

                        btn.disabled = true;

                        commentsApi('set_reaction', {
                            comment_id: commentId,
                            reaction_type: btn.classList.contains('is-active') ? '0' : String(reaction)
                        }, true).then(function (data) {
                            btn.disabled = false;

                            if (!data.success || !item) return;

                            var likeBtn = item.querySelector('.comment-react--like');
                            var dislikeBtn = item.querySelector('.comment-react--dislike');

                            if (likeBtn) {
                                likeBtn.classList.toggle('is-active', data.user_reaction === 1);
                                likeBtn.querySelector('[data-like-count]').textContent = data.likes;
                            }
                            if (dislikeBtn) {
                                dislikeBtn.classList.toggle('is-active', data.user_reaction === -1);
                                dislikeBtn.querySelector('[data-dislike-count]').textContent = data.dislikes;
                            }
                        });
                    });
                });

                container.querySelectorAll('.comment-reply-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var id = btn.getAttribute('data-reply-to');
                        var form = container.querySelector('[data-reply-form="' + id + '"]');
                        if (form) {
                            form.hidden = false;
                            form.querySelector('textarea').focus();
                        }
                    });
                });

                container.querySelectorAll('[data-cancel-reply]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var form = btn.closest('.comment-reply-form');
                        if (form) {
                            form.hidden = true;
                            form.querySelector('textarea').value = '';
                        }
                    });
                });

                container.querySelectorAll('.comment-reply-form').forEach(function (form) {
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        var textarea = form.querySelector('textarea');
                        var parentId = form.getAttribute('data-reply-form');
                        var text = textarea ? textarea.value.trim() : '';

                        if (!text) return;

                        form.querySelector('button[type="submit"]').disabled = true;

                        commentsApi('post_comment', {
                            body: text,
                            parent_comment_id: parentId
                        }, true).then(function (data) {
                            form.querySelector('button[type="submit"]').disabled = false;

                            if (data.success) {
                                form.hidden = true;
                                textarea.value = '';
                                loadComments();
                                showCommentsFeedback(data.message, false);
                            } else {
                                showCommentsFeedback(data.message || 'Could not post reply.', true);
                            }
                        });
                    });
                });
            }

            function renderComments(comments) {
                if (!commentsList) return;

                commentsList.innerHTML = '';

                comments.forEach(function (comment) {
                    var topItem = renderCommentItem(comment, false);
                    commentsList.appendChild(topItem);

                    if (comment.replies && comment.replies.length) {
                        var replyList = document.createElement('ul');
                        replyList.className = 'comment-replies';

                        comment.replies.forEach(function (reply) {
                            replyList.appendChild(renderCommentItem(reply, true));
                        });

                        topItem.querySelector('.comment-item__body').appendChild(replyList);
                    }
                });

                bindCommentEvents(commentsList);

                var hasComments = comments.length > 0;
                commentsList.hidden = !hasComments;
                if (commentsEmpty) commentsEmpty.hidden = hasComments;
            }

            function loadComments() {
                if (commentsLoading) commentsLoading.hidden = false;
                if (commentsList) commentsList.hidden = true;
                if (commentsEmpty) commentsEmpty.hidden = true;

                commentsApi('get_comments', {}, false).then(function (data) {
                    if (commentsLoading) commentsLoading.hidden = true;

                    if (data.success) {
                        renderComments(data.comments || []);
                    } else {
                        showCommentsFeedback('Could not load comments.', true);
                    }
                }).catch(function () {
                    if (commentsLoading) commentsLoading.hidden = true;
                    showCommentsFeedback('Network error loading comments.', true);
                });
            }

            if (commentForm && commentBody) {
                commentBody.addEventListener('input', function () {
                    if (commentCharCount) {
                        commentCharCount.textContent = commentBody.value.length + ' / 2000';
                    }
                });

                commentForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var text = commentBody.value.trim();
                    if (!text) return;

                    if (commentSubmit) commentSubmit.disabled = true;

                    commentsApi('post_comment', { body: text }, true).then(function (data) {
                        if (commentSubmit) commentSubmit.disabled = false;

                        if (data.success) {
                            commentBody.value = '';
                            if (commentCharCount) commentCharCount.textContent = '0 / 2000';
                            loadComments();
                            showCommentsFeedback(data.message, false);
                        } else {
                            showCommentsFeedback(data.message || 'Could not post comment.', true);
                        }
                    }).catch(function () {
                        if (commentSubmit) commentSubmit.disabled = false;
                        showCommentsFeedback('Network error. Please try again.', true);
                    });
                });
            }

            loadComments();
        }
    }
})();
