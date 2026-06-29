# ControlHub Workshop — Sitemap Aplikasi

> Perencanaan (v0.1). Selaras dengan `MODULES.md`. Tiga surface:
> **(A) System/Core** untuk super admin penyedia aplikasi, **(B) App Tenant** untuk
> pengguna bengkel (scoped `company_id`, dengan pemilih **branch**), **(V) Portal Supplier**
> (`/vendor`, web supplier isi Surat Jalan; feature-flag) untuk supplier eksternal (scoped `supplier_id`).

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

### B.2a PMB — Permintaan Mobil Masuk (panel terpisah, `/pmb`)
Peran: **Dispatcher saja**. Aktif hanya pada mode `lkm_intake_mode = dispatcher_permit`.
PMB = pengantar dari pos Dispatcher; **independen** dari LKM (lihat PANELS §2b).
```
/pmb                               Antrian PMB aktif (status=issued, branch)
/pmb/create                        Form Dispatcher: cocokkan truck/plat + sopir, keluhan → Terbitkan (issued)
/pmb/{id}                          Detail PMB (+ telusur LKM terkait bila used)
   ├─ .../print                    Cetak PMB (surat bernomor → dibawa driver ke gate)
   └─ .../cancel                   Batalkan (note wajib → cancelled)
```

### B.2 LKM — Laporan Kendaraan Masuk  (`/app/lkm`)
Peran: ServiceAdvisor (Service Officer), Gate/Satpam, Admin
Dua mode (setting company `lkm_intake_mode`): `direct` / `dispatcher_permit`.
```
/app/lkm                           Daftar kendaraan masuk (filter status/tanggal/branch)
/app/lkm/create                    Buat LKM (input KM, keluhan, inspeksi)
   ├─ ?pmb=cari                    (mode dispatcher) Cari/rujuk PMB issued (no PMB/plat/scan) → prefill + set pmb_id, PMB→used
   └─ (mode direct)               input manual truk+customer (pmb_id null)
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
   ├─ .../items                    Item biaya (jasa/part/ban) — AKTUAL, tanpa estimasi biaya (fase sekarang)
   ├─ .../plan                     WO Plan: task + langkah/step per task (salin template jasa)
   │     └─ .../plan/tasks/{tid}/steps  Kelola langkah (seq, planned/adhoc)
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
/app/inv/sessions                  Sesi Kerja Gudang — 1 siklus/hari per gudang (gate masuk panel)
   ├─ /app/inv/sessions/open        Buka Sesi (operator; snapshot saldo awal) — WAJIB sebelum pakai panel
   ├─ /app/inv/sessions/{id}        Detail: ringkasan mutasi + saldo awal→akhir + anomali
   └─ /app/inv/sessions/{id}/close  Tutup Sesi (**hanya Kepala Gudang/Supervisor**; snapshot akhir + update gudang)
/app/inv/stock                     Stok per gudang/lokasi rak & kondisi (baru/bekas)
   └─ filter: warehouse · rak · condition (new/used/rebuilt)
/app/inv/locations                 Setting Rak/Lokasi (pohon hierarki: area/zona/rak → bin)
   ├─ /app/inv/locations/tree       Susun struktur (drag header & bin; tak terpola)
   ├─ /app/inv/locations/generate   Generator massal bin dari pola (+ kode + barcode)
   ├─ /app/inv/locations/{id}       Detail bin: purpose, kondisi, kapasitas, blok, barcode
   └─ /app/inv/locations/slotting   Lokasi default per SKU (mode fixed/hybrid)
/app/inv/part-issues               Bon Pengeluaran Sparepart (ref WO→LKM→truck)
   ├─ /app/inv/part-issues/create  Usul oleh Mekanik (qty diminta per part)
   ├─ /app/inv/part-issues/{id}/review   Review Service Officer (approve/reject, potong qty)
   └─ /app/inv/part-issues/{id}/issue    Keluarkan oleh Gudang (movement out, HPP→WO)
/app/inv/movements                 Pergerakan stok (ledger: in/out/transfer/adjustment/loan/retur)
/app/inv/relocate                  ④ Mutasi/Relokasi dalam gudang (bin↔bin) — transfer
/app/inv/transfers                 ④ Transfer antar gudang
/app/inv/teardown                  ③ Penerimaan part bekas (copotan/teardown → stok used)
/app/inv/loans                     ⑤ Peminjaman part (storing keluar, WAJIB kembali)
   ├─ /app/inv/loans/create        Pinjam keluar (loan_out)
   ├─ /app/inv/loans/{id}/return   Terima kembali (loan_return, sebagian/penuh)
   └─ /app/inv/loans/{id}/convert  Konversi → Bon (bila tak kembali → pemakaian/HPP)
/app/inv/adjustments               ⑥ Masukkan temuan / penyesuaian (adjustment, reason found)
/app/inv/purchase-returns          ⑦ Retur ke supplier (ref PO/GRN) → nota retur (potong tagihan AP)
   ├─ /app/inv/purchase-returns/create
   └─ /app/inv/purchase-returns/{id}    Post (return_supplier) → credited
/app/inv/issue-returns             ⑧ Retur Bon (part baru tak jadi pakai → kembali, reverse HPP WO)
/app/inv/receiving                 PENERIMAAN dari supplier (barang tiba di gudang) — tulis di sini
   ├─ /app/inv/receiving/supplier-deliveries   Surat Jalan MASUK supplier (per PO; verifikasi saat tiba)
   ├─ /app/inv/receiving/grn/create            Serah Terima/GRN (WAJIB pilih PO + rujuk SJ) → tally
   └─ /app/inv/receiving/grn/{id}              Detail GRN + Post (StockService in + WAC)
/app/inv/delivery-orders           Surat Jalan (barang KELUAR — transfer/issue/retur internal)
   ├─ /app/inv/delivery-orders/create   (transfer/issue/retur supplier)
   └─ /app/inv/delivery-orders/{id}     Detail + cetak + tally muat
/app/inv/tally                     Tally Sheet (verifikasi fisik DO & serah terima)
/app/inv/opname                    Stock opname
   ├─ /app/inv/opname/create
   └─ /app/inv/opname/{id}         Input hitung fisik → adjustment
/app/inv/audit                     AUDIT GUDANG → **panel terpisah `/audit`** (peran Auditor, independen)
   ├─ /app/inv/audit/audits        Audit formal (cycle/spot/full/compliance) — jadwalkan & jalankan
   │     └─ .../{id}/items         Cek fisik independen (book vs counted) → buat temuan
   ├─ /app/inv/audit/findings      Temuan (severity/status) → tindak lanjut → verifikasi auditor
   ├─ /app/inv/audit/trail         Audit Trail (read-only: movement ledger + core audit logs, filter)
   └─ /app/inv/audit/anomalies     Review Anomali (stok negatif/selisih sesi) → Promosikan jadi Temuan
/app/inv/sales                     Penjualan part eceran (over-the-counter)  (future/dormant)
/app/inv/reports                   Laporan: stok kritis · slow moving · valuasi · baru vs bekas · rekap temuan audit
```

