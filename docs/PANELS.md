# ControlHub Workshop — Desain Panel (Filament v5)

> Perencanaan (v0.2). Menjembatani **`SITEMAP.md`** (halaman/route) dan skill
> **`filament-ui`** (konvensi Filament) menjadi *arsitektur panel* konkret.
>
> **Keputusan terkunci:** panel diwujudkan sebagai **panel Filament TERPISAH per
> fungsi** (multi-panel), bukan satu panel App dengan menu terfilter. Tiap fungsi
> punya `PanelProvider` + URL sendiri sehingga tiap peran login ke ruang kerja fokus.
>
> ⚠️ **Filament v5 baru** — semua nama method (`->tenant()`, `->navigationGroups()`,
> `canAccessPanel()`, dsb.) **wajib diverifikasi ke dokumentasi v5** sebelum ditulis
> (RISK R32). Dokumen ini soal *keputusan & pemetaan*, bukan kutipan API.

---

## 0. Ikhtisar — Daftar Panel

| Panel | id | Path | Provider | Tenant | Peran utama | Modul (sub-prefix) | Status |
|---|---|---|---|---|---|---|---|
| **Launcher** (hub depan) | `launcher` | `/home` | `LauncherPanelProvider` | pilih Company | semua user tenant | — | ✅ |
| **System / Core** | `system` | `/system` | `SystemPanelProvider` | ❌ | SuperAdmin, SystemSupport | `wks_core_` | ✅ |
| **PMB** (pengantar dispatcher) | `pmb` | `/pmb` | `PmbPanelProvider` | ✅ Company | Dispatcher | `wks_pmb_` | ✅ |
| **LKM** | `lkm` | `/lkm` | `LkmPanelProvider` | ✅ Company | ServiceAdvisor (Service Officer), Gate/Satpam | `wks_lkm_` | ✅ |
| **Servis** ⚑ | `servis` | `/servis` | `ServisPanelProvider` | ✅ Company | ServiceAdvisor, KepalaMekanik | `wks_svc_` | ✅ |
| **Mekanik** | `mekanik` | `/mekanik` | `MekanikPanelProvider` | ✅ Company | Mekanik, KepalaMekanik | `wks_svc_` (subset) | ✅ |
| **Gudang** | `gudang` | `/gudang` | `GudangPanelProvider` | ✅ Company | Gudang, GudangBan | `wks_inv_` + `wks_tyre_` | ✅ |
| **Audit** (kontrol gudang) | `audit` | `/audit` | `AuditPanelProvider` | ✅ Company | Auditor (Internal Audit) | `wks_inv_` (audit/read) | ✅ scaffold |
| **Purchasing** | `purchasing` | `/purchasing` | `PurchasingPanelProvider` | ✅ Company | Purchasing | `wks_po_` + `wks_price_` | ✅ |
| **Kontrabon** | `kontrabon` | `/kontrabon` | `KontrabonPanelProvider` | ✅ Company | Finance/AP | `wks_ap_` | ✅ scaffold |
| **Kasir** | `kasir` | `/kasir` | `KasirPanelProvider` | ✅ Company | Kasir (bayar supplier) | `wks_ap_` | ✅ scaffold |
| **Admin** | `admin` | `/admin` | `AdminPanelProvider` | ✅ Company | Owner, Admin | `wks_adm_` + `wks_ms_` | ✅ |
| **Vendor / Supplier** | `vendor` | `/vendor` | `VendorPanelProvider` | ✅ (scope `supplier_id`) | Supplier | `wks_po_` (read + SJ) | 🔜 web isi SJ (feature-flag) |

⚑ **Servis belum disebut** di daftar panel yang Anda berikan — saya sertakan karena
modul Work Order adalah *muara* LKM (LKM → WO) dan inti pencatatan biaya. **Konfirmasi**:
dipertahankan sebagai panel sendiri, atau digabung (mis. ke LKM)?

✅ **Kasir & Kontrabon = modul Hutang Supplier (AP) `wks_ap_`** — **panel terpisah sudah
di-scaffold** (`KontrabonPanelProvider` & `KasirPanelProvider` terdaftar di
`bootstrap/providers.php`). Skema/alur dirinci: MODULES §17, DATABASE §7d, WORKFLOWS §4c.
**Resource/tabel dibangun setelah Purchasing/GRN ada** (lihat §9).

### Alur antar panel (procure-to-pay + servis)
```
 PMB (pengantar Dispatcher) ┄┄opsional┄┄► LKM (truk masuk)
        ┌─ Admin (master & setting) ──────────────────────────────────┐
        │                                                             │
 LKM ──► Servis (WO) ──► Gudang (issue part/ban, sesi, GRN terima)    │ Purchasing (PO) ─► Gudang (GRN ─► stok)
        │   ▲                 ▲                                       │        │
        │   └─ Mekanik (update pekerjaan: status, jam, usul Bon, pasang ban)   │
        └─ biaya per unit     └── stok masuk dari Purchasing ─────────┘        ▼
                                                                       Kontrabon (faktur supplier)
                                                                              │
                                                                              ▼
                                                                        Kasir (bayar supplier)
```

---

## 1. Prinsip Desain (Multi-Panel)

1. **Satu `PanelProvider` per panel**, didaftarkan di `bootstrap/providers.php`.
2. **Satu tabel `users`, satu guard `web`.** Pemisahan panel lewat
   `User::canAccessPanel(Panel $panel): bool` (cek peran + `company_id`/`supplier_id`/
   `is_super_admin`). **Pasca-login → Launcher (`/home`, §1b)** sebagai hub & panel
   switcher; switcher juga tersedia di topbar tiap panel agar bisa pindah tanpa kembali.
3. **Tenancy = Company, satu sumber kebenaran.** Semua panel operasional memanggil
   `->tenant(Company::class)` dan bergantung pada **satu** trait `BelongsToCompany`
   (global scope `company_id`). Jangan tiap panel menyetel tenant dengan cara berbeda
   (RISK **R31 🔴**). Multi-panel berbagi mekanisme tenancy yang **sama**.
4. **Branch ≠ tenant.** Company = tenant Filament. **Branch** = pemilih di header
   (render hook bersama) yang menyetel `session('active_branch_id')` → dibaca trait
   `BelongsToBranch`. Bukan tenant kedua (hindari benturan R31). Muncul di panel
   operasional (PMB, LKM, Servis, Gudang, Audit, Purchasing, Kontrabon, Kasir).
5. **Logika bisnis di Service, bukan Resource.** Aksi berstatus/berdampak stok/uang
   memanggil `StockService`/`TyreService`/`PricingService`/(`ApService` menyusul) dalam
   `DB::transaction()`, idempoten (disable tombol setelah `posted`).
