-- 1.1.0: a second rating system alongside IRC; snapshots are tracked per race and system.
-- One change per statement so Joomla's database checker can verify each of them.
ALTER TABLE `#__isorairc_ratings` ADD COLUMN `scheme` varchar(10) NOT NULL DEFAULT 'irc' AFTER `id`;
ALTER TABLE `#__isorairc_ratings` ADD COLUMN `boat_type` varchar(190) NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_ratings` ADD COLUMN `club` varchar(190) NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_ratings` ADD COLUMN `provisional` varchar(190) NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_ratings` MODIFY `tcc` decimal(9,3) NOT NULL;
ALTER TABLE `#__isorairc_ratings` MODIFY `non_spi_tcc` decimal(9,3) NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_snapshot_ratings` ADD COLUMN `scheme` varchar(10) NOT NULL DEFAULT 'irc' AFTER `race_id`;
ALTER TABLE `#__isorairc_snapshot_ratings` ADD COLUMN `boat_type` varchar(190) NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_snapshot_ratings` ADD COLUMN `club` varchar(190) NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_snapshot_ratings` ADD COLUMN `provisional` varchar(190) NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_snapshot_ratings` MODIFY `tcc` decimal(9,3) NOT NULL;
ALTER TABLE `#__isorairc_snapshot_ratings` MODIFY `non_spi_tcc` decimal(9,3) NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_ratings` ADD INDEX `idx_scheme` (`scheme`);
ALTER TABLE `#__isorairc_snapshot_ratings` ADD INDEX `idx_race_scheme` (`race_id`, `scheme`);
ALTER TABLE `#__isorairc_imports` ADD COLUMN `scheme` varchar(10) NOT NULL DEFAULT 'irc' AFTER `id`;

CREATE TABLE IF NOT EXISTS `#__isorairc_race_snapshots` (
  `race_id` int unsigned NOT NULL,
  `scheme` varchar(10) NOT NULL,
  `taken` datetime NOT NULL,
  `import_id` int unsigned DEFAULT NULL,
  `boat_count` int unsigned NOT NULL DEFAULT 0,
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`race_id`, `scheme`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

INSERT INTO `#__isorairc_race_snapshots` (`race_id`, `scheme`, `taken`, `import_id`, `boat_count`, `note`) SELECT `id`, 'irc', `snapshot_taken`, `snapshot_import_id`, IFNULL(`snapshot_count`, 0), `snapshot_note` FROM `#__isorairc_races` WHERE `snapshot_taken` IS NOT NULL;

ALTER TABLE `#__isorairc_races` DROP COLUMN `snapshot_taken`;
ALTER TABLE `#__isorairc_races` DROP COLUMN `snapshot_import_id`;
ALTER TABLE `#__isorairc_races` DROP COLUMN `snapshot_count`;
ALTER TABLE `#__isorairc_races` DROP COLUMN `snapshot_note`;
