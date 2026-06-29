# ControlHub Workshop — Rencana Modul & Arsitektur Multi-Tenant

> Dokumen perencanaan (v0.2). Melengkapi `OVERVIEW.md`. Stack: **Laravel + PostgreSQL**.
> Semua tabel domain pakai prefix `wks_` + **sub-prefix modul** (lihat §10 dan
> `.claude/skills/workshop-feature/NAMING_CONVENTIONS.md`).

**Keputusan terkunci:** tenant = `company_id` · satu tabel `users` (`company_id`
nullable, null = super admin) · ada **branch** (company → branch → warehouse) ·
**sub-prefix per modul** · **modul Servis/Work Order masuk sekarang**.

### Mode Operasi (penting)

**Saat ini: INTERNAL** — bengkel in-house yang merawat **armada milik sendiri**.
Prioritas = **pencatatan biaya (cost)**, bukan penjualan ke pelanggan luar.

- Pemakaian part/ban di Work Order dinilai pada **harga beli / HPP** (cost), bukan harga jual.
- **Tidak ada penjualan & invoice ke customer** yang aktif untuk sekarang.
- **Price List = harga beli supplier** (cost reference) — sudah sesuai.
- "Customer/Armada" untuk sekarang ≈ **pemilik/divisi/cost-center internal** pemilik unit.

**Disiapkan tapi non-aktif (future-ready):** **estimasi/penawaran biaya WO + approval
pelanggan**, penjualan part eceran, harga jual, invoice & pembayaran ke customer. Tabelnya
**tetap dibuat** dan diberi penanda agar bisa diaktifkan via feature-flag tanpa migrasi
besar. Lihat penanda *(future/dormant)* di tiap modul dan §14.

> **Fase sekarang (Servis):** WO langsung ke **rencana kerja (WO Plan)** & eksekusi; **biaya
> dicatat dari pemakaian aktual** (HPP/WAC + ban + jasa), **tanpa estimasi biaya** di depan.

---

## 1. Arsitektur Multi-Tenant

**Strategi: single database, shared schema, dipisah `company_id`.**

Hierarki: **Company (tenant) → Branch (cabang) → Warehouse (gudang)**.

Aturan wajib:
- **Setiap tabel milik tenant punya `company_id`** (FK `wks_core_companies`),
  `not null`, ber-index. Tabel Core/sistem **tidak**.
- **Transaksi operasional** juga membawa `branch_id` (FK `wks_ms_branches`) untuk
  pelaporan per cabang: LKM, Work Order, PO, GRN, stock movement (lewat warehouse).
- **Master lintas-cabang** (customer, truk, supplier) = level **company** (tanpa `branch_id`),
  dipakai bersama semua cabang. Warehouse = level **branch**.
- **Global Scope** Eloquent otomatis filter `company_id` aktif; `company_id` & (bila ada)
  `branch_id` diisi otomatis saat create dari konteks user login.
- **Unique di-scope per company**: `unique(company_id, <doc_no>)` — bukan unik global.

```
┌──────────────────────────────────────────────────────────────┐
│  CORE (System Admin)  — lintas tenant, TANPA company_id        │
│  companies · plans · modules · feature-flag · super admin · audit│
└──────────────────────────────────────────────────────────────┘
          │ 1 company punya banyak branch
          ▼
┌──────────────────────────────────────────────────────────────┐
│  TENANT (company_id)  →  Branch (branch_id)  →  Warehouse       │
│  ADMIN · MASTER · LKM · PURCHASING · GD.SPAREPART · GD.BAN · SERVIS│
└──────────────────────────────────────────────────────────────┘
```

Lapisan teknis (Laravel):
- Trait `app/Models/Concerns/BelongsToCompany.php` — relasi `company()`, global scope,
  auto-set `company_id` saat creating.
- Trait `BelongsToBranch` — untuk tabel transaksi yang ber-branch.
- `app/Scopes/CompanyScope.php` — global scope tenant.
- Middleware `IdentifyTenant` — set `company_id` (& `branch_id`) aktif per request.
- Super admin (Core) bisa impersonate / pilih company untuk dukungan.

---

## 2. Peta Modul, Sub-prefix & Dependensi

| # | Modul | Sub-prefix | Lingkup | Bergantung pada |
|---|---|---|---|---|
| 0 | **Core** (System Admin) | `wks_core_` | Lintas tenant | — |
| 1 | **Admin** (User & Akses) | `wks_adm_` | Per company | Core |
| — | **Master** (data referensi) | `wks_ms_` | Per company | Admin |
| 2 | **PMB** (Permintaan Mobil Masuk) | `wks_pmb_` | Per company/branch | Master |
| 3 | **LKM** (Kendaraan Masuk) | `wks_lkm_` | Per company/branch | Master |
| 4 | **Purchasing Order** | `wks_po_` | Per company/branch | Master, Inventory |
| 5 | **Gudang Sparepart** (Inventory) | `wks_inv_` | Per company/branch | Master, Purchasing |
| 6 | **Gudang Ban** (Tyre) | `wks_tyre_` | Per company/branch | Master, LKM/Servis |
| 7 | **Servis / Work Order** | `wks_svc_` | Per company/branch | LKM, Inventory, Master |
| 8 | **Price List Supplier** (harga beli part & ban) | `wks_price_` | Per company | Master(supplier), Inventory, Tyre |
| 9 | **Hutang Supplier / AP** (Kontrabon + Kasir) | `wks_ap_` | Per company/branch | Purchasing (PO/GRN), Master(supplier) |

> **PMB ≠ LKM (modul terpisah).** PMB = surat **pengantar dari Dispatcher** (pos
> dispatcher) yang diterbitkan saat driver minta masuk bengkel; LKM = pencatatan truk
> **benar-benar masuk** (gate-in) oleh Service Officer. Keduanya **independen**: LKM boleh
> merujuk PMB (`pmb_id` opsional) tapi tidak terbit otomatis dari PMB.

Urutan build: **Core → Admin → Master → Gudang Sparepart → Price List → Purchasing → PMB → LKM → Servis → Gudang Ban.**

---

## 3. Modul 0 — CORE (`wks_core_`) — System Admin Aplikasi

**Tujuan:** administrasi tingkat sistem di atas semua tenant; dipakai tim penyedia aplikasi.

**Fitur:** kelola company/tenant (aktif/suspend, provisioning + seed awal), super admin
lintas tenant + impersonate, audit log sistem, (opsional) plan/langganan & feature-flag modul per company.

**Tabel** (TANPA `company_id`)
- `wks_core_companies` — name, code, npwp, address, phone, status, timezone, logo
- `wks_core_plans` — name, price, limits (jsonb)  *(opsional, lihat §12)*
- `wks_core_subscriptions` — company_id, plan_id, start_date, end_date, status  *(opsional)*
- `wks_core_modules` — daftar modul aplikasi (lkm, inv, tyre, po, svc, …)
- `wks_core_company_modules` — company_id + module_id + is_enabled (feature-flag)  *(opsional)*
- `wks_core_audit_logs` — actor_id, company_id (nullable), action, entity, before/after (jsonb), ip, at

**Peran:** `SuperAdmin`, `SystemSupport`.

---

## 4. Modul 1 — ADMIN (`wks_adm_`) — User & Akses per Company

**Tujuan:** admin perusahaan mengatur user, hak akses (RBAC), dan pengaturan company.

**Fitur:** manajemen user dalam company, Roles & Permissions per company, pengaturan
company (pajak/PPN, penomoran dokumen, logo, jam operasional), pengelolaan **branch**,
**Notifikasi** (aturan WA/email/in-app yang dapat dikonfigurasi).

**Tabel**
- `users` — **tabel bawaan Laravel**, ditambah kolom `company_id` (nullable; null = super admin Core), `supplier_id` (portal)
- `wks_adm_roles`, `wks_adm_permissions`, `wks_adm_role_user`, `wks_adm_permission_role`
- `wks_adm_company_settings` — company_id, key, value (jsonb)
- `wks_adm_document_sequences` — company_id, doc_type, prefix, next_number
- `wks_adm_notification_rules` — aturan notifikasi per event (channel, penerima, ambang/eskalasi, ulang) — **dikonfigurasi di master**
- `wks_adm_notifications` — outbox/log kirim (database/email/whatsapp) + status + dedup

