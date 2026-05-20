-- ============================================================
-- APPROVAL SYSTEM MIGRATION
-- Run this on your database to activate the approval system
-- ============================================================

-- 1. Add approval_status to proposals
ALTER TABLE proposals
  ADD COLUMN IF NOT EXISTS approval_status ENUM('pending_approval','approved','rejected') DEFAULT 'pending_approval' AFTER status,
  ADD COLUMN IF NOT EXISTS approved_by INT DEFAULT NULL AFTER approval_status,
  ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL AFTER approved_by,
  ADD COLUMN IF NOT EXISTS rejection_reason TEXT DEFAULT NULL AFTER approved_at;

-- 2. Add approval_status to purchase_orders
ALTER TABLE purchase_orders
  ADD COLUMN IF NOT EXISTS approval_status ENUM('pending_approval','approved','rejected') DEFAULT 'pending_approval' AFTER status,
  ADD COLUMN IF NOT EXISTS approved_by INT DEFAULT NULL AFTER approval_status,
  ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL AFTER approved_by,
  ADD COLUMN IF NOT EXISTS rejection_reason TEXT DEFAULT NULL AFTER approved_at;

-- 3. Add approval_status to bookings
ALTER TABLE bookings
  ADD COLUMN IF NOT EXISTS approval_status ENUM('pending_approval','approved','rejected') DEFAULT 'pending_approval' AFTER status,
  ADD COLUMN IF NOT EXISTS approved_by INT DEFAULT NULL AFTER approval_status,
  ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL AFTER approved_by,
  ADD COLUMN IF NOT EXISTS rejection_reason TEXT DEFAULT NULL AFTER approved_at;

-- 4. Add approval_status to invoices
ALTER TABLE invoices
  ADD COLUMN IF NOT EXISTS approval_status ENUM('pending_approval','approved','rejected') DEFAULT 'pending_approval' AFTER payment_status,
  ADD COLUMN IF NOT EXISTS approved_by INT DEFAULT NULL AFTER approval_status,
  ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL AFTER approved_by,
  ADD COLUMN IF NOT EXISTS rejection_reason TEXT DEFAULT NULL AFTER approved_at;

-- 5. Create approval_requests audit trail table
CREATE TABLE IF NOT EXISTS approval_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entity_type ENUM('proposal','purchase_order','booking','invoice') NOT NULL,
  entity_id INT NOT NULL,
  entity_ref VARCHAR(100) DEFAULT NULL COMMENT 'Human readable ref like PO number or Proposal number',
  requested_by INT NOT NULL,
  reviewed_by INT DEFAULT NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  remarks TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reviewed_at TIMESTAMP NULL DEFAULT NULL,
  INDEX idx_entity (entity_type, entity_id),
  INDEX idx_status (status),
  INDEX idx_requested_by (requested_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Mark all EXISTING records as already approved (so nothing breaks)
UPDATE proposals SET approval_status = 'approved' WHERE approval_status = 'pending_approval';
UPDATE purchase_orders SET approval_status = 'approved' WHERE approval_status = 'pending_approval';
UPDATE bookings SET approval_status = 'approved' WHERE approval_status = 'pending_approval';
UPDATE invoices SET approval_status = 'approved' WHERE approval_status = 'pending_approval';
