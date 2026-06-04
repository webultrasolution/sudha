-- Consolidated SQL updates for Easy Outdoor CRM

-- 1. Fix missing columns in 'bookings' table
ALTER TABLE `bookings` 
ADD COLUMN IF NOT EXISTS `brand_name` VARCHAR(255) DEFAULT NULL AFTER `campaign_name`,
ADD COLUMN IF NOT EXISTS `external_po` VARCHAR(100) DEFAULT NULL AFTER `brand_name`,
ADD COLUMN IF NOT EXISTS `contact_person` VARCHAR(100) DEFAULT NULL AFTER `external_po`,
ADD COLUMN IF NOT EXISTS `billing_gstin` VARCHAR(15) DEFAULT NULL AFTER `contact_person`,
ADD COLUMN IF NOT EXISTS `tax_type` ENUM('igst', 'cgst_sgst') DEFAULT 'igst' AFTER `billing_gstin`,
ADD COLUMN IF NOT EXISTS `confirmation_type` VARCHAR(20) DEFAULT 'po' AFTER `status`,
ADD COLUMN IF NOT EXISTS `customer_po_no` VARCHAR(50) DEFAULT NULL AFTER `confirmation_type`,
ADD COLUMN IF NOT EXISTS `customer_po_date` DATE DEFAULT NULL AFTER `customer_po_no`,
ADD COLUMN IF NOT EXISTS `email_date` DATE DEFAULT NULL AFTER `customer_po_date`,
ADD COLUMN IF NOT EXISTS `customer_po_file` VARCHAR(255) DEFAULT NULL AFTER `email_date`,
ADD COLUMN IF NOT EXISTS `mounting_date` DATE DEFAULT NULL AFTER `created_at`;

-- 2. Fix 'payments' table columns to match code expectations
ALTER TABLE `payments` CHANGE COLUMN `entity_id` `partner_id` INT(11);
ALTER TABLE `payments` CHANGE COLUMN `reference_no` `transaction_id` VARCHAR(100);
ALTER TABLE `payments` CHANGE COLUMN `po_id` `proposal_id` INT(11);

-- 3. Fix 'approval_requests' table column 'entity_type' enum values
ALTER TABLE `approval_requests` MODIFY COLUMN `entity_type` ENUM('proposal','purchase_order','booking','invoice','client_printing','payment') NOT NULL;
UPDATE `approval_requests` SET `entity_type` = 'client_printing' WHERE `entity_type` = '' AND `entity_ref` LIKE 'CP%';
UPDATE `approval_requests` SET `entity_type` = 'payment' WHERE `entity_type` = '' AND `entity_ref` LIKE 'Payment%';

-- 4. Heal existing invoices with NULL GST values
UPDATE `invoices` i 
JOIN `bookings` b ON i.booking_id = b.id 
SET i.cgst = IF(b.tax_type = 'cgst_sgst', b.tax_amount/2, 0), 
    i.sgst = IF(b.tax_type = 'cgst_sgst', b.tax_amount/2, 0), 
    i.igst = IF(b.tax_type = 'igst', b.tax_amount, 0) 
WHERE i.cgst IS NULL AND i.sgst IS NULL AND i.igst IS NULL;


