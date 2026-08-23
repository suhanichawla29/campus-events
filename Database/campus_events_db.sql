SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";



CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `admins` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'admin', '0192023a7bbd73250516f069df18b500', '2026-07-09 08:31:06');


CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `venue` varchar(200) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `organizer` varchar(100) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



INSERT INTO `events` (`id`, `title`, `description`, `event_date`, `event_time`, `venue`, `category`, `organizer`, `image`, `created_at`) VALUES
(1, 'Tech Hackathon 2026', 'A 24-hour coding competition where students build innovative solutions.', '2027-01-07', '09:00:00', 'Computer Science Block', 'Technical', 'CS Department', '1783608297_Hackathon.jpg', '2026-07-09 08:31:06'),
(2, 'Pravah', 'Showcase your talent in music, dance, drama, and more!', '2026-08-29', '10:00:00', 'College Auditorium', 'Cultural', 'Cultural Committee', '1783612939_pravah.jpg', '2026-07-09 08:31:06'),
(3, 'AI Workshop', 'Learn the basics of Artificial Intelligence and Machine Learning.', '2026-07-30', '14:00:00', 'Lab 301', 'Workshop', 'AI Club', '1783613461_workshop.jpg', '2026-07-09 08:31:06'),
(4, 'Dance Revolution', 'Solo and group dance battles', '2027-03-08', '11:00:00', 'Auditorium', 'Cultural', 'Dance Club', '1783609191_dance-battle.jpg', '2026-07-09 13:14:14'),
(5, 'Sports Meet 2026', 'Annual sports competition with cricket, football, basketball and more.', '2026-09-11', '08:00:00', 'College Sports Ground', 'Sports', 'Sports Department', '1783613070_sports.jpg', '2026-07-09 13:14:14'),
(6, 'Startup Pitch Competition', 'Present your startup idea to a panel of judges and win funding.', '2026-11-05', '13:00:00', 'Seminar Hall', 'Seminar', 'Entrepreneurship Cell', '1783613396_startup.jpg', '2026-07-09 13:14:14');



CREATE TABLE `registrations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO `registrations` (`id`, `user_id`, `event_id`, `registered_at`) VALUES
(1, 1, 4, '2026-07-09 16:15:23'),
(2, 1, 3, '2026-07-11 04:35:46');



CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `phone`, `created_at`) VALUES
(1, 'Suhani', 'suhanichawla47@gmail.com', '$2y$10$XqYCum4x0dqtHHEXDaSP/uA.1vKIbJJ2LldigExcpeiIn1F7IVp..', '8824758482', '2026-07-09 08:33:31'),
(2, 'Suhaniiii', 'suhaniashishchawla8542@gmail.com', '$2y$10$YZ1sT0OOgiQJznIgq/z2F.aId5fSLu4JPG/o5AUtw8y63jwZZehNO', '8824758482', '2026-07-26 06:43:37');


ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);


ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);


ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_registration` (`user_id`,`event_id`),
  ADD KEY `event_id` (`event_id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);


ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;


ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;


ALTER TABLE `registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;


ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;


ALTER TABLE `registrations`
  ADD CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `registrations_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;
COMMIT;
=