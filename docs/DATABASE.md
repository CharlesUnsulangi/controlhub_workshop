# ControlHub Workshop — Data Dictionary (Skema Tabel)

> Cetak biru skema (v0.1) untuk **PostgreSQL** + Laravel. Selaras dengan `MODULES.md`,
> `SITEMAP.md`, dan `.claude/skills/workshop-feature/NAMING_CONVENTIONS.md`.
> Default yang dipakai (belum final, lihat `MODULES.md` §14): valuasi stok **Average/WAC**,
> `wks_mst_customers` = cost-center pemilik unit, mata uang default **IDR**.

## Legenda & Konvensi

- **PK** `id` = `bigint` (`bigIncrements`/`bigserial`). **FK** = `<singular>_id` (`bigint`).
- Uang/biaya = `decimal(15,2)`. Qty = `decimal(15,3)`. Persen = `decimal(5,2)`.
- Waktu = `timestamptz`; tanggal = `date`. `timestamps` = `created_at`,`updated_at`.
- `soft delete` = kolom `deleted_at` (`timestamptz` null) — dipakai di tabel **master**.
- Enum diimplementasikan sebagai `varchar` + **PHP Enum cast** (+ `check` opsional).
- **Tenant:** kolom `company_id` (FK `wks_core_companies`) `NOT NULL`, ber-index, di semua
  tabel tenant. Tabel **Core** tanpa `company_id`. Transaksi operasional + `branch_id`.
- Kolom `created_by`/`updated_by`/`*_by` = FK ke `users` (nullable).
- *(future/dormant)* = tabel/kolom disiapkan tapi belum dipakai (mode internal).

Daftar enum terpusat ada di **§12**.

---

## 1. CORE (`wks_core_`) — tanpa `company_id`

### wks_core_companies
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| name | varchar(150) | no | Nama perusahaan/tenant |
| code | varchar(30) | no | **unique**, kode tenant |
| npwp | varchar(30) | yes | |
| address | text | yes | |
| phone | varchar(30) | yes | |
| email | varchar(150) | yes | |
| timezone | varchar(40) | no | default `Asia/Jakarta` |
| logo_path | varchar(255) | yes | |
| status | varchar(20) | no | enum company_status; default `active` |
| settings | jsonb | yes | pengaturan global tenant |
| timestamps, deleted_at | | | soft delete |

### wks_core_plans *(opsional)*
| id · name · price `decimal(15,2)` · limits `jsonb` · is_active `bool` · timestamps |

### wks_core_subscriptions *(opsional)*
| id · company_id (FK companies) · plan_id (FK plans) · start_date · end_date · status · timestamps |

### wks_core_modules
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| code | varchar(30) | no | **unique** (`lkm`,`inv`,`tyre`,`po`,`svc`,`price`,…) |
| name | varchar(80) | no | |
| description | varchar(255) | yes | |
| is_active | bool | no | default true |

### wks_core_company_modules
| id · company_id (FK) · module_id (FK) · is_enabled `bool` · settings `jsonb` · timestamps · **unique(company_id, module_id)** |

### wks_core_audit_logs
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | yes | FK companies (null = aksi sistem) |
| user_id | bigint | yes | FK users |
| action | varchar(50) | no | created/updated/deleted/login/… |
| auditable_type | varchar(100) | yes | model terkait |
| auditable_id | bigint | yes | |
| old_values | jsonb | yes | |
| new_values | jsonb | yes | |
| ip_address | varchar(45) | yes | |
| created_at | timestamptz | no | |

---

## 2. USERS (tabel bawaan Laravel + kolom tenant)

### users
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | yes | FK companies. **null = super admin Core** |
| branch_id | bigint | yes | FK branches (cabang default user) |
| supplier_id | bigint | yes | FK suppliers — **akun portal supplier** (panel `/vendor`); null = user internal |
| name | varchar(150) | no | |
| email | varchar(150) | no | **unique** |
| password | varchar(255) | no | |
| is_super_admin | bool | no | default false |
| status | varchar(20) | no | enum user_status; default `active` |
| email_verified_at, remember_token, timestamps, deleted_at | | | |

---

## 3. ADMIN (`wks_adm_`)

### wks_adm_roles
| id · company_id (FK) · name `varchar(80)` · slug `varchar(80)` · description · is_system `bool` · timestamps · **unique(company_id, slug)** |

### wks_adm_permissions  *(katalog global — tanpa company_id)*
| id · code `varchar(80)` **unique** (`truck.create`,`stock.adjust`,…) · group `varchar(50)` · description |

### wks_adm_role_user *(pivot)*
| role_id (FK) · user_id (FK) · **PK(role_id, user_id)** |

### wks_adm_permission_role *(pivot)*
| permission_id (FK) · role_id (FK) · **PK(permission_id, role_id)** |

### wks_adm_company_settings
| id · company_id (FK) · group `varchar(50)` · key `varchar(80)` · value `jsonb` · timestamps · **unique(company_id, group, key)** |

### wks_adm_document_sequences
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | yes | FK (null = berlaku semua cabang) |
| doc_type | varchar(30) | no | `lkm`,`wo`,`po`,`grn`,`invoice`,… |
| prefix | varchar(20) | yes | mis. `WO` |
| next_number | int | no | default 1 |
| padding | smallint | no | default 4 |
| period | varchar(10) | no | enum seq_period (none/yearly/monthly) |
| timestamps | | | **unique(company_id, branch_id, doc_type)** |

---

## 4. MASTER (`wks_mst_`)

### wks_mst_branches
| id · company_id (FK) · code `varchar(20)` · name · address · phone · is_active · timestamps · deleted_at · **unique(company_id, code)** |

### wks_mst_customers  *(cost-center / pemilik unit internal; eksternal = future)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| code | varchar(30) | no | unique(company_id, code) |
| name | varchar(150) | no | |
| type | varchar(20) | no | enum customer_type (internal/fleet/individual) |
| npwp | varchar(30) | yes | |
| address | text | yes | |
| phone | varchar(30) | yes | |
| email | varchar(150) | yes | |
| payment_term_days | smallint | yes | *(future, billing)* |
| contacts | jsonb | yes | PIC armada |
| is_active | bool | no | default true |
| timestamps, deleted_at | | | |

### wks_mst_truck_types
| id · company_id (FK) · name `varchar(60)` · axle_config `varchar(20)` · description · timestamps · **unique(company_id, name)** |

### wks_mst_trucks
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| customer_id | bigint | yes | FK customers (pemilik/cost-center) |
| branch_id | bigint | yes | FK branches (home base) |
| truck_type_id | bigint | yes | FK truck_types |
| plate_no | varchar(20) | no | **unique(company_id, plate_no)** |
| brand | varchar(60) | yes | |
| model | varchar(60) | yes | |
| year | smallint | yes | |
| vin | varchar(40) | yes | no. rangka |
| engine_no | varchar(40) | yes | |
| current_km | bigint | no | default 0 |
| current_hours | int | yes | jam operasi |
| ownership_status | varchar(15) | no | enum ownership_status (owned/leased/rented); default `owned` |
| fuel_type | varchar(15) | yes | enum fuel_type (solar/biosolar/dexlite/cng) |
| default_driver_id | bigint | yes | FK drivers (sopir utama) |
| gps_device_id | varchar(50) | yes | unit pelacak (opsional) |
| stnk_no | varchar(30) | yes | nomor STNK |
| stnk_expiry | date | yes | jatuh tempo pajak/STNK → reminder |
| kir_expiry | date | yes | jatuh tempo uji KIR → reminder |
| insurance_no | varchar(50) | yes | polis asuransi |
| insurance_expiry | date | yes | → reminder |
| photo_path | varchar(255) | yes | |
| status | varchar(20) | no | enum truck_status; default `active` |
| timestamps, deleted_at | | | |

