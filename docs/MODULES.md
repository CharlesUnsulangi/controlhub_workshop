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

**Disiapkan tapi non-aktif (future-ready):** penjualan part eceran, harga jual,
invoice & pembayaran ke customer. Tabelnya **tetap dibuat** dan diberi penanda agar
bisa diaktifkan via feature-flag tanpa migrasi besar. Lihat penanda *(future/dormant)*
di tiap modul dan §14.

---

## 1. Arsitektur Multi-Tenant

**Strategi: single database, shared schema, dipisah `company_id`.**

Hierarki: **Company (tenant) → Branch (cabang) → Warehouse (gudang)**.

Aturan wajib:
- **Setiap tabel milik tenant punya `company_id`** (FK `wks_core_companies`),
  `not null`, ber-index. Tabel Core/sistem **tidak**.
- **Transaksi operasional** juga membawa `branch_id` (FK `wks_mst_branches`) untuk
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
| — | **Master** (data referensi) | `wks_mst_` | Per company | Admin |
| 2 | **LKM** (Kendaraan Masuk) | `wks_lkm_` | Per company/branch | Master |
| 3 | **Purchasing Order** | `wks_po_` | Per company/branch | Master, Inventory |
| 4 | **Gudang Sparepart** (Inventory) | `wks_inv_` | Per company/branch | Master, Purchasing |
| 5 | **Gudang Ban** (Tyre) | `wks_tyre_` | Per company/branch | Master, LKM/Servis |
| 6 | **Servis / Work Order** | `wks_svc_` | Per company/branch | LKM, Inventory, Master |
| 7 | **Price List Supplier** (harga beli part & ban) | `wks_price_` | Per company | Master(supplier), Inventory, Tyre |

Urutan build: **Core → Admin → Master → Gudang Sparepart → Price List → Purchasing → LKM → Servis → Gudang Ban.**

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
company (pajak/PPN, penomoran dokumen, logo, jam operasional), pengelolaan **branch**.

**Tabel**
- `users` — **tabel bawaan Laravel**, ditambah kolom `company_id` (nullable; null = super admin Core)
- `wks_adm_roles`, `wks_adm_permissions`, `wks_adm_role_user`, `wks_adm_permission_role`
- `wks_adm_company_settings` — company_id, key, value (jsonb)
- `wks_adm_document_sequences` — company_id, doc_type, prefix, next_number

**Peran:** `Owner`, `Admin`.

---

## 5. Master Data (`wks_mst_`) — dikelola via modul Admin

Data referensi yang dipakai banyak modul. Level **company** kecuali disebut branch.

**Tabel**
- `wks_mst_branches` — company_id, name, code, address, phone  *(cabang)*
- `wks_mst_customers` — company_id, name, type (perorangan/armada), npwp, term, contacts (jsonb)
- `wks_mst_trucks` — unit armada: plat, VIN, tipe, KM/jam, **kepemilikan, BBM, default driver,
  STNK/KIR/pajak/asuransi + reminder, GPS** (detail di `DATABASE.md`)
- `wks_mst_truck_types` — company_id, name (tractor head, dump, box, tangki, …)
- `wks_mst_drivers` — sopir: kode, nama, kontak, **SIM (no/jenis/expiry)**, status;
  **terhubung ke ControlHub HRD sebagai "Mitra Kerja"** via `hrd_mitra_id` (lihat §15)
- `wks_mst_suppliers` — company_id, name, contacts, term, lead_time
- `wks_mst_warehouses` — company_id, **branch_id**, name, code, type (sparepart/ban)
- `wks_mst_locations` — company_id, warehouse_id, code (rak/bin)
- `wks_mst_uoms` — company_id, code, name (satuan)
- `wks_mst_categories` — company_id, type (part/ban), name
- `wks_mst_mechanics` — company_id, branch_id, name, skills (jsonb), status

---

## 6. Modul 2 — LKM (`wks_lkm_`) — Laporan Kendaraan Masuk

