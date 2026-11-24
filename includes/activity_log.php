<?php
declare(strict_types=1);

function recordActivity(mysqli $connection, int $userId, string $activity, string $details = ''): void
{
    if ($userId <= 0 || $activity === '' || strlen($activity) > 100) {
        error_log('Log aktivitas tidak ditulis: data pengguna atau nama aktivitas tidak valid.');
        return;
    }

    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO log_aktivitas (id_user, aktivitas, keterangan) VALUES (?, ?, ?)'
    );
    if ($statement === false) {
        error_log('Gagal menyiapkan log aktivitas: ' . mysqli_error($connection));
        return;
    }

    mysqli_stmt_bind_param($statement, 'iss', $userId, $activity, $details);
    if (!mysqli_stmt_execute($statement)) {
        error_log('Gagal mencatat aktivitas "' . $activity . '": ' . mysqli_stmt_error($statement));
    }
    mysqli_stmt_close($statement);
}
