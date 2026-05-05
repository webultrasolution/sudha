-- Create Campaigns Table
CREATE TABLE IF NOT EXISTS campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id VARCHAR(50) UNIQUE,
    booking_id INT,
    client_id INT,
    employee_id INT,
    display_name VARCHAR(255),
    from_date DATE,
    to_date DATE,
    days INT,
    sqft FLOAT,
    amount DECIMAL(15,2),
    qos_pct DECIMAL(5,2) DEFAULT 100.00,
    status ENUM('planned', 'approved', 'running', 'completed') DEFAULT 'planned',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (client_id) REFERENCES partners(id),
    FOREIGN KEY (employee_id) REFERENCES users(id)
);

-- Seed some dummy data for the dashboard if needed, 
-- or just fix the missing table so the queries work.
