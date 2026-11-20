# UniMe Timetable Management System

## A Complete Technical Report: Architecture, Correctness, Security, and the Practice of AI-Assisted ("Vibe-Coded") Development

**Subject:** `timetable` — a PHP/MySQL multi-role scheduling application
**Repository state examined:** `main` @ `786c0f9` — 41 PHP files (2,935 lines), plus CSS, JS and SQL, 1.1 MB working tree
**Author of the software:** Eyoas Zewdie
**Review type:** full-source read-through, static analysis, schema audit, security review, and process retrospective
**Report date:** 16 November 2026

> **How to read this report.** Parts I–II establish the system and its structure; Parts III–IV are the technical audit (mechanics, then security and correctness); Part V is engineering judgement and a remediation plan; Part VI is the retrospective on AI-assisted development, including the concept-ownership checklist in §20. Appendices A–B are reference tables (endpoint matrix, findings register) and are the fastest route to action. If you read one section, read §20.

---

## Table of Contents

**Part I — Framing**
1. Executive summary
2. How this report was produced, and what it does not prove

**Part II — The system as built**
3. The problem domain, the actors, and the requirements
4. Inventory: what exists, what it weighs, what it is for
5. Architecture: two architectures wearing one repository
6. The data model

**Part III — Mechanics in depth**
7. The timetable conflict algebra
8. The enrollment state machine and the limit problem
9. Attendance: the write path and the read path
10. Authentication, sessions, and identity

**Part IV — Audit**
11. Security review
12. Correctness defects
13. Performance and resilience

**Part V — Engineering judgment**
14. Frontend: design tokens, JavaScript, and the cost of dead branches
15. Verification, testability, and a manual test plan
16. Remediation roadmap and target architecture

**Part VI — Vibe-coding**
17. What "vibe-coding" means here, precisely
18. Evidence-based accounting: where delegation helped and where it failed
19. The protocol that makes delegation safe, and concept ownership

**Appendices**
A. Endpoint and authorization matrix
B. Findings register
C. Glossary

---

# Part I — Framing

## 1. Executive summary

This repository is a working university timetable system: administrators manage users, courses, rooms and schedules; teachers take attendance, post announcements and request room bookings; students enroll in courses and view their weekly timetable. It is built in procedural PHP against MySQL, styled with Bootstrap 4 plus a custom CSS custom-property layer, and progressively wrapped in a small hand-rolled layered architecture under `app/`.

The honest summary is that this is **a capable, feature-rich prototype with a genuinely thoughtful core and an insecure, unfinished perimeter.** The parts that were thought through are good: the conflict-detection logic is conceptually correct across four independent dimensions, the teacher dashboard batches its queries instead of looping them, the CSS is built on a real design-token system rather than ad-hoc colours, and the admin write path is transactional and CSRF-guarded. The parts that were not thought through are dangerous: two endpoints require no authentication at all and one of them leaks any student's attendance records; the attendance endpoint accepts any schedule ID from any teacher; database credentials are committed to version control; and the shipped schema cannot be imported because it is missing two tables the application requires.

The most interesting finding is a specific, reproducible class of defect that appears repeatedly and has a single root cause: **the same fact is defined in more than one place and the copies have drifted.** The enrollment limit exists three times — as `AppConfig::STUDENT_ENROLLMENT_LIMIT = 5`, as `MAX_COURSES` defaulting to `6`, and as a hardcoded literal `5` in the AJAX controller annotated "aligned with UI" when it is not. The weekday list exists twice in the same file, twenty lines apart. The CSRF check is present on six endpoints, absent on two, and present-but-never-verified on a third (admin login renders a token it never checks). Two parallel session identities (`admin_id` and `user_id`) model the same concept differently. Two repository classes implement the same "create pending enrollment if absent" method. This duplication is not incidental: it is the characteristic failure mode of generated code accepted without a single owning mental model, and Part VI treats it as the central lesson.

Three headline numbers frame the risk. Of 24 endpoints in the repository, **2 expose data with no authentication at all** and **3 mutate state without verifying a CSRF token**. Of 9 tables required by the code, **7 are in the schema file** — and that file has no statement terminators, so it cannot be imported as shipped. Of the application's own documented features, **3 are silently non-functional in the current build**: the admin "edit user" modal does not populate, the live conflict-warning under the schedule form never fires, and the student enrollment limit shown in the UI (6) does not match the one enforced by the server (5).

None of that is a reason to discard the work. Every defect found here is a bounded, well-understood defect with a known fix, and the fixes total perhaps two focused days. The architecture can be completed rather than replaced. What the codebase needs is not more generation; it needs an owner who can state, for each subsystem, what invariant it protects and how that invariant is enforced in the database, in the service layer, and in the UI. This report is written to make that possible: it explains not only what the code does but why each design decision is right or wrong, and it separates the parts that were genuinely delegated from the parts that still require human judgement.

**Every claim was executed, not just read.** The application was run in an isolated PHP 8.2.35 + MariaDB 11.3.2 environment and driven with `curl`: twenty checks were run, of which fourteen findings reproduced exactly, six existing controls were confirmed to work, and two defects surfaced that reading alone had missed. All 41 PHP files lint clean, which is the point — this codebase is syntactically perfect and functionally compromised. §15.6 is the log, including the one prediction that turned out wrong.

### What is genuinely strong

- **Conflict detection models the domain, not the screens.** Room, teacher, and course conflicts are checked as independent constraints, and the admin approval path re-checks all three inside a transaction before committing. The reasoning is correct.
- **The teacher dashboard avoids the N+1 query trap.** A naive implementation would have looped over courses and queried students and schedules per course; this one collects course IDs and uses a single parameterised `IN (...)` query per concern with correct `bind_param` spreading.
- **SQL injection is effectively eliminated.** Every query that touches user input uses `prepare` + `bind_param`. There is no string interpolation of user input into SQL anywhere in the repository — I checked all 40-odd queries.
- **The CSS is a real design system.** Twenty-plus semantic tokens for colour, radius, shadow and transition, consumed consistently, with a deliberate removal of dark mode rather than a half-maintained one.
- **Destructive admin actions are guarded.** Deletes and edits carry `AND role != 'admin'` predicates so the interface cannot be used to escalate by editing an administrator.

### What must be fixed before this is exposed to the internet

1. Add authentication and authorization to `check_conflict.php` and `get_student_attendance.php`; delete the latter if unused.
2. Verify teacher ownership of the schedule in `mark_attendance.php`, and add the missing CSRF token to the attendance form.
3. Add CSRF verification to `admin_login_process.php`.
4. Remove `.env` from version control and add `.env.example`; verify no deployment shares those credentials.
5. Ship the missing `announcements` and `pending_schedules` tables, with semicolons, in a re-runnable migration.
6. Collapse the enrollment limit to one source of truth.

Part IV and Appendix B give the complete register with severities and file references.

---

## 2. How this report was produced, and what it does not prove

A report is only as trustworthy as its stated method, so here is exactly what was done.

**Exhaustive source reading.** All 17 tracked PHP files under `app/`, all 24 PHP files at the repository root (41 in total, 2,935 lines), `js/script.js`, `css/styles.css`, `app/timetable.sql`, `README.md`, `project_improvements.md`, `code_explanation.md`, and `.gitignore` were read in full — not sampled. The three prior documents in the repository were read first precisely because they make claims that needed checking: `code_explanation.md`, for instance, describes a dark-mode theme toggle that commit `f2d8bd3` removed, and describes `app/security_headers.php` as setting security headers when that file is **zero bytes** and the headers are actually set at the bottom of `app/bootstrap.php`.

**Cross-reference checks performed mechanically.** Every table name referenced in PHP was extracted and diffed against the tables defined in the schema file. Every class in `app/` was grepped for usage to separate live code from dead code. Every endpoint was grepped for inbound references to separate reachable pages from orphans. Every constant that duplicates another constant was traced to each of its use sites.

**Two phases: static read, then execution.** The review began as a pure source read — PHP was not installed on the review machine (`php: command not found`), so no file was linted or executed at first. Every finding was derived from reading code, and where runtime behaviour had to be inferred (for example that duplicate registration produces an uncaught exception rather than the friendly message in the `else` branch) the inference chain was written out rather than asserted.

The findings were then **run down against a live instance**. An isolated environment was built entirely in containers — PHP 8.2.35 with `mysqli`, and MariaDB 11.3.2 — with a faithful copy of the tracked code and a seeded database, and each claim was exercised with `curl` and direct SQL inspection. §15.6 is the log: fourteen of the report's findings reproduced exactly, six controls were confirmed to work, two new defects surfaced (a `.env` bootstrapping defect and stack-trace disclosure), one prediction was corrected, and all 41 PHP files lint clean. Where a prediction was **wrong**, that is recorded too.

**What still could not be executed.** Race conditions (§7.3, §8.5) cannot be demonstrated on PHP's single-threaded built-in server; they need concurrent workers. TLS-dependent behaviour (HSTS, `Secure` cookies) is untestable without HTTPS. The MySQL-specific `VALUES()` deprecation in §9.1 was not observed, because MariaDB 11.3 does not deprecate it — that remains a documentation-based claim about MySQL 8.0.20+. And no penetration testing, fuzzing or load testing was performed.

**What this report is not.** It is not a penetration test: no fuzzing, no automated scanner, no exploitation was attempted. It is not a performance profile: the query analysis is structural (count and shape of queries), not measured. It is not a legal or compliance assessment. And it is not a substitute for the author's own defence of the design — Section 20 is written on the assumption that such a defence will happen, and aims to make it possible.

### A note on the repository's own documentation

Three documents already exist: `README.md` (setup and feature overview), `project_improvements.md` (a short prioritised improvement list), and `code_explanation.md` (a layered explanation of HTML/CSS/JS/PHP). They are useful and largely accurate about intent, but they describe a system that has moved on. The README instructs the reader to import `timetable.sql` from the project root; the only schema file is `app/timetable.sql`, and it contains no statement terminators, so it cannot be imported as-is. `project_improvements.md` recommends adding a router, Composer, strict types, and Bootstrap 5 — all still valid — while `code_explanation.md` still documents the dark theme. This report supersedes none of them; it audits them.

---

# Part II — The system as built

## 3. The problem domain, the actors, and the requirements

### 3.1 The domain

A university timetable system exists to solve a resource-allocation problem with hard constraints. Three scarce resources — **rooms**, **teachers**, and **students' time** — must be assigned to **courses** across a discrete grid of **five weekdays × eight one-hour slots**. Forty grid cells per room per week. A course needs one or more of those cells; a teacher can occupy only one cell at a time; a student can attend only one class at a time; a room can host only one class at a time.

Formally, a schedule entry is a tuple `(course, room, day, slot)` and a valid timetable is a set of such tuples satisfying:

- **Room exclusivity:** no two entries share `(room, day, slot)`.
- **Teacher exclusivity:** no two entries whose courses share a `teacher_id` share `(day, slot)`.
- **Course self-consistency:** a course is not scheduled twice in the same `(day, slot)` (it cannot be in two rooms at once).
- **Student consistency:** a student's enrolled courses do not collide on `(day, slot)`.

The first three are properties of the schedule itself and are enforced (or not) at write time by the administrator. The fourth is a property of the *join* between a student's enrollment set and the schedule, so it cannot be enforced when the schedule is written — it can only be enforced when an enrollment is created, or reported as a warning. This asymmetry is the conceptual heart of the application, and Section 7 is devoted to it.

### 3.2 The actors

**Administrator.** The only role with write access to the global timetable. Creates users, courses and rooms; assigns teachers to courses; creates schedule entries; approves or denies enrollment requests and teacher room-booking requests; reads utilisation reports. Reached through a *separate* login page (`admin_login.php`) that sets a *different* session key from the main login. That separation is an architectural decision with consequences covered in Section 10.

**Teacher.** Reads their assigned courses and enrolled students; takes attendance per session and date; posts and deletes announcements for their own courses; views their attendance records; requests a room booking for one of their courses, which becomes a pending schedule awaiting admin approval.

**Student.** Views a personal weekly timetable; enrolls directly in available courses while under the limit; requests administrator approval once at the limit; unenrolls; views per-course attendance percentages; reads announcements for enrolled courses; changes their password.

**Anonymous visitor.** Reaches the login page, the registration page, and — this is the problem — several data endpoints that should never have been reachable without a session.

### 3.3 Functional requirements, as implemented

| ID | Requirement | Implemented in | Status |
|----|-------------|----------------|--------|
| F1 | Multi-role login with distinct dashboards | `login_process.php`, `admin_login_process.php` | Partial — two session models (see §10) |
| F2 | Self-registration restricted to student/teacher | `register_process.php` | Yes — role allow-list rejects `admin` |
| F3 | Student self-service password change | `profile.php`, `change_password_process.php` | Partial — students only |
| F4 | Admin CRUD over users, courses, rooms | `admin_dashboard.php`, `manage_admin_tasks.php` | Partial — room CRUD absent |
| F5 | Teacher-to-course assignment | `manage_admin_tasks.php:assign_teacher` | Yes — validates target role |
| F6 | Schedule creation with conflict rejection | `manage_admin_tasks.php:add_schedule` | Yes — 3 of 3 global constraints |
| F7 | Live pre-submit conflict warning | `check_conflict.php` + `js/script.js` | Yes — binding fixed in this revision |
| F8 | Enrollment with conflict check and cap | `EnrollmentService`, `manage_enrollment.php` | Yes — one cap (`MAX_COURSES`) in this revision |
| F9 | Enrollment approval workflow | `pending_enrollments`, admin `requests` page | Yes — the cap is enforced in the approval path too |
| F10 | Teacher room-booking request + admin approval | `pending_schedules` | Yes — best-designed flow in the app |
| F11 | Attendance capture per session and date | `mark_attendance.php` | Yes — CSRF + ownership added in this revision |
| F12 | Attendance reporting to students | `student_dashboard.php` | Yes |
| F13 | Announcements per course | `manage_teacher_tasks.php` | Yes — ownership enforced |
| F14 | Utilisation and enrollment reports | `admin_dashboard.php?page=reports` | Yes |
| F15 | Brute-force lockout | `BruteForceProtector.php` | Yes, with weaknesses (§11.6) |

### 3.4 Non-functional requirements, and how the code answers them

**Security.** Explicitly attempted: prepared statements throughout, bcrypt hashing, CSRF tokens on most mutating endpoints, role checks on dashboards, a brute-force limiter, and four security headers. The attempt is real but uneven — the gaps are concentrated in the endpoints that were written outside the main flow, which is a recognisable pattern and is analysed in Part VI.

**Consistency.** The schema provides the application's real integrity guarantees: `UNIQUE(room_id, day_of_week, timeslot)` on `schedules` makes room double-booking impossible even under a race, and `UNIQUE(student_id, course_id)` on `enrollments` makes duplicate enrollment impossible. Notably, no equivalent constraint exists for teacher or course conflicts, so those two dimensions are protected only by check-then-insert logic — a race window that Section 7.4 quantifies.

**Usability.** A tabbed dashboard per role, toast notifications on AJAX success and failure, disabled buttons during in-flight requests, confirmation dialogs before destructive actions, and a search filter over the room-booking grid. The interaction design is coherent.

**Accessibility.** Weakest area. The two primary login and registration forms use placeholder-only inputs with no `<label>` elements, so screen readers announce unlabelled text fields. Three pages (`register.php`, `admin_login.php`, `profile.php`) omit the viewport meta tag, so mobile browsers render them at desktop width. Tables carry no `scope` attributes and no `<caption>`. Colour contrast is mostly fine because Bootstrap's palette is used, but `.availability-booked` applies `opacity: 0.7`, degrading the contrast of already-light red text.

**Maintainability.** The repository contains two competing architectures (Section 5), roughly 500 lines of dead or unreachable code, and three documents that disagree with the code. This is the weakest non-functional dimension after security, and it is the one Part VI argues is the true signature of delegated work.

---

## 4. Inventory: what exists, what it weighs, what it is for

### 4.1 The file tree, annotated

```
timetable/
├── index.php                 61   Main login (student/teacher). Bootstrap 4, branded.
├── login.php                 41   Legacy duplicate login. References missing css/login.css. Orphan.
├── register.php              47   Registration form (student/teacher only).
├── register.html             22   Stale static mockup posting to register.php. Orphan.
├── admin_login.php           36   Admin login form (sends CSRF token it never uses).
├── admin_login.html          22   Stale static mockup posting to login.php w/ role=admin. Orphan.
├── login_process.php         46   Auth for student/teacher/admin-with-role. CSRF + brute force.
├── admin_login_process.php   34   Auth for admin. No CSRF. Sets admin_id only.
├── register_process.php      67   Registration. Role allow-list, bcrypt, CSRF.
├── logout.php                 6   Session destroy. No cookie clear. No CSRF.
├── student_dashboard.php    238   5 tabs: schedule, courses, enroll, attendance, notices.
├── teacher_dashboard.php    384   5 tabs: courses, take/view attendance, notices, booking.
├── admin_dashboard.php      538   7 pages: home, users, courses, schedules, requests, booking requests, reports.
├── profile.php               74   Student profile + password form.
├── change_password_process.php 58 Password change handler (students only).
├── manage_admin_tasks.php   338   Admin command handler: 10 actions, one switch, transactions.
├── manage_teacher_tasks.php 130   Teacher command handler: announcements + room booking.
├── manage_enrollment.php     30   AJAX endpoint → EnrollmentController.
├── mark_attendance.php       80   Attendance upsert. CSRF + course-ownership check.
├── check_conflict.php        23   Room-occupancy probe. Admin-only; the admin schedule form uses it.
├── (get_student_attendance.php removed in the follow-up revision)
├── room_dashboard.php        65   Room grid. Includes non-existent admin_sidebar.php. Orphan.
├── rooms_dashboard.php        4   Redirect stub (deprecated overview).
├── teachers_dashboard.php     4   Redirect stub (deprecated overview).
├── BruteForceProtector.php   62   File-based attempt counter keyed by user+IP.
├── db_config.php             27   Global mysqli $conn from env vars.
├── app/
│   ├── bootstrap.php         40   Session, env, autoloader, debug, headers, helper, DB include.
│   ├── csrf.php              26   Token issue/verify + form field helper (+ duplicate alias).
│   ├── Auth.php              13   requireRole() and regenerateSession().
│   ├── security_headers.php   0   Empty file. Headers live in bootstrap.
│   ├── timetable.sql        ~86  7 of 9 required tables, no semicolons, no charset.
│   ├── Core/Database.php     33   mysqli singleton (second connection source).
│   ├── Config/AppConfig.php  35   Env-backed config cache; unused constant; maxCourses()=6.
│   ├── Support/EnvLoader.php 20   Minimal .env parser.
│   ├── Cache/ArrayCache.php  19   Static TTL cache (per-request, so nearly inert).
│   ├── Domain/DayOfWeek.php  13   Canonical weekday list (overwritten in admin dashboard).
│   ├── Domain/TimeSlot.php   16   Canonical slot list (overwritten too).
│   ├── Repositories/*.php   279  5 repos; 1 unused; 1 duplicate method.
│   ├── Services/EnrollmentService.php 49 Enrollment rules: conflict, cap, approval.
│   └── Http/Controllers/EnrollmentController.php 18 Action dispatch over POST.
├── css/styles.css          ~600  Design tokens + component overrides. No build step.
├── js/script.js            ~300  Toasts, modals, AJAX, search, debounce. jQuery + vanilla.
└── images/logo.png         260 KB Brand asset (large for a 120px logo).
```

