<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/activity_log.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/mahasiswa_layout.php';

requireLogin(['mahasiswa']);

$user = currentUser();
$userId = (int) ($user['id'] ?? 0);
$accountData = fetchLoggedMahasiswa($koneksi, $userId);
$nim = trim((string) ($accountData['nim'] ?? ''));
$jenisDokumen = trim((string) ($_POST['jenis_dokumen'] ?? 'Tugas Akhir'));
$jenisDokumenValid = [
    'Tugas Akhir',
    'Seminar Proposal',
    'Seminar Hasil',
    'Sidang Tugas Akhir',
];
$isSeminar = in_array($jenisDokumen, array_slice($jenisDokumenValid, 1), true);
$targetPage = $isSeminar
    ? '/dashboard/mahasiswa_seminar.php'
    : '/dashboard/mahasiswa_tugas.php';
$driveId = null;
$drive = null;
$transactionStarted = false;

function kembali(string $pesan, string $targetPage): never
{
    $target = appPath($targetPage);
    echo '<script>alert(' .
        json_encode($pesan, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) .
        '); window.location.href=' .
        json_encode($target, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) .
        ';</script>';
    exit;
}

function bukaFileGoogle(string $link, string $pesan): never
{
    echo '<script>alert(' .
        json_encode($pesan, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) .
        '); window.location.href=' .
        json_encode($link, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) .
        ';</script>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    kembali('Metode pengiriman tidak valid.', '/dashboard/mahasiswa_tugas.php');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    kembali('Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.', $targetPage);
}

if ($accountData === null || $nim === '' || strlen($nim) !== 9) {
    kembali('NIM mahasiswa tidak ditemukan atau tidak valid.', $targetPage);
}

if (!in_array($jenisDokumen, $jenisDokumenValid, true)) {
    kembali('Jenis dokumen tidak valid.', '/dashboard/mahasiswa_tugas.php');
}

if (!isset($_FILES['proposal']) || !is_array($_FILES['proposal'])) {
    kembali('File belum dipilih.', $targetPage);
}

$file = $_FILES['proposal'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    kembali('Upload gagal. Kode error: ' . (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE), $targetPage);
}

$maxSize = 10 * 1024 * 1024;
if ((int) $file['size'] <= 0 || (int) $file['size'] > $maxSize) {
    kembali('Ukuran file harus lebih dari 0 dan maksimal 10 MB.', $targetPage);
}

$namaAsli = basename((string) $file['name']);
if ($namaAsli === '' || strlen($namaAsli) > 255) {
    kembali('Nama file tidak valid atau terlalu panjang.', $targetPage);
}

$ekstensi = strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION));
$allowed = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

if (!isset($allowed[$ekstensi])) {
    kembali('Format file harus PDF, DOC, atau DOCX.', $targetPage);
}

