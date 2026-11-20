# Timetable Management System

The **Timetable Management System** is a web-based application designed to manage schedules, courses, users, and room bookings efficiently. It provides role-based access for administrators, teachers, and students, ensuring secure and streamlined operations.

## Features

### Admin Features
- **User Management**:
  - Add, edit, and delete users.
- **Course Management**:
  - Add, edit, and delete courses.
  - Assign teachers to courses.
- **Schedule Management**:
  - Create schedules with conflict detection.
  - Suggest alternative time slots for conflicts.
- **Enrollment Management**:
  - Approve or deny student enrollment requests.

### Teacher Features
- View assigned schedules.
- Manage class-related tasks.

### Student Features
- View personal timetables.
- Request enrollment in courses.

---

## Installation

### Prerequisites
- **Web Server**: Apache (XAMPP recommended)
- **Database**: MySQL
- **PHP**: Version 8.1 or higher (tested on 8.2)

### Steps
1. **Download and Install XAMPP**:
   - Download XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/).
   - Install and start Apache and MySQL services.

2. **Clone the Project**:
   - Clone the repository or copy the project files to the `htdocs` folder in the XAMPP installation directory.

3. **Set Up the Database**:
   - Open `phpMyAdmin` (http://localhost/phpmyadmin).
   - Create a new database named `timetable` with collation `utf8mb4_general_ci`.
   - Select it, open the **Import** tab, choose `demo.sql` from the project root and run it.
   - `demo.sql` is the complete setup in one file: the full nine-table schema plus demo data.
     Expect **9 tables** and **6 users**. (`app/timetable.sql` holds the same schema without
     the seed rows, if you only want the structure.)

4. **Configure the Connection**:
   - Copy the template and adjust it if your database differs from the XAMPP defaults:
     ```bash
     cp .env.example .env
     ```
   - The defaults in `.env.example` are `localhost` / `root` / empty password / port `3306`.
     For **MAMP**, uncomment the last two lines (`DB_PASS=root`, `DB_PORT=8889`).
   - Credentials live in `.env`, which `app/bootstrap.php` loads. `db_config.php` reads them
     from the environment and should not be edited.

5. **Sign In**:

   | Role | Username | Password |
   |---|---|---|
   | Admin (via *Admin Login*) | `admin` | `admin1234` |
   | Teacher | `teach_abebe` | `pass1234` |
   | Teacher | `teach_kebede` | `pass1234` |
   | Student | `stud_eyoas` | `pass1234` |
   | Student | `stud_hana` | `pass1234` |
   | Student | `stud_selam` | `pass1234` |

6. **Run the Application**:
   - Open your browser and navigate to `http://localhost/timetable/index.php`.

---

## Database Design

Both files describe the same design: `app/timetable.sql` is the schema on its own, and
`demo.sql` is that schema plus demo data. Import `demo.sql` for the working application.
The schema below is what both files create.

### Tables
1. **`users`**:
   - **Columns**: `id`, `username`, `email`, `password`, `role`
   - **Purpose**: Stores user credentials and roles (admin, teacher, student).

2. **`courses`**:
   - **Columns**: `id`, `name`, `description`, `credits`, `teacher_id`
   - **Purpose**: Stores course details and assigned teachers.

3. **`rooms`**:
   - **Columns**: `id`, `name`
   - **Purpose**: Stores room details for scheduling.

4. **`schedules`**:
   - **Columns**: `id`, `course_id`, `room_id`, `day_of_week`, `timeslot`
   - **Purpose**: Stores timetable schedules for courses.

5. **`pending_enrollments`**:
   - **Columns**: `id`, `student_id`, `course_id`, `status`, `processed_at`
   - **Purpose**: Tracks enrollment requests and their statuses.

6. **`enrollments`**:
   - **Columns**: `id`, `student_id`, `course_id`
   - **Purpose**: Tracks approved enrollments.

7. **`attendance`**:
   - **Columns**: `id`, `student_id`, `schedule_id`, `status`, `date`
   - **Purpose**: One row per student per class session, unique on `(student_id, schedule_id, date)`,
     so re-marking the same date corrects the row instead of duplicating it.

8. **`announcements`**:
   - **Columns**: `id`, `course_id`, `teacher_id`, `content`, `created_at`
   - **Purpose**: Course announcements posted by the teacher who owns the course.

9. **`pending_schedules`**:
   - **Columns**: `id`, `course_id`, `room_id`, `teacher_id`, `day_of_week`, `timeslot`, `status`, `request_date`
   - **Purpose**: Teacher room-booking requests awaiting admin approval.

---

## Conflict Detection Logic

### Schedule Conflict Detection
- **Room Conflict**:
  - Checks if the room is already booked for the selected day and time slot.
- **Teacher Conflict**:
  - Checks if the assigned teacher has another class at the same time.
- **Course Conflict**:
  - Ensures the course is not scheduled in multiple rooms at the same time.

### Conflict Resolution
- Suggests up to 5 alternative day and time slot combinations for resolving conflicts.

---

## Security Features
- **CSRF Protection**:
  - Ensures all forms include a CSRF token to prevent cross-site request forgery.
- **Password Hashing**:
  - Uses `password_hash()` for secure password storage.
- **Role-Based Access Control**:
  - Restricts access to features based on user roles.

---

## Future Enhancements
- **Notifications**:
  - Add email or in-app notifications for enrollment approvals and schedule changes.
- **Reporting**:
  - Generate reports for schedules, enrollments, and room usage.
- **Mobile Responsiveness**:
  - Optimize the application for mobile devices.
- **Theme Toggle**:
  - Add a light/dark mode toggle for user preference.

---

## Troubleshooting

### Common Issues
1. **Database Connection Error**:
   - Check the values in `.env` (copy `.env.example` if the file is missing).
   - On MAMP the port is **8889** and the password is `root`, not the XAMPP defaults.
   - Verify that the MySQL service is running.

2. **Fatal error about a missing table (`announcements`, `pending_schedules`)**:
   - The database was created from an older or partial dump. Drop it, create it again and
     import `demo.sql`.

3. **CSRF Token Mismatch**:
   - Ensure all forms include the `csrf_field()` function.

4. **Schedule Conflict**:
   - Check the conflict message and use the suggested time slots.

---

## License
This project is licensed under the MIT License.

---

## Author
Developed by Eyoas Zewdie