-- Flex Mounting Register cum Contractor Payment
-- One trip = one labour contractor visit (date, food/travel/misc) covering one or more sites.
-- Labour = sqft x rate per site; trip overheads (food+travel+misc) are split across sites by sqft.
-- Only approved trips count as site expense (site P&L).
-- Safe to re-run: only CREATE IF NOT EXISTS / additive changes. Take a mysqldump before running on live.

CREATE TABLE IF NOT EXISTS `mounting_trips` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `entity_id` INT(11) DEFAULT NULL,
  `trip_number` VARCHAR(50) NOT NULL,
  `trip_date` DATE NOT NULL,
  `contractor_name` VARCHAR(150) NOT NULL,
  `contractor_mobile` VARCHAR(20) DEFAULT NULL,
  `rate_per_sqft` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_sqft` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `labour_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `food_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `travel_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `misc_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `description` TEXT DEFAULT NULL,
  `approval_status` ENUM('pending_approval','approved','rejected') NOT NULL DEFAULT 'pending_approval',
  `approved_by` INT(11) DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `rejection_reason` VARCHAR(255) DEFAULT NULL,
  `payment_status` ENUM('pending','paid') NOT NULL DEFAULT 'pending',
  `paid_date` DATE DEFAULT NULL,
  `payment_mode` VARCHAR(30) DEFAULT NULL,
  `payment_ref` VARCHAR(100) DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trip_number` (`trip_number`),
  KEY `idx_trip_date` (`trip_date`),
  KEY `idx_contractor` (`contractor_name`),
  KEY `idx_entity` (`entity_id`),
  KEY `idx_status` (`approval_status`, `payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `mounting_trip_sites` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `trip_id` INT(11) NOT NULL,
  `site_id` INT(11) NOT NULL,
  `sqft` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `labour_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `overhead_share` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `remarks` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_trip` (`trip_id`),
  KEY `idx_site` (`site_id`),
  CONSTRAINT `fk_mts_trip` FOREIGN KEY (`trip_id`) REFERENCES `mounting_trips` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mts_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Allow trips in the central approval queue
ALTER TABLE `approval_requests`
  MODIFY `entity_type` ENUM('proposal','purchase_order','booking','invoice','client_printing','client_mounting','payment','vendor_invoice','mounting_trip') NOT NULL;

-- Trip number series: FMR/26-27/0001
INSERT INTO `document_sequences` (`module_key`, `financial_year`, `prefix`, `next_value`, `padding`)
SELECT 'flex_mounting', '26-27', 'FMR/{FY}/', 1, 4
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `document_sequences` WHERE `module_key` = 'flex_mounting');
