<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireLogin(['admin']);
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/activity_log.php';
require_once __DIR__ . '/admin_layout.php';

$user = currentUser();
$successMessage = '';
$errorMessage = '';
$status = trim((string) ($_GET['status'] ?? ''));
$examinerRoles = ['Ketua', 'Penguji 1', 'Penguji 2'];
$activityTypes = ['Seminar Proposal', 'Seminar Hasil', 'Sidang Tugas Akhir'];

if ($status === 'created_schedule') {
    $successMessage = 'Jadwal seminar atau sidang berhasil dibuat.';
} elseif ($status === 'created_assignment' || $status === 'created') {
    $successMessage = 'Data penguji berhasil ditambahkan.';
} elseif ($status === 'deleted') {
    $successMessage = 'Penugasan penguji berhasil dihapus.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    $idJadwal = (int) ($_POST['id_jadwal'] ?? 0);
    $idDosen = (int) ($_POST['id_dosen'] ?? 0);
    $peran = trim((string) ($_POST['peran'] ?? ''));
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif ($action === 'create_schedule') {
        $nim = trim((string) ($_POST['nim'] ?? ''));
        $jenisKegiatan = trim((string) ($_POST['jenis_kegiatan'] ?? ''));
        $tanggalRaw = trim((string) ($_POST['tanggal_kegiatan'] ?? ''));
        $ruangan = trim((string) ($_POST['ruangan'] ?? ''));
        $tanggalValue = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $tanggalRaw);

        if ($nim === '' || !in_array($jenisKegiatan, $activityTypes, true) || $tanggalValue === false
            || $tanggalValue->format('Y-m-d\TH:i') !== $tanggalRaw || $ruangan === '' || strlen($ruangan) > 50) {
            $errorMessage = 'Lengkapi mahasiswa, jenis kegiatan, tanggal, dan ruangan dengan data yang valid.';
        } else {
            $studentCheck = mysqli_prepare($koneksi, 'SELECT nim FROM mahasiswa WHERE nim = ? LIMIT 1');
            if ($studentCheck === false) {
                throw new RuntimeException('Gagal menyiapkan validasi mahasiswa.');
            }
            mysqli_stmt_bind_param($studentCheck, 's', $nim);
            if (!mysqli_stmt_execute($studentCheck)) {
                mysqli_stmt_close($studentCheck);
                throw new RuntimeException('Gagal memvalidasi mahasiswa.');
            }
            $studentResult = mysqli_stmt_get_result($studentCheck);
            $student = $studentResult instanceof mysqli_result ? mysqli_fetch_assoc($studentResult) : null;
            mysqli_stmt_close($studentCheck);

            if ($student === null) {
                $errorMessage = 'Mahasiswa yang dipilih tidak ditemukan.';
            } else {
                $tanggalKegiatan = $tanggalValue->format('Y-m-d H:i:s');
                $insertSchedule = mysqli_prepare(
                    $koneksi,
                    'INSERT INTO jadwal_ta (nim, jenis_kegiatan, tanggal_kegiatan, ruangan) VALUES (?, ?, ?, ?)'
                );
                if ($insertSchedule === false) {
                    throw new RuntimeException('Gagal menyiapkan penyimpanan jadwal TA.');
                }
                mysqli_stmt_bind_param($insertSchedule, 'ssss', $nim, $jenisKegiatan, $tanggalKegiatan, $ruangan);
                if (!mysqli_stmt_execute($insertSchedule)) {
                    mysqli_stmt_close($insertSchedule);
                    throw new RuntimeException('Gagal menyimpan jadwal TA.');
                }
                $newScheduleId = (int) mysqli_insert_id($koneksi);
                mysqli_stmt_close($insertSchedule);
                recordActivity(
                    $koneksi,
                    (int) ($user['id'] ?? 0),
                    'Tambah jadwal TA',
                    'ID jadwal ' . $newScheduleId . ' · NIM ' . $nim . ' · ' . $jenisKegiatan . ' · ' . $tanggalKegiatan
                );
                redirectAdminPage('/dashboard/admin_penguji.php', ['status' => 'created_schedule']);
            }
        }
    } elseif ($action === 'create') {
        if ($idJadwal <= 0 || $idDosen <= 0 || !in_array($peran, $examinerRoles, true)) {
            $errorMessage = 'Pilih jadwal, dosen, dan peran penguji (Ketua, Penguji 1, atau Penguji 2) yang valid.';
        } else {
            $choiceCheck = mysqli_prepare(
                $koneksi,
                "SELECT j.id_jadwal, d.id_user
                 FROM jadwal_ta j
                 CROSS JOIN dosen d
                 INNER JOIN users u ON u.id_user = d.id_user AND u.role = 'dosen'
                 WHERE j.id_jadwal = ? AND d.id_user = ?
                 LIMIT 1"
            );
            if ($choiceCheck === false) {
                throw new RuntimeException('Gagal menyiapkan validasi jadwal dan dosen.');
            }
            mysqli_stmt_bind_param($choiceCheck, 'ii', $idJadwal, $idDosen);
            if (!mysqli_stmt_execute($choiceCheck)) {
                mysqli_stmt_close($choiceCheck);
                throw new RuntimeException('Gagal memvalidasi jadwal dan dosen.');
            }
            $choiceResult = mysqli_stmt_get_result($choiceCheck);
            $validChoice = $choiceResult instanceof mysqli_result
                && mysqli_fetch_assoc($choiceResult) !== null;
            mysqli_stmt_close($choiceCheck);

            if (!$validChoice) {
                $errorMessage = 'Jadwal atau dosen yang dipilih tidak ditemukan.';
            } else {
                if (!mysqli_begin_transaction($koneksi)) {
                    throw new RuntimeException('Gagal memulai transaksi penugasan penguji.');
                }

                try {
                    $scheduleLock = mysqli_prepare(
                        $koneksi,
                        'SELECT id_jadwal FROM jadwal_ta WHERE id_jadwal = ? FOR UPDATE'
                    );
                    if ($scheduleLock === false) {
                        throw new RuntimeException('Gagal mengunci jadwal saat menugaskan penguji.');
                    }
                    mysqli_stmt_bind_param($scheduleLock, 'i', $idJadwal);
                    if (!mysqli_stmt_execute($scheduleLock)) {
                        mysqli_stmt_close($scheduleLock);
                        throw new RuntimeException('Gagal memvalidasi jadwal saat menugaskan penguji.');
                    }
                    $lockedResult = mysqli_stmt_get_result($scheduleLock);
                    $lockedSchedule = $lockedResult instanceof mysqli_result
                        ? mysqli_fetch_assoc($lockedResult)
                        : null;
                    mysqli_stmt_close($scheduleLock);

                    if ($lockedSchedule === null) {
                        mysqli_rollback($koneksi);
                        $errorMessage = 'Jadwal yang dipilih sudah tidak tersedia.';
                    } else {
                        $countStatement = mysqli_prepare(
                            $koneksi,
                            "SELECT COUNT(*) AS total
                             FROM penguji
                             WHERE id_jadwal = ? AND peran IN ('Ketua', 'Penguji 1', 'Penguji 2')"
                        );
                        if ($countStatement === false) {
                            throw new RuntimeException('Gagal menyiapkan pemeriksaan jumlah penguji.');
                        }
                        mysqli_stmt_bind_param($countStatement, 'i', $idJadwal);
                        if (!mysqli_stmt_execute($countStatement)) {
                            mysqli_stmt_close($countStatement);
                            throw new RuntimeException('Gagal menghitung penguji pada jadwal.');
                        }
                        $countResult = mysqli_stmt_get_result($countStatement);
                        $countRow = $countResult instanceof mysqli_result ? mysqli_fetch_assoc($countResult) : null;
                        mysqli_stmt_close($countStatement);
                        $examinerCount = (int) ($countRow['total'] ?? 0);

                        if ($examinerCount >= 3) {
                            mysqli_rollback($koneksi);
                            $errorMessage = 'Jadwal ini sudah memiliki tiga dosen penguji.';
                        } else {
                            $roleCheck = mysqli_prepare(
                                $koneksi,
                                'SELECT id FROM penguji WHERE id_jadwal = ? AND peran = ? LIMIT 1'
                            );
                            if ($roleCheck === false) {
                                throw new RuntimeException('Gagal menyiapkan pemeriksaan peran penguji.');
                            }
                            mysqli_stmt_bind_param($roleCheck, 'is', $idJadwal, $peran);
                            if (!mysqli_stmt_execute($roleCheck)) {
                                mysqli_stmt_close($roleCheck);
                                throw new RuntimeException('Gagal memeriksa peran penguji.');
                            }
                            $roleResult = mysqli_stmt_get_result($roleCheck);
                            $roleTaken = $roleResult instanceof mysqli_result
                                && mysqli_fetch_assoc($roleResult) !== null;
                            mysqli_stmt_close($roleCheck);

                            $duplicate = mysqli_prepare(
                                $koneksi,
                                'SELECT id FROM penguji WHERE id_jadwal = ? AND id_dosen = ? LIMIT 1'
                            );
                            if ($duplicate === false) {
                                throw new RuntimeException('Gagal menyiapkan pemeriksaan dosen penguji.');
                            }
                            mysqli_stmt_bind_param($duplicate, 'ii', $idJadwal, $idDosen);
                            if (!mysqli_stmt_execute($duplicate)) {
                                mysqli_stmt_close($duplicate);
                                throw new RuntimeException('Gagal memeriksa dosen penguji.');
                            }
                            $duplicateResult = mysqli_stmt_get_result($duplicate);
                            $alreadyAssigned = $duplicateResult instanceof mysqli_result
                                && mysqli_fetch_assoc($duplicateResult) !== null;
                            mysqli_stmt_close($duplicate);

                            if ($roleTaken) {
                                mysqli_rollback($koneksi);
                                $errorMessage = 'Peran ' . $peran . ' sudah terisi pada jadwal ini.';
                            } elseif ($alreadyAssigned) {
                                mysqli_rollback($koneksi);
                                $errorMessage = 'Dosen tersebut sudah ditugaskan pada jadwal ini.';
                            } else {
                                $insert = mysqli_prepare(
                                    $koneksi,
                                    'INSERT INTO penguji (id_jadwal, id_dosen, peran) VALUES (?, ?, ?)'
                                );
                                if ($insert === false) {
                                    throw new RuntimeException('Gagal menyiapkan penyimpanan penguji.');
                                }
                                mysqli_stmt_bind_param($insert, 'iis', $idJadwal, $idDosen, $peran);
                                if (!mysqli_stmt_execute($insert)) {
                                    mysqli_stmt_close($insert);
                                    throw new RuntimeException('Gagal menyimpan data penguji.');
                                }
                                mysqli_stmt_close($insert);
                                if (!mysqli_commit($koneksi)) {
                                    throw new RuntimeException('Gagal menyelesaikan transaksi penugasan penguji.');
                                }
                                recordActivity(
                                    $koneksi,
                                    (int) ($user['id'] ?? 0),
                                    'Tambah data penguji',
                                    'Jadwal ' . $idJadwal . ' · Dosen ' . $idDosen . ' · ' . $peran
                                );
                                redirectAdminPage('/dashboard/admin_penguji.php', ['status' => 'created_assignment']);
                            }
                        }
                    }
                } catch (Throwable $exception) {
                    mysqli_rollback($koneksi);
                    throw $exception;
                }
            }
        }
    } elseif ($action === 'delete') {
        $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
        if ($assignmentId <= 0) {
            $errorMessage = 'Data penugasan penguji tidak valid.';
        } else {
            $delete = mysqli_prepare($koneksi, 'DELETE FROM penguji WHERE id = ?');
            if ($delete === false) {
                throw new RuntimeException('Gagal menyiapkan penghapusan penguji.');
            }
            mysqli_stmt_bind_param($delete, 'i', $assignmentId);
            if (!mysqli_stmt_execute($delete)) {
                mysqli_stmt_close($delete);
                throw new RuntimeException('Gagal menghapus penugasan penguji.');
            }
            $deleted = mysqli_stmt_affected_rows($delete) > 0;
            mysqli_stmt_close($delete);
            if (!$deleted) {
                $errorMessage = 'Penugasan penguji tidak ditemukan.';
            } else {
                recordActivity(
                    $koneksi,
                    (int) ($user['id'] ?? 0),
                    'Hapus data penguji',
                    'ID penugasan ' . $assignmentId
                );
                redirectAdminPage('/dashboard/admin_penguji.php', ['status' => 'deleted']);
            }
        }
    } else {
        $errorMessage = 'Aksi tidak dikenali.';
    }
}

