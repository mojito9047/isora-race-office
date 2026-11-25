-- Current ratings: each rating system's rows are replaced on every import.
-- tcc is the main rating and non_spi_tcc the non-spinnaker / white sail rating.
CREATE TABLE IF NOT EXISTS `#__isorairc_ratings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `scheme` varchar(10) NOT NULL DEFAULT 'irc',
  `boat_name` varchar(190) NOT NULL,
  `sail_no` varchar(190) DEFAULT NULL,
  `cert_no` varchar(190) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `cert_year` smallint DEFAULT NULL,
  `tcc` decimal(9,3) NOT NULL,
  `endorsed` varchar(190) DEFAULT NULL,
  `secondary` varchar(190) DEFAULT NULL,
  `non_spi_tcc` decimal(9,3) DEFAULT NULL,
  `crew` smallint DEFAULT NULL,
  `valid_code` varchar(190) DEFAULT NULL,
  `boat_type` varchar(190) DEFAULT NULL,
  `club` varchar(190) DEFAULT NULL,
  `provisional` varchar(190) DEFAULT NULL,
  `headsails` smallint DEFAULT NULL,
  `flying_headsails` smallint DEFAULT NULL,
  `spinnakers` smallint DEFAULT NULL,
  `sss_base` smallint DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_boat` (`boat_name`),
  KEY `idx_sail` (`sail_no`),
  KEY `idx_scheme` (`scheme`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- Frozen ratings per race and rating system, written once the rating deadline has passed.
CREATE TABLE IF NOT EXISTS `#__isorairc_snapshot_ratings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `race_id` int unsigned NOT NULL,
  `scheme` varchar(10) NOT NULL DEFAULT 'irc',
  `boat_name` varchar(190) NOT NULL,
  `sail_no` varchar(190) DEFAULT NULL,
  `cert_no` varchar(190) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `cert_year` smallint DEFAULT NULL,
  `tcc` decimal(9,3) NOT NULL,
  `endorsed` varchar(190) DEFAULT NULL,
  `secondary` varchar(190) DEFAULT NULL,
  `non_spi_tcc` decimal(9,3) DEFAULT NULL,
  `crew` smallint DEFAULT NULL,
  `valid_code` varchar(190) DEFAULT NULL,
  `boat_type` varchar(190) DEFAULT NULL,
  `club` varchar(190) DEFAULT NULL,
  `provisional` varchar(190) DEFAULT NULL,
  `headsails` smallint DEFAULT NULL,
  `flying_headsails` smallint DEFAULT NULL,
  `spinnakers` smallint DEFAULT NULL,
  `sss_base` smallint DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_race_boat` (`race_id`, `boat_name`),
  KEY `idx_race_sail` (`race_id`, `sail_no`),
  KEY `idx_race_scheme` (`race_id`, `scheme`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- Races and their rating deadlines (all datetimes UTC).
CREATE TABLE IF NOT EXISTS `#__isorairc_races` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(190) NOT NULL,
  `race_date` date NOT NULL,
  `deadline_days` smallint unsigned NOT NULL DEFAULT 7,
  `rating_deadline` datetime NOT NULL,
  `entry_field` varchar(190) DEFAULT NULL,
  `form_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_deadline` (`rating_deadline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- One row per race and rating system once its snapshot has been taken.
CREATE TABLE IF NOT EXISTS `#__isorairc_race_snapshots` (
  `race_id` int unsigned NOT NULL,
  `scheme` varchar(10) NOT NULL,
  `taken` datetime NOT NULL,
  `import_id` int unsigned DEFAULT NULL,
  `boat_count` int unsigned NOT NULL DEFAULT 0,
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`race_id`, `scheme`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- Import log and archive. csv_gz holds the gzipped, base64 encoded feed;
-- when a download is identical to the previous one only content_id is set.
CREATE TABLE IF NOT EXISTS `#__isorairc_imports` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `scheme` varchar(10) NOT NULL DEFAULT 'irc',
  `imported_at` datetime NOT NULL,
  `feed_modified` datetime DEFAULT NULL,
  `row_count` int unsigned NOT NULL DEFAULT 0,
  `csv_hash` char(40) NOT NULL,
  `content_id` int unsigned DEFAULT NULL,
  `csv_gz` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_scheme_imported` (`scheme`, `imported_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- A boat's choice of IRC certificate for one race. A boat is on its primary
-- certificate unless it has a row here. boat_key is the boat's sail number and
-- name reduced to capitals and digits ("GBR4822R|MOJITO"), as entries are matched.
CREATE TABLE IF NOT EXISTS `#__isorairc_cert_choices` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `race_id` int unsigned NOT NULL,
  `boat_key` varchar(190) NOT NULL,
  `certificate` varchar(10) NOT NULL DEFAULT 'secondary',
  `set_at` datetime NOT NULL,
  `set_by` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_race_boat` (`race_id`, `boat_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
