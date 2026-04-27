-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 27, 2026 at 10:59 PM
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
-- Database: `mentormatch`
--

-- --------------------------------------------------------

--
-- Table structure for table `availability`
--

CREATE TABLE `availability` (
  `id` int(11) NOT NULL,
  `mentor_id` int(11) NOT NULL,
  `available_date` date DEFAULT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `day_of_week` tinyint(4) DEFAULT NULL,
  `is_recurring` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `availability`
--

INSERT INTO `availability` (`id`, `mentor_id`, `available_date`, `start_time`, `end_time`, `day_of_week`, `is_recurring`) VALUES
(2, 13, NULL, '13:00:00', '15:00:00', 1, 1),
(3, 13, NULL, '13:00:00', '15:00:00', 2, 1),
(4, 13, NULL, '13:00:00', '15:00:00', 3, 1),
(5, 13, NULL, '13:00:00', '15:00:00', 4, 1),
(6, 13, NULL, '13:00:00', '15:00:00', 5, 1),
(9, 13, NULL, '15:30:00', '17:00:00', 1, 1),
(10, 13, NULL, '15:30:00', '17:00:00', 2, 1),
(11, 13, NULL, '15:30:00', '17:00:00', 3, 1),
(12, 13, NULL, '15:30:00', '17:00:00', 4, 1),
(13, 13, NULL, '15:30:00', '17:00:00', 5, 1);

-- --------------------------------------------------------

--
-- Table structure for table `mentor_applications`
--

CREATE TABLE `mentor_applications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `motivation` text NOT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `github` varchar(255) DEFAULT NULL,
  `experience_years` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mentor_applications`
--

INSERT INTO `mentor_applications` (`id`, `user_id`, `motivation`, `linkedin`, `github`, `experience_years`, `status`, `admin_notes`, `submitted_at`, `reviewed_at`) VALUES
(2, 13, 'I have worked in industry', NULL, NULL, 4, 'approved', NULL, '2026-02-11 01:36:10', '2026-02-15 03:16:22'),
(3, 14, 'So that I can share my knowledge with more students', NULL, NULL, 2, 'approved', NULL, '2026-02-16 02:41:10', '2026-02-16 02:46:09');

-- --------------------------------------------------------

--
-- Table structure for table `mentor_application_subjects`
--

CREATE TABLE `mentor_application_subjects` (
  `application_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mentor_application_subjects`
--

INSERT INTO `mentor_application_subjects` (`application_id`, `subject_id`) VALUES
(2, 5),
(3, 11);

-- --------------------------------------------------------

--
-- Table structure for table `mentor_profiles`
--

CREATE TABLE `mentor_profiles` (
  `mentor_id` int(11) NOT NULL,
  `bio` text DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `github` varchar(255) DEFAULT NULL,
  `experience_years` int(11) DEFAULT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mentor_profiles`
--

INSERT INTO `mentor_profiles` (`mentor_id`, `bio`, `linkedin`, `github`, `experience_years`, `verified`, `user_id`) VALUES
(13, 'I am a passionate Computer Science mentor with a strong background in software development and problem-solving. Whether it’s preparing for exams, debugging code, or exploring new technologies, I am committed to supporting his mentees every step of the way. Outside of mentoring, I enjoy contributing to open-source projects and staying up to date with the latest trends in tech', 'https://www.linkedin.com/in/kwadwo-antwi/', 'https://github.com/Kojo6771', 4, 1, 13),
(14, NULL, NULL, NULL, 2, 1, 14),
(25, 'Software engineering mentor focused on web development and clean architecture.', 'https://www.linkedin.com/in/ada-demo', 'https://github.com/ada-demo', 5, 1, 25),
(26, 'Data and algorithms mentor with strong maths/statistics support for undergraduates.', 'https://www.linkedin.com/in/daniel-demo', 'https://github.com/daniel-demo', 4, 1, 26);

-- --------------------------------------------------------

--
-- Table structure for table `mentor_ratings`
--

CREATE TABLE `mentor_ratings` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `mentor_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mentor_ratings`
--

INSERT INTO `mentor_ratings` (`id`, `student_id`, `mentor_id`, `rating`, `created_at`, `updated_at`) VALUES
(1, 15, 13, 4, '2026-03-12 18:33:15', '2026-03-12 18:33:15'),
(2, 27, 25, 5, '2026-03-11 16:20:00', '2026-03-11 16:20:00'),
(3, 28, 25, 4, '2026-03-18 12:10:00', '2026-03-18 12:10:00'),
(4, 29, 26, 5, '2026-03-22 15:30:00', '2026-03-22 15:30:00'),
(5, 30, 26, 4, '2026-03-26 09:00:00', '2026-03-26 09:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `mentor_requests`
--

CREATE TABLE `mentor_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `mentor_id` int(11) NOT NULL,
  `status` enum('pending','accepted','rejected','cancelled') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mentor_requests`
--

INSERT INTO `mentor_requests` (`id`, `student_id`, `mentor_id`, `status`, `requested_at`, `responded_at`) VALUES
(10, 15, 13, 'accepted', '2026-03-07 00:50:47', '2026-03-12 18:23:05'),
(11, 15, 14, 'cancelled', '2026-03-07 00:50:48', NULL),
(12, 1, 13, 'accepted', '2026-03-07 01:19:14', '2026-03-12 18:23:07'),
(13, 1, 14, 'cancelled', '2026-03-07 01:19:16', NULL),
(16, 17, 13, 'pending', '2026-04-23 02:53:21', NULL),
(17, 27, 25, 'accepted', '2026-03-01 10:15:00', '2026-03-01 13:00:00'),
(18, 28, 25, 'accepted', '2026-03-02 11:20:00', '2026-03-02 14:10:00'),
(19, 29, 26, 'accepted', '2026-03-04 09:45:00', '2026-03-04 12:30:00'),
(20, 30, 26, 'accepted', '2026-03-06 16:00:00', '2026-03-06 17:40:00'),
(21, 27, 26, 'pending', '2026-04-20 09:30:00', NULL),
(22, 29, 25, 'pending', '2026-04-21 14:05:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `mentor_student_matches`
--

CREATE TABLE `mentor_student_matches` (
  `id` int(11) NOT NULL,
  `mentor_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `matched_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mentor_student_matches`
--

INSERT INTO `mentor_student_matches` (`id`, `mentor_id`, `student_id`, `matched_at`, `active`) VALUES
(2, 13, 15, '2026-03-05 18:23:05', 1),
(3, 13, 1, '2026-03-12 18:23:07', 1),
(5, 25, 27, '2026-03-01 13:00:00', 1),
(6, 25, 28, '2026-03-02 14:10:00', 1),
(7, 26, 29, '2026-03-04 12:30:00', 1),
(8, 26, 30, '2026-03-06 17:40:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `mentor_subjects`
--

CREATE TABLE `mentor_subjects` (
  `mentor_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mentor_subjects`
--

INSERT INTO `mentor_subjects` (`mentor_id`, `subject_id`) VALUES
(13, 5),
(14, 11),
(25, 1),
(25, 5),
(26, 6),
(26, 12);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `message`, `sent_at`, `read_at`) VALUES
(1, 12, 13, '⚠️ Admin Warning: Your session has passed and hasn\'t been marked as completed. Please update its status.', '2026-03-17 21:32:54', '2026-03-17 21:33:05'),
(2, 12, 13, '⚠️ Admin Warning: You currently have no upcoming sessions with Alistair Ridley. Please propose a session soon.', '2026-03-19 08:40:02', '2026-03-19 08:40:19'),
(3, 12, 13, '⚠️ Admin Warning: You currently have no upcoming sessions with Alistair Ridley. Please propose a session soon.', '2026-03-19 10:32:51', '2026-03-19 10:44:39'),
(4, 12, 13, '⚠️ Admin Warning: You currently have no upcoming sessions with Alistair Ridley. Please propose a session soon.', '2026-03-19 10:39:08', '2026-03-19 10:44:39'),
(5, 12, 13, '⚠️ Admin Warning: Your session with Alistair Ridley has passed and hasn\'t been marked as completed. Please update its status.', '2026-03-20 02:16:22', '2026-03-20 02:40:40'),
(6, 12, 13, '⚠️ Admin Warning: Your session with Stacey Slater has passed and hasn\'t been marked as completed. Please update its status.', '2026-04-07 00:46:50', '2026-04-07 01:47:08'),
(7, 27, 25, 'Hi Ada, thanks again for the DS session. The tree traversal part finally clicked.', '2026-03-11 16:10:00', '2026-03-11 16:12:00'),
(8, 25, 27, 'Great progress. Next week we can practice timed coding rounds.', '2026-03-11 16:14:00', '2026-03-11 16:15:00'),
(9, 28, 25, 'Can we revisit integration by parts before the quiz?', '2026-03-17 20:30:00', '2026-03-17 20:33:00'),
(10, 25, 28, 'Absolutely. I uploaded three practice questions in your notes.', '2026-03-17 20:35:00', '2026-03-17 20:37:00'),
(11, 29, 26, 'Today\'s case workshop was useful. I\'ll rewrite my arguments tonight.', '2026-03-22 15:05:00', '2026-03-22 15:10:00'),
(12, 26, 29, 'Nice work. Share draft 2 and I will annotate it before Friday.', '2026-03-22 15:12:00', '2026-03-22 15:18:00'),
(13, 30, 26, 'Sorry I had to cancel. Can we book another stats session this week?', '2026-03-25 18:08:00', NULL),
(14, 25, 27, 'I have shared extra API exercises. Ping me once you try them.', '2026-04-26 17:05:00', NULL),
(15, 28, 25, 'Could we move Thursday by 30 minutes if possible?', '2026-04-26 18:20:00', NULL),
(16, 26, 29, 'Please upload your updated case summary before tomorrow.', '2026-04-26 19:40:00', NULL),
(17, 30, 26, 'I can do Sunday 4:30pm, just sent the booking request.', '2026-04-26 20:05:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `email`, `code_hash`, `expires_at`, `verified_at`, `used_at`, `created_at`) VALUES
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$ZL4oPWjol/KiOYpQsPF2S.KMBQSpC0xyV6IbndpiB5cVAMc5bcVIK', '2026-03-20 21:47:04', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-20 20:32:04'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$Cliamv/IzoSX35L4w7i6rOk.o1sWWcOFtKY1ZN5I3fJy1290hlCVW', '2026-03-21 23:23:41', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-21 22:08:41'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$EHau2CMp0JX/MtXcFyTXAOvXfVcR9kSmSE9M70R3lcOlU5aZcgDMe', '2026-03-22 03:26:18', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:11:18'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$cWP2GqhXqFMeRTgwDy0QDORE9Bk/qt3VVvx5o7gMhdnwE7UZv1V6y', '2026-03-22 03:26:21', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:11:21'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$I8oT4Jf0TfaHe3m1r14FJOCZ84FxR3sjCQEtcL4SgzpfXAncjGVIK', '2026-03-22 03:26:22', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:11:22'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$DTFRVhEiwHXZ5CC0BK/wd.OSrX8.lWmsj9cgX3j/bByxlFinjD1qK', '2026-03-22 03:26:42', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:11:42'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$//raBzR1ElLfTv0w3g.aWuNF8IFjnTQNDHpLiSkqVb.gf.jyCdJwK', '2026-03-22 03:30:42', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:15:42'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$LF9nCEM5Oqf69FC1Zn9ve.30qIWtaTCIb7CXtszJ75wfnMc7lSIZK', '2026-03-22 03:31:39', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:16:39'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$m.SzYUEzPqw6LSm9IiaYWONHFweIsbzG5v3vKh.hUOl5YTJr0f6bm', '2026-03-22 03:37:17', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:22:17'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$qYidK7aj8552UiBcg1J9rOEX1/dLdy/2Q5cJaSAIXtleaRrsf/mry', '2026-03-22 03:37:22', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:22:22'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$kNtGGIvLP8S2dZIMxuxzV.ezax6QbH0bnK5zIcrosu/Rg8cAZBJbC', '2026-03-22 03:41:45', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:26:45'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$xLZOg50LwFL2LP.2xE993uxV4eMydSrwRiglYbLtj3Y6WkCcW2vhW', '2026-03-22 03:41:47', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:26:47'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$K0nOswHAx43cVB9tLS4dOOPn5dS9RXFdYwBUBHST/ktyhlelqdYqG', '2026-03-22 03:41:51', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:26:51'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$mpS1ZNtkN0gGDAOLowEiAOGvYEV3gsXWz8hYQubtm8sSA5Q3qPkcO', '2026-03-22 03:41:52', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:26:52'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$rM2YQMV6keMovEtFCmETReUPnNaNkEUghETKla9VevADy3WuxuYJa', '2026-03-22 03:42:01', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:27:01'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$2oCTec1rsSoVm2MQUJwWqOHrrqENu1UQjmBVsdrWOGBddch5bFOEa', '2026-03-22 03:42:02', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:27:02'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$EdCMb44u4FcHHgw2ZuGgLuNUi2Q/vpWpe6kWuptRHf4cLDhAXRRfq', '2026-03-22 03:42:07', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:27:07'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$aUK9GIWXFiPB5.yWKsG3f.8j2pcVsEjwc9TIj2XG4MFJ9KUQE/KpG', '2026-03-22 03:42:37', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:27:37'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$ywMfrgWSSVo3kpcKnSuHt.le9mecYbqnFN/3DXKx0tnCC.fEG4YKm', '2026-03-22 03:42:39', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:27:39'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$hHgeNqV/uJqh0uyvWjCVKe5ntffzyUJ6DhhdGrOBJGUQBliSWFgwm', '2026-03-22 03:42:40', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:27:40'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$xte07uxbZzVyMsveW3yr/u/AaGLX5Qfc1Gt7xCPidLngWxtNTk4sS', '2026-03-22 03:45:13', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:30:13'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$H5efDH0kbxbDHzoiqf2HleqVgGsaKZgcu1kqqQIyB/mYhrK5OUrCC', '2026-03-22 03:45:14', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:30:14'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$NQMZgyGQ7JLKtn7MMzygYeeL44h.wjKmq5iDf3ICjhiI2xMrM4ouW', '2026-03-22 03:45:15', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:30:15'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$JTWjgxDttpkhpYxJ4bQg4uDjYWRDEI6qpb.IFF/30AuIqMwy/Byiq', '2026-03-22 03:45:19', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:30:19'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$dYI8AlEunrBbf1VSf6yeTOYp2/9P01muB5DdOJgmQhYYTFfKL9hiO', '2026-03-22 03:58:48', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:43:48'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$zyy.cGyrwi7dyGQTfLac3.Q.wMvBGZDAkdoQEt8D6YeNpS4eH8Gxe', '2026-03-22 03:59:05', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:44:05'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$Daf20Q/AHRqcns2FOpD.G.oNJfHTBNtg4HtwETwZw2rLTrc2dHBym', '2026-03-22 04:00:49', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:45:49'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$C.jsE2T62fi1LVx3lpyYgeGP7qdgmFI1iglmCrytDWmZ.O66n0kjq', '2026-03-22 04:00:50', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:45:50'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$2adpRunBD0MOPg4PGkLocuZBSeIS.jLlfFJeRSxmsc/qPEnZs1Ps2', '2026-03-22 04:00:51', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:45:51'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$EnH6TZNjVt4m5lv20c7VFO56RJfm2hJYs9KVXj/OOlR28ia99El0S', '2026-03-22 04:14:31', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 02:59:31'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$o3ad8cVK6mPy8zk8BrRiYuWxGELD/26h88O//r5Y.nCIesPXsUJQO', '2026-03-22 04:15:57', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 03:00:57'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$gFWaZZ1sUcXiaACb1X70Hu8X5qtqtltlwRyc2BbCH5i9bRm7jPhQi', '2026-03-22 04:16:44', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 03:01:44'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$RwLn7hpT0o0nSV3pm/nIp.kYHVhcXh9DqhRtCZNUZKjIbQcizOfxa', '2026-03-22 05:17:04', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 04:02:04'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$H7k6YUPAFykSz5HoD6y3Ue4m.eKnZmulKIq6EYI2Ghj76gpcDE0SC', '2026-03-22 05:21:07', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 04:06:07'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$dk/YxSNOZS8oBoVjhVUsNOl2M1SQd50ReTOdZpqrDYRNhhzcaDHnG', '2026-03-22 05:28:53', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 04:13:53'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$4Jb1KteduVtpY0XDlkeOOOuF9EOvipL/XXS/PxFzYh10GdtrgkPNm', '2026-03-22 05:28:56', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 04:13:56'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$Q9/j9x9hpcQbaf/9LYi84ep4DS8sqoR2e5b8w991TRFa8xWncT5My', '2026-03-22 05:28:58', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 04:13:58'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$JI7zW2druEE2MhkH8FZ0xOafyaGLFDzC81KAlHh2Q8ceSEhcxzh42', '2026-03-22 05:37:48', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 04:22:48'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$wGU51Vuo.rS/UckhdjeseeE8xjDBe7f/NT8WjydIZGc2MpZwFjP2.', '2026-03-22 05:46:49', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 04:31:49'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$Qc.aF9S.P/TfNEJDc7p1iOaRU4hvgGo2fnCKPDN909.MbMKqBacSe', '2026-03-22 05:48:23', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 04:33:23'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$.Y40Bcm1BobPrQu64ddeM.4a5YqAokbNCowusB0rF0xpRcfRsFIjm', '2026-03-22 05:48:25', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 04:33:25'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$iu1Omtzf3Y2UOmWeX5yDl.bI6CLJQWacyu0TAwJuJWBxIgzeDxzb.', '2026-03-22 05:58:48', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 04:43:48'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$yY3qCPlFR9hC7Ey/qRFdCuOd/Cn1LbK/FcjyBrXoEwtFC55FrNToa', '2026-03-22 06:05:29', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 04:50:29'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$fREpRe/UBP4yNxrvjGG5ju4ckil.6ENwOAC4ukIGj607bs4f/XUoi', '2026-03-22 06:10:58', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 04:55:58'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$QfWWtegcNOyLrxUdOuoWs.Js2bYOlk71HXd4qBqtWTWcq.2dEJuY6', '2026-03-22 06:28:29', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 05:13:29'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$eDhDoMbsTXboMizOG6Tv8eGr4FSTgtzNSzk1geowGxRl7Hn/c7Ob2', '2026-03-22 06:34:19', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 05:19:19'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$qwUTHGzoEc.6oc5bXWn51eQOqKugxwT9snAR9V38xaQyGME.VLRfW', '2026-03-22 07:12:35', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 05:57:35'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$rYdJ87EwNVwp6CqBRVK1h.sZQx1mty1FKO4Gph1OXamsPxER/0bRO', '2026-03-22 07:27:28', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 06:12:28'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$vcoxOR9499zZPQLmq/5SpOL.AQr1xCm/R7fOxpge/k1Jwkfaq4iCe', '2026-03-22 07:27:30', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 06:12:30'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$gXew4afdV5V3mLLGiL/XpO7ftQoaxpDmlQAxqcoEB.za79qwlg9Sy', '2026-03-22 07:50:27', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 06:35:27'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$9BXwgiGWwsOhHT.xW65YtePMIkwBBJGd616xmHG0cAXKUdNhY5fWm', '2026-03-22 07:51:14', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 06:36:14'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$nnwgl9G..f9WVU8e4Nh12..hjHh3qOzlk8l72G6Pi8tI29AYTJfiq', '2026-03-22 07:51:36', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 06:36:36'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$8cb9jQE6/3OOmffj7UKi5uSk/IuOsSzfM/ClE9w7lOzlwJXiVFtV2', '2026-03-22 07:53:43', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 06:38:43'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$eWdihheS7RYxYqVVs5ruKO//ft6VZGpsMQ6m9nULDLuO4O3xNN/zK', '2026-03-22 07:54:34', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 06:39:34'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$ouijYKORzipHZncHLFmtPOy4db4SZKfWHytg9PbJZ8Ce3ywfSiyli', '2026-03-22 07:55:33', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-03-22 06:40:33'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$7NGhsdMw5IWVANzorBdeKOeAKW9RPgO9guHNW8749GBIpgKTCy1Hi', '2026-04-02 22:03:11', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-04-02 19:48:11'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$cOJytN4xDeOlpDqL1V1t5uGXZupO39GtRawSf26hVkdPdaaTWjfeK', '2026-04-03 01:40:38', '2026-04-22 23:15:09', '2026-04-03 00:26:35', '2026-04-02 23:25:38'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$yL9hOC3yLKuFuVlpwkF7xefLfCe3LhIEU9z9.OYMfLCQCorpSq3YK', '2026-04-22 23:31:24', '2026-04-22 23:15:09', '2026-04-22 23:14:05', '2026-04-22 21:16:24'),
(0, 12, 'k_wad_wo@hotmail.co.uk', '$2y$10$PLujixdyAtYamy9OL5EMEuyfBG2NeP8YO5BCOjYDjgrIvKpfZQICG', '2026-04-23 00:29:05', '2026-04-22 23:15:09', NULL, '2026-04-22 22:14:05');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `mentor_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `session_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time DEFAULT NULL,
  `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  `proposed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `student_id`, `mentor_id`, `subject_id`, `title`, `description`, `location`, `session_date`, `start_time`, `end_time`, `status`, `proposed_by`, `created_at`) VALUES
(1, 15, 13, 5, 'OOP', 'to do oop labs', 'Aston University Libary', '2026-03-12', '13:00:00', '15:00:00', 'completed', 13, '2026-03-17 21:04:50'),
(3, 1, 13, 5, 'IAD', NULL, 'Aston University Libary', '2026-03-19', '15:30:00', '17:00:00', 'completed', 13, '2026-03-19 10:45:09'),
(4, 15, 13, 5, 'Web Developemnt', 'How to connect API\'s', 'Aston University Libary', '2026-04-06', '13:00:00', '15:00:00', 'completed', 15, '2026-04-04 17:58:10'),
(5, 27, 25, 5, 'Data Structures Revision', 'Linked lists, trees, and recursion drills.', 'Aston Library - Room 2', '2026-03-11', '14:00:00', '15:30:00', 'completed', 25, '2026-03-08 09:00:00'),
(6, 28, 25, 1, 'Calculus Support Session', 'Differentiation and integration practice questions.', 'Aston Library - Room 1', '2026-03-18', '11:00:00', '12:00:00', 'completed', 28, '2026-03-15 18:20:00'),
(7, 29, 26, 12, 'Contract Law Case Workshop', 'Reviewing case structure and argument clarity.', 'Business School - Seminar 4', '2026-03-22', '13:30:00', '14:30:00', 'completed', 26, '2026-03-20 10:40:00'),
(8, 30, 26, 6, 'Intro to Hypothesis Testing', 'Hypothesis design and p-value interpretation.', 'Online (Teams)', '2026-03-25', '16:00:00', '17:00:00', 'cancelled', 30, '2026-03-24 09:15:00'),
(9, 27, 25, 5, 'API Design Mock Interview', 'Practice system design and endpoint review.', 'Aston Library - Room 5', '2026-05-05', '10:00:00', '11:00:00', 'confirmed', 25, '2026-04-24 08:10:00'),
(10, 28, 25, 1, 'Calculus Past Paper Walkthrough', 'Focused prep for derivatives and optimization.', 'Online (Teams)', '2026-05-07', '14:00:00', '15:00:00', 'pending', 28, '2026-04-24 10:30:00'),
(11, 29, 26, 12, 'Essay Structure Clinic', 'Refine issue spotting and conclusion drafting.', 'Business School - Seminar 3', '2026-05-08', '12:30:00', '13:30:00', 'confirmed', 26, '2026-04-24 12:45:00'),
(12, 30, 26, 6, 'Statistics Quiz Prep', 'Confidence pass on sampling distributions.', 'Online (Teams)', '2026-05-10', '16:30:00', '17:30:00', 'confirmed', 30, '2026-04-24 15:10:00');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `course` varchar(150) NOT NULL,
  `year_of_study` int(11) NOT NULL,
  `learning_preference` enum('Videos','In person sessions','Quizzes') NOT NULL,
  `bio` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) NOT NULL,
  `mentor_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `course`, `year_of_study`, `learning_preference`, `bio`, `created_at`, `user_id`, `mentor_id`) VALUES
(1, 'Engineering', 1, 'In person sessions', '', '2026-02-20 23:39:36', 1, 13),
(12, 'Computer science', 3, 'In person sessions', 'I want to be a software developer', '2026-02-11 01:23:13', 12, NULL),
(15, 'Computer Science', 1, 'In person sessions', 'I am in my first year doing Object oriented programming', '2026-03-07 00:50:39', 15, 13),
(17, 'English', 1, 'In person sessions', 'I am studying english and need help with plays', '2026-03-19 11:19:16', 17, NULL),
(27, 'Computer Science', 2, 'In person sessions', 'Working on web modules and backend design.', '2026-04-27 12:15:17', 27, 25),
(28, 'Computer Science', 1, 'Videos', 'Needs support with Python and problem decomposition.', '2026-04-27 12:15:17', 28, 25),
(29, 'Law', 2, 'In person sessions', 'Preparing for contract law coursework and case analysis.', '2026-04-27 12:15:17', 29, 26),
(30, 'Statistics', 1, 'Quizzes', 'Building confidence in probability and hypothesis testing.', '2026-04-27 12:15:17', 30, 26);

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `name`) VALUES
(8, 'Accounting'),
(4, 'Biology'),
(9, 'Business Studies'),
(3, 'Chemistry'),
(5, 'Computer Science'),
(7, 'Economics'),
(13, 'Engineering'),
(15, 'English'),
(14, 'History'),
(12, 'Law'),
(1, 'Mathematics'),
(2, 'Physics'),
(10, 'Psychology'),
(11, 'Sociology'),
(6, 'Statistics');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','mentor','admin') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL,
  `oauth_provider` varchar(20) DEFAULT NULL,
  `oauth_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `phone`, `email`, `password`, `role`, `created_at`, `profile_picture`, `oauth_provider`, `oauth_id`) VALUES
(1, 'Alistair', 'Ridley', '078456621323', 'mrdownbad@gmail.com', '$2y$10$.UTYGKQKYJRsJ4Wmz3rCfeI7hFz/ZEV4Ggfjwd.ZK4R31fWkJsq8q', 'student', '2026-01-29 22:09:09', NULL, NULL, NULL),
(12, 'Kojo', 'Antwi', '07463885316', 'k_wad_wo@hotmail.co.uk', '$2y$10$BMMjfPfsCVpZpOcZbu04ce77rBcXeI408VjaiTBykIQaYK9t2cfk6', 'admin', '2026-02-11 01:22:41', 'uploads/profile_pictures/profile_698bd9e129821_1770772961.jpg', 'microsoft', '4935826a04a26654'),
(13, 'Jacob', 'Harvey', '07463885316', 'JHBlack@gmail.com', '$2y$10$5C4xySz9am0eWBQWf/BIQuDJ5tMdidEqTBxX2m7lSLG/qpmRNKJpS', 'mentor', '2026-02-11 01:35:51', 'uploads/profile_pictures/profile_69e97dd30053e3.09613944_1776909779.jpg', NULL, NULL),
(14, 'George', 'Burell', '07809639807', 'GJ@gmail.com', '$2y$10$mY9.9HyJju6ePXFPlwO/A.ykCpIHFusAqaqzfHK3njZkaoKDPCnv2', 'mentor', '2026-02-16 02:40:36', 'uploads/profile_pictures/profile_69ef5ac1ba6455.48523413_1777294017.webp', NULL, NULL),
(15, 'Stacey', 'Slater', '07463885316', 'ST@gmail.com', '$2y$10$OctrS1.jo8qRN2NB3ofIfeEMmgvz5xcPVOGOjMCf3VmtJFQlkGVqK', 'student', '2026-03-07 00:49:00', 'uploads/profile_pictures/profile_69ab75fcd2b29_1772844540.webp', NULL, NULL),
(17, 'Phil', 'Mitchel', '07463885316', 'Phil123@gmail.com', '$2y$10$zzJVwvGZcjyCNCe1TgdeJ.N.uvnu.1OEWFFUQ6K4BOwPDt4zrLEze', 'student', '2026-03-19 11:18:34', 'uploads/profile_pictures/profile_69bbdb8a548d9_1773919114.jpg', NULL, NULL),
(25, 'Ada', 'Mensah', '07400111222', 'AM@gmail.com', '$2y$10$uY1psfiGVQFicWMbAgQy3us3xc1SD6EaRXGG0H.VcEVBgkWbo6K/a', 'mentor', '2026-04-27 12:15:17', 'uploads/profile_pictures/profile_69ef5d6b028137.80224882_1777294699.webp', NULL, NULL),
(26, 'Daniel', 'Okoro', '07400111333', 'daniel123@gmail.com', '$2y$10$uY1psfiGVQFicWMbAgQy3us3xc1SD6EaRXGG0H.VcEVBgkWbo6K/a', 'mentor', '2026-04-27 12:15:17', 'uploads/profile_pictures/profile_69ef5c9acba2e3.43270185_1777294490.jpg', NULL, NULL),
(27, 'Ethan', 'Cole', '07400111444', 'Ethan.Cole@gmail.com', '$2y$10$uY1psfiGVQFicWMbAgQy3us3xc1SD6EaRXGG0H.VcEVBgkWbo6K/a', 'student', '2026-04-27 12:15:17', 'uploads/profile_pictures/profile_69ef5d2f7fe6e1.63156451_1777294639.webp', NULL, NULL),
(28, 'Maya', 'Singh', '07400111555', 'maya_S_May@gmail.com', '$2y$10$uY1psfiGVQFicWMbAgQy3us3xc1SD6EaRXGG0H.VcEVBgkWbo6K/a', 'student', '2026-04-27 12:15:17', 'uploads/profile_pictures/profile_69ef5cc8c4f360.34183359_1777294536.webp', NULL, NULL),
(29, 'Liam', 'Baker', '07400111666', 'liam.Baker@gmail.com', '$2y$10$uY1psfiGVQFicWMbAgQy3us3xc1SD6EaRXGG0H.VcEVBgkWbo6K/a', 'student', '2026-04-27 12:15:17', 'uploads/profile_pictures/profile_69ef5b0889bb97.08602931_1777294088.webp', NULL, NULL),
(30, 'Zoe', 'Slater', '07400111777', 'Zoe.Slater@gmail.com', '$2y$10$uY1psfiGVQFicWMbAgQy3us3xc1SD6EaRXGG0H.VcEVBgkWbo6K/a', 'student', '2026-04-27 12:15:17', 'uploads/profile_pictures/profile_69ef5b5171d856.41678229_1777294161.webp', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `availability`
--
ALTER TABLE `availability`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mentor_id` (`mentor_id`);

--
-- Indexes for table `mentor_applications`
--
ALTER TABLE `mentor_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `mentor_application_subjects`
--
ALTER TABLE `mentor_application_subjects`
  ADD PRIMARY KEY (`application_id`,`subject_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `mentor_profiles`
--
ALTER TABLE `mentor_profiles`
  ADD PRIMARY KEY (`mentor_id`),
  ADD KEY `mentor_profiles_user_fk` (`user_id`);

--
-- Indexes for table `mentor_ratings`
--
ALTER TABLE `mentor_ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_mentor` (`student_id`,`mentor_id`);

--
-- Indexes for table `mentor_requests`
--
ALTER TABLE `mentor_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_active_request` (`student_id`,`mentor_id`),
  ADD KEY `fk_request_mentor` (`mentor_id`);

--
-- Indexes for table `mentor_student_matches`
--
ALTER TABLE `mentor_student_matches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_active_match` (`student_id`),
  ADD KEY `fk_match_mentor` (`mentor_id`);

--
-- Indexes for table `mentor_subjects`
--
ALTER TABLE `mentor_subjects`
  ADD PRIMARY KEY (`mentor_id`,`subject_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `mentor_id` (`mentor_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD KEY `students_user_fk` (`user_id`),
  ADD KEY `fk_student_mentor` (`mentor_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `availability`
--
ALTER TABLE `availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `mentor_applications`
--
ALTER TABLE `mentor_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `mentor_ratings`
--
ALTER TABLE `mentor_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `mentor_requests`
--
ALTER TABLE `mentor_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `mentor_student_matches`
--
ALTER TABLE `mentor_student_matches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `availability`
--
ALTER TABLE `availability`
  ADD CONSTRAINT `availability_ibfk_1` FOREIGN KEY (`mentor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mentor_applications`
--
ALTER TABLE `mentor_applications`
  ADD CONSTRAINT `mentor_applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mentor_application_subjects`
--
ALTER TABLE `mentor_application_subjects`
  ADD CONSTRAINT `mentor_application_subjects_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `mentor_applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mentor_application_subjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mentor_profiles`
--
ALTER TABLE `mentor_profiles`
  ADD CONSTRAINT `mentor_profiles_ibfk_1` FOREIGN KEY (`mentor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mentor_profiles_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mentor_requests`
--
ALTER TABLE `mentor_requests`
  ADD CONSTRAINT `fk_request_mentor` FOREIGN KEY (`mentor_id`) REFERENCES `mentor_profiles` (`mentor_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_request_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `mentor_student_matches`
--
ALTER TABLE `mentor_student_matches`
  ADD CONSTRAINT `fk_match_mentor` FOREIGN KEY (`mentor_id`) REFERENCES `mentor_profiles` (`mentor_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_match_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `mentor_subjects`
--
ALTER TABLE `mentor_subjects`
  ADD CONSTRAINT `mentor_subjects_ibfk_1` FOREIGN KEY (`mentor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mentor_subjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sessions_ibfk_2` FOREIGN KEY (`mentor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sessions_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_student_mentor` FOREIGN KEY (`mentor_id`) REFERENCES `mentor_profiles` (`mentor_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `students_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
