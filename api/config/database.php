<?php
function getDB() {
    static $pdo = null;
    if ($pdo !== null) return $pdo; // reuse koneksi dalam satu request

    $host     = getenv('TIDB_HOST')     ?: 'gateway01.ap-southeast-1.prod.alicloud.tidbcloud.com';
    $port     = getenv('TIDB_PORT')     ?: '4000';
    $dbname   = getenv('TIDB_DB')       ?: 'medirek';
    $username = getenv('TIDB_USER')     ?: '3WBVxzrG9xZBsBC.root';
    $password = getenv('TIDB_PASSWORD') ?: '2DO3eBBAAcxqmm37';
    $useSSL   = getenv('TIDB_SSL') !== 'false';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 10,
    ];

    if ($useSSL) {
        $caPaths = [
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/ssl/cert.pem',
            '/etc/pki/tls/certs/ca-bundle.crt',
        ];
        foreach ($caPaths as $ca) {
            if (file_exists($ca)) {
                $options[PDO::MYSQL_ATTR_SSL_CA]                 = $ca;
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                break;
            }
        }
    }

    try {
        $pdo = new PDO($dsn, $username, $password, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("DB Connection Error: " . $e->getMessage());

        // Cek apakah request ini mengharapkan JSON (AJAX) atau HTML (browser)
        $wantsJson = (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
            (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        );

        if ($wantsJson) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Koneksi database gagal. Silakan coba beberapa saat lagi.']);
        } else {
            http_response_code(500);
            echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
            <title>Koneksi Gagal</title>
            <style>
                body{font-family:sans-serif;background:#F0F4F8;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
                .box{background:#fff;border:1px solid #E0E0E0;border-radius:8px;padding:32px 40px;max-width:480px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.08)}
                h2{color:#C62828;margin:0 0 12px}p{color:#616161;font-size:14px;line-height:1.6}
                code{background:#F5F5F5;padding:2px 6px;border-radius:4px;font-size:12px;color:#424242;word-break:break-all}
                .btn{display:inline-block;margin-top:20px;padding:8px 20px;background:#0F2744;color:#fff;border-radius:6px;text-decoration:none;font-size:13px}
            </style></head><body>
            <div class="box">
                <h2>&#9888; Database Tidak Terhubung</h2>
                <p>Sistem tidak dapat terhubung ke database.<br>
                Periksa konfigurasi environment variable atau coba beberapa saat lagi.</p>
                <p><code>' . htmlspecialchars($e->getMessage()) . '</code></p>
                <a class="btn" href="javascript:history.back()">&larr; Kembali</a>
            </div></body></html>';
        }
        exit;
    }
}