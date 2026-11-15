<?php
/**
 * NATOBIN INTERNATIONAL - Printable Surat Perintah Kerja (SPK) & Laporan Produksi Rajang
 * A4 Standard with Yield & Shrinkage Breakdown and Worker Presensi.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$id = (int)($_GET['id'] ?? 0);
$pdo = getDB();

$stmt = $pdo->prepare("
    SELECT 
        p.*, 
        u.nama_lengkap AS operator_nama
    FROM proses_produksi p
    LEFT JOIN users u ON p.operator_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$batch = $stmt->fetch();

if (!$batch) {
    die("Dokumen Produksi tidak ditemukan.");
}

// Fetch consumed raw materials
$stmtIn = $pdo->prepare("
    SELECT pi.used_kg, b.no_badge, b.jenis_tembakau, c.nama_customer, b.tanggal_masuk
    FROM production_inbound_items pi
    JOIN barang_masuk_lpb b ON pi.inbound_id = b.id
    JOIN customers c ON b.customer_id = c.id
    WHERE pi.production_id = ?
");
$stmtIn->execute([$id]);
$rawMaterials = $stmtIn->fetchAll();

// Fetch workers
$stmtWorkers = $pdo->prepare("
    SELECT worker_name, role_type
    FROM production_workers
    WHERE production_id = ?
    ORDER BY id ASC
");
$stmtWorkers->execute([$id]);
$workers = $stmtWorkers->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPK Produksi - <?= htmlspecialchars($batch['kode_produksi']) ?> - NATOBIN</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: #f1f5f9; color: #0f172a; padding: 20px; }
        .print-page { 
            max-width: 800px; margin: 0 auto; background: #fff; padding: 35px 40px; 
            border: 1px solid #cbd5e1; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); 
        }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0f172a; padding-bottom: 15px; margin-bottom: 20px; }
        .logo-wrap { display: flex; align-items: center; gap: 15px; }
        .logo-wrap img { height: 50px; }
        .company-info h1 { font-size: 20px; font-weight: 800; letter-spacing: 0.5px; color: #0f172a; text-transform: uppercase; }
        .company-info p { font-size: 11px; color: #475569; margin-top: 2px; }
        .doc-badge { text-align: right; }
        .doc-badge h2 { font-size: 16px; font-weight: 700; color: #0369a1; text-transform: uppercase; }
        .doc-badge .code { font-family: monospace; font-size: 16px; font-weight: 800; color: #0f172a; background: #f8fafc; border: 1px dashed #94a3b8; padding: 4px 10px; display: inline-block; margin-top: 4px; }
        
        .summary-bar {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px; text-align: center;
        }
        .sum-card { background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 4px; }
        .sum-title { font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; }
        .sum-val { font-size: 16px; font-weight: 800; color: #0f172a; margin-top: 2px; }
        .sum-val.yield { color: #16a34a; }
        .sum-val.shrink { color: #dc2626; }

        .section-title { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #0f172a; border-left: 3px solid #0f172a; padding-left: 8px; margin: 15px 0 8px 0; }
        table.tbl { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 11.5px; }
        table.tbl th { background: #1e293b; color: #fff; text-transform: uppercase; font-size: 10.5px; padding: 7px 10px; text-align: left; }
        table.tbl td { border: 1px solid #e2e8f0; padding: 6px 10px; }
        table.tbl tr:nth-child(even) { background: #f8fafc; }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .box { border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px 12px; background: #fafafa; font-size: 11.5px; }
        .data-row { display: flex; justify-content: space-between; margin-bottom: 4px; }
        .data-label { color: #64748b; font-weight: 500; }
        .data-val { font-weight: 600; color: #0f172a; }

        .signatures { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 30px; text-align: center; }
        .sig-box { border-top: 1px dashed #94a3b8; padding-top: 50px; font-size: 11.5px; font-weight: 600; color: #0f172a; }
        .sig-role { font-size: 10.5px; color: #64748b; font-weight: 400; margin-top: 2px; }

        .btn-print {
            position: fixed; bottom: 25px; right: 25px; background: #0369a1; color: #fff;
            padding: 12px 24px; border: none; border-radius: 6px; font-weight: 700; cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25); display: flex; align-items: center; gap: 8px; font-size: 14px;
        }
        .btn-print:hover { background: #075985; }

        @media print {
            body { background: #fff; padding: 0; }
            .print-page { border: none; box-shadow: none; padding: 10px; max-width: 100%; }
            .btn-print { display: none !important; }
        }
    </style>
</head>
<body>

    <button class="btn-print" onclick="window.print()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        Cetak Dokumen SPK (A4)
    </button>

    <div class="print-page">
        <!-- HEADER -->
        <div class="header">
            <div class="logo-wrap">
                <img src="../assets/logo_natobin.png" alt="NATOBIN">
                <div class="company-info">
                    <h1>NATOBIN INTERNATIONAL</h1>
                    <p>Tobacco Shredding & Warehouse Management System</p>
                    <p>Jl. Industri Tembakau No. 88, Jawa Timur | Telp: (0331) 489-202</p>
                </div>
            </div>
            <div class="doc-badge">
                <h2>Laporan Produksi Rajang</h2>
                <div class="code"><?= htmlspecialchars($batch['kode_produksi']) ?></div>
            </div>
        </div>

        <!-- KPI SUMMARY CARDS -->
        <div class="summary-bar">
            <div class="sum-card">
                <div class="sum-title">Total Input Bahan</div>
                <div class="sum-val"><?= number_format((float)$batch['total_input_kg'], 2, ',', '.') ?> KG</div>
            </div>
            <div class="sum-card">
                <div class="sum-title">Hasil Jadi (Output)</div>
                <div class="sum-val"><?= number_format((float)$batch['berat_hasil_kg'], 2, ',', '.') ?> KG</div>
            </div>
            <div class="sum-card">
                <div class="sum-title">Rendemen (Yield)</div>
                <div class="sum-val yield"><?= number_format((float)$batch['yield_percentage'], 2) ?>%</div>
            </div>
            <div class="sum-card">
                <div class="sum-title">Susut / Shrinkage</div>
                <div class="sum-val shrink"><?= number_format((float)$batch['berat_susut_kg'], 2, ',', '.') ?> KG</div>
            </div>
        </div>

        <!-- SHIFT & BATCH METADATA -->
        <div class="grid-2">
            <div class="box">
                <div class="data-row">
                    <span class="data-label">Tanggal Produksi:</span>
                    <span class="data-val"><?= date('d F Y', strtotime($batch['tanggal_produksi'])) ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Shift Kerja:</span>
                    <span class="data-val">Shift <?= htmlspecialchars($batch['shift_kerja']) ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Ukuran Rajang (Cutting):</span>
                    <span class="data-val"><?= number_format((float)$batch['ukuran_cutting_mm'], 2) ?> mm</span>
                </div>
                <div class="data-row">
                    <span class="data-label">Mandor / Operator:</span>
                    <span class="data-val"><?= htmlspecialchars($batch['operator_nama'] ?: 'Om Dol') ?></span>
                </div>
            </div>

            <div class="box">
                <div class="data-row">
                    <span class="data-label">Jumlah Sak Dihasilkan:</span>
                    <span class="data-val"><strong><?= number_format((int)$batch['jumlah_sak_hasil']) ?> Sak</strong> (@<?= number_format((float)$batch['target_bobot_sak_kg'], 1) ?> KG)</span>
                </div>
                <div class="data-row">
                    <span class="data-label">Kepemilikan Sak:</span>
                    <span class="data-val" style="color: #0369a1; font-weight: 700;"><?= htmlspecialchars($batch['kepemilikan_sak']) ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Lokasi Bin Gudang:</span>
                    <span class="data-val"><strong><?= htmlspecialchars($batch['storage_location'] ?: 'G1-B01-R01-P01') ?></strong></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Status Batch:</span>
                    <span class="data-val"><?= htmlspecialchars($batch['status_produksi']) ?></span>
                </div>
            </div>
        </div>

        <!-- RAW MATERIALS CONSUMED -->
        <div class="section-title">1. Traceability Bahan Baku Masuk (Input Konsumsi)</div>
        <table class="tbl">
            <thead>
                <tr>
                    <th>No. LPB (Inbound Batch)</th>
                    <th>Pemilik / Klien</th>
                    <th>Varietas Tembakau</th>
                    <th>Tgl Diterima</th>
                    <th style="text-align: right;">Bahan Digunakan (KG)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($rawMaterials)): ?>
                    <?php foreach ($rawMaterials as $rm): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($rm['no_badge']) ?></strong></td>
                            <td><?= htmlspecialchars($rm['nama_customer']) ?></td>
                            <td><?= htmlspecialchars($rm['jenis_tembakau']) ?></td>
                            <td><?= date('d/m/Y', strtotime($rm['tanggal_masuk'])) ?></td>
                            <td style="text-align: right; font-weight: 700;"><?= number_format((float)$rm['used_kg'], 2, ',', '.') ?> KG</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #64748b;">Batch langsung dari LPB #<?= htmlspecialchars((string)$batch['lpb_id']) ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f1f5f9; font-weight: 700;">
                    <td colspan="4" style="text-align: right;">TOTAL BAHAN DIGUNAKAN:</td>
                    <td style="text-align: right; font-size: 13px; color: #0f172a;"><?= number_format((float)$batch['total_input_kg'], 2, ',', '.') ?> KG</td>
                </tr>
            </tfoot>
        </table>

        <!-- WORKER ATTENDANCE (PRESENSI) -->
        <div class="section-title">2. Daftar Tenaga Kerja & Pembagian Tugas (Presensi Shift)</div>
        <table class="tbl">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">No</th>
                    <th>Nama Pekerja</th>
                    <th>Divisi / Peran Tugas</th>
                    <th>Keterangan Kehadiran</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($workers)): ?>
                    <?php $no = 1; foreach ($workers as $w): ?>
                        <tr>
                            <td style="text-align: center;"><?= $no++ ?></td>
                            <td><strong><?= htmlspecialchars($w['worker_name']) ?></strong></td>
                            <td>
                                <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; 
                                    background: <?= $w['role_type'] === 'RAJANG' ? '#e0f2fe; color: #0369a1;' : ($w['role_type'] === 'BANGUNAN' ? '#fef3c7; color: #b45309;' : '#f1f5f9; color: #475569;') ?>">
                                    <?= htmlspecialchars($w['role_type']) ?>
                                </span>
                            </td>
                            <td style="color: #16a34a; font-weight: 600;">Hadir (Shift Penuh)</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td style="text-align: center;">1</td>
                        <td><?= htmlspecialchars($batch['daftar_pekerja'] ?: 'Sahrul, B. Amsor, Sugik') ?></td>
                        <td>Tim Rajang & Bangunan</td>
                        <td style="color: #16a34a; font-weight: 600;">Hadir</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- OPERATIONAL NOTES -->
        <div class="box" style="margin-bottom: 20px;">
            <span class="data-label" style="font-weight: 700; text-transform: uppercase;">Catatan Operasional & Kualitas:</span>
            <p style="margin-top: 4px; color: #334155; line-height: 1.4;">
                <?= nl2br(htmlspecialchars($batch['keterangan_operasional'] ?: 'Kadar air tembakau ideal, pisau rajang diasah presisi 2.5mm. Hasil rajangan bersih seragam, telah ditimbang dan disusun pada bin lokasi gudang.')) ?>
            </p>
        </div>

        <!-- 3 SIGNATURE BLOCKS -->
        <div class="signatures">
            <div class="sig-box">
                ( <?= htmlspecialchars($batch['operator_nama'] ?: 'Om Dol') ?> )
                <div class="sig-role">Mandor Produksi Rajang</div>
            </div>
            <div class="sig-box">
                ( Bagas / QC Lead )
                <div class="sig-role">Quality Control & Rendemen</div>
            </div>
            <div class="sig-box">
                ( Cipto / Supervisor )
                <div class="sig-role">Kepala Pergudangan</div>
            </div>
        </div>
    </div>

</body>
</html>
