<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/admin_layout.php';

requireLogin(['admin']);

$user = currentUser();
$sidebarMenus = adminSidebarMenus('/dashboard/admin_log.php');
$search = trim((string) ($_GET['q'] ?? ''));
$role = trim((string) ($_GET['role'] ?? ''));
$activity = trim((string) ($_GET['activity'] ?? ''));
$from = trim((string) ($_GET['from'] ?? ''));
$until = trim((string) ($_GET['until'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = 25;
$errorMessage = '';
$conditions = [];
$params = [];
$types = '';
$validRoles = ['admin', 'dosen', 'mahasiswa'];

function bindAdminLogParameters(mysqli_stmt $statement, string $types, array &$values): void
{
    $arguments = [$statement, $types];
    foreach (array_keys($values) as $index) {
        $arguments[] = &$values[$index];
    }

    if (!call_user_func_array('mysqli_stmt_bind_param', $arguments)) {
        throw new RuntimeException('Gagal mengikat filter riwayat aktivitas.');
    }
}

if ($search !== '') {
    $conditions[] = "(u.username LIKE ? OR COALESCE(m.nama_lengkap, d.nama_lengkap, '') LIKE ?
                      OR COALESCE(m.nim, CAST(d.nidn AS CHAR), '') LIKE ?)";
    $searchValue = '%' . $search . '%';
    array_push($params, $searchValue, $searchValue, $searchValue);
    $types .= 'sss';
}
if ($role !== '' && in_array($role, $validRoles, true)) {
    $conditions[] = 'u.role = ?';
    $params[] = $role;
    $types .= 's';
} elseif ($role !== '') {
    $errorMessage = 'Filter role tidak valid.';
    $role = '';
}
if ($activity !== '') {
    $conditions[] = 'l.aktivitas = ?';
    $params[] = $activity;
    $types .= 's';
}
foreach (['from', 'until'] as $filterName) {
    $dateValue = $filterName === 'from' ? $from : $until;
    if ($dateValue === '') {
        continue;
    }
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
    if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $dateValue) {
        $errorMessage = 'Rentang tanggal tidak valid.';
        if ($filterName === 'from') {
            $from = '';
        } else {
            $until = '';
        }
        continue;
    }
    if ($filterName === 'from') {
        $conditions[] = 'l.tanggal >= ?';
        $params[] = $dateValue . ' 00:00:00';
    } else {
        $conditions[] = 'l.tanggal < DATE_ADD(?, INTERVAL 1 DAY)';
        $params[] = $dateValue;
    }
    $types .= 's';
}
if ($from !== '' && $until !== '' && $from > $until) {
    $errorMessage = 'Tanggal awal tidak boleh melewati tanggal akhir.';
}

$whereSql = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
$activityResult = mysqli_query(
    $koneksi,
    "SELECT DISTINCT aktivitas FROM log_aktivitas WHERE aktivitas <> '' ORDER BY aktivitas ASC"
);
if ($activityResult === false) {
    throw new RuntimeException('Gagal mengambil pilihan jenis aktivitas.');
}
$activityOptions = [];
while ($row = mysqli_fetch_assoc($activityResult)) {
    $activityOptions[] = (string) $row['aktivitas'];
}
mysqli_free_result($activityResult);

$countSql = "SELECT COUNT(*) AS total
             FROM log_aktivitas l
             INNER JOIN users u ON u.id_user = l.id_user
             LEFT JOIN mahasiswa m ON m.id_user = u.id_user
             LEFT JOIN dosen d ON d.id_user = u.id_user" . $whereSql;
$countStatement = mysqli_prepare($koneksi, $countSql);
if ($countStatement === false) {
    throw new RuntimeException('Gagal menyiapkan jumlah riwayat aktivitas.');
}
if ($params !== []) {
    bindAdminLogParameters($countStatement, $types, $params);
}
if (!mysqli_stmt_execute($countStatement)) {
    throw new RuntimeException('Gagal menghitung riwayat aktivitas.');
}
$countResult = mysqli_stmt_get_result($countStatement);
$totalRows = 0;
if ($countResult instanceof mysqli_result && ($countRow = mysqli_fetch_assoc($countResult)) !== null) {
    $totalRows = (int) $countRow['total'];
}
mysqli_stmt_close($countStatement);
$totalPages = max(1, (int) ceil($totalRows / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;

$listSql = "SELECT l.id_log, l.aktivitas, l.keterangan, l.tanggal, l.id_user,
                   u.username, u.role,
                   COALESCE(m.nama_lengkap, d.nama_lengkap, u.username) AS nama_pengguna,
                   COALESCE(m.nim, CAST(d.nidn AS CHAR), u.username) AS identitas
            FROM log_aktivitas l
            INNER JOIN users u ON u.id_user = l.id_user
            LEFT JOIN mahasiswa m ON m.id_user = u.id_user
            LEFT JOIN dosen d ON d.id_user = u.id_user" . $whereSql . "
            ORDER BY l.tanggal DESC, l.id_log DESC
            LIMIT " . (int) $pageSize . ' OFFSET ' . (int) $offset;
$listStatement = mysqli_prepare($koneksi, $listSql);
if ($listStatement === false) {
    throw new RuntimeException('Gagal menyiapkan daftar riwayat aktivitas.');
}
if ($params !== []) {
    bindAdminLogParameters($listStatement, $types, $params);
}
if (!mysqli_stmt_execute($listStatement)) {
    throw new RuntimeException('Gagal mengambil riwayat aktivitas.');
}
$listResult = mysqli_stmt_get_result($listStatement);
$logs = [];
if ($listResult instanceof mysqli_result) {
    while ($row = mysqli_fetch_assoc($listResult)) {
        $logs[] = $row;
    }
}
mysqli_stmt_close($listStatement);

$queryParameters = $_GET;
unset($queryParameters['page']);
$pageUrl = static function (int $targetPage) use ($queryParameters): string {
    return appPath('/dashboard/admin_log.php') . '?' . http_build_query($queryParameters + ['page' => $targetPage]);
};

renderAdminLayoutStart(
    'Log Aktivitas',
    'Log Aktivitas',
    'Riwayat aktivitas pengguna yang tercatat di dalam sistem.',
    $user,
    $sidebarMenus
);
?>
<section class="info-card">
  <h2>Filter Riwayat</h2>
  <form class="log-filters" method="get">
    <label class="form-label">Cari nama atau NIM
      <input class="form-input" type="search" name="q" value="<?= e($search) ?>" placeholder="Nama, username, NIM/NIDN">
    </label>
    <label class="form-label">Role
      <select class="form-input" name="role">
        <option value="">Semua role</option>
        <?php foreach ($validRoles as $roleOption): ?>
          <option value="<?= e($roleOption) ?>" <?= $role === $roleOption ? 'selected' : '' ?>><?= e(ucfirst($roleOption)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form-label">Jenis aktivitas
      <select class="form-input" name="activity">
        <option value="">Semua aktivitas</option>
        <?php foreach ($activityOptions as $activityOption): ?>
          <option value="<?= e($activityOption) ?>" <?= $activity === $activityOption ? 'selected' : '' ?>><?= e($activityOption) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form-label">Dari tanggal
      <input class="form-input" type="date" name="from" value="<?= e($from) ?>">
    </label>
    <label class="form-label">Sampai tanggal
      <input class="form-input" type="date" name="until" value="<?= e($until) ?>">
    </label>
    <div class="filter-actions">
      <button class="action-button" type="submit">Terapkan</button>
      <a href="<?= e(appPath('/dashboard/admin_log.php')) ?>">Reset</a>
    </div>
  </form>
  <?php if ($errorMessage !== ''): ?><div class="message-box message-error"><?= e($errorMessage) ?></div><?php endif; ?>
</section>

<section class="info-card">
  <div class="log-heading">
    <h2>Seluruh Riwayat</h2>
    <span><?= $totalRows ?> catatan · halaman <?= $page ?> dari <?= $totalPages ?></span>
  </div>
  <?php if ($logs === []): ?>
    <p>Tidak ada riwayat yang cocok dengan filter.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="log-table">
        <thead>
          <tr><th>No.</th><th>Nama Pengguna</th><th>NIM / Identitas</th><th>Role</th><th>Aktivitas</th><th>Deskripsi</th><th>Waktu</th><th>ID</th></tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $index => $log): ?>
            <tr>
              <td><?= $offset + $index + 1 ?></td>
              <td><?= e((string) $log['nama_pengguna']) ?></td>
              <td><?= e((string) $log['identitas']) ?></td>
              <td><?= e(ucfirst((string) $log['role'])) ?></td>
              <td><?= e((string) $log['aktivitas']) ?></td>
              <td><?= e((string) ($log['keterangan'] ?? '')) ?></td>
              <td><?= e((string) ($log['tanggal'] ?? 'Tidak tercatat')) ?></td>
              <td><?= (int) $log['id_log'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <nav class="pagination" aria-label="Pagination">
      <?php if ($page > 1): ?><a href="<?= e($pageUrl($page - 1)) ?>">Sebelumnya</a><?php endif; ?>
      <span>Halaman <?= $page ?> / <?= $totalPages ?></span>
      <?php if ($page < $totalPages): ?><a href="<?= e($pageUrl($page + 1)) ?>">Berikutnya</a><?php endif; ?>
    </nav>
  <?php endif; ?>
</section>
<style>
.log-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); align-items: end; gap: 12px; }
.filter-actions { display: flex; align-items: center; gap: 12px; }
.filter-actions a { color: #4d38c7; font-weight: 800; }
.log-heading, .pagination { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.log-heading span { color: #67707d; font-weight: 700; }
.table-responsive { overflow-x: auto; }
.log-table { width: 100%; border-collapse: collapse; min-width: 1050px; }
.log-table th, .log-table td { padding: 11px 9px; border-bottom: 1px solid #e6e9f0; text-align: left; vertical-align: top; }
.log-table th { background: #f8f9fc; color: #596271; }
.log-table td { overflow-wrap: anywhere; }
.pagination { justify-content: flex-end; margin-top: 16px; }
.pagination a { color: #4d38c7; font-weight: 800; }
.pagination span { color: #67707d; font-weight: 700; }
</style>
<?php renderAdminLayoutEnd(); ?>
