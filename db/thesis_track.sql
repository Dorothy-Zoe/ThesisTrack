-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 18, 2025 at 10:18 AM
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
-- Database: `thesis_track`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('student','advisor','coordinator') DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `advisors`
--

CREATE TABLE `advisors` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `requires_password_change` tinyint(1) DEFAULT NULL,
  `year_handled` int(11) DEFAULT NULL,
  `sections_handled` varchar(255) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `specialization` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `advisors`
--

INSERT INTO `advisors` (`id`, `first_name`, `middle_name`, `last_name`, `email`, `password`, `employee_id`, `status`, `profile_picture`, `created_at`, `last_login`, `requires_password_change`, `year_handled`, `sections_handled`, `department`, `specialization`, `updated_at`) VALUES
(13, 'Mariaa', 'Reyes', 'Lopez', 'maria.lopez@example.com', '$2y$10$EehwW4IZaQasM7GVFL5CM.4GBNaKTVUiotF3aSNW80U3je5mvf3tm', 'EMP-2025-001', 'active', NULL, '2025-07-10 07:06:49', '2025-07-18 07:47:49', 0, 0, 'BSCS-3A, BSCS-3B', 'BSCS', 'Business Analytics', '2025-07-18 07:47:49'),
(16, 'Jane', 'Sy', 'Doe', 'test3@gmal.com', '$2y$10$dLcCrwlqCixr.yda2AP6vuF/zEAQEaNdmJje0QCJtLhkQrqT/QbcW', 'EMP-2025-002', 'active', NULL, '2025-07-10 07:18:48', '2025-07-17 05:13:45', 0, NULL, 'BSCS-4A', 'BSCS', 'Web Development', '2025-07-17 05:13:45'),
(17, 'Helen', 'Salon', 'Aquino', 'helen.aquino@gmail.com', '$2y$10$a9JhF5d1bxETBKJfdYg.c.1MtsZVawwodbn9JIu7J39dmtVEYyu3i', 'EMP-2025-003', 'active', NULL, '2025-07-16 03:18:16', '2025-07-16 07:21:56', 0, 0, 'BSIS-4A, BSIS-4B', 'BSIS', 'Mobile Development', '2025-07-18 07:03:17'),
(24, 'Hiro', 'Sy', 'Now', 'hiro.now@gmail.com', '$2y$10$EC1xfO4l6ZWvdx9IcUG5feE4Y4XLJiItMOycuWzFeFeyu8mCJnyQ6', 'EMP-2025-004', 'active', NULL, '2025-07-18 07:00:27', NULL, 1, 0, 'BSCS-3C', 'BSCS', 'AI and Machine Learning', '2025-07-18 07:02:50');

-- --------------------------------------------------------

--
-- Table structure for table `advisor_sections`
--

