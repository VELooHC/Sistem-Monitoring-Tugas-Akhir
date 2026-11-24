<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/mahasiswa_layout.php';

requireLogin(['mahasiswa']);

$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
$accountData = fetchLoggedMahasiswa($koneksi, $userId);
$sidebarMenus = mahasiswaSidebarMenus('/dashboard/mahasiswa_seminar.php');

if ($accountData === null) {
    die('Data akun mahasiswa tidak ditemukan.');
}

$nim = (string) $accountData['nim'];
$documents = [];
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT id_dokumen, jenis_dokumen, nama_file, file_path, tanggal_upload,
            status_persetujuan, nilai, catatan
     FROM dokumen
     WHERE nim = ?
       AND jenis_dokumen IN ('Seminar Proposal', 'Seminar Hasil', 'Sidang Tugas Akhir')
     ORDER BY tanggal_upload DESC, id_dokumen DESC"
);

if ($stmt === false) {
    throw new RuntimeException('Gagal menyiapkan daftar dokumen seminar/sidang.');
}

mysqli_stmt_bind_param($stmt, 's', $nim);
if (!mysqli_stmt_execute($stmt)) {
    throw new RuntimeException('Gagal mengambil daftar dokumen seminar/sidang.');
}

$result = mysqli_stmt_get_result($stmt);
if ($result instanceof mysqli_result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $documents[] = $row;
    }
}
mysqli_stmt_close($stmt);

$statusLabel = [
    'Menunggu' => 'Menunggu penilaian',
    'Disetujui' => 'ACC',
    'Ditolak' => 'Ditolak',
    'Revisi' => 'Revisi',
];

renderMahasiswaLayoutStart(
    'Seminar / Sidang',
    'Seminar / Sidang',
    'Unggah dokumen dan pantau status, nilai, serta catatan dosen.',
    $user,
    $sidebarMenus,
    $accountData
);
?>
<div class="content-column">
  <section class="info-card">
    <h2>Unggah Dokumen Seminar / Sidang</h2>
    <p>Dosen pembimbing mendapat akses lihat saja ke file. Penilaian dan catatan revisi diberikan melalui halaman dosen.</p>

    <div class="seminar-upload-grid">
      <?php foreach (['Seminar Proposal', 'Seminar Hasil', 'Sidang Tugas Akhir'] as $jenis): ?>
        <form class="seminar-upload" action="<?= e(appPath('/dashboard/mahasiswa_upload.php')) ?>" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="jenis_dokumen" value="<?= e($jenis) ?>">
          <strong><?= e($jenis) ?></strong>
          <label class="form-label" for="dokumen-<?= e(strtolower(str_replace(' ', '-', $jenis))) ?>">File (PDF, DOC, DOCX; maksimal 10 MB)</label>
          <input class="form-input" id="dokumen-<?= e(strtolower(str_replace(' ', '-', $jenis))) ?>" type="file" name="proposal" accept=".pdf,.doc,.docx" required>
          <button class="action-button" type="submit">Unggah dan Buka File</button>
        </form>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="info-card">
    <h2>Status dan Penilaian</h2>
    <?php if ($documents === []): ?>
      <p>Belum ada dokumen seminar atau sidang yang diunggah.</p>
    <?php else: ?>
      <div class="list-block">
        <?php foreach ($documents as $document): ?>
          <?php
          $status = (string) ($document['status_persetujuan'] ?? 'Menunggu');
          $label = $statusLabel[$status] ?? $status;
          ?>
          <article class="list-item">
            <strong><?= e((string) $document['jenis_dokumen']) ?> — <?= e((string) $document['nama_file']) ?></strong>
            <span>Diunggah: <?= e((string) $document['tanggal_upload']) ?></span>
            <span>Status: <strong><?= e($label) ?></strong></span>
            <span>Nilai dosen: <?= $document['nilai'] !== null ? e((string) $document['nilai']) . ' / 100' : 'Belum dinilai' ?></span>
            <span>Catatan revisi: <?= e((string) (($document['catatan'] ?? '') !== '' ? $document['catatan'] : 'Tidak ada catatan')) ?></span>
            <a href="<?= e((string) $document['file_path']) ?>" target="_blank" rel="noopener noreferrer">Buka dokumen</a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<style>
.seminar-upload-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
  gap: 14px;
}
.seminar-upload {
  display: grid;
  align-content: start;
  gap: 12px;
  padding: 16px;
  border: 1px solid #e1e6ef;
  border-radius: 16px;
  background: #f8f9fc;
}
</style>

<?php renderMahasiswaLayoutEnd(); ?>
