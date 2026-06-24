# ControlHub Workshop — Overview Aplikasi (Bengkel Truk)

> Dokumen konsep (v0.2). Domain: **bengkel kendaraan berat / truk** dengan
> **gudang sparepart** terintegrasi. Bagian **Asumsi & Konfirmasi** di akhir
> berisi hal-hal yang perlu Anda validasi.

---

## 1. Ringkasan

**ControlHub Workshop** adalah aplikasi manajemen **bengkel truk (kendaraan berat)**
yang menyatukan seluruh operasional bengkel **dan gudang sparepart** dalam satu
sistem: penerimaan unit, perintah kerja (work order), pengelolaan mekanik, servis
berkala armada, manajemen gudang & pembelian suku cadang, hingga pencatatan biaya.

> **Mode operasi saat ini: INTERNAL** — bengkel in-house yang merawat **armada milik
> sendiri**, fokus pada **pencatatan biaya (cost)** per unit. Fitur **penjualan & invoice
> ke customer disiapkan tapi belum diaktifkan** (lihat `MODULES.md` §0). Karakteristik
> "B2B / penagihan armada" di bawah berlaku untuk **fase future** saat penjualan diaktifkan.

Karakteristik khusus bengkel truk yang diakomodasi:
- **Pelanggan armada (B2B)** — perusahaan logistik/ekspedisi dengan banyak unit,
  kontrak servis, dan penagihan terkonsolidasi (mis. bulanan / per termin).
- **Servis berkala berbasis KM / jam operasi** (preventive maintenance) per unit.
- **Sparepart truk** yang besar, mahal, sering bergaransi & ber-nomor seri —
  menuntut **gudang yang tertata** (lokasi rak/bin, multi-gudang, supplier, PO).
- **Downtime mahal** → kecepatan & ketersediaan part jadi prioritas.

Tujuannya: menggantikan pencatatan manual (buku/Excel) dengan satu sumber data,
mempercepat turnaround unit, mengontrol nilai persediaan gudang, dan memberi pemilik
visibilitas penuh atas pendapatan, biaya, stok, dan performa.

**Target pengguna**
- Pemilik / manajer bengkel (performa, laporan, profitabilitas)
- Service advisor / front desk (terima unit, estimasi, koordinasi armada)
- Kepala mekanik & mekanik / teknisi (kerjakan work order)
- **Petugas gudang / warehouse** (terima barang, pick part, stock opname)
- Bagian pembelian / purchasing (PO ke supplier)
- Kasir / admin keuangan (penagihan, pembayaran)
- (Opsional) **Pelanggan armada** — portal lihat status unit & riwayat

---

## 2. Masalah yang Diselesaikan

| Masalah saat ini | Solusi ControlHub Workshop |
|---|---|
| Riwayat servis tiap unit truk tercecer | Database pelanggan armada + per-unit terpusat |
| Lupa jadwal servis berkala armada | Reminder PM otomatis berbasis KM / jam / tanggal |
| Estimasi biaya lambat & tidak konsisten | Template jasa + harga sparepart otomatis |
| Sparepart mahal sering kehabisan / menumpuk | Min-max stock, reorder point, peringatan stok |
| Part sulit dicari di gudang | Lokasi rak/bin per item, multi-gudang |
| Pembelian ke supplier tidak terkontrol | Purchase Order + penerimaan barang (GRN) |
| Nilai persediaan tidak diketahui | Kartu stok + valuasi (HPP) otomatis |
| Sulit hitung produktivitas mekanik | Pelacakan jam kerja & job per mekanik |
| Penagihan armada B2B rumit | Invoice konsolidasi per pelanggan / termin |
| Laporan keuangan manual | Dashboard & laporan otomatis |

---

## 3. Modul Utama

### 3.1 Manajemen Pelanggan (Armada) & Unit Truk
- Data pelanggan: perorangan **atau perusahaan/armada** (kontak PIC, NPWP, termin)
- Data unit truk: plat, merek, tipe/model, tahun, **no. rangka (VIN) & no. mesin**,
  jenis (tractor head, dump, box, tangki, dll.), **KM / jam operasi terakhir**
- Pengelompokan unit per pelanggan armada
- Riwayat servis lengkap per unit

### 3.2 Servis Berkala / Preventive Maintenance (PM)
- Jadwal PM per unit berbasis **KM, jam operasi, atau interval waktu**
- Template paket PM (mis. ganti oli + filter tiap 10.000 KM)
- Reminder otomatis unit yang akan/terlambat servis
- Pembuatan work order langsung dari PM jatuh tempo

