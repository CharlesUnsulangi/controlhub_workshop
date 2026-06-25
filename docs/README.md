# Dokumentasi ControlHub Workshop

Aplikasi manajemen **bengkel truk + gudang sparepart**, multi-tenant (`company_id`),
**Laravel + PostgreSQL**. Mode operasi saat ini: **INTERNAL (cost-focused)** — fitur
penjualan/invoice ke customer disiapkan tapi belum diaktifkan.

## Indeks Dokumen

| Dokumen | Isi |
|---|---|
| [OVERVIEW.md](OVERVIEW.md) | Konsep aplikasi, target pengguna, mode operasi, roadmap |
| [MODULES.md](MODULES.md) | Arsitektur multi-tenant & modul (Core, Admin, LKM, Purchasing, Inventory, Tyre, Servis, Price List) |
| [SITEMAP.md](SITEMAP.md) | Navigasi/halaman 3 surface (App, System, Portal Supplier) & matriks akses peran |
| [PANELS.md](PANELS.md) | Desain panel Filament v5: Launcher (hub depan) + panel terpisah per fungsi (System, LKM, Servis, Mekanik, Gudang, Purchasing, Kontrabon, Kasir, Admin, Vendor) + reservasi modul AP `wks_ap_` |
| [DATABASE.md](DATABASE.md) | Data dictionary: semua tabel, kolom, tipe, enum, relasi |
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

## Perubahan Terkini (2026-06-25) — pendalaman modul Gudang Sparepart

Modul **Gudang Sparepart** didetailkan menyeluruh:
- **Ledger stok 2-lapis**: saldo fisik per bin (`stock_items`) vs valuasi/WAC per gudang
  (`stock_values`); movement `qty_in/qty_out`; **snapshot saldo harian** (anchor + pruning).
- **Konversi UOM** (box↔pcs); **stok negatif diizinkan + alert**; **WAC dibekukan** saat qty ≤ 0.
- **Setting Rak/Lokasi** hierarki fleksibel (`parent_id`/`node_type`, generator, kapasitas soft)
  + **slotting** per gudang (dynamic/fixed/hybrid).
- **Bon Pengeluaran Sparepart** ber-approval (Mekanik→ServiceAdvisor→Gudang), ref WO→LKM→truck.
- **Core Return** (bukti part rusak → scrap) untuk part non-consumable.
- **Sesi Kerja Gudang** (opening/closing per operator, snapshot + deteksi anomali; notif WA/email >24 jam).
- **Surat Jalan masuk dari supplier** + **Portal Supplier** (`/vendor`, fase berikutnya).
- **Lapisan Notifikasi** (WA/email/in-app, dikonfigurasi di master) — resolusi gap G3.

Detail tabel di [DATABASE.md](DATABASE.md) §5/§7c, alur di [WORKFLOWS.md](WORKFLOWS.md) §8b–8d,
risiko baru R33–R51 & gap G13–G17 di [RISK_GAP_ANALYSIS.md](RISK_GAP_ANALYSIS.md).

## Status

Tahap **perencanaan/konsep**. Proyek Laravel belum di-scaffold. Beberapa keputusan
default masih bisa difinalkan — lihat **MODULES.md §14**. Repo:
[github.com/CharlesUnsulangi/controlhub_workshop](https://github.com/CharlesUnsulangi/controlhub_workshop).
