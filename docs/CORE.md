# ControlHub Workshop — Desain Modul CORE (`wks_core_`) + Panel System

> Spesifikasi build **Modul 0 — Core** (System Admin). Dokumen ini **mengonsolidasikan**
> & memfinalkan apa yang tersebar di `DATABASE.md §1`, `MODULES.md §3`, dan `PANELS.md §2`,
> sekaligus **merekonsiliasi** kode yang sudah ada di repo dengan spesifikasi.
> Status: **DESAIN (belum dibangun)** — implementasi menyusul setelah dokumen ini disetujui.
>
> Terakhir diperbarui: **2026-06-29**.

---

## 0. Posisi & Prinsip

**ControlHub Workshop = aplikasi standalone, multi-tenant.** Ada **dua tingkat admin** yang
**TIDAK boleh dikaburkan**:

| Tingkat | Siapa | Mengelola | Panel | Tabel |
|---|---|---|---|---|
| **System / Core** | **Penyedia aplikasi** (operator sistem) | **Tenant** (companies): provisioning, suspend, modul, audit lintas tenant | **`/system`** (`SystemPanelProvider`) — **tanpa tenant** | `wks_core_` |
| **Admin tenant** | **Admin perusahaan** (Owner/Admin company) | Data & user **di dalam** satu company | **`/admin`** (`AdminPanelProvider`) — ber-tenant Company | `wks_adm_` + `wks_ms_` |

Aturan kunci Core:
- Tabel Core **TANPA `company_id`** — ia *akar* tenant, berdiri di atas semua company.
- Panel System **tidak** memakai global scope tenant (`canAccessPanel` hanya untuk super admin,
  `users.company_id = null`). Tak ada `->tenant(Company)`.
- Core adalah **system of record** untuk: daftar tenant, status langganan modul (feature-flag),
  dan **jejak audit** lintas tenant.

---

## 1. Lingkup yang Disepakati (sesi 2026-06-29)

**Termasuk sekarang** (4 tabel):

| Tabel | Fungsi |
|---|---|
| `wks_core_companies` | Master tenant (**rekonsiliasi** dengan migration yang sudah ada — §3.1) |
| `wks_core_modules` | Katalog modul aplikasi (lkm, inv, tyre, po, svc, price, ap, …) |
| `wks_core_company_modules` | **Feature-flag** modul per company (on/off + setting) |
| `wks_core_audit_logs` | Jejak audit lintas tenant (append-only) |

**Ditunda (billing — di luar lingkup sekarang):** `wks_core_plans`, `wks_core_subscriptions`.
Dirancang di `DATABASE.md §1` sebagai *opsional*; **tidak** dibuat tabel/Resource-nya pada
fase ini. Bila langganan berbayar diaktifkan kelak, `company_modules` cukup ditautkan ke plan.

---

## 2. Status Kode Saat Ini (yang sudah ada)

Sudah di-scaffold (lihat `IMPLEMENTATION.md §2`):

- ✅ Migration `wks_core_companies` (**belum lengkap** vs spec — §3.1), `wks_ms_branches`,
  kolom tenant di `users` (`company_id` null = super admin, `default_branch_id`, `is_active`).
- ✅ Model `Company`, `Branch`, `User` (`User::isSuperAdmin()` = `company_id` null).
- ✅ Fondasi tenancy: `Tenancy`, `IdentifyTenant`, `CompanyScope`/`BranchScope`,
  `BelongsToCompany`/`BelongsToBranch`.

**Belum ada (kerja modul Core ini):**

- ❌ Migration + model: `wks_core_modules`, `wks_core_company_modules`, `wks_core_audit_logs`.
- ❌ **`SystemPanelProvider`** (panel `/system`) — di `PANELS.md §2` statusnya tertulis "✅"
  tapi **provider belum ada** di `bootstrap/providers.php` (hanya `admin`, `audit`,
  `kontrabon`, `kasir`). **Ini gap yang harus ditutup.**
- ❌ Resource System: Company, Module, CompanyModule, AuditLog, SystemUser.
- ❌ Seeder Core (super admin + company demo + branch + modules).
- ❌ Penulis audit log (observer/service) + integrasi feature-flag ke panel lain.

---

## 3. Skema Tabel (final untuk lingkup ini)

Konvensi mengikuti `DATABASE.md` Legenda: PK `bigint`; `timestamptz`; enum = `varchar`
+ PHP Enum cast; `jsonb` untuk fleksibel; FK `<singular>_id`.

