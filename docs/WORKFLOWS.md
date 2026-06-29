# ControlHub Workshop — Dokumentasi Workflow

> Alur proses bisnis lintas modul (v0.1). Melengkapi `MODULES.md` (struktur),
> `DATABASE.md` (tabel/enum), `SITEMAP.md` (halaman). Mode operasi: **INTERNAL/cost**
> — alur penjualan & invoice ditandai *(future)*.

**Legenda:** 👤 aktor/peran · 📄 tabel utama · 🔁 perubahan status · ⚙️ service/aturan.

Daftar workflow:
1. Provisioning Tenant (Core)
2. Setup Awal Company (Admin & Master)
3. Update Price List Supplier
4. Pengadaan: PR → PO (Purchasing) → Penerimaan/GRN (Gudang) → Stok
4b. Sinkronisasi Driver dari ControlHub HRD
4c. Hutang Supplier (AP): Kontrabon (faktur supplier) → Kasir (pembayaran)
5. PMB (Pengantar Dispatcher) & Kendaraan Masuk (LKM)
6. Servis / Work Order (alur utama)
6b. Eksekusi Pekerjaan Mekanik (Handheld — clock in/out)
7. Servis Berkala (PM)
8. Siklus Hidup Ban (Tyre)
8b. Penerimaan Part Bekas (Teardown / Copotan)
8c. Core Return (Old-for-New) & Penjualan Scrap
8d. Sesi Kerja Gudang (Opening / Closing)
9. Stock Opname & Penyesuaian
10. Transfer Stok Antar Gudang
10b. Surat Jalan (Barang Keluar) + Tally Sheet
11. Invoicing *(future/dormant)*

---

## 1. Provisioning Tenant (Core)

👤 SuperAdmin · 📄 `wks_core_companies`, `users`, `wks_ms_branches`, `wks_adm_roles`

```
SuperAdmin buka /system/companies/create
  → isi data company (name, code, NPWP, timezone)
  → sistem buat company (status=active)
  → seed default: branch utama, roles standar (Owner/Admin/…), UoM & kategori default
  → buat user Admin pertama (company_id terisi) + kirim undangan/sandi awal
  → (opsional) set plan & feature-flag modul aktif
Selesai → Admin company bisa login ke /app
```
⚙️ Seed default di-clone dari template global Core. Semua data baru otomatis ber-`company_id`.

---

## 2. Setup Awal Company (Admin & Master)

👤 Owner/Admin · 📄 `wks_ms_*`, `wks_adm_*`, `users`

```
1. Branch          → tambah cabang (/app/admin/branches)
2. Users & Roles   → buat user operasional + assign role (RBAC)
3. Master gudang   → warehouses (per branch) + locations (rak/bin)
4. Master referensi→ UoM, kategori (part/ban), tipe truk
5. Master mitra    → suppliers, customers (cost-center/armada), mechanics
6. Master unit     → trucks (plat, VIN, tipe, KM awal) + jadwal PM
7. Master barang   → spare_parts, tyre_products (model ban)
8. Pengaturan      → pajak (PPN), penomoran dokumen (document_sequences)
Selesai → siap operasional
```
⚙️ Penomoran dokumen (PMB/LKM/WO/PO/GRN) diatur di `wks_adm_document_sequences`.

---

## 3. Update Price List Supplier

👤 Purchasing/Admin · 📄 `wks_price_lists`, `wks_price_list_items`, `wks_price_histories`

```
Buat/pilih price list per supplier (set currency, tax_type, tax_rate)
  → tambah/ubah item harga (spare_part / tyre_product)
     ├─ per item (input langsung)
     ├─ massal (% / per kategori)
     └─ impor Excel/CSV
  → setiap perubahan harga → catat wks_price_histories (lama→baru, by, kapan)
  → set valid_from / valid_to / is_active
```
⚙️ Multi-supplier: satu part bisa muncul di beberapa price list → dibandingkan saat PO.
⚙️ `tax_type` menentukan apakah harga sudah termasuk PPN (inclusive/exclusive/non_pkp).

---

## 4. Pengadaan: PR → PO (Purchasing) → Penerimaan/GRN (Gudang) → Stok

👤 **Purchasing** (PR/PO + approval, lalu read), **Gudang** (terima fisik + SJ supplier + GRN), approver Owner/Admin
📄 `wks_po_*`, `wks_inv_stock_movements`/`stock_items`, `wks_tyre_tyres`/`movements`
⚙️ **SoD:** PO dibuat/di-approve di **Purchasing**; **barang tiba & diterima di GUDANG**
(SJ supplier + GRN + posting). Purchasing **lihat-saja** status SJ/GRN untuk pantau PO.