$temporaryFile = (string) $file['tmp_name'];
if (!is_uploaded_file($temporaryFile)) {
    kembali('File unggahan tidak valid.', $targetPage);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($temporaryFile);
if ($mime !== $allowed[$ekstensi]) {
    kembali('Tipe file tidak sesuai dengan ekstensi.', $targetPage);
}

$credentialsPath = __DIR__ . '/../config/google-credentials.json';
$tokenPath = __DIR__ . '/../config/google-token.json';
if (!is_file($credentialsPath) || !is_file($tokenPath)) {
    kembali('Google Drive belum terhubung. Hubungkan Google Drive terlebih dahulu.', $targetPage);
}

$tokenContents = file_get_contents($tokenPath);
$token = $tokenContents === false ? null : json_decode($tokenContents, true);
if (!is_array($token)) {
    kembali('Token Google Drive tidak valid.', $targetPage);
}

try {
    $client = new Google\Client();
    $client->setAuthConfig($credentialsPath);
    $client->addScope(Google\Service\Drive::DRIVE_FILE);
    $client->setAccessType('offline');
    $client->setAccessToken($token);

    if ($client->isAccessTokenExpired()) {
        if (empty($token['refresh_token'])) {
            kembali('Token Google Drive kedaluwarsa. Hubungkan ulang akun Google.', $targetPage);
        }

        $newToken = $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
        if (isset($newToken['error'])) {
            error_log('Google Drive token refresh: ' . (string) ($newToken['error_description'] ?? $newToken['error']));
            kembali('Gagal memperbarui koneksi Google Drive.', $targetPage);
        }

        $newToken['refresh_token'] = $token['refresh_token'];
        if (file_put_contents($tokenPath, json_encode($newToken, JSON_PRETTY_PRINT), LOCK_EX) === false) {
            kembali('Token Google Drive berhasil diperbarui, tetapi gagal disimpan. Periksa izin file token.', $targetPage);
        }

        $client->setAccessToken($newToken);
    }
} catch (Throwable $exception) {
    error_log('Konfigurasi Google Drive: ' . $exception->getMessage());
    kembali('Konfigurasi Google Drive tidak valid. Periksa kredensial dan token.', $targetPage);
}

$shareEmails = [];
$accessWarnings = [];
$dosenStatement = mysqli_prepare(
    $koneksi,
    'SELECT m.email AS email_mahasiswa, d.email AS email_dosen
     FROM mahasiswa m
     LEFT JOIN dosen d ON d.id_user = m.id_dosen
     WHERE m.nim = ?
     LIMIT 1'
);

if ($dosenStatement === false) {
    throw new RuntimeException('Gagal menyiapkan pencarian dosen pembimbing.');
}

mysqli_stmt_bind_param($dosenStatement, 's', $nim);
if (!mysqli_stmt_execute($dosenStatement)) {
    throw new RuntimeException('Gagal mengambil dosen pembimbing.');
}

$dosenResult = mysqli_stmt_get_result($dosenStatement);
$dosenRow = $dosenResult instanceof mysqli_result ? mysqli_fetch_assoc($dosenResult) : null;
mysqli_stmt_close($dosenStatement);

foreach ([
    'mahasiswa' => $dosenRow['email_mahasiswa'] ?? null,
    'dosen' => $dosenRow['email_dosen'] ?? null,
] as $role => $email) {
    if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $shareEmails[] = $email;
    } else {
        $accessWarnings[] = $role === 'dosen'
            ? 'Dosen pembimbing belum memiliki email yang valid.'
            : 'Email mahasiswa belum valid sehingga akses lihat file belum dapat dibagikan.';
    }
}
$shareEmails = array_values(array_unique($shareEmails));

$drive = new Google\Service\Drive($client);
$uploadedFile = null;

