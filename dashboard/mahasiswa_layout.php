<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';

requireLogin(['mahasiswa']);
$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
$accountData = fetchLoggedMahasiswa($koneksi, $userId);

if ($accountData === null) {
    $accountData = [
        'nama_lengkap' => (string) ($user['username'] ?? ''),
    ];
}

function mahasiswaSidebarMenus(string $activePath): array
{
    $menus = [
        ['label' => 'Dashboard', 'href' => '/dashboard/mahasiswa_dashboard.php'],
        ['label' => 'Tugas Akhir', 'href' => '/dashboard/mahasiswa_tugas.php'],
        ['label' => 'Jadwal Bimbingan', 'href' => '/dashboard/mahasiswa_jadwal.php'],
        ['label' => 'Seminar / Sidang', 'href' => '/dashboard/mahasiswa_seminar.php'],
        ['label' => 'Profil Mahasiswa', 'href' => '/dashboard/mahasiswa_profil.php'],
    ];

    foreach ($menus as &$menu) {
        $menu['active'] = $menu['href'] === $activePath;
    }
    unset($menu);

    return $menus;
}

function fetchLoggedMahasiswa(mysqli $koneksi, int $idUser): ?array
{
    $sql = <<<SQL
        SELECT
            users.id_user,
            users.username,
            users.password,
            mahasiswa.nim,
            mahasiswa.nama_lengkap,
            mahasiswa.judul_ta,
            mahasiswa.email
        FROM users
        INNER JOIN mahasiswa ON mahasiswa.id_user = users.id_user
        WHERE users.id_user = ?
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

function redirectMahasiswaPage(string $path, array $params = []): void
{
    $target = appPath($path);
    if ($params !== []) {
        $target .= '?' . http_build_query($params);
    }

    header('Location: ' . $target);
    exit;
}

function renderMahasiswaLayoutStart(
    string $pageTitle,
    string $headerTitle,
    string $headerDescription,
    array $user,
    array $sidebarMenus,
    array $accountData,
    bool $showLogout = true
): void {
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($pageTitle) ?></title>
  <link rel="stylesheet" href="../css/styles.css" />
  <style>
    body.dashboard-screen {
      margin: 0;
      min-height: 100vh;
      background: #eef0f4;
      font-family: "Trebuchet MS", Arial, sans-serif;
      color: #2f2f2fff;
    }

    .dashboard-shell {
      min-height: 100vh;
      display: flex;
      background: #eef0f4;
    }

    .dashboard-sidebar {
      width: 280px;
      padding: 28px 22px;
      background: #3f4452;
      color: #ffffffff;
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
      background: #e7f0ff;
      color: #2450bf;
      font-size: 0.92rem;
      font-weight: 800;
      white-space: nowrap;
    }

    .dashboard-content {
      padding-top: 24px;
      display: grid;
      gap: 20px;
    }

    .grid-two-column {
      display: grid;
      grid-template-columns: minmax(0, 1.35fr) minmax(280px, 0.95fr);
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

    .menu-grid,
    .stats-grid,
    .quick-grid {
      display: grid;
      grid-template-columns: repeat(1, minmax(0, 1fr));
      gap: 14px;
    }

    .menu-card,
    .stat-box,
    .quick-box,
    .list-item,
    .account-row {
      border-radius: 16px;
      background: #f5f7fb;
      padding: 16px 18px;
    }

    .menu-card {
      display: flex;
      flex-direction: column;
      gap: 12px;
      text-decoration: none;
      color: #2f2f2f;
      border: 1px solid #e1e6ef;
      background: #f8f9fc;
    }

    .menu-card svg {
      width: 44px;
      height: 44px;
      color: #687384;
      stroke: currentColor;
    }

    .menu-card span,
    .stat-box strong,
    .quick-box strong,
    .list-item strong,
    .account-row strong {
      display: block;
    }

    .menu-card span,
    .list-item strong,
    .account-row strong {
      font-weight: 800;
    }

    .menu-card small,
    .stat-box span,
    .quick-box span,
    .list-item span,
    .account-row span {
      display: block;
      color: #67707d;
      font-weight: 700;
      line-height: 1.45;
    }

    .quick-box {
      display: block;
      text-decoration: none;
      color: inherit;
      cursor: pointer;
    }

    .quick-box:hover {
      text-decoration: none;
    }

    .quick-box strong,
    .quick-box span {
      text-decoration: none;
    }

    .stat-box strong,
    .quick-box strong {
      font-size: 1.45rem;
      margin-bottom: 6px;
      color: #2e3442;
    }

    .list-block {
      margin: 0;
      padding: 0;
      list-style: none;
      display: grid;
      gap: 12px;
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

    .form-input,
    .form-textarea {
      width: 100%;
      border: 1px solid #d5dbe5;
      border-radius: 14px;
      padding: 12px 14px;
      font-size: 0.96rem;
      font-family: inherit;
      color: #2f3642;
      background: #f8f9fc;
    }

    .form-input[readonly] {
      color: #6b7280;
      background: #eef1f6;
      cursor: not-allowed;
    }

    .form-textarea {
      min-height: 120px;
      resize: vertical;
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
      background: #2450bf;
      color: #ffffff;
      font-size: 0.95rem;
      font-weight: 800;
      cursor: pointer;
      box-shadow: 0 12px 24px rgba(36, 80, 191, 0.22);
    }

    .action-button:hover {
      background: #1d44a5;
    }

    @media (max-width: 920px) {
      .dashboard-shell {
        flex-direction: column;
      }

      .dashboard-sidebar {
        width: auto;
      }

      .grid-two-column {
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

      .menu-grid,
      .stats-grid,
      .quick-grid {
        grid-template-columns: 1fr;
      }

      .form-actions {
        justify-content: stretch;
      }

      .action-button {
        width: 100%;
      }

/* =========================================
   SYARAT DAN KETENTUAN TUGAS AKHIR
========================================= */

.ta-requirements {
    min-width: 0;
}

.ta-intro {
    color: #67707d;
    font-size: .9rem;
    line-height: 1.7;
    margin-bottom: 16px;
}

.ta-source-note,
.ta-info-note,
.ta-footer-note {
    padding: 14px 16px;
    border-radius: 10px;
    margin: 14px 0;
    background: #f3f6fc;
    border: 1px solid #e1e8f5;
}

.ta-source-note strong,
.ta-info-note strong,
.ta-footer-note strong {
    display: block;
    margin-bottom: 7px;
    color: #2450bf;
}

.ta-source-note span,
.ta-info-note p,
.ta-footer-note p {
    display: block;
    color: #586273;
    font-size: .85rem;
    line-height: 1.65;
}

.ta-requirement-group {
    border: 1px solid #e3e7ee;
    border-radius: 11px;
    margin: 12px 0;
    overflow: hidden;
    background: #fff;
}

.ta-requirement-group > summary {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    cursor: pointer;
    list-style: none;
    transition: background .2s ease;
}

.ta-requirement-group > summary::-webkit-details-marker {
    display: none;
}

.ta-requirement-group > summary::after {
    content: "+";
    margin-left: auto;
    color: #2450bf;
    font-size: 1.4rem;
    font-weight: 700;
}

.ta-requirement-group[open] > summary::after {
    content: "−";
}

.ta-requirement-group > summary:hover {
    background: #f6f8fc;
}

.ta-requirement-group > summary > span:nth-child(2) {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.ta-requirement-group > summary strong {
    color: #303847;
    font-size: .9rem;
}

.ta-requirement-group > summary small {
    color: #77808e;
    font-size: .75rem;
}

.ta-step {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: #edf2ff;
    color: #2450bf;
    font-size: .8rem;
    font-weight: 800;
}

.ta-requirement-group > .list-block {
    padding: 0 15px 12px;
}

.ta-requirement-group .list-item {
    padding: 12px 0;
    border-bottom: 1px solid #edf0f4;
}

.ta-requirement-group .list-item:last-child {
    border-bottom: none;
}

.ta-requirement-group .list-item strong {
    display: block;
    margin-bottom: 5px;
    color: #303847;
    font-size: .85rem;
}

.ta-requirement-group .list-item span {
    display: block;
    color: #687383;
    font-size: .82rem;
    line-height: 1.7;
}

.ta-info-note ul {
    padding-left: 20px;
    margin: 8px 0 0;
}

.ta-info-note li {
    color: #586273;
    font-size: .84rem;
    line-height: 1.7;
    margin-bottom: 5px;
}

.ta-footer-note {
    margin-top: 18px;
}

.ta-footer-note small {
    display: block;
    color: #77808e;
    font-size: .75rem;
    margin-top: 10px;
}

@media (max-width: 640px) {
    .ta-requirement-group > summary {
        padding: 12px;
        gap: 9px;
    }

    .ta-requirement-group > summary strong {
        font-size: .82rem;
    }

    .ta-requirement-group > summary small {
        font-size: .7rem;
    }

    .ta-requirement-group > .list-block {
        padding: 0 12px 10px;
    }

    .ta-requirement-group .list-item span {
        font-size: .8rem;
    }
}

    }
  </style>
</head>
<body class="dashboard-screen">
  <div class="dashboard-shell">
    <aside class="dashboard-sidebar" aria-label="Sidebar mahasiswa">
      <div class="sidebar-brand">
        <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <circle cx="32" cy="32" r="29" fill="#EFEFEF" stroke="#A3A4AA" stroke-width="3" />
          <circle cx="32" cy="23" r="10" stroke="#A3A4AA" stroke-width="3" />
          <path d="M15 49C18.5 40.5 24.6 37 32 37C39.4 37 45.5 40.5 49 49" stroke="#A3A4AA" stroke-width="3" stroke-linecap="round" />
        </svg>
        <div class="brand-copy">
          <strong><?= e((string) $accountData['nama_lengkap']) ?></strong>
          <span>Pusat aktivitas tugas akhir</span>
        </div>
      </div>

      <nav class="sidebar-menu" aria-label="Menu mahasiswa">
        <?php foreach ($sidebarMenus as $menu): ?>
          <a class="sidebar-link<?= $menu['active'] ? ' is-active' : '' ?>" href="<?= e(appPath((string) $menu['href'])) ?>">
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
            <strong><?= e((string) ($user['username'] ?? '')) ?></strong>
            <span>Mahasiswa aktif</span>
          </div>
        </div>

        <?php if ($showLogout): ?>
          <a class="logout-link" href="<?= e(appPath('/auth/logout.php')) ?>" aria-label="Keluar">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M14 4H18C19.1046 4 20 4.89543 20 6V9" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
              <path d="M20 15V18C20 19.1046 19.1046 20 18 20H14" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
              <path d="M10 12H20" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
              <path d="M17 9L20 12L17 15" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
              <path d="M10 4H6C4.89543 4 4 4.89543 4 6V18C4 19.1046 4.89543 20 6 20H10" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <span>Logout</span>
          </a>
        <?php endif; ?>
      </div>
    </aside>

    <div class="dashboard-main">
      <header class="dashboard-header">
        <div class="header-copy">
          <h1><?= e($headerTitle) ?></h1>
          <p><?= e($headerDescription) ?></p>
        </div>
        <span class="header-badge">Role: Mahasiswa</span>
      </header>

      <main class="dashboard-content">
<?php
}

function renderMahasiswaLayoutEnd(): void
{
    ?>
      </main>
    </div>
  </div>
</body>
</html>
<?php
}
