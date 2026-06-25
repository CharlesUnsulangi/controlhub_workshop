# User Management — Spesifikasi Modul (Template Generik)

> **Tujuan dokumen.** Ini adalah **dokumen referensi modul User Management** yang
> ditulis agar **dapat dipakai ulang lintas aplikasi**. Isi di-*parameter*-kan: bagian
> yang spesifik per-aplikasi dikumpulkan di **§0 Parameter** sebagai placeholder
> `{{...}}`. Untuk memakai di aplikasi lain, **copy file ini lalu isi tabel §0** dan
> jalankan find-replace (lihat **§16 Cara Adaptasi**). Bagian lain ditulis netral dan
> tidak perlu diubah.
>
> **Untuk AI agent:** dokumen ini adalah *sumber kebenaran (source of truth)* desain
> User Management. Saat membangun fitur, ikuti struktur tabel, aturan otorisasi, dan
> *Definition of Done* di sini. Bila aplikasi target punya konvensi sendiri (penamaan,
> framework, panel), **§0 + §16** yang menjembatani.

Stack acuan: **Laravel + PostgreSQL + Filament v5 (panel admin) + Filament Shield (RBAC)**.
Bila stack target berbeda, lihat **§17 Pemetaan Lintas-Stack**.

---

## 0. Parameter (isi per aplikasi)

| Param | Placeholder | Nilai untuk **ControlHub Workshop** | Keterangan |
|---|---|---|---|
| Nama aplikasi | `{{APP}}` | ControlHub Workshop | Untuk teks/branding |
| Prefix tabel modul | `{{PREFIX}}` | `wks_adm_` | Prefix tabel non-bawaan (roles, dst.) |
| Kolom tenant | `{{TENANT_COL}}` | `company_id` | Kolom pemisah multi-tenant (null bila single-tenant) |
| Entitas tenant | `{{TENANT}}` | Company | Nama domain tenant |
| Kolom sub-scope opsional | `{{SCOPE_COL}}` | `branch_id` | Scope tambahan non-tenant (cabang/divisi) — boleh kosong |
| Akun portal eksternal | `{{PORTAL_COL}}` | `supplier_id` | Untuk user mitra eksternal — boleh kosong |
| Daftar peran inti | `{{ROLES}}` | Owner, Admin, ServiceAdvisor, KepalaMekanik, Mekanik, Gudang, GudangBan, Purchasing, Finance/AP, Kasir, Supplier; + SuperAdmin/SystemSupport (level sistem) | Lihat §6 |
| Super-admin lintas tenant | `{{SUPERADMIN}}` | SuperAdmin / SystemSupport (`{{TENANT_COL}}` = null) | Akun pengelola sistem |
| Channel notifikasi | `{{CHANNELS}}` | email, whatsapp, in-app/database | Untuk undangan & reset |
| Multi-tenant? | `{{MULTI_TENANT}}` | Ya | Bila "Tidak", hapus kolom & scope tenant |

> **Catatan single-tenant.** Bila aplikasi target **bukan** multi-tenant, set
> `{{MULTI_TENANT}} = Tidak`, hapus `{{TENANT_COL}}` dari semua tabel/index/scope, dan
> abaikan §3. Sisanya tetap berlaku.

---

## 1. Ruang Lingkup

Modul User Management menangani **identitas, akses, dan siklus hidup pengguna**:

- **Identitas & autentikasi** — registrasi terkontrol (invite), login, password, reset,
  verifikasi email, (opsional) 2FA, sesi.
- **Otorisasi (RBAC)** — peran (role), izin (permission), penugasan, penjaga akses
  (policy + panel gate).
- **Multi-tenant & scoping** — isolasi data per `{{TENANT}}`, sub-scope `{{SCOPE_COL}}`,
  akun portal eksternal `{{PORTAL_COL}}`.
- **Siklus hidup** — undang → aktif → suspend → arsip (soft-delete), reset, transfer peran.
- **Audit & keamanan** — log aksi sensitif, kebijakan password, rate-limit, deteksi anomali.

**Di luar lingkup** (modul lain): profil domain bisnis, notifikasi umum (modul Notifikasi),
master data. Modul ini **hanya** soal *siapa boleh masuk & melakukan apa*.

---

## 2. Model Data

