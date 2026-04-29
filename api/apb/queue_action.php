<?php
/**
 * api/queue_action.php
 * AJAX endpoint: update status antrian (panggil, mulai, done, batal).
 * Dipanggil dari dashboard dan queues/index.php via JavaScript.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Hanya method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Baca JSON body ATAU form-data
$input    = json_decode(file_get_contents('php://input'), true);
$queueId  = (int)($input['queue_id']  ?? $_POST['queue_id']  ?? 0);
$action   = trim($input['action']     ?? $_POST['action']     ?? '');

if (!$queueId || !$action) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap.']);
    exit;
}

// Peta transisi status yang valid per role
$transitions = [
    // action      => [status_sekarang_valid, status_baru, role_yang_boleh]
    'called'      => [['waiting'],                 'called',      ['admin','perawat']],
    'in_progress' => [['called'],                  'in_progress', ['admin','perawat','dokter']],
    'done'        => [['in_progress','called'],     'done',        ['admin','dokter']],
    'cancelled'   => [['waiting','called'],         'cancelled',   ['admin','perawat']],
];

if (!isset($transitions[$action])) {
    echo json_encode(['success' => false, 'message' => 'Aksi tidak valid.']);
    exit;
}

[$validFromStatuses, $newStatus, $allowedRoles] = $transitions[$action];

if (!hasRole($allowedRoles)) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak berhak melakukan aksi ini.']);
    exit;
}

try {
    $db = getDB();

    // Ambil antrian
    $stmt = $db->prepare("SELECT id, status, doctor_id, patient_id FROM queues WHERE id = ?");
    $stmt->execute([$queueId]);
    $queue = $stmt->fetch();

    if (!$queue) {
        echo json_encode(['success' => false, 'message' => 'Antrian tidak ditemukan.']);
        exit;
    }

    if (!in_array($queue['status'], $validFromStatuses, true)) {
        echo json_encode([
            'success' => false,
            'message' => "Status antrian saat ini ({$queue['status']}) tidak bisa diubah ke {$newStatus}."
        ]);
        exit;
    }

    // Dokter hanya bisa mengubah antrian miliknya
    if (hasRole('dokter') && (int)$queue['doctor_id'] !== (int)currentUser()['id']) {
        echo json_encode(['success' => false, 'message' => 'Antrian ini tidak ditugaskan ke Anda.']);
        exit;
    }

    // Build UPDATE — set timestamp yang relevan
    if ($action === 'called') {
        $timestampField = ', called_at = NOW()';
    } elseif ($action === 'done') {
        $timestampField = ', done_at = NOW()';
    } else {
        $timestampField = '';
    }

    $db->prepare("
        UPDATE queues
        SET status = ?, updated_at = NOW() $timestampField
        WHERE id = ?
    ")->execute([$newStatus, $queueId]);

    echo json_encode([
        'success'    => true,
        'message'    => 'Status antrian berhasil diperbarui.',
        'new_status' => $newStatus,
        'queue_id'   => $queueId,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server.']);
}
