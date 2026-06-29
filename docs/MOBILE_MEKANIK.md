# ControlHub Workshop — Desain Mobile Mekanik (Update Pekerjaan)

> Perencanaan (v0.1). Merinci **UX & arsitektur antarmuka mekanik di handheld** untuk
> *mengambil WO, menyusun WO Plan, dan memperbarui pekerjaan* di lapangan.
> Melengkapi `PANELS.md §4b` (Panel Mekanik), `WORKFLOWS.md §6b` (Eksekusi Mekanik —
> clock in/out), `MODULES.md` (`wks_svc_*`), dan `DATABASE.md` (tabel/enum).
>
> **Keputusan terkunci (sesi 2026-06):**
> 1. **PWA mobile-first = antarmuka UTAMA mekanik** (halaman Livewire/Blade khusus, bukan
>    Resource Filament dipaksa muat di HP). **Panel Filament Mekanik** dipertahankan sebagai
>    **view supervisor/KepalaMekanik** (layar besar, lihat banyak WO, override).
> 2. **Fase 1 = online-first**; arsitektur **disiapkan untuk offline** (kontrak event idempoten
>    `client_event_id` dipakai sejak awal) agar offline-queue bisa ditambah **tanpa rombak server**.
>
> ⚠️ **Timestamp jam kerja SELALU dari server** (anti-manipulasi). Klien hanya kirim *event* +
> waktu lokal (audit). Timer di layar = hitungan dari `started_at` server.

---

## 0. Sasaran & Kendala Lapangan

Mekanik bekerja di **HP/tablet**, sering: layar 5–6", **tangan kotor/oli/sarung tangan**,
**silau**, **sinyal putus-putus**, ingin **1 aksi besar** terlihat dan **minim ketik**.

Desain harus: tap-target besar, kartu (bukan tabel), timer selalu tampak, catatan via
*chip*/kamera (bukan keyboard), indikator sync jelas, dan **menyembunyikan** aksi di luar
hak mekanik (SoD).

**Batas peran (SoD) — tercermin di UI:**
- **Boleh:** ambil WO, susun/centang task & langkah miliknya, clock in/out, usul Bon part,
  input Core Return, pasang/lepas ban (scope `mechanic_id`/assignment).
- **Tidak boleh:** mengubah biaya/HPP, menutup WO (QC final di panel Servis). Tombol-tombol
  ini **tidak dirender** di antarmuka mekanik.

---

## 1. Arsitektur (online-first, offline-ready)

```
[ HP Mekanik ]                         [ Server Laravel ]
 PWA (Livewire/Blade shell)            /api/svc/*  (Sanctum, scope assignment)
   • UI mobile-first                        │
   • setiap aksi → event {client_event_id}  ├─ TaskTimeService   (clock: guard 1-segmen-aktif,
   • Fase 1: kirim langsung (online)        │                     recompute actual_minutes)
   • Fase 2: antre IndexedDB + SW sync ─────┤─ WoPlanService     (task/langkah, urutan, adhoc)
                                            └─ idempoten: unique(company_id, client_event_id)
                                                          + timestamp dari SERVER
[ KepalaMekanik / supervisi ]
 Panel Filament Mekanik (responsif)  ── pakai TaskTimeService & WoPlanService yang SAMA
```

**Prinsip kunci:** **service yang sama** (`TaskTimeService` + `WoPlanService`) dipakai PWA
**dan** panel Filament, sehingga aturan (guard 1-segmen-aktif, recompute `actual_minutes`,
urutan/`adhoc` langkah) **konsisten** di kedua jalur.

**Mengapa `client_event_id` dipakai sejak Fase 1 (walau online):** kontrak `POST` sudah
idempoten dari awal. Saat offline-queue (Fase 2) ditambahkan, klien tinggal **menunda** kirim
event — **server tidak berubah**. Tanpa ini, menambah offline nanti = rombak endpoint.

---

## 2. Peta Layar

```
(1) Tugas Saya  ──tap kartu──►  (2) Detail WO  ──tap task──►  (3) Eksekusi Task ★
       │                                                              │
       └──► (4) Susun WO Plan (task + langkah, salin template)        ├─► (5) Usul Bon Part
                                                                      ├─► (6) Pasang/Lepas Ban
                                                                      └─► (7) Core Return
```

★ = layar jantung (paling sering dipakai sepanjang hari).

---

## 3. Wireframe Layar Inti

