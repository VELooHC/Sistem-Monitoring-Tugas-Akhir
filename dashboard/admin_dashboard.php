<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/academic_events.php';
require_once __DIR__ . '/admin_layout.php';

requireLogin(['admin']);

$user = currentUser();
$sidebarMenus = adminSidebarMenus('/dashboard/admin_dashboard.php');
$today = new DateTimeImmutable('today');
$monthStart = $today->modify('first day of this month');
$monthEnd = $monthStart->modify('+1 month');
$selectedMonth = (int) ($_GET['month'] ?? $today->format('n'));
$selectedYear = (int) ($_GET['year'] ?? $today->format('Y'));

if ($selectedMonth < 1 || $selectedMonth > 12 || $selectedYear < 2000 || $selectedYear > 2100) {
    $selectedMonth = (int) $today->format('n');
    $selectedYear = (int) $today->format('Y');
}
$monthStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $selectedYear, $selectedMonth));
$monthEnd = $monthStart->modify('+1 month');
$monthEvents = fetchAcademicEvents($koneksi, $monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d'));
$upcomingStart = $today;
$upcomingEnd = $today->modify('+15 days');
$upcomingEvents = fetchAcademicEvents($koneksi, $upcomingStart->format('Y-m-d'), $upcomingEnd->format('Y-m-d'));

$summaryResult = mysqli_query(
    $koneksi,
    "SELECT
        (SELECT COUNT(*) FROM mahasiswa) AS total_mahasiswa,
        (SELECT COUNT(*) FROM dosen) AS total_dosen,
        (SELECT COUNT(DISTINCT ta.nim) FROM tugas_akhir ta
         WHERE NOT EXISTS (
             SELECT 1 FROM dokumen final_doc
             WHERE final_doc.nim = ta.nim
               AND final_doc.jenis_dokumen = 'Skripsi'
               AND final_doc.status_persetujuan = 'Disetujui'
         )) AS ta_berjalan,
        (SELECT COUNT(DISTINCT d.nim) FROM dokumen d
         WHERE d.jenis_dokumen = 'Skripsi' AND d.status_persetujuan = 'Disetujui') AS ta_selesai,
        ((SELECT COUNT(*) FROM dokumen)
         + (SELECT COUNT(*) FROM tugas_akhir WHERE file_path IS NOT NULL AND file_path <> '')) AS total_dokumen,
        ((SELECT COUNT(*) FROM dokumen WHERE status_persetujuan = 'Menunggu')
         + (SELECT COUNT(*) FROM tugas_akhir WHERE status_ta = 'Menunggu Persetujuan')) AS menunggu_verifikasi,
        (SELECT COUNT(DISTINCT b.nim) FROM bimbingan b
         WHERE NOT EXISTS (
             SELECT 1 FROM dokumen final_doc
             WHERE final_doc.nim = b.nim
               AND final_doc.jenis_dokumen = 'Skripsi'
               AND final_doc.status_persetujuan = 'Disetujui'
         )) AS masih_bimbingan,
        (SELECT COUNT(DISTINCT pending.nim) FROM (
             SELECT d.nim FROM dokumen d WHERE d.status_persetujuan = 'Menunggu'
             UNION
             SELECT ta.nim FROM tugas_akhir ta WHERE ta.status_ta = 'Menunggu Persetujuan'
         ) AS pending) AS menunggu_tahap"
);
if ($summaryResult === false) {
    throw new RuntimeException('Gagal mengambil ringkasan dashboard admin.');
}
$summary = mysqli_fetch_assoc($summaryResult);
mysqli_free_result($summaryResult);
if ($summary === null) {
    throw new RuntimeException('Ringkasan dashboard admin tidak tersedia.');
}

$recentDocumentsResult = mysqli_query(
    $koneksi,
    "SELECT incoming.nim, incoming.jenis_dokumen, incoming.nama_file,
            incoming.file_path, incoming.waktu_upload, m.nama_lengkap
     FROM (
         SELECT id_dokumen AS id, nim, jenis_dokumen, nama_file,
                file_path, tanggal_upload AS waktu_upload
         FROM dokumen
         UNION ALL
         SELECT id_ta AS id, nim, 'Tugas Akhir' AS jenis_dokumen, nama_file,
                file_path, tanggal_upload AS waktu_upload
         FROM tugas_akhir
         WHERE file_path IS NOT NULL AND file_path <> ''
     ) AS incoming
     INNER JOIN mahasiswa m ON m.nim = incoming.nim
     ORDER BY incoming.waktu_upload DESC, incoming.id DESC
     LIMIT 8"
);
if ($recentDocumentsResult === false) {
    throw new RuntimeException('Gagal mengambil dokumen terbaru.');
}
$recentDocuments = [];
while ($row = mysqli_fetch_assoc($recentDocumentsResult)) {
    $recentDocuments[] = $row;
}
mysqli_free_result($recentDocumentsResult);

$recentLogsResult = mysqli_query(
    $koneksi,
    "SELECT l.aktivitas, l.keterangan, l.tanggal, u.username, u.role,
            COALESCE(m.nama_lengkap, d.nama_lengkap, u.username) AS nama_pengguna
     FROM log_aktivitas l
     INNER JOIN users u ON u.id_user = l.id_user
     LEFT JOIN mahasiswa m ON m.id_user = u.id_user
     LEFT JOIN dosen d ON d.id_user = u.id_user
     WHERE l.tanggal >= NOW() - INTERVAL 1 DAY
     ORDER BY l.tanggal DESC, l.id_log DESC
     LIMIT 8"
);
if ($recentLogsResult === false) {
    throw new RuntimeException('Gagal mengambil aktivitas terbaru.');
}
$recentLogs = [];
while ($row = mysqli_fetch_assoc($recentLogsResult)) {
    $recentLogs[] = $row;
}
mysqli_free_result($recentLogsResult);

$eventsByDate = [];
foreach ($monthEvents as $event) {
    $eventDate = (string) $event['starts_at'];
    $eventsByDate[substr($eventDate, 0, 10)][] = $event;
}
$upcomingEvents = array_values(array_filter(
    $upcomingEvents,
    static function (array $event) use ($today): bool {
        $eventDate = substr((string) $event['starts_at'], 0, 10);
        $dateTime = new DateTimeImmutable((string) $event['starts_at']);
        if ($eventDate === $today->format('Y-m-d') && (int) $event['has_time'] === 0) {
            return true;
        }

        return $dateTime >= new DateTimeImmutable()
            && $dateTime < $today->modify('+15 days');
    }
));
$upcomingEvents = array_slice($upcomingEvents, 0, 5);
$monthNames = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];
$previousMonth = $monthStart->modify('-1 month');
$nextMonth = $monthStart->modify('+1 month');
$firstWeekdayOffset = (int) $monthStart->format('N') - 1;
$daysInMonth = (int) $monthStart->format('t');
$completionTotal = (int) $summary['ta_berjalan'] + (int) $summary['ta_selesai'];
$completionPercent = $completionTotal > 0
    ? (int) round(((int) $summary['ta_selesai'] / $completionTotal) * 100)
    : 0;

