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

if ($status === 'created') {
    $successMessage = 'Data penguji berhasil ditambahkan.';
} elseif ($status === 'deleted') {
    $successMessage = 'Penugasan penguji berhasil dihapus.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    $idJadwal = (int) ($_POST['id_jadwal'] ?? 0);
    $idDosen = (int) ($_POST['id_dosen'] ?? 0);
    $peran = trim((string) ($_POST['peran'] ?? ''));
    $allowedRoles = ['Ketua', 'Penguji 1', 'Penguji 2', 'Sekretaris'];

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif ($action === 'create') {
        if ($idJadwal <= 0 || $idDosen <= 0 || !in_array($peran, $allowedRoles, true)) {
            $errorMessage = 'Pilih jadwal, dosen, dan peran penguji yang valid.';
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
                $duplicate = mysqli_prepare(
                    $koneksi,
                    'SELECT id FROM penguji WHERE id_jadwal = ? AND id_dosen = ? LIMIT 1'
                );
                if ($duplicate === false) {
                    throw new RuntimeException('Gagal menyiapkan pemeriksaan penguji.');
                }
                mysqli_stmt_bind_param($duplicate, 'ii', $idJadwal, $idDosen);
                if (!mysqli_stmt_execute($duplicate)) {
                    mysqli_stmt_close($duplicate);
                    throw new RuntimeException('Gagal memeriksa penugasan penguji.');
                }
                $duplicateResult = mysqli_stmt_get_result($duplicate);
                $alreadyAssigned = $duplicateResult instanceof mysqli_result
                    && mysqli_fetch_assoc($duplicateResult) !== null;
                mysqli_stmt_close($duplicate);

                if ($alreadyAssigned) {
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
                    recordActivity(
                        $koneksi,
                        (int) ($user['id'] ?? 0),
                        'Tambah data penguji',
                        'Jadwal ' . $idJadwal . ' · Dosen ' . $idDosen . ' · ' . $peran
                    );
                    redirectAdminPage('/dashboard/admin_penguji.php', ['status' => 'created']);
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
    .table-wrap { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; min-width: 900px; }
    .data-table th, .data-table td { padding: 14px 12px; border-bottom: 1px solid #e5e9f0; text-align: left; vertical-align: top; }
    .data-table th { font-size: .9rem; color: #596271; background: #f8f9fc; }
    .data-table td { font-weight: 700; color: #39414e; }
    .empty-state { padding: 18px; border-radius: 16px; background: #f8f9fc; color: #5c6471; font-weight: 700; line-height: 1.6; }
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
          <p>Atur penugasan dosen penguji berdasarkan jadwal seminar atau sidang yang tersedia.</p>
        </div>
      </div>
      <span class="header-user">Admin: <?= e((string) ($user['username'] ?? 'Admin')) ?></span>
    </header>

    <section class="page-grid">
      <div class="panel-card">
        <h2>Tambah Penugasan Penguji</h2>
        <p>Penugasan menghubungkan satu dosen dengan satu jadwal TA yang sudah terdaftar.</p>
        <?php if ($successMessage !== ''): ?><div class="message-box message-success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="message-box message-error"><?= e($errorMessage) ?></div><?php endif; ?>
        <?php if ($schedules === [] || $lecturers === []): ?>
          <div class="empty-state"><?= $schedules === [] ? 'Belum ada jadwal seminar atau sidang pada database.' : 'Belum ada dosen untuk ditugaskan sebagai penguji.' ?></div>
        <?php else: ?>
          <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>" />
            <input type="hidden" name="action" value="create" />
            <label class="form-label" for="id_jadwal">
              Jadwal TA
              <select class="form-select" id="id_jadwal" name="id_jadwal" required>
                <option value="">Pilih jadwal</option>
                <?php foreach ($schedules as $schedule): ?>
                  <option value="<?= (int) $schedule['id_jadwal'] ?>">
                    <?= e((string) $schedule['jenis_kegiatan'] . ' · ' . $schedule['nim'] . ' · ' . $schedule['nama_lengkap'] . ' · ' . $schedule['tanggal_kegiatan']) ?>
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
              Peran
              <select class="form-select" id="peran" name="peran" required>
                <option value="">Pilih peran</option>
                <option value="Ketua">Ketua</option>
                <option value="Penguji 1">Penguji 1</option>
                <option value="Penguji 2">Penguji 2</option>
                <option value="Sekretaris">Sekretaris</option>
              </select>
            </label>
            <div class="form-actions"><button class="action-button" type="submit">Tambah Penguji</button></div>
          </form>
        <?php endif; ?>
      </div>

      <div class="panel-card">
        <h2>Tabel Penguji</h2>
        <p>Hapus hanya penugasan penguji yang dipilih, bukan jadwal atau data dosennya.</p>
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
      </div>
    </section>
  </div>
</body>
</html>