6. **Feature-flag Core menyembunyikan panel/Resource** bila modul dimatikan untuk
   company (`wks_core_company_modules`). Field *(future/dormant)* juga via flag.
7. **Otorisasi Policy + Shield**; permission per Resource (`shield:generate`).
8. **Namespace per panel:** `App\Filament\<Panel>\Resources\…`
   (`Pmb`, `Lkm`, `Servis`, `Gudang`, `Audit`, `Purchasing`, `Kontrabon`, `Kasir`, `Admin`,
   `System`, `Vendor`). Kode tanpa prefix `wks` (`NAMING_CONVENTIONS §3`).

> **Catatan reuse:** beberapa Resource dipakai di lebih dari satu panel (mis. PO
> read-only di Vendor; Surat Jalan keluar dilihat dari Gudang). Bagikan lewat base
> Resource/komponen, tapi **registrasi & otorisasi tetap per panel**.

---

## 1b. Panel LAUNCHER (`/home`) — Hub Depan

Halaman depan pasca-login: **memilih & meluncurkan** panel yang berhak diakses user,
sekaligus menyetel **konteks** (company & branch). Karena fungsi dipecah ke banyak
panel terpisah, launcher = titik masuk tunggal + panel switcher.

- **Provider:** `LauncherPanelProvider` — panel Filament **ringan tanpa Resource**,
  path `/home` (tujuan redirect default setelah login). Isi = satu custom **Page**
  berisi **kartu/tile per panel** (ikon · label · deskripsi singkat · badge peran),
  grid responsif.
- **Hanya menampilkan tile** panel yang `canAccessPanel()`-nya `true` bagi user
  (dan modulnya tidak dimatikan untuk company — feature-flag Core).
- **Pemilih Company (tenant):** bila user tergabung di >1 company, pilih dulu di sini;
  tile **deep-link** ke panel ter-scope company terpilih. Bila 1 company → otomatis.
- **Pemilih Branch:** set `session('active_branch_id')` di sini (berlaku ke semua panel
  operasional via `BelongsToBranch`).
- **Bar global:** logo company · notifikasi in-app · menu user (profil, ubah password,
  logout). Bar ini + **panel switcher** juga muncul di topbar tiap panel.
- **Aksi cepat lintas panel:** **+ PMB · + LKM · + Work Order · + PO** (deep-link ke create
  di panel terkait; **+ PMB** hanya untuk Dispatcher pada mode `dispatcher_permit`).
- *(Opsional)* ringkasan ringan lintas panel (read-only, tap → buka panel): WO aktif ·
  stok kritis · sesi gudang belum ditutup · PO outstanding · kontrabon jatuh tempo.

**Routing / redirect:**
- Login → `/home` (Launcher).
- **Super-admin** → langsung `/system` (tak butuh hub tenant); tetap bisa ke `/home`
  bila juga punya akses tenant (mis. saat impersonate).
- **Supplier** → langsung `/vendor` (panel tunggal).
- *(Opsional, via setting)* user yang hanya berhak **satu** panel tenant → auto-redirect
  ke panel itu, lewati launcher.

> **Catatan tenancy:** launcher **tidak** men-scope ke satu company seperti panel
> operasional; ia justru *memilih* tenant aktif lalu mengarahkan. Tetap satu sumber
> kebenaran `BelongsToCompany` (R31) — launcher hanya **menetapkan** konteks, panel
> tujuan yang memberlakukan scope.

---

## 2. Panel SYSTEM / CORE (`/system`) — tanpa tenant

Super-admin penyedia aplikasi, **lintas tenant**. `canAccessPanel()` hanya bila
`is_super_admin` (`company_id = null`). Tidak ber-`->tenant()`.

| Grup | Resource / Page |
|---|---|
| Tenant | CompanyResource (provisioning, suspend) · Action **Impersonate** (ber-audit) · Modules per company (flag) · Subscription *(opsional)* |
| Katalog Sistem | PlanResource *(opsional)* · ModuleResource |
| Pengguna Sistem | SystemUserResource (`users` where `company_id` null) |
| Audit | AuditLogResource (read-only) |
| Pengaturan | Global Settings Page |

Dashboard: jumlah tenant (aktif/suspend), tenant baru, audit terakhir, status
integrasi (HRD/WhatsApp `enabled`).

---

## 2b. Panel PMB (`/pmb`) — Pengantar Dispatcher

Pos **Dispatcher**: menerbitkan **Permintaan Mobil Masuk** (surat pengantar bernomor)
saat driver datang minta truk masuk bengkel. **Panel terpisah dari LKM** (peran & fungsi
beda). Peran: **Dispatcher saja**. **Tampil hanya** bila company memakai mode
`lkm_intake_mode = dispatcher_permit` (feature-flag setting Admin §10).

| Grup | Resource / Page (Action) |
|---|---|
| **Permintaan Mobil Masuk** | PmbResource — Form Dispatcher (cocokkan truck/plat + sopir ke master · keluhan/tujuan) → **Terbitkan** → `issued` |
| | View **Antrian PMB Aktif** (`status=issued`, branch aktif) |
| | Action **Cetak PMB** (surat bernomor, dibawa driver ke gate) |
| | Action **Batalkan** (`note` wajib → `cancelled`) |
| | Kolom telusur: **LKM terkait** (terisi bila PMB sudah dipakai Service Officer → `used`) |

> **Independen dari LKM:** PMB **tidak** menerbitkan LKM. LKM dibuat Service Officer di
> panel LKM (§3) yang **merujuk** PMB (`pmb_id` opsional). **SoD:** Dispatcher menerbitkan
> PMB ≠ Service Officer membuat LKM. Aksi via `PmbService` + `DB::transaction()`, idempoten.

Dashboard: PMB diterbitkan hari ini, antrian PMB aktif (belum dipakai), PMB dibatalkan.
Aksi cepat: **+ PMB**.

---

## 3. Panel LKM (`/lkm`)

Penerimaan truk **benar-benar masuk** (gate-in) — "pintu depan" pencatatan servis.
Peran: **ServiceAdvisor (Service Officer)**, **Gate/Satpam**. *(Dispatcher tidak di sini —
ia di panel PMB §2b.)*

**Dua mode penerimaan** — setting company `lkm_intake_mode` (panel Admin §10):
- **`direct`** — Gate/Satpam langsung buat LKM (`entered`, tanpa pengantar, `pmb_id` null).
- **`dispatcher_permit`** — driver lebih dulu ambil **PMB** di pos Dispatcher (panel PMB
  §2b). Service Officer **cek PMB**; bila ada → buat LKM merujuk PMB (`pmb_id` terisi,
  PMB ditandai `used`). Tidak ada auto-terbit dari PMB.

