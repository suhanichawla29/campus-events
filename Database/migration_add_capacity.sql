ALTER TABLE `events`
    ADD COLUMN `capacity` INT UNSIGNED NOT NULL DEFAULT 50 AFTER `image`;

ALTER TABLE `registrations`
    ADD UNIQUE KEY `unique_registration` (`user_id`, `event_id`);
