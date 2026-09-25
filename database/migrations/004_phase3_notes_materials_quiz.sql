-- ============================================================
-- Fokusin — Migration 004 (Fase 3)
-- Notes / Materi / Kuis.
--
-- Prinsip: HANYA menambah kolom/tabel yang benar-benar dibutuhkan.
-- Tidak ada tabel yang di-duplikasi — notes/materials/quizzes/
-- quiz_questions/quiz_options/quiz_attempts/quiz_answers semua
-- sudah ada sejak schema.sql Fase 1, migration ini hanya
-- melengkapi apa yang belum cukup.
--
-- Aman dijalankan berulang kali (idempotent): setiap ALTER
-- memakai IF NOT EXISTS di mana didukung, dan setiap INSERT seed
-- data dilindungi UNIQUE KEY + ON DUPLICATE KEY UPDATE / INSERT
-- IGNORE, bukan sekadar INSERT biasa.
-- ============================================================

USE fokusin_db;

-- ------------------------------------------------------------
-- NOTES: hubungkan ke tabel `subjects` yang sudah ada (bukan
-- menyimpan nama mapel bebas berulang), dan tambahkan status
-- "penting". Kolom `category` lama TIDAK dihapus (tetap ada demi
-- kompatibilitas), hanya tidak dipakai aktif lagi mulai Fase 3.
-- ------------------------------------------------------------
ALTER TABLE notes
    ADD COLUMN IF NOT EXISTS subject_id INT UNSIGNED NULL AFTER user_id,
    ADD COLUMN IF NOT EXISTS is_important TINYINT(1) NOT NULL DEFAULT 0 AFTER content;

-- FK & index ditambahkan terpisah dan dibuat aman dijalankan berulang:
-- index memakai IF NOT EXISTS (didukung MariaDB), sedangkan foreign key
-- diperiksa dulu lewat information_schema sebelum ditambahkan (FOREIGN
-- KEY tidak mendukung IF NOT EXISTS secara langsung).
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'notes'
      AND CONSTRAINT_NAME = 'fk_notes_subject'
);
SET @add_fk_sql = IF(@fk_exists = 0,
    'ALTER TABLE notes ADD CONSTRAINT fk_notes_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @add_fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE notes
    ADD INDEX IF NOT EXISTS idx_notes_user_subject (user_id, subject_id);

-- ------------------------------------------------------------
-- MATERIALS: tambahkan kategori bebas (terpisah dari mapel) dan
-- UNIQUE pada title supaya seed data di bawah aman diulang.
-- ------------------------------------------------------------
ALTER TABLE materials
    ADD COLUMN IF NOT EXISTS category VARCHAR(60) NULL AFTER subject_id;

ALTER TABLE materials
    ADD UNIQUE KEY IF NOT EXISTS uniq_materials_title (title);

-- ------------------------------------------------------------
-- QUIZZES: UNIQUE pada title (untuk seed idempotent).
-- ------------------------------------------------------------
ALTER TABLE quizzes
    ADD UNIQUE KEY IF NOT EXISTS uniq_quizzes_title (title);

-- QUIZ_QUESTIONS: satu quiz tidak boleh punya dua soal dengan
-- order_index yang sama — sekaligus membuat seed idempotent.
ALTER TABLE quiz_questions
    ADD UNIQUE KEY IF NOT EXISTS uniq_quiz_question_order (quiz_id, order_index);

-- QUIZ_OPTIONS: satu soal tidak boleh punya dua opsi dengan teks
-- yang identik — sekaligus membuat seed idempotent.
ALTER TABLE quiz_options
    ADD UNIQUE KEY IF NOT EXISTS uniq_quiz_option_text (question_id, option_text);

-- ------------------------------------------------------------
-- MATERIAL COMPLETIONS: tabel baru (belum ada di Fase 1/2) untuk
-- mencatat materi yang sudah dipelajari PER USER. UNIQUE
-- (user_id, material_id) membuat penandaan selesai idempotent —
-- tidak mungkin dapat XP dobel dari materi yang sama.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS material_completions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED        NOT NULL,
    material_id     INT UNSIGNED        NOT NULL,
    completed_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_material (user_id, material_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Seed data: mapel, materi & kuis contoh supaya modul ini
-- langsung bisa dicoba/diuji tanpa Admin Panel (baru datang di
-- Fase 6). Konten ditulis sendiri (bukan disalin dari sumber
-- lain). `subjects.name` tidak punya UNIQUE (disengaja — user
-- boleh punya mapel dengan nama sama milik user lain), jadi
-- di-guard manual dengan WHERE NOT EXISTS alih-alih ON DUPLICATE
-- KEY UPDATE.
-- ------------------------------------------------------------
INSERT INTO subjects (name, created_by)
SELECT 'Pemrograman Web', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE name = 'Pemrograman Web' AND created_by IS NULL);

INSERT INTO subjects (name, created_by)
SELECT 'Basis Data', NULL FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE name = 'Basis Data' AND created_by IS NULL);

