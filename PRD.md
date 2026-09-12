# Product Requirements Document (PRD)
## Sistem ERP untuk Bisnis Retail/Dagang Skala Menengah

| | |
|---|---|
| **Versi Dokumen** | 1.0 |
| **Tanggal** | 03 September 2026 |
| **Status** | Draft |
| **Tech Stack** | Laravel + MySQL |
| **Target Pengguna** | Retail/dagang skala menengah, multi-cabang |

---

## 1. Ringkasan Eksekutif

### 1.1 Latar Belakang
Bisnis retail/dagang skala menengah membutuhkan sistem terintegrasi untuk mengelola alur bisnis dari pembelian barang ke supplier, pengelolaan stok multi-gudang, penjualan (offline/POS maupun online), hingga pelaporan keuangan. Sistem ERP ini dibangun untuk menggantikan proses manual/spreadsheet dan sistem yang terfragmentasi.

### 1.2 Tujuan Produk
- Mengintegrasikan proses pembelian, inventori, penjualan, dan keuangan dalam satu sistem.
- Menyediakan visibilitas stok real-time di seluruh cabang/gudang.
- Menyediakan pelaporan keuangan dan operasional yang akurat dan real-time.
- Mendukung operasional multi-cabang dengan kontrol akses berbasis peran (role-based access).

### 1.3 Ruang Lingkup (Scope)
Termasuk dalam versi awal (MVP + Fase 2):
- Master Data (Produk, Supplier, Pelanggan)
- Inventori & Gudang
- Pembelian (Procurement)
- Penjualan & POS
- Keuangan & Akuntansi dasar
- CRM sederhana
- Distribusi & Logistik
- Laporan & Dashboard

Di luar lingkup (fase mendatang):
- HR & Payroll penuh
- Integrasi marketplace otomatis (Tokopedia/Shopee API)
- Payment gateway
- Manajemen konsinyasi lanjutan

### 1.4 Definisi & Istilah
| Istilah | Keterangan |
|---|---|
| SKU | Stock Keeping Unit, kode unik produk |
| PO | Purchase Order |
| SO | Sales Order |
| POS | Point of Sale |
| GRN | Goods Receipt Note (bukti penerimaan barang) |
| RBAC | Role-Based Access Control |

---

## 2. Tujuan Bisnis & Metrik Keberhasilan

| Tujuan Bisnis | Metrik / KPI |
|---|---|
| Mengurangi selisih stok | Selisih stok opname < 1% per periode |
| Mempercepat proses transaksi kasir | Waktu transaksi POS < 30 detik/transaksi |
| Akurasi laporan keuangan | Rekonsiliasi otomatis 100% dari transaksi sistem |
| Visibilitas stok antar cabang | Update stok real-time (< 5 detik delay) |
| Efisiensi pengadaan | Waktu proses PO ke approval < 1 hari kerja |

---

## 3. Aktor & Peran Pengguna (User Roles)

| Role | Deskripsi Akses |
|---|---|
| **Super Admin** | Akses penuh ke seluruh modul & konfigurasi sistem |
| **Admin Cabang** | Kelola operasional 1 cabang (stok, penjualan, laporan cabang) |
| **Kasir (Cashier)** | Akses modul POS/penjualan saja |
| **Staff Gudang** | Kelola stok, penerimaan barang, stock opname |
| **Staff Pembelian (Purchasing)** | Kelola PO, supplier, penerimaan barang |
| **Staff Keuangan (Finance)** | Kelola AP/AR, laporan keuangan, rekonsiliasi |
| **Sales/Marketing** | Kelola data pelanggan, promo, laporan penjualan |
| **Owner/Manajemen** | Akses dashboard & laporan (read-only, lintas cabang) |

---

## 4. Modul & Kebutuhan Fungsional

### 4.1 Modul Master Data

**Deskripsi:** Modul dasar yang menjadi rujukan seluruh modul lain (produk, supplier, pelanggan, gudang/cabang).