### 3.3 Work Order / Perintah Kerja
- Penerimaan unit + keluhan + foto kondisi
- **Checklist inspeksi** (rem, ban, kelistrikan, mesin, dll.)
- Estimasi (jasa + sparepart) → persetujuan pelanggan
- Penugasan mekanik / tim
- **Request part ke gudang** langsung dari work order
- Status: *Antri → Menunggu Part → Dikerjakan → QC → Selesai → Diserahkan*
- Pencatatan jam kerja (jasa) aktual

### 3.4 Katalog Jasa & Master Sparepart
- Daftar jasa servis + tarif standar (jam/flat)
- Master sparepart: kode/part number, merek, **cocok untuk tipe truk apa**,
  satuan, harga beli & jual, min-max stock, lead time
- Paket servis & bundling part

### 3.5 Gudang Sparepart (Inventory & Warehouse) — modul inti
- **Multi-gudang** (mis. gudang utama, gudang cabang) & **lokasi rak/bin** per item
- **Stok masuk:** penerimaan barang (GRN) dari Purchase Order
- **Stok keluar:** pemakaian di work order, penjualan langsung, transfer antar gudang
- **Reorder point / min-max** + daftar usulan pembelian otomatis
- Item bergaransi / **bernomor seri & batch** (lacak per unit)
- **Stock opname / stock take** + penyesuaian (adjustment)
- **Kartu stok** per item & **valuasi persediaan** (FIFO/Average)
- Penjualan part *over-the-counter* (tanpa servis)

### 3.6 Pembelian / Purchasing
- Master supplier + harga & lead time
- **Purchase Order (PO)** → penerimaan (GRN) → hutang dagang (opsional)
- Riwayat pembelian per part / supplier

### 3.7 Penagihan & Pembayaran
- Invoice dari work order (jasa + part) atau penjualan part
- **Invoice konsolidasi armada** (gabungkan banyak WO per pelanggan/termin)
- Pajak (PPN), diskon, metode pembayaran, piutang & jatuh tempo
- Cetak/PDF invoice, struk, & surat jalan unit

### 3.8 Manajemen Mekanik
- Data mekanik + keahlian (mesin, kelistrikan, body, dll.)
- Beban kerja, jadwal, status (tersedia/sibuk)
- Produktivitas (jam terjual vs tersedia) & komisi (opsional)

### 3.9 Dashboard & Laporan
- Omzet harian/bulanan (jasa vs part), profitabilitas
- Work order aktif, antrian, & rata-rata turnaround
- **Nilai persediaan, stok kritis, part terlaris, part mati (slow moving)**
- Kepatuhan servis berkala armada
- Performa mekanik, piutang pelanggan

### 3.10 Pengaturan & Pengguna
- Multi-user dengan peran (RBAC): Owner, Admin, Service Advisor, Kepala Mekanik,
  Mekanik, **Petugas Gudang, Purchasing**, Kasir
- Profil bengkel, pajak, penomoran dokumen, daftar gudang & lokasi rak

---

## 4. Alur Kerja Inti (Happy Path)

### Alur Servis Unit
```
1. Unit truk masuk (atau dari reminder PM jatuh tempo)
2. Service Advisor catat unit + keluhan + inspeksi   → Work Order (Antri)
3. Buat estimasi (jasa + part) → pelanggan setuju
4. Request part ke gudang
   ├─ stok ada   → gudang pick & potong stok          → Dikerjakan
   └─ stok kosong → buat PO ke supplier (Menunggu Part)
5. Mekanik kerjakan, catat jam kerja
6. Selesai → QC → status: Selesai
7. Kasir buat Invoice (atau konsolidasi armada) → bayar / piutang
8. Unit diserahkan (surat jalan)                      → Diserahkan
9. Update KM & jadwal PM berikutnya; masuk riwayat & laporan
```

### Alur Gudang & Pembelian
```
Reorder point tercapai / WO butuh part
  → usulan beli → Purchase Order ke supplier
  → barang datang → penerimaan (GRN) → stok bertambah + kartu stok
  → part dipakai WO / dijual → stok berkurang
  → berkala: stock opname → penyesuaian
```

---

## 5. Arsitektur & Teknologi (TERKUNCI)

**Stack** (dipilih 2026-06-24):

| Lapisan | Pilihan |
|---|---|
| Bahasa | **PHP 8.4** |
| Framework | **Laravel 13** |
| Admin panel / UI back-office | **Filament v5** (Livewire v4) |
| Database | **PostgreSQL** (driver `pgsql`) |
| Multi-tenancy | `company_id` + global scope; **Company = tenant Filament** |
| RBAC | Filament Shield (roles/permissions per company) |
| API (bila perlu) | Laravel API Resources (mis. integrasi) |