CREATE TABLE `advisor_sections` (
  `id` int(11) NOT NULL,
  `advisor_id` int(11) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `course` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `advisor_sections`
--

INSERT INTO `advisor_sections` (`id`, `advisor_id`, `section`, `course`, `created_at`) VALUES
(12, 13, 'BSCS-3A', 'BSCS', '2025-07-10 07:06:49'),
(15, 16, 'BSCS-4A', 'BSCS', '2025-07-10 07:18:48'),
(16, 17, 'BSIS-4A', 'BSIS', '2025-07-16 03:18:16'),
(18, 13, 'BSCS-3B', 'BSCS', '2025-07-18 05:21:55'),
(24, 24, 'BSCS-3C', 'BSCS', '2025-07-18 07:00:27'),
(25, 17, 'BSIS-4B', 'BSIS', '2025-07-18 07:03:17');

-- --------------------------------------------------------

--
-- Table structure for table `chapters`
--

CREATE TABLE `chapters` (
  `id` int(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `chapter_number` int(11) DEFAULT NULL,
  `chapter_name` varchar(255) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `status` enum('pending','under_review','approved','needs_revision') DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_reviewed_date` timestamp NULL DEFAULT NULL,
  `reviewer_id` int(11) DEFAULT NULL,
  `reviewer_type` enum('advisor','coordinator') DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chapter_comments`
--

CREATE TABLE `chapter_comments` (
  `id` int(11) NOT NULL,
  `chapter_id` int(11) DEFAULT NULL,
  `commenter_id` int(11) DEFAULT NULL,
  `commenter_type` enum('advisor','coordinator','student') DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `is_resolved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coordinators`
--

CREATE TABLE `coordinators` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `requires_password_change` tinyint(1) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `coordinators`
--

INSERT INTO `coordinators` (`id`, `first_name`, `middle_name`, `last_name`, `email`, `password`, `employee_id`, `status`, `profile_picture`, `created_at`, `last_login`, `requires_password_change`, `updated_at`) VALUES
(1, 'Carlos', 'Fernandez', 'Tan', 'carlos.tan@example.com', 'coordinator123', 'COORD-001', 'active', 'uploads/profile_pictures/carlos.jpg', '2025-07-08 06:44:24', NULL, 0, '2025-07-10 05:14:25');

-- --------------------------------------------------------

--
-- Table structure for table `groups`
--

CREATE TABLE `groups` (
  `id` int(11) NOT NULL,
  `title` varchar(500) DEFAULT NULL,
  `advisor_id` int(11) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `status` enum('active','completed','inactive') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `groups`
--

INSERT INTO `groups` (`id`, `title`, `advisor_id`, `section`, `status`, `created_at`) VALUES
(12, 'Group 1', 13, 'BSCS-3A', 'active', '2025-07-15 04:31:55'),
(13, 'Group 2', 13, 'BSCS-3A', 'active', '2025-07-15 05:55:04'),
(14, 'Group 3', 13, 'BSCS-3A', 'active', '2025-07-15 08:03:53'),
(15, 'Group 1', 17, 'BSIS-4A', 'active', '2025-07-16 04:45:24'),
(16, 'Group 1', 16, 'BSCS-4A', 'active', '2025-07-17 05:14:54'),
(18, 'Test Group', 13, 'BSCS-3B', 'active', '2025-07-18 08:17:34');

-- --------------------------------------------------------

--
-- Table structure for table `group_members`
--

CREATE TABLE `group_members` (
  `id` int(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `role_in_group` enum('leader','member') DEFAULT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `group_members`
--

INSERT INTO `group_members` (`id`, `group_id`, `student_id`, `role_in_group`, `joined_at`) VALUES
(39, 13, 6, 'leader', '2025-07-15 05:56:45'),
(56, 14, 9, 'leader', '2025-07-15 08:03:53'),
(61, 12, 7, 'leader', '2025-07-15 08:32:08'),
(62, 12, 3, 'member', '2025-07-15 08:32:08'),
(63, 12, 8, 'member', '2025-07-15 08:32:08'),
(64, 12, 2, 'member', '2025-07-15 08:32:08'),
(65, 15, 10, 'leader', '2025-07-16 04:45:24'),
(66, 16, 4, 'leader', '2025-07-17 05:14:54'),
(68, 18, 12, 'leader', '2025-07-18 08:17:34');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('student','advisor','coordinator') DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `type` enum('info','success','warning','error') DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `year_level` int(11) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `course` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `advisor_id` int(11) DEFAULT NULL,
  `requires_password_change` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `first_name`, `middle_name`, `last_name`, `email`, `password`, `student_id`, `year_level`, `section`, `course`, `status`, `profile_picture`, `created_at`, `last_login`, `advisor_id`, `requires_password_change`, `updated_at`) VALUES
(2, 'Jasmine', 'Santos', 'Lim', 'jasmine.lim@student.cict.edu', '$2y$10$WDCuWmxLAzrKJ/X8eD4wSeBpjCQ7nCku2mPFH/53lS.67RLaMM4sW', '2025-BSCS-0001', 3, 'BSCS-3A', 'BSCS', 'active', '', '2025-07-10 02:53:44', '2025-07-17 02:49:58', 13, 0, '2025-07-17 02:49:58'),
(3, 'Johny', 'Sy', 'Doe', 'john.doe@student.cict.edu', '$2y$10$qNeVEFnaUOX1oatNZziSMeESGYUxgDcSpw4PMsQdoyBoClrN8v2Hm', '2025-BSCS-0002', 3, 'BSCS-3A', 'BSCS', 'active', '', '2025-07-10 07:32:13', NULL, 13, 1, '2025-07-15 04:31:55'),
(4, 'Claude', 'Salvador', 'Lee', 'claude.lee@student.cict.edu', '$2y$10$VLudhDY4r07uXKSvJUQLnOUY6Q39paJcMuSLpVFuC9QVMUhcNA1.S', '2025-BSCS-0003', 3, 'BSCS-4A', 'BSCS', 'active', '', '2025-07-10 07:41:16', NULL, 16, 1, '2025-07-11 02:32:38'),
(6, 'Pearl', 'Dee', 'Lee', 'pearl.lee@student.cict.edu', '$2y$10$WbLr2PKr6X6Zmv93gt0U..SeZRDIHD3BdI4M.UPIjjM/zMkVUkIya', '2025-BSCS-0004', 3, 'BSCS-3A', 'BSCS', 'active', '', '2025-07-15 05:51:03', NULL, 13, 1, '2025-07-15 05:51:03'),
(7, 'Claude', 'Meo', 'Brown', 'claude.brown@student.cict.edu', '$2y$10$OTqFjYuqRc1SLF/USDVnOeKacRYIxE2V7v26u4LUnBJhW54BY1Am.', '2025-BSCS-0005', 3, 'BSCS-3A', 'BSCS', 'active', '', '2025-07-15 05:51:57', '2025-07-16 07:53:48', 13, 0, '2025-07-16 07:54:06'),
(8, 'Miski', 'Glow', 'Green', 'miski.green@student.cict.edu', '$2y$10$Qf80oMqG18JbqnnWJ5fw2.L/MQfTsmxbUkaBU8vm4VIMyUXTNmMiG', '2025-BSCS-0006', 3, 'BSCS-3A', 'BSCS', 'active', '', '2025-07-15 05:52:47', NULL, 13, 1, '2025-07-15 05:52:47'),
(9, 'Akie', 'Sam', 'Sung', 'akie.sung@student.cict.edu', '$2y$10$x9fAEeD4SHpUx/40s8Ub0evHRVYixWMFu/hZorYE/ugEHEObCqnbi', '2025-BSCS-0007', 3, 'BSCS-3A', 'BSCS', 'active', '', '2025-07-15 06:43:26', NULL, 13, 1, '2025-07-15 06:43:26'),
(10, 'Callie', 'Fur', 'Kite', 'callie.kite@student.cict.edu', '$2y$10$5ozSupS2WgXfr/zj23uY6e.EJJpF6I50xgF.0D8TwsVIQtxFcZYI6', '2025-BSIS-0001', 3, 'BSIS-4A', 'BSIS', 'active', '', '2025-07-16 04:43:38', NULL, 17, 1, '2025-07-16 04:43:38'),
(11, 'Rio', 'Grand', 'Dee', 'rio.dee@student.cict.edu', '$2y$10$Jh57JDZ9ccgtmYqSVBkWVuBUD8KI0902Es5k/n6kdErBu.CvLEWcm', '2025-BSCS-0008', 3, 'BSCS-3A', 'BSCS', 'active', '', '2025-07-17 02:51:31', NULL, 13, 1, '2025-07-17 02:51:31'),
(12, 'Kyle', 'Gonzales', 'Cruz', 'kyle.cruz@student.cict.edu', '$2y$10$hYZJQnNpcUS2DzJRpGibh.xN6tv5jqdjcnSa9XE8BEH9VFj.3gcUa', '2025-BSCS-0009', 3, 'BSCS-3B', 'BSCS', 'active', '', '2025-07-18 07:46:31', NULL, 13, 1, '2025-07-18 08:17:34');

-- --------------------------------------------------------

--
-- Table structure for table `student_groups`
--

CREATE TABLE `student_groups` (
  `id` int(11) NOT NULL,
  `group_name` varchar(255) DEFAULT NULL,
  `thesis_title` varchar(500) DEFAULT NULL,
  `advisor_id` int(11) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `course` varchar(50) DEFAULT NULL,
  `status` enum('active','completed','inactive') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `group_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `student_groups`
--

INSERT INTO `student_groups` (`id`, `group_name`, `thesis_title`, `advisor_id`, `section`, `course`, `status`, `created_at`, `updated_at`, `group_id`) VALUES
(11, 'Group 1', 'Smiski Game Development', 13, 'BSCS-3A', 'BSCS', 'active', '2025-07-15 04:31:55', '2025-07-15 08:32:08', 12),
(12, 'Group 2', 'Thinkpad Web Development', 13, 'BSCS-3A', 'BSCS', 'active', '2025-07-15 05:55:04', '2025-07-15 06:31:10', 13),
(13, 'Group 3', 'Smiski Employee Management System', 13, 'BSCS-3A', 'BSCS', 'active', '2025-07-15 08:03:53', '2025-07-15 08:03:53', 14),
(14, 'Group 1', 'Local Host Development', 17, 'BSIS-4A', 'BSIS', 'active', '2025-07-16 04:45:24', '2025-07-16 04:45:24', 15),
(15, 'Group 1', 'TC Corporation Employee Management System', 16, 'BSCS-4A', 'BSCS', 'active', '2025-07-17 05:14:54', '2025-07-17 05:14:54', 16),
(17, 'Test Group', 'Test Group', 13, 'BSCS-3B', 'BSCS', 'active', '2025-07-18 08:17:34', '2025-07-18 08:17:34', 18);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `advisors`
--
ALTER TABLE `advisors`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `advisor_sections`
--
ALTER TABLE `advisor_sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `advisor_id` (`advisor_id`);

--
-- Indexes for table `chapters`
--
ALTER TABLE `chapters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_id` (`group_id`);

--
-- Indexes for table `chapter_comments`
--
ALTER TABLE `chapter_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chapter_id` (`chapter_id`);

--
-- Indexes for table `coordinators`
--
ALTER TABLE `coordinators`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `advisor_id` (`advisor_id`);

--
-- Indexes for table `group_members`
--
ALTER TABLE `group_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `student_groups`
--
ALTER TABLE `student_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `advisor_id` (`advisor_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `advisors`
--
ALTER TABLE `advisors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `advisor_sections`
--
ALTER TABLE `advisor_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `chapters`
--
ALTER TABLE `chapters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chapter_comments`
--
ALTER TABLE `chapter_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `coordinators`
--
ALTER TABLE `coordinators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `group_members`
--
ALTER TABLE `group_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `student_groups`
--
ALTER TABLE `student_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `advisor_sections`
--
ALTER TABLE `advisor_sections`
  ADD CONSTRAINT `advisor_sections_ibfk_1` FOREIGN KEY (`advisor_id`) REFERENCES `advisors` (`id`);

--
-- Constraints for table `chapters`
--
ALTER TABLE `chapters`
  ADD CONSTRAINT `chapters_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `student_groups` (`id`),
  ADD CONSTRAINT `chapters_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`);

--
-- Constraints for table `chapter_comments`
--
ALTER TABLE `chapter_comments`
  ADD CONSTRAINT `chapter_comments_ibfk_1` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`);

--
-- Constraints for table `groups`
--
ALTER TABLE `groups`
  ADD CONSTRAINT `groups_ibfk_1` FOREIGN KEY (`advisor_id`) REFERENCES `advisors` (`id`);

--
-- Constraints for table `group_members`
--
ALTER TABLE `group_members`
  ADD CONSTRAINT `group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`),
  ADD CONSTRAINT `group_members_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);

--
-- Constraints for table `student_groups`
--
ALTER TABLE `student_groups`
  ADD CONSTRAINT `student_groups_ibfk_1` FOREIGN KEY (`advisor_id`) REFERENCES `advisors` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