**User Stories:**
- Sebagai Admin, saya ingin menambah/mengedit data produk agar katalog selalu up to date.
- Sebagai Admin, saya ingin mengelola data supplier dan pelanggan agar transaksi tercatat dengan benar.

**Fitur:**
| Fitur | Deskripsi | Prioritas |
|---|---|---|
| Master Produk | CRUD produk: nama, SKU, kategori, brand, satuan, harga beli/jual, gambar | Must Have |
| Kategori & Sub-kategori Produk | Pengelompokan produk hierarkis | Must Have |
| Multi-unit Konversi | Konversi satuan, misal 1 dus = 12 pcs | Must Have |
| Barcode/QR Generator | Generate & cetak barcode per SKU | Must Have |
| Harga Bertingkat | Harga retail, grosir, member, per cabang | Should Have |
| Master Supplier | CRUD data supplier (kontak, alamat, termin bayar) | Must Have |
| Master Pelanggan | CRUD data pelanggan (kontak, alamat, tipe: umum/member/reseller) | Must Have |
| Master Gudang/Cabang | CRUD data gudang dan cabang toko | Must Have |
| Import/Export Data | Import master data via Excel/CSV | Should Have |

**Kebutuhan Non-Fungsional:**
- Validasi SKU harus unik per tenant.
- Riwayat perubahan (audit trail) untuk perubahan harga.

---

### 4.2 Modul Inventori & Gudang

**Deskripsi:** Mengelola stok barang secara real-time di seluruh gudang/cabang.

**User Stories:**
- Sebagai Staff Gudang, saya ingin melihat stok real-time per gudang agar bisa memutuskan kapan harus restock.
- Sebagai Staff Gudang, saya ingin melakukan stock opname agar data sistem sesuai kondisi fisik.

**Fitur:**
| Fitur | Deskripsi | Prioritas |
|---|---|---|
| Stok Real-time Multi-gudang | Tampilan stok per lokasi, update otomatis dari transaksi | Must Have |
| Stock Transfer Antar Gudang | Perpindahan stok antar cabang dengan approval | Must Have |
| Stock Opname | Pencatatan stok fisik vs sistem, penyesuaian (adjustment) | Must Have |
| Reorder Point & Alert | Notifikasi otomatis saat stok di bawah ambang batas | Should Have |
| Batch/Expiry Tracking | Pelacakan nomor batch dan tanggal kedaluwarsa | Should Have |
| Kartu Stok (Stock Card) | Riwayat pergerakan stok per produk (in/out) | Must Have |
| Riwayat Mutasi Stok | Log seluruh transaksi yang mempengaruhi stok | Must Have |

**Business Rules:**
- Stok tidak boleh minus (kecuali fitur back-order diaktifkan).
- Setiap perubahan stok wajib tercatat di tabel mutasi stok dengan referensi transaksi.

---

### 4.3 Modul Pembelian (Procurement)

**Deskripsi:** Mengelola proses pengadaan barang dari supplier.

**User Stories:**
- Sebagai Staff Purchasing, saya ingin membuat PO ke supplier agar stok dapat direstock tepat waktu.
- Sebagai Staff Gudang, saya ingin mencatat penerimaan barang berdasarkan PO agar stok otomatis bertambah.

**Fitur:**
| Fitur | Deskripsi | Prioritas |
|---|---|---|
| Purchase Order (PO) | Buat, edit, approval PO ke supplier | Must Have |
| Approval Workflow PO | Alur persetujuan berjenjang berdasarkan nominal | Should Have |
| Penerimaan Barang (GRN) | Pencatatan barang masuk & matching dengan PO | Must Have |
| Retur Pembelian | Pengembalian barang ke supplier (rusak/tidak sesuai) | Must Have |
| Riwayat Pembelian per Supplier | Histori transaksi & performa supplier | Should Have |
| Hutang Supplier (AP) | Otomatis membentuk hutang dari PO yang diterima | Must Have |

**Business Rules:**
- PO harus melalui status: Draft → Diajukan → Disetujui → Dikirim ke Supplier → Diterima (sebagian/penuh) → Selesai.
- Penerimaan barang parsial diperbolehkan (partial receipt).

