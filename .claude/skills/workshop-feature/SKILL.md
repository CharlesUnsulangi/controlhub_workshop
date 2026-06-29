---
name: workshop-feature
description: Build or modify a feature/module in the ControlHub Workshop app (Laravel + PostgreSQL bengkel-truk + gudang sparepart). Use when adding/editing entities, migrations, models, controllers, API endpoints, policies, or business logic for this project. Triggers on requests like "buat modul X", "tambah fitur", "buat CRUD", "tambah endpoint", "bikin migration/model untuk ...".
---

# ControlHub Workshop — Laravel Feature Builder

Skill untuk membangun fitur di **ControlHub Workshop**: aplikasi manajemen
**bengkel truk + gudang sparepart**. Lihat `docs/OVERVIEW.md` untuk konteks bisnis
(dokumentasi lengkap ada di folder `docs/`).

## Stack & Aturan Wajib

- **Laravel 13** · **PHP 8.4** · **PostgreSQL** (driver `pgsql`) · **Filament v5** (admin panel).
- Eloquent ORM. **Selalu** lewat migration + model, jangan SQL manual.
- **UI back-office = Filament** (Resources/Forms/Tables). Bangun modul lewat Filament
  **Resource** per entitas; logika berat tetap di Service.
- **RBAC** via Filament Shield (peran: Owner, Admin, ServiceAdvisor, KepalaMekanik,
  Mekanik, Gudang, Purchasing, Kasir). Tetap pakai Policy untuk otorisasi.
- API publik (bila perlu, mis. integrasi): JSON via **API Resources** (`app/Http/Resources`).
- Validasi: Filament form rules / **Form Request** untuk endpoint non-Filament.
- **Multi-tenant:** aplikasi multi-company. Setiap tabel tenant punya `company_id`
  + global scope auto-filter (trait `BelongsToCompany`). **Company = tenant Filament**
  (panel tenancy). Jangan query lintas tenant kecuali di modul Core (panel `/system`
  terpisah). Lihat `docs/MODULES.md`.
- Logika bisnis non-trivial → **Service class** (`app/Services`), Resource/controller tipis.
- Uang & stok = transaksi sensitif → bungkus dalam `DB::transaction()`.
- **Penamaan: ikut `NAMING_CONVENTIONS.md`** (di folder skill ini). Inti:
  - Semua tabel domain **wajib prefix `wks_`** + `snake_case` jamak
    (mis. `wks_work_orders`). Tabel bawaan Laravel (`users`, `roles`, dll.) tanpa prefix.
  - Model `StudlyCase` tunggal **tanpa prefix**, dipetakan via
    `protected $table = 'wks_...';`.
  - Kolom `snake_case` tanpa prefix; FK `<singular>_id`.
- Bahasa data domain boleh Indonesia, tapi **kode/identifier pakai Inggris**
  (mis. tabel `wks_work_orders`, bukan `wks_perintah_kerja`).

## Konvensi PostgreSQL

> Untuk desain DB mendalam (index, locking, partial unique, GIN, WAC, concurrency)
> gunakan skill **`postgres-design`**. Ringkas di bawah:

- Primary key: `bigIncrements` (atau UUID bila diminta).
- Uang: `decimal(15,2)`. Jangan float untuk uang/stok.
- Enum status: gunakan kolom `string` + **PHP Enum** (cast di model), atau
  `check` constraint — hindari tipe ENUM native Postgres (susah di-migrate).
- Timestamp: `timestampsTz()` agar timezone-aware.
- Soft delete (`softDeletes()`) untuk master data (Customer, Truck, SparePart, dll.).
- Index foreign key & kolom yang sering difilter (status, warehouse_id, dll.).
- JSON fleksibel → kolom `jsonb` (bukan `json`) agar bisa di-index.

## Entitas Inti (lihat docs/MODULES.md & docs/DATABASE.md)

`Customer` · `Truck` · `PMSchedule` · `WorkOrder` · `WorkOrderItem` · `Service` ·
`SparePart` · `Warehouse` · `StockItem` · `StockMovement` · `Supplier` ·
`PurchaseOrder` · `POItem` · `GoodsReceipt` · `Invoice` · `Payment` · `Mechanic` ·
`User` · `Role`.

**Aturan stok (penting):** stok HANYA berubah lewat baris `StockMovement`
(masuk/keluar/transfer/adjustment). `StockItem.qty` adalah hasil agregat —
jangan update langsung tanpa mencatat movement.

## Langkah Membuat Fitur Baru

1. **Konfirmasi scope** singkat: entitas, field, relasi, peran yang boleh akses.
2. **Migration** — `php artisan make:migration`. Nama tabel **wajib `wks_<modul>_`**
   (lihat `NAMING_CONVENTIONS.md`). Tentukan kolom, FK, index sesuai konvensi Postgres.
3. **Model** — set `protected $table = 'wks_...';`, relasi Eloquent,
   `$fillable`/`$casts`, enum casts, `softDeletes` bila master, trait `BelongsToCompany`.
4. **Service** (jika perlu) — logika bisnis + `DB::transaction()` untuk stok/uang.
5. **Filament Resource** — `php artisan make:filament-resource` di panel yang sesuai
   (tenant `App`, atau `System` untuk Core). Form schema + table + relation managers.
6. **Policy / Shield** — petakan aksi ke peran RBAC; generate permission via Shield.
7. **(Opsional) API Resource + Form Request + route** — hanya bila ada endpoint non-Filament.
8. **Seeder/Factory** — data contoh untuk dev & test.
9. **Test** — minimal feature test happy-path + otorisasi + **isolasi tenant** (Pest).

## Perintah yang Berguna

```bash
php artisan make:model Truck -mf         # model + migration + factory
# NB: setelah generate, ganti nama tabel di migration jadi wks_ms_trucks
#     dan set protected $table = 'wks_ms_trucks'; di model.
php artisan make:filament-resource Truck --generate   # Filament CRUD (form+table)
php artisan make:policy TruckPolicy --model=Truck
php artisan shield:generate --all        # permission RBAC (Filament Shield)
php artisan migrate                       # cek koneksi pgsql di .env dulu
php artisan test                          # jalankan test
```

## Definition of Done

- [ ] Tabel pakai prefix `wks_<modul>_` + model di-map via `$table` + `BelongsToCompany`.
- [ ] Migration jalan bersih di PostgreSQL (`migrate:fresh` lolos).
- [ ] Filament Resource di panel yang benar + otorisasi (Policy/Shield) terpasang.
- [ ] Logika stok/uang dalam transaksi DB, stok via `StockService` (movement).
- [ ] Ada test happy-path + otorisasi + **isolasi tenant**.
- [ ] Identifier kode dalam bahasa Inggris, ikut konvensi penamaan.

## Catatan

- Bila proyek **belum di-scaffold** (folder kosong): `laravel new` (Laravel 13, PHP 8.4),
  set `.env` ke `DB_CONNECTION=pgsql`, install **Filament v5** + panel `App` & `System`
  + Filament Shield, baru bangun fitur.
- Selalu cek dokumen di `docs/` (OVERVIEW, MODULES, DATABASE, WORKFLOWS, RISK_GAP_ANALYSIS)
  agar fitur konsisten dengan modul, skema, alur proses, & mitigasi risiko.
