# 📖 PANDUAN PENGGUNAAN & DEPLOYMENT SISTEM NATOBIN
**Pabrik Rajang & Pengolahan Tembakau • Full Traceability & Yield Tracking**

---

## 📌 DAFTAR ISI
1. [Tentang Aplikasi NATOBIN](#1-tentang-aplikasi-natobin)
2. [Panduan Deployment Hosting & Instalasi](#2-panduan-deployment-hosting--instalasi)
3. [Daftar Akun Pengguna & Matriks Hak Akses (RBAC)](#3-daftar-akun-pengguna--matriks-hak-akses-rbac)
4. [Alur Operasional Lengkap per Modul](#4-alur-operasional-lengkap-per-modul)
   - [A. Autentikasi Login & Anti-Bot](#a-autentikasi-login--anti-bot-human-check)
   - [B. Modul 1: Penerimaan Bahan Masuk (LPB & Timbangan)](#b-modul-1-penerimaan-bahan-masuk-lpb--timbangan)
   - [C. Modul 2: Produksi Rajang & Yield Tracking](#c-modul-2-produksi-rajang--yield-tracking)
   - [D. Modul 3: Pergudangan & Staging Rak](#d-modul-3-pergudangan--staging-rak)
   - [E. Modul 4: Pengiriman (Surat Jalan Outbound)](#e-modul-4-pengiriman-surat-jalan-outbound)
   - [F. Modul 5: Penagihan & Invoice](#f-modul-5-penagihan--invoice-faktur)
   - [G. Modul 6: Master Data (Khusus Admin)](#g-modul-6-master-data-khusus-admin)
   - [H. Modul 7: Audit Trail Log](#h-modul-7-audit-trail-log)
   - [I. Pengaturan Profil & Kata Sandi Mandiri](#i-pengaturan-profil--kata-sandi-mandiri)
5. [Troubleshooting & Pemecahan Masalah](#5-troubleshooting--pemecahan-masalah)

---

## 1. TENTANG APLIKASI NATOBIN
Aplikasi **NATOBIN** dirancang khusus untuk mengelola end-to-end proses industri pengolahan dan perajangan tembakau serta bahan baku non-tembakau dengan prinsip ketertelusuran penuh (*full traceability*) dari penerimaan bahan mentah, susut bobot (*shrinkage*), konversi rendemen rajang (*yield*), penataan gudang rak sak, ekspedisi surat jalan, hingga penagihan invoice.

### Teknologi yang Digunakan:
- **Backend:** CodeIgniter 4 (PHP 8.0+)
- **Database:** MySQL / MariaDB (InnoDB engine)
- **Frontend:** Single Page Application (SPA) responsive dengan Vanilla JavaScript modern, CSS Dark Theme Industrial, dan cetak dokumen terintegrasi (Print Friendly).

---

## 2. PANDUAN DEPLOYMENT HOSTING & INSTALASI

### A. Kebutuhan Server (Server Requirements)
- **PHP:** Versi 8.0 atau yang lebih tinggi (disarankan PHP 8.1 / 8.2).
- **Ekstensi PHP Wajib Aktif:**
  - `php-intl` (Internationalization)
  - `php-mbstring`
  - `php-mysqli` / `pdo_mysql`
  - `php-json`
  - `php-curl`
- **Database:** MySQL 5.7+ atau MariaDB 10.3+

### B. Langkah-Langkah Deployment ke cPanel / Hosting:
1. **Upload Berkas Aplikasi:**
   - Kompres seluruh isi folder project menjadi file `.zip`.
   - Upload dan ekstrak file `.zip` ke folder target di hosting (misal: `public_html/` atau subdomain `public_html/natobin/`).
2. **Import Database:**
   - Masuk ke menu **phpMyAdmin** pada cPanel hosting.
   - Buat database baru (misal: `u123_natobin_db`).
   - Import file SQL schema/data dari database lokal `natobin_db`.
3. **Konfigurasi Berkas `.env`:**
   Buka file `.env` di root direktori hosting dan sesuaikan konfigurasi berikut:
   ```ini
   # 1. Base URL (Sesuaikan dengan domain/subdomain hosting Anda)
   app.baseURL = 'https://domainanda.com/'

   # 2. Mode Environment (Gunakan production saat sudah live)
   CI_ENVIRONMENT = production

   # 3. Kredensial Database Hosting
   database.default.hostname = localhost
   database.default.database = u123_natobin_db
   database.default.username = u123_dbuser
   database.default.password = 'password_db_hosting_anda'
   database.default.DBDriver = MySQLi
   database.default.port     = 3306
   ```
4. **Atur Izin Akses Folder Writable:**
   Pastikan folder `writable/` memiliki permission **775** atau **777** agar sistem dapat menulis log dan session:
   ```bash
   chmod -R 775 writable/
   ```
5. **Konfigurasi Web Server (Apache .htaccess):**
   Pastikan modul `mod_rewrite` aktif dan file `.htaccess` di root direktori mengarahkan permintaan ke `index.php`.

---

## 3. DAFTAR AKUN PENGGUNA & MATRIKS HAK AKSES (RBAC)

Semua akun bawaan sistem telah di-reset dengan kata sandi seragam:
> [!IMPORTANT]
> **Kata Sandi Awal untuk Seluruh Akun:** `password123`  
> *Setiap pengguna disarankan untuk memperbarui kata sandi mandiri setelah login pertama kali.*

| No | Username | Nama Akun | Role (Hak Akses) | Tugas & Batasan Modul |
| :---: | :--- | :--- | :--- | :--- |
| 1 | **`admin`** | Administrator Utama | **ADMIN** | **Akses Penuh**: Master Data, Pengaturan User, Kontrol Log Audit Lengkap, serta seluruh modul operasional. |
| 2 | **`andre`** | Andre | **INBOUND** | **Penerimaan Bahan**: Penimbangan truk timbang masuk, pembuatan formulir LPB, cetak barcode/badge LPB. |
| 3 | **`omdol`** | Om Dol | **PRODUKSI** | **Lantai Rajang**: Pengambilan bahan LPB, form pemrosesan rajang, presensi mandor/pekerja shift, pemantauan rendemen. |
| 4 | **`gudang`** | Cipto | **GUDANG** | **Pergudangan & Logistik**: Alokasi sak ke rak/bin gudang, pembuatan Surat Jalan ekspedisi keluar, cetak Surat Jalan. |
| 5 | **`tiara`** | Tiara | **KEUANGAN** | **Finansial & Faktur**: Penerbitan invoice dari Surat Jalan, perhitungan diskon, biaya sak pabrik/klien, konfirmasi pembayaran. |
| 6 | **`direksi`**| Direksi / Manajemen | **MANAJEMEN** | **Monitoring Eksekutif**: Tinjauan performa di Dashboard, pelacakan susut/yield rajang, inspeksi Audit Trail Log. |

---

## 4. ALUR OPERASIONAL LENGKAP PER MODUL

```
[1. LPB / Timbangan Inbound]
          │ (Bahan Mentah Tembakau Masuk)
          ▼
[2. Produksi Rajang] ──► Validasi Rendemen (Yield <= 100%) & Presensi Shift
          │ (Dihasilkan Sak Tembakau Rajang)
          ▼
[3. Gudang & Staging Rak] ──► Alokasi Sak ke Rak/Bin Gudang
          │
          ▼
[4. Pengiriman / Surat Jalan] ──► Ekspedisi Pengiriman ke Klien
          │
          ▼
[5. Penagihan / Invoice] ──► Faktur Jasa Rajang + Biaya Sak + Diskon + Rekening BRI
```

---

### A. Autentikasi Login & Anti-Bot (Human Check)
1. Buka alamat aplikasi di peramban web (misal: `https://domainanda.com/login`).
2. Masukkan **Username** dan **Kata Sandi** (klik ikon mata untuk memastikan kata sandi terketik benar).
3. **Verifikasi Anti-Robot:**
   - Sistem menampilkan soal penjumlahan sederhana (misal: `7 + 5 = ?`).
   - Masukkan hasil penjumlahan (misal: `12`) pada kolom yang tersedia.
   - Jika soal kurang jelas, klik tombol **"🔄 Ganti Soal"** untuk mendapatkan soal hitungan baru.
4. Klik **"Masuk ke Sistem"**. Pengguna akan langsung diarahkan ke modul sesuai level hak aksesnya.

---

### B. Modul 1: Penerimaan Bahan Masuk (LPB & Timbangan)
*Pengguna: Inbound (`andre`) & Admin (`admin`)*

1. Klik menu **"Barang Masuk (LPB)"**.
2. Klik tombol **"+ Input LPB / Timbangan Baru"**.
3. Isi data penimbangan:
   - **Klien / Pemilik:** Pilih mitra pemilik tembakau.
   - **Varietas / Jenis Tembakau:** Ketikkan varietas tembakau (misal: *Kasturi Super*, *Virginia FC*, *Burley*).
   - **Nomor Armada & Sopir:** Catat nomor plat truk dan nama sopir ekspedisi.
   - **Berat Bruto & Tarra:** Masukkan berat kotor kendaraan dan berat kosong kendaraan untuk menghitung **Berat Netto Masuk (KG)** secara otomatis.
   - **Kadar Air Awal (%):** Catat kadar air saat bahan mentah diterima.
4. Klik **"Simpan & Terbitkan LPB"**.
5. Klik ikon **"🖨️ Cetak"** untuk mencetak lembar LPB resmi dengan barcode pelacakan.

---

### C. Modul 2: Produksi Rajang & Yield Tracking
*Pengguna: Produksi (`omdol`) & Admin (`admin`)*

1. Klik menu **"Produksi Rajang"**.
2. Klik tombol **"+ Buat Batch Pemrosesan Rajang"**.
3. **Pilih Bahan Baku:** Pilih batch LPB yang akan diproses rajang.
4. **Isi Parameter Pemrosesan:**
   - **Target Netto Per Sak (KG):** Standar bobot per karung/sak (misal: 40 KG).
   - **Jumlah Sak Dihasilkan:** Input berapa sak hasil rajangan yang didapat.
   - **Kepemilikan Sak:** Pilih apakah sak disediakan oleh **PABRIK (Milik Sendiri)** atau **CLIENT (Disediakan Klien)**.
5. **Validasi Rendemen (Yield %):**
   - Sistem otomatis menghitung persentase rendemen: $\text{Rendemen} = \frac{\text{Total Output (KG)}}{\text{Bahan Masuk (KG)}} \times 100\%$.
   > [!WARNING]
   > **Validasi Keamanan:** Jika rendemen terhitung **lebih dari 100%**, sistem akan memblokir penyimpanan dan memunculkan pop-up peringatan agar mandor memeriksa kembali isian jumlah sak dan target netto.
6. **Presensi Tenaga Kerja Shift:**
   - Pilih nama pekerja yang bertugas pada shift tersebut.
   - Role aktivitas presensi hanya tersedia untuk kategori: **RAJANG** dan **LAINNYA**.
7. Klik **"Proses & Simpan Batch"**.

---

### D. Modul 3: Pergudangan & Staging Rak
*Pengguna: Gudang (`gudang`) & Admin (`admin`)*

1. Klik menu **"Gudang Sak Tembakau"**.
2. Periksa daftar sak hasil rajang yang belum ditempatkan (*Unassigned*).
3. Klik tombol **"Alokasikan Rak"** pada baris batch sak terkait.
4. Pilih **Rak Gudang & Bin** yang masih memiliki kapasitas sisa.
5. Sistem akan mencatat lokasi fisik sak (misal: `RAK-A1-BIN-03`) sehingga mempermudah pencarian saat proses muat ekspedisi (*outbound*).

---

### E. Modul 4: Pengiriman (Surat Jalan Outbound)
*Pengguna: Gudang (`gudang`), Inbound (`andre`), & Admin (`admin`)*

1. Klik menu **"Pengiriman (Surat Jalan)"**.
2. Klik tombol **"+ Terbitkan Surat Jalan Baru"**.
3. Pilih nomor batch produksi atau nomor sak yang akan dikirim ke klien.
4. Isi data ekspedisi:
   - Nama sopir pembawa muatan.
   - Nomor plat kendaraan pengangkut.
   - Tanggal dan jam keberangkatan truk.
5. Klik **"Terbitkan Surat Jalan"**.
6. Klik tombol **"🖨️ Cetak Surat Jalan"** untuk mencetak dokumen sah pengawalan barang di perjalanan.

---

### F. Modul 5: Penagihan & Invoice (Faktur)
*Pengguna: Keuangan (`tiara`) & Admin (`admin`)*

1. Masuk ke modul **"Pengiriman (Surat Jalan)"** atau **"Faktur & Penagihan"**.
2. Pada baris Surat Jalan yang telah terbit, klik tombol **"Aksi ➔ Buat Invoice"**.
3. **Formulir Invoice Penagihan Otomatis Menyesuaikan:**
   - **Biaya Jasa Rajang:** Dihitung dari bobot netto tembakau $\times$ tarif jasa per KG.
   - **Biaya Sak Pabrik:**
     - *Jika sak tercatat Milik Pabrik:* Isian biaya sak aktif dan otomatis terhitung ke total tagihan.
     - *Jika sak tercatat Milik Client:* Isian biaya sak otomatis dinonaktifkan (Rp 0).
   - **Isian Diskon:** Anda dapat menginput nominal potongan diskon khusus secara manual jika terdapat kesepakatan potongan harga.
   - **Pilihan Metode Pembayaran:**
     - **TUNAI:** Transaksi pembayaran langsung di kasir pabrik.
     - **TRANSFER:** Sistem otomatis mencantumkan detail rekening resmi pada invoice:
       > **PT. BANK RAKYAT INDONESIA, Tbk.**  
       > Nama Rekening: **Ismu Handoko**  
       > Nomor Rekening: **0013 0100 3554 300**
4. Klik **"Terbitkan Faktur"** dan cetak invoice resmi untuk diserahkan ke klien.

---

### G. Modul 6: Master Data (Khusus Admin)
*Pengguna: Administrator (`admin`)*

Modul ini digunakan untuk mengelola data acuan utama sistem (CRUD - *Create, Read, Update, Delete*):
1. **Klien / Pelanggan:** Tambah, edit, dan kelola data perusahaan/pemilik tembakau.
2. **Rak & Bin Gudang:** Konfigurasi kapasitas dan penamaan rak penyimpanan sak.
3. **Tenaga Kerja Rajang:** Master data pekerja lantai produksi, operator mesin rajang, dan mandor.
4. **Akun Pengguna:** Membuat akun staf baru, mengubah peran hak akses (*role*), atau memblokir akses pengguna nonaktif.

---

### H. Modul 7: Audit Trail Log
*Pengguna: Semua Role (Dengan Filter Proteksi Akses)*

- Sistem secara otomatis mencatat setiap aktivitas penting: **LOGIN, LOGOUT, TAMBAH DATA, EDIT, HAPUS, CETAK DOKUMEN**.
- **Privasi & Keamanan Hak Akses:**
  - Akun **ADMIN** & **MANAJEMEN**: Dapat melihat seluruh riwayat audit transaksi pabrik tanpa batas.
  - Akun staf operasional (**INBOUND, PRODUKSI, GUDANG, KEUANGAN**): Hanya dapat melihat catatan riwayat yang berasal dari divisi/role mereka sendiri.

---

### I. Pengaturan Profil & Kata Sandi Mandiri
*Pengguna: Seluruh Akun Pengguna*

1. Di pojok kiri bawah (tepat di atas tombol *Keluar/Logout*), klik pada **kotak nama pengguna dan hak akses Anda**.
2. Popup modal dialog **"Pengaturan Profil & Kata Sandi Akun"** akan terbuka.
3. Anda dapat:
   - Memperbarui alamat email korespondensi akun Anda.
   - Mengubah kata sandi: Masukkan *"Kata Sandi Saat Ini"*, *"Kata Sandi Baru"*, dan *"Konfirmasi Kata Sandi Baru"*.
   - Gunakan ikon **mata (👁️)** di samping kolom input untuk memastikan kata sandi baru tidak salah ketik.
4. Klik **"Simpan Perubahan Profil"**.

---

## 5. TROUBLESHOOTING & PEMECAHAN MASALAH

### Q1: Halaman memunculkan pesan "Whoops! We seem to have hit a snag."
- **Penyebab:** Kesalahan koneksi database atau permission folder `writable`.
- **Solusi:**
  1. Pastikan detail database di `.env` sudah sesuai dengan user dan password database hosting Anda.
  2. Pastikan permission folder `writable/` disetel ke `775` atau `777`.

### Q2: Saat login muncul "Verifikasi keamanan gagal: Jawaban hitungan salah."
- **Penyebab:** Hasil penjumlahan matematika anti-bot salah terhitung atau sesi captcha expired.
- **Solusi:** Klik tombol **"🔄 Ganti Soal"**, masukkan hasil hitungan baru yang tampil, lalu klik masuk kembali.

### Q3: Tampilan visual CSS atau fungsi tombol tidak merespons setelah upload hosting.
- **Penyebab:** Konfigurasi `app.baseURL` di `.env` belum sesuai dengan URL domain Anda.
- **Solusi:** Buka berkas `.env` dan pastikan baris `app.baseURL = 'https://nama-domain-anda.com/'` diakhiri dengan garis miring (*slash* `/`). Lakukan hard-refresh browser (`Ctrl + F5`).

### Q4: Lupa kata sandi akun Admin / Pengguna.
- **Solusi:** Buka phpMyAdmin di cPanel hosting Anda, buka tabel `users`, dan perbarui kolom `password` akun terkait dengan hash bcrypt baru atau hubungi administrator database.

---
*Dokumentasi ini disusun khusus untuk deployment dan operasional sistem NATOBIN v1.*  
*Trusted Partner for Tobacco & Non-Tobacco Raw Materials.*
