-- 1.7.0: a boat can choose its secondary IRC certificate for a race.
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
