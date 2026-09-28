# Fokusin

> **Fokus belajar. Atur tugas. Raih tujuan.**

Fokusin adalah platform produktivitas dan pembelajaran yang dirancang untuk membantu pelajar mengatur tugas, merencanakan waktu belajar, menjaga fokus, mempelajari materi, mengerjakan kuis, dan memantau perkembangan belajar dalam satu aplikasi.

Fokusin menggabungkan berbagai kebutuhan belajar dalam satu tempat dengan tampilan yang sederhana, responsif, dan mudah digunakan.

---

## ✨ Fitur

### 📊 Dashboard

Menampilkan ringkasan aktivitas belajar seperti tugas, waktu belajar, XP, streak, target, dan pencapaian.

### ✅ Task Manager

Mengelola tugas sekolah dengan:

* Tambah, edit, dan hapus tugas
* Status tugas
* Prioritas
* Deadline
* Pencarian dan filter
* Deteksi tugas terlambat

### ⏱️ Focus Mode

Membantu pengguna belajar dengan lebih fokus menggunakan timer.

Fitur:

* Start
* Pause
* Resume
* Complete
* Server-side session tracking
* Perhitungan waktu belajar
* XP
* Streak
* Achievement

### 📅 Study Planner

Membantu pengguna membuat dan mengatur jadwal belajar berdasarkan tanggal, waktu, dan mata pelajaran.

### 📝 Notes

Tempat untuk membuat dan mengelola catatan belajar.

Fitur:

* Tambah catatan
* Edit catatan
* Hapus catatan
* Pencarian
* Filter
* Catatan penting

### 📚 Materials

Tempat menyimpan dan mengelola materi pembelajaran agar lebih mudah diakses.

### 🧠 Quiz

Sistem kuis untuk menguji pemahaman pengguna.

Fitur:

* Daftar kuis
* Soal dan pilihan jawaban
* Penilaian dari server
* Hasil kuis
* Review
* Riwayat pengerjaan

### ⭐ XP, Level & Streak

Aktivitas belajar pengguna dapat meningkatkan perkembangan akun melalui:

* XP
* Level
* Current streak
* Longest streak
* Total waktu belajar

### 🏆 Achievements

Pencapaian dapat terbuka berdasarkan aktivitas dan konsistensi belajar.

### 🎯 Weekly Targets

Target belajar mingguan untuk membantu pengguna memantau:

* Jam belajar
* Tugas
* Kuis
* Materi

### 📖 Resource Library

Tempat untuk menemukan dan mengelola sumber belajar seperti:

* Website
* Artikel
* Video
* Dokumentasi
* File pembelajaran

Tersedia pencarian dan filter untuk mempermudah menemukan resource.

### 🎵 Focus Music

Focus Mode mendukung:

* External music links
* Licensed local audio
* Pemilihan musik dalam sesi fokus

Fokusin tidak mengunduh atau melakukan scraping terhadap YouTube maupun YouTube Music.

### 🛠️ Admin Panel

Admin dapat mengelola:

* Pengguna
* Role pengguna
* Resource
* Focus Music
* Statistik dasar

Admin Panel dilengkapi dengan authorization server-side dan perlindungan CSRF.

---

## 🔐 Security

Fokusin menerapkan berbagai mekanisme keamanan dasar, seperti:

* Password hashing menggunakan `password_hash()`
* Password verification menggunakan `password_verify()`
* Session regeneration
* Role-based authorization
* CSRF protection
* Prepared statements
* Output escaping untuk membantu mencegah XSS
* IDOR protection
* Upload validation
* MIME type validation
* File extension validation
* Safe filename generation
* Path traversal protection
* Secure resource download
* HTTP security headers

Upload file juga divalidasi untuk mencegah file executable digunakan sebagai file upload biasa.

---

## 📱 Responsive Design

Fokusin dirancang agar dapat digunakan pada:

* Desktop
* Laptop
* Tablet
* Smartphone / Android

Tampilan telah disesuaikan untuk berbagai ukuran layar dengan:

* Responsive sidebar
* Mobile header
* Mobile bottom navigation
* Responsive cards
* Responsive forms
* Responsive tables

---

## 🛠️ Tech Stack

### Frontend

* HTML5
* CSS3
* Vanilla JavaScript

### Backend

* PHP

### Database

* MySQL / MariaDB

### Development

* XAMPP

---

## 📁 Struktur Project

```text
fokusin/
├── admin/
├── assets/
│   ├── css/
│   └── js/
├── config/
├── database/
│   ├── migrations/
│   └── schema.sql
├── includes/
├── storage/
├── achievements.php
├── dashboard.php
├── focus.php
├── login.php
├── logout.php
├── materials.php
├── notes.php
├── planner.php
├── profile.php
├── progress.php
├── quiz.php
├── register.php
├── resources.php
├── settings.php
├── targets.php
└── tasks.php
```

---

## 🚀 Instalasi Lokal

### 1. Clone repository

```bash
git clone https://github.com/USERNAME/fokusin.git
```

Masuk ke folder:

```bash
cd fokusin
```

### 2. Jalankan XAMPP

Aktifkan:

```text
Apache
MySQL
```

### 3. Buat database

Buka phpMyAdmin dan buat database:

```text
fokusin_db
```

### 4. Import database

Untuk instalasi baru, import:

```text
database/schema.sql
```

Jika menggunakan database dari instalasi yang sudah berjalan, gunakan migration yang belum pernah dijalankan sesuai kebutuhan.

### 5. Konfigurasi database

Sesuaikan konfigurasi pada:

```text
config/config.php
config/database.php
```

dengan konfigurasi MySQL/MariaDB lokal.

### 6. Jalankan aplikasi

Letakkan folder project di:

```text
C:\xampp\htdocs\fokusin
```

Kemudian buka:

```text
http://localhost/fokusin/
```

---

## 👤 Role Pengguna

### Student

Pengguna utama aplikasi yang dapat menggunakan fitur produktivitas dan pembelajaran.

### Admin

Pengguna dengan akses administratif untuk mengelola pengguna, resource, dan Focus Music.

---

## 🎯 Tujuan Project

Fokusin dibuat untuk membantu pelajar mengelola aktivitas belajar dalam satu platform:

```text
Tugas
+
Jadwal Belajar
+
Focus Mode
+
Catatan
+
Materi
+
Kuis
+
Progress
+
XP
+
Achievement
+
Target
+
Resource
```

Tujuannya adalah membuat kegiatan belajar menjadi lebih **terorganisir, terukur, dan konsisten**.

---

## 📌 Status

**Fokusin v1.0.0**

Status aplikasi:

```text
✅ Authentication
✅ Task Management
✅ Focus Mode
✅ Study Planner
✅ Notes
✅ Materials
✅ Quiz
✅ XP & Level
✅ Streak
✅ Achievements
✅ Weekly Targets
✅ Progress Tracking
✅ Resource Library
✅ File Upload
✅ Secure Download
✅ Focus Music
✅ Admin Panel
✅ Responsive Design
✅ Security Hardening
```

Fokusin saat ini berada pada versi stabil untuk penggunaan lokal, demo, portfolio, dan pengembangan lebih lanjut.

---

## 🔮 Pengembangan Berikutnya

Beberapa fitur yang dapat dikembangkan pada versi selanjutnya:

* Upload musik langsung dari Admin Panel
* Pemutaran audio lokal langsung di Focus Mode
* Notifikasi belajar
* Analytics yang lebih lengkap
* PWA / instalasi sebagai aplikasi
* Pengembangan fitur pembelajaran tambahan

---

## 👨‍💻 Author

**Noval Joeliawan**

Fokusin dikembangkan sebagai project pembelajaran dan pengembangan aplikasi web dalam bidang Rekayasa Perangkat Lunak.

---

## 📄 License

Tambahkan lisensi sesuai kebutuhan project sebelum repository dipublikasikan secara resmi.
