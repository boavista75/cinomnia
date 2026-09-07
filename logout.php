<?php

declare(strict_types=1);

/**
 * Cinomnia Logout Handler
 *
 * Destroys the user session and redirects to the home page.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$auth->logout();
redirect('/login.php');
