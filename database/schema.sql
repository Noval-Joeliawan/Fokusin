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
    title           VARCHAR(150)        NOT NULL,
    content         TEXT                NULL,
    category        VARCHAR(50)         NOT NULL DEFAULT 'Other',
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notes_user (user_id)
) ENGINE=InnoDB;

-- MATERIALS (Materi Belajar)
CREATE TABLE IF NOT EXISTS materials (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_id      INT UNSIGNED        NULL,
    title           VARCHAR(150)        NOT NULL,
    description     TEXT                NULL,
    content         LONGTEXT            NULL,
    external_url    VARCHAR(255)        NULL,
    created_by      INT UNSIGNED        NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
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
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quiz_questions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id         INT UNSIGNED        NOT NULL,
    question_text   TEXT                NOT NULL,
    order_index     INT UNSIGNED        NOT NULL DEFAULT 0,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quiz_options (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id     INT UNSIGNED        NOT NULL,
    option_text     VARCHAR(255)        NOT NULL,
    is_correct      TINYINT(1)          NOT NULL DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
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

-- RESOURCES (File Pribadi)
CREATE TABLE IF NOT EXISTS resources (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED        NOT NULL,
    file_name       VARCHAR(255)        NOT NULL,
    file_path       VARCHAR(255)        NOT NULL,
    file_type       VARCHAR(50)         NULL,
    file_size_kb    INT UNSIGNED        NULL,
    uploaded_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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

-- Seed data: default achievements (used starting Phase 4)
INSERT INTO achievements (code, name, description, icon) VALUES
('first_step', 'Langkah Pertama', 'Selesaikan sesi belajar pertamamu.', '🥇'),
('seven_days', '7 Hari', 'Pertahankan streak belajar 7 hari.', '🔥'),
('bookworm', 'Kutu Buku', 'Selesaikan 10 materi belajar.', '📚'),
('focus_master', 'Master Fokus', 'Selesaikan 20 sesi fokus.', '🧠'),
('task_crusher', 'Penakluk Tugas', 'Selesaikan 50 tugas.', '🎯')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Seed data: musik fokus (Fase 2). Tautan pencarian YouTube (bukan video
-- spesifik) supaya tidak menyalin konten berhak cipta apa pun.
INSERT INTO focus_music (title, source_type, url_or_path, category, added_by) VALUES
('Rain Ambience',    'external_link', 'https://www.youtube.com/results?search_query=rain+sounds+for+studying',    'Rain',         NULL),
('Forest Ambience',  'external_link', 'https://www.youtube.com/results?search_query=forest+ambience+for+study',  'Forest',       NULL),
('Ocean Waves',      'external_link', 'https://www.youtube.com/results?search_query=ocean+waves+for+studying',   'Ocean',        NULL),
('Coffee Shop',      'external_link', 'https://www.youtube.com/results?search_query=coffee+shop+ambience+study', 'Cafe',         NULL),
('Lo-fi Study Beats','external_link', 'https://www.youtube.com/results?search_query=lofi+study+beats',           'Instrumental', NULL)
ON DUPLICATE KEY UPDATE title = VALUES(title);

SET FOREIGN_KEY_CHECKS = 1;
