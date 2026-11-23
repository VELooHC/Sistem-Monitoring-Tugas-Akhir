<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../config/koneksi.php';
require_once __DIR__.'/admin_layout.php';

requireLogin(['admin']);

$user = currentUser();

$sidebarMenus = adminSidebarMenus('/dashboard/admin_dashboard.php');

renderAdminLayoutStart(
    'Dashboard Admin',
    'Dashboard Admin',
    'Kelola seluruh sistem tugas akhir.',
    $user,
    $sidebarMenus,
    true
);
?>
<div class="grid-two-column">
  <div class="content-column">
    <section class="info-card">
      <h2>Akses Cepat</h2>
        <div class="menu-grid" aria-label="Menu dashboard admin">
          <a class="menu-card" href="<?= e(appPath('/dashboard/admin_mahasiswa.php')) ?>">
            <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <rect x="10" y="12" width="44" height="40" rx="10" stroke="#7B7F89" stroke-width="3" />
              <circle cx="25" cy="28" r="5" stroke="#7B7F89" stroke-width="3" />
              <path d="M20 42C21.8 37.8 24.9 36 28.5 36C32.1 36 35.2 37.8 37 42" stroke="#7B7F89" stroke-width="3" stroke-linecap="round" />
              <path d="M43 24H49" stroke="#7B7F89" stroke-width="3" stroke-linecap="round" />
              <path d="M43 31H49" stroke="#7B7F89" stroke-width="3" stroke-linecap="round" />
            </svg>
            <span>Mahasiswa</span>
            <small>Kelola data mahasiswa dan akun login.</small>
          </a>

          <a class="menu-card" href="<?= e(appPath('/dashboard/admin_dosen.php')) ?>">
            <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <rect x="10" y="12" width="44" height="40" rx="10" stroke="#7B7F89" stroke-width="3" />
              <circle cx="24" cy="27" r="5" stroke="#7B7F89" stroke-width="3" />
              <path d="M18 42C20.1 37.4 23.6 35.5 28 35.5C32.4 35.5 35.9 37.4 38 42" stroke="#7B7F89" stroke-width="3" stroke-linecap="round" />
              <path d="M44 22V38" stroke="#7B7F89" stroke-width="3" stroke-linecap="round" />
              <path d="M38 30H50" stroke="#7B7F89" stroke-width="3" stroke-linecap="round" />
            </svg>
            <span>Dosen</span>
            <small>Kelola data dosen dan akun login dosen.</small>
          </a>

          <a class="menu-card" href="<?= e(appPath('/dashboard/admin_arsip.php')) ?>">
            <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <rect x="14" y="10" width="36" height="44" rx="8" stroke="#7B7F89" stroke-width="3" />
              <path d="M22 22H42" stroke="#7B7F89" stroke-width="3" stroke-linecap="round" />
              <path d="M22 30H42" stroke="#7B7F89" stroke-width="3" stroke-linecap="round" />
              <path d="M22 38H35" stroke="#7B7F89" stroke-width="3" stroke-linecap="round" />
            </svg>
            <span>Arsip</span>
            <small>Tempat dokumen dan histori administrasi.</small>
          </a>

          <a class="menu-card" href="<?= e(appPath('/dashboard/admin_jadwal.php')) ?>">
            <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <rect x="12" y="14" width="40" height="36" rx="8" stroke="#7B7F89" stroke-width="3" />
              <path d="M22 10V18" stroke="#7B7F89" stroke-width="3" stroke-linecap="round" />
              <path d="M42 10V18" stroke="#7B7F89" stroke-width="3" stroke-linecap="round" />
              <path d="M12 24H52" stroke="#7B7F89" stroke-width="3" />
              <path d="M22 32H32" stroke="#7B7F89" stroke-width="3" stroke-linecap="round" />
              <path d="M22 39H42" stroke="#7B7F89" stroke-width="3" stroke-linecap="round" />
            </svg>
            <span>Jadwal</span>
            <small>Kelola jadwal akademik dan agenda kampus.</small>
          </a>
        </div>
      </section>

      <section class="info-card">
        <h2>Ringkasan Sistem</h2>
        <div class="stats-grid">
          <div class="stat-box">
            <strong>1</strong>
            <span>Modul aktif siap dipakai: data mahasiswa</span>
          </div>
          <div class="stat-box">
            <strong>3</strong>
            <span>Menu admin siap dikembangkan berikutnya</span>
          </div>
          <div class="stat-box">
            <strong>24/7</strong>
            <span>Akses panel admin dengan proteksi session</span>
          </div>
          <div class="stat-box">
            <strong>1 Panel</strong>
            <span>Pusat kontrol untuk pengguna dan data akademik</span>
          </div>
            </div>
          </section>
        </div>

        <div class="content-column">
          <section class="info-card">
            <h2>Aktivitas Admin</h2>
            <ul class="activity-list">
              <li class="activity-item">
                <strong>Kelola Mahasiswa</strong>
                <span>Tambahkan, edit, dan hapus data mahasiswa melalui modul CRUD admin.</span>
              </li>
              <li class="activity-item">
                <strong>Kontrol Akses</strong>
                <span>Pastikan akun yang masuk memiliki role sesuai modul yang dibuka.</span>
              </li>
              <li class="activity-item">
                <strong>Pengembangan Modul</strong>
                <span>Menu dosen, arsip, dan jadwal siap disambungkan ke fitur berikutnya.</span>
              </li>
            </ul>
          </section>

          <section class="info-card">
            <h2>Informasi Akun</h2>
            <p>Halo, <?= e((string) $user['username']) ?>.</p>
            <p>Dashboard admin sekarang sudah mengikuti gaya layout sidebar seperti dashboard dosen dan mahasiswa.</p>
            <p>Menu `Mahasiswa` juga sudah terhubung langsung ke halaman pengelolaan data mahasiswa.</p>
          </section>
        </div>
      </main>
    </div>
  </div>
</div>
<?php
renderAdminLayoutEnd();
?>