**Notifikasi (resolusi G3):** event (mis. `shift.session_overdue`, `stock.alert`, `pm.due`,
`truck.doc_expiry`) → baca `notification_rules` → kirim via channel. **WhatsApp** lewat gateway
`config/integrations.php` (`whatsapp.provider/base_url/token/sender/enabled`, pola `HrdGateway`),
**email** via Laravel Mail, **in-app** via Filament. Dijalankan job terjadwal + event aplikasi.

**Peran:** `Owner`, `Admin`.

---

## 5. Master Data (`wks_ms_`) — dikelola via modul Admin

Data referensi yang dipakai banyak modul. Level **company** kecuali disebut branch.

**Tabel**
- `wks_ms_branches` — company_id, name, code, address, phone  *(cabang)*
- `wks_ms_customers` — company_id, name, type (perorangan/armada), npwp, term, contacts (jsonb)
- `wks_ms_trucks` — unit armada: plat, VIN, tipe, KM/jam, **kepemilikan, BBM, default driver,
  STNK/KIR/pajak/asuransi + reminder, GPS** (detail di `DATABASE.md`)
- `wks_ms_truck_types` — company_id, name (tractor head, dump, box, tangki, …)
- `wks_ms_drivers` — sopir: kode, nama, kontak, **SIM (no/jenis/expiry)**, status;
  **terhubung ke ControlHub HRD sebagai "Mitra Kerja"** via `hrd_mitra_id` (lihat §15)
- `wks_ms_suppliers` — company_id, name, contacts, term, lead_time
- `wks_ms_warehouses` — company_id, **branch_id**, name, code, type (sparepart/ban)
- `wks_ms_locations` — company_id, warehouse_id, code (rak/bin)
- `wks_ms_uoms` — company_id, code, name (satuan)
- `wks_ms_categories` — company_id, type (part/ban), name
- `wks_ms_mechanics` — company_id, branch_id, name, skills (jsonb), status

---

## 6. Modul 3 — LKM (`wks_lkm_`) — Laporan Kendaraan Masuk

**Tujuan:** mencatat tiap truk **benar-benar masuk** (gate-in) sebagai dasar servis;
"pintu depan" alur kerja. **PMB (pengantar dispatcher) adalah modul terpisah** —
lihat §16; LKM hanya **merujuk** PMB secara opsional, tidak terbit otomatis darinya.

**Dua mode penerimaan** (setting company `lkm_intake_mode`, diatur di Admin):
- **`direct`** — Gate/Satpam langsung buat LKM (`pmb_id` null, tanpa pengantar).
- **`dispatcher_permit`** — driver lebih dulu ambil **PMB** di pos Dispatcher (modul PMB,
  §16). Saat truk tiba, **Service Officer** cek sistem; **jika PMB ada** untuk truk itu →
  buat LKM dengan `pmb_id` terisi (referensi). PMB & LKM tetap **entitas independen**.

**Fitur:** buat LKM (customer + unit, jam masuk, sopir, **KM/jam operasi terkini**,
keluhan; opsional rujuk PMB), checklist inspeksi awal + foto, status
(*Masuk → Diproses → Selesai → Keluar*), gate-out + surat jalan, jadi sumber Work Order,
laporan turnaround.

**Tabel** (dengan `company_id`, `branch_id`)
- `wks_lkm_entries` — lkm_no, **pmb_id** (FK `wks_pmb_requests`, opsional), **intake_mode**, customer_id, truck_id, entry_at, driver_name, km_in, complaints, status, created_by
- `wks_lkm_inspections` — lkm_entry_id, item, condition, note, photo_path
- `wks_lkm_gateouts` — lkm_entry_id, exit_at, km_out, released_by, surat_jalan_no

**Peran:** `ServiceAdvisor` (Service Officer — buat LKM), `Gate/Satpam`, `Admin`.
*(Dispatcher tidak buat LKM — ia menerbitkan PMB di modul §16; SoD terjaga.)*

---

## 7. Modul 4 — PURCHASING ORDER (`wks_po_`) — Sparepart

**Tujuan:** pengadaan sparepart dari supplier: permintaan → PO. **Penerimaan fisik
dikerjakan di Gudang (§8), bukan di sini** — Purchasing **memantau** pemenuhan PO (read).

**Fitur:** (opsional) Purchase Request, PO ke supplier + approval. **Penerimaan (Surat Jalan
MASUK supplier per PO + GRN wajib ber-PO → tally → posting `StockService` *in*) dilakukan di
panel Gudang** oleh operator gudang saat barang tiba (SoD: pembeli ≠ penerima). Tabel tetap
ber-prefix `wks_po_` (domain pembelian), tapi Resource-nya **tulis di Gudang, read di
Purchasing**. Purchasing memantau penerimaan partial, riwayat & status/outstanding PO.

**Tabel** (dengan `company_id`, `branch_id`)
- `wks_po_requests`, `wks_po_request_items`  *(opsional)*
- `wks_po_orders` — po_no, supplier_id, order_date, status, total
- `wks_po_order_items` — po_order_id, item, qty, unit_price, tax, qty_received
- `wks_po_supplier_deliveries`, `wks_po_supplier_delivery_items` — **Surat Jalan masuk** per PO (supplier_do_no, qty_shipped, source portal/manual)
- `wks_po_goods_receipts` — grn_no, **po_id (WAJIB)**, supplier_id, warehouse_id, status, **supplier_delivery_id** (opsional), do_supplier_no (fallback)
- `wks_po_goods_receipt_items` — grn_id, po_item_id, item, condition, qty_doc, qty_received, unit_cost, location_id

**Integrasi:** Serah Terima = satu-satunya jalur "stok masuk pembelian", **selalu ber-PO**,
melewati **tally** (lihat §8b) → `StockService`; tidak mengubah stok langsung.

**Portal Supplier (web `/vendor`) — supplier isi Surat Jalan sendiri:** supplier login (akun
di `users` + `supplier_id`, peran `Supplier`), lihat PO untuknya, lalu **buat Surat Jalan**
(`supplier_do_no` + **item sparepart & ban + qty_shipped**, tarik dari baris PO) → Submit
(`source=portal`). **Tujuan: operator gudang tak perlu menyalin SJ** — saat barang tiba, GRN
tinggal **memilih SJ yang sudah ada** + tally fisik. **Ban:** SJ hanya product+qty; **serial
diregistrasi saat GRN** (sisi kita). Tanpa portal → staf isi (`source=manual`, fallback). Scope
ketat per `supplier_id` (+ company); **feature-flag** per company; keamanan R46/R47 (akun
undangan, rate-limit, audit). ⚠️ Surat Jalan MASUK (ini) ≠ Surat Jalan KELUAR (`wks_inv_delivery_orders`, §8b).

**Peran:** `Purchasing`, `Gudang`, approval `Owner`/`Admin`; **`Supplier`** (portal, read PO + tulis SJ sendiri).

---

## 8. Modul 5 — GUDANG SPAREPART (`wks_inv_`) — Inventory

**Tujuan:** kelola stok sparepart: ketersediaan, lokasi rak, kondisi baru/bekas,
pergerakan, valuasi, opname, serta dokumen serah terima & surat jalan.

**Fitur:**
- Master sparepart (**SKU = kode internal kanonik**; nomor pabrikan **Hino/Isuzu/dst.**
  & brand aftermarket sebagai **cross-reference** di `wks_inv_part_numbers` — banyak per SKU;
  cari part dari nomor mana pun → ketemu SKU; supersession via `superseded_by_id`),
  kategori, satuan, fitment. Standar Hino = nomor OEM utama untuk part Hino.
- **Setting Rak/Lokasi** — `wks_ms_locations` **hierarki fleksibel** (`parent_id` + `node_type`
  area/zona/rak/shelf→bin; tak terpola) untuk menggambarkan struktur; bin = leaf storable +
  barcode. Atribut bin: `purpose` (storage/receiving/shipping/quarantine/scrap/staging),
  `condition_scope`, **kapasitas (soft warning)**, pick priority, blok. **Generator massal**:
  dari pola (mis. zona A, rak 01–10, level 1–5) buat ratusan bin + kode + barcode sekaligus
  (opsional, bukan keharusan). **Slotting** dapat di-set per gudang (`slotting_mode`
  dynamic/fixed/hybrid) + lokasi default per SKU (`wks_inv_part_locations`).
