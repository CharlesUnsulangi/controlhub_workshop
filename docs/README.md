# Dokumentasi ControlHub Workshop

Aplikasi manajemen **bengkel truk + gudang sparepart**, multi-tenant (`company_id`),
**Laravel + PostgreSQL**. Mode operasi saat ini: **INTERNAL (cost-focused)** — fitur
penjualan/invoice ke customer disiapkan tapi belum diaktifkan.

## Indeks Dokumen

| Dokumen | Isi |
|---|---|
| [OVERVIEW.md](OVERVIEW.md) | Konsep aplikasi, target pengguna, mode operasi, roadmap |
| [MODULES.md](MODULES.md) | Arsitektur multi-tenant & 7 modul (Core, Admin, LKM, Purchasing, Inventory, Tyre, Servis, Price List) |
| [SITEMAP.md](SITEMAP.md) | Struktur navigasi/halaman & matriks akses peran |
| [DATABASE.md](DATABASE.md) | Data dictionary: semua tabel, kolom, tipe, enum, relasi |
| [WORKFLOWS.md](WORKFLOWS.md) | 13 alur proses bisnis + state transitions |
| [RISK_GAP_ANALYSIS.md](RISK_GAP_ANALYSIS.md) | Analisa risiko & kesenjangan + prioritas tindak lanjut |

## Referensi Lain

- Konvensi penamaan tabel/kode: [../.claude/skills/workshop-feature/NAMING_CONVENTIONS.md](../.claude/skills/workshop-feature/NAMING_CONVENTIONS.md)
- Panduan membangun fitur (skill): [../.claude/skills/workshop-feature/SKILL.md](../.claude/skills/workshop-feature/SKILL.md)

## Urutan Baca yang Disarankan

1. **OVERVIEW** → gambaran besar & mode operasi
2. **MODULES** → modul, tenancy, sub-prefix `wks_<modul>_`
3. **DATABASE** → skema tabel detail
4. **WORKFLOWS** → bagaimana data mengalir antar modul
5. **SITEMAP** → tampilan/halaman

## Status

Tahap **perencanaan/konsep**. Proyek Laravel belum di-scaffold. Beberapa keputusan
default masih bisa difinalkan — lihat **MODULES.md §14**.
