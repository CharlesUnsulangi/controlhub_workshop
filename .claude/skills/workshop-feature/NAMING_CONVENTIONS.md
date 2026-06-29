# Konvensi Penamaan — ControlHub Workshop

Prefix proyek: **`wks`** (singkatan dari *workshops*).

Tujuan: semua objek database mudah dikenali milik aplikasi ini, tidak bentrok bila
satu database dipakai bersama aplikasi lain, dan konsisten antar developer.

---

## 1. Tabel Database — WAJIB prefix `wks_<modul>_`

- Format: **`wks_<modul>_<entitas_jamak_snake_case>`** — huruf kecil, jamak, `snake_case`, Inggris.
- **Sub-prefix per modul** (lihat `docs/MODULES.md`):

| Sub-prefix | Modul | Contoh tabel |
|---|---|---|
| `wks_core_` | Core / system admin (TANPA `company_id`) | `wks_core_companies`, `wks_core_audit_logs` |
| `wks_adm_` | Admin: user/akses/setting | `wks_adm_roles`, `wks_adm_document_sequences` |
| `wks_ms_` | Master data referensi | `wks_ms_customers`, `wks_ms_trucks`, `wks_ms_warehouses` |
| `wks_lkm_` | Laporan Kendaraan Masuk | `wks_lkm_pmb`, `wks_lkm_entries`, `wks_lkm_inspections` |
| `wks_po_` | Purchasing Order | `wks_po_orders`, `wks_po_goods_receipts` |
| `wks_inv_` | Gudang Sparepart (Inventory) | `wks_inv_spare_parts`, `wks_inv_stock_movements` |
| `wks_tyre_` | Gudang Ban | `wks_tyre_tyres`, `wks_tyre_installations` |
| `wks_svc_` | Servis / Work Order | `wks_svc_work_orders`, `wks_svc_invoices` |
| `wks_price_` | Price List Supplier (harga beli part & ban) | `wks_price_lists`, `wks_price_list_items`, `wks_price_histories` |
| `wks_ap_` | Hutang Supplier / Accounts Payable (Kontrabon + Kasir) | `wks_ap_kontrabons`, `wks_ap_kontrabon_invoices`, `wks_ap_bank_accounts`, `wks_ap_payment_requests`, `wks_ap_payments`, `wks_ap_giros` |

### Tabel pivot (many-to-many)
- Format: `wks_<modul>_<singular_a>_<singular_b>` dua entitas **urut abjad**.
- Contoh: `wks_adm_permission_role`, `wks_ms_spare_part_truck_type`.

### Tabel sistem Laravel
- Tabel bawaan Laravel (`users`, `migrations`, `jobs`, `cache`, dll.)
  **TIDAK** diberi prefix — biarkan default agar paket pihak ketiga tetap jalan.
  Catatan: `users` ditambah kolom `company_id` (nullable; null = super admin Core).
- Tabel domain milik aplikasi → **selalu** `wks_<modul>_`.

---

## 2. Kolom

- `snake_case`, bahasa Inggris, **tanpa** prefix `wks_`.
- Primary key: `id` (`bigIncrements`).
- **Multi-tenant:** setiap tabel milik tenant **wajib** kolom `company_id`
  (FK ke `wks_core_companies`), `not null`, ber-index. Tabel Core/sistem tidak.
  Transaksi operasional juga membawa `branch_id` (FK `wks_ms_branches`).
  Unique constraint di-scope per company: `unique(company_id, <doc_no>)`.
  Hierarki: company → branch → warehouse. Detail lihat `docs/MODULES.md` §1 & §11.
- Foreign key: `<singular>_id` → `customer_id`, `warehouse_id`, `work_order_id`.
- Boolean: awali `is_` / `has_` → `is_active`, `has_warranty`.
- Tanggal/waktu: akhiri `_at` (timestamp) atau `_date` (tanggal) → `received_at`, `due_date`.
- Uang: `decimal(15,2)`, akhiri makna jelas → `unit_price`, `total_amount`, `paid_amount`.
- Jumlah/stok: `qty`, `qty_on_hand`, `qty_reserved`.
- Enum status: kolom `status` (string) + PHP Enum cast.

---

## 3. Kelas PHP / Laravel (TANPA prefix `wks`)

Kode PHP tetap bersih — prefix hanya di lapisan database. Model dipetakan ke tabel
ber-prefix lewat properti `$table`.

| Artefak | Konvensi | Contoh |
|---|---|---|
| Model | StudlyCase, tunggal (boleh namespace modul: `App\Models\Svc\WorkOrder`) | `WorkOrder`, `SparePart` |
| → properti tabel | — | `protected $table = 'wks_svc_work_orders';` |
| Controller | `<Model>Controller` | `WorkOrderController` |
| Form Request | `Store/Update<Model>Request` | `StoreTruckRequest` |
| API Resource | `<Model>Resource` | `TruckResource` |
| Policy | `<Model>Policy` | `TruckPolicy` |
| Service | `<Domain>Service` | `StockService`, `InvoiceService` |
| Enum | StudlyCase | `WorkOrderStatus`, `MovementType` |
| Factory | `<Model>Factory` | `TruckFactory` |
| Seeder | `<Model>Seeder` | `TruckSeeder` |

> Alternatif (opsional): set `'prefix' => 'wks_'` di `config/database.php` sehingga
> prefix otomatis. **Tidak dipakai secara default** karena membuat nama tabel di
> migration tampak tanpa prefix dan menyulitkan query mentah. Default proyek:
> tulis prefix `wks_` secara eksplisit di migration & `$table`.

---

## 4. Migration & Index

- File migration: format Laravel default (`create_wks_trucks_table`).
- Nama index eksplisit pakai prefix tabel:
  - Index: `wks_<tabel>_<kolom>_index` → `wks_trucks_status_index`
  - Foreign key: `wks_<tabel>_<kolom>_foreign` (default Laravel sudah memakai nama tabel).
  - Unique: `wks_<tabel>_<kolom>_unique`.

---

## 5. Lain-lain

- **Route name**: `wks.<resource>.<action>` → `wks.trucks.index` (kelompokkan dgn `Route::name('wks.')`).
- **Permission/ability** (RBAC): `<resource>.<action>` → `truck.create`, `stock.adjust`.
- **Config kustom**: file `config/workshop.php`, akses `config('workshop.xxx')`.
- **ENV kustom**: prefix `WKS_` → `WKS_INVOICE_PREFIX`, `WKS_DEFAULT_WAREHOUSE_ID`.
- **Nomor dokumen** (data, bukan skema): boleh prefix bisnis → `WO-2026-0001`,
  `INV-2026-0001`, `PO-2026-0001`.
- **SKU sparepart** (`wks_inv_spare_parts.sku`): **kode internal kanonik** (mis. `SP-000123`),
  lintas merek. Nomor pabrikan **Hino/Isuzu** & brand aftermarket = cross-reference di
  `wks_inv_part_numbers` (banyak per SKU). Standar Hino = nomor OEM utama untuk part Hino.
  Detail di `docs/DATABASE.md`.

---

*Ringkas: prefix `wks_` hanya untuk **tabel database** (dan turunannya: index, route
name, env). **Kode PHP tetap bersih** tanpa prefix.*
