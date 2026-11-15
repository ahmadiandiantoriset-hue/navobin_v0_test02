<?php
/**
 * NATOBIN INTERNATIONAL - Inbound (LPB) API
 * Handles Goods Receiving (Penerimaan Barang Datang), auto-code generation,
 * stock tracking per batch, and document attachment.
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
        jsonResponse(true, ['code' => generateInboundCode($pdo)]);
    }

    if ($action === 'customers') {
        $stmt = $pdo->query("SELECT id, kode_customer, nama_customer, kontak_person, telepon, alamat FROM customers ORDER BY nama_customer ASC");
        jsonResponse(true, $stmt->fetchAll());
    }

    if ($action === 'detail') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("
            SELECT 
                b.*, 
                c.nama_customer, c.kode_customer, c.telepon, c.alamat,
                u.nama_lengkap AS petugas_nama,
                COALESCE(SUM(pi.used_kg), 0) AS total_used_kg,
                (b.berat_bruto_kg - COALESCE(SUM(pi.used_kg), 0)) AS remaining_kg
            FROM barang_masuk_lpb b
            JOIN customers c ON b.customer_id = c.id
            LEFT JOIN users u ON b.petugas_id = u.id
            LEFT JOIN production_inbound_items pi ON b.id = pi.inbound_id
            WHERE b.id = ?
            GROUP BY b.id
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            jsonResponse(false, null, 'LPB item not found', 404);
        }

        // Fetch production batches that consumed this inbound
        $stmtProd = $pdo->prepare("
            SELECT p.id, p.kode_produksi, p.tanggal_produksi, p.shift_kerja, pi.used_kg, p.yield_percentage
            FROM production_inbound_items pi
            JOIN proses_produksi p ON pi.production_id = p.id
            WHERE pi.inbound_id = ?
            ORDER BY p.id DESC
        ");
        $stmtProd->execute([$id]);
        $row['production_history'] = $stmtProd->fetchAll();

        jsonResponse(true, $row);
    }

    // Default: list all LPB with live remaining KG
    $stmt = $pdo->query("
        SELECT 
            b.id,
            b.no_badge,
            b.surat_jalan_ref,
            b.customer_id,
            c.nama_customer,
            c.kode_customer,
            b.jenis_tembakau,
            b.tanggal_masuk,
            b.berat_bruto_kg,
            b.jumlah_kemasan,
            b.tipe_kemasan,
            b.plat_nomor_truk,
            b.nama_sopir_inbound,
            b.estimasi_sla,
            b.lampiran_berkas,
            b.petugas_id,
            b.status_lpb,
            b.keterangan,
            b.created_at,
            u.nama_lengkap AS petugas_nama,
            COALESCE(SUM(pi.used_kg), 0) AS used_kg,
            ROUND(b.berat_bruto_kg - COALESCE(SUM(pi.used_kg), 0), 2) AS remaining_kg,
            ROUND(
                CASE 
                    WHEN b.berat_bruto_kg > 0 THEN 
                        (b.jumlah_kemasan * ((b.berat_bruto_kg - COALESCE(SUM(pi.used_kg), 0)) / b.berat_bruto_kg))
                    ELSE 0 
                END
            , 0) AS remaining_sak
        FROM barang_masuk_lpb b
        JOIN customers c ON b.customer_id = c.id
        LEFT JOIN users u ON b.petugas_id = u.id
        LEFT JOIN production_inbound_items pi ON b.id = pi.inbound_id
        GROUP BY b.id
        ORDER BY b.id DESC
    ");
    $items = $stmt->fetchAll();

    jsonResponse(true, $items, 'Inbound LPB records loaded');
}

if ($method === 'POST') {
    // Check if multipart form data or JSON
    $input = $_POST;
    if (empty($input)) {
        $json = file_get_contents('php://input');
        $input = json_decode($json, true) ?? [];
    }

    // Validation
    $customerId = (int)($input['customer_id'] ?? 0);
    $jenisTembakau = trim((string)($input['jenis_tembakau'] ?? 'Tembakau Rajang Campur'));
    $tanggalMasuk = !empty($input['tanggal_masuk']) ? $input['tanggal_masuk'] : date('Y-m-d H:i:s');
    $beratBruto = (float)($input['berat_bruto_kg'] ?? 0);
    $jumlahKemasan = (int)($input['jumlah_kemasan'] ?? 0);
    $tipeKemasan = trim((string)($input['tipe_kemasan'] ?? 'Ball'));
    $platNomor = trim((string)($input['plat_nomor_truk'] ?? ''));
    $namaSopir = trim((string)($input['nama_sopir_inbound'] ?? ''));
    $suratJalanRef = trim((string)($input['surat_jalan_ref'] ?? ''));
    $estimasiSla = !empty($input['estimasi_sla']) ? $input['estimasi_sla'] : date('Y-m-d', strtotime('+3 days'));
    $keterangan = trim((string)($input['keterangan'] ?? ''));
    $petugasId = (int)($input['petugas_id'] ?? 1);

    if ($customerId <= 0) {
        jsonResponse(false, null, 'Pilih Klien / Customer terlebih dahulu', 400);
    }
    if ($beratBruto <= 0) {
        jsonResponse(false, null, 'Berat Bruto (KG) harus lebih dari 0', 400);
    }
    if ($jumlahKemasan <= 0) {
        jsonResponse(false, null, 'Jumlah Kemasan (Sak/Ball) harus lebih dari 0', 400);
    }

    // Handle File Attachment Upload if present
    $attachmentUrl = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../assets/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileExt = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        $fileName = 'SJ_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
        $destPath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $destPath)) {
            $attachmentUrl = 'assets/uploads/' . $fileName;
        }
    }

    try {
        $pdo->beginTransaction();

        // Generate unique code if not provided or ensure uniqueness
        $badgeCode = !empty($input['no_badge']) ? trim((string)$input['no_badge']) : generateInboundCode($pdo);

        $stmtInsert = $pdo->prepare("
            INSERT INTO barang_masuk_lpb 
            (no_badge, surat_jalan_ref, customer_id, jenis_tembakau, tanggal_masuk, berat_bruto_kg, jumlah_kemasan, 
             tipe_kemasan, plat_nomor_truk, nama_sopir_inbound, estimasi_sla, lampiran_berkas, petugas_id, status_lpb, keterangan)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'MENUNGGU_RAJANG', ?)
        ");
        $stmtInsert->execute([
            $badgeCode,
            $suratJalanRef,
            $customerId,
            $jenisTembakau,
            $tanggalMasuk,
            $beratBruto,
            $jumlahKemasan,
            $tipeKemasan,
            $platNomor,
            $namaSopir,
            $estimasiSla,
            $attachmentUrl,
            $petugasId,
            $keterangan
        ]);

        $newId = (int)$pdo->lastInsertId();

        // Audit Log
        logAudit($petugasId, 'CREATE', 'INBOUND_LPB', $newId, [
            'no_badge'        => $badgeCode,
            'customer_id'     => $customerId,
            'berat_bruto_kg'  => $beratBruto,
            'jumlah_kemasan'  => $jumlahKemasan,
            'plat_nomor_truk' => $platNomor
        ], "Penerimaan barang datang {$badgeCode} seberat {$beratBruto} KG ({$jumlahKemasan} {$tipeKemasan})");

        $pdo->commit();

        jsonResponse(true, [
            'id'       => $newId,
            'no_badge' => $badgeCode
        ], "Lembar Penerimaan Barang ({$badgeCode}) berhasil disimpan", 201);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(false, null, 'Gagal menyimpan LPB: ' . $e->getMessage(), 500);
    }
}
