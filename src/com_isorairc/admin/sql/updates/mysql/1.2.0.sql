-- 1.2.0: the second rating system is called YTC (it was labelled NHC).
UPDATE `#__isorairc_ratings` SET `scheme` = 'ytc' WHERE `scheme` = 'nhc';

UPDATE `#__isorairc_snapshot_ratings` SET `scheme` = 'ytc' WHERE `scheme` = 'nhc';

UPDATE `#__isorairc_race_snapshots` SET `scheme` = 'ytc' WHERE `scheme` = 'nhc';

UPDATE `#__isorairc_imports` SET `scheme` = 'ytc' WHERE `scheme` = 'nhc';
