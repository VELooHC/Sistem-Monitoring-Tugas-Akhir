<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/mahasiswa_layout.php';

requireLogin(['mahasiswa']);
$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
$sidebarMenus = mahasiswaSidebarMenus('/dashboard/mahasiswa_dashboard.php');
$accountData = fetchLoggedMahasiswa($koneksi, $userId);

if ($accountData === null) {
    $accountData = [
        'nama_lengkap' => (string) ($user['username'] ?? ''),
        'nim' => '-',
        'email' => '',
        'judul_ta' => 'Belum ada data judul tugas akhir.',
    ];
}

$nim = (string) ($accountData['nim'] ?? '');
$today = new DateTimeImmutable('today');
$selectedMonth = (int) ($_GET['month'] ?? $today->format('n'));
$selectedYear = (int) ($_GET['year'] ?? $today->format('Y'));
if ($selectedMonth < 1 || $selectedMonth > 12 || $selectedYear < 2000 || $selectedYear > 2100) {
    $selectedMonth = (int) $today->format('n');
    $selectedYear = (int) $today->format('Y');
}
$monthStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $selectedYear, $selectedMonth));
$monthEnd = $monthStart->modify('+1 month');
$calendarEvents = [];

if ($nim !== '' && $nim !== '-') {
    $calendarStatement = mysqli_prepare(
        $koneksi,
        "SELECT agenda.tanggal, agenda.waktu, agenda.jenis, agenda.keterangan
         FROM (
             SELECT b.tanggal_bimbingan AS tanggal, b.waktu_bimbingan AS waktu,
                    'Bimbingan' AS jenis, b.agenda AS keterangan
             FROM bimbingan b
             WHERE b.nim = ? AND b.tanggal_bimbingan >= ? AND b.tanggal_bimbingan < ?
             UNION ALL
             SELECT DATE(j.tanggal_kegiatan) AS tanggal, TIME(j.tanggal_kegiatan) AS waktu,
                    j.jenis_kegiatan AS jenis, j.ruangan AS keterangan
             FROM jadwal_ta j
             WHERE j.nim = ? AND j.tanggal_kegiatan >= ? AND j.tanggal_kegiatan < ?
         ) AS agenda
         ORDER BY agenda.tanggal ASC, agenda.waktu ASC"
    );
    if ($calendarStatement === false) {
        throw new RuntimeException('Gagal menyiapkan kalender tugas akhir.');
    }

    $monthFrom = $monthStart->format('Y-m-d');
    $monthUntil = $monthEnd->format('Y-m-d');
    mysqli_stmt_bind_param(
        $calendarStatement,
        'ssssss',
        $nim,
        $monthFrom,
        $monthUntil,
        $nim,
        $monthFrom,
        $monthUntil
    );
    if (!mysqli_stmt_execute($calendarStatement)) {
        mysqli_stmt_close($calendarStatement);
        throw new RuntimeException('Gagal mengambil agenda kalender tugas akhir.');
    }
    $calendarResult = mysqli_stmt_get_result($calendarStatement);
    if ($calendarResult instanceof mysqli_result) {
        while ($event = mysqli_fetch_assoc($calendarResult)) {
            $calendarEvents[] = $event;
        }
    }
    mysqli_stmt_close($calendarStatement);
}

$calendarByDate = [];
foreach ($calendarEvents as $event) {
    $calendarByDate[(string) $event['tanggal']][] = $event;
}
$monthNames = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];
$previousMonth = $monthStart->modify('-1 month');
$nextMonth = $monthStart->modify('+1 month');
$firstWeekdayOffset = (int) $monthStart->format('N') - 1;
$daysInMonth = (int) $monthStart->format('t');

