<?php
/**
 * NATOBIN INTERNATIONAL - Invoice & Billing API
 * Handles commercial invoicing linked to processed goods and Delivery Orders,
 * calculating shredding fees (per KG/Sak) and sack ownership charges.
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
        jsonResponse(true, ['code' => generateInvoiceCode($pdo)]);
    }

    if ($action === 'detail') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("
            SELECT 
                inv.*,
                c.nama_customer, c.kode_customer, c.telepon AS customer_telepon, c.alamat AS customer_alamat,
                d.no_surat_jalan, d.tanggal_kirim, d.nama_sopir, d.plat_armada, d.tujuan_pengiriman,
                d.jumlah_sak, d.total_berat_kg,
                p.kode_produksi, p.kepemilikan_sak,
                u.nama_lengkap AS petugas_keuangan_nama
            FROM invoice_penagihan inv
            JOIN customers c ON inv.customer_id = c.id
            JOIN pengiriman d ON inv.pengiriman_id = d.id
            LEFT JOIN proses_produksi p ON d.produksi_id = p.id
            LEFT JOIN users u ON inv.petugas_keuangan_id = u.id
            WHERE inv.id = ?
        ");
        $stmt->execute([$id]);
        $inv = $stmt->fetch();
        if (!$inv) {
            jsonResponse(false, null, 'Invoice tidak ditemukan', 404);
        }
        jsonResponse(true, $inv);
    }

    // Default: list all invoices
    $stmt = $pdo->query("
        SELECT 
            inv.id,
            inv.no_invoice,
            inv.pengiriman_id,
            d.no_surat_jalan,
            d.tanggal_kirim,
            d.jumlah_sak,
            d.total_berat_kg,
            p.kode_produksi,
            p.kepemilikan_sak,
            inv.customer_id,
            c.nama_customer,
            inv.tanggal_invoice,
            inv.jatuh_tempo,
            inv.metode_tarif,
            inv.tarif_satuan,
            inv.biaya_bongkar_muat,
            inv.biaya_lain,
            inv.subtotal,
            inv.pajak_ppn,
            inv.total_tagihan,
            inv.status_pembayaran,
            inv.tanggal_lunas,
            inv.metode_bayar,
            u.nama_lengkap AS petugas_keuangan_nama
        FROM invoice_penagihan inv
        JOIN customers c ON inv.customer_id = c.id
        JOIN pengiriman d ON inv.pengiriman_id = d.id
        LEFT JOIN proses_produksi p ON d.produksi_id = p.id
        LEFT JOIN users u ON inv.petugas_keuangan_id = u.id
        ORDER BY inv.id DESC
    ");
    $invoices = $stmt->fetchAll();

    jsonResponse(true, $invoices, 'Invoices retrieved successfully');
}

if ($method === 'POST') {
    $json = file_get_contents('php://input');
    $input = json_decode($json, true) ?? $_POST;

    $pengirimanId = (int)($input['pengiriman_id'] ?? 0);
    $tanggalInvoice = !empty($input['tanggal_invoice']) ? $input['tanggal_invoice'] : date('Y-m-d');
    $jatuhTempo = !empty($input['jatuh_tempo']) ? $input['jatuh_tempo'] : date('Y-m-d', strtotime('+14 days'));
    $metodeTarif = in_array(strtoupper((string)($input['metode_tarif'] ?? '')), ['PER_KG', 'PER_SAK']) ? strtoupper((string)$input['metode_tarif']) : 'PER_KG';
    $tarifSatuan = (float)($input['tarif_satuan'] ?? 3500.0);
    $tarifSakSendiri = (float)($input['tarif_sak_sendiri'] ?? 15000.0); // charge for factory-provided sacks
    $biayaBongkarMuat = (float)($input['biaya_bongkar_muat'] ?? 0.0);
    $biayaLain = (float)($input['biaya_lain'] ?? 0.0);
    $includePpn = !empty($input['include_ppn']);
    $catatan = trim((string)($input['catatan'] ?? ''));
    $petugasId = (int)($input['petugas_keuangan_id'] ?? 1);

    if ($pengirimanId <= 0) {
        jsonResponse(false, null, 'Pilih Pengiriman / Surat Jalan yang akan ditagihkan', 400);
    }

    // Fetch delivery order details
    $stmtDo = $pdo->prepare("
        SELECT d.*, p.kepemilikan_sak 
        FROM pengiriman d
        LEFT JOIN proses_produksi p ON d.produksi_id = p.id
        WHERE d.id = ?
    ");
    $stmtDo->execute([$pengirimanId]);
    $doData = $stmtDo->fetch();

    if (!$doData) {
        jsonResponse(false, null, 'Data Surat Jalan tidak ditemukan', 404);
    }

    $customerId = (int)$doData['customer_id'];
    $totalKg = (float)$doData['total_berat_kg'];
    $totalSak = (int)$doData['jumlah_sak'];
    $sackOwnership = strtoupper((string)$doData['kepemilikan_sak']);

    // Calculate jasa rajang
    if ($metodeTarif === 'PER_KG') {
        $biayaJasa = $totalKg * $tarifSatuan;
    } else {
        $biayaJasa = $totalSak * $tarifSatuan;
    }

    // Add sack fee if ownership is MILIK SENDIRI
    $biayaKemasanSak = 0.0;
    if ($sackOwnership === 'MILIK SENDIRI') {
        $biayaKemasanSak = $totalSak * $tarifSakSendiri;
    }

    $subtotal = $biayaJasa + $biayaKemasanSak + $biayaBongkarMuat + $biayaLain;
    $ppn = $includePpn ? round($subtotal * 0.11, 2) : 0.0;
    $totalTagihan = $subtotal + $ppn;

    $invCode = !empty($input['no_invoice']) ? trim((string)$input['no_invoice']) : generateInvoiceCode($pdo);

    try {
        $pdo->beginTransaction();

        $stmtInsert = $pdo->prepare("
            INSERT INTO invoice_penagihan (
                no_invoice, pengiriman_id, customer_id, tanggal_invoice, jatuh_tempo,
                metode_tarif, tarif_satuan, biaya_bongkar_muat, biaya_lain, subtotal,
                pajak_ppn, total_tagihan, status_pembayaran, petugas_keuangan_id, catatan
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, 'BELUM TERTAGIH', ?, ?
            )
        ");
        $stmtInsert->execute([
            $invCode,
            $pengirimanId,
            $customerId,
            $tanggalInvoice,
            $jatuhTempo,
            $metodeTarif,
            $tarifSatuan,
            $biayaBongkarMuat,
            $biayaLain + $biayaKemasanSak, // bundle sack cost or itemize
            $subtotal,
            $ppn,
            $totalTagihan,
            $petugasId,
            $catatan ?: "Tagihan Jasa Rajang ({$sackOwnership}) - {$totalSak} Sak / {$totalKg} KG"
        ]);

        $invoiceId = (int)$pdo->lastInsertId();

        // Audit Log
        logAudit($petugasId, 'CREATE', 'INVOICE', $invoiceId, [
            'no_invoice'    => $invCode,
            'pengiriman_id' => $pengirimanId,
            'total_tagihan' => $totalTagihan,
            'subtotal'      => $subtotal
        ], "Terbit Faktur Tagihan {$invCode} sebesar Rp " . number_format($totalTagihan, 0, ',', '.'));

        $pdo->commit();

        jsonResponse(true, [
            'id'            => $invoiceId,
            'no_invoice'    => $invCode,
            'total_tagihan' => $totalTagihan
        ], "Invoice {$invCode} berhasil diterbitkan", 201);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(false, null, 'Gagal menerbitkan invoice: ' . $e->getMessage(), 500);
    }
}

if ($method === 'PUT') {
    // Update invoice payment status (e.g. LUNAS)
    $json = file_get_contents('php://input');
    $input = json_decode($json, true);

    $id = (int)($input['id'] ?? 0);
    $status = trim((string)($input['status_pembayaran'] ?? 'LUNAS'));
    $metodeBayar = trim((string)($input['metode_bayar'] ?? 'Transfer Bank Mandiri'));
    $tanggalLunas = !empty($input['tanggal_lunas']) ? $input['tanggal_lunas'] : date('Y-m-d');

    try {
        $stmt = $pdo->prepare("
            UPDATE invoice_penagihan 
            SET status_pembayaran = ?, metode_bayar = ?, tanggal_lunas = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $metodeBayar, $tanggalLunas, $id]);

        logAudit(1, 'UPDATE', 'INVOICE', $id, [
            'status' => $status,
            'metode' => $metodeBayar
        ], "Update status pembayaran invoice ID {$id} menjadi {$status}");

        jsonResponse(true, null, "Status invoice berhasil diubah menjadi {$status}");
    } catch (Exception $e) {
        jsonResponse(false, null, 'Gagal update status invoice: ' . $e->getMessage(), 500);
    }
}
