<?php
/**
 * NATOBIN INTERNATIONAL - Printable Surat Jalan / Delivery Order (DO)
 * A4 Standard with Driver & Vehicle Details, Itemized Batches, and 4 Signatures.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$id = (int)($_GET['id'] ?? 0);
$pdo = getDB();

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
$do = $stmt->fetch();

if (!$do) {
    die("Dokumen Surat Jalan tidak ditemukan.");
}

// Fetch itemized batches in this shipment
$stmtItems = $pdo->prepare("
    SELECT 
        oi.delivered_sak, oi.delivered_kg,
        p.kode_produksi, p.target_bobot_sak_kg, p.kepemilikan_sak, p.storage_location,
        b.jenis_tembakau
    FROM outbound_items oi
    JOIN proses_produksi p ON oi.production_id = p.id
    LEFT JOIN barang_masuk_lpb b ON p.lpb_id = b.id
    WHERE oi.outbound_id = ?
");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Jalan - <?= htmlspecialchars($do['no_surat_jalan']) ?> - NATOBIN</title>
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
        .doc-badge h2 { font-size: 16px; font-weight: 700; color: #16a34a; text-transform: uppercase; }
        .doc-badge .code { font-family: monospace; font-size: 16px; font-weight: 800; color: #0f172a; background: #f8fafc; border: 1px dashed #94a3b8; padding: 4px 10px; display: inline-block; margin-top: 4px; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .box { border: 1px solid #e2e8f0; border-radius: 4px; padding: 12px 14px; background: #fafafa; }
        .box-title { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .data-row { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 5px; }
        .data-label { color: #64748b; font-weight: 500; }
        .data-val { font-weight: 600; color: #0f172a; text-align: right; }

        table.tbl { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }
        table.tbl th { background: #0f172a; color: #fff; text-transform: uppercase; font-size: 11px; padding: 8px 10px; text-align: left; }
        table.tbl td { border: 1px solid #e2e8f0; padding: 8px 10px; }
        table.tbl tr:nth-child(even) { background: #f8fafc; }

        .signatures { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-top: 35px; text-align: center; }
        .sig-box { border-top: 1px dashed #94a3b8; padding-top: 55px; font-size: 11px; font-weight: 600; color: #0f172a; }
        .sig-role { font-size: 10px; color: #64748b; font-weight: 400; margin-top: 2px; }

        .btn-print {
            position: fixed; bottom: 25px; right: 25px; background: #16a34a; color: #fff;
            padding: 12px 24px; border: none; border-radius: 6px; font-weight: 700; cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25); display: flex; align-items: center; gap: 8px; font-size: 14px;
        }
        .btn-print:hover { background: #15803d; }

        @media print {
            body { background: #fff; padding: 0; }
            .print-page { border: none; box-shadow: none; padding: 15px; max-width: 100%; }
            .btn-print { display: none !important; }
        }
    </style>
</head>
<body>

    <button class="btn-print" onclick="window.print()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        Cetak Surat Jalan (A4)
    </button>

    <div class="print-page">
        <!-- HEADER -->
        <div class="header">
            <div class="logo-wrap">
                <img src="../assets/logo_natobin.png" alt="NATOBIN">
                <div class="company-info">
                    <h1>NATOBIN INTERNATIONAL</h1>
                    <p>Tobacco Processing & Warehouse Management System</p>
                    <p>Jl. Industri Tembakau No. 88, Jawa Timur | Telp: (0331) 489-202</p>
                </div>
            </div>
            <div class="doc-badge">
                <h2>Surat Jalan Pengiriman</h2>
                <div class="code"><?= htmlspecialchars($do['no_surat_jalan']) ?></div>
            </div>
        </div>

        <!-- 2 COLUMN DETAILS -->
        <div class="grid-2">
            <div class="box">
                <div class="box-title">Tujuan Pengiriman / Penerima</div>
                <div class="data-row">
                    <span class="data-label">Nama Klien:</span>
                    <span class="data-val"><?= htmlspecialchars($do['nama_customer']) ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Alamat Bongkar:</span>
                    <span class="data-val" style="max-width: 65%;"><?= htmlspecialchars($do['tujuan_pengiriman']) ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">No. Telepon:</span>
                    <span class="data-val"><?= htmlspecialchars($do['customer_telepon'] ?: '-') ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Status Kirim:</span>
                    <span class="data-val" style="color: #16a34a; font-weight: 700;"><?= htmlspecialchars($do['status_pengiriman']) ?></span>
                </div>
            </div>

            <div class="box">
                <div class="box-title">Data Pengiriman & Armada</div>
                <div class="data-row">
                    <span class="data-label">Tanggal Kirim:</span>
                    <span class="data-val"><?= date('d F Y', strtotime($do['tanggal_kirim'])) ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">No. Polisi Armada:</span>
                    <span class="data-val"><?= htmlspecialchars($do['plat_armada']) ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Nama Pengemudi (Sopir):</span>
                    <span class="data-val"><?= htmlspecialchars($do['nama_sopir']) ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Sumber Barang:</span>
                    <span class="data-val"><?= htmlspecialchars($do['sumber_barang']) ?></span>
                </div>
            </div>
        </div>

        <!-- ITEM DETAILS TABLE -->
        <table class="tbl">
            <thead>
                <tr>
                    <th style="width: 30px; text-align: center;">No</th>
                    <th>Kode Batch Produksi</th>
                    <th>Jenis / Komoditas Tembakau</th>
                    <th>Kepemilikan Sak</th>
                    <th style="text-align: center;">Jumlah Sak</th>
                    <th style="text-align: right;">Total Netto (KG)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)): ?>
                    <?php $no = 1; foreach ($items as $it): ?>
                        <tr>
                            <td style="text-align: center;"><?= $no++ ?></td>
                            <td><strong><?= htmlspecialchars($it['kode_produksi']) ?></strong></td>
                            <td><?= htmlspecialchars($it['jenis_tembakau'] ?: 'Tembakau Rajang Kering') ?></td>
                            <td><?= htmlspecialchars($it['kepemilikan_sak']) ?></td>
                            <td style="text-align: center; font-weight: 700;"><?= number_format((int)$it['delivered_sak']) ?> Sak</td>
                            <td style="text-align: right; font-weight: 800; font-size: 13px;"><?= number_format((float)$it['delivered_kg'], 2, ',', '.') ?> KG</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td style="text-align: center;">1</td>
                        <td><strong><?= htmlspecialchars($do['kode_produksi'] ?: 'PRD-BATCH') ?></strong></td>
                        <td>Tembakau Rajang Standard</td>
                        <td><?= htmlspecialchars($do['kepemilikan_sak'] ?: 'MILIK SENDIRI') ?></td>
                        <td style="text-align: center; font-weight: 700;"><?= number_format((int)$do['jumlah_sak']) ?> Sak</td>
                        <td style="text-align: right; font-weight: 800; font-size: 13px;"><?= number_format((float)$do['total_berat_kg'], 2, ',', '.') ?> KG</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f1f5f9; font-weight: 800; font-size: 13px;">
                    <td colspan="4" style="text-align: right;">TOTAL BARANG DIANGKUT:</td>
                    <td style="text-align: center; color: #0f172a;"><?= number_format((int)$do['jumlah_sak']) ?> SAK</td>
                    <td style="text-align: right; color: #16a34a;"><?= number_format((float)$do['total_berat_kg'], 2, ',', '.') ?> KG</td>
                </tr>
            </tfoot>
        </table>

        <!-- INSTRUCTIONS & NOTES -->
        <div class="box" style="margin-bottom: 25px;">
            <div class="box-title">Pernyataan & Catatan Logistik</div>
            <p style="font-size: 11px; color: #334155; line-height: 1.5;">
                Barang diserahkan dalam kondisi baik, tertutup terpal pelindung hujan, dan berat sesuai dengan timbangan resmi NATOBIN.
                Mohon dilakukan pemeriksaan kemasan dan penandatanganan berita acara penerimaan saat tiba di tujuan.
                <?= $do['catatan'] ? '<br><strong>Catatan Tambahan:</strong> ' . htmlspecialchars($do['catatan']) : '' ?>
            </p>
        </div>

        <!-- 4 SIGNATURE BLOCKS -->
        <div class="signatures">
            <div class="sig-box">
                ( ..................................... )
                <div class="sig-role">Penerima Barang / Klien</div>
            </div>
            <div class="sig-box">
                ( <?= htmlspecialchars($do['nama_sopir']) ?> )
                <div class="sig-role">Pengemudi / Sopir Armada</div>
            </div>
            <div class="sig-box">
                ( <?= htmlspecialchars($do['petugas_logistik_nama'] ?: 'Petugas Muat') ?> )
                <div class="sig-role">Petugas Muat Gudang</div>
            </div>
            <div class="sig-box">
                ( Cipto / Logistik )
                <div class="sig-role">Kepala Distribusi & Gudang</div>
            </div>
        </div>
    </div>

</body>
</html>
