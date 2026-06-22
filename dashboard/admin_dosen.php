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
    'nidn' => '',
    'nama_lengkap' => '',
    'username' => '',
    'password' => '',
];

function redirectToDosenPage(string $status = ''): void
{
    $target = appPath('/dashboard/admin_dosen.php');
    if ($status !== '') {
        $target .= '?status=' . rawurlencode($status);
    }

    header('Location: ' . $target);
    exit;
}

function fetchDosenList(mysqli $koneksi): array
{
    $sql = <<<SQL
        SELECT
            dosen.id_user,
            dosen.nidn,
            dosen.nama_lengkap,
            dosen.username,
            dosen.email,
            users.created_at
        FROM dosen
        INNER JOIN users ON users.id_user = dosen.id_user
        WHERE users.role = 'dosen'
        ORDER BY dosen.nama_lengkap ASC
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

function fetchDosenById(mysqli $koneksi, int $idUser): ?array
{
    $sql = <<<SQL
        SELECT
            dosen.id_user,
            dosen.nidn,
            dosen.nama_lengkap,
            dosen.username,
            dosen.email
        FROM dosen
        INNER JOIN users ON users.id_user = dosen.id_user
        WHERE dosen.id_user = ?
          AND users.role = 'dosen'
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
    $successMessage = 'Data dosen berhasil ditambahkan.';
} elseif ($status === 'updated') {
    $successMessage = 'Data dosen berhasil diperbarui.';
} elseif ($status === 'deleted') {
    $successMessage = 'Data dosen berhasil dihapus.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    $idUser = (int) ($_POST['id_user'] ?? 0);
    $nidn = trim((string) ($_POST['nidn'] ?? ''));
    $namaLengkap = trim((string) ($_POST['nama_lengkap'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $email = '';

    $formData = [
        'id_user' => $idUser,
        'nidn' => $nidn,
        'nama_lengkap' => $namaLengkap,
        'username' => $username,
        'password' => $password,
    ];

    if ($action === 'create' || $action === 'update') {
        if ($nidn === '' || $namaLengkap === '' || $username === '') {
            $errorMessage = 'NIDN, nama lengkap, dan username wajib diisi.';
        } elseif ($action === 'create' && trim($password) === '') {
            $errorMessage = 'Password wajib diisi saat menambahkan dosen.';
        } else {
            mysqli_begin_transaction($koneksi);

            try {
                if ($action === 'create') {
                    $userSql = 'INSERT INTO users (username, password, role) VALUES (?, ?, \'dosen\')';
                    $userStatement = mysqli_prepare($koneksi, $userSql);

                    if ($userStatement === false) {
                        throw new RuntimeException('Gagal menyiapkan query tambah user dosen.');
                    }

                    mysqli_stmt_bind_param($userStatement, 'ss', $username, $password);
                    if (!mysqli_stmt_execute($userStatement)) {
                        throw new RuntimeException('Gagal menambahkan user dosen.');
                    }

                    $newUserId = (int) mysqli_insert_id($koneksi);
                    mysqli_stmt_close($userStatement);

                    $dosenSql = 'INSERT INTO dosen (id_user, nidn, nama_lengkap, username, email) VALUES (?, ?, ?, ?, ?)';
                    $dosenStatement = mysqli_prepare($koneksi, $dosenSql);

                    if ($dosenStatement === false) {
                        throw new RuntimeException('Gagal menyiapkan query tambah dosen.');
                    }

                    mysqli_stmt_bind_param($dosenStatement, 'issss', $newUserId, $nidn, $namaLengkap, $username, $email);
                    if (!mysqli_stmt_execute($dosenStatement)) {
                        throw new RuntimeException('Gagal menambahkan data dosen.');
                    }

                    mysqli_stmt_close($dosenStatement);
                }

                if ($action === 'update') {
                    if ($idUser <= 0 || fetchDosenById($koneksi, $idUser) === null) {
                        throw new RuntimeException('Data dosen yang akan diubah tidak ditemukan.');
                    }

                    if (trim($password) !== '') {
                        $userSql = 'UPDATE users SET username = ?, password = ? WHERE id_user = ? AND role = \'dosen\'';
                        $userStatement = mysqli_prepare($koneksi, $userSql);

                        if ($userStatement === false) {
                            throw new RuntimeException('Gagal menyiapkan query ubah user dosen.');
                        }

                        mysqli_stmt_bind_param($userStatement, 'ssi', $username, $password, $idUser);
                    } else {
                        $userSql = 'UPDATE users SET username = ? WHERE id_user = ? AND role = \'dosen\'';
                        $userStatement = mysqli_prepare($koneksi, $userSql);

                        if ($userStatement === false) {
                            throw new RuntimeException('Gagal menyiapkan query ubah user dosen.');
                        }

                        mysqli_stmt_bind_param($userStatement, 'si', $username, $idUser);
                    }

                    if (!mysqli_stmt_execute($userStatement)) {
                        throw new RuntimeException('Gagal memperbarui user dosen.');
                    }
                    mysqli_stmt_close($userStatement);

                    $dosenSql = 'UPDATE dosen SET nidn = ?, nama_lengkap = ?, username = ? WHERE id_user = ?';
                    $dosenStatement = mysqli_prepare($koneksi, $dosenSql);

                    if ($dosenStatement === false) {
                        throw new RuntimeException('Gagal menyiapkan query ubah dosen.');
                    }

                    mysqli_stmt_bind_param($dosenStatement, 'sssi', $nidn, $namaLengkap, $username, $idUser);
                    if (!mysqli_stmt_execute($dosenStatement)) {
                        throw new RuntimeException('Gagal memperbarui data dosen.');
                    }

                    mysqli_stmt_close($dosenStatement);
                }

                mysqli_commit($koneksi);
                redirectToDosenPage($action === 'create' ? 'created' : 'updated');
            } catch (Throwable $exception) {
                mysqli_rollback($koneksi);
                $errorMessage = isDuplicateEntryError($koneksi)
                    ? 'NIP/NIDN atau username sudah digunakan. Silakan pakai data lain.'
                    : $exception->getMessage();
            }
        }
    }

    if ($action === 'delete') {
        if ($idUser <= 0) {
            $errorMessage = 'Data dosen yang akan dihapus tidak valid.';
        } else {
            mysqli_begin_transaction($koneksi);

            try {
                $dosenStatement = mysqli_prepare($koneksi, 'DELETE FROM dosen WHERE id_user = ?');
                if ($dosenStatement === false) {
                    throw new RuntimeException('Gagal menyiapkan hapus data dosen.');
                }

                mysqli_stmt_bind_param($dosenStatement, 'i', $idUser);
                if (!mysqli_stmt_execute($dosenStatement)) {
                    throw new RuntimeException('Gagal menghapus data dosen.');
                }
                mysqli_stmt_close($dosenStatement);

                $userStatement = mysqli_prepare($koneksi, 'DELETE FROM users WHERE id_user = ? AND role = \'dosen\'');
                if ($userStatement === false) {
                    throw new RuntimeException('Gagal menyiapkan hapus user dosen.');
                }

                mysqli_stmt_bind_param($userStatement, 'i', $idUser);
                if (!mysqli_stmt_execute($userStatement)) {
                    throw new RuntimeException('Gagal menghapus user dosen.');
                }
                mysqli_stmt_close($userStatement);

                mysqli_commit($koneksi);
                redirectToDosenPage('deleted');
            } catch (Throwable $exception) {
                mysqli_rollback($koneksi);
                $errorMessage = $exception->getMessage();
            }
        }
    }
}

if ($editId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $editData = fetchDosenById($koneksi, $editId);

    if ($editData !== null) {
        $isEditMode = true;
        $formData['id_user'] = (int) $editData['id_user'];
        $formData['nidn'] = (string) $editData['nidn'];
        $formData['nama_lengkap'] = (string) $editData['nama_lengkap'];
        $formData['username'] = (string) $editData['username'];
        $formData['password'] = '';
    } else {
        $errorMessage = 'Data dosen yang dipilih tidak ditemukan.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action ?? '') === 'update' && $errorMessage !== '') {
    $isEditMode = true;
}

$dosenList = fetchDosenList($koneksi);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Data Dosen Admin</title>
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

    .form-input {
      width: 100%;
      border: 1px solid #d1d7e2;
      border-radius: 14px;
      padding: 12px 14px;
      font-size: 0.96rem;
      font-family: inherit;
      background: #f8f9fc;
      color: #2f2f2f;
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
          <h1>Kelola Data Dosen</h1>
          <p>Tambah, edit, dan hapus data dosen langsung dari dashboard admin.</p>
        </div>
      </div>

      <div class="header-right">
        <span class="header-user">Admin: <?= e((string) $user['username']) ?></span>
      </div>
    </header>

    <section class="page-grid">
      <div class="panel-card">
        <h2><?= $isEditMode ? 'Edit Dosen' : 'Tambah Dosen' ?></h2>
        <p><?= $isEditMode ? 'Perbarui data dosen yang dipilih. Email dikelola langsung oleh dosen dari halaman profilnya.' : 'Isi form berikut untuk menambahkan akun dosen baru ke sistem. Email dapat diisi nanti oleh dosen melalui halaman profil.' ?></p>

        <?php if ($successMessage !== ''): ?>
          <div class="message-box message-success"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
          <div class="message-box message-error"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <form method="post" class="form-grid">
          <input type="hidden" name="action" value="<?= $isEditMode ? 'update' : 'create' ?>" />
          <input type="hidden" name="id_user" value="<?= (int) $formData['id_user'] ?>" />

          <label class="form-label" for="nidn">
            NIDN
            <input class="form-input" id="nidn" name="nidn" type="text" maxlength="9" value="<?= e((string) $formData['nidn']) ?>" required />
          </label>

          <label class="form-label" for="nama_lengkap">
            Nama Lengkap
            <input class="form-input" id="nama_lengkap" name="nama_lengkap" type="text" value="<?= e((string) $formData['nama_lengkap']) ?>" required />
          </label>

          <label class="form-label" for="username">
            Username
            <input class="form-input" id="username" name="username" type="text" value="<?= e((string) $formData['username']) ?>" required />
          </label>

          <label class="form-label" for="password">
            Password <?= $isEditMode ? '(opsional)' : '' ?>
            <input class="form-input" id="password" name="password" type="text" value="<?= e((string) $formData['password']) ?>" <?= $isEditMode ? '' : 'required' ?> />
          </label>

          <div class="form-actions">
            <button class="action-button" type="submit"><?= $isEditMode ? 'Simpan Perubahan' : 'Tambah Dosen' ?></button>
            <?php if ($isEditMode): ?>
              <a class="link-button secondary-button" href="<?= e(appPath('/dashboard/admin_dosen.php')) ?>">Batal Edit</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div class="panel-card">
        <h2>Tabel Dosen</h2>
        <p>Data di bawah ini diambil langsung dari database dan tersinkron dengan akun login dosen.</p>

        <div class="table-wrap">
          <?php if ($dosenList === []): ?>
            <div class="empty-state">Belum ada data dosen yang tersimpan.</div>
          <?php else: ?>
            <table class="data-table">
              <thead>
                <tr>
                  <th>ID User</th>
                  <th>NIDN</th>
                  <th>Nama Lengkap</th>
                  <th>Username</th>
                  <th>Email</th>
                  <th>Dibuat</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($dosenList as $dosen): ?>
                  <tr>
                    <td><?= e((string) $dosen['id_user']) ?></td>
                    <td><?= e((string) $dosen['nidn']) ?></td>
                    <td><?= e((string) $dosen['nama_lengkap']) ?></td>
                    <td><?= e((string) $dosen['username']) ?></td>
                    <td><?= e((string) $dosen['email']) ?></td>
                    <td><?= e((string) $dosen['created_at']) ?></td>
                    <td>
                      <div class="table-actions">
                        <a class="table-link" href="<?= e(appPath('/dashboard/admin_dosen.php')) . '?edit=' . (int) $dosen['id_user'] ?>">Edit</a>
                        <form class="table-inline-form" method="post" onsubmit="return confirm('Hapus data dosen ini?');">
                          <input type="hidden" name="action" value="delete" />
                          <input type="hidden" name="id_user" value="<?= (int) $dosen['id_user'] ?>" />
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
