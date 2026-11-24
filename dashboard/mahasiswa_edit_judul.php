<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/activity_log.php';
require_once __DIR__ . '/mahasiswa_layout.php';

requireLogin(['mahasiswa']);

$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
$accountData = fetchLoggedMahasiswa($koneksi, $userId);
$sidebarMenus = mahasiswaSidebarMenus('/dashboard/mahasiswa_tugas.php');

if ($accountData === null) {
    die('Data akun mahasiswa tidak ditemukan.');
}

$nim = (string) $accountData['nim'];
$judulSaatIni = (string) ($accountData['judul_ta'] ?? '');
$statusTA = 'Belum Mengajukan';
$pesan = '';

$stmt = mysqli_prepare(
    $koneksi,
    'SELECT judul_ta, status_ta
     FROM tugas_akhir
     WHERE nim = ?
     ORDER BY id_ta DESC
     LIMIT 1'
);

if ($stmt === false) {
    throw new RuntimeException('Gagal menyiapkan data tugas akhir.');
}

mysqli_stmt_bind_param($stmt, 's', $nim);
if (!mysqli_stmt_execute($stmt)) {
    throw new RuntimeException('Gagal mengambil data tugas akhir.');
}

$result = mysqli_stmt_get_result($stmt);
$dataTA = $result instanceof mysqli_result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($stmt);

if ($dataTA !== null) {
    $judulSaatIni = (string) $dataTA['judul_ta'];
    $statusTA = (string) $dataTA['status_ta'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judulBaru = trim((string) ($_POST['judul_ta'] ?? ''));

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $pesan = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif ($judulBaru === '') {
        $pesan = 'Judul tugas akhir tidak boleh kosong.';
    } elseif (strlen($judulBaru) > 500) {
        $pesan = 'Judul terlalu panjang.';
    } else {
        mysqli_begin_transaction($koneksi);

        try {
            $updateMahasiswa = mysqli_prepare(
                $koneksi,
                'UPDATE mahasiswa SET judul_ta = ? WHERE nim = ?'
            );
            if ($updateMahasiswa === false) {
                throw new RuntimeException('Gagal menyiapkan pembaruan judul mahasiswa.');
            }

            mysqli_stmt_bind_param($updateMahasiswa, 'ss', $judulBaru, $nim);
            if (!mysqli_stmt_execute($updateMahasiswa)) {
                throw new RuntimeException('Gagal memperbarui judul mahasiswa.');
            }
            mysqli_stmt_close($updateMahasiswa);

            if ($dataTA !== null) {
                $updateTA = mysqli_prepare(
                    $koneksi,
                    'UPDATE tugas_akhir
                     SET judul_ta = ?, tanggal_update = NOW()
                     WHERE nim = ?
                     ORDER BY id_ta DESC
                     LIMIT 1'
                );
                if ($updateTA === false) {
                    throw new RuntimeException('Gagal menyiapkan pembaruan tugas akhir.');
                }

                mysqli_stmt_bind_param($updateTA, 'ss', $judulBaru, $nim);
                if (!mysqli_stmt_execute($updateTA)) {
                    throw new RuntimeException('Gagal memperbarui judul tugas akhir.');
                }
                mysqli_stmt_close($updateTA);
            } else {
                $insertTA = mysqli_prepare(
                    $koneksi,
                    'INSERT INTO tugas_akhir (nim, judul_ta) VALUES (?, ?)'
                );
                if ($insertTA === false) {
                    throw new RuntimeException('Gagal menyiapkan data tugas akhir baru.');
                }

                mysqli_stmt_bind_param($insertTA, 'ss', $nim, $judulBaru);
                if (!mysqli_stmt_execute($insertTA)) {
                    throw new RuntimeException('Gagal membuat data tugas akhir.');
                }
                mysqli_stmt_close($insertTA);
            }

            mysqli_commit($koneksi);
            recordActivity(
                $koneksi,
                $userId,
                'Ubah judul tugas akhir',
                'NIM ' . $nim . ' · Judul: ' . $judulBaru
            );
            redirectMahasiswaPage('/dashboard/mahasiswa_tugas.php', ['edit' => 'success']);
        } catch (Throwable $exception) {
            mysqli_rollback($koneksi);
            error_log('Edit judul tugas akhir: ' . $exception->getMessage());
            $pesan = 'Judul gagal disimpan. Silakan coba lagi.';
        }
    }

    $judulSaatIni = $judulBaru;
}

renderMahasiswaLayoutStart(
    'Edit Judul Tugas Akhir',
    'Edit Judul',
    'Perbarui judul tugas akhir yang terhubung dengan akun Anda.',
    $user,
    $sidebarMenus,
    $accountData
);
?>
<div class="content-column">
  <section class="info-card">
    <h2>Edit Judul Tugas Akhir</h2>
    <p>NIM: <strong><?= e($nim) ?></strong></p>

    <?php if ($pesan !== ''): ?>
      <div class="message-box message-error"><?= e($pesan) ?></div>
    <?php endif; ?>

    <form class="account-form" method="post">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
      <label class="form-label" for="judul_ta">
        Judul Tugas Akhir
        <textarea class="form-textarea" id="judul_ta" name="judul_ta" maxlength="500" required><?= e($judulSaatIni) ?></textarea>
      </label>

      <label class="form-label" for="status_ta">
        Status Tugas Akhir
        <input class="form-input" id="status_ta" type="text" value="<?= e($statusTA) ?>" readonly>
      </label>

      <p class="form-help">Status pengajuan dikelola oleh dosen atau admin.</p>

      <div class="form-actions">
        <a class="btn btn-secondary" href="<?= e(appPath('/dashboard/mahasiswa_tugas.php')) ?>">Batal</a>
        <button class="action-button" type="submit">Simpan Perubahan</button>
      </div>
    </form>
  </section>
</div>
<?php renderMahasiswaLayoutEnd(); ?>
