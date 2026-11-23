-- Flex Mounting: contractor payments ledger (partial payments, advances, advance adjustment, revert)
-- Run after config/flex_mounting_migration.sql. Additive only; take a mysqldump before running on live.

CREATE TABLE IF NOT EXISTS `mounting_payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `entity_id` INT(11) DEFAULT NULL,
  `kind` ENUM('payment','advance','adjustment') NOT NULL COMMENT 'payment = cash against a trip; advance = cash to contractor not tied to a trip; adjustment = part of an advance applied to a trip (no new cash)',
  `trip_id` INT(11) DEFAULT NULL COMMENT 'set for payment and adjustment, NULL for advance',
  `advance_id` INT(11) DEFAULT NULL COMMENT 'for adjustment: the advance it draws from',
  `contractor_name` VARCHAR(150) NOT NULL,
  `contractor_mobile` VARCHAR(20) DEFAULT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `paid_date` DATE NOT NULL,
  `payment_mode` VARCHAR(30) DEFAULT NULL,
  `payment_ref` VARCHAR(100) DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active','reversed') NOT NULL DEFAULT 'active',
  `reversed_by` INT(11) DEFAULT NULL,
  `reversed_at` DATETIME DEFAULT NULL,
  `reverse_reason` VARCHAR(255) DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_trip` (`trip_id`, `status`),
  KEY `idx_advance` (`advance_id`, `status`),
  KEY `idx_contractor` (`contractor_name`, `status`),
  CONSTRAINT `fk_mp_trip` FOREIGN KEY (`trip_id`) REFERENCES `mounting_trips` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_mp_advance` FOREIGN KEY (`advance_id`) REFERENCES `mounting_payments` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Trip payment state is now derived from mounting_payments: pending / partial / paid
ALTER TABLE `mounting_trips`
  MODIFY `payment_status` ENUM('pending','partial','paid') NOT NULL DEFAULT 'pending';

ALTER TABLE `mounting_trips`
  ADD COLUMN IF NOT EXISTS `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `payment_status`;

-- Carry over trips already marked paid by the first version (one payment row each)
INSERT INTO `mounting_payments` (`entity_id`, `kind`, `trip_id`, `contractor_name`, `contractor_mobile`, `amount`, `paid_date`, `payment_mode`, `payment_ref`, `notes`, `created_by`)
SELECT t.entity_id, 'payment', t.id, t.contractor_name, t.contractor_mobile, t.total_amount, COALESCE(t.paid_date, CURDATE()), t.payment_mode, t.payment_ref, 'Carried over from first version', t.created_by
FROM `mounting_trips` t
WHERE t.payment_status = 'paid'
  AND NOT EXISTS (SELECT 1 FROM `mounting_payments` p WHERE p.trip_id = t.id);

UPDATE `mounting_trips` SET `paid_amount` = `total_amount` WHERE `payment_status` = 'paid' AND `paid_amount` = 0;
