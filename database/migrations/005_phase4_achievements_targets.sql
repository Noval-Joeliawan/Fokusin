-- Fokusin Phase 4: XP, Achievement, Target Mingguan
SET NAMES utf8mb4;
USE fokusin_db;

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

-- PERBAIKAN BUG: 5 achievement placeholder dari draf Phase 1
-- ('first_step', 'seven_days', 'bookworm', 'focus_master', 'task_crusher')
-- tidak pernah punya logika unlock di kode manapun --
-- checkAndUnlockAchievements() di includes/functions.php hanya mengenali
-- 8 kode di atas. Akibatnya kelima achievement lama itu akan TERKUNCI
-- SELAMANYA dan tampil berdampingan dengan achievement baru yang mirip
-- (mis. "7 Hari" terkunci permanen di sebelah "Konsisten 7 Hari" yang
-- berfungsi normal) -- membingungkan pengguna. Karena tidak pernah ada
-- logika unlock untuk kode-kode lama ini, tidak mungkin ada baris
-- user_achievements yang mengacu padanya, sehingga aman dihapus tanpa
-- kehilangan progres unlock milik siapa pun.
DELETE FROM achievements WHERE code IN ('first_step', 'seven_days', 'bookworm', 'focus_master', 'task_crusher');

-- study_targets sudah memiliki UNIQUE(user_id, week_start_date) sejak
-- perbaikan Phase 2 (lihat 003_phase2_integrity_fixes.sql). Upgrade
-- database lama yang entah bagaimana belum memiliki constraint ini
-- dilakukan secara aman di bawah (idempotent -- aman dijalankan berulang).
SET @has_target_unique := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'study_targets' AND index_name = 'uniq_targets_user_week');
SET @sql := IF(@has_target_unique = 0, 'ALTER TABLE study_targets ADD UNIQUE KEY uniq_targets_user_week (user_id, week_start_date)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
