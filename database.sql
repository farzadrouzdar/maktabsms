-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 07, 2025 at 12:01 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `maktabsms`
--

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `mobile` varchar(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `birth_date` date DEFAULT NULL,
  `field_1` date DEFAULT NULL,
  `field_2` date DEFAULT NULL,
  `field_3` date DEFAULT NULL,
  `field_4` date DEFAULT NULL,
  `field1` varchar(255) DEFAULT NULL,
  `field2` varchar(255) DEFAULT NULL,
  `field3` varchar(255) DEFAULT NULL,
  `field4` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `contacts`
--

INSERT INTO `contacts` (`id`, `school_id`, `name`, `mobile`, `group_id`, `created_at`, `birth_date`, `field_1`, `field_2`, `field_3`, `field_4`, `field1`, `field2`, `field3`, `field4`) VALUES
(1, 1, 'فرزاد روزدار', '09129342383', 1, '2025-05-06 10:26:34', '2025-05-06', '0000-00-00', '0000-00-00', '0000-00-00', '0000-00-00', 'کد123', 'مقدار2', 'مقدار3', 'مقدار4'),
(2, 1, 'لاللات', '09356194949', 1, '2025-05-06 10:26:57', '2025-05-06', '0000-00-00', '0000-00-00', '0000-00-00', '0000-00-00', NULL, NULL, NULL, NULL),
(3, 1, 'فرزاد روزدار', '09129342383', 2, '2025-05-06 14:19:53', NULL, '0000-00-00', '0000-00-00', '0000-00-00', '0000-00-00', 'کد123', 'مقدار2', 'مقدار3', 'مقدار4'),
(4, 1, 'فرزاد دوم', '09209342383', 2, '2025-05-06 14:20:37', NULL, '0000-00-00', '0000-00-00', '0000-00-00', '0000-00-00', NULL, NULL, NULL, NULL),
(5, 1, 'فرزاد روزدار', '09129342383', 3, '2025-05-06 21:13:10', '2025-05-07', '0000-00-00', '0000-00-00', '0000-00-00', '0000-00-00', 'کد123', 'مقدار2', 'مقدار3', 'مقدار4'),
(6, 1, 'مهرزاد روزدار', '09397120080', 3, '2025-05-06 21:13:52', '2025-05-02', '0000-00-00', '0000-00-00', '0000-00-00', '0000-00-00', 'کد456', 'مقدار2', 'مقدار3', 'مقدار4'),
(7, 1, 'رستا روزدار', '09209342383', 3, '2025-05-06 21:14:17', '2025-05-02', '0000-00-00', '0000-00-00', '0000-00-00', '0000-00-00', 'یمبنتسمیب', 'نستیبنسب', 'نتاسینبا', 'ستینبا'),
(8, 1, 'رامین روزدار', '09337288808', 3, '2025-05-06 21:22:32', '2025-05-01', '0000-00-00', '0000-00-00', '0000-00-00', '0000-00-00', 'سبسبی', 'سیبسیب', 'نتانت', 'نتانتا'),
(9, 1, 'سنمیتب', '09356194949', 3, '2025-05-06 21:58:21', '0000-00-00', NULL, NULL, NULL, NULL, 'سیبسیبسیب', '', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `contact_groups`
--

CREATE TABLE `contact_groups` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `group_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `contact_groups`
--

INSERT INTO `contact_groups` (`id`, `school_id`, `name`, `created_at`, `group_name`) VALUES
(2, 1, '', '2025-05-06 14:19:39', 'تست فرزد'),
(3, 1, '', '2025-05-06 21:12:33', 'هوشمند');

-- --------------------------------------------------------

--
-- Table structure for table `drafts`
--

CREATE TABLE `drafts` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `group_id` int(11) DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `type` enum('simple','smart') DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `drafts`
--

INSERT INTO `drafts` (`id`, `school_id`, `message`, `created_at`, `updated_at`, `group_id`, `title`, `type`, `status`) VALUES
(1, 1, 'یبلیبل', '2025-05-06 10:23:34', '2025-05-06 10:23:34', NULL, 'سیب', 'simple', 'pending'),
(2, 1, 'سلام.\r\nمن فرزاد هستم - \r\nلغو11', '2025-05-06 10:53:37', '2025-05-06 10:55:32', 4, 'تست پیش نویس', 'simple', 'approved'),
(3, 1, 'سلام {name}\r\nتولد شما {birth_date} میباشد.', '2025-05-06 16:51:27', '2025-05-06 16:51:27', 4, 'تست', 'smart', 'pending'),
(4, 1, 'سلام.\r\n{name}\r\n{mobile}\r\n{birth_date}\r\n{field_1}\r\n{field_2}\r\n{field_3}\r\n{field_4}\r\nلغو11', '2025-05-06 21:17:31', '2025-05-06 21:23:46', 5, 'تست هوشمند', 'smart', 'approved');

-- --------------------------------------------------------

--
-- Table structure for table `draft_groups`
--

CREATE TABLE `draft_groups` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `group_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `draft_groups`
--

INSERT INTO `draft_groups` (`id`, `school_id`, `group_name`, `created_at`) VALUES
(4, 1, 'تست', '2025-05-06 10:53:16'),
(5, 1, 'هوشمند', '2025-05-06 21:16:40');

-- --------------------------------------------------------

--
-- Table structure for table `phonebook_contacts`
--

CREATE TABLE `phonebook_contacts` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `mobile` varchar(11) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `field1` varchar(255) DEFAULT NULL,
  `field2` varchar(255) DEFAULT NULL,
  `field3` varchar(255) DEFAULT NULL,
  `field4` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `phonebook_groups`
--

CREATE TABLE `phonebook_groups` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `group_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `received_sms`
--

CREATE TABLE `received_sms` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `reference_id` int(11) NOT NULL,
  `sender_mobile` varchar(11) NOT NULL,
  `content` text NOT NULL,
  `received_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schools`
--

CREATE TABLE `schools` (
  `id` int(11) NOT NULL,
  `school_type` enum('دبستان','مدرسه','پیش‌دبستانی','متوسطه اول','متوسطه دوم') NOT NULL,
  `gender_type` enum('دخترانه','پسرانه','مختلط') NOT NULL,
  `school_name` varchar(255) NOT NULL,
  `national_id` varchar(20) NOT NULL,
  `province` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `district` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `postal_code` varchar(10) NOT NULL,
  `request_letter_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `balance` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `schools`
--

INSERT INTO `schools` (`id`, `school_type`, `gender_type`, `school_name`, `national_id`, `province`, `city`, `district`, `address`, `postal_code`, `request_letter_path`, `status`, `created_at`, `balance`) VALUES
(1, 'دبستان', 'دخترانه', 'شهید بداغی', '14000520', 'اصفهان', 'شیراز', 'asd', 'بلوار فضیلت - نبش کوچه 22 - ساختمان 2020 - واحد یک', '7145863315', 'uploads/1746520655_Rezoome_ BehFarda1.pdf', 'approved', '2025-05-06 08:37:35', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `sent_sms`
--

CREATE TABLE `sent_sms` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `type` enum('single','group','smart') NOT NULL,
  `recipient_mobile` varchar(11) DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `cost` int(11) NOT NULL,
  `send_time` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `key_name` varchar(255) NOT NULL,
  `key_value` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key_name`, `key_value`, `created_at`) VALUES
(1, 'sms_cost_per_part', '100', '2025-05-06 07:34:08'),
(2, 'sms_max_chars_part1', '70', '2025-05-06 07:34:08'),
(3, 'sms_max_chars_other', '67', '2025-05-06 07:34:08'),
(4, 'sms_footer_text', 'لغو11', '2025-05-06 07:34:08');

-- --------------------------------------------------------

--
-- Table structure for table `sms_drafts`
--

CREATE TABLE `sms_drafts` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `group_name` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `type` enum('simple','smart') NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_logs`
--

CREATE TABLE `sms_logs` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `recipient` varchar(11) NOT NULL,
  `message` text NOT NULL,
  `status` enum('sent','failed') NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `amount` int(11) NOT NULL,
  `status` enum('pending','successful','failed') DEFAULT 'pending',
  `payment_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `mobile` varchar(11) NOT NULL,
  `national_id` varchar(10) NOT NULL,
  `birth_date` date NOT NULL,
  `role` enum('admin','manager','deputy','user') NOT NULL DEFAULT 'user',
  `school_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `mobile`, `national_id`, `birth_date`, `role`, `school_id`, `created_at`) VALUES
(1, 'فرزاد روزدار', '09129342383', '2433015405', '2025-05-06', 'manager', 1, '2025-05-06 08:37:35'),
(2, 'asd', '09209342383', '25550', '2025-05-14', 'deputy', 1, '2025-05-06 08:37:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `school_id` (`school_id`),
  ADD KEY `idx_mobile_contacts` (`mobile`);

--
-- Indexes for table `contact_groups`
--
ALTER TABLE `contact_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `school_id` (`school_id`);

--
-- Indexes for table `drafts`
--
ALTER TABLE `drafts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `school_id` (`school_id`),
  ADD KEY `group_id` (`group_id`);

--
-- Indexes for table `draft_groups`
--
ALTER TABLE `draft_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `school_id` (`school_id`);

--
-- Indexes for table `phonebook_contacts`
--
ALTER TABLE `phonebook_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_id` (`group_id`);

--
-- Indexes for table `phonebook_groups`
--
ALTER TABLE `phonebook_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `school_id` (`school_id`);

--
-- Indexes for table `received_sms`
--
ALTER TABLE `received_sms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `school_id` (`school_id`);

--
-- Indexes for table `schools`
--
ALTER TABLE `schools`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `national_id` (`national_id`);

--
-- Indexes for table `sent_sms`
--
ALTER TABLE `sent_sms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `school_id` (`school_id`),
  ADD KEY `group_id` (`group_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key_name` (`key_name`);

--
-- Indexes for table `sms_drafts`
--
ALTER TABLE `sms_drafts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `school_id` (`school_id`);

--
-- Indexes for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `school_id` (`school_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `school_id` (`school_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mobile` (`mobile`),
  ADD UNIQUE KEY `national_id` (`national_id`),
  ADD KEY `idx_mobile_users` (`mobile`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `contacts`
--
ALTER TABLE `contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `contact_groups`
--
ALTER TABLE `contact_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `drafts`
--
ALTER TABLE `drafts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `draft_groups`
--
ALTER TABLE `draft_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `phonebook_contacts`
--
ALTER TABLE `phonebook_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `phonebook_groups`
--
ALTER TABLE `phonebook_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `received_sms`
--
ALTER TABLE `received_sms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schools`
--
ALTER TABLE `schools`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sent_sms`
--
ALTER TABLE `sent_sms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `sms_drafts`
--
ALTER TABLE `sms_drafts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_logs`
--
ALTER TABLE `sms_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `contacts`
--
ALTER TABLE `contacts`
  ADD CONSTRAINT `contacts_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contact_groups`
--
ALTER TABLE `contact_groups`
  ADD CONSTRAINT `contact_groups_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `drafts`
--
ALTER TABLE `drafts`
  ADD CONSTRAINT `drafts_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `drafts_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `draft_groups` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `draft_groups`
--
ALTER TABLE `draft_groups`
  ADD CONSTRAINT `draft_groups_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `phonebook_contacts`
--
ALTER TABLE `phonebook_contacts`
  ADD CONSTRAINT `phonebook_contacts_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `phonebook_groups` (`id`);

--
-- Constraints for table `phonebook_groups`
--
ALTER TABLE `phonebook_groups`
  ADD CONSTRAINT `phonebook_groups_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`);

--
-- Constraints for table `received_sms`
--
ALTER TABLE `received_sms`
  ADD CONSTRAINT `received_sms_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`);

--
-- Constraints for table `sent_sms`
--
ALTER TABLE `sent_sms`
  ADD CONSTRAINT `sent_sms_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  ADD CONSTRAINT `sent_sms_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `phonebook_groups` (`id`);

--
-- Constraints for table `sms_drafts`
--
ALTER TABLE `sms_drafts`
  ADD CONSTRAINT `sms_drafts_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`);

--
-- Constraints for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD CONSTRAINT `sms_logs_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
