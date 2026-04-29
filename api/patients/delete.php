<?php
/**
 * patients/delete.php
 * Soft-delete pasien (is_active=0). Admin only.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
requireRole('admin');

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    flashMessage('error', 'ID pasien tidak valid.');
    redirect('patients');
}

$row = $db->prepare("SELECT name FROM patients WHERE id = ? AND is_active = 1");
$row->execute([$id]);
$row = $row->fetch();

if (!$row) {
    flashMessage('error', 'Pasien tidak ditemukan atau sudah dihapus.');
    redirect('patients');
}

// Soft-dlete tanpa updated_at (kolom tidak ada di tabel patients)
$db->prepare("UPDATE patients SET is_active = 0 WHERE id = ?")
   ->execute([$id]);

flashMessage('success', "Pasien \"{$row['name']}\" berhasil dihapus dari sistem.");
redirect('patients');