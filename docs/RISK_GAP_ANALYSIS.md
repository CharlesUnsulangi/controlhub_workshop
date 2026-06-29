# ControlHub Workshop — Risk & Gap Analysis

> Analisa risiko & kesenjangan (v0.3, transisi perencanaan→scaffold). Berbasis `OVERVIEW.md`,
> `MODULES.md`, `DATABASE.md`, `WORKFLOWS.md`, `IMPLEMENTATION.md`, `MOBILE_MEKANIK.md`.
> Tujuan: temukan risiko & celah **sebelum coding** (saat termurah), lalu **kawal saat
> implementasi**. Tinjau ulang tiap ada keputusan/modul baru.
>
> **v0.3 (2026-06-29):** dua perubahan besar sejak v0.2. **(a) Fase scaffold dimulai** —
> fondasi multi-tenant **sudah di kode** (`Tenancy` + `CompanyScope`/`BranchScope` +
> `IdentifyTenant`, lihat `IMPLEMENTATION.md`); **keputusan tenancy = scope global, BUKAN
> tenancy URL Filament** → **R31 ditutup (Termitigasi)**, tapi muncul **inkonsistensi dok**
> (`OVERVIEW.md §5` masih tulis "Company = tenant Filament"; R73). RBAC **belum** ditegakkan —
> `canAccessPanel()` masih izinkan semua user tenant (R12 konkret). Migrasi **terblok** (DDL
> grant; R70). **(b) Modul AP / Procure-to-Pay** (Kontrabon + Kasir + Giro, `wks_ap_`) masuk
> desain → **Bagian H baru (R62–R66)**: 3-way match, over-pay/alokasi paralel, SoD
> maker→checker & registrar→penanda→pemeriksa giro, dobel faktur/kredit retur. **Transaksi
> gudang baru** (Peminjaman, Retur Pembelian, Retur Bon → R67–R69) + **handheld/PWA mekanik**
> (R56–R58 dipertajam di `MOBILE_MEKANIK.md`). Tambah G19–G23.
>
> **v0.2 (2026-06-25):** review modul Gudang Sparepart → desain ledger stok dirombak
> (saldo fisik vs valuasi dipisah, `qty_in/qty_out`, snapshot saldo harian). Tambah
> R33–R44, G13–G15. Keputusan: **konversi UOM didukung** (R37/G13), **stok negatif
> diizinkan + alert** (R38/R41/G14), **core return wajib** untuk part non-consumable
> (bukti rusak → scrap; R42–R44, tabel `wks_inv_core_returns`/`scrap_disposals`).
> **Pengeluaran sparepart** ber-approval (usul Mekanik → review ServiceAdvisor → keluar
> Gudang; `wks_inv_part_issues`, SoD; R45) tersambung WO→LKM→truck.
> **Surat Jalan MASUK dari supplier** (`wks_po_supplier_deliveries`, per PO; GRN merujuk,
> fallback manual) + **Portal Supplier** panel `/vendor` (akun `users.supplier_id`, fase
> berikutnya; R46/R47, G16). **Sesi Kerja Gudang** **1 siklus/hari per gudang** — Opening =
> **gate masuk panel** (operator buka), Closing = **akhir hari oleh Kepala Gudang/Supervisor**
> (`wks_inv_shift_sessions`; movement ter-tag, snapshot full + anomali; R48/R49/R60).
> **Lapisan Notifikasi** (resolusi G3): `wks_adm_notification_rules`/`wks_adm_notifications`
> (WA/email/in-app, dikonfigurasi di master); konsumen pertama = notif sesi >24 jam (R50, G17).
> **Setting Rak/Lokasi**: `wks_ms_locations` hierarki fleksibel (`parent_id`+`node_type`,
> generator massal, kapasitas soft) + slotting per gudang (`slotting_mode`, `wks_inv_part_locations`; R51).

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
| R31 | **Dua mekanisme tenancy** (tenant Filament + Global Scope `company_id`) tak konsisten | M | Tinggi | 🔴 | **Diputuskan: satu mekanisme = scope global** (`CompanyScope`+`BranchScope` via `IdentifyTenant`), **bukan** tenancy URL Filament; sudah di-scaffold (`IMPLEMENTATION.md §2`). Sisa: tulis test isolasi tenant+branch | **Termitigasi** |
| R32 | Filament v5 relatif baru → paket pihak ketiga/Plugin belum semua matang | M | Sedang | 🟠 | Verifikasi plugin (Shield, dll.) kompatibel v5; pin versi; siapkan fallback | Terbuka |
| R70 | **Migrasi terblok** — user DB `admin25` tak punya hak DDL (`CREATE` di schema `public`) → skema tak bisa dibuat, semua dev domain tertahan | Tinggi | Sedang | 🟠 | DBA jalankan `GRANT CREATE ON SCHEMA public TO admin25` (atau user migrasi terpisah); verifikasi `php artisan migrate` + seed + uji isolasi; dokumentasikan hak DB di runbook ops | Terbuka |
| R71 | **Proliferasi panel (11+ panel Filament)** — tiap panel pasang middleware/auth sendiri (bukan grup web); satu panel lupa `IdentifyTenant`/gate → bocor lintas-tenant atau akses tak sah | M | Tinggi | 🟠 | `IdentifyTenant` **wajib** di `authMiddleware` **tiap** panel (sudah pola di `IMPLEMENTATION.md`); helper/registrasi panel terpusat; test "setiap panel memuat IdentifyTenant + butuh login"; checklist saat tambah panel | Terbuka |
| R73 | **Dok tenancy tak sinkron** — `OVERVIEW.md §5` & catatan tenancy masih sebut "Company = tenant Filament", padahal kode pakai scope global → bikin developer salah implementasi | M | Sedang | 🟡 | Selaraskan `OVERVIEW.md §5` + catatan tenancy ke keputusan scope global (R31); jadikan `IMPLEMENTATION.md` sumber kebenaran fondasi | Terbuka |