### 3.1 `wks_core_companies` — REKONSILIASI

Migration **yang ada sekarang** menyimpang dari `DATABASE.md §1`. Tabel beda:

| Kolom | Migration sekarang | Spec DATABASE.md | Tindakan |
|---|---|---|---|
| name | `string` (255) | `varchar(150)` | Batasi ke 150 |
| code | `varchar(30)` unique | `varchar(30)` unique | OK |
| npwp | `varchar(30)` | `varchar(30)` | OK |
| phone | `varchar(30)` | `varchar(30)` | OK |
| address | `text` | `text` | OK |
| **email** | — (tidak ada) | `varchar(150)` | **TAMBAH** |
| timezone | `varchar(64)` def `Asia/Makassar` | `varchar(40)` def `Asia/Jakarta` | **Putuskan default — §8 (D1)** |
| logo_path | `varchar(255)` | `varchar(255)` | OK |
| status | `varchar(15)` def `active` | `varchar(20)` enum `company_status` | Lebarkan ke 20 + enum cast |
| **settings** | — (tidak ada) | `jsonb` | **TAMBAH** |
| **deleted_at** | — (tidak ada) | soft delete | **TAMBAH** (`softDeletes`) |
| timestamps | ✅ | ✅ | OK |

**Cara menutup:** karena migrasi **belum pernah jalan** (blocker GRANT DDL, `IMPLEMENTATION.md §3`),
**edit langsung migration `..._create_wks_core_companies_table.php`** (bukan migration baru) agar
skema bersih sejak awal. Setelah disetujui.

Definisi final `wks_core_companies`:

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| name | varchar(150) | no | Nama tenant |
| code | varchar(30) | no | **unique**, kode tenant |
| npwp | varchar(30) | yes | |
| address | text | yes | |
| phone | varchar(30) | yes | |
| email | varchar(150) | yes | |
| timezone | varchar(40) | no | default lihat **D1** |
| logo_path | varchar(255) | yes | |
| status | varchar(20) | no | enum `company_status` (active/suspended); default `active` |
| settings | jsonb | yes | pengaturan global tenant (cast array) |
| timestamps, deleted_at | | | soft delete |

### 3.2 `wks_core_modules` — katalog modul

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| code | varchar(30) | no | **unique** — `pmb`,`lkm`,`po`,`inv`,`tyre`,`svc`,`price`,`ap`,… |
| name | varchar(80) | no | Nama tampil |
| description | varchar(255) | yes | |
| sort_order | smallint | no | default 0 — urutan tampil |
| is_active | bool | no | default true — modul tersedia di aplikasi (master switch) |
| timestamps | | | |

> `is_active` = modul dimatikan untuk **seluruh** instalasi (mis. belum dirilis). Mematikan
> untuk **satu** company = lewat `wks_core_company_modules` (§3.3).

### 3.3 `wks_core_company_modules` — feature-flag per tenant

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | no | FK `wks_core_companies` (cascade) |
| module_id | bigint | no | FK `wks_core_modules` |
| is_enabled | bool | no | default true |
| settings | jsonb | yes | konfigurasi modul per company |
| timestamps | | | |
| | | | **unique(company_id, module_id)** |

Index: `index(company_id)`. Cache resolusi modul aktif per company (§6).

### 3.4 `wks_core_audit_logs` — jejak audit lintas tenant (append-only)

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| company_id | bigint | yes | FK companies — **null = aksi sistem/Core** |
| user_id | bigint | yes | FK users (pelaku) |
| action | varchar(50) | no | created/updated/deleted/login/impersonate/suspend/… |
| auditable_type | varchar(100) | yes | model terkait (morph type) |
| auditable_id | bigint | yes | id model terkait |
| old_values | jsonb | yes | nilai sebelum (untuk update/delete) |
| new_values | jsonb | yes | nilai sesudah |
| ip_address | varchar(45) | yes | IPv4/IPv6 |
| created_at | timestamptz | no | **hanya created_at** (tak ada updated_at — immutable) |

Index: `index(company_id, created_at)`, `index(action)`, `index([auditable_type, auditable_id])`.

> **Tabel ini juga dipakai modul lain** sebagai bagian *audit trail* gudang
> (`MODULES.md §8 ②`, `PANELS.md §5b`). Jadi penulisannya harus **generik** (§5).

---

## 4. Model (rencana)

