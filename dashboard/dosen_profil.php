<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';

requireLogin(['dosen']);
$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
$sidebarMenus = [
    ['label' => 'Dashboard', 'href' => '/dashboard/dosen_dashboard.php', 'active' => false],
    ['label' => 'Jadwal Bimbingan', 'href' => '#', 'active' => false],
    ['label' => 'Data Mahasiswa', 'href' => '#', 'active' => false],
    ['label' => 'Riwayat Seminar', 'href' => '#', 'active' => false],
    ['label' => 'Profil Dosen', 'href' => '/dashboard/dosen_profil.php', 'active' => true],
];
$successMessage = '';
$errorMessage = '';

function fetchLoggedDosen(mysqli $koneksi, int $idUser): ?array
{
    $sql = <<<SQL
        SELECT
            users.id_user,
            users.username,
            users.password,
            dosen.nidn,
            dosen.nama_lengkap,
            dosen.email
        FROM users
        INNER JOIN dosen ON dosen.id_user = users.id_user
        WHERE users.id_user = ?
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

function redirectDosenProfil(string $status): void
{
    $target = appPath('/dashboard/dosen_profil.php');
    if ($status !== '') {
        $target .= '?status=' . rawurlencode($status);
    }

    header('Location: ' . $target);
    exit;
}

$accountData = fetchLoggedDosen($koneksi, $userId);
if ($accountData === null) {
    $errorMessage = 'Data akun dosen tidak ditemukan.';
    $accountData = [
        'id_user' => $userId,
        'username' => (string) ($user['username'] ?? ''),
        'password' => '',
        'nidn' => '',
        'nama_lengkap' => '',
        'email' => '',
    ];
}

$formData = [
    'username' => (string) ($accountData['username'] ?? ''),
    'email' => (string) ($accountData['email'] ?? ''),
    'password' => '',
    'password_confirmation' => '',
];

$status = trim((string) ($_GET['status'] ?? ''));
if ($status === 'updated') {
    $successMessage = 'Data akun dosen berhasil diperbarui.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newUsername = trim((string) ($_POST['username'] ?? ''));
    $newEmail = trim((string) ($_POST['email'] ?? ''));
    $newPassword = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

    $formData = [
        'username' => $newUsername,
        'email' => $newEmail,
        'password' => $newPassword,
        'password_confirmation' => $passwordConfirmation,
    ];

    if ((int) ($accountData['id_user'] ?? 0) !== $userId) {
        $errorMessage = 'Akun dosen yang diproses tidak valid.';
    } elseif ($newUsername === '') {
        $errorMessage = 'Username wajib diisi.';
    } elseif ($newEmail !== '' && filter_var($newEmail, FILTER_VALIDATE_EMAIL) === false) {
        $errorMessage = 'Format email tidak valid.';
    } elseif ($newPassword !== '' && strlen($newPassword) < 6) {
        $errorMessage = 'Password baru minimal 6 karakter.';
    } elseif ($newPassword !== '' && $newPassword !== $passwordConfirmation) {
        $errorMessage = 'Konfirmasi password tidak cocok.';
    } else {
        mysqli_begin_transaction($koneksi);

        try {
            if ($newPassword !== '') {
                $userSql = 'UPDATE users SET username = ?, password = ? WHERE id_user = ? AND role = \'dosen\'';
                $userStatement = mysqli_prepare($koneksi, $userSql);

                if ($userStatement === false) {
                    throw new RuntimeException('Gagal menyiapkan query update user dosen.');
                }

                mysqli_stmt_bind_param($userStatement, 'ssi', $newUsername, $newPassword, $userId);
            } else {
                $userSql = 'UPDATE users SET username = ? WHERE id_user = ? AND role = \'dosen\'';
                $userStatement = mysqli_prepare($koneksi, $userSql);

                if ($userStatement === false) {
                    throw new RuntimeException('Gagal menyiapkan query update user dosen.');
                }

                mysqli_stmt_bind_param($userStatement, 'si', $newUsername, $userId);
            }

            if (!mysqli_stmt_execute($userStatement)) {
                throw new RuntimeException('Gagal memperbarui username atau password dosen.');
            }
            mysqli_stmt_close($userStatement);

            $emailToSave = $newEmail !== '' ? $newEmail : '';
            $dosenSql = 'UPDATE dosen SET username = ?, email = ? WHERE id_user = ?';
            $dosenStatement = mysqli_prepare($koneksi, $dosenSql);

            if ($dosenStatement === false) {
                throw new RuntimeException('Gagal menyiapkan query update data dosen.');
            }

            mysqli_stmt_bind_param($dosenStatement, 'ssi', $newUsername, $emailToSave, $userId);
            if (!mysqli_stmt_execute($dosenStatement)) {
                throw new RuntimeException('Gagal memperbarui email dosen.');
            }
            mysqli_stmt_close($dosenStatement);

            mysqli_commit($koneksi);
            $_SESSION['username'] = $newUsername;
            redirectDosenProfil('updated');
        } catch (Throwable $exception) {
            mysqli_rollback($koneksi);
            $errorMessage = (int) mysqli_errno($koneksi) === 1062
                ? 'Username sudah digunakan. Silakan pakai data lain.'
                : $exception->getMessage();
        }
    }
}

$accountData = fetchLoggedDosen($koneksi, $userId) ?? $accountData;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Profil Dosen</title>
  <link rel="stylesheet" href="../css/styles.css" />
  <style>
    body.dashboard-screen {
      margin: 0;
      min-height: 100vh;
      background: #eef0f4;
      font-family: "Trebuchet MS", Arial, sans-serif;
      color: #2f2f2f;
    }

    .dashboard-shell {
      min-height: 100vh;
      display: flex;
      background: #eef0f4;
    }

    .dashboard-sidebar {
      width: 280px;
      padding: 28px 22px;
      background: #444854;
      color: #ffffff;
      display: flex;
      flex-direction: column;
      gap: 26px;
    }

    .sidebar-brand {
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .sidebar-brand svg {
      width: 54px;
      height: 54px;
      flex: 0 0 auto;
    }

    .brand-copy {
      display: grid;
      gap: 4px;
    }

    .brand-copy strong {
      font-size: 1.15rem;
      letter-spacing: 0.03em;
    }

    .brand-copy span {
      font-size: 0.88rem;
      color: rgba(255, 255, 255, 0.74);
    }

    .sidebar-menu {
      display: grid;
      gap: 10px;
    }

    .sidebar-link {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 14px;
      border-radius: 12px;
      color: #f4f4f5;
      text-decoration: none;
      font-weight: 700;
      letter-spacing: 0.01em;
      transition: background 0.2s ease, color 0.2s ease;
    }

    .sidebar-link:hover,
    .sidebar-link.is-active {
      background: rgba(255, 255, 255, 0.14);
      color: #ffffff;
    }

    .sidebar-link svg {
      width: 20px;
      height: 20px;
      stroke: currentColor;
      flex: 0 0 auto;
    }

    .sidebar-footer {
      margin-top: auto;
      padding-top: 18px;
      border-top: 1px solid rgba(255, 255, 255, 0.16);
      display: grid;
      gap: 14px;
    }

    .sidebar-user {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .sidebar-user svg {
      width: 46px;
      height: 46px;
      flex: 0 0 auto;
    }

    .sidebar-user strong,
    .sidebar-user span {
      display: block;
    }

    .sidebar-user span {
      margin-top: 3px;
      font-size: 0.86rem;
      color: rgba(255, 255, 255, 0.72);
    }

    .logout-link {
      display: inline-flex;
      align-items: center;
      justify-content: flex-start;
      gap: 12px;
      text-decoration: none;
      padding: 12px 14px;
      border-radius: 12px;
      color: #ffffff;
      background: rgba(255, 255, 255, 0.08);
      font-weight: 700;
    }

    .logout-link svg {
      width: 20px;
      height: 20px;
      stroke: currentColor;
    }

    .dashboard-main {
      flex: 1;
      padding: 28px;
    }

    .dashboard-header {
      background: #ffffff;
      border: 1px solid #dadee7;
      border-radius: 22px;
      padding: 24px 26px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
      box-shadow: 0 16px 40px rgba(28, 33, 45, 0.06);
    }

    .header-copy h1 {
      margin: 0 0 8px;
      font-size: 1.55rem;
    }

    .header-copy p {
      margin: 0;
      color: #5d6472;
      line-height: 1.5;
      font-weight: 700;
    }

    .header-badge {
      padding: 10px 16px;
      border-radius: 999px;
      background: #ece8ff;
      color: #4a32c3;
      font-size: 0.92rem;
      font-weight: 800;
      white-space: nowrap;
    }

    .dashboard-content {
      padding-top: 24px;
      display: grid;
      grid-template-columns: minmax(0, 1.25fr) minmax(320px, 1fr);
      gap: 20px;
    }

    .content-column {
      display: grid;
      gap: 20px;
    }

    .info-card {
      border: 1px solid #dadee7;
      border-radius: 20px;
      padding: 22px;
      background: #ffffff;
      box-shadow: 0 12px 30px rgba(28, 33, 45, 0.05);
    }

    .info-card h2 {
      margin: 0 0 14px;
      font-size: 1.08rem;
    }

    .info-card p {
      margin: 0 0 10px;
      font-weight: 700;
      line-height: 1.5;
      color: #515865;
    }

    .info-card p:last-child {
      margin-bottom: 0;
    }

    .list-block {
      margin: 0;
      padding: 0;
      list-style: none;
      display: grid;
      gap: 12px;
    }

    .account-row {
      display: grid;
      gap: 4px;
      padding: 14px 16px;
      border-radius: 16px;
      background: #f5f7fb;
    }

    .account-row span {
      color: #67707d;
      font-size: 0.88rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .account-row strong {
      color: #2f3642;
      font-size: 0.98rem;
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

    .account-form {
      display: grid;
      gap: 14px;
    }

    .form-label {
      display: grid;
      gap: 8px;
      color: #475061;
      font-size: 0.92rem;
      font-weight: 800;
    }

    .form-input {
      width: 100%;
      border: 1px solid #d5dbe5;
      border-radius: 14px;
      padding: 12px 14px;
      font-size: 0.96rem;
      font-family: inherit;
      color: #2f3642;
      background: #f8f9fc;
    }

    .form-help {
      margin: -2px 0 0;
      color: #757e8c;
      font-size: 0.88rem;
      font-weight: 700;
      line-height: 1.5;
    }

    .form-actions {
      display: flex;
      justify-content: flex-end;
      margin-top: 6px;
    }

    .action-button {
      border: 0;
      border-radius: 999px;
      padding: 12px 22px;
      background: #4a32c3;
      color: #ffffff;
      font-size: 0.95rem;
      font-weight: 800;
      cursor: pointer;
      box-shadow: 0 12px 24px rgba(74, 50, 195, 0.22);
    }

    .action-button:hover {
      background: #3f26b1;
    }

    @media (max-width: 920px) {
      .dashboard-shell {
        flex-direction: column;
      }

      .dashboard-sidebar {
        width: auto;
      }

      .dashboard-content {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 640px) {
      .dashboard-main {
        padding: 18px;
      }

      .dashboard-sidebar {
        padding: 22px 18px;
      }

      .dashboard-header {
        padding: 20px;
        flex-direction: column;
        align-items: flex-start;
      }

      .form-actions {
        justify-content: stretch;
      }

      .action-button {
        width: 100%;
      }
    }
  </style>
</head>
<body class="dashboard-screen">
  <div class="dashboard-shell">
    <aside class="dashboard-sidebar" aria-label="Sidebar dosen">
      <div class="sidebar-brand">
        <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <circle cx="32" cy="32" r="29" fill="#EFEFEF" stroke="#A3A4AA" stroke-width="3" />
          <circle cx="32" cy="23" r="10" stroke="#A3A4AA" stroke-width="3" />
          <path d="M15 49C18.5 40.5 24.6 37 32 37C39.4 37 45.5 40.5 49 49" stroke="#A3A4AA" stroke-width="3" stroke-linecap="round" />
        </svg>
        <div class="brand-copy">
          <strong>Panel Dosen</strong>
          <span>Sistem bimbingan tugas akhir</span>
        </div>
      </div>

      <nav class="sidebar-menu" aria-label="Menu dosen">
        <?php foreach ($sidebarMenus as $menu): ?>
          <a class="sidebar-link<?= $menu['active'] ? ' is-active' : '' ?>" href="<?= $menu['href'] === '#' ? '#' : e(appPath((string) $menu['href'])) ?>">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path d="M4 6.5H20" stroke-width="1.8" stroke-linecap="round" />
              <path d="M4 12H20" stroke-width="1.8" stroke-linecap="round" />
              <path d="M4 17.5H20" stroke-width="1.8" stroke-linecap="round" />
            </svg>
            <span><?= e((string) $menu['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>

      <div class="sidebar-footer">
        <div class="sidebar-user">
          <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <circle cx="32" cy="32" r="29" fill="#EFEFEF" stroke="#A3A4AA" stroke-width="3" />
            <circle cx="32" cy="23" r="10" stroke="#A3A4AA" stroke-width="3" />
            <path d="M15 49C18.5 40.5 24.6 37 32 37C39.4 37 45.5 40.5 49 49" stroke="#A3A4AA" stroke-width="3" stroke-linecap="round" />
          </svg>
          <div>
            <strong><?= e((string) $user['username']) ?></strong>
            <span>Dosen aktif</span>
          </div>
        </div>

      </div>
    </aside>

    <div class="dashboard-main">
      <header class="dashboard-header">
        <div class="header-copy">
          <h1>Profil Dosen</h1>
          <p>Kelola username, password, dan email akun dosen secara mandiri dari halaman profil.</p>
        </div>
        <span class="header-badge">Role: Dosen</span>
      </header>

      <main class="dashboard-content">
        <div class="content-column">
          <section class="info-card">
            <h2>Data Akun Saat Ini</h2>
            <div class="list-block">
              <div class="account-row">
                <span>Nama Lengkap</span>
                <strong><?= e((string) $accountData['nama_lengkap']) ?></strong>
              </div>
              <div class="account-row">
                <span>NIP / NIDN</span>
                <strong><?= e((string) ($accountData['nidn'] ?? '')) ?></strong>
              </div>
              <div class="account-row">
                <span>Username</span>
                <strong><?= e((string) $accountData['username']) ?></strong>
              </div>
              <div class="account-row">
                <span>Email</span>
                <strong><?= e((string) (($accountData['email'] ?? '') !== '' ? $accountData['email'] : 'Belum ditambahkan')) ?></strong>
              </div>
            </div>
          </section>
        </div>

        <div class="content-column">
          <section class="info-card">
            <h2>Ubah Data Akun</h2>
            <p>Email dapat dibiarkan kosong saat akun dibuat oleh admin, lalu ditambahkan atau diganti dari halaman profil ini.</p>

            <?php if ($successMessage !== ''): ?>
              <div class="message-box message-success"><?= e($successMessage) ?></div>
            <?php endif; ?>

            <?php if ($errorMessage !== ''): ?>
              <div class="message-box message-error"><?= e($errorMessage) ?></div>
            <?php endif; ?>

            <form class="account-form" method="post">
              <label class="form-label" for="username">
                Ganti Username
                <input class="form-input" id="username" name="username" type="text" value="<?= e((string) $formData['username']) ?>" required />
              </label>

              <label class="form-label" for="email">
                Tambahkan atau Ganti Email
                <input class="form-input" id="email" name="email" type="email" value="<?= e((string) $formData['email']) ?>" placeholder="contoh@email.com" />
              </label>

              <label class="form-label" for="password">
                Ganti Password
                <input class="form-input" id="password" name="password" type="password" placeholder="Kosongkan jika tidak ingin mengganti password" />
              </label>

              <label class="form-label" for="password_confirmation">
                Konfirmasi Password Baru
                <input class="form-input" id="password_confirmation" name="password_confirmation" type="password" placeholder="Ulangi password baru" />
              </label>

              <p class="form-help">
                Password hanya akan berubah bila Anda mengisi password baru. Email boleh ditambahkan nanti jika sebelumnya masih kosong.
              </p>

              <div class="form-actions">
                <button class="action-button" type="submit">Simpan Perubahan</button>
              </div>
            </form>
          </section>
        </div>
      </main>
    </div>
  </div>
</body>
</html>
