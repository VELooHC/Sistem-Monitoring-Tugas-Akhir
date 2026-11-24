<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/mahasiswa_layout.php';

requireLogin(['mahasiswa']);

$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
$accountData = fetchLoggedMahasiswa($koneksi, $userId);
$sidebarMenus = mahasiswaSidebarMenus('/dashboard/mahasiswa_tugas.php');

if ($accountData === null) {
    die('Data akun mahasiswa tidak ditemukan.');
}

$nim = (string) $accountData['nim'];
$pesan = '';
$jenisPesan = '';

if (($_GET['edit'] ?? '') === 'success') {
    $pesan = 'Judul tugas akhir berhasil diperbarui.';
    $jenisPesan = 'success';
}

$stmt = mysqli_prepare(
    $koneksi,
    'SELECT id_ta, judul_ta, status_ta, nama_file, file_path, tanggal_upload
     FROM tugas_akhir
     WHERE nim = ?
     ORDER BY id_ta DESC
     LIMIT 1'
);

if ($stmt === false) {
    throw new RuntimeException('Gagal menyiapkan data tugas akhir.');
}

mysqli_stmt_bind_param($stmt, 's', $nim);
if (!mysqli_stmt_execute($stmt)) {
    throw new RuntimeException('Gagal mengambil data tugas akhir.');
}

$result = mysqli_stmt_get_result($stmt);
$dataTA = $result instanceof mysqli_result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($stmt);

renderMahasiswaLayoutStart(
    'Tugas Akhir',
    'Tugas Akhir',
    'Kelola judul dan dokumen tugas akhir Anda.',
    $user,
    $sidebarMenus,
    $accountData
);
?>
<div class="grid-two-column">
  <div class="content-column">
    <section class="info-card">
      <h2>Pengajuan Tugas Akhir</h2>

      <?php if ($pesan !== ''): ?>
        <div class="message-box message-<?= e($jenisPesan) ?>"><?= e($pesan) ?></div>
      <?php endif; ?>

      <div class="list-block">
        <div class="list-item">
          <div class="list-header">
            <strong>Judul Saat Ini</strong>
            <a href="<?= e(appPath('/dashboard/mahasiswa_edit_judul.php')) ?>" class="btn-edit">Edit</a>
          </div>
          <span><?= e((string) (($accountData['judul_ta'] ?? '') !== '' ? $accountData['judul_ta'] : 'Belum ada judul yang tersimpan')) ?></span>
        </div>

        <div class="list-item">
          <strong>Status Tugas Akhir</strong>
          <span><?= e((string) ($dataTA['status_ta'] ?? 'Belum Mengajukan')) ?></span>
        </div>

        <div class="list-item">
          <strong>Dokumen Tugas Akhir</strong>
          <?php if (!empty($dataTA['nama_file']) && !empty($dataTA['file_path'])): ?>
            <span>
              <?= e((string) $dataTA['nama_file']) ?>
              <?php if (!empty($dataTA['tanggal_upload'])): ?>
                — diunggah <?= e((string) $dataTA['tanggal_upload']) ?>
              <?php endif; ?>
            </span>
            <a href="<?= e((string) $dataTA['file_path']) ?>" target="_blank" rel="noopener noreferrer">Buka dokumen</a>
          <?php else: ?>
            <span>Belum ada file tugas akhir yang diunggah.</span>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </div>

  <div class="content-column">
    <section class="info-card">
      <h2>Unggah File Tugas Akhir</h2>
      <p>File akan disimpan pada data tugas akhir Anda dan dibuka melalui Google Drive setelah berhasil diunggah.</p>
      <form action="<?= e(appPath('/dashboard/mahasiswa_upload.php')) ?>" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="jenis_dokumen" value="Tugas Akhir">
        <label class="form-label" for="proposal">Pilih file (PDF, DOC, atau DOCX; maksimal 10 MB)</label>
        <input class="form-input" id="proposal" type="file" name="proposal" accept=".pdf,.doc,.docx" required>
        <div class="form-actions">
          <button class="action-button" type="submit">Unggah dan Buka File</button>
        </div>
      </form>
    </section>
  </div>
</div>

<style>
.list-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
}
.btn-edit {
  display: inline-block;
  padding: 6px 14px;
  border-radius: 8px;
  background: #1d4ed8;
  color: #fff;
  text-decoration: none;
}
.btn-edit:hover {
  background: #1e40af;
  color: #fff;
}
</style>

<?php renderMahasiswaLayoutEnd(); ?>
