-- ============================================================
-- Migration: Event Capacity & Available Seats
-- Database: campus_events_db
-- Run this ONCE in phpMyAdmin  >  select `campus_events_db`  >  SQL tab
-- ============================================================

-- 1) Add the capacity column.
--    INT UNSIGNED = positive whole numbers only (no negatives stored).
--    Existing events automatically get a default of 50 seats.
ALTER TABLE `events`
    ADD COLUMN `capacity` INT UNSIGNED NOT NULL DEFAULT 50 AFTER `image`;

-- 2) Prevent duplicate registrations: one row per (user_id, event_id).
--    NOTE: your original dump file already contains this key
--    (`unique_registration`). If phpMyAdmin answers
--    "#1062 / Duplicate key name 'unique_registration'" it means it is
--    already in place - just skip this statement and run only #1.
ALTER TABLE `registrations`
    ADD UNIQUE KEY `unique_registration` (`user_id`, `event_id`);