- **Stok baru vs bekas** — dimensi `condition` (new/used/rebuilt); saldo & WAC terpisah;
  gudang bisa dikhususkan `condition_scope=new/used` (gudang part baru vs bekas).
  Part bekas masuk dari **teardown/copotan** (movement `ref=teardown`, bukan PO).
- **Core return (old-for-new)** — pasang part baru **non-consumable** di WO → **wajib** kembalikan
  part bekas RUSAK sebagai **bukti** (`wks_inv_core_returns`), ditahan lalu **dijual scrap**.
  Beda dari teardown: core rusak **tidak** masuk stok layak-pakai. Telusur asal truck→LKM→WO.
- **Pergerakan stok** = SATU-SATUNYA cara stok berubah (in/out/transfer/adjustment/loan/retur),
  reservasi WO. Lihat **Jenis Transaksi Gudang** di bawah (8 fungsi).
- **Pengeluaran Sparepart (Bon/Part Issue)** — alur ber-approval tersambung WO→LKM→truck:
  **diusulkan Mekanik** → **di-review Service Officer (ServiceAdvisor)** (approve/reject, bisa
  potong qty) → **dikeluarkan Gudang** (movement out, HPP ke `wo_item`). SoD: pengusul ≠ reviewer.
- **Konversi UOM** — beli per *box*, simpan per *pcs*; satuan alternatif + factor per SKU
  (`wks_inv_part_uoms`); stok & WAC selalu di **UOM dasar**, dokumen snapshot `uom_factor`.
- **Stok negatif: diizinkan + alert** — `out` melebihi saldo tetap diproses, lalu buat
  `wks_inv_stock_alerts` + notifikasi ke Gudang/Admin (juga di bawah min/reorder).
- **Sesi Kerja Gudang (1 siklus/hari per gudang)** — **Buka Sesi = gate masuk panel Gudang**:
  operator pertama membuka (snapshot saldo awal), **dipakai bersama seharian**; selama belum
  dibuka, panel gudang terblok (lapis kedua: `StockService` juga tolak movement tanpa sesi).
  **1 sesi/gudang/hari** (`unique(warehouse_id, business_date)`). **Tutup Sesi = akhir hari,
  hanya Kepala Gudang/Supervisor** → ringkasan + snapshot **seluruh** saldo (selisih luar yang
  dicatat = **anomali**). Setelah ditutup, siklus hari final; lupa tutup → `force_closed`
  (supervisor/job akhir hari).
- **Stock opname** → adjustment; kartu stok & **valuasi WAC**; peringatan stok kritis & slow-moving.
- **Penerimaan dari supplier (DI SINI):** barang tiba di gudang → operator Gudang cocokkan
  **Surat Jalan supplier + PO** → **GRN (wajib ber-PO)** → tally → posting `StockService` *in*.
  PO dibuat di Purchasing (§7, read-only memantau). Tabel `wks_po_*` (lihat §8b).
- **Surat Jalan (barang keluar) + Tally Sheet** — §8b.
- *(Penjualan part eceran ke customer = future/dormant.)*

### Jenis Transaksi Gudang (semua via `StockService`, ter-tag Sesi Kerja)

Setiap perubahan stok = **dokumen transaksi** yang memposting movement. Daftar kanonik:

| # | Fungsi | Dokumen / Resource | Arah (movement_type) | Status | Catatan |
|---|---|---|---|---|---|
| 1 | **Terima part dari supplier** | GRN `wks_po_goods_receipts` (ber-PO + SJ supplier) | `in` | draft→checking→posted | ada — §8b; barang tiba di gudang |
| 2 | **Keluarkan part ke LKM/WO** | Bon `wks_inv_part_issues` | `out` | usul→review→issued | ada; HPP→`wo_item` |
| 3 | **Terima part bekas dari LKM** | Core Return `wks_inv_core_returns` (rusak, bukti) / **Teardown** (layak→stok used) | `in` | — | ada; bekas rusak ≠ stok layak |
| 4 | **Mutasi part dalam gudang** | **Relokasi** (bin↔bin, gudang sama) / Transfer (antar gudang) | `transfer_out`+`transfer_in` | — | qty total tetap, lokasi pindah |
| 5 | **Pinjamkan part keluar (storing, WAJIB kembali)** | **Peminjaman** `wks_inv_part_loans` 🆕 | `loan_out` → `loan_return` | open→partially_returned→returned (·cancelled) | tetap **aset** (belum dibebankan); bila tak kembali → dikonversi jadi Bon (pemakaian) |
| 6 | **Masukkan part temuan tak tercatat** | **Penyesuaian (reason `found`)** / Opname | `adjustment` (+) | — | nilai masuk pakai WAC terkini / harga acuan |
| 7 | **Retur part ke supplier (potong invoice)** | **Retur Pembelian** `wks_inv_purchase_returns` 🆕 (ref PO/GRN) | `return_supplier` (out) | draft→posted→**credited** | posting → stok turun; **nota retur** mengurangi tagihan supplier (AP, §9 PANELS — *menyusul*) |
| 8 | **Retur part dari LKM (tak jadi pakai)** | **Retur Bon** `wks_inv_issue_returns` 🆕 (reverse Bon) | `return_wo` (in) | draft→posted | part **baru** belum terpakai kembali ke stok; **kurangi biaya WO** (reverse HPP) |

> 🆕 = tabel baru (lihat §8c DATABASE). Semua transaksi **wajib Sesi Kerja `open`** + ter-tag
> `shift_session_id`; idempoten; mengikuti kebijakan stok negatif (izinkan+alert) & WAC.
> **#5 Loan vs #2 Bon:** Bon = pemakaian (dibebankan ke WO); Loan = pinjam sementara (masih
> aset, wajib kembali). **#8 Retur Bon (part baru tak jadi pakai) ≠ #3 Core/Teardown (part bekas).**

**Tabel** (dengan `company_id`)
- `wks_inv_spare_parts` — **sku** (internal), name, primary_make, superseded_by_id, …
- `wks_inv_part_numbers` — spare_part_id, ref_type(oem/aftermarket), brand, part_no, is_primary
- `wks_inv_part_uoms` — spare_part_id, uom_id, **factor** (konversi ke UOM dasar), is_purchase_default, barcode
- `wks_ms_locations` (master) — hierarki rak/bin (parent_id, node_type, purpose, kapasitas, barcode)
- `wks_inv_part_locations` — slotting: lokasi default/home bin per SKU (mode fixed/hybrid)
- `wks_inv_stock_alerts` — alert stok negatif/di bawah ambang + status + notifikasi
- `wks_inv_core_returns` — register part bekas rusak (bukti old-for-new): wo_item (1:1), truck/lkm telusur, disposition held/scrapped
- `wks_inv_scrap_disposals` — lot penjualan/pembuangan scrap *(ringan, future)*
- `wks_inv_stock_items` — saldo **fisik** per rak: spare_part_id, warehouse_id, location_id, **condition**, qty_on_hand, qty_reserved
- `wks_inv_stock_values` — saldo **valuasi/WAC** per gudang: spare_part_id, warehouse_id, condition, qty_on_hand, **avg_cost**, total_value, reorder override
- `wks_inv_stock_movements` — ledger append-only: **condition**, type, **qty_in/qty_out** (net_qty generated), ref_type/ref_id, unit_cost
- `wks_inv_part_issues`, `wks_inv_part_issue_items` — Bon Pengeluaran Sparepart (usul mekanik→review SO→keluar gudang); ref WO/LKM/truck; qty_requested/approved/issued
- `wks_inv_part_loans`, `wks_inv_part_loan_items` — **Peminjaman part keluar** (storing, wajib kembali); qty_loaned/returned, due/expected_return, status loan_status
- `wks_inv_purchase_returns`, `wks_inv_purchase_return_items` — **Retur ke supplier** (ref PO/GRN); → nota retur mengurangi tagihan (AP); status purchase_return_status
- `wks_inv_issue_returns`, `wks_inv_issue_return_items` — **Retur Bon** (part baru dari LKM tak jadi pakai → kembali stok); ref part_issue/WO; reverse HPP; status issue_return_status
- `wks_inv_stock_loc_snapshots`, `wks_inv_stock_val_snapshots` — snapshot saldo harian (anchor bulanan, dipangkas) untuk stok historis & kartu stok
- `wks_inv_stock_opnames`, `wks_inv_stock_opname_items` (per **condition**)
- `wks_inv_shift_sessions`, `wks_inv_shift_session_balances` — Sesi Kerja Gudang (opening/closing, snapshot saldo + anomali); movement di-tag `shift_session_id`
- `wks_inv_audits`, `wks_inv_audit_items`, `wks_inv_audit_findings` — **Audit Gudang** (kontrol independen; cek fisik, temuan, tindak lanjut) — lihat **Audit Gudang** di bawah

