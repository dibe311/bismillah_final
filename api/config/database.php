<?php
function getDB() {
    // Support both hardcoded values AND Vercel environment variables
    $host     = getenv('TIDB_HOST')     ?: 'gateway01.ap-southeast-1.prod.alicloud.tidbcloud.com';
    $port     = getenv('TIDB_PORT')     ?: '4000';
    $dbname   = getenv('TIDB_DB')       ?: 'medirek';
    $username = getenv('TIDB_USER')     ?: '3WBVxzrG9xZBsBC.root';
    $password = getenv('TIDB_PASSWORD') ?: 'n4RcjqVuNQNiRPcv';
    $useSSL   = getenv('TIDB_SSL') !== 'false';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    $options = array(
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    );

    // Aktifkan SSL hanya jika tersedia CA certificate
    if ($useSSL) {
        $caPaths = [
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/ssl/cert.pem',
            '/etc/pki/tls/certs/ca-bundle.crt',
        ];
        foreach ($caPaths as $ca) {
            if (file_exists($ca)) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                break;
            }
        }
    }

    try {
        return new PDO($dsn, $username, $password, $options);
    } catch (PDOException $e) {
        error_log("DB Connection Error: " . $e->getMessage());
        http_response_code(500);
        die(json_encode(['error' => 'Koneksi database gagal. Silakan coba beberapa saat lagi.']));
    }
}
