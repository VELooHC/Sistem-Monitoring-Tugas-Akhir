<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/admin_layout.php';

requireLogin(['admin']);

$user = currentUser();
$sidebarMenus = adminSidebarMenus('/dashboard/admin_arsip.php');
$nim = trim((string) ($_GET['nim'] ?? ''));
$errorMessage = '';
$student = null;
$documents = [];

if ($nim !== '' && strlen($nim) !== 9) {
    $errorMessage = 'NIM harus terdiri dari 9 karakter.';
} elseif ($nim !== '') {
    $studentStatement = mysqli_prepare(
        $koneksi,
        "SELECT m.nim, m.nama_lengkap, m.judul_ta, m.email,
                d.nama_lengkap AS nama_dosen, latest.status_ta
         FROM mahasiswa m
         INNER JOIN users u ON u.id_user = m.id_user AND u.role = 'mahasiswa'
         LEFT JOIN dosen d ON d.id_user = m.id_dosen
         LEFT JOIN (
             SELECT ta.nim, ta.status_ta
             FROM tugas_akhir ta
             INNER JOIN (
                 SELECT nim, MAX(id_ta) AS id_ta
                 FROM tugas_akhir
                 GROUP BY nim
             ) latest_id ON latest_id.id_ta = ta.id_ta
         ) latest ON latest.nim = m.nim
         WHERE m.nim = ?
         LIMIT 1"
    );
    if ($studentStatement === false) {
        throw new RuntimeException('Gagal menyiapkan pencarian mahasiswa.');
    }
    mysqli_stmt_bind_param($studentStatement, 's', $nim);
    if (!mysqli_stmt_execute($studentStatement)) {
        throw new RuntimeException('Gagal mencari data mahasiswa.');
    }
    $studentResult = mysqli_stmt_get_result($studentStatement);
    $student = $studentResult instanceof mysqli_result ? mysqli_fetch_assoc($studentResult) : null;
    mysqli_stmt_close($studentStatement);

    if ($student === null) {
        $errorMessage = 'Mahasiswa dengan NIM tersebut tidak ditemukan.';
    } else {
        $documentsStatement = mysqli_prepare(
            $koneksi,
            "SELECT archive.id, archive.jenis_dokumen, archive.nama_file,
                    archive.file_path, archive.tanggal_upload, archive.status
             FROM (
                 SELECT d.id_dokumen AS id, d.nim, d.jenis_dokumen, d.nama_file,
                        d.file_path, d.tanggal_upload, d.status_persetujuan AS status
                 FROM dokumen d
                 UNION ALL
                 SELECT ta.id_ta AS id, ta.nim, 'Tugas Akhir' AS jenis_dokumen,
                        ta.nama_file, ta.file_path, ta.tanggal_upload, ta.status_ta AS status
                 FROM tugas_akhir ta
                 WHERE ta.file_path IS NOT NULL AND ta.file_path <> ''
             ) AS archive
             WHERE archive.nim = ?
             ORDER BY archive.tanggal_upload DESC, archive.id DESC"
        );
        if ($documentsStatement === false) {
            throw new RuntimeException('Gagal menyiapkan daftar arsip mahasiswa.');
        }
        mysqli_stmt_bind_param($documentsStatement, 's', $nim);
        if (!mysqli_stmt_execute($documentsStatement)) {
            throw new RuntimeException('Gagal mengambil daftar arsip mahasiswa.');
        }
        $documentsResult = mysqli_stmt_get_result($documentsStatement);
        if ($documentsResult instanceof mysqli_result) {
            while ($row = mysqli_fetch_assoc($documentsResult)) {
                $documents[] = $row;
            }
        }
        mysqli_stmt_close($documentsStatement);
    }
}

function adminArchiveLink(string $filePath): ?string
{
    if (filter_var($filePath, FILTER_VALIDATE_URL) === false) {
        return null;
    }

    $host = strtolower((string) parse_url($filePath, PHP_URL_HOST));
    return in_array($host, ['drive.google.com', 'docs.google.com'], true) ? $filePath : null;
}

renderAdminLayoutStart(
    'Arsip Dokumen',
    'Arsip Dokumen dan Tugas Akhir',
    'Cari dan tinjau seluruh dokumen mahasiswa berdasarkan NIM.',
    $user,
    $sidebarMenus
);
?>
<section class="info-card">
  <h2>Pencarian Arsip</h2>
  <form class="archive-search" method="get">
    <label class="form-label" for="nim">NIM mahasiswa</label>
    <div class="archive-search-row">
      <input class="form-input" id="nim" name="nim" type="search" maxlength="9" pattern="[0-9]{9}" value="<?= e($nim) ?>" placeholder="Masukkan NIM 9 digit" required>
      <button class="action-button" type="submit">Cari Arsip</button>
      <?php if ($nim !== ''): ?><a class="reset-link" href="<?= e(appPath('/dashboard/admin_arsip.php')) ?>">Reset</a><?php endif; ?>
    </div>
  </form>
  <?php if ($errorMessage !== ''): ?>
    <div class="message-box message-error"><?= e($errorMessage) ?></div>
  <?php endif; ?>
