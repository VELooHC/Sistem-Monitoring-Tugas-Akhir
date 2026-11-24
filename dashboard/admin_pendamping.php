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

if ($status === 'saved') {
    $successMessage = 'Penugasan dosen pendamping berhasil disimpan.';
} elseif ($status === 'removed') {
    $successMessage = 'Penugasan dosen pendamping berhasil dihapus.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    $studentId = (int) ($_POST['student_id'] ?? 0);

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif ($studentId <= 0) {
        $errorMessage = 'Mahasiswa yang dipilih tidak valid.';
    } elseif ($action === 'assign') {
        $lecturerId = (int) ($_POST['lecturer_id'] ?? 0);
        if ($lecturerId <= 0) {
            $errorMessage = 'Pilih dosen pendamping.';
        } else {
            $studentCheck = mysqli_prepare($koneksi, 'SELECT nim FROM mahasiswa WHERE id_user = ? LIMIT 1');
            $lecturerCheck = mysqli_prepare(
                $koneksi,
                "SELECT d.nama_lengkap FROM dosen d
                 INNER JOIN users u ON u.id_user = d.id_user
                 WHERE d.id_user = ? AND u.role = 'dosen' LIMIT 1"
            );
            if ($studentCheck === false || $lecturerCheck === false) {
                throw new RuntimeException('Gagal menyiapkan validasi data pendamping.');
            }

           
            mysqli_stmt_bind_param($studentCheck, 'i', $studentId);
            mysqli_stmt_bind_param($lecturerCheck, 'i', $lecturerId);

// Jalankan query mahasiswa terlebih dahulu
            if (!mysqli_stmt_execute($studentCheck)) {
                throw new RuntimeException('Gagal memvalidasi data mahasiswa.');
            }

// Ambil dan selesaikan hasil query mahasiswa
            $studentResult = mysqli_stmt_get_result($studentCheck);
            $student = $studentResult instanceof mysqli_result
                ? mysqli_fetch_assoc($studentResult)
                : null;

            if ($studentResult instanceof mysqli_result) {
                mysqli_free_result($studentResult);
            }

// Setelah query pertama selesai, jalankan query dosen
            if (!mysqli_stmt_execute($lecturerCheck)) {
                throw new RuntimeException('Gagal memvalidasi data dosen.');
            }

// Ambil dan selesaikan hasil query dosen
              $lecturerResult = mysqli_stmt_get_result($lecturerCheck);
              $lecturer = $lecturerResult instanceof mysqli_result
                ? mysqli_fetch_assoc($lecturerResult)
                : null;

            if ($lecturerResult instanceof mysqli_result) {
                mysqli_free_result($lecturerResult);
            }

// Tutup kedua statement
            mysqli_stmt_close($studentCheck);
            mysqli_stmt_close($lecturerCheck);


            if ($student === null || $lecturer === null) {
                $errorMessage = 'Mahasiswa atau dosen yang dipilih tidak ditemukan.';
            } else {
                $update = mysqli_prepare($koneksi, 'UPDATE mahasiswa SET id_dosen = ? WHERE id_user = ?');
                if ($update === false) {
                    throw new RuntimeException('Gagal menyiapkan penugasan dosen pendamping.');
                }

                mysqli_stmt_bind_param($update, 'ii', $lecturerId, $studentId);
                if (!mysqli_stmt_execute($update)) {
                    mysqli_stmt_close($update);
                    throw new RuntimeException('Gagal menyimpan penugasan dosen pendamping.');
                }
                mysqli_stmt_close($update);
                recordActivity(
                    $koneksi,
                    (int) ($user['id'] ?? 0),
                    'Atur dosen pendamping',
                    'NIM ' . (string) $student['nim'] . ' · ' . (string) $lecturer['nama_lengkap']
                );
                redirectAdminPage('/dashboard/admin_pendamping.php', ['status' => 'saved']);
            }
        }
    } elseif ($action === 'remove') {
        $studentCheck = mysqli_prepare($koneksi, 'SELECT nim FROM mahasiswa WHERE id_user = ? LIMIT 1');
        if ($studentCheck === false) {
            throw new RuntimeException('Gagal menyiapkan validasi mahasiswa.');
        }

        mysqli_stmt_bind_param($studentCheck, 'i', $studentId);
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
            $remove = mysqli_prepare($koneksi, 'UPDATE mahasiswa SET id_dosen = NULL WHERE id_user = ?');
            if ($remove === false) {
                throw new RuntimeException('Gagal menyiapkan penghapusan penugasan pendamping.');
            }

            mysqli_stmt_bind_param($remove, 'i', $studentId);
            if (!mysqli_stmt_execute($remove)) {
                mysqli_stmt_close($remove);
                throw new RuntimeException('Gagal menghapus penugasan dosen pendamping.');
            }
            mysqli_stmt_close($remove);
            recordActivity(
                $koneksi,
                (int) ($user['id'] ?? 0),
                'Hapus dosen pendamping',
                'NIM ' . (string) $student['nim']
            );
            redirectAdminPage('/dashboard/admin_pendamping.php', ['status' => 'removed']);
        }
    } else {
        $errorMessage = 'Aksi tidak dikenali.';
    }
}