| Grup | Resource / Page (Action) |
|---|---|
| Kendaraan Masuk | LkmEntryResource (filter status/tanggal/branch; `intake_mode`) |
| | **(mode `dispatcher_permit`)** Action **Cari/Rujuk PMB** — Service Officer cari PMB `issued` di sistem (by **no PMB** / **plat** / scan), pilih → form LKM **ter-prefill** dari PMB (truk · customer · sopir · keluhan) |
| | Action **Buat LKM** (`entered`) — lengkapi **KM masuk** + inspeksi; **mode dispatcher: pilih dari hasil cari PMB** (set `pmb_id`, PMB → `used`); **mode direct: input manual** (`pmb_id` null) |
| | RM **Inspeksi** (checklist + foto) |
| | Action **Buat Work Order** → panel Servis |
| | Action **Gate-out** (+ surat jalan unit) |
| Laporan | Page: Turnaround (lama unit di bengkel) |

> **Pencarian referensi PMB (mode dispatcher):** saat truk via PMB tiba, Service Officer
> **tidak mengetik ulang** data — ia **mencari PMB** yang sudah diterbitkan Dispatcher
> (status `issued`, branch aktif). Match → prefill + tautkan `pmb_id` + tandai PMB `used`
> dalam satu transaksi (idempoten, anti dobel-pakai PMB). PMB tak ketemu = belum/ tak ada
> pengantar → tindak lanjut sesuai SOP (mis. minta driver kembali ke Dispatcher).

**SoD:** Dispatcher terbitkan PMB (panel §2b) ≠ Service Officer buat LKM (panel ini).

Dashboard: kendaraan masuk hari ini, status (Masuk/Diproses/Selesai/Keluar),
unit menunggu gate-out, **PMB tersedia belum dipakai** (mode dispatcher). Aksi cepat: **+ LKM**.

---

## 4. Panel SERVIS (`/servis`) ⚑

Perencanaan & supervisi Work Order: buat WO dari LKM, **co-susun WO Plan**, **tugaskan
mekanik**, QC, rekap biaya. Peran: ServiceAdvisor, KepalaMekanik. *(Eksekusi & update
lapangan oleh mekanik ada di panel **Mekanik**, §4b.)* Catat **biaya (HPP)** per unit
(mode internal). **Fase sekarang: TANPA estimasi biaya** — biaya = pemakaian aktual.

| Grup | Resource / Page (Action) |
|---|---|
| Work Order | WorkOrderResource (papan status/kanban) · RM items (jasa/part/ban — **biaya aktual**, tanpa estimasi biaya) |
| **WO Plan (Rencana Kerja)** | RM **Task** (`wks_svc_wo_tasks`, ber-seq) — *apa yang dikerjakan* |
| | RM **Langkah/Step per task** (`wks_svc_wo_task_steps`, ber-seq) — *bagaimana mengerjakan* (mis. Ganti ban → turunkan · periksa · pasang baru · kembalikan lama · cek tekanan) |
| | Action **Salin Template** langkah dari jasa katalog (`wks_svc_service_steps`) → prefill |
| | Action **Tugaskan Mekanik** (assignment → muncul di panel Mekanik) |
| | Action **QC / Selesai** (blok bila **core return** belum kembali) |
| Katalog Jasa | ServiceResource · RM **Template Langkah** (`wks_svc_service_steps` — prosedur standar) |
| Servis Berkala | PmScheduleResource · Page: Due/terlambat |
| Biaya | Page: Rekap biaya per WO & per unit (part HPP + ban + jasa) |
| *(future/dormant)* | InvoiceResource · PaymentResource (harga jual ke customer — non-aktif) |

> **WO Plan disusun Mekanik (bisa bersama Service Officer) setelah mekanik mengambil WO** —
> lihat panel Mekanik (§4b). Di panel Servis ini SO/Kepala Mekanik **ikut menyusun &
> meninjau** (co-author + supervisi), bukan satu-satunya perancang. Mekanik mengeksekusi &
> mencentang langkah di §4b, boleh tambah `adhoc`. Langkah = checklist prosedur, **bukan**
> pengganti pengukuran jam (clock tetap per task).

Dashboard: WO aktif per status, menunggu part, beban per mekanik, PM jatuh tempo,
core return tertunggak, **langkah plan belum tuntas**. Aksi cepat: **+ Work Order**.

---

## 4b. Panel MEKANIK (`/mekanik`) — update pekerjaan

Ruang kerja **ringkas (mobile/tablet-friendly)** bagi mekanik untuk **mengambil WO,
menyusun WO Plan, lalu memperbarui pekerjaan** di lapangan. Menampilkan WO yang
**ditugaskan/diambil dirinya** (scope `mechanic_id`/assignment) — bukan semua WO. Peran:
Mekanik, KepalaMekanik. **Alur:** ambil WO → susun plan (task + langkah, bisa bersama
Service Officer) → eksekusi & centang langkah.

| Grup | Resource / Page (Action) |
|---|---|
| **WO Saya** | Action **Ambil WO** / terima penugasan → WO masuk papan saya (scope `mechanic_id`/assignment) |
| **Tugas Saya** (papan) | TaskResource (`wks_svc_wo_tasks` subset: di-assign ke saya; filter status/WO) |
| **Susun WO Plan** *(setelah ambil WO)* | **Buat/atur Task** + **Langkah/step** (`wks_svc_wo_task_steps`, ber-seq) — mekanik menyusun, **bisa bersama Service Officer** |
| | Action **Salin Template** langkah dari jasa katalog (`wks_svc_service_steps`) → prefill, lalu sesuaikan |
| | Action **Clock in/out**: **Mulai · Jeda · Lanjut · Selesai** (timer berjalan; segmen `task_time_logs`) |
| **Langkah Kerja (eksekusi)** | **Checklist langkah** task: **centang** tiap langkah (`done`/`skipped` + catatan) |
| | Action **+ Langkah Adhoc** — tambah langkah baru saat temukan pekerjaan di lapangan (`source=adhoc`) |
| | Action **Selesai** task + isi `result_note` · lihat **estimasi vs aktual** (peringatan bila langkah masih `pending`) |
| **Part** | Action **Usul Bon Part** → Bon Pengeluaran (review SO di Servis, keluar di Gudang) |
| | Action **Input Core Return** (part bekas rusak saat pasang part baru non-consumable) |
| **Ban** | Action **Pasang/Lepas Ban** → `TyreService` · lihat **layout posisi axle** unit |
| **Referensi** | read-only: detail unit, riwayat servis, spesifikasi |