try {
    // Kunci baris mahasiswa agar dua request upload dari akun yang sama
    // tidak bisa lolos pengecekan secara bersamaan.
    if (!mysqli_begin_transaction($koneksi)) {
        throw new RuntimeException('Gagal memulai transaksi database.');
    }
    $transactionStarted = true;

    $lockStatement = mysqli_prepare(
        $koneksi,
        'SELECT nim FROM mahasiswa WHERE nim = ? FOR UPDATE'
    );
    if ($lockStatement === false) {
        throw new RuntimeException('Gagal menyiapkan penguncian akun mahasiswa.');
    }
    mysqli_stmt_bind_param($lockStatement, 's', $nim);
    if (!mysqli_stmt_execute($lockStatement)) {
        mysqli_stmt_close($lockStatement);
        throw new RuntimeException('Gagal mengunci akun mahasiswa.');
    }
    $lockResult = mysqli_stmt_get_result($lockStatement);
    $lockedMahasiswa = $lockResult instanceof mysqli_result
        ? mysqli_fetch_assoc($lockResult)
        : null;
    mysqli_stmt_close($lockStatement);

    if ($lockedMahasiswa === null) {
        throw new RuntimeException('Data akun mahasiswa tidak ditemukan.');
    }

    // Satu akun hanya boleh memiliki satu file untuk setiap jenis dokumen.
    if ($isSeminar) {
        $checkStatement = mysqli_prepare(
            $koneksi,
            'SELECT id_dokumen FROM dokumen
             WHERE nim = ? AND jenis_dokumen = ?
             LIMIT 1'
        );
        if ($checkStatement === false) {
            throw new RuntimeException('Gagal memeriksa dokumen yang sudah diunggah.');
        }
        mysqli_stmt_bind_param($checkStatement, 'ss', $nim, $jenisDokumen);
    } else {
        $checkStatement = mysqli_prepare(
            $koneksi,
            'SELECT id_ta FROM tugas_akhir
             WHERE nim = ?
             LIMIT 1'
        );
        if ($checkStatement === false) {
            throw new RuntimeException('Gagal memeriksa file tugas akhir yang sudah diunggah.');
        }
        mysqli_stmt_bind_param($checkStatement, 's', $nim);
    }

    if (!mysqli_stmt_execute($checkStatement)) {
        mysqli_stmt_close($checkStatement);
        throw new RuntimeException('Gagal memeriksa status unggahan sebelumnya.');
    }
    $checkResult = mysqli_stmt_get_result($checkStatement);
    $alreadyUploaded = $checkResult instanceof mysqli_result
        && mysqli_num_rows($checkResult) > 0;
    mysqli_stmt_close($checkStatement);

    if ($alreadyUploaded) {
        mysqli_rollback($koneksi);
        $transactionStarted = false;
        kembali(
            'Dokumen ' . $jenisDokumen . ' sudah pernah diunggah. Untuk menggantinya, hapus terlebih dahulu data dokumen di database dan file lamanya di Google Drive, lalu unggah file baru.',
            $targetPage
        );
    }

    $contents = file_get_contents($temporaryFile);
    if ($contents === false) {
        throw new RuntimeException('Tidak dapat membaca file sementara.');
    }

    $driveFile = new Google\Service\Drive\DriveFile([
        'name' => $namaAsli,
        'description' => 'Dokumen ' . $jenisDokumen . ' mahasiswa NIM ' . $nim,
    ]);

    $uploadedFile = $drive->files->create(
        $driveFile,
        [
            'data' => $contents,
            'mimeType' => $mime,
            'uploadType' => 'multipart',
            'fields' => 'id,name,webViewLink',
        ]
    );

    $driveId = (string) ($uploadedFile->id ?? '');
    if ($driveId === '') {
        throw new RuntimeException('Google Drive tidak mengembalikan ID file.');
    }

    foreach ($shareEmails as $email) {
        $drive->permissions->create(
            $driveId,
            new Google\Service\Drive\Permission([
                'type' => 'user',
                'role' => 'reader',
                'emailAddress' => $email,
            ]),
            ['sendNotificationEmail' => true]
        );
    }

    $driveLink = (string) ($uploadedFile->webViewLink ?? '');
    if ($driveLink === '' || !in_array((string) parse_url($driveLink, PHP_URL_HOST), ['drive.google.com', 'docs.google.com'], true)) {
        $driveLink = 'https://drive.google.com/file/d/' . rawurlencode($driveId) . '/view';
    }

    if ($isSeminar) {
        $stmt = mysqli_prepare(
            $koneksi,
            'INSERT INTO dokumen
             (nim, jenis_dokumen, nama_file, file_path, google_drive_id, status_persetujuan)
             VALUES (?, ?, ?, ?, ?, \'Menunggu\')'
        );
        if ($stmt === false) {
            throw new RuntimeException('Gagal menyiapkan penyimpanan dokumen seminar.');
        }

        mysqli_stmt_bind_param($stmt, 'sssss', $nim, $jenisDokumen, $namaAsli, $driveLink, $driveId);
    } else {
        $judulTA = trim((string) ($accountData['judul_ta'] ?? ''));
        if ($judulTA === '') {
            $judulTA = 'Tugas Akhir';
        }

        $stmt = mysqli_prepare(
            $koneksi,
            'INSERT INTO tugas_akhir
             (nim, judul_ta, status_ta, nama_file, file_path, google_drive_id, tanggal_upload)
             VALUES (?, ?, \'Menunggu Persetujuan\', ?, ?, ?, NOW())'
        );
        if ($stmt === false) {
            throw new RuntimeException('Gagal menyiapkan penyimpanan file tugas akhir.');
        }

        mysqli_stmt_bind_param($stmt, 'sssss', $nim, $judulTA, $namaAsli, $driveLink, $driveId);
    }

    if (!mysqli_stmt_execute($stmt)) {
        $statementError = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Gagal menyimpan data dokumen: ' . $statementError);
    }
    mysqli_stmt_close($stmt);
    if (!mysqli_commit($koneksi)) {
        throw new RuntimeException('Gagal mengesahkan penyimpanan dokumen.');
    }
    $transactionStarted = false;
    recordActivity(
        $koneksi,
        $userId,
        'Unggah dokumen',
        'NIM ' . $nim . ' · ' . $jenisDokumen . ' · ' . $namaAsli
    );

    $notice = 'Dokumen berhasil diunggah. File akan dibuka melalui Google Drive.';
    if ($accessWarnings !== []) {
        $notice .= ' ' . implode(' ', $accessWarnings);
    }

    bukaFileGoogle($driveLink, $notice);
} catch (Throwable $exception) {
    if ($transactionStarted) {
        mysqli_rollback($koneksi);
    }

    if ($driveId !== null && $drive !== null) {
        try {
            $drive->files->delete($driveId);
        } catch (Throwable $deleteError) {
            error_log('Gagal menghapus file Google Drive setelah upload gagal: ' . $deleteError->getMessage());
        }
    }

    error_log('Upload dokumen mahasiswa: ' . $exception->getMessage());
    kembali('Upload gagal. Periksa koneksi Google Drive dan database, lalu coba lagi.', $targetPage);
}
