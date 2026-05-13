-- SQL to fix missing columns in bookings table
ALTER TABLE `bookings` 
ADD COLUMN `customer_po_date` DATE DEFAULT NULL AFTER `customer_po_no`,
ADD COLUMN `email_date` DATE DEFAULT NULL AFTER `customer_po_date`,
ADD COLUMN `customer_po_file` VARCHAR(255) DEFAULT NULL AFTER `email_date`,