**Dua jalur handheld** — ✅ **diputuskan (2026-06):** **(b) PWA mobile-first = antarmuka UTAMA
mekanik**; **(a) panel Filament Mekanik = view supervisor/KepalaMekanik**. **Fase 1 online-first**,
arsitektur **siap offline** (event idempoten `client_event_id` dipakai sejak awal). **Desain UX
mobile, wireframe, & kontrak endpoint per layar → [MOBILE_MEKANIK.md](MOBILE_MEKANIK.md).**
- **(a) Panel Filament Mekanik responsif** — layout ringkas; **view supervisor** (lihat banyak WO,
  override). Reuse RBAC/tenancy/Policy. Butuh online.
- **(b) API + PWA mobile-first** — endpoint `/api/svc/*` (auth Sanctum, scope assignment) + PWA
  dengan **antrian event lokal** & **sync idempoten** (`client_event_id`) agar tahan putus
  koneksi. Endpoint inti: `GET my-tasks`, `POST tasks/{id}/start|pause|resume|complete`,
  `GET tasks/{id}/steps`, `POST steps/{id}/check` (done/skip + note), `POST tasks/{id}/steps`
  (tambah adhoc), `POST time-logs/sync` (batch). **Service yang sama** (`TaskTimeService` +
  `WoPlanService` untuk langkah) dipakai panel & API agar aturan (guard 1-segmen-aktif,
  recompute `actual_minutes`, urutan/`adhoc` langkah) konsisten. Centang langkah ikut
  diantre & **sync idempoten** (`client_event_id`) seperti time-log.

**Pemisahan tugas (SoD):** Mekanik **menyusun & update plan/task untuk WO miliknya** (scope
assignment) = **pengusul** Bon part (pengusul ≠ reviewer ≠ pengeluar). Menyusun plan
(task + langkah) **boleh**, tapi **tidak** bisa mengubah **biaya** atau **menutup
WO** (QC final di panel Servis). **Langkah `adhoc`** boleh ditambah mekanik (checklist
prosedur), tapi bila berimplikasi **part/jasa baru** tetap lewat **Bon (review SA)** / item
WO — langkah sendiri **tak menambah biaya** langsung.

Dashboard: tugas saya hari ini per status, **timer aktif**, **langkah belum dicentang**,
menunggu part, ban perlu dipasang, **akumulasi jam kerja (aktual vs estimasi)**. Aksi cepat:
**Mulai/Jeda/Selesai**, **Centang Langkah**, **Usul Part**.

> Layout ringkas untuk perangkat bengkel (selaras catatan mobile di `SITEMAP §D`).
> Timestamp jam kerja **selalu dari server** (anti-manipulasi); klien hanya kirim event.

---

## 5. Panel GUDANG (`/gudang`) — Sparepart + Ban + Sesi Kerja

Stok sparepart (`wks_inv_`) dan ban (`wks_tyre_`) dalam satu panel (gudang `type=both`),
**Sesi Kerja Gudang terpadu**. Peran: Gudang/GudangBan (operator), **Kepala Gudang/
Supervisor** (penutup sesi). *(Mode sekarang: Service Officer kerap memegang LKM + gudang
part + ban sekaligus.)*

> 🔒 **Gate Buka Sesi (Opening) — sebelum masuk panel.** Saat user membuka panel Gudang,
> bila **sesi hari ini** (warehouse aktif, `business_date`) belum `open` → diarahkan ke layar
> **Buka Sesi**; **semua Resource gudang terblok** sampai dibuka. **1 siklus/hari per gudang**
> (`unique(warehouse_id, business_date)`); operator **pertama** membuka, dipakai bersama
> seharian. Diwujudkan via render hook / middleware panel (bukan hanya per-transaksi).

| Grup | Resource / Page (Action) |
|---|---|
| **Sesi Kerja** | ShiftSessionResource — **Buka Sesi** (operator; snapshot saldo awal) · **Tutup Sesi** (**hanya Kepala Gudang/Supervisor**; snapshot akhir + anomali) · **Force-close** (supervisor/sistem) |
| **Sparepart (master)** | SparePartResource (RM part-numbers/UOM/lokasi) · StockResource (filter wh/rak/kondisi new-used) · MovementResource (read — ledger semua jenis) |
| **① Penerimaan supplier** | SupplierDeliveryResource — **Surat Jalan MASUK supplier** (`wks_po_supplier_deliveries`, **per PO**) — **idealnya sudah diisi supplier via Portal `/vendor`** (`source=portal`); bila tidak, staf isi (`source=manual`) |
| | GoodsReceiptResource (GRN, **wajib PO** + **pilih SJ yang sudah ada**) → RM **tally** fisik → Action **Post** (`StockService` *in* + WAC) — operator **tak ketik ulang SJ** |
| **② Keluar ke LKM/WO** | PartIssueResource — **Bon**: usul Mekanik → review SO → **Issue** (`StockService` `out`, HPP→WO) |
| **③ Terima part bekas** | Page **Teardown** (bekas layak → stok used) · Page **Core return** (bekas rusak → bukti/scrap) |
| **④ Mutasi/Relokasi** | Action **Relokasi** (bin↔bin, gudang sama) · DeliveryOrderResource `do_type=transfer` (antar gudang) → `transfer` |
| **⑤ Peminjaman (storing)** | PartLoanResource — **Pinjam keluar** (`loan_out`, wajib kembali) · Action **Terima Kembali** (`loan_return`) · Action **Konversi → Bon** (bila tak kembali) |
| **⑥ Temuan/Penyesuaian** | Action **Masukkan Temuan** (`adjustment` +, reason `found`) · StockOpnameResource → Action **Post** (adjustment) |
| **⑦ Retur ke supplier** | PurchaseReturnResource (ref PO/GRN) → Action **Post** (`return_supplier` out) → **nota retur** kurangi tagihan (AP, *menyusul*) |
| **⑧ Retur Bon dari LKM** | IssueReturnResource — part **baru tak jadi pakai** kembali (`return_wo` in) → **reverse HPP** di WO |
| **Dokumen Keluar (umum)** | DeliveryOrderResource (Surat Jalan **KELUAR** — transfer/issue/return internal) · TallySheetResource · Action **Post** |
| **Ban (master & unit)** | TyreProductResource (model) · TyreResource (unit serial; status/stage; RM instalasi/inspeksi/retread) · TyreAlertResource · TyreOpnameResource |
| **Ban — siklus gudang** | ① **Terima Ban** dari supplier — **rujuk SJ supplier** (portal `/vendor` / manual) → GRN ber-PO → `receipt` (serial diregistrasi saat GRN), Gudang Ban Baru |
| | ② **Pasang/Lepas** (`install`/`removal`) — TyreInstallationResource (+ Page **layout per axle**) · ban lepas layak → ③ **stok used** di Gudang Bekas |
| | ④ Action **Konfirmasi Afkir** (`condemn`, status `afkir`) — **hanya Kepala Gudang/Supervisor** (izin `tyre.condemn`) |
| | ⑤ Action **Pindah ke Gudang Afkir** (`transfer`) · ⑥ TyreDisposalResource — **Jual Afkir** (`sold`) / buang |
| | TyreRetreadResource (vulkanisir) · TyreInspectionResource (tread/tekanan → usul afkir) |
| **Lokasi** | LocationResource (pohon area/zona/rak→bin · generator massal · slotting) |
| **Laporan** | Page: stok kritis · slow-moving · valuasi WAC · baru vs bekas · biaya/KM ban |