$studentResult = mysqli_query(
    $koneksi,
    "SELECT nim, nama_lengkap
     FROM mahasiswa
     ORDER BY nama_lengkap ASC"
);
if ($studentResult === false) {
    throw new RuntimeException('Gagal mengambil daftar mahasiswa.');
}
$students = mysqli_fetch_all($studentResult, MYSQLI_ASSOC);
mysqli_free_result($studentResult);

$scheduleResult = mysqli_query(
    $koneksi,
    "SELECT j.id_jadwal, j.nim, j.jenis_kegiatan, j.tanggal_kegiatan, j.ruangan,
            m.nama_lengkap
     FROM jadwal_ta j
     INNER JOIN mahasiswa m ON m.nim = j.nim
     ORDER BY j.tanggal_kegiatan DESC, j.id_jadwal DESC"
);
if ($scheduleResult === false) {
    throw new RuntimeException('Gagal mengambil daftar jadwal TA.');
}
$schedules = mysqli_fetch_all($scheduleResult, MYSQLI_ASSOC);
mysqli_free_result($scheduleResult);

$lecturerResult = mysqli_query(
    $koneksi,
    "SELECT d.id_user, d.nidn, d.nama_lengkap
     FROM dosen d
     INNER JOIN users u ON u.id_user = d.id_user AND u.role = 'dosen'
     ORDER BY d.nama_lengkap ASC"
);
if ($lecturerResult === false) {
    throw new RuntimeException('Gagal mengambil daftar dosen.');
}
$lecturers = mysqli_fetch_all($lecturerResult, MYSQLI_ASSOC);
mysqli_free_result($lecturerResult);

