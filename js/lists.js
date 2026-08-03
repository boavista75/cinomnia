/**
 * Cinomnia — Lists dashboard interactions
 */
(function () {
    'use strict';

    var renameModal = document.getElementById('rename-list-modal');
    var renameId    = document.getElementById('rename-list-id');
    var renameName  = document.getElementById('rename-list-name');

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
        document.body.classList.remove('modal-open');
    }

    if (renameModal) {
        renameModal.querySelectorAll('[data-close-modal="rename"]').forEach(function (el) {
            el.addEventListener('click', function () {
                closeModal(renameModal);
            });
        });

        document.querySelectorAll('[data-rename-list]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (renameId) {
                    renameId.value = btn.getAttribute('data-list-id') || '';
                }
                if (renameName) {
                    renameName.value = btn.getAttribute('data-list-name') || '';
                    renameName.focus();
                    renameName.select();
                }
                openModal(renameModal);
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && renameModal && !renameModal.hidden) {
                closeModal(renameModal);
            }
        });
    }

    var detail = document.getElementById('list-detail');
    if (detail && window.location.hash === '#list-detail') {
        detail.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
})();
