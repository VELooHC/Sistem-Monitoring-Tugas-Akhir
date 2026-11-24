<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/dosen_layout.php';

requireLogin(['dosen']);
$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
$sidebarMenus = dosenSidebarMenus('/dashboard/dosen_dashboard.php');
$accountData = fetchLoggedDosen($koneksi, $userId);

if ($accountData === null || empty($accountData['nidn'])) {
    throw new RuntimeException('Data dosen tidak ditemukan.');
}

$nidn = (int) $accountData['nidn'];
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

function fetchDosenAgenda(mysqli $connection, int $nidn, int $userId, string $from, string $until): array
{
    $statement = mysqli_prepare(
        $connection,
        "SELECT agenda.tanggal, agenda.waktu, agenda.jenis, agenda.keterangan, agenda.nama_mahasiswa
         FROM (
             SELECT b.tanggal_bimbingan AS tanggal, b.waktu_bimbingan AS waktu,
                    'Bimbingan' AS jenis, b.agenda AS keterangan, m.nama_lengkap AS nama_mahasiswa
             FROM bimbingan b
             INNER JOIN mahasiswa m ON m.nim = b.nim
             WHERE b.id_dosen = ? AND m.id_dosen = ?
               AND b.tanggal_bimbingan >= ? AND b.tanggal_bimbingan < ?
             UNION ALL
             SELECT DATE(j.tanggal_kegiatan) AS tanggal, TIME(j.tanggal_kegiatan) AS waktu,
                    j.jenis_kegiatan AS jenis, j.ruangan AS keterangan, m.nama_lengkap AS nama_mahasiswa
             FROM jadwal_ta j
             INNER JOIN penguji p ON p.id_jadwal = j.id_jadwal
             INNER JOIN mahasiswa m ON m.nim = j.nim
             WHERE p.id_dosen = ? AND j.tanggal_kegiatan >= ? AND j.tanggal_kegiatan < ?
         ) AS agenda
         ORDER BY agenda.tanggal ASC, agenda.waktu ASC"
    );

    if ($statement === false) {
        throw new RuntimeException('Gagal menyiapkan agenda dosen.');
    }

    mysqli_stmt_bind_param($statement, 'iississ', $nidn, $userId, $from, $until, $userId, $from, $until);
    if (!mysqli_stmt_execute($statement)) {
        throw new RuntimeException('Gagal mengambil agenda dosen.');
    }

    $result = mysqli_stmt_get_result($statement);
    $rows = [];
    if ($result instanceof mysqli_result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }
    mysqli_stmt_close($statement);

    return $rows;
}

$calendarEvents = fetchDosenAgenda(
    $koneksi,
    $nidn,
    $userId,
    $monthStart->format('Y-m-d'),
    $monthEnd->format('Y-m-d')
);
$upcomingEnd = $today->modify('+4 days');
$upcomingEvents = fetchDosenAgenda(
    $koneksi,
    $nidn,
    $userId,
    $today->format('Y-m-d'),
    $upcomingEnd->format('Y-m-d')
);
$now = new DateTimeImmutable();
$upcomingEvents = array_values(array_filter(
    $upcomingEvents,
    static function (array $event) use ($today, $now): bool {
        if ((string) $event['tanggal'] !== $today->format('Y-m-d') || empty($event['waktu'])) {
            return true;
        }

        return new DateTimeImmutable((string) $event['tanggal'] . ' ' . (string) $event['waktu']) >= $now;
    }
));

$calendarByDate = [];
foreach ($calendarEvents as $event) {
    $calendarByDate[(string) $event['tanggal']][] = $event;
}

$summaryStatement = mysqli_prepare(
    $koneksi,
    "SELECT
        (SELECT COUNT(*) FROM mahasiswa WHERE id_dosen = ?) AS jumlah_mahasiswa,
        (SELECT COUNT(*) FROM bimbingan b
         INNER JOIN mahasiswa m ON m.nim = b.nim
         WHERE b.id_dosen = ? AND m.id_dosen = ? AND b.status_review = 'Menunggu Review') AS bimbingan_menunggu,
        (SELECT COUNT(*) FROM dokumen d
         INNER JOIN mahasiswa m ON m.nim = d.nim
         WHERE m.id_dosen = ? AND d.status_persetujuan = 'Menunggu'
           AND d.jenis_dokumen IN ('Seminar Proposal', 'Seminar Hasil', 'Sidang Tugas Akhir')) AS dokumen_menunggu,
        (SELECT COUNT(*) FROM tugas_akhir ta
         INNER JOIN mahasiswa m ON m.nim = ta.nim
         WHERE m.id_dosen = ? AND ta.status_ta = 'Menunggu Persetujuan') AS ta_menunggu"
);
if ($summaryStatement === false) {
    throw new RuntimeException('Gagal menyiapkan statistik bimbingan.');
}

mysqli_stmt_bind_param($summaryStatement, 'iiiii', $userId, $nidn, $userId, $userId, $userId);
if (!mysqli_stmt_execute($summaryStatement)) {
    throw new RuntimeException('Gagal mengambil statistik bimbingan.');
}
$summaryResult = mysqli_stmt_get_result($summaryStatement);
$summary = $summaryResult instanceof mysqli_result ? mysqli_fetch_assoc($summaryResult) : null;
mysqli_stmt_close($summaryStatement);

if ($summary === null) {
    throw new RuntimeException('Statistik bimbingan tidak tersedia.');
}

