-- ==========================================
-- Database Schema: cloud_team_management_db
-- ==========================================

CREATE DATABASE IF NOT EXISTS `cloud_team_management_db`;
USE `cloud_team_management_db`;

-- Drop tables if they exist (dependencies first)
DROP TABLE IF EXISTS `anggota`;
DROP TABLE IF EXISTS `proyek`;
DROP TABLE IF EXISTS `users`;

-- 1. Create users table
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'manager', 'member') NOT NULL DEFAULT 'member',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create proyek table
CREATE TABLE `proyek` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_proyek` VARCHAR(100) NOT NULL,
  `deskripsi` TEXT NULL,
  `tanggal_mulai` DATE NOT NULL,
  `tanggal_selesai` DATE NOT NULL,
  `status` ENUM('direncanakan', 'berjalan', 'selesai', 'tertunda') NOT NULL DEFAULT 'direncanakan',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create anggota table
CREATE TABLE `anggota` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(100) NOT NULL,
  `nip_nim` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NULL UNIQUE,
  `telepon` VARCHAR(20) NULL,
  `jabatan` VARCHAR(50) NULL,
  `foto` VARCHAR(255) NULL,
  `id_user` INT UNIQUE NULL,
  `id_proyek` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_anggota_users` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_anggota_proyek` FOREIGN KEY (`id_proyek`) REFERENCES `proyek` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- Seed Data for Development
-- ==========================================

-- Insert Users (Password: admin123 for admin, member123 for others)
INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`) VALUES
(1, 'admin', 'admin@cloudteam.com', '$2y$10$VMfto0o7bcxb55lzm2gJEur3wIfgza71f5RyGJ9JyfJFRMGxjP8Je', 'admin'),
(2, 'hafiz', 'hafiz@cloudteam.com', '$2y$10$x4.Az0VtO.H17TgpPA9wu.nVbb6WIYEQculy8379XfnqW.DVqTCDq', 'manager');

-- Insert Proyek
INSERT INTO `proyek` (`id`, `nama_proyek`, `deskripsi`, `tanggal_mulai`, `tanggal_selesai`, `status`) VALUES
(1, 'Cloud Migration Service', 'Migrasi seluruh server perusahaan dari on-premise ke Amazon Web Services (AWS).', '2026-06-01', '2026-12-31', 'berjalan'),
(2, 'Cloud Team Management App', 'Pembuatan aplikasi manajemen tim internal berbasis cloud (PHP Native & MySQL).', '2026-07-01', '2026-09-30', 'direncanakan');

-- Insert Anggota
INSERT INTO `anggota` (`id`, `nama`, `nip_nim`, `email`, `telepon`, `jabatan`, `foto`, `id_user`, `id_proyek`) VALUES
(1, 'Hafiz Andrean', '2201802140', 'hafiz@cloudteam.com', '081234567890', 'Project Manager', 'hafiz.jpg', 2, 1),
(2, 'Rizqi Rohimulhuda', '2201802141', 'rizqi@cloudteam.com', '081234567891', 'Web Developer', 'rizqi.jpg', NULL, 1),
(3, 'Ellena Eka Hapsari', '2201802142', 'ellena@cloudteam.com', '081234567892', 'Web Designer', 'ellena.jpg', NULL, 2);
