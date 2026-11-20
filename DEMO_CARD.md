# Demo card — one page, print this

Open at `http://localhost/timetable/index.php` (MAMP: port 8889 is only for MySQL, the site stays on 80).

| Role | Username | Password |
|---|---|---|
| Admin (*Admin Login* link) | `admin` | `admin1234` |
| Teacher | `teach_abebe` / `teach_kebede` | `pass1234` |
| Student | `stud_eyoas` / `stud_hana` / `stud_selam` | `pass1234` |

## The 14 steps

| # | Click | One sentence |
|---|---|---|
| 1 | Sign in as `stud_eyoas` | "Student dashboard: schedule, attendance, announcements." |
| 2 | Check the week grid | "Built from four joined tables — enrollment, course, slot, room." |
| 3 | Enroll in **CS106** | "Server re-checks clashes and the cap before inserting." |
| 4 | Enroll in **CS103** | "It accepts — then the course vanishes from the schedule. A course with no slot drops out of an INNER JOIN." |
| 5 | **CS105** | Disabled **Conflict** button: same Monday 09:00 as CS101. |
| 6 | Sign out → `stud_hana` | "She holds 5 courses, so she sees only *Request Approval* — the cap is one value now." |
| 7 | Sign out → `teach_abebe` | "Teacher view: courses, rosters, attendance, announcements." |
| 8 | **Take Attendance** → submit | "One transaction, one row per student, `ON DUPLICATE KEY UPDATE` so re-marking corrects." |
| 9 | **View Attendance** | "Per-student percentage, computed from the same rows." |
| 10 | **Announcements** → post one | "Ownership is in the WHERE clause, not just the session." |
| 11 | **Room booking** modal | "Conflicts checked live; the request goes to the admin as pending." |
| 12 | Sign out → **Admin Login** | "Seven pages, one file, `?page=` decides which." |
| 13 | **Schedule Requests** | "Approving one auto-denies the competitor in the same transaction." |
| 14 | **Enrollment Requests** → try `stud_hana`, then approve `stud_selam` | "Above the cap is refused; inside the cap succeeds. Used to be a bypass." |

Then **Reports** for enrollment counts and room utilization.

## Numbers to have ready

41 PHP files · 9 tables · 6 users · 7 courses · 9 slots · 15 PHP classes in `app/` · cap 5 · 5 attempts per 5 minutes before a login lockout.

## If something goes wrong

| Symptom | Cause | Fix |
|---|---|---|
| "The server requested authentication" / blank 500 | `.env` credentials wrong | `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_PORT` (MAMP: password `root`, port `8889`) |
| Missing table (`announcements`, `pending_schedules`) | old or partial dump | `bash demo/reset.sh` |
| Login says "Too many attempts" | brute-force limiter, 5 wrong passwords | `rm -f storage/bruteforce/*.json` |
| Page has no styling, tabs do nothing | no internet: Bootstrap/jQuery come from CDNs | say so and demo the tabs' content by scrolling; the server side is unaffected |
| Everything is dead | Apache down | `php -S localhost:8099 -t .` from the project folder, then open `:8099` |

## Reset between rehearsal and the real thing

```bash
bash demo/reset.sh          # drop + re-import demo.sql, clear lockouts
```

## Check it before he arrives

```bash
BASE_URL=http://localhost/timetable bash tests/smoke.sh
```

26 assertions: logins, enrollment cap, attendance CSRF and ownership, seven admin pages, approvals, `.env` not downloadable. Expected last line: `26 passed, 0 failed`.

## Six answers to have automatic

- **Where's the password stored?** `password_hash` (bcrypt), verified with `password_verify`; the `.env` file holds the *database* password, not user passwords.
- **Why prepared statements?** The query is compiled before the data arrives, so input is never parsed as SQL. Every user value is bound.
- **What stops a double booking?** `UNIQUE (room_id, day_of_week, timeslot)` in the database; PHP only supplies the friendly error.
- **Why regenerate the session id?** So an id known before login cannot become an authenticated id (session fixation).
- **Weakest part left?** Admin login has no CSRF token or session rotation, and self-registration is open as teacher. The attendance holes and `.env` are fixed.
- **What would you do next?** Migrations, one authentication model, and an audit log for attendance writes.
