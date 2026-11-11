-- CVMS final update: activity schedule (end date, start time, end time) + volunteers' "Other Skills".
--
-- SAFE: only ADDS columns and fills the new end_date from the existing date. Nothing is dropped, renamed or deleted.
--       It can be run twice: a column that already exists is skipped.
-- BEFORE RUNNING: make a backup (cPanel > phpMyAdmin > Export, or cPanel > Backup). Select the CVMS database first.
-- RUN THIS BEFORE deploying the new code (the new pages read and write these columns).
--
-- The existing column activities.activity_date is KEPT and now means "Start date" (no rename, no data copied around).

-- activities.end_date
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activities' AND COLUMN_NAME = 'end_date') = 0,
  'ALTER TABLE `activities` ADD COLUMN `end_date` DATE NULL', 'SELECT ''activities.end_date already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- activities.start_time
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activities' AND COLUMN_NAME = 'start_time') = 0,
  'ALTER TABLE `activities` ADD COLUMN `start_time` TIME NULL', 'SELECT ''activities.start_time already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- activities.end_time
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activities' AND COLUMN_NAME = 'end_time') = 0,
  'ALTER TABLE `activities` ADD COLUMN `end_time` TIME NULL', 'SELECT ''activities.end_time already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- users.other_skills (free text, separate from the official skills tables)
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'other_skills') = 0,
  'ALTER TABLE `users` ADD COLUMN `other_skills` TEXT NULL', 'SELECT ''users.other_skills already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Existing activities: they only had one date, so they become one-day activities (end date = start date).
-- Only rows that have no end date yet are touched, so re-running this does nothing new.
UPDATE `activities` SET `end_date` = `activity_date` WHERE `end_date` IS NULL AND `activity_date` IS NOT NULL;

-- Start time / end time of OLD activities were never recorded, so they stay empty (the site shows "Time not set").
-- This list is what still needs a manual update: open each one in Edit Activity and enter the times
-- (and a different End Date if it ran for several days).
SELECT `activity_id`, `activity_name`, `activity_date` AS start_date, `end_date`, `start_time`, `end_time`
  FROM `activities`
 WHERE `start_time` IS NULL OR `end_time` IS NULL
 ORDER BY `activity_date`;
