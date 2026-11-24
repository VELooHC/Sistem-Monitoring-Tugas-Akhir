
<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/mahasiswa_layout.php';

requireLogin(['mahasiswa']);

$user = currentUser();
$userId = (int) ($user['id'] ?? 0);

$accountData = fetchLoggedMahasiswa($koneksi, $userId);
$nim = trim((string) ($accountData['nim'] ?? ''));

if ($nim === '') {
    die('NIM mahasiswa tidak ditemukan.');
}

$pesan = '';
$jenisPesan = '';

// Ambil data judul milik mahasiswa yang sedang login
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT id_ta, nim, judul_ta, status_ta
     FROM tugas_akhir
     WHERE nim = ?
     ORDER BY id_ta DESC
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, 's', $nim);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$dataTA = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

// Proses penyimpanan perubahan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $judulBaru = trim($_POST['judul_ta'] ?? '');

    if ($judulBaru === '') {
        $pesan = 'Judul tugas akhir tidak boleh kosong.';
        $jenisPesan = 'error';

    } elseif (strlen($judulBaru) > 500) {
        $pesan = 'Judul terlalu panjang.';
        $jenisPesan = 'error';

    } elseif (!$dataTA) {
        $pesan = 'Data tugas akhir belum tersedia. Silakan lakukan pengajuan judul terlebih dahulu.';
        $jenisPesan = 'error';

    } else {

        // Update hanya berdasarkan ID dan NIM mahasiswa
        $update = mysqli_prepare(
            $koneksi,
            "UPDATE tugas_akhir
             SET judul_ta = ?, tanggal_update = NOW()
             WHERE id_ta = ? AND nim = ?"
        );

        $idTA = (int) $dataTA['id_ta'];

        mysqli_stmt_bind_param(
            $update,
            'sis',
            $judulBaru,
            $idTA,
            $nim
        );

        if (mysqli_stmt_execute($update)) {

            mysqli_stmt_close($update);

            header('Location: mahasiswa_tugas.php?edit=success');
            exit;

        } else {

            $pesan = 'Gagal memperbarui judul: ' .
                mysqli_stmt_error($update);

            $jenisPesan = 'error';
            mysqli_stmt_close($update);
        }
    }

    // Pertahankan judul yang diketik jika terjadi error
    if ($pesan !== '') {
        $dataTA['judul_ta'] = $judulBaru;
    }
}

$sidebarMenus = mahasiswaSidebarMenus('/dashboard/mahasiswa_tugas.php');

renderMahasiswaLayoutStart(
    'Edit Judul Tugas Akhir',
    'Edit Judul',
    'Halaman untuk mengubah judul tugas akhir mahasiswa.',
    $user,
    $sidebarMenus
);
?>

<div class="content-column">

    <section class="info-card">

        <h2>Edit Judul Tugas Akhir</h2>

        <p>
            Silakan ubah judul tugas akhir Anda melalui formulir berikut.
        </p>

        <?php if ($pesan !== ''): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($dataTA): ?>

            <form method="POST" action="">

                <div class="mb-3">

                    <label class="form-label">
                        NIM Mahasiswa
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="<?= htmlspecialchars($nim, ENT_QUOTES, 'UTF-8') ?>"
                        readonly
                    >

                </div>

                <div class="mb-3">

                    <label class="form-label">
                        Judul TA
                    </label>

                    <textarea
                        name="judul_ta"
                        class="form-control"
                        rows="5"
                        maxlength="500"
                        required
                    ><?= htmlspecialchars((string)($dataTA['judul_ta'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>

                </div>

                <div class="mb-3">

                    <label class="form-label">
                        Status Tugas Akhir
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="<?= htmlspecialchars((string)($dataTA['status_ta'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>"
                        readonly
                    >

                    <small class="text-muted">
                        Status hanya bisa di ubah oleh pemilik akun.
                    </small>

                </div>

                <div class="d-flex gap-2">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Simpan Perubahan
                    </button>

                    <a
                        href="mahasiswa_tugas.php"
                        class="btn btn-secondary"
                    >
                        Batal
                    </a>

                </div>

            </form>

        <?php else: ?>

            <div class="alert alert-warning">
                Belum ada data judul tugas akhir yang dapat diedit.
            </div>

            <a href="mahasiswa_tugas.php" class="btn btn-secondary">
                Kembali
            </a>

        <?php endif; ?>

    </section>

</div>

<?php renderMahasiswaLayoutEnd(); ?>