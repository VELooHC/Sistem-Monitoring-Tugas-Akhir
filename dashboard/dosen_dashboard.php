<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireLogin(['dosen']);
$user = currentUser();
$sidebarMenus = [
    ['label' => 'Dashboard', 'href' => '/dashboard/dosen_dashboard.php', 'active' => true],
    ['label' => 'Jadwal Bimbingan', 'href' => '#', 'active' => false],
    ['label' => 'Data Mahasiswa', 'href' => '#', 'active' => false],
    ['label' => 'Riwayat Seminar', 'href' => '#', 'active' => false],
    ['label' => 'Profil Dosen', 'href' => '/dashboard/dosen_profil.php', 'active' => false],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard Dosen</title>
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
      grid-template-columns: minmax(0, 1.4fr) minmax(280px, 0.9fr);
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

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px;
    }

    .stat-box {
      border-radius: 16px;
      padding: 18px;
      background: #f5f7fb;
    }

    .stat-box strong,
    .stat-box span {
      display: block;
    }

    .stat-box strong {
      font-size: 1.5rem;
      margin-bottom: 6px;
      color: #2e3442;
    }

    .stat-box span {
      color: #646c79;
      font-weight: 700;
      line-height: 1.4;
    }

    .agenda-list {
      margin: 0;
      padding: 0;
      list-style: none;
      display: grid;
      gap: 12px;
    }

    .agenda-item {
      padding: 14px 16px;
      border-radius: 16px;
      background: #f5f7fb;
    }

    .agenda-item strong,
    .agenda-item span {
      display: block;
    }

    .agenda-item strong {
      margin-bottom: 5px;
      font-size: 0.98rem;
    }

    .agenda-item span {
      color: #666f7c;
      font-weight: 700;
      line-height: 1.45;
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

      .stats-grid {
        grid-template-columns: 1fr;
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
          <a class="sidebar-link<?= $menu['active'] ? ' is-active' : '' ?>" href="<?= $menu['href'] === '#' ? '#' : e(appPath($menu['href'])) ?>">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path d="M4 6.5H20" stroke-width="1.8" stroke-linecap="round" />
              <path d="M4 12H20" stroke-width="1.8" stroke-linecap="round" />
              <path d="M4 17.5H20" stroke-width="1.8" stroke-linecap="round" />
            </svg>
            <span><?= e($menu['label']) ?></span>
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
      </div>
    </aside>

    <div class="dashboard-main">
      <header class="dashboard-header">
        <div class="header-copy">
          <h1>Dashboard Dosen</h1>
          <p>Kelola aktivitas bimbingan, jadwal konsultasi, dan pemantauan mahasiswa dari satu panel.</p>
        </div>
        <span class="header-badge">Role: Dosen</span>
      </header>

      <main class="dashboard-content">
        <div class="content-column">
          <section class="info-card">
            <h2>Ringkasan Akun</h2>
            <p>Selamat datang, <?= e((string) $user['username']) ?>.</p>
            <p>Anda login sebagai dosen dan halaman ini sudah terlindungi oleh session.</p>
          </section>

          <section class="info-card">
            <h2>Statistik Bimbingan</h2>
            <div class="stats-grid">
              <div class="stat-box">
                <strong>12</strong>
                <span>Mahasiswa bimbingan aktif</span>
              </div>
              <div class="stat-box">
                <strong>5</strong>
                <span>Jadwal konsultasi minggu ini</span>
              </div>
              <div class="stat-box">
                <strong>3</strong>
                <span>Pengajuan menunggu review</span>
              </div>
              <div class="stat-box">
                <strong>8</strong>
                <span>Dokumen sudah diverifikasi</span>
              </div>
            </div>
          </section>
        </div>

        <div class="content-column">
          <section class="info-card">
            <h2>Agenda Terdekat</h2>
            <ul class="agenda-list">
              <li class="agenda-item">
                <strong>Bimbingan Bab 3</strong>
                <span>Senin, 09.00 WIB dengan mahasiswa Teknik Informatika.</span>
              </li>
              <li class="agenda-item">
                <strong>Review Proposal</strong>
                <span>Rabu, 13.00 WIB untuk revisi pengajuan judul.</span>
              </li>
              <li class="agenda-item">
                <strong>Validasi Seminar</strong>
                <span>Jumat, 10.00 WIB untuk pengecekan berkas seminar.</span>
              </li>
            </ul>
          </section>

          <section class="info-card">
            <h2>Catatan Sistem</h2>
            <p>Sidebar ini siap dipakai sebagai navigasi utama untuk fitur dosen berikutnya.</p>
            <p>Jika Anda ingin, menu yang masih `#` bisa saya sambungkan ke halaman modul aslinya setelah struktur file disiapkan.</p>
          </section>
        </div>
      </main>
    </div>
  </div>
</body>
</html>
