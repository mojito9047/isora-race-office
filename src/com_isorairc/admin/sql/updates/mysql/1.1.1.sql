-- 1.1.1: text columns widened; some sail numbers are longer than 40 characters.
ALTER TABLE `#__isorairc_ratings` MODIFY `sail_no` varchar(190) NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_ratings` MODIFY `cert_no` varchar(190) NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_ratings` MODIFY `endorsed` varchar(190) NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_ratings` MODIFY `secondary` varchar(190) NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_ratings` MODIFY `valid_code` varchar(190) NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_ratings` MODIFY `provisional` varchar(190) NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_snapshot_ratings` MODIFY `sail_no` varchar(190) NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_snapshot_ratings` MODIFY `cert_no` varchar(190) NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_snapshot_ratings` MODIFY `endorsed` varchar(190) NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_snapshot_ratings` MODIFY `secondary` varchar(190) NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_snapshot_ratings` MODIFY `valid_code` varchar(190) NULL DEFAULT NULL;
ALTER TABLE `#__isorairc_snapshot_ratings` MODIFY `provisional` varchar(190) NULL DEFAULT NULL;
