-- ==========================================
-- Database Schema: cloud_team_management_db
-- ==========================================

CREATE DATABASE IF NOT EXISTS `cloud_team_management_db`;
USE `cloud_team_management_db`;

-- Drop tables if they exist (dependencies first)
DROP TABLE IF EXISTS `anggota_proyek`;
DROP TABLE IF EXISTS `anggota`;
DROP TABLE IF EXISTS `proyek`;
DROP TABLE IF EXISTS `users`;

-- 1. Create users table
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'member') NOT NULL DEFAULT 'member',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create proyek table
CREATE TABLE `proyek` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_proyek` VARCHAR(100) NOT NULL,
  `deskripsi` TEXT NULL,
  `status` ENUM('direncanakan', 'berjalan', 'selesai', 'tertunda') NOT NULL DEFAULT 'direncanakan',
  `deadline` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create anggota table
CREATE TABLE `anggota` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(100) NOT NULL,
  `nim` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NULL UNIQUE,
  `foto` VARCHAR(255) NULL,
  `id_user` INT UNIQUE NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_anggota_users` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create pivot table anggota_proyek (Many-to-Many)
CREATE TABLE `anggota_proyek` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `anggota_id` INT NOT NULL,
  `proyek_id` INT NOT NULL,
  CONSTRAINT `fk_ap_anggota` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ap_proyek` FOREIGN KEY (`proyek_id`) REFERENCES `proyek` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY `uk_anggota_proyek` (`anggota_id`, `proyek_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- Seed Data for Development
-- ==========================================

-- Insert Users (Password: admin123 for admin, member123 for others)
INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`) VALUES
(1, 'admin', 'admin@cloudteam.com', '$2y$10$VMfto0o7bcxb55lzm2gJEur3wIfgza71f5RyGJ9JyfJFRMGxjP8Je', 'admin'),
(2, 'hafiz', 'hafiz@cloudteam.com', '$2y$10$x4.Az0VtO.H17TgpPA9wu.nVbb6WIYEQculy8379XfnqW.DVqTCDq', 'member');

-- Insert Proyek
INSERT INTO `proyek` (`id`, `nama_proyek`, `deskripsi`, `status`, `deadline`) VALUES
(1, 'Cloud Migration Service', 'Migrasi seluruh server perusahaan dari on-premise ke Amazon Web Services (AWS).', 'berjalan', '2026-12-31'),
(2, 'Cloud Team Management App', 'Pembuatan aplikasi manajemen tim internal berbasis cloud (PHP Native & MySQL).', 'direncanakan', '2026-09-30');

-- Insert Anggota (without telepon and jabatan)
INSERT INTO `anggota` (`id`, `nama`, `nim`, `email`, `foto`, `id_user`) VALUES
(1, 'Hafiz Andrean', '2201802140', 'hafiz@cloudteam.com', 'hafiz.jpg', 2),
(2, 'Rizqi Rohimulhuda', '2201802141', 'rizqi@cloudteam.com', 'rizqi.jpg', NULL),
(3, 'Ellena Eka Hapsari', '2201802142', 'ellena@cloudteam.com', 'ellena.jpg', NULL);

-- Insert Anggota Proyek assignments (Hafiz is on both projects, Rizqi on project 1, Ellena on project 2)
INSERT INTO `anggota_proyek` (`anggota_id`, `proyek_id`) VALUES
(1, 1), -- Hafiz on Project 1
(1, 2), -- Hafiz on Project 2
(2, 1), -- Rizqi on Project 1
(3, 2); -- Ellena on Project 2
