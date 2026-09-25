-- ============================================================
-- FOKUSIN - Database Schema
-- Target: MySQL 5.7+ / MariaDB (XAMPP default)
-- Charset: utf8mb4 (full emoji + Indonesian text support)
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS fokusin_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fokusin_db;

-- USERS
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100)        NOT NULL,
    email           VARCHAR(150)        NOT NULL UNIQUE,
    password_hash   VARCHAR(255)        NOT NULL,
    role            ENUM('student','admin') NOT NULL DEFAULT 'student',
    avatar_path     VARCHAR(255)        NULL,
    theme           ENUM('light','dark') NOT NULL DEFAULT 'light',
    is_active       TINYINT(1)          NOT NULL DEFAULT 1,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- SUBJECTS (Mata Pelajaran)
CREATE TABLE IF NOT EXISTS subjects (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100)        NOT NULL,
    color_hex       VARCHAR(7)          NULL,
    created_by      INT UNSIGNED        NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- TASKS (Tugas)
CREATE TABLE IF NOT EXISTS tasks (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED        NOT NULL,
    subject_id      INT UNSIGNED        NULL,
    title           VARCHAR(150)        NOT NULL,
    description     TEXT                NULL,
    priority        ENUM('tinggi','sedang','rendah') NOT NULL DEFAULT 'sedang',
    status          ENUM('belum_selesai','sedang_dikerjakan','selesai') NOT NULL DEFAULT 'belum_selesai',
    deadline        DATETIME            NULL,
    completed_at    DATETIME            NULL,
    completion_rewarded TINYINT(1)      NOT NULL DEFAULT 0,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    INDEX idx_tasks_user_status (user_id, status),
    INDEX idx_tasks_deadline (deadline)
) ENGINE=InnoDB;

-- STUDY SESSIONS (Sesi Fokus)
CREATE TABLE IF NOT EXISTS study_sessions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED        NOT NULL,
    task_id         INT UNSIGNED        NULL,
    label           VARCHAR(150)        NULL,
    duration_minutes INT UNSIGNED       NOT NULL DEFAULT 0,
    started_at      DATETIME            NOT NULL,
    ended_at        DATETIME            NULL,
    is_completed    TINYINT(1)          NOT NULL DEFAULT 0,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
    INDEX idx_sessions_user_date (user_id, started_at)
) ENGINE=InnoDB;

-- STUDY PLANS (Planner)
CREATE TABLE IF NOT EXISTS study_plans (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED        NOT NULL,
    subject_id      INT UNSIGNED        NULL,
    plan_date       DATE                NOT NULL,
    start_time      TIME                NOT NULL,
    end_time        TIME                NOT NULL,
    notes           TEXT                NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    INDEX idx_plans_user_date (user_id, plan_date)
) ENGINE=InnoDB;

-- NOTES (Catatan)
CREATE TABLE IF NOT EXISTS notes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED        NOT NULL,
    subject_id      INT UNSIGNED        NULL,
    title           VARCHAR(150)        NOT NULL,
    content         TEXT                NULL,
    is_important    TINYINT(1)          NOT NULL DEFAULT 0,
    category        VARCHAR(50)         NOT NULL DEFAULT 'Other',
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    INDEX idx_notes_user (user_id),
    INDEX idx_notes_user_subject (user_id, subject_id)
) ENGINE=InnoDB;

-- MATERIALS (Materi Belajar)
CREATE TABLE IF NOT EXISTS materials (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_id      INT UNSIGNED        NULL,
    category        VARCHAR(60)         NULL,
    title           VARCHAR(150)        NOT NULL,
    description     TEXT                NULL,
    content         LONGTEXT            NULL,
    external_url    VARCHAR(255)        NULL,
    created_by      INT UNSIGNED        NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_materials_title (title)
) ENGINE=InnoDB;

-- MATERIAL COMPLETIONS: materi yang sudah ditandai selesai per user.
-- UNIQUE (user_id, material_id) membuat penandaan selesai idempotent.
CREATE TABLE IF NOT EXISTS material_completions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED        NOT NULL,
    material_id     INT UNSIGNED        NOT NULL,
    completed_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_material (user_id, material_id)
) ENGINE=InnoDB;

