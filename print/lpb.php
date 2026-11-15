<?php
/**
 * NATOBIN INTERNATIONAL - Printable Lembar Penerimaan Barang (LPB)
 * Standard A4 Layout with Company Header, Barcode, Details, and Signatures.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$id = (int)($_GET['id'] ?? 0);
$pdo = getDB();

$stmt = $pdo->prepare("
    SELECT 
        b.*, 
        c.nama_customer, c.kode_customer, c.telepon AS customer_telepon, c.alamat AS customer_alamat,
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
$lpb = $stmt->fetch();

if (!$lpb) {
    die("Dokumen LPB tidak ditemukan.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LPB - <?= htmlspecialchars($lpb['no_badge']) ?> - NATOBIN INTERNATIONAL</title>
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
        .doc-badge h2 { font-size: 16px; font-weight: 700; color: #b91c1c; text-transform: uppercase; }
        .doc-badge .code { font-family: monospace; font-size: 16px; font-weight: 800; color: #0f172a; background: #f8fafc; border: 1px dashed #94a3b8; padding: 4px 10px; display: inline-block; margin-top: 4px; }
        
        .barcode-strip {
            display: flex; justify-content: space-between; align-items: center;
            background: #f8fafc; border: 1px solid #e2e8f0; padding: 8px 12px; margin-bottom: 20px; font-size: 11px;
        }
        .barcode-bars {
            font-family: monospace; font-size: 22px; letter-spacing: 4px; font-weight: 900;
        }

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

        .signatures { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 30px; text-align: center; }
        .sig-box { border-top: 1px dashed #94a3b8; padding-top: 60px; font-size: 12px; font-weight: 600; color: #0f172a; }
        .sig-role { font-size: 11px; color: #64748b; font-weight: 400; margin-top: 2px; }

        .btn-print {
            position: fixed; bottom: 25px; right: 25px; background: #0f172a; color: #fff;
            padding: 12px 24px; border: none; border-radius: 6px; font-weight: 700; cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25); display: flex; align-items: center; gap: 8px; font-size: 14px;
        }
        .btn-print:hover { background: #1e293b; }

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
        Cetak Dokumen LPB (A4)
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
                <h2>Lembar Penerimaan Barang</h2>
                <div class="code"><?= htmlspecialchars($lpb['no_badge']) ?></div>
            </div>
        </div>

        <!-- BARCODE & TIMESTAMP STRIP -->
        <div class="barcode-strip">
            <div>
                <strong>TANGGAL MASUK:</strong> <?= date('d M Y, H:i', strtotime($lpb['tanggal_masuk'])) ?> WIB
            </div>
            <div class="barcode-bars">
                ||| | |||| | ||| |||| |
            </div>
            <div>
                <strong>STATUS:</strong> <?= htmlspecialchars($lpb['status_lpb']) ?>
            </div>
        </div>

        <!-- TWO COLUMN DETAILS -->
        <div class="grid-2">
            <div class="box">
                <div class="box-title">Informasi Pengirim / Klien</div>
                <div class="data-row">
                    <span class="data-label">Nama Klien:</span>
                    <span class="data-val"><?= htmlspecialchars($lpb['nama_customer']) ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Kode Klien:</span>
                    <span class="data-val"><?= htmlspecialchars($lpb['kode_customer']) ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">No. Telepon:</span>
                    <span class="data-val"><?= htmlspecialchars($lpb['customer_telepon'] ?: '-') ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Alamat:</span>
                    <span class="data-val" style="max-width: 60%;"><?= htmlspecialchars($lpb['customer_alamat'] ?: '-') ?></span>
                </div>
            </div>

            <div class="box">
                <div class="box-title">Logistik & Angkutan Inbound</div>
                <div class="data-row">
                    <span class="data-label">No. Plat Truk:</span>
                    <span class="data-val"><?= htmlspecialchars($lpb['plat_nomor_truk'] ?: 'N/A') ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Nama Sopir:</span>
                    <span class="data-val"><?= htmlspecialchars($lpb['nama_sopir_inbound'] ?: 'N/A') ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Surat Jalan Klien:</span>
                    <span class="data-val"><?= htmlspecialchars($lpb['surat_jalan_ref'] ?: '-') ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Petugas Timbang:</span>
                    <span class="data-val"><?= htmlspecialchars($lpb['petugas_nama'] ?: 'Petugas Gudang') ?></span>
                </div>
            </div>
        </div>

        <!-- GOODS SPECIFICATION TABLE -->
        <table class="tbl">
            <thead>
                <tr>
                    <th>Komoditas Tembakau</th>
                    <th style="text-align: center;">Kemasan (Kemasan)</th>
                    <th style="text-align: right;">Berat Bruto (KG)</th>
                    <th style="text-align: right;">Telah Dirajang (KG)</th>
                    <th style="text-align: right;">Sisa Bahan (KG)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($lpb['jenis_tembakau']) ?></strong><br>
                        <small style="color: #64748b;">Estimasi Target Selesai (SLA): <?= date('d M Y', strtotime($lpb['estimasi_sla'])) ?></small>
                    </td>
                    <td style="text-align: center; font-weight: 700;">
                        <?= number_format((int)$lpb['jumlah_kemasan']) ?> <?= htmlspecialchars($lpb['tipe_kemasan']) ?>
                    </td>
                    <td style="text-align: right; font-weight: 800; font-size: 14px;">
                        <?= number_format((float)$lpb['berat_bruto_kg'], 2, ',', '.') ?> KG
                    </td>
                    <td style="text-align: right; color: #0284c7; font-weight: 700;">
                        <?= number_format((float)$lpb['total_used_kg'], 2, ',', '.') ?> KG
                    </td>
                    <td style="text-align: right; color: #16a34a; font-weight: 800;">
                        <?= number_format((float)$lpb['remaining_kg'], 2, ',', '.') ?> KG
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- NOTES -->
        <div class="box" style="margin-bottom: 25px;">
            <div class="box-title">Catatan Khusus Penerimaan</div>
            <p style="font-size: 11px; color: #334155; line-height: 1.5;">
                <?= nl2br(htmlspecialchars($lpb['keterangan'] ?: 'Kondisi kemasan utuh, kering, tidak berjamur. Ditimbang dengan timbangan digital terkalibrasi pabrik NATOBIN.')) ?>
            </p>
        </div>

        <!-- 3 SIGNATURE BLOCKS -->
        <div class="signatures">
            <div class="sig-box">
                ( <?= htmlspecialchars($lpb['nama_sopir_inbound'] ?: 'Sopir Pengantar') ?> )
                <div class="sig-role">Sopir / Ekspedisi Klien</div>
            </div>
            <div class="sig-box">
                ( <?= htmlspecialchars($lpb['petugas_nama'] ?: 'Petugas Timbang') ?> )
                <div class="sig-role">Petugas Timbang Inbound</div>
            </div>
            <div class="sig-box">
                ( Cipto / Supervisor )
                <div class="sig-role">Kepala Gudang & Logistik</div>
            </div>
        </div>
    </div>

</body>
</html>
