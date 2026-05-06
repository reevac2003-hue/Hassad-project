<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

unset($_SESSION['auth']);

set_flash('success', 'You have been logged out.');
redirect_to('home');
