# Defense Guide — Timetable Management System

Everything you need for the demo and the questioning, in the order you'll need it.

- **Part 1** is what to say in the first minute.
- **Part 2** is the backend, taught through this codebase (this is what "learn the backend" means here).
- **Part 3** is every file: what it does, what to point at, how to modify it, what changes if you do.
- **Part 4** is the demo script, the "what would you change" table, and a Q&A drill to rehearse.

> **No Docker needed.** Docker was only how I *tested* the app without touching your Mac. To show your professor you need PHP + MySQL running locally — XAMPP/MAMP, or `php -S` with MySQL. §4.1 has the exact steps, including a fix for the broken schema import (which is itself a good talking point).

---

## Part 1 — The 60-second explanation

> "It's a timetable management system for a university, written in plain PHP with MySQL. There are three roles. A student logs in, sees their weekly schedule, enrolls in courses, checks attendance and reads announcements. A teacher takes attendance for their own classes, posts announcements to their courses, and requests a room booking. An admin manages users, courses and schedules, and approves the enrollment and room-booking requests. The interesting part is the conflict logic: no two classes can share a room at the same time, a teacher can't be in two places, and a student can't enroll in two courses that clash."

Then the numbers, which make you sound like you own the code:

| Fact | Value |
|---|---|
| PHP files / lines | 41 files, ~2,900 lines |
| Tables | 7 in the SQL dump, 9 needed by the code |
| Course cap enforced by server | 5 (`manage_enrollment.php:26`) |
| Course cap shown by the UI | 6 (`AppConfig::maxCourses()`, default `MAX_COURSES=6`) |
| Conflict rules | 3 — room, course, teacher |
| Grid | 5 days × 8 one-hour slots = 40 slots per room per week |
| Roles | student, teacher, admin (admin has a separate login) |

The cap mismatch is a genuine bug, not a typo — and it's a *great* answer if he asks "did you find anything yourself?" Say it in one sentence: "The dashboard lets you click Enroll while you have 5 courses because the UI reads a config default of 6, but the server hard-codes 5, so the button always fails at that point. They should read the same constant."

---

## Part 2 — How the backend works, through this code

### 2.1 There is no framework, and that is a real architectural decision

There is no router. **One URL = one PHP file.** `student_dashboard.php` is the page; `manage_enrollment.php` is the AJAX endpoint behind its buttons. Shared work happens in files that get `require`d.

Draw this on the board when he asks "how does a request work?":

```
Browser
  │  GET /student_dashboard.php   (Cookie: PHPSESSID=abc123)
  ▼
Apache  ──►  PHP interpreter
  │
  ├─ student_dashboard.php
  │     require app/bootstrap.php
  │        ├─ session_start()              ← resume $_SESSION from the session file
  │        ├─ EnvLoader::load(.env)        ← DB_HOST etc. into $_ENV + putenv()
  │        ├─ spl_autoload_register(...)   ← App\Foo\Bar  →  app/Foo/Bar.php
  │        ├─ if APP_DEBUG=1 → display_errors on
  │        └─ header(...) security headers
  │        └─ require db_config.php
  │              └─ new mysqli(...) → $conn   (strict error mode)
  │
  ├─ role guard:  $_SESSION['role'] === 'student' ? continue : redirect index.php
  ├─ queries:  $enrollRepo->getDetailedSchedule($conn, $user_id)   ...
  ▼
HTML (server-rendered) ──► Browser
  │
  └─ js/script.js  ──fetch──►  manage_enrollment.php
                                   verify CSRF → role check → EnrollmentService → JSON
```

Two kinds of endpoints, and you must be able to say which is which:

| Kind | Examples | Response | Who calls it |
|---|---|---|---|
| **Page** (GET) | `index.php`, `student_dashboard.php`, `admin_dashboard.php`, `profile.php` | Full HTML | The browser address bar / links |
| **Form target** (POST) | `login_process.php`, `manage_admin_tasks.php`, `change_password_process.php` | `302` + `Location`, or plain text error | An HTML `<form>` |
| **AJAX** (POST/GET) | `manage_enrollment.php`, `manage_teacher_tasks.php`, `check_conflict.php`, `get_student_attendance.php` | JSON | `fetch()` in `js/script.js` |

### 2.2 The superglobals you'll be asked about

| Variable | Meaning | In this project |
|---|---|---|
| `$_POST` | Form/body fields | Credentials, course ids, attendance marks |
| `$_GET` | Query string | `?page=users`, `?room_id=1&day=Monday` |
| `$_SESSION` | Server-side per-user state, keyed by a cookie | `user_id`, `username`, `role`, `admin_id`, `csrf_token` |
| `$_SERVER` | Request metadata | `REQUEST_METHOD`, `REMOTE_ADDR`, `PHP_SELF` |
| `$_ENV` / `getenv()` | Environment | DB credentials, `APP_DEBUG`, `MAX_COURSES` |

Say this: "`$_SESSION` is not stored in the browser. The browser only holds a random session id in a `PHPSESSID` cookie; the actual values live in a file on the server. So a user cannot edit their own role by editing a cookie."

### 2.3 Authentication: two different logins, two different session shapes

**Student/teacher** — `login_process.php`:

1. reject non-POST (`405`),
2. `verify_csrf($_POST['csrf_token'])` → `400` if bad,
3. validate inputs → `422`,
4. `BruteForceProtector::check($username)` → `429` if locked out,
5. `SELECT ... WHERE username=? AND role=?` — **prepared, bound**,
6. `password_verify($password, $user['password'])`,
7. **`session_regenerate_id(true)`** (stops session fixation),
8. set `$_SESSION['user_id' | 'username' | 'role']`,
9. `302` to the right dashboard.

**Admin** — `admin_login_process.php`: same shape, but it sets **`$_SESSION['admin_id']` only**, does **not** regenerate the session id, and does **not** check a CSRF token. That's why `admin_dashboard.php` accepts either `admin_id` or `user_id`+`role=admin`. If he asks "why two systems?", the honest answer is: "It was bolted on later and never unified. `user_id`+`role` is the better design, and consolidating them is a one-afternoon job."

**Passwords:** `password_hash($pw, PASSWORD_BCRYPT)` writes a 60-char hash; `password_verify` re-hashes and compares. Say: "I never store or compare the plaintext, and bcrypt is deliberately slow, so a stolen table isn't crackable at scale."

### 2.4 Authorization: role checks, and where they're missing

The pattern everywhere is:

```php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php"); exit();
}
```

Note the shape: **check the session, then redirect, then `exit`**. Skipping `exit` is the classic bug — the rest of the page still executes and can leak data. Point out that every guard here does call `exit()`, which is correct.

There are two levels, and he will probably test the difference:

- **Role-level** — "is this a teacher?" — checked almost everywhere.
- **Ownership-level** — "is this *your* course?" — checked in `manage_teacher_tasks.php` (announcements and bookings do `WHERE course_id=? AND teacher_id=?`), but **not** in `mark_attendance.php`, where any teacher can post any `schedule_id`. That's the single best thing to volunteer before he finds it: "The authorization model is inconsistent: the booking endpoint verifies ownership, the attendance endpoint trusts the schedule id. They should be the same check."

### 2.5 CSRF: what it is and how it's done here

CSRF = another site makes the user's browser POST to your app while they're logged in. Defence = a random token stored in the **session** and echoed into the form; the server compares with `hash_equals`.

- Generator: `app/csrf.php` — `csrf_token()`, `csrf_field()`, `verify_csrf()`.
- Delivery: hidden `<input name="csrf_token">` for forms, `<meta name="csrf-token">` for `fetch()`.
- Verification: `manage_enrollment.php`, `manage_admin_tasks.php`, `manage_teacher_tasks.php`, `login_process.php`, `register_process.php`, `change_password_process.php`.

Two POST endpoints don't verify it: `admin_login_process.php` and `mark_attendance.php`. (Login CSRF is lower severity; the attendance one is a real hole.)

### 2.6 SQL: prepared statements, joins, transactions

**Prepared statements.** `$stmt = $conn->prepare("... WHERE id = ?"); $stmt->bind_param("i", $id);` — the query is compiled first and the value is sent separately, so a value like `1 OR 1=1` is data, never SQL. Type letters: `i` int, `s` string, `d` double, `b` blob.

Count them when he asks: this app uses prepared statements for **every user-supplied value**, which is why SQL injection is not on the defect list. The one thing to be careful saying: the *identifiers* (`LIMIT`, column names) can't be parameterised — here they're hard-coded, so it's safe.

`mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)` at `db_config.php:9` makes every SQL error **throw** instead of returning `false`. Consequence: `if ($stmt->execute()) { ... } else { "friendly error" }` never reaches the `else`. That's the duplicate-registration bug (`register_process.php`): you get a stack trace, not the message.

**Joins.** The student schedule is built by chaining four tables (`EnrollmentRepository::getDetailedSchedule`):

```
enrollments e → courses c → schedules s → rooms r   (all INNER JOIN)
```

INNER JOIN means "only rows that exist on both sides". So a course with **no** schedule row disappears entirely from the schedule. That is exactly why enrolling in a course that an admin hasn't scheduled yet "succeeds" but never shows up. Say: "The fix is either a LEFT JOIN so the course appears with a blank slot, or a rule that a course can't be enrolled until it's scheduled."

**Transactions.** Two flows need "all or nothing":

- `mark_attendance.php:35-57` — `begin_transaction()`, one `INSERT` per student, `commit()`; `catch` → `rollback()`. Result: you never half-mark a class.
- `manage_admin_tasks.php` `approve_schedule` — inside the transaction: insert the schedule, mark the request approved, then deny the competing pending requests for the same room/day/slot. Order matters: the "deny competitors" query filters `status='pending'`, and the current request is already `'approved'`, so it can't deny itself. That's a nice detail to point at.

**Constraints as the last line of defence.** `schedules` has `UNIQUE (room_id, day_of_week, timeslot)`; `enrollments` has `UNIQUE (student_id, course_id)`; `attendance` has `UNIQUE (student_id, schedule_id, date)`. So even if two requests race past the PHP check, the database refuses the second insert. That's why `mark_attendance` can safely use `ON DUPLICATE KEY UPDATE` — it turns "insert" into "insert or correct".

### 2.7 HTTP: the status codes in this app

| Code | Where | Meaning here |
|---|---|---|
| `302` | `app_redirect()`, every guard | "Go here instead" — and **that's** how login works |
| `400` | `login_process.php` | Bad CSRF |
| `401` | failed login | Wrong credentials |
| `403` | `manage_admin_tasks.php` | Not an admin |
| `405` | most POST handlers | Someone hit the URL with GET |
| `419` | `manage_admin_tasks.php` | Invalid CSRF token |
| `429` | `BruteForceProtector` | Locked out, 5 failures / 5 minutes |
| `500` | `db_config.php` | DB unreachable |

Note the inconsistency, it's a good answer: `400` vs `419` for the same concept, and the AJAX endpoints return **JSON with a 200 status even on logical failure**. Say: "The AJAX contract is `success: true|false` in the body rather than the status code; consistent, but it means monitoring can't see failures."

### 2.8 The JS layer

`js/script.js` is one `DOMContentLoaded` block, jQuery for Bootstrap and vanilla `fetch` for AJAX.

- **Bootstrap tabs**: the `data-toggle="tab"` attributes are handled by Bootstrap; no custom JS needed. That's why tabs work even though the file is short.
- **Event delegation**: one listener on `#studentTabContent` handles all Enroll/Unenroll buttons, reading `data-course-id`. That's why the generated buttons don't each need their own handler.
- **`FormData` + `fetch`**: sends the CSRF token from the meta tag, then reads `result.success`.
- **Booking modal**: `show.bs.modal` copies the button's `data-*` attributes into hidden inputs.

---

## Part 3 — Every file

Format: **what it is → what to point at → how to modify → what changes**.

### 3.1 Plumbing (the files that make everything else work)