```
┌─ (1) TUGAS SAYA ──────🟢─┐  ┌─ (3) TASK: Ganti Kampas ──┐  ┌─ (5) USUL BON PART ──────┐
│ ⏱ AKTIF 01:23:45         │  │ ⏱ 01:23:45    [  JEDA  ]  │  │ Cari part... 🔍          │
│ ╭────────────────────╮   │  │ WO-2401 · B 9123 XX       │  │ ┌──────────────────────┐ │
│ │ B 9123 XX 🔴in_prog│   │  │───────────────────────────│  │ │ Kampas rem depan     │ │
│ │ Ganti kampas rem   │   │  │ LANGKAH (3/5)             │  │ │ qty [ − 2 + ]        │ │
│ │ ▸ 3/5 langkah      │   │  │ ☑ Turunkan roda           │  │ └──────────────────────┘ │
│ ╰────────────────────╯   │  │ ☑ Lepas kaliper           │  │ + tambah baris           │
│ ╭────────────────────╮   │  │ ☑ Periksa cakram          │  │──────────────────────────│
│ │ B 4456 YY ⚪queued │   │  │ ☐ Pasang kampas baru  [✓] │  │ Catatan (chip):          │
│ │ Servis berkala     │   │  │ ☐ Cek & kembalikan    [✓] │  │ [urgent] [stok habis]    │
│ │ [  Ambil WO  ]     │   │  │ + Tambah langkah adhoc    │  │                          │
│ ╰────────────────────╯   │  │───────────────────────────│  │ [ Kirim Usulan → SO ]    │
│                          │  │ [Usul Bon][Pasang Ban][⋯] │  │ (mekanik = pengusul)     │
└─ ⏱Mulai  📋  🔧  👤 ────┘  └───── [ SELESAI TASK ] ────┘  └──────────────────────────┘
   Tugas  Plan  Part Profil      bottom bar = aksi primer
```

### (1) Tugas Saya
- Daftar WO/task yang **di-assign/diambil dirinya** (scope `mechanic_id`/assignment) — **bukan**
  semua WO. Kartu besar: plat · keluhan singkat · status badge · progres langkah.
- Kartu `queued` → tombol **Ambil WO** (`task: assigned`). Kartu dengan timer aktif **naik ke atas**.
- Bila ada segmen aktif → **banner timer sticky** di paling atas (lintas layar).

### (3) Eksekusi Task — jantung aplikasi
- **Tombol state tunggal** yang berubah sesuai status:
  `Mulai` → (`in_progress`) → `Jeda`/`Lanjut` → `Selesai`.
- **Guard 1-segmen-aktif:** bila ada timer jalan, task lain menampilkan *"Selesaikan/Jeda X dulu"*
  (tombol Mulai disabled).
- **Jeda** memilih alasan via chip: `wait_part` · `break` · `qc` · `shift_end` (`task: paused`).
- **Checklist langkah:** toggle `done`/`skipped` (+ catatan opsional via tap ✓). Urutan ber-seq.
- **+ Langkah Adhoc:** tambah langkah baru saat temukan kerja di lapangan (`source=adhoc`).
- **Selesai Task:** tutup segmen + isi `result_note`. Bila masih ada langkah `pending` →
  **peringatan** (boleh tetap `done` dengan catatan). Server **recompute** `actual_minutes`.
- Aksi sekunder (sheet bawah): **Usul Bon** · **Pasang/Lepas Ban** · **Core Return**.

### (4) Susun WO Plan *(setelah Ambil WO)*
- Buat/atur **Task** + **Langkah** (`wks_svc_wo_task_steps`, ber-seq). Mekanik menyusun,
  **bisa bersama Service Officer** (co-author; SO juga menyusun/awasi di panel Servis).
- **Salin Template** langkah dari jasa katalog (`wks_svc_service_steps`) → prefill, lalu sesuaikan.

### (5) Usul Bon Part
- Form super-ringkas: search part → stepper qty → chip catatan → **Kirim** (`issue: draft→submitted`).
- Mekanik = **pengusul** saja. Review oleh **Service Officer** (panel Servis), keluar di **Gudang**
  (SoD: pengusul ≠ reviewer ≠ pengeluar). Task ditandai `requires_part=true`.

### (6) Pasang/Lepas Ban
- Aksi → `TyreService`; tampilkan **layout posisi axle** unit (diagram slot). Validasi posisi vs
  `wks_ms_axle_positions` (1 ban/slot).

### (7) Core Return
- Saat pasang **part baru non-consumable**: input part bekas **rusak** + **foto bukti** (kamera) +
  `failure_reason`. Gate: WO tak bisa `done` tanpa core kembali (ditegakkan di panel Servis).

---

## 4. Kontrak Endpoint per Layar (`/api/svc/*`)

Auth **Sanctum**, scope assignment (`mechanic_id`). Semua aksi mutasi membawa
`client_event_id` (uuid) → **idempoten** `unique(company_id, client_event_id)`.

