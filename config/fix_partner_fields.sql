-- Add missing Company/Vendor fields per CRS
ALTER TABLE partners 
ADD COLUMN city VARCHAR(100) AFTER address,
ADD COLUMN state VARCHAR(100) AFTER city,
ADD COLUMN billing_address TEXT AFTER state;
