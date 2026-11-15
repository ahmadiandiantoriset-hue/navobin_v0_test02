<?php
/**
 * NATOBIN INTERNATIONAL
 * Automated Code Number Generator (IN-, PRD-, DO-, INV-)
 * Format: PREFIX-YYMMDD-XXX
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/**
 * Generate Unique Inbound Code: IN-YYMMDD-XXX
 */
function generateInboundCode(?PDO $pdo = null): string {
    $pdo = $pdo ?? getDB();
    $prefix = 'IN-' . date('ymd') . '-';
    
    $stmt = $pdo->prepare("
        SELECT no_badge 
        FROM barang_masuk_lpb 
        WHERE no_badge LIKE ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);
    $lastCode = $stmt->fetchColumn();

    if ($lastCode && preg_match('/-(\d+)$/', (string)$lastCode, $matches)) {
        $nextSeq = (int)$matches[1] + 1;
    } else {
        $nextSeq = 1;
    }

    return $prefix . sprintf('%03d', $nextSeq);
}

/**
 * Generate Unique Production Batch Code: PRD-YYMMDD-XXX
 */
function generateProductionCode(?PDO $pdo = null): string {
    $pdo = $pdo ?? getDB();
    $prefix = 'PRD-' . date('ymd') . '-';

    $stmt = $pdo->prepare("
        SELECT kode_produksi 
        FROM proses_produksi 
        WHERE kode_produksi LIKE ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);
    $lastCode = $stmt->fetchColumn();

    if ($lastCode && preg_match('/-(\d+)$/', (string)$lastCode, $matches)) {
        $nextSeq = (int)$matches[1] + 1;
    } else {
        $nextSeq = 1;
    }

    return $prefix . sprintf('%03d', $nextSeq);
}

/**
 * Generate Unique Delivery Order (Surat Jalan) Code: DO-YYMMDD-XXX
 */
function generateDeliveryCode(?PDO $pdo = null): string {
    $pdo = $pdo ?? getDB();
    $prefix = 'DO-' . date('ymd') . '-';

    $stmt = $pdo->prepare("
        SELECT no_surat_jalan 
        FROM pengiriman 
        WHERE no_surat_jalan LIKE ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);
    $lastCode = $stmt->fetchColumn();

    if ($lastCode && preg_match('/-(\d+)$/', (string)$lastCode, $matches)) {
        $nextSeq = (int)$matches[1] + 1;
    } else {
        $nextSeq = 1;
    }

    return $prefix . sprintf('%03d', $nextSeq);
}

/**
 * Generate Unique Invoice Code: INV-YYMMDD-XXX
 */
function generateInvoiceCode(?PDO $pdo = null): string {
    $pdo = $pdo ?? getDB();
    $prefix = 'INV-' . date('ymd') . '-';

    $stmt = $pdo->prepare("
        SELECT no_invoice 
        FROM invoice_penagihan 
        WHERE no_invoice LIKE ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);
    $lastCode = $stmt->fetchColumn();

    if ($lastCode && preg_match('/-(\d+)$/', (string)$lastCode, $matches)) {
        $nextSeq = (int)$matches[1] + 1;
    } else {
        $nextSeq = 1;
    }

    return $prefix . sprintf('%03d', $nextSeq);
}
