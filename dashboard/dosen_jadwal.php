<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/dosen_layout.php';

requireLogin(['dosen']);

$user = currentUser();

$sidebarMenus = dosenSidebarMenus('/dashboard/dosen_mahasiswa.php');

renderDosenLayoutStart(
    'Jadwal Bimbingan',
    'Jadwal Bimbingan',
    'Daftar jadwal bimbingan.',
    $user,
    $sidebarMenus
);