---

### 4.4 Modul Penjualan & POS

**Deskripsi:** Mengelola transaksi penjualan baik di toko fisik (POS) maupun order manual/online.

**User Stories:**
- Sebagai Kasir, saya ingin memproses transaksi penjualan dengan cepat menggunakan scan barcode.
- Sebagai Admin, saya ingin memberikan diskon per produk/kategori/pelanggan.

**Fitur:**
| Fitur | Deskripsi | Prioritas |
|---|---|---|
| POS (Point of Sale) | Interface kasir: scan barcode, hitung total, cetak struk | Must Have |
| Sales Order & Invoicing | Order non-POS (B2B/reseller) dengan invoice | Must Have |
| Metode Pembayaran | Tunai, transfer, kartu, split payment | Must Have |
| Diskon & Promo | Diskon per produk/kategori/pelanggan, promo periode tertentu | Should Have |
| Retur Penjualan | Pengembalian barang dari pelanggan | Must Have |
| Multi-channel Ready | Struktur data siap untuk integrasi channel online (fase 2) | Could Have |
| Cetak Struk/Invoice | Cetak fisik & PDF | Must Have |
| Shift Kasir | Buka/tutup shift, rekap kas per shift | Should Have |

**Business Rules:**
- Setiap transaksi penjualan otomatis mengurangi stok dan mencatat mutasi.
- Retur penjualan otomatis mengembalikan stok (jika kondisi barang baik).

---

### 4.5 Modul Keuangan & Akuntansi

**Deskripsi:** Mencatat seluruh transaksi keuangan yang berasal dari modul pembelian dan penjualan, serta pengelolaan kas/bank.

**User Stories:**
- Sebagai Staff Finance, saya ingin melihat hutang ke supplier dan piutang dari pelanggan agar cash flow terkontrol.
- Sebagai Owner, saya ingin melihat laporan laba rugi per cabang.

**Fitur:**
| Fitur | Deskripsi | Prioritas |
|---|---|---|
| Accounts Payable (AP) | Kelola hutang ke supplier, jadwal pembayaran | Must Have |
| Accounts Receivable (AR) | Kelola piutang dari pelanggan/reseller | Must Have |
| Kas & Bank | Pencatatan kas masuk/keluar, mutasi bank | Must Have |
| Jurnal Otomatis | Jurnal otomatis dari transaksi penjualan/pembelian | Should Have |
| Laporan Laba Rugi | Per cabang, per produk, per periode | Must Have |
| Laporan Neraca | Ringkasan aset, kewajiban, ekuitas | Should Have |
| Rekonsiliasi Bank | Pencocokan transaksi sistem vs mutasi bank | Could Have |

**Business Rules:**
- Setiap transaksi penjualan/pembelian membentuk jurnal otomatis (double-entry).
- Laporan keuangan harus bisa difilter per cabang dan per periode.

---

### 4.6 Modul CRM Sederhana

**Fitur:**
| Fitur | Deskripsi | Prioritas |
|---|---|---|
| Database Pelanggan | Data & histori transaksi pelanggan | Must Have |
| Program Loyalitas/Member | Poin, tier member, diskon khusus | Should Have |
| Piutang Pelanggan | Untuk pelanggan B2B/reseller dengan termin bayar | Must Have |
| Riwayat Komunikasi | Catatan interaksi dengan pelanggan | Could Have |

---

### 4.7 Modul Distribusi & Logistik

**Fitur:**
| Fitur | Deskripsi | Prioritas |
|---|---|---|
| Manajemen Pengiriman | Buat surat jalan, status pengiriman | Should Have |
| Tracking Status Kirim | Update status: diproses, dikirim, diterima | Should Have |
| Manajemen Kurir/Ekspedisi | Data kurir internal/eksternal | Could Have |

---

### 4.8 Modul Laporan & Dashboard

