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


    <section class="info-card ta-requirements">
    <h2>Syarat dan Ketentuan Penyusunan Tugas Akhir</h2>

    <p class="ta-intro">
        Berikut merupakan ringkasan persyaratan dan ketentuan penyusunan
        Tugas Akhir berdasarkan Panduan Tugas Akhir Universitas PGRI
        Pontianak Tahun 2026. Pastikan seluruh persyaratan dipenuhi
        sebelum mengajukan setiap tahapan.
    </p>

    <div class="ta-source-note">
        <strong>Perhatian</strong>
        <span>
            Ketentuan dapat memiliki persyaratan tambahan dari Fakultas
            atau Program Studi. Silakan konfirmasi kepada pihak akademik
            untuk memastikan persyaratan terbaru.
        </span>
    </div>

    <!-- A. SYARAT UMUM -->
    <details class="ta-requirement-group" open>
        <summary>
            <span class="ta-step">01</span>
            <span>
                <strong>Persyaratan Umum</strong>
                <small>Ketentuan sebelum menyusun Tugas Akhir</small>
            </span>
        </summary>

        <div class="list-block">
            <div class="list-item">
                <strong>1. Status Akademik</strong>
                <span>Mahasiswa harus berstatus aktif dan telah menyelesaikan minimal 120 SKS untuk program Sarjana (S1), termasuk mata kuliah prasyarat yang ditetapkan Program Studi.</span>
            </div>

            <div class="list-item">
                <strong>2. Indeks Prestasi Kumulatif</strong>
                <span>IPK minimal 2,00 untuk program Sarjana (S1).</span>
            </div>

            <div class="list-item">
                <strong>3. Pengajuan Judul atau Outline</strong>
                <span>Topik Tugas Akhir harus sesuai dengan disiplin ilmu, bidang keahlian, dan peta penelitian Program Studi serta memperoleh persetujuan kelayakan dari Program Studi.</span>
            </div>

            <div class="list-item">
                <strong>4. Bobot dan Waktu Penyelesaian</strong>
                <span>Mata kuliah Tugas Akhir memiliki bobot 6 SKS dan waktu penyelesaian minimal satu semester.</span>
            </div>

            <div class="list-item">
                <strong>5. Bimbingan Tugas Akhir</strong>
                <span>Bimbingan dilakukan secara individual, terstruktur, terjadwal, dan terdokumentasi, dengan minimal 16 kali pertemuan.</span>
            </div>

            <div class="list-item">
                <strong>6. Batas Waktu Penyusunan</strong>
                <span>Jangka waktu penyusunan Tugas Akhir maksimal 12 bulan sejak penetapan SK pembimbing.</span>
            </div>
        </div>
    </details>

    <!-- B. SEMINAR PROPOSAL -->
    <details class="ta-requirement-group">
        <summary>
            <span class="ta-step">02</span>
            <span>
                <strong>Persyaratan Seminar Proposal (Sempro)</strong>
                <small>Persiapan dan administrasi seminar proposal</small>
            </span>
        </summary>

        <div class="list-block">
            <div class="list-item">
                <strong>1. Persetujuan Pembimbing</strong>
                <span>Proposal Tugas Akhir telah disetujui oleh dosen pembimbing dan dibuktikan dengan tanda tangan pada desain penelitian.</span>
            </div>

            <div class="list-item">
                <strong>2. Kartu Tanda Mahasiswa</strong>
                <span>Melampirkan KTM yang masih berlaku.</span>
            </div>

            <div class="list-item">
                <strong>3. Bukti Pembayaran</strong>
                <span>Melampirkan bukti pelunasan SPP/daftar ulang dan biaya seminar.</span>
            </div>

            <div class="list-item">
                <strong>4. Sertifikat Kegiatan</strong>
                <span>Melampirkan sertifikat Pengenalan Etika Kampus (PETIK)/PPS atau Sertifikat Seminar Pendidikan (Karya Tulis Ilmiah).</span>
            </div>

            <div class="list-item">
                <strong>5. Kartu Seminar</strong>
                <span>Melampirkan kartu seminar yang membuktikan kehadiran sebagai peserta minimal 5 kali.</span>
            </div>

            <div class="list-item">
                <strong>6. Bukti Konsultasi</strong>
                <span>Melampirkan buku atau kartu konsultasi dengan dosen pembimbing.</span>
            </div>

            <div class="list-item">
                <strong>7. Keterangan Notulis dan SK Pembimbing</strong>
                <span>Melampirkan keterangan telah menjadi notulis serta Surat Keputusan (SK) pembimbing.</span>
            </div>

            <div class="list-item">
                <strong>8. Dokumen Proposal</strong>
                <span>Menyerahkan proposal yang dilengkapi instrumen, telah ditandatangani pembimbing, dan dijilid sebanyak 7 eksemplar.</span>
            </div>

            <div class="list-item">
                <strong>9. KRS dan Transkrip Nilai</strong>
                <span>Melampirkan KRS Online yang memprogramkan mata kuliah Skripsi/Tugas Akhir dan transkrip nilai.</span>
            </div>

            <div class="list-item">
                <strong>10. Bahan Presentasi</strong>
                <span>Melampirkan hasil cetak bahan presentasi yang telah ditandatangani pembimbing.</span>
            </div>

            <div class="list-item">
                <strong>11. Sertifikat Pelatihan</strong>
                <span>Melampirkan sertifikat pelatihan Bahasa Inggris dan Komputer.</span>
            </div>

            <div class="list-item">
                <strong>12. Persyaratan Tambahan</strong>
                <span>Memenuhi persyaratan tambahan yang ditetapkan oleh masing-masing Fakultas atau Program Studi.</span>
            </div>

            <div class="list-item">
                <strong>13. Literatur Pendukung</strong>
                <span>Membawa minimal 5 literatur pendukung landasan teori dan mempresentasikan proposal menggunakan PowerPoint serta peta konsep.</span>
            </div>
        </div>

        <div class="ta-info-note">
            <strong>Ketentuan Pelaksanaan Sempro</strong>
            <ul>
                <li>Mahasiswa hadir 20 menit sebelum seminar dimulai.</li>
                <li>Mahasiswa wajib memakai jaket almamater.</li>
                <li>Durasi seminar maksimal 90 menit.</li>
                <li>Kesimpulan seminar berupa rekomendasi layak atau tidak layak untuk melanjutkan desain penelitian.</li>
                <li>Mahasiswa tidak diwajibkan menyediakan konsumsi.</li>
            </ul>
        </div>
    </details>

    <!-- C. SEMINAR HASIL -->
    <details class="ta-requirement-group">
        <summary>
            <span class="ta-step">03</span>
            <span>
                <strong>Seminar Hasil (Semhas)</strong>
                <small>Informasi tahapan seminar hasil penelitian</small>
            </span>
        </summary>

        <div class="ta-info-note">
            <strong>Perlu Konfirmasi Program Studi</strong>
            <p>
                Panduan Tugas Akhir Universitas PGRI Pontianak Tahun 2026
                yang menjadi acuan ringkasan ini belum mencantumkan
                persyaratan administrasi Seminar Hasil secara tersendiri.
            </p>
            <p>
                Untuk menghindari informasi yang tidak sesuai, persyaratan
                pendaftaran, dokumen, jumlah bimbingan, dan ketentuan
                pelaksanaan Semhas perlu dikonfirmasi kepada Program Studi
                masing-masing.
            </p>
        </div>
    </details>

    <!-- D. SIDANG TUGAS AKHIR -->
    <details class="ta-requirement-group">
        <summary>
            <span class="ta-step">04</span>
            <span>
                <strong>Persyaratan Sidang Tugas Akhir</strong>
                <small>Ketentuan akademik dan kelengkapan administrasi</small>
            </span>
        </summary>

        <div class="list-block">
            <div class="list-item">
                <strong>1. Persetujuan Tugas Akhir</strong>
                <span>Naskah Tugas Akhir telah disetujui dan ditandatangani oleh dosen pembimbing.</span>
            </div>

            <div class="list-item">
                <strong>2. Kelulusan Mata Kuliah</strong>
                <span>Telah lulus seluruh mata kuliah wajib dan pilihan sesuai kurikulum Program Studi, dengan nilai D maksimal satu dan bukan mata kuliah prasyarat/umum.</span>
            </div>

            <div class="list-item">
                <strong>3. Kartu Tanda Mahasiswa</strong>
                <span>Melampirkan KTM yang masih berlaku.</span>
            </div>

            <div class="list-item">
                <strong>4. Bukti Administrasi Keuangan</strong>
                <span>Melampirkan bukti pelunasan sumbangan gedung, SPP/daftar ulang, biaya ujian Skripsi/Tugas Akhir, dan sumbangan perpustakaan.</span>
            </div>

            <div class="list-item">
                <strong>5. Transkrip Nilai</strong>
                <span>Melampirkan transkrip nilai yang telah dilegalisir oleh Ketua Program Studi.</span>
            </div>

            <div class="list-item">
                <strong>6. Kartu Rencana Studi</strong>
                <span>Melampirkan KRS yang memprogramkan mata kuliah Skripsi/Tugas Akhir.</span>
            </div>

            <div class="list-item">
                <strong>7. Keterangan Notulis</strong>
                <span>Melampirkan keterangan telah menjadi notulis ujian Tugas Akhir.</span>
            </div>

            <div class="list-item">
                <strong>8. Bukti Bimbingan</strong>
                <span>Melampirkan buku atau kartu konsultasi dengan minimal 16 kali pertemuan, dibuktikan dengan tanda tangan atau logbook di SIAKAD.</span>
            </div>

            <div class="list-item">
                <strong>9. Izin Penelitian</strong>
                <span>Melampirkan izin penelitian dari institusi atau Program Studi.</span>
            </div>

            <div class="list-item">
                <strong>10. Bukti Pelaksanaan Penelitian</strong>
                <span>Melampirkan keterangan telah melaksanakan penelitian dari instansi atau sekolah tempat penelitian dilakukan.</span>
            </div>

            <div class="list-item">
                <strong>11. Surat Bebas Perpustakaan</strong>
                <span>Melampirkan surat keterangan bebas perpustakaan.</span>
            </div>

            <div class="list-item">
                <strong>12. Kehadiran Sidang</strong>
                <span>Melampirkan daftar hadir mengikuti sidang ujian Skripsi/Tugas Akhir minimal 5 kali.</span>
            </div>

            <div class="list-item">
                <strong>13. Pas Foto</strong>
                <span>Melampirkan pas foto ukuran 4 × 6 hitam putih untuk pembuatan ijazah.</span>
            </div>

            <div class="list-item">
                <strong>14. Salinan Ijazah Terakhir</strong>
                <span>Melampirkan fotokopi ijazah SMA/sederajat.</span>
            </div>

            <div class="list-item">
                <strong>15. Bahan Presentasi Sidang</strong>
                <span>Melampirkan hasil cetak bahan presentasi yang telah ditandatangani pembimbing sesuai format lembar pengesahan bahan presentasi.</span>
            </div>

            <div class="list-item">
                <strong>16. Literatur Pendukung</strong>
                <span>Membawa literatur yang berkaitan dengan landasan teori Tugas Akhir.</span>
            </div>

            <div class="list-item">
                <strong>17. Bukti Luaran Tugas Akhir</strong>
                <span>Melampirkan bukti luaran sesuai bentuk Tugas Akhir yang diambil. Mekanisme luaran Skripsi diatur lebih lanjut oleh Fakultas.</span>
            </div>

            <div class="list-item">
                <strong>18. Sertifikat TEAP</strong>
                <span>Melampirkan sertifikat Test of English for Academic Purposes (TEAP).</span>
            </div>

            <div class="list-item">
                <strong>19. Bukti Pengecekan Plagiasi</strong>
                <span>Melampirkan surat keterangan pengecekan plagiasi dari Turnitin dengan tingkat kemiripan maksimal 30%, ditandatangani LPPM dan diketahui Ketua Program Studi.</span>
            </div>

            <div class="list-item">
                <strong>20. Persyaratan Tambahan</strong>
                <span>Memenuhi persyaratan tambahan yang ditetapkan Fakultas atau Program Studi.</span>
            </div>
        </div>

        <div class="ta-info-note">
            <strong>Ketentuan Pelaksanaan Sidang</strong>
            <ul>
                <li>Sidang sekurang-kurangnya dihadiri 3 orang penguji, termasuk seorang pembimbing.</li>
                <li>Sidang wajib dihadiri minimal 3 orang penonton.</li>
                <li>Sidang tidak dapat dilaksanakan apabila ketentuan kehadiran tersebut tidak terpenuhi.</li>
                <li>Durasi ujian maksimal 90 menit.</li>
                <li>Mahasiswa hadir 20 menit sebelum sidang dimulai.</li>
                <li>Mahasiswa laki-laki memakai jas, sedangkan mahasiswi memakai kebaya nasional dan sepatu, bukan sandal.</li>
                <li>Mahasiswa tidak diwajibkan menyediakan konsumsi.</li>
            </ul>
        </div>
    </details>

    <!-- E. PASCA SIDANG -->
    <details class="ta-requirement-group">
        <summary>
            <span class="ta-step">05</span>
            <span>
                <strong>Ketentuan Setelah Sidang</strong>
                <small>Revisi, pengesahan, dan pengumpulan Tugas Akhir</small>
            </span>
        </summary>

        <div class="list-block">
            <div class="list-item">
                <strong>1. Revisi Tugas Akhir</strong>
                <span>Mahasiswa yang dinyatakan lulus dengan revisi wajib memperbaiki naskah sesuai masukan penguji dan arahan pembimbing.</span>
            </div>

            <div class="list-item">
                <strong>2. Pengesahan Naskah</strong>
                <span>Naskah yang telah diperbaiki harus disetujui pembimbing, ditandatangani penguji, diketahui Ketua Program Studi, dan disahkan Dekan Fakultas.</span>
            </div>

            <div class="list-item">
                <strong>3. Penjilidan dan Pengumpulan</strong>
                <span>Tugas Akhir dijilid sesuai pedoman dan warna sampul Fakultas masing-masing, kemudian diserahkan kepada perpustakaan, Program Studi, dosen pembimbing, serta tempat pelaksanaan Tugas Akhir.</span>
            </div>

            <div class="list-item">
                <strong>4. Batas Waktu Revisi</strong>
                <span>Jangka waktu perbaikan atau revisi hasil ujian Tugas Akhir maksimal 3 bulan. Apabila tidak diselesaikan dalam waktu tersebut, mahasiswa dinyatakan tidak lulus dan harus mengikuti ujian Tugas Akhir kembali.</span>
            </div>

            <div class="list-item">
                <strong>5. Pengambilan Ijazah</strong>
                <span>Mahasiswa wajib memenuhi seluruh persyaratan akademik, administrasi, dan luaran publikasi sesuai ketentuan yang berlaku untuk pengambilan ijazah.</span>
            </div>
        </div>
    </details>

    <!-- F. CATATAN PENTING -->
    <div class="ta-footer-note">
        <strong>Catatan Penting</strong>
        <p>
            Persyaratan publikasi ilmiah dapat berbeda berdasarkan bentuk
            Tugas Akhir, jalur yang dipilih, angkatan, dan tahun akademik
            kelulusan. Khusus mahasiswa yang lulus mulai Tahun Akademik
            2027/2028, terdapat ketentuan publikasi tambahan sebagaimana
            tercantum dalam Panduan Tugas Akhir.
        </p>
        <p>
            Seluruh informasi di atas merupakan ringkasan panduan dan
            bukan pengganti verifikasi administrasi resmi dari Fakultas
            maupun Program Studi.
        </p>
        <small>
            Sumber: Panduan Tugas Akhir Universitas PGRI Pontianak Tahun 2026.
        </small>
    </div>
</section>
    </div>

    <div class="content-column">
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