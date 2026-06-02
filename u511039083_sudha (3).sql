-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jun 01, 2026 at 03:09 PM
-- Server version: 11.8.6-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u511039083_sudha`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `created_at`) VALUES
(1, 1, 'generated a new proposal', 'proposals', 1, 'Proposal Number: PR-20260519-8145', '2026-05-19 22:15:36'),
(2, 1, 'converted proposal to booking', 'bookings', 6, 'Booking ID: 6', '2026-05-19 22:16:57'),
(3, 1, 'generated a purchase order for booking', 'purchase_orders', 1, 'PO Number: BPO-20260519-641', '2026-05-19 22:17:33'),
(4, 1, 'generated a vendor purchase order', 'purchase_orders', 2, 'PO Number: VPO-20260519-436 for Vendor ID: 3 with 1 sites.', '2026-05-19 22:31:08'),
(5, 1, 'generated proforma invoice', 'invoices', 2, 'Proforma Invoice #PI-20260520-949 generated for Proposal ID: 1', '2026-05-20 20:43:55'),
(6, 1, 'generated a new proposal', 'proposals', 2, 'Proposal Number: PR-20260521-7316', '2026-05-21 11:21:31'),
(7, 1, 'converted proposal to booking', 'bookings', 7, 'Booking ID: 7', '2026-05-21 11:21:59'),
(8, 1, 'generated a vendor purchase order', 'purchase_orders', 3, 'PO Number: VPO-20260521-489 for Vendor ID: 6 with 1 sites.', '2026-05-21 12:37:59'),
(9, 1, 'generated a new proposal', 'proposals', 3, 'Proposal Number: PR-20260523-6170', '2026-05-23 09:27:34'),
(10, 1, 'converted proposal to booking', 'bookings', 8, 'Booking ID: 8', '2026-05-23 09:34:53'),
(11, 1, 'generated a purchase order for booking', 'purchase_orders', 4, 'PO Number: BPO-20260523-172', '2026-05-23 10:15:26'),
(12, 1, 'generated a purchase order for booking', 'purchase_orders', 5, 'PO Number: BPO-20260523-472', '2026-05-23 10:16:47'),
(13, 1, 'generated a purchase order for booking', 'purchase_orders', 6, 'PO Number: BPO-20260523-583', '2026-05-23 10:17:00'),
(14, 1, 'generated a direct booking and purchase order(s)', 'bookings', 9, 'Booking ID: 9, 1 POs generated.', '2026-05-23 10:58:50'),
(15, 1, 'generated a direct booking and purchase order(s)', 'bookings', 9, 'Booking ID: 9, Multiple POs generated.', '2026-05-23 10:58:50'),
(16, 1, 'generated a direct booking and purchase order(s)', 'bookings', 10, 'Booking ID: 10, 1 POs generated.', '2026-05-23 11:00:22'),
(17, 1, 'generated a direct booking and purchase order(s)', 'bookings', 10, 'Booking ID: 10, Multiple POs generated.', '2026-05-23 11:00:22'),
(18, 1, 'generated a vendor purchase order', 'purchase_orders', 9, 'PO Number: VPO-20260525-184 for Vendor ID: 3 with 1 sites.', '2026-05-25 11:03:15'),
(19, 1, 'generated a new proposal', 'proposals', 4, 'Proposal Number: PR-20260525-6247', '2026-05-25 13:02:12'),
(20, 1, 'converted proposal to booking', 'bookings', 11, 'Booking ID: 11', '2026-05-25 13:06:11'),
(21, 1, 'generated a purchase order for booking', 'purchase_orders', 10, 'PO Number: BPO-20260525-868', '2026-05-25 13:24:12'),
(22, 1, 'generated a purchase order for booking', 'purchase_orders', 11, 'PO Number: BPO-20260525-431', '2026-05-25 13:24:20'),
(23, 1, 'generated a purchase order for booking', 'purchase_orders', 12, 'PO Number: BPO-20260525-302', '2026-05-25 13:24:24'),
(24, 1, 'generated a direct booking and purchase order(s)', 'bookings', 12, 'Booking ID: 12, 2 POs generated.', '2026-05-25 13:29:20'),
(25, 1, 'generated a direct booking and purchase order(s)', 'bookings', 13, 'Booking ID: 13, 2 POs generated.', '2026-05-25 13:59:02'),
(26, 1, 'generated a new proposal', 'proposals', 5, 'Proposal Number: PR-20260525-9516', '2026-05-25 14:00:42'),
(27, 1, 'converted proposal to booking', 'bookings', 14, 'Booking ID: 14', '2026-05-25 14:00:57'),
(28, 1, 'deleted invoice', 'financials', 5, 'Invoice SCA/26-27/009 was deleted. Booking #14 reverted to editable state.', '2026-05-25 18:38:47'),
(29, 1, 'generated a direct booking and purchase order(s)', 'bookings', 15, 'Booking ID: 15, 1 POs generated.', '2026-05-25 18:42:38'),
(30, 1, 'generated a direct booking and purchase order(s)', 'bookings', 16, 'Booking ID: 16, 1 POs generated.', '2026-05-25 18:59:33'),
(31, 1, 'generated a direct booking and purchase order(s)', 'bookings', 17, 'Booking ID: 17, 1 POs generated.', '2026-05-25 19:07:13'),
(32, 1, 'generated a direct booking and purchase order(s)', 'bookings', 18, 'Booking ID: 18, 1 POs generated.', '2026-05-25 19:12:55'),
(33, 1, 'deleted PO', 'financials', 20, 'Purchase Order #20 was deleted.', '2026-05-25 19:16:18'),
(34, 1, 'deleted PO', 'financials', 19, 'Purchase Order #19 was deleted.', '2026-05-25 19:16:23'),
(35, 1, 'deleted PO', 'financials', 18, 'Purchase Order #18 was deleted.', '2026-05-25 19:16:29'),
(36, 1, 'deleted PO', 'financials', 16, 'Purchase Order #16 was deleted.', '2026-05-25 19:16:34'),
(37, 1, 'deleted PO', 'financials', 10, 'Purchase Order #10 was deleted.', '2026-05-25 19:16:51'),
(38, 1, 'deleted PO', 'financials', 7, 'Purchase Order #7 was deleted.', '2026-05-25 19:16:56'),
(39, 1, 'deleted PO', 'financials', 6, 'Purchase Order #6 was deleted.', '2026-05-25 19:17:02'),
(40, 1, 'deleted PO', 'financials', 4, 'Purchase Order #4 was deleted.', '2026-05-25 19:17:08'),
(41, 1, 'deleted PO', 'financials', 3, 'Purchase Order #3 was deleted.', '2026-05-25 19:17:15'),
(42, 1, 'deleted PO', 'financials', 2, 'Purchase Order #2 was deleted.', '2026-05-25 19:17:20'),
(43, 1, 'deleted PO', 'financials', 5, 'Purchase Order #5 was deleted.', '2026-05-25 19:17:25'),
(44, 1, 'deleted PO', 'financials', 1, 'Purchase Order #1 was deleted.', '2026-05-25 19:17:30'),
(45, 1, 'deleted PO', 'financials', 8, 'Purchase Order #8 was deleted.', '2026-05-25 19:17:35'),
(46, 1, 'deleted PO', 'financials', 9, 'Purchase Order #9 was deleted.', '2026-05-25 19:17:42'),
(47, 1, 'deleted PO', 'financials', 12, 'Purchase Order #12 was deleted.', '2026-05-25 19:17:48'),
(48, 1, 'deleted PO', 'financials', 17, 'Purchase Order #17 was deleted.', '2026-05-25 19:17:59'),
(49, 1, 'deleted PO', 'financials', 15, 'Purchase Order #15 was deleted.', '2026-05-25 19:18:05'),
(50, 1, 'deleted PO', 'financials', 14, 'Purchase Order #14 was deleted.', '2026-05-25 19:18:13'),
(51, 1, 'deleted PO', 'financials', 13, 'Purchase Order #13 was deleted.', '2026-05-25 19:18:18'),
(52, 1, 'deleted PO', 'financials', 11, 'Purchase Order #11 was deleted.', '2026-05-25 19:18:23'),
(53, 1, 'generated a direct booking and purchase order(s)', 'bookings', 19, 'Booking ID: 19, 1 POs generated.', '2026-05-25 19:19:52'),
(54, 1, 'generated a purchase order for booking', 'purchase_orders', 22, 'PO Number: BPO-20260525-703', '2026-05-25 19:23:45'),
(55, 1, 'deleted PO', 'financials', 22, 'Purchase Order #22 was deleted.', '2026-05-25 19:26:18'),
(56, 1, 'deleted PO', 'financials', 21, 'Purchase Order #21 was deleted.', '2026-05-25 19:26:23'),
(57, 1, 'generated a purchase order for booking', 'purchase_orders', 23, 'PO Number: BPO-20260525-311', '2026-05-25 19:26:40'),
(58, 1, 'generated a direct booking and purchase order(s)', 'bookings', 20, 'Booking ID: 20, 1 POs generated.', '2026-05-25 19:44:34'),
(59, 1, 'generated a purchase order for booking', 'purchase_orders', 25, 'PO Number: BPO-20260525-863', '2026-05-25 19:45:49'),
(60, 1, 'deleted PO', 'financials', 25, 'Purchase Order #25 was deleted.', '2026-05-25 19:46:50'),
(61, 1, 'deleted PO', 'financials', 24, 'Purchase Order #24 was deleted.', '2026-05-25 19:46:56'),
(62, 1, 'deleted PO', 'financials', 23, 'Purchase Order #23 was deleted.', '2026-05-25 19:47:01'),
(63, 1, 'generated a direct booking and purchase order(s)', 'bookings', 21, 'Booking ID: 21, 1 POs generated.', '2026-05-25 19:48:47'),
(64, 1, 'generated a purchase order for booking', 'purchase_orders', 27, 'PO Number: BPO-20260525-890', '2026-05-25 19:49:13'),
(65, 1, 'deleted PO', 'financials', 27, 'Purchase Order #27 was deleted.', '2026-05-25 20:06:22'),
(66, 1, 'deleted PO', 'financials', 26, 'Purchase Order #26 was deleted.', '2026-05-25 20:06:27'),
(67, 1, 'generated a direct booking and purchase order(s)', 'bookings', 22, 'Booking ID: 22, 2 POs generated.', '2026-05-25 20:09:04'),
(68, 1, 'generated a purchase order for booking', 'purchase_orders', 30, 'PO Number: BPO-20260525-659', '2026-05-25 20:09:34'),
(69, 1, 'generated a purchase order for booking', 'purchase_orders', 31, 'PO Number: BPO-20260525-498', '2026-05-25 20:10:12'),
(70, 1, 'generated a purchase order for booking', 'purchase_orders', 31, 'PO Number: BPO-20260525-498', '2026-05-25 20:10:24'),
(71, 1, 'deleted PO', 'financials', 31, 'Purchase Order #31 was deleted.', '2026-05-25 20:12:10'),
(72, 1, 'deleted PO', 'financials', 30, 'Purchase Order #30 was deleted.', '2026-05-25 20:12:14'),
(73, 1, 'deleted PO', 'financials', 29, 'Purchase Order #29 was deleted.', '2026-05-25 20:12:20'),
(74, 1, 'deleted PO', 'financials', 28, 'Purchase Order #28 was deleted.', '2026-05-25 20:12:24'),
(75, 1, 'generated a purchase order for booking', 'purchase_orders', 32, 'PO Number: BPO-20260525-940', '2026-05-25 20:12:39'),
(76, 1, 'generated a purchase order for booking', 'purchase_orders', 33, 'PO Number: BPO-20260525-931', '2026-05-25 20:12:43'),
(77, 1, 'generated a purchase order for booking', 'purchase_orders', 32, 'PO Number: BPO-20260525-940', '2026-05-25 20:12:51'),
(78, 1, 'generated a purchase order for booking', 'purchase_orders', 33, 'PO Number: BPO-20260525-931', '2026-05-25 20:12:55'),
(79, 1, 'deleted PO', 'financials', 33, 'Purchase Order #33 was deleted.', '2026-05-25 20:13:15'),
(80, 1, 'generated a direct booking and purchase order(s)', 'bookings', 23, 'Booking ID: 23, 2 POs generated.', '2026-05-25 20:14:49'),
(81, 1, 'generated a purchase order for booking', 'purchase_orders', 36, 'PO Number: BPO-20260525-910', '2026-05-25 20:16:03'),
(82, 1, 'generated a purchase order for booking', 'purchase_orders', 37, 'PO Number: BPO-20260525-754', '2026-05-25 20:16:08'),
(83, 1, 'deleted PO', 'financials', 35, 'Purchase Order #35 was deleted.', '2026-05-25 20:19:53'),
(84, 1, 'deleted PO', 'financials', 34, 'Purchase Order #34 was deleted.', '2026-05-25 20:19:59'),
(85, 1, 'deleted PO', 'financials', 32, 'Purchase Order #32 was deleted.', '2026-05-25 20:20:05'),
(86, 1, 'deleted PO', 'financials', 37, 'Purchase Order #37 was deleted.', '2026-05-25 20:20:19'),
(87, 1, 'deleted PO', 'financials', 36, 'Purchase Order #36 was deleted.', '2026-05-25 20:20:23'),
(88, 1, 'generated a direct booking and purchase order(s)', 'bookings', 24, 'Booking ID: 24, 2 POs generated.', '2026-05-25 20:21:07'),
(89, 1, 'generated a purchase order for booking', 'purchase_orders', 40, 'PO Number: BPO-20260525-153', '2026-05-25 20:21:33'),
(90, 1, 'generated a purchase order for booking', 'purchase_orders', 41, 'PO Number: BPO-20260525-608', '2026-05-25 20:21:37'),
(91, 1, 'deleted PO', 'financials', 41, 'Purchase Order #41 was deleted.', '2026-05-25 20:24:28'),
(92, 1, 'deleted PO', 'financials', 40, 'Purchase Order #40 was deleted.', '2026-05-25 20:24:32'),
(93, 1, 'deleted PO', 'financials', 39, 'Purchase Order #39 was deleted.', '2026-05-25 20:24:37'),
(94, 1, 'deleted PO', 'financials', 38, 'Purchase Order #38 was deleted.', '2026-05-25 20:24:41'),
(95, 1, 'generated a direct booking and purchase order(s)', 'bookings', 25, 'Booking ID: 25, 1 POs generated.', '2026-05-25 20:33:59'),
(96, 1, 'approved client_printing', 'client_printings', 6, 'Admin approved: CPPO-260525-393', '2026-05-25 20:38:23'),
(97, 1, 'approved client_printing', 'client_printings', 6, 'Admin approved: CPPO-260525-393', '2026-05-25 20:41:08'),
(98, 1, 'deleted PO', 'financials', 42, 'Purchase Order #42 was deleted.', '2026-05-25 21:55:39'),
(99, 1, 'approved client_printing', 'client_printings', 10, 'Admin approved: CPPO-260525-292', '2026-05-25 22:00:03'),
(100, 1, 'generated a purchase order for booking', 'purchase_orders', 43, 'PO Number: BPO-20260526-274', '2026-05-26 08:08:36'),
(101, 1, 'deleted PO', 'financials', 43, 'Purchase Order #43 was deleted.', '2026-05-27 18:18:09'),
(102, 1, 'generated a direct booking and purchase order(s)', 'bookings', 26, 'Booking ID: 26, 1 POs generated.', '2026-05-27 18:23:24'),
(103, 1, 'approved client_printing', 'client_printings', 10, 'Admin approved: CPPO-260525-292', '2026-05-27 18:27:40'),
(104, 1, 'generated a purchase order for booking', 'purchase_orders', 45, 'PO Number: BPO-20260527-196', '2026-05-27 18:36:43'),
(105, 1, 'generated a purchase order for booking', 'purchase_orders', 45, 'PO Number: BPO-20260527-196', '2026-05-27 18:37:06'),
(106, 1, 'generated a purchase order for booking', 'purchase_orders', 45, 'PO Number: BPO-20260527-196', '2026-05-27 18:38:05'),
(107, 1, 'generated a purchase order for booking', 'purchase_orders', 45, 'PO Number: BPO-20260527-196', '2026-05-27 18:38:13'),
(108, 1, 'generated a purchase order for booking', 'purchase_orders', 45, 'PO Number: BPO-20260527-196', '2026-05-27 18:39:07'),
(109, 1, 'deleted PO', 'financials', 44, 'Purchase Order #44 was deleted.', '2026-05-27 19:56:53'),
(110, 1, 'converted proposal to booking', 'bookings', 27, 'Booking Auto-Created for Proforma Invoice from Proposal ID: 5', '2026-05-30 13:55:06'),
(111, 1, 'generated proforma invoice', 'invoices', 12, 'Proforma Invoice #PI-20260530-897 generated for Proposal ID: 5', '2026-05-30 13:55:06'),
(112, 1, 'deleted invoice', 'financials', 12, 'Invoice PI-20260530-897 was deleted. Booking #27 reverted to editable state.', '2026-05-30 15:27:51'),
(113, 1, 'deleted invoice', 'financials', 11, 'Invoice SCR/26-27/001 was deleted. Booking #26 reverted to editable state.', '2026-05-30 15:27:56'),
(114, 1, 'generated a new proposal', 'proposals', 6, 'Proposal Number: PR-20260530-6262', '2026-05-30 15:46:26'),
(115, 1, 'converted proposal to booking', 'bookings', 28, 'Booking ID: 28', '2026-05-30 16:07:04'),
(116, 1, 'generated a purchase order for booking', 'purchase_orders', 46, 'PO Number: BPO-20260530-317', '2026-05-30 16:25:11'),
(117, 1, 'generated a purchase order for booking', 'purchase_orders', 47, 'PO Number: BPO-20260530-804', '2026-05-30 16:26:06'),
(118, 1, 'generated a purchase order for booking', 'purchase_orders', 48, 'PO Number: BPO-20260530-481', '2026-05-30 16:26:58'),
(119, 1, 'approved booking', 'bookings', 27, 'Admin approved: Booking #27', '2026-05-30 16:28:31'),
(120, 1, 'rejected client_printing', 'client_printings', 10, 'Admin rejected: CPPO-260525-292. Reason: htfuly', '2026-05-30 16:28:48'),
(121, 1, 'deleted PO', 'financials', 45, 'Purchase Order #45 was deleted.', '2026-05-30 16:37:05'),
(122, 1, 'deleted invoice', 'financials', 13, 'Invoice SCR/26-27/001 was deleted. Booking #28 reverted to editable state.', '2026-05-30 16:42:00'),
(123, 1, 'generated a direct booking and purchase order(s)', 'bookings', 29, 'Booking ID: 29, 2 POs generated.', '2026-05-30 17:08:23'),
(124, 1, 'generated a purchase order for booking', 'purchase_orders', 51, 'PO Number: BPO-20260530-993', '2026-05-30 17:09:26'),
(125, 1, 'generated a purchase order for booking', 'purchase_orders', 52, 'PO Number: BPO-20260530-336', '2026-05-30 17:09:32'),
(126, 1, 'generated a new proposal', 'proposals', 7, 'Proposal Number: PR-20260530-4401', '2026-05-30 18:21:15'),
(127, 1, 'converted proposal to booking', 'bookings', 30, 'Booking ID: 30', '2026-05-30 18:22:09'),
(128, 1, 'generated a new proposal', 'proposals', 8, 'Proposal Number: PR-20260530-2046', '2026-05-30 18:25:21'),
(129, 1, 'generated a direct booking and purchase order(s)', 'bookings', 31, 'Booking ID: 31, 1 POs generated.', '2026-05-30 18:34:20'),
(130, 1, 'converted proposal to booking', 'bookings', 32, 'Booking Auto-Created for Proforma Invoice from Proposal ID: 8', '2026-06-01 11:14:02'),
(131, 1, 'generated proforma invoice', 'invoices', 21, 'Proforma Invoice #PI-20260601-859 generated for Proposal ID: 8', '2026-06-01 11:14:02');

-- --------------------------------------------------------

--
-- Table structure for table `approval_requests`
--

CREATE TABLE `approval_requests` (
  `id` int(11) NOT NULL,
  `entity_type` enum('proposal','purchase_order','booking','invoice') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `entity_ref` varchar(100) DEFAULT NULL COMMENT 'Human readable ref like PO number or Proposal number',
  `requested_by` int(11) NOT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `approval_requests`
--

INSERT INTO `approval_requests` (`id`, `entity_type`, `entity_id`, `entity_ref`, `requested_by`, `reviewed_by`, `status`, `remarks`, `created_at`, `reviewed_at`) VALUES
(1, '', 6, 'CPPO-260525-393', 0, NULL, 'pending', NULL, '2026-05-25 20:40:43', NULL),
(2, '', 10, 'CPPO-260525-292', 0, NULL, 'pending', NULL, '2026-05-26 08:21:53', NULL),
(3, '', 10, 'CPPO-260525-292', 0, NULL, 'pending', NULL, '2026-05-26 08:22:03', NULL),
(4, '', 10, 'CPPO-260525-292', 0, NULL, 'pending', NULL, '2026-05-26 08:25:13', NULL),
(5, '', 10, 'CPPO-260525-292', 0, NULL, 'pending', NULL, '2026-05-26 08:26:54', NULL),
(6, '', 10, 'CPPO-260525-292', 0, NULL, 'pending', NULL, '2026-05-26 08:26:56', NULL),
(7, '', 10, 'CPPO-260525-292', 0, NULL, 'pending', NULL, '2026-05-27 18:21:17', NULL),
(8, '', 10, 'CPPO-260525-292', 0, NULL, 'pending', NULL, '2026-05-27 18:21:25', NULL),
(9, '', 10, 'CPPO-260525-292', 0, NULL, 'pending', NULL, '2026-05-27 18:24:20', NULL),
(10, '', 10, 'CPPO-260525-292', 0, NULL, 'pending', NULL, '2026-05-27 18:28:28', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) DEFAULT NULL,
  `client_id` int(11) NOT NULL,
  `campaign_name` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT NULL,
  `tax_amount` decimal(15,2) DEFAULT NULL,
  `grand_total` decimal(15,2) DEFAULT NULL,
  `printing_cost` decimal(15,2) DEFAULT 0.00,
  `mounting_cost` decimal(15,2) DEFAULT 0.00,
  `status` enum('pending','mounting','active','completed','cancelled') DEFAULT 'pending',
  `approval_status` enum('pending_approval','approved','rejected') DEFAULT 'pending_approval',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `confirmation_type` varchar(20) DEFAULT 'po',
  `customer_po_no` varchar(50) DEFAULT NULL,
  `customer_po_date` date DEFAULT NULL,
  `email_date` date DEFAULT NULL,
  `customer_po_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `mounting_date` varchar(255) DEFAULT NULL,
  `brand_name` varchar(255) DEFAULT NULL,
  `external_po` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `billing_gstin` varchar(20) DEFAULT NULL,
  `tax_type` enum('cgst_sgst','igst') DEFAULT 'igst'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `proposal_id`, `client_id`, `campaign_name`, `start_date`, `end_date`, `total_amount`, `tax_amount`, `grand_total`, `printing_cost`, `mounting_cost`, `status`, `approval_status`, `approved_by`, `approved_at`, `rejection_reason`, `confirmation_type`, `customer_po_no`, `customer_po_date`, `email_date`, `customer_po_file`, `created_at`, `mounting_date`, `brand_name`, `external_po`, `contact_person`, `billing_gstin`, `tax_type`) VALUES
