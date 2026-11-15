<?php
/**
 * NATOBIN INTERNATIONAL - Outbound & Delivery Order (Surat Jalan) API
 * Handles Goods Dispatch, DO Number Generation, Inventory Deduction,
 * and Status Tracking.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/code_generator.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDB();

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'suggest_code') {
        jsonResponse(true, ['code' => generateDeliveryCode($pdo)]);
    }

    if ($action === 'available_production') {
        // Return production batches or inventory lots ready for delivery
        $stmt = $pdo->query("
            SELECT 
                p.id AS production_id,
                p.kode_produksi,
                p.tanggal_produksi,
                p.jumlah_sak_hasil,
                p.berat_hasil_kg,
                p.target_bobot_sak_kg,
                p.kepemilikan_sak,
                p.storage_location,
                b.jenis_tembakau,
                c.id AS customer_id,
                c.nama_customer,
                COALESCE(SUM(oi.delivered_sak), 0) AS total_delivered_sak,
                COALESCE(SUM(oi.delivered_kg), 0) AS total_delivered_kg,
                (p.jumlah_sak_hasil - COALESCE(SUM(oi.delivered_sak), 0)) AS available_sak,
                (p.berat_hasil_kg - COALESCE(SUM(oi.delivered_kg), 0)) AS available_kg
            FROM proses_produksi p
            LEFT JOIN barang_masuk_lpb b ON p.lpb_id = b.id
            LEFT JOIN customers c ON b.customer_id = c.id
            LEFT JOIN outbound_items oi ON p.id = oi.production_id
            GROUP BY p.id
            HAVING available_sak > 0
            ORDER BY p.id DESC
        ");
        jsonResponse(true, $stmt->fetchAll(), 'Available production batches for outbound');
    }

    if ($action === 'detail') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("
            SELECT 
                d.*,
                c.nama_customer, c.kode_customer, c.telepon AS customer_telepon, c.alamat AS customer_alamat,
                u.nama_lengkap AS petugas_logistik_nama,
                p.kode_produksi, p.kepemilikan_sak
            FROM pengiriman d
            JOIN customers c ON d.customer_id = c.id
            LEFT JOIN users u ON d.petugas_logistik_id = u.id
            LEFT JOIN proses_produksi p ON d.produksi_id = p.id
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        $delivery = $stmt->fetch();
        if (!$delivery) {
            jsonResponse(false, null, 'Delivery Order not found', 404);
        }

        // Fetch itemized batches in this delivery
        $stmtItems = $pdo->prepare("
            SELECT 
                oi.id, oi.production_id, oi.delivered_sak, oi.delivered_kg,
                p.kode_produksi, p.target_bobot_sak_kg, p.kepemilikan_sak, p.storage_location,
                b.jenis_tembakau
            FROM outbound_items oi
            JOIN proses_produksi p ON oi.production_id = p.id
            LEFT JOIN barang_masuk_lpb b ON p.lpb_id = b.id
            WHERE oi.outbound_id = ?
        ");
        $stmtItems->execute([$id]);
        $delivery['items'] = $stmtItems->fetchAll();

        // Check if invoice already generated for this delivery
        $stmtInv = $pdo->prepare("SELECT id, no_invoice, total_tagihan, status_pembayaran FROM invoice_penagihan WHERE pengiriman_id = ?");
        $stmtInv->execute([$id]);
        $delivery['invoice'] = $stmtInv->fetch() ?: null;

        jsonResponse(true, $delivery);
    }

    // Default: list all delivery orders
    $stmt = $pdo->query("
        SELECT 
            d.id,
            d.no_surat_jalan,
            d.tanggal_kirim,
            d.customer_id,
            c.nama_customer,
            c.kode_customer,
            d.sumber_barang,
            d.produksi_id,
            p.kode_produksi,
            p.kepemilikan_sak,
            d.jumlah_sak,
            d.total_berat_kg,
            d.nama_sopir,
            d.plat_armada,
            d.tujuan_pengiriman,
            d.status_pengiriman,
            d.catatan,
            d.created_at,
            u.nama_lengkap AS petugas_logistik_nama,
            (SELECT id FROM invoice_penagihan inv WHERE inv.pengiriman_id = d.id LIMIT 1) AS invoice_id,
            (SELECT no_invoice FROM invoice_penagihan inv WHERE inv.pengiriman_id = d.id LIMIT 1) AS no_invoice,
            (SELECT status_pembayaran FROM invoice_penagihan inv WHERE inv.pengiriman_id = d.id LIMIT 1) AS status_invoice
        FROM pengiriman d
        JOIN customers c ON d.customer_id = c.id
        LEFT JOIN users u ON d.petugas_logistik_id = u.id
        LEFT JOIN proses_produksi p ON d.produksi_id = p.id
        ORDER BY d.id DESC
    ");
    $deliveries = $stmt->fetchAll();

    jsonResponse(true, $deliveries, 'Outbound deliveries retrieved');
}

if ($method === 'POST') {
    $json = file_get_contents('php://input');
    $input = json_decode($json, true) ?? $_POST;

    $doCode = !empty($input['no_surat_jalan']) ? trim((string)$input['no_surat_jalan']) : generateDeliveryCode($pdo);
    $tanggalKirim = !empty($input['tanggal_kirim']) ? $input['tanggal_kirim'] : date('Y-m-d');
    $customerId = (int)($input['customer_id'] ?? 0);
    $platArmada = trim((string)($input['plat_armada'] ?? ''));
    $namaSopir = trim((string)($input['nama_sopir'] ?? ''));
    $tujuan = trim((string)($input['tujuan_pengiriman'] ?? ''));
    $sumberBarang = in_array($input['sumber_barang'] ?? '', ['LANGSUNG_RAJANG', 'DARI_GUDANG']) ? $input['sumber_barang'] : 'DARI_GUDANG';
    $catatan = trim((string)($input['catatan'] ?? ''));
    $petugasId = (int)($input['petugas_logistik_id'] ?? 1);

    // Selected production batches array: [ { production_id: 1, delivered_sak: 60, delivered_kg: 1500.0 }, ... ]
    $items = $input['items'] ?? [];

    if ($customerId <= 0) {
        jsonResponse(false, null, 'Pilih Klien / Customer tujuan pengiriman', 400);
    }
    if (empty($platArmada) || empty($namaSopir)) {
        jsonResponse(false, null, 'Nomor Plat Kendaraan dan Nama Sopir wajib diisi', 400);
    }
    if (empty($tujuan)) {
        jsonResponse(false, null, 'Alamat / Tujuan Pengiriman wajib diisi', 400);
    }
    if (empty($items) || !is_array($items)) {
        jsonResponse(false, null, 'Minimal 1 Batch Tembakau Rajang harus dipilih untuk dikirim', 400);
    }

    $totalSak = 0;
    $totalKg = 0.0;
    foreach ($items as $itm) {
        $sak = (int)($itm['delivered_sak'] ?? 0);
        $kg = (float)($itm['delivered_kg'] ?? 0);
        if ($sak <= 0 || $kg <= 0) {
            jsonResponse(false, null, 'Jumlah sak dan KG per item harus lebih dari 0', 400);
        }
        $totalSak += $sak;
        $totalKg += $kg;
    }

    $firstProdId = (int)$items[0]['production_id'];

    try {
        $pdo->beginTransaction();

        // 1. Insert into pengiriman
        $stmtDo = $pdo->prepare("
            INSERT INTO pengiriman (
                no_surat_jalan, tanggal_kirim, customer_id, sumber_barang, 
                produksi_id, jumlah_sak, total_berat_kg, nama_sopir, 
                plat_armada, tujuan_pengiriman, petugas_logistik_id, 
                status_pengiriman, catatan
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                'SIAP_MUAT', ?
            )
        ");
        $stmtDo->execute([
            $doCode,
            $tanggalKirim,
            $customerId,
            $sumberBarang,
            $firstProdId,
            $totalSak,
            $totalKg,
            $namaSopir,
            $platArmada,
            $tujuan,
            $petugasId,
            $catatan
        ]);

        $outboundId = (int)$pdo->lastInsertId();

        // 2. Insert into outbound_items & deduct from inventory_gudang
        $stmtOi = $pdo->prepare("
            INSERT INTO outbound_items (outbound_id, production_id, delivered_sak, delivered_kg)
            VALUES (?, ?, ?, ?)
        ");

        $stmtDeductInv = $pdo->prepare("
            UPDATE inventory_gudang 
            SET 
                jumlah_sak_tersisa = GREATEST(0, jumlah_sak_tersisa - ?),
                berat_tersisa_kg   = GREATEST(0, berat_tersisa_kg - ?),
                status_stok = CASE 
                    WHEN (jumlah_sak_tersisa - ?) <= 0 THEN 'HABIS' 
                    ELSE 'SEBAGIAN_TERKIRIM' 
                END
            WHERE produksi_id = ? AND status_stok != 'HABIS'
            ORDER BY id ASC
            LIMIT 1
        ");

        foreach ($items as $itm) {
            $pId = (int)$itm['production_id'];
            $sak = (int)$itm['delivered_sak'];
            $kg = (float)$itm['delivered_kg'];

            $stmtOi->execute([$outboundId, $pId, $sak, $kg]);

            // Deduct from warehouse inventory
            $stmtDeductInv->execute([$sak, $kg, $sak, $pId]);
        }

        // 3. Audit Log
        logAudit($petugasId, 'CREATE', 'DELIVERY_ORDER', $outboundId, [
            'no_surat_jalan' => $doCode,
            'customer_id'    => $customerId,
            'total_sak'      => $totalSak,
            'total_berat_kg' => $totalKg,
            'plat_armada'    => $platArmada,
            'nama_sopir'     => $namaSopir
        ], "Pembuatan Surat Jalan {$doCode} kepada {$tujuan} ({$totalSak} Sak / {$totalKg} KG)");

        $pdo->commit();

        jsonResponse(true, [
            'id'             => $outboundId,
            'no_surat_jalan' => $doCode,
            'total_sak'      => $totalSak,
            'total_berat_kg' => $totalKg
        ], "Surat Jalan {$doCode} berhasil diterbitkan", 201);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(false, null, 'Gagal membuat Surat Jalan: ' . $e->getMessage(), 500);
    }
}

if ($method === 'PUT') {
    // Update delivery status (e.g. SIAP_MUAT -> DALAM_PERJALANAN -> DITERIMA)
    $json = file_get_contents('php://input');
    $input = json_decode($json, true);

    $id = (int)($input['id'] ?? 0);
    $status = strtoupper((string)($input['status_pengiriman'] ?? ''));
    $validStatuses = ['SIAP_MUAT', 'DALAM_PERJALANAN', 'DITERIMA'];

    if (!in_array($status, $validStatuses)) {
        jsonResponse(false, null, 'Status pengiriman tidak valid', 400);
    }

    try {
        $stmt = $pdo->prepare("UPDATE pengiriman SET status_pengiriman = ? WHERE id = ?");
        $stmt->execute([$status, $id]);

        logAudit(1, 'UPDATE', 'DELIVERY_ORDER', $id, ['status' => $status], "Update status pengiriman DO ID {$id} menjadi {$status}");

        jsonResponse(true, null, "Status Surat Jalan berhasil diubah menjadi {$status}");
    } catch (Exception $e) {
        jsonResponse(false, null, 'Gagal update status: ' . $e->getMessage(), 500);
    }
}
