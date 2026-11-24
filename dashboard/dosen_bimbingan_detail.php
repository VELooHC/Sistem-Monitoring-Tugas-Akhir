<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/activity_log.php';
require_once __DIR__ . '/dosen_layout.php';

requireLogin(['dosen']);

$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
$dosen = fetchLoggedDosen($koneksi, $userId);
$appointmentId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$appointmentId = $appointmentId === false ? 0 : (int) $appointmentId;
$sidebarMenus = dosenSidebarMenus('/dashboard/dosen_jadwal.php');
$message = '';
$messageType = '';
$stages = [
    'Bimbingan 1', 'Bimbingan 2', 'Bimbingan 3', 'Bimbingan 4',
    'Seminar Proposal',
    'Bimbingan 5', 'Bimbingan 6', 'Bimbingan 7', 'Bimbingan 8',
    'Seminar Hasil',
    'Bimbingan 9', 'Bimbingan 10',
    'Sidang',
];
$reviewStatuses = ['Menunggu Review', 'Revisi', 'Disetujui'];

if ($dosen === null || empty($dosen['nidn']) || $appointmentId <= 0) {
    http_response_code(404);
    throw new RuntimeException('Agenda bimbingan tidak ditemukan.');
}
$nidn = (int) $dosen['nidn'];

$loadAppointment = static function () use ($koneksi, $appointmentId, $nidn, $userId): ?array {
    $statement = mysqli_prepare(
        $koneksi,
        'SELECT b.id_bimbingan, b.nim, b.tanggal_bimbingan, b.waktu_bimbingan,
                b.agenda, b.catatan_bimbingan, b.status_progres, b.status_review,
                b.catatan_dosen, m.nama_lengkap, m.judul_ta
         FROM bimbingan b
         INNER JOIN mahasiswa m ON m.nim = b.nim
         WHERE b.id_bimbingan = ? AND b.id_dosen = ? AND m.id_dosen = ?
         LIMIT 1'
    );
    if ($statement === false) {
        throw new RuntimeException('Gagal menyiapkan data agenda bimbingan.');
    }
    mysqli_stmt_bind_param($statement, 'iii', $appointmentId, $nidn, $userId);
    if (!mysqli_stmt_execute($statement)) {
        throw new RuntimeException('Gagal mengambil data agenda bimbingan.');
    }
    $result = mysqli_stmt_get_result($statement);
    $row = $result instanceof mysqli_result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($statement);

    return $row ?: null;
};

$appointment = $loadAppointment();
if ($appointment === null) {
    http_response_code(404);
    throw new RuntimeException('Agenda bimbingan tidak ditemukan atau bukan milik mahasiswa bimbingan Anda.');
}

if (($_GET['status'] ?? '') === 'updated') {
    $message = 'Progress dan catatan bimbingan berhasil disimpan.';
    $messageType = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stage = trim((string) ($_POST['status_progres'] ?? ''));
    $reviewStatus = trim((string) ($_POST['status_review'] ?? ''));
    $notes = trim((string) ($_POST['catatan_dosen'] ?? ''));

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $message = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
        $messageType = 'error';
    } elseif (($stage !== '' && !in_array($stage, $stages, true))
        || !in_array($reviewStatus, $reviewStatuses, true)) {
        $message = 'Status progress tidak valid.';
        $messageType = 'error';
    } elseif (strlen($notes) > 5000) {
        $message = 'Catatan maksimal 5000 karakter.';
        $messageType = 'error';
    } else {
        $stageValue = $stage !== '' ? $stage : null;
        $update = mysqli_prepare(
            $koneksi,
            'UPDATE bimbingan AS b
             SET status_progres = ?, status_review = ?, catatan_dosen = ?
             WHERE b.id_bimbingan = ? AND b.id_dosen = ?
               AND EXISTS (
                   SELECT 1 FROM mahasiswa m
                   WHERE m.nim = b.nim AND m.id_dosen = ?
               )'
        );
        if ($update === false) {
            throw new RuntimeException('Gagal menyiapkan penyimpanan progress bimbingan.');
        }
        mysqli_stmt_bind_param($update, 'sssiii', $stageValue, $reviewStatus, $notes, $appointmentId, $nidn, $userId);
        if (!mysqli_stmt_execute($update)) {
            error_log('Simpan progress bimbingan: ' . mysqli_stmt_error($update));
            $message = 'Progress bimbingan gagal disimpan. Silakan coba lagi.';
            $messageType = 'error';
            mysqli_stmt_close($update);
        } else {
            mysqli_stmt_close($update);
            recordActivity(
                $koneksi,
                $userId,
                'Review bimbingan',
                'NIM ' . (string) $appointment['nim'] . ' · ' . ($stage !== '' ? $stage : 'Tahap belum ditentukan')
                    . ' · status ' . $reviewStatus
            );
            redirectDosenPage('/dashboard/dosen_bimbingan_detail.php', [
                'id' => $appointmentId,
                'status' => 'updated',
            ]);
        }
    }
}