Namespace Core: `App\Models` (model induk tenant) — konsisten dengan `Company`/`Branch`
yang sudah ada di `App\Models`. (Domain per-tenant lain pakai namespace per modul,
mis. `App\Models\Inv\…` — `NAMING_CONVENTIONS §3`.)

| Model | `$table` | Trait/Catatan |
|---|---|---|
| `Company` (ada) | `wks_core_companies` | **Tambah:** `SoftDeletes`, `$casts['settings'=>'array', 'status'=>CompanyStatus::class]`; relasi `companyModules()`, `modules()` (belongsToMany via pivot) |
| `Module` | `wks_core_modules` | `$casts['is_active'=>'bool']`; relasi `companies()` |
| `CompanyModule` | `wks_core_company_modules` | pivot kaya; `$casts['is_enabled'=>'bool','settings'=>'array']` |
| `AuditLog` | `wks_core_audit_logs` | append-only; `$casts['old_values'=>'array','new_values'=>'array']`; `public $timestamps = false` + isi `created_at` manual / pakai `CREATED_AT` saja |

PHP Enum baru: `App\Enums\CompanyStatus` (`active`,`suspended`) — sesuai `DATABASE.md §12`.

> **Penting:** model Core **TIDAK** memakai `BelongsToCompany` (tak ber-`company_id`).
> `AuditLog` punya `company_id` **nullable** tapi tetap **tanpa** global scope (Core melihat
> semua tenant); pemfilteran per company dilakukan eksplisit di Resource panel tenant bila perlu.

---

## 5. Penulisan Audit Log (strategi)

Agar konsisten dan tak tercecer:

- **`AuditLogger` service** (`app/Services/AuditLogger.php`) — satu pintu menulis
  `wks_core_audit_logs` (`log(action, model?, old?, new?)`), mengambil `user_id`/`company_id`/
  `ip` dari konteks (`Tenancy` + request).
- **Model observer opsional** (`Auditable` trait) untuk auto-catat `created/updated/deleted`
  pada model yang ditandai — diterapkan **selektif** (master & dokumen sensitif), bukan semua tabel.
- **Aksi eksplisit** (login, impersonate, suspend company, post dokumen stok/uang) memanggil
  `AuditLogger` langsung agar `action` bermakna.
- **Append-only:** tak ada update/delete row audit; Resource panel = **read-only**.

> Kaitan: panel Audit Gudang (`/audit`, `PANELS.md §5b`) membaca tabel ini + ledger
> `wks_inv_stock_movements` sebagai *audit trail*. Maka skema audit_logs **tidak** boleh
> bergantung pada modul Core saja.

---

## 6. Feature-flag — integrasi ke panel lain

`wks_core_company_modules` menjadi **gerbang modul per tenant** (`PANELS.md §1.6`):

- Helper `Tenancy::moduleEnabled(string $code): bool` (atau service `ModuleGate`) — resolusi
  `company_modules` untuk company aktif, **di-cache** per request/company.
- **Panel** menyembunyikan diri (`canAccessPanel` → false) bila modul utamanya off untuk company.
- **Resource/Action** future/dormant juga lewat flag ini bila relevan.
- Default saat provisioning: semua modul inti `is_enabled=true` kecuali yang sengaja off
  (mis. `pmb` hanya bila `lkm_intake_mode=dispatcher_permit`, `vendor` bila portal supplier aktif).

---

## 7. Panel SYSTEM (`/system`) — `SystemPanelProvider`

Refinement `PANELS.md §2`, **dipersempit ke lingkup sekarang** (tanpa Plan/Subscription).

- **Provider baru** `app/Providers/Filament/SystemPanelProvider.php`, daftarkan di
  `bootstrap/providers.php`. Path `/system`, id `system`, **tanpa** `->tenant()`.
- `canAccessPanel()` (sudah ada hook-nya di `User`): **true hanya bila** `isSuperAdmin()`
  (`company_id === null` + peran SuperAdmin/SystemSupport).
- Namespace Resource: `App\Filament\System\Resources\…`.
- **Tidak** memuat `IdentifyTenant` sebagai gerbang tenant (super admin lintas tenant);
  tetap auth + session standar.

