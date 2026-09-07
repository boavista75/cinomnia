    <footer class="footer">
        <div class="footer__inner">
            <p class="footer__brand"><?= \Cinomnia\Security\Security::escape(APP_NAME) ?></p>
            <p>&copy; <?= date('Y') ?> Private cinema library</p>
        </div>
    </footer>
    <script src="<?= \Cinomnia\Security\Security::escape(BASE_URL) ?>/js/loading.js?v=<?= (int) filemtime(APP_ROOT . '/js/loading.js') ?>"></script>
    <script src="<?= \Cinomnia\Security\Security::escape(BASE_URL) ?>/js/navbar.js?v=<?= (int) filemtime(APP_ROOT . '/js/navbar.js') ?>" defer></script>
    <script src="<?= \Cinomnia\Security\Security::escape(BASE_URL) ?>/js/search.js?v=<?= (int) filemtime(APP_ROOT . '/js/search.js') ?>" defer></script>
</body>
</html>