#### `app/bootstrap.php` — the shared preamble (40 lines)
- **Does:** starts the session, loads `.env`, registers the class autoloader, optionally enables debug, defines `app_redirect()`, loads `db_config.php`, and sets three security headers.
- **Point at:** `:3` `session_start()`, `:7` `EnvLoader::load()`, `:11` autoloader, `:24` the `APP_DEBUG` branch, `:36` the `db_config` include, `:39-41` the headers.
- **Modify:** add a global helper → wrap in `if (!function_exists(...))` like `app_redirect`. Change security headers → `:39`. Add a new env var → nothing to change here, `EnvLoader` picks it up.
- **Point at:** the `APP_DEBUG` branch now has two arms. `APP_DEBUG=1` prints errors; **anything else forces `display_errors` and `display_startup_errors` off** while keeping `log_errors` on. That `else` is the fix for the original defect: without it, a php.ini that ships `display_errors=On` (XAMPP's default) printed stack traces and absolute paths to the browser even in production mode. Worth saying that you verified both directions — a forced fatal returns a bare `500` with `APP_DEBUG=0`, and the trace with `APP_DEBUG=1`.

#### `db_config.php` — the database connection (27 lines)
- **Does:** refuses to run without `mysqli`, turns SQL errors into exceptions, reads credentials from `$_ENV`/`getenv` with localhost defaults, connects, sets `utf8mb4`, and dies with a generic message on failure.
- **Point at:** `:9` `mysqli_report(...)` — this one line changes error handling for the whole app; `:16` the `env ?: default` chain; `:19` `set_charset('utf8mb4')` (why emoji and Amharic text won't corrupt).
- **Modify:** new credentials → `.env`, not this file. Different fallback → `:16-18`.
- **What changes:** every page includes this, directly or through bootstrap — so a change here is global.
- **Trap:** `check_conflict.php` and `get_student_attendance.php` include **only** this file, not bootstrap, so they never load `.env`. On any non-default database they fail with "Database connection error." (Two pages used to share this bug — `admin_login_process.php` and `mark_attendance.php` — and both were fixed by requiring `app/bootstrap.php`; verified 500 → 302.)

#### `.env` — configuration (5 lines, committed to git)
- **Does:** `APP_DEBUG`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` (a sixth, `DB_PORT`, is optional and defaults to 3306 in `db_config.php`).
- **Point at:** it exists because credentials shouldn't be edited in code — good instinct. Then admit the flaw: it is **committed**, and with no `.htaccess` it's downloadable at `/.env`. The fix is half done: `.env.example` is now committed with placeholder values, so the remaining steps are `git rm --cached .env`, an `.env` line in `.gitignore`, and a dotfile deny rule in Apache (or a `public/` document root).
- **The bigger flaw, mostly fixed:** `admin_login_process.php` and `mark_attendance.php` used to never load this file (they skipped bootstrap), so they ignored `.env` entirely. Both now require `app/bootstrap.php`, so they honour it. Two unused endpoints — `check_conflict.php` and `get_student_attendance.php` — still bypass bootstrap and read `getenv()` only.
- **Modify:** change the DB → edit here, no code change. That separation is the whole point, and it's the right answer to "why not hard-code it?".

#### `app/Support/EnvLoader.php` — 20-line `.env` parser
- **Does:** reads `KEY=value` lines, skips comments and blanks, writes into `$_ENV` **and** `putenv()`.
- **Point at:** the `if (!array_key_exists($k, $_ENV))` check — real environment variables win over the file, which is how you override config in production without editing files.
- **Limits:** no quotes, no escapes, no `export`. That's fine for six variables and wrong for anything complex (that's what `vlucas/phpdotenv` is for).

#### `app/csrf.php` — token generation and checking (26 lines)
- **Does:** `csrf_token()` creates 32 random bytes hex once per session; `csrf_field()` prints the hidden input; `verify_csrf()` compares with `hash_equals`.
- **Point at:** `random_bytes` (CSPRNG, not `rand`), and `hash_equals` — constant-time comparison, so an attacker can't learn the token byte by byte from response timing.
- **Modify:** rotate the token per form → add an id argument and store `$_SESSION['csrf'][$id]`.

#### `app/Auth.php` — 13 lines, two helpers
- **Does:** `requireRole([...])` redirects to `index.php` if the session role isn't allowed; `regenerateSession()` rotates the id **once per session** (guarded by `$_SESSION['__rotated']`).
- **Point at:** the `__rotated` flag — it prevents an infinite rotation loop on every page load, which is a subtle thing to get right.
- **Note:** only `student_dashboard.php` calls `requireRole()`; the other pages inline the same check. That duplication is why the admin path drifted.

#### `app/Core/Database.php` — a singleton wrapper (33 lines)
- **Does:** same job as `db_config.php`, but as a class with `Database::get()` returning one shared `mysqli`.
- **Point at:** it reads only `getenv()`, not `$_ENV` — so it depends on `putenv` having run, i.e. on bootstrap. It's a **second** way to connect, used by `ScheduleRepository` while everything else uses `$conn`. Say: "Two connection paths is a design smell; one of them should go, and `db_config.php` is the one the procedural pages depend on."
- **Modify:** new DB setting → you must update **both** this file and `db_config.php`. That's the cost of the duplication, and a good answer if he asks what you'd refactor.

#### `app/Config/AppConfig.php` — config with a static cache (35 lines)
- **Does:** `AppConfig::get($key, $default)` reads env once and memoises; `maxCourses()` returns `(int) get('MAX_COURSES', 6)`; also `appName()` and `debug()`.
- **Point at:** `maxCourses()` default **6** versus the hard-coded **5** in `manage_enrollment.php:26`. This file is the right home for the limit; the endpoint should call it.
- **Modify (the fix):** in `manage_enrollment.php` replace `5 // aligned with UI` with `AppConfig::maxCourses()`, and add `MAX_COURSES=5` to `.env` if 5 is the intended policy. Now UI and server agree, and the limit is configurable.

#### `app/Cache/ArrayCache.php` — 19 lines, a non-cache
- **Does:** `remember($key, $ttl, $fn)` in a `static array`.
- **Point at:** PHP's lifecycle. Each request is a fresh process state, so a static array lives for **one request** and dies. It cannot cache across users. Real caching needs APCu, Redis, or the database.
- **Note:** `CourseRepository::allCached()` uses it and **nothing calls that method** — it's dead code. Honest answer: "The cache was written to demonstrate the pattern, but a per-request cache earns nothing; it should be removed or backed by APCu."

#### `BruteForceProtector.php` — login throttling (62 lines)
- **Does:** `check()` before verifying credentials, `registerFailure()` after a bad attempt, `registerSuccess()` clears it. Key = `sha256(username|ip)`, file = `storage/bruteforce/<key>.json`, 5 attempts / 300 s.
- **Point at:** the lockout branch that `die()`s with `429` and a "try again in N minutes" message; and the expiry branch that resets after 5 minutes.
- **Modify:** thresholds are **default arguments** — `check($username, 10, 900)`, or change the defaults.
- **Trade-offs to name:** it's file-based (no Redis), it locks per **username+IP** so rotating usernames or IPs evades it, and `storage/` must be writable. `.gitignore` already ignores `storage/` and `*.json`.

#### `app/Domain/DayOfWeek.php` / `app/Domain/TimeSlot.php` — 29 lines together
- **Does:** static allow-lists — Monday–Friday, and the eight one-hour slots from 09:00 to 17:00 — plus `isValid()`.
- **Point at:** this is your validation source of truth for schedules. `manage_admin_tasks.php` validates against it before insert, so a crafted POST can't invent a 03:00 class.
- **Note:** `admin_dashboard.php:21` imports `DayOfWeek::all()` and then overwrites `$weekdays` with a literal at `:34`. That shadowing is small but it's exactly the kind of duplication to name before he does.
- **Modify (very likely question):** "add a Saturday class" → add `'Saturday'` here, and also to the three places the arrays are **re-declared literally**: `teacher_dashboard.php:111-112`, `admin_dashboard.php:34-35`, `room_dashboard.php:20`, plus the `FIELD(...)` ordering in the SQL of `EnrollmentRepository`. That's the "what changes" answer: one domain change, five edit points, which is the argument for using the class everywhere.

#### `app/Repositories/*` — the data-access layer (5 classes, ~250 lines)
- **Does:** `UserRepository` (lookup by id/role, list teachers/students, assign teacher), `CourseRepository` (courses with teacher, available-for-student, slots per course), `EnrollmentRepository` (count, is-enrolled, create, delete, pending request, detailed schedule, enrolled list, count+conflict map), `ScheduleRepository` (student schedule, conflict check), `PendingEnrollmentRepository` (insert-if-absent).
- **Point at:** `EnrollmentRepository::getDetailedSchedule` (the four-table INNER JOIN), and `ScheduleRepository::conflictsForStudentCourse` — an `EXISTS` subquery that answers "does any class of this course overlap any class I'm already in?" in one round trip.
- **Reality check to state:** only the **enrollment** path actually uses these classes. `CourseRepository` is used by the student dashboard; the rest of the app is procedural SQL in page files. So the layered architecture is **half applied**. If he asks "is this MVC?", the accurate answer is "no — it's page-per-file with a repository/service layer that only the enrollment feature uses. The structure exists, the consistency doesn't."

#### `app/Services/EnrollmentService.php` — the enrollment rules (49 lines)
- **Does:** `enroll()` checks conflict → limit → insert; `unenroll()` deletes; `requestApproval()` checks already-enrolled, conflict, then inserts a pending request if absent.
- **Point at:** the order of the two checks — conflict before limit — and that the limit is a **constructor argument** (`$maxCourses = 6`).
- **Modify:** add a prerequisite rule (e.g. "must have CS101 first") → add a check at the top of `enroll()` and a `prerequisite_id` column. Nothing else changes, because the controller only passes `action` and `course_id`.
- **Weak spot:** the public `unenroll()` has no check for, say, "can't drop a mandatory course" — business rules are thin.

#### `app/Http/Controllers/EnrollmentController.php` — 18 lines
- **Does:** maps `action` → service method; validates `course_id > 0`; returns `['success'=>bool,'message'=>string]`.
- **Point at:** this is the only place the three actions are named. Adding an action = one `if` here + one method in the service + one branch in `js/script.js`.
- **Note:** there is no output escaping here because it returns JSON, and the JSON is written by `json_encode` in `manage_enrollment.php`.

#### `app/security_headers.php` — **empty file (0 bytes)**
- **What to say if he opens it:** "Dead file. The headers it was meant to set are inlined at the end of `app/bootstrap.php`. It should be deleted or filled and required — an empty file is worse than no file, because it looks like a control that exists."

#### `manage_teacher_tasks.php` — teacher actions (130 lines)
- **Does:** POST + CSRF + role guard, then three actions: `add_announcement` (ownership-checked, then insert), `delete_announcement` (JSON; `DELETE ... WHERE id=? AND teacher_id=?` — ownership encoded in the WHERE), `book_room` (ownership check → room conflict → teacher conflict → insert into `pending_schedules`).
- **Point at:** `:28` the announcement branch, `:77` the booking branch, and the three numbered checks inside it. This is the best-written handler in the project: authorization first, conflicts second, then the write.
- **Modify:** "let teachers book without approval" → change the insert to target `schedules` and skip the admin step; but then you must re-check the `UNIQUE (room_id, day, timeslot)` constraint, and the admin schedule-requests page becomes empty.
- **Weak spot:** the checks run **before** the insert, not inside a transaction, so two simultaneous bookings can both pass. The `UNIQUE` key is what actually prevents the double-booking. And the room check reads `schedules` only — it ignores other **pending** requests, so the same teacher can submit the same slot twice and both are accepted (verified: two identical bookings, both `success:true`). Whether pending requests should reserve a slot is a policy question worth being able to answer.

#### `manage_admin_tasks.php` — the admin command centre (338 lines)
- **Does:** one POST endpoint, one `switch ($action)` with ten cases: `add_user`, `edit_user`, `delete_user`, `add_course`, `assign_teacher`, `add_schedule`, `approve_enrollment`, `deny_enrollment`, `approve_schedule`, `deny_schedule`. Then redirects back to the page the form came from.
- **Point at:** `:65` the switch start; `:156` `add_schedule` and its three conflict queries; `:240` `approve_schedule` and the transaction.
- **Modify (very likely):** "add a feature to the admin panel" = a `case` here + a form in `admin_dashboard.php` + a `source_page` value. That's the whole extension pattern, and it's worth saying out loud.
- **Weak spots to volunteer:** `approve_enrollment` uses `INSERT IGNORE` with **no limit check**, so an admin approval can push a student past the cap; `edit_user` calls `execute()` without `try/catch`, so a duplicate username crashes; `delete_user` relies on `ON DELETE CASCADE` to clean up enrollments and attendance.

#### `js/script.js` — the whole client side (261 lines)
- **Does:** toasts, the admin edit-modal fill, the teacher room search + booking modal + announcement delete, the student enroll/unenroll/request flow, delete confirmations, and a debounced conflict check.
- **Point at:** `:19` and `:253` — two handlers wrapped in `if (document.getElementById('adminTabContent'))`. **No page has that id** (the admin layout is `div.sidebar` + `div.main-content`). So the Edit-user modal never fills, and the live conflict warning never runs. This is the single most interesting code-reading answer you have: the feature looks present in the HTML (`#editUserModal`, `.edit-user-btn`, `#conflict-warning` all exist) but the binding is dead because of one stale selector.
- **Fix:** rename the guard to something that exists (e.g. `document.querySelector('.main-content')`) or add `id="adminTabContent"` to the admin container. Then the modal populates from the button's `data-*` attributes and `check_conflict.php` starts being used.
- **Modify (enrollment buttons):** `handleEnrollment()` builds `FormData` from `data-course-id`; add an action → add a `classList.contains()` branch and the matching service method.

#### `css/styles.css` — the theme (397 lines)
- **Does:** CSS custom properties for colour, radius, shadow, transition; restyles Bootstrap's card/table/form/button; sidebar layout; the fixed "toast" notification; the login card.
- **Point at:** `:root` — say "one place to re-theme; components only reference tokens."
- **Modify:** change the brand colour → one token (`--color-accent`). Dark mode → override the tokens under a `[data-theme="dark"]` selector; nothing else needs to change.
- **Bug to own:** `.btn-outline:hover` uses `var(--color-text-hover)`, which is never defined — that declaration silently drops. Either define it or use `--color-text-primary`.

#### `app/timetable.sql` — the schema dump (7 tables)
- **Does:** `CREATE TABLE` for `users`, `courses`, `rooms`, `schedules`, `enrollments`, `pending_enrollments`, `attendance`.
- **Point at:** it's the database design in one file — that's the artefact to show when he asks about normalization.
- **Two real problems to admit (they're good marks, not bad ones):**
  1. **No semicolons.** Every block ends with `)` and a blank line. Importing it as-is gives `ERROR 1064` and creates nothing.
  2. **Alphabetical order breaks the foreign keys.** `attendance` references `users` and `schedules` but is created first, so even with semicolons you get `errno 150`. It needs `SET FOREIGN_KEY_CHECKS=0;` at the top (and `=1` at the end).
  3. **Two tables are missing** that the code requires: `announcements` and `pending_schedules`. Without them the teacher dashboard and the admin schedule-requests page crash. `demo.sql` in the project root is the fixed, seeded replacement — that's the file to import.

---

### 3.2 Login and registration

#### `index.php` — the main sign-in page (61 lines)
- **Does:** starts bootstrap + CSRF, prints a role selector (student/teacher) and the credential form, links to register and admin login.
- **Point at:** the hidden `csrf_token` input; the `role` select — the role is part of the credential check, so the same username can't log in through the wrong door.
- **Modify:** add a "remember me" checkbox → needs a separate persistent-token table; do **not** just extend the session lifetime, because that's a security regression you'd have to defend.

#### `login.php` — an **unused duplicate** of the login page
- **Does:** the same job as `index.php` with different markup, and it loads `css/login.css`, which doesn't exist.
- **What to say:** "Dead page, left over from an earlier layout. `index.php` is the live one. I'd delete it so there aren't two login forms to keep in sync."
- **The two `.html` leftovers** belong to the same story, and they are *broken* rather than merely unused: `admin_login.html` posts to `login.php` (a display-only page), and `register.html` posts to `register.php` (also display-only, and with no CSRF token). Both were superseded by the PHP pages. Delete all three; don't try to defend them.

#### `login_process.php` — credential verification (46 lines)
- **Does:** the nine-step flow in §2.3.
- **Point at:** `:12` CSRF, `:26` brute-force check, `:28` the prepared `SELECT`, `:33` `password_verify`, `:35` `session_regenerate_id(true)`, `:36-38` the session writes.
- **Modify:** add a "must change password on first login" flag → an extra column, a session flag, and a guard in each dashboard.
- **Detail worth knowing:** it accepts `role=admin` in validation but the form never offers it, and admins use the separate page. So the branch is unreachable in practice.

#### `register.php` / `register_process.php` — self-registration (47 + 67 lines)
- **Does:** the form; then validation (required fields, email format, role must be student or teacher, username 3–50 `[a-zA-Z0-9_]`, password ≥ 6), `password_hash`, `INSERT`.
- **Point at:** the role restriction — **a user cannot self-register as admin**, which is the important security property here.
- **Modify:** add "confirm password" → one input, one comparison. Add a department field → column + bind + form.
- **Weak spot:** `$stmt->execute()` is outside a `try/catch` while `db_config` is in strict mode, so a duplicate username throws a fatal error with a stack trace instead of the friendly message in the `else` branch. `manage_admin_tasks.php` gets this right with `try/catch (mysqli_sql_exception)`. Same author, two standards — a strong answer about consistency.

#### `admin_login.php` / `admin_login_process.php` — the admin door (36 + 34 lines)
- **Does:** the form; then a query filtered `AND role='admin'`, `password_verify`, set `$_SESSION['admin_id']`, redirect.
- **Point at:** `:23` `$_SESSION['admin_id'] = $admin_id` — the separate session shape.
- **Also point at:** lines 2-3. This was the file's real defect: it used to do `session_start(); require_once 'db_config.php';` with no bootstrap, so it ignored `.env` and only worked on the compiled default credentials. It now starts with `require_once __DIR__ . '/app/bootstrap.php';`, which loads the session, the env file and the connection. Verified: with `root`/`root` supplied only through `.env`, login returns `302` to the dashboard.
- **Modify (the fix, if asked what you'd change):** add CSRF verification (the form already prints a token), set `user_id`/`role` as well so there's one identity model, and call `session_regenerate_id(true)` before writing the session. Four lines, and it removes the whole second identity system.

#### `logout.php` — 6 lines
- **Does:** `session_unset()`, `session_destroy()`, redirect to `index.php`.
- **Modify:** also expire the cookie (`setcookie(session_name(), '', time()-42000, '/')`) — otherwise the browser keeps sending a dead id. Good tiny answer if he asks "is logout complete?"

---

### 3.3 Student side

#### `student_dashboard.php` — the student home page (238 lines)
- **Does:** five Bootstrap tabs. Loads, in order: the detailed schedule, the schedule map, the enrollment count, the limit, the available courses, the enrolled list, attendance grouped by course, and announcements for the enrolled courses.
- **Point at:** `:37` `getDetailedSchedule` (four-table join), `:45` `AppConfig::maxCourses()` (the "6"), `:47` available courses, `:152` the limit warning, `:169-172` the three button states (Enroll / Request Approval / Conflict).
- **Modify (the fix everyone asks about):** make the button agree with the server → in `manage_enrollment.php` use `AppConfig::maxCourses()`, and set `MAX_COURSES=5` in `.env`. One line, both sides agree.
- **Modify:** add a "drop" confirmation reason → add a column and pass it through the AJAX call.
- **Weak spots:** the Request Approval button appears at the limit, but `requestApproval()` doesn't check the limit — it only checks duplicates and conflicts, which is arguably right but means the UI implies a rule the server doesn't enforce; and enrolling in an unscheduled course yields a row that appears under *My Courses* but not under *My Schedule* (the INNER JOIN).

#### `manage_enrollment.php` — the enrollment AJAX endpoint (30 lines)
- **Does:** JSON header; method must be POST; CSRF; must be a student; builds `EnrollmentController` → `EnrollmentService` → repositories; echoes the JSON result.
- **Point at:** `:26` the literal `5 // aligned with UI` — the bug in one line.
- **Modify:** change the cap → here **and** the UI's config, or better, call `AppConfig::maxCourses()`.
- **Note:** it does **not** set an HTTP error status on failure — the JSON says `success:false` with `200`. Say that as a design choice with a cost, not as an accident.

#### `profile.php` — profile and password form (74 lines)
- **Does:** student-only guard; prints username and email; renders the change-password form.
- **Point at:** `$email = $_SESSION['email'] ?? 'No email on file'` — but `login_process.php` never stores `email`. So **every** student sees "No email on file". That's a data-source bug with a one-line fix: query `users.email` by `$_SESSION['user_id']`.
- **Modify:** show enrolled course count or attendance percentage → one query in the PHP block and one line in the HTML.

#### `change_password_process.php` — password change (58 lines)
- **Does:** student guard, POST guard, CSRF, all fields required, new == confirm, verify current password, `password_hash` the new one, `UPDATE`.
- **Point at:** the order — you must prove the current password **before** writing. Good habit to name.
- **Modify:** enforce strength (length, classes) → add checks next to the mismatch check. Force re-login after change → `session_regenerate_id(true)` and update the stored hash check.
- **Gap:** teachers have no way to change their password, because the guard requires `role === 'student'`.

---

### 3.4 Teacher side

#### `teacher_dashboard.php` — the teacher home page (384 lines)
- **Does:** five tabs. Loads the teacher's courses, then in **three** batch queries (using `IN (?,?,...)` placeholders) the students, the schedules and the attendance for all those courses, then groups them in PHP. Also loads pending bookings, all rooms, and a booked-slot map.
- **Point at:** `:32` the dynamic `$placeholders` + `str_repeat('i', ...)` + `bind_param($types, ...$course_ids)` pattern — that's the "safe `IN` clause" idiom, and it's the most advanced SQL technique in the project. Also `:77` the announcements query, `:83` its `prepare()`, and `:103` the booked-slot map that colours the grid.
- **Modify:** add a "class roster download" → one more query or reuse `$course['students']`, then `header('Content-Type: text/csv')` and echo rows.
- **Weak spot:** it uses `announcements` and `pending_schedules`, which **don't exist** in the shipped SQL — so on a fresh import this page is a fatal error at `:83`. If your demo DB is built correctly, you'll never see it; know why.
- **Note:** `$weekdays` and `$timeslots` are re-declared literally at `:111-112`, shadowing the `DayOfWeek`/`TimeSlot` values imported at the top. That's the duplication behind the "add Saturday" question.

#### `mark_attendance.php` — attendance write (64 lines)
- **Does:** starts the session, requires a teacher, validates the posted fields, looks up the schedule's course, loads the enrolled students, then in one transaction upserts one row per student with `ON DUPLICATE KEY UPDATE status = VALUES(status)`, and redirects with a flash message.
- **Point at:** `:35` the transaction, `:38-39` the upsert, `:44` the per-student `bind_param`, `:49` commit / `:55` rollback.
- **Why the upsert matters:** `attendance` is unique on `(student_id, schedule_id, date)`, so re-submitting the same date **corrects** rather than duplicates. Good design to defend. On MySQL 8.0.20+ `VALUES(status)` is deprecated (warning 1287, observed on 8.0.46); the modern form is `INSERT ... AS new ON DUPLICATE KEY UPDATE status = new.status`.
- **Modify:** add a "late" status → change the `ENUM` and the radio buttons; the PHP needs no change.
- **Weak spots to volunteer:** no CSRF token; no ownership check (any teacher can post any `schedule_id`); and it writes to a legacy `attendance_date` column that nothing reads (the live column is `date`) — so the table has two date columns, one of them dead. The `.env`-bypass bug it used to share with admin login is fixed: the file now requires `app/bootstrap.php` instead of calling `session_start()` and `db_config.php` directly.

#### `check_conflict.php` — room-occupancy probe (23 lines)
- **Does:** JSON; if `room_id`, `day`, `timeslot` are present, returns `{conflict:bool, course?:string}`.
- **Point at:** the prepared statement — even a read-only endpoint uses bound parameters.
- **Reality:** it's only called from the dead admin JS branch, and it never loads `.env`, so in practice it's an orphan endpoint that (a) leaks course names to anyone and (b) fails on a non-default DB. Say: "It should require the admin session and include bootstrap."

#### `get_student_attendance.php` — attendance summary probe (27 lines)
- **Does:** JSON; returns counts grouped by `present`/`absent` for a `student_id` + `course_id`.
- **Reality:** **nothing in the app calls it.** It has no auth and no bootstrap. Honest line: "This is leftover from an earlier AJAX widget. It's unused, and if left deployed it lets anyone read any student's attendance counts. Deleting it is the fix; if it's needed, add the session check and derive `student_id` from `$_SESSION`."

---

### 3.5 Admin side

#### `admin_dashboard.php` — the admin panel (539 lines)
- **Does:** one file, seven pages chosen by `?page=`. Fetches `$users`, `$teachers`, `$students`, `$courses`, `$rooms` on **every** request, then page-specific data (`$schedule_data` for home, `$pending_requests`, `$pending_schedules` + per-request student-conflict counts, `$schedules`, `$enrollment_stats` + `$room_utilization`). Then a single `if/elseif` chain renders the chosen page.
- **Point at:** `:25` `$page = $_GET['page'] ?? 'home'` — the whole "routing" of the panel; `:42`, `:55`, `:66`, `:97`, `:107` the data blocks; `:151` onward the render chain; `:219` the `.edit-user-btn` markup; `:333` `#conflict-warning`.
- **Modify (likely ask):** add a page → one `if ($page === 'x')` data block, one `elseif`, one sidebar link. That is the pattern, and it's worth being able to do live.
- **Modify:** "show the teacher on the schedules table" → the join already fetches `teacher_name` on the home grid; the schedules table query at `:98` would need `LEFT JOIN users` and an extra `<th>`.
- **Weak spots:** no `id="adminTabContent"`, so the Edit modal and the conflict warning are dead; `$users/$teachers/$students/$courses/$rooms` are fetched even for pages that don't use them; and the page name comes from `$_GET` but is only ever compared against literals, so it can't be used for injection or file inclusion.

#### `room_dashboard.php` — room overview (65 lines)
- **Does:** admin-only; renders a room × day grid of booked/free slots.
- **Point at:** `include 'admin_sidebar.php'` — **that file does not exist**, so this page emits a warning and renders without a sidebar. It's also not linked from anywhere. Say: "Orphan page with a broken include; its content duplicates the teacher's booking grid. I'd delete it or wire it into the sidebar."
- **Modify:** add teacher names per slot → the query already `LEFT JOIN`s users; just print it.

#### `rooms_dashboard.php` / `teachers_dashboard.php` — 4-line redirect stubs
- **Does:** `302` to `admin_dashboard.php?page=schedules` and `?page=home`.
- **What to say:** "Deprecated overview pages, kept as redirects so old bookmarks don't 404. That's a reasonable way to retire a URL."

---

## Part 4 — Demo, modifications, and rehearsal

### 4.1 Make it run without Docker

**One import, one config file, done.** `demo.sql` in the project root is the whole setup.

1. Install XAMPP (or MAMP), start **Apache** and **MySQL**.
2. Copy the project folder into `htdocs/timetable`.
3. Open `http://localhost/phpmyadmin` → **New** → database name `timetable`, collation `utf8mb4_general_ci` → **Create**.
4. Select `timetable` → **Import** → **Choose File** → `demo.sql` → **Go**. One click; there is nothing to paste and nothing to edit.
5. Check the result: **9 tables**, `users` has **6 rows**. If the import errors with "table already exists", the database wasn't empty — drop it, create it again, re-import.

   Verified on **MySQL 8.0.46** (what MAMP ships) and **MariaDB 11.3.2** (what XAMPP ships): identical import, 9 tables, 6 users, no errors. MySQL 8 emits two harmless deprecation warnings while importing — `int(11)` display width, and a duplicate index on `attendance` (`student_id` and `idx_attendance_student` index the same column). Both come from the original dump, neither blocks the import.
6. `.env` — only needed if your credentials differ from the XAMPP defaults:
```
APP_DEBUG=0
DB_HOST=localhost
DB_NAME=timetable
DB_USER=root
DB_PASS=
DB_PORT=3306
```
   **On MAMP** the same block needs two changes: `DB_PASS=root` and `DB_PORT=8889` — MAMP's MySQL listens on 8889, not 3306. With the bootstrap fix below in place, those two lines are all the app needs; without it, admin login and attendance ignore this file entirely.

   `.env.example` in the project root is the same template with those MAMP values commented out, so `cp .env.example .env` then uncommenting two lines is the whole configuration step.
7. Open `http://localhost/timetable/index.php`.

**What `demo.sql` contains, and why it's one file:** the 7 tables from `app/timetable.sql` with the missing `;` terminators added (definitions unchanged), the two tables the code needs but the dump omits (`announcements`, `pending_schedules`), `SET FOREIGN_KEY_CHECKS=0/1` around the whole thing so the alphabetical order can't break the `attendance` foreign key, and seed data sized for the demo: 6 users, 3 rooms, 7 courses, 9 scheduled slots, 9 enrollments, 10 attendance rows, 3 announcements, 2 pending enrollments and 2 competing room bookings. Logins: `admin`/`admin1234`, everyone else `pass1234`.

The seed deliberately contains three live bugs so you can *show* them rather than describe them: **CS103 has no slot** (the invisible-course bug), **CS105 clashes with CS101** (a real `Conflict` button), and **stud_hana already has 5 courses** (the cap mismatch).

**The trap that used to be here, and is now fixed in this project.** `admin_login_process.php` and `mark_attendance.php` used to start with `session_start(); require_once 'db_config.php';` — no `app/bootstrap.php`, therefore no `.env`, therefore only `getenv()` and the compiled defaults. On XAMPP that was invisible (the defaults match); on **MAMP** (port 8889, password `root`) admin login died with **HTTP 500 "Database connection error."**. I reproduced exactly that, then changed both files to start with

```php
require_once __DIR__ . '/app/bootstrap.php'; //session, .env, db_config
```

which starts the session, loads `.env` and pulls in `db_config.php`. With that one line, admin login and attendance work from `.env` alone — verified against MySQL 8 with `root`/`root` and no environment variables set.

**Useful talking point:** two endpoints still bypass bootstrap — `check_conflict.php` and `get_student_attendance.php`. Neither is called by the app in normal use (one is called only by dead JS, the other by nothing), so they don't affect the demo, but they are the same defect and worth naming before he finds them.

**If you have PHP installed but no XAMPP:** `php -S localhost:8099 -t .` from the project folder, with MySQL running and the same database. No Apache needed for a demo.

### 4.2 The demo script (14 steps, about 15 minutes, in this order)

| # | Do this | Say this |
|---|---|---|
| 1 | Open `index.php` | "Three roles, one entry point. The role is part of the credential check, so the same username can't log in through the wrong door." |
| 2 | View source / inspect the password field | "Nothing is stored in plaintext — `password_hash` with bcrypt, verified with `password_verify`." |
| 3 | Log in as `stud_eyoas` | "This is the 302 redirect pattern: the POST handler never renders HTML, it sets the session and redirects." |
| 4 | Show the **My Schedule** tab | "Four tables joined: enrollments → courses → schedules → rooms. That's why the room name appears here." |
| 5 | **Enroll in New Course** → pick **CS106** | "The button is AJAX. CSRF token from the meta tag, `fetch` to `manage_enrollment.php`, and the service checks conflicts before the insert. It shows up in My Schedule only because CS106 actually has a slot." |
| 6 | Point at **CS105**, which shows a disabled **Conflict** button | "This is the conflict rule working backwards: CS105 is scheduled Monday 09:00, and I'm already in CS101 then, so the UI disables it. The check is an `EXISTS` subquery over my enrollments and the target course's slots." |
| 7 | Show **My Attendance** | "Percentages are computed in PHP from the rows the teacher submitted." |
| 8 | Log out, log in as `teach_abebe` | "Same session mechanism, different role." |
| 9 | **Take Attendance** → submit | "One transaction: either every student is marked, or none. Re-submitting the same date corrects the rows because of `ON DUPLICATE KEY UPDATE`." |
| 10 | **Book a Room** → pick a free cell (e.g. Lab-1, Friday 14:00) | "Three checks: you own the course, the room is free, you're not already teaching. Then it becomes a *pending* request — the teacher can't put themselves on the timetable. Note the grid paints it as booked immediately, even though nothing is committed yet; that's a UI shortcut worth fixing." |
| 11 | Log out, `admin` / `admin1234` | "The admin has a separate login and a separate session key; that's a design inconsistency I'd unify." |
| 12 | **Schedule Requests** → Approve the CS103 / R102 / Thursday row | "Two teachers are competing for the same slot in the seed. Inside one transaction: insert the schedule, mark this request approved, and auto-deny the other. Reload and the second row is `Denied`." |
| 13 | **Enrollment Requests** → Approve stud_hana's request | "She already had five courses; approving gives her six. The admin path skips the cap the student path enforces — that's the bug, not a feature." |
| 14 | **Reports** | "Enrollment counts per course and room utilization against 40 slots per week." |

If something breaks mid-demo, the honest line depends on the symptom: a **fatal on the teacher dashboard** means the database was imported from `app/timetable.sql` instead of `demo.sql` (the `announcements` table); a **500 on admin login** would now mean the `.env` values themselves are wrong, since those pages honour `.env` — check `DB_HOST`, `DB_USER`, `DB_PASS` and the port (MAMP's MySQL listens on **8889**, not 3306). Say which one it is, name the cause, move on. You lose nothing by knowing.

### 4.3 "How would you modify it?" — the table to memorise

| If he asks… | Files to touch | What else changes |
|---|---|---|
| Change the enrollment cap | `manage_enrollment.php` (use `AppConfig::maxCourses()`), `.env` (`MAX_COURSES`) | The dashboard warning text and button state follow automatically, because they read the same method |
| Add a "Saturday" class day | `app/Domain/DayOfWeek.php`, the three literal arrays in `teacher_dashboard.php`, `admin_dashboard.php`, `room_dashboard.php`, plus the `FIELD(...)` sorts | Every grid gains a column; the `UNIQUE (room, day, timeslot)` key needs no change |
| Add a new admin feature | `manage_admin_tasks.php` (new `case`), `admin_dashboard.php` (form + `source_page`) | Nothing else — that's the extension pattern |
| Show course credits | `admin_dashboard.php` (the data is already fetched at `:31`), one `<th>` and one `<td>` | No query change |
| Fix the dead Edit-user modal | `js/script.js:19` guard, or add `id="adminTabContent"` to `div.main-content` | The modal also needs the `data-*` attributes, which already exist |
| Make enrollment cap consistent | as row 1 | Also update §"Request Approval" logic if 5 is the hard policy |
| Add prerequisites | new `prerequisite_id` column, `EnrollmentService::enroll()` | The controller and JS need no change |
| Add an audit log for attendance | new table, write inside the existing transaction in `mark_attendance.php` | The read side is a new admin page |
| Let teachers book without approval | `manage_teacher_tasks.php:77` — insert into `schedules` instead of `pending_schedules` | The admin approval page loses its purpose; the UNIQUE key still protects you |
| Stop duplicate/overlapping pending bookings | `manage_teacher_tasks.php:77` — the room check queries only `schedules` | Add a check (or a unique key) against `pending_schedules`; today two requests for the same slot are both accepted |
| Split the app into MVC | the repository layer already shows the shape; move page queries into repositories, add a front controller | Big change: every URL changes, and `require` chains become a router |

### 4.4 Q&A drill (say these out loud once)

1. **"Walk me through a login."** → CSRF check, brute-force check, prepared `SELECT` by username+role, `password_verify`, `session_regenerate_id`, write `user_id`/`role`, 302.
2. **"Why a prepared statement?"** → the query is compiled before the data arrives, so input is never parsed as SQL. Every user value here is bound.
3. **"Where's the session stored?"** → on the server, in a session file; the browser only has a random `PHPSESSID` cookie.
4. **"Why regenerate the session id?"** → to defeat session fixation: an id known before login must not become an authenticated id.
5. **"What stops two bookings at once?"** → `UNIQUE (room_id, day_of_week, timeslot)` in the database; the PHP check is the friendly error, the constraint is the guarantee.
6. **"Why a transaction for attendance?"** → a class is marked fully or not at all; a mid-loop failure would leave half the roster wrong.
7. **"What's `ON DUPLICATE KEY UPDATE` for?"** → re-submitting attendance for the same date corrects instead of erroring, using the unique key. Its `VALUES()` form is deprecated on MySQL 8.0.20+ (warning 1287, observed on 8.0.46); the modern form aliases the inserted row.
8. **"Where do you check authorization?"** → role guard at the top of every page, plus ownership checks in `manage_teacher_tasks.php` (`WHERE ... AND teacher_id=?`).
9. **"Is any SQL injectable?"** → no: all user input is bound. The only string interpolation is in `IN (?,?,...)` placeholders, which are generated from a count, not from input.
10. **"What's the weakest part?"** → `mark_attendance.php`: no CSRF, no ownership check. Then `.env` sitting in the web root.
11. **"Why is a course missing from my schedule?"** → the schedule query uses INNER JOIN on `schedules`; a course with no scheduled slot drops out.
12. **"Why does Enroll fail at 5 courses?"** → the UI reads `MAX_COURSES` (default 6), the endpoint hard-codes 5.
13. **"What's the difference between `pending_enrollments` and `enrollments`?"** → a request is a queue row with a status; an enrollment is the real relation with a unique key.
14. **"Why two tables for room booking?"** → `pending_schedules` is the request queue; `schedules` is the committed timetable. A teacher proposes, an admin commits.
15. **"Where's the N+1?"** → avoided in `teacher_dashboard.php`: three batch queries + PHP grouping instead of one query per course.
16. **"What does `mysqli_report(STRICT)` change?"** → SQL errors throw exceptions instead of returning `false`, which unmakes every `if ($stmt->execute()) else` pattern.
17. **"How would you deploy this?"** → set `APP_DEBUG=0`, deny `.env` and `storage/`, force HTTPS, set `session.cookie_httponly`/`samesite`, and add the two missing tables to the schema.
18. **"Is this MVC?"** → not strictly: page-per-file, with a repository/service layer used only by enrollment.
19. **"Why no framework?"** → it keeps the stack to PHP+MySQL and shows the mechanics directly; the cost is duplicated plumbing, which is visible in `db_config.php` vs `app/Core/Database.php`.
20. **"What would you do first with another week?"** → unify authentication, fix the schema so a fresh clone installs, add CSRF + ownership to `mark_attendance.php`, and make the two caps read one constant.
21. **"What's the empty file?"** → `app/security_headers.php`; the headers moved into `bootstrap.php` and the file should be deleted.
22. **"Why does the profile say 'No email on file'?"** → `profile.php` reads `$_SESSION['email']`, which login never sets; it should query the `users` row.
23. **"Why would admin login fail on another machine?"** → It did: the page skipped bootstrap, so `.env` was ignored and only `getenv()` plus defaults counted — 500 on MAMP credentials. I changed it (and `mark_attendance.php`) to require `app/bootstrap.php`, and verified admin login returns 302 from `.env` alone against MySQL 8 with `root`/`root`.
24. **"Does a pending booking reserve the room?"** → No. The teacher-side check reads `schedules`, not `pending_schedules`, so duplicate pending requests can pile up; the admin's approval is what serialises them, backed by the `UNIQUE (room, day, timeslot)` key.
25. **"Where do errors go in production?"** → `bootstrap.php` logs them (`log_errors=1`) and never prints them: the non-debug branch sets `display_errors=0` explicitly, so a crash is a bare `500` plus a line in the server log, not a stack trace for the user.

### 4.5 The five-minute study plan

If you only have an hour, learn these five things in this order:

1. **The request path** (§2.1) — bootstrap → db_config → page → HTML / AJAX.
2. **The session and the three guards** (§2.3, §2.4) — `user_id`/`role`, redirect, `exit`.
3. **One prepared statement, one transaction, one UNIQUE key** (§2.6) — with the attendance flow as your example.
4. **The conflict rules** — room, course, teacher, and where each is checked.
5. **Three honest weaknesses** — attendance authorization, the enrollment cap mismatch, and the `.env` exposure. Volunteering these is worth more than being caught by them.

`PROJECT_REPORT.md` is the deep reference: §6 is the schema, §9 the authorization model, §10 sessions and CSRF, §15 the test plan and the runtime verification log. You don't need to read it end to end — use it when a question goes deeper than this guide.