$documentStatement = mysqli_prepare(
    $koneksi,
    'SELECT judul_ta, status_ta, nama_file, file_path, tanggal_upload
     FROM tugas_akhir
     WHERE nim = ?
     ORDER BY id_ta DESC
     LIMIT 1'
);
if ($documentStatement === false) {
    throw new RuntimeException('Gagal menyiapkan dokumen tugas akhir.');
}
$nim = (string) $appointment['nim'];
mysqli_stmt_bind_param($documentStatement, 's', $nim);
if (!mysqli_stmt_execute($documentStatement)) {
    throw new RuntimeException('Gagal mengambil dokumen tugas akhir.');
}
$documentResult = mysqli_stmt_get_result($documentStatement);
$thesis = $documentResult instanceof mysqli_result ? mysqli_fetch_assoc($documentResult) : null;
mysqli_stmt_close($documentStatement);

renderDosenLayoutStart(
    'Detail Bimbingan',
    'Detail Bimbingan',
    'Tinjau dokumen tugas akhir dan perbarui progress serta catatan bimbingan.',
    $user,
    $sidebarMenus
);
?>
<div class="grid-two-column">
  <section class="info-card">
    <h2>Informasi Mahasiswa</h2>
    <div class="list-block">
      <div class="account-row"><span>Nama</span><strong><?= e((string) $appointment['nama_lengkap']) ?></strong></div>
      <div class="account-row"><span>NIM</span><strong><?= e((string) $appointment['nim']) ?></strong></div>
      <div class="account-row"><span>Judul Tugas Akhir</span><strong><?= e((string) $appointment['judul_ta']) ?></strong></div>
      <div class="account-row">
        <span>Agenda</span>
        <strong><?= e((string) $appointment['agenda']) ?> · <?= e((string) $appointment['tanggal_bimbingan']) ?><?= !empty($appointment['waktu_bimbingan']) ? ' ' . e(substr((string) $appointment['waktu_bimbingan'], 0, 5)) : '' ?></strong>
      </div>
      <div class="account-row">
        <span>Catatan mahasiswa</span>
        <strong><?= e((string) (($appointment['catatan_bimbingan'] ?? '') !== '' ? $appointment['catatan_bimbingan'] : 'Tidak ada catatan')) ?></strong>
      </div>
    </div>
  </section>

  <section class="info-card">
    <h2>Dokumen Tugas Akhir</h2>
    <?php if ($thesis !== null && !empty($thesis['file_path'])): ?>
      <p><?= e((string) $thesis['nama_file']) ?><?= !empty($thesis['tanggal_upload']) ? ' · diunggah ' . e((string) $thesis['tanggal_upload']) : '' ?></p>
      <p>Status dokumen: <strong><?= e((string) $thesis['status_ta']) ?></strong></p>
      <a class="action-button document-link" href="<?= e((string) $thesis['file_path']) ?>" target="_blank" rel="noopener noreferrer">Lihat file TA</a>
    <?php else: ?>
      <p>Mahasiswa belum mengunggah file tugas akhir.</p>
    <?php endif; ?>
  </section>

  <section class="info-card full-width">
    <h2>Review Bimbingan</h2>
    <?php if ($message !== ''): ?>
      <div class="message-box message-<?= e($messageType) ?>"><?= e($message) ?></div>
    <?php endif; ?>
    <form class="account-form" method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <label class="form-label" for="status_progres">
        Tahap tugas akhir
        <select class="form-input" id="status_progres" name="status_progres" required>
          <option value="">Pilih tahap</option>
          <?php foreach ($stages as $stageOption): ?>
            <option value="<?= e($stageOption) ?>" <?= $appointment['status_progres'] === $stageOption ? 'selected' : '' ?>><?= e($stageOption) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="form-label" for="status_review">
        Status review
        <select class="form-input" id="status_review" name="status_review" required>
          <?php foreach ($reviewStatuses as $statusOption): ?>
            <option value="<?= e($statusOption) ?>" <?= $appointment['status_review'] === $statusOption ? 'selected' : '' ?>><?= e($statusOption) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="form-label" for="catatan_dosen">
        Catatan dosen
        <textarea class="form-textarea" id="catatan_dosen" name="catatan_dosen" maxlength="5000"><?= e((string) ($appointment['catatan_dosen'] ?? '')) ?></textarea>
      </label>
      <div class="form-actions"><button class="action-button" type="submit">Simpan Review</button></div>
    </form>
  </section>
</div>
<style>
.document-link { display: inline-block; text-decoration: none; }
.document-link:hover { color: #fff; }
</style>
<?php renderDosenLayoutEnd(); ?>
