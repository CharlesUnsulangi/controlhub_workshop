# ControlHub Workshop — Dokumentasi Workflow

> Alur proses bisnis lintas modul (v0.1). Melengkapi `MODULES.md` (struktur),
> `DATABASE.md` (tabel/enum), `SITEMAP.md` (halaman). Mode operasi: **INTERNAL/cost**
> — alur penjualan & invoice ditandai *(future)*.

**Legenda:** 👤 aktor/peran · 📄 tabel utama · 🔁 perubahan status · ⚙️ service/aturan.

Daftar workflow:
1. Provisioning Tenant (Core)
2. Setup Awal Company (Admin & Master)
3. Update Price List Supplier
4. Pengadaan: PR → PO → GRN → Stok
4b. Sinkronisasi Driver dari ControlHub HRD
5. Kendaraan Masuk (LKM)
6. Servis / Work Order (alur utama)
7. Servis Berkala (PM)
8. Siklus Hidup Ban (Tyre)
8b. Penerimaan Part Bekas (Teardown / Copotan)
8c. Core Return (Old-for-New) & Penjualan Scrap
9. Stock Opname & Penyesuaian
10. Transfer Stok Antar Gudang
10b. Surat Jalan (Barang Keluar) + Tally Sheet
11. Invoicing *(future/dormant)*

---

## 1. Provisioning Tenant (Core)

👤 SuperAdmin · 📄 `wks_core_companies`, `users`, `wks_mst_branches`, `wks_adm_roles`

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

👤 Owner/Admin · 📄 `wks_mst_*`, `wks_adm_*`, `users`

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
⚙️ Penomoran dokumen (LKM/WO/PO/GRN) diatur di `wks_adm_document_sequences`.

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

## 4. Pengadaan: PR → PO → GRN → Stok

👤 Purchasing, Gudang, approver Owner/Admin
📄 `wks_po_*`, `wks_inv_stock_movements`/`stock_items`, `wks_tyre_tyres`/`movements`

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
(opsional) SURAT JALAN MASUK dari supplier (📄 wks_po_supplier_deliveries)
   • supplier daftarkan via portal /vendor (source=portal) ATAU staf input (source=manual)
   • atas PO; isi supplier_do_no + qty_shipped per baris   🔁 SJ: submitted
        │
        ▼
Barang datang → SERAH TERIMA (GRN, WAJIB pilih PO)  🔁 GRN: draft
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

---

## 4b. Sinkronisasi Driver dari ControlHub HRD

👤 Admin · 📄 `wks_mst_drivers` · ⚙️ `HrdGateway` (feature-flag integrasi)

```
Aktifkan integrasi HRD (config/integrations.php: base_url, token, mapping company)
  → /app/master/drivers/sync (manual) atau cron terjadwal
  → tarik "Mitra Kerja" berperan driver dari HRD (per hrd_company_id yang dipetakan)
  → untuk tiap mitra kerja:
       ├─ sudah ada (match hrd_mitra_id) → update field inti (read-only) + hrd_synced_at
       └─ baru → buat wks_mst_drivers (source=hrd, hrd_mitra_id, hrd_company_id)
  → driver source=manual TIDAK ditimpa
```
⚙️ Driver di HRD = entitas **"Mitra Kerja"**; HRD = system of record, Workshop kelola
   data operasional (SIM, penugasan unit). Pemetaan: driver ↔ mitra kerja (`hrd_mitra_id`),
   tenant Workshop ↔ tenant HRD (`hrd_company_id`).
⚙️ Metode koneksi (API / shared-DB / impor berkala) — lihat `MODULES.md` §15.

---

## 5. Kendaraan Masuk (LKM)

👤 ServiceAdvisor / Gate · 📄 `wks_lkm_entries`, `wks_lkm_inspections`, `wks_lkm_gateouts`

```
Truk tiba → buat LKM (/app/lkm/create)        🔁 LKM: entered
  • pilih truck (+ customer), entry_at, driver, km_in, keluhan
  → checklist inspeksi awal + foto (wks_lkm_inspections)
  → buat Work Order dari LKM                    🔁 LKM: in_progress
  ... (servis berjalan, lihat #6) ...
  → pekerjaan selesai                            🔁 LKM: done
  → Gate-out: catat exit_at, km_out, surat jalan 🔁 LKM: exited
```
⚙️ `km_in` memperbarui `trucks.current_km`; dasar perhitungan PM.

---

## 6. Servis / Work Order (alur utama)

👤 ServiceAdvisor, KepalaMekanik, Mekanik, Gudang
📄 `wks_svc_work_orders`/`work_order_items`, `wks_inv_*`, `wks_tyre_*`

```
LKM → Work Order                               🔁 WO: queued
  → estimasi/rencana: tambah item (jasa/part/ban) ke wks_svc_work_order_items
  → BON PENGELUARAN SPAREPART (📄 wks_inv_part_issues, ref wo_id → lkm_id, truck_id)
        👤 Mekanik USUL (qty_requested)              🔁 issue: draft→submitted
        👤 Service Officer REVIEW (approve qty_approved / reject)  🔁 issue: approved/rejected
            └─ approved → ⚙️ StockService reserve (qty_reserved↑)  🔁 WO: in_progress
            └─ stok kurang → buat PR/PO (#4)                       🔁 WO: waiting_part
  → mekanik kerjakan; catat jam kerja
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

## 7. Servis Berkala (Preventive Maintenance)

👤 Sistem (terjadwal) + ServiceAdvisor · 📄 `wks_svc_pm_schedules`, `wks_mst_trucks`

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

👤 Gudang, Mekanik · 📄 `wks_tyre_tyres`/`products`/`movements`/`installations`/`inspections`/`retreads`

```
Terima dari GRN (#4) → unit ban dibuat per serial   🔁 tyre: in_stock
  → pasang ke truk (posisi FL/FR/RL1…, km_install)  🔁 tyre: installed
        → wks_tyre_installations (removed_at=null)
  → inspeksi berkala (tread_depth, pressure)         📄 tyre_inspections
  → lepas (km_remove) → installation ditutup         🔁 tyre: removed
        ├─ masih layak → kembali ke stok             🔁 tyre: in_stock
        ├─ kirim vulkanisir → supplier                🔁 tyre: retreading
        │     → terima kembali (retread_count↑)       🔁 tyre: in_stock
        └─ tidak layak → scrap                        🔁 tyre: scrapped
```
⚙️ Tiap ban unik per `serial_no`; harga/model mengacu `wks_tyre_products`.
⚙️ Umur/biaya per KM dihitung dari `installations` (km_remove − km_install).

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
| Serah Terima (GRN) | draft → checking → posted |
| Surat Jalan (DO) | draft → in_transit → delivered (· cancelled) |
| Tally Sheet | draft → completed |
| LKM | entered → in_progress → done → exited |
| Work Order | queued → waiting_part → in_progress → qc → done → delivered |
| Stock Opname | draft → counting → posted |
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