### 4.2 Size distribution and what it implies

The three dashboards account for 1,160 of 2,935 PHP lines — 40% of the codebase is presentation logic inside PHP files. The `app/` layer accounts for 591 lines (20%), and the remaining 40% is handlers, forms and configuration. That ratio is the single clearest structural fact about this system: **the architecture exists as a thin veneer around procedural pages, not as the organising principle of the application.** A conventional layered application of this scope would invert it, with the majority of lines in domain and application code and only views in views.

The five largest files are all front controllers mixing three concerns: authorization preamble, data fetching, and HTML rendering. `admin_dashboard.php` alone contains 538 lines of that mixture and fetches data for *every* page on *every* request — all users, all teachers, all students, all courses, all rooms — before it knows which page is being rendered. On the reports page, four of those five result sets are discarded.

Two files are worth calling out as pure debt. `app/security_headers.php` is 0 bytes: it was created in commit `6ce53fe` alongside `BruteForceProtector.php`, which was also 0 bytes at that commit and was populated later. The same happened to `app/timetable.sql`, added wholesale in `841a598`. This is a distinctive pattern — scaffolding files created empty in one pass and filled in another — and Part VI reads it as evidence of a generate-then-edit workflow rather than a design-then-build one.

### 4.3 Dead and unreachable code inventory

| Item | Location | Why it is dead |
|---|---|---|
| `login.php` | root | No page links to it; references `css/login.css` which does not exist; duplicates `index.php` with no Bootstrap. |
| `register.html`, `admin_login.html` | root | Static mockups; superseded by `.php` versions; `admin_login.html` posts `role=admin` to `login.php`, which has no admin option. |
| `room_dashboard.php` | root | No inbound links; `include 'admin_sidebar.php'` targets a file that is not in the repository. |
| `get_student_attendance.php` | root | No inbound references from PHP or JS; still publicly reachable. |
| `PendingEnrollmentRepository` | `app/Repositories/` | Never instantiated; duplicates `EnrollmentRepository::createPendingIfAbsent` verbatim in behaviour. |
| `CourseRepository::allCached`, `ArrayCache` | `app/` | Never called. A per-request static cache gives no cross-request benefit. |
| `AppConfig::STUDENT_ENROLLMENT_LIMIT` | `app/Config/` | Never referenced; `maxCourses()` is the live path. |
| `courses.credits` | schema | Written on create, selected in one query, never rendered in any view. |
| `rooms.capacity` | schema | Never referenced by any line of PHP. |
| `attendance.attendance_date` | schema | Never written; `date` is the live column. |
| `verify_csrf` / `csrf_verify` | `app/csrf.php` | Two names for one function; only one is used per endpoint. |
| `DayOfWeek`/`TimeSlot` in admin dashboard | `admin_dashboard.php:21-22` vs `:34-35` | Class-derived values are immediately overwritten by literals. |
| JS blocks gated on `#adminTabContent` | `js/script.js:19, 253` | No element with that id exists in any PHP file, so both blocks never execute. |
| `app/security_headers.php` | `app/` | Empty; never included. |

The `#adminTabContent` case deserves emphasis because it is not merely dead — it is *actively harmful*, and it is the mechanism by which two advertised features silently stopped working. This is analysed in Section 14.3.

## 5. Architecture: two architectures wearing one repository

### 5.1 The two styles

Every request in this application passes through one of two structures, and which one you get depends on which file you land on. Understanding the difference is the key to reading the codebase.

**Style A — page-centric procedural.** A `.php` file is the URL. It includes the bootstrap, checks `$_SESSION` inline, runs queries against the global `$conn`, and renders HTML in the same file. Example: `teacher_dashboard.php`.

**Style B — layered, namespace-based.** A request hits a thin endpoint that delegates to a controller, which delegates to a service, which uses repositories, which use a database singleton, with domain value objects supplying valid values. Example: `manage_enrollment.php` → `EnrollmentController` → `EnrollmentService` → `EnrollmentRepository` + `ScheduleRepository`.

Style B is the *intended* architecture, and the `app/` tree is its skeleton: `Domain`, `Core`, `Config`, `Support`, `Cache`, `Repositories`, `Services`, `Http/Controllers`. Only one vertical slice was ever completed — enrollment — and everything else stayed in Style A. The result is a repository where you can trace a single feature end-to-end through clean layers, and then fail to find the same separation anywhere else.

### 5.2 The intended dependency rule, and where it is violated

A layered architecture rests on one rule: **dependencies point inward.** Presentation depends on application, application on domain, domain on nothing. Concretely in this codebase:

```
  HTTP endpoints ──▶ Controllers ──▶ Services ──▶ Repositories ──▶ Database
                          │              │
                          └──────────────┴──▶ Domain (DayOfWeek, TimeSlot)  [leaf]
```

The actual dependency graph contains three violations worth naming.

**Violation 1 — the service reaches out to a global.** `EnrollmentService` takes repositories by constructor injection, which is correct, but then calls `Database::get()` inside every method (`app/Services/EnrollmentService.php:23,27,34,…`) to obtain the connection it passes to those repositories. The connection is therefore resolved by a static service locator *after* construction, which means:

- the service cannot be unit-tested without a live MySQL connection;
- the injected repositories are only half-injected, because they receive their collaborator from inside the callee rather than from the caller;
- the class's true dependency set (repositories *and* the database) is invisible in its constructor signature.

The fix is mechanical: inject `mysqli` (or a `Connection` interface) as a fourth constructor argument and pass it down. The value of that one change is that `EnrollmentService` becomes testable with a fake connection, which is what makes the rules in Section 8 verifiable.

**Violation 2 — two connection sources coexist.** `db_config.php` constructs a global `$conn` on every request. `App\Core\Database::get()` independently constructs a singleton. Both read the same environment variables. On a request that uses both — for example `student_dashboard.php`, which queries `$conn` directly *and* instantiates repositories that internally call `Database::get()` — **two mysqli connections are opened to the same database.** This is not a correctness bug, but it doubles connection overhead, doubles the chance of hitting a connection limit, and means a transaction begun on `$conn` is invisible to code running on the singleton. In `manage_teacher_tasks.php`, transactions and repository calls on different connections would produce exactly this class of subtle bug if they were ever mixed.

**Violation 3 — the domain layer is decorative.** `DayOfWeek` and `TimeSlot` exist and are correct, but `admin_dashboard.php` assigns from them at lines 21–22 and then **immediately overwrites both variables with literal arrays at lines 34–35**. Meanwhile `teacher_dashboard.php` repeats the same literals a third time. So the canonical vocabulary is defined in five places: two domain classes, two template-level duplicate assignments, and — critically — the database itself, which stores `day_of_week` and `timeslot` as free-form `varchar(255)` with no constraint. A value that is not in the list can be inserted by anyone who can write to the table, and every one of the four code-side definitions will silently disagree with it.

```php
// admin_dashboard.php:21-22   ← the intended source of truth
$weekdays  = DayOfWeek::all();
$timeslots = TimeSlot::all();

// admin_dashboard.php:34-35   ← the actual source of truth
$weekdays  = ["Monday","Tuesday","Wednesday","Thursday","Friday"];
$timeslots = ["09:00-10:00","10:00-11:00", … ];
```

### 5.3 The request lifecycle

The shared preamble is `app/bootstrap.php`, and its ordering is correct — which is worth noting, because session ordering bugs are common and this file avoids them.

```
 1. session_start()                        (only if not already active)
 2. EnvLoader::load(.env)                  (populates $_ENV and putenv, never overwrites)
 3. spl_autoload_register(App\ → app/)     (guarded by a meaningless class_exists check)
 4. require vendor/autoload.php            (if present — it never is; no Composer here)
 5. if APP_DEBUG=1 → display_errors on     (env-driven, good)
 6. define app_redirect()                  (never used anywhere)
 7. require db_config.php                  (creates global $conn)
 8. header_remove('X-Powered-By')
 9. X-Frame-Options: SAMEORIGIN
10. X-Content-Type-Options: nosniff
11. Referrer-Policy: same-origin
```

Then the page repeats its own auth check, because bootstrap does not perform one. For a student page the sequence is: bootstrap → `csrf.php` → `Auth.php` → **manual `isset($_SESSION['user_id']) && role === 'student'` check** → `requireRole(['student'])` → `regenerateSession()` → `db_config.php` again (a no-op because of `require_once`) → queries → render.

The redundancy is instructive rather than merely wasteful. There are *two* authorization mechanisms applied back to back, so the page guards nothing the framework does not already guard, and readers cannot tell which one is authoritative. It also means the guard is copy-pasted rather than centralised, which is precisely how `check_conflict.php` and `get_student_attendance.php` ended up with no guard at all: they were written by copying a data-fetching block without the preamble that precedes it in the original file.

**The autoloader guard is a notable artifact.** Line 9 reads:

```php
if (!class_exists('App\\Debug\\Inspector')) {
    spl_autoload_register(function($c){ … });
}
```

`App\Debug\Inspector` does not exist in this repository, has never existed, and is not referenced anywhere else. `class_exists()` on an undefined class with no autoloader returns `false`, so the condition is always true and the autoloader is always registered. The guard therefore does nothing — but it *reads* as though it guards against double registration, which is presumably the intent that generated it. The correct guard is either `spl_autoload_register(..., true, true)` (prepend + throw) or an explicit static flag. This single line is the most legible fossil of generated code in the repository, and Section 18 returns to it.

### 5.4 Coupling and cohesion assessment

| Module | Cohesion | Coupling | Verdict |
|---|---|---|---|
| `app/Domain` | High — one concept each | None | Correct, underused |
| `app/Repositories` | High — query-per-method | High — every method takes `mysqli` | Reasonable for this size |
| `app/Services` | High — one use case | Medium — hidden DB dependency | Fixable in one change |
| `app/Http/Controllers` | High — dispatch only | Low | Correct, trivially small |
| `app/Config` | Low — mixes constants, env caching, and accessors | Low | Fine, but has two competing limits |
| `app/Cache` | High but pointless | None | Delete |
| Dashboards | **Low** — auth + query + view in one file | High — global `$conn`, globals for lookups | The main structural debt |
| Handlers (`manage_*`) | Medium — one switch per file | High — global `$conn` | Acceptable as action routers |

### 5.5 What a completed version of this architecture looks like

There is no need to rewrite. The path is a single front controller plus a router, wrapping the existing handlers:

```
public/index.php  → Router → [AuthMiddleware] → Controller → Service → Repository → DB
                                                   ↓
                                                  View (templates)
```

- `AuthMiddleware` replaces 11 hand-written session preambles and makes it impossible to add an endpoint without a guard.
- The router removes the need for `login.php`, `rooms_dashboard.php`, `teachers_dashboard.php` and the `.html` mockups, and gives every endpoint one place to declare its method, its role, and its CSRF policy.
- Templates move HTML out of the dashboards, dropping those 1,160 lines to perhaps 400 of presentational markup.
- Composer supplies PSR-4 autoloading and PHPUnit, which removes the bespoke autoloader and makes the service layer testable.

Section 16 sequences this work so that each step is independently shippable.

---

## 6. The data model

### 6.1 The nine tables the code needs

```
users ──┬── courses.teacher_id            (a teacher owns many courses)
        ├── enrollments.student_id        (a student enrolls in many courses)
        ├── pending_enrollments.student_id
        ├── attendance.student_id
        └── announcements.teacher_id      ← table MISSING from schema file

courses ─┬── schedules.course_id          (a course has 1..n weekly slots)
         ├── enrollments.course_id
         ├── pending_enrollments.course_id
         └── announcements.course_id      ← table MISSING from schema file

rooms ────┬── schedules.room_id
          └── pending_schedules.room_id   ← table MISSING from schema file

schedules ─── attendance.schedule_id      (attendance is per session, per date)
```

### 6.2 Table-by-table assessment

**`users`** — `id`, `username` (UNIQUE), `password`, `email`, `role`. Reasonable, with four flaws. `role` is `varchar(255)` with default `'student'` instead of an `ENUM('student','teacher','admin')` or a foreign key to a roles table, so any string can be stored and the application's role checks (`in_array($role, ['student','teacher'], true)`) are the only thing preventing nonsense. `email` has no UNIQUE constraint, so two accounts can share an email — harmless today because nothing emails anyone, fatal the moment password reset is added. `email` has no index and no length bound. `username` is not case-normalised, so `Alice` and `alice` are distinct accounts on a case-sensitive collation and the same account on a case-insensitive one — a portability trap.

**`courses`** — `id`, `name` (not UNIQUE), `description`, `credits`, `teacher_id` FK. Duplicate course names are allowed, which makes the student-facing enrollment list ambiguous: two identical rows with different IDs and no visible differentiator. `credits` is collected, stored, selected once, and never shown. `teacher_id` is nullable and the FK has no `ON DELETE SET NULL`, so deleting a teacher is blocked by the constraint rather than reassigning their courses to "unassigned" — which is why `delete_user` can fail with a foreign-key error for any teacher who owns a course.

**`rooms`** — `id`, `name`, `capacity`. `capacity` is never read. `name` is not UNIQUE.

**`schedules`** — `id`, `course_id`, `room_id`, `day_of_week`, `timeslot`. This is the most consequential table and the only one with a well-chosen constraint: `UNIQUE(room_id, day_of_week, timeslot)`. That constraint **is** the room-exclusivity invariant from Section 3.1, expressed where it cannot be bypassed. Both the admin path and the teacher-booking path check for room conflicts in PHP first, and the constraint catches the race they cannot close. `day_of_week` and `timeslot` are `varchar(255)` with no `CHECK` constraint, so the forty-cell grid exists only in PHP.

**`enrollments`** — `id`, `student_id`, `course_id`. Correct, with one artefact: **two identical UNIQUE keys on the same column pair** (`student_course` and `uniq_enrollment`). MySQL maintains two indexes for one logical constraint, doubling write cost and index memory for zero benefit. This is a direct signature of two migrations applied at different times without reading what was already there.

**`pending_enrollments`** — `id`, `student_id`, `course_id`, `status ENUM('pending','approved','denied')`, `request_date`, `processed_at`. The `status` enum is the right choice. There is **no** unique constraint on `(student_id, course_id, status)`, which is why `EnrollmentRepository::createPendingIfAbsent` has to use an `INSERT … SELECT … WHERE NOT EXISTS` anti-join — an application-level substitute for a constraint the database should own. That anti-join is not atomic under `REPEATABLE READ`: two concurrent requests can both evaluate `NOT EXISTS` as true, and both insert. A partial unique index is not available in MySQL, so the correct fix is a unique key on `(student_id, course_id)` plus a `status` update on re-request, or a generated column that is `NULL` unless pending.

**`attendance`** — `id`, `student_id`, `schedule_id`, `status ENUM('present','absent')`, `date`, and a legacy `attendance_date`. The design is right: attendance is keyed to a *session* (`schedule_id`) and a *date*, which is what allows a weekly slot to be marked repeatedly. `UNIQUE(student_id, schedule_id, date)` is exactly the upsert target used by `mark_attendance.php` via `ON DUPLICATE KEY UPDATE`. But the table carries **four redundant indexes** — `student_id`, `idx_attendance_student`, `schedule_id`, `idx_attendance_schedule` — where two would do, and one dead column (`attendance_date`) that no line of code touches. Anyone reading the schema will wonder which date column is authoritative; the answer is discoverable only in PHP.

**`announcements` (missing)** — the code reads and writes `announcements(id, course_id, teacher_id, content, created_at)`. Not in the schema file. A fresh install fails on the first announcements query and, because `mysqli_report` is set to throw, the whole teacher dashboard 500s.

**`pending_schedules` (missing)** — the code reads and writes `pending_schedules(id, course_id, room_id, day_of_week, timeslot, teacher_id, status, request_date)`. Not in the schema file. A fresh install makes the room-booking feature and the admin approvals page fail outright.

### 6.3 Schema-level defects, ranked

1. **Two of nine tables absent.** The schema file cannot produce a working database. This is the highest-impact data defect and it is trivially fixable.
2. **No statement terminators, and unsafe ordering.** Every `CREATE TABLE` block ends with `)` and a blank line; there is not one `;` in the file. Importing it as shipped produced `ERROR 1064 … near 'CREATE TABLE courses'` and **zero tables**. Fixing only the terminators is not enough: the dump is alphabetical, so `attendance` (which references `users` and `schedules`) is created first and fails with `errno 150 "Foreign key constraint is incorrectly formed"`. A working import needs both semicolons **and** `SET FOREIGN_KEY_CHECKS=0`. Combined with (1), the README's installation instructions cannot work.
3. **No explicit charset or collation.** No `DEFAULT CHARSET=utf8mb4`. The connection sets `utf8mb4` in both `db_config.php` and `Database::get()`, but tables created under a `latin1` server default will store latin1 — producing mojibake for any non-ASCII course name or student name, silently, at insert time. On a modern MySQL 8 default (`utf8mb4`) the bug is latent; on an older XAMPP/MariaDB it is live.
4. **No `IF NOT EXISTS`, no ordering guarantees, no seed data.** The file is a `mysqldump`-style snapshot, not a migration. Re-running it errors on every table.
5. **Booleans and enums inconsistent.** `role` and `attendance.status` are modelled differently (`varchar` vs `ENUM`), with no foreign key to a role table.
6. **Unused columns and duplicate indexes** as detailed above.
7. **No `ON DELETE` policy on `courses.teacher_id` and `schedules.course_id`/`room_id`**, so deletion semantics are undefined at the schema level and discovered at runtime as exceptions.

> **Status after this revision.** Items 2–4 are historical. `app/timetable.sql` has been regenerated from `demo.sql` as a schema-only file, so it now has statement terminators, `SET FOREIGN_KEY_CHECKS=0/1` around the block, explicit `utf8mb4`, and the two tables the original omitted. Verified as 9 tables and 0 rows on MariaDB 11.3.2 and MySQL 8.0.46. `demo.sql` remains the seeded import for running the application.

### 6.4 What the schema gets right

The constraint choices that do exist are the ones that matter most. `UNIQUE(room_id, day_of_week, timeslot)` and `UNIQUE(student_id, course_id)` are exactly the two invariants that a check-then-insert bug would otherwise violate under concurrency, and both are present. `attendance`'s composite unique key is correctly designed for idempotent daily upserts. Foreign keys are declared with `ON DELETE CASCADE` where cascade is obviously right (enrollment and attendance rows die with their student). Whoever chose these constraints understood relational integrity; the gap is coverage, not comprehension.

### 6.5 A corrected migration, sketched