### Audit Gudang (kontrol independen — `wks_inv_audits` dst.)

Lapis **kontrol & jejak** atas stok, **terpisah dari operasi** (SoD: **Auditor** ≠ operator
Gudang ≠ Kepala Gudang). **Audit tidak mengubah stok** — ia menghasilkan **temuan**; koreksi
tetap lewat **opname/penyesuaian** (tertelusur) lalu auditor **verifikasi**. Tiga komponen:

- **① Audit formal** (`wks_inv_audits` + `_items` + `_findings`): auditor menjadwalkan audit
  (cycle count / spot check / full count / kepatuhan / investigasi) ber-scope (gudang/kategori/
  periode), **hitung independen** (book vs counted), lalu catat **temuan** (severity +
  rekomendasi). Tindak lanjut: PIC perbaiki → `resolved` → auditor `verified`/`closed`.
- **② Jejak immutable (audit trail)** — **tanpa tabel baru**: gabungan ledger
  **`wks_inv_stock_movements`** (append-only: pelaku `created_by`, jenis, ref, sesi) +
  **`wks_core_audit_logs`** (perubahan master/konfigurasi inv: before→after). Disajikan
  sebagai **Page read-only** terfilter (per SKU/gudang/user/tanggal/jenis).
- **③ Review anomali** — **tanpa tabel baru**: papan atas `wks_inv_stock_alerts` (stok negatif/
  di bawah min), **anomali Sesi** (`shift_sessions.anomaly_count` / `shift_session_balances.diff_qty`),
  movement tak wajar → tombol **Promosikan jadi Temuan** (set `source_type`/`source_id`).

**Peran:** **`Auditor`** (Internal Audit) — **read-only** atas stok/dokumen + **tulis** audit &
temuan; verifikasi koreksi. Operator/Kepala Gudang **tidak** menulis audit (subjek audit);
Owner/Admin melihat ringkasan. **Panel terpisah `/audit`** (`AuditPanelProvider`) untuk
independensi — lihat PANELS §5b.

**Service inti:** `StockService` — semua mutasi stok (per kondisi) dalam `DB::transaction()`.
Dipakai juga oleh Purchasing (serah terima), Surat Jalan, dan Servis (pemakaian part).
**`AuditService`** — kelola audit/temuan & tautkan koreksi (tak menyentuh saldo stok).

---

## 8b. Dokumen Gudang — Serah Terima, Surat Jalan & Tally Sheet (`wks_inv_` / `wks_po_`)

**Serah Terima dari Supplier (GRN)** — tabel `wks_po_goods_receipts` (domain pembelian), tapi
**dikerjakan di panel Gudang** saat barang tiba (Purchasing read-only memantau; SoD pembeli ≠ penerima):
- **Wajib referensi PO** (`po_id` not null) + rujuk **Surat Jalan supplier** — tak ada penerimaan tanpa PO.
- Alur status: *draft → checking (tally) → posted*; saat posted → stok masuk via `StockService`.
- Baris terhubung baris PO; `qty_doc` (PO) vs `qty_received` (hasil tally), condition, lokasi rak.

**Surat Jalan / Delivery Order** (`wks_inv_delivery_orders`) — barang **keluar** gudang:
- Tipe: transfer antar gudang / issue (ke WO/site) / retur ke supplier / lainnya.
- Berisi item + kondisi + qty; saat posting → movement out/transfer.
- ⚠️ Beda dari surat jalan **unit truk** (di LKM gate-out).

**Tally Sheet** (`wks_inv_tally_sheets`) — lembar **verifikasi hitung fisik**:
- Polimorfik: menempel ke **Serah Terima** (saat bongkar) atau **Surat Jalan** (saat muat).
- Catat `doc_qty` vs `counted_qty` (+ selisih) per item & kondisi; status draft → completed.
- Hasil tally mengisi `qty_received` GRN / mengonfirmasi DO sebelum posting.

**Tabel:** `wks_inv_delivery_orders`, `wks_inv_delivery_order_items`,
`wks_inv_tally_sheets`, `wks_inv_tally_sheet_items` (+ `wks_po_goods_receipts*` di §7).

**Peran:** `Gudang` (**terima/GRN**/keluar/tally), `Purchasing` (**read** SJ/GRN, kelola PO), `Admin`.

**Peran:** `Gudang` (operator: buka sesi, transaksi), **`KepalaGudang`/Supervisor** (tutup/
force-close sesi — izin `shift_session.close`), `Admin`.

> **Izin closing (permission-based):** `shift_session.close` dimiliki peran **`KepalaGudang`**.
> **Sampai peran khusus di-provisioning, izin ini default melekat ke `Owner`/`Admin`** (sebagai
> supervisor) — bukan di-hardcode; cukup atur lewat Shield. Operator `Gudang` **tidak** punya izin ini.

---

## 9. Modul 6 — GUDANG BAN (`wks_tyre_`) — Tyre

**Tujuan:** kelola ban sebagai **aset ber-nomor seri dengan siklus hidup & posisi pada unit**.
Setiap ban fisik **unik & wajib punya serial number** (`serial_no`).

**Dua level data ban:**
- **Tyre Product** (`wks_tyre_products`) — *model/spesifikasi* ban: merek + ukuran + pola.
  Jadi acuan **harga beli** (Price List) & katalog.
- **Tyre (unit)** (`wks_tyre_tyres`) — *fisik per serial*, menunjuk ke satu product;
  punya siklus hidup, posisi, tread depth sendiri.

**Fitur:**
- **Master model ban** (`wks_tyre_products`) — brand+size+pattern (acuan harga) + spesifikasi:
  `axle_application` (steer/drive/trailer/all), tube_type, load_index, ply_rating,
  **`min_tread_mm`** (ambang ganti), **`retread_max`** (maks vulkanisir).
- **Registrasi unit** ber-serial unik (DOT, tread, kondisi new/used/retread), status lifecycle
  (*in_stock → installed → removed → retreading → **afkir** → scrapped*).
- **Lokasi rak** — ban di gudang menempati **bin** (`wks_ms_locations`, reuse master Inventory);
  `location_id` pada unit; gudang `type=tyre`/`both`.
- **Skema posisi per axle config** — `wks_ms_axle_positions` mendefinisikan slot valid per
  `axle_config` truk (4x2/6x4/…); posisi instalasi **divalidasi** + jadi **diagram layout ban**;
  ban *steer* di sumbu *drive* → peringatan fitment. (R22 termitigasi.)
- **Instalasi/rotasi** (`wks_tyre_installations`) — posisi FL/FR/RL1/…, KM pasang & lepas, alasan
  lepas; **integritas posisi** via partial-unique (1 ban/slot; 1 ban tak terpasang ganda).
- **Pergerakan** = SATU-SATUNYA cara ubah posisi/status (`wks_tyre_movements`, enum
  `tyre_movement_type`), via **`TyreService`** dalam transaksi; **wajib Sesi Kerja Gudang** terpadu.
- **Ban bekas dilepas** — masih layak → masuk **stok `used` di Gudang Bekas** + keputusan
  (pakai lagi / vulkanisir / afkir); tak layak → usul afkir.
- **Konfirmasi Afkir (kontrol)** — usul afkir (operator) → **dikonfirmasi Kepala Gudang/
  Supervisor** (izin `tyre.condemn`) → status **`afkir`** + movement `condemn` (alasan, confirmed_by).
  SoD: pengusul ≠ pengkonfirmasi (cegah ban hilang/dibuang sepihak). Lalu **pindah ke Gudang
  Afkir** (movement `transfer`).
- **Inspeksi berkala** (tread & tekanan) → update tread unit + alert bila < `min_tread_mm`.
- **Vulkanisir** (`wks_tyre_retreads`) — kirim/terima sbg movement; **biaya dikapitalisasi** ke
  `book_value`; gagal → scrap; blok bila `retread_count ≥ retread_max`.