```
(opsional) Purchase Request          🔁 PR: draft → submitted
   • dari stok ≤ reorder_point atau kebutuhan WO
        │
        ▼
Purchase Order (pilih supplier)      🔁 PO: draft
   • ambil harga + PPN via PricingService (bandingkan supplier)
   • harga & pajak DI-SNAPSHOT ke wks_po_order_items
        │ approval
        ▼                            🔁 PO: approved
SURAT JALAN MASUK dari supplier (📄 wks_po_supplier_deliveries) — sparepart & BAN
   • 👤 SUPPLIER isi SJ sendiri di web /vendor (source=portal) → operator TAK menyalin
     (fallback: staf input source=manual)
   • atas PO; supplier_do_no + item (part/ban) + qty_shipped per baris   🔁 SJ: submitted
   • ban: hanya product+qty; serial diregistrasi nanti saat GRN
        │
        ▼
Barang tiba DI GUDANG → 👤 Operator Gudang SERAH TERIMA (GRN, WAJIB pilih PO)  🔁 GRN: draft
   • tarik baris dari PO (qty_doc); rujuk SJ masuk (supplier_delivery_id) bila ada,
     selain itu isi do_supplier_no manual (fallback)        🔁 SJ: received
        │ hitung fisik
        ▼                            🔁 GRN: checking
   Tally Sheet (bongkar) → counted_qty per item & kondisi → isi qty_received
        │ posting
        ▼                            🔁 GRN: posted  | PO: partial/received
   ├─ item PART → ⚙️ StockService (konversi UOM → dasar via uom_factor):
   │     stock_movement (in, condition, unit_cost base, location_id) → qty_on_hand↑ → WAC
   └─ item BAN  → buat unit wks_tyre_tyres per serial (in_stock) + tyre_movements (in)
        │ semua item diterima
        ▼                            🔁 PO: closed
```
⚙️ **Tidak ada serah terima tanpa PO** (`po_id` wajib).
⚙️ Penerimaan sebagian → PO `partial`; `qty_received` per item bertambah.
⚙️ Stok TIDAK pernah diubah langsung — selalu lewat movement (per kondisi & lokasi rak).
⚙️ **Putaway**: `location_id` bin diisi sesuai `slotting_mode` gudang — `fixed`=wajib bin default
   SKU, `hybrid`=disarankan (`wks_inv_part_locations`), `dynamic`=bebas; kapasitas bin terlampaui → peringatan (soft).

---

## 4b. Sinkronisasi Driver dari ControlHub HRD

👤 Admin · 📄 `wks_ms_drivers` · ⚙️ `HrdGateway` (feature-flag integrasi)

```
Aktifkan integrasi HRD (config/integrations.php: base_url, token, mapping company)
  → /app/master/drivers/sync (manual) atau cron terjadwal
  → tarik "Mitra Kerja" berperan driver dari HRD (per hrd_company_id yang dipetakan)
  → untuk tiap mitra kerja:
       ├─ sudah ada (match hrd_mitra_id) → update field inti (read-only) + hrd_synced_at
       └─ baru → buat wks_ms_drivers (source=hrd, hrd_mitra_id, hrd_company_id)
  → driver source=manual TIDAK ditimpa
```
⚙️ Driver di HRD = entitas **"Mitra Kerja"**; HRD = system of record, Workshop kelola
   data operasional (SIM, penugasan unit). Pemetaan: driver ↔ mitra kerja (`hrd_mitra_id`),
   tenant Workshop ↔ tenant HRD (`hrd_company_id`).
⚙️ Metode koneksi (API / shared-DB / impor berkala) — lihat `MODULES.md` §15.

---

## 4c. Hutang Supplier (AP): Kontrabon (faktur supplier) → Kasir (pembayaran)

👤 **Finance/AP** (Kontrabon: salin tagihan, cek satu per satu, approve) · **Kasir** (rekening, request bayar maker→checker, realisasi) · approver ≠ verifikator · pengaju request ≠ checker
📄 `wks_ap_kontrabons`/`_invoices`, `wks_ap_bank_accounts`, `wks_ap_payment_requests`/`_items`, `wks_ap_payments`, ref `wks_po_goods_receipts`/`wks_po_orders`
⚙️ Hilir **PO→GRN** (§4). **SoD lintas panel:** Finance/AP **akui** hutang (panel `/kontrabon`) ≠
Kasir **bayar** (panel `/kasir`). Semua posting via **`ApService`** (`DB::transaction()`, idempoten).
⚠️ Hutang ke **supplier** (sah, mode internal) — beda dari invoice **customer** (§11, future/dormant).
🔑 **Kontrabon = dokumen KITA** menyalin tagihan supplier; 1 kontrabon (per supplier) bisa memuat **1..n** tagihan.

