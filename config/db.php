<?php
/**
 * NATOBIN INTERNATIONAL - Tobacco Processing WMS
 * Database Connection & Core Utilities
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'natobin_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error'   => 'Database connection failed: ' . $e->getMessage()
            ]);
            exit;
        }
    }
    return $pdo;
}

/**
 * Record Audit Log into log_audit_akses
 */
function logAudit(
    int $userId,
    string $action,
    string $entity,
    ?int $entityId = null,
    mixed $payload = null,
    string $detail = ''
): void {
    try {
        $pdo = getDB();
        
        // Fetch user name and role
        $stmtUser = $pdo->prepare("SELECT nama_lengkap, role FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch();
        $userName = $user ? $user['nama_lengkap'] : 'System / Operator';
        $userRole = $user ? $user['role'] : 'admin';

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI / Internal';
        $payloadStr = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE);

        $stmt = $pdo->prepare("
            INSERT INTO log_audit_akses 
            (user_id, nama_user, role, waktu, ip_address, aksi, entity, entity_id, payload, keterangan_detail, user_agent)
            VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $userName,
            $userRole,
            $ip,
            $action,
            $entity,
            $entityId,
            $payloadStr,
            $detail ?: "Action {$action} on {$entity}" . ($entityId ? " ID: {$entityId}" : ""),
            substr($userAgent, 0, 250)
        ]);
    } catch (Exception $e) {
        // Fallback write to error log to avoid breaking parent transaction
        error_log("Audit log failed: " . $e->getMessage());
    }
}

/**
 * Standard JSON Response Helper
 */
function jsonResponse(bool $success, mixed $data = null, string $message = '', int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success'   => $success,
        'message'   => $message,
        'data'      => $data,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Active Session User Helper
 */
function getCurrentUser(): array {
    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        return $_SESSION['user'];
    }
    // Default fallback to Admin (id: 1)
    return [
        'id'           => 1,
        'username'     => 'admin',
        'nama_lengkap' => 'Administrator Utama',
        'role'         => 'admin',
        'email'        => 'admin@natobin.com'
    ];
}