- **Opname ban** (`wks_tyre_opnames`) — cek **kehadiran** per serial (match/missing/extra/misplaced).
- **Alert ban** (`wks_tyre_alerts`) — tread_low, inspection_due, retread_overdue, dot_aged, low_stock.
- **Jual/Buang Afkir** (`wks_tyre_disposals`) — dari **Gudang Afkir**, lot **jual afkir** /
  buang ban `afkir` + proceeds → status `scrapped` (disposed). Movement `scrap`.
- **Biaya per KM** — `book_value / total_km_run` (acquired_cost + Σ retread ÷ KM tempuh).
- **Sesi Kerja Gudang terpadu** — satu sesi mencakup mutasi part **dan** ban (gudang `both`);
  movement ban di-tag `shift_session_id` (`wks_inv_shift_sessions`).

### Siklus & Tahap Gudang Ban (3 gudang: Baru → Bekas → Afkir)

Ban melewati **tahap gudang** (warehouse type=ban, klasifikasi `tyre_stage` baru/bekas/afkir —
opsional; bisa juga 1 gudang dgn area). Semua via `TyreService` (wajib Sesi `open`, ter-tag):

| # | Fungsi | Movement | Status → | Lokasi (tyre_stage) |
|---|---|---|---|---|
| 1 | **Ban masuk dari supplier** | `receipt` (rujuk **SJ supplier** dari portal/manual → GRN ber-PO) | `in_stock` (new) | **Gudang Ban Baru** |
| 2 | **Ban keluar ke LKM/WO** (pasang) | `install` | `installed` | terpasang di truk |
| 3 | **Ban bekas masuk Gudang Bekas** (lepas) | `removal` | `removed`→`in_stock` (used) | **Gudang Bekas** |
| 4 | **Konfirmasi jadi Afkir** (kontrol) | `condemn` | `afkir` | (masih di Gudang Bekas) |
| 5 | **Pindah ke Gudang Afkir** | `transfer` | `afkir` | **Gudang Afkir** |
| 6 | **Jual Afkir** | `scrap` (disposal sold) | `scrapped` | keluar (terjual) |

> **#4 = titik kontrol:** operator **usul**, **Kepala Gudang/Supervisor konfirmasi** (izin
> `tyre.condemn`) — SoD agar ban tak "hilang/dibuang" sepihak. Selingan: ban bekas layak bisa
> ke **vulkanisir** (retread) atau **dipakai lagi**, bukan afkir. Telusur penuh per serial di
> `wks_tyre_movements` (append-only).

**Tabel** (dengan `company_id`)
- `wks_ms_axle_positions` (master) — skema posisi per `axle_config` (slot, sumbu, sisi, role)
- `wks_tyre_products` — brand, size, pattern, category_id, axle_application, tube_type, load_index, ply_rating, min_tread_mm, retread_max
- `wks_tyre_tyres` — **serial_no (unik per company)**, tyre_product_id, dot, tread_depth, condition, status, warehouse_id, **location_id**, acquired_cost, retread_cost_total, **book_value**, total_km_run, retread_count
- `wks_tyre_movements` — tyre_id, type (`tyre_movement_type`), warehouse_id, location_id, **shift_session_id**, unit_cost, ref, moved_at
- `wks_tyre_installations` — tyre_id, truck_id, position, installed_at/km_install, removed_at/km_remove, removal_reason, work_order_id *(partial-unique posisi)*
- `wks_tyre_inspections` — tyre_id, inspected_at, tread_depth, pressure, result, recommendation
- `wks_tyre_retreads` — tyre_id, supplier_id, sent_at, received_at, cost, result, delivery_order_id
- `wks_tyre_opnames`, `wks_tyre_opname_items` — opname kehadiran per serial
- `wks_tyre_alerts` — peringatan ban (tread/inspeksi/retread/DOT/stok)
- `wks_tyre_disposals`, `wks_tyre_disposal_items` — lot scrap + proceeds
- `wks_tyre_shift_session_tyres` — snapshot kehadiran ban saat buka/tutup sesi

**Service inti:** `TyreService` — instalasi, lifecycle, retread, opname, valuasi per-unit &
biaya/KM (semua mutasi dalam `DB::transaction()` + Sesi Kerja Gudang). Berbagi gudang/lokasi &
sesi dgn Inventory; berbeda dari `StockService` karena **per-unit serial (tanpa WAC)**.

**Peran:** `Gudang`/`GudangBan` (terima/lepas/usul afkir/pindah/jual), `Mekanik` (pasang/lepas),
**`KepalaGudang`/Supervisor** (**konfirmasi afkir** — izin `tyre.condemn`), `Admin`.

---

## 10. Modul 7 — SERVIS / WORK ORDER (`wks_svc_`)

**Tujuan:** eksekusi perawatan/perbaikan armada dari LKM sampai selesai; mencatat **biaya
(cost)** per unit. *(Mode internal — lihat §0 Mode Operasi.)*

**Fitur (aktif):** Work Order dari LKM, **WO Plan (rencana kerja: task + langkah)**,
penugasan/ambil WO oleh mekanik, **request part ke gudang** (reservasi/pemakaian via
`StockService`), pasang ban (via `TyreService`), catat jam kerja, status (*Antri → Menunggu
Part → Dikerjakan → QC → Selesai → Diserahkan*), **servis berkala/PM** berbasis KM/jam/waktu
+ reminder, **rekap biaya per WO & per unit** (cost: part HPP + ban + jasa).

> ⚠️ **Fase sekarang: TANPA estimasi biaya.** Tidak ada langkah estimasi/penawaran nilai
> uang atau persetujuan biaya di depan. **WO Plan = rencana KERJA** (apa & bagaimana
> dikerjakan), **bukan estimasi biaya**. Biaya WO **terbentuk dari pemakaian aktual**
> (part HPP/WAC dari Bon + ban + jasa `std_cost`) dan direkap setelah selesai. *(Estimasi
> biaya & penawaran/approval pelanggan = future/dormant, selaras mode internal §0.)*
> Catatan: **estimasi waktu** per task (`est_minutes`) tetap ada — itu untuk **produktivitas
> (jam)**, bukan biaya.
**Core return wajib:** tiap pemasangan part baru **non-consumable** harus disertai
pengembalian part bekas rusak (`wks_inv_core_returns`, §8) sebagai bukti — WO **tak bisa
`done`** bila core belum kembali (qty cocok). Consumable (oli/filter/grease) dikecualikan.

**WO Plan (rencana kerja berlangkah):** **rencana kerja** = **daftar task**
(`wks_svc_wo_tasks`, *apa yang dikerjakan*), dan tiap task dirinci menjadi **langkah/sub-step**
(`wks_svc_wo_task_steps`) — *bagaimana mengerjakannya*. Contoh task "Ganti ban" → langkah:
turunkan ban · periksa · minta ban baru · pasang baru · kembalikan lama · periksa tekanan
akhir.

**Siapa & kapan menyusun:** WO Plan **dibuat oleh Mekanik** — **bisa bersama Service
Officer** — **setelah mekanik mengambil/ditugaskan WO** (bukan dirancang di depan oleh SA
saja). Jadi yang akan mengerjakan ikut menyusun rencananya. Langkah boleh **disalin dari
template jasa** (`wks_svc_service_steps`) sebagai titik awal lalu disesuaikan. Saat eksekusi
**mekanik mencentang** tiap langkah (`done`/`skipped` + catatan) dan **boleh menambah langkah
`adhoc`** untuk pekerjaan baru di lapangan. *(Service Officer/Kepala Mekanik tetap bisa
ikut menyusun & meninjau di panel Servis.)*

> **WO Plan = panduan kerja, bukan gerbang kaku.** Sejak awal mekanik sudah punya
> rangkaian langkah sebagai pemandu, **tapi tidak mengikat**: langkah boleh `skipped`
> (dengan catatan), urutan tak dipaksa, dan rencana **menyesuaikan pekerjaan nyata**
> (tambah `adhoc`) saat update. Task tetap bisa `done` walau ada langkah ter-skip — sistem
> hanya **memberi peringatan**, tidak memblokir (gate keras tetap: core-return & semua task
> selesai, lihat §6/§8c DATABASE).

