<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/activity_log.php';
require_once __DIR__ . '/dosen_layout.php';

requireLogin(['dosen']);

$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
$sidebarMenus = dosenSidebarMenus('/dashboard/dosen_seminar.php');
$message = '';
$messageType = '';
$categories = ['Seminar Proposal', 'Seminar Hasil', 'Sidang Tugas Akhir'];

if (($_GET['status'] ?? '') === 'updated') {
    $message = 'Nilai dan catatan seminar/sidang berhasil disimpan.';
    $messageType = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scheduleId = filter_var($_POST['id_jadwal'] ?? null, FILTER_VALIDATE_INT);
    $scoreInput = trim((string) ($_POST['nilai'] ?? ''));
    $notes = trim((string) ($_POST['catatan'] ?? ''));

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $message = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
        $messageType = 'error';
    } elseif ($scheduleId === false || $scheduleId <= 0 || $scoreInput === ''
        || !is_numeric($scoreInput) || (float) $scoreInput < 0 || (float) $scoreInput > 100) {
        $message = 'Pilih jadwal yang valid dan masukkan nilai antara 0 dan 100.';
        $messageType = 'error';
    } elseif (strlen($notes) > 5000) {
        $message = 'Catatan maksimal 5000 karakter.';
        $messageType = 'error';
    } else {
        $assignment = mysqli_prepare(
            $koneksi,
            'SELECT j.id_jadwal, j.nim, j.jenis_kegiatan
             FROM penguji p
             INNER JOIN jadwal_ta j ON j.id_jadwal = p.id_jadwal
             INNER JOIN mahasiswa m ON m.nim = j.nim
             WHERE j.id_jadwal = ? AND p.id_dosen = ?
               AND (m.id_dosen IS NULL OR m.id_dosen <> ?)
             LIMIT 1'
        );
        if ($assignment === false) {
            throw new RuntimeException('Gagal menyiapkan pemeriksaan penugasan penguji.');
        }
        $scheduleId = (int) $scheduleId;
        mysqli_stmt_bind_param($assignment, 'iii', $scheduleId, $userId, $userId);
        if (!mysqli_stmt_execute($assignment)) {
            throw new RuntimeException('Gagal memeriksa penugasan penguji.');
        }
        $assignmentResult = mysqli_stmt_get_result($assignment);
        $assignedEvent = $assignmentResult instanceof mysqli_result
            ? mysqli_fetch_assoc($assignmentResult)
            : null;
        mysqli_stmt_close($assignment);

        if ($assignedEvent === null) {
            $message = 'Jadwal tidak ditemukan atau Anda bukan penguji yang ditugaskan.';
            $messageType = 'error';
        } else {
            $score = number_format((float) $scoreInput, 2, '.', '');

            // Simpan nilai dan sinkronkan hasil akhir dokumen dalam satu transaksi.
            mysqli_begin_transaction($koneksi);

            try {
                $save = mysqli_prepare(
                    $koneksi,
                    'INSERT INTO penilaian_seminar (id_jadwal, id_dosen, nilai, catatan)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE nilai = VALUES(nilai), catatan = VALUES(catatan)'
                );

                if ($save === false) {
                    throw new RuntimeException('Gagal menyiapkan penyimpanan nilai seminar/sidang.');
                }

                mysqli_stmt_bind_param($save, 'iiss', $scheduleId, $userId, $score, $notes);

                if (!mysqli_stmt_execute($save)) {
                    $error = mysqli_stmt_error($save);
                    mysqli_stmt_close($save);
                    throw new RuntimeException('Gagal menyimpan nilai: ' . $error);
                }

                mysqli_stmt_close($save);

                // Hitung jumlah penguji yang ditugaskan dan jumlah yang sudah menilai.
                // DISTINCT mencegah satu dosen yang tercatat lebih dari sekali dihitung ganda.
                $summary = mysqli_prepare(
                    $koneksi,
                    'SELECT
                        COUNT(DISTINCT p.id_dosen) AS jumlah_penguji,
                        COUNT(DISTINCT ps.id_dosen) AS jumlah_penilai,
                        AVG(ps.nilai) AS rata_rata
                     FROM penguji p
                     LEFT JOIN penilaian_seminar ps
                       ON ps.id_jadwal = p.id_jadwal
                      AND ps.id_dosen = p.id_dosen
                     WHERE p.id_jadwal = ?'
                );

                if ($summary === false) {
                    throw new RuntimeException('Gagal menyiapkan perhitungan nilai akhir.');
                }

                mysqli_stmt_bind_param($summary, 'i', $scheduleId);

                if (!mysqli_stmt_execute($summary)) {
                    $error = mysqli_stmt_error($summary);
                    mysqli_stmt_close($summary);
                    throw new RuntimeException('Gagal menghitung nilai akhir: ' . $error);
                }

                $summaryResult = mysqli_stmt_get_result($summary);
                $scoreSummary = $summaryResult instanceof mysqli_result
                    ? mysqli_fetch_assoc($summaryResult)
                    : null;

                if ($summaryResult instanceof mysqli_result) {
                    mysqli_free_result($summaryResult);
                }
                mysqli_stmt_close($summary);

                $assignedCount = (int) ($scoreSummary['jumlah_penguji'] ?? 0);
                $gradedCount = (int) ($scoreSummary['jumlah_penilai'] ?? 0);

                // Status dan nilai dokumen baru ditetapkan jika semua penguji sudah mengisi.
                if ($assignedCount > 0 && $gradedCount === $assignedCount) {
                    $average = (float) $scoreSummary['rata_rata'];

                    if ($average >= 70) {
                        $documentStatus = 'Lulus';
                    } elseif ($average >= 50) {
                        $documentStatus = 'Revisi';
                    } else {
                        $documentStatus = 'Mengulang';
                    }

                    $updateDocument = mysqli_prepare(
                        $koneksi,
                        'UPDATE dokumen
                         SET nilai = ?, status_persetujuan = ?
                         WHERE id_dokumen = (
                             SELECT id_dokumen FROM (
                                 SELECT id_dokumen
                                 FROM dokumen
                                 WHERE nim = ? AND jenis_dokumen = ?
                                 ORDER BY id_dokumen DESC
                                 LIMIT 1
                             ) AS latest_document
                         )'
                    );

                    if ($updateDocument === false) {
                        throw new RuntimeException('Gagal menyiapkan pembaruan status dokumen.');
                    }

                    $studentNim = (string) $assignedEvent['nim'];
                    $activityType = (string) $assignedEvent['jenis_kegiatan'];
                    mysqli_stmt_bind_param(
                        $updateDocument,
                        'dsss',
                        $average,
                        $documentStatus,
                        $studentNim,
                        $activityType
                    );

                    if (!mysqli_stmt_execute($updateDocument)) {
                        $error = mysqli_stmt_error($updateDocument);
                        mysqli_stmt_close($updateDocument);
                        throw new RuntimeException('Gagal memperbarui status dokumen: ' . $error);
                    }

                    mysqli_stmt_close($updateDocument);
                }

                mysqli_commit($koneksi);

                recordActivity(
                    $koneksi,
                    $userId,
                    'Penilaian seminar / sidang',
                    (string) $assignedEvent['jenis_kegiatan'] . ' · NIM ' . (string) $assignedEvent['nim']
                        . ' · nilai ' . $score
                        . ($assignedCount > 0 && $gradedCount === $assignedCount
                            ? ' · nilai akhir ' . number_format((float) $scoreSummary['rata_rata'], 2)
                                . ' · status ' . $documentStatus
                            : ' · menunggu penilaian penguji lain')
                );

                redirectDosenPage('/dashboard/dosen_seminar.php', ['status' => 'updated']);
            } catch (Throwable $exception) {
                mysqli_rollback($koneksi);
                error_log('Simpan penilaian seminar: ' . $exception->getMessage());
                $message = 'Penilaian gagal diproses. Silakan coba lagi.';
                $messageType = 'error';
            }
        }
    }
}