**Tujuan:** mencatat tiap truk masuk (gate-in) sebagai dasar servis; "pintu depan" alur kerja.

**Fitur:** buat LKM (customer + unit, jam masuk, sopir, **KM/jam operasi terkini**,
keluhan), checklist inspeksi awal + foto, status (*Masuk → Diproses → Selesai → Keluar*),
gate-out + surat jalan, jadi sumber Work Order, laporan turnaround.

**Tabel** (dengan `company_id`, `branch_id`)
- `wks_lkm_entries` — lkm_no, customer_id, truck_id, entry_at, driver_name, km_in, complaints, status, created_by
- `wks_lkm_inspections` — lkm_entry_id, item, condition, note, photo_path
- `wks_lkm_gateouts` — lkm_entry_id, exit_at, km_out, released_by, surat_jalan_no

**Peran:** `ServiceAdvisor`, `Gate/Satpam`, `Admin`.

---

## 7. Modul 3 — PURCHASING ORDER (`wks_po_`) — Sparepart

**Tujuan:** pengadaan sparepart dari supplier: permintaan → PO → penerimaan ke gudang.

**Fitur:** (opsional) Purchase Request, PO ke supplier + approval, **Surat Jalan MASUK dari
supplier** (per PO; bisa didaftarkan supplier via **portal supplier**), **Serah Terima barang
(GRN) yang WAJIB merujuk PO** (boleh merujuk SJ masuk) → tally → posting → tambah stok via
`StockService` (movement *in*), penerimaan partial, riwayat & status PO.

**Tabel** (dengan `company_id`, `branch_id`)
- `wks_po_requests`, `wks_po_request_items`  *(opsional)*
- `wks_po_orders` — po_no, supplier_id, order_date, status, total
- `wks_po_order_items` — po_order_id, item, qty, unit_price, tax, qty_received
- `wks_po_supplier_deliveries`, `wks_po_supplier_delivery_items` — **Surat Jalan masuk** per PO (supplier_do_no, qty_shipped, source portal/manual)
- `wks_po_goods_receipts` — grn_no, **po_id (WAJIB)**, supplier_id, warehouse_id, status, **supplier_delivery_id** (opsional), do_supplier_no (fallback)
- `wks_po_goods_receipt_items` — grn_id, po_item_id, item, condition, qty_doc, qty_received, unit_cost, location_id

**Integrasi:** Serah Terima = satu-satunya jalur "stok masuk pembelian", **selalu ber-PO**,
melewati **tally** (lihat §8b) → `StockService`; tidak mengubah stok langsung.

**Portal Supplier (panel `/vendor`, fase berikutnya):** supplier login (akun di `users` +
`supplier_id`, peran `Supplier`), lihat PO yang ditujukan padanya, **daftarkan Surat Jalan**.
Di-scope ketat per `supplier_id` (+ company). Kini entri SJ oleh staf (`source=manual`);
portal diaktifkan via feature-flag. ⚠️ Surat Jalan MASUK (ini) ≠ Surat Jalan KELUAR (`wks_inv_delivery_orders`, §8b).

**Peran:** `Purchasing`, `Gudang`, approval `Owner`/`Admin`; **`Supplier`** (portal, read PO + tulis SJ sendiri).

---

## 8. Modul 4 — GUDANG SPAREPART (`wks_inv_`) — Inventory

**Tujuan:** kelola stok sparepart: ketersediaan, lokasi rak, kondisi baru/bekas,
pergerakan, valuasi, opname, serta dokumen serah terima & surat jalan.

**Fitur:**
- Master sparepart (**SKU = kode internal kanonik**; nomor pabrikan **Hino/Isuzu/dst.**
  & brand aftermarket sebagai **cross-reference** di `wks_inv_part_numbers` — banyak per SKU;
  cari part dari nomor mana pun → ketemu SKU; supersession via `superseded_by_id`),
  kategori, satuan, fitment. Standar Hino = nomor OEM utama untuk part Hino.
