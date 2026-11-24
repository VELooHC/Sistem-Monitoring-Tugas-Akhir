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
$sidebarMenus = mahasiswaSidebarMenus('/dashboard/mahasiswa_jadwal.php');

if ($accountData === null) {
    die('Data akun mahasiswa tidak ditemukan.');
}

$nim = (string) $accountData['nim'];
$pesan = '';
$jenisPesan = '';
$dosen = null;

$dosenStatement = mysqli_prepare(
    $koneksi,
    'SELECT d.nidn, d.nama_lengkap
     FROM mahasiswa m
     LEFT JOIN dosen d ON d.id_user = m.id_dosen
     WHERE m.nim = ?
     LIMIT 1'
);

if ($dosenStatement === false) {
    throw new RuntimeException('Gagal menyiapkan data dosen pembimbing.');
}

mysqli_stmt_bind_param($dosenStatement, 's', $nim);
if (!mysqli_stmt_execute($dosenStatement)) {
    throw new RuntimeException('Gagal mengambil data dosen pembimbing.');
}

$dosenResult = mysqli_stmt_get_result($dosenStatement);
$dosen = $dosenResult instanceof mysqli_result ? mysqli_fetch_assoc($dosenResult) : null;
mysqli_stmt_close($dosenStatement);

if (($_GET['status'] ?? '') === 'created') {
    $pesan = 'Agenda bimbingan berhasil ditambahkan.';
    $jenisPesan = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal = trim((string) ($_POST['tanggal_bimbingan'] ?? ''));
    $waktu = trim((string) ($_POST['waktu_bimbingan'] ?? ''));
    $agenda = trim((string) ($_POST['agenda'] ?? ''));
    $catatan = trim((string) ($_POST['catatan_bimbingan'] ?? ''));

    $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $tanggal);
    $timeValue = DateTimeImmutable::createFromFormat('!H:i', $waktu);

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $pesan = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
        $jenisPesan = 'error';
    } elseif (empty($dosen['nidn'])) {
        $pesan = 'Dosen pembimbing belum ditetapkan. Hubungi admin sebelum menambahkan agenda.';
        $jenisPesan = 'error';
    } elseif ($dateValue === false || $dateValue->format('Y-m-d') !== $tanggal) {
        $pesan = 'Tanggal bimbingan tidak valid.';
        $jenisPesan = 'error';
    } elseif ($timeValue === false || $timeValue->format('H:i') !== $waktu) {
        $pesan = 'Waktu bimbingan tidak valid.';
        $jenisPesan = 'error';
    } elseif ($agenda === '' || strlen($agenda) > 255) {
        $pesan = 'Agenda wajib diisi dan maksimal 255 karakter.';
        $jenisPesan = 'error';
    } elseif (strlen($catatan) > 5000) {
        $pesan = 'Catatan maksimal 5000 karakter.';
        $jenisPesan = 'error';
    } else {
        $idDosen = (int) $dosen['nidn'];
        $insert = mysqli_prepare(
            $koneksi,
            'INSERT INTO bimbingan
             (nim, id_dosen, tanggal_bimbingan, waktu_bimbingan, agenda, catatan_bimbingan)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        if ($insert === false) {
            throw new RuntimeException('Gagal menyiapkan penyimpanan agenda.');
        }

        mysqli_stmt_bind_param($insert, 'sissss', $nim, $idDosen, $tanggal, $waktu, $agenda, $catatan);
        if (!mysqli_stmt_execute($insert)) {
            error_log('Tambah agenda bimbingan: ' . mysqli_stmt_error($insert));
            $pesan = 'Agenda gagal disimpan. Silakan coba lagi.';
            $jenisPesan = 'error';
        } else {
            mysqli_stmt_close($insert);
            recordActivity(
                $koneksi,
                $userId,
                'Tambah agenda bimbingan',
                'NIM ' . $nim . ' · ' . $agenda . ' · ' . $tanggal . ' ' . $waktu
            );
            redirectMahasiswaPage('/dashboard/mahasiswa_jadwal.php', ['status' => 'created']);
        }
        mysqli_stmt_close($insert);
    }
}