| Layar | Method & Endpoint | Catatan |
|---|---|---|
| (1) | `GET /api/svc/my-tasks` | task di-assign ke saya (+ filter status/WO) |
| (1) | `POST /api/svc/wo/{id}/claim` | Ambil WO → `task: assigned` |
| (3) | `POST /api/svc/tasks/{id}/start` | buka segmen (`started_at` server); guard 1-segmen |
| (3) | `POST /api/svc/tasks/{id}/pause` | tutup segmen + `pause_reason` |
| (3) | `POST /api/svc/tasks/{id}/resume` | buka segmen baru |
| (3) | `POST /api/svc/tasks/{id}/complete` | tutup segmen + `result_note`; recompute `actual_minutes` |
| (3) | `GET /api/svc/tasks/{id}/steps` | daftar langkah ber-seq |
| (3) | `POST /api/svc/steps/{id}/check` | `done`/`skip` + note |
| (3/4) | `POST /api/svc/tasks/{id}/steps` | tambah langkah `adhoc` |
| (5) | `POST /api/svc/part-issues` | usul Bon (draft→submitted) |
| (6) | `POST /api/svc/tyres/install` · `/remove` | via `TyreService`, validasi posisi |
| (7) | `POST /api/svc/core-returns` | + upload foto bukti |
| (Fase 2) | `POST /api/svc/time-logs/sync` | **batch** event tertunda (offline) — idempoten |

> **Fase 1:** klien memanggil endpoint per-aksi langsung (butuh online). **Fase 2:** saat offline,
> event diantre lokal (IndexedDB) lalu dikirim batch via `time-logs/sync` ketika online kembali.
> **Server identik** di kedua fase (karena `client_event_id` sudah dipakai sejak awal).

---

## 5. Penanganan Sync & Konflik (siap Fase 2)

- **Indikator sync** selalu tampil: 🟢 tersinkron · 🟡 *N* event antre · 🔴 offline.
- **Idempoten:** event dobel (retry jaringan) di-*dedup* via `client_event_id`; server set `synced_at`.
- **Konflik** (overlap segmen / segmen menggantung) → **tidak silent**: kartu ditandai untuk
  **ditinjau KepalaMekanik** (panel Servis/Mekanik supervisor).
- **Timestamp otoritatif server** → kalaupun jam HP salah, durasi tetap benar.

---

## 6. Pengukuran (selaras WORKFLOWS §6b)

- **Labor-minutes** = Σ durasi segmen (produktivitas & biaya jasa).
- **Wall-clock** = rentang start..done (turnaround).
- **WO `done`** mensyaratkan semua task `done`/`cancelled` (+ gate core-return §6/§8c) —
  ditegakkan di **panel Servis**, bukan di antarmuka mekanik.

---

## 7. Stamp Waktu per Langkah & Contoh "Ganti Ban"

Tiap langkah **dicap waktu saat diklik** (`wo_task_steps.done_at` + `done_by`, **dari server**).
Ini **sudah** didukung model — tak perlu clock-in/out per langkah (melelahkan). Cukup **1 tap
"done" → 1 stamp**; **durasi per langkah = turunan**: `done_at[i] − done_at[i−1]` (langkah pertama
dihitung dari mulai task).

### Dua lapis waktu — JANGAN dicampur

| | Sumber | Untuk apa | Otoritatif? |
|---|---|---|---|
| **Jam kerja / biaya** | `task_time_logs` (segmen clock per **task**) | labor-minutes → `actual_minutes`, biaya jasa | ✅ ya |
| **Stamp per langkah** | `wo_task_steps.done_at` (klik per langkah) | timeline, langkah terlama, kepatuhan SOP, audit | ❌ visibilitas saja |

⚠️ Bila ada **Jeda** (mis. `wait_part`) di antara dua langkah, interval itu **ikut terhitung** →
durasi per-langkah = *elapsed* (untuk dilihat), **bukan** dasar biaya. Biaya tetap dari **Σ segmen**
(yang sudah mengurangi jeda). *(Durasi **aktif** per langkah yang akurat = irisan interval dengan
segmen aktif — opsional, fase lanjut.)*

### Timeline contoh (task "Ganti Ban", posisi RR-outer)

```
09:00:00  ▶ MULAI task           → task_time_logs segmen dibuka (started_at)
09:04:12  ☑ Turunkan ban         → done_at ·Δ 4m12s· ⚙️ TyreService removal
                                     → tyre_movements(removal) + installations.removed_at
09:09:30  ☑ Masukkan bekas→gudang → done_at ·Δ 5m18s· ⚙️ ban→stok used @ Gudang Bekas
09:12:00  ☑ Ambil ban baru        → done_at ·Δ 2m30s· ⚙️ keluar ban baru dari stok
09:20:45  ☑ Pasang ban            → done_at ·Δ 8m45s· ⚙️ TyreService install
                                     → tyre_movements(install) + installations baru (validasi axle)
09:21:00  ■ SELESAI task          → segmen ditutup (ended_at); recompute actual_minutes = 21m
```

