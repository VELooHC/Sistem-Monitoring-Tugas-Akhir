<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/mahasiswa_layout.php';

requireLogin(['mahasiswa']);
$user = currentUser();
$sidebarMenus = mahasiswaSidebarMenus('/dashboard/mahasiswa_riwayat.php');

renderMahasiswaLayoutStart(
    'Riwayat Tugas Akhir',
    'Riwayat Tugas Akhir',
    'Halaman khusus untuk melihat progres, revisi, dan histori aktivitas tugas akhir.',
    $user,
    $sidebarMenus,
    $accountData
);
?>
        <div class="grid-two-column">
          <div class="content-column">
            <section class="info-card">
              <h2>Riwayat Aktivitas</h2>
              <ul class="list-block">
                <li class="list-item">
                  <strong>Pengajuan Awal</strong>
                  <span>Judul tugas akhir sudah tersimpan pada sistem dan menunggu proses lanjutan.</span>
                </li>
                <li class="list-item">
                  <strong>Catatan Revisi</strong>
                  <span>Halaman ini siap dipakai untuk menampilkan histori revisi dari dosen pembimbing.</span>
                </li>
                <li class="list-item">
                  <strong>Status Dokumen</strong>
                  <span>Riwayat berkas seminar atau dokumen pendukung bisa ditambahkan di sini.</span>
                </li>
              </ul>
            </section>
          </div>

          <div class="content-column">
            <section class="info-card">
              <h2>Ringkasan</h2>
              <p>Riwayat tugas akhir dipisahkan ke halaman sendiri agar dashboard mahasiswa tetap fokus pada navigasi utama dan ringkasan singkat.</p>
              <p>Dengan struktur ini, setiap modul bisa dikembangkan tanpa membuat tampilan dashboard utama berantakan.</p>
            </section>
          </div>
        </div>
<?php renderMahasiswaLayoutEnd(); ?>
