# Dokumentasi ControlHub Workshop

Aplikasi manajemen **bengkel truk + gudang sparepart**, multi-tenant (`company_id`),
**Laravel + PostgreSQL**. Mode operasi saat ini: **INTERNAL (cost-focused)** — fitur
penjualan/invoice ke customer disiapkan tapi belum diaktifkan.

## Indeks Dokumen

| Dokumen | Isi |
|---|---|
| [OVERVIEW.md](OVERVIEW.md) | Konsep aplikasi, target pengguna, mode operasi, roadmap |
| [MODULES.md](MODULES.md) | Arsitektur multi-tenant & modul (Core, Admin, PMB, LKM, Purchasing, Inventory, Tyre, Servis, Price List, **Hutang Supplier/AP**) |
| [CORE.md](CORE.md) | **Desain build Modul 0 — Core** (`wks_core_`) + panel System (`/system`): rekonsiliasi `companies`, `modules`/`company_modules` (feature-flag), `audit_logs`, provisioning tenant, RBAC super admin |
| [SITEMAP.md](SITEMAP.md) | Navigasi/halaman 3 surface (App, System, Portal Supplier) & matriks akses peran |
| [PANELS.md](PANELS.md) | Desain panel Filament v5: Launcher (hub depan) + panel terpisah per fungsi (System, PMB, LKM, Servis, Mekanik, Gudang, Purchasing, Kontrabon, Kasir, Admin, Vendor) + modul AP `wks_ap_` |
| [DATABASE.md](DATABASE.md) | Data dictionary: semua tabel, kolom, tipe, enum, relasi |
| [IMPLEMENTATION.md](IMPLEMENTATION.md) | **Status implementasi (kode)**: stack terpasang, fondasi multi-tenant yang sudah dibangun, blocker, langkah berikutnya |
| [WORKFLOWS.md](WORKFLOWS.md) | Alur proses bisnis (PO/GRN, WO, gudang, sesi, dll.) + state transitions |
| [RISK_GAP_ANALYSIS.md](RISK_GAP_ANALYSIS.md) | Analisa risiko & kesenjangan + prioritas tindak lanjut |

## Template Reusable (di-copy ke aplikasi lain)

| Folder | Isi |
|---|---|
| [_templates/](_templates/) | Dokumen modul generik di-parameter `{{...}}` — copy seluruh folder ke proyek baru lalu isi parameter. Saat ini: [User Management](_templates/USER_MANAGEMENT.md). |

## Referensi Lain

- Konvensi penamaan tabel/kode: [../.claude/skills/workshop-feature/NAMING_CONVENTIONS.md](../.claude/skills/workshop-feature/NAMING_CONVENTIONS.md)
- Panduan membangun fitur (skill): [../.claude/skills/workshop-feature/SKILL.md](../.claude/skills/workshop-feature/SKILL.md)

## Urutan Baca yang Disarankan

1. **OVERVIEW** → gambaran besar & mode operasi
2. **MODULES** → modul, tenancy, sub-prefix `wks_<modul>_`
3. **DATABASE** → skema tabel detail
4. **WORKFLOWS** → bagaimana data mengalir antar modul
5. **SITEMAP** → tampilan/halaman
6. **PANELS** → arsitektur panel Filament (provider, tenancy, grup navigasi)

## Perubahan Terkini (2026-06-29) — modul Hutang Supplier (AP): Kontrabon + Kasir (panel terpisah)

- **Modul baru Hutang Supplier / Accounts Payable** (`wks_ap_`, MODULES §17) — hilir
  **PO → GRN**: **Kontrabon** (faktur supplier) → **Kasir** (pembayaran).
- **Kontrabon = dokumen yang KITA buat** ("tanda terima tagihan") menyalin tagihan supplier,
  lalu **direview & dicocokkan satu per satu**. **2 level**: header `wks_ap_kontrabons`
  (per supplier, jatuh tempo, **unit hutang**) + baris `wks_ap_kontrabon_invoices`
  (**1..n** faktur supplier). Tiap baris dicek **checklist**: ☑ barang diterima (GRN) ·
  ☑ surat jalan · ☑ faktur pajak (PPN) · ☑ PO & nominal cocok. Header **`verified` hanya
  bila semua baris `ok`** → **`approved`** (hutang diakui). `outstanding` = kolom **generated**.
- **Kasir** = pembayaran ke **supplier**: kelola **master rekening bank/kas**
  (`wks_ap_bank_accounts`) → **Request Pembayaran** (alur **maker→checker**,
  `wks_ap_payment_requests`/`_items`, alokasi ke 1/banyak kontrabon, anti over-pay) → **Realisasi**
  (`wks_ap_payments`) via **digital banking (maker-checker)** atau **giro**. **Giro punya Register
  sendiri** (`wks_ap_giros`): **register di aplikasi → print → tanda tangan → verifikasi (giro fisik
  harus sesuai sistem, dicek lewat print) → serah → cair** (SoD: registrar ≠ penanda tangan ≠
  pemeriksa). Semua via `ApService`. ⚠️ AP (supplier), **bukan** kasir penjualan customer (future/dormant).