Tiap langkah punya stamp klik-nya; langkah yang memicu gerakan ban **juga** menghasilkan
`tyre_movements` dengan **timestamp transaksinya sendiri** (bisa beda dari `done_at`, lihat handoff).

### Langkah ≠ checkbox murni — sebagian = transaksi (perlakuan SoD)

Dari 4 langkah di atas, dua ranah **mekanik** dan dua ranah **gudang**:

| Langkah | Ranah | Aksi |
|---|---|---|
| Turunkan ban · Pasang ban | **Mekanik** | `TyreService` removal/install (validasi posisi axle, 1 ban/slot) |
| Masukkan bekas → gudang · Ambil ban baru | **Gudang** | gerakan stok ban — **wajib sesi gudang open + SoD** |

✅ **Keputusan (2026-06): SoD ketat — langkah-gudang = SERAH/PERMINTAAN, bukan movement langsung
oleh mekanik.** Saat mekanik centang "Masukkan bekas → gudang" / "Ambil ban baru":
- Langkah ditandai `done` (`done_at` = saat mekanik **serah/minta**) dan **memunculkan permintaan**
  ke panel Gudang (terima ban bekas / keluarkan ban baru).
- **Operator Gudang** yang **menstempel `tyre_movements`** (di bawah **sesi gudang** aktif) →
  `movement.created_at` = waktu transaksi gudang sebenarnya (bisa beda dari `done_at` langkah).
- SoD terjaga: **mekanik ≠ penerima/pengeluar gudang**. Langkah mekanik **tak memotong stok langsung**.

> Catatan UI: langkah-gudang menampilkan status tindak-lanjut (mis. *"menunggu gudang terima"* /
> *"ban baru siap diambil"*) sehingga mekanik tahu kapan bisa lanjut ke "Pasang ban".

### Tindak lanjut model (DITUNDA — belum diterapkan ke DATABASE.md)

Agar langkah dapat **memicu permintaan** & **tertelusur** ke dokumen hasil, diusulkan
(saat modul `wks_svc_*` dibangun) menambah ke `wks_svc_wo_task_steps`:
- `action_type` (enum: `none`/`tyre_remove`/`tyre_install`/`part_issue`/`core_return`) — penanda langkah-transaksi.
- `action_ref_type` + `action_ref_id` (morph) — tautan ke `tyre_movement`/`part_issue`/`core_return` hasilnya.
- `client_event_id` (uuid, **unique(company_id, client_event_id)**) + `synced_at` — idempotensi centang langkah
  (sejajar `task_time_logs`; desain §1/§4 mengandalkannya, kolomnya **belum** ada di DATABASE.md).

---

## 8. Definition of Done (antarmuka mekanik)

- [ ] PWA mobile-first (Livewire/Blade) memuat: Tugas Saya · Eksekusi Task · Susun Plan · Usul Bon · Ban · Core Return.
- [ ] Semua aksi mutasi membawa `client_event_id`; server idempoten `unique(company_id, client_event_id)`.
- [ ] **Timestamp dari server**; klien hanya kirim event + waktu lokal.
- [ ] Guard **1-segmen-aktif/mekanik** divisualkan & ditegakkan service.
- [ ] Checklist langkah: `done`/`skipped` + note + `adhoc`; peringatan saat `complete` bila ada `pending`.
- [ ] Scope ketat assignment (`mechanic_id`); aksi luar hak (ubah biaya/tutup WO) **tak dirender**.
- [ ] Panel Filament Mekanik (supervisor) memakai `TaskTimeService`/`WoPlanService` yang sama.
- [ ] Indikator sync 🟢/🟡/🔴; konflik ditandai untuk KepalaMekanik (tidak silent).
- [ ] Tap-target ≥ 48px, kartu bukan tabel, catatan via chip/kamera (minim ketik).
- [ ] Test: clock happy-path · guard 1-segmen · idempotensi event · scope assignment · SoD.

---

## 9. Roadmap

- **Fase 1 (sekarang):** PWA online-first + panel Filament Mekanik supervisor. Kontrak event
  idempoten sudah dipakai.
- **Fase 2:** offline-queue (IndexedDB + Service Worker) + `time-logs/sync` batch. **Tanpa**
  perubahan server (lihat §1, §4).
- **Fase 3 (opsional):** push notification (penugasan baru, part siap diambil), scan QR unit/part.
