<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/mahasiswa_layout.php';

requireLogin(['mahasiswa']);

$user = currentUser();
$userId = (int) ($user['id'] ?? 0);

$accountData = fetchLoggedMahasiswa($koneksi, $userId);
$sidebarMenus = mahasiswaSidebarMenus('/dashboard/mahasiswa_seminar.php');

if ($accountData === null) {
    die('Data akun mahasiswa tidak ditemukan.');
}

$nim = (string) $accountData['nim'];

$documents = [];
$evaluationsByType = [];

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT
        id_dokumen,
        jenis_dokumen,
        nama_file,
        file_path,
        tanggal_upload,
        status_persetujuan,
        nilai
     FROM dokumen
     WHERE nim = ?
       AND jenis_dokumen IN (
           'Seminar Proposal',
           'Seminar Hasil',
           'Sidang Tugas Akhir'
       )
     ORDER BY tanggal_upload DESC, id_dokumen DESC"
);

if ($stmt === false) {
    throw new RuntimeException('Gagal menyiapkan daftar dokumen.');
}

mysqli_stmt_bind_param($stmt, 's', $nim);

if (!mysqli_stmt_execute($stmt)) {
    throw new RuntimeException('Gagal mengambil daftar dokumen.');
}

$result = mysqli_stmt_get_result($stmt);

if ($result instanceof mysqli_result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $documents[] = $row;
    }

    mysqli_free_result($result);
}

mysqli_stmt_close($stmt);

$sqlPenilaian = "
    SELECT
        jt.id_jadwal,
        jt.jenis_kegiatan,
        pg.id_dosen,
        pg.peran,
        d.nama_lengkap AS nama_dosen,
        ps.nilai,
        ps.catatan,
        ps.created_at

    FROM jadwal_ta jt

    INNER JOIN penguji pg
        ON pg.id_jadwal = jt.id_jadwal

    INNER JOIN dosen d
        ON d.id_user = pg.id_dosen

    LEFT JOIN penilaian_seminar ps
        ON ps.id_jadwal = pg.id_jadwal
        AND ps.id_dosen = pg.id_dosen

    WHERE jt.nim = ?
      AND jt.jenis_kegiatan IN (
          'Seminar Proposal',
          'Seminar Hasil',
          'Sidang Tugas Akhir'
      )

    ORDER BY
        FIELD(
            jt.jenis_kegiatan,
            'Seminar Proposal',
            'Seminar Hasil',
            'Sidang Tugas Akhir'
        ),
        pg.peran ASC
";

$stmtPenilaian = mysqli_prepare($koneksi, $sqlPenilaian);

if ($stmtPenilaian === false) {
    throw new RuntimeException('Gagal menyiapkan data penilaian.');
}

mysqli_stmt_bind_param($stmtPenilaian, 's', $nim);

if (!mysqli_stmt_execute($stmtPenilaian)) {
    throw new RuntimeException('Gagal mengambil data penilaian.');
}

$resultPenilaian = mysqli_stmt_get_result($stmtPenilaian);

if ($resultPenilaian instanceof mysqli_result) {
    while ($row = mysqli_fetch_assoc($resultPenilaian)) {
        $jenis = (string) $row['jenis_kegiatan'];

        if (!isset($evaluationsByType[$jenis])) {
            $evaluationsByType[$jenis] = [];
        }

        $evaluationsByType[$jenis][] = $row;
    }

    mysqli_free_result($resultPenilaian);
}

mysqli_stmt_close($stmtPenilaian);

$statusLabel = [
    'Menunggu' => 'Menunggu pemeriksaan',
    'Disetujui' => 'ACC',
    'Ditolak' => 'Ditolak',
    'Revisi' => 'Revisi',
    'Lulus' => 'Lulus',
    'Mengulang' => 'Mengulang',
];

renderMahasiswaLayoutStart(
    'Seminar / Sidang',
    'Seminar / Sidang',
    'Unggah dokumen dan pantau status, nilai, serta catatan dosen.',
    $user,
    $sidebarMenus,
    $accountData
);
?>