renderAdminLayoutStart(
    'Dashboard Admin',
    'Dashboard Admin',
    'Pusat informasi dan monitoring proses tugas akhir.',
    $user,
    $sidebarMenus,
    true
);
?>
<section class="info-card">
  <h2>Informasi Cepat</h2>
  <div class="admin-stat-grid">
    <div class="stat-box"><strong><?= (int) $summary['total_mahasiswa'] ?></strong><span>Total mahasiswa</span></div>
    <div class="stat-box"><strong><?= (int) $summary['total_dosen'] ?></strong><span>Dosen pembimbing</span></div>
    <div class="stat-box"><strong><?= (int) $summary['ta_berjalan'] ?></strong><span>Tugas akhir berjalan</span></div>
    <div class="stat-box"><strong><?= (int) $summary['ta_selesai'] ?></strong><span>Tugas akhir selesai</span></div>
    <div class="stat-box"><strong><?= (int) $summary['total_dokumen'] ?></strong><span>Total dokumen</span></div>
    <div class="stat-box"><strong><?= (int) $summary['menunggu_verifikasi'] ?></strong><span>Menunggu verifikasi</span></div>
  </div>
</section>

<div class="grid-two-column">
  <section class="info-card">
    <div class="calendar-heading">
      <h2>Kalender Ringkas</h2>
      <div class="calendar-navigation">
        <a href="?month=<?= (int) $previousMonth->format('n') ?>&amp;year=<?= (int) $previousMonth->format('Y') ?>" aria-label="Bulan sebelumnya">&lsaquo;</a>
        <strong><?= e($monthNames[$selectedMonth] . ' ' . (string) $selectedYear) ?></strong>
        <a href="?month=<?= (int) $nextMonth->format('n') ?>&amp;year=<?= (int) $nextMonth->format('Y') ?>" aria-label="Bulan berikutnya">&rsaquo;</a>
      </div>
    </div>
    <div class="mini-calendar">
      <?php foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $weekday): ?>
        <strong class="calendar-weekday"><?= e($weekday) ?></strong>
      <?php endforeach; ?>
      <?php for ($blank = 0; $blank < $firstWeekdayOffset; $blank++): ?>
        <span class="calendar-day is-empty" aria-hidden="true"></span>
      <?php endfor; ?>
      <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
        <?php $dateKey = $monthStart->setDate($selectedYear, $selectedMonth, $day)->format('Y-m-d'); ?>
        <div class="calendar-day<?= $dateKey === $today->format('Y-m-d') ? ' is-today' : '' ?><?= isset($eventsByDate[$dateKey]) ? ' has-event' : '' ?>">
          <span><?= $day ?></span>
          <?php if (isset($eventsByDate[$dateKey])): ?>
            <small><?= count($eventsByDate[$dateKey]) ?> kegiatan</small>
          <?php endif; ?>
        </div>
      <?php endfor; ?>
    </div>
    <a class="calendar-full-link" href="<?= e(appPath('/dashboard/admin_jadwal.php')) ?>">Buka kalender lengkap</a>
  </section>

  <section class="info-card">
    <h2>Agenda Terdekat</h2>
    <?php if ($upcomingEvents === []): ?>
      <p>Tidak ada agenda dalam 14 hari ke depan.</p>
    <?php else: ?>
      <div class="list-block">
        <?php foreach ($upcomingEvents as $event): ?>
          <article class="list-item">
            <strong><?= e((string) $event['title']) ?> — <?= e((string) $event['student_name']) ?></strong>
            <span><?= e(date('d/m/Y H:i', strtotime((string) $event['starts_at']))) ?> · <?= e((string) $event['category']) ?></span>
            <span><?= e((string) ($event['location'] !== '' ? $event['location'] : 'Lokasi tidak tersedia')) ?></span>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<div class="grid-two-column">
  <section class="info-card">
    <div class="card-heading">
      <h2>Dokumen Terbaru</h2>
      <a href="<?= e(appPath('/dashboard/admin_arsip.php')) ?>">Cari arsip</a>
    </div>
    <?php if ($recentDocuments === []): ?>
      <p>Belum ada dokumen yang diunggah.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="admin-table">
          <thead><tr><th>Mahasiswa</th><th>NIM</th><th>Jenis</th><th>Waktu</th><th>Detail</th></tr></thead>
          <tbody>
          <?php foreach ($recentDocuments as $document): ?>
            <tr>
              <td><?= e((string) $document['nama_lengkap']) ?></td>
              <td><?= e((string) $document['nim']) ?></td>
              <td><?= e((string) $document['jenis_dokumen']) ?></td>
              <td><?= e((string) ($document['waktu_upload'] ?? '-')) ?></td>
              <td><a href="<?= e(appPath('/dashboard/admin_arsip.php')) ?>?nim=<?= rawurlencode((string) $document['nim']) ?>">Lihat arsip</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="info-card">
    <div class="card-heading">
      <h2>Aktivitas 24 Jam Terakhir</h2>
      <a href="<?= e(appPath('/dashboard/admin_log.php')) ?>">Semua aktivitas</a>
    </div>
    <?php if ($recentLogs === []): ?>
      <p>Belum ada aktivitas tercatat dalam 24 jam terakhir.</p>
    <?php else: ?>
      <div class="list-block">
        <?php foreach ($recentLogs as $log): ?>
          <article class="list-item">
            <strong><?= e((string) $log['aktivitas']) ?> · <?= e((string) $log['nama_pengguna']) ?></strong>
            <span><?= e((string) $log['role']) ?> · <?= e((string) $log['tanggal']) ?></span>
            <span><?= e((string) ($log['keterangan'] ?? '')) ?></span>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<section class="info-card">
  <h2>Perkembangan Penyelesaian Tugas Akhir</h2>
  <div class="completion-summary">
    <div><strong><?= (int) $summary['ta_berjalan'] ?></strong><span>Sedang mengerjakan</span></div>
    <div><strong><?= (int) $summary['ta_selesai'] ?></strong><span>Selesai (skripsi disetujui)</span></div>
    <div><strong><?= (int) $summary['masih_bimbingan'] ?></strong><span>Dalam proses bimbingan</span></div>
    <div><strong><?= (int) $summary['menunggu_tahap'] ?></strong><span>Menunggu tahapan / verifikasi</span></div>
  </div>
  <div class="completion-track" role="progressbar" aria-label="Persentase tugas akhir selesai" aria-valuenow="<?= $completionPercent ?>" aria-valuemin="0" aria-valuemax="100">
    <span style="width: <?= $completionPercent ?>%"></span>
  </div>
  <p class="form-help"><?= $completionPercent ?>% dari mahasiswa yang sudah memiliki data tugas akhir mempunyai skripsi berstatus disetujui.</p>
