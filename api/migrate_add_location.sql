-- Migration: tambah kolom lokasi (provinsi & kabupaten/kota) ke tabel users
-- Jalankan sekali di database medirek sebelum menggunakan fitur registrasi baru.

ALTER TABLE `users`
  ADD COLUMN `province_code` VARCHAR(10)  NULL DEFAULT NULL COMMENT 'Kode domain provinsi dari BPS' AFTER `phone`,
  ADD COLUMN `province_name` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Nama provinsi dari BPS'        AFTER `province_code`,
  ADD COLUMN `city_code`     VARCHAR(10)  NULL DEFAULT NULL COMMENT 'Kode domain kab/kota dari BPS' AFTER `province_name`,
  ADD COLUMN `city_name`     VARCHAR(100) NULL DEFAULT NULL COMMENT 'Nama kab/kota dari BPS'        AFTER `city_code`;
