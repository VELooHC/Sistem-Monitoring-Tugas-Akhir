
<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$client = new Google\Client();
$client->setAuthConfig(__DIR__ . '/../config/google-credentials.json');
$client->addScope(Google\Service\Drive::DRIVE_FILE);
$client->setAccessType('offline');
$client->setPrompt('consent');
$client->setRedirectUri(
    'http://localhost/NAMA_FOLDER/dashboard/auth_google.php'
);

$tokenPath = __DIR__ . '/../config/google-token.json';

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        die('Gagal menghubungkan Google Drive: ' .
            htmlspecialchars($token['error']));
    }

    file_put_contents(
        $tokenPath,
        json_encode($token, JSON_PRETTY_PRINT)
    );

    header('Location: mahasiswa_tugas.php?drive=connected');
    exit;
}

if (file_exists($tokenPath)) {
    $token = json_decode(
        file_get_contents($tokenPath),
        true
    );

    if (is_array($token)) {
        $client->setAccessToken($token);

        if (!$client->isAccessTokenExpired()) {
            echo 'Google Drive sudah terhubung.';
            exit;
        }

        if (!empty($token['refresh_token'])) {
            $newToken = $client->fetchAccessTokenWithRefreshToken(
                $token['refresh_token']
            );

            if (!isset($newToken['error'])) {
                $newToken['refresh_token'] =
                    $token['refresh_token'];

                file_put_contents(
                    $tokenPath,
                    json_encode($newToken, JSON_PRETTY_PRINT)
                );

                echo 'Google Drive berhasil diperbarui.';
                exit;
            }
        }
    }
}

$authUrl = $client->createAuthUrl();

header('Location: ' . $authUrl);
exit;