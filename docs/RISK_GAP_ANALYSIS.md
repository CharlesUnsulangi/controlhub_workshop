# ControlHub Workshop — Risk & Gap Analysis

> Analisa risiko & kesenjangan (v0.2, tahap perencanaan). Berbasis `OVERVIEW.md`,
> `MODULES.md`, `DATABASE.md`, `WORKFLOWS.md`. Tujuan: temukan risiko & celah **sebelum
> coding**, saat termurah diperbaiki. Tinjau ulang tiap ada keputusan/modul baru.
>
> **v0.2 (2026-06-25):** review modul Gudang Sparepart → desain ledger stok dirombak
> (saldo fisik vs valuasi dipisah, `qty_in/qty_out`, snapshot saldo harian). Tambah
> R33–R40, G13–G15.

## Cara Baca

- **Likelihood (L)** & **Impact (I)**: Rendah / Sedang / Tinggi.
- **Severity** = gabungan L×I → 🔴 Tinggi · 🟠 Sedang · 🟡 Rendah.
- **Status**: Terbuka / Termitigasi (sudah ada rancangan mitigasi) / Diterima.

---

## 1. Risk Register

### A. Arsitektur & Multi-Tenancy

| ID | Risiko | L | I | Sev | Mitigasi | Status |
|---|---|---|---|---|---|---|
| R1 | **Kebocoran data antar-tenant** — query lupa filter `company_id` (mis. raw query, relasi tanpa scope) | M | Tinggi | 🔴 | Trait `BelongsToCompany` + Global Scope wajib; larang raw query lintas tenant; test otomatis "tenant isolation"; review | Termitigasi |
| R2 | Insert tanpa `company_id` (mass-assign) → data yatim | M | Tinggi | 🔴 | Observer auto-set saat creating; kolom `NOT NULL`; FK | Termitigasi |
| R3 | Bentrok nomor dokumen antar/­dalam tenant | M | Sedang | 🟠 | `unique(company_id, doc_no)`; sequence per company | Termitigasi |
| R4 | `branch_id` salah/inconsistent pada transaksi | M | Sedang | 🟠 | Middleware set branch aktif; validasi; default dari user | Terbuka |
| R5 | Super-admin impersonation disalahgunakan | L | Tinggi | 🟠 | Audit setiap impersonate; izin ketat; sesi terbatas | Terbuka |
| R31 | **Dua mekanisme tenancy** (tenant Filament + Global Scope `company_id`) tak konsisten | M | Tinggi | 🔴 | Satu sumber kebenaran tenant aktif; Company sbg tenant Filament memicu scope; test isolasi | Terbuka |
| R32 | Filament v5 relatif baru → paket pihak ketiga/Plugin belum semua matang | M | Sedang | 🟠 | Verifikasi plugin (Shield, dll.) kompatibel v5; pin versi; siapkan fallback | Terbuka |

### B. Integritas Data Stok & Biaya

| ID | Risiko | L | I | Sev | Mitigasi | Status |
|---|---|---|---|---|---|---|
| R6 | **Stok tidak konsisten** bila mutasi tak lewat `StockService` / ada update langsung | M | Tinggi | 🔴 | Enkapsulasi total di `StockService`; `DB::transaction`; larangan update `stock_items` langsung; rekonsiliasi opname | Termitigasi |
| R7 | **Race condition** WAC/qty saat WO/GRN paralel pakai part sama | M | Tinggi | 🔴 | Pessimistic lock (`SELECT … FOR UPDATE`) pada baris `wks_inv_stock_values` (grain WAC); transaksi pendek | Terbuka |
| R33 | **`avg_cost` sumber ganda** — dulu di `stock_items` (per lokasi) & `spare_parts` (per SKU) → drift WAC | M | Tinggi | 🔴 | **Satu sumber kebenaran**: WAC hanya di `wks_inv_stock_values` (grain warehouse+condition); `stock_items` fisik-saja; `spare_parts.last_cost` cache tampilan | Termitigasi |
| R34 | **Baris saldo duplikat** — `unique(...,location_id,...)` dgn `location_id` NULL dianggap distinct di PG → agregasi stok rusak | M | Tinggi | 🔴 | `UNIQUE NULLS NOT DISTINCT` (PG17) atau lokasi sentinel; berlaku juga di snapshot fisik | Termitigasi |
| R35 | **Opname tak bisa rekonsiliasi per-kondisi** — `opname_items` tanpa `condition` (stok dilacak per new/used/rebuilt) | M | Tinggi | 🔴 | Tambah kolom `condition` (wajib) di `opname_items`; adjustment movement bawa condition | Termitigasi |
| R38 | **Stok negatif / over-reserve** — `out` melebihi saldo; `qty_reserved > qty_on_hand` | M | Sedang | 🟠 | CHECK `qty_reserved <= qty_on_hand`; **kebijakan stok negatif belum final** (tolak vs izinkan+alert) | Terbuka |
| R39 | **Double-allocation Surat Jalan** — stok tak di-reserve antara DO draft→delivered | M | Sedang | 🟠 | Reserve `qty_reserved` saat DO dibuat; lepas saat posting/cancel (sejajar reservasi WO) | Terbuka |
| R40 | **Snapshot historis hilang** — pruning harian menghapus anchor → query stok tanggal lama harus scan jauh | L | Sedang | 🟡 | `is_anchor` (akhir bulan) permanen, hanya snapshot harian non-anchor dipangkas | Termitigasi |
| R8 | Reservasi part menggantung saat WO batal (`qty_reserved` tak dilepas) | M | Sedang | 🟠 | Lifecycle reservasi eksplisit; job pembersih; event saat WO cancel | Terbuka |
| R9 | Snapshot harga gagal → biaya berubah retroaktif | L | Tinggi | 🟠 | Copy `unit_cost`/`unit_price` ke item saat create (sudah dirancang) | Termitigasi |
| R10 | Valuasi **part bekas** subyektif (unit_cost taksiran) | M | Sedang | 🟠 | Kebijakan penilaian; approval; kategori cost used terpisah | Terbuka |
| R11 | WAC vs FIFO belum final → koreksi mahal bila berubah setelah live | M | Sedang | 🟠 | Putuskan sekarang (default WAC, MODULES §14) | Terbuka |

