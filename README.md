# Fokusin — Phase 1 + 2 + 3 + 4 + 5 + 6 MVP

Platform produktivitas & belajar untuk siswa.

- **Fase 1 (Foundation)**: database, autentikasi, layout responsif, dashboard.
- **Fase 2**: Task Manager, Focus Mode (Pomodoro), Study Planner.
- **Fase 3**: Catatan, Materi Belajar, Kuis — plus Progress page dan integrasi dashboard.
- **Fase 4**: XP/Level yang lebih lengkap, Pencapaian, dan Target Mingguan.
- **Fase 5**: Resource Library, upload/download aman, dan Focus Music.
- **Fase 6 MVP**: Admin Dashboard, pengelolaan pengguna, resource, dan Focus Music.


---

## 1. File yang diubah/ditambah di Fase 3

**Baru:**
- `notes.php` — CRUD Catatan
- `materials.php` — daftar & detail Materi Belajar
- `quiz.php` — daftar, mulai, kerjakan, submit, hasil, pembahasan, riwayat Kuis
- `database/migrations/004_phase3_notes_materials_quiz.sql`

**Diubah:**
- `dashboard.php` — tambah 3 panel: Catatan Terbaru, Materi Dipelajari, Kuis Terakhir
- `progress.php` — dibangun penuh (sebelumnya placeholder): level/XP, streak,
  total belajar, tugas, **materi selesai, kuis selesai, rata-rata nilai kuis,
  jumlah catatan**
- `includes/functions.php` — tambah `recordQuizCompletion()`,
  `recordMaterialCompletion()`, `truncateText()`
- `database/schema.sql` — perubahan Fase 3 digabungkan juga untuk instalasi baru
- `assets/css/style.css` — ditambah (bukan diganti), styling untuk Catatan/
  Materi/Kuis/Progress mengikuti token warna & komponen yang sudah ada

**Tidak disentuh sama sekali:** `tasks.php`, `focus.php`, `planner.php`,
`includes/auth.php`, `config/*`, sistem sidebar/bottom-nav, dark mode.

---

## 2. Perubahan database

Lihat `database/migrations/004_phase3_notes_materials_quiz.sql`. Ringkasan:

| Tabel | Perubahan |
|---|---|
| `notes` | + `subject_id` (FK ke `subjects`), + `is_important`. Kolom `category` lama **tidak dihapus**, hanya tidak dipakai aktif lagi. |
| `materials` | + `category` (VARCHAR bebas, terpisah dari mapel) |
| `material_completions` | **Tabel baru** — mencatat materi selesai per user, `UNIQUE(user_id, material_id)` membuat penandaan selesai idempotent |
| `materials`, `quizzes`, `quiz_questions`, `quiz_options` | + UNIQUE key masing-masing (title / title / quiz_id+order_index / question_id+option_text) — **bukan** perubahan fungsional, hanya supaya seed data di bawah aman dijalankan berulang |

Tidak ada tabel yang di-duplikasi — semua tabel inti (`notes`, `materials`,
`quizzes`, `quiz_questions`, `quiz_options`, `quiz_attempts`, `quiz_answers`)
sudah ada sejak Fase 1 dan dipakai langsung.

**Seed data** (supaya modul bisa langsung dicoba tanpa Admin Panel, yang baru
ada di Fase 6): 2 mapel, 4 materi (konten ditulis sendiri), 2 kuis berisi 5
soal masing-masing (JavaScript Dasar, Database Dasar).

Migration ini **diuji idempotent secara nyata**: dijalankan 3x berturut-turut
terhadap database sungguhan, jumlah baris materi/kuis/soal/opsi tetap sama
persis di setiap run (4/2/10/40) — tidak ada duplikasi.

Cara menjalankan di database Fase 1/2 yang sudah ada:
```
mysql -u root fokusin_db < database/migrations/004_phase3_notes_materials_quiz.sql
```
Untuk instalasi baru, cukup `database/schema.sql` (migration sudah digabungkan).

---

## 3. Fitur yang diimplementasikan

**Catatan** — CRUD lewat modal (pola sama seperti Tugas/Planner), cari
(judul+isi), filter mapel, sort (terbaru/penting dulu/A-Z), toggle ⭐ penting
langsung dari daftar, mapel diketik & auto-dibuat lewat `findOrCreateSubject()`
yang sudah ada sejak Fase 2.

**Materi Belajar** — daftar dengan cari + filter mapel + filter kategori,
halaman detail (`materials.php?id=`), tombol "Tandai Sudah Dipelajari" yang
**idempotent** (dibuktikan lewat test: klik dua kali, XP hanya bertambah
sekali), link eksternal dibuka dengan `target="_blank" rel="noopener noreferrer"`.

