# Project Code Explanation

This document explains the architecture and structure of the **Timetable Management System**, separated by technology stack: **HTML**, **CSS**, **JS**, and **PHP**. It explains how the code functions, why specific design choices were made, and how changes would affect the system.

---

## 1. HTML (Structure & Layouts)

The user interface of the application is built using HTML5 structure, styled with the **Bootstrap 4** utility class system, and decorated with **Font Awesome** icons.

### Key Components

#### A. Centralized Form Containers (`index.php`, `admin_login.php`, `register.php`)
*   **What it does:** Uses a compact layout (`.login-container`) centered on the page to collect user credentials or details.
*   **Why it's used:** Provides a standard, distraction-free authentication experience.
*   **What happens if changed:** Modifying the form fields (like inputs or dropdowns) requires updating the corresponding PHP handler scripts (e.g., `login_process.php`) to match the updated input `name` attributes.

#### B. Dashboard Views (`admin_dashboard.php`, `teacher_dashboard.php`, `student_dashboard.php`)
*   **What it does:** Uses a sidebar/main-content layout for admins, and a tabbed layout (`.nav-tabs`) for students and teachers to swap views.
*   **Why it's used:** Organizes complex panels (Schedules, Attendance, Announcements, Reports) into a single, clean workspace.
*   **What happens if changed:** If you change the tab IDs or classes, the Javascript Bootstrap tab navigation will break unless you update the Bootstrap `data-toggle="tab"` and `href` attributes.

#### C. Grid Timetables
*   **What it does:** Employs standard HTML tables structured dynamically using PHP loops (iterating over weekdays and timeslots) to render scheduling slot matrices.
*   **Why it's used:** Displays room and course bookings visually in a spreadsheet-like grid.
*   **What happens if changed:** Modifying table headings or loops can cause alignments to break or double bookings to display incorrectly.

---

## 2. CSS (Design & Theme System)

All custom styles are located in `css/styles.css`. The application uses a custom design system based on **CSS Custom Properties (variables)**.

### Key Components

#### A. Design Tokens (`:root`)
*   **What it does:** Defines colors, border radii, shadows, and transition durations at the root level (e.g., `--color-bg-base`, `--color-accent`, `--radius-md`).
*   **Why it's used:** Ensures visual consistency across the entire project. Changing a variable changes it globally.
*   **What happens if changed:** Changing these values updates the theme instantly. If you delete these variables, the stylesheet will break wherever they are referenced (using `var(...)`).

#### B. Dark Mode Support (`body[data-theme="dark"]`)
*   **What it does:** Overrides the root design tokens with darker background colors and lighter text colors when the `data-theme` attribute is set to `dark`.
*   **Why it's used:** Provides a built-in dark theme without duplicating the CSS files.
*   **What happens if changed:** If you delete these rules, dark mode will not render, and toggling the dark theme will have no effect.

#### C. Custom Scrollbars & Interactive Elements
*   **What it does:** Stylizes elements like scrollbars (`::-webkit-scrollbar`), list items, cards, and transitions on hover (`transform: translateY(-4px)`).
*   **Why it's used:** Enhances micro-interactions and transitions to make the dashboard feel alive.
*   **What happens if changed:** Transitions will become abrupt if the hover state transformations are removed.

---

## 3. JS (Interactivity & AJAX)

All custom scripts are in `js/script.js`, which handles interactive elements, theme toggling, and asynchronous database interactions (AJAX).

### Key Components

#### A. Theme Toggle Controller
*   **What it does:** Checks `localStorage` for a saved theme, applies it to `body.dataset.theme`, and toggles between dark/light on button clicks.
*   **Why it's used:** Remembers the user's theme preference across page reloads.
*   **What happens if changed:** Deleting `localStorage` code will cause the theme to reset to light mode on every page refresh.

#### B. AJAX Request Handlers (Room Booking & Enrollment)
*   **What it does:** Intercepts form submissions (e.g., `#bookRoomForm`) and button clicks (e.g., `.enroll-btn`, `.unenroll-btn`), sending them to PHP scripts (`manage_teacher_tasks.php`, `manage_enrollment.php`) via `fetch()` without reloading the page.
*   **Why it's used:** Creates a smooth, app-like experience where schedules update instantly.
*   **What happens if changed:** Removing the AJAX handlers will require reloading the page for every action, reverting back to full-page PHP submissions.

#### C. Real-time Conflict Warning Checker
*   **What it does:** Uses a debounced input listener on room booking inputs. After 300ms, it calls `check_conflict.php` to verify availability.
*   **Why it's used:** Warns the admin/teacher of a timetable conflict *before* they click submit.
*   **What happens if changed:** Removing this requires the user to submit the form before knowing if a slot is taken, which causes frustration.

---

## 4. PHP (Backend Core, Logic & DB)

PHP handles the business logic, security validations, database mapping, and authentication checks.

### Key Components

#### A. Initialization (`app/bootstrap.php`)
*   **What it does:** Starts sessions, registers autoloaders, sets security headers (e.g., `X-Frame-Options`, `X-Content-Type-Options`), and loads environmental configuration (`.env`).
*   **Why it's used:** Acts as the entry bootstrap file that must run before any script executes.
*   **What happens if changed:** Removing this script or its security headers will open up the application to framing (Clickjacking) or mime type sniffing vulnerabilities.

#### B. Security & CSRF (`app/csrf.php` & `app/Auth.php`)
*   **What it does:** Generates and verifies cryptographic session tokens to block CSRF requests. Also manages session rotation and role checks (`requireRole()`).
*   **Why it's used:** Prevents unauthorized POST requests and protects user sessions.
*   **What happens if changed:** Disabling CSRF verification allows malicious external forms to execute database write actions on behalf of logged-in users.

#### C. Database Core (`app/Core/Database.php` & `db_config.php`)
*   **What it does:** Creates a singleton MySQLi connection `$conn`.
*   **Why it's used:** Ensures only one database connection is opened per request, maximizing resource efficiency.
*   **What happens if changed:** Changing to multiple instantiations could slow down server performance.

#### D. Repository Layer (`app/Repositories/`)
*   **What it does:** Houses isolated database queries (like `EnrollmentRepository` and `CourseRepository`).
*   **Why it's used:** Keeps database-specific queries out of view scripts, maintaining clean, reusable database access.
*   **What happens if changed:** Modifying SQL statements inside a repository updates database retrievals globally across all dashboards.

#### E. Auth Processors (`login_process.php` & `register_process.php`)
*   **What it does:** Verifies user inputs, performs password hashing verification via `password_verify()`, and integrates `BruteForceProtector.php` to prevent brute force login attempts.
*   **Why it's used:** Secures user accounts from standard password guessing and injection attacks.
*   **What happens if changed:** Bypassing `password_verify` will lock out all users whose passwords are encrypted in the database.

#### F. Brute-Force Rate Limiting (`BruteForceProtector.php`)
*   **What it does:** Writes temporary tracking JSON files under `storage/bruteforce/` to keep track of failed attempts by username/IP combination, blocking them if they exceed 5 failures within 5 minutes.
*   **Why it's used:** Prevents automated login attacks.
*   **What happens if changed:** Deleting the lockout directory resets rate limits, exposing the login forms to brute force guessing.