$assignmentResult = mysqli_query(
    $koneksi,
    "SELECT p.id, p.peran, j.id_jadwal, j.nim, j.jenis_kegiatan, j.tanggal_kegiatan,
            j.ruangan, m.nama_lengkap AS nama_mahasiswa,
            d.nama_lengkap AS nama_dosen, d.nidn
     FROM penguji p
     INNER JOIN jadwal_ta j ON j.id_jadwal = p.id_jadwal
     INNER JOIN mahasiswa m ON m.nim = j.nim
     INNER JOIN dosen d ON d.id_user = p.id_dosen
     ORDER BY j.tanggal_kegiatan DESC, m.nama_lengkap ASC, p.peran ASC"
);
if ($assignmentResult === false) {
    throw new RuntimeException('Gagal mengambil daftar penugasan penguji.');
}
$assignments = mysqli_fetch_all($assignmentResult, MYSQLI_ASSOC);
mysqli_free_result($assignmentResult);

$examinerCounts = [];
$assignedRoles = [];
$assignedExaminerNames = [];
$assignmentsBySchedule = [];
foreach ($assignments as $assignment) {
    $scheduleId = (int) $assignment['id_jadwal'];
    $assignmentsBySchedule[$scheduleId][] = $assignment;
    if (!in_array((string) $assignment['peran'], ['Ketua', 'Penguji 1', 'Penguji 2'], true)) {
        continue;
    }

    $examinerCounts[$scheduleId] = ($examinerCounts[$scheduleId] ?? 0) + 1;
    $assignedRoles[$scheduleId][] = (string) $assignment['peran'];
    $assignedExaminerNames[$scheduleId][(string) $assignment['peran']] = (string) $assignment['nama_dosen'];
}
$hasAvailableExaminerSlot = false;
foreach ($schedules as $schedule) {
    if (($examinerCounts[(int) $schedule['id_jadwal']] ?? 0) < 3) {
        $hasAvailableExaminerSlot = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Data Penguji Admin</title>
  <link rel="stylesheet" href="../css/styles.css" />
  <style>
    * { box-sizing: border-box; }
    body.admin-page { margin: 0; min-height: 100vh; background: #eef0f4; font-family: "Trebuchet MS", Arial, sans-serif; color: #2f2f2f; }
    .page-shell { width: 100%; max-width: 1440px; min-height: 100vh; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; padding: 24px; }
    .page-header, .panel-card { background: #fff; border: 1px solid #dadfe8; border-radius: 22px; box-shadow: 0 14px 34px rgba(30,34,44,.06); }
    .page-header { padding: 22px 24px; display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
    .page-title h1 { margin: 0 0 6px; font-size: 1.6rem; }
    .page-title p, .panel-card > p { margin: 0; color: #5c6471; font-weight: 700; line-height: 1.5; }
    .page-title p { margin-bottom: 14px; }
    .header-user { padding: 10px 16px; border-radius: 999px; background: #ebe7ff; color: #4d38c7; font-weight: 800; white-space: nowrap; }
    .link-button, .action-button, .danger-button { border: 0; border-radius: 12px; padding: 11px 16px; font-weight: 700; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
    .link-button, .action-button { background: #4d38c7; color: #fff; }
    .danger-button { background: #c62828; color: #fff; }
    .secondary-button { background: #edf0f6; color: #445062; }
    .page-grid { display: grid; grid-template-columns: minmax(320px, 380px) minmax(0, 1fr); gap: 20px; align-items: start; }
    .panel-card { padding: 22px; }
    .panel-card h2 { margin: 0 0 8px; font-size: 1.2rem; }
    .panel-card > p { margin-bottom: 18px; }
    .form-grid { display: grid; gap: 14px; }
    .form-label { display: grid; gap: 8px; font-weight: 800; color: #404754; }
    .form-input, .form-select { width: 100%; border: 1px solid #d1d7e2; border-radius: 14px; padding: 12px 14px; font: inherit; background: #f8f9fc; color: #2f2f2f; }
    .form-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .message-box { margin-bottom: 16px; border-radius: 14px; padding: 14px 16px; font-weight: 700; line-height: 1.5; }
    .message-success { background: #eaf8ef; color: #246942; border: 1px solid #b6e0c3; }
    .message-error { background: #fdeeee; color: #a12727; border: 1px solid #f0c3c3; }
    .message-warning { background: #fff6df; color: #76500c; border: 1px solid #f1d58a; }
    .table-wrap { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; min-width: 900px; }
    .data-table th, .data-table td { padding: 14px 12px; border-bottom: 1px solid #e5e9f0; text-align: left; vertical-align: top; }
    .data-table th { font-size: .9rem; color: #596271; background: #f8f9fc; }
    .data-table td { font-weight: 700; color: #39414e; }
    .empty-state { padding: 18px; border-radius: 16px; background: #f8f9fc; color: #5c6471; font-weight: 700; line-height: 1.6; }
    .full-width { grid-column: 1 / -1; }
    .schedule-table { min-width: 760px; }
    .schedule-table small { color: #67707d; }
    .role-list { margin: 0; padding-left: 18px; }
    .role-list li { margin: 3px 0; }
    .completion-chip { display: inline-block; padding: 6px 10px; border-radius: 999px; background: #fff3db; color: #8a5a08; font-size: .83rem; font-weight: 800; white-space: nowrap; }
    .completion-chip.is-complete { background: #e8f7ed; color: #246942; }
    @media (max-width: 960px) { .page-grid { grid-template-columns: 1fr; } }
    @media (max-width: 640px) { .page-shell { padding: 16px; } .page-header { padding: 18px; flex-direction: column; } .panel-card { padding: 18px; } }
  </style>
</head>
<body class="admin-page">
  <div class="page-shell">
    <header class="page-header">
      <div>
        <a class="link-button secondary-button" href="<?= e(appPath('/dashboard/admin_dashboard.php')) ?>">Kembali ke Dashboard</a>
        <div class="page-title">
          <h1>Kelola Data Penguji</h1>
          <p>Buat jadwal seminar atau sidang, lalu lengkapi tiga penugasan pengujinya.</p>
        </div>
      </div>
      <span class="header-user">Admin: <?= e((string) ($user['username'] ?? 'Admin')) ?></span>
    </header>

    <?php if ($successMessage !== ''): ?><div class="message-box message-success"><?= e($successMessage) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="message-box message-error"><?= e($errorMessage) ?></div><?php endif; ?>
    <?php if (count($lecturers) < 3): ?>
      <div class="message-box message-warning">
        Tersedia <?= count($lecturers) ?> dosen. Tambahkan <?= 3 - count($lecturers) ?> dosen lagi melalui
        <a href="<?= e(appPath('/dashboard/admin_dosen.php')) ?>">Data Dosen</a> agar satu kegiatan dapat memiliki tiga penguji yang berbeda.
      </div>
    <?php endif; ?>

    <section class="page-grid">
      <div class="panel-card">
        <h2>1. Buat Jadwal Seminar / Sidang</h2>
        <p>Simpan kegiatan untuk mahasiswa terlebih dahulu. Jadwal akan langsung tersedia pada pilihan penugasan penguji.</p>
        <?php if ($students === []): ?>
          <div class="empty-state">Belum ada data mahasiswa untuk dibuatkan jadwal.</div>
        <?php else: ?>
          <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>" />
            <input type="hidden" name="action" value="create_schedule" />
            <label class="form-label" for="nim">
              Mahasiswa
              <select class="form-select" id="nim" name="nim" required>
                <option value="">Pilih mahasiswa</option>
                <?php foreach ($students as $student): ?>
                  <option value="<?= e((string) $student['nim']) ?>"><?= e((string) $student['nim'] . ' · ' . $student['nama_lengkap']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="form-label" for="jenis_kegiatan">
              Jenis kegiatan
              <select class="form-select" id="jenis_kegiatan" name="jenis_kegiatan" required>
                <option value="">Pilih kegiatan</option>
                <?php foreach ($activityTypes as $activityType): ?>
                  <option value="<?= e($activityType) ?>"><?= e($activityType) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="form-label" for="tanggal_kegiatan">
              Tanggal dan waktu
              <input class="form-input" id="tanggal_kegiatan" name="tanggal_kegiatan" type="datetime-local" required />
            </label>
            <label class="form-label" for="ruangan">
              Ruangan
              <input class="form-input" id="ruangan" name="ruangan" type="text" maxlength="50" required />
            </label>
            <div class="form-actions"><button class="action-button" type="submit">Simpan Jadwal</button></div>
          </form>
        <?php endif; ?>
      </div>

      <div class="panel-card">
        <h2>2. Tugaskan Tiga Dosen Penguji</h2>
        <p>Simpan satu penugasan setiap kali. Lengkapi peran Ketua, Penguji 1, dan Penguji 2 dengan dosen yang berbeda.</p>
        <?php if ($schedules === []): ?>
          <div class="empty-state">Buat jadwal seminar atau sidang terlebih dahulu agar dapat memilihnya di sini.</div>
        <?php elseif ($lecturers === []): ?>
          <div class="empty-state">Belum ada data dosen untuk ditugaskan sebagai penguji.</div>
        <?php elseif (!$hasAvailableExaminerSlot): ?>
          <div class="empty-state">Semua jadwal sudah memiliki tiga dosen penguji.</div>
        <?php else: ?>
          <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>" />
            <input type="hidden" name="action" value="create" />
            <label class="form-label" for="id_jadwal">
              Jadwal TA
              <select class="form-select" id="id_jadwal" name="id_jadwal" required>
                <option value="">Pilih jadwal</option>
                <?php foreach ($schedules as $schedule): ?>
                  <?php
                  $scheduleId = (int) $schedule['id_jadwal'];
                  $scheduleExaminerCount = $examinerCounts[$scheduleId] ?? 0;
                  ?>
                  <option value="<?= $scheduleId ?>" <?= $scheduleExaminerCount >= 3 ? 'disabled' : '' ?>
                    data-assigned-roles="<?= e(implode('|', $assignedRoles[$scheduleId] ?? [])) ?>">
                    <?= e((string) $schedule['jenis_kegiatan'] . ' · ' . $schedule['nim'] . ' · ' . $schedule['nama_lengkap'] . ' · ' . $schedule['tanggal_kegiatan'] . ' · ' . $scheduleExaminerCount . '/3 penguji') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="form-label" for="id_dosen">
              Dosen penguji
              <select class="form-select" id="id_dosen" name="id_dosen" required>
                <option value="">Pilih dosen</option>
                <?php foreach ($lecturers as $lecturer): ?>
                  <option value="<?= (int) $lecturer['id_user'] ?>"><?= e((string) $lecturer['nama_lengkap'] . ' · NIDN ' . $lecturer['nidn']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="form-label" for="peran">
              Peran penguji
              <select class="form-select" id="peran" name="peran" required>
                <option value="">Pilih peran</option>
                <?php foreach ($examinerRoles as $examinerRole): ?>
                  <option value="<?= e($examinerRole) ?>"><?= e($examinerRole) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <div class="form-actions"><button class="action-button" type="submit">Simpan Penugasan</button></div>
          </form>
        <?php endif; ?>
      </div>
    </section>

    <section class="panel-card full-width">
      <h2>Jadwal dan Kelengkapan Penguji</h2>
      <p>Setiap kegiatan dinyatakan lengkap setelah ketiga peran penguji terisi.</p>
      <div class="table-wrap">
        <?php if ($schedules === []): ?>
          <div class="empty-state">Belum ada jadwal seminar atau sidang. Gunakan formulir di atas untuk membuat jadwal pertama.</div>
        <?php else: ?>
          <table class="data-table schedule-table">
            <thead><tr><th>Mahasiswa</th><th>Kegiatan</th><th>Tanggal / Ruang</th><th>Penguji</th><th>Kelengkapan</th></tr></thead>
            <tbody>
              <?php foreach ($schedules as $schedule): ?>
                <?php
                $scheduleId = (int) $schedule['id_jadwal'];
                $scheduleExaminerCount = $examinerCounts[$scheduleId] ?? 0;
                ?>
                <tr>
                  <td><?= e((string) $schedule['nama_lengkap']) ?><br><small>NIM <?= e((string) $schedule['nim']) ?></small></td>
                  <td><?= e((string) $schedule['jenis_kegiatan']) ?></td>
                  <td><?= e((string) $schedule['tanggal_kegiatan']) ?><br><?= e((string) $schedule['ruangan']) ?></td>
                  <td>
                    <ul class="role-list">
                      <?php foreach ($examinerRoles as $examinerRole): ?>
                      <?php $assignedExaminerName = $assignedExaminerNames[$scheduleId][$examinerRole] ?? null; ?>
                      <li><?= e($examinerRole) ?>: <?= $assignedExaminerName !== null ? 'Terisi · ' . e($assignedExaminerName) : 'Belum ditugaskan' ?></li>
                      <?php endforeach; ?>
                    </ul>
                  </td>
                  <td>
                    <span class="completion-chip<?= $scheduleExaminerCount === 3 ? ' is-complete' : '' ?>">
                      <?= $scheduleExaminerCount ?>/3 penguji<?= $scheduleExaminerCount === 3 ? ' · Lengkap' : ' · Belum lengkap' ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </section>

    <section class="panel-card full-width">
      <h2>Tabel Penugasan Penguji</h2>
      <p>Hapus hanya penugasan yang dipilih, bukan jadwal atau akun dosennya. Penguji yang dihapus dapat ditugaskan kembali.</p>
      <div class="table-wrap">
        <?php if ($assignments === []): ?>
          <div class="empty-state">Belum ada penugasan penguji pada jadwal TA.</div>
        <?php else: ?>
          <table class="data-table">
            <thead><tr><th>Mahasiswa</th><th>NIM</th><th>Kegiatan</th><th>Tanggal / Ruang</th><th>Dosen Penguji</th><th>Peran</th><th>Aksi</th></tr></thead>
            <tbody>
              <?php foreach ($assignments as $assignment): ?>
                <tr>
                  <td><?= e((string) $assignment['nama_mahasiswa']) ?></td>
                  <td><?= e((string) $assignment['nim']) ?></td>
                  <td><?= e((string) $assignment['jenis_kegiatan']) ?></td>
                  <td><?= e((string) $assignment['tanggal_kegiatan']) ?><br><?= e((string) $assignment['ruangan']) ?></td>
                  <td><?= e((string) $assignment['nama_dosen']) ?><br>NIDN <?= e((string) $assignment['nidn']) ?></td>
                  <td><?= e((string) $assignment['peran']) ?></td>
                  <td>
                    <form method="post" onsubmit="return confirm('Hapus penugasan penguji ini?');">
                      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>" />
                      <input type="hidden" name="action" value="delete" />
                      <input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>" />
                      <button class="danger-button" type="submit">Hapus</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </section>
    <script>
    (function () {
      var scheduleSelect = document.getElementById('id_jadwal');
      var roleSelect = document.getElementById('peran');
      if (!scheduleSelect || !roleSelect) return;

      function updateAvailableRoles() {
        var selectedSchedule = scheduleSelect.options[scheduleSelect.selectedIndex];
        var assignedRoles = (selectedSchedule.getAttribute('data-assigned-roles') || '').split('|');
        Array.prototype.forEach.call(roleSelect.options, function (option) {
          option.disabled = option.value !== '' && assignedRoles.indexOf(option.value) !== -1;
        });
        if (roleSelect.options[roleSelect.selectedIndex].disabled) {
          roleSelect.value = '';
        }
      }

      scheduleSelect.addEventListener('change', updateAvailableRoles);
      updateAvailableRoles();
    })();
    </script>
  </div>
</body>
</html>
