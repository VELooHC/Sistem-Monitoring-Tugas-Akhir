<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/mahasiswa_layout.php';

requireLogin(['mahasiswa']);
$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
$sidebarMenus = mahasiswaSidebarMenus('/dashboard/mahasiswa_dashboard.php');
$accountData = fetchLoggedMahasiswa($koneksi, $userId);

if ($accountData === null) {
    $accountData = [
        'nama_lengkap' => (string) ($user['username'] ?? ''),
        'nim' => '-',
        'email' => '',
        'judul_ta' => 'Belum ada data judul tugas akhir.',
    ];
}

renderMahasiswaLayoutStart(
    'Dashboard Mahasiswa',
    'Dashboard',
    'Selamat datang, ' . (string) ($accountData['nama_lengkap'] ?? $user['username'] ?? 'Mahasiswa'),
    $user,
    $sidebarMenus,
    $accountData,
    true
);
?>
        <div class="grid-two-column">
          <div class="content-column">
            <section class="info-card">
              <h2>Kalender TA</h2>
              <p>Dashboard utama kini dibuat lebih ringkas agar navigasi ke setiap fitur mahasiswa lebih rapi.</p>
              <div class="list-block">
                <div class="account-row">
                  <span>NIM</span>
                  <strong><?= e((string) $accountData['nim']) ?></strong>
                </div>
                <div class="account-row">
                  <span>Email</span>
                  <strong><?= e((string) (($accountData['email'] ?? '') !== '' ? $accountData['email'] : 'Belum ditambahkan')) ?></strong>
                </div>
                <div class="account-row">
                  <span>Judul Tugas Akhir</span>
                  <strong><?= e((string) $accountData['judul_ta']) ?></strong>
                </div>
              </div>
            </section>

            <section class="info-card">
              <h2>Menu Fitur Mahasiswa</h2>
              <div class="menu-grid">
                <a class="menu-card" href="<?= e(appPath('/dashboard/mahasiswa_tugas.php')) ?>">
                  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 6H16" stroke-width="1.8" stroke-linecap="round" />
                    <path d="M8 10H16" stroke-width="1.8" stroke-linecap="round" />
                    <path d="M8 14H12" stroke-width="1.8" stroke-linecap="round" />
                    <rect x="4" y="3.5" width="16" height="17" rx="2.5" stroke-width="1.8" />
                  </svg>
                  <span>Pengajuan Judul</span>
                  <small>Kelola pengajuan judul tugas akhir pada halaman tersendiri.</small>
                </a>

                <a class="menu-card" href="<?= e(appPath('/dashboard/mahasiswa_seminar.php')) ?>">
                  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 7V12L15 14" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    <circle cx="12" cy="12" r="8.5" stroke-width="1.8" />
                  </svg>
                  <span>Riwayat Tugas Akhir</span>
                  <small>Buka catatan progres dan histori aktivitas pada halaman sendiri.</small>
                </a>

                <a class="menu-card" href="<?= e(appPath('/dashboard/mahasiswa_profil.php')) ?>">
                  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="8" r="3" stroke-width="1.8" />
                    <path d="M5.5 19C7 15.5 9.3 14 12 14C14.7 14 17 15.5 18.5 19" stroke-width="1.8" stroke-linecap="round" />
                    <rect x="3.5" y="3.5" width="17" height="17" rx="3.5" stroke-width="1.8" />
                  </svg>
                  <span>Profil Mahasiswa</span>
                  <small>Ubah username, password, dan email pada halaman profil khusus.</small>
                </a>
              </div>
            </section>
          </div>

          <div class="content-column">
            <section class="info-card">
              <h2>Info Cepat</h2>
              <div class="quick-grid">
                <div class="quick-box">
                  <strong>Status Bimbingan</strong>
                  <span>Halaman fitur terpisah agar dashboard lebih rapi</span>
                </div>
                <a class="quick-box" href="<?= e(appPath('/dashboard/mahasiswa_jadwal.php')) ?>">         
                  <strong>Catatan</strong>
                  <span>Halaman fitur terpisah agar dashboard lebih rapi</span>
                </a>
            </section>

            <section class="info-card">
              <h2>Petunjuk Penggunaan</h2>
              <ul class="list-block">
                <li class="list-item">
                  <strong>Pengajuan Judul</strong>
                  <span>Gunakan halaman ini untuk melihat ringkasan dan langkah pengajuan topik tugas akhir.</span>
                </li>
                <li class="list-item">
                  <strong>Jadwal Bimbingan</strong>
                  <span>Buka halaman jadwal untuk melihat agenda konsultasi dan persiapan bimbingan.</span>
                </li>
                <li class="list-item">
                  <strong>Riwayat Tugas Akhir</strong>
                  <span>Lihat histori progres, revisi, dan status dokumen tugas akhir di halaman riwayat.</span>
                </li>
                <li class="list-item">
                  <strong>Profil Mahasiswa</strong>
                  <span>Kelola username, password, dan email Anda pada halaman profil mahasiswa.</span>
                </li>
              </ul>
            </section>
          </div>
        </div>
<?php renderMahasiswaLayoutEnd(); ?>