### B.5 Gudang Ban (Tyre)  (`/app/tyre`)
Peran: Gudang/GudangBan, Mekanik, Admin
```
/app/tyre/products                 Master model ban (merek+ukuran+pola+spek) — acuan harga
/app/tyre/tyres                    Daftar unit ban per serial (filter merek/ukuran/status/kondisi)
   ├─ /app/tyre/tyres/create       Registrasi unit ban (serial unik, model, DOT, lokasi)
   └─ /app/tyre/tyres/{id}         Detail + riwayat instalasi/inspeksi/retread + biaya/KM
/app/tyre/stock                    Stok ban per gudang/tahap (baru/bekas/afkir) & lokasi
/app/tyre/receive                  ① Terima ban dari supplier — pilih SJ supplier (portal/manual) → GRN ber-PO → receipt (registrasi serial), Gudang Ban Baru
/app/tyre/installations            ②/③ Instalasi / rotasi (pasang-lepas) — lepas layak → stok used (Gudang Bekas)
   └─ /app/tyre/trucks/{id}/layout Diagram posisi ban pada unit (per axle config)
/app/tyre/inspections              Inspeksi tread depth & tekanan (+ rekomendasi / usul afkir)
/app/tyre/retreads                 Vulkanisir (kirim/terima + biaya)
/app/tyre/condemn                  ④ Konfirmasi Afkir (status afkir) — HANYA Kepala Gudang/Supervisor (tyre.condemn)
/app/tyre/transfer-afkir           ⑤ Pindah ke Gudang Afkir (movement transfer)
/app/tyre/opnames                  Opname ban (cek kehadiran per serial)
/app/tyre/alerts                   Peringatan ban (tread/inspeksi/retread/DOT/stok)
/app/tyre/disposals                ⑥ JUAL AFKIR / buang (lot, dari Gudang Afkir → proceeds)
/app/tyre/reports                  Biaya per KM · ban perlu ganti/rotasi
```
> Sesi Kerja Gudang **terpadu** dgn sparepart (`/app/inv/shift-sessions`) — gudang `type=both`:
> satu Buka/Tutup Sesi mencakup mutasi part & ban.