-- QUIZZES
CREATE TABLE IF NOT EXISTS quizzes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_id      INT UNSIGNED        NULL,
    title           VARCHAR(150)        NOT NULL,
    description     TEXT                NULL,
    created_by      INT UNSIGNED        NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_quizzes_title (title)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quiz_questions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id         INT UNSIGNED        NOT NULL,
    question_text   TEXT                NOT NULL,
    order_index     INT UNSIGNED        NOT NULL DEFAULT 0,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_quiz_question_order (quiz_id, order_index)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quiz_options (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id     INT UNSIGNED        NOT NULL,
    option_text     VARCHAR(255)        NOT NULL,
    is_correct      TINYINT(1)          NOT NULL DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_quiz_option_text (question_id, option_text)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quiz_attempts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id         INT UNSIGNED        NOT NULL,
    user_id         INT UNSIGNED        NOT NULL,
    score           DECIMAL(5,2)        NOT NULL DEFAULT 0,
    correct_count   INT UNSIGNED        NOT NULL DEFAULT 0,
    wrong_count     INT UNSIGNED        NOT NULL DEFAULT 0,
    started_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at     DATETIME            NULL,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quiz_answers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_id      INT UNSIGNED        NOT NULL,
    question_id     INT UNSIGNED        NOT NULL,
    selected_option_id INT UNSIGNED     NULL,
    is_correct      TINYINT(1)          NOT NULL DEFAULT 0,
    FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (selected_option_id) REFERENCES quiz_options(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ACHIEVEMENTS (Pencapaian)
CREATE TABLE IF NOT EXISTS achievements (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(50)         NOT NULL UNIQUE,
    name            VARCHAR(100)        NOT NULL,
    description     VARCHAR(255)        NOT NULL,
    icon            VARCHAR(10)         NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_achievements (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED        NOT NULL,
    achievement_id  INT UNSIGNED        NOT NULL,
    unlocked_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_achievement (user_id, achievement_id)
) ENGINE=InnoDB;

-- Default achievements Phase 4
INSERT INTO achievements (code, name, description, icon) VALUES
('first_focus', 'Mulai Fokus', 'Selesaikan sesi fokus pertamamu.', '⏱️'),
('focus_60', 'Satu Jam Fokus', 'Kumpulkan total 60 menit belajar.', '🔥'),
('task_5', 'Task Crusher', 'Selesaikan 5 tugas.', '✅'),
('quiz_3', 'Quiz Runner', 'Selesaikan 3 percobaan kuis.', '🧠'),
('material_3', 'Rajin Belajar', 'Selesaikan 3 materi belajar.', '📚'),
('notes_5', 'Note Taker', 'Buat 5 catatan.', '📝'),
('streak_7', 'Konsisten 7 Hari', 'Pertahankan streak belajar hingga 7 hari.', '📅'),
('level_5', 'Naik Kelas', 'Capai Level 5.', '🏆')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), icon = VALUES(icon);

-- USER PROGRESS (XP, Level, Streak - one row per user)
CREATE TABLE IF NOT EXISTS user_progress (
    user_id             INT UNSIGNED    NOT NULL PRIMARY KEY,
    xp                  INT UNSIGNED    NOT NULL DEFAULT 0,
    level               INT UNSIGNED    NOT NULL DEFAULT 1,
    current_streak      INT UNSIGNED    NOT NULL DEFAULT 0,
    longest_streak      INT UNSIGNED    NOT NULL DEFAULT 0,
    last_activity_date  DATE            NULL,
    total_study_minutes INT UNSIGNED    NOT NULL DEFAULT 0,
    tasks_completed     INT UNSIGNED    NOT NULL DEFAULT 0,
    quizzes_completed   INT UNSIGNED    NOT NULL DEFAULT 0,
    focus_sessions_completed INT UNSIGNED NOT NULL DEFAULT 0,
    materials_completed INT UNSIGNED    NOT NULL DEFAULT 0,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- STUDY TARGETS (Target Mingguan)
CREATE TABLE IF NOT EXISTS study_targets (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED        NOT NULL,
    subject_id      INT UNSIGNED        NULL,
    week_start_date DATE                NOT NULL,
    target_hours    DECIMAL(5,2)        NOT NULL DEFAULT 0,
    target_tasks    INT UNSIGNED        NOT NULL DEFAULT 0,
    target_quizzes  INT UNSIGNED        NOT NULL DEFAULT 0,
    target_materials INT UNSIGNED       NOT NULL DEFAULT 0,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    INDEX idx_targets_user_week (user_id, week_start_date),
    UNIQUE KEY uniq_targets_user_week (user_id, week_start_date)
) ENGINE=InnoDB;

-- RESOURCES (File & Link Pribadi)
-- Bisa berupa file yang diupload (resource_type='file') atau tautan
-- eksternal (resource_type='link', file_name/file_path NULL).
CREATE TABLE IF NOT EXISTS resources (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED        NOT NULL,
    title           VARCHAR(150)        NULL,
    description     TEXT                NULL,
    category        VARCHAR(60)         NULL,
    resource_type   ENUM('file','link') NOT NULL DEFAULT 'file',
    external_url    VARCHAR(255)        NULL,
    file_name       VARCHAR(255)        NULL,
    file_path       VARCHAR(255)        NULL,
    file_type       VARCHAR(50)         NULL,
    file_size_kb    INT UNSIGNED        NULL,
    uploaded_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_resources_category (category),
    INDEX idx_resources_type (resource_type)
) ENGINE=InnoDB;

-- FOCUS MUSIC (Musik Fokus)
CREATE TABLE IF NOT EXISTS focus_music (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(150)        NOT NULL,
    source_type     ENUM('external_link','licensed_audio') NOT NULL,
    url_or_path     VARCHAR(255)        NOT NULL,
    category        VARCHAR(50)         NULL,
    added_by        INT UNSIGNED        NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_focus_music_title (title)
) ENGINE=InnoDB;

-- NOTIFICATIONS (Reminders)
CREATE TABLE IF NOT EXISTS notifications (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED        NOT NULL,
    message         VARCHAR(255)        NOT NULL,
    type            VARCHAR(50)         NOT NULL DEFAULT 'general',
    is_read         TINYINT(1)          NOT NULL DEFAULT 0,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notifications_user_read (user_id, is_read)
) ENGINE=InnoDB;

-- Catatan: seed 5 achievement placeholder dari draf Phase 1 sengaja TIDAK
-- disertakan di sini. Placeholder itu tidak pernah punya logika unlock,
-- dan sudah digantikan oleh 8 achievement Fase 4 (lihat INSERT achievements
-- di bawah, dekat akhir file) yang justru dicek oleh
-- checkAndUnlockAchievements() di includes/functions.php. Menyertakan
-- keduanya akan membuat achievement "hantu" yang terkunci selamanya.

-- Seed data: musik fokus (Fase 2). Tautan pencarian YouTube (bukan video
-- spesifik) supaya tidak menyalin konten berhak cipta apa pun.
INSERT INTO focus_music (title, source_type, url_or_path, category, added_by) VALUES
('Rain Ambience',    'external_link', 'https://www.youtube.com/results?search_query=rain+sounds+for+studying',    'Rain',         NULL),
('Forest Ambience',  'external_link', 'https://www.youtube.com/results?search_query=forest+ambience+for+study',  'Forest',       NULL),
('Ocean Waves',      'external_link', 'https://www.youtube.com/results?search_query=ocean+waves+for+studying',   'Ocean',        NULL),
('Coffee Shop',      'external_link', 'https://www.youtube.com/results?search_query=coffee+shop+ambience+study', 'Cafe',         NULL),
('Lo-fi Study Beats','external_link', 'https://www.youtube.com/results?search_query=lofi+study+beats',           'Instrumental', NULL)
ON DUPLICATE KEY UPDATE title = VALUES(title);

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

SET FOREIGN_KEY_CHECKS = 1;
