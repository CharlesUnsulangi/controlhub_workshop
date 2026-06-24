# ControlHub Workshop — Sitemap Aplikasi

> Perencanaan (v0.1). Selaras dengan `MODULES.md`. Tiga surface:
> **(A) System/Core** untuk super admin penyedia aplikasi, **(B) App Tenant** untuk
> pengguna bengkel (scoped `company_id`, dengan pemilih **branch**), **(V) Portal Supplier**
> (`/vendor`, fase berikutnya) untuk supplier eksternal (scoped `supplier_id`).

Konvensi route: prefix mengikuti sub-prefix modul (`/inv`, `/po`, `/lkm`, `/svc`,
`/tyre`, `/admin`). Nama route: `wks.<area>.<resource>.<action>` (lihat NAMING_CONVENTIONS §5).

---

## 0. Publik / Autentikasi  (`/`)

```
/login
/forgot-password
/reset-password/{token}
/logout
(pasca-login: super admin → /system ; user tenant → /app)
```

---

## A. SYSTEM / CORE  (`/system`) — peran: SuperAdmin, SystemSupport

```
/system
├─ /system                         Dashboard sistem (jumlah tenant, status, aktivitas)
├─ /system/companies               Daftar Company/Tenant
│  ├─ /system/companies/create     Provisioning tenant baru (+ seed + admin pertama)
│  └─ /system/companies/{id}       Detail company
│     ├─ .../edit                  Ubah data, status (aktif/suspend)
│     ├─ .../modules               Feature-flag modul per company   (opsional)
│     ├─ .../subscription          Plan & langganan                 (opsional)
│     └─ .../impersonate           Masuk sebagai tenant (support)
├─ /system/plans                   Master plan/paket                (opsional)
├─ /system/modules                 Daftar modul aplikasi
├─ /system/users                   Super admin / system users
├─ /system/audit-logs              Audit log lintas tenant
└─ /system/settings                Pengaturan global aplikasi
```

---

## B. APP TENANT  (`/app`) — scoped `company_id`, pemilih branch di header

Header global: **logo company · pemilih Branch · pencarian · notifikasi · menu user**
Menu user: Profil · Ubah Password · Logout

### B.1 Dashboard  (`/app`)
```
/app                               Dashboard (ringkasan per branch terpilih)
   • Kendaraan masuk hari ini · WO aktif · stok kritis · PM jatuh tempo
   • Omzet ringkas · piutang · ban perlu rotasi
```

### B.2 LKM — Laporan Kendaraan Masuk  (`/app/lkm`)
Peran: ServiceAdvisor, Gate/Satpam, Admin
```
/app/lkm                           Daftar kendaraan masuk (filter status/tanggal/branch)
/app/lkm/create                    Buat LKM (pilih customer+unit, KM, keluhan)
/app/lkm/{id}                      Detail LKM
   ├─ .../inspection               Checklist inspeksi awal + foto
   ├─ .../to-work-order            Buat Work Order dari LKM
   └─ .../gate-out                 Gate-out + surat jalan
```

### B.3 Servis / Work Order  (`/app/svc`)
Peran: ServiceAdvisor, KepalaMekanik, Mekanik, Kasir, Admin
```
/app/svc/work-orders               Daftar Work Order (papan status / kanban)
/app/svc/work-orders/create        Buat WO
/app/svc/work-orders/{id}          Detail WO
   ├─ .../estimate                 Estimasi (jasa + part + ban)
   ├─ .../items                    Item pekerjaan & part (request ke gudang)
   ├─ .../mechanics                Penugasan mekanik & jam kerja
   └─ .../qc                       QC & penyelesaian
/app/svc/pm                        Servis Berkala (PM)
   ├─ /app/svc/pm/due              Daftar unit jatuh tempo / terlambat
   └─ /app/svc/pm/schedules        Pengaturan interval PM per unit
/app/svc/cost                      Rekap biaya per WO & per unit (cost: part HPP + ban + jasa)
─── future/dormant (mode internal, non-aktif) ───
/app/svc/invoices                  Daftar Invoice           (future)
   ├─ /app/svc/invoices/create     Buat invoice              (future)
   └─ /app/svc/invoices/{id}       Detail + cetak/PDF        (future)
/app/svc/payments                  Pembayaran & piutang      (future)
```

