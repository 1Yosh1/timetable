-- ============================================================
--  UniMe — Timetable Management System : demo database
-- ============================================================
--  Import into an EMPTY database named `timetable`.
--  phpMyAdmin:  select the database  ->  Import  ->  choose this file  ->  Go
--  CLI:         mysql -u root timetable < demo.sql
--
--  What this file contains
--    1. The 7 tables from app/timetable.sql, with the missing `;`
--       terminators added (the definitions themselves are unchanged).
--    2. The 2 tables the PHP code requires but the dump omits:
--       `announcements` and `pending_schedules`.
--    3. Demo seed data: 6 users, 3 rooms, 7 courses, 9 scheduled slots,
--       9 enrollments, 10 attendance rows, 3 announcements and 4 pending
--       requests (2 enrollment requests + 2 competing room bookings).
--
--  Why FOREIGN_KEY_CHECKS=0
--    The dump is alphabetical, so `attendance` is created before `users`
--    and `schedules` and its foreign keys would fail (errno 150).
--
--  Logins
--    admin        / admin1234
--    teach_abebe  / pass1234
--    teach_kebede / pass1234
--    stud_eyoas   / pass1234
--    stud_hana    / pass1234
--    stud_selam   / pass1234
--
--  Deliberate quirks kept for the demo (they are real code paths, not
--  data mistakes):
--    * CS103 has no schedule row  -> enrolling shows the "invisible course"
--      bug (appears under My Courses, absent from My Schedule).
--    * CS105 clashes with CS101 (both Monday 09:00) -> the student sees a
--      disabled "Conflict" button instead of "Enroll".
--    * stud_hana already has 5 courses -> the UI still offers Enroll (it
--      reads MAX_COURSES=6) but the server rejects it (hard-coded 5).
--    * Two pending room bookings compete for the same slot -> approving one
--      auto-denies the other inside a transaction.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- 1. Shipped tables (definitions from app/timetable.sql)
-- ------------------------------------------------------------

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) DEFAULT NULL,
  `attendance_date` date DEFAULT NULL,
  `schedule_id` int(11) DEFAULT NULL,
  `status` enum('present','absent') NOT NULL,
  `date` date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_schedule_date` (`student_id`,`schedule_id`,`date`),
  KEY `student_id` (`student_id`),
  KEY `schedule_id` (`schedule_id`),
  KEY `idx_attendance_student` (`student_id`),
  KEY `idx_attendance_schedule` (`schedule_id`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `credits` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_teacher` (`teacher_id`),
  CONSTRAINT `fk_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_course` (`student_id`,`course_id`),
  UNIQUE KEY `uniq_enrollment` (`student_id`,`course_id`),
  KEY `student_id` (`student_id`),
  KEY `course_id` (`course_id`),
  KEY `idx_enrollments_student` (`student_id`),
  KEY `idx_enrollments_course` (`course_id`),
  CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `pending_enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `status` enum('pending','approved','denied') NOT NULL DEFAULT 'pending',
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `pe_fk_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pe_fk_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `capacity` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) DEFAULT NULL,
  `room_id` int(11) DEFAULT NULL,
  `timeslot` varchar(255) DEFAULT NULL,
  `day_of_week` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_room_slot` (`room_id`,`day_of_week`,`timeslot`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `schedules_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role` varchar(255) DEFAULT 'student',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 2. Tables the code needs but app/timetable.sql omits
-- ------------------------------------------------------------

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_announcements_course` (`course_id`),
  KEY `idx_announcements_teacher` (`teacher_id`),
  CONSTRAINT `ann_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ann_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `pending_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `day_of_week` varchar(255) NOT NULL,
  `timeslot` varchar(255) NOT NULL,
  `status` enum('pending','approved','denied') NOT NULL DEFAULT 'pending',
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pending_schedules_status` (`status`),
  KEY `idx_pending_schedules_slot` (`room_id`,`day_of_week`,`timeslot`),
  CONSTRAINT `ps_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ps_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ps_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 3. Seed data
-- ------------------------------------------------------------