**Fitur:**
| Fitur | Deskripsi | Prioritas |
|---|---|---|
| Dashboard Real-time | Ringkasan penjualan, stok, keuangan (per role) | Must Have |
| Laporan Penjualan | Per produk, per cabang, per sales, per periode | Must Have |
| Laporan Inventory Turnover | Perputaran stok per produk | Should Have |
| Laporan Profit Margin | Margin per produk/kategori | Should Have |
| Export Laporan | Export ke Excel/PDF | Must Have |

---

## 5. Kebutuhan Non-Fungsional

| Kategori | Kebutuhan |
|---|---|
| **Performa** | Waktu respon halaman < 2 detik untuk operasi umum; transaksi POS < 1 detik untuk simpan |
| **Skalabilitas** | Mendukung minimal 20 cabang dan 100 pengguna aktif bersamaan |
| **Keamanan** | Autentikasi berbasis token (Laravel Sanctum), enkripsi password (bcrypt), RBAC di setiap endpoint |
| **Audit Trail** | Seluruh perubahan data master & transaksi tercatat (siapa, kapan, apa yang berubah) |
| **Ketersediaan** | Uptime target 99.5% |
| **Backup & Recovery** | Backup database harian otomatis, retention 30 hari |
| **Kompatibilitas** | Web responsive (desktop untuk back-office, tablet/mobile-friendly untuk POS) |
| **Localization** | Bahasa Indonesia, format mata uang Rupiah, format tanggal Indonesia |

---

## 6. Arsitektur Teknis

### 6.1 Tech Stack
| Layer | Teknologi |
|---|---|
| Backend Framework | Laravel 11.x (PHP 8.3+) |
| Database | MySQL 8.x |
| Frontend | Laravel Blade + Livewire / Vue.js (SPA opsional untuk POS) |
| Autentikasi | Laravel Sanctum / Breeze |
| Queue & Job | Laravel Queue (database/Redis driver) untuk proses async (laporan, notifikasi) |
| Cache | Redis (opsional, untuk performa dashboard) |
| Storage File | Local storage / S3-compatible untuk gambar produk & lampiran |
| PDF/Struk | DomPDF / Laravel Snappy |
| Excel Import/Export | Laravel Excel (Maatwebsite) |
| API | RESTful API (untuk integrasi POS/mobile di masa depan) |
| Testing | PHPUnit / Pest |

### 6.2 Prinsip Arsitektur
- **Modular by Domain**: setiap modul (Inventori, Pembelian, Penjualan, Keuangan) dibangun sebagai domain terpisah menggunakan struktur folder modular (misal Laravel Modules atau DDD-lite).
- **Multi-tenant Ready (opsional)**: jika ke depan digunakan multi-perusahaan, gunakan pendekatan `company_id` di setiap tabel transaksi.
- **Event-Driven untuk Stok**: perubahan stok dipicu melalui Laravel Events & Listeners agar konsisten di seluruh modul (penjualan, pembelian, transfer, retur).
- **Soft Delete** untuk data master penting (produk, pelanggan, supplier).

### 6.3 Rancangan Skema Database (High-Level)

**Master Data:**
- `products`, `product_categories`, `product_units`, `product_prices`
- `suppliers`, `customers`, `warehouses`, `branches`

**Inventori:**
- `stocks` (stok per produk per gudang)
- `stock_movements` (log mutasi stok)
- `stock_transfers`, `stock_transfer_items`
- `stock_opnames`, `stock_opname_items`

**Pembelian:**
- `purchase_orders`, `purchase_order_items`
- `goods_receipts`, `goods_receipt_items`
- `purchase_returns`, `purchase_return_items`

**Penjualan:**
- `sales_orders`, `sales_order_items`
- `sales_transactions` (POS), `sales_transaction_items`
- `sales_returns`, `sales_return_items`
- `pos_shifts`

**Keuangan:**
- `accounts_payable`, `accounts_receivable`
- `cash_transactions`, `bank_transactions`
- `journal_entries`, `journal_entry_lines`

**Sistem:**
- `users`, `roles`, `permissions` (role_has_permissions)
- `audit_logs`

