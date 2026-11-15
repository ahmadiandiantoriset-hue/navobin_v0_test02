<?php
/**
 * NATOBIN INTERNATIONAL - Dashboard API
 * Returns real-time KPI metrics, shift status, and daily activity feed
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    $pdo = getDB();

    // 1. KPI: Total Bahan Masuk (KG & Sak)
    $stmtInbound = $pdo->query("
        SELECT 
            COALESCE(SUM(berat_bruto_kg), 0) AS total_inbound_kg,
            COALESCE(SUM(jumlah_kemasan), 0) AS total_inbound_sak,
            COUNT(id) AS total_inbound_batches
        FROM barang_masuk_lpb
    ");
    $inboundKPI = $stmtInbound->fetch();

    // 2. KPI: Total Produksi Rajang (Input KG, Output KG, Sak, Susut KG, Average Yield)
    $stmtProd = $pdo->query("
        SELECT 
            COALESCE(SUM(total_input_kg), 0) AS total_input_kg,
            COALESCE(SUM(berat_hasil_kg), 0) AS total_output_kg,
            COALESCE(SUM(jumlah_sak_hasil), 0) AS total_output_sak,
            COALESCE(SUM(berat_susut_kg), 0) AS total_shrinkage_kg,
            COUNT(id) AS total_production_batches
        FROM proses_produksi
    ");
    $prodKPI = $stmtProd->fetch();

    $totalInputKg = (float)$prodKPI['total_input_kg'];
    $totalOutputKg = (float)$prodKPI['total_output_kg'];
    $avgYield = $totalInputKg > 0 ? round(($totalOutputKg / $totalInputKg) * 100, 2) : 0.0;

    // 3. KPI: Inventory Gudang Tersimpan
    $stmtInv = $pdo->query("
        SELECT 
            COALESCE(SUM(jumlah_sak_tersisa), 0) AS total_stok_sak,
            COALESCE(SUM(berat_tersisa_kg), 0) AS total_stok_kg
        FROM inventory_gudang
        WHERE status_stok != 'HABIS'
    ");
    $invKPI = $stmtInv->fetch();

    // 4. KPI: Outbound / Pengiriman
    $stmtOut = $pdo->query("
        SELECT 
            COALESCE(SUM(jumlah_sak), 0) AS total_shipped_sak,
            COALESCE(SUM(total_berat_kg), 0) AS total_shipped_kg,
            COUNT(id) AS total_shipped_orders
        FROM pengiriman
    ");
    $outKPI = $stmtOut->fetch();

    // 5. Inbound Breakdown by Status
    $stmtStatus = $pdo->query("
        SELECT status_lpb, COUNT(*) as count, COALESCE(SUM(berat_bruto_kg), 0) as total_kg
        FROM barang_masuk_lpb
        GROUP BY status_lpb
    ");
    $inboundStatusBreakdown = $stmtStatus->fetchAll();

    // 6. Recent Production Batches
    $stmtRecentProd = $pdo->query("
        SELECT 
            p.id, p.kode_produksi, p.tanggal_produksi, p.shift_kerja, 
            p.total_input_kg, p.berat_hasil_kg, p.jumlah_sak_hasil, 
            p.berat_susut_kg, p.yield_percentage, p.storage_location,
            p.kepemilikan_sak, p.daftar_pekerja
        FROM proses_produksi p
        ORDER BY p.id DESC
        LIMIT 5
    ");
    $recentBatches = $stmtRecentProd->fetchAll();

    // 7. Recent Inbound LPB
    $stmtRecentIn = $pdo->query("
        SELECT 
            b.id, b.no_badge, b.jenis_tembakau, b.tanggal_masuk, 
            b.berat_bruto_kg, b.jumlah_kemasan, b.status_lpb,
            c.nama_customer
        FROM barang_masuk_lpb b
        JOIN customers c ON b.customer_id = c.id
        ORDER BY b.id DESC
        LIMIT 5
    ");
    $recentInbound = $stmtRecentIn->fetchAll();

    // 8. Recent Outbound Delivery
    $stmtRecentOut = $pdo->query("
        SELECT 
            d.id, d.no_surat_jalan, d.tanggal_kirim, d.jumlah_sak, 
            d.total_berat_kg, d.nama_sopir, d.plat_armada, d.status_pengiriman,
            c.nama_customer
        FROM pengiriman d
        JOIN customers c ON d.customer_id = c.id
        ORDER BY d.id DESC
        LIMIT 5
    ");
    $recentOutbound = $stmtRecentOut->fetchAll();

    // 9. Active Shift determination (WIB UTC+7)
    $currentHour = (int)date('G');
    $activeShift = ($currentHour >= 7 && $currentHour < 15) ? 'PAGI' : 'SORE';

    // 10. Audit Activity Feed (Latest 10 actions)
    $stmtAudit = $pdo->query("
        SELECT id, nama_user, role, waktu, ip_address, aksi, entity, entity_id, keterangan_detail
        FROM log_audit_akses
        ORDER BY id DESC
        LIMIT 10
    ");
    $recentAudit = $stmtAudit->fetchAll();

    // 11. Warehouse Rack Utilization
    $stmtRacks = $pdo->query("
        SELECT 
            r.kode_rak, r.nama_blok, r.kapasitas_sak,
            COALESCE(SUM(i.jumlah_sak_tersisa), 0) AS terisi_sak
        FROM master_rak r
        LEFT JOIN inventory_gudang i ON r.kode_rak = i.kode_rak AND i.status_stok != 'HABIS'
        GROUP BY r.id, r.kode_rak, r.nama_blok, r.kapasitas_sak
    ");
    $rackUtilization = $stmtRacks->fetchAll();

    jsonResponse(true, [
        'kpi' => [
            'total_inbound_kg'          => (float)$inboundKPI['total_inbound_kg'],
            'total_inbound_sak'         => (int)$inboundKPI['total_inbound_sak'],
            'total_inbound_batches'     => (int)$inboundKPI['total_inbound_batches'],
            'total_input_kg'            => $totalInputKg,
            'total_output_kg'           => $totalOutputKg,
            'total_output_sak'          => (int)$prodKPI['total_output_sak'],
            'total_shrinkage_kg'        => (float)$prodKPI['total_shrinkage_kg'],
            'avg_yield_percentage'      => $avgYield,
            'total_stok_sak'            => (int)$invKPI['total_stok_sak'],
            'total_stok_kg'             => (float)$invKPI['total_stok_kg'],
            'total_shipped_sak'         => (int)$outKPI['total_shipped_sak'],
            'total_shipped_kg'          => (float)$outKPI['total_shipped_kg'],
            'total_shipped_orders'      => (int)$outKPI['total_shipped_orders'],
            'active_shift'              => $activeShift,
        ],
        'inbound_status_breakdown' => $inboundStatusBreakdown,
        'recent_batches'          => $recentBatches,
        'recent_inbound'          => $recentInbound,
        'recent_outbound'         => $recentOutbound,
        'recent_audit'            => $recentAudit,
        'rack_utilization'        => $rackUtilization
    ], 'Dashboard metrics retrieved successfully');

} catch (Exception $e) {
    jsonResponse(false, null, 'Error: ' . $e->getMessage(), 500);
}
