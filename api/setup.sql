-- ============================================================
-- MediRek — Full Database Setup
-- Jalankan sekali untuk inisialisasi database
-- ============================================================

CREATE DATABASE IF NOT EXISTS medirek CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE medirek;

-- ── USERS ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(150) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    phone         VARCHAR(20)  DEFAULT NULL,
    role          ENUM('admin','dokter','perawat','pasien') NOT NULL DEFAULT 'pasien',
    province_code VARCHAR(10)  DEFAULT NULL,
    province_name VARCHAR(100) DEFAULT NULL,
    city_code     VARCHAR(10)  DEFAULT NULL,
    city_name     VARCHAR(100) DEFAULT NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    last_login    DATETIME     DEFAULT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── DOCTOR PROFILES ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS doctor_profiles (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL UNIQUE,
    specialization  VARCHAR(150) DEFAULT 'Dokter Umum',
    license_number  VARCHAR(50)  DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── PATIENTS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS patients (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED DEFAULT NULL,
    nik              VARCHAR(16)  NOT NULL UNIQUE,
    name             VARCHAR(150) NOT NULL,
    gender           ENUM('L','P') NOT NULL,
    birth_date       DATE         NOT NULL,
    blood_type       ENUM('A','B','AB','O','unknown') NOT NULL DEFAULT 'unknown',
    address          TEXT         DEFAULT NULL,
    phone            VARCHAR(20)  DEFAULT NULL,
    email            VARCHAR(150) DEFAULT NULL,
    insurance_number VARCHAR(50)  DEFAULT NULL,
    insurance_type   VARCHAR(50)  NOT NULL DEFAULT 'Umum',
    allergy          TEXT         DEFAULT NULL,
    is_active        TINYINT(1)   NOT NULL DEFAULT 1,
    created_by       INT UNSIGNED DEFAULT NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── QUEUES ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS queues (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue_number  VARCHAR(10)  NOT NULL,
    patient_id    INT UNSIGNED NOT NULL,
    doctor_id     INT UNSIGNED DEFAULT NULL,
    queue_date    DATE         NOT NULL,
    status        ENUM('waiting','called','in_progress','done','cancelled') NOT NULL DEFAULT 'waiting',
    notes         TEXT         DEFAULT NULL,
    created_by    INT UNSIGNED DEFAULT NULL,
    called_at     DATETIME     DEFAULT NULL,
    done_at       DATETIME     DEFAULT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id)  REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── INITIAL CHECKS ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS initial_checks (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue_id           INT UNSIGNED NOT NULL UNIQUE,
    patient_id         INT UNSIGNED NOT NULL,
    nurse_id           INT UNSIGNED DEFAULT NULL,
    blood_pressure     VARCHAR(10)  DEFAULT NULL,
    temperature        DECIMAL(4,1) DEFAULT NULL,
    pulse              SMALLINT     DEFAULT NULL,
    oxygen_saturation  TINYINT      DEFAULT NULL,
    weight             DECIMAL(5,1) DEFAULT NULL,
    height             DECIMAL(5,1) DEFAULT NULL,
    chief_complaint    TEXT         NOT NULL,
    notes              TEXT         DEFAULT NULL,
    checked_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (queue_id)   REFERENCES queues(id)   ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (nurse_id)   REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── MEDICAL RECORDS ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS medical_records (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue_id         INT UNSIGNED DEFAULT NULL,
    patient_id       INT UNSIGNED NOT NULL,
    doctor_id        INT UNSIGNED NOT NULL,
    visit_date       DATE         NOT NULL,
    chief_complaint  TEXT         NOT NULL,
    objective_notes  TEXT         DEFAULT NULL,
    diagnosis        TEXT         NOT NULL,
    icd_code         VARCHAR(15)  DEFAULT NULL,
    treatment        TEXT         DEFAULT NULL,
    prescription     TEXT         DEFAULT NULL,
    lab_notes        TEXT         DEFAULT NULL,
    follow_up_date   DATE         DEFAULT NULL,
    follow_up_notes  TEXT         DEFAULT NULL,
    is_referred      TINYINT(1)   NOT NULL DEFAULT 0,
    referral_notes   TEXT         DEFAULT NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (queue_id)   REFERENCES queues(id)   ON DELETE SET NULL,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id)  REFERENCES users(id)    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── VIEW: v_indikator_landing (BPS data fallback) ─────────────
CREATE OR REPLACE VIEW v_indikator_landing AS
SELECT 'KELUHAN_TOTAL' AS kode, 28.75 AS nilai
UNION ALL SELECT 'OBATI_SENDIRI', 79.93
UNION ALL SELECT 'RAWAT_JALAN',   39.36
UNION ALL SELECT 'JKN_PESERTA',   77.30;

-- ── TREN KELUHAN table (BPS data) ────────────────────────────
CREATE TABLE IF NOT EXISTS tren_keluhan (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun       YEAR        NOT NULL,
    tipe_daerah VARCHAR(20) NOT NULL,
    persentase  DECIMAL(5,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO tren_keluhan (tahun, tipe_daerah, persentase) VALUES
(2022,'Perkotaan',27.32),(2023,'Perkotaan',27.30),(2024,'Perkotaan',26.85),
(2022,'Perdesaan',35.58),(2023,'Perdesaan',27.31),(2024,'Perdesaan',30.61),
(2022,'Total',31.45),(2023,'Total',27.30),(2024,'Total',28.75);

-- ── FASKES DIGUNAKAN table (BPS data) ────────────────────────
CREATE TABLE IF NOT EXISTS faskes_digunakan (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun       YEAR        NOT NULL,
    nama_faskes VARCHAR(100) NOT NULL,
    total       DECIMAL(5,2) NOT NULL,
    urutan      TINYINT     NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO faskes_digunakan (tahun, nama_faskes, total, urutan) VALUES
(2024,'Praktik Dokter',47.78,1),
(2024,'Puskesmas',20.11,2),
(2024,'Klinik',12.77,3),
(2024,'RS Pemerintah',11.25,4),
(2024,'RS Swasta',8.64,5);

-- ── SEED: Demo Users (password = 'password') ─────────────────
-- Hash bcrypt untuk 'password'
INSERT IGNORE INTO users (name, email, password, role, is_active) VALUES
('Administrator',  'admin@medirek.id',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',   1),
('dr. Budi Santoso','dokter@medirek.id', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dokter',  1),
('Sari Perawat',   'perawat@medirek.id', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'perawat', 1),
('Ahmad Pasien',   'pasien@medirek.id',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pasien',  1);

-- Add doctor profile for demo doctor
INSERT IGNORE INTO doctor_profiles (user_id, specialization)
SELECT id, 'Dokter Umum' FROM users WHERE email = 'dokter@medirek.id';

-- ── SEED: Demo Patient linked to pasien user ─────────────────
INSERT IGNORE INTO patients (user_id, nik, name, gender, birth_date, blood_type, address, phone, insurance_type, created_by)
SELECT 
    u.id,
    '3578010101900001',
    'Ahmad Pasien',
    'L',
    '1990-01-01',
    'A',
    'Jl. Madiun No. 1, Caruban',
    '081234567890',
    'BPJS',
    (SELECT id FROM users WHERE role='admin' LIMIT 1)
FROM users u WHERE u.email = 'pasien@medirek.id';

