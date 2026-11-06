-- phpMyAdmin SQL Dump
-- version 5.2.3deb1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 22, 2026 at 11:06 AM
-- Server version: 8.4.11-0ubuntu0.26.04.1
-- PHP Version: 8.5.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `school_admin_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_leaves`
--

CREATE TABLE `academic_leaves` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `leave_type` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `academic_leaves`
--

INSERT INTO `academic_leaves` (`id`, `student_id`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `created_at`) VALUES
(1, 1, 'Medical', '2026-10-01', '2026-11-28', '......', 'Pending', '2026-10-14 07:01:43'),
(2, 1, 'Medical', '2026-10-01', '2026-11-28', '......', 'Pending', '2026-10-14 07:02:31');

-- --------------------------------------------------------

--
-- Table structure for table `admission_applications`
--

CREATE TABLE `admission_applications` (
  `id` int NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `form_requested` varchar(20) DEFAULT NULL,
  `parent_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `status` enum('pending','Approved','Rejected') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int NOT NULL,
  `student_id` int NOT NULL,
  `date` date NOT NULL,
  `status` enum('Present','Absent','Late') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `student_id`, `date`, `status`) VALUES
(1, 1, '2026-10-14', 'Absent');

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `class_id` int NOT NULL,
  `name` varchar(50) NOT NULL,
  `level` enum('Primary','Secondary') NOT NULL,
  `curriculum` enum('CBC','8-4-4') NOT NULL,
  `sort_order` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`class_id`, `name`, `level`, `curriculum`, `sort_order`) VALUES
(1, 'Grade 1', 'Primary', 'CBC', 1),
(2, 'Grade 2', 'Primary', 'CBC', 2),
(3, 'Grade 3', 'Primary', 'CBC', 3),
(4, 'Grade 4', 'Primary', 'CBC', 4),
(5, 'Grade 5', 'Primary', 'CBC', 5),
(6, 'Grade 6', 'Primary', 'CBC', 6),
(7, 'Grade 7', 'Primary', 'CBC', 7),
(8, 'Grade 8', 'Primary', 'CBC', 8),
(9, 'Grade 9', 'Primary', 'CBC', 9),
(10, 'Form 1', 'Secondary', '8-4-4', 10),
(11, 'Form 2', 'Secondary', '8-4-4', 11),
(12, 'Form 3', 'Secondary', '8-4-4', 12),
(13, 'Form 4', 'Secondary', '8-4-4', 13);

-- --------------------------------------------------------

--
-- Table structure for table `coursework`
--

CREATE TABLE `coursework` (
  `cw_id` int NOT NULL,
  `student_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `cat_1` decimal(5,2) DEFAULT '0.00',
  `cat_2` decimal(5,2) DEFAULT '0.00',
  `assignment` decimal(5,2) DEFAULT '0.00',
  `recorded_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `coursework`
--

INSERT INTO `coursework` (`cw_id`, `student_id`, `subject_id`, `cat_1`, `cat_2`, `assignment`, `recorded_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 12.50, 13.00, 8.50, NULL, '2026-10-14 06:49:21', '2026-10-14 06:49:21'),
(2, 1, 2, 10.00, 11.50, 7.00, NULL, '2026-10-14 06:49:21', '2026-10-14 06:49:21'),
(3, 1, 3, 8.50, 9.00, 6.00, NULL, '2026-10-14 06:49:21', '2026-10-14 06:49:21'),
(4, 1, 4, 13.00, 14.00, 9.00, NULL, '2026-10-14 06:49:21', '2026-10-14 06:49:21');

-- --------------------------------------------------------

--
-- Table structure for table `downloads`
--

