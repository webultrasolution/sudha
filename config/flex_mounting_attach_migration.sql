-- Flex Mounting: mounting date on entries, attachment (receipt / screenshot) on payments and advances.
-- Additive only; take a mysqldump before running on live.
ALTER TABLE `mounting_trips`
  ADD COLUMN IF NOT EXISTS `mounting_date` DATE DEFAULT NULL COMMENT 'Day the flex was actually mounted' AFTER `misc_amount`;
ALTER TABLE `mounting_payments`
  ADD COLUMN IF NOT EXISTS `attachment` VARCHAR(255) DEFAULT NULL COMMENT 'File in uploads/mounting_payments/' AFTER `notes`;