### B.6 Purchasing  (`/app/po`)
Peran: Purchasing, Admin (approval: Owner/Admin). **Penerimaan/GRN dikerjakan di Gudang**
(`/app/inv/receiving`) — di sini SJ supplier/GRN **read-only** untuk pantau pemenuhan PO.
```
/app/po/requests                   Purchase Request (opsional)
/app/po/orders                     Daftar Purchase Order
   ├─ /app/po/orders/create
   └─ /app/po/orders/{id}          Detail + approval + status
/app/po/supplier-deliveries        Surat Jalan MASUK supplier (per PO) — READ-ONLY (tulis di Gudang /app/inv/receiving)
   └─ /app/po/supplier-deliveries/{id}     Detail SJ (pantau pemenuhan PO)
/app/po/receipts                   Serah Terima / GRN per PO — READ-ONLY (status & qty diterima)
   →  buat/tally/post GRN ada di Gudang: /app/inv/receiving/grn/*
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
   └─ /app/master/axle-positions   Skema posisi ban per axle config (4x2/6x4 → slot valid)
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
/app/admin/notifications           Aturan Notifikasi (event → channel WA/email/in-app, penerima, ambang/eskalasi)
   └─ /app/admin/notifications/log  Riwayat kirim (outbox: terkirim/gagal)
```

### B.10 Kontrabon — Hutang Supplier / AP (panel terpisah, `/kontrabon`)
Peran: **Finance/AP** (approver ≠ verifikator). Modul `wks_ap_` (PANELS §7). Hilir PO→GRN.
```
/kontrabon                          Daftar kontrabon (filter status/supplier/jatuh tempo)
/kontrabon/create                   Buat kontrabon: pilih supplier + tanggal terima + jatuh tempo
/kontrabon/{id}                     Detail kontrabon (tanda terima tagihan)
   ├─ .../invoices                  Baris tagihan (salin 1..n faktur supplier: no faktur/faktur pajak/nilai/PPN, rujuk GRN/PO)
   │     └─ .../{lid}/check         Cek satu per satu: ☑ barang diterima · ☑ surat jalan · ☑ faktur pajak · ☑ PO & nominal → ok/problem
   ├─ .../verify                    Verifikasi (gate: semua baris ok → verified)
   ├─ .../approve                   Approve → hutang diakui (SoD: approver ≠ verifikator)
   └─ .../reject                    Tolak/sengketa (rejected + alasan → kembali ke supplier)
/kontrabon/aging                    Aging hutang per supplier (bucket umur, outstanding)
/kontrabon/due                      Kontrabon jatuh tempo (umpan ke Kasir)
```