(27, 5, 22, NULL, '2026-05-01', '2026-05-30', 161000.00, 28980.00, 189980.00, 0.00, 0.00, 'active', 'approved', 1, '2026-05-30 16:28:31', NULL, 'po', NULL, NULL, NULL, NULL, '2026-05-30 13:55:06', NULL, NULL, '', NULL, '07AAECM4369H1ZA', 'igst'),
(28, 6, 20, 'Relaxo', '2026-05-01', '2026-05-30', 188000.00, 33840.00, 221840.00, 0.00, 0.00, 'active', 'approved', NULL, NULL, NULL, 'po', '123', '2026-05-30', NULL, 'uploads/customer_pos/PO_28_1780160753.pdf', '2026-05-30 16:07:04', NULL, NULL, '', NULL, '27AABCP1311M2ZK', 'igst'),
(29, NULL, 22, 'HUL', '2026-05-01', '2026-05-30', 36000.00, 6480.00, 42480.00, 0.00, 0.00, 'active', 'approved', NULL, NULL, NULL, 'po', '456', '2026-05-30', NULL, 'uploads/customer_pos/PO_29_1780161038.pdf', '2026-05-30 17:08:23', NULL, '', '', 'NA', '29AAECM4369H1Z4', 'cgst_sgst'),
(30, 7, 42, 'HUL', '2026-05-21', '2026-05-13', 16000.00, 2880.00, 18880.00, 0.00, 0.00, 'active', 'approved', NULL, NULL, NULL, 'po', '123', '2026-05-29', NULL, 'uploads/customer_pos/PO_30_1780165615.pdf', '2026-05-30 18:22:09', NULL, NULL, '', NULL, '', 'igst'),
(31, NULL, 4, 'HUL', '2026-05-30', '2026-06-30', 61000.00, 10980.00, 71980.00, 0.00, 0.00, 'active', 'approved', NULL, NULL, NULL, 'po', '123', '2026-05-29', NULL, 'uploads/customer_pos/PO_31_1780166521.pdf', '2026-05-30 18:34:20', NULL, '', '', 'Nishant Jha', '', 'igst'),
(32, 8, 16, NULL, '0000-00-00', '0000-00-00', 16000.00, 2880.00, 18880.00, 0.00, 0.00, 'active', 'pending_approval', NULL, NULL, NULL, 'po', '123', '2026-06-11', NULL, 'uploads/customer_pos/PO_32_1780322501.pdf', '2026-06-01 11:14:02', NULL, NULL, '', NULL, '', 'igst');

-- --------------------------------------------------------

--
-- Table structure for table `booking_items`
--

CREATE TABLE `booking_items` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `proposal_item_id` int(11) DEFAULT NULL,
  `site_id` int(11) NOT NULL,
  `purchase_rate` decimal(15,2) DEFAULT NULL,
  `sale_rate` decimal(15,2) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `days` int(11) DEFAULT 30,
  `purchase_amount` decimal(15,2) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `selected_image` varchar(255) DEFAULT NULL,
  `printing_vendor_id` varchar(255) DEFAULT NULL,
  `printing_rate` varchar(255) DEFAULT NULL,
  `printing_amount` varchar(255) DEFAULT NULL,
  `custom_location` varchar(255) DEFAULT NULL,
  `custom_site_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_items`
--