**Kuis** — ini bagian paling kritis dari sisi keamanan:
- Daftar kuis menampilkan jumlah soal **asli** dari `COUNT()`, bukan angka
  hardcode.
- Halaman kerjakan soal me-render SEMUA soal sekaligus di DOM (disembunyikan
  via JS untuk navigasi Sebelumnya/Berikutnya dengan counter "Soal X dari N"),
  tapi **tidak pernah mengirim `is_correct` atau opsi jawaban benar ke
  klien** — sudah diverifikasi langsung dengan meng-grep HTML yang dikirim ke
  browser.
- Submit meminta konfirmasi JS sebelum benar-benar mengirim.
- **Server menghitung skor sendiri dari database**, sama sekali tidak
  memercayai field `score`/`correct_count`/`xp` yang dikirim dari form.
  Dibuktikan dengan mengirim `score=999&correct_count=999&xp=999` lewat
  request manual — server tetap menyimpan skor asli hasil hitungannya
  sendiri (60, sesuai jawaban yang benar-benar dikirim).
- Setiap `option_id` yang disubmit diverifikasi benar-benar milik
  `question_id` yang sedang dinilai (mencegah pengiriman option_id dari
  soal/kuis lain).
- Hasil, Pembahasan, dan Riwayat semuanya memverifikasi `attempt.user_id`
  sebelum menampilkan apa pun — dibuktikan dengan mendaftarkan user kedua dan
  mencoba mengakses `attempt_id` milik user pertama (hasil: ditolak dengan
  pesan "Kamu tidak memiliki akses ke data ini").

**Progress & Dashboard** — progress.php sekarang menampilkan gabungan
statistik Fase 1-3 (level/XP dengan progress bar, streak, total belajar,
tugas, **plus** materi selesai, kuis selesai, rata-rata nilai kuis, jumlah
catatan). Dashboard menambah 3 panel ringkas tanpa mengubah tata letak/hirarki
yang sudah ada.

---

## 4. Integrasi XP/Progress (tanpa sistem XP kedua)

Semua lewat fungsi di `includes/functions.php` yang **sudah ada sejak Fase 2**
(`addXp()`, `touchStreak()`, `ensureUserProgressRow()`) — Fase 3 hanya
menambah dua fungsi pemanggil baru di atasnya:

- `recordQuizCompletion($pdo, $userId, $scorePercent)` — dipanggil setiap
  attempt kuis berhasil disimpan (kuis boleh diulang, jadi ini BUKAN
  idempotent secara sengaja — setiap percobaan kuis yang selesai dihitung).
  XP = 5 dasar + hingga 20 sesuai skor.
- `recordMaterialCompletion($pdo, $userId)` — dipanggil HANYA setelah INSERT
  baru ke `material_completions` berhasil (bukan saat baris sudah ada), jadi
  idempotent per desain. XP = 8 flat.

---

## 5. Hasil pengujian

Diuji langsung dengan PHP built-in server + MariaDB sungguhan (bukan hanya
membaca kode):

- Migration dijalankan 3x berturut-turut → jumlah baris seed tidak berubah
  (idempotent, terbukti).
- Catatan: buat → tersimpan dengan subject_id & is_important benar, tampil
  dengan badge ⭐.
- Materi: tandai selesai → XP +8 → tandai selesai lagi → XP **tidak
  bertambah lagi** (masih 8), status di halaman detail berubah jadi
  "✓ Sudah Dipelajari" dan tombol disabled.
- Kuis: halaman kerjakan soal diperiksa langsung — tidak ada `is_correct`
  di HTML yang dikirim ke browser. Submit dengan 3 jawaban benar + 2 salah,
  **sekaligus menyisipkan `score=999` palsu** → server tetap menyimpan
  skor 60 (3/5) yang benar. XP yang diberikan (17) sesuai skor asli, bukan
  angka yang disisipkan.
- Keamanan lintas-user: user kedua ("Budi") mencoba mengakses
  `quiz.php?action=review&attempt_id=` milik user pertama → ditolak dengan
  flash message yang benar, tidak ada data yang bocor.
- Regresi: tasks.php, focus.php, planner.php, dan dashboard Fase 1/2 dites
  ulang setelah semua perubahan Fase 3 — semuanya masih berfungsi normal.
- **Tidak ada satu pun PHP warning/notice/error** di log server selama
  seluruh sesi pengujian (26 request).
- `php -l` bersih untuk seluruh file `.php` di project.

---

## 6. Keterbatasan yang tersisa