<div class="content-column">

    <section class="info-card">
        <h2>Unggah Dokumen Seminar / Sidang</h2>

        <p>
            Dosen pembimbing mendapat akses lihat saja ke file.
            Penilaian dan catatan revisi diberikan melalui halaman dosen.
        </p>

        <div class="seminar-upload-grid">

            <?php foreach (
                ['Seminar Proposal', 'Seminar Hasil', 'Sidang Tugas Akhir']
                as $jenis
            ): ?>

                <form
                    class="seminar-upload"
                    action="<?= e(appPath('/dashboard/mahasiswa_upload.php')) ?>"
                    method="post"
                    enctype="multipart/form-data"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e(csrfToken()) ?>"
                    >

                    <input
                        type="hidden"
                        name="jenis_dokumen"
                        value="<?= e($jenis) ?>"
                    >

                    <strong><?= e($jenis) ?></strong>

                    <label
                        class="form-label"
                        for="dokumen-<?= e(strtolower(str_replace(' ', '-', $jenis))) ?>"
                    >
                        File (PDF, DOC, DOCX; maksimal 10 MB)
                    </label>

                    <input
                        class="form-input"
                        id="dokumen-<?= e(strtolower(str_replace(' ', '-', $jenis))) ?>"
                        type="file"
                        name="proposal"
                        accept=".pdf,.doc,.docx"
                        required
                    >

                    <button class="action-button" type="submit">
                        Unggah dan Buka File
                    </button>

                </form>

            <?php endforeach; ?>

        </div>
    </section>


    <section class="info-card">

        <h2>Status dan Penilaian</h2>

        <?php if ($documents === []): ?>

            <p>
                Belum ada dokumen seminar atau sidang yang diunggah.
            </p>

        <?php else: ?>

            <div class="list-block">

                <?php foreach ($documents as $document): ?>

                    <?php
                    $jenis = (string) $document['jenis_dokumen'];

                    $statusDokumen = (string) (
                        $document['status_persetujuan'] ?? 'Menunggu'
                    );

                    $labelDokumen = $statusLabel[$statusDokumen]
                        ?? $statusDokumen;

                    $pengujiList = $evaluationsByType[$jenis] ?? [];

                    $jumlahPenguji = count($pengujiList);
                    $jumlahDinilai = 0;
                    $totalNilai = 0;

                    foreach ($pengujiList as $penguji) {
                        if ($penguji['nilai'] !== null) {
                            $jumlahDinilai++;
                            $totalNilai += (float) $penguji['nilai'];
                        }
                    }

                    if ($jumlahPenguji === 0) {
                        $statusPenilaian = 'Belum ada penugasan penguji';
                    } elseif ($jumlahDinilai === $jumlahPenguji) {
                        $statusPenilaian = 'Penilaian lengkap';
                    } else {
                        $statusPenilaian = 'Menunggu penilaian (' .
                            $jumlahDinilai . '/' . $jumlahPenguji . ')';
                    }

                    // Gunakan nilai akhir yang sudah disimpan oleh proses penilaian dosen.
                    // Jangan tampilkan rata-rata sementara sebagai hasil final.
                    $rataRata = ($jumlahPenguji > 0 && $jumlahDinilai === $jumlahPenguji
                        && $document['nilai'] !== null)
                        ? (float) $document['nilai']
                        : null;
                    ?>

                    <article class="list-item">

                        <strong>
                            <?= e($jenis) ?> —
                            <?= e((string) $document['nama_file']) ?>
                        </strong>

                        <span>
                            Diunggah:
                            <?= e((string) $document['tanggal_upload']) ?>
                        </span>

                        <span>
                            Status dokumen:
                            <strong><?= e($labelDokumen) ?></strong>
                        </span>

                        <span>
                            Status penilaian:
                            <strong><?= e($statusPenilaian) ?></strong>
                        </span>

                        <h3>Penilaian Dosen Penguji</h3>

                        <?php if ($pengujiList === []): ?>

                            <p>
                                Belum ada dosen penguji yang ditugaskan
                                untuk kegiatan ini.
                            </p>

                        <?php else: ?>

                            <?php foreach ($pengujiList as $penguji): ?>

                                <div class="penguji-result">

                                    <strong>
                                        <?= e((string) $penguji['peran']) ?>
                                        -
                                        <?= e((string) $penguji['nama_dosen']) ?>
                                    </strong>

                                    <span>
                                        Nilai:

                                        <?php if ($penguji['nilai'] !== null): ?>

                                            <strong>
                                                <?= e((string) $penguji['nilai']) ?>
                                                / 100
                                            </strong>

                                        <?php else: ?>

                                            <span>Belum dinilai</span>

                                        <?php endif; ?>
                                    </span>

                                    <span>
                                        Catatan:

                                        <?= e(
                                            (string) (
                                                ($penguji['catatan'] ?? '') !== ''
                                                ? $penguji['catatan']
                                                : 'Belum ada catatan'
                                            )
                                        ) ?>
                                    </span>

                                </div>

                            <?php endforeach; ?>

                            <div class="average-result">

                                <strong>Rata-rata nilai:</strong>

                                <?php if ($rataRata !== null): ?>

                                    <strong>
                                        <?= number_format($rataRata, 2) ?>
                                        / 100
                                    </strong>

                                <?php else: ?>

                                    <span>Belum tersedia</span>

                                <?php endif; ?>

                            </div>

                        <?php endif; ?>

                        <a
                            href="<?= e((string) $document['file_path']) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Buka dokumen
                        </a>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</div>


<style>
.seminar-upload-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 14px;
}

.seminar-upload {
    display: grid;
    align-content: start;
    gap: 12px;
    padding: 16px;
    border: 1px solid #e1e6ef;
    border-radius: 16px;
    background: #f8f9fc;
}

.penguji-result {
    display: grid;
    gap: 7px;
    padding: 14px;
    margin: 10px 0;
    border: 1px solid #e1e6ef;
    border-radius: 12px;
    background: #f8f9fc;
}

.average-result {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 14px;
    margin: 12px 0;
    border-radius: 12px;
    background: #eef5ff;
}
</style>

<?php renderMahasiswaLayoutEnd(); ?>
