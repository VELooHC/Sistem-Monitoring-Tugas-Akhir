<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    require_once __DIR__ . '/../config/koneksi.php';
    require_once __DIR__ . '/../includes/activity_log.php';
    $currentUser = currentUser();
    recordActivity(
        $koneksi,
        (int) ($currentUser['id'] ?? 0),
        'Logout',
        'Pengguna logout dari sistem.'
    );
}
logoutUser();
redirect('/login.php');