### B. Integritas Data Stok & Biaya

| ID | Risiko | L | I | Sev | Mitigasi | Status |
|---|---|---|---|---|---|---|
| R6 | **Stok tidak konsisten** bila mutasi tak lewat `StockService` / ada update langsung | M | Tinggi | 🔴 | Enkapsulasi total di `StockService`; `DB::transaction`; larangan update `stock_items` langsung; rekonsiliasi opname | Termitigasi |
| R7 | **Race condition** WAC/qty saat WO/GRN paralel pakai part sama | M | Tinggi | 🔴 | Pessimistic lock (`SELECT … FOR UPDATE`) pada baris `wks_inv_stock_values` (grain WAC); transaksi pendek | Terbuka |
| R33 | **`avg_cost` sumber ganda** — dulu di `stock_items` (per lokasi) & `spare_parts` (per SKU) → drift WAC | M | Tinggi | 🔴 | **Satu sumber kebenaran**: WAC hanya di `wks_inv_stock_values` (grain warehouse+condition); `stock_items` fisik-saja; `spare_parts.last_cost` cache tampilan | Termitigasi |
| R34 | **Baris saldo duplikat** — `unique(...,location_id,...)` dgn `location_id` NULL dianggap distinct di PG → agregasi stok rusak | M | Tinggi | 🔴 | `UNIQUE NULLS NOT DISTINCT` (PG17) atau lokasi sentinel; berlaku juga di snapshot fisik | Termitigasi |
| R35 | **Opname tak bisa rekonsiliasi per-kondisi** — `opname_items` tanpa `condition` (stok dilacak per new/used/rebuilt) | M | Tinggi | 🔴 | Tambah kolom `condition` (wajib) di `opname_items`; adjustment movement bawa condition | Termitigasi |
| R38 | **Stok negatif / over-reserve** — `out` melebihi saldo; `qty_reserved > qty_on_hand` | M | Sedang | 🟠 | **Kebijakan: izinkan + alert** (tak blokir); `wks_inv_stock_alerts` (negative_stock) + notifikasi Gudang/Admin; tanpa CHECK keras | Termitigasi |
| R41 | **WAC rusak saat saldo ≤ 0** — pembagian qty ≤ 0 (akibat stok negatif diizinkan) | M | Sedang | 🟠 | **Bekukan `avg_cost`** (nilai terakhir) selama qty ≤ 0; hitung ulang saat qty positif; `out` saat negatif pakai WAC beku | Termitigasi |
| R39 | **Double-allocation Surat Jalan** — stok tak di-reserve antara DO draft→delivered | M | Sedang | 🟠 | Reserve `qty_reserved` saat DO dibuat; lepas saat posting/cancel (sejajar reservasi WO) | Terbuka |
| R40 | **Snapshot historis hilang** — pruning harian menghapus anchor → query stok tanggal lama harus scan jauh | L | Sedang | 🟡 | `is_anchor` (akhir bulan) permanen, hanya snapshot harian non-anchor dipangkas | Termitigasi |
| R8 | Reservasi part menggantung saat WO/bon batal (`qty_reserved` tak dilepas) | M | Sedang | 🟠 | Lifecycle reservasi eksplisit; lepas saat `part_issue` rejected/cancelled & WO cancel; job pembersih | Terbuka |
| R48 | **Sesi gudang menggantung** — lupa Tutup Sesi → snapshot closing tak terbentuk, sesi open lintas hari | M | Sedang | 🟠 | Notif **WA+email** bila >24 jam (rule dikonfigurasi di master, eskalasi); force-close Supervisor; **job akhir hari** auto `force_closed`; partial unique **1 open/gudang** + `unique(warehouse_id, business_date)` (1 siklus/hari) | Termitigasi |
| R50 | **Gateway WhatsApp gagal/biaya** — provider down, nomor diblokir, kuota/biaya, pesan tak sampai | M | Sedang | 🟠 | Outbox `wks_adm_notifications` (status/retry); fallback email; channel `database` selalu ada; abstraksi `WaGateway` (ganti provider); monitor gagal-kirim | Terbuka |
| R49 | **Mutasi tak ter-tag sesi** — gerakan stok lolos tanpa `shift_session_id` (akuntabilitas bocor) | M | Sedang | 🟠 | Enforcement **wajib** sesi di `StockService` (blok); override sistem/admin di-audit; `diff_qty` deteksi anomali | Termitigasi |
| R9 | Snapshot harga gagal → biaya berubah retroaktif | L | Tinggi | 🟠 | Copy `unit_cost`/`unit_price` ke item saat create (sudah dirancang) | Termitigasi |
| R10 | Valuasi **part bekas** subyektif (unit_cost taksiran) | M | Sedang | 🟠 | Kebijakan penilaian; approval; kategori cost used terpisah | Terbuka |
| R11 | WAC vs FIFO belum final → koreksi mahal bila berubah setelah live | M | Sedang | 🟠 | Putuskan sekarang (default WAC, MODULES §14) | Terbuka |
| R67 | **Peminjaman part menggantung** — `wks_inv_part_loans` keluar (`loan_out`) tapi tak pernah kembali / tak dikonversi jadi Bon → part hilang dari saldo fisik tapi tetap "aset" (tak dibebankan ke WO) | M | Sedang | 🟠 | Lifecycle loan eksplisit (`open→partially_returned→returned`); `due/expected_return` + alert overdue; job ingatkan; bila tak kembali → **konversi jadi Bon** (pemakaian, bebankan WO); rekonsiliasi `qty_loaned = qty_returned + qty_converted + outstanding` | Terbuka |
| R68 | **Retur pembelian dobel-kredit** — `wks_inv_purchase_returns` posting stok turun + nota retur ke AP, tapi nota dipakai/di-credit **dua kali** (di gudang & lagi di kontrabon) → tagihan supplier salah potong | M | Sedang | 🟠 | Nota retur 1 sumber kebenaran; `credit_amount` AP hanya dari nota `posted`→`credited` (idempoten, status guard); link 1:1 retur→kredit kontrabon; rekonsiliasi AP vs gudang | Terbuka |
| R69 | **Retur Bon salah reverse HPP** — `wks_inv_issue_returns` kembalikan part baru ke stok + reverse biaya WO; risiko reverse ganda atau part bekas/rusak masuk lagi sebagai "baru" | M | Sedang | 🟠 | Hanya part **baru belum terpakai** boleh retur-bon (bukan core/teardown); reverse HPP idempoten (ref `part_issue`); status `draft→posted` sekali; pisahkan tegas dari Core Return (R42/§3 #3) | Terbuka |

### C. Keamanan & Akses

| ID | Risiko | L | I | Sev | Mitigasi | Status |
|---|---|---|---|---|---|---|
| R12 | Endpoint tak cek **RBAC** → akses tak sah | M | Tinggi | 🔴 | Policy per resource; default-deny; test otorisasi. **Konkret (2026-06-29):** `canAccessPanel()` masih **izinkan semua user tenant aktif** (`IMPLEMENTATION.md §3/§4`) → gating panel sensitif (`/kontrabon`, `/kasir`, `/audit`) **belum** ada. Aksi: pasang Shield + gate `canAccessPanel()` per peran **sebelum** modul AP/Audit dipakai | Terbuka |
| R13 | Token integrasi HRD tersimpan plaintext | M | Tinggi | 🔴 | Enkripsi (Laravel encrypted cast); `.env`; rotasi token | Terbuka |
| R14 | Audit log tak mencakup aksi sensitif (adjustment stok, ubah harga, impersonate) | M | Sedang | 🟠 | Audit wajib pada aksi kritis; `wks_core_audit_logs` + `wks_price_histories` | Termitigasi |
| R15 | Upload foto (LKM) tak tervalidasi (tipe/ukuran/malware) | M | Sedang | 🟠 | Validasi mime/size; storage privat; nama acak | Terbuka |
| R45 | **Pengeluaran sparepart tanpa review** — mekanik keluarkan part tanpa persetujuan / setujui sendiri | M | Tinggi | 🟠 | Bon `wks_inv_part_issues` ber-status; **SoD `requested_by ≠ reviewed_by`**; hanya `approved` bisa di-issue; policy per peran (Mekanik usul, ServiceAdvisor review, Gudang issue) | Termitigasi |
| R46 | **Kebocoran data antar-supplier di portal** — supplier lihat PO/SJ supplier lain | M | Tinggi | 🔴 | Scope wajib `supplier_id` (+ company) di semua query panel `/vendor`; policy default-deny; test isolasi supplier (mirip R1) | Terbuka |
| R47 | **Surface eksternal portal supplier** — akun supplier disusupi/abuse (brute force, enumerasi PO) | M | Tinggi | 🟠 | Akun undangan saja (tak open signup); 2FA opsional; rate-limit; audit login; verifikasi email; panel terpisah dari internal | Terbuka |
| R59 | **PMB & LKM tanpa SoD / input tak tepercaya** — satu peran terbitkan PMB sekaligus buat LKM, atau data truk/sopir salah → truk masuk tanpa kontrol | M | Sedang | 🟠 | **PMB modul/panel terpisah** (`wks_pmb_`, `/pmb`): Dispatcher **menerbitkan** PMB ≠ Service Officer **membuat** LKM (SoD via permission per panel); PMB **tidak** auto-terbit LKM (`pmb_id` opsional di LKM); `PmbService` transaksional, `created_by`/`issued_at` & `used_lkm_id` ter-audit; `intake_mode` snapshot di LKM; Dispatcher cocokkan truck/sopir ke master saat terbit | Terbuka |
| R61 | **Audit gudang tak independen / audit mengubah stok** — auditor = operator yang sama, atau temuan langsung meng-adjust saldo (sembunyikan selisih) | M | Sedang | 🟠 | Peran **`Auditor`** + **panel terpisah `/audit`** (SoD: ≠ operator/Kepala Gudang); **read-only stok** — temuan **tak** mengubah saldo, koreksi via opname/penyesuaian tertelusur lalu auditor **verifikasi**; trail **append-only** (`stock_movements` + `wks_core_audit_logs`); auditor tak menutup temuan sbg pelaksana koreksi | Terbuka |
| R60 | **Gate sesi gudang dilewati / closing tanpa kontrol** — kerja gudang tanpa Buka Sesi (gate hanya di UI panel), atau operator menutup sesi sendiri tanpa snapshot terverifikasi | M | Sedang | 🟠 | Gate Opening di **render hook/middleware panel** (blok semua Resource) **+ lapis kedua** `StockService`/`TyreService` tolak movement tanpa sesi `open` (jaga API/PWA); **Closing izin = Kepala Gudang/Supervisor** saja (operator tak bisa), snapshot akhir + `anomaly_count`; `unique(warehouse_id, business_date)` (1 siklus/hari), buka-ulang tanggal sama = override supervisor ter-audit | Terbuka |

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
| R37 | **Tak ada konversi UOM** — beli per box, simpan per pcs → qty/biaya beda satuan | M | Sedang | 🟠 | **Dukung konversi**: `wks_inv_part_uoms` (factor per SKU); UOM dasar utk stok/WAC; dokumen snapshot `uom_factor`; konversi di `StockService` | Termitigasi |
| R51 | **Integritas lokasi** — stok nempel di node header (bukan bin), parent lintas-gudang, lokasi terhapus masih dipakai | M | Sedang | 🟠 | Validasi `stock_items.location_id` → hanya `is_storable=true` (bin); `parent_id` se-warehouse; FK restrict + `is_active`; regen `full_path` saat pindah | Terbuka |
| R22 | **Posisi ban berbeda per tipe truk** (jumlah/axle beda) | M | Sedang | 🟠 | Master `wks_ms_axle_positions` per `axle_config`; posisi instalasi divalidasi + diagram layout | Termitigasi |
| R52 | **Integritas posisi ban** — dua ban di slot sama / satu ban terpasang ganda | M | Tinggi | 🟠 | Partial-unique `(truck_id, position) WHERE removed_at IS NULL` + `(tyre_id) WHERE removed_at IS NULL` di `wks_tyre_installations` | Termitigasi |
| R53 | **Sesi gudang terpadu** — mutasi ban tak ter-tag `shift_session_id` → akuntabilitas/anomali bolong | M | Sedang | 🟠 | `TyreService` tolak movement tanpa sesi `open` (sama spt `StockService`); snapshot kehadiran ban (`wks_tyre_shift_session_tyres`) saat closing | Termitigasi |
| R54 | **Valuasi & biaya/KM ban** — per-unit (tanpa WAC); `book_value`/`total_km_run` basi bila tak di-update di transaksi | M | Sedang | 🟠 | Update `book_value` (acquired+Σretread) & `total_km_run` di `TyreService` saat tutup instalasi/terima retread dalam transaksi terkunci | Terbuka |
| R55 | **Ban WIP di vulkanisir (off-site)** — `retreading` di supplier, lama tak kembali / hilang | M | Sedang | 🟠 | Status `retreading` keluar saldo gudang; alert `retread_overdue`; `delivery_order_id` (surat jalan kirim); blok `retread_count ≥ retread_max` | Terbuka |
| R23 | Fitur jual dormant: logika invoice/harga jual belum dirancang detail | M | Sedang | 🟡 | Feature-flag bersih; rancang detail saat diaktifkan; kolom nullable siap | Diterima |
| R42 | **Core return tak ditegakkan** → part baru keluar tanpa bukti bekas (fraud) | M | Tinggi | 🟠 | Gate tutup WO: non-consumable wajib `wks_inv_core_returns` 1:1 (qty cocok); `categories.is_consumable` | Termitigasi |
| R43 | **Klasifikasi consumable salah** → part rusak penting tak diminta core, atau consumable malah diminta | M | Sedang | 🟠 | Tetapkan `is_consumable` per kategori saat setup master; review; override per-SKU bila perlu | Terbuka |
| R44 | **Bukti core lemah** — tanpa foto/alasan, bukti rusak tak kuat; storage foto belum ada (G4) | M | Sedang | 🟠 | `failure_reason` wajib + `photo_path` (tergantung strategi media G4); retensi sebelum scrap | Terbuka |
| R56 | **Sync offline ganda/konflik** — handheld PWA kirim event berulang / overlap saat reconnect → jam kerja dobel atau segmen menggantung | M | Tinggi | 🟠 | `unique(company_id, client_event_id)` (idempoten); guard 1-segmen-aktif/mekanik; server tutup segmen menggantung saat shift_end; konflik ditandai untuk ditinjau KepalaMekanik | Termitigasi |
| R57 | **Manipulasi jam kerja** — mekanik andalkan waktu klien / lupa clock-out → aktual menggelembung | M | Sedang | 🟠 | Timestamp **dari server**, bukan klien; auto-close segmen di akhir shift + `pause_reason=shift_end`; anomali (durasi > ambang) → alert; aktual vs estimasi dimonitor | Terbuka |
| R58 | **Biaya jasa: basis std vs aktual** — `actual_minutes` ≠ `est_hours` katalog; ambiguitas dasar `total_cost` jasa | L | Sedang | 🟡 | **Keputusan:** biaya jasa tetap dari `services.std_cost` (mode internal); `actual_minutes` untuk **produktivitas**, bukan biaya — kecuali ditetapkan tarif/jam (G18) | Terbuka |

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

### H. Hutang Supplier / Procure-to-Pay & Kasir (`wks_ap_`)

| ID | Risiko | L | I | Sev | Mitigasi | Status |
|---|---|---|---|---|---|---|
| R62 | **Hutang fiktif / 3-way match dilewati** — kontrabon di-`approve` tanpa cocok GRN/PO/SJ/faktur pajak (apalagi `po_id`/`goods_receipt_id` **nullable** karena AP dibangun sebelum PO/GRN matang) → akui/bayar tagihan yang barangnya tak diterima | M | Tinggi | 🔴 | Checklist 4 per baris (`chk_goods_received/_delivery_note/_tax_invoice/_po_match`); **gate verify**: header `verified` hanya bila semua baris `ok`; `approved`≠`verified` (SoD); saat PO/GRN aktif, **wajibkan** ref + tegakkan 3-way; audit approve | Terbuka |
| R63 | **Giro keluar tak sesuai sistem / kebocoran kas** — giro fisik ditandatangani & diserahkan tanpa cocok nilai/penerima di sistem, atau langkah verifikasi dilompati | M | Tinggi | 🔴 | Alur status terkunci `registered→printed→signed→verified→released→cleared/bounced`; **verifikasi giro fisik vs sistem via print** sebelum serah; **SoD berlapis** `registered_by`(Kasir)≠`signed_by`≠`verified_by`; `released` baru men-`posted` payment; audit tiap transisi | Terbuka |
| R64 | **Over-payment / over-allocation paralel** — dua Request Pembayaran alokasikan ke kontrabon sama; `paid_amount` (cache) telat → total bayar > `outstanding` | M | Tinggi | 🟠 | Guard `amount ≤ outstanding` **di dalam transaksi terkunci** (lock baris kontrabon, mirip WAC R7); `outstanding` GENERATED (total−credit−paid); `unique(payment_request_id, kontrabon_id)`; `ApService` idempoten; rekonsiliasi `paid_amount` vs Σ payment `posted` | Terbuka |
| R65 | **SoD maker→checker dilewati** — pengaju Request Pembayaran = penyetuju (checker), atau verifikator kontrabon = approver, terutama selama peran Finance/AP & Kasir **belum di-provisioning** (gate `canAccessPanel` masih izinkan semua, R12) | M | Tinggi | 🟠 | Enforce `requested_by ≠ approved_by` & `verified_by ≠ approved_by` di service (bukan hanya UI); permission Shield per panel `/kontrabon` & `/kasir`; **jangan** aktifkan pembayaran sebelum RBAC terpasang | Terbuka |
| R66 | **Dobel-input faktur / kredit retur ganda** — faktur supplier sama masuk 2 kontrabon, atau nota retur mengurangi tagihan lebih dari sekali | M | Sedang | 🟠 | `unique(company_id, supplier_id, supplier_invoice_no)` di baris kontrabon; `credit_amount` hanya dari nota retur `posted`→`credited` sekali (status guard, idempoten — lihat R68); review aging | Termitigasi |

---

## 2. Gap Analysis (yang belum ada / belum diputuskan)

| ID | Area | Diharapkan | Status saat ini | Aksi |
|---|---|---|---|---|
| G1 | Keputusan desain | WAC vs FIFO, feature-flag plan, multi-currency, pajak lain, mechanics scope, cost-per-brand | Terbuka (MODULES §14) | Finalkan sebelum scaffold |
| G2 | Autentikasi | Login, kebijakan sandi, 2FA, sesi | Belum dirancang detail | Rancang modul Auth (Core/Admin) |
| G3 | Notifikasi | Reminder PM/STNK/KIR, WA/email, stok, sesi gudang | **Skema dirancang**: `wks_adm_notification_rules` + `wks_adm_notifications` + channel (mail/WA/db) + scheduler | Implementasi job + `WaGateway`; **pilih provider WA** (G17) |
| G17 | Provider WA | Penyedia WhatsApp gateway (Fonnte/Wablas/Meta Cloud API/dll.) | Belum dipilih (abstraksi `WaGateway` siap; config `integrations.php`) | Pilih provider + kredensial saat fase notifikasi aktif (R50) |
| G4 | Media/file | Foto inspeksi LKM, lampiran | Belum ada strategi storage | Tentukan disk (S3/local), tabel attachment |
| G5 | Reporting/BI | Laporan lintas modul | Daftar laporan ada, mekanisme belum | Tentukan read-model/query layer |
| G6 | Migrasi data | Import master (part Hino/Isuzu, truk) | Belum ada | Buat importer + template |
| G7 | Kualitas | Testing, CI/CD, environment | Belum ada | Pest + GitHub Actions + .env.example |
| G8 | Operasi | Backup, DR, monitoring, logging | Belum ada | Rencana ops sebelum produksi |
| G9 | Ban | Template posisi ban per tipe truk | Belum ada | Konfigurasi posisi per `truck_type` |
| G10 | Approval | Ambang & alur approval PO | Disebut, belum detail | Aturan approval (nilai/peran) |
| G11 | Scaffold | Laravel 13 + PHP 8.4 + Filament v5 + PostgreSQL | Stack terkunci, belum di-scaffold | Langkah berikutnya |
| G12 | Kontrak HRD | Spesifikasi API (endpoint, auth, payload) | Belum disepakati | Koordinasi tim HRD; dokumen kontrak |
| G13 | UOM | Konversi satuan beli↔simpan (box/pcs/liter) | **Diputuskan: dukung konversi** (R37) — `wks_inv_part_uoms` dirancang | Implementasi di `StockService` saat scaffold Inventory |
| G14 | Kebijakan stok negatif | Boleh/tidaknya `out` melebihi saldo | **Diputuskan: izinkan + alert** (R38/R41) — `wks_inv_stock_alerts` dirancang | Implementasi alert+notifikasi (tergantung G3) |
| G15 | Job stok | Snapshot harian + retensi/pruning anchor (§7c DATABASE) | Dirancang, belum diimplementasi | Scheduler (Laravel) snapshot + prune; idempotent |
| G16 | Portal Supplier | Panel `/vendor` (login supplier, daftar PO, register SJ masuk) | Skema dirancang (`wks_po_supplier_deliveries`, `users.supplier_id`); UI/auth **fase berikutnya** | Bangun panel Filament + guard + scope `supplier_id` + test isolasi (R46/R47) saat fase aktif |
| G18 | Handheld mekanik | Jam kerja per-task (clock in/out) + **PWA offline** | **Skema + UX dirancang** (`MOBILE_MEKANIK.md`): `wks_svc_wo_tasks`/`task_assignments`/`task_time_logs` + `TaskTimeService` + API `/svc/*`; **Fase 1 online-first**, offline (IndexedDB+SW) Fase 2 | Bangun PWA mobile-first + panel Mekanik supervisor + API Sanctum; offline-queue/sync idempoten Fase 2 (R56/R57); putuskan tarif/jam bila biaya jasa berbasis aktual (R58); **kolom `action_type`/`action_ref`/`client_event_id` di `wo_task_steps` belum ada di DATABASE** |
| G19 | Scaffold fondasi | Multi-tenant di kode + migrasi jalan | **Fondasi tenancy SUDAH di kode** (scope global, `IMPLEMENTATION.md §2`); **migrasi belum jalan** (DDL grant, R70) | DBA `GRANT CREATE`; `migrate`+seed+**uji isolasi tenant/branch** (tutup R31); selaraskan dok tenancy (R73) |
| G20 | RBAC & gating panel | Shield + Policy + `canAccessPanel()` per peran | **Belum** — `canAccessPanel()` izinkan semua user tenant (R12); 11+ panel direncana, baru 4 provider ada | Pasang Shield; gate panel sensitif (`/kontrabon`,`/kasir`,`/audit`) per peran **sebelum** AP/Audit dipakai (R12/R65/R71) |
| G21 | Modul AP (`wks_ap_`) | Kontrabon + Kasir + Giro + `ApService` | **Desain final** (DATABASE §7d, MODULES §17); tabel/service **belum** (menyusul setelah PO/GRN) | Implementasi `ApService` dgn guard 3-way/over-pay/SoD/giro (R62–R66) dalam transaksi terkunci; bangun setelah `wks_po_`/GRN |
| G22 | Transaksi gudang baru | Loan, Retur Pembelian, Retur Bon | **Desain** (MODULES §8 #5/#7/#8); belum diimplementasi | Implementasi di `StockService` dgn lifecycle/alert (R67–R69) saat scaffold Inventory |

---

## 3. Prioritas Tindak Lanjut (Top)

1. **Buka blocker migrasi** (R70, G19): `GRANT CREATE` → `migrate` + seed + **uji isolasi tenant/branch** (membuktikan R31/R1/R2 di kode). 🔴
2. **RBAC + gating panel sebelum modul sensitif**: Shield + `canAccessPanel()` per peran untuk `/kontrabon`,`/kasir`,`/audit` (R12, R65, R71, G20). 🔴
3. **Kunci pola integritas stok & uang**: `StockService`/`ApService` + locking baris (WAC R7, over-pay AP R64). 🔴
4. **Kontrol keuangan AP sebelum live**: 3-way match (R62) & SoD giro berlapis (R63) — **tak boleh produksi tanpa ini**. 🔴
5. **Finalkan keputusan terbuka** (G1 / MODULES §14) yang masih mengganjal skema (WAC vs FIFO, dll.). 🔴
6. **Sepakati kontrak API HRD** dengan tim HRD (R19, G12). 🔴
7. **Siapkan testing/CI + backup sejak awal** (R26, G7, G8); enkripsi kredensial (R13). 🔴
8. **Selaraskan dok tenancy** (R73, G19) — `OVERVIEW.md §5` vs kode.
9. Lifecycle transaksi gudang baru (loan/retur, R67–R69, G22); notifikasi & media (G3, G4); posisi ban (G9).

---

## 4. Catatan

- Severity di sini **indikatif** pada tahap desain; tinjau ulang saat implementasi.
- Risiko 🔴 sebaiknya tidak masuk produksi tanpa mitigasi.
- Dokumen ini hidup — perbarui via skill `risk-gap-analysis` tiap modul/keputusan baru.