INSERT INTO `booking_items` (`id`, `booking_id`, `proposal_item_id`, `site_id`, `purchase_rate`, `sale_rate`, `start_date`, `end_date`, `days`, `purchase_amount`, `amount`, `selected_image`, `printing_vendor_id`, `printing_rate`, `printing_amount`, `custom_location`, `custom_site_name`) VALUES
(79, 27, 12, 7, 55000.00, 55000.00, '2026-05-01', '2026-05-30', NULL, 0.00, 55000.00, NULL, NULL, NULL, NULL, NULL, NULL),
(80, 27, 13, 60, 8000.00, 25000.00, '2026-05-01', '2026-05-30', NULL, 0.00, 25000.00, NULL, NULL, NULL, NULL, NULL, NULL),
(81, 27, 14, 28, 45000.00, 45000.00, '2026-05-01', '2026-05-30', NULL, 0.00, 45000.00, NULL, NULL, NULL, NULL, NULL, NULL),
(82, 27, 15, 56, 3000.00, 16000.00, '2026-05-01', '2026-05-30', 30, 0.00, 16000.00, NULL, NULL, NULL, NULL, NULL, NULL),
(83, 27, 16, 47, 20000.00, 20000.00, '2026-05-01', '2026-05-30', 30, 0.00, 20000.00, NULL, NULL, NULL, NULL, NULL, NULL),
(84, 28, 17, 1, 40000.00, 40000.00, '2026-05-01', '2026-05-30', NULL, 0.00, 40000.00, NULL, NULL, NULL, NULL, '', 'Rathbari'),
(85, 28, 18, 2, 30000.00, 30000.00, '2026-05-01', '2026-05-30', NULL, 0.00, 30000.00, NULL, NULL, NULL, NULL, '', 'Station Road'),
(86, 28, 19, 44, 45000.00, 45000.00, '2026-05-01', '2026-05-30', NULL, 20000.00, 45000.00, NULL, NULL, NULL, NULL, '', NULL),
(87, 28, 20, 51, 4000.00, 12000.00, '2026-05-01', '2026-05-30', NULL, 3000.00, 12000.00, NULL, NULL, NULL, NULL, '', NULL),
(88, 28, 21, 66, 20000.00, 45000.00, '2026-05-01', '2026-05-30', NULL, 20000.00, 45000.00, NULL, NULL, NULL, NULL, '', NULL),
(89, 28, 22, 65, 5000.00, 16000.00, '2026-05-01', '2026-05-30', NULL, 5000.00, 16000.00, NULL, NULL, NULL, NULL, '', NULL),
(91, 29, NULL, 2, NULL, NULL, '2026-05-01', '2026-05-30', 30, 30000.00, 30000.00, NULL, NULL, '0', '0', NULL, NULL),
(92, 29, NULL, 6, NULL, NULL, '2026-05-01', '2026-05-30', 30, 30000.00, 30000.00, NULL, NULL, '0', '0', NULL, NULL),
(93, 29, NULL, 43, NULL, NULL, '2026-05-01', '2026-05-30', 30, 4000.00, 16000.00, NULL, NULL, '0', '0', NULL, NULL),
(94, 29, NULL, 47, NULL, NULL, '2026-05-01', '2026-05-30', 30, 10000.00, 20000.00, NULL, NULL, '0', '0', NULL, NULL),
(95, 30, 24, 43, 16000.00, 16000.00, '2026-05-21', '2026-05-13', NULL, 0.00, 16000.00, NULL, NULL, NULL, NULL, NULL, NULL),
(96, 31, NULL, 43, NULL, NULL, '2026-05-30', '2026-06-30', 30, 16000.00, 16000.00, NULL, NULL, '0', '0', NULL, NULL),
(97, 31, NULL, 44, NULL, NULL, '2026-05-30', '2026-06-30', 30, 45000.00, 45000.00, NULL, NULL, '0', '0', NULL, NULL),
(98, 32, 25, 43, 16000.00, 16000.00, '0000-00-00', '0000-00-00', NULL, 0.00, 16000.00, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `campaigns`
--

CREATE TABLE `campaigns` (
  `id` int(11) NOT NULL,
  `project_id` varchar(50) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `from_date` date DEFAULT NULL,
  `to_date` date DEFAULT NULL,
  `days` int(11) DEFAULT NULL,
  `sqft` float DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `qos_pct` decimal(5,2) DEFAULT 100.00,
  `status` enum('planned','approved','running','completed') DEFAULT 'planned',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_pos`
--

CREATE TABLE `client_pos` (
  `id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `po_number` varchar(100) DEFAULT NULL,
  `po_date` date DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_printing_rates`
--

CREATE TABLE `client_printing_rates` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `po_number` varchar(50) DEFAULT NULL,
  `site_id` int(11) DEFAULT NULL,
  `media_type` varchar(50) DEFAULT NULL,
  `rate_per_sqft` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `attachments` varchar(255) DEFAULT NULL,
  `client_tax_order` varchar(255) DEFAULT NULL,
  `custom_invoice_number` varchar(100) DEFAULT NULL,
  `custom_invoice_date` date DEFAULT NULL,
  `confirmation_type` varchar(20) DEFAULT 'po',
  `customer_po_no` varchar(50) DEFAULT NULL,
  `customer_po_date` date DEFAULT NULL,
  `email_date` date DEFAULT NULL,
  `customer_po_file` varchar(255) DEFAULT NULL,
  `is_final_invoice` tinyint(1) DEFAULT 0,
  `approval_status` enum('pending_approval','approved','rejected') DEFAULT 'pending_approval',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_printing_rates`
--

INSERT INTO `client_printing_rates` (`id`, `client_id`, `po_number`, `site_id`, `media_type`, `rate_per_sqft`, `created_at`, `attachments`, `client_tax_order`, `custom_invoice_number`, `custom_invoice_date`, `confirmation_type`, `customer_po_no`, `customer_po_date`, `email_date`, `customer_po_file`, `is_final_invoice`, `approval_status`, `approved_by`, `approved_at`, `rejection_reason`) VALUES
(10, 21, 'CPPO-260525-292', 2, 'Flex', 8.00, '2026-05-25 21:59:41', NULL, NULL, NULL, '2026-05-27', 'po', '123', '2026-05-28', NULL, 'uploads/customer_pos/PRINT_PO_21_1779906508.pdf', 0, 'rejected', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `entities`
--

CREATE TABLE `entities` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `pan` varchar(10) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `bank_details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `entities`
--

INSERT INTO `entities` (`id`, `name`, `logo`, `gstin`, `pan`, `address`, `bank_details`, `created_at`) VALUES
(1, 'Suudha Creative & Avertising (OPC) Private Limited', 'entity_1779875972_WhatsApp Image 2026-05-27 at 3.27.55 PM.jpeg', '19ABOCS6241D1Z4', 'ABOCS6241D', 'Deshbandhu Para.\r\nJhaljhalia\r\nMalda -732102', 'Axis Bank Limited \r\nEnglish Bazar,  Malda - 732101\r\nAC No. 923020028425900\r\nIFSC :UTIB0001981', '2026-05-27 09:45:52'),
(2, 'Sudha Creative ', '', '19AHRPT4740Q1Z6', 'AHRPT4740Q', 'Mahananda Pally, Jhaljhalia \r\nMalda - 732102', 'ICICI Bank\r\nAC No: 777705002560\r\nIFSC: ICIC0000472', '2026-05-27 20:25:13');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(50) DEFAULT NULL,
  `booking_id` int(11) NOT NULL,
  `invoice_date` date DEFAULT NULL,
  `type` enum('tax','proforma','estimate') DEFAULT 'tax',
  `sub_total` decimal(15,2) DEFAULT NULL,
  `cgst` decimal(15,2) DEFAULT NULL,
  `sgst` decimal(15,2) DEFAULT NULL,
  `igst` decimal(15,2) DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT NULL,
  `payment_status` enum('unpaid','partially_paid','paid') DEFAULT 'unpaid',
  `approval_status` enum('pending_approval','approved','rejected') DEFAULT 'pending_approval',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `invoice_number`, `booking_id`, `invoice_date`, `type`, `sub_total`, `cgst`, `sgst`, `igst`, `total_amount`, `payment_status`, `approval_status`, `approved_by`, `approved_at`, `rejection_reason`, `created_at`) VALUES
(14, 'SCR/26-27/001', 28, '2026-05-30', 'tax', 188000.00, NULL, NULL, NULL, 221840.00, 'unpaid', 'approved', NULL, NULL, NULL, '2026-05-30 17:05:53'),
(15, 'SCR/26-27/002', 29, '2026-05-30', 'tax', 36000.00, NULL, NULL, NULL, 42480.00, 'unpaid', 'approved', NULL, NULL, NULL, '2026-05-30 17:10:38'),
(16, 'SCR/P/001', 30, '2026-05-30', 'tax', 16000.00, NULL, NULL, NULL, 18880.00, 'unpaid', 'approved', NULL, NULL, NULL, '2026-05-30 18:26:55'),
(21, 'PI-20260601-859', 32, NULL, 'proforma', 16000.00, 1440.00, 1440.00, 0.00, 18880.00, 'unpaid', 'pending_approval', NULL, NULL, NULL, '2026-06-01 11:14:02');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `media_types`
--

CREATE TABLE `media_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `media_types`
--

INSERT INTO `media_types` (`id`, `name`) VALUES
(1, 'Billboard'),
(4, 'BQS'),
(3, 'Gantry'),
(6, 'LED Screen'),
(7, 'Traffic Signal Pole Kiosks'),
(2, 'Unipole');

-- --------------------------------------------------------

--
-- Table structure for table `operations`
--

CREATE TABLE `operations` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `assigned_mounter_id` int(11) DEFAULT NULL,
  `mounting_date` date DEFAULT NULL,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `field_team_notes` text DEFAULT NULL,
  `proof_image` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `operations`
--

INSERT INTO `operations` (`id`, `booking_id`, `site_id`, `assigned_mounter_id`, `mounting_date`, `status`, `field_team_notes`, `proof_image`, `updated_at`) VALUES
(79, 27, 7, NULL, NULL, 'pending', NULL, NULL, '2026-05-30 13:55:06'),
(80, 27, 60, NULL, NULL, 'pending', NULL, NULL, '2026-05-30 13:55:06'),
(81, 27, 28, NULL, NULL, 'pending', NULL, NULL, '2026-05-30 13:55:06'),
(82, 27, 56, NULL, NULL, 'pending', NULL, NULL, '2026-05-30 13:55:06'),
(83, 27, 47, NULL, NULL, 'pending', NULL, NULL, '2026-05-30 13:55:06'),
(84, 28, 1, NULL, NULL, 'pending', NULL, NULL, '2026-05-30 16:07:04'),
(85, 28, 2, NULL, NULL, 'pending', NULL, NULL, '2026-05-30 16:07:04'),
(86, 28, 44, NULL, NULL, 'pending', NULL, NULL, '2026-05-30 16:07:04'),
(87, 28, 51, NULL, NULL, 'pending', NULL, NULL, '2026-05-30 16:07:04'),
(88, 28, 66, NULL, NULL, 'pending', NULL, NULL, '2026-05-30 16:07:04'),
(89, 28, 65, NULL, NULL, 'pending', NULL, NULL, '2026-05-30 16:07:04'),
(91, 29, 2, NULL, NULL, 'pending', NULL, NULL, '2026-05-30 17:08:23'),
(92, 29, 6, NULL, NULL, 'pending', NULL, NULL, '2026-05-30 17:08:23'),
(93, 29, 43, NULL, NULL, 'pending', NULL, NULL, '2026-05-30 17:08:23'),
(94, 29, 47, NULL, NULL, 'pending', NULL, NULL, '2026-05-30 17:08:23'),
(95, 30, 43, NULL, NULL, 'pending', NULL, NULL, '2026-05-30 18:22:09'),
(96, 31, 43, NULL, NULL, 'pending', NULL, NULL, '2026-05-30 18:34:20'),
(97, 31, 44, NULL, NULL, 'pending', NULL, NULL, '2026-05-30 18:34:20'),
(98, 32, 43, NULL, NULL, 'pending', NULL, NULL, '2026-06-01 11:14:02');

-- --------------------------------------------------------

--
-- Table structure for table `partners`
--

CREATE TABLE `partners` (
  `id` int(11) NOT NULL,
  `type` enum('client','vendor') NOT NULL,
  `business_type` varchar(100) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `additional_gst` text DEFAULT NULL,
  `pan` varchar(10) DEFAULT NULL,
  `msme` varchar(100) DEFAULT NULL,
  `cin` varchar(100) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `payment_terms` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `partners`
--

INSERT INTO `partners` (`id`, `type`, `business_type`, `name`, `gstin`, `additional_gst`, `pan`, `msme`, `cin`, `contact_person`, `phone`, `email`, `address`, `city`, `state`, `district`, `pincode`, `billing_address`, `payment_terms`, `status`, `created_at`) VALUES
(2, 'vendor', 'Proprietorship', 'Ananda Arts', '19ADAPB9585G1ZD', '', 'ADAPB9585G', NULL, NULL, 'Mr. Angeekar Basak', '9733192248', 'ananda.arts09@gmail.com', '2/2 Nazrul Bagh\r\nOpp. I T I College', 'Malda', 'West Bengal', 'Malda', '732101', '2/2 Nazrul Bagh\r\nOpp. I T I College', '90 Days', 'active', '2026-05-10 10:12:22'),
(3, 'vendor', 'Proprietorship', 'Alka Ad', '19ADAPB9585G1ZD', '', 'ADAPB9585G', NULL, NULL, 'Mr. Sudipta Das', '9933797759', 'alkaad.sdas@gmail.com', 'Rabindra Pally \r\n3rd Lane\r\nMalda-732101', 'Malda', 'West Bengal', 'Malda', '732101', 'Rabindra Pally \r\n3rd Lane\r\nMalda-732101', '90 Days', 'active', '2026-05-10 10:12:47'),
(4, 'client', 'Proprietorship', 'K.D.Advertising', '07ABVPJ9669E1ZW', '', 'ABVPJ9669E', NULL, NULL, 'Nishant Jha', '', '', 'J-11,2nd Floor,Yamaha Apartment,\r\nHoly Chawk,Devli- Delhi-110062', 'Delhi', 'Delhi', 'Delhi', '110062', 'J-11,2nd Floor,Yamaha Apartment,\r\nHoly Chawk,Devli- Delhi-110062', NULL, 'active', '2026-05-10 10:34:53'),
(6, 'vendor', 'Proprietorship', 'Shubhadip Art', '19AKWPR6694K1ZR', '', 'AKWPR6694K', NULL, NULL, 'Mr. Arjun Roy', '9733078449', '', 'Fulbari More, MK Road, Malda - 732101', '732101', 'West Bengal', 'Malda', '732101', 'Fuulbari More, MK Road, Malda - 732101', '', 'active', '2026-05-11 12:59:42'),
(7, 'vendor', 'Proprietorship', 'Monalisa Arts', '19AKRPP4184F1ZK', '', 'AKRPP4184F', NULL, NULL, 'Mr. Subir Basu', '8373050771', '', '24/A, Sriram Siromoni Road, Noyanee Apartment ,\r\n1st Floor, Berhampore -742101', '742101', 'West Bengal', '742101', '742101', '24/A, Sriram Siromoni Road, Noyanee Apartment ,\r\n1st Floor, Berhampore -742101', '', 'active', '2026-05-11 13:05:31'),
(8, 'vendor', 'Proprietorship', 'VISIBILITY', '19AARFV4116H1ZS', '', 'AARFV4116H', NULL, NULL, '', '', '', 'L 1/20 Vidyasagar\r\nKolkata -700047', 'Kolkata', 'West Bengal', 'kolkata', '700047', 'L 1/20 Vidyasagar, \r\nKolkata - 700047', '', 'active', '2026-05-11 13:15:12'),
(9, 'vendor', 'Proprietorship', 'RISING SUN', '19AIKPM5663M1ZH', '', 'AIKPM5663M', NULL, NULL, 'Mr. Sudip Bhattacharjee', '9051631010', '', 'D L Roy Sarani\r\nMahananda Para, Siliguri - 734001', 'SILIGURI', 'West Bengal', 'DARJEELING', '734001', 'D L Roy Sarani,\r\nMahananda Para, Siliguri - 734001', '', 'active', '2026-05-11 13:22:35'),
(10, 'vendor', 'Proprietorship', 'DEMARG ADVERTISERS', '11AVQPP6808C1ZM', '', 'AVQPP6808C', NULL, NULL, '', '', '', 'Tenzing, Gelek, Tibet Road, \r\nGangtok, East Sikkim,, Sikkim - 737101', 'GANGTOK', 'Sikkim', 'GANGTOK', '737101', 'Tenzing, Gelek, Tibet Road, \r\nGangtok, East Sikkim,, Sikkim - 737101', '', 'active', '2026-05-11 13:40:37'),
(11, 'vendor', 'Proprietorship', 'Rajanya', '', '', '', NULL, NULL, 'Jeet Kundu', '8597939069', '', 'Foara More, Malda - 7321101', 'Malda', 'West Bengal', 'Malda', '732101', 'Foara More, Malda - 7321101', '', 'active', '2026-05-11 13:56:53'),
(12, 'vendor', '', 'Shree Advertisement', '', '', '', NULL, NULL, 'Mr. Biswajit Ghosh', '9734947135', '', 'SM Pally, Malda - 732102', 'Malda', 'West Bengal', 'Malda', '732102', 'SM Pally, Malda - 732102', '', 'active', '2026-05-11 13:58:25'),
(13, 'client', 'Proprietorship', 'Advise Advertising &amp; Media Pvt. Ltd.', '19AAJCA7446A1ZQ', '', 'AAJCA7446A', NULL, NULL, '', '', '', 'Binapani Enclave, P-3\r\nVIP Road, Raghunathpur\r\nBaguihati, Kolkata - 700059', 'Kolkata', 'West Bengal', 'kolkata', '700059', 'Binapani Enclave, P-3\r\nVIP Road, Raghunathpur\r\nBaguihati, Kolkata - 700059', NULL, 'active', '2026-05-11 14:03:42'),
(14, 'client', 'Proprietorship', 'Adwise India', '19AAOFA7238C1ZD', '', 'AAOFA7238C', NULL, NULL, '', '', '', 'AH 76, Saltlake City\r\nKolkata - 700091', 'Kolkata', 'West Bengal', 'kolkata', '700091', 'AH 76, Saltlake City\r\nKolkata - 700091', NULL, 'active', '2026-05-11 14:05:35'),
(15, 'client', 'Proprietorship', 'Baazar Style Retail Limited', '19AAECD7575J1Z3', '', 'AAECD7575J', NULL, NULL, '', '', '', 'P S Srijan tech Park, DN-52,\r\n12th Floor, Street No -11,\r\nDN Block Sector - V, Kolkata - 700091', 'Kolkata', 'West Bengal', 'kolkata', '', 'P S Srijan tech Park, DN-52,\r\n12th Floor, Street No -11,\r\nDN Block Sector - V, Kolkata - 700091', NULL, 'active', '2026-05-11 14:09:51'),
(16, 'client', 'Proprietorship', 'Manmohini Textile Private Limited', '19AADCM6065B1ZL', '', 'AADCM6065B', NULL, NULL, '', '', '', '315/1 &amp; 316/B, Netaji Road, Khagra\r\nMurshidabad - 742103', 'Berhampore', 'West Bengal', 'Murshidabad', '742103', '315/1 &amp; 316/B, Netaji Road, Khagra\r\nMurshidabad - 742103', NULL, 'active', '2026-05-11 14:13:02'),
(18, 'client', 'Private Limited', 'Sampark Advertising &amp; Media Pvt. Ltd.', '19AAMCS3863N1ZH', '', 'AAMCS3863N', NULL, NULL, '', '', '', '9A Esplanade, Kolkata -700069', 'Kolkata', 'West Bengal', 'kolkata', '700069', '9A Esplanade, Kolkata -700069', NULL, 'active', '2026-05-11 14:36:43'),
(19, 'vendor', 'Proprietorship', 'Sarkar Ad Agency', '19ARLPS4320H1ZJ', '', 'ARLPS4320H', NULL, NULL, 'Ayan Sarkar', '09434020181', 'saa.malda@gmail.com', 'Ramkrishna Pally, Malda - 732101', 'Malda', 'West Bengal', 'Malda', '732101', 'Ramkrishna Pally, Malda - 732101', '', 'active', '2026-05-13 09:48:37'),
(20, 'client', 'Group of Companies', 'Kinetic Advertising India Private Limited', '19AABCP1311M1ZJ', '{\"row_1778676360805i4vrc\":{\"gstin\":\"27AABCP1311M2ZK\",\"state\":\"Maharashtra\",\"city\":\"Mumbai\",\"district\":\"Mumbai\",\"address\":\"4th Floor, A Wing\\r\\nThe ORB Sahar\\r\\nVill: Marol\\r\\nAndheri Eaast\\r\\nMumbai - 400099\",\"pan\":\"AABCP1311M\"},\"row_1778676360809zae0e\":{\"gstin\":\"29AABCP1311M1ZH\",\"state\":\"Karnataka\",\"city\":\"Bangalore\",\"district\":\"Bangalore\",\"address\":\"3rd Floor, Mahalakshmi Chember\\r\\nMG Road, Bangalore - 560001\",\"pan\":\"AABCP1311M\"},\"row_17786763608122n5hr\":{\"gstin\":\"06AABCP1311M1ZP\",\"state\":\"Haryana\",\"city\":\"Gurgaon\",\"district\":\"Gurgaon\",\"address\":\"5th Floor, 405-B, Tower - B, DLF Cyber Perk Sector - 20, Udyog Bihar, Phase - III, Gurgaon - 122016\",\"pan\":\"AABCP1311M\"},\"row_1778676365070pcecs\":{\"gstin\":\"33AABCP1311M1ZS\",\"state\":\"Tamil Nadu\",\"city\":\"Chennai\",\"district\":\"Chennai\",\"address\":\"139\\/140, Rukmani, Lakshmipathy, Salai\\r\\nMarshalls Road, Egmore, Chennai - 600008\",\"pan\":\"AABCP1311M\"}}', 'AABCP1311M', NULL, NULL, 'NA', 'NA', '', 'Plot No. A2 M2 N2\r\n5th Floor\r\nOmegha Building, Bengal Intelligent Park \r\nSector V\r\nKolkata - 700091', 'Kolkata', 'West Bengal', 'kolkata', '700091', 'Plot No. A2 M2 N2\r\n5th Floor\r\nOmegha Building, Bengal Intelligent Park \r\nSector V\r\nKolkata - 700091', NULL, 'active', '2026-05-13 10:03:43'),
(21, 'client', 'Group of Companies', 'LAQSHYA MEDIA  LIMITED', '09AAACL5004C1Z3', '{\"row_1778676790160hq4eq\":{\"gstin\":\"29AAACL5004C1Z1\",\"state\":\"Karnataka\",\"city\":\"Bangalore\",\"district\":\"Bangalore\",\"address\":\"New No -13\\/1, Old No-73\\/1, 2nd Floor, \\r\\n2nd Main Road, Above Namdhari Fresh\\r\\nBangalore \",\"pan\":\"AAACL5004C\"},\"row_1778676940974evq3x\":{\"gstin\":\"27AAACL5004C1Z5\",\"state\":\"Maharashtra\",\"city\":\"Mumbai\",\"district\":\"Mumbai\",\"address\":\"Laqshya House, Saraswati Baug,Near Rameshwar Temple, Socity Road, Jogeshwari East, Mumbai - 400060\",\"pan\":\"AAACL5004C\"}}', 'AAACL5004C', NULL, NULL, 'NA', 'NA', '', 'Sector - 4, Noida -201301', 'Noida', 'Uttar Pradesh', 'Noida', '201301', 'Sector - 4, Noida -201301', NULL, 'active', '2026-05-13 12:52:58'),
(22, 'client', 'Group of Companies', 'MOMS OUTDOOR MEDIA SOLUTIONS PRIVATE LIMITED', '19AAECM4369H2Z4', '{\"row_1778677389143yit2p\":{\"gstin\":\"27AAECM4369H1Z8\",\"state\":\"Maharashtra\",\"city\":\"Mumbai\",\"district\":\"Mumbai\",\"address\":\"202-203, 349 Business Point\\r\\nWestern Express Highway\\r\\nAndheri East, Mumbai  - 400069 \",\"pan\":\"AAECM4369H\"},\"row_1778677501549anmct\":{\"gstin\":\"29AAECM4369H1Z4\",\"state\":\"Karnataka\",\"city\":\"Bangalore\",\"district\":\"Bangalore\",\"address\":\"NO-801-808 8th floor\\r\\nThe Estate Building No-121\\r\\nDickenson Road, bangalore - 560042\",\"pan\":\"AAECM4369H\"},\"row_1778677676381ezvp6\":{\"gstin\":\"07AAECM4369H1ZA\",\"state\":\"Delhi\",\"city\":\"Delhi\",\"district\":\"Delhi\",\"address\":\"38, Okhla Idustrial Estate\\r\\nPhase -III, Delhi - 110020\",\"pan\":\"AAECM4369H\"}}', 'AAECM4369H', NULL, NULL, 'NA', 'NA', '', '12th Floor, 12G/1, Everest House,\r\n46C Chowringhee Road, Kolkata - 700071', 'Kolkata', 'West Bengal', 'Kolkata', '700071', '', NULL, 'active', '2026-05-13 13:03:00'),
(23, 'client', 'Group of Companies', 'OAP MEDIATECH PRIVATE LIMITED', '19AAACO5220D3ZT', '{\"row_1778678323687ahplo\":{\"gstin\":\"27AAACO5220D1ZY\",\"state\":\"Maharashtra\",\"city\":\"Mumbai\",\"district\":\"Mumbai\",\"address\":\"5th Floor, S.M. House 11,\\r\\nSahakar Road, Vile Parle East\\r\\nMumbai - 400057\",\"pan\":\"AAACO5220D\"}}', 'AAACO5220D', NULL, NULL, '', '', '', '137-GC Block, sector - III, ground Floor\r\nSaltlake City ( Near GD Island )\r\nKolkata - 700106', 'Kolkata', 'West Bengal', 'kolkata', '700106', '137-GC Block, sector - III, ground Floor\r\nSaltlake City ( Near GD Island )\r\nKolkata - 700106', NULL, 'active', '2026-05-13 13:20:36'),
(24, 'client', 'Group of Companies', 'PLATINUM COMMUNICATIONS PRIVATE LIMITED', '19AAECP2215R1Z0', '{\"row_1778741126552mscae\":{\"gstin\":\"07AAECP2215R1Z5\",\"state\":\"Delhi\",\"city\":\"Delhi\",\"district\":\"Delhi\",\"address\":\"38, Okhla Industrial Estate\\r\\nPhase III, New Delhi - 110020\",\"pan\":\"AAECP2215R\"},\"row_1778741126554xax2x\":{\"gstin\":\"27AAECP2215R1Z3\",\"state\":\"Maharashtra\",\"city\":\"Mumbai\",\"district\":\"Mumbai\",\"address\":\"402, 349 Business Point\\r\\nWestern Express Highway\\r\\nAndheri East - Mumbai - 400069\",\"pan\":\"AAECP2215R\"}}', 'AAECP2215R', NULL, NULL, '', '', '', '12th Floor, 12G/1, Everest House, \r\n46C Chowringhee Road,Kolkata - 700071', 'Kolkata', 'West Bengal', 'kolkata', '700071', '12th Floor, 12G/1, Everest House, \r\n46C Chowringhee Road,Kolkata - 700071', NULL, 'active', '2026-05-13 15:29:28'),
(25, 'client', 'Group of Companies', 'SIGNPOST INDIA LIMITED', '19AADCC3101C1ZE', '{\"row_17786865158746g4d7\":{\"gstin\":\"29AADCC3101C2ZC\",\"state\":\"Karnataka\",\"city\":\"Bangalore\",\"district\":\"Bangalore\",\"address\":\"BBMP No-18, Pritham Plaza\\r\\nYellamman Koil Street, Off Kensington Road,\\r\\nUlsoor, Bangalore -560008\",\"pan\":\"AADCC3101C\"},\"row_1778686646474nau1m\":{\"gstin\":\"27AADCC3101C1ZH\",\"state\":\"Maharashtra\",\"city\":\"Mumbai\",\"district\":\"Mumbai\",\"address\":\"202, Pressman House, Vile Parle\\r\\nNear Santacruz Airport Terminal\\r\\nMumbai - 400099\",\"pan\":\"AADCC3101C\"}}', 'AADCC3101C', NULL, NULL, '', '', '', 'Ergo Brilliant Tower,EP &amp; GP Block\r\nSector - V, Bidhan Nagar, Saltlake City\r\nKolkata - 700091', 'Kolkata', 'West Bengal', 'Kolkata', '700091', 'Ergo Brilliant Tower,EP &amp; GP Block\r\nSector - V, Bidhan Nagar, Saltlake City\r\nKolkata - 700091', NULL, 'active', '2026-05-13 15:38:58'),
(26, 'client', '', 'ENTRUST COMMUNICATIONS PRIVATE LIMITED', '19AABCE7669P1ZQ', '', 'AABCE7669P', NULL, NULL, '', '', '', '12th Floor,12G/1, Everest House, \r\n46C Chowringhee Road, Kolkata - 700071', 'Kolkata', 'West Bengal', 'kolkata', '700071', '12th Floor,12G/1, Everest House, \r\n46C Chowringhee Road, Kolkata - 700071', NULL, 'active', '2026-05-13 15:41:41'),
(27, 'client', 'Group of Companies', 'CWW SOLUTIONS PRIVATE LIMITED', '27AAJCC8934B1ZM', '{\"row_1778740080881b9yqu\":{\"gstin\":\"29AAJCC8934B1ZI\",\"state\":\"Karnataka\",\"city\":\"Bangalore\",\"district\":\"Bangalore\",\"address\":\"6th Cross, 1st Floor Cabin - 109\\r\\nPremises No- 372, Golden Square Wilson Garden , Bangalore - 560027\",\"pan\":\"AAJCC8934B\"},\"row_1778740263302u6dbp\":{\"gstin\":\"06AAJCC8934B1ZQ\",\"state\":\"Haryana\",\"city\":\"Gurgaon\",\"district\":\"Gurgaon\",\"address\":\"4th Floor, JMD Regent Arcade, MG Road\\r\\nGurgaon - 122001\",\"pan\":\"AAJCC8934B\"}}', 'AAJCC8934B', NULL, NULL, '', '', '', '1108, Hubtown Viva, W.E. Highway\r\nJogeshwari East, Mumbai - 400060', 'Mumbai', 'Maharashtra', 'Mumbai', '400060', '1108, Hubtown Viva, W.E. Highway\r\nJogeshwari East, Mumbai - 400060', NULL, 'active', '2026-05-14 06:34:20'),
(28, 'client', 'Group of Companies', 'MAX PUBLICITY &amp;amp; COMMUNICATION PRIVATE LIMITED', '06AAHCM3403H1ZS', '{\"row_1778740630983sqg1v\":{\"gstin\":\"27AAHCM3403H1ZO\",\"state\":\"Maharashtra\",\"city\":\"Mumbai\",\"district\":\"Mumbai\",\"address\":\"Phoenix Market City, 3rd Unit No-3B\\r\\n23-27, Phoenix Paragon Plaza,\\r\\nLal Bahadur Shastri Marg , Kurla West \\r\\nMumbai - 400070\",\"pan\":\"AAHCM3403H\"}}', 'AAHCM3403H', NULL, NULL, '', '', '', '304/305, 3rd Floor, Sun City Trade Tower\r\nGurgaon Road, Gurugram, Haryana -122016', 'Gurgaon', 'Haryana', 'Gurgaon', '122016', '304/305, 3rd Floor, Sun City Trade Tower\r\nGurgaon Road, Gurugram, Haryana -122016', NULL, 'active', '2026-05-14 06:37:02'),
(29, 'client', 'Proprietorship', 'Monalisa Arts', '19AKRPP4184F1ZK', '', 'AKRPP4184F', NULL, NULL, '', '', '', '24/A, Sriram Siromoni Road , Nayanee Apartment ( 1st Floor ) Berhampore - 742101', 'Berhampore', 'West Bengal', 'Murshidabad', '742103', '24/A, Sriram Siromoni Road , Nayanee Apartment ( 1st Floor ) Berhampore - 742101', NULL, 'active', '2026-05-14 06:53:44'),
(30, 'client', '', 'SYLVAN PLYBOARD (INDIA) LIMITED', '19AAHCS0099R1ZG', '', 'AAHCS0099R', NULL, NULL, '', '', '', 'NH-2, Delhi Road, Champasara, Chinnamore, Baidyabati, Hooghly - 712222', 'Hooghly', 'West Bengal', 'Hooghly', '712222', 'NH-2, Delhi Road, Champasara, Chinnamore, Baidyabati, Hooghly - 712222', NULL, 'active', '2026-05-14 06:56:44'),
(31, 'client', 'Group of Companies', 'TRIBES COMMUNICATION PRIVATE LIMITED', '06AAFCT2849C1ZH', '', 'AAFCT2849C', NULL, NULL, '', '', '', '734, Udyog Bihar, Phase - V,\r\nGurgaon - 122016', 'Gurgaon', 'Haryana', 'Gurgaon', '122001', '734, Udyog Bihar, Phase - V,\r\nGurgaon - 122016', NULL, 'active', '2026-05-14 06:58:37'),
(32, 'client', 'Private Limited', 'TRIMURTI PUBLICITY &amp; MARKETING PRIVATE LIMITED', '10AACCT5390D1ZR', '', 'AACCT5390D', NULL, NULL, '', '', '', '102, 1st Floor, APV Complex, \r\nwest Boring Canal Road, Patna - 800001', 'Patna', 'Bihar', 'Patna', '800001', '102, 1st Floor, APV Complex, \r\nwest Boring Canal Road, Patna - 800001', NULL, 'active', '2026-05-14 07:00:56'),
(33, 'client', 'Private Limited', 'WALK THE TALK COMMUNICATIONS PRIVATE LIMITED', '27AACCW9927D1Z2', '', 'AACCW9927D', NULL, NULL, '', '', '', '910, 9th Floor, The Summit Business Park,\r\nBehind Guru Nanak Petrol Pump, MV Road\r\nAndheri Kurla Road, Andheri East, Mumbai -400093', 'Mumbai', 'Maharashtra', 'Mumbai', '400093', '910, 9th Floor, The Summit Business Park,\r\nBehind Guru Nanak Petrol Pump, MV Road\r\nAndheri Kurla Road, Andheri East, Mumbai -400093', NULL, 'active', '2026-05-14 07:03:28'),
(34, 'client', 'Private Limited', 'WANSHAN MOBILES PRIVATE LIMITED', '19AACCO6914C1ZK', '', 'AACCO6914C', NULL, NULL, '', '', '', 'Block EP &amp; GP , 12th Floor, Godrej Genesis Building\r\nSector - V, Bidhan Nagar, Kolkata -  700091', 'Kolkata', 'West Bengal', 'kolkata', '700091', 'Block EP &amp; GP , 12th Floor, Godrej Genesis Building\r\nSector - V, Bidhan Nagar, Kolkata -  700091', NULL, 'active', '2026-05-14 07:07:23'),
(35, 'client', 'Private Limited', 'ORIENT GEMS AND ORNAMENTS PRIVATE LIMITED', '19AABCO1999E1Z6', '', 'AABCO1999E', NULL, NULL, '', '', '', 'Thana Road, Ukil Para, Raiganj \r\nWest Bengal - 733134', 'Raiganj', 'West Bengal', 'Uttar Dinajpur', '700020', 'Thana Road, Ukil Para, Raiganj \r\nWest Bengal - 733134', NULL, 'active', '2026-05-14 07:09:41'),
(36, 'client', 'Proprietorship', 'Brandwave', '19AIQPA1609R1ZR', '', 'AIQPA1609R', NULL, NULL, '', '', '', '3rd Floor, Flat C/3, EP-58/38\r\nNagendranath Road, Kolkata ,\r\nNorth 24 Parganas - 700028', 'North 24 Pargana', 'West Bengal', 'North 24 Pargana', '700028', '3rd Floor, Flat C/3, EP-58/38\r\nNagendranath Road, Kolkata ,\r\nNorth 24 Parganas - 700028', NULL, 'active', '2026-05-14 07:12:57'),
(37, 'client', 'Private Limited', 'BMEG PRIVATE LIMITED', '29AAKCB5971E1ZD', '', 'AAKCB5971E', NULL, NULL, '', '', '', '3rd , 4th Floor, No- 2,VRR Legacy\r\n1st Main Road, Jakkasandra , Bangalore\r\nKarnataka - 560034', 'Bangalore', 'Karnataka', 'Bangalore', '560103', '3rd , 4th Floor, No- 2,VRR Legacy\r\n1st Main Road, Jakkasandra , Bangalore\r\nKarnataka - 560034', NULL, 'active', '2026-05-14 07:16:45'),
(38, 'client', 'Proprietorship', 'Amaze Ads', '20BGNPG2245P1ZB', '', 'BGNPG2245P', NULL, NULL, '', '', '', '63/D, Road No-1, Ashoke Nagar,\r\nRanchi- 834001', 'Ranchi', 'Jharkhand', 'Ranchi', '834002', '63/D, Road No-1, Ashoke Nagar,\r\nRanchi- 834001', NULL, 'active', '2026-05-14 07:18:56'),
(39, 'client', 'Private Limited', 'DEBLAKSHMI HEALTHCARE PRIVATE LIMITED', '19AAGCD8148M1Z0', '', 'AAGCD8148M', NULL, NULL, '', '', '', 'Medical College Road, Singatala, Malda - 732101', 'Malda', 'West Bengal', 'Malda', '732101', 'Medical College Road, Singatala, Malda - 732101', NULL, 'active', '2026-05-14 07:21:29'),
(40, 'client', 'Group of Companies', 'IDEACAFE. AGENCY PRIVATE LIMITED', '27AAICB9440J1ZC', '', 'AAICB9440J', NULL, NULL, '', '', '', '01B-117, Raheja Platinum, Sag Baug,\r\nAndheri- Kurla Road, Marol ( Andheri East )\r\nMumbai - 400059', 'Mumbai', 'Maharashtra', 'Mumbai', '400059', '', NULL, 'active', '2026-05-14 07:24:13'),
(41, 'client', 'Proprietorship', 'RISING SUN', '19AIKPM5663M1ZH', '', 'AIKPM5663M', NULL, NULL, '', '', '', 'DL Roy Sarani, Mahananda Para\r\nSiliguri, Darjeeling - 734401', 'SILIGURI', 'West Bengal', 'Siliguri', '734401', 'DL Roy Sarani, Mahananda Para\r\nSiliguri, Darjeeling - 734401', NULL, 'active', '2026-05-14 07:27:22'),
(42, 'client', 'Private Limited', 'METRO RETAIL PRIVATE LIMITED', '19AAGCM0499Q1ZL', '', 'AAGCM0499Q', NULL, NULL, '', '', '', '97, GKW, Industrial Compound, Shed No-1\r\nAndul Road, Shibpur, Howrah - 711103', 'Howrah', 'West Bengal', 'Howrah', '711103', '', NULL, 'active', '2026-05-14 07:29:31'),
(43, 'client', 'Private Limited', 'H K JEWELS PRIVATE LIMITED', '19AACCH2454E1ZS', '', 'AACCH2454E', NULL, NULL, '', '', '', 'R/AA-39, Shop No- 6, Debanjali Apartment,\r\nRaghunathpur, Vip Road, Kolkata,\r\nNorth 24 Parganas.', 'Kolkata', 'West Bengal', 'North 24 Parganas', '700059', 'R/AA-39, Shop No- 6, Debanjali Apartment,\r\nRaghunathpur, Vip Road, Kolkata,\r\nNorth 24 Parganas.', NULL, 'active', '2026-05-14 07:37:37'),
(44, 'client', 'Private Limited', 'PRIMEPIXEL MEDIA PRIVATE LIMITED', '19AAPCP4067L1ZN', '', 'AAPCP4067L', NULL, NULL, 'NA', 'NA', 'NA@gmailcom', 'Floor No. 3rd, 1/G/4C, LP-5/44/2\r\nFL 312, Saltee Plaza Mall Road\r\nKolkata - 700080\r\nWest Bengal', 'Kolkata', 'West Bengal', 'West Bengal', '700080', 'Floor No. 3rd, 1/G/4C, LP-5/44/2\r\nFL 312, Saltee Plaza Mall Road\r\nKolkata - 700080\r\nWest Bengal', NULL, 'active', '2026-05-19 11:18:58'),
(45, 'vendor', 'Proprietorship', 'LIPIKA', '', '', '', NULL, NULL, '', '', '', 'M.G Road, New Market, Raiganj -733134', 'Raiganj', 'West Bengal', 'Uttar Dinajpur', '733134', 'M.G Road, New Market, Raiganj -733134', '', 'active', '2026-05-23 09:00:36');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `type` enum('receivable','payable') DEFAULT NULL,
  `partner_id` int(11) DEFAULT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `proposal_id` int(11) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `payment_mode` varchar(50) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approval_status` enum('pending_approval','approved','rejected') DEFAULT 'pending_approval',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `type`, `partner_id`, `invoice_id`, `proposal_id`, `amount`, `payment_mode`, `payment_date`, `transaction_id`, `notes`, `created_at`, `approval_status`, `approved_by`, `approved_at`, `rejection_reason`) VALUES
(1, 'receivable', 42, 1, NULL, 8800.00, 'NEFT', '2026-05-23', 'Only GST Paid', '', '2026-05-19 22:47:59', 'approved', NULL, NULL, NULL),
(2, 'receivable', 42, 1, NULL, 8880.00, 'NEFT', '2026-05-23', '', '', '2026-05-20 21:22:24', 'approved', NULL, NULL, NULL),
(3, 'receivable', 42, 1, NULL, 8000.00, 'NEFT', '2026-05-23', '', '', '2026-05-20 21:26:31', 'approved', NULL, NULL, NULL),
(5, 'receivable', 22, 3, NULL, 143910.00, 'NEFT', '2026-05-24', '', '', '2026-05-23 10:26:15', 'approved', NULL, NULL, NULL),
(7, 'receivable', 22, 3, NULL, 5656.00, 'Cash', '2026-05-24', '', '', '2026-05-24 20:58:34', 'approved', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `po_attachments`
--

CREATE TABLE `po_attachments` (
  `id` int(11) NOT NULL,
  `po_id` int(11) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `po_attachments`
--

INSERT INTO `po_attachments` (`id`, `po_id`, `filename`, `upload_date`) VALUES
(8, 46, '1780159066_Credit Note___Tribes.pdf', '2026-05-30 16:37:46');

-- --------------------------------------------------------

--
-- Table structure for table `po_items`
--

CREATE TABLE `po_items` (
  `id` int(11) NOT NULL,
  `po_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `site_id` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `days` int(11) DEFAULT NULL,
  `monthly_rate` decimal(15,2) DEFAULT NULL,
  `cost` decimal(15,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `po_items`
--

INSERT INTO `po_items` (`id`, `po_id`, `description`, `site_id`, `start_date`, `end_date`, `days`, `monthly_rate`, `cost`) VALUES
(62, 46, NULL, 44, '2026-05-01', '2026-05-30', 30, 20000.00, 20000.00),
(63, 46, NULL, 51, '2026-05-01', '2026-05-30', 30, 3000.00, 3000.00),
(64, 47, NULL, 66, '2026-05-01', '2026-05-30', 30, 20000.00, 20000.00),
(65, 47, NULL, 65, '2026-05-01', '2026-05-30', 30, 5000.00, 5000.00),
(66, 48, NULL, 56, '2026-05-01', '2026-05-30', 30, 3000.00, 3000.00),
(67, 49, NULL, 43, '2026-05-01', '2026-05-30', 30, 4000.00, 4000.00),
(68, 50, NULL, 47, '2026-05-01', '2026-05-30', 30, 10000.00, 10000.00),
(69, 51, NULL, 43, '2026-05-01', '2026-05-30', 30, 4000.00, 4000.00),
(70, 52, NULL, 47, '2026-05-01', '2026-05-30', 30, 10000.00, 10000.00),
(71, 53, NULL, 43, '2026-05-30', '2026-06-30', 30, 16000.00, 16000.00),
(72, 53, NULL, 44, '2026-05-30', '2026-06-30', 30, 45000.00, 45000.00);

-- --------------------------------------------------------

--
-- Table structure for table `proposals`
--

CREATE TABLE `proposals` (
  `id` int(11) NOT NULL,
  `proposal_number` varchar(50) DEFAULT NULL,
  `campaign_name` varchar(255) DEFAULT NULL,
  `client_id` int(11) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `total_days` int(11) DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT NULL,
  `tax_amount` decimal(15,2) DEFAULT NULL,
  `grand_total` decimal(15,2) DEFAULT NULL,
  `status` enum('draft','sent','confirmed','cancelled') DEFAULT 'draft',
  `approval_status` enum('pending_approval','approved','rejected') DEFAULT 'pending_approval',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `discounting_pct` decimal(5,2) DEFAULT 0.00,
  `pricing_pct` decimal(5,2) DEFAULT 0.00,
  `printing_cost` decimal(15,2) DEFAULT 0.00,
  `mounting_cost` decimal(15,2) DEFAULT 0.00,
  `ha_markup_amount` decimal(15,2) DEFAULT 0.00,
  `ta_markup_amount` decimal(15,2) DEFAULT 0.00,
  `total_sqft` float DEFAULT 0,
  `price_per_sqft` decimal(15,2) DEFAULT 0.00,
  `display_cost` decimal(15,2) DEFAULT 0.00,
  `media_type` int(255) DEFAULT NULL,
  `inventory_type` varchar(255) DEFAULT NULL,
  `light_type` varchar(255) DEFAULT NULL,
  `billing_gstin` varchar(255) DEFAULT NULL,
  `tax_type` enum('cgst_sgst','igst') DEFAULT 'igst'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `proposals`
--

INSERT INTO `proposals` (`id`, `proposal_number`, `campaign_name`, `client_id`, `contact_person`, `start_date`, `end_date`, `delivery_date`, `total_days`, `remark`, `total_amount`, `tax_amount`, `grand_total`, `status`, `approval_status`, `approved_by`, `approved_at`, `rejection_reason`, `created_by`, `created_at`, `discounting_pct`, `pricing_pct`, `printing_cost`, `mounting_cost`, `ha_markup_amount`, `ta_markup_amount`, `total_sqft`, `price_per_sqft`, `display_cost`, `media_type`, `inventory_type`, `light_type`, `billing_gstin`, `tax_type`) VALUES
(6, 'PR-20260530-6262', 'HUL', 20, 'NA', '2026-05-01', '2026-05-30', NULL, 30, '', 204000.00, 36720.00, 240720.00, 'confirmed', 'approved', NULL, NULL, NULL, 1, '2026-05-30 15:46:26', 0.00, 0.00, 0.00, 0.00, 0.00, 44000.00, 3100, 60.65, 188000.00, 0, 'TA', '', '27AABCP1311M2ZK', 'igst'),
(7, 'PR-20260530-4401', 'HUL', 42, '', '2026-05-21', '2026-05-13', NULL, NULL, '', 16000.00, 2880.00, 18880.00, 'confirmed', 'approved', NULL, NULL, NULL, 1, '2026-05-30 18:21:15', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 400, 40.00, 16000.00, 0, 'all', '', '', 'igst'),
(8, 'PR-20260530-2046', 'jhg', 16, '', '0000-00-00', '0000-00-00', NULL, NULL, '', 16000.00, 2880.00, 18880.00, 'confirmed', 'approved', NULL, NULL, NULL, 1, '2026-05-30 18:25:21', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 400, 40.00, 16000.00, 0, 'all', '', '', 'igst');

-- --------------------------------------------------------

--
-- Table structure for table `proposal_items`
--

CREATE TABLE `proposal_items` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `sale_rate` decimal(10,2) DEFAULT NULL,
  `purchase_rate` decimal(10,2) DEFAULT NULL,
  `margin_pct` decimal(5,2) DEFAULT NULL,
  `days` int(11) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `selected_image` varchar(255) DEFAULT NULL,
  `printing_vendor_id` varchar(255) DEFAULT NULL,
  `printing_rate` varchar(255) DEFAULT NULL,
  `printing_amount` int(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `proposal_items`
--

INSERT INTO `proposal_items` (`id`, `proposal_id`, `site_id`, `sale_rate`, `purchase_rate`, `margin_pct`, `days`, `amount`, `selected_image`, `printing_vendor_id`, `printing_rate`, `printing_amount`) VALUES
(17, 6, 1, 40000.00, 40000.00, 0.00, NULL, 40000.00, '1778407185_1_Picture1.png', NULL, '0', 0),
(18, 6, 2, 30000.00, 30000.00, 0.00, NULL, 30000.00, '1778504569_2_9. Malda Station Road Jhaljhalia Market.20X30. FL..jpeg', NULL, '0', 0),
(19, 6, 44, 45000.00, 45000.00, 0.00, NULL, 45000.00, '1778512687_44_2. Malda Rabindra Avenue.40X20. FL (1).jpeg', NULL, '0', 0),
(20, 6, 51, 12000.00, 4000.00, 200.00, NULL, 12000.00, '1778745732_51_Buniadpur Bus Stand.20X20.. (3).jpeg', NULL, '0', 0),
(21, 6, 66, 45000.00, 20000.00, 125.00, NULL, 45000.00, '1779893221_66_Berhampore Church More.20X10.. (6).jpeg', NULL, '0', 0),
(22, 6, 65, 16000.00, 5000.00, 220.00, NULL, 16000.00, '1779892100_65_Berhampore Bus Stand.20X15.. (2).jpeg', NULL, '0', 0),
(23, 6, 56, 16000.00, 3000.00, 433.33, 30, 16000.00, '1778746931_56_Malda Rabindra Bhavan More.20X20.. (1).jpeg', NULL, NULL, NULL),
(24, 7, 43, 16000.00, 16000.00, 0.00, NULL, 16000.00, '1778512577_43_1. Malda 320 More.20X20.NL..jpeg', NULL, '0', 0),
(25, 8, 43, 16000.00, 16000.00, 0.00, NULL, 16000.00, '1778512577_43_1. Malda 320 More.20X20.NL..jpeg', NULL, '0', 0);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `project_id` varchar(50) DEFAULT NULL,
  `campaign_id` int(11) DEFAULT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `type` enum('rental','printing','adhoc','system','direct') DEFAULT 'direct',
  `po_number` varchar(50) DEFAULT NULL,
  `po_date` date DEFAULT NULL,
  `payment_due_date` date DEFAULT NULL,
  `po_amount` decimal(15,2) DEFAULT NULL,
  `cgst_amount` decimal(15,2) DEFAULT 0.00,
  `sgst_amount` decimal(15,2) DEFAULT 0.00,
  `igst_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) DEFAULT NULL,
  `status` enum('draft','approved','paid') DEFAULT 'draft',
  `approval_status` enum('pending_approval','approved','rejected') DEFAULT 'pending_approval',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `vendor_invoice_no` varchar(50) DEFAULT NULL,
  `vendor_invoice_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `campaign_name` varchar(255) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `brand_name` varchar(255) NOT NULL,
  `external_po` varchar(255) DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `client_tax_order` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `project_id`, `campaign_id`, `vendor_id`, `entity_id`, `customer_id`, `employee_id`, `type`, `po_number`, `po_date`, `payment_due_date`, `po_amount`, `cgst_amount`, `sgst_amount`, `igst_amount`, `total_amount`, `status`, `approval_status`, `approved_by`, `approved_at`, `rejection_reason`, `vendor_invoice_no`, `vendor_invoice_date`, `created_at`, `campaign_name`, `remarks`, `brand_name`, `external_po`, `contact_person`, `client_tax_order`) VALUES
(46, NULL, 28, 3, NULL, 20, 1, 'system', 'BPO-20260530-317', '2026-05-30', NULL, 23000.00, 2070.00, 2070.00, 0.00, 27140.00, 'approved', 'approved', NULL, NULL, NULL, '123', '2026-05-30', '2026-05-30 16:25:11', 'Relaxo', NULL, '', NULL, NULL, NULL),
(47, NULL, 28, 7, NULL, 20, 1, 'system', 'BPO-20260530-804', '2026-05-30', NULL, 25000.00, 2250.00, 2250.00, 0.00, 29500.00, 'approved', 'approved', NULL, NULL, NULL, NULL, NULL, '2026-05-30 16:26:06', 'Relaxo', NULL, '', NULL, NULL, NULL),
(48, NULL, 28, 11, NULL, 20, 1, 'system', 'BPO-20260530-481', '2026-05-30', NULL, 3000.00, 0.00, 0.00, 0.00, 3000.00, 'approved', 'approved', NULL, NULL, NULL, NULL, NULL, '2026-05-30 16:26:58', 'Relaxo', NULL, '', NULL, NULL, NULL),
(49, NULL, NULL, 3, NULL, 22, 1, 'direct', 'PO-20260530-204', '2026-05-30', NULL, 4000.00, 360.00, 360.00, 0.00, 4720.00, 'approved', 'approved', NULL, NULL, NULL, NULL, NULL, '2026-05-30 17:08:23', 'HUL', '', '', '', NULL, NULL),
(50, NULL, NULL, 10, NULL, 22, 1, 'direct', 'PO-20260530-441', '2026-05-30', NULL, 10000.00, 900.00, 900.00, 0.00, 11800.00, 'approved', 'approved', NULL, NULL, NULL, NULL, NULL, '2026-05-30 17:08:23', 'HUL', '', '', '', NULL, NULL),
(51, NULL, 29, 3, NULL, 22, 1, 'system', 'BPO-20260530-993', '2026-05-30', NULL, 4000.00, 360.00, 360.00, 0.00, 4720.00, 'approved', 'approved', NULL, NULL, NULL, NULL, NULL, '2026-05-30 17:09:26', 'HUL', NULL, '', NULL, NULL, NULL),
(52, NULL, 29, 10, NULL, 22, 1, 'system', 'BPO-20260530-336', '2026-05-30', NULL, 10000.00, 900.00, 900.00, 0.00, 11800.00, 'approved', 'approved', NULL, NULL, NULL, NULL, NULL, '2026-05-30 17:09:32', 'HUL', NULL, '', NULL, NULL, NULL),
(53, NULL, NULL, 3, NULL, 4, 1, 'direct', 'PO-20260530-277', '2026-05-30', NULL, 61000.00, 0.00, 0.00, 10980.00, 71980.00, 'approved', 'approved', NULL, NULL, NULL, NULL, NULL, '2026-05-30 18:34:20', 'HUL', '', '', '', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL,
  `role` enum('admin','manager','sales','staff') NOT NULL,
  `module_key` varchar(50) NOT NULL,
  `can_view` tinyint(1) DEFAULT 0,
  `can_add` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role`, `module_key`, `can_view`, `can_add`, `can_edit`, `can_delete`) VALUES
(1, 'manager', 'clients', 1, 1, 0, 0),
(2, 'manager', 'vendors', 1, 1, 0, 0),
(3, 'manager', 'inventory', 1, 1, 0, 0),
(4, 'manager', 'proposals', 1, 1, 1, 0),
(5, 'manager', 'bookings', 1, 1, 1, 0),
(6, 'manager', 'financials', 1, 1, 0, 0),
(7, 'manager', 'users', 0, 0, 0, 0),
(8, 'sales', 'clients', 0, 0, 0, 0),
(9, 'sales', 'vendors', 0, 0, 0, 0),
(10, 'sales', 'inventory', 0, 0, 0, 0),
(11, 'sales', 'proposals', 0, 0, 0, 0),
(12, 'sales', 'bookings', 0, 0, 0, 0),
(13, 'sales', 'financials', 0, 0, 0, 0),
(14, 'sales', 'users', 0, 0, 0, 0),
(15, 'staff', 'clients', 0, 0, 0, 0),
(16, 'staff', 'vendors', 0, 0, 0, 0),
(17, 'staff', 'inventory', 0, 0, 0, 0),
(18, 'staff', 'proposals', 0, 0, 0, 0),
(19, 'staff', 'bookings', 0, 0, 0, 0),
(20, 'staff', 'financials', 0, 0, 0, 0),
(21, 'staff', 'users', 0, 0, 0, 0),
(22, 'manager', 'dashboard', 0, 0, 0, 0),
(30, 'sales', 'dashboard', 0, 0, 0, 0),
(38, 'staff', 'dashboard', 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'company_name', 'SUDHA CREATIVE', '2026-05-13 20:17:32'),
(2, 'company_address', 'Mahanada Pally.\r\nJhaljhalia\r\nMalda - 732102', '2026-05-13 20:17:32'),
(3, 'company_city', 'Malda.  West Bengal ', '2026-05-13 20:17:32'),
(4, 'company_gstin', '19AHRPT4740Q1Z6', '2026-05-13 20:17:32'),
(5, 'company_phone', '8653444413', '2026-05-13 20:17:32'),
(6, 'company_email', 'sudhacreativemalda@gmail.com', '2026-05-13 20:17:32'),
(7, 'company_logo', 'logo_1778703452_WhatsApp Image 2026-04-13 at 7.36.25 PM.jpeg', '2026-05-13 20:17:32'),
(8, 'company_signature', 'sig_1778400384_Screenshot.png', '2026-05-10 08:06:24'),
(9, 'company_pan', 'AHRPT4740Q', '2026-05-13 20:17:32'),
(10, 'company_letterhead', 'lh_1779309994_WhatsApp Image 2026-05-21 at 2.14.54 AM.jpeg', '2026-05-20 20:46:34'),
(15, 'po_terms', '1. Flex mounting will be Free of Cost d\r\n2. Kindly take proper care while mounting the vinyl & make sure there should be no wrinkles seen on the above hoarding. In case of execution being not proper resulting in poor quality of Flex/Vinyl mounting the same should be remounted free of cost within 24 hours. Penalty of display charges will be deducted on prorata basis for every day delayed.\r\n3. In case of non illumination of Lit sites display charge will be deducted on prorata basis for everyday of non illuminous.\r\n4. The media should be maintained by the contractor in good condition throughout the contract period.\r\n5. We reserves the right to discontinue or cancel the booking midway of display period.\r\n6. We reserves the right to accept or reject the quality of the job executed.\r\n7. Please arrange to send four sets of High Resolution Photographs (Two Long view & Two Close view).\r\n8. The contract period will start from the date of display.\r\n9. For the print jobs the printer will have to give free replacement of print for any prints found fading or not as per given specification immediately.\r\n10. Any deviation from the specification given above will not be payable.\r\n11. All Bills should carry our purchase order copy.\r\n12. Required 2 Nos Bill For Payment Processing.\r\n13. Raise Your Bill In favor Of: SUDHA CREATIVE, Mahanandapally, Jhaljhalia, Malda-732102\r\n14. GSTIN Number : 19AHRPT4740Q1Z6\r\n15. State Code : 19\r\n16. State Name : West Bengal\r\n17. Place of supply : West Bengal', '2026-05-22 09:49:25'),
(16, 'po_important_note', '', '2026-05-13 20:00:06');

-- --------------------------------------------------------

--
-- Table structure for table `sites`
--

CREATE TABLE `sites` (
  `id` int(11) NOT NULL,
  `site_code` varchar(50) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `genre` varchar(100) DEFAULT NULL,
  `width` float DEFAULT NULL,
  `height` float DEFAULT NULL,
  `facing` varchar(50) DEFAULT NULL,
  `light_type` enum('BL','NL','FL') DEFAULT 'NL',
  `hsn_code` varchar(10) DEFAULT '998366',
  `grade` enum('A','B','C') DEFAULT 'B',
  `sqft` float GENERATED ALWAYS AS (`width` * `height`) STORED,
  `owner_type` enum('HA','TA') DEFAULT 'HA',
  `vendor_id` int(11) DEFAULT NULL,
  `vendor_gst` varchar(20) DEFAULT NULL,
  `card_rate` decimal(10,2) DEFAULT NULL,
  `purchase_rate` decimal(10,2) DEFAULT NULL,
  `status` enum('available','booked','maintenance') DEFAULT 'available',
  `available_from` date DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `area` varchar(255) DEFAULT NULL,
  `district` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sites`
--

INSERT INTO `sites` (`id`, `site_code`, `name`, `location`, `city`, `state`, `type`, `genre`, `width`, `height`, `facing`, `light_type`, `hsn_code`, `grade`, `owner_type`, `vendor_id`, `vendor_gst`, `card_rate`, `purchase_rate`, `status`, `available_from`, `image_url`, `latitude`, `longitude`, `area`, `district`) VALUES
(1, 'SC001', 'Kani More', 'Above WOW Momo', 'Malda', NULL, 'Billboard', NULL, 40, 20, 'Fcing Station', 'FL', '998366', 'A', 'HA', NULL, '', 40000.00, 40000.00, 'available', '2026-05-10', NULL, 25.01095900, 88.13541800, 'Malda', 'Malda'),
(2, 'SC002', 'Jhaljhalia Market', 'Above Gol Panghar', 'Malda', NULL, 'Billboard', NULL, 20, 30, 'Fcing Station', 'FL', '998366', 'A', 'HA', NULL, '', 30000.00, 30000.00, 'available', '2026-05-10', NULL, 25.01302000, 88.13411200, 'Malda', 'Malda'),
(3, 'ALK001', 'Ranindra Avenue, Rathbari Flyover', 'Above Saha Textile', 'Malda', NULL, 'Billboard', NULL, 40, 20, 'ITI More', 'FL', '998366', 'A', 'TA', 3, '', 40000.00, 40000.00, 'available', '2026-05-10', NULL, 99.99999999, 999.99999999, 'Malda', 'Malda'),
(5, 'ANA001', 'Rabindra Avenue', 'Opp Hero Shorom', 'Malda', NULL, 'Billboard', NULL, 20, 20, 'ITI More', 'NL', '998366', 'A', 'TA', 2, '', 20000.00, 20000.00, 'available', '2026-05-10', NULL, 25.00810000, 88.21020000, 'Malda', 'Malda'),
(6, 'SC003', 'Mokdampur, LIC More', 'Above EBM Jaltank', 'Malda', NULL, 'Billboard', NULL, 30, 20, 'Barlow School', 'NL', '998366', 'A', 'HA', NULL, '', 30000.00, 30000.00, 'available', '2026-05-10', NULL, 24.99688300, 88.14338000, 'Malda', 'Malda'),
(7, 'SC004', 'Kani More, Infort of Pantaloons', 'Opp Pantaloons Showroom', 'Malda', NULL, 'Unipole', NULL, 20, 10, 'Kani More, Ratbari', 'FL', '998366', 'A', 'HA', NULL, '', 55000.00, 55000.00, 'available', '2026-05-11', NULL, 25.00939100, 88.13537000, 'Station Road', 'Malda'),
(8, 'SC005', 'Mokdampur, Atul Market', 'Beside LIC Main Branch', 'Malda', NULL, 'Billboard', NULL, 50, 10, 'Badh Road', 'NL', '998366', 'A', 'HA', NULL, '', 40000.00, 40000.00, 'available', '2026-05-11', NULL, 24.99741200, 88.14430600, 'Atul Market', 'Malda'),
(9, 'SC006', 'Rabindra Avenue', 'Beside Malda College wall', 'Malda', NULL, 'Billboard', NULL, 20, 20, 'Rathbari', 'FL', '998366', 'A', 'HA', NULL, '', 25000.00, 25000.00, 'available', '2026-05-11', NULL, 25.00183900, 88.13817600, 'Rabindra Avenue', 'Malda'),
(10, 'SC007', 'Rabindra Avenue', 'Beside Hero Showroom', 'Malda', NULL, 'Billboard', NULL, 20, 20, 'Clock Tower', 'NL', '998366', 'A', 'HA', NULL, '', 25000.00, 25000.00, 'available', '2026-05-11', NULL, 25.00181000, 88.13809600, 'Rabindra Avenue', 'Malda'),
(11, 'SC008', 'Rathbari More', 'Beside Railway Subway', 'Malda', NULL, 'Billboard', NULL, 40, 15, 'Bichitra Market', 'FL', '998366', 'A', 'HA', NULL, '', 40000.00, 40000.00, 'available', '2026-05-11', NULL, NULL, NULL, 'Rathbari', 'Malda'),
(12, 'SC009', 'Station Compound', 'Opp Railway Park', 'Malda', NULL, 'Unipole', NULL, 20, 10, 'MLDT Station, Kani More', 'FL', '998366', 'A', 'HA', NULL, '', 50000.00, 50000.00, 'available', '2026-05-11', NULL, 25.01507500, 88.13279500, 'Railway Colony', 'Malda'),
(13, 'SC010', 'Kani More', 'Beside Haribasartala Ground', 'Malda', NULL, 'Gantry', NULL, 42, 10, 'Kani More &amp; Jhaljhalia Market', 'FL', '998366', 'A', 'HA', NULL, '', 80000.00, 80000.00, 'available', '2026-05-11', NULL, 25.01281200, 88.13418400, 'Jhaljhalia', 'Malda'),
(14, 'SC011', 'Station Compound', 'Opp Railway Park', 'Malda', NULL, 'Billboard', NULL, 20, 20, 'MLDT Station', 'NL', '998366', 'A', 'HA', NULL, '', 18000.00, 18000.00, 'available', '2026-05-11', NULL, 25.01521300, 88.13270300, 'Malda Station Compound', 'Malda'),
(15, 'SC012', 'Station Road', 'Atop of Subham Garment', 'Malda', NULL, 'Billboard', NULL, 20, 30, 'MLDT Station', 'FL', '998366', 'A', 'HA', NULL, '', 35000.00, 35000.00, 'available', '2026-05-11', NULL, 25.01216800, 88.13472300, 'Jaljhalia, Kani More', 'Malda'),
(16, 'SC013', 'Sukanto More (420 More)', 'Atop of Saptarishi BLDG', 'Malda', NULL, 'Billboard', NULL, 60, 30, 'M K Road', 'FL', '998366', 'A', 'HA', NULL, '', 70000.00, 70000.00, 'available', '2026-05-11', NULL, 25.01103800, 88.14075000, 'Sukanto More', 'Malda'),
(17, 'SC014', 'Mangalbari Railgate', 'Opp. WEBELIT Park', 'Malda', NULL, 'Billboard', NULL, 20, 20, 'Jhagra More', 'NL', '998366', 'A', 'HA', NULL, '', 18000.00, 18000.00, 'available', '2026-05-11', NULL, NULL, NULL, 'Old Malda', 'Malda'),
(18, 'SC015', 'Bus Stand', 'Above Orient Jewllers', 'Sujapur / Kaliachawk', NULL, 'Billboard', NULL, 35, 20, 'Kaliachawk', 'NL', '998366', 'A', 'HA', NULL, '', 35000.00, 35000.00, 'available', '2026-05-11', NULL, 24.91278200, 88.08774000, 'Sujapur', 'Malda'),
(19, 'SC016', 'Sujapur / Kaliachawk', 'Above Orient Jewllers', 'Sujapur', NULL, 'Billboard', NULL, 20, 20, 'Kaliachawk', 'NL', '998366', 'A', 'HA', NULL, '', 18000.00, 18000.00, 'available', '2026-05-11', NULL, 24.91278200, 88.68774000, 'Sujapur', 'Malda'),
(20, 'SC017', 'Kani More', 'Above EBM Heath Centre', 'Malda', NULL, 'Billboard', NULL, 42, 20, 'Sukanto More', 'NL', '998366', 'A', 'HA', NULL, '', 35000.00, 35000.00, 'available', '2026-05-11', NULL, 25.01077000, 88.13568600, 'Kani More', 'M'),
(21, 'SC018', 'Bus Stand', 'Infromt Of M Bazar', 'Samsi', NULL, 'Billboard', NULL, 20, 20, 'Gasiram More', 'NL', '998366', 'A', 'HA', NULL, '', 18000.00, 18000.00, 'available', '2026-05-11', NULL, 25.27845200, 88.01429200, 'Samsi', 'Malda'),
(22, 'SC019', 'Bus Stand', 'Chachal Dakhin Para Duga Puja Committee', 'Chachal', NULL, 'Billboard', NULL, 20, 20, 'Bus Stand', 'NL', '998366', 'A', 'HA', NULL, '', 18000.00, 18000.00, 'available', '2026-05-11', NULL, 25.38970500, 88.00929400, 'Chachal', 'Malda'),
(23, 'SC020', 'Bus Stand', 'Atop of Traffic Police Control Room Mayna', 'Gazole, Mayna', NULL, 'Billboard', NULL, 20, 20, 'Raiganj', 'NL', '998366', 'A', 'HA', NULL, '', 18000.00, 18000.00, 'available', '2026-05-11', NULL, 25.28872800, 88.16206500, 'Mayna Check Post', 'Malda'),
(24, 'SC021', 'Bus Stand', 'Atop of Traffic Police Control Room Mayna', 'Gazole, Mayna', NULL, 'Billboard', NULL, 20, 20, 'Malda', 'NL', '998366', 'A', 'HA', NULL, '', 18000.00, 18000.00, 'available', '2026-05-11', NULL, 25.09007700, 999.99999999, 'Mayna Check Post', 'Malda'),
(25, 'SC022', 'Mangalbari, Jhagra More', 'Atop of Traffic Police Control Room Jhagra More', 'Malda', NULL, 'Billboard', NULL, 40, 20, 'Siliguri', 'NL', '998366', 'A', 'HA', NULL, '', 35000.00, 35000.00, 'available', '2026-05-11', NULL, 25.02866300, 88.16096700, 'Old Malda, Mangalbari', 'Malda'),
(26, 'SC023', 'Mangalbari, Jhagra More', 'Atop of Traffic Police Control Room Jhagra More', 'Malda', NULL, 'Billboard', NULL, 40, 20, 'Kolkata', 'NL', '998366', 'A', 'HA', NULL, '', 35000.00, 35000.00, 'available', '2026-05-11', NULL, 25.02866700, 88.13596570, 'Old Malda, Mangalbari', 'Malda'),
(27, 'SD001', 'Sadarghat More', 'Front of Market', 'Malda', NULL, 'Billboard', NULL, 20, 20, 'Mangalbari', 'NL', '998366', 'A', 'TA', 6, '', 16000.00, 16000.00, 'available', '2026-05-11', NULL, 25.00836700, 88.15608700, '', 'Malda'),
(28, 'SD002', 'Mangal Bari Market', 'Beside Petrol Pump', 'Malda', NULL, 'Billboard', NULL, 40, 20, 'Gazole', 'FL', '998366', 'A', 'TA', 6, '', 45000.00, 10000.00, 'available', '2026-05-28', NULL, 25.02009800, 88.15111700, 'Mangal Bari', 'Malda'),
(29, 'SC0024', 'Badhapukur More', 'Atop of Traffic Police Control Room Badhapukur More', 'Malda', NULL, 'Billboard', NULL, 40, 20, 'Kolkata', 'FL', '998366', 'A', 'HA', NULL, '', 40000.00, 40000.00, 'available', '2026-05-11', NULL, 25.02866300, 88.16096700, 'Malda, On NH 34', 'Malda'),
(30, 'SC025', 'Badhapukur More', 'Atop of Traffic Police Control Room Badhapukur More', 'Malda', NULL, 'Billboard', NULL, 40, 20, 'Siliguri, Malda', 'FL', '998366', 'A', 'HA', NULL, '', 40000.00, 40000.00, 'available', '2026-05-11', NULL, 25.04263100, 88.14651200, 'Malda, On NH 34', 'Malda'),
(31, 'RJ001', 'Raj Hotel More', 'Front of Axis Bank', 'Malda', NULL, 'Gantry', NULL, 33, 8, 'Raj Hotel', 'FL', '998366', 'A', 'TA', 11, '', 75000.00, 75000.00, 'available', '2026-05-11', NULL, 24.99986000, 88.14203100, '', 'Malda'),
(32, 'RJ002', 'Post Office Foara More', 'Opp Circuit House', 'Malda', NULL, 'Billboard', NULL, 20, 20, 'Circuit House', 'NL', '998366', 'A', 'TA', 11, '', 16000.00, 16000.00, 'available', '2026-05-11', NULL, 24.99970800, 88.14498900, '', 'Malda'),
(33, 'SC026', 'Rabindra Bhawan More', 'Opp Gour Banga University Main Gate', 'Malda', NULL, 'Billboard', NULL, 20, 20, 'Gour Banga Unversity', 'NL', '998366', 'A', 'HA', NULL, '', 18000.00, 18000.00, 'available', '2026-05-11', NULL, 24.98417400, 88.13724200, 'Rabindra Bhawan', 'Malda'),
(34, 'RS001', 'Samsi Rail Gate', 'Above Rail Gate', 'Samsi', NULL, 'Gantry', NULL, 40, 6, 'Ratua', 'NL', '998366', 'A', 'TA', 9, '', 30000.00, 30000.00, 'available', '2026-05-11', NULL, 25.27784300, 88.00637300, '', 'Malda'),
(35, 'SC027', 'Medical Collge', 'Opp Malda Medical College', 'Malda', NULL, 'Billboard', NULL, 20, 20, 'Rabindra Bhawan', 'NL', '998366', 'A', 'HA', NULL, '', 18000.00, 18000.00, 'available', '2026-05-11', NULL, 24.99490200, 88.13635800, 'Medical College', 'Malda'),
(36, 'SA001', 'Chowrangee More', 'Beside Pach Tala Masjod', 'Kaliachawk', NULL, 'Billboard', NULL, 40, 20, 'Police Station', 'FL', '998366', 'A', 'TA', 19, '', 45000.00, 45000.00, 'available', '2026-05-11', NULL, 24.86121500, 88.01845000, '', 'Malda'),
(37, 'SC028', 'Medical Collge', 'Opp. Malda Medical Collge', 'Malda', NULL, 'Billboard', NULL, 20, 20, 'Rathbari', 'NL', '998366', 'A', 'HA', NULL, '', 18000.00, 18000.00, 'available', '2026-05-11', NULL, 24.99552100, 88.13625100, 'Medical College', 'Malda'),
(38, 'SA002', 'College Road', 'Beside BRDC Building', 'Malda', NULL, 'Billboard', NULL, 20, 20, 'Medical College', 'FL', '998366', 'A', 'TA', 19, '', 25000.00, 25000.00, 'available', '2026-05-11', NULL, 24.99879000, 88.13568500, '', 'Malda'),
(39, 'SC029', 'Bus Stand', 'Opp. Prangan Market', 'Berhampore', NULL, 'Billboard', NULL, 40, 30, 'Parngan Market', 'NL', '998366', 'A', 'HA', NULL, '', 45000.00, 45000.00, 'available', '2026-05-11', NULL, 24.09796200, 88.25846600, 'Bus Stand', 'Mursidabad'),
(40, 'SC030', 'Somobika More', 'Opp. Somobika', 'Berhampore', NULL, 'Billboard', NULL, 20, 10, 'Laldighi', 'NL', '998366', 'A', 'HA', NULL, '', 5000.00, 5000.00, 'available', '2026-05-11', NULL, 24.09743300, 88.25559100, 'Somobika More', 'Mursidabad'),
(41, 'SA003', 'Amrity Bus Stand', 'Beside Shiv mandir', 'Amrity', NULL, 'Billboard', NULL, 15, 20, 'Malda', 'NL', '998366', 'A', 'TA', 19, '', 10000.00, 10000.00, 'available', '2026-05-11', NULL, 25.02854000, 88.04801200, '', 'Malda'),
(42, 'ANA002', '420 More', 'Front Of Style Bazar', 'Malda', NULL, 'Unipole', NULL, 20, 10, '420 More', 'NL', '998366', 'A', 'TA', 2, '', 26000.00, 11000.00, 'available', '2026-05-14', NULL, 25.01026600, 88.14034000, '', 'Malda'),
(43, 'AL001', '320 More', 'Beside Swastik Lodge', 'Malda', NULL, 'Billboard', NULL, 20, 20, '420 More', 'NL', '998366', 'A', 'TA', 3, '', 16000.00, 16000.00, 'available', '2026-05-11', NULL, 25.01136600, 88.14129200, '', 'Malda'),
(44, 'AL002', 'Rabindra Avenue', 'Above EBM Market', 'Malda', NULL, 'Billboard', NULL, 40, 20, 'Rathbari', 'FL', '998366', 'A', 'TA', 3, '', 45000.00, 45000.00, 'available', '2026-05-11', NULL, 25.00276100, 88.13629200, '', 'Malda'),
(45, 'SH001', 'Bus Stand', 'Above PNB', 'Gazole', NULL, 'Billboard', NULL, 50, 30, 'Balurghat', 'NL', '998366', 'A', 'TA', 12, '', 60000.00, 60000.00, 'available', '2026-05-11', NULL, 25.21722800, 88.19605000, '', 'Malda'),
(46, 'SC032', 'Gazole, Mayna Check Post. NH 34', 'Mayna Check Post', 'Malda', NULL, 'Traffic Signal Pole Kiosks', NULL, 4, 6, 'Siliguri, Kolkata', 'BL', '998366', 'A', 'HA', NULL, '', 40000.00, 40000.00, 'available', '2026-05-11', NULL, 25.28913300, 88.16206500, 'Mayna Check Post', 'Malda'),
(47, 'DM001', '7th Mile Mayfair', '7th Mile', 'GANGTOK', NULL, 'Billboard', NULL, 15, 9, '7th Mile', 'NL', '998366', 'A', 'TA', 10, '', 20000.00, 20000.00, 'available', '2026-05-11', NULL, 27.30311400, 88.58786400, '', 'Gangtok'),
(48, 'MA001', 'Bus Stand', 'Above Municipality Building', 'Berhampore', NULL, 'Billboard', NULL, 30, 10, 'Opp Prangon Market', 'NL', '998366', 'A', 'TA', 7, '', 16000.00, 16000.00, 'available', '2026-05-11', NULL, 24.09760700, 88.25819300, 'Parngan Market', 'Murshidabad'),
(49, 'VS001', 'Badhapukur More', 'Bypass', 'Malda', NULL, 'Billboard', NULL, 4, 6, 'Badhapukur', 'NL', '998366', 'A', 'TA', 8, '', 45000.00, 45000.00, 'available', '2026-05-11', NULL, 24.96251000, 88.12413600, '', 'Malda'),
(51, 'AL003', 'Buniadpur Bus Stand', 'Bus Stand', 'Buniadpur', NULL, 'Billboard', NULL, 20, 20, 'Balurghat', 'NL', '998366', 'A', 'TA', 3, '', 12000.00, 4000.00, 'available', '2026-05-14', NULL, 25.39069600, 88.39740900, '', 'Dakshin Dinajpur'),
(52, 'SA004', 'Main Road', 'Beside Hospital', 'Buniadpur', NULL, 'Billboard', NULL, 20, 25, 'Balurghat', 'NL', '998366', 'A', 'TA', 19, '', 8000.00, 5000.00, 'available', '2026-05-14', NULL, 25.38751200, 88.39645000, '', 'Dakshin Dinajpur'),
(53, 'AL004', 'Buniadpur Main Market', 'Bus Stand', 'Buniadpur', NULL, 'Billboard', NULL, 20, 20, 'Malda', 'NL', '998366', 'A', 'TA', 3, '', 6500.00, 3000.00, 'available', '2026-05-14', NULL, 25.39081200, 88.39781100, '', 'Dakshin Dinajpur'),
(54, 'SC031', 'Sukanto More (420 More)', 'Saptarshi Building', 'Malda', NULL, 'Billboard', NULL, 60, 10, 'MK Road', 'FL', '998366', 'A', 'HA', NULL, '', 35000.00, 20000.00, 'available', '2026-05-14', NULL, 25.01129700, 88.14135600, '', 'Malda'),
(55, 'ANA003', 'Medical College Road', 'St. Xavier School', 'Malda', NULL, 'Billboard', NULL, 20, 20, 'Rathbari', 'NL', '998366', 'A', 'TA', 2, '', 7000.00, 3000.00, 'available', '2026-05-14', NULL, 24.98867500, 88.11374030, '', 'Malda'),
(56, 'RJ003', 'Rabindra Bhavan More', 'Front Of Yamaha Showroom', 'Malda', NULL, 'Billboard', NULL, 20, 20, 'Kolkata', 'NL', '998366', 'A', 'TA', 11, '', 16000.00, 3000.00, 'available', '2026-05-14', NULL, 24.97946500, 88.13357500, '', 'Malda'),
(57, 'ANA004', 'NBSTC Bus Stand', 'NBSTC Bus Stand', 'Balurghat', NULL, 'Billboard', NULL, 20, 20, 'Bus Stand', 'NL', '998366', 'A', 'TA', 2, '', 9000.00, 4000.00, 'available', '2026-05-22', NULL, 25.22750500, 88.78493600, '', 'Dakshin Dinajpur'),
(58, 'AL005', 'Tank More', 'Tank More', 'Balurghat', NULL, 'Billboard', NULL, 20, 20, 'Malda', 'NL', '998366', 'A', 'TA', 3, '', 9000.00, 4000.00, 'available', '2026-05-22', NULL, 25.24953000, 88.77918800, '', 'Dakshin Dinajpur'),
(60, 'SA005', 'Rathbari Bus Stand', 'Beside BRDC Building', 'Malda', NULL, 'Billboard', NULL, 20, 20, 'Rathbari', 'FL', '998366', 'A', 'TA', 19, '', 25000.00, 8000.00, 'available', '2026-05-22', NULL, 24.99927000, 88.13559500, '', 'Malda'),
(61, 'AL006', 'Bus Stand', 'Market', 'Bhatol', NULL, 'Billboard', NULL, 20, 10, 'Bindol', 'NL', '998366', 'A', 'TA', 3, '', 3500.00, 1500.00, 'available', '2026-05-22', NULL, 25.80119600, 88.08571200, '', 'Uttar Dinajpur'),
(62, 'LP001', 'Siliguri More', 'Siliguri More', 'Raiganj', NULL, 'Billboard', NULL, 20, 20, 'Raiganj', 'NL', '998366', 'A', 'TA', 45, '', 12000.00, 5000.00, 'available', '2026-05-23', NULL, 25.06327830, 88.13152700, '', 'Uttar Dinajpur'),
(63, 'LP002', 'Post Office More', 'Beside post Office', 'Raiganj', NULL, 'Unipole', NULL, 20, 10, 'Bidrohi More', 'NL', '998366', 'A', 'TA', 45, '', 35000.00, 18000.00, 'available', '2026-05-23', NULL, 25.59619600, 88.12581800, '', 'Uttar Dinajpur'),
(64, 'MA002', 'Station Road', 'Sankar Mandal Raod', 'Berhampore', NULL, 'Billboard', NULL, 20, 10, 'Bus Stand', 'NL', '998366', 'A', 'TA', 7, '', 5000.00, 5000.00, 'available', '2026-05-27', NULL, 24.09188600, 88.26144900, 'Gora Bazar', 'Murshidabad'),
(65, 'MA003', 'Bus Stand', 'Bus Stand', 'Berhampore', NULL, 'Unipole', NULL, 20, 15, 'Prangon Market', 'NL', '998366', 'A', 'TA', 7, '', 16000.00, 5000.00, 'available', '2026-05-27', NULL, 24.09767000, 88.25746500, 'Bus Stand', 'Murshidabad'),
(66, 'MA004', 'Church More', 'Beside Church', 'Berhampore', NULL, 'Unipole', NULL, 20, 10, 'Bus Stand', 'FL', '998366', 'A', 'TA', 7, '', 45000.00, 20000.00, 'available', '2026-05-27', NULL, 24.09840500, 88.25599000, 'Church More', 'Murshidabad'),
(67, 'MA005', 'Gora Bazar Jelkhana Road', 'Beside Jelkhana', 'Berhampore', NULL, 'Unipole', NULL, 20, 10, 'Gora Bazar', 'FL', '998366', 'A', 'TA', 7, '', 45000.00, 20000.00, 'available', '2026-05-27', NULL, 24.09328400, 88.25096400, 'Gora Bazar', 'Murshidabad'),
(68, 'MA006', 'Panchanantala More', 'Front of FCI', 'Berhampore', NULL, 'Unipole', NULL, 30, 15, 'Kolkata', 'FL', '998366', 'A', 'TA', 7, '', 40000.00, 22500.00, 'available', '2026-05-27', NULL, 24.09838300, 88.26769500, 'Panchanantala More', 'Murshidabad'),
(69, 'MA008', 'Station Road', 'Beside Station', 'Berhampore', NULL, 'Unipole', NULL, 20, 10, 'Bus Stand', 'FL', '998366', 'A', 'TA', 7, '', 45000.00, 20000.00, 'available', '2026-05-27', NULL, 24.09181200, 88.26206200, 'Station', 'Murshidabad'),
(70, 'SA006', 'Bus Stand', 'Bus Stand', 'Balurghat', NULL, 'Billboard', NULL, 20, 10, 'Market', 'NL', '998366', 'A', 'TA', 19, '', 5000.00, 5000.00, 'available', '2026-05-27', NULL, 25.22654000, 88.78196000, 'Bus Stand', 'Dakshin Dinajpur'),
(71, 'MA009', 'YMA Ground', 'Beside YMA Ground', 'Berhampore', NULL, 'Unipole', NULL, 20, 10, 'Gorabazar', 'FL', '998366', 'A', 'TA', 7, '', 45000.00, 22000.00, 'available', '2026-05-27', NULL, 24.08971200, 88.25629500, 'Gora Bazar', 'Murshidabad'),
(73, 'MA010', 'Bow Bazar', 'Bow Bazar', 'Krishnanagar ``', NULL, 'Unipole', NULL, 20, 10, 'Bus Stand', 'NL', '998366', 'A', 'TA', 7, '', 45000.00, 20000.00, 'available', '2026-05-27', NULL, 23.39838500, 88.49098100, 'Bow Bazar', 'Nadia'),
(74, 'MA011', 'Gora Bazar Main Market', 'Main Market', 'Berhampore', NULL, 'Unipole', NULL, 18, 9, 'Gora Bazaar', 'FL', '998366', 'A', 'TA', 7, '', 45000.00, 20000.00, 'available', '2026-05-27', NULL, 24.09002800, 88.25223400, 'Gora Bazar', 'Murshidabad'),
(75, 'MA012', 'Ranaghar Bridge', 'Beside Bridge', 'Kandi', NULL, 'Billboard', NULL, 30, 20, 'Bus Stand', 'NL', '998366', 'A', 'TA', 7, '', 25000.00, 25000.00, 'available', '2026-05-27', NULL, 24.01481900, 88.09290900, 'Ranaghar', 'Murshidabad'),
(76, 'MA013', 'Khagraghat Road', 'Beside Station', 'Berhampore', NULL, 'Billboard', NULL, 30, 20, 'Berhampore', 'NL', '998366', 'A', 'TA', 7, '', 18000.00, 12000.00, 'available', '2026-05-28', NULL, 24.11176000, 88.23268700, 'Khagraghat', 'Murshidabad'),
(77, 'MA014', 'Lalbagh Bypass Road', 'Lalbagh', 'Berhampore', NULL, 'Billboard', NULL, 30, 20, 'Bus Stand', 'NL', '998366', 'A', 'TA', 7, '', 18000.00, 7200.00, 'available', '2026-05-28', NULL, 24.13907000, 88.26350400, 'Lalbagh', 'Murshidabad'),
(78, 'MA015', 'Main Market', 'Bus Stand', 'Jiaganj', NULL, 'Billboard', NULL, 20, 20, 'Bus Stand', 'NL', '998366', 'A', 'TA', 7, '', 9000.00, 4800.00, 'available', '2026-05-28', NULL, 24.24713200, 88.27182100, 'Market Area', 'Murshidabad'),
(79, 'MA016', 'Ratanpur Market', 'Beside Market', 'Dhulian', NULL, 'Billboard', NULL, 20, 20, 'Duckbanglow More', 'NL', '998366', 'A', 'TA', 7, '', 9000.00, 4800.00, 'available', '2026-05-28', NULL, 24.66223600, 87.94413500, 'Ratanpur Market', 'Murshidabad'),
(80, 'RJ004', 'Sahapur Setu More', 'Setu More', 'Malda', NULL, 'Billboard', NULL, 30, 20, 'Pakua', 'FL', '998366', 'A', 'TA', 11, '', 30000.00, 6000.00, 'available', '2026-05-28', NULL, 24.99653500, 88.15814600, 'Sahapur', 'Malda'),
(81, 'SA007', 'Hospital More', 'Beside Hospital', 'Raiganj', NULL, 'Billboard', NULL, 40, 20, 'Jelkhana More', 'FL', '998366', 'A', 'TA', 19, '', 45000.00, 22000.00, 'available', '2026-05-28', NULL, 25.61216800, 88.12950200, 'Hospital', 'Uttar Dinajpur'),
(82, 'SA008', 'NBSTC Bus Stand', 'Above Bus Stand', 'Raiganj', NULL, 'Billboard', NULL, 40, 20, 'Bidrohi More', 'NL', '998366', 'A', 'TA', 19, '', 30000.00, 16000.00, 'available', '2026-05-28', NULL, 25.61240200, 88.12849300, 'Bus Stand', 'Uttar Dinajpur'),
(83, 'AL007', 'Bidrohi More', 'Bidrohi More', 'Raiganj', NULL, 'Billboard', NULL, 30, 30, 'Vivekananda More', 'NL', '998366', 'A', 'TA', 3, '', 30000.00, 10000.00, 'available', '2026-05-28', NULL, 25.61271200, 88.12626900, 'Bidrohi More', 'Uttar Dinajpur'),
(84, 'SA009', 'Mahananda Bridge', 'Opp EBM Park', 'Malda', NULL, 'Billboard', NULL, 25, 30, 'Mangal Bari', 'FL', '998366', 'A', 'TA', 19, '', 35000.00, 22000.00, 'available', '2026-05-28', NULL, 25.01471400, 88.14414400, 'Mangal Bari', 'Malda'),
(85, 'SA010', 'Sukanta More', 'Beside Vodafone Store', 'Malda', NULL, 'Billboard', NULL, 25, 30, 'LIC office', 'FL', '998366', 'A', 'TA', 19, '', 40000.00, 22000.00, 'available', '2026-05-28', NULL, 25.01093000, 88.14099500, 'Sukanta More', 'Malda'),
(86, 'SA011', 'Rathbari fly Over', 'Above Binapani Book House', 'Malda', NULL, 'Billboard', NULL, 30, 30, 'ITI More', 'FL', '998366', 'A', 'TA', 19, '', 45000.00, 25000.00, 'available', '2026-05-28', NULL, 25.00260900, 88.13638300, 'Rathbari', 'Malda'),
(89, 'SC033', 'Station Road', 'Opp Jhaljhalia Market, Beside Haribasartala', 'Malda', NULL, 'Billboard', NULL, 20, 15, 'Rathbari', 'NL', '998366', 'A', 'HA', NULL, '', 12000.00, 12000.00, 'available', '2026-05-31', NULL, 25.01225000, 88.13452800, 'Jhaljhalia', 'Malda'),
(90, 'SC034', 'Bus Stand', 'On National Highway 34', 'Farkka', NULL, 'Traffic Signal Pole Kiosks', NULL, 4, 6, 'Malda &amp;amp; Berhampore', 'BL', '998366', 'A', 'HA', NULL, '', 35000.00, 35000.00, 'available', '2026-05-31', NULL, 24.79521200, 87.91722100, 'Farakka', 'Mursidabad'),
(91, 'SC035', 'Dakbanglow More', 'On National Highway 34', 'Dhulian', NULL, 'Traffic Signal Pole Kiosks', NULL, 6, 4, 'Malda &amp; Berhampore', 'BL', '998366', 'A', 'HA', NULL, '', 35000.00, 35000.00, 'available', '2026-05-31', NULL, 24.67067100, 87.94811820, 'Dhulian', 'Murshidabad'),
(92, 'SC036', 'Aurangabad, Sajur More', 'On National Highway 34', 'Aurangabad', NULL, 'Traffic Signal Pole Kiosks', NULL, 4, 6, 'Malda &amp; Berhampore', 'BL', '998366', 'A', 'HA', NULL, '', 35000.00, 35000.00, 'available', '2026-05-31', NULL, 24.62352800, 87.99563700, 'Sajur More / Aurangabad', 'Mursidabad'),
(93, 'SC039', 'Baliadanga More', 'On National Highway 34', 'Kaliachawk', NULL, 'Traffic Signal Pole Kiosks', NULL, 4, 6, 'Malda &amp;amp; Berhampore', 'BL', '998366', 'A', 'HA', NULL, '', 35000.00, 35000.00, 'available', '2026-05-31', NULL, 24.86584100, 88.02376600, 'Kaliachawk', 'Malda'),
(94, 'SC040', 'Station 1 No. Platform  LED (7 Nos.)', 'Malda Town Station', 'Malda', NULL, 'LED Screen', NULL, 3, 4, 'Malda &amp; Siliguri', 'FL', '998366', 'A', 'HA', NULL, '', 35000.00, 35000.00, 'available', '2026-05-31', NULL, 25.01520100, 88.13110960, 'Station Platform', 'Malda'),
(95, 'SC0041', 'Jhaljhalia. Market, Staion Road', 'Beside Haribasartala', 'Malda', NULL, 'Billboard', NULL, 20, 18, 'Malda Station Incoming', 'NL', '998366', 'A', 'HA', NULL, '', 12000.00, 12000.00, 'available', '2026-05-31', NULL, 25.01280400, 88.13413800, 'Jhaljhalia', 'Malda'),
(96, 'SC042', 'Bus Stand Traffic Xing', 'Atop of Traffic Police Control Room', 'Sujapur', NULL, 'Billboard', NULL, 20, 15, 'Malda', 'NL', '998366', 'A', 'HA', NULL, '', 12000.00, 12000.00, 'available', '2026-05-31', NULL, 24.91124500, 88.08699200, 'Sujapur', 'Malda'),
(97, 'SC043', 'Baliadanga More', 'Atop of Police Control Room', 'Kaliachawk', NULL, 'Billboard', NULL, 20, 20, 'Kolakta', 'NL', '998366', 'A', 'HA', NULL, '', 12000.00, 12000.00, 'available', '2026-05-31', NULL, 24.86584000, 88.02376600, 'Kaliachawk', 'Malda');

-- --------------------------------------------------------

--
-- Table structure for table `site_images`
--

CREATE TABLE `site_images` (
  `id` int(11) NOT NULL,
  `site_id` int(11) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_images`
--

INSERT INTO `site_images` (`id`, `site_id`, `filename`, `is_primary`, `created_at`) VALUES
(1, 1, '1778407185_1_Picture1.png', 0, '2026-05-10 09:59:45'),
(2, 1, '1778407185_1_Picture2.jpg', 0, '2026-05-10 09:59:45'),
(8, 3, '1778408430_3_Malda rathbari More.40X20.. (2).jpeg', 0, '2026-05-10 10:20:30'),
(9, 3, '1778408430_3_Malda rathbari More.40X20.. (2) (1).jpeg', 0, '2026-05-10 10:20:30'),
(12, 5, '1778409077_5_d0cb6f5f-d448-43b0-900f-fa717988d192.JPG', 0, '2026-05-10 10:31:17'),
(13, 5, '1778409077_5_ea297032-a0b3-4dc6-97bd-b276b38ebea6.JPG', 0, '2026-05-10 10:31:17'),
(14, 5, '1778409077_5_357b6700-efb1-431a-8a53-975d46e5dd6f.JPG', 0, '2026-05-10 10:31:17'),
(15, 5, '1778409077_5_a7b21f70-b3b0-4c67-9832-5422cd942fed.JPG', 0, '2026-05-10 10:31:17'),
(16, 6, '1778414955_6_3. Malda Makdampur LIC More.30X20.NL.jpeg', 0, '2026-05-10 12:09:15'),
(17, 7, '1778489466_7_be3b6e99-91ab-4c2f-90dd-1380713557da.JPG', 0, '2026-05-11 08:51:06'),
(18, 7, '1778489466_7_f148e776-36b2-4e6f-b4b9-52df07864678.JPG', 0, '2026-05-11 08:51:06'),
(19, 8, '1778489756_8_4. Malda Makdampur LIC More.50X10. NL..jpeg', 0, '2026-05-11 08:55:56'),
(20, 9, '1778490147_9_6. Malda Rabindra Avenue.20X20. FL..jpg', 0, '2026-05-11 09:02:27'),
(21, 10, '1778490648_10_Malda Rabindra Avenue.20X20.. (3).jpeg', 0, '2026-05-11 09:10:48'),
(22, 11, '1778490878_11_7. Malda Rathbari More.40X15. FL....jpeg', 0, '2026-05-11 09:14:38'),
(23, 12, '1778492590_12_unnamed.jpg', 0, '2026-05-11 09:43:10'),
(24, 12, '1778492590_12_unnamed (1).jpg', 0, '2026-05-11 09:43:10'),
(25, 2, '1778504569_2_9. Malda Station Road Jhaljhalia Market.20X30. FL..jpeg', 0, '2026-05-11 13:02:49'),
(26, 13, '1778505214_13_unnamed (2).jpg', 0, '2026-05-11 13:13:34'),
(27, 14, '1778505442_14_11. Malda Station Road.20X20. NL..jpg', 0, '2026-05-11 13:17:22'),
(28, 15, '1778505702_15_12. Malda Station Road.20X30. FL....jpeg', 0, '2026-05-11 13:21:42'),
(29, 16, '1778505930_16_13. Malda Sukanta More.60X30. FL..jpeg', 0, '2026-05-11 13:25:30'),
(30, 17, '1778506523_17_14. Malda Mangal Bari Rail Gate.20X20. NL..jpeg', 0, '2026-05-11 13:35:23'),
(31, 18, '1778506701_18_15. Sujapur Bus Stand.35X20. NL..jpg', 0, '2026-05-11 13:38:21'),
(32, 19, '1778507039_19_Sujapur Bus Stand.35X20... (5).jpeg', 0, '2026-05-11 13:43:59'),
(33, 20, '1778507413_20_20. Malda Kani More.42X20. NL..jpeg', 0, '2026-05-11 13:50:13'),
(34, 21, '1778507635_21_21. Samsi Bus Stand.20X20. NL..jpg', 0, '2026-05-11 13:53:55'),
(35, 22, '1778508208_22_22. Chanchal Bus Stand.20X20. NL...jpeg', 0, '2026-05-11 14:03:28'),
(36, 23, '1778508435_23_24. Gazole Mayna Bus Stand Fcg Siliguri.20X20. NL..jpeg', 0, '2026-05-11 14:07:15'),
(37, 24, '1778508826_24_23. Gazole Mayna Bus Stand Fcg Kolkata.20X20. NL..jpeg', 0, '2026-05-11 14:13:46'),
(38, 25, '1778509317_25_25. Malda Bypass Fcg Siliguri.40X20. NL..jpeg', 0, '2026-05-11 14:21:57'),
(39, 26, '1778509731_26_26. Malda Bypass Fcg Kolkata.40X20. NL...jpeg', 0, '2026-05-11 14:28:51'),
(40, 27, '1778510942_27_1. Malda Sadarghat More.20X20. NL..jpeg', 0, '2026-05-11 14:49:02'),
(42, 29, '1778511089_29_27. Malda ON NH Badhapukur More Fcg Kolkata.40X20. NL..jpeg', 0, '2026-05-11 14:51:29'),
(43, 30, '1778511180_30_28. Malda ON NH Badhapukur More Fcg Siliguri.40X20. NL..jpeg', 0, '2026-05-11 14:53:00'),
(44, 31, '1778511254_31_WhatsApp Image 2026-05-11 at 8.23.11 PM.jpeg', 0, '2026-05-11 14:54:14'),
(45, 32, '1778511420_32_5. Malda Post Office More.20X20. NL..jpg', 0, '2026-05-11 14:57:00'),
(46, 33, '1778511446_33_29. Malda Rabindra Bhavan More.20X20.NL.jpeg', 0, '2026-05-11 14:57:26'),
(47, 34, '1778511578_34_WhatsApp Image 2026-03-20 at 4.07.29 PM.jpeg', 0, '2026-05-11 14:59:38'),
(48, 35, '1778511658_35_30. Malda Medical College More Fcg Kolkata.20X20.NL..jpeg', 0, '2026-05-11 15:00:58'),
(49, 36, '1778511799_36_1. Kaliachawk Chowrangee More.40X20.FL.. (1).jpeg', 0, '2026-05-11 15:03:19'),
(50, 37, '1778511822_37_31. Malda Medical college More Fcg Rathbari.20X20.NL..jpeg', 0, '2026-05-11 15:03:42'),
(51, 38, '1778512227_38_2. Malda Medical College More.20X20... (1).jpeg', 0, '2026-05-11 15:10:27'),
(52, 39, '1778512251_39_32. Berhampore Bus Stand.40X20.NL..jpeg', 0, '2026-05-11 15:10:51'),
(53, 40, '1778512404_40_33. Berhampore Samabaika More.20X10.NL..jpeg', 0, '2026-05-11 15:13:24'),
(54, 41, '1778512435_41_3. Amrity Bus Stand.15X20.NL..jpeg', 0, '2026-05-11 15:13:55'),
(56, 43, '1778512577_43_1. Malda 320 More.20X20.NL..jpeg', 0, '2026-05-11 15:16:17'),
(57, 44, '1778512687_44_2. Malda Rabindra Avenue.40X20. FL (1).jpeg', 0, '2026-05-11 15:18:07'),
(58, 45, '1778512849_45_Gazole Bus Stand.50X30... (2).jpeg', 0, '2026-05-11 15:20:49'),
(59, 46, '1778512871_46_35. Mayna Traffic Signal.4X6.. (2).jpeg', 0, '2026-05-11 15:21:11'),
(60, 46, '1778512871_46_35. Mayna Traffic Signal.4X6.. (1).jpeg', 0, '2026-05-11 15:21:11'),
(61, 47, '1778513765_47_7th mile mayfair.15X9... (3).jpeg', 0, '2026-05-11 15:36:05'),
(62, 48, '1778514003_48_Berhampore Bus Stand.30X10.. (3).jpeg', 0, '2026-05-11 15:40:03'),
(63, 49, '1778514213_49_Malda ON NH.40X20.. (2).jpeg', 0, '2026-05-11 15:43:33'),
(64, 42, '1778744792_42_WhatsApp Image 2025-09-07 at 1.09.16 PM (1).jpeg', 0, '2026-05-14 07:46:32'),
(65, 42, '1778744792_42_WhatsApp Image 2025-09-07 at 1.09.03 PM.jpeg', 0, '2026-05-14 07:46:32'),
(66, 51, '1778745732_51_Buniadpur Bus Stand.20X20.. (3).jpeg', 0, '2026-05-14 08:02:12'),
(67, 52, '1778746056_52_WhatsApp Image 2026-02-04 at 7.28.05 PM.jpeg', 0, '2026-05-14 08:07:36'),
(68, 53, '1778746228_53_Buniadpur Main Road.20X20.. (4).jpeg', 0, '2026-05-14 08:10:28'),
(69, 54, '1778746564_54_Malda 420 More.60X10.. (1).jpeg', 0, '2026-05-14 08:16:04'),
(70, 55, '1778746710_55_Malda Medical College More.20X20... (5).jpeg', 0, '2026-05-14 08:18:30'),
(71, 56, '1778746931_56_Malda Rabindra Bhavan More.20X20.. (1).jpeg', 0, '2026-05-14 08:22:11'),
(72, 57, '1779451833_57_WhatsApp Image 2024-05-29 at 2.23.55 PM (2).jpeg', 0, '2026-05-22 12:10:33'),
(73, 58, '1779451974_58_WhatsApp Image 2024-05-29 at 12.55.39 PM (1).jpeg', 0, '2026-05-22 12:12:54'),
(74, 60, '1779456921_60_Malda Rathbari Bus Stand.20X20. FL. Rs.22000 PM.jpeg', 0, '2026-05-22 13:35:21'),
(75, 61, '1779461261_61_Bhatol Bus Stand.20X10.. (3).jpeg', 0, '2026-05-22 14:47:41'),
(76, 62, '1779526945_62_Raiganj Siliguri More.20X20. NL. Rs.12000 PM.jpeg', 0, '2026-05-23 09:02:25'),
(77, 63, '1779527062_63_Raiganj Post Office More.20X10.. (3).jpeg', 0, '2026-05-23 09:04:22'),
(78, 63, '1779527062_63_Raiganj Post Office More.20X10.. (8).jpeg', 0, '2026-05-23 09:04:22'),
(79, 64, '1779891606_64_WhatsApp Image 2026-05-27 at 7.42.33 PM.jpeg', 0, '2026-05-27 14:20:06'),
(80, 64, '1779891606_64_WhatsApp Image 2026-05-27 at 7.41.07 PM.jpeg', 0, '2026-05-27 14:20:06'),
(81, 64, '1779891606_64_WhatsApp Image 2026-05-27 at 7.43.01 PM.jpeg', 0, '2026-05-27 14:20:06'),
(82, 65, '1779892100_65_Berhampore Bus Stand.20X15.. (2).jpeg', 0, '2026-05-27 14:28:20'),
(83, 66, '1779893221_66_Berhampore Church More.20X10.. (6).jpeg', 0, '2026-05-27 14:47:01'),
(84, 66, '1779893221_66_Berhampore Church More.20X10.. (1).jpeg', 0, '2026-05-27 14:47:01'),
(85, 66, '1779893221_66_Berhampore Church More.20X10.. (2).jpeg', 0, '2026-05-27 14:47:01'),
(86, 66, '1779893221_66_Berhampore Church More.20X10.. (3).jpeg', 0, '2026-05-27 14:47:01'),
(87, 67, '1779893542_67_Berhampore Gora Bazar Jelkhana Road.20X10.. (5).jpeg', 0, '2026-05-27 14:52:22'),
(88, 67, '1779893542_67_Berhampore Gora Bazar Jelkhana Road.20X10.. (4).jpeg', 0, '2026-05-27 14:52:22'),
(89, 67, '1779893542_67_Berhampore Jelkhana Road.20X10. (2).jpeg', 0, '2026-05-27 14:52:22'),
(90, 67, '1779893542_67_Berhampore Jelkhana Road.20X10. (1).jpeg', 0, '2026-05-27 14:52:22'),
(91, 68, '1779893771_68_Berhampore Panchanantala.30X15.. (3).jpeg', 0, '2026-05-27 14:56:11'),
(92, 68, '1779893771_68_Berhampore Panchanantala More.30X15.. (1).jpeg', 0, '2026-05-27 14:56:11'),
(93, 69, '1779894385_69_Berhampore Station Road Opp Fame Hotel.20X10.. (2).jpeg', 0, '2026-05-27 15:06:25'),
(94, 69, '1779894385_69_Berhampore Station Road Opp Fame Hotel.20X10.. (4).jpeg', 0, '2026-05-27 15:06:25'),
(95, 69, '1779894385_69_Berhampore Station Road Opp Fame Hotel.20X10.. (2) - Copy.jpeg', 0, '2026-05-27 15:06:25'),
(96, 69, '1779894385_69_Berhampore Station Road Opp Fame Hotel.20X10.. (1) - Copy.jpeg', 0, '2026-05-27 15:06:25'),
(97, 70, '1779894459_70_WhatsApp Image 2026-05-27 at 8.26.30 PM.jpeg', 0, '2026-05-27 15:07:39'),
(98, 70, '1779894459_70_WhatsApp Image 2026-05-27 at 8.25.53 PM.jpeg', 0, '2026-05-27 15:07:39'),
(99, 71, '1779894711_71_Berhampore YMA Ground.20X10.. (5).jpeg', 0, '2026-05-27 15:11:51'),
(100, 71, '1779894711_71_Berhampore YMA Ground.20X10.. (2).jpeg', 0, '2026-05-27 15:11:51'),
(101, 71, '1779894711_71_Berhampore YMA Ground.20X10.. (2) - Copy.jpeg', 0, '2026-05-27 15:11:51'),
(102, 71, '1779894711_71_Berhampore YMA Ground.20X10.. (1) - Copy.jpeg', 0, '2026-05-27 15:11:51'),
(103, 73, '1779895092_73_Krishnanagar Bow Bazar.20X10. (6).jpeg', 0, '2026-05-27 15:18:12'),
(104, 73, '1779895092_73_Krishnanagar Bow Bazar.20X10. (7).jpeg', 0, '2026-05-27 15:18:12'),
(105, 74, '1779895378_74_Berhampore Gora Bazar Market.18X9.. (2).jpeg', 0, '2026-05-27 15:22:58'),
(106, 74, '1779895378_74_Berhampore Gora Bazar Market.18X9.. (1).jpeg', 0, '2026-05-27 15:22:58'),
(107, 74, '1779895378_74_Berhampore Gora Bazar Market.18X9... (10) - Copy.jpeg', 0, '2026-05-27 15:22:58'),
(108, 74, '1779895378_74_Berhampore Gora Bazar Market.18X9... (11) - Copy.jpeg', 0, '2026-05-27 15:22:58'),
(109, 75, '1779895602_75_WhatsApp Image 2024-10-28 at 3.42.05 PM.jpeg', 0, '2026-05-27 15:26:42'),
(110, 76, '1779962791_76_Berhampore Khagraghat Road.30X20.. (2).jpeg', 0, '2026-05-28 10:06:31'),
(111, 77, '1779962936_77_Berhampore Lalbagh Bypass Road.30X20... (3).jpeg', 0, '2026-05-28 10:08:56'),
(112, 78, '1779963060_78_Jiaganj Main Road.20X20.. (3).jpeg', 0, '2026-05-28 10:11:00'),
(113, 79, '1779963359_79_Dhulian Ratanpur Market.20X20. (2) - Copy.jpeg', 0, '2026-05-28 10:15:59'),
(114, 80, '1779963714_80_Malda Sahapur Setu More.30X20... (5) - Copy.jpg', 0, '2026-05-28 10:21:54'),
(115, 81, '1779964654_81_Raiganj Ghodi More.40X20.. (3).jpeg', 0, '2026-05-28 10:37:34'),
(116, 81, '1779964654_81_Raiganj Ghodi More.40X20... (2).jpeg', 0, '2026-05-28 10:37:34'),
(117, 82, '1779964845_82_Raiganj NBSTC Bus Stand.38X20... (2).jpeg', 0, '2026-05-28 10:40:45'),
(118, 83, '1779965185_83_Raiganj Reshbehari Market.30X30. NL. Rs.30000 PM.jpeg', 0, '2026-05-28 10:46:25'),
(119, 60, '1779965499_60_Malda Rabindra Avenue.20X20... (1).jpeg', 0, '2026-05-28 10:51:39'),
(120, 38, '1779965565_38_Malda Medical College More.20X20... - Copy.jpeg', 0, '2026-05-28 10:52:45'),
(121, 84, '1779965805_84_Malda Mangal Bari Fly Over.25X30.. (3).jpeg', 0, '2026-05-28 10:56:45'),
(122, 84, '1779965805_84_Malda Mangal Bari Fly Over.25X30... (1).jpeg', 0, '2026-05-28 10:56:45'),
(123, 85, '1779966071_85_Malda Sukanta More.30X25... (10).jpeg', 0, '2026-05-28 11:01:11'),
(124, 85, '1779966071_85_Malda Sukanta More.30X25... (6).jpeg', 0, '2026-05-28 11:01:11'),
(125, 86, '1779966331_86_Malda Rabindra Avenue fcg Airport.30X30.. (1).jpeg', 0, '2026-05-28 11:05:31'),
(126, 86, '1779966331_86_Malda Rabindra Avenue.20X20... (6).jpeg', 0, '2026-05-28 11:05:31'),
(127, 28, '1779966596_28_Malda Mangal Bari Rail Gate.40X20.. (1).jpeg', 0, '2026-05-28 11:09:56'),
(128, 28, '1779966596_28_Malda Mangal Bari Rail Gate.40X20..  (2) - Copy.jpeg', 0, '2026-05-28 11:09:56'),
(129, 89, '1780219589_89_M1kYRN9nzS2eivVUi780sdWbGNfgKNAZ6ozHW6Dw.jpg', 0, '2026-05-31 09:26:29'),
(130, 89, '1780219589_89_V1qJQVqfdVlCYaxXGeBtZ3YktY11PZG8jimhGV5y.jpg', 0, '2026-05-31 09:26:29'),
(135, 91, '1780220699_91_Picture9.png', 0, '2026-05-31 09:44:59'),
(136, 91, '1780220699_91_Picture8.jpg', 0, '2026-05-31 09:44:59'),
(137, 91, '1780220699_91_Picture7.jpg', 0, '2026-05-31 09:44:59'),
(138, 91, '1780220699_91_Picture6.png', 0, '2026-05-31 09:44:59'),
(139, 90, '1780220844_90_Picture13.jpg', 0, '2026-05-31 09:47:24'),
(140, 90, '1780220844_90_Picture12.jpg', 0, '2026-05-31 09:47:24'),
(141, 90, '1780220844_90_Picture11.png', 0, '2026-05-31 09:47:24'),
(142, 90, '1780220844_90_Picture10.png', 0, '2026-05-31 09:47:24'),
(143, 92, '1780221099_92_Picture15.png', 0, '2026-05-31 09:51:39'),
(144, 92, '1780221099_92_Picture18.jpg', 0, '2026-05-31 09:51:39'),
(145, 92, '1780221099_92_Picture17.jpg', 0, '2026-05-31 09:51:39'),
(146, 92, '1780221099_92_Picture16.png', 0, '2026-05-31 09:51:39'),
(147, 93, '1780221390_93_Picture22.jpg', 0, '2026-05-31 09:56:30'),
(148, 93, '1780221390_93_Picture21.jpg', 0, '2026-05-31 09:56:30'),
(149, 93, '1780221390_93_Picture20.png', 0, '2026-05-31 09:56:30'),
(150, 93, '1780221390_93_Picture19.jpg', 0, '2026-05-31 09:56:30'),
(151, 94, '1780222216_94_Picture27.jpg', 0, '2026-05-31 10:10:16'),
(152, 94, '1780222216_94_Picture26.jpg', 0, '2026-05-31 10:10:16'),
(153, 94, '1780222216_94_Picture25.jpg', 0, '2026-05-31 10:10:16'),
(154, 94, '1780222216_94_Picture24.jpg', 0, '2026-05-31 10:10:16'),
(155, 94, '1780222216_94_Picture23.jpg', 0, '2026-05-31 10:10:16'),
(156, 94, '1780222216_94_Picture28.jpg', 0, '2026-05-31 10:10:16'),
(157, 95, '1780222954_95_WhatsApp Image 2026-05-31 at 3.50.26 PM (1).jpeg', 0, '2026-05-31 10:22:34'),
(158, 95, '1780222954_95_WhatsApp Image 2026-05-31 at 3.50.26 PM.jpeg', 0, '2026-05-31 10:22:34'),
(159, 96, '1780223201_96_Picture29.jpg', 0, '2026-05-31 10:26:41'),
(160, 97, '1780223368_97_Picture30.png', 0, '2026-05-31 10:29:28');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','sales','operations','accounts') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`, `status`, `full_name`, `email`, `created_at`) VALUES
(1, 'Subhaendu Saha', 'admin', '$2y$10$Ci8cikzlk/ou3iSIKSYVP.OSpV3gT04fG8gxDlulbIQ89SAUhIIWu', 'admin', 'active', 'System Admin', 'admin@easyoutdoor.com', '2026-05-04 16:16:56'),
(2, 'Operation team', 'Operation', '$2y$10$Z8H5fpHJRDZxs4Ej7I15m.i.1o99E3xYwBlsbJEqbhf8LXTqsfem2', '', 'active', 'Sales Manager', 'sales@easyoutdoor.com', '2026-05-04 16:16:56'),
(3, '', 'ops', '$2y$10$AW94CUG25qbeHCEFFnmXJeI7ZiMQrp/kPEMXH0BiVPxj66MpJ.Nbe', 'operations', 'active', 'Ops Lead', 'ops@easyoutdoor.com', '2026-05-04 16:16:56'),
(4, '', 'accounts', '$2y$10$5XZAjKyM4sePrpt2cTXcaeaaWkaPMw72I28BSCNtCi1mXq4ABqwcO', 'accounts', 'active', 'Accountant', 'accounts@easyoutdoor.com', '2026-05-04 16:16:56'),
(5, '', 'dsaf', '$2y$10$mSJ6053RBcAqMuSu86rPzeNhtI70eZbkMpw9E6.A2G7E3u/MDJRpC', 'sales', 'active', 'dsaf', NULL, '2026-05-04 17:06:44'),
(10, 'Surojit Mondal', 'Surojit Mondal', '$2y$10$xHRvx0XH9yh4eNLuvT0GA.esdTnye9PqsZtUQNLbVxkRWIRbmci8q', '', 'active', NULL, NULL, '2026-05-20 13:56:53');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_printing_rates`
--

CREATE TABLE `vendor_printing_rates` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `site_id` int(11) DEFAULT NULL,
  `media_type` varchar(50) DEFAULT NULL,
  `rate_per_sqft` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `po_number` varchar(50) DEFAULT NULL,
  `attachments` varchar(255) DEFAULT NULL,
  `client_tax_order` varchar(255) DEFAULT NULL,
  `vendor_invoice_no` varchar(100) DEFAULT NULL,
  `vendor_invoice_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `approval_requests`
--
ALTER TABLE `approval_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_requested_by` (`requested_by`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `booking_items`
--
ALTER TABLE `booking_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `site_id` (`site_id`);

--
-- Indexes for table `campaigns`
--
ALTER TABLE `campaigns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_id` (`project_id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `client_pos`
--
ALTER TABLE `client_pos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `client_printing_rates`
--
ALTER TABLE `client_printing_rates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `site_id` (`site_id`);

--
-- Indexes for table `entities`
--
ALTER TABLE `entities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `media_types`
--
ALTER TABLE `media_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `operations`
--
ALTER TABLE `operations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `site_id` (`site_id`);

--
-- Indexes for table `partners`
--
ALTER TABLE `partners`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entity_id` (`partner_id`);

--
-- Indexes for table `po_attachments`
--
ALTER TABLE `po_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_po_attachments_po_id` (`po_id`);

--
-- Indexes for table `po_items`
--
ALTER TABLE `po_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `site_id` (`site_id`);

--
-- Indexes for table `proposals`
--
ALTER TABLE `proposals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `proposal_number` (`proposal_number`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `proposal_items`
--
ALTER TABLE `proposal_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proposal_id` (`proposal_id`),
  ADD KEY `site_id` (`site_id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role` (`role`,`module_key`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `sites`
--
ALTER TABLE `sites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `site_code` (`site_code`),
  ADD KEY `vendor_id` (`vendor_id`);

--
-- Indexes for table `site_images`
--
ALTER TABLE `site_images`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `vendor_printing_rates`
--
ALTER TABLE `vendor_printing_rates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `site_id` (`site_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=132;

--
-- AUTO_INCREMENT for table `approval_requests`
--
ALTER TABLE `approval_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `booking_items`
--
ALTER TABLE `booking_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT for table `campaigns`
--
ALTER TABLE `campaigns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_pos`
--
ALTER TABLE `client_pos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_printing_rates`
--
ALTER TABLE `client_printing_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `entities`
--
ALTER TABLE `entities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `media_types`
--
ALTER TABLE `media_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `operations`
--
ALTER TABLE `operations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT for table `partners`
--
ALTER TABLE `partners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `po_attachments`
--
ALTER TABLE `po_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `po_items`
--
ALTER TABLE `po_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `proposals`
--
ALTER TABLE `proposals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `proposal_items`
--
ALTER TABLE `proposal_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `sites`
--
ALTER TABLE `sites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- AUTO_INCREMENT for table `site_images`
--
ALTER TABLE `site_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=161;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `vendor_printing_rates`
--
ALTER TABLE `vendor_printing_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `partners` (`id`);

--
-- Constraints for table `booking_items`
--
ALTER TABLE `booking_items`
  ADD CONSTRAINT `booking_items_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_items_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`);

--
-- Constraints for table `campaigns`
--
ALTER TABLE `campaigns`
  ADD CONSTRAINT `campaigns_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `campaigns_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `partners` (`id`),
  ADD CONSTRAINT `campaigns_ibfk_3` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `operations`
--
ALTER TABLE `operations`
  ADD CONSTRAINT `operations_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `operations_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`);

--
-- Constraints for table `po_items`
--
ALTER TABLE `po_items`
  ADD CONSTRAINT `po_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `po_items_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`);

--
-- Constraints for table `proposals`
--
ALTER TABLE `proposals`
  ADD CONSTRAINT `proposals_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `partners` (`id`),
  ADD CONSTRAINT `proposals_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `proposal_items`
--
ALTER TABLE `proposal_items`
  ADD CONSTRAINT `proposal_items_ibfk_1` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proposal_items_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`);

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `partners` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `partners` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_3` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `sites`
--
ALTER TABLE `sites`
  ADD CONSTRAINT `sites_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vendor_printing_rates`
--
ALTER TABLE `vendor_printing_rates`
  ADD CONSTRAINT `vendor_printing_rates_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `partners` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendor_printing_rates_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
