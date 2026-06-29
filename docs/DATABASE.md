# ControlHub Workshop — Data Dictionary (Skema Tabel)

> Cetak biru skema (v0.1) untuk **PostgreSQL** + Laravel. Selaras dengan `MODULES.md`,
> `SITEMAP.md`, dan `.claude/skills/workshop-feature/NAMING_CONVENTIONS.md`.
> Default yang dipakai (belum final, lihat `MODULES.md` §14): valuasi stok **Average/WAC**,
> `wks_ms_customers` = cost-center pemilik unit, mata uang default **IDR**.

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

### wks_adm_notification_rules  *(aturan notifikasi — dikonfigurasi di master per company)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| event_key | varchar(40) | no | `shift.session_overdue`, `stock.alert`, `pm.due`, `truck.doc_expiry`, … |
| name | varchar(100) | no | label aturan |
| is_active | bool | no | default true |
| channels | jsonb | no | channel default, mis. `["email","whatsapp","database"]` |
| recipients | jsonb | no | `{"actor":true,"roles":["Gudang","Admin"],"user_ids":[...]}` (actor=user terkait, mis. operator) |
| escalations | jsonb | yes | tahap: `[{"after_hours":24,"recipients":{...},"channels":[...]},{"after_hours":48,...}]` |
| repeat_hours | smallint | yes | ulang tiap N jam sampai selesai (null = tidak) |
| template | jsonb | yes | `{"subject":"...","body":"..."}` + placeholder (mis. `{operator}`,`{warehouse}`,`{hours}`) |
| timestamps | | | **unique(company_id, event_key)** |

### wks_adm_notifications  *(outbox/log — sekaligus pencegah kirim ganda)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| event_key | varchar(40) | no | dari rule |
| ref_type | varchar(50) | yes | polimorfik (shift_session, stock_alert, …) |
| ref_id | bigint | yes | |
| step | smallint | yes | tahap eskalasi (dedup: 1 kirim per channel per step per ref) |
| channel | varchar(12) | no | enum notification_channel (database/email/whatsapp) |
| recipient_user_id | bigint | yes | FK users |
| recipient_address | varchar(150) | yes | email / no. WA |
| subject | varchar(200) | yes | |
| body | text | no | |
| status | varchar(12) | no | enum notification_status (pending/sent/failed); default `pending` |
| sent_at | timestamptz | yes | |
| error | varchar(255) | yes | |
| created_at | timestamptz | no | index(company_id, event_key, ref_type, ref_id, step) · index(company_id, status) |

> **Mekanik:** event memicu evaluasi `notification_rules` → render `template` → tulis baris
> outbox per (penerima × channel) → channel `email` (Laravel Mail), `whatsapp` (gateway
> `config/integrations.php` — pola sama `HrdGateway`), `database` (in-app Filament). Dedup via
> `(event_key, ref, step, channel)`. Gagal kirim → `status=failed` + retry. Resolusi **G3**.

### wks_adm_document_sequences
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | yes | FK (null = berlaku semua cabang) |
| doc_type | varchar(30) | no | `pmb`,`lkm`,`wo`,`po`,`grn`,`invoice`,… |
| prefix | varchar(20) | yes | mis. `WO` |
| next_number | int | no | default 1 |
| padding | smallint | no | default 4 |
| period | varchar(10) | no | enum seq_period (none/yearly/monthly) |
| timestamps | | | **unique(company_id, branch_id, doc_type)** |

---

## 4. MASTER (`wks_ms_`)

### wks_ms_branches
| id · company_id (FK) · code `varchar(20)` · name · address · phone · is_active · timestamps · deleted_at · **unique(company_id, code)** |

### wks_ms_customers  *(cost-center / pemilik unit internal; eksternal = future)*
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

### wks_ms_truck_types
| id · company_id (FK) · name `varchar(60)` · axle_config `varchar(20)` (mis. 4x2, 6x4, 6x2) · axle_count `smallint` null · description · timestamps · **unique(company_id, name)** |

### wks_ms_axle_positions  *(skema posisi ban per axle_config — slot valid + diagram)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| axle_config | varchar(20) | no | mis. `6x4` — dipadankan ke `truck_types.axle_config` |
| position_code | varchar(10) | no | FL, FR, RL1, RR1, RL2, RR2, … |
| axle_no | smallint | no | nomor sumbu (1=depan) |
| side | varchar(5) | no | enum axle_side (left/right) |
| ordinal | smallint | no | urut tampil pada diagram |
| axle_role | varchar(10) | no | enum axle_application (steer/drive/trailer) — utk validasi fitment |
| is_dual | bool | no | default false — posisi roda ganda (dalam/luar) |
| timestamps | | | **unique(company_id, axle_config, position_code)** · index(company_id, axle_config) |

> **Validasi instalasi:** `wks_tyre_installations.position` harus salah satu `position_code`
> untuk `axle_config` dari `truck.truck_type`. Mencegah salah-ketik posisi & jadi sumber
> **diagram layout ban** (R22). `axle_role` vs `tyre_products.axle_application` → peringatan
> bila ban *steer* dipasang di sumbu *drive* (warning, bukan blok).

### wks_ms_trucks
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

### wks_ms_drivers  *(dapat terhubung ke ControlHub HRD)*
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

### wks_ms_suppliers
| id · company_id (FK) · code `varchar(30)` · name · npwp · address · phone · email · payment_term_days `smallint` · lead_time_days `smallint` · is_pkp `bool` · contacts `jsonb` · is_active · timestamps · deleted_at · **unique(company_id, code)** |