INSERT INTO materials (subject_id, category, title, description, content, external_url, created_by)
SELECT s.id, 'Tutorial', 'Pengenalan DOM JavaScript',
       'Memahami apa itu Document Object Model dan cara mengaksesnya.',
       'DOM (Document Object Model) adalah representasi struktur HTML sebuah halaman dalam bentuk objek yang bisa dibaca dan diubah lewat JavaScript. Setiap elemen HTML — heading, paragraf, tombol — menjadi sebuah node dalam DOM.\n\nUntuk mengambil elemen, kamu bisa memakai document.querySelector("selector") untuk satu elemen pertama yang cocok, atau document.querySelectorAll("selector") untuk semua elemen yang cocok.\n\nSetelah elemen didapat, kamu bisa membaca atau mengubah isinya lewat properti seperti .textContent, .innerHTML, atau mengubah tampilannya lewat .style dan .classList.\n\nDOM juga mendukung event, misalnya element.addEventListener("click", fungsi) untuk menjalankan kode saat elemen tersebut diklik.',
       NULL, NULL
FROM subjects s WHERE s.name = 'Pemrograman Web' AND s.created_by IS NULL
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO materials (subject_id, category, title, description, content, external_url, created_by)
SELECT s.id, 'Referensi', 'Dasar CSS Flexbox',
       'Cara menyusun elemen secara fleksibel dengan display: flex.',
       'Flexbox adalah model layout CSS untuk mengatur elemen dalam satu baris atau satu kolom secara fleksibel.\n\nMengaktifkannya cukup dengan memberi display: flex pada elemen induk (container). Elemen-elemen di dalamnya (item) otomatis tersusun berdampingan.\n\nBeberapa properti penting pada container: justify-content mengatur perataan horizontal (misalnya center, space-between), align-items mengatur perataan vertikal, dan flex-wrap mengatur apakah item boleh turun ke baris baru saat kehabisan tempat.\n\nPada item, properti flex: 1 membuat item tersebut mengisi sisa ruang yang tersedia secara proporsional.',
       'https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_flexible_box_layout/Basic_concepts_of_flexbox', NULL
FROM subjects s WHERE s.name = 'Pemrograman Web' AND s.created_by IS NULL
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO materials (subject_id, category, title, description, content, external_url, created_by)
SELECT s.id, 'Tutorial', 'Perintah Dasar SQL: SELECT, INSERT, UPDATE, DELETE',
       'Empat perintah SQL paling sering dipakai untuk mengelola data di tabel.',
       'SELECT dipakai untuk mengambil data dari tabel, misalnya SELECT nama, umur FROM siswa WHERE kelas = "10A".\n\nINSERT dipakai untuk menambah baris baru: INSERT INTO siswa (nama, umur) VALUES ("Rani", 16).\n\nUPDATE dipakai untuk mengubah data yang sudah ada: UPDATE siswa SET umur = 17 WHERE nama = "Rani". Selalu sertakan WHERE, karena UPDATE tanpa WHERE akan mengubah SEMUA baris di tabel.\n\nDELETE dipakai untuk menghapus baris: DELETE FROM siswa WHERE nama = "Rani". Sama seperti UPDATE, DELETE tanpa WHERE akan menghapus seluruh isi tabel — jadi selalu periksa kondisi WHERE sebelum menjalankannya.',
       NULL, NULL