### C. Keamanan & Akses

| ID | Risiko | L | I | Sev | Mitigasi | Status |
|---|---|---|---|---|---|---|
| R12 | Endpoint tak cek **RBAC** → akses tak sah | M | Tinggi | 🔴 | Policy per resource; default-deny; test otorisasi | Terbuka |
| R13 | Token integrasi HRD tersimpan plaintext | M | Tinggi | 🔴 | Enkripsi (Laravel encrypted cast); `.env`; rotasi token | Terbuka |
| R14 | Audit log tak mencakup aksi sensitif (adjustment stok, ubah harga, impersonate) | M | Sedang | 🟠 | Audit wajib pada aksi kritis; `wks_core_audit_logs` + `wks_price_histories` | Termitigasi |
| R15 | Upload foto (LKM) tak tervalidasi (tipe/ukuran/malware) | M | Sedang | 🟠 | Validasi mime/size; storage privat; nama acak | Terbuka |

### D. Integrasi ControlHub HRD

| ID | Risiko | L | I | Sev | Mitigasi | Status |
|---|---|---|---|---|---|---|
| R16 | HRD API down/lambat → ganggu operasional | M | Sedang | 🟠 | Cache lokal driver; timeout+retry/backoff; degrade graceful (dirancang) | Termitigasi |
| R17 | **Mapping company/mitra salah** → driver salah tenant | L | Tinggi | 🟠 | `unique(company_id, hrd_mitra_id)`; validasi `hrd_company_id`; review mapping | Termitigasi |
| R18 | Data drift (driver diubah di HRD & lokal) | M | Sedang | 🟠 | Source-of-truth jelas: field inti read-only saat `source=hrd` | Termitigasi |
| R19 | Kontrak/versi API HRD belum disepakati / berubah | Tinggi | Sedang | 🔴 | Koordinasi tim HRD; abstraksi `HrdGateway`; versioning; kontrak tertulis | Terbuka |

### E. Fungsional & Data Master

| ID | Risiko | L | I | Sev | Mitigasi | Status |
|---|---|---|---|---|---|---|
| R20 | SKU cross-ref salah resolve (nomor part sama beda brand) | M | Sedang | 🟠 | `unique(company_id, brand, part_no)`; UI pencarian jelas (tampilkan brand+SKU) | Termitigasi |
| R21 | Supersession part: stok lama vs baru tak ter-mapping | M | Sedang | 🟠 | Prosedur supersession (`superseded_by_id` + alih stok) | Terbuka |
| R36 | **Reorder point tak bisa per-gudang** — dulu hanya di `spare_parts` (company-wide) | M | Sedang | 🟠 | Override `reorder_point`/`min_qty`/`max_qty` di `stock_values` (per gudang); null → default SKU | Termitigasi |
| R37 | **Tak ada konversi UOM** — beli per box, simpan per pcs → qty/biaya beda satuan | M | Sedang | 🟠 | Putuskan: kunci 1 UOM/SKU, atau faktor konversi purchase_uom→stock_uom | Terbuka |
| R22 | **Posisi ban berbeda per tipe truk** (jumlah/axle beda) | M | Sedang | 🟠 | Template posisi per `truck_type` (mis. 4x2, 6x4) | Terbuka |
| R23 | Fitur jual dormant: logika invoice/harga jual belum dirancang detail | M | Sedang | 🟡 | Feature-flag bersih; rancang detail saat diaktifkan; kolom nullable siap | Diterima |

### F. Operasional & Non-Fungsional