### 2.1 `users` (tabel bawaan framework + kolom tenant)

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| id | bigint | no | PK |
| `{{TENANT_COL}}` | bigint | yes | FK tenant. **null = super-admin sistem** |
| `{{SCOPE_COL}}` | bigint | yes | FK sub-scope (cabang default) — opsional |
| `{{PORTAL_COL}}` | bigint | yes | FK mitra eksternal — akun portal; null = internal |
| name | varchar(150) | no | |
| email | varchar(150) | no | **unique** (lihat catatan keunikan di §3.1) |
| password | varchar(255) | no | hash (bcrypt/argon2) |
| is_super_admin | bool | no | default false; bypass policy bila true |
| status | varchar(20) | no | enum: `active` · `invited` · `suspended` · `disabled`; default `invited` |
| last_login_at | timestamptz | yes | telemetri |
| email_verified_at | timestamptz | yes | |
| remember_token | varchar | yes | |
| timestamps, deleted_at | | | **soft delete** |

### 2.2 Tabel RBAC (`{{PREFIX}}`)

| Tabel | Kolom inti | Catatan |
|---|---|---|
| `{{PREFIX}}roles` | id · `{{TENANT_COL}}` (FK) · name · slug · description · is_system `bool` · timestamps | **unique(`{{TENANT_COL}}`, slug)**. `is_system` = peran bawaan, tak boleh dihapus/rename |
| `{{PREFIX}}permissions` | id · code **unique** · group · description | **Katalog global** (tanpa `{{TENANT_COL}}`). `code` = `<resource>.<action>`, mis. `user.create` |
| `{{PREFIX}}role_user` *(pivot)* | role_id (FK) · user_id (FK) | **PK(role_id, user_id)** |
| `{{PREFIX}}permission_role` *(pivot)* | permission_id (FK) · role_id (FK) | **PK(permission_id, role_id)** |

### 2.3 Tabel pendukung (opsional, aktifkan sesuai kebutuhan)

| Tabel | Guna |
|---|---|
| `{{PREFIX}}user_invitations` | token undangan: email · role default · `{{TENANT_COL}}` · invited_by · token (hash) · expires_at · accepted_at |
| `{{PREFIX}}password_reset_tokens` | (bawaan framework) email · token · created_at |
| `{{PREFIX}}sessions` | (bawaan) id · user_id · ip · user_agent · last_activity |
| `{{PREFIX}}user_two_factor` | user_id · secret (enkripsi) · recovery_codes (enkripsi) · confirmed_at |
| `{{PREFIX}}login_attempts` | email/ip · success · at — untuk rate-limit & deteksi brute force |
| `audit_logs` (Core) | actor_id · `{{TENANT_COL}}` · action · entity · before/after (jsonb) · ip · at |

> **Relasi:** `roles 1—* role_user *—1 users`; `roles *—* permissions` via `permission_role`.
> User efektif memiliki **union** permission dari semua role-nya.

---

## 3. Multi-Tenancy & Scoping  *(lewati bila `{{MULTI_TENANT}} = Tidak`)*

- **Isolasi tenant:** setiap query user/role di-*scope* otomatis ke `{{TENANT_COL}}`
  user yang login (global scope `BelongsTo{{TENANT}}`). User hanya melihat user lain
  dalam tenant yang sama.
- **Super-admin sistem:** `{{TENANT_COL}}` = null **dan** `is_super_admin = true` →
  lintas tenant, dikelola di panel sistem terpisah, **tidak** muncul di daftar user tenant.
- **Akun portal eksternal:** `{{PORTAL_COL}}` terisi → akses **hanya** panel portal +
  scope ketat ke entitas mitranya. Tak boleh punya peran internal.
- **Sub-scope (`{{SCOPE_COL}}`):** *bukan* tenant kedua — hanya filter/preferensi
  (cabang default). Jangan menduplikasi logika tenancy untuknya.

### 3.1 Keunikan email
- **Single-tenant / portal global:** `email` unique global.
- **Multi-tenant ketat:** email boleh sama lintas tenant → unique **(`{{TENANT_COL}}`, email)**.
- **Default ControlHub:** email **unique global** (satu orang = satu akun) — paling sederhana
  untuk login. Putuskan eksplisit di §0 bila berbeda.

---

## 4. Autentikasi

