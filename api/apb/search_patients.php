<?php
/**
 * api/search_patients.php
 * AJAX endpoint: cari pasien by nama, NIK, atau telepon.
 * Dipanggil oleh queues/create.php dan halaman lain yang butuh patient picker.
 * Harus login, role admin/perawat/dokter.
 */

// Satu level lebih dalam dari root, path ke config berbeda
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

// Selalu return JSON
header('Content-Type: application/json; charset=utf-8');

// Harus login
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Hanya staf medis
if (!hasRole(['admin', 'perawat', 'dokter'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $db   = getDB();
    $like = '%' . $q . '%';

    $stmt = $db->prepare("
        SELECT
            id,
            nik,
            name,
            gender,
            birth_date,
            phone,
            insurance_type
        FROM patients
        WHERE is_active = 1
          AND (name LIKE ? OR nik LIKE ? OR phone LIKE ?)
        ORDER BY name ASC
        LIMIT 10
    ");
    $stmt->execute([$like, $like, $like]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tambah field 'age' agar JS tidak perlu hitung sendiri
    $result = array_map(function ($row) {
        $row['age']  = calculateAge($row['birth_date']);
        $row['name'] = htmlspecialchars_decode($row['name']); // kirim raw, JS yang escape
        return [
            'id'             => (int)$row['id'],
            'nik'            => $row['nik'],
            'name'           => $row['name'],
            'gender'         => $row['gender'],
            'birth_date'     => $row['birth_date'],
            'age'            => $row['age'],
            'phone'          => $row['phone'] ?? '',
            'insurance_type' => $row['insurance_type'] ?? 'Umum',
        ];
    }, $rows);

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
