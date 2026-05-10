ALTER TABLE booking_items 
ADD COLUMN purchase_rate DECIMAL(15,2) AFTER site_id,
ADD COLUMN purchase_amount DECIMAL(15,2) AFTER amount;
