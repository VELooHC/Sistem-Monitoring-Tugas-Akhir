<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireLogin(['admin']);
require_once __DIR__ . '/../config/koneksi.php';

$user = currentUser();
$successMessage = '';
$errorMessage = '';
$isEditMode = false;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$status = trim((string) ($_GET['status'] ?? ''));

$formData = [
    'id_user' => 0,
    'nim' => '',
    'nama_lengkap' => '',
    'username' => '',
    'judul_ta' => '',
    'password' => '',
];

function redirectToMahasiswaPage(string $status = ''): void
{
    $target = appPath('/dashboard/admin_mahasiswa.php');
    if ($status !== '') {
        $target .= '?status=' . rawurlencode($status);
    }

    header('Location: ' . $target);
    exit;
}

function fetchMahasiswaList(mysqli $koneksi): array
{
    $sql = <<<SQL
        SELECT
            mahasiswa.id_user,
            mahasiswa.nim,
            mahasiswa.nama_lengkap,
            mahasiswa.username,
            mahasiswa.judul_ta,
            users.created_at
        FROM mahasiswa
        INNER JOIN users ON users.id_user = mahasiswa.id_user
        WHERE users.role = 'mahasiswa'
        ORDER BY mahasiswa.nama_lengkap ASC
    SQL;

    $result = mysqli_query($koneksi, $sql);
    if ($result === false) {
        return [];
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_free_result($result);
    return $rows;
}

function fetchMahasiswaById(mysqli $koneksi, int $idUser): ?array
{
    $sql = <<<SQL
        SELECT
            mahasiswa.id_user,
            mahasiswa.nim,
            mahasiswa.nama_lengkap,
            mahasiswa.username,
            mahasiswa.judul_ta
        FROM mahasiswa
        INNER JOIN users ON users.id_user = mahasiswa.id_user
        WHERE mahasiswa.id_user = ?
          AND users.role = 'mahasiswa'
        LIMIT 1
    SQL;

    $statement = mysqli_prepare($koneksi, $sql);
    if ($statement === false) {
        return null;
    }

    mysqli_stmt_bind_param($statement, 'i', $idUser);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $row = $result instanceof mysqli_result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($statement);

    return $row ?: null;
}

function isDuplicateEntryError(mysqli $koneksi): bool
{
    return (int) mysqli_errno($koneksi) === 1062;
}

if ($status === 'created') {
    $successMessage = 'Data mahasiswa berhasil ditambahkan.';
} elseif ($status === 'updated') {
    $successMessage = 'Data mahasiswa berhasil diperbarui.';
} elseif ($status === 'deleted') {
    $successMessage = 'Data mahasiswa berhasil dihapus.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    $idUser = (int) ($_POST['id_user'] ?? 0);
    $nim = trim((string) ($_POST['nim'] ?? ''));
    $namaLengkap = trim((string) ($_POST['nama_lengkap'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $judulTa = trim((string) ($_POST['judul_ta'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $formData = [
        'id_user' => $idUser,
        'nim' => $nim,
        'nama_lengkap' => $namaLengkap,
        'username' => $username,
        'judul_ta' => $judulTa,
        'password' => $password,
    ];

    if ($action === 'create' || $action === 'update') {
        if ($nim === '' || $namaLengkap === '' || $username === '' || $judulTa === '') {
            $errorMessage = 'Semua field mahasiswa wajib diisi.';
        } elseif ($action === 'create' && trim($password) === '') {
            $errorMessage = 'Password wajib diisi saat menambahkan mahasiswa.';
        } else {
            mysqli_begin_transaction($koneksi);

            try {
                if ($action === 'create') {
                    $userSql = 'INSERT INTO users (username, password, role) VALUES (?, ?, \'mahasiswa\')';
                    $userStatement = mysqli_prepare($koneksi, $userSql);

                    if ($userStatement === false) {
                        throw new RuntimeException('Gagal menyiapkan query tambah user.');
                    }

                    mysqli_stmt_bind_param($userStatement, 'ss', $username, $password);
                    if (!mysqli_stmt_execute($userStatement)) {
                        throw new RuntimeException('Gagal menambahkan user mahasiswa.');
                    }

                    $newUserId = (int) mysqli_insert_id($koneksi);
                    mysqli_stmt_close($userStatement);

                    $mahasiswaSql = 'INSERT INTO mahasiswa (id_user, nim, nama_lengkap, username, judul_ta) VALUES (?, ?, ?, ?, ?)';
                    $mahasiswaStatement = mysqli_prepare($koneksi, $mahasiswaSql);

                    if ($mahasiswaStatement === false) {
                        throw new RuntimeException('Gagal menyiapkan query tambah mahasiswa.');
                    }

                    mysqli_stmt_bind_param($mahasiswaStatement, 'issss', $newUserId, $nim, $namaLengkap, $username, $judulTa);
                    if (!mysqli_stmt_execute($mahasiswaStatement)) {
                        throw new RuntimeException('Gagal menambahkan data mahasiswa.');
                    }

                    mysqli_stmt_close($mahasiswaStatement);
                }

                if ($action === 'update') {
                    if ($idUser <= 0 || fetchMahasiswaById($koneksi, $idUser) === null) {
                        throw new RuntimeException('Data mahasiswa yang akan diubah tidak ditemukan.');
                    }

                    if (trim($password) !== '') {
                        $userSql = 'UPDATE users SET username = ?, password = ? WHERE id_user = ? AND role = \'mahasiswa\'';
                        $userStatement = mysqli_prepare($koneksi, $userSql);

                        if ($userStatement === false) {
                            throw new RuntimeException('Gagal menyiapkan query ubah user.');
                        }

                        mysqli_stmt_bind_param($userStatement, 'ssi', $username, $password, $idUser);
                    } else {
                        $userSql = 'UPDATE users SET username = ? WHERE id_user = ? AND role = \'mahasiswa\'';
                        $userStatement = mysqli_prepare($koneksi, $userSql);

                        if ($userStatement === false) {
                            throw new RuntimeException('Gagal menyiapkan query ubah user.');
                        }

                        mysqli_stmt_bind_param($userStatement, 'si', $username, $idUser);
                    }

                    if (!mysqli_stmt_execute($userStatement)) {
                        throw new RuntimeException('Gagal memperbarui user mahasiswa.');
                    }

                    mysqli_stmt_close($userStatement);

                    $mahasiswaSql = 'UPDATE mahasiswa SET nim = ?, nama_lengkap = ?, username = ?, judul_ta = ? WHERE id_user = ?';
                    $mahasiswaStatement = mysqli_prepare($koneksi, $mahasiswaSql);

                    if ($mahasiswaStatement === false) {
                        throw new RuntimeException('Gagal menyiapkan query ubah mahasiswa.');
                    }

                    mysqli_stmt_bind_param($mahasiswaStatement, 'ssssi', $nim, $namaLengkap, $username, $judulTa, $idUser);
                    if (!mysqli_stmt_execute($mahasiswaStatement)) {
                        throw new RuntimeException('Gagal memperbarui data mahasiswa.');
                    }

                    mysqli_stmt_close($mahasiswaStatement);
                }

                mysqli_commit($koneksi);
                redirectToMahasiswaPage($action === 'create' ? 'created' : 'updated');
            } catch (Throwable $exception) {
                mysqli_rollback($koneksi);
                $errorMessage = isDuplicateEntryError($koneksi)
                    ? 'NIM atau username sudah digunakan. Silakan pakai data lain.'
                    : $exception->getMessage();
            }
        }
    }

    if ($action === 'delete') {
        if ($idUser <= 0) {
            $errorMessage = 'Data mahasiswa yang akan dihapus tidak valid.';
        } else {
            mysqli_begin_transaction($koneksi);

            try {
                $mahasiswaStatement = mysqli_prepare($koneksi, 'DELETE FROM mahasiswa WHERE id_user = ?');
                if ($mahasiswaStatement === false) {
                    throw new RuntimeException('Gagal menyiapkan hapus data mahasiswa.');
                }

                mysqli_stmt_bind_param($mahasiswaStatement, 'i', $idUser);
                if (!mysqli_stmt_execute($mahasiswaStatement)) {
                    throw new RuntimeException('Gagal menghapus data mahasiswa.');
                }
                mysqli_stmt_close($mahasiswaStatement);

                $userStatement = mysqli_prepare($koneksi, 'DELETE FROM users WHERE id_user = ? AND role = \'mahasiswa\'');
                if ($userStatement === false) {
                    throw new RuntimeException('Gagal menyiapkan hapus user mahasiswa.');
                }

                mysqli_stmt_bind_param($userStatement, 'i', $idUser);
                if (!mysqli_stmt_execute($userStatement)) {
                    throw new RuntimeException('Gagal menghapus user mahasiswa.');
                }
                mysqli_stmt_close($userStatement);

                mysqli_commit($koneksi);
                redirectToMahasiswaPage('deleted');
            } catch (Throwable $exception) {
                mysqli_rollback($koneksi);
                $errorMessage = $exception->getMessage();
            }
        }
    }
}

if ($editId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $editData = fetchMahasiswaById($koneksi, $editId);

    if ($editData !== null) {
        $isEditMode = true;
        $formData['id_user'] = (int) $editData['id_user'];
        $formData['nim'] = (string) $editData['nim'];
        $formData['nama_lengkap'] = (string) $editData['nama_lengkap'];
        $formData['username'] = (string) $editData['username'];
        $formData['judul_ta'] = (string) $editData['judul_ta'];
        $formData['password'] = '';
    } else {
        $errorMessage = 'Data mahasiswa yang dipilih tidak ditemukan.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action ?? '') === 'update' && $errorMessage !== '') {
    $isEditMode = true;
}

$mahasiswaList = fetchMahasiswaList($koneksi);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Data Mahasiswa Admin</title>
  <link rel="stylesheet" href="../css/styles.css" />
  <style>
    * {
      box-sizing: border-box;
    }

    body.admin-page {
      margin: 0;
      min-height: 100vh;
      background: #eef0f4;
      font-family: "Trebuchet MS", Arial, sans-serif;
      color: #2f2f2f;
    }

    .page-shell {
      width: 100%;
      max-width: 1440px;
      min-height: 100vh;
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      gap: 20px;
      padding: 24px;
    }

    .page-header,
    .panel-card {
      background: #ffffff;
      border: 1px solid #dadfe8;
      border-radius: 22px;
      box-shadow: 0 14px 34px rgba(30, 34, 44, 0.06);
    }

    .page-header {
      padding: 22px 24px;
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
    }

    .header-left {
      display: grid;
      gap: 14px;
    }

    .header-right {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 12px;
      flex-wrap: wrap;
      margin-left: auto;
    }

    .page-title h1 {
      margin: 0 0 6px;
      font-size: 1.6rem;
    }

    .page-title p {
      margin: 0;
      font-weight: 700;
      color: #5c6471;
    }

    .header-actions {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }

    .header-user {
      padding: 10px 16px;
      border-radius: 999px;
      background: #ebe7ff;
      color: #4d38c7;
      font-weight: 800;
    }

    .header-back {
      width: fit-content;
    }

    .link-button,
    .action-button,
    .danger-button {
      border: 0;
      border-radius: 12px;
      padding: 11px 16px;
      font-weight: 700;
      text-decoration: none;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .link-button,
    .action-button {
      background: #4d38c7;
      color: #ffffff;
    }

    .danger-button {
      background: #c62828;
      color: #ffffff;
    }

    .page-grid {
      display: grid;
      grid-template-columns: minmax(320px, 380px) minmax(0, 1fr);
      gap: 20px;
      align-items: start;
    }

    .panel-card {
      padding: 22px;
    }

    .panel-card h2 {
      margin: 0 0 8px;
      font-size: 1.2rem;
    }

    .panel-card > p {
      margin: 0 0 18px;
      color: #5c6471;
      font-weight: 700;
      line-height: 1.5;
    }

    .message-box {
      margin-bottom: 16px;
      border-radius: 14px;
      padding: 14px 16px;
      font-weight: 700;
      line-height: 1.5;
    }

    .message-success {
      background: #eaf8ef;
      color: #246942;
      border: 1px solid #b6e0c3;
    }

    .message-error {
      background: #fdeeee;
      color: #a12727;
      border: 1px solid #f0c3c3;
    }

    .form-grid {
      display: grid;
      gap: 14px;
    }

    .form-label {
      display: grid;
      gap: 8px;
      font-weight: 800;
      color: #404754;
    }

    .form-input,
    .form-textarea {
      width: 100%;
      border: 1px solid #d1d7e2;
      border-radius: 14px;
      padding: 12px 14px;
      font-size: 0.96rem;
      font-family: inherit;
      background: #f8f9fc;
      color: #2f2f2f;
    }

    .form-textarea {
      min-height: 104px;
      resize: vertical;
    }

    .form-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 6px;
    }

    .secondary-button {
      background: #edf0f6;
      color: #445062;
    }

    .table-wrap {
      overflow-x: auto;
    }

    .data-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 760px;
    }

    .data-table th,
    .data-table td {
      padding: 14px 12px;
      border-bottom: 1px solid #e5e9f0;
      text-align: left;
      vertical-align: top;
    }

    .data-table th {
      font-size: 0.9rem;
      color: #596271;
      background: #f8f9fc;
    }

    .data-table td {
      font-weight: 700;
      color: #39414e;
    }

    .table-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .table-link {
      border-radius: 10px;
      padding: 8px 12px;
      font-size: 0.9rem;
      font-weight: 700;
      text-decoration: none;
      background: #ece8ff;
      color: #4d38c7;
    }

    .table-inline-form {
      margin: 0;
    }

    .empty-state {
      padding: 18px;
      border-radius: 16px;
      background: #f8f9fc;
      color: #5c6471;
      font-weight: 700;
      line-height: 1.6;
    }

    @media (max-width: 960px) {
      .page-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 640px) {
      .page-shell {
        padding: 16px;
      }

      .page-header {
        padding: 18px;
        flex-direction: column;
        align-items: flex-start;
      }

      .header-right {
        margin-left: 0;
        justify-content: flex-start;
      }

      .panel-card {
        padding: 18px;
      }
    }
  </style>
</head>
<body class="admin-page">
  <div class="page-shell">
    <header class="page-header">
      <div class="header-left">
        <a class="link-button secondary-button header-back" href="<?= e(appPath('/dashboard/admin_dashboard.php')) ?>">Kembali ke Dashboard</a>
        <div class="page-title">
          <h1>Kelola Data Mahasiswa</h1>
          <p>Tambah, edit, dan hapus data mahasiswa langsung dari dashboard admin.</p>
        </div>
      </div>

      <div class="header-right">
        <span class="header-user">Admin: <?= e((string) $user['username']) ?></span>
      </div>
    </header>

    <section class="page-grid">
      <div class="panel-card">
        <h2><?= $isEditMode ? 'Edit Mahasiswa' : 'Tambah Mahasiswa' ?></h2>
        <p><?= $isEditMode ? 'Perbarui data mahasiswa yang dipilih. Kosongkan password bila tidak ingin mengubah password login.' : 'Isi form berikut untuk menambahkan akun mahasiswa baru ke sistem.' ?></p>

        <?php if ($successMessage !== ''): ?>
          <div class="message-box message-success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
          <div class="message-box message-error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <form method="post" class="form-grid">
          <input type="hidden" name="action" value="<?= $isEditMode ? 'update' : 'create' ?>" />
          <input type="hidden" name="id_user" value="<?= (int) $formData['id_user'] ?>" />

          <label class="form-label" for="nim">
            NIM
            <input class="form-input" id="nim" name="nim" type="text" maxlength="9" value="<?= e((string) $formData['nim']) ?>" required />
          </label>

          <label class="form-label" for="nama_lengkap">
            Nama Lengkap
            <input class="form-input" id="nama_lengkap" name="nama_lengkap" type="text" value="<?= e((string) $formData['nama_lengkap']) ?>" required />
          </label>

          <label class="form-label" for="username">
            Username
            <input class="form-input" id="username" name="username" type="text" value="<?= e((string) $formData['username']) ?>" required />
          </label>

          <label class="form-label" for="judul_ta">
            Judul Tugas Akhir
            <textarea class="form-textarea" id="judul_ta" name="judul_ta" required><?= e((string) $formData['judul_ta']) ?></textarea>
          </label>

          <label class="form-label" for="password">
            Password <?= $isEditMode ? '(opsional)' : '' ?>
            <input class="form-input" id="password" name="password" type="text" value="<?= e((string) $formData['password']) ?>" <?= $isEditMode ? '' : 'required' ?> />
          </label>

          <div class="form-actions">
            <button class="action-button" type="submit"><?= $isEditMode ? 'Simpan Perubahan' : 'Tambah Mahasiswa' ?></button>
            <?php if ($isEditMode): ?>
              <a class="link-button secondary-button" href="<?= e(appPath('/dashboard/admin_mahasiswa.php')) ?>">Batal Edit</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div class="panel-card">
        <h2>Tabel Mahasiswa</h2>
        <p>Data di bawah ini diambil langsung dari database dan tersinkron dengan akun login mahasiswa.</p>

        <div class="table-wrap">
          <?php if ($mahasiswaList === []): ?>
            <div class="empty-state">Belum ada data mahasiswa yang tersimpan.</div>
          <?php else: ?>
            <table class="data-table">
              <thead>
                <tr>
                  <th>ID User</th>
                  <th>NIM</th>
                  <th>Nama Lengkap</th>
                  <th>Username</th>
                  <th>Judul TA</th>
                  <th>Dibuat</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($mahasiswaList as $mahasiswa): ?>
                  <tr>
                    <td><?= e((string) $mahasiswa['id_user']) ?></td>
                    <td><?= e((string) $mahasiswa['nim']) ?></td>
                    <td><?= e((string) $mahasiswa['nama_lengkap']) ?></td>
                    <td><?= e((string) $mahasiswa['username']) ?></td>
                    <td><?= e((string) $mahasiswa['judul_ta']) ?></td>
                    <td><?= e((string) $mahasiswa['created_at']) ?></td>
                    <td>
                      <div class="table-actions">
                        <a class="table-link" href="<?= e(appPath('/dashboard/admin_mahasiswa.php')) . '?edit=' . (int) $mahasiswa['id_user'] ?>">Edit</a>
                        <form class="table-inline-form" method="post" onsubmit="return confirm('Hapus data mahasiswa ini?');">
                          <input type="hidden" name="action" value="delete" />
                          <input type="hidden" name="id_user" value="<?= (int) $mahasiswa['id_user'] ?>" />
                          <button class="danger-button" type="submit">Hapus</button>
                        </form>
                      </div>
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
