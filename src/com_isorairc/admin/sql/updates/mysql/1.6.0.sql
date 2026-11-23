-- 1.6.0: more of the IRC certificate is kept: sail counts and the SSS base value.
ALTER TABLE `#__isorairc_ratings` ADD COLUMN `headsails` smallint NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_ratings` ADD COLUMN `flying_headsails` smallint NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_ratings` ADD COLUMN `spinnakers` smallint NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_ratings` ADD COLUMN `sss_base` smallint NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_snapshot_ratings` ADD COLUMN `headsails` smallint NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_snapshot_ratings` ADD COLUMN `flying_headsails` smallint NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_snapshot_ratings` ADD COLUMN `spinnakers` smallint NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_snapshot_ratings` ADD COLUMN `sss_base` smallint NULL DEFAULT NULL;
