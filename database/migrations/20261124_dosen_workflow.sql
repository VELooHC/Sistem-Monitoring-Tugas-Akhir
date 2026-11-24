ALTER TABLE bimbingan
    ADD COLUMN IF NOT EXISTS status_progres VARCHAR(40) DEFAULT NULL AFTER catatan_bimbingan,
    ADD COLUMN IF NOT EXISTS status_review ENUM('Menunggu Review', 'Revisi', 'Disetujui')
        NOT NULL DEFAULT 'Menunggu Review' AFTER status_progres,
    ADD COLUMN IF NOT EXISTS catatan_dosen TEXT DEFAULT NULL AFTER status_review;

CREATE TABLE IF NOT EXISTS penilaian_seminar (
    id_penilaian INT(11) NOT NULL AUTO_INCREMENT,
    id_jadwal INT(11) NOT NULL,
    id_dosen INT(11) NOT NULL,
    nilai DECIMAL(5,2) NOT NULL,
    catatan TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_penilaian),
    UNIQUE KEY uq_penilaian_jadwal_dosen (id_jadwal, id_dosen),
    KEY fk_penilaian_dosen (id_dosen),
    CONSTRAINT fk_penilaian_jadwal
        FOREIGN KEY (id_jadwal) REFERENCES jadwal_ta (id_jadwal)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_penilaian_dosen
        FOREIGN KEY (id_dosen) REFERENCES dosen (id_user)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
