/**
 * Cinomnia Admin Panel — AJAX actions for user/comment deletion.
 */
(function () {
    'use strict';

    const config = window.CINOMNIA_ADMIN || {};
    const csrfToken = config.csrfToken || '';
    const apiUrl = config.apiUrl || '/admin-actions.php';

    function showToast(message, type) {
        const existing = document.querySelector('.admin-toast');
        if (existing) {
            existing.remove();
        }

        const toast = document.createElement('div');
        toast.className = 'admin-toast admin-toast--' + (type === 'error' ? 'error' : 'success');
        toast.setAttribute('role', 'status');
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(function () {
            toast.remove();
        }, 4000);
    }

    async function postAction(action, data) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('csrf_token', csrfToken);

        Object.keys(data).forEach(function (key) {
            formData.append(key, String(data[key]));
        });

        const response = await fetch(apiUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-CSRF-Token': csrfToken,
            },
        });

        return response.json();
    }

    function bindDeleteButtons() {
        document.querySelectorAll('[data-admin-delete-user]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const userId = btn.getAttribute('data-admin-delete-user');
                const username = btn.getAttribute('data-username') || 'this user';

                if (!confirm('Delete user "' + username + '"? This cannot be undone.')) {
                    return;
                }

                btn.disabled = true;

                try {
                    const result = await postAction('delete_user', { user_id: userId });

                    if (result.success) {
                        const row = btn.closest('tr');
                        const editRow = row && row.nextElementSibling;

                        if (row) {
                            row.remove();
                        }

                        if (editRow && editRow.classList.contains('admin-edit-row')) {
                            editRow.remove();
                        }

                        showToast(result.message, 'success');
                    } else {
                        showToast(result.message || 'Delete failed.', 'error');
                        btn.disabled = false;
                    }
                } catch (err) {
                    showToast('Network error. Please try again.', 'error');
                    btn.disabled = false;
                }
            });
        });

        document.querySelectorAll('[data-admin-delete-comment]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const commentId = btn.getAttribute('data-admin-delete-comment');

                if (!confirm('Delete this comment? Replies will also be removed.')) {
                    return;
                }

                btn.disabled = true;

                try {
                    const result = await postAction('delete_comment', { comment_id: commentId });

                    if (result.success) {
                        const card = btn.closest('.admin-comment-card');

                        if (card) {
                            card.remove();
                        }

                        showToast(result.message, 'success');
                    } else {
                        showToast(result.message || 'Delete failed.', 'error');
                        btn.disabled = false;
                    }
                } catch (err) {
                    showToast('Network error. Please try again.', 'error');
                    btn.disabled = false;
                }
            });
        });
    }

    function bindEditToggles() {
        document.querySelectorAll('[data-admin-edit-user]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const userId = btn.getAttribute('data-admin-edit-user');
                const editRow = document.getElementById('edit-user-' + userId);

                if (!editRow) {
                    return;
                }

                const isOpen = editRow.classList.contains('is-open');

                document.querySelectorAll('.admin-edit-row.is-open').forEach(function (row) {
                    row.classList.remove('is-open');
                });

                if (!isOpen) {
                    editRow.classList.add('is-open');
                    const input = editRow.querySelector('input[name="username"]');

                    if (input) {
                        input.focus();
                        input.select();
                    }
                }
            });
        });

        document.querySelectorAll('[data-admin-cancel-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const editRow = btn.closest('.admin-edit-row');

                if (editRow) {
                    editRow.classList.remove('is-open');
                }
            });
        });
    }

    function bindRatingToggles() {
        document.querySelectorAll('[data-admin-rating-toggle]').forEach(function (header) {
            header.addEventListener('click', function () {
                const card = header.closest('.admin-rating-card');

                if (card) {
                    card.classList.toggle('is-expanded');
                }
            });

            header.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    header.click();
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindDeleteButtons();
        bindEditToggles();
        bindRatingToggles();
    });
})();