$studentsResult = mysqli_query(
    $koneksi,
    "SELECT m.id_user, m.nim, m.nama_lengkap, m.judul_ta, m.id_dosen,
            d.nama_lengkap AS nama_pendamping, d.nidn
     FROM mahasiswa m
     INNER JOIN users u ON u.id_user = m.id_user AND u.role = 'mahasiswa'
     LEFT JOIN dosen d ON d.id_user = m.id_dosen
     ORDER BY m.nama_lengkap ASC"
);
if ($studentsResult === false) {
    throw new RuntimeException('Gagal mengambil daftar mahasiswa dan pendamping.');
}
$students = mysqli_fetch_all($studentsResult, MYSQLI_ASSOC);
mysqli_free_result($studentsResult);

$lecturersResult = mysqli_query(
    $koneksi,
    "SELECT d.id_user, d.nidn, d.nama_lengkap
     FROM dosen d
     INNER JOIN users u ON u.id_user = d.id_user AND u.role = 'dosen'
     ORDER BY d.nama_lengkap ASC"
);
if ($lecturersResult === false) {
    throw new RuntimeException('Gagal mengambil daftar dosen.');
}
$lecturers = mysqli_fetch_all($lecturersResult, MYSQLI_ASSOC);
mysqli_free_result($lecturersResult);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Data Pendamping Admin</title>
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
    .form-actions, .table-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .message-box { margin-bottom: 16px; border-radius: 14px; padding: 14px 16px; font-weight: 700; line-height: 1.5; }
    .message-success { background: #eaf8ef; color: #246942; border: 1px solid #b6e0c3; }
    .message-error { background: #fdeeee; color: #a12727; border: 1px solid #f0c3c3; }
    .table-wrap { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; min-width: 820px; }
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
          <h1>Kelola Data Pendamping</h1>
          <p>Penugasan pendamping memakai relasi dosen pada data mahasiswa.</p>
        </div>
      </div>
      <span class="header-user">Admin: <?= e((string) ($user['username'] ?? 'Admin')) ?></span>
    </header>

    <section class="page-grid">
      <div class="panel-card">
        <h2>Tambah / Ubah Pendamping</h2>
        <p>Pilih mahasiswa dan dosen. Penugasan baru akan mengganti pendamping mahasiswa saat ini.</p>
        <?php if ($successMessage !== ''): ?><div class="message-box message-success"><?= e($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage !== ''): ?><div class="message-box message-error"><?= e($errorMessage) ?></div><?php endif; ?>
        <?php if ($students === [] || $lecturers === []): ?>
          <div class="empty-state"><?= $students === [] ? 'Belum ada data mahasiswa.' : 'Belum ada data dosen untuk ditugaskan.' ?></div>
        <?php else: ?>
          <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>" />
            <input type="hidden" name="action" value="assign" />
            <label class="form-label" for="student_id">
              Mahasiswa
              <select class="form-select" id="student_id" name="student_id" required>
                <option value="">Pilih mahasiswa</option>
                <?php foreach ($students as $student): ?>
                  <option value="<?= (int) $student['id_user'] ?>"><?= e((string) $student['nim'] . ' — ' . $student['nama_lengkap']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="form-label" for="lecturer_id">
              Dosen pendamping
              <select class="form-select" id="lecturer_id" name="lecturer_id" required>
                <option value="">Pilih dosen</option>
                <?php foreach ($lecturers as $lecturer): ?>
                  <option value="<?= (int) $lecturer['id_user'] ?>"><?= e((string) $lecturer['nama_lengkap'] . ' · NIDN ' . $lecturer['nidn']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <div class="form-actions"><button class="action-button" type="submit">Simpan Penugasan</button></div>
          </form>
        <?php endif; ?>
      </div>

      <div class="panel-card">
        <h2>Tabel Pendamping</h2>
        <p>Hapus penugasan hanya melepas dosen dari mahasiswa; akun dosen dan data mahasiswa tidak dihapus.</p>
        <div class="table-wrap">
          <?php if ($students === []): ?>
            <div class="empty-state">Belum ada data mahasiswa yang tersimpan.</div>
          <?php else: ?>
            <table class="data-table">
              <thead><tr><th>NIM</th><th>Nama Mahasiswa</th><th>Judul TA</th><th>Dosen Pendamping</th><th>Aksi</th></tr></thead>
              <tbody>
                <?php foreach ($students as $student): ?>
                  <tr>
                    <td><?= e((string) $student['nim']) ?></td>
                    <td><?= e((string) $student['nama_lengkap']) ?></td>
                    <td><?= e((string) $student['judul_ta']) ?></td>
                    <td><?= e((string) ($student['nama_pendamping'] ?? 'Belum ditetapkan')) ?></td>
                    <td>
                      <?php if ($student['id_dosen'] !== null): ?>
                        <form method="post" onsubmit="return confirm('Hapus penugasan pendamping mahasiswa ini?');">
                          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>" />
                          <input type="hidden" name="action" value="remove" />
                          <input type="hidden" name="student_id" value="<?= (int) $student['id_user'] ?>" />
                          <button class="danger-button" type="submit">Hapus Penugasan</button>
                        </form>
                      <?php else: ?>
                        Belum ditetapkan
                      <?php endif; ?>
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