| Aspek | Aturan default |
|---|---|
| Login | email + password; throttle (mis. 5 gagal / menit / IP+email) → backoff |
| Password hash | argon2id atau bcrypt (cost ≥ 12); **tak pernah** simpan plaintext |
| Kebijakan password | min 10 char; cek daftar password bocor (opsional); tak ada paksa-rotasi periodik (anti-pattern) |
| Reset password | token sekali-pakai, kedaluwarsa ≤ 60 mnt, dikirim via `{{CHANNELS}}`; invalidasi sesi lain setelah ganti |
| Verifikasi email | wajib sebelum status `active` (kecuali dibuat admin & ditandai terverifikasi) |
| 2FA (opsional) | TOTP + recovery codes; wajib-kan untuk peran sensitif (Owner/Admin/Finance) bila diaktifkan |
| Sesi | server-side; logout meng-invalidasi; "logout semua perangkat"; idle timeout konfigurable |
| Remember-me | token aman, rotasi; dapat dicabut |

**Lockout:** setelah N gagal, kunci sementara + (opsional) notifikasi ke pemilik akun.
Catat semua percobaan di `login_attempts` / `audit_logs`.

---

## 5. Siklus Hidup Pengguna

```
[invite]──► invited ──(terima + set password)──► active ◄──┐
                                                   │        │ (reset/aktifkan)
                                  (admin suspend)  ▼        │
                                              suspended ────┘
                                                   │ (nonaktifkan permanen)
                                                   ▼
                                              disabled ──(soft delete)──► arsip
```

| Aksi | Pelaku | Efek |
|---|---|---|
| **Undang** | Admin/Owner | buat user `invited` + kirim token (kedaluwarsa); belum bisa login |
| **Terima undangan** | Calon user | set password → `active`, email terverifikasi |
| **Buat langsung** | Admin | buat `active` + paksa ganti password saat login pertama (opsional) |
| **Suspend** | Admin | blokir login sementara, data tetap; sesi aktif dicabut |
| **Aktifkan** | Admin | kembalikan ke `active` |
| **Nonaktifkan** | Admin | `disabled` permanen (mis. resign) |
| **Soft delete** | Admin | sembunyikan; pertahankan FK histori (`*_by`) — **jangan hard delete** bila ada referensi |
| **Reset password (admin)** | Admin | kirim token reset; tak pernah lihat/ketik password user |
| **Ubah peran** | Admin | catat di audit; berlaku pada sesi/permission berikutnya |

> **Aturan integritas:** user yang pernah jadi `created_by`/`*_by` transaksi **tak boleh
> hard-delete**. Selalu soft-delete agar jejak audit utuh.

---

## 6. RBAC — Peran & Izin

### 6.1 Model
- **Permission** = atom akses `<resource>.<action>` (mis. `user.create`, `role.assign`,
  `stock.adjust`). Katalog **global**, di-*generate*/seed.
- **Role** = kumpulan permission, **per tenant** (`{{TENANT_COL}}`). Peran `is_system`
  bawaan; tenant boleh buat peran kustom dari katalog permission.
- **User** = banyak role → permission efektif = **union**. Cek akses selalu via
  permission, **bukan** nama role (role bisa di-rename/kustom).

### 6.2 Peran inti (`{{ROLES}}`)
Untuk **ControlHub Workshop** (sesuaikan per app):

| Peran | Lingkup |
|---|---|
| SuperAdmin / SystemSupport | level sistem (tenant null) — kelola tenant, modul, support |
| Owner / Admin | penuh dalam satu tenant; Admin kelola user/RBAC/master/setting |
| ServiceAdvisor, KepalaMekanik, Mekanik | operasional servis |
| Gudang, GudangBan | operasional gudang/ban |
| Purchasing, Finance/AP, Kasir | pengadaan & keuangan |
| Supplier | portal eksternal (`{{PORTAL_COL}}`) |

### 6.3 Penjaga akses (berlapis)
1. **Panel/route gate** — `canAccessPanel()` cek peran + scope (`{{TENANT_COL}}`/
   `{{PORTAL_COL}}`). Panel/menu disembunyikan bila tak berhak atau modul off.
2. **Policy per model** — penjaga akhir tiap aksi (view/create/update/delete) cek permission.
3. **Query scope** — global scope tenant memastikan data tak bocor lintas tenant.