```
GRN posted (§4) → barang & cost masuk; PO partial/received
        │  supplier setor tagihan ("tukar faktur") — bisa beberapa faktur sekaligus
        ▼
👤 FINANCE/AP buat KONTRABON (📄 wks_ap_kontrabons; per supplier)   🔁 kontrabon: draft
   • header: supplier, received_date, due_date (= +supplier.payment_term_days, boleh override)
   • SALIN tiap tagihan jadi BARIS (📄 wks_ap_kontrabon_invoices, 1..n):
        no faktur supplier + no Faktur Pajak (NSFP) + tanggal + nilai + PPN ; rujuk GRN/PO
        │
        ▼  REVIEW SATU PER SATU (cek tiap baris)                    🔁 kontrabon: checking
   per baris → centang CHECKLIST (4):
        ☑ barang sudah diterima (cocok GRN)   ☑ Surat Jalan
        ☑ Faktur Pajak (PPN)                  ☑ PO & nominal cocok
        ├─ keempat ☑ → baris check_status=ok
        └─ ada kurang/selisih → problem (+ check_note) → tindak lanjut ke supplier
        │ ✋ gate: semua baris ok?
        ▼                                                            🔁 kontrabon: verified  (verified_by)
👤 APPROVER (Finance) AKUI HUTANG                                    🔁 kontrabon: approved  (approved_by ≠ verified_by)
   → ⚙️ ApService: hutang efektif = outstanding (= total − credit_amount − paid_amount)
   ✋ hanya kontrabon approved/partially_paid yang bisa dibayar
   • (opsional) NOTA RETUR supplier (§10c ⑦) → credit_amount↑ → outstanding↓
        │ jatuh tempo → muncul di aging
        ▼
   [prasyarat Kasir] 👤 Kasir kelola MASTER REKENING (📄 wks_ap_bank_accounts: bank/kas, supports giro/digital)
        │
        ▼
👤 KASIR (MAKER) buat REQUEST PEMBAYARAN (📄 wks_ap_payment_requests) — per supplier  🔁 request: draft
   • pilih kontrabon approved jatuh tempo → ALOKASI (📄 wks_ap_payment_request_items, partial/banyak)
   • pilih METODE (transfer/giro/digital) + REKENING sumber → submit  🔁 request: submitted
   ✋ guard: Σ item = requested_amount ; amount ≤ outstanding per kontrabon (anti over-pay)
        │
        ▼  👤 CHECKER approve/reject (SoD: pengaju ≠ checker)         🔁 request: approved / rejected
        │
        ▼  eksekusi hanya bila request approved
👤 KASIR REALISASI PEMBAYARAN (📄 wks_ap_payments; ref request)        🔁 payment: draft
   ├─ TRANSFER/DIGITAL → input digital banking (maker-checker bank) / transfer → reference_no/digital_ref
   │        │ Post → 🔁 payment: posted
   └─ GIRO → REGISTER GIRO dulu (📄 wks_ap_giros)                      🔁 giro: registered
            • input di APLIKASI sebelum tanda tangan (no, nilai, jatuh tempo, atas nama)
            → 🖨️ PRINT lembar register/voucher (print_count++)         🔁 giro: printed
            → ✍️ giro FISIK ditandatangani (signed_by, otorisasi)      🔁 giro: signed
            → 🔍 PERIKSA: giro fisik HARUS SESUAI SISTEM (dicek lewat print) 🔁 giro: verified
                  ✋ beda no/nilai/atas nama/jatuh tempo → tahan/perbaiki (tak boleh serah)
            → giro DISERAHKAN ke supplier                              🔁 giro: released ; 🔁 payment: posted
        │ payment posted
        ▼                                                            🔁 request: paid
   → ⚙️ ApService (transaksi): tiap item request → kontrabons.paid_amount↑
        → outstanding habis → 🔁 kontrabon: paid ; sebagian → 🔁 kontrabon: partially_paid
   • GIRO cair → 🔁 giro: cleared ; 🔁 payment: cleared
        └─ gagal cair → 🔁 giro: bounced ; 🔁 payment: cancelled (hutang kembali outstanding)
```
⚙️ **SoD giro:** Kasir register (`registered_by`) ≠ penanda tangan (`signed_by`) ≠ pemeriksa
(`verified_by`); print = alat kontrol cocokkan giro fisik vs sistem.
⚙️ `outstanding` = kolom **GENERATED** (sumber kebenaran sisa hutang). **Aging** = group
`outstanding` per supplier × bucket umur dari `due_date`.
⚙️ Anti dobel-input faktur: `unique(company_id, supplier_id, supplier_invoice_no)` di baris.
⚙️ Ada baris `problem`/sengketa → `kontrabon: rejected` (reject_reason) dikembalikan ke supplier; batal → `cancelled`.

---

## 5. PMB (Pengantar Dispatcher) & Kendaraan Masuk (LKM)

👤 Dispatcher (PMB) / ServiceAdvisor·Service Officer (LKM) / Gate
📄 `wks_pmb_requests` (PMB) · `wks_lkm_entries`, `wks_lkm_inspections`, `wks_lkm_gateouts` (LKM)

⚙️ **PMB & LKM = modul/entitas terpisah** (PMB = pengantar dari pos Dispatcher; LKM = truk
benar-benar masuk). **Dua mode** (setting company `lkm_intake_mode`, di Admin):