> **Reminder dokumen unit:** `stnk_expiry`, `kir_expiry`, `insurance_expiry` dipantau
> seperti PM (muncul peringatan saat mendekati jatuh tempo).

### wks_mst_drivers  *(dapat terhubung ke ControlHub HRD)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | yes | FK branches |
| code | varchar(20) | no | **unique(company_id, code)** |
| name | varchar(150) | no | |
| phone | varchar(30) | yes | |
| sim_no | varchar(30) | yes | nomor SIM |
| sim_type | varchar(10) | yes | enum sim_type (B1/B2/B1U/B2U) |
| sim_expiry | date | yes | → reminder |
| status | varchar(20) | no | enum driver_status (active/inactive/suspended) |
| source | varchar(10) | no | enum driver_source (manual/hrd); default `manual` |
| hrd_mitra_id | bigint | yes | **ID Mitra Kerja di ControlHub HRD** (driver = mitra kerja) |
| hrd_company_id | bigint | yes | ID company/tenant di HRD (pemetaan tenant) |
| hrd_synced_at | timestamptz | yes | waktu sinkron terakhir dari HRD |
| timestamps, deleted_at | | | **unique(company_id, hrd_mitra_id)** bila source=hrd |

> **Integrasi HRD:** di ControlHub HRD, driver tercatat sebagai **"Mitra Kerja"**
> (pekerja mitra/eksternal). Bila `source=hrd`, data inti (nama, kontak, SIM) bersumber
> dari HRD via `hrd_mitra_id` (read-only di Workshop, diperbarui saat sinkron).
> `hrd_company_id` memetakan tenant Workshop ↔ tenant HRD. Bila `source=manual`,
> dikelola lokal. Lihat `MODULES.md` §15 (Integrasi Eksternal).

### wks_mst_suppliers
| id · company_id (FK) · code `varchar(30)` · name · npwp · address · phone · email · payment_term_days `smallint` · lead_time_days `smallint` · is_pkp `bool` · contacts `jsonb` · is_active · timestamps · deleted_at · **unique(company_id, code)** |

