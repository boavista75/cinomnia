    <footer class="footer">
        <div class="footer__inner">
            <p>&copy; <?= date('Y') ?> <?= \Cinomnia\Security\Security::escape(APP_NAME) ?></p>
        </div>
    </footer>
    <script src="<?= \Cinomnia\Security\Security::escape(BASE_URL) ?>/js/navbar.js" defer></script>
</body>
</html>
