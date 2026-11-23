<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/koneksi.php';

if (isLoggedIn()) {
    redirectByRole((string) $_SESSION['role']);
}

$errorMessage = '';
$usernameValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameValue = trim((string) ($_POST['username'] ?? ''));
    $passwordValue = (string) ($_POST['password'] ?? '');

    if ($usernameValue === '' || $passwordValue === '') {
        $errorMessage = 'Username dan password wajib diisi.';
    } else {
        $sql = 'SELECT id_user AS id, username, password, role FROM users WHERE username = ? LIMIT 1';
        $statement = mysqli_prepare($koneksi, $sql);

        if ($statement === false) {
            $errorMessage = 'Terjadi kesalahan saat memproses login.';
        } else {
            mysqli_stmt_bind_param($statement, 's', $usernameValue);
            mysqli_stmt_execute($statement);
            mysqli_stmt_bind_result($statement, $id, $username, $passwordFromDb, $role);
            $user = mysqli_stmt_fetch($statement)
                ? [
                    'id' => $id,
                    'username' => $username,
                    'password' => $passwordFromDb,
                    'role' => $role,
                ]
                : null;
            mysqli_stmt_close($statement);

            $storedPassword = (string) ($user['password'] ?? '');
            $isPasswordValid = $user !== null && (
                password_verify($passwordValue, $storedPassword) ||
                hash_equals($storedPassword, $passwordValue)
            );

            if (!$isPasswordValid) {
                $errorMessage = 'Username atau password tidak valid.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (string) $user['id'];
                $_SESSION['username'] = (string) $user['username'];
                $_SESSION['role'] = (string) $user['role'];
                redirectByRole((string) $user['role']);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login</title>
  <link rel="stylesheet" href="css/styles.css" />
  <style>
    body.login-screen {
      margin: 0;
      min-height: 100vh;
      background: #eef1f6;
      font-family: "Trebuchet MS", Arial, sans-serif;
      color: #1f1f1f;
    }

    .login-shell {
      min-height: 100vh;
      display: grid;
      grid-template-columns: minmax(280px, 1fr) minmax(420px, 2fr);
    }

    .welcome-panel {
      position: relative;
      padding: 48px 38px;
      background:
        linear-gradient(160deg, rgba(70, 37, 209, 0.94) 0%, rgba(33, 17, 103, 0.96) 100%);
      color: #ffffff;
      display: flex;
      align-items: flex-end;
      overflow: hidden;
    }

    .welcome-panel::before,
    .welcome-panel::after {
      content: "";
      position: absolute;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.08);
    }

    .welcome-panel::before {
      width: 220px;
      height: 220px;
      top: -70px;
      left: -70px;
    }

    .welcome-panel::after {
      width: 260px;
      height: 260px;
      right: -90px;
      bottom: -90px;
    }

    .welcome-content {
      position: relative;
      z-index: 1;
      max-width: 320px;
      display: grid;
      gap: 20px;
    }

    .welcome-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: fit-content;
      padding: 8px 14px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.12);
      font-size: 0.84rem;
      font-weight: 800;
      letter-spacing: 0.03em;
    }

    .welcome-title {
      margin: 0;
      font-size: clamp(2rem, 4vw, 2.8rem);
      line-height: 1.15;
    }

    .welcome-copy {
      margin: 0;
      line-height: 1.7;
      color: rgba(255, 255, 255, 0.84);
      font-weight: 700;
    }

    .welcome-points {
      margin: 0;
      padding: 0;
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .welcome-points li {
      padding-left: 18px;
      position: relative;
      color: rgba(255, 255, 255, 0.88);
      font-weight: 700;
      line-height: 1.5;
    }

    .welcome-points li::before {
      content: "";
      position: absolute;
      left: 0;
      top: 10px;
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #ffffff;
    }

    .login-panel {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      padding: 42px;
      background: linear-gradient(180deg, #eef1f6 0%, #f7f8fb 100%);
    }

    .login-card {
      width: min(100%, 560px);
      min-height: calc(100vh - 84px);
      padding: 42px 40px;
      border-left: 1px solid #d7dce7;
      background: #ffffff;
      box-shadow: -20px 0 40px rgba(28, 33, 45, 0.06);
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .login-card-head {
      display: grid;
      gap: 16px;
      margin-bottom: 30px;
    }

    .login-avatar {
      width: 72px;
      height: 72px;
      border-radius: 20px;
      background: linear-gradient(135deg, #ece8ff 0%, #f5f1ff 100%);
      color: #4d2fd0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      box-shadow: inset 0 0 0 1px rgba(77, 47, 208, 0.08);
    }

    .login-avatar svg,
    .toggle-password svg {
      width: 24px;
      height: 24px;
      stroke: currentColor;
    }

    .login-heading {
      margin: 0;
      font-size: 1.85rem;
      line-height: 1.2;
    }

    .login-subtitle {
      margin: 0;
      color: #6b7280;
      line-height: 1.6;
      font-weight: 700;
    }

    .login-error {
      margin: 0 0 18px;
      border-radius: 14px;
      padding: 14px 16px;
      background: #fdeeee;
      border: 1px solid #f0c3c3;
      color: #ab2828;
      font-size: 0.92rem;
      font-weight: 700;
      line-height: 1.5;
    }

    .login-form {
      width: 100%;
      display: flex;
      flex-direction: column;
      gap: 18px;
      align-items: stretch;
    }

    .field-label {
      display: grid;
      gap: 8px;
      font-size: 0.92rem;
      font-weight: 800;
      color: #475061;
    }

    .field-box {
      min-height: 52px;
      border: 1px solid #d5dbe5;
      border-radius: 16px;
      background: #f8f9fc;
      display: flex;
      align-items: center;
      overflow: hidden;
    }

    .field-box input {
      width: 100%;
      min-height: 52px;
      border: 0;
      background: transparent;
      padding: 0 16px;
      font-size: 1rem;
      color: #2f3642;
      box-shadow: none;
    }

    .field-box input::placeholder {
      color: #9aa2af;
      font-weight: 700;
    }

    .field-box input:focus {
      outline: none;
      box-shadow: none;
    }

    .password-box {
      padding-right: 12px;
    }

    .toggle-password {
      width: 34px;
      height: 34px;
      border: 0;
      padding: 0;
      background: transparent;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: #8d97a7;
      cursor: pointer;
      flex: 0 0 auto;
    }

    .login-actions {
      width: 100%;
      display: flex;
      justify-content: flex-end;
      margin-top: 10px;
    }

    .login-submit {
      border: 0;
      border-radius: 999px;
      padding: 13px 26px;
      background: #4a2fd2;
      color: #ffffff;
      font-size: 0.98rem;
      font-weight: 800;
      cursor: pointer;
      box-shadow: 0 12px 28px rgba(74, 47, 210, 0.28);
    }

    .login-submit:hover {
      background: #3f24bf;
    }

    .login-helper {
      margin-top: 22px;
      color: #7a8392;
      font-size: 0.9rem;
      font-weight: 700;
      line-height: 1.6;
    }

    @media (max-width: 920px) {
      .login-shell {
        grid-template-columns: 1fr;
      }

      .welcome-panel {
        min-height: 36vh;
        align-items: center;
      }

      .login-panel {
        justify-content: center;
        padding: 24px 16px 36px;
      }

      .login-card {
        min-height: auto;
        border-left: 0;
        border-top: 1px solid #d7dce7;
        box-shadow: 0 -8px 30px rgba(28, 33, 45, 0.06);
      }
    }

    @media (max-width: 640px) {
      .welcome-panel,
      .login-panel {
        padding: 24px 18px;
      }

      .login-card {
        padding: 28px 22px;
      }

      .login-actions {
        justify-content: stretch;
      }

      .login-submit {
        width: 100%;
      }
    }
  </style>
</head>
<body class="login-screen">
  <div class="login-shell">
    <section class="welcome-panel" aria-label="Sambutan sistem">
      <div class="welcome-content">
        <span class="welcome-badge">SIAMBITA</span>
        <div>
          <h1 class="welcome-title">Selamat datang di Sistem Monitoring dan Bimbingan Tugas Akhir</h1>
        </div>
        <p class="welcome-copy">
          Pantau progres, bimbingan, dan pengelolaan data akademik dalam satu sistem
          yang terintegrasi.
        </p>
        <ul class="welcome-points">
          <li>Akses informasi tugas akhir dengan lebih terstruktur.</li>
          <li>Kelola aktivitas akademik dari satu dashboard yang sesuai role.</li>
          <li>Masuk ke sistem melalui panel login di sisi kanan halaman.</li>
        </ul>
      </div>
    </section>

    <section class="login-panel">
      <div class="login-card">
        <div class="login-card-head">
          <span class="login-avatar" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect x="3.5" y="3.5" width="17" height="17" rx="3.5" stroke-width="1.8" />
              <circle cx="12" cy="10" r="2.7" stroke-width="1.8" />
              <path d="M7 18C8.3 14.8 10.4 13.5 12 13.5C13.6 13.5 15.7 14.8 17 18" stroke-width="1.8" stroke-linecap="round" />
            </svg>
          </span>
          <div>
            <h1 class="login-heading">Log In</h1>
            <p class="login-subtitle">
              Masukkan username dan password Anda untuk masuk ke dashboard sistem.
            </p>
          </div>
        </div>

        <?php if ($errorMessage !== ''): ?>
          <p class="login-error"><?= e($errorMessage) ?></p>
        <?php endif; ?>

        <form id="loginForm" class="login-form" method="post" action="">
          <label class="field-label" for="username">
            Username
            <span class="field-box">
              <input
                id="username"
                name="username"
                type="text"
                placeholder="Masukkan username"
                value="<?= e($usernameValue) ?>"
                required
              />
            </span>
          </label>

          <label class="field-label" for="password">
            Password
            <span class="field-box password-box">
              <input id="password" name="password" type="password" placeholder="Masukkan password" required />
              <button class="toggle-password" type="button" aria-label="Tampilkan password">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M2.5 12C4.8 8.5 8 6.5 12 6.5C16 6.5 19.2 8.5 21.5 12C19.2 15.5 16 17.5 12 17.5C8 17.5 4.8 15.5 2.5 12Z" stroke-width="1.8" stroke-linejoin="round" />
                  <circle cx="12" cy="12" r="3" stroke-width="1.8" />
                </svg>
              </button>
            </span>
          </label>

          <div class="login-actions">
            <button type="submit" class="login-submit">Masuk</button>
          </div>
        </form>

        <p class="login-helper">
          Gunakan akun yang sudah terdaftar agar dapat masuk ke dashboard sesuai peran Anda.
        </p>
      </div>
    </section>
  </div>

  <script src="js/app.js"></script>
</body>
</html>
