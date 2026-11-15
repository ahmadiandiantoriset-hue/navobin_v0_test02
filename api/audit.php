<?php
/**
 * NATOBIN INTERNATIONAL - Audit Trail API
 * Provides complete traceable activity logs of system transactions.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    $pdo = getDB();

    $limit = (int)($_GET['limit'] ?? 50);
    $entity = trim((string)($_GET['entity'] ?? ''));
    $action = trim((string)($_GET['action'] ?? ''));

    $sql = "SELECT * FROM log_audit_akses WHERE 1=1";
    $params = [];

    if (!empty($entity)) {
        $sql .= " AND entity = ?";
        $params[] = $entity;
    }
    if (!empty($action)) {
        $sql .= " AND aksi = ?";
        $params[] = $action;
    }

    $sql .= " ORDER BY id DESC LIMIT " . max(1, min($limit, 200));

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    jsonResponse(true, $logs, 'Audit logs retrieved');
} catch (Exception $e) {
    jsonResponse(false, null, 'Error: ' . $e->getMessage(), 500);
}
