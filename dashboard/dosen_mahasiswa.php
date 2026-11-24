<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/dosen_layout.php';

requireLogin(['dosen']);

$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
$dosen = fetchLoggedDosen($koneksi, $userId);
$sidebarMenus = dosenSidebarMenus('/dashboard/dosen_mahasiswa.php');

if ($dosen === null || empty($dosen['nidn'])) {
    throw new RuntimeException('Data dosen tidak ditemukan.');
}

$stages = [
    'Bimbingan 1', 'Bimbingan 2', 'Bimbingan 3', 'Bimbingan 4',
    'Seminar Proposal',
    'Bimbingan 5', 'Bimbingan 6', 'Bimbingan 7', 'Bimbingan 8',
    'Seminar Hasil',
    'Bimbingan 9', 'Bimbingan 10',
    'Sidang',
];
$nidn = (int) $dosen['nidn'];
$statement = mysqli_prepare(
    $koneksi,
    "SELECT m.nim, m.nama_lengkap, m.judul_ta,
            latest.status_progres, latest.status_review,
            thesis.status_ta
     FROM mahasiswa m
     LEFT JOIN (
         SELECT b.nim, b.status_progres, b.status_review
         FROM bimbingan b
         INNER JOIN (
             SELECT nim, MAX(id_bimbingan) AS id_bimbingan
             FROM bimbingan
             WHERE id_dosen = ?
             GROUP BY nim
         ) latest_id ON latest_id.id_bimbingan = b.id_bimbingan
     ) latest ON latest.nim = m.nim
     LEFT JOIN (
         SELECT ta.nim, ta.status_ta
         FROM tugas_akhir ta
         INNER JOIN (
             SELECT nim, MAX(id_ta) AS id_ta
             FROM tugas_akhir
             GROUP BY nim
         ) latest_ta ON latest_ta.id_ta = ta.id_ta
     ) thesis ON thesis.nim = m.nim
     WHERE m.id_dosen = ?
     ORDER BY m.nama_lengkap ASC"
);
if ($statement === false) {
    throw new RuntimeException('Gagal menyiapkan data mahasiswa bimbingan.');
}

mysqli_stmt_bind_param($statement, 'ii', $nidn, $userId);
if (!mysqli_stmt_execute($statement)) {
    throw new RuntimeException('Gagal mengambil data mahasiswa bimbingan.');
}
$result = mysqli_stmt_get_result($statement);
$students = [];
if ($result instanceof mysqli_result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $students[] = $row;
    }
}
mysqli_stmt_close($statement);

renderDosenLayoutStart(
    'Data Mahasiswa',
    'Data Mahasiswa',
    'Status dan tahapan penyusunan tugas akhir mahasiswa bimbingan.',
    $user,
    $sidebarMenus
);
?>
<section class="info-card">
  <h2>Progres Penyusunan Tugas Akhir</h2>
  <p class="form-help">Tahapan: bimbingan 1–4, seminar proposal, bimbingan 5–8, seminar hasil, bimbingan 9–10, dan sidang.</p>
  <?php if ($students === []): ?>
    <p>Belum ada mahasiswa yang ditetapkan sebagai mahasiswa bimbingan Anda.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="dosen-table">
        <thead>
          <tr>
            <th>NIM</th>
            <th>Nama Mahasiswa</th>
            <th>Judul Tugas Akhir</th>
            <th>Status</th>
            <th>Progress Tahapan</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($students as $student): ?>
            <?php
            $stage = (string) ($student['status_progres'] ?? '');
            $stageIndex = array_search($stage, $stages, true);
            $progress = $stageIndex === false ? 0 : (int) $stageIndex + 1;
            $progressPercent = (int) round(($progress / count($stages)) * 100);
            $reviewStatus = (string) ($student['status_review'] ?? '');
            $thesisStatus = (string) ($student['status_ta'] ?? '');

            if ($reviewStatus === 'Revisi') {
                $status = 'Perlu revisi';
            } elseif ($reviewStatus === 'Menunggu Review') {
                $status = 'Menunggu review';
            } elseif ($thesisStatus !== '') {
                $status = $thesisStatus;
            } elseif ($stage !== '') {
                $status = 'Dalam proses';
            } else {
                $status = 'Belum memulai';
            }
            ?>
            <tr>
              <td><?= e((string) $student['nim']) ?></td>
              <td><?= e((string) $student['nama_lengkap']) ?></td>
              <td><?= e((string) $student['judul_ta']) ?></td>
              <td><span class="status-chip"><?= e($status) ?></span></td>
              <td>
                <strong><?= e($stage !== '' ? $stage : 'Belum ditentukan') ?></strong>
                <small class="progress-label"><?= $progress ?> dari <?= count($stages) ?> tahapan</small>
                <div class="progress-track" role="progressbar" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="<?= count($stages) ?>">
                  <span style="width: <?= $progressPercent ?>%"></span>
                </div>
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
.status-chip { display: inline-block; padding: 5px 9px; border-radius: 999px; background: #e7f0ff; color: #2450bf; font-size: .8rem; font-weight: 800; }
.progress-label { display: block; margin-top: 5px; color: #67707d; }
.progress-track { height: 7px; min-width: 120px; margin-top: 8px; overflow: hidden; border-radius: 999px; background: #e8ebf1; }
.progress-track span { display: block; height: 100%; border-radius: inherit; background: #2450bf; }
</style>
<?php renderDosenLayoutEnd(); ?>