CREATE TABLE `downloads` (
  `id` int NOT NULL,
  `title` varchar(200) NOT NULL,
  `category` varchar(50) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` varchar(20) DEFAULT NULL,
  `file_type` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

CREATE TABLE `exams` (
  `exam_id` int NOT NULL,
  `exam_code` varchar(50) DEFAULT NULL,
  `subject_id` int DEFAULT NULL,
  `form` varchar(50) DEFAULT NULL,
  `exam_name` varchar(100) DEFAULT NULL,
  `term` enum('Term 1','Term 2','Term 3') NOT NULL,
  `exam_date` date DEFAULT NULL,
  `exam_time` time DEFAULT NULL,
  `room` varchar(50) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('Scheduled','Ongoing','Completed') DEFAULT 'Scheduled',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `exams`
--

INSERT INTO `exams` (`exam_id`, `exam_code`, `subject_id`, `form`, `exam_name`, `term`, `exam_date`, `exam_time`, `room`, `academic_year`, `start_date`, `end_date`, `status`, `created_at`) VALUES
(1, 'End001', 4, 'Form 1', NULL, 'Term 1', '2026-10-17', '10:30:00', 'LT-3-3', NULL, NULL, NULL, 'Scheduled', '2026-10-16 10:50:55'),
(2, 'End001', 4, 'Form 1', NULL, 'Term 1', '2026-10-17', '10:30:00', 'LT-3-3', NULL, NULL, NULL, 'Scheduled', '2026-10-16 10:52:14');

-- --------------------------------------------------------

--
-- Table structure for table `fees`
--

CREATE TABLE `fees` (
  `fee_id` int NOT NULL,
  `student_id` int NOT NULL,
  `amount_total` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) DEFAULT '0.00',
  `status` enum('Paid','Pending','Partial') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fee_structure`
--

CREATE TABLE `fee_structure` (
  `id` int NOT NULL,
  `form` varchar(20) NOT NULL,
  `term` enum('Term 1','Term 2','Term 3') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `tuition` decimal(10,2) DEFAULT '0.00',
  `boarding` decimal(10,2) DEFAULT '0.00',
  `activity` decimal(10,2) DEFAULT '0.00',
  `others` decimal(10,2) DEFAULT '0.00',
  `total_amount` decimal(10,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fee_structure`
--

INSERT INTO `fee_structure` (`id`, `form`, `term`, `amount`, `academic_year`, `notes`, `created_at`, `tuition`, `boarding`, `activity`, `others`, `total_amount`) VALUES
(1, 'Form 1', 'Term 1', 14100.00, NULL, NULL, '2026-10-17 14:46:48', 10000.00, 3000.00, 970.00, 130.00, 14100.00),
(2, 'Form 2', 'Term 1', 15200.00, NULL, NULL, '2026-10-17 14:49:21', 11000.00, 3000.00, 990.00, 210.00, 15200.00);

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `grade_id` int NOT NULL,
  `student_id` int NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `form` varchar(50) DEFAULT NULL,
  `cat1` decimal(5,2) DEFAULT '0.00',
  `cat2` decimal(5,2) DEFAULT '0.00',
  `exam` decimal(5,2) DEFAULT '0.00',
  `total` decimal(6,2) DEFAULT '0.00',
  `grade` varchar(5) DEFAULT NULL,
  `recorded_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int NOT NULL,
  `sender_role` enum('student','teacher','staff','admin') NOT NULL,
  `sender_id` int DEFAULT NULL,
  `sender_name` varchar(100) NOT NULL,
  `sender_email` varchar(100) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `replied_at` timestamp NULL DEFAULT NULL,
  `admin_reply` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notices`
--

CREATE TABLE `notices` (
  `notice_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `type` enum('General','Homework','Academic','Event','Emergency') NOT NULL,
  `target_audience` varchar(20) DEFAULT 'Both',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notices`
--

INSERT INTO `notices` (`notice_id`, `title`, `content`, `type`, `target_audience`, `created_by`, `created_at`) VALUES
(1, 'Clossing Day', '<p>We will be going for the holiday break tommorow….be prepared for clossing meeting this evening. Thank you all !!</p>', 'General', 'Both', 4, '2026-10-17 15:20:18');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int NOT NULL,
  `student_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `method` enum('Cash','M-Pesa','Bank','Cheque','Card') DEFAULT 'Cash',
  `reference` varchar(100) DEFAULT NULL,
  `recorded_by` int DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `student_id`, `amount`, `payment_date`, `method`, `reference`, `recorded_by`, `notes`, `created_at`) VALUES
(1, 1, 15000.00, '2026-01-10', 'M-Pesa', 'RCP-8821', NULL, 'Term 1 Tuition Fees', '2026-10-14 06:12:56');

-- --------------------------------------------------------

--
-- Table structure for table `results`
--

CREATE TABLE `results` (
  `result_id` int NOT NULL,
  `exam_id` int NOT NULL,
  `student_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `marks` decimal(5,2) DEFAULT NULL,
  `grade` varchar(5) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `recorded_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `staff`
-- (See below for the actual view)
--
CREATE TABLE `staff` (
`staff_id` int
,`user_id` int
,`employee_id` varchar(50)
,`full_name` varchar(100)
,`email` varchar(100)
,`phone` varchar(20)
,`qualification` varchar(255)
,`experience` varchar(50)
,`assigned_form` varchar(50)
,`assigned_subject` varchar(100)
,`subject_id` int
,`status` enum('Active','Inactive')
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `standard`
-- (See below for the actual view)
--
CREATE TABLE `standard` (
`std_id` int
,`form` varchar(50)
,`level` enum('Primary','Secondary')
,`curriculum` enum('CBC','8-4-4')
,`sort_order` int
);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int NOT NULL,
  `user_id` int NOT NULL,
  `adm_no` varchar(20) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `form` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `parent_name` varchar(100) DEFAULT NULL,
  `parent_contact` varchar(20) DEFAULT NULL,
  `status` enum('Active','Suspended','Graduated') DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `user_id`, `adm_no`, `full_name`, `gender`, `form`, `email`, `dob`, `address`, `password`, `parent_name`, `parent_contact`, `status`, `created_at`) VALUES
(1, 6, 'ADM-7262', 'Mariba Vincent', 'Male', 'Form 1', 'vinniemariba2004@gmail.com', '2004-09-29', 'East West', '$2y$12$5WiPjDl.ycJIFPAi9VE4EeMNXjyXcbeAifqWETIJgoxmiGm2Jfm8G', 'Babustine Vince', '+25412345678', 'Active', '2026-10-13 16:01:19'),
(2, 9, 'ADM-3990', 'Angie W', 'Female', 'Form 2', 'angie@gmail.com', NULL, 'Nairobi', '$2y$12$M0EToWlA41NuHJ5r99tqyeNbwUmJbbZB1TH1nq484VoBLjfQjZafu', 'Wanjiku', '0732165438', 'Active', '2026-10-14 12:52:00');

-- --------------------------------------------------------

--
-- Table structure for table `student_clearance`
--

CREATE TABLE `student_clearance` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `finance_status` enum('Cleared','Pending','Blocked') DEFAULT 'Pending',
  `library_status` enum('Cleared','Pending','Blocked') DEFAULT 'Pending',
  `sports_status` enum('Cleared','Pending','Blocked') DEFAULT 'Pending',
  `hostel_status` enum('Cleared','Pending','Blocked') DEFAULT 'Pending',
  `dept_status` enum('Cleared','Pending','Blocked') DEFAULT 'Pending',
  `final_status` enum('In Progress','Completed','Blocked') DEFAULT 'In Progress',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_clearance`
--

INSERT INTO `student_clearance` (`id`, `student_id`, `finance_status`, `library_status`, `sports_status`, `hostel_status`, `dept_status`, `final_status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Pending', 'Pending', 'Pending', 'Pending', 'Pending', 'In Progress', '2026-10-14 07:02:01', '2026-10-14 07:02:01');

-- --------------------------------------------------------

--
-- Table structure for table `student_subjects`
--

CREATE TABLE `student_subjects` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `registration_date` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_subjects`
--

INSERT INTO `student_subjects` (`id`, `student_id`, `subject_id`, `created_at`, `registration_date`) VALUES
(3, 1, 1, '2026-10-14 06:49:01', '2026-10-14 10:00:03'),
(4, 1, 2, '2026-10-14 06:49:01', '2026-10-14 10:00:03'),
(5, 1, 3, '2026-10-14 06:49:01', '2026-10-14 10:00:03'),
(6, 1, 4, '2026-10-14 06:49:01', '2026-10-14 10:00:03'),
(7, 1, 6, '2026-10-14 06:49:01', '2026-10-14 10:00:03'),
(9, 1, 5, '2026-10-14 07:03:37', '2026-10-14 10:03:37'),
(15, 1, 10, '2026-10-14 09:28:09', '2026-10-14 12:28:09');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `subject_id` int NOT NULL,
  `subject_code` varchar(20) DEFAULT NULL,
  `subject_name` varchar(100) NOT NULL,
  `form` varchar(20) DEFAULT NULL,
  `term` enum('Term 1','Term 2','Term 3') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`subject_id`, `subject_code`, `subject_name`, `form`, `term`) VALUES
(1, NULL, 'Mathematics', 'Form 1', 'Term 1'),
(2, NULL, 'English', 'Form 1', 'Term 1'),
(3, NULL, 'Kiswahili', 'Form 1', 'Term 1'),
(4, 'BIO131', 'Biology', 'Form 1', 'Term 1'),
(5, NULL, 'Chemistry', 'Form 1', 'Term 1'),
(6, NULL, 'Physics', 'Form 1', 'Term 1'),
(7, NULL, 'History', 'Form 1', 'Term 1'),
(8, NULL, 'Geography', 'Form 1', 'Term 1'),
(9, NULL, 'CRE', 'Form 1', 'Term 1'),
(10, 'BST622', 'Business Studies', 'Form 1', 'Term 1'),
(11, 'Math501', 'Mathematics for Science', 'Form 2', 'Term 1');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int NOT NULL,
  `school_name` varchar(150) NOT NULL DEFAULT 'The Michaelson School',
  `logo_path` varchar(255) DEFAULT 'images/logo.jpg',
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `motto` varchar(150) DEFAULT 'Higher Is The Goal',
  `currency` varchar(10) DEFAULT 'KES',
  `academic_year` varchar(20) DEFAULT NULL,
  `current_term` enum('Term 1','Term 2','Term 3') DEFAULT 'Term 1',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `school_name`, `logo_path`, `address`, `phone`, `email`, `motto`, `currency`, `academic_year`, `current_term`, `updated_at`) VALUES
(1, 'The Michaelson School', 'images/logo.jpg', NULL, NULL, NULL, 'Higher Is The Goal', 'KES', NULL, 'Term 1', '2026-10-13 12:35:07');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `teacher_id` int NOT NULL,
  `user_id` int NOT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `qualification` varchar(255) DEFAULT NULL,
  `experience` varchar(50) DEFAULT NULL,
  `assigned_form` varchar(50) DEFAULT NULL,
  `assigned_subject` varchar(100) DEFAULT NULL,
  `subject_id` int DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`teacher_id`, `user_id`, `employee_id`, `full_name`, `email`, `phone`, `qualification`, `experience`, `assigned_form`, `assigned_subject`, `subject_id`, `status`, `created_at`) VALUES
(1, 7, '30554295', 'Kate T', 'kathrynmwamburi@gmail.com', '0797369845', 'English Litreture', '7 Years', 'Form 1', 'English', NULL, 'Active', '2026-10-13 16:18:36'),
(3, 10, 'STF/001/2026', 'Nancy K', 'stf0012026@michaelson.sc.ke', '0797369845', 'Dmnmsjnjer', '7 Years', NULL, NULL, NULL, 'Active', '2026-10-16 10:23:10');

-- --------------------------------------------------------

--
-- Table structure for table `timetable`
--

CREATE TABLE `timetable` (
  `timetable_id` int NOT NULL,
  `form` varchar(50) NOT NULL,
  `subject_id` int NOT NULL,
  `teacher_id` int DEFAULT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room_no` varchar(50) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `term` enum('Term 1','Term 2','Term 3') DEFAULT 'Term 1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `timetable`
--

INSERT INTO `timetable` (`timetable_id`, `form`, `subject_id`, `teacher_id`, `day_of_week`, `start_time`, `end_time`, `room_no`, `academic_year`, `term`, `created_at`) VALUES
(1, 'Form 2', 11, NULL, 'Monday', '12:00:00', '14:00:00', 'Lab 4', NULL, 'Term 1', '2026-10-17 14:38:58');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','teacher','student') NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `employee_id`, `email`, `full_name`, `password`, `role`, `last_login`, `created_at`) VALUES
(4, 'admin', NULL, NULL, NULL, 'admin123', 'admin', NULL, '2026-10-13 12:27:37'),
(6, 'ADM-7262', NULL, 'vinniemariba2004@gmail.com', NULL, '$2y$12$9nToPKAGbMkZF1x.3z87E.J2tJoIm0QLN4xH5pbaN6ylJG2TLlYCq', 'student', NULL, '2026-10-13 16:01:19'),
(7, '30554295', '30554295', 'kathrynmwamburi@gmail.com', 'Kate T', '$2y$12$GAgWdIfeZj./Fb23UG4oru7PRpk6jJAQSs5.2sfH2WpfG0/ApQSLC', 'teacher', NULL, '2026-10-13 16:18:36'),
(9, 'ADM-3990', NULL, 'angie@gmail.com', NULL, '$2y$12$M0EToWlA41NuHJ5r99tqyeNbwUmJbbZB1TH1nq484VoBLjfQjZafu', 'student', NULL, '2026-10-14 12:52:00'),
(10, 'STF/001/2026', 'STF/001/2026', 'stf0012026@michaelson.sc.ke', 'Nancy K', '$2y$12$4Q/fHj189eHlJIN6PvMHduTGKUTTa1kv8CnVn06U.Z4onsnpJLeWq', 'teacher', NULL, '2026-10-16 10:23:10');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_leaves`
--
ALTER TABLE `academic_leaves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student` (`student_id`);

--
-- Indexes for table `admission_applications`
--
ALTER TABLE `admission_applications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`class_id`),
  ADD UNIQUE KEY `unique_name` (`name`);

--
-- Indexes for table `coursework`
--
ALTER TABLE `coursework`
  ADD PRIMARY KEY (`cw_id`),
  ADD UNIQUE KEY `uq_student_subject_cw` (`student_id`,`subject_id`),
  ADD KEY `fk_cw_subject` (`subject_id`);

--
-- Indexes for table `downloads`
--
ALTER TABLE `downloads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`exam_id`),
  ADD KEY `fk_exam_subject` (`subject_id`);

--
-- Indexes for table `fees`
--
ALTER TABLE `fees`
  ADD PRIMARY KEY (`fee_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `fee_structure`
--
ALTER TABLE `fee_structure`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_form_term_year` (`form`,`term`,`academic_year`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`grade_id`),
  ADD UNIQUE KEY `unique_student_subject` (`student_id`,`subject_name`),
  ADD KEY `idx_form` (`form`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_sender_role` (`sender_role`);

--
-- Indexes for table `notices`
--
ALTER TABLE `notices`
  ADD PRIMARY KEY (`notice_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `results`
--
ALTER TABLE `results`
  ADD PRIMARY KEY (`result_id`),
  ADD UNIQUE KEY `unique_result` (`exam_id`,`student_id`,`subject_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `adm_no` (`adm_no`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `student_clearance`
--
ALTER TABLE `student_clearance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- Indexes for table `student_subjects`
--
ALTER TABLE `student_subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_subject` (`student_id`,`subject_id`),
  ADD KEY `fk_ss_subject` (`subject_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`subject_id`),
  ADD UNIQUE KEY `subject_code` (`subject_code`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`teacher_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `timetable`
--
ALTER TABLE `timetable`
  ADD PRIMARY KEY (`timetable_id`),
  ADD KEY `idx_form_day` (`form`,`day_of_week`),
  ADD KEY `fk_timetable_subject` (`subject_id`),
  ADD KEY `fk_timetable_teacher` (`teacher_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `employee_id` (`employee_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_leaves`
--
ALTER TABLE `academic_leaves`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `admission_applications`
--
ALTER TABLE `admission_applications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `class_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `coursework`
--
ALTER TABLE `coursework`
  MODIFY `cw_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `downloads`
--
ALTER TABLE `downloads`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exams`
--
ALTER TABLE `exams`
  MODIFY `exam_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `fees`
--
ALTER TABLE `fees`
  MODIFY `fee_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fee_structure`
--
ALTER TABLE `fee_structure`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `grade_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notices`
--
ALTER TABLE `notices`
  MODIFY `notice_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `results`
--
ALTER TABLE `results`
  MODIFY `result_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `student_clearance`
--
ALTER TABLE `student_clearance`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `student_subjects`
--
ALTER TABLE `student_subjects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `subject_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `teacher_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `timetable`
--
ALTER TABLE `timetable`
  MODIFY `timetable_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

-- --------------------------------------------------------

--
-- Structure for view `staff`
--
DROP TABLE IF EXISTS `staff`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `staff`  AS SELECT `teachers`.`teacher_id` AS `staff_id`, `teachers`.`user_id` AS `user_id`, `teachers`.`employee_id` AS `employee_id`, `teachers`.`full_name` AS `full_name`, `teachers`.`email` AS `email`, `teachers`.`phone` AS `phone`, `teachers`.`qualification` AS `qualification`, `teachers`.`experience` AS `experience`, `teachers`.`assigned_form` AS `assigned_form`, `teachers`.`assigned_subject` AS `assigned_subject`, `teachers`.`subject_id` AS `subject_id`, `teachers`.`status` AS `status`, `teachers`.`created_at` AS `created_at` FROM `teachers` ;

-- --------------------------------------------------------

--
-- Structure for view `standard`
--
DROP TABLE IF EXISTS `standard`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `standard`  AS SELECT `classes`.`class_id` AS `std_id`, `classes`.`name` AS `form`, `classes`.`level` AS `level`, `classes`.`curriculum` AS `curriculum`, `classes`.`sort_order` AS `sort_order` FROM `classes` ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `academic_leaves`
--
ALTER TABLE `academic_leaves`
  ADD CONSTRAINT `fk_leave_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `coursework`
--
ALTER TABLE `coursework`
  ADD CONSTRAINT `fk_cw_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cw_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE;

--
-- Constraints for table `exams`
--
ALTER TABLE `exams`
  ADD CONSTRAINT `fk_exam_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE SET NULL;

--
-- Constraints for table `fees`
--
ALTER TABLE `fees`
  ADD CONSTRAINT `fees_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `fk_grades_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `results`
--
ALTER TABLE `results`
  ADD CONSTRAINT `results_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`exam_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_clearance`
--
ALTER TABLE `student_clearance`
  ADD CONSTRAINT `fk_clear_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_subjects`
--
ALTER TABLE `student_subjects`
  ADD CONSTRAINT `fk_ss_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ss_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE;

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `timetable`
--
ALTER TABLE `timetable`
  ADD CONSTRAINT `fk_timetable_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_timetable_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;