**Eksekusi pekerjaan oleh mekanik (handheld — terukur):** tiap task ditugaskan ke
mekanik (`wks_svc_task_assignments`, mendukung **multi-mekanik** lead/helper). Mekanik
memperbarui pekerjaan lewat **handheld** dengan **clock in/out live** (Mulai → Jeda →
Lanjut → Selesai); tiap segmen tercatat di `wks_svc_task_time_logs` (timestamp **dari
server**) → menghasilkan **estimasi vs aktual** (`est_minutes` vs `actual_minutes`),
dasar laporan **produktivitas mekanik** & **turnaround**. Handheld diwujudkan **dua jalur
sekaligus**: (a) **panel Filament Mekanik responsif** (online) dan (b) **API + PWA offline**
(antrian lokal + sync **idempoten** via `client_event_id`) agar tahan putus koneksi di bengkel.
Logika di `TaskTimeService` (transaksi DB + guard satu segmen aktif per mekanik). Lihat
WORKFLOWS §6b, DATABASE §10, PANELS §4b.

**Fitur (future/dormant):** harga jual, **Invoice & pembayaran** ke customer
(termasuk konsolidasi armada). Tabel disiapkan, UI non-aktif via feature-flag.

**Tabel** (dengan `company_id`, `branch_id`)
- `wks_svc_work_orders` — wo_no, lkm_entry_id, truck_id, mechanic_id, status, total_cost
- `wks_svc_work_order_items` — work_order_id, item_type (service/part/tyre), ref_id, qty,
  **unit_cost** (HPP, selalu diisi), **unit_price** (jual, nullable — *future*)
- `wks_svc_wo_tasks` — **daftar pekerjaan** per WO (title, service_id, status, `est_minutes`,
  **`actual_minutes`** cache, started/completed_at, requires_part, result_note)
- `wks_svc_wo_task_steps` — **langkah/sub-step per task** (WO Plan; seq, title, status
  `pending/done/skipped`, source `planned/adhoc`, done_by/at, result_note)
- `wks_svc_service_steps` — **template langkah** per jasa katalog (opsional; prefill plan)
- `wks_svc_task_assignments` — penugasan mekanik ke task (lead/helper; multi-mekanik)
- `wks_svc_task_time_logs` — **segmen jam kerja** clock in/out; `client_event_id` (idempoten sync offline)
- `wks_svc_services` — name, std_cost, est_hours  *(katalog jasa; std_price jual — future)*
- `wks_svc_pm_schedules` — truck_id, type, interval_km/hours/days, next_due_km, next_due_date
- `wks_svc_invoices` *(future/dormant)* — invoice_no, customer_id, work_order_id (nullable), total, status
- `wks_svc_payments` *(future/dormant)* — invoice_id, paid_at, amount, method

**Peran:** `ServiceAdvisor`, `KepalaMekanik`, `Mekanik`, `Admin`. (`Kasir` — future.)

---

## 10b. Modul 8 — PRICE LIST SUPPLIER (`wks_price_`) — Harga Beli Sparepart & Ban

**Tujuan:** mengelola **harga BELI dari supplier** untuk sparepart & ban, **bisa di-update**,
mendukung **multi-supplier** per item, dengan **kejelasan status PPN** dan riwayat perubahan.
Menjadi acuan harga saat membuat **Purchase Order** dan membandingkan supplier.

**Catatan lingkup:** modul ini = **harga beli (supplier)**, BUKAN harga jual.
Harga jual ke pelanggan ditentukan terpisah (markup / lihat §14).

**Fitur**
- **Price list per supplier** — satu supplier punya daftar harganya sendiri.
- **Multi-supplier per item** — satu part/ban bisa punya harga dari banyak supplier
  → bisa dibandingkan (harga termurah / supplier preferensi) saat membuat PO.
- Item harga **polimorfik**: `spare_part` (ref `wks_inv_spare_parts`) atau
  `tyre_product` (ref `wks_tyre_products` — model ban, BUKAN unit serial).
- **Status PPN per harga** — `tax_type`:
  - `exclusive` → harga belum termasuk PPN (PPN ditambahkan saat PO)
  - `inclusive` → harga sudah termasuk PPN
  - `non_pkp`   → tanpa PPN (supplier non-PKP)
  - `tax_rate` disimpan (mis. 11%) agar perhitungan historis tetap benar.
- **Update harga**: per item, atau **massal** (per kategori / % / impor Excel/CSV).
- **Effective date** + masa berlaku (`valid_from`/`valid_to`) + `is_active`.
- **Riwayat harga** (`wks_price_histories`): tiap perubahan tercatat (lama→baru, oleh siapa, kapan).
- (Opsional) **harga bertingkat** per kuantitas (`min_qty`) untuk diskon volume.
- Mata uang per list (`currency`, default IDR) untuk supplier impor *(opsional)*.

**Tabel** (dengan `company_id`; level company, bukan branch)
- `wks_price_lists` — supplier_id (FK `wks_ms_suppliers`, **wajib**), name, currency,
  `tax_type` (exclusive/inclusive/non_pkp), `tax_rate`, is_active, valid_from, valid_to
- `wks_price_list_items` — price_list_id, item_type (`spare_part`/`tyre_product`), item_id,
  price, min_qty (default 1), effective_from, `tax_type` (nullable override), `tax_rate` (nullable override)
- `wks_price_histories` — item_type, item_id, price_list_id, supplier_id, old_price,
  new_price, old_tax_type, new_tax_type, source (manual/bulk/import), changed_by, changed_at

**Integrasi:**
- **Purchasing** (`wks_po_`) ambil harga beli + status PPN via `PricingService`
  (pilih supplier → harga aktif; bisa bandingkan antar supplier).
- Saat PO dibuat, harga & PPN **ter-snapshot** di `wks_po_order_items` (perubahan price
  list kemudian tidak mengubah PO lama).
- Penerimaan (GRN) memakai harga PO sebagai dasar **cost** untuk valuasi stok (`StockService`).

**Service inti:** `PricingService` — resolve harga supplier aktif (termasuk normalisasi
PPN ke nilai net/gross) + catat `wks_price_histories` saat update.

**Peran:** `Purchasing`, `Admin`, `Owner` (update harga); read saat PO.

> **Master ban untuk harga:** karena tiap ban fisik **unik & ber-serial**
> (`wks_tyre_tyres`), harga beli mengacu ke **model ban** `wks_tyre_products`
> (merek + ukuran + pola). Saat barang diterima, tiap unit ban dibuat dengan serial
> sendiri yang menunjuk ke model tersebut. (Lihat modul 5, ditambah `wks_tyre_products`.)

---

## 11. Ringkasan Kepemilikan & Tenancy

| Data | Modul | Prefix | `company_id` | `branch_id` |
|---|---|---|---|---|
| companies, plans, modules, audit | Core | `wks_core_` | ❌ | ❌ |
| users | Admin | (Laravel `users`) | ✅ (nullable) | — |
| roles, permissions, settings, sequences | Admin | `wks_adm_` | ✅ | ❌ |
| branches | Master | `wks_ms_` | ✅ | (itu sendiri) |
| customers, trucks, suppliers, uom, category, truck_types | Master | `wks_ms_` | ✅ | ❌ (company-level) |
| warehouses, locations, mechanics | Master | `wks_ms_` | ✅ | ✅ |
| pmb (permintaan mobil masuk) | PMB | `wks_pmb_` | ✅ | ✅ |
| lkm | LKM | `wks_lkm_` | ✅ | ✅ |
| PO, GRN *(GRN dikerjakan di panel Gudang)* | Purchasing | `wks_po_` | ✅ | ✅ |
| spare parts, stock | Inventory | `wks_inv_` | ✅ | (via warehouse) |
| tyres, installations | Tyre | `wks_tyre_` | ✅ | (via warehouse) |
| work orders, invoices, PM | Servis | `wks_svc_` | ✅ | ✅ |
| tyre products (model) | Tyre | `wks_tyre_` | ✅ | ❌ |
| supplier price lists, items, history | Price List | `wks_price_` | ✅ | ❌ (company-level) |
| supplier invoices (kontrabon), payments, allocations | AP | `wks_ap_` | ✅ | ✅ |

---

## 12. Konvensi Penamaan (sub-prefix)