### wks_mst_warehouses
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK branches |
| code | varchar(20) | no | **unique(company_id, code)** |
| name | varchar(80) | no | |
| type | varchar(15) | no | enum warehouse_type (sparepart/tyre/both) |
| condition_scope | varchar(10) | no | enum condition_scope (any/new/used); default `any` — gudang khusus part **baru**/**bekas** |
| is_active | bool | no | default true |
| timestamps, deleted_at | | | |

### wks_mst_locations  *(rak/bin terstruktur)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| warehouse_id | bigint | no | FK |
| code | varchar(30) | no | kode lokasi mis. `A-01-03` — **unique(warehouse_id, code)** |
| zone | varchar(20) | yes | zona/area |
| rack | varchar(20) | yes | rak |
| bay | varchar(20) | yes | kolom/baris |
| level | varchar(20) | yes | tingkat |
| bin | varchar(20) | yes | kotak/slot |
| location_type | varchar(15) | no | enum location_type (rack/floor/staging); default `rack` |
| barcode | varchar(50) | yes | label scan |
| is_active | bool | no | default true |
| timestamps | | | |

### wks_mst_uoms
| id · company_id (FK) · code `varchar(15)` · name `varchar(40)` · timestamps · **unique(company_id, code)** |

### wks_mst_categories
| id · company_id (FK) · type `varchar(10)` (enum category_type: part/tyre) · name `varchar(80)` · parent_id (FK self, null) · **is_consumable `bool` default false** (habis-pakai: oli/filter/grease → tak wajib core return) · timestamps · **unique(company_id, type, name)** |

### wks_mst_mechanics
| id · company_id (FK) · branch_id (FK) · user_id (FK users, null) · code `varchar(20)` · name · skills `jsonb` · hourly_rate `decimal(15,2)` null · status `varchar(20)` (enum mechanic_status) · timestamps · deleted_at · **unique(company_id, code)** |

---

## 5. GUDANG SPAREPART / INVENTORY (`wks_inv_`)

### wks_inv_spare_parts
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| sku | varchar(30) | no | **SKU internal kanonik** (mis. `SP-000123`). **unique(company_id, sku)** |
| name | varchar(150) | no | |
| primary_make | varchar(30) | yes | merek kendaraan utama (Hino/Isuzu/universal) — untuk filter |
| superseded_by_id | bigint | yes | FK self — SKU pengganti (penggantian part) |
| category_id | bigint | yes | FK categories (type=part) |
| uom_id | bigint | yes | FK uoms — **UOM dasar/stok (kanonik)**; stok & WAC SELALU di satuan ini (mis. pcs) |
| min_qty | decimal(15,3) | no | default 0 |
| max_qty | decimal(15,3) | yes | |
| reorder_point | decimal(15,3) | no | default 0 |
| last_cost | decimal(15,2) | yes | harga beli terakhir (cache tampilan; bukan dasar valuasi) |
| sell_price | decimal(15,2) | yes | *(future/dormant)* |
| attributes | jsonb | yes | fitment (cocok tipe/model truk), dll. |
| is_active | bool | no | default true |
| timestamps, deleted_at | | | |

> **SKU = kode internal kanonik**, bukan serial & bukan dikunci ke satu pabrikan.
> Nomor part Hino/Isuzu dan brand aftermarket disimpan sebagai cross-reference di
> `wks_inv_part_numbers` (banyak per SKU). Cari part bisa dari nomor pabrikan mana pun
> → ketemu SKU. **Standar Hino** tetap dipakai sebagai nomor OEM utama untuk part Hino.
> **Supersession Hino** (nomor berganti) cukup tambah baris part-number baru di SKU yang
> sama (tandai primary); `superseded_by_id` dipakai bila part benar-benar diganti SKU lain.
> **Valuasi (WAC) tidak disimpan di sini** — sumber kebenaran WAC ada di
> `wks_inv_stock_values` (grain warehouse+condition). `reorder_point`/`min_qty`/`max_qty` di
> tabel ini = default tingkat-SKU; override per gudang lihat `wks_inv_stock_values`.

### wks_inv_part_numbers  *(cross-reference nomor pabrikan & brand per SKU)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| spare_part_id | bigint | no | FK spare_parts (SKU) |
| ref_type | varchar(12) | no | enum part_ref_type (oem/aftermarket) |
| brand | varchar(60) | no | OEM: merek kendaraan (Hino/Isuzu); aftermarket: brand (Sakura/Denso) |
| part_no | varchar(40) | no | nomor part menurut brand tsb. |
| is_primary | bool | no | default false — nomor utama untuk SKU ini |
| note | varchar(255) | yes | |
| timestamps | | | **unique(company_id, brand, part_no)** · index(part_no) |

> Contoh satu SKU: `(oem, Hino, 23300-78010, primary)`, `(oem, Isuzu, 8-97xxxxxx)`,
> `(aftermarket, Sakura, FC-1501)`. Pencarian by `part_no` → resolve ke `spare_part_id`.

### wks_inv_part_uoms  *(satuan alternatif per SKU — konversi box↔pcs)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| spare_part_id | bigint | no | FK |
| uom_id | bigint | no | FK uoms — satuan alternatif (mis. box, dus) |
| factor | decimal(15,6) | no | **1 satuan ini = `factor` UOM dasar** (mis. 1 box = 12 pcs) |
| is_purchase_default | bool | no | default false — satuan default saat buat PO |
| barcode | varchar(40) | yes | barcode kemasan (scan box) |
| note | varchar(255) | yes | |
| timestamps | | | **unique(company_id, spare_part_id, uom_id)** |

> UOM dasar (`spare_parts.uom_id`, mis. pcs) **tidak** masuk tabel ini (factor-nya implisit 1).
> Dokumen beli/terima/keluar mencatat satuan + **`uom_factor` ter-snapshot** di barisnya;
> `StockService` mengonversi ke UOM dasar sebelum posting (qty_base = qty × factor,
> unit_cost_base = unit_price ÷ factor). Stok & WAC selalu UOM dasar.

### wks_inv_stock_items  *(saldo FISIK live, per rak/bin — agregat dari movements)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| spare_part_id | bigint | no | FK |
| warehouse_id | bigint | no | FK |
| location_id | bigint | yes | FK locations (rak/bin) |
| condition | varchar(10) | no | enum part_condition (new/used/rebuilt); default `new` |
| qty_on_hand | decimal(15,3) | no | default 0 |
| qty_reserved | decimal(15,3) | no | default 0 — TANPA CHECK keras (stok negatif diizinkan); over-reserve → alert |
| timestamps | | | **unique NULLS NOT DISTINCT (spare_part_id, warehouse_id, location_id, condition)** |

> **Saldo fisik saja, tanpa biaya.** Jawab "barang ada di rak mana, berapa" secara O(1).
> ⚠️ `location_id` nullable → **wajib** `NULLS NOT DISTINCT` (PG15+, kita PG17) atau lokasi
> sentinel; tanpa itu PG menganggap tiap NULL distinct → baris saldo duplikat.
> **Baru vs bekas:** stok dipisah per `condition` (part copotan/teardown punya saldo sendiri,
> bisa ditempatkan di gudang `condition_scope=used`). Valuasi/WAC → `wks_inv_stock_values`.

### wks_inv_stock_values  *(saldo VALUASI live + WAC — sumber kebenaran nilai)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| spare_part_id | bigint | no | FK |
| warehouse_id | bigint | no | FK |
| condition | varchar(10) | no | enum part_condition (new/used/rebuilt) |
| qty_on_hand | decimal(15,3) | no | default 0 — total qty grain ini (= Σ stock_items se-gudang+kondisi) |
| avg_cost | decimal(15,2) | no | default 0 — **WAC, satu sumber kebenaran** |
| total_value | decimal(15,2) | no | default 0 — = qty_on_hand × avg_cost |
| reorder_point | decimal(15,3) | yes | override per gudang (null → pakai default SKU) |
| min_qty | decimal(15,3) | yes | override per gudang |
| max_qty | decimal(15,3) | yes | override per gudang |
| timestamps | | | **unique(company_id, spare_part_id, warehouse_id, condition)** |

> **Grain WAC = warehouse + condition** (bukan per-bin). Memindah barang antar-rak di gudang
> yang sama tidak mengubah WAC. `StockService` meng-update `stock_items` (fisik per lokasi)
> **dan** `stock_values` (WAC per gudang) dalam satu `DB::transaction()` + `SELECT … FOR UPDATE`
> pada baris `stock_values` (cegah race WAC — lihat R7). Reorder point realistis berbeda
> per gudang → diletakkan di sini, bukan hanya di `spare_parts`.

### wks_inv_stock_movements  *(SATU-SATUNYA sumber perubahan stok)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| spare_part_id | bigint | no | FK (SKU) |
| part_number_id | bigint | yes | FK part_numbers — brand/nomor yang ditransaksikan (telusur) |
| warehouse_id | bigint | no | FK |
| location_id | bigint | yes | FK |
| condition | varchar(10) | no | enum part_condition (new/used/rebuilt) |
| type | varchar(20) | no | enum movement_type |
| qty_in | decimal(15,3) | no | default 0 — qty masuk |
| qty_out | decimal(15,3) | no | default 0 — qty keluar |
| net_qty | decimal(15,3) | — | **GENERATED ALWAYS AS (qty_in - qty_out) STORED** |
| unit_cost | decimal(15,2) | no | biaya per unit saat mutasi |
| ref_type | varchar(50) | yes | sumber (GRN, DO, part_issue, opname, teardown) — polimorfik |
| ref_id | bigint | yes | |
| note | varchar(255) | yes | |
| moved_at | timestamptz | no | |
| created_by | bigint | yes | FK users |
| created_at | timestamptz | no | index(company_id, spare_part_id, warehouse_id, moved_at) |

> **append-only** (tidak pernah di-UPDATE). **CHECK** tepat satu arah:
> `(qty_in > 0 AND qty_out = 0) OR (qty_out > 0 AND qty_in = 0)`. Kolom `qty_in`/`qty_out`
> terpisah memudahkan report (`SUM(qty_in)`/`SUM(qty_out)`) & layout **kartu stok**
> (Tanggal·Masuk·Keluar·Saldo); saldo berjalan **tidak** disimpan per-baris (rawan saat
> backdate/insert paralel) → dihitung saat query via window function dalam satu periode,
> berpangkal pada snapshot harian (lihat §7c).
> Part **bekas masuk** lewat `type=in`, `ref_type=teardown`/`wo_return` (bukan GRN/PO).

### wks_inv_part_issues  *(Bon Pengeluaran Sparepart — usul mekanik → review SO → keluar gudang)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK |
| issue_no | varchar(30) | no | **unique(company_id, issue_no)** |
| wo_id | bigint | no | FK work_orders — kontainer (bawa LKM & truck) |
| lkm_id | bigint | yes | FK lkm_entries — **telusur** (denormalized dari WO) |
| truck_id | bigint | yes | FK trucks — **telusur** (denormalized dari WO) |
| warehouse_id | bigint | no | FK — gudang sumber |
| status | varchar(15) | no | enum part_issue_status; default `draft` |
| requested_by | bigint | no | FK users — **mekanik pengusul** |
| requested_at | timestamptz | no | |
| reviewed_by | bigint | yes | FK users — **service officer (ServiceAdvisor) reviewer** |
| reviewed_at | timestamptz | yes | |
| review_note | varchar(255) | yes | alasan approve/reject |
| issued_by | bigint | yes | FK users — petugas Gudang yang mengeluarkan |
| issued_at | timestamptz | yes | |
| note | varchar(255) | yes | |
| timestamps | | | index(company_id, wo_id) · index(company_id, status) |

> **Alur:** mekanik buat (`draft`) → `submitted` → Service Officer **review**:
> `approved` (isi `qty_approved`, reserve stok) / `rejected` (+`review_note`) → Gudang keluarkan:
> `issued`/`partially_issued` (movement out, ref=`part_issue`). **SoD:** `requested_by ≠ reviewed_by`
> (mekanik tak bisa setujui sendiri). Telusur **truck & LKM** otomatis dari `wo_id` (disalin ke
> `lkm_id`/`truck_id` saat dibuat). Beda dari **Surat Jalan** (`delivery_orders`): part issue =
> pengeluaran **internal ke WO** (konsumsi), bukan barang keluar fisik ke luar gudang.

### wks_inv_part_issue_items
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| issue_id | bigint | no | FK |
| spare_part_id | bigint | no | FK (SKU) |
| wo_item_id | bigint | yes | FK work_order_items — baris rencana WO terkait |
| condition | varchar(10) | no | part_condition; default `new` |
| uom_id | bigint | yes | FK uoms (null = UOM dasar) |
| uom_factor | decimal(15,6) | no | default 1 — snapshot konversi |
| qty_requested | decimal(15,3) | no | **diusulkan mekanik** |
| qty_approved | decimal(15,3) | no | default 0 — **disetujui Service Officer** (≤ requested) |
| qty_issued | decimal(15,3) | no | default 0 — **nyata dikeluarkan Gudang** |
| location_id | bigint | yes | FK locations — rak asal |
| unit_cost | decimal(15,2) | yes | HPP ter-snapshot saat issue (dari `avg_cost`) → isi `wo_item.unit_cost` |
| note | varchar(255) | yes | |

> `qty_approved` boleh < `qty_requested` (SO memangkas). Saat approve → `StockService` reserve
> (`qty_reserved += qty_approved`). Saat issue → movement `out` (qty_issued, UOM dasar via factor),
> `qty_reserved` & `qty_on_hand` turun; stok negatif diizinkan + alert (lihat `stock_alerts`).

### wks_inv_stock_opnames
| id · company_id (FK) · warehouse_id (FK) · opname_no · status `varchar(15)` (enum opname_status: draft/counting/posted) · opname_date `date` · note · created_by · posted_at `timestamptz` null · timestamps · **unique(company_id, opname_no)** |

### wks_inv_stock_opname_items
| id · opname_id (FK) · spare_part_id (FK) · location_id (FK null) · **condition `varchar(10)` (part_condition)** · system_qty `decimal(15,3)` · counted_qty `decimal(15,3)` · diff_qty `decimal(15,3)` · note |

> `condition` **wajib**: stok dilacak per new/used/rebuilt, jadi hitung fisik & adjustment
> (movement) harus menyebut kondisi — tanpa ini saldo per-kondisi tak bisa direkonsiliasi.

### wks_inv_stock_alerts  *(peringatan stok — negatif / di bawah ambang)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | yes | FK |
| spare_part_id | bigint | no | FK |
| warehouse_id | bigint | no | FK |
| condition | varchar(10) | no | part_condition |
| alert_type | varchar(20) | no | enum stock_alert_type (negative_stock/below_min/below_reorder) |
| qty_after | decimal(15,3) | no | saldo setelah mutasi pemicu |
| threshold | decimal(15,3) | yes | ambang yang dilanggar (0 untuk negatif; reorder_point/min_qty) |
| movement_id | bigint | yes | FK stock_movements pemicu (telusur) |
| status | varchar(12) | no | enum alert_status (open/acknowledged/resolved); default `open` |
| acknowledged_by | bigint | yes | FK users |
| acknowledged_at | timestamptz | yes | |
| created_at | timestamptz | no | index(company_id, status, spare_part_id) |

> Dibuat oleh `StockService` saat mutasi membuat saldo **< 0** (`negative_stock`) atau turun
> di bawah `min_qty`/`reorder_point`. **Tidak memblokir** transaksi (kebijakan: izinkan +
> alert). Memicu notifikasi in-app (Filament) ke peran Gudang/Admin; channel WA/email
> menyusul via modul Notifikasi (G3). De-dup: hindari alert ganda saat masih `open` untuk
> kombinasi (part, warehouse, condition, alert_type) yang sama.

### wks_inv_core_returns  *(pengembalian part bekas RUSAK — bukti old-for-new → scrap)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK |
| return_no | varchar(30) | no | **unique(company_id, return_no)** |
| wo_id | bigint | no | FK work_orders — job yang memasang part baru |
| wo_item_id | bigint | no | FK work_order_items — baris pemasangan part baru (pemicu, **1:1**) |
| truck_id | bigint | yes | FK trucks — telusur asal |
| lkm_id | bigint | yes | FK lkm_entries — telusur asal |
| spare_part_id | bigint | no | FK — SKU part bekas |
| qty | decimal(15,3) | no | jumlah bekas dikembalikan (= qty part baru dipasang) |
| failure_reason | varchar(255) | yes | bukti: kenapa part diganti (rusak/aus/patah) |
| photo_path | varchar(255) | yes | foto bukti (storage privat — lihat G4) |
| assessed_value | decimal(15,2) | no | default 0 — taksiran nilai scrap (bukan WAC stok) |
| warehouse_id | bigint | no | FK — gudang penampung bekas |
| location_id | bigint | yes | FK locations — **area holding/scrap** |
| disposition | varchar(12) | no | enum core_disposition (held/scrapped/disposed); default `held` |
| scrap_disposal_id | bigint | yes | FK scrap_disposals — bila masuk lot penjualan scrap |
| received_by | bigint | yes | FK users (Gudang yang menerima bekas) |
| status | varchar(12) | no | enum core_return_status (pending/stored/released); default `pending` |
| note | varchar(255) | yes | |
| timestamps | | | **unique(company_id, wo_item_id)** · index(company_id, spare_part_id) |

> **Wajib** untuk part **non-consumable** (`categories.is_consumable=false`): WO tak boleh
> `done` sebelum tiap baris pemasangan part baru non-consumable punya core return (qty cocok).
> Consumable (oli/filter/grease) dikecualikan. **Tidak masuk `stock_movements`/`stock_values`**
> (bukan stok layak-pakai → jangan kotori WAC); register bukti tersendiri. Telusur asal lengkap
> (truck→LKM→WO→SKU). Nasib: ditahan sebagai bukti (`held`) → dijual scrap (`scrapped`).

### wks_inv_scrap_disposals  *(lot penjualan/pembuangan scrap — ringan, opsional/future)*
| id · company_id (FK) · branch_id (FK) · disposal_no `varchar(30)` **unique(company_id, disposal_no)** · disposal_type `varchar(12)` (enum: sold/discarded) · disposal_date `date` · buyer_name `varchar(150)` null · total_weight `decimal(15,3)` null · total_amount `decimal(15,2)` null *(hasil jual — future)* · note · created_by (FK users) · timestamps |

> Mengelompokkan banyak `core_returns` jadi satu lot scrap; saat lot dijual/dibuang →
> `core_returns.disposition=scrapped/disposed` + `scrap_disposal_id` diisi. Pencatatan
> pendapatan scrap penuh = **future** (selaras mode INTERNAL; lihat §0 MODULES).

---

## 6. PRICE LIST SUPPLIER (`wks_price_`) — harga beli

### wks_price_lists
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| supplier_id | bigint | no | FK suppliers |
| name | varchar(120) | no | |
| currency | varchar(3) | no | default `IDR` |
| tax_type | varchar(12) | no | enum tax_type (exclusive/inclusive/non_pkp) |
| tax_rate | decimal(5,2) | no | default 11.00 |
| is_active | bool | no | default true |
| valid_from | date | yes | |
| valid_to | date | yes | |
| note | varchar(255) | yes | |
| timestamps, deleted_at | | | |

### wks_price_list_items
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| price_list_id | bigint | no | FK |
| item_type | varchar(15) | no | enum price_item_type (spare_part/tyre_product) |
| item_id | bigint | no | id part / tyre_product (polimorfik) |
| price | decimal(15,2) | no | harga beli |
| min_qty | decimal(15,3) | no | default 1 |
| tax_type | varchar(12) | yes | override list |
| tax_rate | decimal(5,2) | yes | override list |
| effective_from | date | yes | |
| timestamps | | | index(item_type, item_id) |

### wks_price_histories
| id · company_id (FK) · price_list_id (FK null) · supplier_id (FK) · item_type · item_id · old_price · new_price · old_tax_type · new_tax_type · source `varchar(10)` (enum price_source: manual/bulk/import) · changed_by (FK users) · changed_at `timestamptz` |

---

## 7. PURCHASING (`wks_po_`)

### wks_po_requests *(opsional)*
| id · company_id (FK) · branch_id (FK) · pr_no · status · requested_by (FK users) · request_date `date` · note · timestamps · **unique(company_id, pr_no)** |

### wks_po_request_items
| id · pr_id (FK) · item_type · item_id · qty `decimal(15,3)` · note |

### wks_po_orders
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK |
| po_no | varchar(30) | no | **unique(company_id, po_no)** |
| supplier_id | bigint | no | FK |
| order_date | date | no | |
| expected_date | date | yes | |
| status | varchar(15) | no | enum po_status |
| currency | varchar(3) | no | default `IDR` |
| tax_type | varchar(12) | no | enum tax_type |
| tax_rate | decimal(5,2) | no | |
| subtotal | decimal(15,2) | no | default 0 |
| tax_amount | decimal(15,2) | no | default 0 |
| total | decimal(15,2) | no | default 0 |
| note | varchar(255) | yes | |
| created_by | bigint | yes | FK users |
| approved_by | bigint | yes | FK users |
| approved_at | timestamptz | yes | |
| timestamps | | | |

### wks_po_order_items  *(harga, PPN & UOM ter-snapshot)*
| id · po_id (FK) · item_type `varchar(15)` (spare_part/tyre_product) · item_id · uom_id (FK, null=UOM dasar) · uom_factor `decimal(15,6)` default 1 · qty `decimal(15,3)` · unit_price `decimal(15,2)` · tax_type · tax_rate · qty_received `decimal(15,3)` default 0 · line_total `decimal(15,2)` |

> `qty`/`unit_price` dalam **satuan dokumen** (`uom_id`); `uom_factor` di-snapshot dari
> `wks_inv_part_uoms` saat baris dibuat. Base: qty_base = qty × factor, unit_cost_base =
> unit_price ÷ factor (dipakai stok & WAC). `qty_received` dalam satuan dokumen yang sama.

### wks_po_supplier_deliveries  *(Surat Jalan MASUK dari supplier — per PO; bisa diisi supplier via portal)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | yes | FK (tujuan kirim) |
| po_id | bigint | no | FK po_orders — **wajib** (SJ selalu atas PO) |
| supplier_id | bigint | no | FK suppliers (dari PO) |
| supplier_do_no | varchar(40) | no | **nomor surat jalan menurut supplier** |
| ship_date | date | yes | tanggal kirim |
| eta | date | yes | perkiraan tiba |
| driver_name | varchar(100) | yes | sopir pengirim |
| vehicle_no | varchar(20) | yes | nopol kendaraan |
| status | varchar(15) | no | enum supplier_delivery_status (draft/submitted/received/cancelled); default `draft` |
| source | varchar(10) | no | enum supplier_delivery_source (portal/manual); default `manual` |
| created_by | bigint | yes | FK users — staf (manual) **atau supplier user (portal)** |
| note | varchar(255) | yes | |
| timestamps | | | **unique(company_id, supplier_id, supplier_do_no)** · index(company_id, po_id) |

### wks_po_supplier_delivery_items
| id · supplier_delivery_id (FK) · po_item_id (FK po_order_items) · item_type `varchar(15)` (spare_part/tyre_product) · item_id · condition `varchar(10)` (part_condition; default new) · qty_shipped `decimal(15,3)` (satuan dokumen PO) · note |

> **Alur:** supplier (atau staf) daftarkan SJ atas PO → `submitted`. Saat barang tiba, **GRN
> merujuk SJ** (`supplier_delivery_id`) → tally → posting; status SJ → `received`. SJ **opsional**:
> bila supplier tak pakai portal, staf cukup isi `do_supplier_no` teks di GRN (fallback).
> **Telusur** brand/nomor part nyata tetap di GRN item (`part_number_id`). Portal supplier
> (panel `/vendor`, login akun supplier) = **fase berikutnya** (feature-flag); kini `source=manual`.

### wks_po_goods_receipts (Serah Terima / GRN — **WAJIB ref PO**)
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK |
| grn_no | varchar(30) | no | **unique(company_id, grn_no)** |
| po_id | bigint | **no** | FK po_orders — **wajib** (tak ada serah terima tanpa PO) |
| supplier_id | bigint | no | FK (dari PO) |
| warehouse_id | bigint | no | FK tujuan |
| supplier_delivery_id | bigint | yes | FK supplier_deliveries — SJ masuk terdaftar (opsional) |
| do_supplier_no | varchar(40) | yes | no. surat jalan supplier (fallback teks bila tanpa SJ terdaftar) |
| status | varchar(15) | no | enum gr_status (draft/checking/posted) |
| received_at | timestamptz | no | |
| received_by | bigint | yes | FK users |
| checked_by | bigint | yes | FK users (verifikasi tally) |
| posted_at | timestamptz | yes | saat stok dibukukan |
| note | varchar(255) | yes | |
| timestamps | | | |

### wks_po_goods_receipt_items
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| grn_id | bigint | no | FK |
| po_item_id | bigint | **no** | FK po_order_items — tiap baris terkait baris PO |
| item_type | varchar(15) | no | spare_part(=SKU)/tyre_product |
| item_id | bigint | no | spare_part_id (SKU) atau tyre_product_id |
| part_number_id | bigint | yes | FK part_numbers — brand/nomor yang nyata diterima (telusur) |
| condition | varchar(10) | no | part_condition; default `new` |
| uom_id | bigint | yes | FK uoms (null = UOM dasar) — satuan dokumen |
| uom_factor | decimal(15,6) | no | default 1 — snapshot konversi ke UOM dasar |
| qty_doc | decimal(15,3) | no | qty sesuai PO/surat jalan (satuan dokumen) |
| qty_received | decimal(15,3) | no | qty diterima nyata/hasil tally (satuan dokumen) |
| unit_cost | decimal(15,2) | no | per satuan dokumen; base = unit_cost ÷ uom_factor (dasar WAC) |
| location_id | bigint | yes | FK locations (rak penyimpanan) |
| tyre_serials | jsonb | yes | daftar serial bila item ban → buat unit di `wks_tyre_tyres` |

> Serah terima **selalu** merujuk PO (`po_id` not null); selisih qty_doc vs qty_received
> tercatat per baris (lihat juga Tally Sheet §7b).
> Posting (status=posted) → part: `wks_inv_stock_movements` (in) + WAC; ban: buat unit
> `wks_tyre_tyres` per serial + `wks_tyre_movements` (in).

---

## 7b. SURAT JALAN & TALLY SHEET (`wks_inv_`) — dokumen gudang

### wks_inv_delivery_orders (Surat Jalan — barang keluar)
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK |
| do_no | varchar(30) | no | **unique(company_id, do_no)** |
| do_type | varchar(15) | no | enum do_type (transfer/issue/return_supplier/other) |
| warehouse_id | bigint | no | FK gudang asal |
| dest_warehouse_id | bigint | yes | FK tujuan (bila transfer antar gudang) |
| recipient_name | varchar(150) | yes | penerima (mis. supplier/site/cabang) |
| recipient_address | text | yes | |
| ref_type | varchar(50) | yes | sumber (work_order/transfer/po_return) — polimorfik |
| ref_id | bigint | yes | |
| status | varchar(15) | no | enum do_status (draft/in_transit/delivered/cancelled) |
| issued_at | timestamptz | no | |
| delivered_at | timestamptz | yes | |
| driver_name | varchar(100) | yes | pengirim |
| vehicle_no | varchar(20) | yes | kendaraan pengirim |
| created_by | bigint | yes | FK users |
| note | varchar(255) | yes | |
| timestamps | | | |

### wks_inv_delivery_order_items
| id · delivery_order_id (FK) · spare_part_id (FK) · condition `varchar(10)` (part_condition) · uom_id (FK null=dasar) · uom_factor `decimal(15,6)` default 1 · qty `decimal(15,3)` (satuan dokumen) · location_id (FK null, asal) · note |

> Surat Jalan barang ≠ surat jalan unit truk (`wks_lkm_gateouts.surat_jalan_no`).
> Posting DO → `wks_inv_stock_movements` (out / transfer) sesuai `do_type`.

### wks_inv_tally_sheets  *(verifikasi hitung fisik — bisa untuk DO atau Serah Terima)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK |
| tally_no | varchar(30) | no | **unique(company_id, tally_no)** |
| source_type | varchar(20) | no | enum tally_source (delivery_order/goods_receipt) |
| source_id | bigint | no | id DO / GRN (polimorfik) |
| tally_date | date | no | |
| status | varchar(12) | no | enum tally_status (draft/completed) |
| tallied_by | bigint | yes | FK users (penghitung) |
| verified_by | bigint | yes | FK users (pemeriksa) |
| note | varchar(255) | yes | |
| timestamps | | | |

### wks_inv_tally_sheet_items
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| tally_sheet_id | bigint | no | FK |
| spare_part_id | bigint | no | FK |
| condition | varchar(10) | no | part_condition |
| doc_qty | decimal(15,3) | no | qty sesuai dokumen (DO/GRN) |
| counted_qty | decimal(15,3) | no | qty hitung fisik |
| diff_qty | decimal(15,3) | no | counted − doc |
| note | varchar(255) | yes | mis. rusak/kurang/lebih |

> Tally sheet = lapisan verifikasi fisik saat bongkar (serah terima) / muat (surat jalan).
> Hasil tally mengisi `qty_received` (GRN) atau mengonfirmasi qty DO sebelum posting.

---

## 7c. SNAPSHOT SALDO STOK (`wks_inv_`) — saldo awal periodik

**Tujuan:** hitung stok pada tanggal manapun & kartu stok **tanpa menjumlah seluruh
sejarah movement**. Saldo "sekarang" tetap dari `wks_inv_stock_items`/`_values` (live, O(1));
snapshot menyediakan **anchor "saldo awal"** harian. Pola: *time-series retention* —
snapshot harian dibuat job tengah malam, dipangkas berkala, **baris akhir-bulan
(`is_anchor`) tak pernah dihapus** agar query historis lama tetap terbatas.

### wks_inv_stock_loc_snapshots  *(FISIK per rak — besar, dipangkas berkala)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| snapshot_date | date | no | tanggal saldo |
| spare_part_id | bigint | no | FK |
| warehouse_id | bigint | no | FK |
| location_id | bigint | yes | FK locations |
| condition | varchar(10) | no | part_condition |
| in_qty | decimal(15,3) | no | mutasi masuk hari itu |
| out_qty | decimal(15,3) | no | mutasi keluar hari itu |
| closing_qty | decimal(15,3) | no | saldo akhir hari (= saldo awal hari berikutnya) |
| is_anchor | bool | no | true bila akhir bulan → **tidak ikut dipangkas** |
| timestamps | | | **unique NULLS NOT DISTINCT (company_id, snapshot_date, spare_part_id, warehouse_id, location_id, condition)** · index(company_id, spare_part_id, warehouse_id, snapshot_date) |

### wks_inv_stock_val_snapshots  *(VALUASI per gudang — ramping, retensi panjang)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| snapshot_date | date | no | |
| spare_part_id | bigint | no | FK |
| warehouse_id | bigint | no | FK |
| condition | varchar(10) | no | part_condition |
| closing_qty | decimal(15,3) | no | saldo akhir hari |
| avg_cost | decimal(15,2) | no | WAC pada tanggal itu |
| closing_value | decimal(15,2) | no | = closing_qty × avg_cost (nilai persediaan) |
| is_anchor | bool | no | true bila akhir bulan |
| timestamps | | | **unique(company_id, snapshot_date, spare_part_id, warehouse_id, condition)** |

> **Job harian (tengah malam):** baca `stock_items`/`stock_values` → tulis snapshot
> `snapshot_date = kemarin`; set `is_anchor=true` bila tanggal = akhir bulan.
> **Job retensi (mingguan):** `DELETE loc_snapshots WHERE snapshot_date < now()-90d AND is_anchor=false`
> (anchor bulanan tetap; `val_snapshots` lebih kecil → retensi lebih panjang).
> **Query stok tanggal X:** snapshot ≤ X terdekat + Σ movement sejak snapshot itu → scan
> ≤ 1 hari (≤ 1 bulan setelah pruning, jatuh ke anchor bulanan). **Kartu stok:** anchor
> pembuka + window `qty_in/qty_out`. **Nilai persediaan tanggal X:** baca `val_snapshots`.

---

## 8. LKM — Laporan Kendaraan Masuk (`wks_lkm_`)

### wks_lkm_entries
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK |
| lkm_no | varchar(30) | no | **unique(company_id, lkm_no)** |
| customer_id | bigint | yes | FK customers |
| truck_id | bigint | no | FK trucks |
| entry_at | timestamptz | no | |
| driver_id | bigint | yes | FK drivers (sopir pengantar) |
| driver_name | varchar(100) | yes | snapshot/fallback bila non-master |
| km_in | bigint | yes | |
| hours_in | int | yes | |
| complaints | text | yes | keluhan |
| status | varchar(15) | no | enum lkm_status |
| created_by | bigint | yes | FK users |
| timestamps | | | |

### wks_lkm_inspections
| id · lkm_entry_id (FK) · item `varchar(80)` · condition `varchar(10)` (enum inspect_condition: good/warning/bad) · note · photo_path `varchar(255)` · created_at |

### wks_lkm_gateouts
| id · lkm_entry_id (FK) · exit_at `timestamptz` · km_out `bigint` · released_by (FK users) · surat_jalan_no `varchar(30)` · note · created_at |

---

## 9. GUDANG BAN / TYRE (`wks_tyre_`)

### wks_tyre_products  *(model ban — acuan harga)*
| id · company_id (FK) · code `varchar(40)` · brand `varchar(60)` · size `varchar(30)` (mis. 1000R20) · pattern `varchar(40)` · category_id (FK null) · description · timestamps · deleted_at · **unique(company_id, code)** |

### wks_tyre_tyres  *(unit fisik per serial)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| tyre_product_id | bigint | no | FK tyre_products |
| serial_no | varchar(50) | no | **unique(company_id, serial_no)** |
| warehouse_id | bigint | yes | FK (null bila terpasang) |
| dot_code | varchar(20) | yes | |
| condition | varchar(10) | no | enum tyre_condition (new/used/retread) |
| tread_depth_mm | decimal(5,2) | yes | |
| status | varchar(15) | no | enum tyre_status |
| purchase_cost | decimal(15,2) | yes | |
| retread_count | smallint | no | default 0 |
| timestamps, deleted_at | | | |

### wks_tyre_movements
| id · company_id (FK) · tyre_id (FK) · warehouse_id (FK null) · type `varchar(20)` (enum movement_type) · ref_type · ref_id · note · moved_at `timestamptz` · created_by (FK users) |

### wks_tyre_installations
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| tyre_id | bigint | no | FK tyres |
| truck_id | bigint | no | FK trucks |
| position | varchar(10) | no | FL,FR,RL1,RR1,… |
| installed_at | timestamptz | no | |
| km_install | bigint | yes | |
| removed_at | timestamptz | yes | null = masih terpasang |
| km_remove | bigint | yes | |
| work_order_id | bigint | yes | FK work_orders |
| note | varchar(255) | yes | |

### wks_tyre_inspections
| id · company_id (FK) · tyre_id (FK) · inspected_at `timestamptz` · tread_depth_mm `decimal(5,2)` · pressure_psi `decimal(6,2)` · position `varchar(10)` null · note |

### wks_tyre_retreads
| id · company_id (FK) · tyre_id (FK) · supplier_id (FK) · sent_at `timestamptz` · received_at `timestamptz` null · cost `decimal(15,2)` · result `varchar(10)` (enum retread_result: ok/failed) · note |

---

## 10. SERVIS / WORK ORDER (`wks_svc_`)

### wks_svc_work_orders
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK |
| wo_no | varchar(30) | no | **unique(company_id, wo_no)** |
| lkm_entry_id | bigint | yes | FK lkm_entries |
| truck_id | bigint | no | FK trucks |
| mechanic_id | bigint | yes | FK mechanics (PIC) |
| status | varchar(15) | no | enum wo_status |
| opened_at | timestamptz | no | |
| closed_at | timestamptz | yes | |
| total_cost | decimal(15,2) | no | default 0 (part HPP + ban + jasa) |
| note | text | yes | |
| created_by | bigint | yes | FK users |
| timestamps | | | |

### wks_svc_work_order_items
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| work_order_id | bigint | no | FK |
| item_type | varchar(12) | no | enum wo_item_type (service/spare_part/tyre) |
| ref_id | bigint | yes | id jasa/part/tyre |
| description | varchar(150) | yes | |
| qty | decimal(15,3) | no | default 1 |
| unit_cost | decimal(15,2) | no | HPP — selalu diisi |
| unit_price | decimal(15,2) | yes | jual — *(future/dormant)* |
| line_cost | decimal(15,2) | no | qty × unit_cost |
| line_price | decimal(15,2) | yes | *(future)* |

> **Core return:** baris `item_type=spare_part` dgn kategori **non-consumable** wajib punya
> `wks_inv_core_returns` (1:1, qty cocok) sebelum WO bisa `done` — bukti part lama rusak.
> Consumable dikecualikan. Lihat §5.

### wks_svc_services *(katalog jasa)*
| id · company_id (FK) · code `varchar(30)` · name · std_cost `decimal(15,2)` · std_price `decimal(15,2)` null *(future)* · est_hours `decimal(6,2)` · is_active · timestamps · **unique(company_id, code)** |

### wks_svc_pm_schedules
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| truck_id | bigint | no | FK trucks |
| name | varchar(80) | no | mis. "Ganti oli mesin" |
| interval_type | varchar(10) | no | enum pm_interval (km/hours/days) |
| interval_value | int | no | |
| last_done_km | bigint | yes | |
| last_done_at | date | yes | |
| next_due_km | bigint | yes | |
| next_due_date | date | yes | |
| is_active | bool | no | default true |
| timestamps | | | |

### wks_svc_invoices  *(future/dormant)*
| id · company_id (FK) · branch_id (FK) · invoice_no · customer_id (FK) · work_order_id (FK null, konsolidasi) · invoice_date · due_date · subtotal · tax_amount · total · status · timestamps · **unique(company_id, invoice_no)** |

### wks_svc_payments  *(future/dormant)*
| id · company_id (FK) · invoice_id (FK) · paid_at `timestamptz` · amount `decimal(15,2)` · method `varchar(20)` · reference `varchar(60)` · created_by · timestamps |

---

## 11. Ringkasan Relasi Antar-Modul

```
core_companies 1─* (semua tabel tenant via company_id)
core_companies 1─* mst_branches 1─* mst_warehouses 1─* mst_locations
mst_customers 1─* mst_trucks *─1 mst_truck_types
mst_drivers 1─* mst_trucks (default_driver) ; mst_drivers ─▶ HRD "Mitra Kerja" (hrd_mitra_id)
mst_drivers 1─* lkm_entries
mst_suppliers 1─* price_lists 1─* price_list_items
inv_spare_parts (SKU internal) 1─* inv_part_numbers (cross-ref Hino/Isuzu/brand)
inv_spare_parts 1─* inv_stock_items *─1 mst_warehouses
inv_spare_parts 1─1 inv_spare_parts (superseded_by — penggantian SKU)
inv_spare_parts 1─* inv_stock_movements
po_orders 1─* po_order_items ; po_orders 1─* po_goods_receipts (po_id WAJIB) 1─* po_goods_receipt_items
po_goods_receipt_items ─▶ inv_stock_movements (part) | tyre_tyres (ban)
inv_delivery_orders 1─* inv_delivery_order_items ─▶ inv_stock_movements (out/transfer)
inv_tally_sheets ─▶ (delivery_order | goods_receipt) 1─* inv_tally_sheet_items
mst_warehouses 1─* mst_locations (zona/rak/bay/level/bin) ; warehouse.condition_scope=new/used
inv_stock_items/movements ber-dimensi condition (new/used/rebuilt)
lkm_entries 1─* lkm_inspections ; lkm_entries 1─1 lkm_gateouts
lkm_entries 1─* svc_work_orders 1─* svc_work_order_items
svc_work_orders *─1 mst_trucks ; *─1 mst_mechanics
tyre_products 1─* tyre_tyres 1─* tyre_installations *─1 mst_trucks
tyre_tyres 1─* tyre_inspections ; tyre_tyres 1─* tyre_retreads
svc_work_orders 1─* svc_invoices 1─* svc_payments   (future)
```

---

## 12. Daftar Enum (varchar + PHP Enum)

| Enum | Nilai |
|---|---|
| company_status | active, suspended, inactive |
| user_status | active, inactive |
| seq_period | none, yearly, monthly |
| customer_type | internal, fleet, individual |
| truck_status | active, inactive, sold |
| ownership_status | owned, leased, rented |
| fuel_type | solar, biosolar, dexlite, cng |
| driver_status | active, inactive, suspended |
| driver_source | manual, hrd |
| sim_type | B1, B2, B1U, B2U |
| warehouse_type | sparepart, tyre, both |
| condition_scope | any, new, used |
| part_condition | new, used, rebuilt |
| part_ref_type | oem, aftermarket |
| location_type | rack, floor, staging |
| category_type | part, tyre |
| mechanic_status | active, inactive |
| movement_type | in, out, transfer_in, transfer_out, adjustment |
| opname_status | draft, counting, posted |
| gr_status | draft, checking, posted |
| do_type | transfer, issue, return_supplier, other |
| do_status | draft, in_transit, delivered, cancelled |
| tally_source | delivery_order, goods_receipt |
| tally_status | draft, completed |
| stock_alert_type | negative_stock, below_min, below_reorder |
| alert_status | open, acknowledged, resolved |
| core_disposition | held, scrapped, disposed |
| core_return_status | pending, stored, released |
| scrap_disposal_type | sold, discarded |
| part_issue_status | draft, submitted, approved, rejected, partially_issued, issued, cancelled |
| supplier_delivery_status | draft, submitted, received, cancelled |
| supplier_delivery_source | portal, manual |
| tax_type | exclusive, inclusive, non_pkp |
| price_item_type | spare_part, tyre_product |
| price_source | manual, bulk, import |
| po_status | draft, approved, partial, received, closed, cancelled |
| lkm_status | entered, in_progress, done, exited |
| inspect_condition | good, warning, bad |
| tyre_condition | new, used, retread |
| tyre_status | in_stock, installed, removed, retreading, scrapped |
| retread_result | ok, failed |
| wo_status | queued, waiting_part, in_progress, qc, done, delivered |
| wo_item_type | service, spare_part, tyre |
| pm_interval | km, hours, days |

---

## 13. Catatan Implementasi

- **Dua lapis saldo live:** `wks_inv_stock_items` = **fisik per rak** (qty saja), grain
  (part, warehouse, location, condition); `wks_inv_stock_values` = **valuasi + WAC** grain
  (part, warehouse, condition). `StockService` meng-update **keduanya** dari tiap movement
  dalam satu `DB::transaction()`. Jangan update lepas.
- **Ledger:** `wks_inv_stock_movements` append-only, `qty_in`/`qty_out` (CHECK tepat satu arah),
  `net_qty` generated. Saldo berjalan TIDAK disimpan per-baris → window function + snapshot.
- **Snapshot saldo (§7c):** job harian tulis `loc_snapshots`/`val_snapshots`; baris akhir-bulan
  `is_anchor=true` permanen; job retensi memangkas snapshot harian non-anchor. Query historis =
  anchor terdekat + movement sesudahnya (scan terbatas).
- **WAC:** `avg_cost` baru = (nilai_lama + nilai_masuk) / (qty_lama + qty_masuk) saat GRN,
  di-hitung **per (part, warehouse, condition)** di `stock_values` dengan `SELECT … FOR UPDATE`
  (cegah race — R7). `spare_parts.last_cost` hanya cache tampilan, bukan dasar valuasi.
- **UOM (konversi box↔pcs):** stok & WAC SELALU di **UOM dasar** (`spare_parts.uom_id`).
  Satuan alternatif di `wks_inv_part_uoms` (`factor` = berapa UOM dasar per satuan itu).
  Dokumen (PO/GRN/DO) menyimpan `uom_id` + `uom_factor` ter-snapshot; `StockService`
  mengonversi ke base saat posting (`qty_base = qty × factor`, `unit_cost_base = unit_cost ÷ factor`).
  Movement & saldo selalu base. Validasi: `factor > 0`.
- **Kebijakan stok negatif: IZINKAN + ALERT** (bukan blokir). `StockService` tetap memproses
  `out` walau `qty_on_hand` jadi < 0, lalu buat `wks_inv_stock_alerts` (`negative_stock`) +
  notifikasi in-app ke Gudang/Admin. Juga alert saat saldo turun di bawah `min_qty`/`reorder_point`.
- **WAC saat saldo ≤ 0:** jangan bagi dengan qty ≤ 0 — **bekukan `avg_cost`** (pakai nilai
  terakhir) selama `qty_on_hand <= 0`; saat stok masuk lagi & qty positif, WAC dihitung ulang
  normal. `out` saat negatif memakai `avg_cost` beku sebagai HPP.
- **Core return (old-for-new):** part baru **non-consumable** dipasang di WO → **wajib** kembalikan
  part bekas rusak (`wks_inv_core_returns`, 1:1) sebagai bukti sebelum WO `done`. Core bekas
  **bukan stok layak-pakai** → tidak masuk `stock_movements`/`stock_values` (beda dari teardown/
  copotan yang reusable); ditampung di area holding/scrap, lalu dijual scrap (`wks_inv_scrap_disposals`).
  Telusur asal: truck→LKM→WO→SKU. Enforcement via `categories.is_consumable` + validasi tutup WO.
- **Snapshot harga:** `po_order_items.unit_price`, `wo_items.unit_cost` di-copy saat dibuat;
  perubahan master/price-list tidak mengubah dokumen lama.
- **Polimorfik** (`item_type`+`item_id`, `ref_type`+`ref_id`): pakai cast/relasi morph
  Laravel; beri index gabungan.
- **FK on delete:** master pakai `restrict` (cegah hapus bila dipakai) + soft delete;
  detail/anak pakai `cascade` ke induknya.
- Belum final (lihat `MODULES.md` §14): metode valuasi (WAC vs FIFO), multi-currency,
  pajak selain PPN, status feature-flag plan/langganan.
