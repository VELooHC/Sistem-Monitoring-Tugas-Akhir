-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 09, 2026 at 09:20 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_bimbingan_ta`
--

-- --------------------------------------------------------

--
-- Table structure for table `bimbingan`
--

CREATE TABLE `bimbingan` (
  `id_bimbingan` int(11) NOT NULL,
  `nim` char(9) NOT NULL,
  `id_dosen` int(11) NOT NULL,
  `tanggal_bimbingan` date NOT NULL,
  `waktu_bimbingan` time DEFAULT NULL,
  `agenda` varchar(255) NOT NULL DEFAULT '',
  `catatan_bimbingan` text DEFAULT NULL,
  `status_progres` varchar(40) DEFAULT NULL,
  `status_review` enum('Menunggu Review','Revisi','Disetujui') NOT NULL DEFAULT 'Menunggu Review',
  `catatan_dosen` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dokumen`
--

CREATE TABLE `dokumen` (
  `id_dokumen` int(11) NOT NULL,
  `nim` char(9) NOT NULL,
  `jenis_dokumen` enum('Proposal','Surat Pengajuan','Surat Penelitian','Seminar Proposal','Seminar Hasil','Sidang Tugas Akhir','Skripsi','Lainnya') NOT NULL,
  `nama_file` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `google_drive_id` varchar(100) DEFAULT NULL,
  `tanggal_upload` datetime DEFAULT current_timestamp(),
  `status_persetujuan` enum('Menunggu','Disetujui','Ditolak','Revisi') DEFAULT 'Menunggu',
  `nilai` decimal(5,2) DEFAULT NULL,
  `catatan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dosen`
--

CREATE TABLE `dosen` (
  `id_user` int(11) NOT NULL,
  `nidn` int(9) DEFAULT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dosen`
--

INSERT INTO `dosen` (`id_user`, `nidn`, `nama_lengkap`, `username`, `email`) VALUES
(9, 1110419, 'Veloohc Yuuki', 'Vel', 'vel@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `jadwal_ta`
--

CREATE TABLE `jadwal_ta` (
  `id_jadwal` int(11) NOT NULL,
  `nim` char(9) NOT NULL,
  `jenis_kegiatan` enum('Seminar Proposal','Seminar Hasil','Sidang Tugas Akhir') NOT NULL,
  `tanggal_kegiatan` datetime NOT NULL,
  `ruangan` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `log_aktivitas`
--

CREATE TABLE `log_aktivitas` (
  `id_log` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `aktivitas` varchar(100) NOT NULL,
  `tanggal` datetime DEFAULT current_timestamp(),
  `keterangan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mahasiswa`
--

CREATE TABLE `mahasiswa` (
  `id_user` int(11) NOT NULL,
  `nim` char(9) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `judul_ta` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `id_dosen` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mahasiswa`
--

INSERT INTO `mahasiswa` (`id_user`, `nim`, `nama_lengkap`, `username`, `judul_ta`, `email`, `id_dosen`) VALUES
(3, '262410013', 'Widtya Lestari', '262410013', 'Monitoring Tugas Akhir', NULL, NULL),
(4, '262410018', 'Muhammad Sri Maulana', 'Veloohc_', 'Analisis Sentimen pada Ulasan Genshin impact di Goggle play', NULL, NULL),
(6, '262410022', 'Laurenius Fadlyanus Fajar', '262410022', 'Database Bimbingan', NULL, NULL),
(10, '262410010', 'Karlitos Aril', '262410010', 'Pengembangan Web', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `penguji`
--

CREATE TABLE `penguji` (
  `id` int(11) NOT NULL,
  `id_jadwal` int(11) NOT NULL,
  `id_dosen` int(11) NOT NULL,
  `peran` enum('Ketua','Penguji 1','Penguji 2','Sekretaris') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `penilaian_seminar`
--

CREATE TABLE `penilaian_seminar` (
  `id_penilaian` int(11) NOT NULL,
  `id_jadwal` int(11) NOT NULL,
  `id_dosen` int(11) NOT NULL,
  `nilai` decimal(5,2) NOT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tugas_akhir`
--

CREATE TABLE `tugas_akhir` (
  `id_ta` int(11) NOT NULL,
  `nim` char(9) NOT NULL,
  `judul_ta` varchar(255) NOT NULL,
  `tanggal_update` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status_ta` enum('Belum Mengajukan','Menunggu Persetujuan','Disetujui','Ditolak','Revisi') DEFAULT 'Belum Mengajukan',
  `nama_file` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `google_drive_id` varchar(100) DEFAULT NULL,
  `tanggal_upload` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id_user` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(225) NOT NULL,
  `role` enum('admin','dosen','mahasiswa') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `update_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id_user`, `username`, `password`, `role`, `created_at`, `update_at`) VALUES
(1, 'Admin', 'Admin#1234', 'admin', '2026-06-09 11:44:00', '2026-06-09 11:44:00'),
(3, '262410013', '12345678', 'mahasiswa', '2026-06-09 11:44:00', '2026-06-11 05:16:34'),
(4, 'Veloohc_', '12345678', 'mahasiswa', '2026-06-09 14:32:22', '2026-07-08 16:16:10'),
(6, '262410022', '12345678', 'mahasiswa', '2026-06-11 02:32:45', '2026-06-11 05:16:43'),
(9, 'Vel', '12345678', 'dosen', '2026-06-11 05:59:03', '2026-06-11 05:59:03'),
(10, '262410010', '12345678', 'mahasiswa', '2026-07-08 03:28:57', '2026-07-08 03:28:57');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bimbingan`
--
ALTER TABLE `bimbingan`
  ADD PRIMARY KEY (`id_bimbingan`),
  ADD KEY `fk_bimbingan_mahasiswa` (`nim`),
  ADD KEY `fk_bimbingan_dosen` (`id_dosen`);

--
-- Indexes for table `dokumen`
--
ALTER TABLE `dokumen`
  ADD PRIMARY KEY (`id_dokumen`),
  ADD KEY `fk_dokumen_mahasiswa` (`nim`);

--
-- Indexes for table `dosen`
--
ALTER TABLE `dosen`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `nidn` (`nidn`) USING BTREE;

--
-- Indexes for table `jadwal_ta`
--
ALTER TABLE `jadwal_ta`
  ADD PRIMARY KEY (`id_jadwal`),
  ADD KEY `fk_jadwal_mahasiswa` (`nim`);

--
-- Indexes for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `fk_log_user` (`id_user`);

--
-- Indexes for table `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `nim` (`nim`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_mahasiswa_dosen` (`id_dosen`);

--
-- Indexes for table `penguji`
--
ALTER TABLE `penguji`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_penguji` (`id_jadwal`),
  ADD KEY `fk_penguji_dosen` (`id_dosen`);

--
-- Indexes for table `penilaian_seminar`
--
ALTER TABLE `penilaian_seminar`
  ADD PRIMARY KEY (`id_penilaian`),
  ADD UNIQUE KEY `uq_penilaian_jadwal_dosen` (`id_jadwal`,`id_dosen`),
  ADD KEY `fk_penilaian_dosen` (`id_dosen`);

--
-- Indexes for table `tugas_akhir`
--
ALTER TABLE `tugas_akhir`
  ADD PRIMARY KEY (`id_ta`),
  ADD KEY `fk_ta_mahasiswa` (`nim`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bimbingan`
--
ALTER TABLE `bimbingan`
  MODIFY `id_bimbingan` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dokumen`
--
ALTER TABLE `dokumen`
  MODIFY `id_dokumen` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jadwal_ta`
--
ALTER TABLE `jadwal_ta`
  MODIFY `id_jadwal` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `penguji`
--
ALTER TABLE `penguji`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `penilaian_seminar`
--
ALTER TABLE `penilaian_seminar`
  MODIFY `id_penilaian` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tugas_akhir`
--
ALTER TABLE `tugas_akhir`
  MODIFY `id_ta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bimbingan`
--
ALTER TABLE `bimbingan`
  ADD CONSTRAINT `fk_bimbingan_dosen` FOREIGN KEY (`id_dosen`) REFERENCES `dosen` (`nidn`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bimbingan_mahasiswa` FOREIGN KEY (`nim`) REFERENCES `mahasiswa` (`nim`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `dokumen`
--
ALTER TABLE `dokumen`
  ADD CONSTRAINT `fk_dokumen_mahasiswa` FOREIGN KEY (`nim`) REFERENCES `mahasiswa` (`nim`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `dosen`
--
ALTER TABLE `dosen`
  ADD CONSTRAINT `fk_dosen_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `jadwal_ta`
--
ALTER TABLE `jadwal_ta`
  ADD CONSTRAINT `fk_jadwal_mahasiswa` FOREIGN KEY (`nim`) REFERENCES `mahasiswa` (`nim`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD CONSTRAINT `fk_log_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD CONSTRAINT `fk_mahasiswa_dosen` FOREIGN KEY (`id_dosen`) REFERENCES `dosen` (`id_user`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mahasiswa_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `penguji`
--
ALTER TABLE `penguji`
  ADD CONSTRAINT `fk_penguji` FOREIGN KEY (`id_jadwal`) REFERENCES `jadwal_ta` (`id_jadwal`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_penguji_dosen` FOREIGN KEY (`id_dosen`) REFERENCES `dosen` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `penilaian_seminar`
--
ALTER TABLE `penilaian_seminar`
  ADD CONSTRAINT `fk_penilaian_jadwal` FOREIGN KEY (`id_jadwal`) REFERENCES `jadwal_ta` (`id_jadwal`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_penilaian_dosen` FOREIGN KEY (`id_dosen`) REFERENCES `dosen` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tugas_akhir`
--
ALTER TABLE `tugas_akhir`
  ADD CONSTRAINT `fk_ta_mahasiswa` FOREIGN KEY (`nim`) REFERENCES `mahasiswa` (`nim`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
