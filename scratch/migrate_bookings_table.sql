ALTER TABLE bookings 
ADD COLUMN client_id INT AFTER proposal_id,
ADD COLUMN start_date DATE AFTER client_id,
ADD COLUMN end_date DATE AFTER start_date,
ADD COLUMN total_amount DECIMAL(15,2) AFTER end_date,
ADD COLUMN tax_amount DECIMAL(15,2) AFTER total_amount,
ADD COLUMN grand_total DECIMAL(15,2) AFTER tax_amount,
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Optional: If you want to link back to client
-- ALTER TABLE bookings ADD CONSTRAINT fk_booking_client FOREIGN KEY (client_id) REFERENCES partners(id);