### wks_ms_warehouses
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK branches |
| code | varchar(20) | no | **unique(company_id, code)** |
| name | varchar(80) | no | |
| type | varchar(15) | no | enum warehouse_type (sparepart/tyre/both) |
| condition_scope | varchar(10) | no | enum condition_scope (any/new/used); default `any` — gudang khusus part **baru**/**bekas** |
| tyre_stage | varchar(10) | yes | enum tyre_stage (new/used/afkir/mixed) — **klasifikasi gudang ban**: Gudang Ban Baru / Bekas / **Afkir**; null = bukan/khusus ban. *(Opsional; bisa juga 1 gudang ban dgn area.)* |
| slotting_mode | varchar(10) | no | enum slotting_mode (dynamic/fixed/hybrid); default `dynamic` — **dapat di-set per gudang** |
| is_active | bool | no | default true |
| timestamps, deleted_at | | | |

> **slotting_mode:** `dynamic` = taruh di bin kosong mana pun; `fixed` = wajib di lokasi default
> SKU (`wks_inv_part_locations`); `hybrid` = lokasi default disarankan tapi boleh di mana saja.

### wks_ms_locations  *(rak/bin — hierarki fleksibel, tak terpola)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| warehouse_id | bigint | no | FK |
| parent_id | bigint | yes | **FK self** — hierarki fleksibel (header area/zona/rak → bin); null = root |
| node_type | varchar(10) | no | enum location_node (area/zone/rack/shelf/bin); default `bin` — **header vs bin** |
| code | varchar(30) | no | kode segmen (mis. `A`, `R01`, `B-03`) — **unique(warehouse_id, parent_id, code)** |
| name | varchar(60) | yes | label tampil header/bin |
| full_path | varchar(150) | yes | kode tergabung dari root (mis. `A / R01 / L3 / B05`) — denormalized, regen saat pindah |
| zone | varchar(20) | yes | atribut bebas (opsional, tak wajib terpola) |
| rack | varchar(20) | yes | atribut bebas |
| bay | varchar(20) | yes | atribut bebas |
| level | varchar(20) | yes | atribut bebas |
| bin | varchar(20) | yes | atribut bebas |
| is_storable | bool | no | default true — **hanya bin (leaf) yang menampung stok**; header = false |
| purpose | varchar(12) | no | enum location_purpose (storage/receiving/shipping/quarantine/scrap/staging); default `storage` |
| condition_scope | varchar(10) | no | enum condition_scope (any/new/used); default `any` — bin khusus baru/bekas |
| capacity_qty | decimal(15,3) | yes | kapasitas (qty) — **soft warning** bila terlampaui |
| max_weight_kg | decimal(15,3) | yes | kapasitas berat — soft warning |
| pick_priority | smallint | no | default 0 — urutan saran ambil/putaway |
| is_pickable | bool | no | default true |
| is_blocked | bool | no | default false — bin diblok (rusak/audit) |
| blocked_reason | varchar(100) | yes | |
| barcode | varchar(50) | yes | label scan — **unique(company_id, barcode)** bila terisi |
| is_active | bool | no | default true |
| timestamps | | | index(warehouse_id, parent_id) |

> **Hierarki fleksibel (tak terpola):** `parent_id` + `node_type` membuat pohon bebas —
> header (`area/zone/rack/shelf`, `is_storable=false`) untuk **menggambarkan struktur**, bin
> (`is_storable=true`) untuk menampung stok. Tak ada kedalaman wajib: boleh langsung
> `rack → bin`, atau `zone → rack → shelf → bin`. `stock_items.location_id` selalu menunjuk
> **bin** (leaf storable). `full_path` untuk tampil/cari. Setup massal lihat **generator** (§ MODULES §8).

### wks_ms_uoms
| id · company_id (FK) · code `varchar(15)` · name `varchar(40)` · timestamps · **unique(company_id, code)** |

### wks_ms_categories
| id · company_id (FK) · type `varchar(10)` (enum category_type: part/tyre) · name `varchar(80)` · parent_id (FK self, null) · **is_consumable `bool` default false** (habis-pakai: oli/filter/grease → tak wajib core return) · timestamps · **unique(company_id, type, name)** |

### wks_ms_mechanics
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

### wks_inv_part_locations  *(slotting — lokasi default/home bin per SKU; dipakai bila mode fixed/hybrid)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| spare_part_id | bigint | no | FK |
| warehouse_id | bigint | no | FK |
| location_id | bigint | no | FK locations (**bin** storable) |
| condition | varchar(10) | no | part_condition; default `new` |
| is_default | bool | no | default true — bin utama (saran putaway/pick) |
| max_qty | decimal(15,3) | yes | batas slot (fixed slotting) — soft |
| note | varchar(255) | yes | |
| timestamps | | | **unique(company_id, spare_part_id, warehouse_id, location_id, condition)** |

> Dipakai sesuai `warehouses.slotting_mode`: `dynamic` → tabel ini opsional/kosong (taruh bebas);
> `hybrid` → `is_default` jadi **saran** putaway, boleh ditimpa; `fixed` → putaway **wajib** ke
> lokasi default. Satu SKU bisa punya >1 bin (mis. new vs used kondisi beda, atau multi-bin).

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
| shift_session_id | bigint | yes | FK shift_sessions — sesi gudang harian saat mutasi (akuntabilitas; pelaku = `created_by`) |
| created_at | timestamptz | no | index(company_id, spare_part_id, warehouse_id, moved_at) · index(shift_session_id) |

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

### wks_inv_shift_sessions  *(Sesi Kerja Gudang — **1 siklus/hari per gudang**; Opening = gate masuk panel, Closing = supervisor; **terpadu part + ban**)*

> **Lingkup:** **satu sesi per warehouse per hari** (`business_date`), dipakai bersama semua
> operator gudang hari itu — **bukan** per-operator. Operator **pertama BUKA** (gate masuk
> panel Gudang); **supervisor/Kepala Gudang TUTUP** di akhir hari. Sesi gudang-scoped,
> **bukan** modul-scoped: di gudang `type=both`, SATU sesi mencakup mutasi **sparepart**
> (`wks_inv_stock_movements`) **dan ban** (`wks_tyre_movements`) — keduanya di-tag
> `shift_session_id`. (Nama tabel tetap `wks_inv_` historis; peran = **sesi gudang generik**.)
> Closing men-snapshot saldo part (`wks_inv_shift_session_balances`) **dan** kehadiran ban
> (`wks_tyre_shift_session_tyres`). Akuntabilitas per-aksi tetap dari `created_by`/operator di
> tiap movement.

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK |
| warehouse_id | bigint | no | FK — gudang yang dijaga |
| business_date | date | no | **tanggal kerja gudang** — 1 sesi per gudang per tanggal |
| opened_by | bigint | no | FK users — operator yang **Buka Sesi** (mis. Service Officer) |
| session_no | varchar(30) | no | **unique(company_id, session_no)** |
| status | varchar(12) | no | enum shift_session_status (open/closed/force_closed); default `open` |
| opened_at | timestamptz | no | mulai hari kerja (aksi **Buka Sesi**) |
| closed_at | timestamptz | yes | tutup hari kerja (aksi **Tutup Sesi**) |
| closed_by | bigint | yes | FK users — **supervisor/Kepala Gudang** (closing) / sistem (force) |
| total_movements | int | no | default 0 — jumlah mutasi ter-tag (diisi saat closing) |
| total_in_qty | decimal(15,3) | no | default 0 |
| total_out_qty | decimal(15,3) | no | default 0 |
| total_value_in | decimal(15,2) | no | default 0 |
| total_value_out | decimal(15,2) | no | default 0 |
| anomaly_count | int | no | default 0 — item dgn `diff_qty ≠ 0` (perubahan tak ter-tag) |
| overdue_notified_at | timestamptz | yes | kapan terakhir dinotifikasi belum-ditutup (anti-spam) |
| overdue_notify_step | smallint | no | default 0 — tahap eskalasi notifikasi terakhir terkirim |
| opening_note | varchar(255) | yes | |
| closing_note | varchar(255) | yes | |
| timestamps | | | **1 sesi/gudang/hari:** `unique(warehouse_id, business_date)` · **satu sesi `open` per gudang:** `unique(warehouse_id) WHERE status='open'` · index(company_id, warehouse_id, status) |

> **Gate masuk panel (Opening):** sebelum memakai **panel Gudang**, sesi hari ini untuk
> warehouse aktif **wajib `open`**. Bila belum ada → user diarahkan ke layar **Buka Sesi**
> (semua Resource gudang terblok sampai dibuka). Buka Sesi men-snapshot saldo awal
> (`shift_session_balances` + `tyre_shift_session_tyres`). *(Lapis kedua tetap:* `StockService`
> juga menolak movement tanpa sesi `open` & men-tag `shift_session_id` — jaring pengaman API/PWA.)*
> **Closing (akhir hari, supervisor):** **hanya supervisor/Kepala Gudang** boleh Tutup Sesi
> (izin `shift_session.close`; operator biasa tidak — Buka Sesi = izin `shift_session.open`).
> *Default izin closing melekat ke `Owner`/`Admin` sampai peran `KepalaGudang` di-provisioning.*
> Ringkas movement ter-tag → isi total_* + saldo akhir (full) +
> hitung `anomaly_count` → **update snapshot gudang** (§7c). Setelah `closed`, **siklus hari itu
> selesai**; kerja berikutnya menunggu **Buka Sesi hari berikutnya** (buka ulang tanggal sama =
> override supervisor, di-audit).
> **Lupa tutup → `force_closed`:** supervisor force-close / **job akhir hari** (closed_by=sistem)
> agar tak ada sesi menggantung lintas hari (mencegah benturan `unique(warehouse_id) WHERE open`).
> **Belum ditutup >24 jam:** job terjadwal kirim **WA + email** (event `shift.session_overdue`)
> sesuai `wks_adm_notification_rules`; `overdue_notify_step`/`overdue_notified_at` cegah kirim ganda.

### wks_inv_shift_session_balances  *(snapshot SELURUH saldo gudang saat buka & tutup)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| session_id | bigint | no | FK shift_sessions |
| spare_part_id | bigint | no | FK |
| location_id | bigint | yes | FK locations |
| condition | varchar(10) | no | part_condition |
| opening_qty | decimal(15,3) | no | saldo saat **buka** |
| opening_avg_cost | decimal(15,2) | no | WAC saat buka |
| in_qty | decimal(15,3) | no | default 0 — Σ masuk **ter-tag sesi ini** |
| out_qty | decimal(15,3) | no | default 0 — Σ keluar ter-tag sesi ini |
| closing_qty | decimal(15,3) | yes | saldo saat **tutup** |
| closing_avg_cost | decimal(15,2) | yes | WAC saat tutup |
| diff_qty | decimal(15,3) | yes | `closing − (opening + in − out)`; **≠0 = anomali** (mutasi tak ter-tag / operator lain) |
| | | | **unique(session_id, spare_part_id, location_id, condition)** |

> Cakupan **seluruh saldo gudang** (semua SKU+kondisi+lokasi yang ada stok) di-snapshot saat
> buka (opening_qty) & tutup (closing_qty). `in/out` = movement **ter-tag sesi** (akuntabilitas
> operator). `diff_qty≠0` menandai gudang berubah di luar yang dicatat operator ini (mis.
> operator lain di gudang sama, atau mutasi tak ter-tag) → naikkan `anomaly_count` & review.

### wks_tyre_shift_session_tyres  *(snapshot kehadiran BAN per serial saat buka & tutup)*
| id · session_id (FK shift_sessions) · tyre_id (FK tyres) · location_id (FK null) · present_open `bool` (di gudang saat buka) · present_close `bool` null (saat tutup) · moved_in `bool` default false (masuk ter-tag sesi) · moved_out `bool` default false (keluar ter-tag sesi) · anomaly `bool` default false (`present_close` beda dari `present_open ± moved`) · **unique(session_id, tyre_id)** |

> Untuk ban (serial), "saldo" = **kehadiran** (ada/tidak), bukan qty. `anomaly=true` bila ban
> hilang/muncul tanpa movement ter-tag → naikkan `shift_sessions.anomaly_count`.

---

## 5b. TRANSAKSI GUDANG — Peminjaman, Retur Supplier, Retur Bon (`wks_inv_`)

> Melengkapi **Jenis Transaksi Gudang** (MODULES §8): #5 Loan, #7 Retur Supplier, #8 Retur Bon.
> Semua memposting `wks_inv_stock_movements` via `StockService` (wajib Sesi `open`, ter-tag,
> idempoten). #4 Mutasi/Relokasi pakai `wks_inv_delivery_orders` (do_type `transfer`) atau
> movement transfer antar lokasi; #6 Temuan = `wks_inv_stock_opnames` atau penyesuaian
> (`movement_type=adjustment`, `adjustment_reason=found`).

### wks_inv_part_loans  *(#5 Peminjaman part keluar — "storing", WAJIB kembali)*
Part keluar **sementara** (mis. unit mogok/perbaikan di luar). **Tetap aset** (tidak dibebankan
ke WO) sampai kembali; bila tak kembali → **dikonversi jadi Bon** (pemakaian, baru dibebankan).
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK |
| loan_no | varchar(30) | no | **unique(company_id, loan_no)** |
| warehouse_id | bigint | no | FK gudang asal |
| borrower | varchar(150) | no | peminjam (driver/site/unit/mekanik luar) |
| ref_type | varchar(50) | yes | konteks (lkm/truck/wo/site) — polimorfik |
| ref_id | bigint | yes | |
| status | varchar(20) | no | enum loan_status (open/partially_returned/returned/cancelled); default `open` |
| loaned_at | timestamptz | no | saat pinjam (movement `loan_out`) |
| due_date | date | yes | target kembali |
| returned_at | timestamptz | yes | saat lunas kembali |
| converted_issue_id | bigint | yes | FK `wks_inv_part_issues` — bila dikonversi jadi pemakaian |
| note | varchar(255) | yes | |
| created_by | bigint | yes | FK users |
| timestamps | | | index(company_id, status) |

### wks_inv_part_loan_items
| id · part_loan_id (FK) · spare_part_id (FK) · condition `varchar(10)` (part_condition) · location_id (FK, asal) · qty_loaned `decimal(15,3)` · qty_returned `decimal(15,3)` default 0 · qty_converted `decimal(15,3)` default 0 (jadi pemakaian) · unit_cost `decimal(15,2)` (WAC saat pinjam, untuk konversi) · **unique(part_loan_id, spare_part_id, condition)** |

> **Post pinjam** → `loan_out` (qty keluar dari lokasi, tetap tercatat sebagai milik perusahaan
> via dokumen ini; tak masuk biaya WO). **Pengembalian** → `loan_return` (qty kembali ke stok);
> `qty_returned` naik; semua kembali → `returned`. **Konversi** (tak jadi kembali) → buat Bon
> (`converted_issue_id`), sisa qty jadi pemakaian (HPP→WO). `StockService` menjaga
> `qty_loaned = qty_returned + qty_converted + outstanding`.

### wks_inv_purchase_returns  *(#7 Retur ke supplier — memotong tagihan)*
Kembalikan part ke supplier (rusak/salah kirim/kelebihan), **ref PO/GRN**; menerbitkan **nota
retur** yang **mengurangi hutang** ke supplier (AP — modul §9 PANELS, *menyusul*).
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK |
| return_no | varchar(30) | no | **unique(company_id, return_no)** |
| supplier_id | bigint | no | FK suppliers |
| po_id | bigint | yes | FK po_orders (asal) |
| goods_receipt_id | bigint | yes | FK `wks_po_goods_receipts` (GRN asal) |
| warehouse_id | bigint | no | FK gudang asal |
| reason | varchar(30) | no | rusak/salah_kirim/kelebihan/lainnya |
| status | varchar(15) | no | enum purchase_return_status (draft/posted/credited/cancelled); default `draft` |
| returned_at | timestamptz | yes | saat barang keluar (movement `return_supplier`) |
| credit_note_no | varchar(40) | yes | no nota retur/kredit dari/ke supplier (AP) |
| credit_amount | decimal(15,2) | no | default 0 — nilai pengurang tagihan |
| note | varchar(255) | yes | |
| created_by | bigint | yes | FK users |
| timestamps | | | index(company_id, supplier_id) |

### wks_inv_purchase_return_items
| id · purchase_return_id (FK) · spare_part_id (FK) · goods_receipt_item_id (FK null, telusur) · condition `varchar(10)` · qty `decimal(15,3)` · unit_cost `decimal(15,2)` (WAC/harga GRN) · line_amount `decimal(15,2)` · **unique(purchase_return_id, spare_part_id, condition)** |

> **Post** → `return_supplier` (stok turun, pakai WAC) → `posted`. Saat AP aktif, nota retur
> **mengurangi** `wks_ap_kontrabons.credit_amount` terkait → `credited` (`credit_note_no`/`credit_amount`).
> Selama AP belum ada: catat `credit_amount` sebagai memo (telusur ke PO/GRN).

### wks_inv_issue_returns  *(#8 Retur Bon — part baru dari LKM TAK JADI PAKAI)*
Part **baru** yang sudah dikeluarkan via Bon tapi **tak terpakai** di WO → kembali ke stok,
**kurangi biaya WO** (reverse HPP). **Beda** dari Core/Teardown (itu part **bekas**).
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK |
| return_no | varchar(30) | no | **unique(company_id, return_no)** |
| part_issue_id | bigint | no | FK `wks_inv_part_issues` (Bon asal) |
| work_order_id | bigint | yes | FK work_orders (telusur biaya) |
| warehouse_id | bigint | no | FK gudang tujuan kembali |
| status | varchar(15) | no | enum issue_return_status (draft/posted/cancelled); default `draft` |
| returned_at | timestamptz | yes | saat masuk kembali (movement `return_wo`) |
| note | varchar(255) | yes | |
| created_by | bigint | yes | FK users (Gudang; usul bisa dari Mekanik/SO) |
| timestamps | | | index(company_id, part_issue_id) |

### wks_inv_issue_return_items
| id · issue_return_id (FK) · part_issue_item_id (FK, baris Bon asal) · spare_part_id (FK) · condition `varchar(10)` (umumnya new) · location_id (FK, rak tujuan) · qty `decimal(15,3)` · unit_cost `decimal(15,2)` (HPP saat dikeluarkan — untuk reverse) · **unique(issue_return_id, part_issue_item_id)** |

> **Post** → `return_wo` (stok naik, condition new) → kurangi `wo_item.line_cost`/`total_cost`
> sebesar qty×unit_cost (reverse HPP). Tak boleh melebihi qty yang sudah di-`issue`.

---

## 5c. AUDIT GUDANG SPAREPART (`wks_inv_`) — kontrol & jejak

> Modul **audit** = lapis **kontrol independen** atas stok. **Tidak mengubah stok langsung**:
> audit menghasilkan **temuan + rekomendasi**; koreksi tetap lewat **opname/penyesuaian**
> (tertelusur) lalu auditor **verifikasi**. **SoD:** **Auditor** ≠ operator Gudang ≠ Kepala
> Gudang (subjek audit). Tiga komponen:
> 1. **Audit formal** (`wks_inv_audits` + items + findings) — periodik/ad-hoc oleh auditor.
> 2. **Jejak immutable (trail)** — *tanpa tabel baru*: gabungan **`wks_inv_stock_movements`**
>    (ledger append-only: siapa `created_by`, jenis, ref, sesi) + **`wks_core_audit_logs`**
>    (perubahan master/konfigurasi inv: before→after). Disajikan sbg **Page read-only** terfilter.
> 3. **Review anomali** — *tanpa tabel baru*: papan atas `wks_inv_stock_alerts` (stok negatif/min),
>    `shift_sessions.anomaly_count`/`shift_session_balances.diff_qty` (selisih sesi), movement tak
>    wajar → **Promosikan jadi Temuan** (isi `source_type`/`source_id` di finding).

### wks_inv_audits  *(header audit gudang)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | yes | FK (null = lintas cabang) |
| audit_no | varchar(30) | no | **unique(company_id, audit_no)** |
| type | varchar(20) | no | enum audit_type (cycle_count/spot_check/full_count/compliance/investigation) |
| title | varchar(150) | no | |
| scope_warehouse_id | bigint | yes | FK warehouses (null = semua) |
| scope_category_id | bigint | yes | FK categories (null = semua) |
| scope_filter | jsonb | yes | filter tambahan (SKU/lokasi/kondisi) bila perlu |
| period_start | date | yes | untuk audit periode (movement/aging) |
| period_end | date | yes | |
| auditor_id | bigint | no | FK users — **auditor independen** |
| status | varchar(15) | no | enum audit_status (planned/in_progress/review/closed/cancelled); default `planned` |
| planned_date | date | yes | |
| started_at | timestamptz | yes | |
| closed_at | timestamptz | yes | |
| summary | text | yes | kesimpulan |
| findings_count | int | no | default 0 (cache) |
| open_critical_count | int | no | default 0 (cache) |
| created_by | bigint | yes | FK users |
| timestamps | | | index(company_id, status) |

### wks_inv_audit_items  *(baris cek fisik — untuk cycle/full count; book vs counted)*
| id · audit_id (FK) · spare_part_id (FK) · warehouse_id (FK) · location_id (FK null) · condition `varchar(10)` (part_condition) · book_qty `decimal(15,3)` (saldo catatan saat audit) · counted_qty `decimal(15,3)` null (hasil hitung auditor) · diff_qty `decimal(15,3)` (counted − book) · book_value `decimal(15,2)` · note `varchar(255)` · **unique(audit_id, spare_part_id, location_id, condition)** |

> Diff signifikan → **buat finding** (bukan adjustment otomatis). **Koreksi saldo** dilakukan
> Gudang via **opname/penyesuaian** terpisah (movement tertelusur), lalu di-link ke finding
> (`resolution_ref`). Audit_item = bukti hitung independen, **read-only terhadap stok**.

### wks_inv_audit_findings  *(temuan + rekomendasi + tindak lanjut)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| audit_id | bigint | yes | FK wks_inv_audits — **null = temuan ad-hoc** dari review anomali |
| finding_no | varchar(30) | no | **unique(company_id, finding_no)** |
| type | varchar(25) | no | enum finding_type (stock_discrepancy/negative_stock/sop_violation/missing_document/aging_slow_moving/valuation_issue/theft_suspected/other) |
| severity | varchar(10) | no | enum finding_severity (low/medium/high/critical) |
| source_type | varchar(30) | yes | polimorfik: audit_item/stock_alert/shift_session/stock_movement/manual |
| source_id | bigint | yes | |
| spare_part_id | bigint | yes | FK (bila spesifik SKU) |
| warehouse_id | bigint | yes | FK |
| location_id | bigint | yes | FK |
| expected | varchar(255) | yes | nilai seharusnya (mis. book_qty / SOP) |
| actual | varchar(255) | yes | nilai aktual ditemukan |
| description | text | no | uraian temuan |
| recommendation | text | yes | rekomendasi auditor |
| corrective_action | text | yes | tindakan korektif (diisi penanggung jawab) |
| responsible_user_id | bigint | yes | FK users — PIC tindak lanjut |
| due_date | date | yes | target penyelesaian |
| resolution_ref_type | varchar(50) | yes | telusur koreksi (stock_opname/stock_movement/…) |
| resolution_ref_id | bigint | yes | |
| status | varchar(15) | no | enum finding_status (open/in_progress/resolved/verified/closed/rejected); default `open` |
| resolved_by | bigint | yes | FK users (Gudang/PIC) |
| resolved_at | timestamptz | yes | |
| verified_by | bigint | yes | FK users — **auditor** (verifikasi koreksi) |
| verified_at | timestamptz | yes | |
| created_by | bigint | yes | FK users (auditor) |
| timestamps | | | index(company_id, status) · index(company_id, severity) · index(audit_id) |

> **Alur temuan:** `open` → PIC isi `corrective_action` (status `in_progress`) → koreksi
> dijalankan (opname/penyesuaian, di-link `resolution_ref`) → `resolved` → **auditor
> verifikasi** → `verified`/`closed` (atau `rejected` bila tak valid). Temuan **tak menyentuh
> stok**; hanya menautkan ke dokumen koreksi. Severity `critical` → notifikasi (Owner/Admin).

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

> **Berlaku untuk SPAREPART & BAN** (`item_type` spare_part/tyre_product). **Untuk ban,** SJ
> hanya **product + qty_shipped**; **serial ban diregistrasi saat GRN** (sisi kita), bukan di SJ.
>
> **Alur (idealnya supplier-first via Portal):** **supplier isi SJ sendiri di web `/vendor`**
> (`source=portal`) atas PO → `submitted` — **operator gudang tak menyalin SJ**. **Saat barang
> tiba di GUDANG**, operator Gudang (panel §5) buka **GRN → pilih SJ yang sudah ada** → tally
> fisik → posting (`StockService`/`TyreService` *in*); status SJ → `received`. **Tanpa portal:**
> staf isi SJ sendiri (`source=manual`) atau cukup `do_supplier_no` teks di GRN (fallback).
> **PO dibuat/di-approve di Purchasing** (lihat-saja SJ/GRN; SoD pembeli ≠ penerima).
> **Telusur** brand/nomor part nyata tetap di GRN item. **Web supplier `/vendor` diaktifkan via
> feature-flag** (keamanan R46/R47: akun undangan, scope `supplier_id`, rate-limit, audit).
> ⚠️ **Surat Jalan MASUK** (ini) = pengantar sparepart/ban dari supplier ke gudang (per PO) ≠
> **Surat Jalan KELUAR** (`wks_inv_delivery_orders` §7b, transfer/issue).

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

## 7d. HUTANG SUPPLIER / ACCOUNTS PAYABLE (`wks_ap_`) — Kontrabon & Kasir

> **Kontrabon = dokumen yang KITA buat** ("tanda terima tagihan") untuk **menyalin tagihan
> supplier**, lalu **review & cocokkan satu per satu**. **2 level:** header **kontrabon**
> (per supplier, jatuh tempo, hutang) + **baris tagihan** (`_invoices`, **bisa 1 atau banyak**
> faktur supplier per kontrabon) — tiap baris dicek **checklist**: **barang diterima (GRN) ·
> surat jalan · faktur pajak (PPN) · PO & nominal cocok**. Hilir **PO → GRN**:
> ```
> PO → GRN ─► KONTRABON (salin tagihan + cek satu per satu) ─► approve (hutang)
>          ─► KASIR: Request Pembayaran (maker→checker) ─► Realisasi (giro/digital) ─► lunas
> ```
> Dua panel terpisah: **Kontrabon** (Finance/AP, akui hutang) & **Kasir** (kelola **rekening
> bank**, **request pembayaran** maker→checker, realisasi via **giro/digital**). Mode internal:
> hutang ke **supplier** (sah), beda dari invoice **customer** (future/dormant). Semua posting via
> **`ApService`** (`DB::transaction()`, idempoten). Lihat MODULES §17, WORKFLOWS §4c, PANELS §7–§9.

### wks_ap_kontrabons  *(header — tanda terima tagihan; per supplier, 1..n tagihan)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK |
| kontrabon_no | varchar(30) | no | no. internal **kita** (doc_type `kontrabon`) — **unique(company_id, kontrabon_no)** |
| supplier_id | bigint | no | FK suppliers — satu kontrabon = satu supplier |
| received_date | date | no | tanggal tagihan diterima dari supplier ("tukar faktur") |
| due_date | date | no | jatuh tempo bayar (= received/invoice + supplier.payment_term_days; bisa override) |
| currency | varchar(3) | no | default `IDR` |
| invoice_count | smallint | no | default 0 — jumlah baris tagihan (cache) |
| subtotal | decimal(15,2) | no | default 0 — Σ baris (DPP) |
| tax_amount | decimal(15,2) | no | default 0 — Σ PPN baris |
| total | decimal(15,2) | no | default 0 — Σ total baris |
| credit_amount | decimal(15,2) | no | default 0 — pengurang dari **nota retur** (`wks_inv_purchase_returns`) |
| paid_amount | decimal(15,2) | no | default 0 — cache Σ alokasi pembayaran (dijaga ApService) |
| outstanding | decimal(15,2) | — | **GENERATED ALWAYS AS (total - credit_amount - paid_amount) STORED** — hutang efektif |
| status | varchar(15) | no | enum ap_kontrabon_status; default `draft` |
| verified_by | bigint | yes | FK users — **Finance/AP yang review/cek satu per satu** |
| verified_at | timestamptz | yes | |
| approved_by | bigint | yes | FK users — **approver (akui hutang)** — SoD: ≠ verified_by |
| approved_at | timestamptz | yes | |
| reject_reason | varchar(255) | yes | bila ditolak/disengketakan |
| note | varchar(255) | yes | |
| created_by | bigint | yes | FK users |
| timestamps | | | index(company_id, supplier_id, status) · index(company_id, due_date, status) |

> **Alur status:** `draft` (Finance salin tagihan) → `checking` (review cek satu per satu) →
> `verified` (**semua baris OK**; checklist lengkap; `verified_by`) → `approved` (hutang **diakui**;
> `approved_by` ≠ `verified_by`, SoD) → `partially_paid`/`paid` (pembayaran Kasir). `rejected`
> (ada baris bermasalah/sengketa → kembalikan ke supplier) · `cancelled`. **Hutang efektif** =
> `outstanding`. **Pembayaran hanya** atas kontrabon `approved`/`partially_paid`. **Gate verify:**
> tak bisa `verified` bila ada baris `check_status ≠ ok` (lihat checklist baris).

### wks_ap_kontrabon_invoices  *(baris tagihan = faktur supplier; dicek satu per satu)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK (denormalized utk unique anti-dobel) |
| kontrabon_id | bigint | no | FK `wks_ap_kontrabons` (cascade) |
| supplier_id | bigint | no | FK suppliers (denormalized dari header) |
| supplier_invoice_no | varchar(40) | no | **no. faktur/tagihan menurut supplier** |
| tax_invoice_no | varchar(40) | yes | **no. Faktur Pajak (NSFP)** — PPN masukan |
| invoice_date | date | no | tanggal faktur supplier |
| po_id | bigint | yes | FK po_orders — PO yang dirujuk (telusur) |
| goods_receipt_id | bigint | yes | FK `wks_po_goods_receipts` — penerimaan barang yang ditagih |
| description | varchar(150) | yes | uraian (bila bukan ber-GRN, mis. jasa/ongkir) |
| tax_type | varchar(12) | no | enum tax_type (exclusive/inclusive/non_pkp) |
| tax_rate | decimal(5,2) | no | mis. 11.00 |
| subtotal | decimal(15,2) | no | default 0 — DPP tagihan |
| tax_amount | decimal(15,2) | no | default 0 — PPN |
| total | decimal(15,2) | no | default 0 — subtotal + tax_amount |
| chk_goods_received | bool | no | default false — ☑ **barang sudah diterima** (cocok GRN) |
| chk_delivery_note | bool | no | default false — ☑ **Surat Jalan** ada & cocok |
| chk_tax_invoice | bool | no | default false — ☑ **Faktur Pajak (PPN)** lengkap |
| chk_po_match | bool | no | default false — ☑ **PO & nominal cocok** |
| check_status | varchar(10) | no | enum ap_invoice_check_status; default `pending` (pending/ok/problem) |
| check_note | varchar(255) | yes | catatan masalah (selisih nominal/qty, dokumen kurang) |
| checked_by | bigint | yes | FK users — pemeriksa baris |
| checked_at | timestamptz | yes | |
| timestamps | | | **unique(company_id, supplier_id, supplier_invoice_no)** (cegah dobel-input faktur) · index(kontrabon_id) · index(company_id, goods_receipt_id) |

> **Review satu per satu:** tiap baris = satu tagihan/faktur supplier. Pemeriksa centang 4
> checklist (`chk_*`). Bila **keempat true** → `check_status=ok`; ada yang kurang/selisih →
> `problem` (+ `check_note`). Header **`verified`** hanya bila **semua baris `ok`** (gate).
> `goods_received` dicocokkan ke **GRN** (`wks_po_goods_receipts`); `po_match` ke **PO** (nominal).
> Satu faktur boleh tanpa GRN (jasa/ongkir → `goods_receipt_id` null, `description` diisi).
> **Satu kontrabon boleh memuat banyak faktur**; tiap faktur **unik** per supplier (anti dobel).

### wks_ap_bank_accounts  *(master rekening kas/bank — dikelola Kasir)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| code | varchar(20) | no | kode internal — **unique(company_id, code)** |
| name | varchar(80) | no | label tampil (mis. "BCA Operasional") |
| type | varchar(10) | no | enum ap_account_type (cash/bank); default `bank` |
| bank_name | varchar(60) | yes | nama bank (null bila kas tunai) |
| account_no | varchar(40) | yes | no. rekening |
| account_holder | varchar(100) | yes | atas nama |
| currency | varchar(3) | no | default `IDR` |
| supports_digital | bool | no | default false — rekening terhubung **digital banking (maker-checker)** |
| supports_giro | bool | no | default false — rekening bisa terbitkan giro/cek |
| is_active | bool | no | default true |
| note | varchar(255) | yes | |
| timestamps, deleted_at | | | soft delete (master) |

> **Dikelola Kasir** (panel `/kasir`). Sumber dana pembayaran (`ap_payment_requests`/`ap_payments`).
> `supports_digital`/`supports_giro` membatasi metode yang valid per rekening. *(Bila kelak dipakai
> modul lain — payroll/AR — bisa dipromosikan ke master umum; sekarang scope AP.)*

### wks_ap_payment_requests  *(Request Pembayaran ke supplier sparepart/ban — maker→checker)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK |
| request_no | varchar(30) | no | no. internal (doc_type `ap_pay_req`) — **unique(company_id, request_no)** |
| supplier_id | bigint | no | FK suppliers |
| bank_account_id | bigint | yes | FK `wks_ap_bank_accounts` — rekening sumber (boleh ditetapkan saat approve) |
| method | varchar(12) | no | enum ap_payment_method (transfer/giro/digital/cash) |
| requested_amount | decimal(15,2) | no | default 0 — Σ item (kontrabon yang diminta dibayar) |
| due_date | date | yes | target bayar (dari jatuh tempo kontrabon) |
| status | varchar(12) | no | enum ap_payment_request_status; default `draft` |
| requested_by | bigint | no | FK users — **maker** (pengaju) |
| requested_at | timestamptz | no | |
| approved_by | bigint | yes | FK users — **checker** (penyetuju) — SoD: ≠ requested_by |
| approved_at | timestamptz | yes | |
| reject_reason | varchar(255) | yes | bila ditolak |
| note | varchar(255) | yes | |
| timestamps | | | index(company_id, supplier_id, status) · index(company_id, status, due_date) |

> **Maker→checker:** `draft` (maker susun: pilih kontrabon `approved` + metode + rekening) →
> `submitted` → `approved` (checker; SoD `approved_by ≠ requested_by`) / `rejected`. Setelah
> `approved` → dieksekusi jadi `wks_ap_payments`; saat pembayaran `posted` → request `paid`.
> `cancelled` membatalkan. **"Digital maker"** = metode `digital`: eksekusi via digital banking
> (maker input di sistem bank, checker bank otorisasi) — governance maker-checker kita = request ini.

### wks_ap_payment_request_items  *(kontrabon yang diminta dibayar — alokasi)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| payment_request_id | bigint | no | FK (cascade) |
| kontrabon_id | bigint | no | FK `wks_ap_kontrabons` |
| amount | decimal(15,2) | no | nilai diminta utk kontrabon ini (≤ outstanding) |
| note | varchar(255) | yes | |
| | | | **unique(payment_request_id, kontrabon_id)** |

> Satu request boleh menutup **banyak** kontrabon; satu kontrabon boleh dibayar **bertahap**
> (muncul di beberapa request). Guard: `Σ item = requested_amount` & `amount ≤ outstanding` per kontrabon.

### wks_ap_payments  *(realisasi pembayaran — eksekusi giro/digital → settle hutang)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK |
| payment_no | varchar(30) | no | no. internal (doc_type `ap_payment`) — **unique(company_id, payment_no)** |
| payment_request_id | bigint | no | FK `wks_ap_payment_requests` — eksekusi atas request `approved` |
| supplier_id | bigint | no | FK suppliers (dari request) |
| bank_account_id | bigint | no | FK `wks_ap_bank_accounts` — rekening sumber dana |
| payment_date | date | no | tanggal bayar |
| method | varchar(12) | no | enum ap_payment_method (transfer/giro/digital/cash) |
| reference_no | varchar(50) | yes | no. referensi transfer / bukti |
| digital_ref | varchar(60) | yes | no. transaksi/bukti **digital banking** (method=digital) |
| amount | decimal(15,2) | no | default 0 — total bayar (= Σ item request) |
| status | varchar(12) | no | enum ap_payment_status; default `draft` |
| note | varchar(255) | yes | |
| created_by | bigint | yes | FK users — **Kasir** (eksekutor/maker bank) |
| posted_by | bigint | yes | FK users |
| posted_at | timestamptz | yes | saat di-posting (hutang ter-settle) |
| timestamps | | | index(company_id, supplier_id, status) · index(bank_account_id, payment_date) |

> **Status:** `draft` → `posted` (settle: `paid_amount` kontrabon naik, request → `paid`) →
> `cleared` / `cancelled`. **transfer/digital:** `posted` langsung (simpan `reference_no`/`digital_ref`).
> **giro:** pembayaran **tidak** langsung `posted` — menunggu **Register Giro** (`wks_ap_giros`,
> di bawah) melewati register→print→tanda tangan→verifikasi→**diserahkan**; saat giro `released`
> → payment `posted` (hutang dianggap lunas), saat giro `cleared`/`bounced` → payment `cleared`/`cancelled`.
> `ApService` (transaksi): saat `posted` → untuk tiap **request item** naikkan `kontrabons.paid_amount`,
> set `partially_paid`/`paid` bila `outstanding` habis; guard `amount ≤ outstanding` per kontrabon
> (cegah over-pay), idempoten. **Hanya request `approved` yang bisa dieksekusi.**

### wks_ap_giros  *(Register Giro/Cek — kontrol: register→print→tanda tangan→verifikasi→serah→cair)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK |
| payment_id | bigint | no | FK `wks_ap_payments` (method=giro) — **unique** (1:1) |
| bank_account_id | bigint | no | FK `wks_ap_bank_accounts` — rekening penerbit (harus `supports_giro`) |
| giro_no | varchar(40) | no | no. giro/cek — **unique(company_id, giro_no)** |
| payee_name | varchar(150) | no | atas nama (supplier) |
| amount | decimal(15,2) | no | nilai giro |
| giro_date | date | no | tanggal giro |
| due_date | date | no | tanggal cair (jatuh tempo giro) |
| status | varchar(12) | no | enum ap_giro_status; default `registered` |
| registered_by | bigint | no | FK users — **Kasir yang register di aplikasi** (sebelum tanda tangan) |
| registered_at | timestamptz | no | |
| printed_at | timestamptz | yes | saat dicetak (lembar register/voucher) |
| print_count | smallint | no | default 0 — berapa kali dicetak (audit) |
| signed_by | bigint | yes | FK users — **penanda tangan** (otorisasi); fisik ditandatangani setelah print |
| signed_at | timestamptz | yes | |
| verified_by | bigint | yes | FK users — **pemeriksa**: giro fisik **harus sesuai sistem** (dicek via print) |
| verified_at | timestamptz | yes | |
| released_at | timestamptz | yes | giro **diserahkan** ke supplier → payment `posted` |
| cleared_at | timestamptz | yes | giro cair |
| bounce_reason | varchar(255) | yes | bila gagal cair (`bounced`) |
| note | varchar(255) | yes | |
| timestamps | | | index(company_id, status, due_date) · index(bank_account_id) |

> **Kontrol giro (SoD berlapis):** ① **`registered`** — Kasir input giro di aplikasi **sebelum
> tanda tangan** (no, nilai, jatuh tempo, atas nama dari payment/request). ② **`printed`** — cetak
> lembar register/voucher (`print_count`++); **giro fisik ditandatangani setelah ini**. ③ **`signed`**
> — giro fisik ditandatangani (`signed_by`). ④ **`verified`** — pemeriksa cek **giro fisik vs sistem
> lewat print** (no/nilai/atas nama/jatuh tempo **harus sama**); beda → tahan/perbaiki. ⑤ **`released`**
> — giro diserahkan ke supplier → **payment `posted`** (hutang dianggap lunas). ⑥ **`cleared`** — giro
> cair (`cleared_at`) → payment `cleared`. **`bounced`** (gagal cair → payment `cancelled`, hutang
> kembali `outstanding`) · **`cancelled`** (giro batal sebelum serah). **SoD:** `registered_by`
> (Kasir) ≠ `signed_by` (otorisasi) ≠ `verified_by` (pemeriksa) — cegah giro keluar tak sesuai sistem.

---

## 8a. PMB — Permintaan Mobil Masuk (`wks_pmb_`) — Pengantar Dispatcher

> **PMB ≠ LKM (entitas/modul terpisah, lihat MODULES §16).** PMB = surat **pengantar
> bernomor** yang **diterbitkan Dispatcher** saat driver minta masuk bengkel; LKM (§8) =
> pencatatan truk **benar-benar masuk** oleh Service Officer. Aktif hanya pada mode
> `lkm_intake_mode = dispatcher_permit`. **Independen:** LKM merujuk PMB lewat `pmb_id`
> opsional; **tidak** ada auto-pembuatan LKM dari PMB.

### wks_pmb_requests — Permintaan Mobil Masuk
**Diterbitkan Dispatcher** (bukan driver): cocokkan truk + sopir ke master, catat keluhan/
tujuan. Bisa **dicetak** sebagai surat bernomor.
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK |
| pmb_no | varchar(30) | no | **unique(company_id, pmb_no)** · doc_type `pmb` |
| truck_no | varchar(20) | no | plat/no unit (ketik) — dasar pencocokan |
| truck_id | bigint | yes | FK trucks — dicocokkan **Dispatcher** ke master |
| customer_id | bigint | yes | FK customers — diturunkan dari truck |
| driver_id | bigint | yes | FK drivers (sopir pengantar) |
| driver_name | varchar(100) | yes | snapshot/fallback bila non-master |
| complaints | text | yes | keluhan/tujuan masuk |
| issued_at | timestamptz | no | waktu PMB diterbitkan |
| status | varchar(15) | no | enum pmb_status (`issued`/`used`/`cancelled`) |
| used_lkm_id | bigint | yes | FK wks_lkm_entries — telusur LKM yang merujuk PMB ini |
| note | text | yes | catatan / alasan batal |
| created_by | bigint | no | FK users — **Dispatcher** penerbit |
| timestamps | | | |

> Alur: Dispatcher **terbitkan** → `issued`. Saat truk tiba, Service Officer **mencari PMB**
> (by `pmb_no`/`truck_no`, status `issued`, branch aktif) lalu buat LKM merujuk PMB ini →
> set `status=used` + `used_lkm_id` (dalam transaksi pembuatan LKM, idempoten). PMB tak jadi
> dipakai → **batalkan** (`cancelled` + `note`). Tidak ada langkah "approve" — penerbitan oleh
> Dispatcher sudah final. **SoD:** Dispatcher menerbitkan ≠ Service Officer membuat LKM.
>
> **Anti dobel-pakai:** hanya PMB `issued` yang boleh dipakai; transisi `issued → used`
> dijaga di `PmbService` (lock baris saat buat LKM). `used_lkm_id` **unique** (satu PMB =
> satu LKM); `wks_lkm_entries.pmb_id` juga unique-bila-tidak-null (telusur 1–1).

---

## 8. LKM — Laporan Kendaraan Masuk (`wks_lkm_`)

> **Dua mode penerimaan** (setting company `lkm_intake_mode`, di Admin):
> - **`direct`** — Gate/Satpam langsung buat LKM (`pmb_id` null, tanpa pengantar).
> - **`dispatcher_permit`** — driver lebih dulu ambil **PMB** di pos Dispatcher
>   (`wks_pmb_requests`, §8a). Saat truk tiba, **Service Officer** cek sistem; **bila PMB
>   ada** → buat LKM dengan `pmb_id` terisi. PMB & LKM tetap **entitas independen**.

### wks_lkm_entries
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK |
| lkm_no | varchar(30) | no | **unique(company_id, lkm_no)** |
| pmb_id | bigint | yes | FK `wks_pmb_requests` (§8a) — referensi opsional · **null di mode `direct`** |
| intake_mode | varchar(20) | no | snapshot: `direct` / `dispatcher_permit` |
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

> Ban = **aset ber-serial** dgn siklus hidup & posisi pada unit (bukan stok bulk). Tiap unit
> **dinilai sendiri** (TANPA WAC). Berbagi **gudang & lokasi rak** (`wks_ms_warehouses`/
> `wks_ms_locations`) dan **Sesi Kerja Gudang terpadu** (`wks_inv_shift_sessions` — satu sesi
> mencakup mutasi part **dan** ban di gudang `type=both`) dgn modul Inventory (§5).

### wks_tyre_products  *(model ban — acuan harga & spesifikasi)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id · company_id (FK) | | | |
| code | varchar(40) | no | **unique(company_id, code)** |
| brand | varchar(60) | no | |
| size | varchar(30) | no | mis. 1000R20 |
| pattern | varchar(40) | yes | pola tapak |
| category_id | bigint | yes | FK categories (type=tyre) |
| axle_application | varchar(10) | yes | enum axle_application (steer/drive/trailer/all) — posisi disarankan |
| tube_type | varchar(10) | yes | enum tube_type (tubeless/tubetype) |
| load_index | varchar(10) | yes | mis. 146/143 |
| ply_rating | varchar(10) | yes | mis. 16PR |
| min_tread_mm | decimal(5,2) | yes | ambang **ganti** (mis. 3.00) → alert/scrap |
| retread_max | smallint | no | default 0 — maks vulkanisir (0 = tak boleh retread) |
| is_active | bool | no | default true |
| timestamps, deleted_at | | | |

### wks_tyre_tyres  *(unit fisik per serial — aset)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| tyre_product_id | bigint | no | FK tyre_products |
| serial_no | varchar(50) | no | **identitas unik** — partial unique `(company_id, serial_no) WHERE deleted_at IS NULL` |
| warehouse_id | bigint | yes | FK — null bila `installed`/`retreading` |
| location_id | bigint | yes | FK `wks_ms_locations` (rak/bin saat di gudang; bin storable) |
| dot_code | varchar(20) | yes | usia ban (minggu/tahun) → alert umur |
| condition | varchar(10) | no | enum tyre_condition (new/used/retread) |
| tread_depth_mm | decimal(5,2) | yes | tapak terkini (diisi dari inspeksi) |
| status | varchar(15) | no | enum tyre_status |
| acquired_cost | decimal(15,2) | yes | HPP perolehan (dari GRN / beli used) |
| retread_cost_total | decimal(15,2) | no | default 0 — Σ biaya vulkanisir |
| book_value | decimal(15,2) | no | default 0 — `acquired_cost + retread_cost_total` (basis biaya) |
| total_km_run | bigint | no | default 0 — cache Σ (km_remove − km_install) instalasi tertutup |
| retread_count | smallint | no | default 0 |
| timestamps, deleted_at | | | index(company_id, status) · index(company_id, warehouse_id, condition) |

> **Biaya per KM** = `book_value / total_km_run` (∞ bila belum jalan). `total_km_run` &
> `book_value` di-update via `TyreService` saat instalasi ditutup / retread diterima.
> **Tahap gudang (3):** ban dilepas masih layak → `status=in_stock`+`condition=used` di
> **Gudang Bekas** (`warehouse_id` = gudang `tyre_stage=used`). **Konfirmasi afkir** (Kepala
> Gudang) → `status=afkir` (movement `condemn`) → **pindah** (movement `transfer`) ke **Gudang
> Afkir** (`tyre_stage=afkir`) → **jual afkir** (disposal) → `status=scrapped`.
> `warehouse_id`/`location_id` diisi kembali saat masuk/pindah stok.

### wks_tyre_movements  *(SATU-SATUNYA sumber perubahan posisi/status — append-only)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| tyre_id | bigint | no | FK tyres |
| type | varchar(20) | no | enum **tyre_movement_type** (receipt/install/removal/transfer/retread_send/retread_return/scrap/adjustment) |
| warehouse_id | bigint | yes | FK — gudang konteks (tujuan utk in, asal utk out) |
| location_id | bigint | yes | FK locations — bin tujuan (in) |
| from_warehouse_id | bigint | yes | FK — utk `transfer` (asal) |
| shift_session_id | bigint | yes | FK `wks_inv_shift_sessions` (sesi gudang terpadu yg men-tag) |
| unit_cost | decimal(15,2) | yes | biaya terkait (receipt=HPP, retread_return=biaya vulkanisir) → kapitalisasi `book_value` |
| ref_type | varchar(30) | yes | goods_receipt / installation / retread / opname / disposal / delivery_order |
| ref_id | bigint | yes | |
| note | varchar(255) | yes | |
| moved_at | timestamptz | no | |
| created_by | bigint | yes | FK users |
| | | | index(company_id, tyre_id, moved_at) · index(company_id, type, moved_at) |

> Mutasi dilakukan via **`TyreService`** dalam `DB::transaction()`: ubah `tyres.status`/
> `warehouse_id`/`location_id` + tulis movement (1 baris/peristiwa, qty implisit 1).
> Wajib **Sesi Kerja Gudang `open`** (sama spt Inventory) — di-tag `shift_session_id`.

### wks_tyre_installations  *(pemasangan/rotasi di posisi unit)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| tyre_id | bigint | no | FK tyres |
| truck_id | bigint | no | FK trucks |
| position | varchar(10) | no | FL,FR,RL1,RR1,… (divalidasi vs `wks_ms_axle_positions`) |
| installed_at | timestamptz | no | |
| km_install | bigint | yes | KM unit saat pasang |
| removed_at | timestamptz | yes | null = masih terpasang |
| km_remove | bigint | yes | KM unit saat lepas |
| tread_install_mm | decimal(5,2) | yes | tapak saat pasang |
| tread_remove_mm | decimal(5,2) | yes | tapak saat lepas |
| work_order_id | bigint | yes | FK work_orders |
| removal_reason | varchar(20) | yes | enum removal_reason (rotation/worn/damage/retread/swap) |
| note | varchar(255) | yes | |
| | | | **partial unique:** `(truck_id, position) WHERE removed_at IS NULL` (1 ban/slot) · `(tyre_id) WHERE removed_at IS NULL` (ban di 1 tempat) · index(company_id, truck_id) |

> Dua partial-unique = **integritas posisi**: tak ada dua ban di slot sama, satu ban tak
> bisa terpasang ganda. Tutup instalasi (`removed_at`) → tambah `km_remove−km_install` ke
> `tyres.total_km_run`. `km_remove ≥ km_install` (CHECK).

### wks_tyre_inspections  *(inspeksi berkala — tapak & tekanan)*
| id · company_id (FK) · tyre_id (FK) · truck_id (FK null, bila terpasang) · position `varchar(10)` null · inspected_at `timestamptz` · tread_depth_mm `decimal(5,2)` · pressure_psi `decimal(6,2)` · result `varchar(10)` (enum inspect_condition: good/warning/bad) · recommendation `varchar(20)` null (keep/rotate/retread/scrap) · inspected_by (FK users) · note |

> Inspeksi meng-update `tyres.tread_depth_mm`. `tread < product.min_tread_mm` → buat
> `wks_tyre_alerts` (tread_low). Inspeksi terjadwal (overdue) juga memicu alert.

### wks_tyre_retreads  *(vulkanisir — kirim & terima)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id · company_id (FK) · tyre_id (FK) | | | |
| supplier_id | bigint | no | FK suppliers (tukang vulkanisir) |
| retread_no | varchar(30) | yes | **unique(company_id, retread_no)** |
| sent_at | timestamptz | no | → movement `retread_send`, tyre `retreading` |
| received_at | timestamptz | yes | → movement `retread_return`, tyre `in_stock` (condition=retread) |
| cost | decimal(15,2) | yes | biaya → kapitalisasi `book_value` + `retread_cost_total`, `retread_count++` |
| new_tread_mm | decimal(5,2) | yes | tapak hasil |
| result | varchar(10) | yes | enum retread_result (ok/failed) |
| delivery_order_id | bigint | yes | FK `wks_inv_delivery_orders` (surat jalan kirim, opsional) |
| note | varchar(255) | yes | |

> `result=failed` → tyre langsung `scrapped` (tak masuk stok) + masuk `wks_tyre_disposals`.
> Saat `retreading`, ban **WIP di supplier** (off-site) — tak terhitung stok gudang.
> Blok bila `retread_count ≥ product.retread_max`.

### wks_tyre_opnames  *(stok opname ban — cek kehadiran fisik per serial)*
| id · company_id (FK) · branch_id (FK) · warehouse_id (FK) · opname_no `varchar(30)` **unique(company_id, opname_no)** · status `varchar(12)` (enum opname_status: draft/counting/posted) · counted_at `timestamptz` null · counted_by (FK users) · note · timestamps |

### wks_tyre_opname_items
| id · opname_id (FK) · tyre_id (FK null — null bila serial **asing** ter-scan) · scanned_serial `varchar(50)` · expected `bool` (sistem catat di gudang ini) · present `bool` (fisik ada) · location_id (FK null, lokasi temuan) · result `varchar(10)` (enum opname_result: match/missing/extra/misplaced) · note · **unique(opname_id, tyre_id)** |

> Posting opname → ban `missing` (hilang) di-`scrapped`/`adjustment` (movement `adjustment`);
> `misplaced` → update `location_id`; `extra`/asing → registrasi/alert. Serialized = cek
> **kehadiran**, bukan hitung qty.

### wks_tyre_alerts  *(peringatan ban)*
| id · company_id (FK) · tyre_id (FK null) · warehouse_id (FK null) · type `varchar(20)` (enum tyre_alert_type: tread_low/inspection_due/retread_overdue/dot_aged/low_stock) · severity `varchar(10)` (warning/critical) · status `varchar(12)` (enum alert_status: open/acknowledged/resolved) · detail `jsonb` null · created_at · resolved_at null · resolved_by null |

> `tread_low` (tapak < min), `inspection_due` (inspeksi telat), `retread_overdue` (terlalu lama
> di supplier), `dot_aged` (umur DOT > ambang), `low_stock` (stok ban per ukuran < min). Notif
> via `wks_adm_notification_rules` (lihat §3 Admin).

### wks_tyre_disposals  *(lot JUAL AFKIR / buang ban — dari Gudang Afkir)*
| id · company_id (FK) · branch_id (FK) · disposal_no `varchar(30)` **unique(company_id, disposal_no)** · type `varchar(10)` (enum scrap_disposal_type: sold/discarded) · disposed_at `timestamptz` · buyer `varchar(120)` null · total_proceeds `decimal(15,2)` default 0 · note · created_by · timestamps |

### wks_tyre_disposal_items
| id · disposal_id (FK) · tyre_id (FK) · book_value `decimal(15,2)` (nilai saat dibuang) · proceeds `decimal(15,2)` default 0 · **unique(disposal_id, tyre_id)** |

> Ban **`afkir`** (sudah dikonfirmasi + dipindah ke **Gudang Afkir**), juga ban dari retread
> gagal/opname missing → dikumpulkan → **jual afkir** (`sold`) / buang (`discarded`) per lot.
> Posting → movement `scrap` + status tyre `scrapped` (final). `total_proceeds` = hasil jual.

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

### wks_svc_wo_tasks  *(daftar pekerjaan terukur per WO — "apa yang dikerjakan")*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK |
| work_order_id | bigint | no | FK work_orders |
| seq | smallint | no | urutan tampil; default 1 |
| service_id | bigint | yes | FK services (katalog jasa; null = task ad-hoc) |
| work_order_item_id | bigint | yes | FK work_order_items (baris biaya jasa terkait; diisi saat posting biaya) |
| title | varchar(150) | no | deskripsi pekerjaan (mis. "Ganti kampas rem depan") |
| status | varchar(15) | no | enum task_status; default `pending` |
| priority | varchar(10) | no | enum task_priority; default `normal` |
| est_minutes | int | yes | estimasi (default `services.est_hours`×60) |
| actual_minutes | int | no | default 0 — **cache** = Σ `duration_minutes` time_logs (labor-minutes) |
| started_at | timestamptz | yes | saat pertama `in_progress` |
| completed_at | timestamptz | yes | saat `done` |
| requires_part | bool | no | default false (memicu usul Bon ke gudang) |
| result_note | text | yes | hasil/temuan dari mekanik |
| created_by | bigint | yes | FK users — penyusun plan: **Mekanik** (setelah ambil WO), bisa bersama ServiceAdvisor/KepalaMekanik |
| timestamps | | | index(company_id, work_order_id) · index(company_id, status) |

### wks_svc_wo_task_steps  *(langkah/sub-step per task — detail "WO Plan" yang dicentang mekanik)*
**WO Plan** = rangkaian **langkah** di dalam tiap task. Disusun **oleh Mekanik (bisa bersama
Service Officer) setelah mekanik mengambil/ditugaskan WO**; mekanik **mencentang** tiap
langkah saat eksekusi dan **boleh menambah** langkah `adhoc` bila menemukan pekerjaan baru.
Contoh task "Ganti ban" → langkah: *turunkan ban · periksa · minta ban baru · pasang ban
baru · kembalikan yang lama · periksa tekanan akhir.*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK |
| task_id | bigint | no | FK wo_tasks |
| work_order_id | bigint | no | FK work_orders (denormalisasi laporan/index) |
| seq | smallint | no | urutan langkah; default 1 |
| title | varchar(150) | no | langkah (mis. "Turunkan ban", "Periksa tekanan akhir") |
| status | varchar(12) | no | enum step_status; default `pending` (pending/done/skipped) |
| source | varchar(10) | no | enum step_source; default `planned` — `adhoc` = ditambah mekanik di lapangan |
| result_note | text | yes | temuan/hasil per langkah |
| done_by | bigint | yes | FK users — penanda selesai/skip (mekanik) |
| done_at | timestamptz | yes | saat status → `done`/`skipped` |
| created_by | bigint | yes | FK users — **Mekanik** (penyusun plan/`adhoc`), bisa ServiceAdvisor/KepalaMekanik (co-author) |
| timestamps | | | index(company_id, task_id, seq) |

> **Hubungan dengan task:** langkah = sub-bagian task, **bukan** segmen waktu. Pengukuran
> jam tetap di `task_time_logs` (clock-in per **task**), bukan per langkah. Task boleh
> ditandai `done` walau ada langkah `skipped` (dengan `result_note`), tapi UI memberi
> peringatan bila masih ada langkah `pending`. Langkah `adhoc` yang butuh part **tetap**
> lewat alur Bon (`requires_part` di task → usul ke gudang) — tak memotong stok langsung.

### wks_svc_service_steps  *(template langkah per jasa katalog — opsional, prefill WO Plan)*
| id · company_id (FK) · service_id (FK services) · seq `smallint` · title `varchar(150)` · is_active `bool` default true · timestamps · index(company_id, service_id, seq) |

> Saat task dibuat dari `services` (mis. "Ganti ban"), langkah template ini **disalin** ke
> `wo_task_steps` (status `pending`, source `planned`) agar prosedur standar tak diketik ulang.
> Mekanik tetap bisa menambah langkah `adhoc`. *(Opsional — bila kosong, plan disusun manual.)*

### wks_svc_task_assignments  *(penugasan mekanik ke task — planning, multi-mekanik)*
| id · company_id (FK) · task_id (FK wo_tasks) · mechanic_id (FK mechanics) · role `varchar(10)` (enum task_role: lead/helper; default lead) · assigned_by (FK users) · assigned_at `timestamptz` · timestamps · **unique(task_id, mechanic_id)** · index(company_id, mechanic_id) |

### wks_svc_task_time_logs  *(segmen waktu kerja — "berapa lama"; clock in/out live)*
| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK |
| branch_id | bigint | no | FK |
| task_id | bigint | no | FK wo_tasks |
| work_order_id | bigint | no | FK work_orders (denormalisasi untuk laporan/index) |
| mechanic_id | bigint | no | FK mechanics |
| started_at | timestamptz | no | clock-in (mulai/lanjut) |
| ended_at | timestamptz | yes | clock-out (jeda/selesai); **null = sedang berjalan** |
| duration_minutes | int | yes | diisi saat `ended_at` (= ended−started, dibulatkan) |
| pause_reason | varchar(15) | yes | enum pause_reason (saat ditutup karena jeda): wait_part/break/qc/shift_end/other |
| note | text | yes | catatan segmen |
| source | varchar(10) | no | enum log_source (handheld/web); default handheld |
| client_event_id | uuid | yes | id event dari klien — **unique(company_id, client_event_id)** untuk **idempotensi sync offline** |
| synced_at | timestamptz | yes | kapan diterima server (PWA offline) |
| created_at | timestamptz | no | index(company_id, work_order_id) · index(company_id, mechanic_id, started_at) |

> **Satu segmen aktif per mekanik:** partial unique `unique(mechanic_id) WHERE ended_at IS NULL`
> → mekanik tak bisa meng-clock-in dua task sekaligus (integritas pengukuran).
> **Waktu task** dihitung dua cara dari log: **labor-minutes** = Σ `duration_minutes`
> (produktivitas/biaya; multi-mekanik dijumlah) → disimpan di `wo_tasks.actual_minutes` (cache);
> **wall-clock** = `max(ended)−min(started)` (turnaround). Segmen append-only; koreksi via segmen
> pembatalan, bukan update destruktif.

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
core_companies 1─* ms_branches 1─* ms_warehouses 1─* ms_locations
ms_customers 1─* ms_trucks *─1 ms_truck_types
ms_drivers 1─* ms_trucks (default_driver) ; ms_drivers ─▶ HRD "Mitra Kerja" (hrd_mitra_id)
ms_drivers 1─* pmb_requests ; ms_drivers 1─* lkm_entries
ms_suppliers 1─* price_lists 1─* price_list_items
inv_spare_parts (SKU internal) 1─* inv_part_numbers (cross-ref Hino/Isuzu/brand)
inv_spare_parts 1─* inv_stock_items *─1 ms_warehouses
inv_spare_parts 1─1 inv_spare_parts (superseded_by — penggantian SKU)
inv_spare_parts 1─* inv_stock_movements
po_orders 1─* po_order_items ; po_orders 1─* po_goods_receipts (po_id WAJIB) 1─* po_goods_receipt_items
po_goods_receipt_items ─▶ inv_stock_movements (part) | tyre_tyres (ban)
ap_kontrabons (tanda terima tagihan) 1─* ap_kontrabon_invoices (baris tagihan/faktur supplier; cek satu per satu) ; ap_kontrabons *─1 ms_suppliers
ap_kontrabon_invoices ─▶ po_goods_receipts (barang diterima) | po_orders (PO & nominal cocok) ; checklist chk_goods_received/delivery_note/tax_invoice/po_match
ap_bank_accounts (master rekening; dikelola Kasir) ◀ ap_payment_requests / ap_payments
ap_payment_requests (request bayar; maker→checker) 1─* ap_payment_request_items *─1 ap_kontrabons (alokasi; partial/banyak)
ap_payment_requests 1─* ap_payments (realisasi giro/digital → settle kontrabons.paid_amount) ; ap_payments *─1 ap_bank_accounts ; *─1 ms_suppliers
ap_payments 1─1 ap_giros (method=giro; register→print→sign→verify→released→cleared; released → payment posted) ; ap_giros *─1 ap_bank_accounts
inv_purchase_returns ─▶ ap_kontrabons (credit_amount kurangi tagihan; nota retur)
inv_delivery_orders 1─* inv_delivery_order_items ─▶ inv_stock_movements (out/transfer)
inv_part_loans 1─* inv_part_loan_items ─▶ inv_stock_movements (loan_out/loan_return) ; inv_part_loans *─1 inv_part_issues (converted_issue_id)
inv_purchase_returns 1─* inv_purchase_return_items ─▶ inv_stock_movements (return_supplier) ; inv_purchase_returns *─1 po_goods_receipts/po_orders
inv_issue_returns 1─* inv_issue_return_items ─▶ inv_stock_movements (return_wo) ; inv_issue_returns *─1 inv_part_issues *─1 svc_work_orders (reverse HPP)
inv_audits 1─* inv_audit_items (book vs counted) ; inv_audits 1─* inv_audit_findings *─? (source: audit_item|stock_alert|shift_session|stock_movement) ; inv_audit_findings ─▶ resolusi (stock_opname/movement) — audit TIDAK ubah stok
inv_tally_sheets ─▶ (delivery_order | goods_receipt) 1─* inv_tally_sheet_items
ms_warehouses 1─* ms_locations (hierarki parent_id: area/zone/rack/shelf→bin) ; warehouse.condition_scope/slotting_mode
inv_part_locations: SKU →(default bin) location (slotting fixed/hybrid)
inv_stock_items/movements ber-dimensi condition (new/used/rebuilt)
pmb_requests 1─1 lkm_entries (pmb_id opsional, mode dispatcher_permit) ; pmb_requests *─1 ms_trucks
lkm_entries 1─* lkm_inspections ; lkm_entries 1─1 lkm_gateouts
lkm_entries 1─* svc_work_orders 1─* svc_work_order_items
svc_work_orders *─1 ms_trucks ; *─1 ms_mechanics
svc_work_orders 1─* svc_wo_tasks 1─* svc_task_time_logs *─1 ms_mechanics
svc_wo_tasks 1─* svc_wo_task_steps (WO Plan: langkah ber-seq; source planned/adhoc)
svc_wo_tasks 1─* svc_task_assignments *─1 ms_mechanics ; svc_wo_tasks *─1 svc_services (opsional) ; svc_wo_tasks *─1 svc_work_order_items (opsional, biaya jasa)
svc_services 1─* svc_service_steps (template langkah → disalin ke wo_task_steps; opsional)
tyre_products 1─* tyre_tyres 1─* tyre_installations *─1 ms_trucks
tyre_tyres 1─* tyre_inspections ; tyre_tyres 1─* tyre_retreads ; tyre_tyres 1─* tyre_movements
tyre_tyres *─1 ms_locations (rak/bin saat in_stock) ; tyre_movements *─1 inv_shift_sessions (sesi terpadu)
ms_truck_types.axle_config ─* ms_axle_positions ◀ validasi tyre_installations.position
tyre_opnames 1─* tyre_opname_items *─1 tyre_tyres ; tyre_disposals 1─* tyre_disposal_items *─1 tyre_tyres
tyre_tyres 1─* tyre_alerts ; tyre_retreads ─▶ tyre_movements (send/return) + book_value
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
| location_node | area, zone, rack, shelf, bin |
| location_purpose | storage, receiving, shipping, quarantine, scrap, staging |
| slotting_mode | dynamic, fixed, hybrid |
| category_type | part, tyre |
| mechanic_status | active, inactive |
| movement_type | in, out, transfer_in, transfer_out, adjustment, loan_out, loan_return, return_supplier, return_wo |
| adjustment_reason | found, loss, damage, correction |
| loan_status | open, partially_returned, returned, cancelled |
| purchase_return_status | draft, posted, credited, cancelled |
| issue_return_status | draft, posted, cancelled |
| audit_type | cycle_count, spot_check, full_count, compliance, investigation |
| audit_status | planned, in_progress, review, closed, cancelled |
| finding_type | stock_discrepancy, negative_stock, sop_violation, missing_document, aging_slow_moving, valuation_issue, theft_suspected, other |
| finding_severity | low, medium, high, critical |
| finding_status | open, in_progress, resolved, verified, closed, rejected |
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
| shift_session_status | open, closed, force_closed |
| notification_channel | database, email, whatsapp |
| notification_status | pending, sent, failed |
| tax_type | exclusive, inclusive, non_pkp |
| price_item_type | spare_part, tyre_product |
| price_source | manual, bulk, import |
| po_status | draft, approved, partial, received, closed, cancelled |
| ap_kontrabon_status | draft, checking, verified, approved, partially_paid, paid, rejected, cancelled |
| ap_invoice_check_status | pending, ok, problem |
| ap_account_type | cash, bank |
| ap_payment_method | transfer, giro, digital, cash |
| ap_payment_request_status | draft, submitted, approved, rejected, paid, cancelled |
| ap_payment_status | draft, posted, cleared, cancelled |
| ap_giro_status | registered, printed, signed, verified, released, cleared, bounced, cancelled |
| pmb_status | issued, used, cancelled |
| lkm_intake_mode | direct, dispatcher_permit |
| lkm_status | entered, in_progress, done, exited |
| inspect_condition | good, warning, bad |
| tyre_condition | new, used, retread |
| tyre_status | in_stock, installed, removed, retreading, afkir, scrapped |
| tyre_movement_type | receipt, install, removal, transfer, retread_send, retread_return, condemn, scrap, adjustment |
| tyre_stage | new, used, afkir, mixed  *(klasifikasi gudang ban, di `wks_ms_warehouses`)* |
| axle_application | steer, drive, trailer, all |
| axle_side | left, right |
| tube_type | tubeless, tubetype |
| removal_reason | rotation, worn, damage, retread, swap |
| opname_result | match, missing, extra, misplaced |
| tyre_alert_type | tread_low, inspection_due, retread_overdue, dot_aged, low_stock |
| retread_result | ok, failed |
| wo_status | queued, waiting_part, in_progress, qc, done, delivered |
| wo_item_type | service, spare_part, tyre |
| task_status | pending, assigned, in_progress, paused, done, cancelled |
| task_priority | low, normal, high |
| task_role | lead, helper |
| step_status | pending, done, skipped |
| step_source | planned, adhoc |
| pause_reason | wait_part, break, qc, shift_end, other |
| log_source | handheld, web |
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
- **Jam kerja mekanik (clock in/out):** `TaskTimeService` membuka segmen `wks_svc_task_time_logs`
  (`started_at`, `ended_at=null`) saat **Mulai/Lanjut** dan menutupnya (`ended_at`, `duration_minutes`)
  saat **Jeda/Selesai**, dalam `DB::transaction()` dengan guard **satu segmen aktif per mekanik**
  (partial unique). Tiap penutupan me-recompute cache `wo_tasks.actual_minutes`. Timestamp **selalu
  dari server** (anti-manipulasi); `client_event_id` (uuid) menjaga **idempotensi** saat sync offline
  (PWA) sehingga event berulang tidak ganda. Status task & WO mengikuti state machine (lihat WORKFLOWS §6/§6b).
  Movement & saldo selalu base. Validasi: `factor > 0`.
- **Kebijakan stok negatif: IZINKAN + ALERT** (bukan blokir). `StockService` tetap memproses
  `out` walau `qty_on_hand` jadi < 0, lalu buat `wks_inv_stock_alerts` (`negative_stock`) +
  notifikasi in-app ke Gudang/Admin. Juga alert saat saldo turun di bawah `min_qty`/`reorder_point`.
- **WAC saat saldo ≤ 0:** jangan bagi dengan qty ≤ 0 — **bekukan `avg_cost`** (pakai nilai
  terakhir) selama `qty_on_hand <= 0`; saat stok masuk lagi & qty positif, WAC dihitung ulang
  normal. `out` saat negatif memakai `avg_cost` beku sebagai HPP.
- **Sesi Kerja Gudang (1 siklus/hari per gudang):** **Buka Sesi = gate masuk panel Gudang**
  (operator pertama membuka untuk warehouse+`business_date`; panel terblok sampai dibuka).
  `unique(warehouse_id, business_date)` menjamin 1 sesi/hari; `StockService` tetap menolak
  movement tanpa sesi `open` & men-tag `shift_session_id` (jaring kedua). **Tutup Sesi = akhir
  hari, hanya supervisor/Kepala Gudang** → ringkas movement ter-tag + snapshot **seluruh** saldo
  gudang (opening vs closing) → update snapshot §7c. `diff_qty≠0` di `shift_session_balances` =
  perubahan tak ter-tag (anomali). Satu sesi `open` per **gudang** (partial unique) +
  `unique(warehouse_id, business_date)` (1 siklus/hari). Sesi menggantung lintas hari /
  lupa tutup → `force_closed` (supervisor/job akhir hari).
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
- **Hutang Supplier / AP (`wks_ap_`):** **2 level** — `wks_ap_kontrabons` (tanda terima tagihan,
  per supplier, **unit hutang** dgn `due_date`) + `wks_ap_kontrabon_invoices` (**1..n** faktur
  supplier, **dicek satu per satu**). **`ApService`** dalam `DB::transaction()`: (1) **Review
  baris** — 4 checklist (`chk_goods_received`/`chk_delivery_note`/`chk_tax_invoice`/`chk_po_match`)
  → `check_status=ok` bila keempat true; header **`verified`** hanya bila **semua baris `ok`**
  (gate). (2) **Pengakuan hutang** saat kontrabon `approved` (SoD: `verified_by` ≠ `approved_by`).
  (3) `outstanding` = kolom **GENERATED** (`total − credit_amount − paid_amount`) → sumber kebenaran
  sisa hutang. (4) **Pembayaran via Kasir, 2 tahap:** **(a)** `wks_ap_payment_requests` (**Request
  Pembayaran, maker→checker**: `requested_by` ≠ `approved_by`) pilih kontrabon `approved` + metode +
  **rekening** `wks_ap_bank_accounts` (master dikelola Kasir); **(b)** `wks_ap_payments` (**realisasi**
  giro/**digital**) atas request `approved` → saat `posted` naikkan `kontrabons.paid_amount` per item
  request + set `partially_paid`/`paid`; guard `amount ≤ outstanding` per kontrabon (cegah over-pay),
  idempoten. Metode `transfer`/`giro`/`digital`(digital banking maker-checker)/`cash`.
  **(4b) Giro punya Register sendiri** (`wks_ap_giros`): **register di aplikasi → print → tanda
  tangan giro fisik → verifikasi (fisik harus sesuai sistem, dicek lewat print) → diserahkan
  → cair**. Payment method=giro **baru `posted` saat giro `released`** (diserahkan), `cleared` saat
  giro cair, `cancelled` bila `bounced`. **SoD giro:** registrar (Kasir) ≠ penanda tangan ≠ pemeriksa.
  (5) **Nota retur** ke supplier
  (`wks_inv_purchase_returns.credit_amount`) menaikkan `credit_amount` kontrabon terkait. (6) **Aging**
  = group `outstanding` per supplier × bucket umur dari `due_date`. Anti dobel-input faktur:
  `unique(company_id, supplier_id, supplier_invoice_no)` di baris. Penomoran: `doc_type` `kontrabon`,
  `ap_pay_req`, `ap_payment` & `giro` (no. giro internal bila perlu) di `wks_adm_document_sequences`.
- Belum final (lihat `MODULES.md` §14): metode valuasi (WAC vs FIFO), multi-currency,
  pajak selain PPN, status feature-flag plan/langganan.