$pendingReviews = (int) $summary['bimbingan_menunggu']
    + (int) $summary['dokumen_menunggu']
    + (int) $summary['ta_menunggu'];
$monthNames = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];
$previousMonth = $monthStart->modify('-1 month');
$nextMonth = $monthStart->modify('+1 month');
$firstWeekdayOffset = (int) $monthStart->format('N') - 1;
$daysInMonth = (int) $monthStart->format('t');

renderDosenLayoutStart(
    'Dashboard Dosen',
    'Dashboard',
    'Selamat datang di dashboard dosen.',
    $user,
    $sidebarMenus,
    true
);
?>

<div class="grid-two-column">

    <div class="content-column">

        <section class="info-card">
            <h2>Ringkasan Akun</h2>

            <p>Selamat datang,
                <?= e((string) $accountData['nama_lengkap']) ?>.
            </p>

        </section>

        <section class="info-card">
            <h2>Statistik Bimbingan</h2>

            <div class="stats-grid">

                <div class="stat-box">
                    <strong><?= (int) $summary['jumlah_mahasiswa'] ?></strong>
                    <span>Mahasiswa bimbingan aktif</span>
                </div>

                <div class="stat-box">
                    <strong><?= $pendingReviews ?></strong>
                    <span>Pengajuan / bimbingan belum direview</span>
                </div>

            </div>

        </section>

    </div>

    <div class="content-column">

        <section class="info-card">
            <div class="calendar-heading">
                <h2>Kalender Agenda</h2>
                <div class="calendar-navigation">
                    <a href="?month=<?= (int) $previousMonth->format('n') ?>&amp;year=<?= (int) $previousMonth->format('Y') ?>" aria-label="Bulan sebelumnya">&lsaquo;</a>
                    <strong><?= e($monthNames[$selectedMonth] . ' ' . (string) $selectedYear) ?></strong>
                    <a href="?month=<?= (int) $nextMonth->format('n') ?>&amp;year=<?= (int) $nextMonth->format('Y') ?>" aria-label="Bulan berikutnya">&rsaquo;</a>
                </div>
            </div>
            <div class="calendar-grid" role="grid" aria-label="Kalender agenda dosen">
                <?php foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $weekday): ?>
                    <strong class="calendar-weekday"><?= e($weekday) ?></strong>
                <?php endforeach; ?>
                <?php for ($blank = 0; $blank < $firstWeekdayOffset; $blank++): ?>
                    <span class="calendar-day is-empty" aria-hidden="true"></span>
                <?php endfor; ?>
                <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                    <?php
                    $dateKey = $monthStart->setDate($selectedYear, $selectedMonth, $day)->format('Y-m-d');
                    $dayEvents = $calendarByDate[$dateKey] ?? [];
                    ?>
                    <div class="calendar-day<?= $dateKey === $today->format('Y-m-d') ? ' is-today' : '' ?>">
                        <span class="calendar-date"><?= $day ?></span>
                        <?php foreach (array_slice($dayEvents, 0, 2) as $dayEvent): ?>
                            <span class="calendar-event" title="<?= e((string) $dayEvent['jenis'] . ': ' . $dayEvent['nama_mahasiswa']) ?>">
                                <?= e((string) $dayEvent['jenis']) ?>
                            </span>
                        <?php endforeach; ?>
                        <?php if (count($dayEvents) > 2): ?>
                            <span class="calendar-more">+<?= count($dayEvents) - 2 ?> agenda</span>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </section>

        <section class="info-card">

            <h2>Agenda Terdekat</h2>

            <?php if ($upcomingEvents === []): ?>
                <p>Tidak ada agenda dalam tiga hari ke depan.</p>
            <?php else: ?>
                <div class="list-block">
                    <?php foreach ($upcomingEvents as $event): ?>
                        <article class="list-item">
                            <strong><?= e((string) $event['jenis']) ?> — <?= e((string) $event['nama_mahasiswa']) ?></strong>
                            <span><?= e((string) $event['tanggal']) ?><?= !empty($event['waktu']) ? ' · ' . e(substr((string) $event['waktu'], 0, 5)) : '' ?></span>
                            <span><?= e((string) $event['keterangan']) ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    </div>

</div>
<style>
.calendar-heading,
.calendar-navigation {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.calendar-heading h2 {
    margin-bottom: 12px;
}
.calendar-navigation {
    justify-content: flex-end;
    margin-bottom: 12px;
}
.calendar-navigation a {
    color: #2450bf;
    font-size: 1.5rem;
    font-weight: 800;
    text-decoration: none;
}
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 6px;
}
.calendar-weekday {
    padding: 8px 2px;
    color: #67707d;
    text-align: center;
}
.calendar-day {
    min-height: 76px;
    padding: 7px;
    border: 1px solid #e1e6ef;
    border-radius: 10px;
    background: #f8f9fc;
    overflow: hidden;
}
.calendar-day.is-empty {
    border-color: transparent;
    background: transparent;
}
.calendar-day.is-today {
    border-color: #2450bf;
}
.calendar-date {
    display: block;
    margin-bottom: 4px;
    color: #2e3442;
    font-weight: 800;
}
.calendar-event,
.calendar-more {
    display: block;
    overflow: hidden;
    color: #2450bf;
    font-size: .7rem;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.calendar-more {
    color: #67707d;
}
@media (max-width: 640px) {
    .calendar-grid { gap: 3px; }
    .calendar-day { min-height: 58px; padding: 4px; }
    .calendar-event, .calendar-more { font-size: .6rem; }
}
</style>
<?php renderDosenLayoutEnd(); ?>