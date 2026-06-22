<?php
declare(strict_types=1);

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'db_bimbingan_ta';

$koneksi = mysqli_init();

if ($koneksi === false) {
    die('Gagal menginisialisasi koneksi database.');
}

mysqli_options($koneksi, MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);

if (!mysqli_real_connect($koneksi, $dbHost, $dbUser, $dbPass, $dbName)) {
    die('Koneksi database gagal: ' . mysqli_connect_error());
}

mysqli_set_charset($koneksi, 'utf8mb4');