**Pola:** aplikasi back-office berbasis **Filament** (2 panel inti: `App` untuk tenant,
`System` untuk Core/super-admin; + panel **`Vendor`** `/vendor` untuk portal supplier —
fase berikutnya, feature-flag). Logika bisnis di **Service class**; mutasi stok/uang
selalu dalam `DB::transaction()` lewat `StockService`. Detail di
`.claude/skills/workshop-feature/SKILL.md`.

> **Catatan tenancy:** memakai tenancy bawaan Filament (Company sebagai tenant) **dan**
> Global Scope `company_id` di Eloquent — keduanya saling memperkuat. Pastikan keduanya
> konsisten (lihat `RISK_GAP_ANALYSIS.md`).

---

## 6. Model Data (Garis Besar)

```
Customer (1) ──< (N) Truck (unit)
Truck    (1) ──< (N) PMSchedule           (jadwal servis berkala)
Truck    (1) ──< (N) WorkOrder
WorkOrder (1) ──< (N) WorkOrderItem  >── (1) Service | SparePart
WorkOrder (N) ──> (1) Mechanic (assignee)
WorkOrder (1) ──  (1) Invoice ──< (N) Payment

-- Gudang & Pembelian --
SparePart (1) ──< (N) StockItem  >── (1) Warehouse   (stok per gudang + lokasi rak)
SparePart (1) ──< (N) StockMovement (masuk/keluar/transfer/adjustment)
Supplier  (1) ──< (N) PurchaseOrder ──< (N) POItem
PurchaseOrder (1) ──< (N) GoodsReceipt (GRN) → StockMovement

User ──> Role (RBAC)
```

Entitas inti: `Customer`, `Truck`, `PMSchedule`, `WorkOrder`, `WorkOrderItem`,
`Service`, `SparePart`, `Warehouse`, `StockItem`, `StockMovement`, `Supplier`,
`PurchaseOrder`, `POItem`, `GoodsReceipt`, `Invoice`, `Payment`, `Mechanic`,
`User`, `Role`.

---

## 7. Roadmap Bertahap (Saran)

**MVP (Fase 1) — operasional inti + gudang dasar**
- Pelanggan (armada) & unit truk
- Work order + estimasi + request part
- Katalog jasa & master sparepart
- Gudang: stok masuk/keluar + kartu stok + peringatan stok minimum
- Invoice & pembayaran sederhana
- Login & peran dasar (RBAC)

**Fase 2 — kontrol gudang & visibilitas**
- Pembelian penuh: Purchase Order + penerimaan (GRN) + supplier
- Multi-gudang + lokasi rak/bin, transfer antar gudang
- Stock opname + valuasi persediaan
- Servis berkala (PM) + reminder
- Dashboard & laporan lengkap
- Manajemen mekanik & produktivitas

**Fase 3 — pengembangan**
- Invoice konsolidasi armada + piutang
- Notifikasi WhatsApp/SMS (reminder PM, status unit selesai)
- Portal pelanggan armada
- Barcode/QR untuk part & lokasi gudang
- Multi-cabang
- Aplikasi mobile untuk mekanik & petugas gudang

---

## 8. Asumsi & Hal yang Perlu Dikonfirmasi

✅ **Domain sudah dikonfirmasi:** bengkel **truk (kendaraan berat)** + **gudang sparepart**.

Yang masih perlu Anda tentukan agar rancangan bisa dipertajam:

1. **Platform** — Web (akses dari banyak komputer/HP), desktop Windows, atau keduanya?
2. **Skala gudang** — Satu gudang atau **banyak gudang/cabang** dari awal?
3. **Pelanggan** — Mayoritas **armada B2B** (perlu invoice konsolidasi & piutang) atau
   juga banyak pelanggan umum/eceran?
4. **Teknologi** — Ada preferensi stack tim (mis. .NET/C#, PHP/Laravel, JS/Node)?
5. **Akuntansi** — Cukup laporan operasional, atau perlu hutang/piutang & integrasi
   software akuntansi?
6. **Fitur khusus** — Perlu barcode/QR part, foto inspeksi, atau notifikasi WhatsApp
   sejak awal?
7. **Prioritas MVP** — Mana yang paling mendesak: operasional bengkel dulu, atau
   gudang/stok dulu?

---

*Setelah poin di atas Anda jawab, dokumen ini bisa saya lanjutkan menjadi:*
*(a) spesifikasi teknis (skema DB detail + endpoint API), (b) wireframe layar utama,*
*atau (c) scaffold struktur proyek.*
