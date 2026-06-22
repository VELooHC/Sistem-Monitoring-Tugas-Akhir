<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/mahasiswa_layout.php';

requireLogin(['mahasiswa']);
$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
$sidebarMenus = mahasiswaSidebarMenus('/dashboard/mahasiswa_pengajuan.php');
$accountData = fetchLoggedMahasiswa($koneksi, $userId);

renderMahasiswaLayoutStart(
    'Pengajuan Judul',
    'Pengajuan Judul',
    'Halaman khusus untuk memantau dan menyiapkan proses pengajuan judul tugas akhir.',
    $user,
    $sidebarMenus
);
?>
        <div class="grid-two-column">
          <div class="content-column">
            <section class="info-card">
              <h2>Status Pengajuan</h2>
              <div class="list-block">
                <div class="list-item">
                  <strong>Judul Saat Ini</strong>
                  <span><?= e((string) (($accountData['judul_ta'] ?? '') !== '' ? $accountData['judul_ta'] : 'Belum ada judul yang tersimpan')) ?></span>
                </div>
                <div class="list-item">
                  <strong>Status</strong>
                  <span>Menunggu integrasi form pengajuan aktif pada tahap berikutnya.</span>
                </div>
              </div>
            </section>
          </div>

          <div class="content-column">
            <section class="info-card">
              <h2>Petunjuk Pengajuan</h2>
              <ul class="list-block">
                <li class="list-item">
                  <strong>Langkah 1</strong>
                  <span>Siapkan judul yang relevan dengan topik tugas akhir Anda.</span>
                </li>
                <li class="list-item">
                  <strong>Langkah 2</strong>
                  <span>Pastikan judul sesuai bidang studi dan arahan dosen pembimbing.</span>
                </li>
                <li class="list-item">
                  <strong>Langkah 3</strong>
                  <span>Form pengajuan detail dapat ditambahkan berikutnya pada halaman ini tanpa membuat dashboard utama ramai.</span>
                </li>
              </ul>
            </section>
          </div>
        </div>
<?php renderMahasiswaLayoutEnd(); ?>
