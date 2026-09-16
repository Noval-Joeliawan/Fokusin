# Fokusin — Phase 1 + Phase 2

Platform produktivitas & belajar untuk siswa.

- **Fase 1 (Foundation)**: database, autentikasi, layout responsif, dashboard.
- **Fase 2 (fitur inti)**: Task Manager, Focus Mode (Pomodoro), Study Planner —
  semuanya terhubung ke dashboard.

Fitur lain (Catatan, Materi, Kuis, Progress penuh, Target, Pencapaian,
Resource, Admin Panel) masih berupa halaman placeholder untuk Fase 3+.

## Yang sudah berfungsi

### Dari Fase 1
- Database MySQL lengkap (`database/schema.sql`), autentikasi nyata
  (register/login/logout, password di-hash, session, CSRF), layout
  responsif (sidebar desktop + bottom nav mobile), dashboard dinamis,
  pengaturan nama & tema.

### Baru di Fase 2

**Task Manager (`tasks.php`)**
- CRUD lengkap (tambah/edit/hapus tugas) lewat modal, tanpa reload halaman
  saat membuka form.
- Quick-complete lewat tombol centang di setiap baris.
- Cari (judul & mapel), filter (prioritas/status/mapel), sort (deadline
  terdekat / terbaru dibuat / prioritas tertinggi).
- Deteksi overdue otomatis (tugas tanpa deadline TIDAK pernah dianggap
  overdue), ditandai dengan banner + badge merah "Deadline sudah lewat".
- Mapel diketik langsung di form (auto dibuatkan lewat `findOrCreateSubject()`
  di `includes/functions.php`), tidak perlu halaman kelola mapel terpisah.
- Semua query dibatasi `user_id` — user lain tidak bisa melihat atau menghapus
  tugas milik user lain. Reward penyelesaian task juga idempotent: satu task
  hanya memberi counter/XP sekali walaupun ditandai selesai lagi.

**Focus Mode (`focus.php`)**
- Timer Pomodoro berjalan di JS (25/5, 50/10, atau custom), tahan
  pause/resume tanpa kehilangan sisa waktu.
- Panel setup → running → completed, UI running dibuat minim distraksi
  sesuai spesifikasi.
- Sesi hanya disimpan ke `study_sessions` SEKALI saat selesai (bukan per
  tick). Waktu mulai sesi dicatat di server sehingga `duration_minutes` dari
  browser tidak dipercaya; durasi server dibatasi oleh durasi belajar yang
  dipilih. Setelah itu memicu `recordFocusSessionCompletion()`: menambah
  `total_study_minutes`, `focus_sessions_completed`, XP (1 XP/menit, hook
  disiapkan untuk sistem XP penuh di Fase 4), dan streak harian.
- Musik: tautan eksternal (YouTube search query — bukan video spesifik,
  supaya tidak menyalin konten berhak cipta apa pun) sudah aktif dan
  di-seed lewat migration; player audio lokal (Option B) sudah punya UI
  lengkap (play/pause/volume) dan siap dipakai begitu admin menambahkan
  file audio royalti-bebas di Fase 6 — sengaja tidak diisi placeholder
  audio palsu.

**Study Planner (`planner.php`)**
- CRUD jadwal belajar (mapel, tanggal, jam mulai/selesai, catatan).
- List View (dikelompokkan per tanggal, label "Hari Ini"/"Besok" otomatis)
  dan Calendar View (grid bulanan ringan, tanpa library JS).
- Terhubung ke dashboard: "Jadwal Berikutnya" otomatis menampilkan jadwal
  terdekat dari tabel `study_plans`.

**Dashboard**
- "Fokus belajar hari ini" & "Jadwal Berikutnya" sekarang menampilkan data
  sungguhan dari Fokus/Planner (bukan lagi selalu kosong seperti Fase 1).
- Panel "Tugas Hari Ini" menampilkan jumlah total di judul panel.

## Keputusan desain yang perlu diketahui

- Spec Fase 2 menyertakan contoh migration `ALTER TABLE tasks ADD status
  ENUM('belum_selesai','dikerjakan','selesai')`. Migration ini **tidak**
  dijalankan karena skema Fase 1 sudah punya kolom `status` dengan nilai
  `sedang_dikerjakan` (bukan `dikerjakan`) yang sudah konsisten dipakai di
  seluruh kode (label, badge, dashboard). Menjalankan migration itu akan
  memecah konsistensi tanpa manfaat baru — jadi dilewati sesuai instruksi
  "do not duplicate data unnecessarily" & "do not destroy existing tables".
- Migration baru yang tersedia:
  `database/migrations/002_seed_focus_music.sql` — menambah constraint UNIQUE
  pada `focus_music.title` + mengisi 5 kategori musik eksternal.
  `database/migrations/003_phase2_integrity_fixes.sql` — menambahkan penanda
  reward task, menormalkan task lama yang sudah selesai, membersihkan target
  mingguan ganda, dan menambahkan UNIQUE `(user_id, week_start_date)`.
  Perubahan struktur untuk instalasi baru juga sudah digabungkan ke
  `schema.sql`.

## Pengujian yang sudah dilakukan

Diuji langsung dengan PHP built-in server + MariaDB sungguhan (bukan hanya
baca kode): register → login → buat tugas → toggle selesai → overdue
banner → search/filter → sesi fokus selesai (XP & streak bertambah) →
buat jadwal planner → list & calendar view → dashboard menampilkan semua
data di atas → user kedua tidak bisa melihat/menghapus data user pertama.
Tidak ada PHP syntax error saat pemeriksaan ulang seluruh file PHP.
Perbaikan integritas Phase 2 juga diperiksa dengan lint PHP setelah perubahan.

## Cara menjalankan (XAMPP)

1. Salin folder `fokusin/` ke `htdocs/` XAMPP Anda.
2. Nyalakan **Apache** dan **MySQL** dari XAMPP Control Panel.
3. Jalankan `database/schema.sql` di phpMyAdmin (membuat DB + semua tabel +
   data awal, termasuk musik fokus).
   - Jika Anda sudah punya database Fase 1 dan hanya ingin menambahkan
     fitur musik fokus tanpa reset data, jalankan
     `database/migrations/002_seed_focus_music.sql` dan
     `database/migrations/003_phase2_integrity_fixes.sql` secara berurutan.
4. Sesuaikan kredensial di `config/database.php` jika berbeda dari default
   XAMPP.
5. Buka `http://localhost/fokusin/`, daftar akun baru, lalu coba Tugas,
   Fokus, dan Planner dari sidebar/bottom nav.

## Struktur folder (tambahan Fase 2)

```
fokusin/
├── database/
│   └── migrations/
│       ├── 002_seed_focus_music.sql
│       └── 003_phase2_integrity_fixes.sql
├── tasks.php      # Task Manager — CRUD, search, filter, sort, overdue
├── focus.php      # Focus Mode — timer, sesi, musik
├── planner.php    # Study Planner — CRUD, list & calendar view
└── (struktur Fase 1 lainnya tidak berubah)
```

## Rencana fase berikutnya

- **Fase 3** — Catatan, Materi Belajar, Kuis.
- **Fase 4** — XP penuh (level-up, achievement unlock), Target Mingguan.
- **Fase 5** — Resource/file upload, admin bisa kelola Musik Fokus.
- **Fase 6** — Admin Panel penuh.
- **Fase 7** — Polish: aksesibilitas, performa, review keamanan.

Beri tahu saya kapan siap lanjut ke Fase 3.
