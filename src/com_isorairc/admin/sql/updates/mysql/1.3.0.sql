-- 1.3.0: a race can be linked to its yes/no question on the entry form.
ALTER TABLE `#__isorairc_races` ADD COLUMN `entry_field` varchar(190) NULL DEFAULT NULL;