**Mode `dispatcher_permit` (lewat pengantar):**
```
Driver datang ke pos Dispatcher → 👤 Dispatcher TERBITKAN PMB     🔁 PMB: issued
  • Dispatcher: cocokkan truck_id/customer + sopir ke master · keluhan/tujuan · pmb_no (doc_type pmb)
  → 🖨️ cetak PMB (surat bernomor) → driver bawa ke gate
Truk tiba → 👤 Service Officer CARI REFERENSI PMB di sistem (by no PMB / plat / scan)
  • cari PMB status=issued di branch aktif (bukan ketik ulang data)
  → bila PMB ketemu: form LKM ter-prefill (truk · customer · sopir · keluhan), lengkapi km_in
        → buat LKM merujuk PMB                                    🔁 LKM: entered
        └─ ⚙️ PmbService: set PMB.status=used + used_lkm_id (transaksi, idempoten, anti dobel-pakai) 🔁 PMB: used
  → bila PMB tak ketemu: belum/tak ada pengantar → SOP (driver kembali ke Dispatcher)
  PMB tak jadi dipakai → 👤 Dispatcher BATALKAN (note wajib)      🔁 PMB: cancelled
```
**Mode `direct` (langsung masuk, tanpa PMB):**
```
Truk tiba → Gate/Satpam buat LKM (pmb_id null)   🔁 LKM: entered
```
**Lanjutan (kedua mode):**
```
  • LKM: pilih truck (+ customer), entry_at, driver, km_in, keluhan (rujuk pmb_id bila ada)
  → checklist inspeksi awal + foto (wks_lkm_inspections)
  → buat Work Order dari LKM                    🔁 LKM: in_progress
  ... (servis berjalan, lihat #6) ...
  → pekerjaan selesai                            🔁 LKM: done
  → Gate-out: catat exit_at, km_out, surat jalan 🔁 LKM: exited
```
⚙️ `km_in` memperbarui `trucks.current_km`; dasar perhitungan PM.
⚙️ SoD: Dispatcher menerbitkan PMB ≠ Service Officer membuat LKM. PMB **tidak** auto-terbit LKM.

---

## 6. Servis / Work Order (alur utama)

👤 ServiceAdvisor, KepalaMekanik, Mekanik, Gudang
📄 `wks_svc_work_orders`/`work_order_items`, `wks_inv_*`, `wks_tyre_*`

```
LKM → Work Order                               🔁 WO: queued
  ⚠️ fase sekarang: TANPA estimasi biaya — biaya terbentuk dari pemakaian AKTUAL (lihat akhir §6)
  → item biaya (jasa/part/ban) di wks_svc_work_order_items TERISI saat pemakaian aktual (bukan estimasi di depan)
  → BON PENGELUARAN SPAREPART (📄 wks_inv_part_issues, ref wo_id → lkm_id, truck_id)
        👤 Mekanik USUL (qty_requested)              🔁 issue: draft→submitted
        👤 Service Officer REVIEW (approve qty_approved / reject)  🔁 issue: approved/rejected
            └─ approved → ⚙️ StockService reserve (qty_reserved↑)  🔁 WO: in_progress
            └─ stok kurang → buat PR/PO (#4)                       🔁 WO: waiting_part
  → mekanik AMBIL/ditugaskan WO → SUSUN WO PLAN bersama (Mekanik + Service Officer):
        TASK (wks_svc_wo_tasks) + LANGKAH/step (wks_svc_wo_task_steps; boleh dari template jasa) (lihat §6b)
  → PENGELUARAN/PEMAKAIAN part → 👤 Gudang issue → ⚙️ StockService:
        stock_movement (type=out, ref=part_issue, unit_cost=avg_cost) → qty_on_hand↓, reserved↓
        🔁 issue: issued/partially_issued
        → bila qty_on_hand < 0 → tidak diblokir; buat stock_alert (negative_stock) + notifikasi
        → wo_item.unit_cost diisi dari avg_cost (HPP; beku bila saldo ≤ 0)
  → CORE RETURN (part baru non-consumable) → 📄 wks_inv_core_returns (1:1 wo_item)
        bekas RUSAK kembali ke gudang (holding/scrap) + foto bukti + failure_reason
        ⚠️ bukan stok layak-pakai (tak masuk stock_movements); telusur truck→LKM→WO
  → PASANG/ROTASI ban → ⚙️ TyreService (lihat #8); wo_item ban dgn unit_cost
  → QC                                          🔁 WO: qc
  → ✋ gate: semua core non-consumable sudah kembali? belum → tak bisa done
  → selesai → hitung total_cost (Σ line_cost)   🔁 WO: done
  → unit diserahkan (gate-out LKM)              🔁 WO: delivered
  → update next PM (#7); masuk riwayat & laporan biaya per unit
```
⚙️ `unit_cost` SELALU diisi (mode internal). `unit_price` kosong → diaktifkan saat fitur jual.
⚙️ Biaya WO = part (HPP/WAC) + ban + jasa (std_cost / jam × rate).

---

## 6b. Eksekusi Pekerjaan Mekanik (Handheld — clock in/out)

👤 Mekanik (ambil WO, susun plan, kerjakan) · Service Officer/KepalaMekanik (co-susun, tugaskan, supervisi)
📄 `wks_svc_wo_tasks`, `wks_svc_wo_task_steps`, `wks_svc_task_assignments`, `wks_svc_task_time_logs` · ⚙️ `TaskTimeService`