$statement = mysqli_prepare(
    $koneksi,
    "SELECT j.id_jadwal, j.nim, j.jenis_kegiatan, j.tanggal_kegiatan, j.ruangan,
            p.peran, m.nama_lengkap, m.judul_ta,
            doc.nama_file, doc.file_path,
            mine.nilai AS nilai_saya, mine.catatan AS catatan_saya,
            scores.rata_rata, scores.jumlah_penilai,
            assignments.jumlah_penguji
     FROM (
         SELECT id_jadwal, id_dosen, MIN(peran) AS peran
         FROM penguji
         GROUP BY id_jadwal, id_dosen
     ) p
     INNER JOIN jadwal_ta j ON j.id_jadwal = p.id_jadwal
     INNER JOIN mahasiswa m ON m.nim = j.nim
     LEFT JOIN dokumen doc ON doc.id_dokumen = (
         SELECT d.id_dokumen
         FROM dokumen d
         WHERE d.nim = j.nim AND d.jenis_dokumen = j.jenis_kegiatan
         ORDER BY d.id_dokumen DESC
         LIMIT 1
     )
     LEFT JOIN penilaian_seminar mine
       ON mine.id_jadwal = j.id_jadwal AND mine.id_dosen = p.id_dosen
     LEFT JOIN (
         SELECT id_jadwal, AVG(nilai) AS rata_rata, COUNT(nilai) AS jumlah_penilai
         FROM penilaian_seminar
         GROUP BY id_jadwal
     ) scores ON scores.id_jadwal = j.id_jadwal
     LEFT JOIN (
         SELECT id_jadwal, COUNT(DISTINCT id_dosen) AS jumlah_penguji
         FROM penguji
         GROUP BY id_jadwal
     ) assignments ON assignments.id_jadwal = j.id_jadwal
     WHERE p.id_dosen = ?
       AND (m.id_dosen IS NULL OR m.id_dosen <> ?)
     ORDER BY j.tanggal_kegiatan DESC, j.id_jadwal DESC"
);
if ($statement === false) {
    throw new RuntimeException('Gagal menyiapkan daftar penilaian seminar/sidang.');
}
mysqli_stmt_bind_param($statement, 'ii', $userId, $userId);
if (!mysqli_stmt_execute($statement)) {
    throw new RuntimeException('Gagal mengambil daftar penilaian seminar/sidang.');
}
$result = mysqli_stmt_get_result($statement);
$eventsByCategory = array_fill_keys($categories, []);
if ($result instanceof mysqli_result) {
    while ($row = mysqli_fetch_assoc($result)) {
        if (isset($eventsByCategory[$row['jenis_kegiatan']])) {
            $eventsByCategory[$row['jenis_kegiatan']][] = $row;
        }
    }
}
mysqli_stmt_close($statement);

