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
- **PHP**: Version 7.4 or higher

### Steps
1. **Download and Install XAMPP**:
   - Download XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/).
   - Install and start Apache and MySQL services.

2. **Clone the Project**:
   - Clone the repository or copy the project files to the `htdocs` folder in the XAMPP installation directory.

3. **Set Up the Database**:
   - Open `phpMyAdmin` (http://localhost/phpmyadmin).
   - Create a new database named `timetable`.
   - Import the `timetable.sql` file provided in the project directory.

4. **Configure the Database Connection**:
   - Open the `db_config.php` file in the project.
   - Update the database credentials:
     ```php
     $db_host = 'localhost';
     $db_user = 'root';
     $db_pass = '';
     $db_name = 'timetable';
     ```

5. **Run the Application**:
   - Open your browser and navigate to `http://localhost/timetable`.

---

## Database Design

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
   - Ensure the database credentials in [db_config.php](http://_vscodecontentref_/0) are correct.
   - Verify that the MySQL service is running.

2. **CSRF Token Mismatch**:
   - Ensure all forms include the `csrf_field()` function.

3. **Schedule Conflict**:
   - Check the conflict message and use the suggested time slots.

---

## License
This project is licensed under the MIT License.

---

## Author
Developed by [Your Name]. For inquiries, contact [your_email@example.com].