- **Dua panel TERPISAH di-scaffold** (sesuai permintaan: diurus divisi Finance & Kasir):
  `KontrabonPanelProvider` (`/kontrabon`, peran **Finance/AP**) & `KasirPanelProvider`
  (`/kasir`, peran **Kasir**) — terdaftar di `bootstrap/providers.php`. **SoD:** verifikator ≠
  approver ≠ pembayar. **Tabel/Resource menyusul** setelah Purchasing/GRN dibangun.
- Detail: [MODULES.md](MODULES.md) §17, [DATABASE.md](DATABASE.md) §7d (+ enum §12, relasi §11),
  [WORKFLOWS.md](WORKFLOWS.md) §4c, [PANELS.md](PANELS.md) §7–§9, [SITEMAP.md](SITEMAP.md) §B.10/§B.11.

## Perubahan Terkini (2026-06-29) — bootstrap aplikasi + fondasi multi-tenant + relokasi penerimaan

- **Aplikasi di-bootstrap**: PHP 8.4.22, **Laravel 13.17**, **Filament v5.6.7**, PostgreSQL 17.10.
  Detail & blocker (GRANT DDL) di [IMPLEMENTATION.md](IMPLEMENTATION.md).
- **Fondasi multi-tenant** dibangun: begitu masuk, data **terpisah per Company & per Branch**
  via `Tenancy` + `IdentifyTenant` + global scope `BelongsToCompany`/`BelongsToBranch`.
- **Penerimaan sparepart direlokasi ke panel Gudang**: **Surat Jalan MASUK supplier (per PO) +
  GRN + posting stok** dikerjakan operator Gudang saat barang tiba; **Purchasing hanya membuat
  PO + lihat-saja** SJ/GRN (SoD: pembeli ≠ penerima). "Surat Jalan masuk" (supplier) ≠ "Surat
  Jalan keluar" (transfer/issue internal).
- **Taksonomi 8 jenis transaksi Gudang** (MODULES §8): ① terima supplier · ② keluar ke LKM/WO
  (Bon) · ③ terima part bekas (teardown/core) · ④ mutasi/relokasi · ⑤ **Peminjaman** (storing,
  wajib kembali 🆕) · ⑥ temuan/penyesuaian · ⑦ **Retur ke supplier** (potong tagihan 🆕) ·
  ⑧ **Retur Bon** (part baru tak jadi pakai → reverse HPP 🆕). Tabel baru `wks_inv_part_loans`,
  `wks_inv_purchase_returns`, `wks_inv_issue_returns` (DATABASE §5b).
- **Modul Audit Gudang** (DATABASE §5c, MODULES §8): **audit formal** (auditor independen → cek
  fisik → **temuan** + severity + tindak lanjut → verifikasi), **audit trail** immutable (ledger
  movement + `wks_core_audit_logs`), **review anomali** (stok negatif/selisih sesi → promosikan
  jadi temuan). Peran **`Auditor`** (SoD: ≠ operator/Kepala Gudang). Audit **tidak mengubah stok** —
  koreksi tetap via opname/penyesuaian. Tabel `wks_inv_audits`/`_items`/`_findings`.
- **Panel Audit terpisah `/audit`** — `AuditPanelProvider` (Filament) di-scaffold; independen dari
  panel Gudang (auditor ≠ operator). `IdentifyTenant` ditambah ke `authMiddleware` tiap panel
  (panel Filament tak pakai grup `web`). Lihat [IMPLEMENTATION.md](IMPLEMENTATION.md).
- **Gudang Ban — siklus 3 tahap gudang**: ① terima supplier (Gudang Ban Baru) · ② pasang ke LKM ·
  ③ lepas → **Gudang Bekas** (used) · ④ **Konfirmasi Afkir** (kontrol Kepala Gudang, status `afkir`,
  movement `condemn`) · ⑤ pindah **Gudang Afkir** · ⑥ **Jual Afkir** (disposal). Enum baru
  `tyre_status +afkir`, `tyre_movement_type +condemn`, `warehouses.tyre_stage` (new/used/afkir).
- **Web Supplier isi Surat Jalan** (`/vendor`): supplier isi SJ sendiri (**sparepart & ban**) per PO
  (`source=portal`) → **operator Gudang tak menyalin SJ** (GRN tinggal pilih SJ + tally fisik).
  Ban: SJ = product+qty, **serial diregistrasi saat GRN**. Diaktifkan via feature-flag (keamanan R46/R47).