```
WO siap dari LKM (TANPA estimasi biaya) → 👤 Mekanik AMBIL WO / ditugaskan   🔁 task: assigned
SUSUN WO PLAN (👤 Mekanik, bisa BERSAMA Service Officer) — setelah ambil WO:
  → pecah jadi TASK (apa yang dikerjakan)                      🔁 task: pending→assigned
  → rinci LANGKAH/step tiap task — wks_svc_wo_task_steps       🔁 step: pending
        (opsional disalin dari template jasa wks_svc_service_steps, lalu sesuaikan)
MEKANIK di handheld (panel Filament responsif ATAU PWA offline):
  → MULAI task → ⚙️ buka segmen time_log (started_at, ended_at=null)   🔁 task: in_progress
        ✋ guard: 1 segmen aktif/mekanik (tak bisa 2 task sekaligus)
  → CENTANG langkah satu per satu (done/skipped + catatan)     🔁 step: done/skipped
  → (+) TAMBAH langkah ADHOC bila temukan kerja baru di lapangan (source=adhoc)
  → JEDA (wait_part/break/qc/shift_end) → ⚙️ tutup segmen (ended_at, duration)  🔁 task: paused
  → LANJUT → buka segmen baru                                   🔁 task: in_progress
  → (butuh part) → Usul Bon ke gudang (alur #6); task.requires_part=true
  → SELESAI → tutup segmen + isi result_note                   🔁 task: done
        ⚙️ recompute wo_tasks.actual_minutes = Σ duration_minutes (labor)
        ⚠️ bila masih ada langkah pending → UI peringatkan (boleh tetap done dgn catatan)
  → (opsional) BATAL task                                       🔁 task: cancelled

PWA OFFLINE: event (start/stop) diantre lokal dgn client_event_id (uuid)
  → saat online → POST /api/svc/time-logs/sync (batch)
  → ⚙️ server idempoten via unique(company_id, client_event_id); set synced_at
  → konflik (overlap/segmen menggantung) → ditandai untuk ditinjau KepalaMekanik
```
⚙️ Timestamp **selalu dari server** (anti-manipulasi); klien hanya kirim event + waktu lokal (audit).
⚙️ **WO `done`** mensyaratkan semua task `done`/`cancelled` (selain gate core-return §6/§8c).
⚙️ Pengukuran: **labor-minutes** = Σ durasi (produktivitas/biaya) · **wall-clock** = rentang (turnaround).
🔐 SoD: Mekanik susun/update plan task miliknya (scope assignment); tak bisa ubah biaya/tutup WO.

---

## 7. Servis Berkala (Preventive Maintenance)

👤 Sistem (terjadwal) + ServiceAdvisor · 📄 `wks_svc_pm_schedules`, `wks_ms_trucks`

```
Tiap unit punya jadwal PM (interval km / hours / days)
  → sistem hitung jatuh tempo (next_due_km vs current_km, atau next_due_date)
  → unit mendekati/lewat tempo → muncul di /app/svc/pm/due (+ reminder)
  → ServiceAdvisor buat Work Order PM (alur #6)
  → WO selesai → update last_done_km/at → hitung next_due_* berikutnya
```
⚙️ `current_km` ter-update dari LKM (km_in) atau pencatatan manual.

---

## 8. Siklus Hidup Ban (Tyre)

👤 Gudang/GudangBan, Mekanik · 📄 `wks_tyre_*`, `wks_ms_axle_positions`, `wks_inv_shift_sessions`
⚙️ Semua mutasi via **TyreService** (DB::transaction) — **wajib Sesi Kerja Gudang terpadu** (di-tag).

```
3 TAHAP GUDANG: [Gudang Ban Baru] → [Gudang Bekas] → [Gudang Afkir]

① Terima dari GRN (#4) → unit ban dibuat per serial   🔁 tyre: in_stock new @ GUDANG BAN BARU  (receipt, acquired_cost)
        → simpan di lokasi/bin (location_id)
② pasang ke truk → ⚙️ validasi posisi vs axle_positions 🔁 tyre: installed  (movement install)
        → wks_tyre_installations (removed_at=null)   🔒 partial-unique: 1 ban/slot
   inspeksi berkala (tread, pressure) → rekomendasi  📄 tyre_inspections
        → tread < min_tread_mm → ⚠️ wks_tyre_alerts (tread_low)
③ lepas (km_remove, removal_reason) → tutup instalasi 🔁 tyre: removed  (movement removal)
        → total_km_run += (km_remove − km_install)
        ├─ masih layak → stok used @ GUDANG BEKAS     🔁 tyre: in_stock (condition=used)
        │     → keputusan: pakai lagi / vulkanisir / usul afkir
        ├─ kirim vulkanisir → supplier                🔁 tyre: retreading (movement retread_send)
        │     → terima kembali: cost→book_value, retread_count↑ 🔁 in_stock (retread_return)
        │           └─ result=failed → usul afkir
        └─ tidak layak → usul afkir
④ KONFIRMASI AFKIR — 👤 Kepala Gudang/Supervisor (izin tyre.condemn)  🔁 tyre: afkir  (movement condemn, alasan, confirmed_by)
        ✋ SoD: operator usul ≠ supervisor konfirmasi
⑤ PINDAH ke GUDANG AFKIR                               🔁 tyre: afkir @ GUDANG AFKIR  (movement transfer)
⑥ JUAL AFKIR (lot) → wks_tyre_disposals (sold + proceeds)  🔁 tyre: scrapped (movement scrap)
        → buang (discarded) bila tak terjual

Opname ban → scan serial → match/missing/extra/misplaced → adjustment/update lokasi
```
⚙️ Tiap ban unik per `serial_no`; harga/model mengacu `wks_tyre_products`. **Tanpa WAC** —
   tiap unit dinilai sendiri: `book_value = acquired_cost + Σ retread_cost`.
