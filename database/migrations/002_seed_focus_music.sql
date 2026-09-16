-- ============================================================
-- Fokusin — Migration 002 (Fase 2)
-- Menambahkan constraint UNIQUE pada focus_music.title (supaya re-run
-- migration ini aman/idempotent) dan mengisi data awal.
-- Tidak mengubah kolom atau tabel lain.
--
-- Catatan penting soal hak cipta:
-- Baris di bawah ini adalah tautan PENCARIAN YouTube (bukan video
-- spesifik), sehingga tidak menyalin/menyimpan konten berhak cipta apa
-- pun — pengguna diarahkan untuk memilih sendiri video yang mereka
-- suka. Admin bisa mengganti ke tautan yang lebih spesifik nanti lewat
-- Admin Panel (Fase 6) tanpa mengubah struktur tabel ini.
-- ============================================================

USE fokusin_db;

ALTER TABLE focus_music ADD UNIQUE KEY uniq_focus_music_title (title);

INSERT INTO focus_music (title, source_type, url_or_path, category, added_by) VALUES
('Rain Ambience',    'external_link', 'https://www.youtube.com/results?search_query=rain+sounds+for+studying',    'Rain',         NULL),
('Forest Ambience',  'external_link', 'https://www.youtube.com/results?search_query=forest+ambience+for+study',  'Forest',       NULL),
('Ocean Waves',      'external_link', 'https://www.youtube.com/results?search_query=ocean+waves+for+studying',   'Ocean',        NULL),
('Coffee Shop',      'external_link', 'https://www.youtube.com/results?search_query=coffee+shop+ambience+study', 'Cafe',         NULL),
('Lo-fi Study Beats','external_link', 'https://www.youtube.com/results?search_query=lofi+study+beats',           'Instrumental', NULL)
ON DUPLICATE KEY UPDATE title = VALUES(title);
