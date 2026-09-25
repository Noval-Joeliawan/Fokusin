-- ============================================================
-- Fokusin — Migration 006 (Fase 5)
-- Resource Library + pengelolaan Musik Fokus.
--
-- Tabel `resources` sudah ada sejak Fase 1 (dirancang untuk file pribadi
-- per user). Migration ini MEMPERLUAS tabel yang sama — bukan membuat
-- tabel baru — supaya bisa juga menyimpan resource bertipe link eksternal
-- dengan metadata (judul, deskripsi, kategori), sesuai instruksi untuk
-- tidak menduplikasi struktur yang sudah ada.
--
-- `focus_music` TIDAK diubah sama sekali — kolomnya (title, source_type,
-- url_or_path, category, added_by) sudah cukup untuk fitur kelola musik
-- di Fase 5; hanya kode PHP yang baru (focus_music.php).
--
-- Idempotent: aman dijalankan berulang kali.
-- ============================================================

USE fokusin_db;

-- ------------------------------------------------------------
-- RESOURCES: tambah metadata + dukung tipe 'link' (tanpa file).
-- ------------------------------------------------------------
ALTER TABLE resources
    ADD COLUMN IF NOT EXISTS title VARCHAR(150) NULL AFTER user_id,
    ADD COLUMN IF NOT EXISTS description TEXT NULL AFTER title,
    ADD COLUMN IF NOT EXISTS category VARCHAR(60) NULL AFTER description,
    ADD COLUMN IF NOT EXISTS resource_type ENUM('file','link') NOT NULL DEFAULT 'file' AFTER category,
    ADD COLUMN IF NOT EXISTS external_url VARCHAR(255) NULL AFTER resource_type;

-- file_name/file_path dulu NOT NULL (semua resource pasti file). Sekarang
-- resource bertipe 'link' tidak punya file sama sekali, jadi kolom ini
-- harus boleh NULL. MODIFY COLUMN aman dijalankan berulang (idempotent).
ALTER TABLE resources MODIFY COLUMN file_name VARCHAR(255) NULL;
ALTER TABLE resources MODIFY COLUMN file_path VARCHAR(255) NULL;

ALTER TABLE resources
    ADD INDEX IF NOT EXISTS idx_resources_category (category),
    ADD INDEX IF NOT EXISTS idx_resources_type (resource_type);

-- Data lama (upload pribadi Fase 1) belum punya title -- isi dari nama
-- file aslinya supaya tetap tampil dengan baik di UI baru. Tidak ada
-- data yang dihapus atau ditimpa selain mengisi kolom yang sebelumnya
-- kosong.
UPDATE resources SET title = file_name WHERE title IS NULL AND file_name IS NOT NULL;