### 6.4 Separation of Duties (SoD)
Definisikan pasangan peran yang **tak boleh dirangkap satu orang** (mis. pengusul ≠
penyetuju ≠ pelaksana; verifikator faktur ≠ kasir pembayar). Tegakkan di policy/validasi
penugasan peran, bukan sekadar konvensi.

---

## 7. API / Endpoint (opsional, bila ada layer API)

| Method & Path | Aksi | Izin |
|---|---|---|
| `GET /users` | daftar (scoped tenant) | `user.viewAny` |
| `POST /users/invite` | undang user | `user.create` |
| `POST /users/{id}/suspend` | suspend | `user.update` |
| `PUT /users/{id}/roles` | set peran | `role.assign` |
| `DELETE /users/{id}` | soft delete | `user.delete` |
| `GET /roles` · `POST /roles` · `PUT /roles/{id}` | kelola peran | `role.*` |
| `POST /auth/login` · `/logout` · `/password/forgot` · `/password/reset` | auth | publik / sesi |
| `POST /auth/2fa/enable` · `/confirm` · `/disable` | 2FA | sesi pemilik |

Semua endpoint mutasi: **transaksi DB**, **idempoten** bila relevan, **audit** otomatis.

---

## 8. Migration — sketsa urutan

1. (bawaan) `users`, `password_reset_tokens`, `sessions` → **tambah** kolom
   `{{TENANT_COL}}`, `{{SCOPE_COL}}`, `{{PORTAL_COL}}`, `is_super_admin`, `status`,
   `last_login_at`, `deleted_at` + index.
2. `{{PREFIX}}roles` (+ unique `{{TENANT_COL}}`,slug).
3. `{{PREFIX}}permissions` (unique code).
4. Pivot `{{PREFIX}}role_user`, `{{PREFIX}}permission_role`.
5. Opsional: `{{PREFIX}}user_invitations`, `{{PREFIX}}user_two_factor`, `login_attempts`.

**Index wajib:** `users(({{TENANT_COL}}), status)`, `users(email)` (unique sesuai §3.1),
FK semua kolom relasi, pivot PK komposit.

---

## 9. Seeding — peran & permission default
- Seed **katalog permission** dari daftar resource×action (idempoten — `upsert` by `code`).
- Seed **peran sistem** (`is_system=true`) per tenant baru (mis. saat tenant dibuat:
  buat Owner+Admin otomatis, tetapkan pembuat sebagai Owner).
- Jangan hard-code id; referensikan via `slug`/`code`.

---

## 10. UI / Panel (Filament v5 — opsional)

| Grup | Resource / Page |
|---|---|
| **Pengguna & Akses** | `UserResource` · `RoleResource` (Shield) · (sistem) `SystemUserResource` (`users` where `{{TENANT_COL}}` null) |
| **Undangan** | `UserInvitationResource` (kirim ulang, cabut) |
| **Setting akun** | profil, ganti password, 2FA self-service |

- Permission tiap Resource: `<resource>.<action>` via `shield:generate`.
- Sembunyikan field/aksi sensitif (set peran, status) dari yang tak berhak.
- Dashboard admin: aktivitas user, login terakhir, akun suspended, undangan pending.

---

## 11. Audit & Keamanan
- **Audit** semua aksi sensitif: login/logout, gagal login, ubah peran/permission,
  suspend/aktifkan, reset password, ubah email. Simpan actor, target, before/after, ip, waktu.
- **Rate-limit** login & endpoint reset; captcha bila perlu.
- **Least privilege**: default peran paling sempit; tambah eksplisit.
- **Cabut akses cepat**: suspend langsung mematikan sesi aktif.
- **Rahasia**: 2FA secret & recovery codes **dienkripsi at-rest**; password hanya hash.
- **PII**: email/nama dilindungi; ekspor user butuh izin & teraudit.

---

## 12. Edge Cases
- User terakhir ber-peran Owner **tak boleh** di-suspend/hapus (cegah lockout tenant).
- Suspend diri sendiri / hapus akun sendiri → blokir.
- Undangan kedaluwarsa → tolak terima; sediakan kirim-ulang.
- Email berubah → wajib verifikasi ulang.
- User pindah tenant → pada model ini **buat akun baru** di tenant tujuan (jangan pindah FK).
- Portal user (`{{PORTAL_COL}}`) tak boleh diberi peran internal.

---

