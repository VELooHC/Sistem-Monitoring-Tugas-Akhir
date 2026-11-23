<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/dosen_layout.php';

requireLogin(['dosen']);
$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
$sidebarMenus = dosenSidebarMenus('/dashboard/dosen_profil.php');
$successMessage = '';
  $errorMessage = '';

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
    $successMessage = 'Data akun Anda berhasil diperbarui.';
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

            $dosenSql = 'UPDATE dosen SET username = ?, email = ? WHERE id_user = ?';
            $dosenStatement = mysqli_prepare($koneksi, $dosenSql);

            if ($dosenStatement === false) {
                throw new RuntimeException('Gagal menyiapkan query update data dosen.');
            }

            $emailToSave = $newEmail !== '' ? $newEmail : null;
            mysqli_stmt_bind_param($dosenStatement, 'ssi', $newUsername, $emailToSave, $userId);
            if (!mysqli_stmt_execute($dosenStatement)) {
                throw new RuntimeException('Gagal memperbarui email dosen.');
            }
            mysqli_stmt_close($dosenStatement);

            mysqli_commit($koneksi);
            $_SESSION['username'] = $newUsername;
            redirectDosenPage('/dashboard/dosen_profil.php', ['status' => 'updated']);
        } catch (Throwable $exception) {
            mysqli_rollback($koneksi);
            $errorMessage = (int) mysqli_errno($koneksi) === 1062
                ? 'Username atau email sudah digunakan. Silakan pakai data lain.'
                : $exception->getMessage();
        }
    }
}

$accountData = fetchLoggedDosen($koneksi, $userId) ?? $accountData;

renderDosenLayoutStart(
    'Profil Dosen',
    'Profil Dosen',
    'Kelola data akun Anda sendiri tanpa membuat tampilan dashboard utama menjadi terlalu padat.',
    $user,
    $sidebarMenus
);
?>
        <div class="grid-two-column">
          <div class="content-column">
            <section class="info-card">
              <h2>Data Akun Saat Ini</h2>
              <div class="list-block">
                <div class="account-row">
                  <span>Nama Lengkap</span>
                  <strong><?= e((string) $accountData['nama_lengkap']) ?></strong>
                </div>
                <div class="account-row">
                  <span>NIDN</span>
                  <strong><?= e((string) $accountData['nidn']) ?></strong>
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
              <p>Anda dapat mengganti username, password, dan email. Perubahan ini hanya berlaku untuk akun mahasiswa yang sedang login.</p>

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
                  Email boleh ditambahkan jika sebelumnya kosong, dan dapat diganti kapan saja. Password hanya akan berubah bila Anda mengisi password baru.
                </p>

                <div class="form-actions">
                  <button class="action-button" type="submit">Simpan Perubahan</button>
                </div>
              </form>
            </section>
          </div>
        </div>
<?php renderDosenLayoutEnd(); ?>
