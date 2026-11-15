<?php
/**
 * NATOBIN INTERNATIONAL - Production (Rajang) API
 * Handles Tobacco Shredding Production, Multi-Inbound Raw Material Consumption,
 * Live Yield / Shrinkage Calculations, Worker Presensi, and Storage Binning.
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
        jsonResponse(true, ['code' => generateProductionCode($pdo)]);
    }

    if ($action === 'available_inbounds') {
        // Return all inbounds that still have remaining raw material
        $stmt = $pdo->query("
            SELECT 
                b.id,
                b.no_badge,
                b.jenis_tembakau,
                b.tanggal_masuk,
                b.berat_bruto_kg,
                b.jumlah_kemasan,
                b.tipe_kemasan,
                b.status_lpb,
                c.nama_customer,
                COALESCE(SUM(pi.used_kg), 0) AS used_kg,
                ROUND(b.berat_bruto_kg - COALESCE(SUM(pi.used_kg), 0), 2) AS available_kg
            FROM barang_masuk_lpb b
            JOIN customers c ON b.customer_id = c.id
            LEFT JOIN production_inbound_items pi ON b.id = pi.inbound_id
            GROUP BY b.id
            HAVING available_kg > 0
            ORDER BY b.id ASC
        ");
        jsonResponse(true, $stmt->fetchAll(), 'Available inbounds for production');
    }

    if ($action === 'racks') {
        $stmt = $pdo->query("SELECT id, kode_rak, nama_blok, kapasitas_sak, keterangan FROM master_rak ORDER BY kode_rak ASC");
        jsonResponse(true, $stmt->fetchAll());
    }

    if ($action === 'detail') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("
            SELECT p.*, u.nama_lengkap AS operator_nama
            FROM proses_produksi p
            LEFT JOIN users u ON p.operator_id = u.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $batch = $stmt->fetch();
        if (!$batch) {
            jsonResponse(false, null, 'Production batch not found', 404);
        }

        // Fetch consumed raw materials
        $stmtIn = $pdo->prepare("
            SELECT pi.id, pi.inbound_id, pi.used_kg, b.no_badge, b.jenis_tembakau, c.nama_customer
            FROM production_inbound_items pi
            JOIN barang_masuk_lpb b ON pi.inbound_id = b.id
            JOIN customers c ON b.customer_id = c.id
            WHERE pi.production_id = ?
        ");
        $stmtIn->execute([$id]);
        $batch['inbound_items'] = $stmtIn->fetchAll();

        // Fetch workers
        $stmtWorkers = $pdo->prepare("
            SELECT id, worker_name, role_type
            FROM production_workers
            WHERE production_id = ?
            ORDER BY id ASC
        ");
        $stmtWorkers->execute([$id]);
        $batch['workers'] = $stmtWorkers->fetchAll();

        jsonResponse(true, $batch);
    }

    // Default: list all production batches
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.kode_produksi,
            p.tanggal_produksi,
            p.shift_kerja,
            p.total_input_kg,
            p.berat_hasil_kg,
            p.jumlah_sak_hasil,
            p.target_bobot_sak_kg,
            p.berat_susut_kg,
            p.yield_percentage,
            p.kepemilikan_sak,
            p.storage_location,
            p.status_produksi,
            p.ukuran_cutting_mm,
            p.daftar_pekerja,
            p.keterangan_operasional,
            p.created_at,
            u.nama_lengkap AS operator_nama,
            (SELECT COUNT(*) FROM production_workers pw WHERE pw.production_id = p.id) AS worker_count,
            (SELECT GROUP_CONCAT(b.no_badge SEPARATOR ', ') 
             FROM production_inbound_items pi 
             JOIN barang_masuk_lpb b ON pi.inbound_id = b.id 
             WHERE pi.production_id = p.id) AS raw_inbound_codes
        FROM proses_produksi p
        LEFT JOIN users u ON p.operator_id = u.id
        ORDER BY p.id DESC
    ");
    $batches = $stmt->fetchAll();

    jsonResponse(true, $batches, 'Production batches retrieved');
}

if ($method === 'POST') {
    $json = file_get_contents('php://input');
    $input = json_decode($json, true);

    if (empty($input)) {
        $input = $_POST;
    }

    $batchCode = !empty($input['kode_produksi']) ? trim((string)$input['kode_produksi']) : generateProductionCode($pdo);
    $tanggalProduksi = !empty($input['tanggal_produksi']) ? $input['tanggal_produksi'] : date('Y-m-d');
    $shiftKerja = in_array(strtoupper((string)($input['shift_kerja'] ?? '')), ['PAGI', 'SORE']) ? strtoupper((string)$input['shift_kerja']) : 'PAGI';
    $targetBobotSak = (float)($input['target_bobot_sak_kg'] ?? 25.0);
    $jumlahSakHasil = (int)($input['jumlah_sak_hasil'] ?? 0);
    $ukuranCutting = (float)($input['ukuran_cutting_mm'] ?? 2.5);
    $kepemilikanSak = (in_array(strtoupper((string)($input['kepemilikan_sak'] ?? '')), ['MILIK CLIENT', 'CLIENT'])) ? 'MILIK CLIENT' : 'MILIK SENDIRI';
    $storageLocation = trim((string)($input['storage_location'] ?? 'G1-B01-R01-P01'));
    $keterangan = trim((string)($input['keterangan_operasional'] ?? ''));
    $operatorId = (int)($input['operator_id'] ?? 1);

    // Raw Material Inbound array: [ { inbound_id: 1, used_kg: 500.0 }, ... ]
    $inboundSources = $input['inbound_sources'] ?? [];
    if (empty($inboundSources) || !is_array($inboundSources)) {
        jsonResponse(false, null, 'Minimal 1 Bahan Masuk (Inbound LPB) harus dipilih', 400);
    }

    // Workers array: [ { worker_name: 'Sahrul', role_type: 'RAJANG' }, ... ]
    $workers = $input['workers'] ?? [];

    // Calculate Total Input KG
    $totalInputKg = 0.0;
    foreach ($inboundSources as $src) {
        $usedKg = (float)($src['used_kg'] ?? 0);
        if ($usedKg <= 0) {
            jsonResponse(false, null, 'Nilai Used KG pada bahan masuk harus lebih dari 0', 400);
        }
        $totalInputKg += $usedKg;
    }

    if ($totalInputKg <= 0) {
        jsonResponse(false, null, 'Total Berat Input (KG) harus lebih dari 0', 400);
    }
    if ($jumlahSakHasil <= 0) {
        jsonResponse(false, null, 'Jumlah Sak Hasil harus lebih dari 0', 400);
    }
    if ($targetBobotSak <= 0) {
        $targetBobotSak = 25.0;
    }

    // Mathematical Core Formulas:
    // Total Berat Output (KG) = Jumlah Sak * Target KG/Sak
    // Rendemen Yield (%) = (Total Output / Total Input) * 100
    // Susut Loss (KG) = Total Input - Total Output
    // If output is custom provided, allow override or compute:
    $totalOutputKg = isset($input['berat_hasil_kg']) && (float)$input['berat_hasil_kg'] > 0
        ? (float)$input['berat_hasil_kg']
        : round($jumlahSakHasil * $targetBobotSak, 2);

    $shrinkageKg = round($totalInputKg - $totalOutputKg, 2);
    $yieldPercentage = round(($totalOutputKg / $totalInputKg) * 100, 2);
    $persentaseSusut = round(100 - $yieldPercentage, 2);

    // Compile worker names string for backward compatibility
    $workerNamesList = [];
    foreach ($workers as $w) {
        if (!empty($w['worker_name'])) {
            $workerNamesList[] = trim($w['worker_name']);
        }
    }
    $daftarPekerjaStr = !empty($workerNamesList) ? implode(', ', $workerNamesList) : 'Tim Produksi Shift ' . $shiftKerja;
    $jumlahPekerja = count($workerNamesList) ?: 1;

    // Pick first inbound id for backward compatibility column `lpb_id`
    $firstInboundId = (int)$inboundSources[0]['inbound_id'];

    try {
        $pdo->beginTransaction();

        // 1. Insert into proses_produksi
        $stmtProd = $pdo->prepare("
            INSERT INTO proses_produksi (
                kode_produksi, lpb_id, tanggal_produksi, shift_kerja, 
                jumlah_pekerja, daftar_pekerja, ukuran_cutting_mm, target_bobot_sak_kg, 
                jumlah_sak_hasil, total_input_kg, berat_hasil_kg, berat_susut_kg, 
                persentase_susut, yield_percentage, kepemilikan_sak, storage_location, 
                jalur_distribusi, status_produksi, keterangan_operasional, operator_id
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                'GUDANG', 'SELESAI', ?, ?
            )
        ");
        $stmtProd->execute([
            $batchCode,
            $firstInboundId,
            $tanggalProduksi,
            $shiftKerja,
            $jumlahPekerja,
            $daftarPekerjaStr,
            $ukuranCutting,
            $targetBobotSak,
            $jumlahSakHasil,
            $totalInputKg,
            $totalOutputKg,
            $shrinkageKg,
            $persentaseSusut,
            $yieldPercentage,
            $kepemilikanSak,
            $storageLocation,
            $keterangan,
            $operatorId
        ]);

        $productionId = (int)$pdo->lastInsertId();

        // 2. Insert into production_inbound_items (many-to-many consumption)
        $stmtInsertPii = $pdo->prepare("
            INSERT INTO production_inbound_items (production_id, inbound_id, used_kg)
            VALUES (?, ?, ?)
        ");
        foreach ($inboundSources as $src) {
            $inboundId = (int)$src['inbound_id'];
            $usedKg = (float)$src['used_kg'];
            $stmtInsertPii->execute([$productionId, $inboundId, $usedKg]);

            // Check remaining stock of this inbound and update status
            $stmtCheckStock = $pdo->prepare("
                SELECT b.berat_bruto_kg, COALESCE(SUM(pi.used_kg), 0) AS total_used
                FROM barang_masuk_lpb b
                LEFT JOIN production_inbound_items pi ON b.id = pi.inbound_id
                WHERE b.id = ?
                GROUP BY b.id
            ");
            $stmtCheckStock->execute([$inboundId]);
            $stockData = $stmtCheckStock->fetch();

            if ($stockData) {
                $totalBruto = (float)$stockData['berat_bruto_kg'];
                $totalUsed = (float)$stockData['total_used'];
                $newStatus = ($totalUsed >= $totalBruto) ? 'SELESAI_RAJANG' : 'SEDANG_DIRAJANG';

                $stmtUpdateLpb = $pdo->prepare("UPDATE barang_masuk_lpb SET status_lpb = ? WHERE id = ?");
                $stmtUpdateLpb->execute([$newStatus, $inboundId]);
            }
        }

        // 3. Insert into production_workers
        if (!empty($workers)) {
            $stmtInsertWorker = $pdo->prepare("
                INSERT INTO production_workers (production_id, worker_name, role_type)
                VALUES (?, ?, ?)
            ");
            foreach ($workers as $w) {
                $wName = trim((string)($w['worker_name'] ?? ''));
                if ($wName !== '') {
                    $roleType = strtoupper((string)($w['role_type'] ?? 'RAJANG'));
                    if (!in_array($roleType, ['RAJANG', 'BANGUNAN', 'LAINNYA'])) {
                        $roleType = 'RAJANG';
                    }
                    $stmtInsertWorker->execute([$productionId, $wName, $roleType]);
                }
            }
        }

        // 4. Automatically record into inventory_gudang
        $rackCode = explode('-', $storageLocation)[0] ?? 'RAK-A1';
        $stmtInv = $pdo->prepare("
            INSERT INTO inventory_gudang (
                kode_rak, produksi_id, tanggal_masuk_gudang, jumlah_sak_masuk, 
                jumlah_sak_tersisa, berat_per_sak_kg, berat_tersisa_kg, 
                status_stok, petugas_gudang_id, catatan
            ) VALUES (?, ?, NOW(), ?, ?, ?, ?, 'TERSEDIA', ?, ?)
        ");
        $stmtInv->execute([
            $rackCode,
            $productionId,
            $jumlahSakHasil,
            $jumlahSakHasil,
            $targetBobotSak,
            $totalOutputKg,
            $operatorId,
            "Alokasi bin otomatis: {$storageLocation} dari batch {$batchCode}"
        ]);

        // 5. Audit Log
        logAudit($operatorId, 'CREATE', 'PRODUCTION_BATCH', $productionId, [
            'batch_code'       => $batchCode,
            'shift'            => $shiftKerja,
            'total_input_kg'   => $totalInputKg,
            'total_output_kg'  => $totalOutputKg,
            'jumlah_sak_hasil' => $jumlahSakHasil,
            'yield_percentage' => $yieldPercentage,
            'shrinkage_kg'     => $shrinkageKg,
            'storage_location' => $storageLocation
        ], "Produksi Rajang {$batchCode} selesai. Input: {$totalInputKg} KG, Output: {$totalOutputKg} KG ({$jumlahSakHasil} Sak), Yield: {$yieldPercentage}%, Susut: {$shrinkageKg} KG");

        $pdo->commit();

        jsonResponse(true, [
            'id'               => $productionId,
            'kode_produksi'    => $batchCode,
            'total_input_kg'   => $totalInputKg,
            'total_output_kg'  => $totalOutputKg,
            'yield_percentage' => $yieldPercentage,
            'shrinkage_kg'     => $shrinkageKg
        ], "Batch Produksi {$batchCode} berhasil diproses dan disimpan ke gudang", 201);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(false, null, 'Gagal memproses batch produksi: ' . $e->getMessage(), 500);
    }
}