- Format tabel: **`wks_<modul>_<entitas_jamak>`** — `snake_case`, jamak, bahasa Inggris.
  Contoh: `wks_inv_stock_movements`, `wks_lkm_entries`, `wks_svc_work_orders`.
- Sub-prefix modul: `core`, `adm`, `ms`, `pmb`, `lkm`, `po`, `inv`, `tyre`, `svc`, `price`, `ap`.
- Tabel pivot: `wks_<modul>_<a>_<b>` urut abjad → `wks_adm_permission_role`.
- `users` = tabel bawaan Laravel, **tetap tanpa prefix** (+ kolom `company_id`).
- Kode PHP (Model/Controller/Service) **tetap bersih tanpa prefix**; map via `$table`.
  Disarankan namespace per modul: `App\Models\Inv\SparePart`, `App\Models\Svc\WorkOrder`.
- Detail lengkap: `.claude/skills/workshop-feature/NAMING_CONVENTIONS.md`.

---

## 13. Roadmap Build

1. **Core** — companies + tenancy (trait/scope/middleware) + super admin.
2. **Admin + Master** — users, RBAC, branch, master data.
3. **Gudang Sparepart** — master part + `StockService`.
4. **Price List Supplier** — harga beli part & ban (multi-supplier, PPN) + `PricingService` + riwayat.
5. **Purchasing** — PO (ambil harga supplier). **Penerimaan/GRN dikerjakan di Gudang** → stok masuk.
6. **PMB** — pengantar Dispatcher (sebelum LKM); `pmb_id` opsional di LKM.
7. **LKM** — kendaraan masuk.
8. **Servis / Work Order** — WO + WO Plan (task + langkah) + biaya aktual + PM. *(Estimasi biaya, invoice & harga jual/markup = future, lihat §14.)*
9. **Gudang Ban** — `wks_tyre_products` + unit serialized + instalasi.
10. **Hutang Supplier / AP** — Kontrabon (salin tagihan + cek satu per satu) + Kasir (rekening bank + request pembayaran maker→checker + realisasi giro/digital). *Dibangun setelah Purchasing/GRN ada; panel `/kontrabon` & `/kasir` sudah di-scaffold (§17, PANELS §7–§9).*

---

## 14. Masih Perlu Dikonfirmasi

Sudah terjawab: ✅ Price List = **harga beli supplier** · ✅ **multi-supplier** per item ·
✅ status **PPN** eksplisit (exclusive/inclusive/non_pkp + rate) · ✅ ban **unik per serial** +
master **tyre product** sebagai acuan harga · ✅ **mode INTERNAL** (cost-focused; jual & invoice
ke customer disiapkan tapi non-aktif — lihat §0).

**Dikonfirmasi sesi 2026-06:** ✅ **PMB** modul/panel terpisah (`wks_pmb_`, §16) · ✅ **WO Plan**
= task + langkah (`wks_svc_wo_task_steps`), disusun Mekanik+SO setelah ambil WO, panduan tak
mengikat · ✅ **fase ini TANPA estimasi biaya** (biaya = aktual) · ✅ **Sesi Gudang 1 siklus/hari
per gudang** (opening = gate panel, closing = Kepala Gudang/Supervisor) · ✅ **Penerimaan (SJ
supplier + GRN) di panel Gudang**, Purchasing read · ✅ **isolasi tenant+branch** di-scaffold
(scope global, lihat `IMPLEMENTATION.md`).

Masih perlu diputuskan:
1. **Plan/langganan & feature-flag modul**: pakai sekarang atau siapkan struktur & enforce nanti? *(default: siapkan, enforce nanti)*
2. **Mechanics** level branch (default) atau lintas branch dalam satu company?
3. **Penilaian biaya part di WO**: metode valuasi **FIFO** atau **Average (WAC)**? *(rekomendasi: Average/WAC, lebih sederhana)*
4. **Pemilik unit internal**: cukup di level **branch/divisi**, atau tetap pakai entitas
   `wks_ms_customers` sebagai cost-center pemilik armada? *(default: customer = cost-center internal)*
5. **Mata uang**: hanya IDR, atau perlu multi-currency untuk supplier impor?
6. **Pajak lain**: selain PPN, ada **PPh / bea** yang perlu dicatat di harga beli?

*Catatan: penentuan harga JUAL (markup/manual) ditunda sampai fitur penjualan diaktifkan;
kolom `unit_price`/`std_price` sudah disiapkan (nullable).*

7. ✅ **Integrasi ControlHub HRD (driver)**: via **REST API** (`HrdGateway`). Lihat §15.

---

## 15. Integrasi Eksternal — ControlHub HRD (Master Driver)

**Tujuan:** master **driver** (sopir) di Workshop dipetakan ke entitas **"Mitra Kerja"**
di aplikasi **ControlHub HRD** (driver = mitra kerja / pekerja mitra, bukan karyawan tetap),
sehingga data tidak diinput ganda — HRD sebagai *system of record* mitra kerja, Workshop
memakainya untuk operasional armada.

**Pola data (loose coupling):**
- `wks_ms_drivers.hrd_mitra_id` = referensi ke **Mitra Kerja** di HRD.
- `source` = `hrd` (bersumber & disinkron dari HRD, field inti read-only) atau `manual` (lokal).
- `hrd_synced_at` = jejak sinkron terakhir.
- Data spesifik operasional (SIM jenis/expiry, penugasan unit) tetap dikelola di Workshop.

**Pemetaan (mapping):**
- **Driver ↔ Mitra Kerja**: `hrd_mitra_id` (unik per company); satu driver = satu mitra kerja HRD.
- **Tenant**: `hrd_company_id` memetakan `company_id` Workshop ↔ company/tenant di HRD
  (bila id berbeda). Bila perlu lebih dari satu pemetaan, gunakan tabel pemetaan
  `wks_core_hrd_mappings` (company_id, hrd_company_id, base_url/token).
- Hanya mitra kerja **berperan driver** di HRD yang ditarik (filter saat sinkron).

**Metode koneksi: REST API (terkunci).** Workshop menarik data Mitra Kerja dari HRD
lewat REST API (HRD & Workshop bisa beda server/DB — coupling paling longgar).

**Desain API (Laravel):**
- Service `HrdGateway` (HTTP client) membungkus semua panggilan ke HRD.
- Config `config/integrations.php`: `hrd.base_url`, `hrd.token`, `hrd.enabled` (feature-flag),
  pemetaan `company_id` → `hrd_company_id` (atau tabel `wks_core_hrd_mappings`).
- **Auth**: Bearer token / API key per tenant (disimpan terenkripsi).
- **Endpoint yang dipakai** (read-only dari sisi Workshop), contoh:
  - `GET /api/mitra-kerja?role=driver&company_id={hrd_company_id}&updated_since={ts}` → daftar/sinkron delta
  - `GET /api/mitra-kerja/{id}` → detail satu mitra kerja
- **Sinkron**: manual (`/app/master/drivers/sync`) atau cron terjadwal; pakai
  `updated_since` (incremental) + simpan `hrd_synced_at`.
- **Ketahanan**: timeout + retry/backoff, log kegagalan; bila API down, driver `source=hrd`
  tetap dipakai dari cache lokal (tidak memblok operasional).
- Pola `HrdGateway` ini reusable bila kelak entitas lain perlu di-link ke HRD.

---

## 16. Modul 2 — PMB (`wks_pmb_`) — Permintaan Mobil Masuk (Pengantar Dispatcher)

**Tujuan:** mencatat **surat pengantar dari pos Dispatcher** saat driver datang meminta
truk masuk bengkel. PMB adalah **dokumen bernomor** yang dibawa driver ke gate; **bukan**
pencatatan truk masuk (itu LKM, §6). **Diaktifkan** hanya bila company memakai mode
`lkm_intake_mode = dispatcher_permit` (setting Admin); pada mode `direct`, modul/panel PMB
tidak dipakai.

**Alur singkat (independen dari LKM):**
1. Driver datang ke pos Dispatcher → **Dispatcher terbitkan PMB** (pilih/cocokkan truk +
   sopir, catat keluhan/tujuan) → PMB `issued`, bisa **dicetak** (surat bernomor).
2. Driver bawa PMB ke gate/LKM. Di **modul LKM**, **Service Officer** cek sistem; **jika
   PMB ada** → buat LKM dengan `pmb_id` (referensi). Saat itu PMB ditandai `used`.