## 13. Definition of Done (checklist)
- [ ] Migrasi `users` + kolom tenant/scope/portal/status + soft delete + index.
- [ ] Tabel RBAC + pivot + unique/PK benar.
- [ ] Katalog permission ter-seed; peran sistem ter-seed per tenant.
- [ ] Login throttle + lockout + audit; reset password sekali-pakai + invalidasi sesi.
- [ ] (Opsional) 2FA TOTP + recovery codes terenkripsi.
- [ ] Penjaga berlapis: panel gate + policy + global scope tenant.
- [ ] SoD ditegakkan pada penugasan peran.
- [ ] Soft-delete melindungi FK histori; cegah hard-delete user bereferensi.
- [ ] Guard lockout Owner terakhir.
- [ ] Aksi mutasi via service + transaksi DB + audit.

---

## 14. Asumsi & Konfirmasi
- [ ] Keunikan email: global vs per-tenant? (default: global)
- [ ] 2FA: aktif? wajib untuk peran apa?
- [ ] Registrasi mandiri publik **dimatikan** — semua via undangan admin? (default: ya)
- [ ] Verifikasi email wajib sebelum aktif? (default: ya, kecuali dibuat admin)
- [ ] Sub-scope `{{SCOPE_COL}}` dipakai? portal `{{PORTAL_COL}}` dipakai?

---

## 15. Relasi dengan Dokumen/Modul Lain *(khusus repo ini, hapus saat di-copy)*
- Master tabel: lihat [`../DATABASE.md`](../DATABASE.md) §2 (users) & §3 (`wks_adm_`).
- Modul Admin & RBAC: [`../MODULES.md`](../MODULES.md) §4. Panel & matriks peran:
  [`../PANELS.md`](../PANELS.md) §10, §12.
- Konvensi penamaan: `../../.claude/skills/workshop-feature/NAMING_CONVENTIONS.md`.

---

## 16. Cara Adaptasi ke Aplikasi Lain (untuk AI agent)

Saat meng-copy dokumen ini ke proyek baru:

1. **Isi tabel §0** dengan nilai aplikasi target.
2. **Find-replace** seluruh placeholder:
   `{{APP}}`, `{{PREFIX}}`, `{{TENANT_COL}}`, `{{TENANT}}`, `{{SCOPE_COL}}`,
   `{{PORTAL_COL}}`, `{{ROLES}}`, `{{SUPERADMIN}}`, `{{CHANNELS}}`, `{{MULTI_TENANT}}`.
3. **Bila single-tenant** (`{{MULTI_TENANT}} = Tidak`): hapus §3, buang `{{TENANT_COL}}`
   dari semua tabel/index/scope, jadikan email unique global.
4. **Bila tanpa portal eksternal**: buang `{{PORTAL_COL}}` & baris terkait.
5. **Sesuaikan §6.2** (daftar peran) ke domain aplikasi target.
6. **Hapus §15** (referensi spesifik repo ini).
7. Sesuaikan **§17** ke stack target bila bukan Laravel/Filament.
8. Verifikasi `DATABASE`/`MODULES`/panel proyek target agar konsisten.

> Pertahankan §1–§14 sebagai **kontrak desain** yang netral-domain. Hanya §0, §6.2, §15,
> §17 yang biasanya berubah antar aplikasi.

---

## 17. Pemetaan Lintas-Stack (referensi)

| Konsep | Laravel/Filament (acuan) | Node/NestJS | Django |
|---|---|---|---|
| Tabel user | `users` (Eloquent) | entity `User` (TypeORM/Prisma) | `auth.User` / custom |
| RBAC | Filament Shield (role/permission) | `casl`/`nest-access-control` | `django-guardian`/groups+perms |
| Policy | Model Policy | Guard + `@UseGuards` | permission classes/DRF |
| Multi-tenant scope | global scope `BelongsTo{{TENANT}}` | middleware/repository filter | manager/`TenantMixin` |
| Hash password | Hash (argon2/bcrypt) | `argon2`/`bcrypt` | PBKDF2/argon2 default |
| 2FA | `pragmarx/google2fa` | `otplib` | `django-otp` |
| Audit | event + `audit_logs` | interceptor + tabel | signals/`django-auditlog` |

Konsep desain (§1–§14) tetap; hanya nama API/pustaka yang berbeda.
