<?php
declare(strict_types=1);

function fetchAcademicEvents(mysqli $connection, string $startDate, string $endDate): array
{
    $statement = mysqli_prepare(
        $connection,
        "        SELECT events.event_id, events.starts_at, events.has_time, events.title, events.category,
                events.location, events.student_name, events.nim, events.lecturer_name,
                events.status, events.details
         FROM (
             SELECT CONCAT('b-', b.id_bimbingan) AS event_id,
                    TIMESTAMP(b.tanggal_bimbingan, COALESCE(b.waktu_bimbingan, '00:00:00')) AS starts_at,
                    IF(b.waktu_bimbingan IS NULL, 0, 1) AS has_time,
                    COALESCE(NULLIF(b.agenda, ''), 'Bimbingan') AS title,
                    'Bimbingan' AS category,
                    '' AS location,
                    m.nama_lengkap AS student_name,
                    m.nim,
                    COALESCE(d.nama_lengkap, '') AS lecturer_name,
                    'Tidak tersedia pada database' AS status,
                    COALESCE(b.catatan_bimbingan, '') AS details
             FROM bimbingan b
             INNER JOIN mahasiswa m ON m.nim = b.nim
             LEFT JOIN dosen d ON d.nidn = b.id_dosen
             WHERE b.tanggal_bimbingan >= ? AND b.tanggal_bimbingan < ?
             UNION ALL
             SELECT CONCAT('j-', j.id_jadwal) AS event_id,
                    j.tanggal_kegiatan AS starts_at,
                    1 AS has_time,
                    j.jenis_kegiatan AS title,
                    j.jenis_kegiatan AS category,
                    j.ruangan AS location,
                    m.nama_lengkap AS student_name,
                    m.nim,
                    COALESCE(GROUP_CONCAT(DISTINCT d.nama_lengkap ORDER BY d.nama_lengkap SEPARATOR ', '), '') AS lecturer_name,
                    'Tidak tersedia pada database' AS status,
                    '' AS details
             FROM jadwal_ta j
             INNER JOIN mahasiswa m ON m.nim = j.nim
             LEFT JOIN penguji p ON p.id_jadwal = j.id_jadwal
             LEFT JOIN dosen d ON d.id_user = p.id_dosen
             WHERE j.tanggal_kegiatan >= ? AND j.tanggal_kegiatan < ?
             GROUP BY j.id_jadwal, j.tanggal_kegiatan, j.jenis_kegiatan,
                      j.ruangan, m.nama_lengkap, m.nim
         ) AS events
         ORDER BY events.starts_at ASC, events.event_id ASC"
    );

    if ($statement === false) {
        throw new RuntimeException('Gagal menyiapkan agenda akademik.');
    }

    mysqli_stmt_bind_param($statement, 'ssss', $startDate, $endDate, $startDate, $endDate);
    if (!mysqli_stmt_execute($statement)) {
        throw new RuntimeException('Gagal mengambil agenda akademik.');
    }

    $result = mysqli_stmt_get_result($statement);
    $events = [];
    if ($result instanceof mysqli_result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $events[] = $row;
        }
    }
    mysqli_stmt_close($statement);

    return $events;
}