### B.11 Kasir — Pembayaran Supplier / AP (panel terpisah, `/kasir`)
Peran: **Kasir**. Kelola rekening, request pembayaran (maker→checker), realisasi giro/digital atas
kontrabon `approved`. ⚠️ AP (supplier), bukan kasir customer.
```
/kasir                              Dashboard kas (jatuh tempo, request menunggu approve, kas keluar)
/kasir/bank-accounts                Master Rekening Bank/Kas (bank/tunai; supports giro/digital)
   └─ /kasir/bank-accounts/{id}     Detail rekening
/kasir/payment-requests             Request Pembayaran (maker→checker)
   ├─ /kasir/payment-requests/create   Maker: pilih supplier + kontrabon jatuh tempo + metode + rekening
   │     └─ .../allocations            Alokasi ke 1/banyak kontrabon (partial; anti over-pay)
   ├─ /kasir/payment-requests/{id}/submit    Ajukan (submitted)
   └─ /kasir/payment-requests/{id}/approve   Checker approve/reject (SoD: ≠ pengaju)
/kasir/payments                     Realisasi pembayaran (eksekusi request approved)
   ├─ /kasir/payments/create        Transfer/Digital (digital_ref) → Post; atau Giro → Register Giro
   └─ /kasir/payments/{id}/post     Post → settle hutang (paid_amount↑, status kontrabon)
/kasir/giros                        Register Giro (kontrol sebelum tanda tangan)
   ├─ /kasir/giros/create           Register giro di aplikasi (no/nilai/jatuh tempo/atas nama) — registered
   ├─ /kasir/giros/{id}/print       Cetak lembar register/voucher → printed (lalu giro fisik ditandatangani)
   ├─ /kasir/giros/{id}/sign        Tandai ditandatangani (signed)
   ├─ /kasir/giros/{id}/verify      Verifikasi: giro fisik vs sistem (lewat print) → verified
   ├─ /kasir/giros/{id}/release     Serahkan ke supplier → released (payment posted)
   └─ /kasir/giros/{id}/clear       Tandai cair (cleared) / bounce (gagal cair)
/kasir/cash-out                     Rekap kas keluar per rekening/metode/periode + daftar giro
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

## V. PORTAL SUPPLIER  (`/vendor`) — Web Supplier Isi Surat Jalan *(feature-flag)*
Peran: **Supplier** (akun di `users` + `supplier_id`; panel Filament terpisah).
**Tujuan: supplier isi SJ sendiri (part & ban) → operator gudang tak menyalin.**
Scope ketat: hanya data milik `supplier_id` sendiri (+ company). **Read** PO yang ditujukan
padanya; **tulis** Surat Jalan miliknya saja.
```
/vendor/login                      Login supplier (akun undangan)
/vendor/dashboard                  Ringkasan PO aktif & status SJ/penerimaan
/vendor/purchase-orders            Daftar PO ke supplier ini (read-only)
   └─ /vendor/purchase-orders/{id} Detail PO (item part/ban, qty, status penerimaan)
/vendor/deliveries                 Surat Jalan yang diisi supplier (part & ban)
   ├─ /vendor/deliveries/create    Buat SJ atas PO (supplier_do_no + item part/ban + qty kirim) → Submit
   └─ /vendor/deliveries/{id}      Detail + status (submitted/received)
