<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/mahasiswa_layout.php';

requireLogin(['mahasiswa']);
$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
$sidebarMenus = mahasiswaSidebarMenus('/dashboard/mahasiswa_tugas.php');
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
                  <div class="list-item">
                    <div class="list-header">
                      <strong>Judul Saat Ini</strong>

                      <a href="edit_judul.php" class="btn-edit">
                        Edit
                      </a>
                    </div>

                    <span>
                      <?= e((string)(($accountData['judul_ta'] ?? '') !== '' ? $accountData['judul_ta'] : 'Belum ada judul yang tersimpan')) ?>
                    </span>

                  </div>
                <div class="list-item">
                  <strong>Status</strong>
                  <span>Menunggu integrasi form pengajuan aktif pada tahap berikutnya.</span>
                </div>
                <div class="list-item">
                  <strong>Upload Proposal / File Tugas Akhir</strong>

                  <form action="upload_proposal.php" method="POST" enctype="multipart/form-data" class="mt-2">

                    <input
                      type="file"
                      name="proposal"
                      class="form-control"
                      accept=".pdf,.doc,.docx"
                      required
                    >

                    <small class="text-muted">
                      Format: PDF/DOC/DOCX (Maksimal 10 MB)
                    /small>

                    <div class="mt-3">
                      <button type="submit" class="btn btn-success">
                        Upload File
                      </button>
                    </div>

                  </form>
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

        <style>
        .list-header{
          display:flex;
          justify-content:space-between;
          align-items:center;
          margin-bottom:8px;
        }

        .btn-edit{
          background:#1d4ed8;
          color:#fff;
          text-decoration:none;
          padding:6px 14px;
          border-radius:8px;
          font-size:.9rem;
          font-weight:600;
          transition:.2s;
        }

        .btn-edit:hover{
          background:#1e40af;
          color:#fff;
        } 

<?php renderMahasiswaLayoutEnd(); ?>
