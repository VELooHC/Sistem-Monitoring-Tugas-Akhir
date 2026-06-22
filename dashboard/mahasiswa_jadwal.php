<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/mahasiswa_layout.php';

requireLogin(['mahasiswa']);
$user = currentUser();
$sidebarMenus = mahasiswaSidebarMenus('/dashboard/mahasiswa_jadwal.php');

renderMahasiswaLayoutStart(
    'Jadwal Bimbingan',
    'Jadwal Bimbingan',
    'Halaman khusus untuk melihat agenda bimbingan dan jadwal kegiatan tugas akhir.',
    $user,
    $sidebarMenus
);
?>
        <div class="grid-two-column">
          <div class="content-column">
            <section class="info-card">
              <h2>Agenda Pekan Ini</h2>
              <ul class="list-block">
                <li class="list-item">
                  <strong>Senin, 09.00 WIB</strong>
                  <span>Bimbingan revisi bab pendahuluan dengan dosen pembimbing.</span>
                </li>
                <li class="list-item">
                  <strong>Rabu, 13.00 WIB</strong>
                  <span>Persiapan dokumen seminar dan pengecekan kelengkapan berkas.</span>
                </li>
              </ul>
            </section>
          </div>

          <div class="content-column">
            <section class="info-card">
              <h2>Catatan Jadwal</h2>
              <p>Halaman ini dipisahkan dari dashboard utama agar informasi agenda tidak menumpuk di halaman depan mahasiswa.</p>
              <p>Jika Anda ingin, saya bisa lanjut menghubungkan halaman ini dengan data jadwal yang tersimpan di database.</p>
            </section>
          </div>
        </div>
<?php renderMahasiswaLayoutEnd(); ?>
