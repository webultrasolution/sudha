-- Update Sites Table
ALTER TABLE sites 
ADD COLUMN facing VARCHAR(50) AFTER height,
ADD COLUMN light_type ENUM('BL', 'NL', 'FL') DEFAULT 'NL' AFTER facing,
ADD COLUMN grade ENUM('A', 'B', 'C') DEFAULT 'B' AFTER light_type,
ADD COLUMN available_from DATE AFTER status,
ADD COLUMN mounting_hsn VARCHAR(50) DEFAULT NULL AFTER hsn_code;

-- Update Proposals Table
ALTER TABLE proposals
ADD COLUMN discounting_pct DECIMAL(5,2) DEFAULT 0.00,
ADD COLUMN pricing_pct DECIMAL(5,2) DEFAULT 0.00,
ADD COLUMN printing_cost DECIMAL(15,2) DEFAULT 0.00,
ADD COLUMN mounting_cost DECIMAL(15,2) DEFAULT 0.00,
ADD COLUMN ha_markup_amount DECIMAL(15,2) DEFAULT 0.00,
ADD COLUMN ta_markup_amount DECIMAL(15,2) DEFAULT 0.00,
ADD COLUMN total_sqft FLOAT DEFAULT 0,
ADD COLUMN price_per_sqft DECIMAL(15,2) DEFAULT 0.00,
ADD COLUMN display_cost DECIMAL(15,2) DEFAULT 0.00;

-- Purchase Orders Table
CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id VARCHAR(50),
    campaign_id INT,
    vendor_id INT,
    customer_id INT,
    employee_id INT,
    type ENUM('rental', 'printing', 'adhoc') DEFAULT 'rental',
    po_number VARCHAR(50) UNIQUE,
    po_date DATE,
    payment_due_date DATE,
    po_amount DECIMAL(15,2),
    cgst_amount DECIMAL(15,2) DEFAULT 0,
    sgst_amount DECIMAL(15,2) DEFAULT 0,
    igst_amount DECIMAL(15,2) DEFAULT 0,
    total_amount DECIMAL(15,2),
    status ENUM('draft', 'approved', 'paid') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES partners(id),
    FOREIGN KEY (customer_id) REFERENCES partners(id),
    FOREIGN KEY (employee_id) REFERENCES users(id)
);

-- PO Items Table
CREATE TABLE IF NOT EXISTS po_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT,
    description TEXT,
    site_id INT,
    start_date DATE,
    end_date DATE,
    days INT,
    monthly_rate DECIMAL(15,2),
    cost DECIMAL(15,2),
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (site_id) REFERENCES sites(id)
);

-- Payments Table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('receivable', 'payable'),
    entity_id INT, -- partner_id
    invoice_id INT NULL,
    po_id INT NULL,
    amount DECIMAL(15,2),
    payment_mode VARCHAR(50),
    payment_date DATE,
    reference_no VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (entity_id) REFERENCES partners(id)
);

-- Activity Log Table
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255),
    entity_type VARCHAR(50),
    entity_id INT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
