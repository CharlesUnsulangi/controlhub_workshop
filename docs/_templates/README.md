# Dokumen Template Generik (Reusable)

Folder ini berisi **dokumen modul yang di-parameter (`{{...}}`) agar dapat dipakai
ulang lintas aplikasi**. Tujuannya: saat memulai proyek baru, **copy seluruh folder ini**
ke `docs/` proyek baru, lalu isi parameter di tiap dokumen.

## Isi

| Dokumen | Modul | Cara pakai |
|---|---|---|
| [USER_MANAGEMENT.md](USER_MANAGEMENT.md) | Identitas, autentikasi, RBAC, siklus hidup user, audit | Isi **§0 Parameter** → find-replace `{{...}}` → ikuti **§16 Cara Adaptasi** |

## Cara memakai di aplikasi lain (untuk AI agent)

1. Copy folder `docs/_templates/` ke proyek target.
2. Untuk tiap dokumen: isi tabel **§0 Parameter**, lalu find-replace semua placeholder `{{...}}`.
3. Hapus bagian yang ditandai *"khusus repo ini, hapus saat di-copy"*.
4. Sesuaikan daftar peran/domain ke aplikasi target.
5. (Opsional) pindahkan dokumen yang sudah diisi keluar dari `_templates/` ke `docs/`
   karena sudah menjadi spesifik-aplikasi.

> Konvensi: dokumen di folder ini **netral-domain**. Bagian yang berubah antar aplikasi
> selalu dikumpulkan di **§0** + section adaptasi, bukan tersebar di seluruh teks.
