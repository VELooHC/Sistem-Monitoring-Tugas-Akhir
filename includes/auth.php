<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(mixed $token): bool
{
    return isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

function redirect(string $path): void
{
    header('Location: ' . appPath($path));
    exit;
}

function appBasePath(): string
{
    $appRoot = str_replace('\\', '/', (string) (realpath(dirname(__DIR__)) ?: dirname(__DIR__)));
    $documentRootRaw = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
    $documentRoot = str_replace('\\', '/', (string) (realpath($documentRootRaw) ?: $documentRootRaw));
    $documentRoot = rtrim($documentRoot, '/');

    if ($documentRoot !== '' && str_starts_with($appRoot, $documentRoot)) {
        $relativePath = trim(substr($appRoot, strlen($documentRoot)), '/');
        return $relativePath === '' ? '' : '/' . $relativePath;
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $appDirectory = '/' . trim(basename($appRoot), '/');
    $directoryPosition = strpos($scriptName, $appDirectory . '/');

    if ($directoryPosition !== false) {
        return substr($scriptName, 0, $directoryPosition + strlen($appDirectory));
    }

    if ($scriptName !== '' && str_ends_with($scriptName, $appDirectory)) {
        return $scriptName;
    }

    return '';
}

function appPath(string $path): string
{
    $normalizedPath = '/' . ltrim($path, '/');
    return appBasePath() . $normalizedPath;
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);
}

function currentUser(): array
{
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? '',
        'role' => $_SESSION['role'] ?? '',
    ];
}

function redirectByRole(string $role): void
{
    $destinations = [
        'admin' => '/dashboard/admin_dashboard.php',
        'dosen' => '/dashboard/dosen_dashboard.php',
        'mahasiswa' => '/dashboard/mahasiswa_dashboard.php',
    ];

    if (!isset($destinations[$role])) {
        redirect('/login.php');
    }

    redirect($destinations[$role]);
}

function requireLogin(array $allowedRoles = []): void
{
    if (!isLoggedIn()) {
        redirect('/login.php');
    }

    if ($allowedRoles !== [] && !in_array($_SESSION['role'], $allowedRoles, true)) {
        redirectByRole((string) $_SESSION['role']);
    }
}

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