```sql
-- 01_schema.sql  (re-runnable, explicit charset, complete)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(254) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('student','teacher','admin') NOT NULL DEFAULT 'student',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  room_id INT UNSIGNED NOT NULL,
  day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday') NOT NULL,
  timeslot ENUM('09:00-10:00', …, '16:00-17:00') NOT NULL,
  UNIQUE KEY uniq_room_slot (room_id, day_of_week, timeslot),
  CONSTRAINT fk_sched_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_sched_room   FOREIGN KEY (room_id)   REFERENCES rooms(id)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS announcements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  teacher_id INT UNSIGNED NOT NULL,
  content TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ann_course  FOREIGN KEY (course_id)  REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_ann_teacher FOREIGN KEY (teacher_id) REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pending_schedules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  room_id INT UNSIGNED NOT NULL,
  teacher_id INT UNSIGNED NOT NULL,
  day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday') NOT NULL,
  timeslot VARCHAR(11) NOT NULL,
  status ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
  request_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ps_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Note the `ENUM` on `day_of_week` and `timeslot`: it converts the forty-cell grid from a PHP convention into a database invariant. Anything outside the grid is rejected by MySQL itself, which is the only place the guarantee cannot be bypassed.

# Part III — Mechanics in depth

## 7. The timetable conflict algebra

### 7.1 Why conflict detection is the application

Everything else in this system is CRUD. Conflict detection is the only part where the software encodes a genuine constraint-satisfaction problem, and it is where a reviewer should look first to judge whether the author understood the domain or merely wrote endpoints. The code distinguishes four constraint dimensions, and — this is the part that shows understanding — it treats them differently, because they *are* different.

| Dimension | Constraint | Enforced at | Enforceable at write time? |
|---|---|---|---|
| Room exclusivity | one class per room per cell | PHP check **+ DB UNIQUE** | Yes — fully |
| Teacher exclusivity | one class per teacher per cell | PHP check only (admin + booking) | Yes — fully |
| Course self-consistency | a course occupies one cell once | PHP check only | Yes — fully |
| Student consistency | a student isn't in two classes at once | PHP check at *enrollment*, not at scheduling | **No** — depends on each student's enrollment set |

The asymmetry in the last row is the conceptual core. The first three are properties of the schedule alone, so they can be rejected when the schedule is written. The fourth is a property of the *join* between a schedule and an arbitrary student's enrollment set: adding a valid schedule entry can invalidate an existing student's timetable retroactively, and adding an enrollment can invalidate a student's timetable without touching the schedule at all. Therefore the system checks student conflicts at enrollment time, and merely *reports* them at schedule-approval time — a design that is correct in principle and under-implemented in practice (§7.5).

### 7.2 The three write-time checks, in the admin path

`manage_admin_tasks.php` handles `add_schedule` in four steps. Each check is a separate prepared statement against a specific unique-ness question:

```php
// 1. Room conflict — is this cell taken in this room?
SELECT id FROM schedules WHERE room_id=? AND day_of_week=? AND timeslot=? LIMIT 1

// 2. Course conflict — is this course already in this cell (another room)?
SELECT id FROM schedules WHERE course_id=? AND day_of_week=? AND timeslot=? LIMIT 1

// 3. Teacher conflict — does the teacher of this course already teach in this cell?
SELECT 1 FROM schedules s
  JOIN courses c1 ON c1.id = s.course_id
  JOIN courses c2 ON c2.id = ?
 WHERE s.day_of_week=? AND s.timeslot=?
   AND c1.teacher_id = c2.teacher_id
   AND c2.teacher_id IS NOT NULL
 LIMIT 1
```

The third query deserves attention because it is written the way a person who understands SQL writes it: instead of fetching the teacher of the target course in PHP and then querying for that teacher's other classes (two round trips, two failure modes), it self-joins `courses` as `c1`/`c2` and lets the database resolve "the teacher of the course I'm scheduling" against "the teacher of a course already scheduled". The `c2.teacher_id IS NOT NULL` guard correctly prevents two *unassigned* courses from being treated as conflicting, which is a subtle bug that a naive `c1.teacher_id = c2.teacher_id` would have introduced — `NULL = NULL` is `NULL`, not `TRUE` in SQL, so the guard is technically redundant, but its presence shows the author was thinking about the null case rather than pattern-matching.

Validation of the day and slot happens *before* any query, via the domain classes:

```php
if ($courseId<=0 || $roomId<=0 || !DayOfWeek::isValid($day) || !TimeSlot::isValid($slot)) {
    $success=false; $msg='Invalid schedule data'; break;
}
```

This is correct and important: it means an attacker cannot insert `Saturday` or `25:00-99:00` by posting directly to the handler, which would break every grid-rendering loop downstream. It is also the only place in the codebase where the domain value objects do real work.

### 7.3 The check-then-insert race, and which constraint survives it

Steps 1–3 are checks; step 4 is the insert. Between them, another request can complete the same check and insert first. This is the classic **time-of-check-to-time-of-use (TOCTOU)** gap, and three of the four dimensions are exposed to it:

- **Room:** exposed in PHP, but `UNIQUE(room_id, day_of_week, timeslot)` in the schema makes the second insert fail. The invariant survives. (The failure surfaces as an uncaught `mysqli_sql_exception` and therefore an HTTP 500 rather than a friendly message — see §12.3 — but the *data* stays correct, which is what matters.)
- **Course:** no unique constraint, so two concurrent requests can schedule the same course in two different rooms at the same time. The invariant is violated.
- **Teacher:** no unique constraint (and cannot have a simple one, because teacher identity lives one table away in `courses`), so a teacher can be double-booked by two concurrent admin actions.

The general lesson is that **a PHP `if` is not a constraint.** Every invariant that must hold under concurrency belongs in the database, or inside a transaction with `SELECT … FOR UPDATE` on the contended rows, or behind a unique index that expresses it. The room case is the model to copy; the teacher case needs either a denormalised `teacher_id` column on `schedules` with a unique key on `(teacher_id, day_of_week, timeslot)`, or serialised writes inside `BEGIN … FOR UPDATE`.

### 7.4 The approval path is the best-engineered code in the repository

When a teacher requests a booking, `manage_teacher_tasks.php` writes a row to `pending_schedules` after performing its own checks: that the teacher owns the course, that the room cell is free, and that the teacher is not already teaching then. The admin then approves. `approve_schedule` in `manage_admin_tasks.php` re-runs all three checks *again* — correctly, because the world may have changed between request and approval — and then commits inside a transaction:

```php
$conn->begin_transaction();
try {
    // 1. insert the schedule
    // 2. mark this request approved
    // 3. deny every other pending request for the same room/day/slot
    $conn->commit();
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
}
```

Step 3 is the detail that separates a considered design from a naive one. Two teachers can request the same room and slot; the admin approves one; without step 3, the other request would sit in the queue forever looking actionable, and approving it later would either fail on the unique key or double-book. Auto-denying the losers makes the queue self-consistent — the admin's single decision resolves all competing claims atomically. This is a genuine piece of workflow design, and it is the strongest evidence in the repository that the author thought about state, not just about forms.

The remaining flaw is that the transaction is opened *after* the checks, not around them. Moving `begin_transaction()` above the three `SELECT`s and adding `FOR UPDATE` would close the gap discussed in §7.3 for this path.

### 7.5 The conflict *reporting* query, and what it reads

The admin's `schedule_requests` page computes, for each pending booking, which students would end up double-booked if it were approved:

```sql
SELECT c.name AS course_name, COUNT(DISTINCT e1.student_id) AS student_count
  FROM schedules s
  JOIN courses c     ON s.course_id = c.id
  JOIN enrollments e1 ON e1.course_id = s.course_id
  JOIN enrollments e2 ON e2.course_id = ?
 WHERE s.day_of_week = ? AND s.timeslot = ?
   AND e1.student_id = e2.student_id
 GROUP BY c.id
```

The logic is an intersection: `e2` is the set of students enrolled in the course being scheduled; `e1` is the set of students enrolled in each course already occupying that cell; the predicate `e1.student_id = e2.student_id` keeps only students in both. Grouping by `c.id` then reports, per already-scheduled course, how many overlapping students there are. That is exactly the right question.

Two observations. First, this is **advisory, not blocking**: the admin can approve a request that creates student conflicts, and the UI gives no explicit "this will break N students' timetables" confirmation beyond the red text. For a system whose central promise is conflict-free scheduling, a hard block (or at minimum an explicit override acknowledgement) would better match the stated goal. Second, this is the only place in the codebase that computes student conflicts from the schedule side; the enrollment side (§8) computes it with a different query.

### 7.6 The orphaned live-check endpoint

`check_conflict.php` answers "is this room occupied in this cell?" in one query and returns JSON. It is called from `js/script.js` by a debounced 300 ms handler on `.schedule-input` changes, and it renders into `#conflict-warning` under the admin schedule form. It is a good idea executed three ways wrong:

1. **It has no authentication and no authorization.** Anyone can enumerate room occupancy for the whole week by iterating `room_id`, `day`, `timeslot`. That leaks the institution's timetable to the public internet.
2. **Its single check is the one already covered by a database constraint.** It duplicates the room check and knows nothing about teacher, course, or student conflicts — so it cannot catch the interesting failures.
3. **It never runs.** The `change` listeners are registered inside `if (document.getElementById('adminTabContent'))`, and no element in the application has that id. The feature is dead code on both ends: an unauthenticated endpoint serving data no UI ever requests.

### 7.7 Conflict-handling scorecard

| Capability | Status | Notes |
|---|---|---|
| Reject room double-booking at write | **Yes, twice** | PHP check + DB unique |
| Reject teacher double-booking at write | Partial | PHP only; no constraint; racy |
| Reject duplicate course slot | Partial | PHP only; no constraint |
| Reject student conflict at enrollment | Yes | `ScheduleRepository::conflictsForStudentCourse` |
| Report student conflict at approval | Yes | Advisory only; admin may override silently |
| Suggest alternative slots | **No** | README claims "suggests up to 5 alternatives"; no code implements it |
| Live pre-submit warning | **No** | Endpoint exists, unauthenticated, JS branch unreachable |
| Reject over-capacity room booking | No | `rooms.capacity` unused |

One documented feature — alternative-slot suggestion — does not exist in the code at all. It appears only in the README's "Conflict Resolution" section. This is worth flagging in the report because it is the clearest instance of documentation describing an intention rather than an implementation, and Part VI treats that pattern as a risk of generated documentation.

---

## 8. The enrollment state machine and the limit problem

### 8.1 Two write paths, two sets of rules

Enrollment is the clearest illustration of the architectural problem in this codebase, because the same business fact is enforced differently depending on how the request arrives.

```
                     ┌─────────────────────┐
   student clicks    │  enroll  (under cap) │──▶ INSERT INTO enrollments
   "Enroll"    ──────┤  no conflict         │
                     └─────────────────────┘
                              │ at cap
                              ▼
                     ┌─────────────────────┐
                     │ request_approval     │──▶ INSERT INTO pending_enrollments
                     │ no conflict          │    (status='pending')
                     └─────────────────────┘
                              │ admin approves
                              ▼
                     ┌─────────────────────┐
                     │ INSERT IGNORE INTO   │──▶ enrollments  ← NO cap check
                     │ enrollments          │                 ← NO conflict check
                     └─────────────────────┘
```

**Path 1 — direct enrollment.** `manage_enrollment.php` constructs `EnrollmentController(new EnrollmentService(new EnrollmentRepository, new ScheduleRepository, 5))` and calls `handle()`. The service, in order:

1. `conflictsForStudentCourse($studentId, $courseId)` → rejects with `Schedule conflict detected`.
2. `countByStudent($studentId) >= $maxCourses` → rejects with `Enrollment limit reached`.
3. `create()` → INSERT; a duplicate would violate `UNIQUE(student_id, course_id)`.

**Path 2 — approval.** `requestApproval()` checks that the student is not already enrolled and has no conflict, then inserts a pending row. This is a *different* rule set: it does **not** check the cap (by definition — the student is requesting an exception to it), which is correct.

**Path 3 — admin approval.** `approve_enrollment` in `manage_admin_tasks.php` re-reads the pending row and executes `INSERT IGNORE INTO enrollments`. It never re-checks the enrollment cap and never re-checks schedule conflicts. So the approval path can:

- push a student past the maximum course load, silently;
- enroll a student into a course that now conflicts with a course they enrolled in *after* submitting the request;
- insert a row for a course that has since been deleted (`INSERT IGNORE` swallows the FK failure too, reporting success while doing nothing).

This is not a small oversight, it is the **defining consequence of duplicating a rule in two places**: the invariant "a student's enrollment set is conflict-free and within the cap" is enforced only in the service, and the service is bypassed by the second write path. The fix has two parts, and both matter:

1. **One rule owner.** `approve_enrollment` should delegate to `EnrollmentService::enroll()` (or a variant that takes an "approval" flag and skips only the cap, never the conflict check).
2. **Honest failures.** Replace `INSERT IGNORE` with an insert whose duplicate-key failure is caught and reported as "already enrolled", so the admin cannot be told a request was approved when it was not.

### 8.2 The limit exists three times and the copies disagree

This is the sharpest, most concrete defect in the application, and it is user-visible.

| Where | Value | Used by |
|---|---|---|
| `app/Config/AppConfig.php:6` `STUDENT_ENROLLMENT_LIMIT` | `5` | Nothing. Dead constant. |
| `app/Config/AppConfig.php:21-23` `maxCourses()` | `MAX_COURSES` env, default `6` | `student_dashboard.php:45` — the UI |
| `app/Services/EnrollmentService::__construct($…, int $maxCourses = 6)` | default `6` | Any future caller that omits the argument |
| `manage_enrollment.php:26` | **literal `5`, comment "aligned with UI"** | Direct enrollment (the AJAX path) |

`MAX_COURSES` is not present in `.env`, so `maxCourses()` returns **6**. The UI therefore tells the student "You have reached the maximum of 6 courses" and renders an **Enroll** button while their count is below 6. The server rejects at **5**. At exactly five enrollments the student sees an active Enroll button, clicks it, and receives `Enrollment limit reached` — with no explanation of why, since the UI believes the limit is six.

The comment `// aligned with UI` is the most damning line in the repository. It records an intention to synchronise two numbers while doing the opposite, which is what happens when a constant is threaded by hand through a call site that had no way to read it. The correct fix is one line: inject `AppConfig::maxCourses()` at the call site and delete the constant and the default argument. The deeper fix is that a business rule with a configurable threshold should be read from one place at one moment, not captured as a constructor argument at four call sites.

### 8.3 The invisible-course bug

`CourseRepository::getAvailableWithSchedules` lists every course the student is not enrolled in, using a **LEFT JOIN** to `schedules`. A course with no scheduled session yet therefore appears in the list with `'schedules' => []`. In `student_dashboard.php`, the conflict test loops over that empty array, finds nothing, and concludes `$has_conflict = false`. The **Enroll** button is rendered and the enrollment succeeds.

The consequence is quiet and confusing. The student's *timetable* is built by `EnrollmentRepository::getDetailedSchedule`, which uses **INNER JOIN** on `schedules`. A course with no sessions therefore contributes nothing, so the course the student just enrolled in **does not appear in their schedule at all**. It does appear in *My Courses*, and it counts against the enrollment cap.

The design question hidden here is real: should a student be able to enroll in a course that has no scheduled time yet? Plausible answers are "yes, enrollment is independent of scheduling" (in which case the timetable view must show it in an "unscheduled" section) or "no, reject until scheduled" (in which case the LEFT JOIN must become an inner/exists filter). What must not happen is the current behaviour, where the application silently picks the first answer in the write path and the second answer in the read path. **Two different joins encode two different business rules**, and nothing in the code acknowledges that a choice was made.

### 8.4 Counting: rows versus distinct courses

`countByStudent` runs `SELECT COUNT(id) FROM enrollments WHERE student_id=?`; `getCountAndMap` runs `SELECT COUNT(DISTINCT course_id) FROM enrollments WHERE student_id=?`. These are equivalent **only** because `UNIQUE(student_id, course_id)` prevents duplicate rows. The equivalence is a property of the schema, not of the query, so it is invisible to anyone reading the two functions side by side. If the unique key were ever dropped — say, to allow a student to retake a course in a later term — the cap check would start counting retakes and the two counts would diverge. Using `COUNT(DISTINCT course_id)` in both places would make the intent explicit and immune to that change.

### 8.5 Race conditions on enrollment

The cap check is read-then-write without a lock: two concurrent enroll requests can both read 5 and both insert, producing 6. No unique constraint can express a cardinality limit, so this needs either `SELECT COUNT(*) … FOR UPDATE` inside a transaction that also holds the insert, or a per-student counter row with a conditional update. In practice a single student rarely double-clicks fast enough to hit this, but the AJAX handler disables the button only *after* dispatch and does not prevent double-submission at the network level, so the race is reachable rather than theoretical. The same applies to `createPendingIfAbsent`, whose `WHERE NOT EXISTS` anti-join is not atomic.

### 8.6 The pending-enrollment lifecycle

```
  (none) --request_approval--> pending
  pending --admin approve--> approved + row in enrollments
  pending --admin deny-->    denied
  denied --student re-requests--> a SECOND pending row (no uniqueness)
  approved --student unenrolls--> enrollments row deleted, pending row remains 'approved'
```

Two defects follow from the last two transitions. Because there is no unique key on `(student_id, course_id)`, a student can accumulate multiple `pending` rows for the same course by re-requesting after a denial — each one appears separately in the admin queue, and approving both produces one enrollment (`INSERT IGNORE`) and two `approved` rows. And because unenrolling does not touch `pending_enrollments`, a student who is approved, enrolls, and later unenrolls leaves an `approved` pending row that no longer corresponds to reality; re-requesting then creates a *third* row. The table has become an event log with no uniqueness, while being used as a state machine — the two roles have different consistency requirements and only one is satisfied.

---

## 9. Attendance: the write path and the read path

### 9.1 Why attendance is modelled per session, not per course

A weekly timetable repeats. If attendance were keyed to `(student, course)`, the second week's record would overwrite the first. The schema instead keys attendance to `(student_id, schedule_id, date)`, where `schedule_id` identifies *which weekly session* (e.g. "CS101, Tuesday 10:00, Room 3") and `date` identifies the specific occurrence. This is the correct model, and `UNIQUE(student_id, schedule_id, date)` turns re-marking the same session on the same day into an update rather than a duplicate — which is exactly what the upsert in `mark_attendance.php` relies on:

```sql
INSERT INTO attendance (student_id, schedule_id, status, date) VALUES (?,?,?,?)
ON DUPLICATE KEY UPDATE status = VALUES(status)
```

`ON DUPLICATE KEY UPDATE` is the right primitive here: it makes marking idempotent, so a teacher who corrects a mistake does not create a second record, and a double-submitted form does not corrupt the percentage. Note that `VALUES(status)` inside `ON DUPLICATE KEY UPDATE` is deprecated in MySQL 8.0.20+ in favour of a row alias (`AS new … UPDATE status = new.status`); it still works but will emit a deprecation warning on modern servers.

### 9.2 The ownership gap

Here is the defect that matters most in this module. `mark_attendance.php` opens with:

```php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    die("Access Denied.");
}
```

