-- PKL Journey - contoh skema database (struktur saja, tanpa data)
-- File ini dibuat untuk repository publik. Tidak berisi data siswa, akun, atau kata sandi.
-- Impor melalui phpMyAdmin atau: mysql -u root -p < database/schema.example.sql

CREATE DATABASE IF NOT EXISTS `smart_pkl` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `smart_pkl`;

SET FOREIGN_KEY_CHECKS = 0;

-- Tabel: schools
CREATE TABLE IF NOT EXISTS `schools` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabel: users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `kelas` varchar(50) DEFAULT NULL,
  `jurusan` varchar(100) DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `jalur_pkl` varchar(10) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('murid','guru','admin','guru_industri','penguji') NOT NULL DEFAULT 'murid',
  `school_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_users_school` (`school_id`),
  CONSTRAINT `fk_users_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabel: companies
CREATE TABLE IF NOT EXISTS `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL DEFAULT 1,
  `name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_companies_school` (`school_id`),
  CONSTRAINT `fk_companies_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabel: internships
CREATE TABLE IF NOT EXISTS `internships` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `guru_id` int(11) DEFAULT NULL,
  `industri_id` int(11) DEFAULT NULL,
  `penguji_id` int(11) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_id` (`student_id`),
  KEY `fk_internships_guru` (`guru_id`),
  KEY `fk_internships_company` (`company_id`),
  KEY `fk_internships_industri` (`industri_id`),
  KEY `fk_internships_penguji` (`penguji_id`),
  CONSTRAINT `fk_internships_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_internships_guru` FOREIGN KEY (`guru_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_internships_industri` FOREIGN KEY (`industri_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_internships_penguji` FOREIGN KEY (`penguji_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_internships_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabel: pkl_journals
CREATE TABLE IF NOT EXISTS `pkl_journals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `activity_date` date NOT NULL,
  `description` text NOT NULL,
  `status` enum('menunggu','disetujui','revisi') NOT NULL DEFAULT 'menunggu',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_journals_student` (`student_id`),
  CONSTRAINT `fk_journals_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabel: pkl_reports
CREATE TABLE IF NOT EXISTS `pkl_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `summary` text NOT NULL,
  `submission_date` date NOT NULL,
  `status` enum('draft','dikirim','disetujui') NOT NULL DEFAULT 'dikirim',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_reports_student` (`student_id`),
  CONSTRAINT `fk_reports_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabel: pkl_assessments
CREATE TABLE IF NOT EXISTS `pkl_assessments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `guru_id` int(11) NOT NULL,
  `score_discipline` int(11) NOT NULL DEFAULT 0,
  `score_technical` int(11) NOT NULL DEFAULT 0,
  `score_communication` int(11) NOT NULL DEFAULT 0,
  `final_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `feedback` text DEFAULT NULL,
  `assessed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_id` (`student_id`),
  KEY `fk_assessments_guru` (`guru_id`),
  CONSTRAINT `fk_assessments_guru` FOREIGN KEY (`guru_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assessments_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabel: industry_assessments
CREATE TABLE IF NOT EXISTS `industry_assessments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `industri_id` int(11) NOT NULL,
  `score_discipline` int(11) NOT NULL DEFAULT 0,
  `score_attendance` int(11) NOT NULL DEFAULT 0,
  `score_work_quality` int(11) NOT NULL DEFAULT 0,
  `final_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `feedback` text DEFAULT NULL,
  `assessed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_industry_assessment_student_industri` (`student_id`,`industri_id`),
  KEY `fk_ind_assess_industri` (`industri_id`),
  CONSTRAINT `fk_ind_assess_industri` FOREIGN KEY (`industri_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ind_assess_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabel: sidang_assessments
CREATE TABLE IF NOT EXISTS `sidang_assessments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `penguji_id` int(11) NOT NULL,
  `score_understanding` int(11) NOT NULL DEFAULT 0,
  `score_presentation` int(11) NOT NULL DEFAULT 0,
  `score_qa` int(11) NOT NULL DEFAULT 0,
  `final_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `feedback` text DEFAULT NULL,
  `assessed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sidang_assessment_student_penguji` (`student_id`,`penguji_id`),
  KEY `fk_sidang_assess_penguji` (`penguji_id`),
  CONSTRAINT `fk_sidang_assess_penguji` FOREIGN KEY (`penguji_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sidang_assess_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabel: examiner_assignments
CREATE TABLE IF NOT EXISTS `examiner_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `penguji_id` int(11) NOT NULL,
  `category` enum('produktif','jurnal') NOT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_examiner_student_category` (`student_id`,`category`),
  KEY `fk_exa_penguji` (`penguji_id`),
  CONSTRAINT `fk_exa_penguji` FOREIGN KEY (`penguji_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exa_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabel: produktif_assessments
CREATE TABLE IF NOT EXISTS `produktif_assessments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `penguji_id` int(11) NOT NULL,
  `score_materi` int(11) NOT NULL DEFAULT 0,
  `score_teknis` int(11) NOT NULL DEFAULT 0,
  `score_hasil` int(11) NOT NULL DEFAULT 0,
  `score_masalah` int(11) NOT NULL DEFAULT 0,
  `score_proses` int(11) NOT NULL DEFAULT 0,
  `catatan` text DEFAULT NULL,
  `assessed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_produktif_student_penguji` (`student_id`,`penguji_id`),
  KEY `fk_pa_penguji` (`penguji_id`),
  CONSTRAINT `fk_pa_penguji` FOREIGN KEY (`penguji_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pa_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabel: jurnal_assessments
CREATE TABLE IF NOT EXISTS `jurnal_assessments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `penguji_id` int(11) NOT NULL,
  `score_kelengkapan` int(11) NOT NULL DEFAULT 0,
  `score_sesuai` int(11) NOT NULL DEFAULT 0,
  `score_pemahaman` int(11) NOT NULL DEFAULT 0,
  `score_jelaskan` int(11) NOT NULL DEFAULT 0,
  `score_komunikasi` int(11) NOT NULL DEFAULT 0,
  `catatan` text DEFAULT NULL,
  `assessed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jurnal_student_penguji` (`student_id`,`penguji_id`),
  KEY `fk_ja_penguji` (`penguji_id`),
  CONSTRAINT `fk_ja_penguji` FOREIGN KEY (`penguji_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ja_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;

