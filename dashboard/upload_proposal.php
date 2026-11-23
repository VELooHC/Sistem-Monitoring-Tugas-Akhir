
<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/mahasiswa_layout.php';

requireLogin(['mahasiswa']);

$user = currentUser();
$userId = (int) ($user['id'] ?? 0);

$accountData = fetchLoggedMahasiswa($koneksi, $userId);
$nim = trim((string) ($accountData['nim'] ?? ''));

function kembali(string $pesan): never
{
    echo '<script>alert(' .
        json_encode($pesan) .
        '); window.location.href="mahasiswa_tugas.php";</script>';
    exit;
}

// 1. Periksa request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    kembali('Metode pengiriman tidak valid.');
}

// 2. Periksa NIM
if ($nim === '' || strlen($nim) !== 9) {
    kembali('NIM mahasiswa tidak ditemukan atau tidak valid.');
}

// 3. Periksa file
if (!isset($_FILES['proposal'])) {
    kembali('File belum dipilih.');
}

$file = $_FILES['proposal'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    kembali('Upload gagal. Kode error: ' . $file['error']);
}

$maxSize = 10 * 1024 * 1024;

if ($file['size'] > $maxSize) {
    kembali('Ukuran file maksimal 10 MB.');
}

$namaAsli = basename($file['name']);
$ekstensi = strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION));

$allowed = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

if (!isset($allowed[$ekstensi])) {
    kembali('Format file harus PDF, DOC, atau DOCX.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);

if ($mime !== $allowed[$ekstensi]) {
    kembali('Tipe file tidak sesuai dengan ekstensi.');
}

// 4. Periksa Google Drive
$tokenPath = __DIR__ . '/../config/google-token.json';

if (!file_exists($tokenPath)) {
    kembali('Google Drive belum terhubung. Hubungkan Google Drive terlebih dahulu.');
}

$client = new Google\Client();
$client->setAuthConfig(
    __DIR__ . '/../config/google-credentials.json'
);
$client->addScope(Google\Service\Drive::DRIVE_FILE);
$client->setAccessType('offline');

$token = json_decode(file_get_contents($tokenPath), true);

if (!is_array($token)) {
    kembali('Token Google Drive tidak valid.');
}

$client->setAccessToken($token);

if ($client->isAccessTokenExpired()) {
    if (empty($token['refresh_token'])) {
        kembali('Token Google Drive kedaluwarsa. Hubungkan ulang akun Google.');
    }

    $newToken = $client->fetchAccessTokenWithRefreshToken(
        $token['refresh_token']
    );

    if (isset($newToken['error'])) {
        kembali('Gagal memperbarui koneksi Google Drive.');
    }

    $newToken['refresh_token'] = $token['refresh_token'];

    file_put_contents(
        $tokenPath,
        json_encode($newToken, JSON_PRETTY_PRINT)
    );

    $client->setAccessToken($newToken);
}

// 5. Upload ke Google Drive
$drive = new Google\Service\Drive($client);

$driveFile = new Google\Service\Drive\DriveFile([
    'name' => $namaAsli,
    'description' => 'Dokumen tugas akhir mahasiswa NIM ' . $nim
]);

try {
    $uploaded = $drive->files->create(
        $driveFile,
        [
            'data' => file_get_contents($file['tmp_name']),
            'mimeType' => $mime,
            'uploadType' => 'multipart',
            'fields' => 'id,name,webViewLink'
        ]
    );

    $driveId = $uploaded->id;

    if (!$driveId) {
        kembali('Google Drive tidak mengembalikan ID file.');
    }

    $driveLink = $uploaded->webViewLink
        ?: 'https://drive.google.com/file/d/' . $driveId . '/view';

    // 6. Simpan informasi ke database
    $jenisDokumen = 'Proposal';
    $status = 'Menunggu';
    $catatan = null;

    $stmt = mysqli_prepare(
        $koneksi,
        "INSERT INTO dokumen
        (nim, jenis_dokumen, nama_file, file_path,
         status_persetujuan, catatan)
        VALUES (?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        throw new Exception(mysqli_error($koneksi));
    }

    mysqli_stmt_bind_param(
        $stmt,
        'ssssss',
        $nim,
        $jenisDokumen,
        $namaAsli,
        $driveLink,
        $status,
        $catatan
    );

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);

    kembali('Dokumen berhasil diunggah ke Google Drive dan database!');

} catch (Throwable $e) {

    // Jika database gagal, hapus file yang telanjur diunggah
    if (!empty($driveId)) {
        try {
            $drive->files->delete($driveId);
        } catch (Throwable $deleteError) {
            error_log($deleteError->getMessage());
        }
    }

    error_log('Upload dokumen: ' . $e->getMessage());

    kembali('Upload gagal. Periksa konfigurasi Google Drive dan database. Detail: ' . $e->getMessage());
}