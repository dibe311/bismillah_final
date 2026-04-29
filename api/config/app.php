<?php
ob_start();

define('APP_NAME', 'MediRek');
define('APP_VERSION', '1.0.0');
define('BASE_URL', rtrim(getenv('APP_URL') ? getenv('APP_URL') : '', '/'));
define('SESSION_TIMEOUT', 3600);
// Kunci enkripsi - ganti dengan string random yang panjang
define('COOKIE_SECRET', 'MediRek_S3cr3t_K3y_2024_XyZ!@#$%');
define('COOKIE_NAME', 'medirek_auth');

// ============================================================
// COOKIE-BASED AUTH (menggantikan PHP session untuk Vercel)
// PHP session tidak bekerja di Vercel serverless karena
// tidak ada shared filesystem. Cookie enkripsi adalah solusinya.
// ============================================================

function cookieEncrypt($data) {
    $json    = json_encode($data);
    $key     = hash('sha256', COOKIE_SECRET, true);
    $iv      = random_bytes(16);
    $enc     = openssl_encrypt($json, 'AES-256-CBC', $key, 0, $iv);
    $payload = base64_encode($iv . '::' . $enc);
    $sig     = hash_hmac('sha256', $payload, COOKIE_SECRET);
    return $payload . '.' . $sig;
}

function cookieDecrypt($token) {
    if (empty($token)) return null;
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) return null;
    list($payload, $sig) = $parts;
    // Verifikasi signature
    $expectedSig = hash_hmac('sha256', $payload, COOKIE_SECRET);
    if (!hash_equals($expectedSig, $sig)) return null;
    $decoded = base64_decode($payload);
    if (!$decoded) return null;
    $pieces = explode('::', $decoded, 2);
    if (count($pieces) !== 2) return null;
    list($iv, $enc) = $pieces;
    $key  = hash('sha256', COOKIE_SECRET, true);
    $json = openssl_decrypt($enc, 'AES-256-CBC', $key, 0, $iv);
    if (!$json) return null;
    $data = json_decode($json, true);
    // Cek expiry
    if (!$data || !isset($data['exp']) || $data['exp'] < time()) return null;
    return $data;
}

function setAuthCookie($userData) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $payload = $userData;
    $payload['exp'] = time() + SESSION_TIMEOUT;
    $token = cookieEncrypt($payload);
    setcookie(COOKIE_NAME, $token, [
        'expires'  => time() + SESSION_TIMEOUT,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    // Simpan juga di $_COOKIE agar langsung terbaca di request ini
    $_COOKIE[COOKIE_NAME] = $token;
}

function clearAuthCookie() {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    setcookie(COOKIE_NAME, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[COOKIE_NAME]);
}

// Flash message pakai cookie juga
define('FLASH_COOKIE', 'medirek_flash');

function flashMessage($type, $message) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $val = base64_encode(json_encode(['type' => $type, 'message' => $message]));
    setcookie(FLASH_COOKIE, $val, [
        'expires'  => time() + 60,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[FLASH_COOKIE] = $val;
}

function getFlash() {
    if (empty($_COOKIE[FLASH_COOKIE])) return null;
    $data = json_decode(base64_decode($_COOKIE[FLASH_COOKIE]), true);
    clearFlashCookie();
    return $data;
}

function clearFlashCookie() {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    setcookie(FLASH_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[FLASH_COOKIE]);
}

// ----- Auth helpers -----

function loginUser(array $data) {
    setAuthCookie([
        'id'    => (int)$data['id'],
        'name'  => $data['name'],
        'email' => $data['email'],
        'role'  => $data['role'],
    ]);
}

function logoutUser() {
    clearAuthCookie();
}

function currentUser() {
    if (empty($_COOKIE[COOKIE_NAME])) return null;
    $data = cookieDecrypt($_COOKIE[COOKIE_NAME]);
    if (!$data) return null;
    return [
        'id'    => $data['id'],
        'name'  => $data['name'],
        'email' => $data['email'],
        'role'  => $data['role'],
    ];
}

function isLoggedIn() {
    return currentUser() !== null;
}

function hasRole($roles) {
    $user = currentUser();
    if (!$user) return false;
    if (is_string($roles)) $roles = array($roles);
    return in_array($user['role'], $roles);
}

function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login');
        exit;
    }
}

function requireRole($roles) {
    requireAuth();
    if (!hasRole($roles)) {
        header('Location: ' . BASE_URL . '/dashboard?error=unauthorized');
        exit;
    }
}

function redirect($path) {
    header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
    exit;
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateQueueNumber($pdo, $date) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM queues WHERE queue_date = ?");
    $stmt->execute(array($date));
    $count = (int)$stmt->fetchColumn();
    return 'A' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
}

function calculateAge($birthDate) {
    return (int)(new DateTime($birthDate))->diff(new DateTime())->y;
}

function queueStatusLabel($status) {
    $labels = array(
        'waiting'     => 'Menunggu',
        'called'      => 'Dipanggil',
        'in_progress' => 'Diperiksa',
        'done'        => 'Selesai',
        'cancelled'   => 'Batal',
    );
    return isset($labels[$status]) ? $labels[$status] : $status;
}
