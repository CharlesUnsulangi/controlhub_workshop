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
| **LKM** | `lkm` | `/lkm` | `LkmPanelProvider` | ✅ Company | ServiceAdvisor, Gate/Satpam | `wks_lkm_` | ✅ |
| **Servis** ⚑ | `servis` | `/servis` | `ServisPanelProvider` | ✅ Company | ServiceAdvisor, KepalaMekanik | `wks_svc_` | ✅ |
| **Mekanik** | `mekanik` | `/mekanik` | `MekanikPanelProvider` | ✅ Company | Mekanik, KepalaMekanik | `wks_svc_` (subset) | ✅ |
| **Gudang** | `gudang` | `/gudang` | `GudangPanelProvider` | ✅ Company | Gudang, GudangBan | `wks_inv_` + `wks_tyre_` | ✅ |
| **Purchasing** | `purchasing` | `/purchasing` | `PurchasingPanelProvider` | ✅ Company | Purchasing | `wks_po_` + `wks_price_` | ✅ |
| **Kontrabon** | `kontrabon` | `/kontrabon` | `KontrabonPanelProvider` | ✅ Company | Finance/AP | `wks_ap_` *(menyusul)* | 🧩 placeholder |
| **Kasir** | `kasir` | `/kasir` | `KasirPanelProvider` | ✅ Company | Kasir (bayar supplier) | `wks_ap_` *(menyusul)* | 🧩 placeholder |
| **Admin** | `admin` | `/admin` | `AdminPanelProvider` | ✅ Company | Owner, Admin | `wks_adm_` + `wks_mst_` | ✅ |
| **Vendor / Supplier** | `vendor` | `/vendor` | `VendorPanelProvider` | ✅ (scope `supplier_id`) | Supplier | `wks_po_` (read) | 🔒 fase berikutnya |

⚑ **Servis belum disebut** di daftar panel yang Anda berikan — saya sertakan karena
modul Work Order adalah *muara* LKM (LKM → WO) dan inti pencatatan biaya. **Konfirmasi**:
dipertahankan sebagai panel sendiri, atau digabung (mis. ke LKM)?

🧩 **Kasir & Kontrabon = modul Hutang Supplier (AP) baru** — panel dibuat sekarang,
**detail tabel/alur menyusul** (lihat §6).

