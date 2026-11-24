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

$nim = (string) ($accountData['nim'] ?? '');
$latestGuidance = null;
if ($nim !== '' && $nim !== '-') {
    $guidanceStatement = mysqli_prepare(
        $koneksi,
        'SELECT b.tanggal_bimbingan, b.waktu_bimbingan, b.agenda,
                b.catatan_bimbingan, d.nama_lengkap AS nama_dosen
         FROM bimbingan b
         LEFT JOIN dosen d ON d.nidn = b.id_dosen
         WHERE b.nim = ?
         ORDER BY b.tanggal_bimbingan DESC, b.waktu_bimbingan DESC, b.id_bimbingan DESC
         LIMIT 1'
    );

    if ($guidanceStatement === false) {
        throw new RuntimeException('Gagal menyiapkan data bimbingan terbaru.');
    }

    mysqli_stmt_bind_param($guidanceStatement, 's', $nim);
    if (!mysqli_stmt_execute($guidanceStatement)) {
        throw new RuntimeException('Gagal mengambil data bimbingan terbaru.');
    }

    $guidanceResult = mysqli_stmt_get_result($guidanceStatement);
    $latestGuidance = $guidanceResult instanceof mysqli_result
        ? mysqli_fetch_assoc($guidanceResult)
        : null;
    mysqli_stmt_close($guidanceStatement);
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
                <a class="menu-card" href="<?= e(appPath('/dashboard/mahasiswa_jadwal.php')) ?>">
                  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3.5" y="5" width="17" height="15.5" rx="2.5" stroke-width="1.8" />
                    <path d="M7.5 3.5V7" stroke-width="1.8" stroke-linecap="round" />
                    <path d="M16.5 3.5V7" stroke-width="1.8" stroke-linecap="round" />
                    <path d="M3.5 9H20.5" stroke-width="1.8" />
                  </svg>
                  <span>Jadwal Bimbingan</span>
                  <small>Tambahkan agenda bimbingan dan catatan pertemuan.</small>
                </a>
                <a class="menu-card" href="<?= e(appPath('/dashboard/mahasiswa_seminar.php')) ?>">
                  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 7V12L15 14" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    <circle cx="12" cy="12" r="8.5" stroke-width="1.8" />
                  </svg>
                  <span>Seminar / Sidang</span>
                  <small>Unggah dokumen dan pantau penilaian serta catatan dosen.</small>
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
                <a class="quick-box" href="<?= e(appPath('/dashboard/mahasiswa_jadwal.php')) ?>">
                  <strong>Bimbingan Terbaru</strong>
                  <?php if ($latestGuidance !== null): ?>
                    <span><?= e((string) $latestGuidance['tanggal_bimbingan']) ?><?= !empty($latestGuidance['waktu_bimbingan']) ? ' · ' . e(substr((string) $latestGuidance['waktu_bimbingan'], 0, 5)) : '' ?></span>
                    <span><?= e((string) (($latestGuidance['agenda'] ?? '') !== '' ? $latestGuidance['agenda'] : ($latestGuidance['catatan_bimbingan'] ?? 'Agenda bimbingan'))) ?></span>
                    <?php if (!empty($latestGuidance['nama_dosen'])): ?>
                      <span>Dosen: <?= e((string) $latestGuidance['nama_dosen']) ?></span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span>Belum ada agenda bimbingan.</span>
                  <?php endif; ?>
                </a>
              </div>
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
                  <strong>Seminar / Sidang</strong>
                  <span>Lihat status, nilai, dan catatan dosen untuk dokumen seminar atau sidang.</span>
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
