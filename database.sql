-- easy-outdoor-crm Database Schema
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- 1. Users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','sales','operations','accounts') NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Partners (Clients & Vendors)
CREATE TABLE IF NOT EXISTS `partners` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `type` enum('client','vendor') NOT NULL,
  `name` varchar(255) NOT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `pan` varchar(10) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `additional_gst` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Inventory (Sites)
CREATE TABLE IF NOT EXISTS `sites` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `site_code` varchar(50) DEFAULT NULL UNIQUE,
  `name` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `area` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `width` float DEFAULT NULL,
  `height` float DEFAULT NULL,
  `sqft` float GENERATED ALWAYS AS (`width` * `height`) STORED,
  `facing` varchar(50) DEFAULT NULL,
  `light_type` enum('NL','BL','FL') DEFAULT 'NL',
  `hsn_code` varchar(10) DEFAULT '998366',
  `grade` enum('A','B','C') DEFAULT 'B',
  `owner_type` enum('HA','TA') DEFAULT 'HA',
  `vendor_id` int(11) DEFAULT NULL,
  `vendor_gst` varchar(20) DEFAULT NULL,
  `card_rate` decimal(15,2) DEFAULT NULL,
  `purchase_rate` decimal(15,2) DEFAULT NULL,
  `available_from` date DEFAULT NULL,
  `status` enum('available','booked','maintenance') DEFAULT 'available',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  FOREIGN KEY (`vendor_id`) REFERENCES `partners`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Proposals
CREATE TABLE IF NOT EXISTS `proposals` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `proposal_number` varchar(50) UNIQUE,
  `client_id` int(11) NOT NULL,
  `campaign_name` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT 0,
  `tax_amount` decimal(15,2) DEFAULT 0,
  `grand_total` decimal(15,2) DEFAULT 0,
  `status` enum('draft','sent','confirmed','cancelled') DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  FOREIGN KEY (`client_id`) REFERENCES `partners`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `proposal_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `proposal_id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `sale_rate` decimal(15,2) DEFAULT NULL,
  `purchase_rate` decimal(15,2) DEFAULT NULL,
  `days` int(11) DEFAULT 30,
  `amount` decimal(15,2) DEFAULT NULL,
  FOREIGN KEY (`proposal_id`) REFERENCES `proposals`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`site_id`) REFERENCES `sites`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Bookings
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `proposal_id` int(11) DEFAULT NULL,
  `client_id` int(11) NOT NULL,
  `campaign_name` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT 0,
  `tax_amount` decimal(15,2) DEFAULT 0,
  `grand_total` decimal(15,2) DEFAULT 0,
  `status` enum('pending','mounting','active','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  FOREIGN KEY (`client_id`) REFERENCES `partners`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `booking_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `booking_id` int(11) NOT NULL,
  `proposal_item_id` int(11) DEFAULT NULL,
  `site_id` int(11) NOT NULL,
  `purchase_rate` decimal(15,2) DEFAULT 0.00,
  `sale_rate` decimal(15,2) DEFAULT 0.00,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `days` int(11) DEFAULT 30,
  `purchase_amount` decimal(15,2) DEFAULT 0.00, -- Defaulted to 0 as requested
  `amount` decimal(15,2) DEFAULT 0.00,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`site_id`) REFERENCES `sites`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Operations & Invoices
CREATE TABLE IF NOT EXISTS `operations` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `booking_id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `field_team_notes` text DEFAULT NULL,
  `proof_image` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`),
  FOREIGN KEY (`site_id`) REFERENCES `sites`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `invoice_number` varchar(50) DEFAULT NULL UNIQUE,
  `booking_id` int(11) NOT NULL,
  `type` enum('tax','proforma','estimate') DEFAULT 'tax',
  `sub_total` decimal(15,2) DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT NULL,
  `payment_status` enum('unpaid','partially_paid','paid') DEFAULT 'unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. System Settings
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` varchar(50) NOT NULL PRIMARY KEY,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES 
('company_name', 'Sudha Creative & Advertising'),
('company_logo', 'logo.png'),
('company_letterhead', 'letterhead.png'),
('company_signature', 'signature.png'),
('company_pan', '');

COMMIT;
