<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/dosen_layout.php';

requireLogin(['dosen']);
$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
$sidebarMenus = dosenSidebarMenus('/dashboard/dosen_dashboard.php');
$accountData = fetchLoggedDosen($koneksi, $userId);

if ($accountData === null) {
    $accountData = [
        'nama_lengkap' => (string) ($user['username'] ?? ''),
        'nidn' => '-',
        'email' => '',
    ];
}

renderDosenLayoutStart(
    'Dashboard Dosen',
    'Dashboard',
    'Selamat datang di dashboard dosen.',
    $user,
    $sidebarMenus,
    true
);
?>

<div class="grid-two-column">

    <div class="content-column">

        <section class="info-card">
            <h2>Ringkasan Akun</h2>

            <p>Selamat datang,
                <?= e((string)$user['username']) ?>.
            </p>

            <p>
                Anda login sebagai dosen dan halaman ini sudah terlindungi oleh session.
            </p>

        </section>

        <section class="info-card">
            <h2>Statistik Bimbingan</h2>

            <div class="stats-grid">

                <div class="stat-box">
                    <strong>12</strong>
                    <span>Mahasiswa bimbingan aktif</span>
                </div>

                <div class="stat-box">
                    <strong>5</strong>
                    <span>Jadwal konsultasi minggu ini</span>
                </div>

                <div class="stat-box">
                    <strong>3</strong>
                    <span>Pengajuan menunggu review</span>
                </div>

                <div class="stat-box">
                    <strong>8</strong>
                    <span>Dokumen sudah diverifikasi</span>
                </div>

            </div>

        </section>

    </div>

    <div class="content-column">

        <section class="info-card">

            <h2>Agenda Terdekat</h2>

            <ul class="agenda-list">

                <li class="agenda-item">
                    <strong>Bimbingan Bab 3</strong>
                    <span>Senin, 09.00 WIB dengan mahasiswa Teknik Informatika.</span>
                </li>

                <li class="agenda-item">
                    <strong>Review Proposal</strong>
                    <span>Rabu, 13.00 WIB untuk revisi pengajuan judul.</span>
                </li>

                <li class="agenda-item">
                    <strong>Validasi Seminar</strong>
                    <span>Jumat, 10.00 WIB untuk pengecekan berkas seminar.</span>
                </li>

            </ul>

        </section>

        <section class="info-card">

            <h2>Catatan Sistem</h2>

            <p>Sidebar ini siap dipakai sebagai navigasi utama untuk fitur dosen berikutnya.</p>

            <p>
                Jika Anda ingin, menu yang masih kosong bisa langsung disambungkan
                ke modul berikutnya.
            </p>

        </section>

    </div>

</div>

<?php
renderDosenLayoutEnd();
?>