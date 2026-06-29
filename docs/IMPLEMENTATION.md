# ControlHub Workshop — Status Implementasi (kode)

> Jembatan **desain ↔ kode**. Dokumen desain (`MODULES`, `DATABASE`, `PANELS`, `WORKFLOWS`,
> `SITEMAP`) menggambarkan rencana; dokumen ini mencatat **apa yang sudah benar-benar
> di-scaffold** di repo, lingkungan, dan blocker aktif. Perbarui tiap ada milestone kode.
>
> Terakhir diperbarui: **2026-06-29**.

---

## 1. Stack terpasang

| Komponen | Versi | Catatan |
|---|---|---|
| PHP | **8.4.22** (NTS, vs17 x64) | terpasang di `C:\php84` (di luar PATH global) |
| Laravel | **13.17.0** | di root project |
| Filament | **v5.6.7** | provider: `AdminPanelProvider` (`/admin`), `AuditPanelProvider` (`/audit`), **`KontrabonPanelProvider` (`/kontrabon`)**, **`KasirPanelProvider` (`/kasir`)** |
| PostgreSQL | **17.10** | remote `103.119.49.160:2626`, db `controlhub_workshop` |
| Composer | 2.8.9 | dijalankan dengan PHP 8.4 (lihat §5) |
| Node | 22.x | untuk aset Filament/Vite |

`php.ini` (`C:\php84\php.ini`) mengaktifkan: `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`,
`curl`, `intl`, `zip`, `gd`, `fileinfo`, `bcmath`, `sodium`; `curl.cainfo`/`openssl.cafile`
→ `C:\php84\cacert.pem`; `date.timezone = Asia/Makassar`.

---

## 2. Fondasi multi-tenant (SUDAH dibangun)

Tujuan: **begitu user masuk, data otomatis terpisah per tenant (Company) dan per lokasi
(Branch)** — lihat `PANELS.md §1`. Implementasi (scope global, bukan tenancy URL Filament):

| Berkas | Peran |
|---|---|
| `app/Support/Tenancy.php` | Konteks tenant aktif per-request (companyId, branchId, superAdmin). Singleton. |
| `app/Http/Middleware/IdentifyTenant.php` | Set konteks dari user login + `session('active_branch_id')`/`default_branch_id`. Terdaftar di grup **web** (`bootstrap/app.php`) **dan di `authMiddleware` tiap panel Filament** (panel pakai middleware sendiri, bukan grup web). |
| `app/Models/Scopes/CompanyScope.php` | Global scope `where company_id = <tenant>` (super admin → tak difilter). Jaminan isolasi (R1). |
| `app/Models/Scopes/BranchScope.php` | Global scope `where branch_id = <branch aktif>` (hanya bila branch dipilih). |
| `app/Models/Concerns/BelongsToCompany.php` | Pasang `CompanyScope` + auto-isi `company_id` saat create. |
| `app/Models/Concerns/BelongsToBranch.php` | Pasang `BranchScope` + auto-isi `branch_id` saat create. |
| `app/Models/Company.php` | Tenant induk (Core, tanpa company_id). |
| `app/Models/Branch.php` | Lokasi milik Company (pakai `BelongsToCompany`). |
| `app/Models/User.php` | `implements FilamentUser`; `canAccessPanel()`, `isSuperAdmin()` (`company_id` null = super admin). |
| `app/Providers/AppServiceProvider.php` | Bind `Tenancy` sebagai singleton. |

**Migrations** (`database/migrations/2026_06_29_0000*`):
- `wks_core_companies` — tenant induk.
- `wks_ms_branches` — lokasi per company (`unique(company_id, code)`).
- `users` + `company_id` (null=super admin), `default_branch_id`, `is_active` (FK hanya di pgsql).

**Cara kerja isolasi:** login → `IdentifyTenant` set `Tenancy` (company + branch) → setiap
query model ber-`BelongsToCompany`/`BelongsToBranch` otomatis terfilter → data terpisah.

---

## 3. Blocker aktif

- 🔴 **Migrasi belum jalan.** Koneksi PG **OK**, tetapi user `admin25` **tak punya hak DDL**
  (`CREATE` di schema `public` = false). **Perlu** DBA/superuser menjalankan:
  ```sql
  GRANT CREATE ON SCHEMA public TO admin25;
  ```
  Setelah itu: `php artisan migrate` → seed → uji isolasi tenant/branch.

---

## 4. Belum dibangun (rencana berikutnya)

- Multi-panel sesuai `PANELS.md`: **System, PMB, LKM, Servis, Mekanik, Gudang, Purchasing,
  Kontrabon, Kasir, Admin, Vendor** (+ Launcher `/home`). **Sudah ada provider:** `admin`
  (`/admin`), **`audit` (`/audit`)**, **`kontrabon` (`/kontrabon`, peran Finance/AP)**,
  **`kasir` (`/kasir`, peran Kasir)** — Resource/halaman isi menyusul.
- RBAC **Shield** (permission per Resource), Policy — termasuk gating panel `kontrabon`/`kasir`
  ke peran Finance/AP & Kasir (kini `canAccessPanel()` masih izinkan semua user tenant aktif).
- Migrasi & model domain: `wks_adm_`, `wks_ms_` (lengkap), `wks_pmb_`, `wks_lkm_`, `wks_inv_`,
  `wks_tyre_`, `wks_po_`, `wks_price_`, `wks_svc_` (termasuk WO Plan `wo_task_steps`), **`wks_ap_`
  (Kontrabon + Kasir — desain final di DATABASE §7d; tabel menyusul setelah `wks_po_`/GRN ada)**.
- Service domain: `StockService`, `TyreService`, `PricingService`, `PmbService`, `TaskTimeService`,
  `WoPlanService`, `HrdGateway`, `ApService`.
- Seeder contoh (super admin, company, branch, user tenant) + uji isolasi.

---

## 5. Menjalankan (dev, Windows)

Karena PHP 8.4 ada di `C:\php84` (bukan PATH global), prefiks PATH per sesi atau panggil absolut:

```powershell
$env:Path = "C:\php84;" + $env:Path
php artisan migrate         # setelah GRANT
php artisan serve           # buka http://127.0.0.1:8000/admin
```

Composer dengan PHP 8.4: `& "C:\php84\php.exe" "C:\composer\composer.phar" <perintah>`.

> Kredensial DB ada di `.env` (ter-`.gitignore`, tidak ikut commit).