3. PMB yang tak jadi dipakai bisa di-`cancel`. **Tidak ada** auto-pembuatan LKM dari PMB.

**Fitur:** terbitkan/cetak PMB, antrian PMB aktif (`issued`) per branch, pencocokan truk &
sopir ke master, batalkan PMB, telusur PMB → LKM (bila terpakai).

**Tabel** (dengan `company_id`, `branch_id`)
- `wks_pmb_requests` — pmb_no, truck_no (plat ketik), truck_id/customer_id (dicocokkan
  Dispatcher), driver_id/driver_name, complaints/purpose, issued_at, status
  (`issued`/`used`/`cancelled`), used_lkm_id (telusur, opsional), note, created_by.

**Peran:** `Dispatcher` (penerbit PMB). **SoD:** Dispatcher menerbitkan PMB ≠ Service
Officer (`ServiceAdvisor`) yang membuat LKM (§6).

> **Build order:** dibangun **sebelum** LKM (LKM punya FK opsional `pmb_id` ke sini), tapi
> tidak saling memblok karena `pmb_id` nullable. Detail kolom di `DATABASE.md` §8a, alur di
> `WORKFLOWS.md` §5, panel di `PANELS.md` §2b.

---

## 17. Modul 9 — HUTANG SUPPLIER / ACCOUNTS PAYABLE (`wks_ap_`) — Kontrabon & Kasir

**Tujuan:** mengelola **hutang ke supplier** sparepart & ban — dari terima/verifikasi **faktur
supplier (kontrabon)** sampai **pembayaran**. Hilir rantai *procure-to-pay*:

```
PO ─► GRN (stok masuk, cost) ─► KONTRABON (salin tagihan + cek satu per satu: barang/SJ/faktur pajak/PO) ─► approve (hutang)
   ─► KASIR: Request Pembayaran (maker→checker) ─► Realisasi (giro/digital) ─► hutang lunas
```

> **Dua panel terpisah, satu domain `wks_ap_`** (SoD): **Kontrabon** (`/kontrabon`, peran
> **Finance/AP**) mengakui hutang; **Kasir** (`/kasir`, peran **Kasir**) membayar. Pemisahan
> panel sesuai permintaan: yang mengurus = **divisi Finance** (Kontrabon) dan **Kasir** (bayar).
> ⚠️ **Mode internal tetap berlaku:** ini hutang ke **supplier** (sah), **berbeda** dari
> invoice/penjualan **ke customer** yang masih *future/dormant* (§0, §10).

**Konsep:** **kontrabon = dokumen yang KITA buat** ("tanda terima tagihan") untuk **menyalin
tagihan supplier**, lalu **review & cocokkan satu per satu**. Satu kontrabon (per supplier) bisa
memuat **satu atau banyak** faktur/tagihan supplier.

**Fitur — Kontrabon (Finance/AP, pengakuan hutang):**
- **Salin tagihan supplier ("tukar faktur")** — buat kontrabon (header per supplier + tanggal
  terima + jatuh tempo), lalu tambah **baris tagihan**: no. faktur supplier + no. **Faktur Pajak
  (NSFP)** + tanggal + nilai + PPN (`tax_type`/`tax_rate`). **Bisa 1 atau banyak** baris.
- **Review satu per satu** — tiap baris tagihan dicek **checklist (4)**: ☑ **barang diterima**
  (cocok GRN `wks_po_goods_receipts`) · ☑ **Surat Jalan** · ☑ **Faktur Pajak (PPN)** · ☑ **PO &
  nominal cocok**. Keempat true → baris `ok`; ada kurang/selisih → `problem` (+ catatan).
- **Jatuh tempo** otomatis dari `supplier.payment_term_days` (boleh override) → **aging hutang**.
- **Verifikasi → Approve** — header `verified` **hanya bila semua baris `ok`** (gate) →
  `approved` (**hutang diakui**). **SoD:** verifikator ≠ approver. Hanya kontrabon `approved`
  yang bisa dibayar.
- **Nota retur** dari Gudang (`wks_inv_purchase_returns`, MODULES §8 #7) **mengurangi** tagihan
  (`credit_amount`) → `outstanding` turun.
- Anti dobel-input faktur: `unique(company_id, supplier_id, supplier_invoice_no)` di baris.

**Fitur — Kasir (pembayaran supplier sparepart/ban):**
- **Master Rekening Bank/Kas** (`wks_ap_bank_accounts`) — Kasir kelola rekening sumber dana
  (bank/tunai); penanda `supports_giro`/`supports_digital` membatasi metode per rekening.
- **Request Pembayaran** (`wks_ap_payment_requests`) — **alur maker→checker**: maker pilih
  kontrabon `approved` jatuh tempo + metode + rekening → submit → **checker approve/reject**
  (SoD: pengaju ≠ penyetuju). **Alokasi** ke 1/banyak kontrabon (partial; anti over-pay).
- **Realisasi Pembayaran** (`wks_ap_payments`) atas request `approved` — **transfer/digital**
  (digital banking maker-checker; simpan `digital_ref`) `posted` langsung; **giro** lewat Register Giro.
- **Register Giro** (`wks_ap_giros`) — kontrol: **register di aplikasi sebelum tanda tangan → print
  (lembar register) → giro fisik ditandatangani → verifikasi (giro fisik HARUS sesuai sistem, dicek
  lewat print) → diserahkan** (payment `posted`) → cair (`cleared`)/gagal (`bounced`). **SoD:**
  registrar (Kasir) ≠ penanda tangan ≠ pemeriksa.
- **Post** → `ApService`: `paid_amount` kontrabon naik → `partially_paid`/`paid`.
- Rekap **kas keluar** per rekening/metode/periode + **daftar giro** (belum cair/jatuh tempo).

**Tabel** (dengan `company_id`, `branch_id`) — detail kolom di `DATABASE.md` §7d
- `wks_ap_kontrabons` — **header** (tanda terima tagihan): kontrabon_no, supplier_id, received_date,
  due_date, subtotal/tax/total, credit_amount, paid_amount, **outstanding (generated)**, status,
  verified_by/approved_by (SoD) — **unit hutang**.
- `wks_ap_kontrabon_invoices` — **baris tagihan** (1..n faktur supplier): supplier_invoice_no,
  tax_invoice_no, invoice_date, ref po_id/goods_receipt_id, nilai+PPN, **checklist**
  `chk_goods_received`/`chk_delivery_note`/`chk_tax_invoice`/`chk_po_match` + `check_status`.
- `wks_ap_bank_accounts` — **master rekening kas/bank** (dikelola Kasir): bank, no rek, tipe, supports_giro/digital.
- `wks_ap_payment_requests` — **Request Pembayaran** (maker→checker): supplier, bank_account, method, status, requested_by/approved_by (SoD).
- `wks_ap_payment_request_items` — alokasi request ↔ **kontrabon** (partial/banyak), `unique(payment_request_id, kontrabon_id)`.
- `wks_ap_payments` — **realisasi**: payment_no, ref payment_request, bank_account, method, digital_ref, amount, status.
- `wks_ap_giros` — **Register Giro** (1:1 payment method=giro): giro_no, atas nama, nilai, jatuh tempo, status (registered→printed→signed→verified→released→cleared·bounced), registered_by/signed_by/verified_by (SoD).

**Service inti:** **`ApService`** — pengakuan hutang (approve kontrabon), request pembayaran
(maker→checker) + realisasi (giro/digital) → settle `paid_amount`/status kontrabon, terapkan
kredit nota retur (semua dalam `DB::transaction()`, idempoten).

**Peran:** **Finance/AP** (Kontrabon: input/verifikasi/approve — approver ≠ verifikator),
**Kasir** (rekening bank, **request pembayaran maker→checker**, realisasi giro/digital).
Owner/Admin: lihat aging & rekap. **SoD:** pengaju request ≠ penyetuju (checker).

> **Dependensi:** butuh **Purchasing** (PO) + **GRN** (Gudang) + master **Supplier** untuk
> 3-way match penuh. Bila dibangun lebih dulu, `po_id`/`goods_receipt_id` di-nullable + supplier
> wajib; 3-way match menyusul saat PO/GRN aktif. Detail kolom `DATABASE.md` §7d, alur
> `WORKFLOWS.md` §4c, panel `PANELS.md` §7–§9.
