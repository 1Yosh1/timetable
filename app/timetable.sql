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
) 

CREATE TABLE `courses` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `name` varchar(255) NOT NULL,
 `description` text DEFAULT NULL,
 `credits` int(11) DEFAULT NULL,
 `teacher_id` int(11) DEFAULT NULL,
 PRIMARY KEY (`id`),
 KEY `fk_teacher` (`teacher_id`),
 CONSTRAINT `fk_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`)
) 

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
) 

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
) 

CREATE TABLE `rooms` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `name` varchar(255) NOT NULL,
 `capacity` int(11) DEFAULT NULL,
 PRIMARY KEY (`id`)
) 

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
) 

CREATE TABLE `users` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `username` varchar(255) NOT NULL,
 `password` varchar(255) NOT NULL,
 `email` varchar(255) DEFAULT NULL,
 `role` varchar(255) DEFAULT 'student',
 PRIMARY KEY (`id`),
 UNIQUE KEY `username` (`username`)
) 