$appointments = [];
$appointmentsStatement = mysqli_prepare(
    $koneksi,
    'SELECT b.tanggal_bimbingan, b.waktu_bimbingan, b.agenda,
            b.catatan_bimbingan, b.status_progres, b.status_review,
            b.catatan_dosen, d.nama_lengkap AS nama_dosen
     FROM bimbingan b
     LEFT JOIN dosen d ON d.nidn = b.id_dosen
     WHERE b.nim = ?
     ORDER BY b.tanggal_bimbingan DESC, b.waktu_bimbingan DESC, b.id_bimbingan DESC'
);

if ($appointmentsStatement === false) {
    throw new RuntimeException('Gagal menyiapkan daftar agenda.');
}

mysqli_stmt_bind_param($appointmentsStatement, 's', $nim);
if (!mysqli_stmt_execute($appointmentsStatement)) {
    throw new RuntimeException('Gagal mengambil daftar agenda.');
}

$appointmentsResult = mysqli_stmt_get_result($appointmentsStatement);
if ($appointmentsResult instanceof mysqli_result) {
    while ($row = mysqli_fetch_assoc($appointmentsResult)) {
        $appointments[] = $row;
    }
}
mysqli_stmt_close($appointmentsStatement);

renderMahasiswaLayoutStart(
    'Jadwal Bimbingan',
    'Jadwal Bimbingan',
    'Tambahkan agenda bimbingan dan simpan catatan untuk setiap pertemuan.',
    $user,
    $sidebarMenus,
    $accountData
);
?>
<div class="grid-two-column">
  <div class="content-column">
    <section class="info-card">
      <h2>Tambah Agenda</h2>
      <?php if ($pesan !== ''): ?>
        <div class="message-box message-<?= e($jenisPesan) ?>"><?= e($pesan) ?></div>
      <?php endif; ?>
      <?php if (!empty($dosen['nama_lengkap'])): ?>
        <p>Dosen pembimbing: <strong><?= e((string) $dosen['nama_lengkap']) ?></strong></p>
      <?php endif; ?>

      <form class="account-form" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <label class="form-label" for="tanggal_bimbingan">
          Tanggal
          <input class="form-input" id="tanggal_bimbingan" name="tanggal_bimbingan" type="date" required>
        </label>
        <label class="form-label" for="waktu_bimbingan">
          Waktu
          <input class="form-input" id="waktu_bimbingan" name="waktu_bimbingan" type="time" required>
        </label>
        <label class="form-label" for="agenda">
          Agenda
          <input class="form-input" id="agenda" name="agenda" type="text" maxlength="255" required>
        </label>
        <label class="form-label" for="catatan_bimbingan">
          Catatan
          <textarea class="form-textarea" id="catatan_bimbingan" name="catatan_bimbingan" maxlength="5000"></textarea>
        </label>
        <div class="form-actions">
          <button class="action-button" type="submit" <?= empty($dosen['nidn']) ? 'disabled' : '' ?>>Simpan Agenda</button>
        </div>
        <?php if (empty($dosen['nidn'])): ?>
          <p class="form-help">Dosen pembimbing belum ditetapkan, jadi agenda belum dapat disimpan.</p>
        <?php endif; ?>
      </form>
    </section>
  </div>

  <div class="content-column">
    <section class="info-card">
      <h2>Agenda dan Catatan</h2>
      <?php if ($appointments === []): ?>
        <p>Belum ada agenda bimbingan.</p>
      <?php else: ?>
        <div class="list-block">
          <?php foreach ($appointments as $appointment): ?>
            <article class="list-item">
              <strong><?= e((string) $appointment['agenda']) ?></strong>
              <span><?= e((string) $appointment['tanggal_bimbingan']) ?><?= !empty($appointment['waktu_bimbingan']) ? ' · ' . e(substr((string) $appointment['waktu_bimbingan'], 0, 5)) : '' ?></span>
              <span>Dosen: <?= e((string) ($appointment['nama_dosen'] ?? 'Belum ditentukan')) ?></span>
              <span>Status: <?= e((string) ($appointment['status_progres'] ?: 'Belum ditentukan')) ?> · <?= e((string) $appointment['status_review']) ?></span>
              <span>Catatan: <?= e((string) (($appointment['catatan_bimbingan'] ?? '') !== '' ? $appointment['catatan_bimbingan'] : 'Belum ada catatan')) ?></span>
              <span>Catatan dosen: <?= e((string) (($appointment['catatan_dosen'] ?? '') !== '' ? $appointment['catatan_dosen'] : 'Belum ada catatan')) ?></span>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php renderMahasiswaLayoutEnd(); ?>
