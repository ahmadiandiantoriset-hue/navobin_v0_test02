<?php
/**
 * NATOBIN INTERNATIONAL - Inventory & Bin Location API
 * Manages warehouse binning, rack capacity, lot tracking, and stock status.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDB();

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'racks_overview') {
        // Racks with aggregated stock
        $stmtRacks = $pdo->query("
            SELECT 
                r.id,
                r.kode_rak,
                r.nama_blok,
                r.kapasitas_sak,
                r.keterangan,
                COALESCE(SUM(i.jumlah_sak_tersisa), 0) AS total_sak_tersisa,
                COALESCE(SUM(i.berat_tersisa_kg), 0) AS total_kg_tersisa,
                COUNT(DISTINCT CASE WHEN i.status_stok != 'HABIS' THEN i.id END) AS active_lots_count
            FROM master_rak r
            LEFT JOIN inventory_gudang i ON r.kode_rak = i.kode_rak AND i.status_stok != 'HABIS'
            GROUP BY r.id, r.kode_rak, r.nama_blok, r.kapasitas_sak, r.keterangan
            ORDER BY r.kode_rak ASC
        ");
        $racks = $stmtRacks->fetchAll();

        // Stock by Tobacco Type
        $stmtTypes = $pdo->query("
            SELECT 
                COALESCE(b.jenis_tembakau, 'Campur') AS jenis_tembakau,
                SUM(i.jumlah_sak_tersisa) AS total_sak,
                SUM(i.berat_tersisa_kg) AS total_kg
            FROM inventory_gudang i
            JOIN proses_produksi p ON i.produksi_id = p.id
            LEFT JOIN barang_masuk_lpb b ON p.lpb_id = b.id
            WHERE i.status_stok != 'HABIS'
            GROUP BY b.jenis_tembakau
        ");
        $stockByTobacco = $stmtTypes->fetchAll();

        jsonResponse(true, [
            'racks'            => $racks,
            'stock_by_tobacco' => $stockByTobacco
        ]);
    }

    // Default: list all inventory lots
    $stmt = $pdo->query("
        SELECT 
            i.id,
            i.kode_rak,
            i.produksi_id,
            p.kode_produksi,
            p.tanggal_produksi,
            p.shift_kerja,
            p.kepemilikan_sak,
            p.storage_location,
            i.tanggal_masuk_gudang,
            i.jumlah_sak_masuk,
            i.jumlah_sak_tersisa,
            i.berat_per_sak_kg,
            i.berat_tersisa_kg,
            i.status_stok,
            i.catatan,
            c.nama_customer,
            b.jenis_tembakau,
            u.nama_lengkap AS petugas_gudang_nama
        FROM inventory_gudang i
        JOIN proses_produksi p ON i.produksi_id = p.id
        LEFT JOIN barang_masuk_lpb b ON p.lpb_id = b.id
        LEFT JOIN customers c ON b.customer_id = c.id
        LEFT JOIN users u ON i.petugas_gudang_id = u.id
        ORDER BY i.status_stok ASC, i.id DESC
    ");
    $lots = $stmt->fetchAll();

    jsonResponse(true, $lots, 'Inventory warehouse lots retrieved');
}

if ($method === 'POST') {
    // Relocate lot or update rack
    $json = file_get_contents('php://input');
    $input = json_decode($json, true) ?? $_POST;

    $lotId = (int)($input['lot_id'] ?? 0);
    $newRackCode = trim((string)($input['kode_rak'] ?? ''));
    $newLocationStr = trim((string)($input['storage_location'] ?? ''));
    $catatan = trim((string)($input['catatan'] ?? ''));
    $userId = (int)($input['user_id'] ?? 1);

    if ($lotId <= 0 || empty($newRackCode)) {
        jsonResponse(false, null, 'Lot ID dan Kode Rak baru harus diisi', 400);
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE inventory_gudang 
            SET kode_rak = ?, catatan = CONCAT(COALESCE(catatan, ''), ' | Dipindah ke: ', ?)
            WHERE id = ?
        ");
        $stmt->execute([$newRackCode, $newLocationStr ?: $newRackCode, $lotId]);

        logAudit($userId, 'UPDATE', 'INVENTORY_GUDANG', $lotId, [
            'new_rack'     => $newRackCode,
            'new_location' => $newLocationStr
        ], "Pindah lokasi bin lot {$lotId} ke rak {$newRackCode}");

        jsonResponse(true, null, 'Lokasi bin lot gudang berhasil diperbarui');
    } catch (Exception $e) {
        jsonResponse(false, null, 'Gagal update lokasi bin: ' . $e->getMessage(), 500);
    }
}