</section>
<style>
.admin-stat-grid, .completion-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.admin-stat-grid .stat-box { min-height: 100px; }
.calendar-heading, .card-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.calendar-heading h2, .card-heading h2 { margin-bottom: 12px; }
.calendar-navigation { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.calendar-navigation a { color: #4d38c7; font-size: 1.4rem; font-weight: 800; text-decoration: none; }
.mini-calendar { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 5px; }
.calendar-weekday { padding: 6px 2px; color: #67707d; text-align: center; }
.calendar-day { min-height: 54px; padding: 6px; border: 1px solid #e1e6ef; border-radius: 9px; background: #f8f9fc; text-align: center; }
.calendar-day.is-empty { border-color: transparent; background: transparent; }
.calendar-day.is-today { border-color: #4d38c7; }
.calendar-day.has-event { background: #f0edff; color: #4d38c7; font-weight: 800; }
.calendar-day small { display: block; font-size: .65rem; }
.calendar-full-link { display: inline-block; margin-top: 12px; color: #4d38c7; font-weight: 800; }
.card-heading a, .admin-table a { color: #4d38c7; font-weight: 800; }
.table-responsive { overflow-x: auto; }
.admin-table { width: 100%; border-collapse: collapse; min-width: 600px; }
.admin-table th, .admin-table td { padding: 11px 9px; border-bottom: 1px solid #e6e9f0; text-align: left; vertical-align: top; }
.admin-table th { background: #f8f9fc; color: #596271; }
.completion-summary { margin: 8px 0 16px; }
.completion-summary div { padding: 14px; border-radius: 14px; background: #f5f7fb; }
.completion-summary strong, .completion-summary span { display: block; }
.completion-summary strong { font-size: 1.3rem; color: #2e3442; }
.completion-summary span { margin-top: 4px; color: #67707d; font-weight: 700; }
.completion-track { height: 10px; overflow: hidden; border-radius: 999px; background: #e8ebf1; }
.completion-track span { display: block; height: 100%; border-radius: inherit; background: #4d38c7; }
@media (max-width: 700px) { .admin-stat-grid, .completion-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
<?php renderAdminLayoutEnd(); ?>
