<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/admin_layout.php';

requireLogin(['admin']);

$user = currentUser();

$sidebarMenus = adminSidebarMenus('/dashboard/admin_arsip.php');

renderAdminLayoutStart(
    'Riwayat Aktivitas',
    'Riwayat Aktivitas',
    'Daftar riwayat aktivitas.',
    $user,
    $sidebarMenus
);