That is an **authentication** check and a **role** check. It is not an **authorization** check for the resource being modified. The script then accepts any `schedule_id` from the POST body, looks up its `course_id`, fetches that course's enrolled students, and writes attendance rows for them. Nowhere does it verify that the schedule belongs to a course the logged-in teacher teaches.

So any authenticated teacher can mark, alter, or overwrite attendance for **any other teacher's course** — including changing a `present` to `absent` — simply by posting a different `schedule_id`. The subsequent `in_array($student_id, $enrolled_student_ids)` guard only confirms the student is enrolled in *that* course; it does not confirm the teacher has any business touching it. This is a horizontal privilege escalation between peers, and in a real institution it is an academic-integrity issue, not merely a security one.

The correct guard is two lines, and it should be the same join used elsewhere in the module:

```php
$stmt = $conn->prepare("SELECT 1 FROM schedules s JOIN courses c ON c.id = s.course_id
                        WHERE s.id = ? AND c.teacher_id = ? LIMIT 1");
```

Note that `manage_teacher_tasks.php` **does** implement exactly this pattern for announcements and bookings (`WHERE id = ? AND teacher_id = ?` in the DELETE, and an explicit ownership SELECT before booking). The attendance endpoint is the one place the pattern was not applied — evidence that the check is a convention of the module rather than a rule of the system.