| Grup navigasi | Resource / Page (Action) |
|---|---|
| **Tenant** | `CompanyResource` — provisioning (create→seed), **Suspend/Aktifkan** (audit), edit profil/timezone/settings |
| | RM **Modul per company** (`company_modules`: toggle `is_enabled` + settings) |
| | Action **Impersonate** company (ber-audit) — *(mekanisme: lihat D3)* |
| **Katalog Sistem** | `ModuleResource` — kelola katalog modul (`wks_core_modules`) |
| **Pengguna Sistem** | `SystemUserResource` — `users` **where `company_id` null** (super admin/support) |
| **Audit** | `AuditLogResource` — **read-only**, filter company/user/action/tanggal/entity |
| **Pengaturan** | Page **Global Settings** (status integrasi HRD/WhatsApp `enabled`) |

**Dashboard System:** jumlah tenant (aktif/suspend), tenant baru, audit terakhir,
status integrasi (HRD/WhatsApp).

> **Dibuang dari §2 PANELS untuk fase ini:** `PlanResource`, `Subscription` (billing ditunda).

---

## 8. Provisioning Tenant (alur Core)

Aksi **Create Company** di `CompanyResource` menjalankan `TenantProvisioner` service dalam
`DB::transaction()`:

```
1. Buat wks_core_companies (status=active, settings default)
2. Buat 1 wks_ms_branches default (is_default=true) — "Pusat"
3. Buat user Owner pertama (company_id terisi, default_branch_id = branch tadi)
   └─ kirim undangan / set password awal
4. Aktifkan modul inti di wks_core_company_modules (is_enabled per default §6)
5. (RBAC) seed peran dasar company via Shield untuk company itu
6. AuditLogger->log('company.provisioned', company)
```

Suspend = set `status=suspended` → `canAccessPanel` semua panel tenant company itu menolak
(kecuali super admin impersonate); dicatat audit.

---

## 9. RBAC

- Peran Core: **`SuperAdmin`**, **`SystemSupport`** (read + support, mis. tanpa hapus tenant).
- `users.company_id null` = identitas super admin; peran via Shield (atau flag awal sebelum
  Shield aktif). Gerbang panel = `canAccessPanel()` di `User` (sudah ada).
- Policy untuk `Company`/`Module`/`AuditLog`: tulis hanya SuperAdmin; `SystemSupport` read +
  aksi support terbatas (mis. impersonate ya, suspend/hapus tidak). `AuditLog` = read-only semua.

---

## 10. Definition of Done (modul Core)

- [ ] Migration `wks_core_companies` **direkonsiliasi** (email, settings, soft delete, enum, panjang) — D1 diputuskan.
- [ ] Migration + model: `Module`, `CompanyModule`, `AuditLog` (+ enum `CompanyStatus`).
- [ ] `Company` model dilengkapi (SoftDeletes, casts, relasi modul).
- [ ] `SystemPanelProvider` dibuat + terdaftar di `bootstrap/providers.php`; `canAccessPanel` = super admin saja.
- [ ] Resource System: Company (+RM modul, Impersonate, Suspend), Module, SystemUser, AuditLog (read-only).
- [ ] `AuditLogger` service + (opsional) `Auditable` trait; aksi Core mencatat audit.
- [ ] `ModuleGate`/`Tenancy::moduleEnabled()` + integrasi `canAccessPanel` panel lain (feature-flag).
- [ ] `TenantProvisioner` (company→branch→owner→modules→roles) dalam transaksi.
- [ ] Seeder Core: 1 super admin, modules katalog, 1 company demo + branch + owner.
- [ ] Test: provisioning happy-path, **isolasi** (super admin lihat semua; admin tenant tak lihat Core), audit tertulis, feature-flag menyembunyikan panel.

---

## 11. Keputusan Terbuka (perlu Anda tentukan)

- **D1 — Default timezone company.** Migration sekarang `Asia/Makassar` (selaras `php.ini`
  `IMPLEMENTATION.md §1`); spec `DATABASE.md` `Asia/Jakarta`. **Mana default-nya?**
  (Saran: `Asia/Makassar` agar konsisten lingkungan; per-company tetap bisa diubah.)
- **D2 — `SystemSupport` boleh impersonate?** Default desain: ya (support), tapi tak boleh
  suspend/hapus tenant. Setuju?
- **D3 — Mekanisme Impersonate.** Plugin pihak ketiga atau implementasi sendiri (login-as
  + banner + audit)? (`PANELS.md §15.5` masih terbuka.)
- **D4 — `Auditable` trait otomatis** diterapkan ke model mana saja di fase Core? (Saran:
  cukup `Company`, `CompanyModule`, `User` dulu; modul lain menambah sendiri.)