### Alur antar panel (procure-to-pay + servis)
```
        ┌─ Admin (master & setting) ──────────────────────────────────┐
        │                                                             │
 LKM ──► Servis (WO) ──► Gudang (issue part/ban, sesi kerja)          │ Purchasing (PO ─► GRN ─► stok)
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
   operasional (LKM, Servis, Gudang, Purchasing, Kontrabon, Kasir).
5. **Logika bisnis di Service, bukan Resource.** Aksi berstatus/berdampak stok/uang
   memanggil `StockService`/`TyreService`/`PricingService`/(`ApService` menyusul) dalam
   `DB::transaction()`, idempoten (disable tombol setelah `posted`).
6. **Feature-flag Core menyembunyikan panel/Resource** bila modul dimatikan untuk
   company (`wks_core_company_modules`). Field *(future/dormant)* juga via flag.
7. **Otorisasi Policy + Shield**; permission per Resource (`shield:generate`).
8. **Namespace per panel:** `App\Filament\<Panel>\Resources\…`
   (`Lkm`, `Servis`, `Gudang`, `Purchasing`, `Kontrabon`, `Kasir`, `Admin`, `System`,
   `Vendor`). Kode tanpa prefix `wks` (`NAMING_CONVENTIONS §3`).

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
- **Aksi cepat lintas panel:** **+ LKM · + Work Order · + PO** (deep-link ke create di
  panel terkait).
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

## 3. Panel LKM (`/lkm`)

Penerimaan truk masuk (gate-in) — "pintu depan". Peran: ServiceAdvisor, Gate/Satpam.

| Grup | Resource / Page (Action) |
|---|---|
| Kendaraan Masuk | LkmEntryResource (filter status/tanggal/branch) |
| | RM **Inspeksi** (checklist + foto) |
| | Action **Buat Work Order** → panel Servis |
| | Action **Gate-out** (+ surat jalan unit) |
| Laporan | Page: Turnaround (lama unit di bengkel) |

Dashboard: kendaraan masuk hari ini, status (Masuk/Diproses/Selesai/Keluar),
unit menunggu gate-out. Aksi cepat: **+ LKM**.

---

## 4. Panel SERVIS (`/servis`) ⚑

Perencanaan & supervisi Work Order: buat WO dari LKM, estimasi, **tugaskan mekanik**,
QC, rekap biaya. Peran: ServiceAdvisor, KepalaMekanik. *(Eksekusi & update lapangan
oleh mekanik ada di panel **Mekanik**, §4b.)* Catat **biaya (HPP)** per unit (mode internal).

| Grup | Resource / Page (Action) |
|---|---|
| Work Order | WorkOrderResource (papan status/kanban) · RM items (jasa/part/ban) · estimasi |
| | Action **Tugaskan Mekanik** (assignment → muncul di panel Mekanik) |
| | Action **QC / Selesai** (blok bila **core return** belum kembali) |
| Servis Berkala | PmScheduleResource · Page: Due/terlambat |
| Biaya | Page: Rekap biaya per WO & per unit (part HPP + ban + jasa) |
| *(future/dormant)* | InvoiceResource · PaymentResource (harga jual ke customer — non-aktif) |

Dashboard: WO aktif per status, menunggu part, beban per mekanik, PM jatuh tempo,
core return tertunggak. Aksi cepat: **+ Work Order**.

---

## 4b. Panel MEKANIK (`/mekanik`) — update pekerjaan

Ruang kerja **ringkas (mobile/tablet-friendly)** bagi mekanik untuk **memperbarui
pekerjaan** di lapangan. Hanya menampilkan WO yang **ditugaskan ke dirinya**
(scope `mechanic_id`/assignment) — bukan semua WO. Peran: Mekanik, KepalaMekanik.

| Grup | Resource / Page (Action) |
|---|---|
| **Pekerjaan Saya** | WorkOrderResource (subset: ditugaskan ke saya; filter status) |
| | Action **Update Status/Progress** (Antri → Dikerjakan → QC) |
| | Action **Catat Jam Kerja** (mulai/selesai per item) · catat temuan/keluhan tambahan |
| **Part** | Action **Usul Bon Part** → Bon Pengeluaran (review SO di Servis, keluar di Gudang) |
| | Action **Input Core Return** (part bekas rusak saat pasang part baru non-consumable) |
| **Ban** | Action **Pasang/Lepas Ban** → `TyreService` · lihat **layout posisi axle** unit |
| **Referensi** | read-only: detail unit, riwayat servis, spesifikasi |

**Pemisahan tugas (SoD):** Mekanik = **pengusul** Bon part (pengusul ≠ reviewer ≠
pengeluar). Update status dibatasi transisi yang diizinkan; **tidak** bisa mengubah
estimasi/biaya atau menutup WO (QC final di panel Servis).

Dashboard: WO saya hari ini per status, menunggu part, ban perlu dipasang,
jam kerja tercatat. Aksi cepat: **Mulai/Selesai pekerjaan**, **Usul Part**.

> Layout ringkas untuk perangkat bengkel (selaras catatan mobile di `SITEMAP §D`).

---

## 5. Panel GUDANG (`/gudang`) — Sparepart + Ban + Sesi Kerja

Stok sparepart (`wks_inv_`) dan ban (`wks_tyre_`) dalam satu panel (gudang `type=both`),
**Sesi Kerja Gudang terpadu**. Peran: Gudang, GudangBan.

| Grup | Resource / Page (Action) |
|---|---|
| **Sesi Kerja** | ShiftSessionResource — **Buka/Tutup Sesi** (snapshot saldo + anomali); wajib sebelum transaksi |
| **Sparepart** | SparePartResource (RM part-numbers/UOM/lokasi) · StockResource (filter wh/rak/kondisi new-used) · MovementResource (read) |
| | PartIssueResource — Bon: usul Mekanik → review SO → **Issue** (`StockService` out, HPP→WO) |
| | Page: Teardown (part bekas → stok used) · Page: Core return |
| **Dokumen Gudang** | DeliveryOrderResource (Surat Jalan **keluar**) · TallySheetResource · Action **Post** → `StockService` |
| **Opname** | StockOpnameResource → Action **Post** (adjustment) |
| **Ban** | TyreProductResource · TyreResource (unit serial; RM instalasi/inspeksi/retread) · TyreInstallationResource (+ Page **layout per axle**) · TyreInspectionResource · TyreRetreadResource · TyreOpnameResource · TyreAlertResource · TyreDisposalResource |
| **Lokasi** | LocationResource (pohon area/zona/rak→bin · generator massal · slotting) |
| **Laporan** | Page: stok kritis · slow-moving · valuasi WAC · baru vs bekas · biaya/KM ban |

> **Serah Terima (GRN)** stok-masuk **bukan** di sini — ada di panel **Purchasing**
> (wajib ber-PO). Gudang hanya menerima hasil posting via `StockService`.

Dashboard: stok kritis, sesi belum ditutup (>24 jam → notif WA/email, gap G3),
alert ban (tread/retread/DOT), opname terjadwal.

---

## 6. Panel PURCHASING (`/purchasing`) — PO + Price List

Pengadaan sparepart & ban: harga → PO → penerimaan ke gudang. Peran: Purchasing
(approval Owner/Admin).

| Grup | Resource / Page (Action) |
|---|---|
| **Purchase Order** | PurchaseOrderResource (+ Action **Approve**) · RM items (snapshot harga+PPN via `PricingService`) · PurchaseRequestResource *(opsional)* |
| **Penerimaan** | SupplierDeliveryResource (Surat Jalan **masuk** dari supplier) · GoodsReceiptResource (GRN, **wajib pilih PO**) → RM tally → Action **Post** (`StockService` *in* + WAC) |
| **Price List** | PriceListResource (RM items; Action **bulk-update**, **import** Excel/CSV) · Page bandingkan supplier · Page riwayat harga |
| **Laporan** | Page: pembelian per part/supplier |

> ⚠️ Surat Jalan **masuk** (`wks_po_supplier_deliveries`, panel ini) ≠ Surat Jalan
> **keluar** (`wks_inv_delivery_orders`, panel Gudang).

Dashboard: PO menunggu approval, PO outstanding (belum diterima penuh), GRN draft/tally.
Aksi cepat: **+ PO**.

---

## 7. Panel KONTRABON (`/kontrabon`) — 🧩 modul AP, detail menyusul

**Fungsi (rencana):** terima & verifikasi **faktur/tagihan supplier** ("tukar faktur"),
**cocokkan** dengan PO & Surat Jalan/GRN (3-way match), catat **PPN & no. faktur pajak**,
tetapkan **jatuh tempo** → memunculkan **hutang (AP)** yang siap dibayar Kasir.

| Grup (rencana) | Resource / Page (Action) |
|---|---|
| Faktur Supplier | SupplierInvoiceResource (kontrabon) — ref PO + GRN, 3-way match, nilai + PPN + faktur pajak, jatuh tempo |
| | Action **Verifikasi/Approve** → hutang diakui |
| Hutang | Page: aging hutang per supplier (akan disempurnakan di modul AP) |

**Status:** panel & peran disiapkan; **tabel `wks_ap_*` dan workflow belum dirinci**
(menunggu modul AP — §9). Untuk sementara isi placeholder + tautan ke PO/GRN.

---

## 8. Panel KASIR (`/kasir`) — 🧩 modul AP, detail menyusul

**Fungsi (rencana):** **pembayaran ke supplier sparepart/ban** atas kontrabon yang
jatuh tempo: pilih faktur → bayar (transfer/giro/tunai) → **alokasi** ke satu/banyak
faktur (boleh partial) → hutang ter-settle. Peran: **Kasir**.

| Grup (rencana) | Resource / Page (Action) |
|---|---|
| Pembayaran | PaymentResource (AP) — pilih supplier/kontrabon jatuh tempo, metode, alokasi ke faktur |
| | Action **Post Pembayaran** → settle hutang (`ApService`, transaksi) |
| Kas/Bank | Page: rekap kas keluar per metode/periode |

> ⚠️ **Kasir ini = pembayaran ke SUPPLIER (AP)**, beda dari "Kasir" *future/dormant*
> untuk pembayaran **customer** (penjualan/invoice) yang masih non-aktif di mode internal.

**Status:** placeholder seperti Kontrabon — detail di modul AP (§9).

---

## 9. Modul Baru: Hutang Supplier / Accounts Payable (`wks_ap_`) — *menyusul*

Kontrabon + Kasir butuh domain **AP** yang belum ada di `MODULES.md`/`DATABASE.md`.
Sub-prefix **`wks_ap_` direservasi**. Posisi dalam alur:

```
PO ─► GRN (stok masuk, cost) ─► KONTRABON (faktur supplier + 3-way match + PPN/faktur pajak + jatuh tempo) ─► KASIR (pembayaran + alokasi) ─► hutang lunas
```

Rencana tabel (akan dirinci saat modul dibangun — **bukan** bagian dokumen ini):
- `wks_ap_supplier_invoices` — kontrabon: no internal, no faktur supplier, supplier_id,
  ref PO/GRN, nilai, tax_type/tax_rate, faktur_pajak_no, invoice_date, due_date, status.
- `wks_ap_supplier_invoice_items` — pencocokan ke baris GRN/PO.
- `wks_ap_payments` — pembayaran kasir: no, supplier_id, paid_at, amount, method.
- `wks_ap_payment_allocations` — alokasi pembayaran ↔ faktur (partial/banyak).
- `ApService` — pengakuan hutang & posting pembayaran (transaksi).

Mode internal tetap berlaku: ini **hutang ke supplier** (sah), berbeda dari
penjualan/invoice **ke customer** (ditunda). Saat modul dibangun: update
`MODULES.md` (modul 8 `wks_ap_`), `DATABASE.md`, `SITEMAP.md`, `WORKFLOWS.md`, dan
isi §7–§8 panel ini.

---

## 10. Panel ADMIN (`/admin`) — Master & Setting

Pengelolaan user/akses, master data, dan pengaturan company. Peran: Owner, Admin.

| Grup | Resource / Page |
|---|---|
| **Pengguna & Akses** | UserResource · RoleResource (Shield) · BranchResource |
| **Master Data** | Customer · Truck (RM dokumen STNK/KIR + reminder, axle-positions) · Driver (Action **Sync HRD** via `HrdGateway`) · Supplier · Warehouse · Uom · Category · Mechanic · ServiceCatalog |
| **Pengaturan** | Company Profile · Settings (pajak/PPN, jam operasional) · DocumentSequenceResource · NotificationRuleResource (+ outbox/log) |
| **Laporan lintas modul** | inventory · PM compliance · tyre · mechanics · turnaround *(revenue/receivables — future)* |

Dashboard: ringkasan kepatuhan (dokumen unit kedaluwarsa, PM), aktivitas user.

---

## 11. Panel VENDOR / Supplier (`/vendor`) — *fase berikutnya*

Supplier eksternal: lihat PO yang ditujukan padanya & daftarkan Surat Jalan masuk.
`canAccessPanel()` hanya bila `user.supplier_id` terisi; **scope ketat** `supplier_id`
+ `company_id` (global scope `BelongsToSupplier`).

| Grup | Resource | Akses |
|---|---|---|
| Dashboard | ringkasan PO aktif & SJ | read |
| Purchase Orders | PurchaseOrderResource | **read-only** |
| Deliveries | SupplierDeliveryResource | tulis (miliknya) |
| Profil | kontak supplier | edit terbatas |

Diaktifkan via feature-flag; sampai aktif, entri SJ oleh staf di panel Purchasing
(`source=manual`).

---

## 12. RBAC — Peta Peran ↔ Panel (Shield)

| Peran | Panel yang bisa diakses |
|---|---|
| *(semua user tenant)* | Launcher (`/home`) — hanya tile panel yang berhak |
| SuperAdmin / SystemSupport | System |
| Owner / Admin | semua panel tenant (Admin penuh; lain read/awas sesuai Policy) |
| ServiceAdvisor | LKM, Servis (+ review Bon di Gudang) |
| KepalaMekanik | Servis (supervisi/QC) + Mekanik |
| Mekanik | Mekanik (update pekerjaan: status, jam, usul Bon, pasang/lepas ban) |
| Gudang / GudangBan | Gudang (+ GRN Purchasing read) |
| Purchasing | Purchasing, Price List, (read Gudang) |
| Finance/AP | Kontrabon |
| Kasir | Kasir (pembayaran supplier) |
| Supplier | Vendor saja |

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
| `/app/lkm/*` | `/lkm/*` |
| `/app/svc/*` | `/servis/*` |
| `/app/inv/*` + `/app/tyre/*` | `/gudang/*` |
| `/app/po/*` + `/app/price/*` | `/purchasing/*` |
| `/app/master/*` + `/app/admin/*` + `/app/reports/*` | `/admin/*` |
| *(baru)* | `/kontrabon/*`, `/kasir/*` |

> **Tindak lanjut:** perbarui `SITEMAP.md` & matriks akses agar konsisten dengan
> 9 panel ini (termasuk Servis bila dikonfirmasi) — dilakukan setelah daftar panel final.

---

## 14. Definition of Done (per panel)

- [ ] `PanelProvider` terdaftar; `canAccessPanel()` memisahkan akses sesuai peran.
- [ ] Panel operasional ber-`->tenant(Company)`; **branch = pemilih header** bersama,
      bukan tenant kedua (R31); satu trait `BelongsToCompany` sebagai sumber kebenaran.
- [ ] Resource di panel & grup navigasi benar; namespace per panel.
- [ ] Otorisasi Policy/Shield; panel/Resource/Action tersembunyi bila tak berhak / modul off.
- [ ] FK select searchable; status badge; filter (kondisi new/used, branch, warehouse) & search SKU/part-no.
- [ ] Aksi stok/uang lewat Service + `DB::transaction()`, idempoten.
- [ ] Field future/dormant ter-feature-flag.
- [ ] Test: happy-path + otorisasi + **isolasi tenant** (R1) + scope supplier (Vendor) + SoD.

---

## 15. Masih Perlu Diputuskan

1. **Panel Servis** ⚑ — dipertahankan terpisah atau digabung (mis. ke LKM)?
2. **Modul AP (`wks_ap_`)** — kapan dirinci (tabel + workflow Kontrabon→Kasir)?
   Apakah perlu **3-way match** ketat (PO=GRN=faktur) atau cukup ref PO + nominal?
3. **Metode bayar Kasir** — transfer/giro/tunai saja, atau perlu kas/bank account & rekonsiliasi?
4. **Launcher** — user yang hanya berhak **satu** panel tenant: auto-redirect lewati
   launcher, atau tetap tampilkan hub? Perlu ringkasan lintas panel di launcher (opsional)?
5. **Vendor panel** — subpath `/vendor` atau subdomain terpisah untuk isolasi lebih kuat?
6. **Impersonate** (System→tenant) — plugin pihak ketiga atau fitur tenant bawaan v5?