- **Gudang ber-rak terstruktur** — lokasi `zona/rak/bay/level/bin` + barcode (`wks_mst_locations`).
- **Stok baru vs bekas** — dimensi `condition` (new/used/rebuilt); saldo & WAC terpisah;
  gudang bisa dikhususkan `condition_scope=new/used` (gudang part baru vs bekas).
  Part bekas masuk dari **teardown/copotan** (movement `ref=teardown`, bukan PO).
- **Core return (old-for-new)** — pasang part baru **non-consumable** di WO → **wajib** kembalikan
  part bekas RUSAK sebagai **bukti** (`wks_inv_core_returns`), ditahan lalu **dijual scrap**.
  Beda dari teardown: core rusak **tidak** masuk stok layak-pakai. Telusur asal truck→LKM→WO.
- **Pergerakan stok** = SATU-SATUNYA cara stok berubah (in/out/transfer/adjustment), reservasi WO.
- **Pengeluaran Sparepart (Bon/Part Issue)** — alur ber-approval tersambung WO→LKM→truck:
  **diusulkan Mekanik** → **di-review Service Officer (ServiceAdvisor)** (approve/reject, bisa
  potong qty) → **dikeluarkan Gudang** (movement out, HPP ke `wo_item`). SoD: pengusul ≠ reviewer.
- **Konversi UOM** — beli per *box*, simpan per *pcs*; satuan alternatif + factor per SKU
  (`wks_inv_part_uoms`); stok & WAC selalu di **UOM dasar**, dokumen snapshot `uom_factor`.
- **Stok negatif: diizinkan + alert** — `out` melebihi saldo tetap diproses, lalu buat
  `wks_inv_stock_alerts` + notifikasi ke Gudang/Admin (juga di bawah min/reorder).
- **Stock opname** → adjustment; kartu stok & **valuasi WAC**; peringatan stok kritis & slow-moving.
- **Serah Terima dari supplier** — lihat modul Purchasing (§7), **wajib referensi PO** + tally.
- **Surat Jalan (barang keluar) + Tally Sheet** — §8b.
- *(Penjualan part eceran ke customer = future/dormant.)*

**Tabel** (dengan `company_id`)
- `wks_inv_spare_parts` — **sku** (internal), name, primary_make, superseded_by_id, …
- `wks_inv_part_numbers` — spare_part_id, ref_type(oem/aftermarket), brand, part_no, is_primary
- `wks_inv_part_uoms` — spare_part_id, uom_id, **factor** (konversi ke UOM dasar), is_purchase_default, barcode
- `wks_inv_stock_alerts` — alert stok negatif/di bawah ambang + status + notifikasi
- `wks_inv_core_returns` — register part bekas rusak (bukti old-for-new): wo_item (1:1), truck/lkm telusur, disposition held/scrapped
- `wks_inv_scrap_disposals` — lot penjualan/pembuangan scrap *(ringan, future)*
- `wks_inv_stock_items` — saldo **fisik** per rak: spare_part_id, warehouse_id, location_id, **condition**, qty_on_hand, qty_reserved
- `wks_inv_stock_values` — saldo **valuasi/WAC** per gudang: spare_part_id, warehouse_id, condition, qty_on_hand, **avg_cost**, total_value, reorder override
- `wks_inv_stock_movements` — ledger append-only: **condition**, type, **qty_in/qty_out** (net_qty generated), ref_type/ref_id, unit_cost
- `wks_inv_part_issues`, `wks_inv_part_issue_items` — Bon Pengeluaran Sparepart (usul mekanik→review SO→keluar gudang); ref WO/LKM/truck; qty_requested/approved/issued
- `wks_inv_stock_loc_snapshots`, `wks_inv_stock_val_snapshots` — snapshot saldo harian (anchor bulanan, dipangkas) untuk stok historis & kartu stok
- `wks_inv_stock_opnames`, `wks_inv_stock_opname_items` (per **condition**)

**Service inti:** `StockService` — semua mutasi stok (per kondisi) dalam `DB::transaction()`.
Dipakai juga oleh Purchasing (serah terima), Surat Jalan, dan Servis (pemakaian part).

