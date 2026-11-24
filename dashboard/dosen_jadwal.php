<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/dosen_layout.php';

requireLogin(['dosen']);

$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
$dosen = fetchLoggedDosen($koneksi, $userId);
$sidebarMenus = dosenSidebarMenus('/dashboard/dosen_jadwal.php');

if ($dosen === null || empty($dosen['nidn'])) {
    throw new RuntimeException('Data dosen tidak ditemukan.');
}

$statement = mysqli_prepare(
    $koneksi,
    "SELECT b.id_bimbingan, b.tanggal_bimbingan, b.waktu_bimbingan, b.agenda,
            b.status_progres, b.status_review, m.nim, m.nama_lengkap, m.judul_ta
     FROM bimbingan b
     INNER JOIN mahasiswa m ON m.nim = b.nim
     WHERE b.id_dosen = ? AND m.id_dosen = ?
     ORDER BY b.tanggal_bimbingan DESC, b.waktu_bimbingan DESC, b.id_bimbingan DESC"
);
if ($statement === false) {
    throw new RuntimeException('Gagal menyiapkan daftar bimbingan.');
}

$nidn = (int) $dosen['nidn'];
mysqli_stmt_bind_param($statement, 'ii', $nidn, $userId);
if (!mysqli_stmt_execute($statement)) {
    throw new RuntimeException('Gagal mengambil daftar bimbingan.');
}

$result = mysqli_stmt_get_result($statement);
$appointments = [];
if ($result instanceof mysqli_result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $appointments[] = $row;
    }
}
mysqli_stmt_close($statement);

renderDosenLayoutStart(
    'Bimbingan',
    'Bimbingan',
    'Daftar agenda bimbingan mahasiswa yang Anda bimbing.',
    $user,
    $sidebarMenus
);
?>
<section class="info-card">
  <h2>Agenda Bimbingan</h2>
  <?php if ($appointments === []): ?>
    <p>Belum ada agenda bimbingan dari mahasiswa bimbingan Anda.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="dosen-table">
        <thead>
          <tr>
            <th>Nama Mahasiswa</th>
            <th>NIM</th>
            <th>Judul Tugas Akhir</th>
            <th>Agenda</th>
            <th>Progress Bimbingan</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($appointments as $appointment): ?>
            <?php
            $detailUrl = appPath('/dashboard/dosen_bimbingan_detail.php')
                . '?id=' . (int) $appointment['id_bimbingan'];
            $progress = (string) ($appointment['status_progres'] ?? '');
            ?>
            <tr class="clickable-row" data-href="<?= e($detailUrl) ?>" tabindex="0">
              <td><a href="<?= e($detailUrl) ?>"><?= e((string) $appointment['nama_lengkap']) ?></a></td>
              <td><?= e((string) $appointment['nim']) ?></td>
              <td><?= e((string) $appointment['judul_ta']) ?></td>
              <td>
                <?= e((string) $appointment['agenda']) ?>
                <small><?= e((string) $appointment['tanggal_bimbingan']) ?><?= !empty($appointment['waktu_bimbingan']) ? ' · ' . e(substr((string) $appointment['waktu_bimbingan'], 0, 5)) : '' ?></small>
              </td>
              <td>
                <span class="status-chip"><?= e($progress !== '' ? $progress : 'Belum ditentukan') ?></span>
                <small><?= e((string) $appointment['status_review']) ?></small>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<style>
.table-responsive { overflow-x: auto; }
.dosen-table { width: 100%; border-collapse: collapse; min-width: 760px; }
.dosen-table th, .dosen-table td { padding: 13px 12px; border-bottom: 1px solid #e8ebf1; text-align: left; vertical-align: top; }
.dosen-table th { background: #f5f7fb; color: #475061; font-size: .88rem; }
.dosen-table td { color: #3d4552; }
.dosen-table td a { color: #2450bf; font-weight: 800; text-decoration: none; }
.dosen-table td small { display: block; margin-top: 5px; color: #67707d; }
.clickable-row { cursor: pointer; }
.clickable-row:hover, .clickable-row:focus { background: #f8fbff; outline: none; }
.status-chip { display: inline-block; padding: 5px 9px; border-radius: 999px; background: #e7f0ff; color: #2450bf; font-size: .8rem; font-weight: 800; }
</style>
<script>
document.querySelectorAll('.clickable-row').forEach(function (row) {
  row.addEventListener('click', function (event) {
    if (event.target.closest('a')) return;
    window.location.href = row.dataset.href;
  });
  row.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      window.location.href = row.dataset.href;
    }
  });
});
</script>
<?php renderDosenLayoutEnd(); ?>
