CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'sales', 'operations', 'accounts') NOT NULL,
    full_name VARCHAR(100),
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS partners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('client', 'vendor') NOT NULL,
    name VARCHAR(255) NOT NULL,
    gstin VARCHAR(15),
    pan VARCHAR(10),
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_code VARCHAR(50) UNIQUE,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255),
    city VARCHAR(100),
    type VARCHAR(50),
    width FLOAT,
    height FLOAT,
    sqft FLOAT AS (width * height) STORED,
    owner_type ENUM('HA', 'TA') DEFAULT 'HA',
    vendor_id INT,
    card_rate DECIMAL(10, 2),
    purchase_rate DECIMAL(10, 2),
    status ENUM('available', 'booked', 'maintenance') DEFAULT 'available',
    image_url VARCHAR(255),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    FOREIGN KEY (vendor_id) REFERENCES partners(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS proposals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proposal_number VARCHAR(50) UNIQUE,
    client_id INT NOT NULL,
    start_date DATE,
    end_date DATE,
    total_amount DECIMAL(15, 2),
    tax_amount DECIMAL(15, 2),
    grand_total DECIMAL(15, 2),
    status ENUM('draft', 'sent', 'confirmed', 'cancelled') DEFAULT 'draft',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES partners(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS proposal_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id INT NOT NULL,
    site_id INT NOT NULL,
    sale_rate DECIMAL(10, 2),
    purchase_rate DECIMAL(10, 2),
    margin_pct DECIMAL(5, 2),
    days INT,
    amount DECIMAL(15, 2),
    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
    FOREIGN KEY (site_id) REFERENCES sites(id)
);

CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id INT NOT NULL,
    status ENUM('pending', 'mounting', 'active', 'completed') DEFAULT 'pending',
    mounting_date DATE,
    completion_date DATE,
    FOREIGN KEY (proposal_id) REFERENCES proposals(id)
);

CREATE TABLE IF NOT EXISTS operations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    site_id INT NOT NULL,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    field_team_notes TEXT,
    proof_image VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (site_id) REFERENCES sites(id)
);

CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE,
    booking_id INT NOT NULL,
    type ENUM('tax', 'proforma', 'estimate') DEFAULT 'tax',
    sub_total DECIMAL(15, 2),
    cgst DECIMAL(15, 2),
    sgst DECIMAL(15, 2),
    igst DECIMAL(15, 2),
    total_amount DECIMAL(15, 2),
    payment_status ENUM('unpaid', 'partially_paid', 'paid') DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id)
);