### B.4 Gudang Sparepart  (`/app/inv`)
Peran: Gudang, Admin · *(Mekanik: usul pengeluaran · ServiceAdvisor: review)*
```
/app/inv/parts                     Master Sparepart (SKU internal kanonik)
   ├─ /app/inv/parts/create        + cross-ref nomor pabrikan (Hino/Isuzu) & brand
   ├─ /app/inv/parts/{id}          Detail: part-numbers, kartu stok (per kondisi)
   └─ /app/inv/parts/search        Cari by nomor pabrikan/brand → resolve ke SKU
/app/inv/stock                     Stok per gudang/lokasi rak & kondisi (baru/bekas)
   └─ filter: warehouse · rak · condition (new/used/rebuilt)
/app/inv/locations                 Lokasi rak (zona/rak/bay/level/bin + barcode)
/app/inv/part-issues               Bon Pengeluaran Sparepart (ref WO→LKM→truck)
   ├─ /app/inv/part-issues/create  Usul oleh Mekanik (qty diminta per part)
   ├─ /app/inv/part-issues/{id}/review   Review Service Officer (approve/reject, potong qty)
   └─ /app/inv/part-issues/{id}/issue    Keluarkan oleh Gudang (movement out, HPP→WO)
/app/inv/movements                 Pergerakan stok (in/out/transfer/adjustment)
/app/inv/transfers                 Transfer antar gudang
/app/inv/teardown                  Penerimaan part bekas (copotan/teardown → stok used)
/app/inv/delivery-orders           Surat Jalan (barang keluar)
   ├─ /app/inv/delivery-orders/create   (transfer/issue/retur supplier)
   └─ /app/inv/delivery-orders/{id}     Detail + cetak + tally muat
/app/inv/tally                     Tally Sheet (verifikasi fisik DO & serah terima)
/app/inv/opname                    Stock opname
   ├─ /app/inv/opname/create
   └─ /app/inv/opname/{id}         Input hitung fisik → adjustment
/app/inv/sales                     Penjualan part eceran (over-the-counter)  (future/dormant)
/app/inv/reports                   Laporan: stok kritis · slow moving · valuasi · baru vs bekas
```

### B.5 Gudang Ban (Tyre)  (`/app/tyre`)
Peran: Gudang/GudangBan, Mekanik, Admin
```
/app/tyre/products                 Master model ban (merek+ukuran+pola) — acuan harga
/app/tyre/tyres                    Daftar unit ban per serial (filter merek/ukuran/status)
   ├─ /app/tyre/tyres/create       Registrasi unit ban (serial unik, model, DOT)
   └─ /app/tyre/tyres/{id}         Detail + riwayat instalasi & inspeksi
/app/tyre/stock                    Stok ban per gudang
/app/tyre/installations            Instalasi / rotasi (pasang-lepas di posisi unit)
/app/tyre/inspections              Inspeksi tread depth & tekanan
/app/tyre/retreads                 Vulkanisir (kirim/terima + biaya)
/app/tyre/reports                  Biaya per KM · ban perlu ganti/rotasi
```

### B.6 Purchasing  (`/app/po`)
Peran: Purchasing, Gudang, Admin (approval: Owner/Admin)
```
/app/po/requests                   Purchase Request (opsional)
/app/po/orders                     Daftar Purchase Order
   ├─ /app/po/orders/create
   └─ /app/po/orders/{id}          Detail + approval + status
/app/po/supplier-deliveries        Surat Jalan MASUK dari supplier (per PO)
   ├─ /app/po/supplier-deliveries/create   Daftarkan SJ (staf; supplier via portal /vendor)
   └─ /app/po/supplier-deliveries/{id}     Detail SJ + item dikirim
/app/po/receipts                   Serah Terima / GRN (WAJIB pilih PO)
   ├─ /app/po/receipts/create      pilih PO (+ opsional SJ masuk) → input penerimaan
   ├─ .../tally                    Tally sheet (hitung fisik bongkar)
   └─ .../post                     Posting → tambah stok (StockService)
/app/po/reports                    Riwayat pembelian per part/supplier
```

### B.6b Price List Supplier — Harga Beli Part & Ban  (`/app/price`)
Peran: Purchasing, Admin, Owner (update); read saat PO
```
/app/price/lists                   Daftar price list per supplier
   ├─ /app/price/lists/create       Buat (supplier, mata uang, status PPN, masa berlaku)
   └─ /app/price/lists/{id}         Detail + daftar item harga
      ├─ .../items                  Kelola harga per part/model-ban (+ PPN + effective date)
      ├─ .../bulk-update            Update massal (per kategori / %)
      └─ .../import                 Impor harga (Excel/CSV)
/app/price/compare                  Bandingkan harga antar supplier per item
/app/price/history                  Riwayat perubahan harga (audit)
```

### B.7 Master Data  (`/app/master`)
Peran: Admin (sebagian read untuk operasional)
```
/app/master/customers              Customer / Armada
/app/master/trucks                 Unit Truk (+ tipe truk)
   ├─ /app/master/trucks/{id}      Detail: spesifikasi, default driver, GPS
   └─ .../documents                STNK/KIR/pajak/asuransi + reminder expiry
/app/master/drivers                Master Driver / Sopir
   ├─ /app/master/drivers/create   Tambah manual
   ├─ /app/master/drivers/{id}     Detail (SIM, status, unit)
   └─ /app/master/drivers/sync     Sinkron dari ControlHub HRD
/app/master/suppliers              Supplier
/app/master/warehouses             Gudang & Lokasi (rak/bin)   [per branch]
/app/master/uoms                   Satuan (UoM)
/app/master/categories             Kategori part/ban
/app/master/mechanics              Mekanik
/app/master/services               Katalog Jasa
```