## Perubahan Terkini (2026-06-27) — panel PMB terpisah & WO Plan berlangkah

- **PMB jadi modul/panel sendiri** (`wks_pmb_`, `/pmb`, peran **Dispatcher**) — pengantar
  dari pos dispatcher, **independen** dari LKM. Saat truk via PMB tiba, **Service Officer**
  **mencari referensi PMB** di sistem (no/plat) → prefill & buat LKM (`pmb_id` opsional,
  PMB→`used`). Tidak ada auto-terbit LKM.
- **WO Plan berlangkah**: tiap task WO dirinci jadi **langkah/sub-step** (`wks_svc_wo_task_steps`)
  — *bagaimana* mengerjakan (mis. Ganti ban → turunkan · periksa · pasang baru · kembalikan
  lama · cek tekanan). **Disusun Mekanik (bisa bersama Service Officer) setelah mekanik
  mengambil WO**; bisa salin **template jasa** `wks_svc_service_steps`. Plan = **panduan,
  tidak mengikat**: mekanik **mencentang** langkah (`done`/`skipped`) & boleh tambah **`adhoc`**.

Detail di [DATABASE.md](DATABASE.md) §8a/§10, [MODULES.md](MODULES.md) §16/§10, alur di
[WORKFLOWS.md](WORKFLOWS.md) §5/§6b, panel di [PANELS.md](PANELS.md) §2b/§3/§4/§4b.

## Perubahan Terkini (2026-06-25) — pendalaman modul Gudang Sparepart

Modul **Gudang Sparepart** didetailkan menyeluruh:
- **Ledger stok 2-lapis**: saldo fisik per bin (`stock_items`) vs valuasi/WAC per gudang
  (`stock_values`); movement `qty_in/qty_out`; **snapshot saldo harian** (anchor + pruning).
- **Konversi UOM** (box↔pcs); **stok negatif diizinkan + alert**; **WAC dibekukan** saat qty ≤ 0.
- **Setting Rak/Lokasi** hierarki fleksibel (`parent_id`/`node_type`, generator, kapasitas soft)
  + **slotting** per gudang (dynamic/fixed/hybrid).
- **Bon Pengeluaran Sparepart** ber-approval (Mekanik→ServiceAdvisor→Gudang), ref WO→LKM→truck.
- **Core Return** (bukti part rusak → scrap) untuk part non-consumable.
- **Sesi Kerja Gudang** **1 siklus/hari per gudang**: Opening = **gate masuk panel** (operator buka), Closing = **akhir hari oleh Kepala Gudang/Supervisor**; snapshot + deteksi anomali; notif WA/email >24 jam.
- **Surat Jalan masuk dari supplier** + **Portal Supplier** (`/vendor`, fase berikutnya).
- **Lapisan Notifikasi** (WA/email/in-app, dikonfigurasi di master) — resolusi gap G3.

Detail tabel di [DATABASE.md](DATABASE.md) §5/§7c, alur di [WORKFLOWS.md](WORKFLOWS.md) §8b–8d,
risiko baru R33–R51 & gap G13–G17 di [RISK_GAP_ANALYSIS.md](RISK_GAP_ANALYSIS.md).

## Perubahan Terkini (2026-06-25) — pekerjaan mekanik terukur (handheld)

Modul **Servis/Work Order** diperdalam: mekanik memperbarui pekerjaan **per task** via
**handheld** dengan **clock in/out live** agar terukur *apa yang dikerjakan* & *berapa lama*.
- **Daftar task per WO** (`wks_svc_wo_tasks`) + **penugasan multi-mekanik** (`task_assignments`).
- **Segmen jam kerja** (`wks_svc_task_time_logs`) → **estimasi vs aktual**, dasar laporan
  produktivitas & turnaround; guard **1 segmen aktif/mekanik**; timestamp **dari server**.
- **Dua jalur handheld**: panel **Filament Mekanik responsif** **+** **API + PWA offline**
  (sync **idempoten** via `client_event_id`) lewat `TaskTimeService` yang sama.

Detail di [DATABASE.md](DATABASE.md) §10, alur di [WORKFLOWS.md](WORKFLOWS.md) §6b, panel di
[PANELS.md](PANELS.md) §4b, risiko R56–R58 & gap G18 di [RISK_GAP_ANALYSIS.md](RISK_GAP_ANALYSIS.md).

## Status

Tahap **perencanaan/konsep**. Proyek Laravel belum di-scaffold. Beberapa keputusan
default masih bisa difinalkan — lihat **MODULES.md §14**. Repo:
[github.com/CharlesUnsulangi/controlhub_workshop](https://github.com/CharlesUnsulangi/controlhub_workshop).