function gradeFromAverage(?float $average): string
{
    if ($average === null) {
        return 'Belum dinilai';
    }
    if ($average >= 80) {
        return 'A';
    }
    if ($average >= 70) {
        return 'B';
    }
    if ($average >= 60) {
        return 'C';
    }
    if ($average >= 50) {
        return 'D';
    }

    return 'E';
}

function resultFromGrade(string $grade): string
{
    if ($grade === 'A' || $grade === 'B') {
        return 'Lulus';
    }
    if ($grade === 'C' || $grade === 'D') {
        return 'Revisi';
    }
    if ($grade === 'E') {
        return 'Buat ulang / ganti judul';
    }

    return 'Menunggu nilai';
}

renderDosenLayoutStart(
    'Seminar / Sidang',
    'Seminar / Sidang',
    'Beri nilai dan catatan untuk mahasiswa pada jadwal yang menugaskan Anda sebagai penguji.',
    $user,
    $sidebarMenus
);
?>
<?php if ($message !== ''): ?>
  <div class="message-box message-<?= e($messageType) ?>"><?= e($message) ?></div>
<?php endif; ?>
<p class="form-help">Nilai akhir merupakan rata-rata nilai dari para penguji pada jadwal yang sama. Grade: A ≥ 80, B ≥ 70, C ≥ 60, D ≥ 50, E &lt; 50.</p>