> **Audit gudang = panel terpisah `/audit`** (§5b, peran Auditor) — independen dari operasi.
> Gudang adalah **subjek** audit; operator/Kepala Gudang tak menulis audit.

> **Penerimaan fisik ada DI SINI.** Barang tiba di gudang → operator Gudang cocokkan
> **Surat Jalan supplier + PO** → GRN → tally → Post (`StockService` *in*). **PO tetap dibuat
> & di-approve di Purchasing**; Purchasing hanya **lihat-saja** status SJ/GRN untuk pantau PO.
> **SoD:** pembeli (Purchasing) ≠ penerima (Gudang). GRN tetap **wajib ber-PO**.
> ⚠️ "Surat Jalan **masuk** (supplier, `wks_po_supplier_deliveries`)" ≠ "Surat Jalan **keluar**
> (`wks_inv_delivery_orders`, transfer/issue internal)".

Dashboard: **status sesi hari ini (open/closed)**, stok kritis, sesi belum ditutup
(>24 jam → notif WA/email, gap G3), alert ban (tread/retread/DOT), opname terjadwal.

> **SoD sesi:** operator **membuka** (mulai hari) ≠ **Kepala Gudang/Supervisor menutup**
> (akhir hari, dengan snapshot+anomali). Setelah `closed`, siklus hari itu final — kerja
> berikutnya menunggu Buka Sesi keesokan hari (buka ulang tanggal sama = override supervisor,
> di-audit).

---

## 5b. Panel AUDIT (`/audit`) — Kontrol Independen Gudang

Panel **terpisah** untuk **Internal Audit** atas Gudang Sparepart — independen dari operasi
(SoD: **Auditor** ≠ operator Gudang ≠ Kepala Gudang). Peran: **Auditor**. **Read-only terhadap
stok**: audit menghasilkan **temuan**; koreksi saldo tetap di panel Gudang (opname/penyesuaian),
lalu auditor **verifikasi**. *(Provider `AuditPanelProvider` sudah di-scaffold — IMPLEMENTATION.md.)*

| Grup | Resource / Page (Action) |
|---|---|
| **Audit Formal** | AuditResource — jadwalkan/jalankan audit (cycle/spot/full/compliance/investigasi) ber-scope (gudang/kategori/periode) |
| | RM **Cek Fisik** (`audit_items`: book_qty vs counted_qty) — hitung independen |
| **Temuan** | AuditFindingResource — **Temuan** (type · severity · expected/actual · rekomendasi); Action **Verifikasi** (auditor) saat koreksi `resolved` |
| **Jejak (Trail)** | Page **Audit Trail** *(read-only)*: ledger `stock_movements` + `wks_core_audit_logs` (filter SKU/gudang/user/tanggal/jenis) — append-only |
| **Anomali** | Page **Review Anomali**: stok negatif (`stock_alerts`) + selisih sesi (`anomaly_count`/`diff_qty`) + movement tak wajar → Action **Promosikan jadi Temuan** |
| **Laporan** | Page: rekap temuan (status/severity), audit selesai, temuan tertunggak per PIC |

> **Akses data:** panel ini ber-`->tenant(Company)` + scope branch seperti panel operasional,
> tetapi **policy read-only** atas Resource stok/dokumen Gudang (lihat, tak ubah). Tulis hanya
> pada `wks_inv_audits`/`_items`/`_findings`. **Temuan tak menyentuh saldo** (R61).
> **SoD:** auditor **tak boleh** menutup temuannya sendiri sebagai pelaksana koreksi.

Dashboard: audit berjalan, temuan `open`/`critical`, temuan jatuh tempo, anomali belum ditinjau.

---

## 6. Panel PURCHASING (`/purchasing`) — PO + Price List

Pengadaan sparepart & ban: harga → PO. **Penerimaan fisik dilakukan di Gudang** (§5);
Purchasing memantau pemenuhannya. Peran: Purchasing (approval Owner/Admin).

| Grup | Resource / Page (Action) |
|---|---|
| **Purchase Order** | PurchaseOrderResource (+ Action **Approve**) · RM items (snapshot harga+PPN via `PricingService`) · PurchaseRequestResource *(opsional)* |
| **Pemenuhan PO** *(lihat-saja)* | SupplierDeliveryResource **(read)** — status Surat Jalan masuk per PO · GoodsReceiptResource **(read)** — status/qty GRN per PO; pantau **outstanding** (belum diterima penuh) |
| **Price List** | PriceListResource (RM items; Action **bulk-update**, **import** Excel/CSV) · Page bandingkan supplier · Page riwayat harga |
| **Laporan** | Page: pembelian per part/supplier |

> **Penerimaan (SJ supplier + GRN + Post stok) ada di panel Gudang (§5)** — pembeli ≠ penerima
> (SoD). Resource SupplierDelivery/GoodsReceipt **dibagikan lintas panel**: tulis di Gudang,
> **read-only** di Purchasing. ⚠️ Surat Jalan **masuk** (`wks_po_supplier_deliveries`) ≠ Surat
> Jalan **keluar** (`wks_inv_delivery_orders`).

Dashboard: PO menunggu approval, **PO outstanding** (belum diterima penuh), SJ/GRN terkait PO.
Aksi cepat: **+ PO**.

---

## 7. Panel KONTRABON (`/kontrabon`) — Hutang Supplier (AP) · ✅ scaffold

Ruang kerja **divisi Finance/AP** (sesuai permintaan: **panel sendiri** karena yang mengurus
Finance). **Kontrabon = dokumen yang kita buat** menyalin **tagihan supplier** ("tukar faktur"),
lalu **review & cocokkan satu per satu**. Satu kontrabon (per supplier) bisa memuat **1 atau
banyak** tagihan. Tiap baris dicek: **barang diterima (GRN) · surat jalan · faktur pajak · PO &
nominal cocok**; tetapkan **jatuh tempo** → akui **hutang (AP)** yang dibayar di Kasir (§8).
Peran: **Finance/AP** (approver ≠ verifikator). Modul `wks_ap_` — DATABASE §7d, WORKFLOWS §4c, MODULES §17.