FROM subjects s WHERE s.name = 'Basis Data' AND s.created_by IS NULL
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO materials (subject_id, category, title, description, content, external_url, created_by)
SELECT s.id, 'Referensi', 'Apa itu Primary Key dan Foreign Key?',
       'Konsep dasar relasi antar tabel dalam basis data relasional.',
       'Primary key adalah kolom (atau kombinasi kolom) yang nilainya unik untuk setiap baris dalam satu tabel, dan dipakai untuk mengidentifikasi baris tersebut secara pasti. Biasanya berupa kolom id yang auto-increment.\n\nForeign key adalah kolom pada satu tabel yang menyimpan nilai primary key dari tabel lain, untuk menghubungkan (relasi) kedua tabel tersebut. Misalnya, kolom user_id pada tabel tasks adalah foreign key yang menunjuk ke id pada tabel users — artinya setiap tugas "milik" satu user tertentu.\n\nDengan foreign key, database bisa menjaga integritas data: misalnya mencegah kita menyimpan tugas dengan user_id yang usernya sendiri tidak pernah ada.',
       NULL, NULL
FROM subjects s WHERE s.name = 'Basis Data' AND s.created_by IS NULL
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- Kuis contoh: "JavaScript Dasar"
INSERT INTO quizzes (subject_id, title, description, created_by)
SELECT s.id, 'JavaScript Dasar', 'Uji pemahamanmu mengenai dasar-dasar JavaScript.', NULL
FROM subjects s WHERE s.name = 'Pemrograman Web' AND s.created_by IS NULL
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO quiz_questions (quiz_id, question_text, order_index)
SELECT q.id, 'Apa fungsi dari document.querySelector()?', 1 FROM quizzes q WHERE q.title = 'JavaScript Dasar'
UNION ALL
SELECT q.id, 'Kata kunci apa yang dipakai untuk mendeklarasikan variabel yang nilainya tidak boleh diubah?', 2 FROM quizzes q WHERE q.title = 'JavaScript Dasar'
UNION ALL
SELECT q.id, 'Apa hasil dari typeof "10" di JavaScript?', 3 FROM quizzes q WHERE q.title = 'JavaScript Dasar'
UNION ALL
SELECT q.id, 'Method array apa yang dipakai untuk menambahkan elemen di akhir array?', 4 FROM quizzes q WHERE q.title = 'JavaScript Dasar'
UNION ALL
SELECT q.id, 'Apa fungsi dari addEventListener()?', 5 FROM quizzes q WHERE q.title = 'JavaScript Dasar'
ON DUPLICATE KEY UPDATE question_text = VALUES(question_text);

INSERT INTO quiz_options (question_id, option_text, is_correct)
SELECT qq.id, 'Menghapus elemen HTML', 0 FROM quiz_questions qq JOIN quizzes q ON q.id = qq.quiz_id WHERE q.title='JavaScript Dasar' AND qq.order_index=1
UNION ALL SELECT qq.id, 'Memilih elemen HTML', 1 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='JavaScript Dasar' AND qq.order_index=1
UNION ALL SELECT qq.id, 'Membuat database', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='JavaScript Dasar' AND qq.order_index=1
UNION ALL SELECT qq.id, 'Menjalankan PHP', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='JavaScript Dasar' AND qq.order_index=1

UNION ALL SELECT qq.id, 'var', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='JavaScript Dasar' AND qq.order_index=2
UNION ALL SELECT qq.id, 'let', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='JavaScript Dasar' AND qq.order_index=2
UNION ALL SELECT qq.id, 'const', 1 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='JavaScript Dasar' AND qq.order_index=2
UNION ALL SELECT qq.id, 'function', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='JavaScript Dasar' AND qq.order_index=2

UNION ALL SELECT qq.id, '"number"', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='JavaScript Dasar' AND qq.order_index=3
UNION ALL SELECT qq.id, '"string"', 1 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='JavaScript Dasar' AND qq.order_index=3
UNION ALL SELECT qq.id, '"boolean"', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='JavaScript Dasar' AND qq.order_index=3
UNION ALL SELECT qq.id, '"undefined"', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='JavaScript Dasar' AND qq.order_index=3

UNION ALL SELECT qq.id, 'push()', 1 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='JavaScript Dasar' AND qq.order_index=4
UNION ALL SELECT qq.id, 'pop()', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='JavaScript Dasar' AND qq.order_index=4
UNION ALL SELECT qq.id, 'shift()', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='JavaScript Dasar' AND qq.order_index=4
UNION ALL SELECT qq.id, 'slice()', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='JavaScript Dasar' AND qq.order_index=4

