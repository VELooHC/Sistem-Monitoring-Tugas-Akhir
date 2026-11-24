<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/mahasiswa_layout.php';

requireLogin(['mahasiswa']);
$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
$accountData = fetchLoggedMahasiswa($koneksi, $userId);
$sidebarMenus = mahasiswaSidebarMenus('/dashboard/mahasiswa_jadwal.php');

if ($accountData === null) {
    die('Data akun mahasiswa tidak ditemukan.');
}

$appointmentId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$appointment = null;
if ($appointmentId !== false && $appointmentId !== null && $appointmentId > 0) {
    $statement = mysqli_prepare(
        $koneksi,
        'SELECT b.id_bimbingan, b.tanggal_bimbingan, b.waktu_bimbingan, b.agenda,
                b.catatan_bimbingan, b.status_progres, b.status_review, b.catatan_dosen,
                d.nama_lengkap AS nama_dosen
         FROM bimbingan b
         LEFT JOIN dosen d ON d.nidn = b.id_dosen
         WHERE b.id_bimbingan = ? AND b.nim = ?
         LIMIT 1'
    );
    if ($statement === false) {
        throw new RuntimeException('Gagal menyiapkan detail bimbingan.');
    }

    $nim = (string) $accountData['nim'];
    mysqli_stmt_bind_param($statement, 'is', $appointmentId, $nim);
    if (!mysqli_stmt_execute($statement)) {
        mysqli_stmt_close($statement);
        throw new RuntimeException('Gagal mengambil detail bimbingan.');
    }
    $result = mysqli_stmt_get_result($statement);
    $appointment = $result instanceof mysqli_result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($statement);
}

if ($appointment === null) {
    http_response_code(404);
}

renderMahasiswaLayoutStart(
    'Detail Bimbingan',
    'Detail Bimbingan',
    'Informasi agenda dan catatan bimbingan.',
    $user,
    $sidebarMenus,
    $accountData
);
?>
<section class="info-card">
  <p><a class="action-button secondary-button" href="<?= e(appPath('/dashboard/mahasiswa_jadwal.php')) ?>">Kembali ke Jadwal Bimbingan</a></p>
  <?php if ($appointment === null): ?>
    <h2>Agenda tidak ditemukan</h2>
    <p>Agenda tidak tersedia atau bukan milik akun mahasiswa ini.</p>
  <?php else: ?>
    <h2><?= e((string) $appointment['agenda']) ?></h2>
    <div class="list-block">
      <div class="account-row"><span>Tanggal</span><strong><?= e((string) $appointment['tanggal_bimbingan']) ?></strong></div>
      <div class="account-row"><span>Waktu</span><strong><?= e((string) (($appointment['waktu_bimbingan'] ?? '') !== '' ? substr((string) $appointment['waktu_bimbingan'], 0, 5) : 'Belum ditentukan')) ?></strong></div>
      <div class="account-row"><span>Dosen pembimbing</span><strong><?= e((string) ($appointment['nama_dosen'] ?? 'Belum ditentukan')) ?></strong></div>
      <div class="account-row"><span>Status progres</span><strong><?= e((string) (($appointment['status_progres'] ?? '') !== '' ? $appointment['status_progres'] : 'Belum ditentukan')) ?></strong></div>
      <div class="account-row"><span>Status review</span><strong><?= e((string) $appointment['status_review']) ?></strong></div>
      <div class="account-row"><span>Catatan mahasiswa</span><strong><?= nl2br(e((string) (($appointment['catatan_bimbingan'] ?? '') !== '' ? $appointment['catatan_bimbingan'] : 'Belum ada catatan.'))) ?></strong></div>
      <div class="account-row"><span>Catatan dosen</span><strong><?= nl2br(e((string) (($appointment['catatan_dosen'] ?? '') !== '' ? $appointment['catatan_dosen'] : 'Belum ada catatan dari dosen.'))) ?></strong></div>
    </div>
  <?php endif; ?>
</section>
<?php renderMahasiswaLayoutEnd(); ?>
