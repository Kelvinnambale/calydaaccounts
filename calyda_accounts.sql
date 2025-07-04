-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 04, 2025 at 12:30 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `calyda_accounts`
--

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `kra_pin` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `tax_obligations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`tax_obligations`)),
  `email_address` varchar(100) NOT NULL,
  `id_number` varchar(20) NOT NULL,
  `client_type` enum('Individual','Company','Both') NOT NULL,
  `county` varchar(50) NOT NULL,
  `etims_status` enum('Registered','Pending','Not Registered') DEFAULT 'Not Registered',
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `full_name`, `phone_number`, `kra_pin`, `password`, `tax_obligations`, `email_address`, `id_number`, `client_type`, `county`, `etims_status`, `registration_date`, `created_by`, `updated_at`) VALUES
(3, 'kelvin nambale', '0701924999', 'A016915745K', 'admin@123', '[\"VAT\"]', 'knambale5@gmail.com', '34567800', 'Company', 'Kilifi', 'Not Registered', '2025-07-02 19:33:54', 1, '2025-07-02 19:33:54'),
(6, 'Lincy Lumayo', '0796041079', 'A016915745A', 'admin@123', '[\"Income Tax\"]', 'lincy@gmail.com', '34567811', 'Company', 'Bungoma', 'Not Registered', '2025-07-03 14:25:30', 1, '2025-07-03 14:25:30'),
(7, 'Chrispine Onyango', '0701108805', 'A014015745K', '9Wkmzh4k', '[\"VAT\",\"Other\"]', 'knambale5@gmail.com', '39567800', 'Company', 'Kiambu', 'Not Registered', '2025-07-04 08:01:41', 1, '2025-07-04 08:01:41');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `role`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@calydaaccounts.com', '$2y$10$V.H3q/6tCbBB/dJo197xo.bnXjZ7y3AweT1vgEbYboal3GC7wXApu', 'System Administrator', 'admin', '2025-07-02 15:37:20', '2025-07-03 16:26:02');

-- --------------------------------------------------------

--
-- Table structure for table `vat_records`
--

CREATE TABLE `vat_records` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `record_year` int(11) NOT NULL,
  `record_month` int(11) NOT NULL,
  `sales_amount` decimal(15,2) DEFAULT 0.00,
  `purchases_amount` decimal(15,2) DEFAULT 0.00,
  `sales_vat` decimal(15,2) DEFAULT 0.00,
  `purchases_vat` decimal(15,2) DEFAULT 0.00,
  `net_vat` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vat_records`
--

INSERT INTO `vat_records` (`id`, `client_id`, `record_year`, `record_month`, `sales_amount`, `purchases_amount`, `sales_vat`, `purchases_vat`, `net_vat`, `created_at`, `updated_at`) VALUES
(6, 7, 2025, 1, 19000.00, 13456.00, 3040.00, 2152.96, 887.04, '2025-07-04 09:46:52', '2025-07-04 09:46:52'),
(7, 7, 2025, 2, 6780.00, 5670.00, 1084.80, 907.20, 177.60, '2025-07-04 10:29:52', '2025-07-04 10:29:52');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kra_pin` (`kra_pin`),
  ADD UNIQUE KEY `id_number` (`id_number`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vat_records`
--
ALTER TABLE `vat_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_client_month` (`client_id`,`record_year`,`record_month`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vat_records`
--
ALTER TABLE `vat_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `clients`
--
ALTER TABLE `clients`
  ADD CONSTRAINT `clients_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `vat_records`
--
ALTER TABLE `vat_records`
  ADD CONSTRAINT `vat_records_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
