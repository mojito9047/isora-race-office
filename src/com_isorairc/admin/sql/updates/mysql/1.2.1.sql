-- 1.2.1: update files rewritten for Joomla's database checker; import index renamed.
ALTER TABLE `#__isorairc_imports` DROP KEY `idx_imported`;
ALTER TABLE `#__isorairc_imports` ADD INDEX `idx_scheme_imported` (`scheme`, `imported_at`);
