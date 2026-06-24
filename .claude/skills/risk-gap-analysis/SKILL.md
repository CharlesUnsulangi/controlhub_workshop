---
name: risk-gap-analysis
description: Lakukan/perbarui analisa risiko & kesenjangan (risk & gap) untuk proyek ControlHub Workshop. Gunakan saat user minta "analisa risk", "gap analysis", "cek risiko", "review kesiapan", "apa yang kurang", atau setelah ada modul/keputusan desain baru yang perlu dinilai ulang. Output: perbarui docs/RISK_GAP_ANALYSIS.md.
---

# Risk & Gap Analysis — ControlHub Workshop

Skill untuk menilai **risiko** dan **celah (gap)** proyek secara sistematis, lalu menulis/
memperbarui `docs/RISK_GAP_ANALYSIS.md`. Dipakai berulang setiap ada perubahan besar.

## Kapan dipakai
- User minta analisa risiko / gap / kesiapan.
- Setelah modul baru, keputusan desain baru, atau perubahan skema.
- Sebelum pindah fase (mis. dari perencanaan ke scaffold/implementasi).

## Langkah

1. **Baca konteks terbaru**: `docs/OVERVIEW.md`, `MODULES.md` (terutama §14 keputusan
   terbuka), `DATABASE.md`, `WORKFLOWS.md`, dan `RISK_GAP_ANALYSIS.md` yang ada.
2. **Telusuri tiap dimensi** (checklist di bawah) — cari risiko & gap konkret, **spesifik
   ke desain ini**, bukan generik.
3. **Skor**: Likelihood (L) × Impact (I) → Severity 🔴/🟠/🟡. Tetapkan Status
   (Terbuka/Termitigasi/Diterima) + mitigasi nyata.
4. **Bedakan**: *Risk* = sesuatu bisa salah; *Gap* = sesuatu belum ada/belum diputuskan.
5. **Perbarui dokumen**: jaga format Risk Register + Gap table + Prioritas. Pertahankan ID
   lama (R1, G1, …) agar bisa dilacak; tandai yang sudah teratasi jadi "Termitigasi/Closed",
   jangan hapus diam-diam. Tambah ID baru di urutan.
6. **Naikkan ke user** hanya item 🔴 yang butuh keputusan, ringkas.

## Checklist Dimensi (tailored)

- **Multi-tenancy**: kebocoran `company_id`, auto-set, unique per company, `branch_id`,
  impersonation.
- **Integritas stok/biaya**: mutasi via `StockService` saja, race/locking WAC, reservasi
  menggantung, snapshot harga, valuasi part bekas, WAC vs FIFO.
- **Keamanan/akses**: RBAC per endpoint (default-deny), enkripsi kredensial, audit aksi
  sensitif, upload file.
- **Integrasi HRD**: ketahanan API, mapping company/mitra, data drift, kontrak/versi API.
- **Master & fungsional**: SKU cross-ref (resolve nomor/brand), supersession, posisi ban
  per tipe truk, fitur jual dormant.
- **Non-fungsional/operasional**: performa tabel besar & index, penomoran dokumen
  (concurrency), testing/CI, backup/DR, monitoring, import data master, reporting.
- **Kepatuhan**: PPN/faktur pajak (saat jual aktif), privasi data driver/mitra.

## Prinsip
- Spesifik & dapat ditindaklanjuti (sebut tabel/service/keputusan terkait).
- Jujur soal severity; jangan kecilkan risiko 🔴.
- Hubungkan ke keputusan terbuka di `MODULES.md` §14 bila relevan.
- Item 🔴 tidak boleh masuk produksi tanpa mitigasi — nyatakan itu.

## Output
`docs/RISK_GAP_ANALYSIS.md` ter-update + ringkasan singkat ke user (perubahan utama +
item 🔴 yang menunggu keputusan).
