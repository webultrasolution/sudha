-- Flex Mounting: staff payments need admin approval (admin payments are approved directly).
-- Existing payments stay approved. Additive only; take a mysqldump before running on live.
ALTER TABLE `mounting_payments`
  ADD COLUMN IF NOT EXISTS `approval_status` ENUM('pending_approval','approved','rejected') NOT NULL DEFAULT 'approved' AFTER `status`,
  ADD COLUMN IF NOT EXISTS `approved_by` INT(11) DEFAULT NULL AFTER `approval_status`,
  ADD COLUMN IF NOT EXISTS `approved_at` DATETIME DEFAULT NULL AFTER `approved_by`,
  ADD COLUMN IF NOT EXISTS `rejection_reason` VARCHAR(255) DEFAULT NULL AFTER `approved_at`;

ALTER TABLE `approval_requests`
  MODIFY `entity_type` ENUM('proposal','purchase_order','booking','invoice','client_printing','client_mounting','payment','vendor_invoice','mounting_trip','mounting_payment') NOT NULL;