### B.8 Admin / Pengaturan  (`/app/admin`)
Peran: Owner, Admin
```
/app/admin/users                   Manajemen user (dalam company)
/app/admin/roles                   Roles & Permissions (RBAC)
/app/admin/branches                Cabang / Branch
/app/admin/company                 Profil company (logo, NPWP, kontak)
/app/admin/settings                Pengaturan: pajak/PPN, jam operasional
/app/admin/document-sequences      Penomoran dokumen (LKM/WO/PO/Invoice)
```

### B.9 Laporan (lintas modul)  (`/app/reports`)
Peran: Owner, Admin, (sebagian) Kasir
```
/app/reports/revenue               Omzet & profitabilitas (jasa vs part)
/app/reports/turnaround            Lama unit di bengkel
/app/reports/inventory             Nilai persediaan & pergerakan
/app/reports/receivables           Piutang pelanggan/armada
/app/reports/mechanics             Produktivitas mekanik
/app/reports/pm-compliance         Kepatuhan servis berkala armada
/app/reports/tyre                  Performa & biaya ban
```

---

## V. PORTAL SUPPLIER  (`/vendor`) — *fase berikutnya (feature-flag)*
Peran: **Supplier** (akun di `users` + `supplier_id`; panel Filament terpisah).
Scope ketat: hanya data milik `supplier_id` sendiri (+ company). **Read** PO yang ditujukan
padanya; **tulis** Surat Jalan miliknya saja.
```
/vendor/login                      Login supplier (akun undangan)
/vendor/dashboard                  Ringkasan PO aktif & SJ
/vendor/purchase-orders            Daftar PO ke supplier ini (read-only)
   └─ /vendor/purchase-orders/{id} Detail PO (item, qty, status penerimaan)
/vendor/deliveries                 Surat Jalan yang didaftarkan supplier
   ├─ /vendor/deliveries/create    Buat SJ atas PO (pilih PO → qty kirim per item)
   └─ /vendor/deliveries/{id}      Detail + status (submitted/received)
/vendor/profile                    Profil & kontak supplier
```
> Entri SJ awalnya oleh staf di `/app/po/supplier-deliveries` (`source=manual`); portal ini
> mengaktifkan supplier mengisi sendiri (`source=portal`). Dibangun setelah core internal.

---

## C. Matriks Akses Menu (ringkas)

| Menu \ Peran | Super Admin | Owner | Admin | Service Advisor | Mekanik | Gudang | Purchasing | Kasir |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| /system (Core) | ✅ | – | – | – | – | – | – | – |
| Dashboard | – | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| LKM | – | ✅ | ✅ | ✅ | – | – | – | – |
| Servis/WO | – | ✅ | ✅ | ✅ | ✅ | – | – | r |
| Invoice/Payment | – | ✅ | ✅ | r | – | – | – | ✅ |
| Gudang Sparepart | – | ✅ | ✅ | r | r | ✅ | r | – |
| Gudang Ban | – | ✅ | ✅ | r | ✅ | ✅ | – | – |
| Price List (supplier) | – | ✅ | ✅ | – | – | r | ✅ | – |
| Purchasing | – | ✅ | ✅ | – | – | r | ✅ | – |
| Master Data | – | ✅ | ✅ | r | – | r | r | – |
| Admin/Pengaturan | – | ✅ | ✅ | – | – | – | – | – |
| Laporan | – | ✅ | ✅ | – | – | r | r | r |

`✅` akses penuh · `r` read-only · `–` tidak ada akses. (Final via RBAC `wks_adm_permissions`.)

> **Catatan peran khusus:** **Supplier** tidak ada di matriks ini — aksesnya **hanya** di
> surface **V. Portal Supplier** (`/vendor`), scoped `supplier_id`. Pada **Pengeluaran Sparepart**
> (`/app/inv/part-issues`): Mekanik *usul*, Service Advisor *review/approve*, Gudang *issue*.

---

## D. Catatan Navigasi

- **Sidebar dikelompokkan per modul**; menu disembunyikan bila modul dimatikan untuk
  company (feature-flag Core) atau peran tidak berhak.
- **Pemilih Branch** di header memengaruhi semua data transaksi & laporan.
- **Breadcrumb** mengikuti hierarki route.
- **Aksi cepat** di dashboard: "+ LKM", "+ Work Order", "+ PO".
- Mobile/tablet (mekanik & gudang) cukup akses: WO, request part, instalasi ban,
  opname — pertimbangkan layout ringkas (roadmap Fase mobile).
- **Mode internal:** menu bertanda *(future/dormant)* — penjualan part, invoice,
  pembayaran — disembunyikan via feature-flag sampai fitur penjualan diaktifkan.
```
