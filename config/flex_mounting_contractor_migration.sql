-- Flex Mounting: labour contractor master + brand / campaign on entries.
-- Existing contractor names on entries are copied into the master and linked. Additive only; take a mysqldump first on live.

CREATE TABLE IF NOT EXISTS `mounting_contractors` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `mobile` VARCHAR(20) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contractor_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `mounting_trips`
  ADD COLUMN IF NOT EXISTS `contractor_id` INT(11) DEFAULT NULL AFTER `trip_date`,
  ADD COLUMN IF NOT EXISTS `brand_campaign` VARCHAR(150) DEFAULT NULL AFTER `contractor_mobile`;
ALTER TABLE `mounting_trips`
  ADD INDEX IF NOT EXISTS `idx_contractor_id` (`contractor_id`);

-- Copy existing contractor names into the master (latest mobile per name) and link the entries
INSERT IGNORE INTO `mounting_contractors` (`name`, `mobile`)
SELECT TRIM(t.contractor_name),
       (SELECT x.contractor_mobile FROM `mounting_trips` x WHERE x.contractor_name = t.contractor_name AND x.contractor_mobile IS NOT NULL ORDER BY x.trip_date DESC, x.id DESC LIMIT 1)
FROM `mounting_trips` t GROUP BY TRIM(t.contractor_name);

UPDATE `mounting_trips` t JOIN `mounting_contractors` c ON c.name = TRIM(t.contractor_name)
SET t.contractor_id = c.id WHERE t.contractor_id IS NULL;
