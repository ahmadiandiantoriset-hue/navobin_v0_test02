<?php
/**
 * NATOBIN INTERNATIONAL - Printable Invoice / Faktur Penagihan
 * Commercial Invoice with shredding fees, sack ownership charges, and banking details.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$id = (int)($_GET['id'] ?? 0);
$pdo = getDB();

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
    die("Dokumen Invoice tidak ditemukan.");
}

function terbilang($nilai): string {
    $nilai = abs($nilai);
    $huruf = ["", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas"];
    $temp = "";
    if ($nilai < 12) {
        $temp = " " . $huruf[$nilai];
    } else if ($nilai < 20) {
        $temp = terbilang($nilai - 10) . " Belas";
    } else if ($nilai < 100) {
        $temp = terbilang(floor($nilai / 10)) . " Puluh" . terbilang($nilai % 10);
    } else if ($nilai < 200) {
        $temp = " Seratus" . terbilang($nilai - 100);
    } else if ($nilai < 1000) {
        $temp = terbilang(floor($nilai / 100)) . " Ratus" . terbilang($nilai % 100);
    } else if ($nilai < 2000) {
        $temp = " Seribu" . terbilang($nilai - 1000);
    } else if ($nilai < 1000000) {
        $temp = terbilang(floor($nilai / 1000)) . " Ribu" . terbilang($nilai % 1000);
    } else if ($nilai < 1000000000) {
        $temp = terbilang(floor($nilai / 1000000)) . " Juta" . terbilang($nilai % 1000000);
    } else if ($nilai < 1000000000000) {
        $temp = terbilang(floor($nilai / 1000000000)) . " Milyar" . terbilang(fmod($nilai, 1000000000));
    }
    return trim($temp);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?= htmlspecialchars($inv['no_invoice']) ?> - NATOBIN</title>
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
        .doc-badge h2 { font-size: 18px; font-weight: 800; color: #b91c1c; text-transform: uppercase; }
        .doc-badge .code { font-family: monospace; font-size: 16px; font-weight: 800; color: #0f172a; background: #f8fafc; border: 1px dashed #94a3b8; padding: 4px 10px; display: inline-block; margin-top: 4px; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .box { border: 1px solid #e2e8f0; border-radius: 4px; padding: 12px 14px; background: #fafafa; }
        .box-title { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .data-row { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 5px; }
        .data-label { color: #64748b; font-weight: 500; }
        .data-val { font-weight: 600; color: #0f172a; text-align: right; }

        table.tbl { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 12px; }
        table.tbl th { background: #0f172a; color: #fff; text-transform: uppercase; font-size: 11px; padding: 8px 10px; text-align: left; }
        table.tbl td { border: 1px solid #e2e8f0; padding: 8px 10px; }
        table.tbl tr:nth-child(even) { background: #f8fafc; }

        .totals-table { width: 50%; margin-left: auto; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }
        .totals-table td { padding: 6px 10px; }
        .totals-table tr.grand-total { background: #0f172a; color: #fff; font-size: 14px; font-weight: 800; }

        .terbilang-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px 14px; margin-bottom: 20px; font-size: 11px; font-style: italic; color: #334155; }

        .payment-info { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 20px; margin-top: 15px; }
        .bank-box { border: 1px solid #cbd5e1; border-radius: 4px; padding: 12px; background: #f8fafc; font-size: 11.5px; }
        .bank-name { font-weight: 800; color: #0369a1; font-size: 13px; margin-bottom: 4px; }

        .signatures { text-align: center; }
        .sig-box { border-top: 1px dashed #94a3b8; padding-top: 60px; font-size: 12px; font-weight: 600; color: #0f172a; }
        .sig-role { font-size: 11px; color: #64748b; font-weight: 400; margin-top: 2px; }

        .btn-print {
            position: fixed; bottom: 25px; right: 25px; background: #b91c1c; color: #fff;
            padding: 12px 24px; border: none; border-radius: 6px; font-weight: 700; cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25); display: flex; align-items: center; gap: 8px; font-size: 14px;
        }
        .btn-print:hover { background: #991b1b; }

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
        Cetak Faktur / Invoice (A4)
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
                <h2>FAKTUR PENAGIHAN</h2>
                <div class="code"><?= htmlspecialchars($inv['no_invoice']) ?></div>
            </div>
        </div>

        <!-- TWO COLUMN DETAILS -->
        <div class="grid-2">
            <div class="box">
                <div class="box-title">Ditagihkan Kepada (Klien)</div>
                <div class="data-row">
                    <span class="data-label">Nama Klien:</span>
                    <span class="data-val"><?= htmlspecialchars($inv['nama_customer']) ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Kode Klien:</span>
                    <span class="data-val"><?= htmlspecialchars($inv['kode_customer']) ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Telepon:</span>
                    <span class="data-val"><?= htmlspecialchars($inv['customer_telepon'] ?: '-') ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Alamat:</span>
                    <span class="data-val" style="max-width: 65%;"><?= htmlspecialchars($inv['customer_alamat'] ?: '-') ?></span>
                </div>
            </div>

            <div class="box">
                <div class="box-title">Metadata Tagihan & Referensi</div>
                <div class="data-row">
                    <span class="data-label">Tanggal Terbit:</span>
                    <span class="data-val"><?= date('d F Y', strtotime($inv['tanggal_invoice'])) ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Jatuh Tempo:</span>
                    <span class="data-val" style="color: #b91c1c; font-weight: 700;"><?= date('d F Y', strtotime($inv['jatuh_tempo'])) ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Ref Surat Jalan (DO):</span>
                    <span class="data-val"><strong><?= htmlspecialchars($inv['no_surat_jalan']) ?></strong></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Status Bayar:</span>
                    <span class="data-val" style="color: <?= $inv['status_pembayaran'] === 'LUNAS' ? '#16a34a' : '#ea580c' ?>; font-weight: 800;">
                        <?= htmlspecialchars($inv['status_pembayaran']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- ITEMIZED INVOICE TABLE -->
        <table class="tbl">
            <thead>
                <tr>
                    <th style="width: 30px; text-align: center;">No</th>
                    <th>Deskripsi Jasa / Pengadaan</th>
                    <th style="text-align: center;">Volume</th>
                    <th style="text-align: right;">Tarif Satuan (Rp)</th>
                    <th style="text-align: right;">Jumlah (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="text-align: center;">1</td>
                    <td>
                        <strong>Jasa Pengolahan & Perajangan Tembakau</strong><br>
                        <small style="color: #64748b;">Batch: <?= htmlspecialchars($inv['kode_produksi'] ?: 'PRD-BATCH') ?> | Dasar: <?= htmlspecialchars($inv['metode_tarif']) ?></small>
                    </td>
                    <td style="text-align: center; font-weight: 600;">
                        <?= $inv['metode_tarif'] === 'PER_KG' ? number_format((float)$inv['total_berat_kg'], 2, ',', '.') . ' KG' : number_format((int)$inv['jumlah_sak']) . ' Sak' ?>
                    </td>
                    <td style="text-align: right;">
                        Rp <?= number_format((float)$inv['tarif_satuan'], 0, ',', '.') ?>
                    </td>
                    <td style="text-align: right; font-weight: 700;">
                        <?php 
                            $biayaJasa = $inv['metode_tarif'] === 'PER_KG' 
                                ? (float)$inv['total_berat_kg'] * (float)$inv['tarif_satuan']
                                : (int)$inv['jumlah_sak'] * (float)$inv['tarif_satuan'];
                            echo 'Rp ' . number_format($biayaJasa, 0, ',', '.');
                        ?>
                    </td>
                </tr>
                <?php if ((float)$inv['biaya_lain'] > 0 || (float)$inv['biaya_bongkar_muat'] > 0): ?>
                    <tr>
                        <td style="text-align: center;">2</td>
                        <td>
                            <strong>Pengadaan Sak / Kemasan & Biaya Operasional</strong><br>
                            <small style="color: #64748b;">Sack Ownership: <?= htmlspecialchars($inv['kepemilikan_sak'] ?: 'MILIK SENDIRI') ?> | Termasuk bongkar muat</small>
                        </td>
                        <td style="text-align: center; font-weight: 600;">
                            <?= number_format((int)$inv['jumlah_sak']) ?> Sak
                        </td>
                        <td style="text-align: right;">-</td>
                        <td style="text-align: right; font-weight: 700;">
                            Rp <?= number_format((float)$inv['biaya_lain'] + (float)$inv['biaya_bongkar_muat'], 0, ',', '.') ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- TOTALS TABLE -->
        <table class="totals-table">
            <tr>
                <td style="color: #64748b;">Subtotal Biaya:</td>
                <td style="text-align: right; font-weight: 700;">Rp <?= number_format((float)$inv['subtotal'], 0, ',', '.') ?></td>
            </tr>
            <tr>
                <td style="color: #64748b;">Pajak Pertambahan Nilai (PPN):</td>
                <td style="text-align: right; font-weight: 700;">Rp <?= number_format((float)$inv['pajak_ppn'], 0, ',', '.') ?></td>
            </tr>
            <tr class="grand-total">
                <td>TOTAL TAGIHAN:</td>
                <td style="text-align: right;">Rp <?= number_format((float)$inv['total_tagihan'], 0, ',', '.') ?></td>
            </tr>
        </table>

        <!-- TERBILANG -->
        <div class="terbilang-box">
            <strong>Terbilang:</strong> <?= terbilang((int)$inv['total_tagihan']) ?> Rupiah
        </div>

        <!-- PAYMENT INFO & SIGNATURE -->
        <div class="payment-info">
            <div class="bank-box">
                <div class="bank-name">INSTRUKSI PEMBAYARAN TRANSFER BANK</div>
                <div style="margin-bottom: 4px;"><strong>Bank Mandiri Cabang Jember</strong></div>
                <div style="margin-bottom: 4px;">No. Rekening: <strong>143-00-2918820-1</strong></div>
                <div style="margin-bottom: 6px;">Atas Nama: <strong>PT NATOBIN INTERNATIONAL</strong></div>
                <p style="font-size: 10.5px; color: #64748b;">*Mohon sertakan nomor invoice (<?= htmlspecialchars($inv['no_invoice']) ?>) pada berita transfer.</p>
            </div>

            <div class="signatures">
                <div class="sig-box">
                    ( <?= htmlspecialchars($inv['petugas_keuangan_nama'] ?: 'Tiara - Finance') ?> )
                    <div class="sig-role">Finance & Billing Department</div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