> Catatan: Skema detail (kolom, tipe data, relasi) akan disusun pada dokumen terpisah **Database Design Document** setelah PRD ini disetujui.

---

## 7. Alur Proses Bisnis Utama (High-Level Flow)

### 7.1 Alur Pembelian
```
Buat PO → Approval PO → Kirim ke Supplier → Terima Barang (GRN) 
→ Stok Bertambah → Hutang Terbentuk (AP) → Pembayaran ke Supplier
```

### 7.2 Alur Penjualan (POS)
```
Scan/Pilih Produk → Hitung Total (+diskon) → Pilih Metode Bayar 
→ Simpan Transaksi → Stok Berkurang → Cetak Struk → Jurnal Otomatis
```

### 7.3 Alur Stock Opname
```
Buat Sesi Opname → Input Stok Fisik per Produk → Sistem Hitung Selisih 
→ Approval Adjustment → Stok Sistem Disesuaikan
```

---

## 8. Prioritas Pengembangan (Roadmap)

### Fase 1 — MVP (Fondasi)
1. Modul Master Data (Produk, Supplier, Pelanggan, Gudang/Cabang)
2. Modul Inventori & Gudang (stok, mutasi, transfer)
3. Modul Pembelian (PO, penerimaan barang)
4. Modul Penjualan/POS dasar

### Fase 2 — Operasional Penuh
5. Modul Keuangan & Akuntansi dasar (AP, AR, kas/bank)
6. Modul Laporan & Dashboard
7. Stock opname & reorder alert

### Fase 3 — Penyempurnaan
8. Modul CRM (loyalitas, member)
9. Modul Distribusi & Logistik
10. Jurnal otomatis & rekonsiliasi bank
11. Approval workflow berjenjang

### Fase 4 — Ekspansi (Di Luar Scope Awal)
12. HR & Payroll
13. Integrasi marketplace
14. Payment gateway
15. Manajemen konsinyasi

---

## 9. Asumsi & Batasan (Assumptions & Constraints)

**Asumsi:**
- Sistem digunakan oleh perusahaan dengan struktur multi-cabang namun single-entity (belum multi-tenant/multi-perusahaan di MVP).
- Koneksi internet stabil tersedia di seluruh cabang untuk sinkronisasi real-time.
- Pengguna memiliki perangkat (PC/tablet) dengan browser modern.

**Batasan:**
- Belum mendukung mode offline penuh untuk POS di Fase 1 (akan dievaluasi di fase berikutnya).
- Integrasi pihak ketiga (marketplace, payment gateway) di luar scope MVP.

---

## 10. Kriteria Penerimaan (Acceptance Criteria) — Contoh per Modul

**Modul POS:**
- [ ] Kasir dapat menyelesaikan transaksi menggunakan scan barcode dalam < 30 detik.
- [ ] Stok otomatis berkurang setelah transaksi tersimpan.
- [ ] Struk dapat dicetak dan disimpan sebagai PDF.

**Modul Inventori:**
- [ ] Stok per gudang dapat dilihat real-time di dashboard.
- [ ] Stock transfer antar gudang memerlukan approval sebelum stok berpindah.
- [ ] Hasil stock opname menghasilkan laporan selisih otomatis.

**Modul Pembelian:**
- [ ] PO tidak dapat diterima barangnya tanpa status "Disetujui".
- [ ] Penerimaan barang parsial tercatat dengan benar dan sisa PO tetap terbuka.

---

## 11. Lampiran

### 11.1 Daftar Terbuka (Open Questions)
- Apakah dibutuhkan mode offline untuk POS di cabang dengan koneksi internet tidak stabil?
- Apakah harga jual berbeda per cabang atau seragam?
- Berapa jumlah cabang & estimasi transaksi harian untuk kebutuhan sizing server?

### 11.2 Riwayat Revisi
| Versi | Tanggal | Perubahan | Penulis |
|---|---|---|---|
| 1.0 | 03 Sep 2026 | Draft awal PRD | - |