---

## 8b. Dokumen Gudang — Serah Terima, Surat Jalan & Tally Sheet (`wks_inv_` / `wks_po_`)

**Serah Terima dari Supplier (GRN)** — di modul Purchasing (`wks_po_goods_receipts`):
- **Wajib referensi PO** (`po_id` not null) — tidak ada penerimaan tanpa PO.
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

**Peran:** `Gudang` (terima/keluar/tally), `Purchasing` (GRN), `Admin`.

**Peran:** `Gudang`, `Admin`.

---

## 9. Modul 5 — GUDANG BAN (`wks_tyre_`) — Tyre

**Tujuan:** kelola ban sebagai **aset ber-nomor seri dengan siklus hidup & posisi pada unit**.
Setiap ban fisik **unik & wajib punya serial number** (`serial_no`).

**Dua level data ban:**
- **Tyre Product** (`wks_tyre_products`) — *model/spesifikasi* ban: merek + ukuran + pola.
  Jadi acuan **harga beli** (Price List) & katalog.
- **Tyre (unit)** (`wks_tyre_tyres`) — *fisik per serial*, menunjuk ke satu product;
  punya siklus hidup, posisi, tread depth sendiri.

**Fitur:** registrasi unit ban (serial unik, DOT, tread depth, kondisi), status
(*Stok → Terpasang → Dilepas → Vulkanisir → Scrap*), stok & pergerakan,
**instalasi/rotasi** (ban di posisi truk FL/FR/RL1/…, KM pasang & lepas),
inspeksi (tread depth & tekanan berkala), vulkanisir (kirim/terima + biaya), laporan biaya per KM.

**Tabel** (dengan `company_id`)
- `wks_tyre_products` — brand, size, pattern, type, category_id  *(model — acuan harga)*
- `wks_tyre_tyres` — **serial_no (unik per company)**, tyre_product_id, dot, tread_depth, condition, status, warehouse_id
- `wks_tyre_movements` — tyre_id, warehouse_id, type, ref, at
- `wks_tyre_installations` — tyre_id, truck_id, position, installed_at, km_install, removed_at, km_remove
- `wks_tyre_inspections` — tyre_id, inspected_at, tread_depth, pressure, note
- `wks_tyre_retreads` — tyre_id, supplier_id, sent_at, received_at, cost, retread_count

**Desain:** berbagi pola pergerakan dengan `StockService` generik; `TyreService` khusus
instalasi & lifecycle.

**Peran:** `Gudang`/`GudangBan`, `Mekanik` (pasang/lepas), `Admin`.

---

## 10. Modul 6 — SERVIS / WORK ORDER (`wks_svc_`)

**Tujuan:** eksekusi perawatan/perbaikan armada dari LKM sampai selesai; mencatat **biaya
(cost)** per unit. *(Mode internal — lihat §0 Mode Operasi.)*

**Fitur (aktif):** Work Order dari LKM, estimasi/rencana kerja (jasa + part + ban),
penugasan mekanik, **request part ke gudang** (reservasi/pemakaian via `StockService`),
pasang ban (via `TyreService`), catat jam kerja, status (*Antri → Menunggu Part →
Dikerjakan → QC → Selesai → Diserahkan*), **servis berkala/PM** berbasis KM/jam/waktu +
reminder, **rekap biaya per WO & per unit** (cost: part HPP + ban + jasa).
**Core return wajib:** tiap pemasangan part baru **non-consumable** harus disertai
pengembalian part bekas rusak (`wks_inv_core_returns`, §8) sebagai bukti — WO **tak bisa
`done`** bila core belum kembali (qty cocok). Consumable (oli/filter/grease) dikecualikan.

**Fitur (future/dormant):** harga jual, **Invoice & pembayaran** ke customer
(termasuk konsolidasi armada). Tabel disiapkan, UI non-aktif via feature-flag.

