-- READ-ONLY: run this first in phpMyAdmin (SQL tab) and compare with what 001 expects. It changes nothing.
SHOW CREATE TABLE `activities`;
SHOW CREATE TABLE `users`;
-- 001 expects: activities.activity_date (DATE) exists; end_date, start_time, end_time do NOT exist yet;
--              users.other_skills does NOT exist yet.
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND ((TABLE_NAME = 'activities' AND COLUMN_NAME IN ('activity_date','end_date','start_time','end_time'))
     OR (TABLE_NAME = 'users'      AND COLUMN_NAME = 'other_skills'));
