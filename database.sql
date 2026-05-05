-- Easy Outdoor CRM - Database Migration Script
-- Version: 1.0.0
-- Description: Complete schema and seed data for initial setup

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- 1. Database Structure
-- --------------------------------------------------------

-- Drop tables if they exist to ensure a clean start (CAUTION: Removes all data)
-- DROP TABLE IF EXISTS invoices, operations, bookings, proposal_items, proposals, sites, partners, users;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','sales','operations','accounts') NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `partners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('client','vendor') NOT NULL,
  `name` varchar(255) NOT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `pan` varchar(10) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_code` varchar(50) DEFAULT NULL UNIQUE,
  `name` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `width` float DEFAULT NULL,
  `height` float DEFAULT NULL,
  `sqft` float GENERATED ALWAYS AS (`width` * `height`) STORED,
  `owner_type` enum('HA','TA') DEFAULT 'HA',
  `vendor_id` int(11) DEFAULT NULL,
  `card_rate` decimal(10,2) DEFAULT NULL,
  `purchase_rate` decimal(10,2) DEFAULT NULL,
  `status` enum('available','booked','maintenance') DEFAULT 'available',
  `image_url` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vendor_id` (`vendor_id`),
  CONSTRAINT `sites_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `proposals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proposal_number` varchar(50) DEFAULT NULL UNIQUE,
  `client_id` int(11) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT NULL,
  `tax_amount` decimal(15,2) DEFAULT NULL,
  `grand_total` decimal(15,2) DEFAULT NULL,
  `status` enum('draft','sent','confirmed','cancelled') DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `proposals_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `partners` (`id`),
  CONSTRAINT `proposals_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `proposal_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proposal_id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `sale_rate` decimal(10,2) DEFAULT NULL,
  `purchase_rate` decimal(10,2) DEFAULT NULL,
  `margin_pct` decimal(5,2) DEFAULT NULL,
  `days` int(11) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proposal_id` (`proposal_id`),
  KEY `site_id` (`site_id`),
  CONSTRAINT `proposal_items_ibfk_1` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proposal_items_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proposal_id` int(11) NOT NULL,
  `status` enum('pending','mounting','active','completed') DEFAULT 'pending',
  `mounting_date` date DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proposal_id` (`proposal_id`),
  CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `operations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `field_team_notes` text DEFAULT NULL,
  `proof_image` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `site_id` (`site_id`),
  CONSTRAINT `operations_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `operations_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) DEFAULT NULL UNIQUE,
  `booking_id` int(11) NOT NULL,
  `type` enum('tax','proforma','estimate') DEFAULT 'tax',
  `sub_total` decimal(15,2) DEFAULT NULL,
  `cgst` decimal(15,2) DEFAULT NULL,
  `sgst` decimal(15,2) DEFAULT NULL,
  `igst` decimal(15,2) DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT NULL,
  `payment_status` enum('unpaid','partially_paid','paid') DEFAULT 'unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 2. Seed Data
-- --------------------------------------------------------

-- Default Users (Passwords are: admin123, sales123, ops123, accounts123 respectively)
INSERT INTO `users` (`username`, `password`, `role`, `full_name`, `email`) VALUES
('admin', '$2y$10$mbcjPnx.gJFzEk78Jf/xUOnVVVEee.06degayJrRdsORioBCGuVwO', 'admin', 'System Admin', 'admin@easyoutdoor.com'),
('sales', '$2y$10$Cr3fv/BQUSpd5usvBzEAsO8jBZwtyNIJXyG.fNsbu5.83R3/J9liq', 'sales', 'Sales Manager', 'sales@easyoutdoor.com'),
('ops', '$2y$10$dY28rJsqAEv/gFrvSBWHjOjglJ2.v5CyjShkrPgehqS6MyYsnGPoG', 'operations', 'Ops Lead', 'ops@easyoutdoor.com'),
('accounts', '$2y$10$rmkNELO9tBJ6e99InnVdB.6slkAeawk.qkh4ZOOeIJW2dHC/bjkaG', 'accounts', 'Accountant', 'accounts@easyoutdoor.com');

-- Default Partners
INSERT INTO `partners` (`type`, `name`, `gstin`, `pan`) VALUES
('client', 'Brand Connect Pvt Ltd', '29AAAAA0000A1Z5', 'ABCDE1234F'),
('vendor', 'Outdoor Media Solutions', '29BBBBB0000B1Z5', 'FGHIJ5678K');

-- Default Sites
INSERT INTO `sites` (`site_code`, `name`, `location`, `city`, `type`, `width`, `height`, `owner_type`, `card_rate`, `purchase_rate`) VALUES
('S-001', 'Elite Hoarding - MG Road', 'MG Road Junction', 'Bangalore', 'Hoarding', 40, 20, 'HA', 150000.00, 100000.00),
('S-002', 'Airport Unipole - Gate 1', 'NH-44 Airport Road', 'Bangalore', 'Unipole', 20, 10, 'TA', 80000.00, 50000.00),
('S-003', 'Bus Queen Square - Central', 'Majestic Bus Stand', 'Bangalore', 'BQS', 10, 8, 'HA', 25000.00, 15000.00);

COMMIT;