- Materi & kuis belum punya UI kelola (create/edit/delete) untuk
  siswa/admin — sesuai instruksi Fase 3 ("do not invent unnecessary
  file-upload/admin functionality yet"), konten diisi lewat seed migration.
  UI kelola penuh menyusul di Admin Panel (Fase 6).
- Field `explanation` untuk pembahasan soal tidak dibuat karena tidak ada
  di skema asli dan spec mengizinkan ini dilewati ("do not invent unnecessary
  database structure unless needed").
- Kuis tidak punya batas waktu, sesuai instruksi eksplisit spec.
- `category` lama pada tabel `notes` dibiarkan ada tapi tidak dipakai —
  akan dibersihkan kalau memang tidak diperlukan lagi di fase mendatang.

---

## Cara menjalankan (XAMPP)

1. Salin folder `fokusin/` ke `htdocs/`.
2. Nyalakan Apache + MySQL dari XAMPP Control Panel.
3. **Instalasi baru**: jalankan `database/schema.sql` di phpMyAdmin (sudah
   termasuk semua perubahan Fase 1-4 + seed data, 8 achievement bersih).
   **Upgrade dari Fase 3**: jalankan migration secara berurutan —
   `002_seed_focus_music.sql` → `003_phase2_integrity_fixes.sql` →
   `004_phase3_notes_materials_quiz.sql` → `005_phase4_achievements_targets.sql`.
4. Sesuaikan kredensial di `config/database.php` jika perlu.
5. Buka `http://localhost/fokusin/`, daftar/masuk, lalu coba Catatan, Materi,
   dan Kuis dari sidebar/bottom nav.

## Fase 4

- XP tetap terpusat di `addXp()` sehingga tidak membuat sistem XP kedua.
- Level dihitung otomatis dari XP; setiap kenaikan level menampilkan notifikasi.
- 8 achievement otomatis terbuka berdasarkan aktivitas belajar.
- Target Mingguan menyimpan satu target per user per minggu dan menampilkan progres waktu belajar, tugas, kuis, dan materi.
- `005_phase4_achievements_targets.sql` menambahkan seed achievement dan memastikan constraint target mingguan.

## Bug yang ditemukan & diperbaiki (audit sebelum rilis)

Sebelum dipaketkan, seluruh perubahan Fase 4 diperiksa ulang — dites nyata
lewat PHP built-in server + MariaDB, bukan hanya baca kode. Tiga bug nyata
ditemukan dan diperbaiki:

1. **CSS: variabel `--fs-md` tidak pernah didefinisikan.**
   `.achievement-card__body h3` memakai `var(--fs-md)`, padahal daftar token
   font-size yang ada hanya `--fs-xs/sm/base/lg/xl/2xl`. Karena browser
   mengabaikan properti dengan custom property yang tidak terdefinisi, judul
   kartu achievement diam-diam jatuh ke ukuran font default (inherited)
   bukan ukuran yang dimaksud. **Diperbaiki**: diganti ke `--fs-lg`, konsisten
   dengan judul kartu lain (note-card, material-card, dst). Sudah di-scan
   otomatis ke seluruh file CSS — ini satu-satunya variabel yang tidak
   terdefinisi.

2. **Data: 5 achievement placeholder Fase 1 jadi "hantu" terkunci selamanya.**
   `checkAndUnlockAchievements()` di Fase 4 hanya mengenali 8 kode baru
   (`first_focus`, `focus_60`, dst). Tapi seed lama dari Fase 1
   (`first_step`, `seven_days`, `bookworm`, `focus_master`, `task_crusher`)
   masih ada di `schema.sql` dan tidak pernah dicek oleh logika unlock
   apa pun — akan selalu tampil "🔒 Belum terbuka" selamanya, membingungkan
   karena tampil berdampingan dengan versi barunya yang mirip (mis. "7 Hari"
   vs "Konsisten 7 Hari" untuk syarat yang sama). **Diperbaiki**: seed lama
   dihapus dari `schema.sql`, dan `005_phase4_achievements_targets.sql`
   ditambah `DELETE FROM achievements WHERE code IN (...)` untuk
   membersihkan database yang sudah lama terpasang. Aman — kelima kode lama
   itu tidak pernah punya logika unlock, jadi mustahil ada baris
   `user_achievements` yang mengacu padanya.

3. **Migration: `005_phase4_achievements_targets.sql` tidak ada `USE fokusin_db;`.**
   Migration 002/003/004 semua diawali `USE fokusin_db;`, tapi 005 lupa
   menambahkannya — dijalankan sendiri (`mysql -u root < file.sql`) langsung
   gagal dengan "No database selected". **Diperbaiki**: ditambahkan.

Sudah diverifikasi (bukan sekadar dianggap benar):
- Fresh install → tepat 8 achievement, tidak ada duplikat/hantu.
- Simulasi upgrade dari database lama (5 achievement hantu ditanam manual)
  → migration 005 membersihkannya jadi 8 yang benar, dijalankan 3x
  berturut-turut tanpa error dan tanpa duplikasi (idempotent, terbukti).
- `mondayOfWeek()` di `targets.php` konsisten dengan query `WEEKDAY()`
  MySQL yang dipakai `dashboard.php` untuk mencari target minggu berjalan
  (dites untuk Senin, Selasa, Kamis, Minggu — semua memetakan ke Senin yang
  benar).
- Achievement `first_focus` terbuka otomatis setelah sesi fokus pertama;
  `level_5` terbuka otomatis begitu level mencapai 5; keduanya dites lewat
  request HTTP sungguhan, bukan simulasi.
- Notifikasi naik level tampil sekali lalu hilang di reload berikutnya
  (session flag dibersihkan dengan benar).
- Isolasi antar-user: user kedua ("Budi") mendaftar dan mengunjungi
  `targets.php`/`achievements.php` — melihat form kosong dengan nilai
  default (bukan data milik user pertama) dan 0/8 achievement (bukan
  1/8 milik user pertama).
- Regresi penuh: dashboard, tasks, focus, planner, notes, materials, quiz,
  progress, profile, settings — semua HTTP 200 setelah semua perbaikan di
  atas diterapkan.
- **Tidak ada satu pun PHP warning/notice/error** di log server selama
  seluruh sesi pengujian (29 request).
- `php -l` bersih untuk seluruh file `.php` di project.

## Rencana fase berikutnya

- **Fase 6 MVP** — Admin Dashboard, Pengguna, Resource, Focus Music.
- **Fase berikutnya** — perluasan Admin Panel, polish, aksesibilitas, performa, dan review keamanan.

Fokusin siap dilanjutkan dari MVP Admin Panel ke perluasan fitur berikutnya setelah pengujian di lingkungan XAMPP.

---

# Fase 5 — Resource Library & Kelola Musik Fokus

## Files Changed

**Baru:**
- `resources.php` — dibangun penuh (sebelumnya placeholder): browse, cari,
  filter, tambah (tautan/upload file), edit, hapus, download aman.
- `focus_music.php` — halaman baru untuk kelola musik fokus (tambah/edit/
  hapus), hanya untuk entri milik sendiri.
- `database/migrations/006_phase5_resources_music.sql`
- `uploads/resources/.htaccess` — pengerasan Apache (blokir eksekusi PHP/
  script apa pun di folder upload, matikan directory listing).

**Diubah:**
- `includes/functions.php` — tambah `validateUploadedResourceFile()`,
  `generateSafeStoredFilename()`, `sanitizeDisplayFilename()`,
  `formatFileSizeKb()`, `validateExternalUrl()`.
- `focus.php` — **satu baris** ditambahkan: link "⚙️ Kelola Musik" di
  panel Musik Fokus. Timer, session tracking server-side, XP, dan semua
  logika Fokus Mode lain **tidak disentuh sama sekali** (dikonfirmasi
  lewat `diff` line-by-line terhadap file Fase 4).
- `database/schema.sql` — perubahan tabel `resources` digabungkan untuk
  instalasi baru.
- `assets/css/style.css` — ditambah (bukan diganti).

**Tidak disentuh:** `tasks.php`, `notes.php`, `materials.php`, `quiz.php`,
`achievements.php`, `targets.php`, `dashboard.php`, `includes/auth.php`,
`config/*`, sistem sidebar/bottom-nav, dark mode, `focus_music` (struktur
tabel — sudah cukup sejak Fase 2, tidak perlu kolom baru).

## Database Changes

`006_phase5_resources_music.sql` **memperluas** tabel `resources` yang
sudah ada sejak Fase 1 (bukan membuat tabel baru):
- + `title`, `description`, `category`, `resource_type` ('file'/'link'),
  `external_url`
- `file_name`/`file_path` diubah jadi nullable (resource bertipe 'link'
  tidak punya file)
- Index pada `category` dan `resource_type`
- Data lama (upload pribadi Fase 1) di-backfill: `title` diisi dari
  `file_name` — **tidak ada data yang dihapus atau ditimpa**.

`focus_music` **tidak diubah sama sekali** — kolom yang sudah ada sejak
Fase 2 (`title`, `source_type`, `url_or_path`, `category`, `added_by`)
sudah cukup untuk UI kelola musik yang baru.

Diuji nyata: fresh install → struktur identik dengan hasil migrasi dari
Fase 4; migration dijalankan 3x berturut-turut → idempotent (tidak ada
kolom/index duplikat, exit code 0 setiap kali); baris resource lama milik
user tetap ada dan tampil benar setelah migrasi.

## Features Added

**Resource Library** — perpustakaan bersama (semua user bisa melihat
resource siapa pun), tapi edit/hapus hanya untuk pemilik masing-masing
(pola sama seperti Notes/Tasks). Dua jenis resource: tautan eksternal atau
file upload. Cari (judul+deskripsi), filter kategori & tipe, empty state
untuk kondisi kosong maupun hasil pencarian kosong.

**Upload aman** — validasi berlapis: ekstensi dari nama asli DAN mime
type sungguhan (dibaca dari isi file lewat `finfo`, bukan dipercaya dari
header browser) harus cocok; ukuran maks 10MB; nama file asli tidak
pernah dipakai untuk penyimpanan fisik (diganti nama acak 32-karakter
hex); nama asli hanya disimpan sebagai teks di database untuk ditampilkan/
di-download dengan nama yang familiar.

**Download aman** — lewat `resources.php?action=download&id=`, bukan
link langsung ke file. Path fisik divalidasi dengan `basename()` +
`realpath()` + memastikan hasil akhirnya benar-benar di dalam folder
`uploads/resources` sebelum di-`readfile()` — mencegah path traversal
sepenuhnya (dibuktikan lewat percobaan serangan nyata, lihat Testing).

**Kelola Musik Fokus** — halaman baru, bisa diakses dari link di
`focus.php`. Tambah/edit/hapus hanya untuk musik yang ditambahkan sendiri
(`added_by = user_id`); 5 musik bawaan sistem (`added_by IS NULL`) tidak
bisa disentuh siapa pun lewat UI ini. Path audio lokal divalidasi (tolak
`..`, wajib berekstensi audio) — **tidak ada fitur upload audio** di form
ini, sesuai instruksi untuk tidak membuka celah pelanggaran hak cipta;
path hanya menunjuk ke file yang sudah ditaruh manual di server.

## Security

- **Upload**: validasi ekstensi + MIME sungguhan (bukan dari klien) +
  ukuran; nama file fisik selalu digenerate acak; upload PHP yang
  disamarkan sebagai `.pdf` **dites langsung dan berhasil ditolak**
  (lihat Testing).
- **Path traversal**: `basename()` + `realpath()` + pengecekan prefix
  path pada download DAN saat hapus file; **dites langsung dengan baris
  database yang direkayasa berisi `../../../../etc/passwd`** — ditolak
  dengan benar, tidak ada kebocoran.
- **Eksekusi file**: `.htaccess` di `uploads/resources/` memblokir
  eksekusi PHP/CGI/script apa pun dan mematikan directory listing
  (pertahanan lapis kedua di atas validasi MIME).
- **Otorisasi**: edit/hapus resource & musik memakai pola ownership yang
  sama dengan Notes/Tasks (`WHERE ... AND user_id/added_by = ?`); dites
  langsung dengan user kedua mencoba menghapus resource user pertama —
  ditolak, data tetap utuh.
- **CSRF**: semua POST (tambah/edit/hapus resource, tambah/edit/hapus
  musik) memakai token CSRF yang sudah ada.
- **XSS**: semua output lewat helper `e()` yang sudah ada.
- **URL**: `validateExternalUrl()` menolak skema selain `http`/`https`
  (mis. `javascript:`) — dites langsung, ditolak dengan benar.
- **SQL**: seluruh query baru memakai prepared statements.
- **XP**: tidak ada reward XP baru ditambahkan untuk resource (sesuai
  instruksi "do not invent excessive reward system" — Fase 3 tidak pernah
  mendefinisikan mekanisme completion untuk resource, jadi tidak dibuat
  di Fase 5 juga).

## Testing

Semua diuji lewat PHP built-in server + MariaDB sungguhan:
- Migration 006 dijalankan 3x berturut-turut → idempotent.
- Tambah resource tautan → tersimpan & tampil benar.
- Upload PDF asli → tersimpan dengan nama fisik acak, `file_name` asli
  tetap tersimpan sebagai metadata.
- **Upload file PHP yang disamarkan sebagai `.pdf`** → ditolak
  ("Isi file tidak sesuai dengan jenis ekstensinya"), tidak pernah
  tertulis ke disk.
- Download → konten yang diunduh dibandingkan byte-per-byte dengan file
  asli, identik.
- **Percobaan path traversal** lewat baris database yang direkayasa
  (`file_path = '../../../../etc/passwd'`) → ditolak, tidak ada kebocoran.
- Hapus resource file → baris DB terhapus DAN file fisik ikut terhapus
  dari disk (dicek langsung).
- Isolasi antar-user: user kedua ("Budi") tidak bisa menghapus resource
  user pertama, dan tidak melihat tombol edit/hapus pada resource yang
  bukan miliknya.
- Kelola Musik: tambah musik baru → langsung muncul di pemilih musik
  `focus.php`; 5 musik bawaan sistem tidak punya tombol edit/hapus;
  URL tidak valid (`javascript:alert(1)`) ditolak; path audio dengan
  `..` ditolak; path audio valid diterima.
- Regresi penuh Fase 1-4: dashboard, tasks, focus (termasuk timer &
  session tracking), planner, notes, materials, quiz, progress,
  achievements, targets, profile, settings — semua HTTP 200.
- **Tidak ada satu pun PHP warning/notice/error** di log server selama
  seluruh sesi pengujian.
- `php -l` bersih + scan otomatis CSS untuk custom property yang tidak
  terdefinisi (bersih).

## Known Limitations

- Tidak ada UI upload untuk file audio (by design, lihat alasan hak
  cipta di atas) — hanya path ke file yang sudah ada di server.
- Belum ada halaman detail resource terpisah — deskripsi lengkap sudah
  ditampilkan langsung di kartu daftar (resource cenderung ringkas,
  halaman detail terpisah akan jadi duplikasi tanpa manfaat berarti).
- Batas upload 10MB diterapkan di kode aplikasi; jika `upload_max_filesize`
  atau `post_max_size` di `php.ini` server lebih kecil dari itu, PHP akan
  menolak lebih dulu dengan pesan yang tetap ramah (sudah ditangani).
- Belum ada admin role khusus yang bisa mengelola resource/musik milik
  user lain — sesuai instruksi Fase 5 untuk tetap kompatibel dengan
  Admin Panel Fase 6 tanpa membangunnya lebih dini.


---

# Fase 6 MVP — Admin Panel

**Baru:**
- `admin/index.php` — dashboard ringkas statistik aplikasi.
- `admin/users.php` — pencarian, filter role, dan perubahan role `student/admin`.
- `admin/resources.php` — CRUD resource global dengan validasi upload dan pembersihan file lama.
- `admin/music.php` — CRUD Focus Music untuk external link dan licensed audio.
- `admin/_bootstrap.php` — bootstrap dan navigasi admin bersama.

**Diubah:**
- `assets/css/style.css` — styling responsif untuk Admin Panel MVP.

**Catatan database:**
- Tidak membutuhkan migration baru karena kolom `users.role` (`student/admin`) dan `is_active` sudah tersedia pada schema.
- Tidak membuat tabel admin, resource, atau music baru; semua memakai tabel yang sudah ada.

**Keamanan:**
- Semua halaman admin memakai `requireAdmin()`.
- Mutasi memakai CSRF token dan prepared statements.
- Perubahan role melindungi admin terakhir dan mencegah self-demotion.
- Pengelolaan resource memvalidasi URL/file dan membatasi penghapusan file ke storage resource.

**Status pengujian:**
- `php -l` bersih untuk seluruh file PHP, termasuk file baru di `admin/`.
- Pengujian browser/XAMPP end-to-end tetap diperlukan untuk login sebagai student/admin dan alur CRUD nyata.

---

# Fase 7 — Final Polish, Security Hardening, Bug Sweep

Fase ini adalah audit & stabilisasi terhadap kode yang sudah ada (Fase 1-6
MVP), bukan penambahan fitur baru. Semua temuan di bawah diuji langsung
lewat PHP built-in server + MariaDB sungguhan — bukan sekadar baca kode.

## Bugs Found

1. **`admin/resources.php` — penggantian file rusak total.** Saat admin
   mengedit resource bertipe file yang SUDAH ADA dan melampirkan file
   pengganti, kode hanya memproses upload baru jika `!$existing ||
   $existing['resource_type'] !== 'file'` — kondisi ini otomatis salah
   untuk kasus paling umum (mengganti file dengan file lain), sehingga
   file baru yang dilampirkan **diam-diam diabaikan**. UI mengundang admin
   untuk "pilih file baru untuk mengganti file lama", tapi fitur itu tidak
   pernah benar-benar berfungsi.
2. **Focus Mode: waktu pause ikut terhitung sebagai waktu fokus aktif.**
   Pause/resume di `focus.php` murni state JavaScript di browser — server
   hanya tahu `started_at` (saat mulai) dan menghitung durasi akhir dari
   `time() - started_at` tanpa mengetahui kapan/berapa lama sesi di-pause.
   Akibatnya: mulai sesi, pause, tinggalkan komputer 20 menit, resume,
   langsung klik Selesai — server tetap menghitung ~20 menit sebagai waktu
   fokus aktif (dibatasi hanya oleh durasi rencana), padahal waktu aktif
   sungguhan mungkin hanya beberapa menit. Ini melemahkan perlindungan
   anti-manipulasi XP yang sudah dibangun sejak Fase 2.
3. **Dead markup**: `<nobr></nobr>` kosong tanpa fungsi di `admin/users.php`.

## Bugs Fixed

1. **File replacement** — logika diperbaiki jadi tiga jalur yang jelas:
   (a) resource baru atau ganti tipe ke 'file' → file **wajib** ada;
   (b) edit resource file yang sudah ada TANPA melampirkan file baru →
   file lama dipertahankan (metadata-only update, seperti semula);
   (c) edit resource file yang sudah ada DENGAN melampirkan file baru →
   file baru diproses & memvalidasi seperti upload biasa, lalu
   menggantikan file lama. **Diuji langsung**: upload file A → edit
   dengan melampirkan file B → isi yang di-download berubah jadi file B
   byte-per-byte, file A lama terhapus dari disk; lalu diuji lagi edit
   metadata saja (tanpa file baru) → file B tetap dipertahankan.
2. **Focus Mode pause tracking** — ditambahkan dua endpoint server baru
   yang minimal, memakai pola `$_SESSION` yang sudah ada:
   `action=pause_session` (mencatat waktu mulai pause) dan
   `action=resume_session` (mengakumulasi total detik pause, idempotent
   terhadap pause/resume ganda). `save_session` sekarang mengurangi total
   waktu pause dari durasi yang dihitung sebelum divalidasi/dibatasi.
   Tombol Pause/Resume di JS memanggil endpoint ini di background tanpa
   menghambat animasi timer. **Diuji langsung dengan skenario nyata**:
   sesi dengan 2 detik aktif → pause 32 detik → 2 detik aktif lagi
   (total wall-clock ~36 detik, melewati ambang minimum 30 detik) → sesi
   **ditolak** ("Sesi terlalu singkat untuk disimpan") karena waktu aktif
   sungguhan (~4 detik) dengan benar dikeluarkan dari waktu pause. Lalu
   diuji kasus normal (31 detik aktif + 3 detik pause + 2 detik aktif) →
   **berhasil disimpan** sebagai 1 menit, membuktikan fix tidak merusak
   alur normal. Juga diuji multiple pause/resume cycles (3x berturut)
   dan double-pause tanpa resume di antaranya — semua idempotent, tidak
   ada error.
3. Baris `<nobr></nobr>` dihapus.

## Security Improvements

- **Header keamanan global** ditambahkan di `config/config.php` (berlaku
  di setiap halaman): `X-Content-Type-Options: nosniff`,
  `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy:
  strict-origin-when-cross-origin`. **Sengaja tidak menambahkan CSP** —
  banyak halaman memakai `<script>` inline; CSP yang ketat akan
  mematahkannya tanpa audit nonce/hash penuh yang di luar cakupan
  perbaikan minimal fase ini.
- Semua temuan bug di atas (file replacement, focus pause) adalah
  perbaikan keamanan/integritas, bukan sekadar kosmetik.

## Performance Improvements

Tidak ditemukan masalah N+1 query atau query berlebih yang signifikan di
kode yang diaudit — pola query yang sudah ada (agregasi via SQL,
`LIMIT`, prepared statements per halaman) sudah wajar untuk skala
aplikasi ini. Tidak ada perubahan performa yang dilakukan karena tidak
ada masalah nyata yang ditemukan (sesuai instruksi "jangan optimasi
membabi buta").

## UI/UX Improvements

- Menghapus markup mati (`<nobr></nobr>`) di `admin/users.php`.
- Tidak ada perubahan visual/desain lain — audit tidak menemukan
  inkonsistensi UI yang cukup signifikan untuk diperbaiki tanpa risiko
  mengubah tampilan yang sudah stabil.

## Files Changed

- `focus.php` — endpoint `pause_session`/`resume_session` baru,
  `save_session` mengurangi waktu pause, JS Pause/Resume memanggil
  endpoint baru di background.
- `admin/resources.php` — logika penggantian file diperbaiki.
- `admin/users.php` — hapus `<nobr></nobr>` mati.
- `config/config.php` — tambah 3 header keamanan global.

**Tidak diubah sama sekali** (diverifikasi lewat diff): `admin/index.php`,
`admin/music.php`, `admin/_bootstrap.php`, seluruh `includes/`,
`database/`, dan semua halaman Fase 1-5 lainnya (`tasks.php`, `notes.php`,
`materials.php`, `quiz.php`, dll).

## Database Changes

**Tidak ada.** Diverifikasi lewat `diff` bahwa folder `database/` sama
sekali tidak berubah dari upload Fase 6 MVP — tidak ada migration baru
karena tidak ada defek skema nyata yang ditemukan selama audit.

## Tests Performed

Semua lewat PHP built-in server + MariaDB sungguhan, sesi ini:

- **Otorisasi admin** (4 rute: index/users/resources/music) untuk 3
  kondisi: logout → redirect ke login; student → 403 "Akses ditolak";
  admin → 200 OK.
- **Eskalasi role**: student mencoba POST `role=admin` langsung ke
  `admin/users.php` → 403 sebelum mencapai logika apa pun; role di DB
  tidak berubah. Di-scan juga seluruh project untuk memastikan tidak ada
  halaman lain yang mempercayai field `role` dari klien.
- **CSRF**: submit `change_role` dengan token acak/salah → ditolak
  ("Sesi form tidak valid"), tidak ada perubahan di DB.
- **Last-admin / self-demotion protection**: admin tunggal mencoba
  menurunkan role dirinya sendiri → ditolak, role tetap admin.
- **IDOR**: dua siswa berbeda — satu tidak bisa menghapus/mengubah task
  atau catatan milik yang lain lewat manipulasi ID; bahkan akun admin
  tidak bisa menghapus catatan siswa lewat halaman `notes.php` biasa
  (ownership check berlaku terlepas dari role di halaman non-admin).
- **Upload**: file PHP yang disamarkan sebagai `.pdf` ditolak di DUA
  jalur upload (`resources.php` milik siswa DAN `admin/resources.php`)
  lewat pengecekan MIME sungguhan (`finfo`), tidak pernah tertulis ke
  disk.
- **File replacement** (lihat Bugs Fixed #1) — diuji penuh termasuk
  pembersihan file lama dan preservasi file saat edit metadata-only.
- **Focus Mode pause/resume** (lihat Bugs Fixed #2) — skenario negatif
  (waktu pause lama, harus ditolak) dan positif (pause singkat, harus
  tetap tersimpan dengan durasi yang benar) diuji nyata dengan `sleep`
  sungguhan, bukan simulasi.
- **Idempotensi XP tugas**: task yang di-toggle selesai↔belum selesai
  5 kali berturut-turut → XP dan `tasks_completed` hanya bertambah SATU
  kali (terbukti lewat `completion_rewarded`), tidak peduli berapa kali
  siklusnya.
- **Audit mutasi-lewat-GET**: setiap query `INSERT`/`UPDATE`/`DELETE` di
  seluruh project dipastikan berada di dalam blok
  `$_SERVER['REQUEST_METHOD'] === 'POST'` — tidak ada satu pun mutasi
  yang bisa dipicu lewat request GET biasa.
- **Regresi penuh**: 14 halaman siswa + 4 halaman admin, semua HTTP 200,
  untuk total 63 request dalam sesi pengujian — **nol PHP
  warning/notice/error** di log server.
- `php -l` bersih untuk seluruh file `.php`, dan scan otomatis
  memastikan tidak ada custom property CSS yang tidak terdefinisi.

## Known Limitations

- **Tidak ada Content-Security-Policy** — didokumentasikan di atas,
  memerlukan audit nonce/hash terpisah karena banyak `<script>` inline.
- **Toggle aktif/nonaktif user belum ada UI-nya** di `admin/users.php`
  (kolom status ditampilkan, tapi tombol untuk mengubahnya belum
  dibangun) — bukan bug, hanya fitur yang belum ada; tidak dibangun di
  fase ini karena Fase 7 secara eksplisit melarang penambahan fitur baru.
- **Audit ini tidak menguji SETIAP kombinasi IDOR/CSRF** di semua 20+
  halaman satu per satu — difokuskan ke area berisiko tertinggi (admin,
  upload/download, Focus Mode, progres/XP) sesuai prioritas yang
  diminta. Halaman lain (planner, quiz review, dst.) mewarisi pola
  ownership-check yang sama dan sudah diuji di fase-fase sebelumnya,
  tapi tidak diuji ulang satu-per-satu secara eksplisit di sesi ini.
- **Race condition** pada Focus Mode pause/resume (dua klik pause/resume
  yang sangat berdekatan dari koneksi lambat) belum diuji secara
  spesifik — desainnya idempotent secara logika, tapi belum ada uji
  concurrency nyata.
- Klaim di laporan ini terbatas pada apa yang benar-benar diuji di atas.
  **Aplikasi ini TIDAK diklaim "100% aman" atau "bebas bug sepenuhnya"**
  — hanya area yang diaudit di Fase 7 yang bisa dipertanggungjawabkan
  lewat pengujian nyata di atas.