**Tabel** (dengan `company_id`, `branch_id`)
- `wks_svc_work_orders` — wo_no, lkm_entry_id, truck_id, mechanic_id, status, total_cost
- `wks_svc_work_order_items` — work_order_id, item_type (service/part/tyre), ref_id, qty,
  **unit_cost** (HPP, selalu diisi), **unit_price** (jual, nullable — *future*)
- `wks_svc_services` — name, std_cost, est_hours  *(katalog jasa; std_price jual — future)*
- `wks_svc_pm_schedules` — truck_id, type, interval_km/hours/days, next_due_km, next_due_date
- `wks_svc_invoices` *(future/dormant)* — invoice_no, customer_id, work_order_id (nullable), total, status
- `wks_svc_payments` *(future/dormant)* — invoice_id, paid_at, amount, method

**Peran:** `ServiceAdvisor`, `KepalaMekanik`, `Mekanik`, `Admin`. (`Kasir` — future.)

---

## 10b. Modul 7 — PRICE LIST SUPPLIER (`wks_price_`) — Harga Beli Sparepart & Ban

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
- `wks_price_lists` — supplier_id (FK `wks_mst_suppliers`, **wajib**), name, currency,
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
| branches | Master | `wks_mst_` | ✅ | (itu sendiri) |
| customers, trucks, suppliers, uom, category, truck_types | Master | `wks_mst_` | ✅ | ❌ (company-level) |
| warehouses, locations, mechanics | Master | `wks_mst_` | ✅ | ✅ |
| lkm | LKM | `wks_lkm_` | ✅ | ✅ |
| PO, GRN | Purchasing | `wks_po_` | ✅ | ✅ |
| spare parts, stock | Inventory | `wks_inv_` | ✅ | (via warehouse) |
| tyres, installations | Tyre | `wks_tyre_` | ✅ | (via warehouse) |
| work orders, invoices, PM | Servis | `wks_svc_` | ✅ | ✅ |
| tyre products (model) | Tyre | `wks_tyre_` | ✅ | ❌ |
| supplier price lists, items, history | Price List | `wks_price_` | ✅ | ❌ (company-level) |

---

## 12. Konvensi Penamaan (sub-prefix)

- Format tabel: **`wks_<modul>_<entitas_jamak>`** — `snake_case`, jamak, bahasa Inggris.
  Contoh: `wks_inv_stock_movements`, `wks_lkm_entries`, `wks_svc_work_orders`.
- Sub-prefix modul: `core`, `adm`, `mst`, `lkm`, `po`, `inv`, `tyre`, `svc`, `price`.
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
5. **Purchasing** — PO (ambil harga supplier) → GRN → stok masuk.
6. **LKM** — kendaraan masuk.
7. **Servis / Work Order** — WO + estimasi + invoice + PM (harga jual via markup, lihat §14).
8. **Gudang Ban** — `wks_tyre_products` + unit serialized + instalasi.

---

## 14. Masih Perlu Dikonfirmasi

Sudah terjawab: ✅ Price List = **harga beli supplier** · ✅ **multi-supplier** per item ·
✅ status **PPN** eksplisit (exclusive/inclusive/non_pkp + rate) · ✅ ban **unik per serial** +
master **tyre product** sebagai acuan harga · ✅ **mode INTERNAL** (cost-focused; jual & invoice
ke customer disiapkan tapi non-aktif — lihat §0).

Masih perlu diputuskan:
1. **Plan/langganan & feature-flag modul**: pakai sekarang atau siapkan struktur & enforce nanti? *(default: siapkan, enforce nanti)*
2. **Mechanics** level branch (default) atau lintas branch dalam satu company?
3. **Penilaian biaya part di WO**: metode valuasi **FIFO** atau **Average (WAC)**? *(rekomendasi: Average/WAC, lebih sederhana)*
4. **Pemilik unit internal**: cukup di level **branch/divisi**, atau tetap pakai entitas
   `wks_mst_customers` sebagai cost-center pemilik armada? *(default: customer = cost-center internal)*
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
- `wks_mst_drivers.hrd_mitra_id` = referensi ke **Mitra Kerja** di HRD.
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