| ID | Risiko | L | I | Sev | Mitigasi | Status |
|---|---|---|---|---|---|---|
| R24 | Tabel besar (`stock_movements`, `audit_logs`) → performa turun | M | Sedang | 🟠 | Index tepat; arsip/partisi audit per tanggal; pagination | Terbuka |
| R25 | Concurrency penomoran dokumen (`next_number`) → nomor dobel | M | Sedang | 🟠 | Atomic increment / lock baris sequence dalam transaksi | Terbuka |
| R26 | Belum ada **testing/CI**, **backup/DR**, **monitoring** | Tinggi | Tinggi | 🔴 | Siapkan test (Pest), CI, backup terjadwal, log/monitoring sejak awal | Terbuka |
| R27 | Import data master awal (ribuan part/truk) rawan kotor | M | Sedang | 🟠 | Tool import + validasi + dry-run; staging | Terbuka |
| R28 | Reporting lintas modul berat | L | Sedang | 🟡 | Index; read-model/materialized view bila perlu | Terbuka |

### G. Kepatuhan & Hukum

| ID | Risiko | L | I | Sev | Mitigasi | Status |
|---|---|---|---|---|---|---|
| R29 | PPN/faktur pajak (saat jual aktif) belum tentu sesuai aturan DJP | M | Sedang | 🟠 | Rancang sesuai regulasi saat fitur jual diaktifkan | Diterima |
| R30 | Data pribadi driver/mitra (privasi) | L | Sedang | 🟡 | Akses terbatas; retensi; minimisasi data | Terbuka |

---

## 2. Gap Analysis (yang belum ada / belum diputuskan)

| ID | Area | Diharapkan | Status saat ini | Aksi |
|---|---|---|---|---|
| G1 | Keputusan desain | WAC vs FIFO, feature-flag plan, multi-currency, pajak lain, mechanics scope, cost-per-brand | Terbuka (MODULES §14) | Finalkan sebelum scaffold |
| G2 | Autentikasi | Login, kebijakan sandi, 2FA, sesi | Belum dirancang detail | Rancang modul Auth (Core/Admin) |
| G3 | Notifikasi | Reminder PM/STNK/KIR, WA/email | Disebut di roadmap, tak ada skema | Tabel `notifications` + channel + scheduler |
| G4 | Media/file | Foto inspeksi LKM, lampiran | Belum ada strategi storage | Tentukan disk (S3/local), tabel attachment |
| G5 | Reporting/BI | Laporan lintas modul | Daftar laporan ada, mekanisme belum | Tentukan read-model/query layer |
| G6 | Migrasi data | Import master (part Hino/Isuzu, truk) | Belum ada | Buat importer + template |
| G7 | Kualitas | Testing, CI/CD, environment | Belum ada | Pest + GitHub Actions + .env.example |
| G8 | Operasi | Backup, DR, monitoring, logging | Belum ada | Rencana ops sebelum produksi |
| G9 | Ban | Template posisi ban per tipe truk | Belum ada | Konfigurasi posisi per `truck_type` |
| G10 | Approval | Ambang & alur approval PO | Disebut, belum detail | Aturan approval (nilai/peran) |
| G11 | Scaffold | Laravel 13 + PHP 8.4 + Filament v5 + PostgreSQL | Stack terkunci, belum di-scaffold | Langkah berikutnya |
| G12 | Kontrak HRD | Spesifikasi API (endpoint, auth, payload) | Belum disepakati | Koordinasi tim HRD; dokumen kontrak |
| G13 | UOM | Konversi satuan beli↔simpan (box/pcs/liter) | Belum diputuskan (R37) | Kunci 1 UOM/SKU atau tabel konversi sebelum scaffold Inventory |
| G14 | Kebijakan stok negatif | Boleh/tidaknya `out` melebihi saldo | Belum diputuskan (R38) | Tetapkan policy + enforcement di `StockService` |
| G15 | Job stok | Snapshot harian + retensi/pruning anchor (§7c DATABASE) | Dirancang, belum diimplementasi | Scheduler (Laravel) snapshot + prune; idempotent |

---

## 3. Prioritas Tindak Lanjut (Top)

1. **Finalkan keputusan terbuka** (G1 / MODULES §14) — pondasi skema. 🔴
2. **Kunci pola integritas stok**: `StockService` + locking (R6, R7). 🔴
3. **Multi-tenant guardrails**: trait+scope+observer + test isolasi (R1, R2). 🔴
4. **Sepakati kontrak API HRD** dengan tim HRD (R19, G12). 🔴
5. **Siapkan testing/CI + backup sejak awal** (R26, G7, G8). 🔴
6. **RBAC + enkripsi kredensial** (R12, R13). 🔴
7. Rancang notifikasi & media storage (G3, G4); template posisi ban (G9).

---

## 4. Catatan

- Severity di sini **indikatif** pada tahap desain; tinjau ulang saat implementasi.
- Risiko 🔴 sebaiknya tidak masuk produksi tanpa mitigasi.
- Dokumen ini hidup — perbarui via skill `risk-gap-analysis` tiap modul/keputusan baru.