⚙️ **Biaya per KM** = `book_value / total_km_run`. Posisi divalidasi → diagram layout ban.

---

## 8b. Penerimaan Part Bekas (Teardown / Copotan)

👤 Gudang, Mekanik · 📄 `wks_inv_stock_movements`, `wks_inv_stock_items` (condition=used)

```
Part bekas dari unit (lepasan saat WO / pembongkaran)
  → nilai kondisi: layak pakai? (used / rebuilt) atau scrap
  → /app/inv/teardown → input part, qty, kondisi, gudang/rak (condition_scope=used)
  → ⚙️ StockService: stock_movement (type=in, condition=used,
        ref_type=teardown/wo_return, unit_cost taksiran) → stok USED↑
```
⚙️ Masuk **tanpa PO** (bukan pembelian). Stok used punya saldo & WAC terpisah dari new.
⚙️ Part used bisa dipakai kembali di WO (dipilih saat request part, kondisi=used).
⚙️ Beda dari **Core Return** (#8c): teardown = part **layak pakai**; core return = part **rusak**.

---

## 8c. Core Return (Old-for-New) & Penjualan Scrap

👤 Gudang, Mekanik · 📄 `wks_inv_core_returns`, `wks_inv_scrap_disposals`

```
WO pasang part baru non-consumable (categories.is_consumable=false)
  → WAJIB kembalikan part bekas RUSAK   ⚠️ gate: WO tak bisa done tanpa ini
  → /app/inv/core-return → input wo_item (1:1), qty (= qty baru), failure_reason, foto bukti
        simpan di gudang/rak holding (warehouse_id, location_id)   🔁 core: pending→stored
  → telusur asal otomatis: truck_id, lkm_id, wo_id (dari WO)
  ⚠️ TIDAK masuk stock_movements/stock_values (bukan stok layak-pakai; assessed_value=nilai scrap)

Akumulasi bekas → buat lot scrap
  → /app/inv/scrap-disposal → pilih core_returns → set disposal_type (sold/discarded)
  → core_returns.disposition=scrapped/disposed + scrap_disposal_id   🔁 core: released
  → (pendapatan jual scrap = future)
```
⚙️ Consumable (oli/filter/grease/gasket) **dikecualikan** dari core return.
⚙️ Tujuan utama: **bukti** part memang rusak (anti-fraud) → lalu dijual besi tua.

---

## 8d. Sesi Kerja Gudang (Opening / Closing)

👤 Operator Gudang (BUKA) · Kepala Gudang/Supervisor (TUTUP) · 📄 `wks_inv_shift_sessions`/`_balances`, `wks_inv_stock_movements`

```
MASUK PANEL GUDANG → cek sesi hari ini (warehouse, business_date)
   → belum open → ⛔ GATE: wajib BUKA SESI dulu; semua Resource gudang terblok
👤 Operator BUKA SESI (gudang + tanggal)             🔁 session: open
   → snapshot SELURUH saldo gudang (opening_qty per SKU+kondisi+lokasi) + kehadiran ban
   → 1 SESI/GUDANG/HARI (unique warehouse_id+business_date); sesi dipakai BERSAMA seharian
   → (jaring kedua) tanpa sesi open → ⛔ StockService tolak movement
        │
        ▼  (selama hari kerja — banyak operator boleh ikut)
Semua mutasi (issue/GRN/transfer/adjustment/teardown/core, part & ban)
   → ⚙️ StockService/TyreService tag shift_session_id; pelaku tercatat di created_by
        │
        ▼
Akhir hari → 👤 KEPALA GUDANG/SUPERVISOR TUTUP SESI   🔁 session: closed
   → ⛔ operator biasa TIDAK boleh menutup (izin closing = supervisor)
   → ringkas movement ter-tag: total_movements, in/out qty & nilai
   → snapshot saldo akhir (closing_qty); diff = closing − (opening + in − out)
   → diff ≠ 0 → anomaly_count↑ (perubahan tak ter-tag) → review
   → ⚙️ update snapshot gudang (§7c)
   → setelah closed → siklus hari itu FINAL; kerja lagi = Buka Sesi besok
        │
        ├─ belum ditutup >24 jam → ⚙️ Job terjadwal cek sesi open
        │     → kirim WA + email (event shift.session_overdue) sesuai wks_adm_notification_rules
        │     → penerima/ambang/eskalasi/ulang DIKONFIGURASI DI MASTER; tandai overdue_notify_step
        └─ lupa tutup → Supervisor/Job akhir hari → force_closed (cegah sesi menggantung lintas hari)
```
⚙️ Akuntabilitas presisi = movement ter-tag sesi + `created_by` per aksi; snapshot full = rekonsiliasi state gudang.
⚙️ Sesi harian dipakai banyak operator; diff sesi = total perubahan tak ter-tag sepanjang hari.
⚙️ Notifikasi 24 jam reusable (lapisan Notifikasi modul Admin); WA via gateway, email via Mail.

---

## 9. Stock Opname & Penyesuaian

👤 Gudang · 📄 `wks_inv_stock_opnames`/`opname_items`, `wks_inv_stock_movements`

```
Buat opname (pilih gudang)                 🔁 opname: draft
  → tarik system_qty per item               🔁 opname: counting
  → input counted_qty (hitung fisik) → diff_qty otomatis
  → posting                                  🔁 opname: posted
        → ⚙️ StockService buat stock_movement (type=adjustment, qty=diff)
        → qty_on_hand disesuaikan
```
⚙️ Hanya posting yang mengubah stok; sebelum posting = draft/hitung saja.

---

## 10. Transfer Stok Antar Gudang

👤 Gudang · 📄 `wks_inv_stock_movements`, `wks_inv_stock_items`

```
Pilih part, gudang asal → gudang tujuan, qty
  → ⚙️ StockService (satu transaksi):
        movement (transfer_out) di gudang asal  → qty_on_hand↓
        movement (transfer_in)  di gudang tujuan → qty_on_hand↑ (bawa avg_cost)
```
⚙️ Ban dipindah lewat `wks_tyre_movements` (type=transfer), per unit serial.
⚙️ Transfer fisik antar lokasi/cabang bisa disertai **Surat Jalan** (#10b).

---

## 10b. Surat Jalan (Barang Keluar) + Tally Sheet

👤 Gudang · 📄 `wks_inv_delivery_orders`/`_items`, `wks_inv_tally_sheets`/`_items`, `wks_inv_stock_movements`

```
Buat Surat Jalan (pilih do_type)              🔁 DO: draft
  • transfer antar gudang / issue ke WO-site / retur ke supplier
  • isi item (part, kondisi, qty, lokasi asal), penerima, kendaraan/sopir
  → Tally Sheet (muat): counted_qty per item → konfirmasi vs doc_qty
  → posting / kirim                            🔁 DO: in_transit
        → ⚙️ StockService: movement (out / transfer_out) → qty_on_hand↓
  → diterima di tujuan                          🔁 DO: delivered
        → (bila transfer) movement (transfer_in) di gudang tujuan
```
⚙️ Surat jalan **barang** (≠ surat jalan unit truk di LKM gate-out, #5).
⚙️ Tally sheet memverifikasi fisik saat muat sebelum barang keluar.

---

## 10c. Transaksi Gudang Lain — Pinjam, Retur Supplier, Retur Bon

👤 Gudang (⑦ retur supplier: + Purchasing/Owner) · 📄 `wks_inv_part_loans`, `wks_inv_purchase_returns`, `wks_inv_issue_returns`, `wks_inv_stock_movements`
⚙️ Semua wajib Sesi `open`, ter-tag, via `StockService`. (Lihat taksonomi MODULES §8.)

```
⑤ PEMINJAMAN (storing keluar, wajib kembali)
  Buat Peminjaman (borrower, item, qty)        🔁 loan: open
   → ⚙️ movement loan_out (qty keluar; TETAP aset, tak dibebankan ke WO)
   → Terima Kembali (sebagian/penuh)            🔁 loan: partially_returned → returned
        → ⚙️ movement loan_return (qty masuk)
   → bila TAK kembali → Konversi jadi Bon (converted_issue_id) → HPP baru dibebankan ke WO

⑦ RETUR KE SUPPLIER (memotong tagihan)
  Buat Retur (ref PO/GRN, alasan)               🔁 pr: draft
   → Post → ⚙️ movement return_supplier (stok turun, WAC)   🔁 pr: posted
   → nota retur → kurangi hutang supplier (AP §9)  🔁 pr: credited (credit_note_no/amount)

⑧ RETUR BON (part baru dari LKM TAK JADI PAKAI)
  Buat Retur Bon (ref Bon/WO, item, qty)        🔁 ir: draft
   → Post → ⚙️ movement return_wo (stok naik, new)  🔁 ir: posted
   → ⚙️ reverse HPP: wo_item.line_cost / total_cost berkurang qty×unit_cost
```
⚙️ ⑧ (part **baru** tak terpakai) ≠ ③ Core/Teardown (part **bekas**). ⑤ Loan (masih aset) ≠ ② Bon (pemakaian).

---

## 10d. Audit Gudang (kontrol independen — TIDAK mengubah stok)

👤 **Auditor** (independen; verifikasi) · PIC Gudang (tindak lanjut) · 📄 `wks_inv_audits`/`_items`/`_findings`, (trail: `wks_inv_stock_movements` + `wks_core_audit_logs`)
⚙️ SoD: Auditor ≠ operator/Kepala Gudang. Audit **read-only stok**; koreksi via opname/penyesuaian.

```
AUDIT FORMAL
  👤 Auditor jadwalkan Audit (type, scope gudang/kategori/periode)   🔁 audit: planned
   → mulai → CEK FISIK independen (audit_items: book_qty vs counted_qty) 🔁 audit: in_progress
        │ diff signifikan / penyimpangan SOP / anomali
        ▼
   Catat TEMUAN (type, severity, expected vs actual, rekomendasi)    🔁 finding: open
   → (critical → notifikasi Owner/Admin)                            🔁 audit: review
        │
        ▼  TINDAK LANJUT (bukan oleh auditor)
   👤 PIC Gudang isi corrective_action                               🔁 finding: in_progress
   → koreksi saldo via OPNAME/PENYESUAIAN (movement tertelusur) → link resolution_ref  🔁 finding: resolved
        │
        ▼  VERIFIKASI
   👤 Auditor cek koreksi → 🔁 finding: verified/closed (atau rejected bila tak valid)
   → semua temuan selesai → 🔁 audit: closed (summary)

REVIEW ANOMALI (sumber temuan ad-hoc, tanpa audit formal)
  Papan: stok negatif (stock_alerts) · selisih sesi (anomaly_count/diff_qty) · movement tak wajar
   → 👤 Auditor "Promosikan jadi Temuan" (finding.source_type/source_id) → alur temuan di atas

AUDIT TRAIL (read-only, forensik)
  Gabungan ledger movement (siapa/kapan/ref/sesi) + core_audit_logs (before→after master/konfig)
   → filter per SKU/gudang/user/tanggal/jenis — append-only, tak bisa diubah
```
⚙️ **Temuan tak menyentuh saldo** — hanya menautkan ke dokumen koreksi (jejak audit utuh).
⚙️ Auditor **tak boleh** menutup temuannya sendiri sebagai pelaksana koreksi (independensi).

---

## 11. Invoicing *(future / dormant — mode internal)*

👤 Kasir/Admin · 📄 `wks_svc_invoices`, `wks_svc_payments`

```
[NONAKTIF saat ini] Saat fitur jual diaktifkan (feature-flag):
WO done → buat Invoice (single / konsolidasi armada)  🔁 invoice: draft → issued
  → hitung subtotal (unit_price) + PPN → total
  → pembayaran (wks_svc_payments) → 🔁 invoice: partial / paid
```
⚙️ Memakai `unit_price`/`std_price` yang kini nullable; tabel sudah disiapkan agar
tidak perlu migrasi besar saat diaktifkan.

---

## Ringkasan Status (state) per Entitas

| Entitas | Status (enum) |
|---|---|
| Purchase Order | draft → approved → partial → received → closed (· cancelled) |
| Surat Jalan Masuk (Supplier) | draft → submitted → received (· cancelled) |
| Serah Terima (GRN) | draft → checking → posted |
| Kontrabon (Tanda Terima Tagihan/AP) | draft → checking → verified → approved → partially_paid → paid (· rejected · cancelled) |
| Baris Tagihan (cek satu per satu) | pending → ok / problem |
| Request Pembayaran (maker→checker) | draft → submitted → approved → paid (· rejected · cancelled) |
| Realisasi Pembayaran Supplier (AP) | draft → posted → cleared (giro) (· cancelled) |
| Register Giro | registered → printed → signed → verified → released → cleared (· bounced · cancelled) |
| Surat Jalan Keluar (DO) | draft → in_transit → delivered (· cancelled) |
| Tally Sheet | draft → completed |
| PMB (Permintaan Mobil Masuk) | issued → used / cancelled |
| LKM | entered → in_progress → done → exited |
| Work Order | queued → waiting_part → in_progress → qc → done → delivered |
| Bon Pengeluaran Sparepart | draft → submitted → approved/rejected → partially_issued → issued (· cancelled) |
| Peminjaman Part (Loan) | open → partially_returned → returned (· cancelled) |
| Retur ke Supplier | draft → posted → credited (· cancelled) |
| Retur Bon (part tak jadi pakai) | draft → posted (· cancelled) |
| Core Return | pending → stored → released |
| Sesi Kerja Gudang | open → closed (· force_closed) |
| Stock Opname | draft → counting → posted |
| Audit Gudang | planned → in_progress → review → closed (· cancelled) |
| Temuan Audit (Finding) | open → in_progress → resolved → verified → closed (· rejected) |
| Ban (Tyre) | in_stock (baru/bekas) → installed → removed → (retreading) → **afkir** → scrapped |
| Ban (Tyre) | in_stock → installed → removed → (retreading →) in_stock / scrapped |
| Invoice *(future)* | draft → issued → partial → paid |

---

## Prinsip Lintas Workflow

1. **Stok hanya berubah via movement** (`StockService`/`TyreService`) dalam `DB::transaction()`.
2. **Harga & biaya di-snapshot** saat dokumen dibuat (PO item, WO item) — perubahan
   master/price-list tidak mengubah dokumen lama.
3. **Mode internal**: `unit_cost` selalu diisi; `unit_price` & alur jual *(future)*.
4. **Tenant-aware**: setiap aksi terjadi dalam konteks `company_id` (+ `branch_id` untuk transaksi).
5. **Telusur (audit)**: perubahan penting tercatat di `wks_core_audit_logs` & `wks_price_histories`.
