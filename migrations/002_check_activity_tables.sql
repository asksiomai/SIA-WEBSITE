-- READ-ONLY. Run in phpMyAdmin (SQL tab). Changes nothing. Shows what the Create Activities page depends on.
SHOW CREATE TABLE `activity_requirements`;
SHOW CREATE TABLE `activities`;
SHOW CREATE TABLE `skills`;
-- Expected: activities has end_date, start_time, end_time (run 001 if not);
--           activity_requirements has activity_id, skill_id, required_level (+ a primary key; its name no longer matters).
SELECT COLUMN_NAME FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activities' AND COLUMN_NAME IN ('end_date','start_time','end_time');
