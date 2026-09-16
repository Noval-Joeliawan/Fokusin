-- Fokusin — Migration 003: perbaikan integritas Phase 2
-- Untuk database Phase 2 yang sudah terlanjur dibuat.

USE fokusin_db;

ALTER TABLE tasks
    ADD COLUMN completion_rewarded TINYINT(1) NOT NULL DEFAULT 0 AFTER completed_at;

UPDATE tasks
SET completion_rewarded = 1
WHERE status = 'selesai';

-- Jika ada target mingguan ganda, sisakan ID paling kecil sebelum menambah UNIQUE.
DELETE t1 FROM study_targets t1
INNER JOIN study_targets t2
    ON t1.user_id = t2.user_id
   AND t1.week_start_date = t2.week_start_date
   AND t1.id > t2.id;

ALTER TABLE study_targets
    ADD UNIQUE KEY uniq_targets_user_week (user_id, week_start_date);