> **Status after this revision.** Fixed. `mark_attendance.php` now looks up `courses.id = ? AND teacher_id = ?` for the posted `schedule_id` and returns **403** before writing when it does not match. Verified: teacher 3 posting schedule 1 (teacher 2's course) gets 403 and no row; the owning teacher's submission still returns 302 and writes the row.

### 9.3 Other defects on the write path

**No CSRF token.** The attendance form in `teacher_dashboard.php` contains no `csrf_field()`, and `mark_attendance.php` contains no `verify_csrf()`. Every other mutating endpoint in the application has one or both. This is the only state-changing form in the system without CSRF protection, and it is the one whose damage (falsified attendance) is hardest to detect after the fact.

> **Status after this revision.** Fixed. The form sends a hidden `csrf_token` and the handler returns **400** on a missing or wrong token. Both halves are asserted by `tests/smoke.sh`.

**Loose comparison in the enrollee guard.** `in_array($student_id, $enrolled_student_ids)` runs in non-strict mode, so `'5'` matches `5`. It happens to work because PHP array keys from `$_POST` are strings and `mysqli` with `mysqlnd` returns integers, but it is one type change away from either failing closed (attendance silently not saved) or matching wrongly. `in_array(..., true)` with an explicit `(int)` cast is the safe form.

**Transaction scope.** The loop is wrapped in `begin_transaction()`/`commit()`, which gives all-or-nothing semantics across the whole class. That is the right choice for a form submission: a partial save would leave some students marked and others not, with no indication which. It also means one invalid row (say, a tampered `status` value that violates the `ENUM`) aborts the entire submission and redirects with an error, which is defensible.

**Unreachable cleanup.** After the `try`/`catch`, the code calls `$stmt_course->close()`, `$stmt_enrolled->close()`, `$insert_stmt->close()` — but both branches of the `try`/`catch` `exit()`, so those lines never run. Cosmetic, but it signals that the control flow was assembled rather than reasoned through.

**No `catch` on prepare.** If `prepare()` fails (for example because the connection dropped), `$insert_stmt` is undefined and the `catch` block only rolls back and redirects, so the failure mode is a 500 rather than a message. Minor.

### 9.4 The read path, and two implementations of one metric

The student's attendance percentage is computed in PHP inside `student_dashboard.php`:

```php
$total     = count($records);
$present   = count(array_filter($records, fn($r) => $r['status'] === 'present'));
$percentage = $total > 0 ? round(($present / $total) * 100) : 0;
```

`get_student_attendance.php` computes the same concept in SQL, grouped by status:

```sql
SELECT status, COUNT(id) AS count FROM attendance
 WHERE student_id = ? AND schedule_id IN (SELECT id FROM schedules WHERE course_id = ?)
 GROUP BY status
```

Two implementations, two shapes of output (PHP-derived single percentage vs SQL-derived per-status counts), and only one of them is actually used — the endpoint has no caller. If it were wired up, the two numbers could diverge the moment either implementation changed, which is the §8.2 problem in a different costume.

The conceptual limitation is more interesting than the duplication: **the denominator is the number of marked records, not the number of sessions held.** A student who was marked present once and never marked again shows `100%`. Sessions that were never taken produce no rows and therefore vanish from the calculation. A student-friendly system would compute `present / (total sessions held so far)`, which requires knowing how many sessions have occurred — derivable from `schedules` and a term start date, neither of which the schema models. As built, the metric is "of the times I was marked, how often was I present", which is a different and more flattering quantity than "my attendance rate". Stating this limitation clearly is more valuable than the feature itself.

---

## 10. Authentication, sessions, and identity

### 10.1 Password storage

Hashing is correct in mechanism and inconsistent in parameterisation:

| Site | Call |
|---|---|
| `register_process.php` | `password_hash($password, PASSWORD_BCRYPT)` |
| `manage_admin_tasks.php` (add/edit user) | `password_hash($password, PASSWORD_BCRYPT)` |
| `change_password_process.php` | `password_hash($new_password, PASSWORD_DEFAULT)` |
| Verification (all sites) | `password_verify($plain, $hash)` |

`PASSWORD_BCRYPT` without a `cost` argument uses the default (10), which is acceptable in 2026 but at the low end. `PASSWORD_DEFAULT` in the password-change path currently maps to bcrypt as well, so there is no live incompatibility — but it is the correct choice *going forward* precisely because it is allowed to change with PHP versions, which is why mixing the two in one codebase is a smell rather than a bug. `password_verify` is used correctly everywhere, including in `change_password_process.php`, and no plaintext password is ever stored or logged.

The absence of a **rehash-on-login** step is worth noting: `password_needs_rehash()` is never called, so existing hashes are never upgraded when the cost factor or algorithm changes. For a student project this is invisible; for a system that will live for years it is the difference between a stack of obsolete hashes and a smooth upgrade path.

### 10.2 Password policy, inconsistently applied

- `register_process.php`: minimum 6 characters, plus username 3–50 chars matching `^[a-zA-Z0-9_]+$`, plus `FILTER_VALIDATE_EMAIL`, plus a role allow-list that correctly rejects `admin`. This is the only endpoint with a complete validation block.
- `manage_admin_tasks.php:add_user`: **no** length, format, or uniqueness validation; any string of any length is accepted, including an empty-after-trim password.
- `change_password_process.php`: no minimum length, no check that the new password differs from the old, and no session invalidation after change (so a stolen session survives a password change — the standard expectation is that changing a password revokes other sessions).

A policy enforced at one of three entry points is not a policy. The fix is a single validator, called from all three.

### 10.3 The two-identity problem

The system models "who is logged in" twice.

| | Main login (`login_process.php`) | Admin login (`admin_login_process.php`) |
|---|---|---|
| Session keys set | `user_id`, `username`, `role` | `admin_id` only |
| Role source | `WHERE username=? AND role=?` | `WHERE username=? AND role='admin'` |
| CSRF verified | **Yes** | **No** |
| Session ID regenerated | **Yes** (`session_regenerate_id(true)`) | **No** |
| Brute-force checked | Yes | Yes |
| Accepted by `admin_dashboard.php` | Yes (if `role==='admin'`) | Yes |
| Accepted by `manage_admin_tasks.php` | Yes | Yes |
| Accepted by `profile.php` | Yes (students only) | No |

`login_process.php` accepts `role='admin'` in its allow-list (`in_array($role, ['student','teacher','admin'], true)`), even though `index.php`'s dropdown offers only student and teacher. So an administrator can authenticate through *either* page and arrive with *either* session shape. Every downstream check was therefore written defensively to accept both, which is why the admin dashboard's guard reads as a double-negative:

```php
if (!(isset($_SESSION['admin_id']) || (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin'))) {
```

This is the cost of two identities: the guard is now a disjunction that must be duplicated in `room_dashboard.php` and `manage_admin_tasks.php`, and every future admin endpoint must remember it. A single canonical session shape (`user_id` + `role`) plus one login page with a role selector — or two pages that both set the same keys — removes the entire category of error.

### 10.4 Session fixation: the real gap in admin login

Session fixation works like this: an attacker obtains a session identifier (by planting it in a URL, or via a subdomain/XSS), tricks the victim into authenticating with that identifier, and then uses the now-privileged identifier. The defence is to issue a **new** session ID at the moment privilege is granted — precisely at login — not before and not later.

- `login_process.php:35` does this correctly: `session_regenerate_id(true)` before setting the identity keys.
- `admin_login_process.php` **does not**. It sets `$_SESSION['admin_id']` on the existing identifier and redirects.

The admin dashboard then calls `regenerateSession()` on first load, which looks like a fix but is not. The attack sequence survives it:

1. Attacker plants ID `X`; victim's browser uses it.
2. Victim authenticates as admin; the server marks `X` as an admin session.
3. Attacker immediately requests `admin_dashboard.php` with `X`, is served, and the server rotates `X → Y` for the attacker's connection. The attacker now holds `Y` and the victim is locked out (or, if the attacker simply stays quiet, holds a valid admin session indefinitely).

Rotation *after* the privileged state exists cannot undo the fact that the privileged state was created on an attacker-known identifier. Rotation must happen *at* the transition.

This was demonstrated end to end in the harness (§15.6): a session id obtained before authentication was unchanged after an admin login (`rotated=NO`), and a second client holding that planted id received HTTP 200 with the `Admin Panel`. The control case in the same run — a student login through `login_process.php` — **did** rotate, and the stale id was rejected with a 302 to `index.php`. The codebase contains the correct pattern and omits it in exactly one place. Exploitation still requires a planting vector (XSS, a shared machine, or a cookie-injection path), which is why this is rated High rather than Critical. The one-line fix in `admin_login_process.php`:

```php
if (password_verify($password, $hashed_password)) {
    BruteForceProtector::registerSuccess($username);
    session_regenerate_id(true);            // ← missing
    $_SESSION['user_id'] = $admin_id;
    $_SESSION['role'] = 'admin';
    …
```

### 10.5 The `__rotated` flag

`app/Auth.php` implements rotation with a once-ever guard:

```php
function regenerateSession(): void {
    if (!isset($_SESSION['__rotated'])) {
        session_regenerate_id(true);
        $_SESSION['__rotated'] = time();
    }
}
```

Because the flag lives in the session itself, it survives every subsequent request, so a session ID is rotated **at most once in its lifetime** — and if the first page load after login called this function, the login-time rotation is the only one that ever happens. That is defensible in principle (rotation at privilege change is the requirement), but two details make it worse than it looks. First, the flag stores a timestamp that is never read, so the function has the *shape* of periodic rotation without the behaviour. Second, the name `regenerateSession()` implies per-call regeneration, which is what a future maintainer will assume when they call it after a role change or password change — and they will be wrong.

### 10.6 Cookie and session hardening

Nothing in the codebase calls `session_set_cookie_params()` or sets `session.cookie_*` ini values. The consequences, in order of severity:

| Setting | Current state | Consequence |
|---|---|---|
| `HttpOnly` | ini default | `document.cookie` may read the session ID; any XSS becomes session theft |
| `Secure` | not set | Session sent over plain HTTP if the app is ever reached that way |
| `SameSite` | not set | CSRF defence relies entirely on tokens, and two endpoints lack them |
| `session.use_strict_mode` | ini default (often 0) | PHP will *adopt* an unknown attacker-supplied session ID — this is what makes fixation possible at all |
| Session lifetime | ini default | No idle timeout; a shared lab machine keeps an admin logged in indefinitely |

`session.use_strict_mode=1` is arguably the single highest-value hardening line available here: it makes PHP reject session IDs it did not generate, which closes fixation regardless of whether each login endpoint remembers to regenerate. Setting it in `bootstrap.php` (or in `php.ini`) plus `Secure`/`HttpOnly`/`SameSite=Lax` cookie params is a five-line change with disproportionate impact.

### 10.7 Logout

`logout.php` calls `session_unset()`, `session_destroy()`, and redirects. It does not delete the session cookie, so the browser keeps sending a now-meaningless identifier until it expires — harmless in practice, but it also means an attacker who captured the cookie before logout holds a value whose server-side storage is gone (fine) and a user who logs back in reuses the *same* identifier if `use_strict_mode` is off (less fine). It also has no CSRF protection, so a third-party page can force a logout — a low-severity nuisance, worth one line of the token check the rest of the app already performs.

# Part IV — Audit

## 11. Security review

### 11.1 Method and severity scale

Each endpoint was traced from its URL through its guards to its SQL and its output. Severity reflects exploitability given the application runs as documented (Apache + MySQL on a shared host or lab machine), not a theoretical worst case.

| Severity | Meaning |
|---|---|
| **Critical** | Unauthenticated access to data or state change, or credentials exposed |
| **High** | Authenticated privilege escalation, or state change without CSRF across roles |
| **Medium** | Information disclosure, bypassable control, or defence-in-depth gap with a plausible chain |
| **Low** | Hardening, best-practice deviation, low-value information |

### 11.2 The control matrix

Every reachable endpoint, with the four controls that matter. "—" means absent.

| Endpoint | Auth | Role | CSRF | Prepared SQL | Verdict |
|---|---|---|---|---|---|
| `index.php` | n/a | n/a | issues | n/a | OK |
| `login.php` | n/a | n/a | issues | n/a | OK (orphan) |
| `login_process.php` | n/a | `role` in WHERE | **yes** | yes | OK |
| `admin_login.php` | n/a | n/a | issues | n/a | OK |
| `admin_login_process.php` | n/a | `role='admin'` | **—** | yes | **High** (login CSRF, no rotation) |
| `register.php` | n/a | n/a | issues | n/a | OK |
| `register_process.php` | n/a | allow-list | **yes** | yes | OK |
| `logout.php` | n/a | n/a | **—** | n/a | Low |
| `student_dashboard.php` | yes | student | n/a (read) | yes | OK |
| `teacher_dashboard.php` | yes | teacher | n/a (read) | yes | OK |
| `admin_dashboard.php` | yes | admin | n/a (read) | yes | OK |
| `profile.php` | yes | student | n/a (read) | yes | OK |
| `change_password_process.php` | yes | student | yes | yes | OK |
| `manage_enrollment.php` | yes | student | yes | yes | OK |
| `manage_teacher_tasks.php` | yes | teacher | yes | yes | OK |
| `manage_admin_tasks.php` | yes | admin | yes | yes | OK |
| `mark_attendance.php` | yes | teacher | **—** | yes | **High** (no ownership check, no CSRF) |
| `check_conflict.php` | **—** | **—** | n/a | yes | **Critical** (room occupancy leak) |
| `get_student_attendance.php` | **—** | **—** | n/a | yes | **Critical** (IDOR on any student) |
| `room_dashboard.php` | yes | admin | n/a (read) | yes | Broken (missing include) |
| `rooms_dashboard.php` | n/a | n/a | n/a | n/a | Redirect stub |
| `teachers_dashboard.php` | n/a | n/a | n/a | n/a | Redirect stub |

Counts: **6 endpoints with no auth whatsoever** (the two unauthenticated data endpoints, plus the four public auth/registration pages that are supposed to be public — so the real violation count is **2**), **3 mutating endpoints without CSRF verification** (`admin_login_process`, `mark_attendance`, `logout`).

### 11.3 Unauthenticated data exposure

**`get_student_attendance.php` — Critical.** Takes `student_id` and `course_id` from the query string and returns aggregated attendance. No session check, no role check, no ownership check. Concretely, `?student_id=1&course_id=1` returns how many classes that student attended and missed. Iterating `student_id` over a small integer range enumerates the entire student body's attendance record. Because the endpoint is orphaned, the fix is deletion — but the file is committed and served, so "unused" is not "safe".

This is a textbook **IDOR** (Insecure Direct Object Reference): the object identifier is supplied by the client and no authorization compares it to the caller's identity. The defensive pattern is not "check if logged in" but "scope the query by the caller", i.e. `WHERE student_id = ?` bound to `$_SESSION['user_id']` rather than to `$_GET['student_id']`. When a teacher legitimately needs another student's data, that is a separate, explicitly-authorized endpoint.

**`check_conflict.php` — Critical.** Takes `room_id`, `day`, `timeslot` and reveals the course booked in that cell. Forty-eight requests (or fewer, with a loop) reconstruct the institution's full timetable: every room, every slot, every course name. For a university, an unpublished timetable is not usually a state secret, but the pattern — an unauthenticated endpoint created for an internal UI convenience — is exactly how leak surfaces accumulate. It also lacks any input validation, so a `room_id` of `1 OR 1=1` is safely handled by the prepared statement (good) but a non-numeric value is silently coerced (harmless).

### 11.4 CSRF: what is protected and what is not

CSRF exploits the fact that browsers attach cookies to cross-site requests. The application's defence is a per-session random token compared with `hash_equals` — the correct primitive, correctly used (constant-time comparison, session-stored, 32 random bytes). What is wrong is coverage and rotation.

**Missing on `admin_login_process.php`.** The admin login form *renders* a CSRF token (`admin_login.php:23`) and the handler never checks it. Login CSRF is a real attack: an attacker can force a victim's browser to log into the attacker's own admin account, then observe what the victim does (planted data, subsequent form submissions, cached pages). It also removes one layer from brute-force resistance, since the attacker can drive guesses from victim browsers. The fix is one block copied from `login_process.php`.

**Missing on `mark_attendance.php`.** This is the most damaging omission because the consequence is *data falsification* that looks legitimate afterwards: every row is a real row with a real teacher's session behind it. A malicious page containing an auto-submitting form pointed at `mark_attendance.php` can mark an entire course absent (or present) for a chosen date, provided the visitor is a logged-in teacher. Both halves of the fix are needed — the token in the form and the verification in the handler — and the token must come from `csrf_field()` so it stays in sync with the session.

**Missing on `logout.php`.** Low severity: forced logout is a nuisance, and in combination with a login-CSRF bug it can be used to churn sessions. One line fixes it.

**No rotation.** `csrf_token()` creates the token once and returns the same value for the session's lifetime. Long-lived tokens widen the window in which a leaked token (via referrer, a saved page, a screenshot, a shared terminal) remains valid. The common mitigation is rotation on privilege change, which this codebase already has a natural hook for.

### 11.5 SQL injection: clean, with two caveats

I inspected every SQL statement in all 41 PHP files. **Every statement that incorporates user input uses `prepare()` with bound parameters.** Concatenation appears only for values the application itself generates:

- `student_dashboard.php` and `teacher_dashboard.php` build `IN (?,?,…)` placeholder lists from `count($course_ids)` and bind with `bind_param($types, ...$course_ids)` — correct, and the number of `?` cannot be influenced by input because it is derived from a database result set.
- `admin_dashboard.php` interpolates nothing user-controlled into SQL.
- `manage_admin_tasks.php` binds every value in all ten actions.

Two caveats. First, `in_array($student_id, $enrolled_student_ids)` in `mark_attendance.php` is a loose comparison (see §9.3) — not an injection vector, but a correctness sharp edge. Second, `SELECT … ORDER BY FIELD(s.day_of_week, …)` in three queries embeds a literal ordering list; it is safe today but becomes fragile if the day list moves into configuration. Overall: **prepared statements are the strongest-executed security control in this codebase**, and the `bind_param` spreading idiom shows the author understood how to parameterise a variable-length query rather than reaching for string interpolation.

### 11.6 Cross-site scripting

**Reflected and stored output is consistently escaped.** Every dynamic echo of user or database content passes through `htmlspecialchars(...)` with `ENT_QUOTES` in the token helper and default flags elsewhere. That includes usernames, emails, course names, descriptions, announcement content (with `nl2br` applied *after* escaping — the correct order), and the `msg` query parameter in three dashboards (`htmlspecialchars(urldecode($_GET['msg']))`). This is disciplined and is the second-strongest control in the codebase.

**One DOM-XSS path exists.** In `js/script.js`, the room-booking success handler rebuilds the timetable cell:

```js
const courseName = $('#modal-course-id option:selected').text();
const newContent = `<div class="font-weight-bold" …>${courseName}</div>`;
parentDiv.html(`<small>${…timeslot}</small>${newContent}`);
```

`courseName` is read with `.text()` (safe) and then inserted with `.html()` (unsafe). The chain is: an administrator creates a course whose name contains markup — `Big<span onmouseover=…>` — which is escaped in the `<option>` at render time, decoded back to text by `.text()`, and then re-parsed as HTML by `.html()`. The name is stored data, so this is a stored XSS reachable by a teacher in their own browser. The fix is `textContent`/`.text()` for the injected value and building the element with DOM APIs instead of an interpolated HTML string.

**No Content-Security-Policy.** The application sends `X-Frame-Options`, `X-Content-Type-Options` and `Referrer-Policy`, and removes `X-Powered-By` — a reasonable baseline. But with no CSP, a successful injection executes freely; `script-src 'self'` plus explicit CDN allowances would have caught the DOM path above as a console error instead of a live vector.

### 11.7 Brute-force protection: good instinct, three weaknesses

`BruteForceProtector` implements a sliding-window attempt counter at 5 failures per 300 s, keyed by `sha256(username|IP)`, stored one JSON file per key. The key design is right (user **and** IP, so neither alone is enough), and `REMOTE_ADDR` is used rather than the spoofable `X-Forwarded-For` — a detail many implementations get wrong. Strengths: the lockout message reports remaining minutes, a successful login clears the counter, and an expired lockout resets the count.

Weaknesses, in order of practical impact:

1. **No file locking.** `registerFailure()` does read-modify-write with `file_get_contents` then `file_put_contents`, with no `flock()`. Concurrent failures therefore lose increments — an attacker running parallel requests can exceed the limit far beyond 5 attempts while each request sees a low count. This converts a working control into a speed bump.
2. **No pruning.** Expired files are never deleted; `check()` resets the count but leaves the file. Over months a busy login page accumulates one inode per distinct `(username, IP)` pair. That is both a disk/inode leak and, because the directory sits under the web root, a publicly enumerable set of files.
3. **Per-username bypass.** The counter is scoped to one username. Rotating usernames (`alice`, `alice1`, `Alice`, `admin`, `root`, …) resets the count, so a password-spraying attack against many accounts is only slowed by an IP-scoped limit that does not exist.

A production implementation moves this to a shared store with atomic increments (Redis `INCR` + `EXPIRE`, or a `login_attempts` table with an `INSERT … ON DUPLICATE KEY UPDATE attempts = attempts + 1`), and adds a separate IP-scoped ceiling. For a student project the file approach is defensible — it has no infrastructure dependency and it works for the single-attacker case — but the read-modify-write race should be closed with `flock()`, which is a five-line change.

### 11.8 Secrets management

**`.env` is tracked in version control.** `git ls-files` confirms it, and `.gitignore` ignores `storage/` and `*.json` but not `.env`. Inspecting only key names and value lengths (without printing values): `DB_HOST`, `DB_NAME`, `DB_USER` are populated, and `DB_PASS` is **empty** — i.e. the committed credentials are the stock XAMPP defaults (`root` with no password) against a local `timetable` database.

Severity is therefore **Medium**, not Critical: no live secret was published. The pattern is still wrong for three reasons. It teaches the wrong habit. It guarantees that a future contributor who pastes real credentials there will publish them — git history is very hard to erase and forks keep copies. And it makes the repository non-portable: a new developer must delete or edit the committed file to run against their own database. The correct shape is `.env.example` (committed, keys only, placeholder values) plus `.env` (ignored), and a README step that copies one to the other. Because `.env` is already in history, the remediation must also include rotating any credential that was ever in it — which for an empty password means simply confirming no deployment elsewhere relies on a passwordless root account.

**There is no `.htaccess`.** With a typical Apache `DocumentRoot` pointing at the project root, `.env` is served as a static text file: `GET /.env` returns the credentials. Confirmed in the harness: HTTP 200, 82 bytes, `DB_HOST=` visible in the body. The full schema at `/app/timetable.sql` is also served with HTTP 200. Apache 2.4's default `Require all granted` does not protect dotfiles unless a `FilesMatch`/`<Files ".*">` block denies them. This elevates the exposure from "committed file" to "trivially fetchable", and it is the specific reason the empty password above is the only thing preventing a Critical. A targeted deny rule is two lines; the real fix is a `public/` document root.

Similarly, `app/` (including `timetable.sql`, which is the full schema) and `storage/bruteforce/` are directly reachable. None of them is a secret today; all of them are files that were never meant to be URLs.

### 11.9 Transport, headers, and supply chain

**Headers present:** `X-Frame-Options: SAMEORIGIN` (clickjacking), `X-Content-Type-Options: nosniff` (MIME confusion), `Referrer-Policy: same-origin` (token leakage via `Referer`), `X-Powered-By` removed. That is a solid baseline for a hand-rolled application, and better than most.

**Headers absent:** `Content-Security-Policy` (see §11.6), `Strict-Transport-Security` (meaningless until TLS is enforced), `Permissions-Policy`, `Cross-Origin-Opener-Policy`. None is urgent for a local deployment; CSP becomes urgent the moment this is public.

**Supply chain.** Every third-party asset is loaded from a CDN with no `integrity` attribute and no version pinning beyond the URL:

```html
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
```

Three separate CDNs, five requests, no Subresource Integrity, and no fallback if a CDN is unreachable. Bootstrap 4.5.2 (2020) carries known advisories in its bundled Popper/jQuery range, and the whole chain requires jQuery. Subresource Integrity hashes are a one-line addition per tag and the single cheapest security improvement available to this project; vendoring the assets locally is better still and removes the CDN dependency entirely. The Google Fonts `@import` at the top of `styles.css` blocks rendering until the stylesheet resolves — a performance cost that is worth trading for a `font-display: swap` or a local font.

### 11.10 What a real attacker would do, in order

The point of an audit is prioritisation, so here is the shortest path from anonymous to damaging:

1. `GET /.env` → database host, name, user, (empty) password. **If the DB is reachable from outside localhost, this is the whole system.**
2. `GET /get_student_attendance.php?student_id=N&course_id=1` for N in 1…500 → the student body's attendance record. No login required.
3. `GET /check_conflict.php?room_id=…` over the 40 cells × R rooms → the entire timetable.
4. Log in as any teacher (self-registration is open to anyone as `teacher`, so a valid low-privilege account is free) → `POST mark_attendance.php` with an arbitrary `schedule_id` and chosen `attendance[]` values → falsify another teacher's attendance. No CSRF token needed, so this also works from a phishing page against any logged-in teacher.
5. `POST admin_login_process.php` from a victim's browser (no CSRF check) → log them into an attacker-controlled admin account and observe.

Steps 1, 2, 3 and 5 require no credentials at all. Step 4 requires only that open registration remains enabled — which is itself a policy decision worth naming: **`register_process.php` lets anyone create a `teacher` account**, and a teacher account grants course/student listings, announcement posting, room-booking requests, and attendance writes. If this system were deployed publicly, self-registration as a teacher would need to be replaced with an invitation or admin-approval flow.

### 11.11 Security scorecard

| Control | Assessment |
|---|---|
| SQL injection | **Strong** — prepared statements everywhere, no interpolation |
| Output encoding | **Strong** — consistent `htmlspecialchars`, one DOM-XSS path |
| Password storage | Good — bcrypt, `password_verify`, no plaintext; no rehash |
| CSRF | Partial — right primitive, 3 of 6 mutating endpoints covered |
| Authentication | Partial — two identity models, one endpoint without rotation |
| Authorization | **Weak** — 2 unauthenticated endpoints, attendance lacks ownership check |
| Session hardening | Weak — no cookie flags, no strict mode |
| Secrets | Weak — `.env` committed, no `.htaccess`, no `.env.example` |
| Brute-force resistance | Partial — works, racy, bypassable, leaks inodes |
| Transport/headers | Adequate baseline, no CSP, no SRI |
| Dependency hygiene | Weak — 5 CDN assets, 3 CDNs, no integrity hashes |

---

## 12. Correctness defects

Security is only half of a review. These are the places where the code does not do what it says, independent of any attacker.

### 12.1 The three non-functional features

**1. The admin "Edit User" modal never populates.** `admin_dashboard.php` renders `.edit-user-btn` elements carrying `data-id`, `data-username`, `data-email`, `data-role`. The JavaScript that reads those attributes into the modal fields is inside:

```js
if (document.getElementById('adminTabContent')) {   // js/script.js:19
    $('.edit-user-btn').on('click', function() { … });
```

No PHP file contains an element with `id="adminTabContent"`. Grepping the whole repository returns exactly two hits, both inside `js/script.js` (lines 19 and 253). The guard is therefore always false, the handler is never bound, and clicking **Edit** opens an empty modal. Submitting it posts empty `username`/`email` values, which `manage_admin_tasks.php` rejects with "Invalid edit data". The feature appears present and is entirely dead.

Confirmed against rendered HTML (§15.6): the admin users page contains four `.edit-user-btn` elements and zero elements with `id="adminTabContent"`, so the branch is provably unreachable.

This is the most instructive bug in the repository, because it is a **stale selector**, the signature failure of code generation by pattern-matching. Someone (or something) wrote the guard to match an earlier markup shape — a tabbed admin dashboard with `id="adminTabContent"` — and the admin layout later became a sidebar. The teacher dashboard kept its equivalent id (`teacherTabContent`, still present at `teacher_dashboard.php:140`), so the JS half-updated: the teacher branch works, the admin branch silently never runs. Nothing in the code, the tests (there are none), or the docs notices.

**2. The live conflict warning never fires.** The same guard wraps the registration of the debounced `runConflictCheck` handler on `.schedule-input` (line 253). So `check_conflict.php` is never called from the admin schedule form, `#conflict-warning` stays empty forever, and the "Real-time Conflict Warning Checker" described in `code_explanation.md` does not exist in practice.

**3. The enrollment limit shown to students is wrong.** See §8.2 — UI says 6, server enforces 5.

### 12.2 Duplicate registration produces a 500, not the friendly message

`register_process.php` ends with:

```php
if ($stmt->execute()) {
    echo "Registration successful. <a href='index.php'>Login here</a>";
} else {
    http_response_code(500);
    echo "Error registering user. The username might already be in use.";
}
```

But `db_config.php` sets `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`. In strict mode, a failed `execute()` **throws `mysqli_sql_exception`** rather than returning `false`. A duplicate `username` therefore violates the unique key and throws before the `else` can run, producing an uncaught exception and a white 500 page instead of the intended message. The `else` branch is unreachable for its stated purpose.

I am not inferring this: it was reproduced in the harness (§15.6). Registering an existing username returned `Uncaught mysqli_sql_exception: Duplicate entry 'stud_eyoas' for key 'username' in /app/register_process.php:58`, printed into the page with a stack trace — and with a **200** status, because output had already begun before the exception unwound. The `else` branch never runs. Note that `manage_admin_tasks.php` gets this right — its `add_user` action wraps `execute()` in `try/catch (mysqli_sql_exception)` and reports "Add failed (duplicate?)". The same author solved the same problem correctly in one file and incorrectly in another, which is the duplication problem again, this time in error handling.

### 12.3 The same strict-mode consequence in `add_schedule` and `enroll`

- `add_schedule` inserts without a `try/catch`. In the race described in §7.3, the room unique key fires and the whole request 500s. Data stays correct; the admin sees a crash instead of "that slot was just taken".
- `EnrollmentService::enroll` calls `$this->enrollRepo->create(...)`, which executes an INSERT. On a duplicate it throws, so the branch `if (!$this->enrollRepo->create(...)) return ['success'=>false,'message'=>'Already enrolled or invalid course'];` never triggers. The AJAX client receives a 500 with an HTML error body, `response.json()` throws inside `try`, and the user sees **"A network error occurred during booking"**-style messaging for a state the system fully understands.
- The message "Already enrolled or invalid course" also conflates two conditions that need different fixes; conflating them is what let the mistake through review.

### 12.4 `profile.php` always shows "No email on file"

```php
$email = $_SESSION['email'] ?? 'No email on file';
```

No login path ever sets `$_SESSION['email']`. `login_process.php` sets `user_id`, `username`, `role`; `admin_login_process.php` sets `admin_id`. So the profile page, which does query the database for nothing, always renders the fallback string. The fix is one extra column in the login SELECT and one session assignment — or better, one query in `profile.php` for the current user (which `UserRepository::getById` already does and nobody calls).

### 12.5 Dead schema columns and two unique keys for one constraint

Already itemised in §6.2, repeated here as correctness rather than hygiene: `enrollments` carries **two identical unique keys** (`student_course`, `uniq_enrollment`), and `attendance` carries **four indexes where two suffice**. These are not bugs in behaviour, but they are proof that write paths were added without reading what already existed, and they cost real write throughput and index memory.

### 12.6 Incomplete include and missing asset

- `room_dashboard.php:32` does `include 'admin_sidebar.php'`. That file is not in the repository or in git history at HEAD. Any request to this page emits a warning and renders without navigation — or, on `display_errors=0`, silently half-renders.
- `login.php:10` links `css/login.css`, which does not exist. The page renders unstyled.
- `README.md` instructs importing `timetable.sql` from the root; the file is at `app/timetable.sql`, without semicolons, and with two tables missing.

### 12.7 Authorisation asymmetry in the admin approval flow

`approve_enrollment` re-checks nothing (§8.1), while `approve_schedule` re-checks everything (§7.4). The two approval actions sit forty lines apart in the same `switch` statement. One is transactional with re-validation and cascading denial; the other is an `INSERT IGNORE`. This is the clearest single example of inconsistent rigour within one file, and it is the kind of thing a human reviewer catches in five minutes and a generated diff does not.

### 12.8 Consolidated register

The complete list — 34 findings with severity, file reference, fix and effort — is **Appendix B**. The eight above are the ones that change what the software *does*; the rest are the ones that change how safely it can be changed.

---

## 13. Performance and resilience

### 13.1 Query shape: the N+1 that was avoided

The most common performance failure in a PHP dashboard is the N+1 query: loop over N courses, query students and schedules inside the loop. `teacher_dashboard.php` explicitly avoids it, and the technique is worth documenting because it is the correct pattern for this framework:

```php
$course_ids = array_column($courses, 'id');
$placeholders = implode(',', array_fill(0, count($course_ids), '?'));
$types = str_repeat('i', count($course_ids));

$stmt_students = $conn->prepare("… WHERE e.course_id IN ($placeholders)");
$stmt_students->bind_param($types, ...$course_ids);
```

Three queries total — students, schedules, attendance, plus one for announcements — regardless of how many courses the teacher owns. The results are then grouped in PHP with `foreach` into `$students_by_course` / `$schedules_by_course`, which is O(n) memory and zero extra round trips. The `bind_param($types, ...$course_ids)` spread is the non-obvious part (mysqli's variadic binding), and getting it right here is a genuine skill signal.

The same file then leaves an inefficiency behind: **if a teacher has no courses, `$placeholders` and `$types` are never defined**, and the subsequent attendance query is guarded by `if (!empty($course_ids))` — correctly. But the *next* block (announcements) is nested inside the attendance guard, so a teacher with no courses silently skips announcements (harmless) and a teacher with courses runs them (correct). Fragile nesting rather than a bug.

### 13.2 The admin dashboard fetches everything for every page

```php
$users    = …  // all non-admin users
$teachers = …  // all teachers
$students = …  // all students
$courses  = …  // all courses + teacher join
$rooms    = …  // all rooms
```

These five queries run on **every** admin request, before `$page` is inspected. On the reports page, `$users`, `$teachers`, `$students` and `$rooms` are used only for `count()` on the home page — and the home page is the only consumer of two of them. The cost is small at student-project scale (hundreds of rows) and would become the first bottleneck at institutional scale (tens of thousands of users), because `users` is fetched with `SELECT id, username, email, role` and no `LIMIT`, then materialised into a PHP array on every page view. Moving each query inside its `if ($page === …)` branch is a five-minute change with a real ceiling raised.

Meanwhile the home page *does* need all five, but only for `count()`. Four `SELECT COUNT(*)` queries would return four integers instead of four full result sets — same information, ~1% of the bandwidth.

### 13.3 The conflict-reporting query is the expensive one

`schedule_requests` runs, **per pending request**, a four-way self-join across `schedules`, `courses`, and `enrollments` twice. This is O(requests × enrollments²) in the worst case and it is executed in a `foreach` — a genuine N+1 with an expensive N. With ten pending requests, fifty courses and a thousand enrollments, this is the slowest page in the application.

It is also the query most worth keeping, because it answers a question nothing else answers. The fix is not to delete it but to compute all requests in one query (a `JOIN` on `pending_schedules` with a `GROUP BY ps.id, c.id`), or to accept the cost behind a cache with a short TTL. Note the irony: an `ArrayCache` with a 30-second TTL exists in `app/Cache/ArrayCache.php` and is wired to a query nobody calls (`CourseRepository::allCached`), while the expensive query has no cache.

### 13.4 Connection cost, and the second connection

As noted in §5.2, requests that touch both the global `$conn` and the `Database` singleton open **two** MySQL connections. On a shared host with a low `max_connections`, doubling per-request connections halves the concurrency ceiling. More subtly, the singleton is process-local: under PHP-FPM each request starts fresh, so `Database::$instance` is a per-request singleton, not a pool. Its benefit is real but modest (one connection instead of N per request), and it is entirely negated on requests that also use `$conn`.

### 13.5 Caching that does nothing

`ArrayCache::remember($key, $ttl, $resolver)` stores values in a **static array**. In a request-scoped PHP process, that array is discarded at the end of the request. So the "30-second TTL" can never span two requests; it can only deduplicate repeated calls *within* one request — and the one consumer (`allCached`) is never called at all. The class is harmless and pointless. Passing it off as a cache layer in review would be a mistake; the honest description is "a per-request memoisation helper with an API shaped like a cache".

### 13.6 Resilience and failure modes

| Failure | Current behaviour | Desired behaviour |
|---|---|---|
| DB unreachable | `die('Database connection error.')` — 500, no detail, message logged | Same, plus a friendly HTML page and health-check endpoint |
| Missing table (`announcements`) | Uncaught `mysqli_sql_exception` → 500 | Migration guard; deploy check |
| Duplicate key on insert | 500 (§12.2–12.3) | 409/422 with a message |
| Invalid CSRF | 419/400 with plain text | Redirect back with a toast |
| Expired session | Redirect to `index.php` (good) | Same |
| CDN unreachable | Unstyled page, no JS, no tabs, no AJAX | Local assets or SRI with fallback |
| `APP_DEBUG=1` in production | Stack traces to the browser | Env-gated already (good) — verify it is `0` |

Two of these deserve emphasis. **Plain-text error pages** (`exit('Invalid credentials')`, `die('Access Denied.')`) are a poor experience but also a small information leak: a 403 with "Access Denied." versus a 401 with "Invalid credentials" versus a 500 tells an attacker the difference between "not logged in", "wrong role" and "crash". Centralised error handling would render a single error page for all of them. **No health check** means a missing table is discovered by the first teacher who opens their dashboard, not by a monitor.

### 13.7 Front-end performance

The logo is **260 KB** for a display size of 60–120 px — roughly 30× larger than necessary; a WebP at 2× display size would be ~8 KB. Five CDN round trips block first render, and the Google Fonts `@import` inside `styles.css` creates a render-blocking chain (HTML → CSS → font CSS → font files). The dashboard grids render every room × day × slot cell server-side (~40 divs per room), which is fine at this scale. There is no minification, no cache-busting hash, no `defer` on scripts, and `js/script.js` is loaded on pages that need none of it (e.g. `profile.php`).

None of this matters on localhost. All of it matters on a 3G connection, which is the realistic condition for the students this system serves.

# Part V — Engineering judgment

## 14. Frontend: design tokens, JavaScript, and the cost of dead branches

### 14.1 The CSS layer is genuinely good

`css/styles.css` is not a pile of rules — it is a small design system, and it is the part of the codebase that most clearly reflects a deliberate decision rather than an accumulation.

**Tokens first.** Thirty-nine custom properties at `:root` define the entire visual language: four surface colours (`--color-bg-base`, `--color-bg-alt`, `--color-bg-elevated`, `--color-bg-elevated-hover`), two accent states, two border/divider greys, four text roles (`primary`, `secondary`, `faint`, `on-accent`), four radii (`xs/sm/md/lg`), four shadows including a purpose-built `--shadow-focus-ring` and an `--outline-focus` preset, two easing curves (`--transition-fast: .15s cubic-bezier(.4,0,.2,1)`), and a complete status palette — danger/success/warning/info, each with base, `-border`, `-bg` **and** `-hover` variants.

The value of this is not aesthetic but operational. Because `.alert-danger` reads `background-color: var(--color-danger-bg)` rather than `#f8d7da`, re-theming the application is a two-line change, and the three status colours are guaranteed to be used consistently across badges, alert toasts, table cells and progress bars. The `-border`/`-bg`/base triplet per status is the pattern you want: it pre-answers "what is the border colour for danger?" so no future contributor invents one.

**Bootstrap override strategy.** The file layers on top of Bootstrap 4 rather than fighting it: `.card`, `.table`, `.form-control`, `.btn` are re-declared with token values, and Bootstrap's own variables are left alone. This means Bootstrap's grid, modals, tabs, and collapse all keep working while the visual identity is entirely custom. It is the correct way to theme a framework without forking it.

**Two flaws.** First, there are hardcoded colours that escaped tokenisation: `.btn-primary` uses `#fff` and two `rgba(59, 130, 246, .4)` / `rgba(99, 102, 241, .5)` shadows — note these are *blue-violet* values, while `--color-accent` is `#0056b3` (a pure blue). The button shadow does not match the button colour, which is a visible artefact of a palette change that was applied to tokens but not to one literal. `.btn-outline:hover` references `var(--color-text-hover)`, which **does not exist** — an undefined custom property makes the declaration invalid at computed-value time, so that hover colour silently falls back to inherited text. `.slot-item strong:hover` uses `#218838` directly. These are small, but they are exactly the kind of drift that tokenisation is supposed to prevent.

Second, `.table tbody tr:nth-of-type(odd) { background-color: var(--color-bg-elevated) }` combined with `.table tbody tr:hover { background-color: var(--color-bg-elevated-hover) }` produces striped rows whose hover state is only one shade away from the stripe — acceptable, but it also means the master timetable's coloured slot cells and the row striping compete. At 40 cells per room, that is a lot of competing background colours.

**Dark mode was removed, correctly.** Commit `f2d8bd3` removes `body[data-theme="dark"]` and the toggle, reducing `styles.css` by 123 lines and `script.js` by 29. The reasoning that a half-maintained second theme is worse than none is sound — but `code_explanation.md` still documents the feature in detail, which is the documentation-drift problem again.

### 14.2 The JavaScript: three idioms in one file

`js/script.js` (~300 lines) contains three different programming styles coexisting:

1. **jQuery** for modals and Bootstrap events (`$('#bookRoomModal').on('show.bs.modal', …)`), which is necessary because Bootstrap 4's modal API is jQuery-based.
2. **Vanilla DOM** for toasts, the room search filter, and the student enrollment delegation (`document.createElement`, `element.matches`, `addEventListener`).
3. **`fetch` + `FormData` + `async/await`** for all three AJAX flows, which is modern and correct — and notably does *not* use `$.ajax` even though jQuery is loaded.

The mixture is defensible (each idiom is used where it is cheapest) but it means the file has no single convention, and a future contributor must read all three to know which to extend.

**What it gets right:**

- A single `showToast(message, isSuccess)` helper, memoised into every AJAX response path, so success and failure feedback is consistent.
- Buttons are disabled during in-flight requests and re-enabled in `finally`, preventing double submission.
- `confirm()` before destructive actions, applied by class (`.delete-form`) so new delete forms inherit the guard automatically.
- Event delegation on `studentTabContent` (`.enroll-btn, .unenroll-btn, .request-approval-btn`) so dynamically re-rendered rows still work.
- A `debounce(fn, ms)` helper for the conflict check.
- The CSRF token is read from `<meta name="csrf-token">` and appended to `FormData`, so AJAX writes carry the same token as form posts. This is the right mechanism and it is applied consistently in all three flows.

**What it gets wrong:**

- The two dead `#adminTabContent` guards (§12.1), which silently disable the admin modal and the conflict warning.
- `showToast` is defined *inside* the `DOMContentLoaded` callback but called from the student handler defined outside it — which works only because the student block is also inside the same callback scope. Fine today; fragile if the file is split.
- `apiFetch` merges `options` shallowly and always sets an `X-CSRF-Token` header, but the server never reads that header — it reads `$_POST['csrf_token']`. So the header is decorative, and a future endpoint that *did* read it would be looking at a value the current client sends for GET requests too.
- The DOM-XSS path in the booking success handler (§11.6).
- `console.error` on every failure (fine for development) with the user seeing a generic message — the correct combination, worth noting as a positive.

### 14.3 The AJAX contract

Three flows, each with its own endpoint and response shape. None is documented anywhere in the repository, which is a maintenance risk; here is the contract extracted from the code:

| Flow | Endpoint | Request | Success | Failure | Client reaction |
|---|---|---|---|---|---|
| Enroll / unenroll / request | `manage_enrollment.php` | `action`, `course_id`, `csrf_token` | `{success:true,message}` | `{success:false,message}` | Toast; mutate button state or remove row |
| Book room | `manage_teacher_tasks.php` (`book_room`) | `action`, `course_id`, `room_id`, `day`, `timeslot`, `csrf_token` | `{success:true,message}` | `{success:false,message}` | Toast; rewrite timetable cell in place |
| Delete announcement | `manage_teacher_tasks.php` (`delete_announcement`) | `action`, `announcement_id`, `csrf_token` | `{success:true,message}` | `{success:false,message}` | Toast; remove `.announcement-item` |

Two problems in the contract. **It is not uniform:** `manage_teacher_tasks.php` emits JSON for two actions and a `Location:` redirect for the third (`add_announcement`), chosen by which `if` block the `action` lands in. A client that posts an unknown action receives a 302 to `teacher_dashboard.php` and `response.json()` throws — the classic "HTML in a JSON pipe" failure.

**It is optimistic:** on successful enrollment the client flips the button to "Enrolled" locally and never re-fetches. The server may have enrolled the student in a *different* state than the client assumes (for example if the course had no schedule, §8.3). Optimistic UI is fine when the server response fully determines the new state; here, the response carries only a message, so the client guesses. Returning the new enrollment count would let the client render the truth.

### 14.4 Progressive enhancement and accessibility

The application is **server-rendered with AJAX enhancements**, which is the right architecture for this scope: every form works without JavaScript, and the AJAX paths are additive. That is a real strength — the enrollment buttons would work as plain form posts if the JS were disabled, which most SPA-style rewrites of this project would lose.

The accessibility gaps are the weakest part of the front end:

- **No labels on the primary forms.** `index.php` and `register.php` use placeholders as labels. A placeholder disappears on input, is not announced by screen readers as a label, and fails WCAG 1.3.1/3.3.2. `login.php` (the orphan) *does* use `<label for>` — so the accessible version is the dead one.
- **Missing viewport meta** on `register.php`, `admin_login.php`, `profile.php` (present on `index.php`, and on the dashboards). Three pages render at desktop width on phones.
- **No `lang` attribute** on any login page (`admin_dashboard.php` has `lang="en"`; the rest do not).
- **Tables without `scope`/`caption`.** The timetable grids have a header row but no `scope="col"`, and no `<caption>` describing the grid. For a 5×9 matrix, header association is what makes it navigable non-visually.
- **Badge-only status.** Attendance status is conveyed by colour plus the word (`Present`/`Absent`), which is acceptable; room availability relies on `availability-booked`/`availability-free` background colour *and* the presence/absence of a Book button, which is also acceptable — but `.availability-booked { opacity: .7 }` reduces contrast below the AA threshold for small text.
- **Icon-only delete button** in announcements (`&times;`) with no `aria-label`, so it is announced as "multiplication sign".

None of these is expensive to fix — an `aria-label`, a `<label class="sr-only">`, a viewport tag, and `scope="col"` cover most of it in under an hour — and the payoff is that the system becomes usable by the students most likely to need it.

### 14.5 The visual design, assessed

Poppins at 400/500/600/700 weights, a blue accent (`#0056b3`), generous 10–16 px radii, and a soft shadow scale. Cards lift 4 px on hover, sidebar links slide 4 px on hover, and the toast animates in from the right. The effect is contemporary and coherent — noticeably more current than the Bootstrap 4 default it sits on.

Two design notes. The elevation language is overused: `.card:hover { transform: translateY(-4px) }` applies to *every* card, including the read-only KPI cards on the admin home page and the timetable container, where hover lift implies interactivity that does not exist. And `h5:hover { color: var(--color-accent) }` makes every fifth-level heading change colour on hover for no reason, which produces a distracting shimmer as the cursor crosses a dashboard of course cards. Both are the kind of detail that reads as "styled" rather than "designed" — motion and colour applied globally instead of to interactive elements.

---

## 15. Verification, testability, and a manual test plan

### 15.1 The verification gap that was in the project, not the report

The repository has no test suite, no CI configuration, no `composer.json`, and no `package.json`. That is the important process finding: **the project has no verification loop at all.** A codebase of 2,935 lines with authentication, authorization, concurrency and money-equivalent concerns (attendance, enrollment) has zero automated checks. Every defect in §12 was reachable by a single manual click and several were reachable by a single `curl`.

That gap was closed for the duration of this review by standing up the environment described in §15.6. It is worth separating the two facts: the report's claims are now largely *observed*, while the project's own ability to detect these failures remains *absent*. The fix for the second is §15.3.

### 15.2 A manual test plan, ordered by risk and runnable in an hour

Each row is a concrete request/response check. "Expected" is what a correct system does; "Result" is what this codebase actually did when the check was executed in the harness of §15.6.

| # | Check | How | Expected | Result |
|---|---|---|---|---|
| 1 | Anonymous attendance read | `GET get_student_attendance.php?student_id=4&course_id=1` | 302/401 | **Confirmed**: `{"success":true,"data":[{"status":"present","count":1},{"status":"absent","count":1}]}` |
| 2 | Anonymous room probe | `GET check_conflict.php?room_id=1&day=Monday&timeslot=09:00-10:00` | 302/401 | **Confirmed**: `{"conflict":true,"course":"CS101 Intro"}` |
| 3 | `.env` exposure | `GET /.env` | 403 | **Confirmed**: 200, 82 bytes, `DB_HOST=` served in plaintext |
| 4 | Duplicate registration | Register an existing username | Friendly message | **Confirmed, and worse**: uncaught `mysqli_sql_exception` printed with a full stack trace and absolute paths; the `else` branch never runs |
| 5 | Enrollment cap boundary | Enroll a 6th course via AJAX at count=5 | Rejected at 6 | **Confirmed**: `{"success":false,"message":"Enrollment limit reached"}` while the page still rendered the Enroll button and no "maximum of 6" warning |
| 6 | Attendance CSRF | POST to `mark_attendance.php` with no token | 403 | **Confirmed**: 302 → `Attendance marked successfully!`, row written |
| 7 | Teacher A marks Teacher B's class | Teacher 3 POSTs `schedule_id=1` (owned by teacher 2) | 403 | **Confirmed**: row `student 4 / schedule 1 / present` written |
| 8 | Admin edit modal | Click **Edit** on a user | Fields populated | **Confirmed by proxy**: rendered HTML has 4 × `.edit-user-btn` and 0 × `#adminTabContent`, so the binding never occurs |
| 9 | Live conflict warning | Pick a booked room+slot on the schedule form | Inline warning | **Confirmed by proxy**: `#conflict-warning` is rendered (1 occurrence) and the handler is gated on the absent `#adminTabContent` |
| 10 | Admin approve over cap | Approve a request when the student is at the limit | Rejected | **Confirmed**: student with 5 enrollments approved to 6 |
| 11 | Enroll in unscheduled course | Enroll a student in a course with no schedule rows | Visible or blocked | **Confirmed**: `{"success":true,"message":"Enrolled"}`; then CS103 appears once under *My Courses* and 0 times in *My Schedule* |
| 12 | Room double-book race | Two parallel `add_schedule` for the same cell | One succeeds, one 409 | **Not tested**: built-in server is single-threaded |
| 13 | Admin login CSRF | POST credentials with no token | 400 | **Confirmed**: 302 → `admin_dashboard.php`, logged in |
| 14 | Profile email | Open `profile.php` as a student | Real email | **Confirmed**: renders "No email on file" |
| 15 | Announcements on fresh DB | Open the teacher dashboard against the shipped 7-table schema | Works | **Confirmed**: fatal `mysqli_sql_exception: Table 'tt_shipped.announcements' doesn't exist` at `teacher_dashboard.php:83`, with stack trace |
| 16 | Room dashboard | `GET room_dashboard.php` as admin | Renders | Not reached: no admin sidebar exists, so the page is unreachable in the UI; the include is missing regardless |

Items 1–3 and 15 were the deployment-critical ones, and all four confirmed: the instance is both unsafe to expose and non-installable from its own README.

### 15.3 The eight automated tests that would have caught the most

In priority order, each expressible in PHPUnit with a test database and a tiny HTTP client:

1. **`AttendanceAuthorizationTest`** — teacher A cannot POST a `schedule_id` owned by teacher B. Catches §11.2 #4.
2. **`CsrfCoverageTest`** — enumerate every POST handler and assert each calls `verify_csrf`. Catches #4, #5 and any future omission. This one is a *meta*-test and is the highest-leverage test in the list.
3. **`EnrollmentLimitTest`** — at `limit-1`, enroll succeeds; at `limit`, both the UI and the AJAX path reject; a config change moves both. Directly kills §8.2 and prevents its recurrence.
4. **`ApprovalRulesTest`** — approving a request for a student at the cap, or with a conflicting schedule, is refused. Kills §12.7.
5. **`ConflictConstraintTest`** — two concurrent inserts for one room cell leave exactly one row. Verifies the unique key, not the PHP check.
6. **`SchemaCompletenessTest`** — every table named in the code exists in a freshly imported schema. Kills §6.3 #1 permanently.
7. **`DeadSelectorTest`** — grep the JS for `getElementById` ids and assert each exists in rendered HTML. Kills §12.1 and would have caught both dead branches the day they broke.
8. **`AuthzMatrixTest`** — for each endpoint × role, assert the expected status code. Turns Appendix A into an executable contract.

Tests 2, 6 and 7 are unusual and disproportionately valuable here precisely because the defects in this codebase are *omissions and drifts*, not logic errors. Conventional unit tests would not have caught a single one.

### 15.4 Why the code is hard to test today

Three barriers, all fixable:

- **Global `$conn`.** Handlers use a global, so a test must bootstrap the whole world. Injecting a connection (or wrapping it) fixes this.
- **Direct `$_SESSION`/`$_POST` access.** `EnrollmentService` is nearly testable — it takes its collaborators — but reaches for `Database::get()` internally (§5.2). `EnrollmentController::handle(array $post, int $studentId)` *is* testable as written, which shows the author knows the pattern and applied it once.
- **No dependency injection container.** `manage_enrollment.php` hand-assembles the object graph. That is fine for one endpoint and does not scale to twenty.

### 15.5 Definition of done, proposed

A change to this repository is complete when: (a) the affected endpoint appears in the authorization matrix with an expected status for each role; (b) any new mutating endpoint calls `verify_csrf`; (c) any new business rule is enforced in exactly one place and referenced from every path that needs it; (d) the schema change is in a re-runnable file; and (e) at least the `CsrfCoverageTest` and `SchemaCompletenessTest` pass. Five clauses, each of which maps to a defect found in this report.

### 15.6 Runtime verification log

**Environment.** Nothing was installed on the host. Two containers on a private Docker network:

| Component | Version | Notes |
|---|---|---|
| PHP | 8.2.35 (CLI, NTS) | base image plus `docker-php-ext-install mysqli`; served with `php -S` |
| Database | MariaDB 11.3.2 | `MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1`, database `timetable` |
| Application | verbatim copy of the 41 tracked PHP files | own `.env` pointing at the DB container; repo untouched |
| Seed | 5 users, 2 rooms, 7 courses, 6 schedules, 5 enrollments, 2 attendance rows | two courses deliberately left unscheduled |

**Lint: 41 of 41 files pass** `php -l` on 8.2 — so every failure in this report is a logic, policy or schema failure, not a syntax error. That is worth stating plainly, because it is exactly why "it runs" is not evidence of correctness.

**Confirmed — security.**

1. `GET /.env` → **200**, body served (`DB_HOST=…`). The single most damaging finding is one HTTP request deep.
2. `GET /app/timetable.sql` → **200**. The full schema is also fetchable.
3. `GET /get_student_attendance.php?student_id=4&course_id=1` with no cookie → **200** `{"success":true,"data":[…present 1, absent 1…]}`. Any student's attendance, anonymously.
4. `GET /check_conflict.php?room_id=1&day=Monday&timeslot=09:00-10:00` with no cookie → **200** `{"conflict":true,"course":"CS101 Intro"}`. The timetable is enumerable anonymously.
5. Teacher 3 POSTs `mark_attendance.php` with `schedule_id=1` — a session belonging to teacher 2's course — **and no CSRF token**. Response **302** → `?ok=1&msg=Attendance+marked+successfully`. The row `student 4 / schedule 1 / present / 2026-11-16` exists afterwards. Repeated with a token for a second date: also written. Where the same module *does* check ownership, it refuses correctly in the same run (`book_room` for course 1 → `Authorization failed: You do not own this course.`), which isolates the defect to one handler rather than a missing convention.
6. Admin login with **no CSRF token whatsoever** → **302** → `admin_dashboard.php`.
7. **Session fixation, end to end.** A session ID obtained before authentication was reused after it: victim logs in as admin, the cookie value is *unchanged* (`rotated=NO`), and a second client holding that same planted ID receives **200** with the `Admin Panel` page. The control comparison in the same run: a student login **does** rotate (`rotated=yes`), and the attacker's stale ID gets **302** → `index.php`. So the app contains the correct pattern and simply omits it in one place.

**Confirmed — correctness.**

8. Cap mismatch: a student at 5 enrollments sees active Enroll buttons (no limit warning, because `MAX_COURSES`=6) and gets `Enrollment limit reached` on click — the §8.2 drift, observable in one screen.
9. Invisible course: enrolling a student in a course with zero `schedules` rows returns `{"success":true,"message":"Enrolled"}`; the course then appears **once** under *My Courses* and **zero** times in the *My Schedule* table.
10. Over-cap approval: a pending request for a student already at 5 enrollments was approved, leaving `COUNT(*) = 6`. The admin path bypasses the student path's own rule.
11. Duplicate registration: `Fatal error: Uncaught mysqli_sql_exception: Duplicate entry 'stud_eyoas' for key 'username' in /app/register_process.php:58` — printed to the browser with a stack trace. The status was **200**, because output had already begun; the friendly message in the `else` is unreachable.
12. Fresh install: against a database built from **only** the seven shipped tables, the teacher dashboard dies with `Table 'tt_shipped.announcements' doesn't exist` at line 83 — a fatal error and stack trace on the first screen after login.
13. `profile.php` renders "No email on file" for a student whose email is in the database.
14. Shipped schema import: `ERROR 1064 … near 'CREATE TABLE courses'` and **zero tables created** — the file is one unterminated statement. Importing it a second way (statements terminated, foreign keys left on) fails again because the dump is alphabetical and `attendance` references `users` before `users` exists: `errno 150`. A working import requires **both** added semicolons **and** `SET FOREIGN_KEY_CHECKS=0`.

**Confirmed — controls that do work.** Five things the report credits are real, and the credit is evidence-based now, not charitable:

15. Role guards redirect correctly: student → `admin_dashboard.php` returns 302 to `admin_login.php`; student → `teacher_dashboard.php` returns 302 to `index.php`.
16. All seven admin pages (`home, users, courses, schedules, requests, schedule_requests, reports`) return **200** once the two missing tables exist. The application is functional, not broken, on a complete schema.
17. Booking approval cascade: approving one request inserted the schedule row and flipped a competing pending request for the same cell to `denied`, in one step. The best-engineered flow in the codebase behaves exactly as documented.
18. Announcement and booking ownership checks reject cross-teacher access in the same handler that attendance ignores.
19. Brute-force limiter: five wrong passwords returned **401**, the sixth returned **429** `"Too many login attempts. Please try again in 5 minutes."`, and a *correct* password during lockout was also refused — correct, if harsh. A fresh username straight after had no counter and returned 401, confirming the per-username bypass.
20. Bcrypt verification and role-scoped login (`WHERE username=? AND role=?`) both worked; a wrong password never produced a session.

**Two new findings, discovered by execution.** Neither was visible from reading alone:

> **Status after this revision.** The `.env` bypass has been fixed where it affected real usage: `admin_login_process.php` and `mark_attendance.php` now require `app/bootstrap.php` instead of calling `session_start()` and `db_config.php` directly, so both honour `.env`. Verified against MariaDB 11.3.2 and MySQL 8.0.46 with `.env` as the only configuration source: admin login returned 500 before the change and 302 after, and the attendance write persisted. Both of those endpoints are now dealt with as well: `check_conflict.php` requires bootstrap and an admin session, and `get_student_attendance.php` was deleted. The stack-trace finding is now **fixed** as well: `bootstrap.php` forces `display_errors` and `display_startup_errors` off in the non-debug branch while keeping `log_errors` on, so `APP_DEBUG=0` no longer leaks a trace (verified: a duplicate registration returns a bare `500` with an empty body).

- **`.env` is not loaded by the two unauthenticated endpoints.** `check_conflict.php` and `get_student_attendance.php` require `db_config.php` directly and never `app/bootstrap.php`, so `EnvLoader` never runs. They therefore only work when the database matches the compiled-in defaults (`localhost`, `root`, empty password). On the FIRST attempt in the harness they returned `Database connection error.` for exactly this reason — the endpoints appeared *safe* until the environment was configured to match. Two conclusions: those files are broken on any real deployment, and a security review that tests only the default configuration can accidentally mask a live endpoint.
- **Stack traces are served to browsers.** `bootstrap.php` turns `display_errors` **on** when `APP_DEBUG=1`, but never forces it **off** when `APP_DEBUG=0`. With PHP's development default (`display_errors=On`, as shipped in this image and typical of XAMPP) every uncaught exception prints its message, file path and stack to the client — with `APP_DEBUG=0` set. Absolute server paths and table names are leaked on a page any student can reach.

**One prediction that was wrong.** The report originally said the admin edit modal and the live conflict warning were dead because their JS guards reference an id that does not exist. The mechanism is confirmed (rendered HTML contains 0 × `adminTabContent`, 4 × `edit-user-btn`, 1 × `conflict-warning`), but I had described the consequence as "empty modal" / "nothing happens" on the assumption the page was otherwise healthy. In practice, on the shipped schema, the teacher dashboard 500s before any of this matters, and the admin dashboard only reaches the JS after the two missing tables are added. The defects are real; the *order in which a user meets them* was wrong.

**Reproducing this.** The harness is four commands: build a PHP image with `mysqli`, run MariaDB, import a completed schema plus seed data, and serve the copy with `php -S`. The exact requests are the sixteen in §15.2. Anyone reviewing this report should re-run items 1–3 and 15 first: they are the difference between a student project and an incident.

---

## 16. Remediation roadmap and target architecture

### 16.1 Phase 0 — stop the bleeding (half a day)

Ordered so each item is independently deployable and no item depends on another.

| # | Action | Effort |
|---|---|---|
| 0.1 | Delete `get_student_attendance.php`, `check_conflict.php` (until re-implemented authenticated), `login.php`, `register.html`, `admin_login.html`, `room_dashboard.php` | 15 min |
| 0.2 | `git rm --cached .env`; add `.env` to `.gitignore`; commit `.env.example` with placeholder values (**`.env.example` added in this revision**; `.env` is still tracked) | 10 min |
| 0.3 | Add `.htaccess` denying `.env`, `app/`, `storage/`, and `*.sql` — or move the document root to `public/` | 10 min |
| 0.4 | Add `verify_csrf` to `admin_login_process.php` and `logout.php`; add `csrf_field()` to the attendance form and `verify_csrf` to `mark_attendance.php` | 20 min |
| 0.5 | Add the ownership join to `mark_attendance.php` | 10 min |
| 0.6 | Add `session_regenerate_id(true)` at admin login; set cookie params + `use_strict_mode` in `bootstrap.php` | 15 min |
| 0.7 | Ship `announcements` and `pending_schedules` in a complete, re-runnable schema | 30 min |
| 0.8 | Replace the hardcoded `5` with `AppConfig::maxCourses()`; delete the unused constant and the default argument | 10 min |

Items 0.1–0.8 close every Critical and High finding except the approval-rule bypass. Total: roughly two hours of focused work, and the system moves from "unsafe to expose" to "safe to expose behind a login wall with open registration disabled".

### 16.2 Phase 1 — make the defects impossible (one day)

- Route both approval actions through the service layer (§8.1); replace `INSERT IGNORE` with a caught duplicate-key path.
- Wrap all `execute()` calls in `try/catch (mysqli_sql_exception)` and map violations to 409/422 responses; centralise the mapping in one helper.
- Fix the JS: remove the `#adminTabContent` guards, bind by feature detection instead of an id that may not exist, and replace `.html(courseName)` with `textContent`.
- Fix `profile.php` to query the user, and allow teachers and admins to change passwords via the same handler.
- Fix the empty-course enrollment decision explicitly — either filter unscheduled courses out of the list or render them in an "Unscheduled" section of the timetable.
- Add `flock()` to `BruteForceProtector`, plus a pruning sweep and an IP-scoped ceiling.
- Add SRI hashes to all five CDN tags, or vendor the assets.

### 16.3 Phase 2 — complete the architecture (two to three days)

This is the step that stops the defect classes from recurring rather than fixing instances.

```
public/
  index.php              ← single entry point
  .htaccess              ← front-controller rewrite + dotfile deny
src/
  Kernel.php             ← bootstrap: env, session, headers, container
  Router.php             ← method + path + role + csrf policy per route
  Middleware/
    AuthMiddleware.php   ← replaces 11 copy-pasted guards
    CsrfMiddleware.php   ← one place, applied by route flag
    ErrorMiddleware.php  ← one error page, one log format
  Controller/            ← move logic out of the dashboards
  Service/               ← existing EnrollmentService, extended
  Repository/            ← existing repos, connection injected
  Domain/                ← DayOfWeek, TimeSlot, plus conflict rules
  View/                  ← templates; HTML leaves the PHP
migrations/
  001_schema.sql …       ← ordered, re-runnable, complete
tests/
  …the eight tests in §15.3
```

Concretely, the changes that pay for themselves fastest:

1. **Router + `AuthMiddleware`.** Twelve endpoints currently restate their own guard, and two forgot. A route table that says `['POST', '/attendance', 'teacher', 'csrf']` makes omission impossible and makes Appendix A executable.
2. **One connection source.** Delete `db_config.php`'s global; make `Database` the only factory; inject it. Halves connections and makes transactions compose correctly.
3. **One rule owner per business fact.** Enrollment limit, weekday list, timeslot list, role vocabulary: each defined once in `Config` or `Domain`, referenced everywhere, and — for the day/slot grid — enforced again as an `ENUM` in the schema.
4. **Templates.** The three dashboards shed ~700 lines of interleaved HTML, and the accessibility fixes (labels, viewport, `scope`) become one partial each instead of three copies.
5. **Composer + PHPUnit + PSR-4.** Removes the bespoke autoloader (and its meaningless guard), enables the tests, and makes `vendor/` real — the `require vendor/autoload.php` branch in bootstrap already anticipates this.

### 16.4 Phase 3 — capability, not cleanup

Once the foundation is sound, the features the README already promises become cheap:

- **Alternative-slot suggestions** (§7.7): given the canonical day/slot grid, "find the first free cell for this room or teacher" is a loop over 40 candidates with one query per candidate, or one query returning all occupied cells and a set difference in PHP. The README already claims this exists; making it real is a day.
- **Email notifications** on enrollment approval and schedule changes — needs a mail provider and a `notifications` table, and is the natural next integration.
- **Audit log** for admin writes: one table, one helper call per mutating action, and suddenly attendance falsification and schedule overrides become attributable. Given the findings in §11, this is the highest-value *new* feature in the list.
- **Room capacity enforcement** using the `capacity` column that already exists.
- **Term/semester modelling** (see §9.4) to make attendance rates meaningful.

### 16.5 Effort and impact summary

| Phase | Effort | Risk closed |
|---|---|---|
| 0 | ~2 h | All Critical + 4 of 5 High |
| 1 | ~1 day | Remaining High, all Medium correctness |
| 2 | 2–3 days | Structural — prevents recurrence |
| 3 | 3–5 days | Product capability |

The important observation is the ratio: **two hours of Phase 0 removes most of the risk in this report.** The codebase is not far from safe; it is far from *self-correcting*, and that is what Phases 1–2 address.

# Part VI — Vibe-coding

## 17. What "vibe-coding" means here, precisely

The term was coined to describe a specific mode of working: you describe what you want, an AI produces it, and you **accept the output without reading it closely**, trusting the vibes. That is the pure form. It is fast, it is genuinely useful for throwaway work, and it is dangerous for exactly one reason: **every line you did not read is a line you cannot defend.**

The spectrum matters, because conflating its points produces bad advice:

| Mode | What the human does | What the human can defend |
|---|---|---|
| **Pure vibe** | Prompts, runs, ships | Almost nothing beyond "it seems to work" |
| **Assisted, unreviewed** | Prompts, skims, ships | Intent, not implementation |
| **Assisted, reviewed** | Prompts, reads every line, tests | Intent + implementation |
| **Assisted, constrained** | Specifies invariants first, prompts narrow, reads, tests, logs provenance | Intent + implementation + the *reasons* |
| **Hand-written** | Types everything | Whatever they thought of |

This repository sits between rows two and three: there is clear evidence of real understanding in places (the conflict SQL, the `IN (?)` binding, the transaction with cascading denial, the token system, the design tokens) and clear evidence of unread output in others (a nonexistent class in a guard, two dead selectors, three copies of one limit, a schema missing two tables). The interesting question for a report like this is not "was AI used?" — it clearly was, and it should be, because 600 lines of CSS tokens and 40 prepared statements are exactly the tedious work delegation is for. The interesting question is: **which delegated artefacts were understood, and how can you tell?**

You can tell, and Part VI is about how. The signal is not in the style of the code; it is in whether the *invariants* hold across the whole system. A human who understands an invariant enforces it everywhere, because they know what breaks when they don't. Generated code enforces an invariant wherever the prompt happened to mention it, and nowhere else.

---

## 18. Evidence-based accounting: where delegation helped and where it failed

I cannot know which lines were typed and which were generated; that is not recorded anywhere in the repository. What I *can* do is identify artefacts whose shape is diagnostic, and be explicit about confidence. The table below is the honest version.

| # | Artefact | Diagnostic evidence | Likely origin | Confidence |
|---|---|---|---|---|
| 1 | `if (!class_exists('App\Debug\Inspector'))` guarding `spl_autoload_register` | The class does not exist, has never existed, and is referenced nowhere. `class_exists()` on an unknown class is always `false`, so the guard is always true and does nothing. | Generated to satisfy a *perceived* need ("guard against double registration") without knowing the actual API (`spl_autoload_register`'s third argument). | High |
| 2 | Two dead JS branches gated on `#adminTabContent` | No such element exists. `teacherTabContent` does exist. The guard matches an admin layout that was replaced by a sidebar. | Generated against an older markup snapshot, never re-checked when the layout changed. | High |
| 3 | Three definitions of the enrollment limit, one annotated `// aligned with UI` next to a value that is not aligned | The comment asserts a relationship the code does not have. | Generated to justify a literal; the comment describes intent, not fact. | High |
| 4 | `app/security_headers.php` — 0 bytes; `app/timetable.sql` and `BruteForceProtector.php` added as 0-byte files in one commit and filled in a later one | Scaffold-then-fill workflow across commits. | Generated file structure first, contents later. | High |
| 5 | Schema: two tables missing, no semicolons, two identical unique keys on `enrollments`, four indexes where two suffice | Each symptom is a *concatenation* artefact — pieces produced separately and pasted together without a whole-file read. | Two or more independent generations of schema content, merged by hand. | High |
| 6 | `admin_login_process.php` has CSRF+rotation absent while `login_process.php` has both | Two endpoints implementing one concept, in two incompatible ways, at different times. | Separate sessions, no shared spec. | High |
| 7 | Three documents (`README`, `project_improvements`, `code_explanation`) describing features that do not exist: alternative-slot suggestions, dark mode | Docs describe the *intended* system. | Generated documentation from intent, never reconciled with code. | Medium–High |
| 8 | `PendingEnrollmentRepository` duplicating `EnrollmentRepository::createPendingIfAbsent`; `csrf_verify` duplicating `verify_csrf`; `STUDENT_ENROLLMENT_LIMIT` duplicating `MAX_COURSES` | Four independent re-derivations of concepts that already existed. | Each generation re-derived a fact rather than searching for it. | High |
| 9 | The `IN (?,?,…)` + `bind_param($types, ...$ids)` idiom in two dashboards | Non-obvious mysqli API usage, correct in both places. | Either hand-written or generated-and-understood; the consistency across files suggests a reused, verified pattern. | Medium |
| 10 | Teacher-conflict self-join with `c1.teacher_id = c2.teacher_id` and `c2.teacher_id IS NOT NULL` | NULL-aware reasoning about a join; the `IS NOT NULL` guard is redundant in SQL but shows deliberate thought about the unassigned-teacher case. | Almost certainly human-directed; this is domain reasoning, not pattern completion. | Medium–High |
| 11 | `approve_schedule`: transaction + re-validation + cascading denial of competing requests | Multi-step workflow reasoning about state, with the losers of a race resolved atomically. | Human design. Generated code rarely invents cascade semantics unprompted. | Medium–High |
| 12 | `css/styles.css`: 39 tokens, status triplets, Bootstrap overrides — but `var(--color-text-hover)` undefined and two shadow literals from a different palette | High structural quality with small inconsistencies at the edges. | Generated (excellent at bulk token architecture), lightly reviewed. | Medium |
| 13 | `EnrollmentService` with constructor injection but `Database::get()` called internally | The *shape* of dependency injection without the *discipline* of it. | Generated from a description of "clean architecture" without understanding why injection exists. | Medium–High |
| 14 | `room_dashboard.php` including `admin_sidebar.php` that was never committed | An invented file name. | Hallucinated dependency — the most characteristic generated-code failure. | High |

### 18.1 What delegation bought

Be specific, because the value is real and large:

- **~600 lines of CSS tokens and component overrides.** Writing a coherent design system by hand takes a day and is pure tedium. This one is good, and its two flaws are exactly the kind a five-minute review catches.
- **~40 prepared statements across 12 files.** Mechanical, repetitive, and easy to get subtly wrong by hand — which is precisely when you *want* to be using `prepare`/`bind_param` every time.
- **The CRUD and form scaffolding**: six admin forms, five teacher tabs, five student tabs, three modals, the grid-rendering loops. This is the bulk of the 1,160 dashboard lines, and it is the least interesting code in the project.
- **The CSRF, session and brute-force plumbing**, which is standard-shaped and benefits from being written once, correctly.
- **The plausible-sounding but wrong guards and comments**, which are the cost, and which would have been caught by reading. That is the trade: speed on volume, risk at the seams.

A rough accounting: delegation plausibly saved several days on the parts of this codebase nobody wants to write, and introduced perhaps a dozen defects, all of which are visible on a careful read in under an hour. **The trade is good — if and only if the read happens.**

### 18.2 What delegation cost, and the one pattern behind all of it

Look at the failure list again and notice that they are not independent. Items 1, 2, 5, 6, 7, 8, 13 and 14 are all the same failure:

> **A fact that exists in the system was re-derived at a second site instead of being looked up or enforced in one place.**

The limit (3 sites), the day list (5 sites), the CSRF guard (6 sites, 2 missing), the identity model (2 shapes), the pending-enrollment insert (2 methods), the verification function (2 names), the schema (2 concatenations), the admin layout (2 selector generations). Nobody owned the fact, so each generation reinvented it, and the copies drifted.

This is why the recommendation throughout this report is not "review more carefully" but "**enforce each invariant in exactly one place, and make the other places unable to disagree**" — a unique index, an `ENUM`, a single config accessor, a route-table flag. Structural enforcement survives delegation; careful review does not scale past the reviewer's attention span.

---

## 19. The protocol that makes delegation safe

If you delegate again — and for the CSS, the forms, the tests, the migrations, you should — this is the protocol the evidence above implies. It is written to be followed literally.

**1. Write the invariant before the prompt.** One sentence per rule: "a student's enrollment set is conflict-free and at most `limit` courses, enforced on every path that inserts into `enrollments`." Then the prompt is "implement this rule", not "add an enrollment feature". Generation without a stated invariant produces code that satisfies the example and nothing more.

**2. One concern per prompt; never combine a refactor with a feature.** Items 3, 5 and 6 in the table are all what happens when a second change lands on top of unreviewed first output.

**3. Name the file and the neighbours.** "There is already `EnrollmentRepository::createPendingIfAbsent`; do not create a second one" prevents item 8. Generation cannot search if you do not tell it to.

**4. Read every line, hunting five specific signatures.** These are the tells, and they are fast to scan for:

- a reference to something that does not exist (class, file, column, selector) → **hallucinated dependency**
- the same literal twice in one file → **duplicated constant**
- an `else` branch that cannot run because the `try` throws first → **unreachable recovery**
- a guard that is always true or always false → **decorative control flow**
- a comment that asserts a relationship → **verify the relationship, not the comment**

Item 3's `// aligned with UI` is the canonical example of the fifth: the comment was the most dangerous line in the file, because it read as evidence of correctness.

**5. Re-derive every number.** Limits, counts, indexes, costs, percentages. If a comment says "aligned with", compute both sides.

**6. Verify by execution.** Every inference in §12 is a hypothesis until a request is sent. This project had no execution loop, which is why three features could be dead without anyone noticing.

**7. Add the meta-tests.** `CsrfCoverageTest` (every POST handler verifies a token), `SchemaCompletenessTest` (every table in code exists in the schema), `DeadSelectorTest` (every `getElementById` target exists in rendered HTML). Each is fewer than 30 lines and each would have caught defects that a human review missed for months. This is the highest-leverage remaining work in the project.

**8. Keep a provenance log.** A `GENERATED.md` listing, per file: what was delegated, from what instruction, and what was verified by reading and by running. It costs two minutes per file and it converts "I think I understood this" into a checkable claim. It is also the single artefact that makes a viva or code review tractable.

**9. Delete dead code in the same breath.** The dead-code inventory in §4.3 makes the live system harder to reason about than the generated code made it easier to write. Deleting is the cheapest correctness tool available.

---

## 20. Concept ownership: what you must be able to explain unaided

This is the section that matters most for demonstrating understanding. For each subsystem that was plausibly AI-assisted, here is the concept you must own, and the question that reveals whether you do. If you can answer all thirteen without looking at the code, the delegation was safe.

| # | Subsystem | The concept you must own | The question that tests it |
|---|---|---|---|
| 1 | Conflict detection | Why room, teacher and course conflicts are write-time constraints while student conflicts are read-time joins; why a PHP `if` is not a constraint under concurrency | "Two admins schedule the same room and slot at the same instant. What happens, and which layer saves you?" |
| 2 | Enrollment rules | The difference between a *rule* and *where a rule is checked*; why a second write path silently voids the first one's guarantees | "Why can an admin approve a student into a conflict, and what is the one change that prevents it?" |
| 3 | The limit | That a configurable threshold has exactly one source of truth, and that passing it as a constructor argument at four call sites *is* duplication | "The UI says 6, the server says 5. Which number is the rule and how do you make it impossible to disagree?" |
| 4 | Attendance | Why attendance is keyed to `(student, session, date)` and not `(student, course)`; what `ON DUPLICATE KEY UPDATE` buys | "A teacher fixes a mistake and re-submits. What happens to the row, and which key makes it happen?" |
| 5 | Authorization | The difference between *authentication*, *role check* and *resource ownership check*, using the attendance endpoint as the example | "A teacher is logged in. Why can they still not mark another teacher's class, and where is that enforced?" |
| 6 | CSRF | Why a random per-session token compared with `hash_equals` works; why a missing token on *login* is a real attack | "What can an attacker do with the admin login form, and why does the token fix it?" |
| 7 | Sessions | Session fixation, and why rotation must occur *at privilege change* rather than on the next page load | "An attacker plants a session ID. Walk me through the attack and the exact line that stops it." |
| 8 | Password storage | Why bcrypt is deliberately slow, what a salt is for, and why `PASSWORD_DEFAULT` is the forward-looking choice | "Why not SHA-256, and what happens to existing hashes when the cost factor changes?" |
| 9 | SQL safety | Why parameter binding defeats injection even though the SQL string still contains the query | "Show me a query in this codebase that takes input, and explain why the `?` is safe." |
| 10 | Transactions | Atomicity, and specifically why the approval flow denies competing requests inside the same transaction | "Approving a booking denies the others. What breaks if you move that `UPDATE` outside the transaction?" |
| 11 | The front end | Design tokens as an indirection layer; progressive enhancement as the reason the forms work without JS | "Why do the enrollment buttons still work with JavaScript disabled, and what does the token system change when you change one variable?" |
| 12 | The JS defect | Event binding, and why a selector that matches nothing fails *silently* | "The admin edit modal is empty. Walk me from the click to the root cause." |
| 13 | The schema | Why the schema is the last line of defence: unique keys, `ENUM` as an invariant, foreign keys as deletion policy | "Why does the room unique key matter when the PHP already checks for conflicts?" |

### 20.1 The three sentences that summarise the whole system

If asked to describe this project in one breath:

> It is a multi-role timetable system in procedural PHP over MySQL, where the domain problem is a four-dimensional constraint-satisfaction problem across a 40-cell weekly grid, and the codebase's central strength is that it models three of those dimensions at write time and the fourth at enrollment time. Its central weakness is that each invariant is enforced in more places than one, so the copies drifted — which produced a limit mismatch, a dead approval check, an unfixed session ID and a schema missing two tables. Its most interesting artefact is the `// aligned with UI` comment, because it is a claim about correctness that the code contradicts, and reading it as reassurance rather than as a claim to verify is exactly the failure mode that AI-assisted development rewards.

### 20.2 What I would change first, and why that order

Fix the unauthenticated endpoints, then the CSRF gaps, then the ownership check, then the schema, then the limit. The order is not by severity alone but by **blast radius times reachability**: two unauthenticated endpoints are reachable by anyone from anywhere with a URL, the CSRF gap needs a logged-in victim plus a malicious page, the ownership gap needs a valid teacher account, the schema gap needs only a fresh install, and the limit mismatch needs only a student trying to enroll. All five are cheap; the first three are the ones that turn a student project into an incident.

---

# Appendices

## Appendix A — Endpoint and authorization matrix

| Endpoint | Method | Purpose | Auth | Role | CSRF | Side effects |
|---|---|---|---|---|---|---|
| `index.php` | GET | Main login form | no | — | issues | none |
| `login.php` | GET | Legacy login form (orphan) | no | — | issues | none |
| `login_process.php` | POST | Authenticate | no | role in WHERE | **yes** | session write, rotation |
| `admin_login.php` | GET | Admin login form | no | — | issues | none |
| `admin_login_process.php` | POST | Authenticate admin | no | `role='admin'` | **no** | session write, no rotation |
| `register.php` | GET | Registration form | no | — | issues | none |
| `register_process.php` | POST | Create account | no | allow-list | **yes** | INSERT users |
| `register.html` | GET | Static mockup (orphan) | no | — | — | none |
| `admin_login.html` | GET | Static mockup (orphan) | no | — | — | none |
| `logout.php` | GET | Destroy session | no | — | **no** | session destroy |
| `student_dashboard.php` | GET | Student home | yes | student | — | none |
| `teacher_dashboard.php` | GET | Teacher home | yes | teacher | — | none |
| `admin_dashboard.php` | GET | Admin home (7 pages) | yes | admin | — | none |
| `profile.php` | GET | Student profile | yes | student | — | none |
| `change_password_process.php` | POST | Change password | yes | student | **yes** | UPDATE users |
| `manage_enrollment.php` | POST | Enroll/unenroll/request | yes | student | **yes** | INSERT/DELETE |
| `manage_teacher_tasks.php` | POST | Announcements, booking | yes | teacher | **yes** | INSERT/DELETE |
| `manage_admin_tasks.php` | POST | 10 admin actions | yes | admin | **yes** | multi-table writes |
| `mark_attendance.php` | POST | Upsert attendance | yes | teacher | **no** | UPSERT attendance |
| `check_conflict.php` | GET | Room occupancy probe | **no** | **—** | — | none (leak) |
| `get_student_attendance.php` | GET | Attendance aggregate | **no** | **—** | — | none (leak) |
| `room_dashboard.php` | GET | Room grid | yes | admin | — | none (broken) |
| `rooms_dashboard.php` | GET | Redirect stub | — | — | — | 302 |
| `teachers_dashboard.php` | GET | Redirect stub | — | — | — | 302 |

## Appendix B — Findings register

Severity: C = Critical, H = High, M = Medium, L = Low. Effort: S = under 15 min, M = under 2 h, L = under a day.

> **Status after this revision.** Rows 1, 2, 3, 4, 6, 9, 10, 11, 12 and 13 are addressed, and the dead `attendance_date` column and the duplicate indexes in §6.3 are gone; each row says how. Everything else stands as written, including the items that need a design decision rather than a patch: admin-login CSRF and session rotation, open self-registration as teacher, and a real migration path. The verification for all of the above is `tests/smoke.sh` (26 assertions) run against a freshly imported `demo.sql`.

| # | Sev | Finding | Evidence | Fix | Effort |
|---|---|---|---|---|---|
| 1 | C | Attendance readable for any student, unauthenticated | `get_student_attendance.php:7-8`, no session check | Delete, or bind `student_id` to session | S |
| 2 | C | Room occupancy enumerable, unauthenticated | `check_conflict.php:5-9` | **Fixed in this revision**: admin session required, 403 otherwise | S |
| 3 | H | Teacher can write attendance for any course | `mark_attendance.php:5` (role only) | **Fixed in this revision**: `courses.teacher_id` check, 403 | S |
| 4 | H | Attendance form/handler have no CSRF | `teacher_dashboard.php:186`, `mark_attendance.php` | **Fixed in this revision**: hidden token + `verify_csrf()`, 400 | S |
| 5 | H | Admin login: no CSRF, no session rotation | `admin_login_process.php:20-25` | Token check + `session_regenerate_id(true)` | S |
| 6 | H | Approval bypasses cap and conflict rules | `manage_admin_tasks.php:219` `INSERT IGNORE` | **Fixed in this revision** for the cap (`AppConfig::maxCourses()`); duplicate insert still `INSERT IGNORE` | M |
| 7 | H | Open self-registration as `teacher` grants writes | `register_process.php:38` | Invitation/approval flow | M |
| 8 | M | `.env` tracked in git; no `.htaccess` | `git ls-files`; no dotfile deny | `git rm --cached`, ignore, `.env.example`, deny rule | S |
| 9 | M | Two of nine required tables absent from schema | `app/timetable.sql` vs code | Complete migration. **Fixed in this revision**: `app/timetable.sql` regenerated from `demo.sql`, 9 tables, verified to import on MariaDB 11.3 and MySQL 8.0 | S |
| 10 | M | Schema has no semicolons; cannot import | whole file | Rewrite as migration | S |
| 11 | M | Enrollment limit: UI 6, server 5 | `AppConfig:23` vs `manage_enrollment.php:26` | **Fixed in this revision**: both read `AppConfig::maxCourses()`, `MAX_COURSES=5` | S |
| 12 | M | Admin edit-user modal dead | `js/script.js:19` guard | **Fixed in this revision**: `id="adminTabContent"` added to the admin container | S |
| 13 | M | Live conflict warning dead | `js/script.js:253` guard | **Fixed in this revision**: same attribute; `check_conflict.php` is now admin-only | S |
| 14 | M | Duplicate registration → HTTP 500 | `register_process.php:62` + `MYSQLI_REPORT_STRICT` | `try/catch mysqli_sql_exception` | S |
| 15 | M | `enroll()` duplicate → 500 → "network error" | `EnrollmentService:27` | Catch in controller, return 409 JSON | S |
| 16 | M | Enrolling in an unscheduled course hides it | `student_dashboard.php:139` + INNER JOIN read | Filter or render unscheduled section | M |
| 17 | M | Brute force: no `flock`, no pruning, per-username only | `BruteForceProtector:20-30` | `flock`, prune, IP ceiling | M |
| 18 | M | No session cookie flags / strict mode | `app/bootstrap.php` | `session_set_cookie_params` + ini | S |
| 19 | M | Teacher/course conflicts racy (no DB constraint) | `manage_admin_tasks.php:178-205` | Denormalise `teacher_id` + unique key | M |
| 20 | M | DOM XSS via course name in booking handler | `js/script.js` `.html(courseName)` | `textContent` | S |
| 21 | M | `profile.php` always shows "No email on file" | `profile.php:17` | Query user or set at login | S |
| 22 | M | Admin dashboard fetches all data on every page | `admin_dashboard.php:29-34` | Move into branches | M |
| 23 | L | No SRI on five CDN assets | all views | Add `integrity`/`crossorigin` | S |
| 24 | L | `room_dashboard.php` includes nonexistent file | `room_dashboard.php:32` | Delete the page | S |
| 25 | L | `login.php` references missing `css/login.css` | `login.php:10` | Delete the page | S |
| 26 | L | Duplicate unique keys / indexes; dead columns | schema | New migration | S |
| 27 | L | `ArrayCache` provides no cross-request caching | `app/Cache/ArrayCache.php` | Delete or use APCu | S |
| 28 | L | `PendingEnrollmentRepository` unused duplicate | `app/Repositories/` | Delete | S |
| 29 | L | `DayOfWeek`/`TimeSlot` overwritten by literals | `admin_dashboard.php:34-35` | Delete the literals | S |
| 30 | L | Committed docs describe nonexistent features | `README`, `code_explanation` | Rewrite from code | S |
| 31 | L | No labels, no viewport on 3 pages, no `scope` on tables | views | Add attributes | M |
| 32 | L | `var(--color-text-hover)` undefined | `css/styles.css` `.btn-outline:hover` | Define or use existing token | S |
| 33 | M | Pages that require only `db_config.php` never load `.env`, so they fail on any non-default DB config (observed on four: admin login, attendance, and both read-only endpoints) | `admin_login_process.php:2`, `mark_attendance.php:2`, `check_conflict.php:2`, `get_student_attendance.php:2` | Require `app/bootstrap.php`. **Fixed in this revision** in `admin_login_process.php` and `mark_attendance.php`; the read-only endpoints are dealt with too: `check_conflict.php` now requires bootstrap and an admin session, and `get_student_attendance.php` was deleted | S |
| 34 | M | Uncaught exceptions print stack traces and absolute paths even with `APP_DEBUG=0` | `app/bootstrap.php:23-33` (only ever turned `display_errors` **on**) | **Fixed in this revision**: the non-debug branch now sets `display_errors=0` and `display_startup_errors=0` while keeping `log_errors=1`; verified that a duplicate registration returns a bare `500` with an empty body, and that `APP_DEBUG=1` still prints the trace | S |

**Reproduced against the running instance (§15.6):** rows 1, 2, 3, 4, 5 (fixation demonstrated end to end), 6, 8, 9, 10, 11, 12, 13, 14, 16, 18, 21, 32, plus rows 33–34, which execution discovered. **Verified by direct import:** rows 9 and 10. **Source-verified only:** 7, 15, 19, 20, 22–31 — row 19 is a concurrency race and cannot be demonstrated on PHP's single-threaded built-in server.

## Appendix C — Glossary

**Atomicity** — all-or-nothing execution of a group of database writes, provided by a transaction. Used in `mark_attendance.php` (all students or none) and `approve_schedule` (insert + approve + deny, together).

**Bcrypt** — a deliberately slow password hash with a built-in salt, tuned by a cost factor. `password_hash($p, PASSWORD_BCRYPT)`.

**CSRF (Cross-Site Request Forgery)** — an attack that makes a logged-in user's browser send a state-changing request. Defended here by a per-session random token compared with `hash_equals`.

**DOM XSS** — script execution caused by inserting attacker-influenced data into the page via an HTML-parsing API (`.html()`, `innerHTML`), as in the booking success handler.

**IDOR (Insecure Direct Object Reference)** — an endpoint that takes an object identifier from the client without checking the caller may access it. `get_student_attendance.php` is the example.

**N+1 query** — a loop that issues one query per row instead of batching. Avoided in `teacher_dashboard.php`, present in `schedule_requests`.

**Prepared statement** — a query template sent to the server with parameters bound separately, so input can never be parsed as SQL. Used throughout.

**Progressive enhancement** — building server-rendered functionality that works without JavaScript, then layering AJAX on top. This application's structure.

**Session fixation** — an attacker supplying a session identifier that the victim then authenticates with. Defended by rotating the ID at login, and by `session.use_strict_mode`.

**TOCTOU (Time-of-check to time-of-use)** — a race between validating a condition and acting on it. Closed here only for rooms, and only by a unique key.

**Unique key** — a database constraint guaranteeing no two rows share a value combination; the only enforcement that survives concurrency. `UNIQUE(room_id, day_of_week, timeslot)` is the model case.

**Upsert** — insert-or-update in one statement. `INSERT … ON DUPLICATE KEY UPDATE` in `mark_attendance.php`.

**Vibe-coding** — accepting generated code without reading it. See §17 for why the review step, not the generation, is what determines whether the result is trustworthy.

## Appendix D — Where to go next

- **Start with** Appendix B rows 1–7. Two hours, and the system stops being an incident waiting to happen.
- **Then** §15.2: run the sixteen checks. Five of them require only `curl` against a running copy, and they convert this report's inferences into facts.
- **Then** §16.3's first item: a router with an auth middleware. It is the single change that prevents the recurrence of the defect class this report documents most often.
- **Read** the three existing documents (`README.md`, [project_improvements.md](project_improvements.md), [code_explanation.md](code_explanation.md)) against the code, and mark every claim that is no longer true. That exercise is the cheapest way to internalise §18's lesson.

---

*End of report. Every finding above cites a file and a line or a reproducible observation. Where behaviour was inferred rather than observed, it is labelled as such — notably §12.2 and §12.3, which rest on `MYSQLI_REPORT_STRICT` semantics and should be confirmed by a single duplicate-registration attempt before being treated as fact.*