$latestGuidance = null;
if ($nim !== '' && $nim !== '-') {
    $guidanceStatement = mysqli_prepare(
        $koneksi,
        'SELECT b.tanggal_bimbingan, b.waktu_bimbingan, b.agenda,
                b.catatan_bimbingan, d.nama_lengkap AS nama_dosen
         FROM bimbingan b
         LEFT JOIN dosen d ON d.nidn = b.id_dosen
         WHERE b.nim = ?
         ORDER BY b.tanggal_bimbingan DESC, b.waktu_bimbingan DESC, b.id_bimbingan DESC
         LIMIT 1'
    );

    if ($guidanceStatement === false) {
        throw new RuntimeException('Gagal menyiapkan data bimbingan terbaru.');
    }

    mysqli_stmt_bind_param($guidanceStatement, 's', $nim);
    if (!mysqli_stmt_execute($guidanceStatement)) {
        throw new RuntimeException('Gagal mengambil data bimbingan terbaru.');
    }

    $guidanceResult = mysqli_stmt_get_result($guidanceStatement);
    $latestGuidance = $guidanceResult instanceof mysqli_result
        ? mysqli_fetch_assoc($guidanceResult)
        : null;
    mysqli_stmt_close($guidanceStatement);
}

renderMahasiswaLayoutStart(
    'Dashboard Mahasiswa',
    'Dashboard',
    'Selamat datang, ' . (string) ($accountData['nama_lengkap'] ?? $user['username'] ?? 'Mahasiswa'),
    $user,
    $sidebarMenus,
    $accountData,
    true
);
?>
        <div class="grid-two-column">
          <div class="content-column">
            <section class="info-card">
              <h2>Kalender TA</h2>
              <div class="calendar-heading">
                <div class="calendar-navigation">
                  <a href="?month=<?= (int) $previousMonth->format('n') ?>&amp;year=<?= (int) $previousMonth->format('Y') ?>" aria-label="Bulan sebelumnya">&lsaquo;</a>
                  <strong><?= e($monthNames[$selectedMonth] . ' ' . (string) $selectedYear) ?></strong>
                  <a href="?month=<?= (int) $nextMonth->format('n') ?>&amp;year=<?= (int) $nextMonth->format('Y') ?>" aria-label="Bulan berikutnya">&rsaquo;</a>
                </div>
              </div>
              <div class="calendar-grid" role="grid" aria-label="Kalender tugas akhir mahasiswa">
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
                      <span class="calendar-event" title="<?= e((string) $dayEvent['jenis'] . ': ' . $dayEvent['keterangan']) ?>">
                        <?= e((string) $dayEvent['jenis']) ?>
                      </span>
                    <?php endforeach; ?>
                    <?php if (count($dayEvents) > 2): ?>
                      <span class="calendar-more">+<?= count($dayEvents) - 2 ?> agenda</span>
                    <?php endif; ?>
                  </div>
                <?php endfor; ?>
              </div>
              <?php if ($calendarEvents === []): ?>
                <p class="calendar-empty">Belum ada agenda tugas akhir pada bulan ini.</p>
              <?php endif; ?>
            </section>

            <section class="info-card">
              <h2>Ringkasan Tugas Akhir</h2>
              <div class="list-block">
                <div class="account-row">
                  <span>NIM</span>
                  <strong><?= e((string) $accountData['nim']) ?></strong>
                </div>
                <div class="account-row">
                  <span>Email</span>
                  <strong><?= e((string) (($accountData['email'] ?? '') !== '' ? $accountData['email'] : 'Belum ditambahkan')) ?></strong>
                </div>
                <div class="account-row">
                  <span>Judul Tugas Akhir</span>
                  <strong><?= e((string) $accountData['judul_ta']) ?></strong>
                </div>
              </div>
            </section>

            <section class="info-card">
              <h2>Menu Fitur Mahasiswa</h2>
              <div class="menu-grid">
                <a class="menu-card" href="<?= e(appPath('/dashboard/mahasiswa_tugas.php')) ?>">
                  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 6H16" stroke-width="1.8" stroke-linecap="round" />
                    <path d="M8 10H16" stroke-width="1.8" stroke-linecap="round" />
                    <path d="M8 14H12" stroke-width="1.8" stroke-linecap="round" />
                    <rect x="4" y="3.5" width="16" height="17" rx="2.5" stroke-width="1.8" />
                  </svg>
                  <span>Pengajuan Judul</span>
                  <small>Kelola pengajuan judul tugas akhir pada halaman tersendiri.</small>
                </a>
                <a class="menu-card" href="<?= e(appPath('/dashboard/mahasiswa_jadwal.php')) ?>">
                  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3.5" y="5" width="17" height="15.5" rx="2.5" stroke-width="1.8" />
                    <path d="M7.5 3.5V7" stroke-width="1.8" stroke-linecap="round" />
                    <path d="M16.5 3.5V7" stroke-width="1.8" stroke-linecap="round" />
                    <path d="M3.5 9H20.5" stroke-width="1.8" />
                  </svg>
                  <span>Jadwal Bimbingan</span>
                  <small>Tambahkan agenda bimbingan dan catatan pertemuan.</small>
                </a>
                <a class="menu-card" href="<?= e(appPath('/dashboard/mahasiswa_seminar.php')) ?>">
                  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 7V12L15 14" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    <circle cx="12" cy="12" r="8.5" stroke-width="1.8" />
                  </svg>
                  <span>Seminar / Sidang</span>
                  <small>Unggah dokumen dan pantau penilaian serta catatan dosen.</small>
                </a>
                <a class="menu-card" href="<?= e(appPath('/dashboard/mahasiswa_profil.php')) ?>">
                  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="8" r="3" stroke-width="1.8" />
                    <path d="M5.5 19C7 15.5 9.3 14 12 14C14.7 14 17 15.5 18.5 19" stroke-width="1.8" stroke-linecap="round" />
                    <rect x="3.5" y="3.5" width="17" height="17" rx="3.5" stroke-width="1.8" />
                  </svg>
                  <span>Profil Mahasiswa</span>
                  <small>Ubah username, password, dan email pada halaman profil khusus.</small>
                </a>
              </div>
            </section>
          </div>

          <div class="content-column">
            <section class="info-card">
              <h2>Info Cepat</h2>
              <div class="quick-grid">
                <a class="quick-box" href="<?= e(appPath('/dashboard/mahasiswa_jadwal.php')) ?>">
                  <strong>Bimbingan Terbaru</strong>
                  <?php if ($latestGuidance !== null): ?>
                    <span><?= e((string) $latestGuidance['tanggal_bimbingan']) ?><?= !empty($latestGuidance['waktu_bimbingan']) ? ' · ' . e(substr((string) $latestGuidance['waktu_bimbingan'], 0, 5)) : '' ?></span>
                    <span><?= e((string) (($latestGuidance['agenda'] ?? '') !== '' ? $latestGuidance['agenda'] : ($latestGuidance['catatan_bimbingan'] ?? 'Agenda bimbingan'))) ?></span>
                    <?php if (!empty($latestGuidance['nama_dosen'])): ?>
                      <span>Dosen: <?= e((string) $latestGuidance['nama_dosen']) ?></span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span>Belum ada agenda bimbingan.</span>
                  <?php endif; ?>
                </a>
              </div>
            </section>

            <section class="info-card">
              <h2>Petunjuk Penggunaan</h2>
              <ul class="list-block">
                <li class="list-item">
                  <strong>Pengajuan Judul</strong>
                  <span>Gunakan halaman ini untuk melihat ringkasan dan langkah pengajuan topik tugas akhir.</span>
                </li>
                <li class="list-item">
                  <strong>Jadwal Bimbingan</strong>
                  <span>Buka halaman jadwal untuk melihat agenda konsultasi dan persiapan bimbingan.</span>
                </li>
                <li class="list-item">
                  <strong>Seminar / Sidang</strong>
                  <span>Lihat status, nilai, dan catatan dosen untuk dokumen seminar atau sidang.</span>
                </li>
                <li class="list-item">
                  <strong>Profil Mahasiswa</strong>
                  <span>Kelola username, password, dan email Anda pada halaman profil mahasiswa.</span>
                </li>
              </ul>
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
            width: 100%;
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
        .calendar-more,
        .calendar-empty {
            color: #67707d;
        }
        @media (max-width: 640px) {
            .calendar-grid { gap: 3px; }
            .calendar-day { min-height: 58px; padding: 4px; }
            .calendar-event, .calendar-more { font-size: .6rem; }
        }
        </style>
<?php renderMahasiswaLayoutEnd(); ?>