| Grup | Resource / Page (Action) |
|---|---|
| **Kontrabon (Tanda Terima Tagihan)** | KontrabonResource — header: supplier · tanggal terima · jatuh tempo · total |
| | RM **Baris Tagihan** — salin tiap faktur supplier (no faktur · **no Faktur Pajak (NSFP)** · tanggal · nilai · PPN · rujuk GRN/PO) — **bisa 1 atau banyak** |
| | **Cek satu per satu** (per baris): ☑ barang diterima (GRN) · ☑ Surat Jalan · ☑ Faktur Pajak · ☑ PO & nominal cocok → `ok`/`problem` (+ catatan) |
| | Action **Verifikasi** (`verified`) — **gate: hanya bila semua baris `ok`** |
| | Action **Approve** (`approved` → **hutang diakui**) — **SoD:** approver ≠ verifikator |
| | Action **Tolak** (`rejected` + alasan → kembalikan ke supplier) / **Batal** |
| **Hutang (AP)** | Page **Aging Hutang** per supplier (bucket umur dari `due_date`, `outstanding`) |
| | Page: kontrabon jatuh tempo / lewat tempo (umpan ke Kasir §8) |

> **Pencocokan:** baris tagihan dirujuk ke **GRN** (`wks_po_goods_receipts`, read-only dari
> Gudang/Purchasing) untuk "barang diterima", dan ke **PO** untuk "nominal cocok" (= 3-way match).
> Satu kontrabon **boleh memuat beberapa faktur/GRN/PO**. **Nota retur** supplier
> (`wks_inv_purchase_returns`, §5 ⑦) **mengurangi** tagihan (`credit_amount`). Aksi berstatus via
> **`ApService`** + `DB::transaction()`, idempoten (tombol nonaktif pasca-approve).
> **SoD:** review/approve di sini (Finance) ≠ pembayaran (Kasir §8).

Dashboard: kontrabon dalam pengecekan, baris `problem`, hutang jatuh tempo minggu ini,
total `outstanding` per supplier. Aksi cepat: **+ Kontrabon**.

---

## 8. Panel KASIR (`/kasir`) — Pembayaran Supplier (AP) · ✅ scaffold

**Pembayaran ke supplier sparepart/ban** atas kontrabon yang **sudah `approved`** & jatuh tempo.
Kasir **mengelola rekening bank/kas**, membuat **Request Pembayaran** (alur **maker→checker**),
lalu **merealisasikan** lewat **giro** atau **digital banking (maker-checker)**. **Panel sendiri**
(peran **Kasir**). Modul `wks_ap_` — DATABASE §7d, alur WORKFLOWS §4c.

| Grup | Resource / Page (Action) |
|---|---|
| **Rekening Bank/Kas** | BankAccountResource — master rekening sumber dana (bank/tunai; `supports_giro`/`supports_digital`) |
| **Request Pembayaran** | PaymentRequestResource (**maker**) — pilih supplier + **kontrabon `approved` jatuh tempo** + metode + rekening |
| | RM **Alokasi** — bagi request ke 1/banyak kontrabon (partial); guard **anti over-pay** (`amount ≤ outstanding`) |
| | Action **Submit** (`submitted`) → Action **Approve/Reject** (**checker**; **SoD: checker ≠ pengaju**) |
| **Realisasi Pembayaran** | PaymentResource — **eksekusi request `approved`**: metode **transfer/digital** (digital banking maker-checker; simpan `digital_ref`) → Action **Post** langsung; **giro** → lewat Register Giro (di bawah) |
| | Action **Post** → settle hutang (`ApService`); kontrabon → `partially_paid`/`paid` ; request → `paid` |
| **Register Giro** | GiroResource — **register giro di aplikasi SEBELUM tanda tangan** (no, nilai, jatuh tempo, atas nama dari payment) → `registered` |
| | Action **Cetak** (lembar register/voucher; `print_count`++) → `printed` — **giro fisik ditandatangani setelah ini** |
| | Action **Tandai Ditandatangani** (`signed`, `signed_by` otorisasi) |
| | Action **Verifikasi** — periksa **giro fisik vs sistem lewat print** (no/nilai/atas nama/jatuh tempo **harus sama**) → `verified` |
| | Action **Serahkan** (`released`) → **payment `posted`** (hutang lunas) · Action **Tandai Cair** (`cleared`) · **Tolak/Bounce** (`bounced` → payment cancel, hutang kembali) |
| **Kas/Bank** | Page: rekap **kas keluar** per rekening/metode/periode · **daftar giro** (belum cair/jatuh tempo) |

> ⚠️ **Kasir ini = pembayaran ke SUPPLIER (AP)**, beda dari "Kasir" *future/dormant*
> untuk pembayaran **customer** (penjualan/invoice) yang masih non-aktif di mode internal.
> **SoD berlapis:** (a) Kasir **hanya membayar** kontrabon yang sudah di-`approve` Finance/AP (§7);
> (b) **request pembayaran** punya maker→checker (pengaju ≠ penyetuju); (c) **giro** punya kontrol
> sendiri — **register di aplikasi → print → tanda tangan → verifikasi (fisik harus sesuai sistem,
> dicek lewat print) → serah**; registrar ≠ penanda tangan ≠ pemeriksa. **"Digital maker"** = metode
> `digital`: realisasi via digital banking (maker input, checker bank otorisasi) — di sistem kita
> diwakili persetujuan request. Posting via **`ApService`** + `DB::transaction()`, idempoten.

Dashboard: kontrabon jatuh tempo siap bayar, **request menunggu approve**, **giro perlu
tanda tangan/verifikasi**, total `outstanding` per supplier, giro belum cair, kas keluar per
rekening. Aksi cepat: **+ Request Pembayaran**.

---

## 9. Modul Hutang Supplier / Accounts Payable (`wks_ap_`) — ✅ dirinci

Domain **AP** untuk Kontrabon (§7) + Kasir (§8). Sub-prefix **`wks_ap_`**. Posisi dalam alur:

```
PO ─► GRN ─► KONTRABON (salin tagihan + cek satu per satu + jatuh tempo) ─► approve (hutang)
        ─► KASIR: Request Pembayaran (maker→checker) ─► Realisasi (giro/digital) ─► lunas
```

**Sudah didokumentasikan:**
- **MODULES §17** — modul 9 `wks_ap_` (tujuan, fitur, tabel, peran, dependensi).
- **DATABASE §7d** — `wks_ap_kontrabons` (header) / `wks_ap_kontrabon_invoices` (baris tagihan +
  checklist) · `wks_ap_bank_accounts` (master rekening) · `wks_ap_payment_requests`/`_items`
  (request maker→checker) · `wks_ap_payments` (realisasi) · `wks_ap_giros` (Register Giro:
  register→print→sign→verify→serah→cair) + enum (`ap_kontrabon_status`, `ap_invoice_check_status`,
  `ap_account_type`, `ap_payment_method`, `ap_payment_request_status`, `ap_payment_status`,
  `ap_giro_status`) di §12 + relasi §11.
