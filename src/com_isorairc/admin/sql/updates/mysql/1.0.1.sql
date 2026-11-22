-- 1.0.1: the rating deadline is now set as a number of days before the race.
ALTER TABLE `#__isorairc_races` ADD COLUMN `deadline_days` smallint unsigned NOT NULL DEFAULT 7 AFTER `race_date`;