UNION ALL SELECT qq.id, 'Menjalankan fungsi saat suatu event terjadi pada elemen', 1 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='JavaScript Dasar' AND qq.order_index=5
UNION ALL SELECT qq.id, 'Menghapus event dari elemen', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='JavaScript Dasar' AND qq.order_index=5
UNION ALL SELECT qq.id, 'Membuat elemen baru', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='JavaScript Dasar' AND qq.order_index=5
UNION ALL SELECT qq.id, 'Mengubah warna elemen', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='JavaScript Dasar' AND qq.order_index=5
ON DUPLICATE KEY UPDATE is_correct = VALUES(is_correct);

-- Kuis contoh: "Database Dasar"
INSERT INTO quizzes (subject_id, title, description, created_by)
SELECT s.id, 'Database Dasar', 'Uji pemahamanmu mengenai dasar-dasar basis data relasional.', NULL
FROM subjects s WHERE s.name = 'Basis Data' AND s.created_by IS NULL
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO quiz_questions (quiz_id, question_text, order_index)
SELECT q.id, 'Perintah SQL apa yang dipakai untuk mengambil data dari tabel?', 1 FROM quizzes q WHERE q.title = 'Database Dasar'
UNION ALL
SELECT q.id, 'Apa fungsi utama dari PRIMARY KEY?', 2 FROM quizzes q WHERE q.title = 'Database Dasar'
UNION ALL
SELECT q.id, 'Perintah apa yang dipakai untuk menghapus baris pada tabel?', 3 FROM quizzes q WHERE q.title = 'Database Dasar'
UNION ALL
SELECT q.id, 'Apa itu FOREIGN KEY?', 4 FROM quizzes q WHERE q.title = 'Database Dasar'
UNION ALL
SELECT q.id, 'Klausa apa yang dipakai untuk menyaring baris berdasarkan kondisi tertentu?', 5 FROM quizzes q WHERE q.title = 'Database Dasar'
ON DUPLICATE KEY UPDATE question_text = VALUES(question_text);

INSERT INTO quiz_options (question_id, option_text, is_correct)
SELECT qq.id, 'SELECT', 1 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='Database Dasar' AND qq.order_index=1
UNION ALL SELECT qq.id, 'INSERT', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='Database Dasar' AND qq.order_index=1
UNION ALL SELECT qq.id, 'UPDATE', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='Database Dasar' AND qq.order_index=1
UNION ALL SELECT qq.id, 'DELETE', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='Database Dasar' AND qq.order_index=1

UNION ALL SELECT qq.id, 'Mempercantik tampilan tabel', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='Database Dasar' AND qq.order_index=2
UNION ALL SELECT qq.id, 'Mengidentifikasi setiap baris secara unik', 1 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='Database Dasar' AND qq.order_index=2
UNION ALL SELECT qq.id, 'Menghapus data duplikat otomatis', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='Database Dasar' AND qq.order_index=2
UNION ALL SELECT qq.id, 'Mengurutkan data', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='Database Dasar' AND qq.order_index=2

UNION ALL SELECT qq.id, 'SELECT', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='Database Dasar' AND qq.order_index=3
UNION ALL SELECT qq.id, 'DELETE', 1 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='Database Dasar' AND qq.order_index=3
UNION ALL SELECT qq.id, 'DROP', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='Database Dasar' AND qq.order_index=3
UNION ALL SELECT qq.id, 'ALTER', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='Database Dasar' AND qq.order_index=3

UNION ALL SELECT qq.id, 'Kolom yang menyimpan file gambar', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='Database Dasar' AND qq.order_index=4
UNION ALL SELECT qq.id, 'Kolom yang menunjuk ke primary key tabel lain untuk membuat relasi', 1 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='Database Dasar' AND qq.order_index=4
UNION ALL SELECT qq.id, 'Kolom yang nilainya selalu NULL', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='Database Dasar' AND qq.order_index=4
UNION ALL SELECT qq.id, 'Kolom yang otomatis terhapus', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='Database Dasar' AND qq.order_index=4

UNION ALL SELECT qq.id, 'ORDER BY', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='Database Dasar' AND qq.order_index=5
UNION ALL SELECT qq.id, 'GROUP BY', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='Database Dasar' AND qq.order_index=5
UNION ALL SELECT qq.id, 'WHERE', 1 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='Database Dasar' AND qq.order_index=5
UNION ALL SELECT qq.id, 'LIMIT', 0 FROM quiz_questions qq JOIN quizzes q ON q.id=qq.quiz_id WHERE q.title='Database Dasar' AND qq.order_index=5
ON DUPLICATE KEY UPDATE is_correct = VALUES(is_correct);