-- passwords: bcrypt hashes of admin1234 (admin) and pass1234 (everyone else)
INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`) VALUES
  (1, 'admin',        '$2y$10$jTtBt00j3uobx8rl/V3azeo09vrbhN8V3iubHTdvPKpBD6PxonZiC', 'admin@unime.test',        'admin'),
  (2, 'teach_abebe',  '$2y$10$FFwTryz8AOh73MGGycPIEucncFA5/w9kGU/pmmtdSevcC93K30dl2', 'abebe@unime.test',        'teacher'),
  (3, 'teach_kebede', '$2y$10$FFwTryz8AOh73MGGycPIEucncFA5/w9kGU/pmmtdSevcC93K30dl2', 'kebede@unime.test',       'teacher'),
  (4, 'stud_eyoas',   '$2y$10$FFwTryz8AOh73MGGycPIEucncFA5/w9kGU/pmmtdSevcC93K30dl2', 'eyoas@unime.test',        'student'),
  (5, 'stud_hana',    '$2y$10$FFwTryz8AOh73MGGycPIEucncFA5/w9kGU/pmmtdSevcC93K30dl2', 'hana@unime.test',         'student'),
  (6, 'stud_selam',   '$2y$10$FFwTryz8AOh73MGGycPIEucncFA5/w9kGU/pmmtdSevcC93K30dl2', 'selam@unime.test',        'student');

INSERT INTO `rooms` (`id`, `name`, `capacity`) VALUES
  (1, 'R101', 40),
  (2, 'R102', 30),
  (3, 'Lab-1', 25);

INSERT INTO `courses` (`id`, `name`, `description`, `credits`, `teacher_id`) VALUES
  (1, 'CS101 Introduction to Programming', 'Variables, control flow, functions and problem solving.', 3, 2),
  (2, 'CS102 Data Structures',             'Lists, stacks, queues, trees, hashing and complexity.',     4, 3),
  (3, 'CS103 Operating Systems',           'Processes, memory, file systems and concurrency. (No slot scheduled yet.)', 3, 2),
  (4, 'CS104 Computer Networks',           'Layers, addressing, routing and the TCP/IP stack.',         3, 3),
  (5, 'CS105 Database Systems',            'Relational modelling, SQL, normalisation and transactions.', 3, NULL),
  (6, 'CS106 Software Engineering',        'Requirements, design, testing and version control.',        3, NULL),
  (7, 'CS107 Compilers',                   'Lexing, parsing, semantic analysis and code generation.',   3, NULL);

-- 9 slots. uniq_room_slot means no two rows may share (room, day, timeslot).
INSERT INTO `schedules` (`id`, `course_id`, `room_id`, `timeslot`, `day_of_week`) VALUES
  (1, 1, 1, '09:00-10:00', 'Monday'),
  (2, 2, 1, '10:00-11:00', 'Monday'),
  (3, 4, 2, '09:00-10:00', 'Tuesday'),
  (4, 5, 1, '10:00-11:00', 'Tuesday'),
  (5, 6, 2, '09:00-10:00', 'Wednesday'),
  (6, 7, 1, '10:00-11:00', 'Wednesday'),
  (7, 1, 2, '09:00-10:00', 'Thursday'),
  (8, 2, 1, '09:00-10:00', 'Friday'),
  (9, 5, 2, '09:00-10:00', 'Monday');

-- stud_eyoas (4): 3 courses -> the Enroll button works in the demo
-- stud_hana  (5): 5 courses -> UI still offers Enroll (reads 6), server rejects (hard-codes 5)
-- stud_selam (6): 1 course
INSERT INTO `enrollments` (`student_id`, `course_id`) VALUES
  (4, 1), (4, 2), (4, 4),
  (5, 1), (5, 2), (5, 4), (5, 5), (5, 6),
  (6, 1);

INSERT INTO `attendance` (`student_id`, `schedule_id`, `status`, `date`) VALUES
  (4, 1, 'present', '2026-11-02'),
  (4, 1, 'absent',  '2026-11-09'),
  (4, 1, 'present', '2026-11-16'),
  (4, 7, 'present', '2026-11-05'),
  (4, 7, 'present', '2026-11-12'),
  (4, 2, 'present', '2026-11-02'),
  (4, 2, 'present', '2026-11-09'),
  (5, 1, 'absent',  '2026-11-02'),
  (5, 1, 'present', '2026-11-16'),
  (6, 1, 'present', '2026-11-16');

INSERT INTO `announcements` (`course_id`, `teacher_id`, `content`, `created_at`) VALUES
  (1, 2, 'Problem set 3 is due Monday. Bring your printed solutions to class.', '2026-11-14 09:15:00'),
  (1, 2, 'No class on Thursday 20 November; there is a make-up session on Friday.', '2026-11-18 16:40:00'),
  (2, 3, 'Lab 4 (balanced trees) is posted. Read sections 4.2-4.4 before Thursday.', '2026-11-17 11:05:00');

-- one pending enrollment so the admin Requests page is never empty
-- (stud_hana already has 5 courses: approving this demonstrates the cap bypass)
INSERT INTO `pending_enrollments` (`id`, `student_id`, `course_id`, `status`, `request_date`, `processed_at`) VALUES
  (1, 5, 7, 'pending', '2026-11-18 10:22:00', NULL),
  (2, 6, 3, 'pending', '2026-11-19 08:05:00', NULL);

-- two COMPETING room bookings for the same slot (R102, Thursday 10:00-11:00).
-- Approving either one marks the other 'denied' inside the same transaction.
INSERT INTO `pending_schedules` (`id`, `course_id`, `room_id`, `teacher_id`, `day_of_week`, `timeslot`, `status`, `request_date`) VALUES
  (1, 3, 2, 2, 'Thursday', '10:00-11:00', 'pending', '2026-11-18 12:00:00'),
  (2, 4, 2, 3, 'Thursday', '10:00-11:00', 'pending', '2026-11-18 14:30:00');

SET FOREIGN_KEY_CHECKS = 1;
