---
name: postgres-design
description: Panduan desain skema & migration PostgreSQL untuk ControlHub Workshop (Laravel 13 + PG17). Gunakan saat membuat/menelaah migration, memilih tipe kolom, index, constraint, locking, atau menangani integritas stok/keuangan & concurrency. Pelengkap workshop-feature (alur fitur) & NAMING_CONVENTIONS (penamaan).
---

# PostgreSQL Design — ControlHub Workshop

Fokus: **skema, migration, index, constraint, locking** di PostgreSQL 17 + Laravel 13.
Penamaan → `workshop-feature/NAMING_CONVENTIONS.md`. Skema lengkap → `docs/DATABASE.md`.
Alur bangun fitur → `workshop-feature/SKILL.md`. JANGAN duplikasi isi file itu.

## Tipe Kolom (ringkas)
- PK `bigIncrements`; FK `<singular>_id` `bigInteger`.
- Uang/biaya `decimal(15,2)`; qty `decimal(15,3)`; persen `decimal(5,2)`. **Jangan float**.
- Waktu `timestampTz()`; tanggal `date()`.
- Enum = `string` + **PHP Enum cast** (hindari tipe ENUM native PG — sulit di-migrate).
  Boleh tambah `check` constraint bila mau ketat.
- Data fleksibel = `jsonb` (bukan `json`) agar bisa di-index.

## Multi-Tenant (wajib)
- Tiap tabel tenant: `company_id` `NOT NULL` + FK `wks_core_companies`, ber-index.
- **Index selalu diawali `company_id`** untuk query ter-scope:
  `index(['company_id','status'])`, `index(['company_id','spare_part_id','moved_at'])`.
- Unique **per company**: `unique(['company_id','<doc_no>'])`.

## Constraint & Integritas
- **Soft delete + unique** → pakai *partial unique index* agar baris terhapus tak bentrok:
  ```php
  DB::statement('CREATE UNIQUE INDEX wks_ms_customers_company_code_uq
                 ON wks_ms_customers (company_id, code) WHERE deleted_at IS NULL');
  ```
- **FK on delete**: master = `restrict` (cegah hapus saat dipakai) + softDeletes;
  baris anak/detail = `cascade` ke induk.
- Kolom boolean default eksplisit; kolom uang/qty default `0`.
- Pertimbangkan `check` untuk nilai non-negatif stok bila perlu.

## Index Strategy
- FK & kolom filter sering (`status`, `warehouse_id`, `condition`) → index.
- Pencarian SKU/cross-ref: index `wks_inv_part_numbers(part_no)` (resolve nomor→SKU);
  pertimbangkan index fungsional `lower(part_no)` bila pencarian case-insensitive.
- `jsonb` yang dicari → **GIN index** (`USING gin (attributes)`).
- Tabel besar (`*_stock_movements`, `*_audit_logs`): index `(company_id, <entity>_id, moved_at)`;
  pertimbangkan **partisi per tanggal** + arsip untuk audit.
- Hindari over-index; ukur dengan `EXPLAIN (ANALYZE, BUFFERS)`.

## Concurrency & Locking (kritis — lihat RISK R7, R25)
- **Mutasi stok** (`StockService`) dalam `DB::transaction()` + **kunci baris saldo**:
  ```php
  $item = StockItem::where(...)->lockForUpdate()->first(); // SELECT ... FOR UPDATE
  // hitung qty & WAC baru, simpan movement, update item
  ```
- **Penomoran dokumen** (`wks_adm_document_sequences`): increment atomik dengan
  `lockForUpdate()` pada baris sequence di dalam transaksi (cegah nomor dobel).
- Transaksi **pendek**; ambil lock se-akhir mungkin; urutan lock konsisten (hindari deadlock).
- Default isolation `READ COMMITTED` cukup; pakai lock eksplisit untuk invarian stok.

## WAC (Average Cost)
- Saat stok masuk: `avg_cost_baru = (qty_lama*avg_lama + qty_masuk*cost_masuk)/(qty_lama+qty_masuk)`.
- Hitung **di dalam** transaksi terkunci; jangan dari nilai yang sudah basi.

## Migration (Laravel 13)
- Satu migration per tabel/perubahan; nama tabel `wks_<modul>_...` (lihat NAMING).
- Set `protected $table` & trait `BelongsToCompany` di model, bukan di migration.
- Index/unique kompleks (partial, GIN, functional) → `DB::statement()` dalam migration
  (Schema builder belum cakup semua).
- Wajib lolos `php artisan migrate:fresh` di PostgreSQL bersih.
- Seeder default (company, roles, UoM, kategori) terpisah & idempoten.

## Verifikasi (lingkungan ini)
- PHP 8.4 di `C:\Users\user\php84` (prepend ke PATH agar `php`/`composer` pakai 8.4).
- `psql` belum terpasang → tes DB pakai **PDO pgsql** (`new PDO('pgsql:...')`),
  password via env var, jangan tulis ke file.
- DB: PostgreSQL 17.10, `controlhub_workshop`, UTF8.

## Definition of Done (DB)
- [ ] `company_id` + index berawalan company_id pada tabel tenant.
- [ ] Unique per-company (partial index bila softDeletes).
- [ ] FK on-delete benar (restrict/cascade); default kolom uang/qty.
- [ ] Mutasi stok/uang: transaksi + `lockForUpdate`.
- [ ] jsonb yang dicari ber-GIN; tabel besar ber-index waktu.
- [ ] `migrate:fresh` lolos di PostgreSQL.