/vendor/profile                    Profil & kontak supplier
```
> **Alur ringan:** supplier Submit SJ (`source=portal`) → barang tiba → operator Gudang **GRN
> pilih SJ** + tally (tak ketik ulang). Ban: SJ = product+qty, **serial diregistrasi saat GRN**.
> Fallback tanpa portal: staf isi di `/app/inv/receiving/supplier-deliveries` (`source=manual`).

---

## C. Matriks Akses Menu (ringkas)

| Menu \ Peran | Super Admin | Owner | Admin | Service Advisor | Mekanik | Gudang | Purchasing | Kasir |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| /system (Core) | ✅ | – | – | – | – | – | – | – |
| Dashboard | – | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| PMB (pengantar dispatcher) | – | ✅ | ✅ | – | – | – | – | – |
| LKM | – | ✅ | ✅ | ✅ | – | – | – | – |
| Servis/WO | – | ✅ | ✅ | ✅ | ✅ | – | – | r |
| Invoice/Payment | – | ✅ | ✅ | r | – | – | – | ✅ |
| Gudang Sparepart | – | ✅ | ✅ | r | r | ✅ | r | – |
| Penerimaan (SJ supplier + GRN) | – | ✅ | ✅ | – | – | ✅ | r | – |
| Audit Gudang (kontrol) | – | r | r | – | – | – | – | – |
| Gudang Ban | – | ✅ | ✅ | r | ✅ | ✅ | – | – |
| Price List (supplier) | – | ✅ | ✅ | – | – | r | ✅ | – |
| Purchasing (PO) | – | ✅ | ✅ | – | – | r | ✅ | – |
| Kontrabon (Hutang Supplier/AP) | – | ✅ | ✅ | – | – | – | r | r |
| Kasir — Bayar Supplier (AP) | – | ✅ | ✅ | – | – | – | – | ✅ |
| Master Data | – | ✅ | ✅ | r | – | r | r | – |
| Admin/Pengaturan | – | ✅ | ✅ | – | – | – | – | – |
| Laporan | – | ✅ | ✅ | – | – | r | r | r |

`✅` akses penuh · `r` read-only · `–` tidak ada akses. (Final via RBAC `wks_adm_permissions`.)

> **Catatan peran khusus:** **Supplier** tidak ada di matriks ini — aksesnya **hanya** di
> surface **V. Portal Supplier** (`/vendor`), scoped `supplier_id`. Pada **Pengeluaran Sparepart**
> (`/app/inv/part-issues`): Mekanik *usul*, Service Advisor *review/approve*, Gudang *issue*.
> **Auditor** (peran khusus, tak berkolom) = **Internal Audit** di **panel terpisah `/audit`**:
> **read** stok/dokumen + **tulis** audit/temuan + verifikasi; **independen** (operator/Kepala
> Gudang = subjek audit, tak menulis audit). Audit **tak mengubah stok**.
> **Dispatcher** (peran khusus, tak berkolom) = **penerbit PMB** (panel terpisah `/pmb`)
> pada mode `dispatcher_permit`; **SoD:** Dispatcher terbitkan PMB ≠ Service Officer
> (Service Advisor) buat LKM. PMB independen — tidak auto-terbit LKM.
> **Finance/AP** (peran khusus, tak berkolom) = **Kontrabon** (panel terpisah `/kontrabon`):
> verifikasi & approve faktur supplier (akui hutang). **Kasir** = **bayar supplier** (panel
> `/kasir`). **SoD AP:** verifikator ≠ approver (di Kontrabon) ≠ pembayar (Kasir). Kontrabon &
> Kasir = modul Hutang Supplier `wks_ap_` (PANELS §7–§9).

---

## D. Catatan Navigasi

- **Sidebar dikelompokkan per modul**; menu disembunyikan bila modul dimatikan untuk
  company (feature-flag Core) atau peran tidak berhak.
- **Pemilih Branch** di header memengaruhi semua data transaksi & laporan.
- **Breadcrumb** mengikuti hierarki route.
- **Aksi cepat** di dashboard: "+ LKM", "+ Work Order", "+ PO".
- Mobile/tablet (mekanik & gudang) cukup akses: WO, request part, instalasi ban,
  opname — pertimbangkan layout ringkas (roadmap Fase mobile).
  - **Mekanik:** antarmuka UTAMA = **PWA mobile-first** (update pekerjaan: clock in/out,
    checklist langkah, usul Bon, pasang ban); panel Filament Mekanik = view supervisor.
    Fase 1 online-first, siap offline. Detail → `MOBILE_MEKANIK.md` / `PANELS.md §4b`.
- **Mode internal:** menu bertanda *(future/dormant)* — penjualan part, invoice,
  pembayaran — disembunyikan via feature-flag sampai fitur penjualan diaktifkan.
```