- **WORKFLOWS §4c** — alur Kontrabon → Kasir + ringkasan status.
- **SITEMAP §B.10/§B.11** — halaman `/kontrabon` & `/kasir`.

**Service:** `ApService` — pengakuan hutang (approve kontrabon), request pembayaran (maker→checker)
+ realisasi (giro/digital) → settle kontrabon, terapkan kredit nota retur (semua `DB::transaction()`, idempoten).

**Panel sudah di-scaffold** (`KontrabonPanelProvider`, `KasirPanelProvider`). **Sisa pekerjaan
(saat Purchasing/GRN siap):** migration + model `wks_ap_*`, `ApService`, Resource/Page di panel
`Kontrabon` & `Kasir`, Policy/Shield (SoD: verifikator ≠ approver ≠ kasir), seeder/test
(happy-path + isolasi tenant + SoD). Mode internal tetap: **hutang ke supplier** (sah), beda dari
invoice **ke customer** (future/dormant).

> **Catatan dependensi:** 3-way match penuh perlu **Purchasing (PO)** & **GRN (Gudang)** yang
> belum ada di kode. Saat AP dibangun lebih dulu, `po_id`/`goods_receipt_id` di-nullable + supplier
> wajib; pencocokan menyusul. Urut build: lihat MODULES §13 (#10).

---

## 10. Panel ADMIN (`/admin`) — Master & Setting

Pengelolaan user/akses, master data, dan pengaturan company. Peran: Owner, Admin.

| Grup | Resource / Page |
|---|---|
| **Pengguna & Akses** | UserResource · RoleResource (Shield) · BranchResource |
| **Master Data** | Customer · Truck (RM dokumen STNK/KIR + reminder, axle-positions) · Driver (Action **Sync HRD** via `HrdGateway`) · Supplier · Warehouse · Uom · Category · Mechanic · ServiceCatalog |
| **Pengaturan** | Company Profile · Settings (pajak/PPN, jam operasional, **mode penerimaan LKM `lkm_intake_mode`: direct / dispatcher_permit**) · DocumentSequenceResource · NotificationRuleResource (+ outbox/log) |
| **Laporan lintas modul** | inventory · PM compliance · tyre · mechanics · turnaround *(revenue/receivables — future)* |

Dashboard: ringkasan kepatuhan (dokumen unit kedaluwarsa, PM), aktivitas user.

---

## 11. Panel VENDOR / Supplier (`/vendor`) — Web Supplier Isi Surat Jalan

**Tujuan utama:** supplier **mengisi sendiri Surat Jalan**-nya (untuk **sparepart & ban**)
**sebelum** barang tiba → saat barang datang, **operator Gudang tinggal verifikasi fisik +
GRN**, tak perlu mengetik ulang SJ. Mengurangi beban operator & salah ketik.

Supplier eksternal: lihat PO yang ditujukan padanya & **daftarkan Surat Jalan masuk** per PO.
`canAccessPanel()` hanya bila `user.supplier_id` terisi; **scope ketat** `supplier_id` +
`company_id` (global scope `BelongsToSupplier`).

| Grup | Resource | Akses |
|---|---|---|
| Dashboard | PO aktif & status SJ/penerimaan miliknya | read |
| Purchase Orders | PurchaseOrderResource (PO untuknya) | **read-only** |
| **Surat Jalan** | SupplierDeliveryResource — **buat SJ** atas PO: `supplier_do_no`, tanggal kirim, **item part/ban + qty_shipped** (tarik dari baris PO) → **Submit** (`source=portal`) | **tulis (miliknya)** |
| Profil | kontak supplier | edit terbatas |

> **Alur ringan (portal aktif):** Supplier **Submit SJ** (`status=submitted`, `source=portal`) →
> barang tiba → **operator Gudang** buka GRN, **pilih SJ** yang sudah ada → tally fisik → Post
> (`StockService`/`TyreService` *in*). **Ban:** SJ supplier hanya **product + qty**; **serial
> ban diregistrasi saat GRN** (sisi kita). **Tanpa portal (`source=manual`):** staf isi SJ
> sendiri (fallback) — alur sama, beda penginput.
>
> **Keamanan (R46/R47):** akun **undangan** (tak open signup), scope ketat `supplier_id`,
> rate-limit, audit login, panel terpisah dari internal. **Diaktifkan via feature-flag** per
> company; bila belum, `source=manual` tetap jalan.

---

## 12. RBAC — Peta Peran ↔ Panel (Shield)

| Peran | Panel yang bisa diakses |
|---|---|
| *(semua user tenant)* | Launcher (`/home`) — hanya tile panel yang berhak |
| SuperAdmin / SystemSupport | System |
| Owner / Admin | semua panel tenant (Admin penuh; lain read/awas sesuai Policy) |
| Dispatcher | PMB (terbitkan/cetak/batalkan pengantar masuk — mode `dispatcher_permit`) |
| ServiceAdvisor (Service Officer) | LKM (buat LKM, rujuk PMB), Servis (+ review Bon di Gudang) |
| KepalaMekanik | Servis (supervisi/QC) + Mekanik |
| Mekanik | Mekanik (update pekerjaan: status, jam, usul Bon, pasang/lepas ban) |
| Gudang / GudangBan | Gudang (buka sesi, transaksi, **penerimaan: SJ supplier + GRN + post stok**) |
| Kepala Gudang / Supervisor | Gudang (**tutup/force-close sesi** + supervisi; izin `shift_session.close`) |
| Auditor (Internal Audit) | **Audit** (panel `/audit`) — read stok/dokumen + tulis audit/temuan + verifikasi; independen |
| Purchasing | Purchasing, Price List (+ **read** SJ supplier/GRN penerimaan di Gudang) |
| Finance/AP | Kontrabon |
| Kasir | Kasir (pembayaran supplier) |
| Supplier | Vendor saja |

> **Izin closing (`shift_session.close`) bersifat permission-based, bukan hardcode.** Default
> melekat ke peran **`KepalaGudang`**; **sampai peran itu di-provisioning, Owner/Admin
> memegangnya** (bertindak sebagai supervisor). Operator `Gudang`/`GudangBan` tak punya izin ini.
> Buka Sesi = izin `shift_session.open` (operator gudang).

- Permission `<resource>.<action>` via `shield:generate`; Policy model = penjaga akhir.
- Resource/Action & **seluruh panel** disembunyikan bila peran tak berhak atau modul off.
- **SoD** dipertahankan lintas panel: pengusul Bon (Mekanik) ≠ reviewer (ServiceAdvisor)
  ≠ pengeluar (Gudang); verifikasi kontrabon ≠ pembayaran kasir.

---

## 13. Rekonsiliasi dengan SITEMAP

`SITEMAP.md` masih menggambarkan satu panel App dengan sub-path `/app/<modul>`.
Dengan keputusan multi-panel, pohon halaman itu **dipindah** menjadi panel top-level:

| SITEMAP (lama) | Panel (baru) |
|---|---|
| `/app/lkm/pmb/*` | `/pmb/*` *(panel terpisah, peran Dispatcher)* |
| `/app/lkm/*` | `/lkm/*` |
| `/app/svc/*` | `/servis/*` |
| `/app/inv/*` + `/app/tyre/*` + **penerimaan (SJ supplier + GRN)** | `/gudang/*` |
| `/app/inv/audit/*` | `/audit/*` *(panel terpisah, peran Auditor)* |
| `/app/po/*` (PO; SJ/GRN **read-only**) + `/app/price/*` | `/purchasing/*` |
| `/app/master/*` + `/app/admin/*` + `/app/reports/*` | `/admin/*` |
| *(baru)* | `/kontrabon/*`, `/kasir/*` |

> **Tindak lanjut:** `SITEMAP.md` & matriks akses sudah disinkronkan dengan daftar panel
> di §0 (Launcher + System, **PMB**, LKM, Servis, Mekanik, Gudang, Purchasing, Kontrabon,
> Kasir, Admin, Vendor). Servis & PMB **sudah dikonfirmasi terpisah** (§15 Sudah Diputuskan).

---

## 14. Definition of Done (per panel)

- [ ] `PanelProvider` terdaftar; `canAccessPanel()` memisahkan akses sesuai peran.
- [ ] Isolasi tenant via **`BelongsToCompany` (scope global) sebagai sumber kebenaran** —
      sudah di-scaffold (`IMPLEMENTATION.md`); `->tenant(Company)` URL Filament opsional (§15.6).
      **branch = pemilih header** bersama (`BelongsToBranch`), bukan tenant kedua (R31).
- [ ] Resource di panel & grup navigasi benar; namespace per panel.
- [ ] Otorisasi Policy/Shield; panel/Resource/Action tersembunyi bila tak berhak / modul off.
- [ ] FK select searchable; status badge; filter (kondisi new/used, branch, warehouse) & search SKU/part-no.
- [ ] Aksi stok/uang lewat Service + `DB::transaction()`, idempoten.
- [ ] Field future/dormant ter-feature-flag.
- [ ] Test: happy-path + otorisasi + **isolasi tenant** (R1) + scope supplier (Vendor) + SoD.

---

## 15. Masih Perlu Diputuskan

1. **Gate verifikasi kontrabon** — desain sekarang: header tak bisa `verified` bila ada baris
   `check_status ≠ ok` (semua checklist wajib ☑). **Konfirmasi:** boleh ada **pengecualian
   ber-catatan** (approve baris `problem` dengan alasan, mis. selisih kecil di-toleransi) atau
   **wajib semua `ok`** tanpa pengecualian (default sekarang)?
2. ✅ **Metode bayar & rekening Kasir (diputuskan):** ada **master rekening bank/kas**
   (`wks_ap_bank_accounts`), **Request Pembayaran maker→checker**, metode **giro** & **digital**
   (digital banking maker-checker) + transfer/tunai. **Sisa konfirmasi:** perlu **rekonsiliasi
   saldo rekening** (kas/bank ledger) atau cukup catat transaksi keluar tanpa saldo berjalan?
3. **Launcher** — user yang hanya berhak **satu** panel tenant: auto-redirect lewati
   launcher, atau tetap tampilkan hub? Perlu ringkasan lintas panel di launcher (opsional)?
4. **Vendor panel** — subpath `/vendor` atau subdomain terpisah untuk isolasi lebih kuat?
5. **Impersonate** (System→tenant) — plugin pihak ketiga atau fitur tenant bawaan v5?
6. **Tenancy Filament `->tenant()`** — fondasi sekarang pakai **scope global** (`BelongsToCompany`
   /`BelongsToBranch` + `IdentifyTenant`), bukan tenancy URL Filament. Perlu tambah `->tenant(Company)`
   (company di URL) nanti, atau cukup scope global? (Isolasi sudah terpenuhi oleh scope; lihat IMPLEMENTATION.md.)

### Sudah Diputuskan (sesi 2026-06)

- ✅ **Panel Servis tetap TERPISAH** (bukan digabung ke LKM) — diperkuat desain **WO Plan**
  (Servis menyusun/awasi, Mekanik eksekusi).
- ✅ **PMB = panel/modul sendiri** (`/pmb`, `wks_pmb_`, Dispatcher), independen dari LKM (§2b).
- ✅ **WO Plan = task + langkah/sub-step** (`wks_svc_wo_task_steps`), disusun **Mekanik (bisa
  bersama Service Officer) setelah ambil WO**; panduan, **tidak mengikat**; boleh tambah `adhoc`.
- ✅ **Fase sekarang TANPA estimasi biaya** — biaya = pemakaian aktual (HPP).
- ✅ **Sesi Kerja Gudang = 1 siklus/hari per gudang**; Opening = **gate masuk panel**, Closing =
  **akhir hari oleh Kepala Gudang/Supervisor**; peran **Kepala Gudang** (izin `shift_session.close`).
- ✅ **Penerimaan sparepart di panel Gudang** (SJ supplier + GRN + post); Purchasing buat PO + read.
- ✅ **Isolasi tenant + branch di-scaffold** via scope global (lihat [IMPLEMENTATION.md](IMPLEMENTATION.md)).
- ✅ **Audit = panel TERPISAH `/audit`** (peran Auditor, independen) — `AuditPanelProvider` sudah di-scaffold (§5b).
- ✅ **Antarmuka mekanik = PWA mobile-first** (utama) + **panel Filament Mekanik** (supervisor); **Fase 1
  online-first**, siap offline (event idempoten sejak awal). Detail → [MOBILE_MEKANIK.md](MOBILE_MEKANIK.md).
- ✅ **Modul AP (`wks_ap_`) dirinci & panel Kontrabon + Kasir TERPISAH** (Finance/AP vs Kasir; SoD
  verifikator≠approver≠pembayar). Panel di-scaffold; tabel/Resource menyusul setelah Purchasing/GRN.
  Detail: MODULES §17, DATABASE §7d, WORKFLOWS §4c, panel §7–§9.