</section>

<?php if ($student !== null): ?>
  <section class="info-card">
    <h2>Informasi Mahasiswa</h2>
    <div class="archive-student-grid">
      <div><span>Nama mahasiswa</span><strong><?= e((string) $student['nama_lengkap']) ?></strong></div>
      <div><span>NIM</span><strong><?= e((string) $student['nim']) ?></strong></div>
      <div><span>Program studi</span><strong>Tidak tersedia pada database</strong></div>
      <div><span>Dosen pembimbing</span><strong><?= e((string) ($student['nama_dosen'] ?? 'Belum ditetapkan')) ?></strong></div>
      <div class="wide-row"><span>Judul tugas akhir</span><strong><?= e((string) $student['judul_ta']) ?></strong></div>
      <div><span>Status tugas akhir</span><strong><?= e((string) ($student['status_ta'] ?? 'Belum mengajukan')) ?></strong></div>
    </div>
  </section>

  <section class="info-card">
    <div class="card-heading">
      <h2>Daftar Dokumen</h2>
      <span><?= count($documents) ?> dokumen</span>
    </div>
    <?php if ($documents === []): ?>
      <p>Belum ada dokumen yang tersimpan untuk mahasiswa ini.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="archive-table">
          <thead>
            <tr><th>Jenis Dokumen</th><th>Nama Berkas</th><th>Tanggal Unggah</th><th>Status</th><th>Berkas</th></tr>
          </thead>
          <tbody>
            <?php foreach ($documents as $document): ?>
              <?php $fileUrl = adminArchiveLink((string) ($document['file_path'] ?? '')); ?>
              <tr>
                <td><?= e((string) $document['jenis_dokumen']) ?></td>
                <td><?= e((string) ($document['nama_file'] ?? '')) ?></td>
                <td><?= e((string) ($document['tanggal_upload'] ?? 'Tidak tercatat')) ?></td>
                <td><span class="archive-status"><?= e((string) ($document['status'] ?? 'Tidak tersedia')) ?></span></td>
                <td>
                  <?php if ($fileUrl !== null): ?>
                    <a href="<?= e($fileUrl) ?>" target="_blank" rel="noopener noreferrer">Lihat / unduh</a>
                  <?php else: ?>
                    Tautan tidak tersedia
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
    <p class="form-help">Berkas baru diunggah melalui alur Google Drive mahasiswa yang sudah tersedia. Halaman arsip ini tidak mengubah atau menghapus dokumen.</p>
  </section>
<?php endif; ?>
<style>
.archive-search { display: grid; gap: 8px; max-width: 700px; }
.archive-search-row { display: flex; align-items: center; gap: 10px; }
.archive-search-row .form-input { flex: 1; min-width: 140px; }
.archive-search-row .action-button { white-space: nowrap; }
.reset-link { color: #4d38c7; font-weight: 800; }
.archive-student-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.archive-student-grid > div { padding: 14px; border-radius: 14px; background: #f5f7fb; }
.archive-student-grid span, .archive-student-grid strong { display: block; }
.archive-student-grid span { margin-bottom: 5px; color: #67707d; font-weight: 700; }
.wide-row { grid-column: 1 / -1; }
.card-heading { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.card-heading h2 { margin-bottom: 12px; }
.table-responsive { overflow-x: auto; }
.archive-table { width: 100%; border-collapse: collapse; min-width: 760px; }
.archive-table th, .archive-table td { padding: 12px 10px; border-bottom: 1px solid #e6e9f0; text-align: left; vertical-align: top; }
.archive-table th { background: #f8f9fc; color: #596271; }
.archive-table a { color: #4d38c7; font-weight: 800; }
.archive-status { display: inline-block; padding: 4px 9px; border-radius: 999px; background: #eeeaff; color: #4d38c7; font-size: .82rem; font-weight: 800; }
@media (max-width: 640px) { .archive-search-row { align-items: stretch; flex-wrap: wrap; } .archive-student-grid { grid-template-columns: 1fr; } .wide-row { grid-column: auto; } }
</style>
<?php renderAdminLayoutEnd(); ?>