<?php foreach ($categories as $category): ?>
  <section class="info-card">
    <h2><?= e($category === 'Sidang Tugas Akhir' ? 'Sidang Skripsi' : $category) ?></h2>
    <?php if ($eventsByCategory[$category] === []): ?>
      <p>Tidak ada mahasiswa yang ditugaskan kepada Anda pada kategori ini.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="dosen-table">
          <thead>
            <tr>
              <th>Mahasiswa</th>
              <th>Jadwal</th>
              <th>Dokumen</th>
              <th>Nilai Rata-rata</th>
              <th>Grade / Hasil</th>
              <th>Penilaian Anda</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($eventsByCategory[$category] as $event): ?>
              <?php
              $average = $event['rata_rata'] === null ? null : (float) $event['rata_rata'];
              $jumlahPenilai = (int) ($event['jumlah_penilai'] ?? 0);
              $jumlahPenguji = (int) ($event['jumlah_penguji'] ?? 0);
              $allGraded = $jumlahPenguji > 0 && $jumlahPenilai === $jumlahPenguji;
              $grade = $allGraded ? gradeFromAverage($average) : 'Menunggu';
              $outcome = $allGraded ? resultFromGrade($grade) : 'Menunggu penilaian (' . $jumlahPenilai . '/' . $jumlahPenguji . ')';
              ?>
              <tr>
                <td>
                  <strong><?= e((string) $event['nama_lengkap']) ?></strong>
                  <small>NIM: <?= e((string) $event['nim']) ?></small>
                  <small><?= e((string) $event['judul_ta']) ?></small>
                </td>
                <td>
                  <?= e((string) $event['tanggal_kegiatan']) ?>
                  <small><?= e((string) $event['ruangan']) ?> · <?= e((string) $event['peran']) ?></small>
                </td>
                <td>
                  <?php if (!empty($event['file_path'])): ?>
                    <a href="<?= e((string) $event['file_path']) ?>" target="_blank" rel="noopener noreferrer"><?= e((string) $event['nama_file']) ?></a>
                  <?php else: ?>
                    Belum ada dokumen
                  <?php endif; ?>
                </td>
                <td>
                  <?= $allGraded && $average !== null
                      ? e(number_format($average, 2, ',', '.')) . ' / 100'
                      : ($average !== null ? 'Rata-rata sementara: ' . e(number_format($average, 2, ',', '.')) : 'Belum dinilai') ?>
                  <small><?= $jumlahPenilai ?>/<?= $jumlahPenguji ?> penguji memberi nilai</small>
                </td>
                <td>
                  <strong><?= e($grade) ?></strong>
                  <small><?= e($outcome) ?></small>
                </td>
                <td>
                  <form class="score-form" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="id_jadwal" value="<?= (int) $event['id_jadwal'] ?>">
                    <label class="form-label">
                      Nilai (0–100)
                      <input class="form-input" type="number" name="nilai" min="0" max="100" step="0.01" value="<?= $event['nilai_saya'] !== null ? e((string) $event['nilai_saya']) : '' ?>" required>
                    </label>
                    <label class="form-label">
                      Catatan hasil
                      <textarea class="form-textarea" name="catatan" maxlength="5000"><?= e((string) ($event['catatan_saya'] ?? '')) ?></textarea>
                    </label>
                    <button class="action-button" type="submit">Simpan Nilai</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
<style>
.table-responsive { overflow-x: auto; }
.dosen-table { width: 100%; border-collapse: collapse; min-width: 1050px; }
.dosen-table th, .dosen-table td { padding: 13px 12px; border-bottom: 1px solid #e8ebf1; text-align: left; vertical-align: top; }
.dosen-table th { background: #f5f7fb; color: #475061; font-size: .88rem; }
.dosen-table td small { display: block; margin-top: 5px; color: #67707d; }
.score-form { display: grid; min-width: 210px; gap: 9px; }
.score-form .form-textarea { min-height: 72px; }
.score-form .action-button { justify-self: start; padding: 9px 16px; }
</style>
<?php renderDosenLayoutEnd(); ?>
