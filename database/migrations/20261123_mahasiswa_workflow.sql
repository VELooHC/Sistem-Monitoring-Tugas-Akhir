ALTER TABLE bimbingan
    ADD COLUMN IF NOT EXISTS waktu_bimbingan TIME DEFAULT NULL AFTER tanggal_bimbingan,
    ADD COLUMN IF NOT EXISTS agenda VARCHAR(255) NOT NULL DEFAULT '' AFTER waktu_bimbingan;

ALTER TABLE dokumen
    MODIFY COLUMN jenis_dokumen ENUM(
        'Proposal',
        'Surat Pengajuan',
        'Surat Penelitian',
        'Seminar Proposal',
        'Seminar Hasil',
        'Sidang Tugas Akhir',
        'Skripsi',
        'Lainnya'
    ) NOT NULL,
    ADD COLUMN IF NOT EXISTS google_drive_id VARCHAR(100) DEFAULT NULL AFTER file_path,
    ADD COLUMN IF NOT EXISTS nilai DECIMAL(5,2) DEFAULT NULL AFTER status_persetujuan;

ALTER TABLE tugas_akhir
    ADD COLUMN IF NOT EXISTS nama_file VARCHAR(255) DEFAULT NULL AFTER status_ta,
    ADD COLUMN IF NOT EXISTS file_path VARCHAR(255) DEFAULT NULL AFTER nama_file,
    ADD COLUMN IF NOT EXISTS google_drive_id VARCHAR(100) DEFAULT NULL AFTER file_path,
    ADD COLUMN IF NOT EXISTS tanggal_upload DATETIME DEFAULT NULL AFTER google_drive_id;

INSERT INTO tugas_akhir
    (nim, judul_ta, status_ta, nama_file, file_path, google_drive_id, tanggal_upload)
SELECT
    d.nim,
    m.judul_ta,
    'Menunggu Persetujuan',
    d.nama_file,
    d.file_path,
    d.google_drive_id,
    d.tanggal_upload
FROM dokumen d
INNER JOIN mahasiswa m ON m.nim = d.nim
WHERE d.jenis_dokumen = 'Proposal'
  AND NOT EXISTS (
      SELECT 1
      FROM tugas_akhir ta
      WHERE ta.nim = d.nim
        AND ta.file_path = d.file_path
  );
