-- Each race remembers which entry form it reads, so that a new season's form does not change last season's lists.
ALTER TABLE `#__isorairc_races` ADD COLUMN `form_id` int unsigned NULL DEFAULT NULL;
