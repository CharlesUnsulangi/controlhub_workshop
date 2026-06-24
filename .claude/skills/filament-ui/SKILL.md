---
name: filament-ui
description: Panduan membangun UI back-office dengan Filament v5 untuk ControlHub Workshop — panel, Resource (form/table/relation manager), tenancy (Company), RBAC (Shield), custom action/page. Gunakan saat membuat/menelaah Filament Resource, panel, form, table, atau halaman admin. Pelengkap workshop-feature (alur fitur) & postgres-design (DB).
---

# Filament v5 UI — ControlHub Workshop

UI back-office = **Filament v5** (Livewire v4). Skill ini = konvensi Filament khusus
proyek. Penamaan → `NAMING_CONVENTIONS.md`. DB → `postgres-design` & `docs/DATABASE.md`.
Logika bisnis tetap di **Service** (jangan di Resource).

> ⚠️ Filament v5 masih baru — **verifikasi sintaks/method persis ke dokumentasi v5**
> sebelum menulis (API form/schema bisa beda dari v3). Skill ini soal *pola & keputusan*,
> bukan kutipan API. Cek kompatibilitas plugin dengan v5 (RISK R32).

## Panel (2 buah)
- **App** (`/app`) — panel tenant operasional bengkel. Multi-tenant: **Company = tenant**.
- **System** (`/system`) — panel Core/super-admin (kelola company, audit). **Tanpa tenant**;
  hanya `is_super_admin`.
- Tiap panel `PanelProvider` sendiri; register Resource ke panel yang sesuai.

## Tenancy (selaras desain `company_id`)
- Set Company sebagai tenant panel App (`->tenant(Company::class)`).
- **Satu sumber kebenaran tenant aktif**: tenant Filament memicu/menyetel scope `company_id`
  (trait `BelongsToCompany`). Jangan sampai dua mekanisme bentrok (RISK R31 🔴).
- User hanya bisa pilih company yang berhak (relasi user↔company).
- Super-admin operasi lintas tenant lewat panel System / impersonate (audit).

## RBAC — Filament Shield
- Generate permission per Resource (`shield:generate`).
- Peta peran: Owner, Admin, ServiceAdvisor, KepalaMekanik, Mekanik, Gudang, Purchasing, Kasir.
- Tetap pakai **Policy** model; Shield menyinkronkan permission ke Resource.
- Sembunyikan Resource/aksi bila peran tak berhak & bila modul dimatikan (feature-flag Core).

## Resource (per entitas)
- Satu Resource per entitas master/transaksi (lihat peta modul `docs/MODULES.md`).
- **Form**: field sesuai `DATABASE.md`; FK pakai select relationship + searchable;
  enum status pakai select dgn opsi dari PHP Enum; uang/qty pakai numeric + step;
  field *(future/dormant)* disembunyikan via feature-flag.
- **Table**: kolom ringkas, badge untuk status (warna per enum), filter
  (status, warehouse, condition new/used, branch), search by kode/SKU/part number.
- **Relation managers**: untuk anak (mis. WorkOrder→items, PO→items, GRN→items, Tally→items).
- **Group navigasi** per modul (Core/Admin/Master/LKM/Inventory/Tyre/Purchasing/Servis/Price).

## Aksi Transaksional (jangan taruh logika di Resource)
- Operasi berstatus & berdampak stok → **custom Action** yang memanggil Service:
  - GRN **Post** → `StockService` (in + WAC) — lihat workflow #4.
  - Surat Jalan **Post/Kirim** → `StockService` (out/transfer) — #10b.
  - Opname **Post** → adjustment — #9.
  - WO **pemakaian part / pasang ban** → `StockService`/`TyreService` — #6/#8.
- Aksi membungkus `DB::transaction()` di Service; Resource hanya memicu + tampilkan hasil.
- Cegah aksi ganda (idempoten / disable tombol setelah posted).

## Halaman & Dashboard
- Dashboard per panel (widget: kendaraan masuk, WO aktif, stok kritis, PM/STNK due).
- Laporan lintas modul → custom Page + query (pertimbangkan read-model bila berat).
- Sinkron Driver HRD → custom Page/Action memanggil `HrdGateway` (#4b).

## Konvensi Kode
- Resource/Page **tanpa prefix `wks`** (itu hanya untuk tabel DB).
- Namespace per modul boleh (`App\Filament\Resources\Inv\...`).
- Bahasa label UI boleh Indonesia; identifier kode Inggris.
- Format uang (Rp) & tanggal konsisten via helper/cast.

## Testing
- Livewire/Filament test untuk Resource penting (create/edit + otorisasi).
- **Test isolasi tenant**: user company A tak bisa lihat/ubah data company B (RISK R1).

## Definition of Done (Filament)
- [ ] Resource di panel yang benar (App vs System) + grup navigasi modul.
- [ ] Otorisasi via Policy/Shield; tersembunyi bila tak berhak / modul off.
- [ ] FK select searchable; status badge; filter (incl. condition/branch) & search SKU.
- [ ] Aksi stok/uang lewat Service + transaksi (bukan di Resource).
- [ ] Field future/dormant ter-feature-flag.
- [ ] Test happy-path + otorisasi + isolasi tenant.
