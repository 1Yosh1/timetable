#!/usr/bin/env bash
# Smoke test — the assertions that matter for this application, in one command.
#
#   export BASE_URL=http://localhost/timetable      # or http://localhost:8099
#   bash demo/reset.sh                              # start from a known state
#   bash tests/smoke.sh
#
# Needs curl and grep only. It mutates the database (enrolls, marks attendance,
# approves a request), so run demo/reset.sh afterwards if you want the demo
# state back. Exit status is 0 only when every assertion passes.
set -uo pipefail

BASE_URL=${BASE_URL:-http://localhost/timetable}
BASE_URL=${BASE_URL%/}
JARS=$(mktemp -d)
trap 'rm -rf "$JARS"' EXIT
pass=0; fail=0

ok()   { pass=$((pass+1)); printf '  ok    %s\n' "$1"; }
bad()  { fail=$((fail+1)); printf '  FAIL  %s  (%s)\n' "$1" "$2"; }
warn() { printf '  warn  %s\n' "$1"; }

# code <jar> <path> -> HTTP status, body in $BODY
code() { curl -s -o "$JARS/body" -w '%{http_code}' -b "$JARS/$1" -c "$JARS/$1" "$BASE_URL/$2"; }
body() { cat "$JARS/body"; }
status_of() { curl -s -o /dev/null -w '%{http_code}' -b "$JARS/$1" -c "$JARS/$1" "$BASE_URL/$2"; }
# csrf <jar> <path> -> token rendered on that page (meta tag or hidden field)
csrf() {
  curl -s -b "$JARS/$1" -c "$JARS/$1" "$BASE_URL/$2" \
    | grep -oE '(name="csrf-token" content="|name="csrf_token" value=")[^"]+' \
    | head -1 | sed 's/.*"//'
}
post() { # post <jar> <path> <data...>
  local jar=$1 path=$2; shift 2
  curl -s -o "$JARS/body" -w '%{http_code}' -b "$JARS/$jar" -c "$JARS/$jar" \
       -d "$*" "$BASE_URL/$path"
}

echo "Smoke test against $BASE_URL"

# --- public pages -----------------------------------------------------------
[ "$(status_of pub index.php)" = "200" ] && ok "index.php renders" || bad "index.php renders" "$(status_of pub index.php)"

# --- student login and enrollment ------------------------------------------
t=$(csrf stud login.php)
[ -n "$t" ] || t=$(csrf stud index.php)
[ -n "$t" ] && ok "login page issues a CSRF token" || bad "login page issues a CSRF token" "no token found"
s=$(post stud login_process.php "csrf_token=$t&username=stud_eyoas&password=pass1234&role=student")
[ "$s" = "302" ] && ok "student login redirects" || bad "student login redirects" "HTTP $s"
[ "$(status_of stud student_dashboard.php)" = "200" ] && ok "student dashboard renders" || bad "student dashboard renders" "$(status_of stud student_dashboard.php)"

t=$(csrf stud student_dashboard.php)
[ -n "$t" ] && ok "student dashboard carries a CSRF token" || bad "student dashboard carries a CSRF token" "no token"
post stud manage_enrollment.php "csrf_token=$t&action=enroll&course_id=6" >/dev/null
r=$(body)
echo "$r" | grep -q '"success":true' && ok "student can enroll in an open course" || bad "student can enroll in an open course" "$r"

# --- enrollment limit: one number for UI and server ------------------------
h=$(csrf hana login.php)
[ -n "$h" ] || h=$(csrf hana index.php)
post hana login_process.php "csrf_token=$h&username=stud_hana&password=pass1234&role=student" >/dev/null
t=$(csrf hana student_dashboard.php)
post hana manage_enrollment.php "csrf_token=$t&action=enroll&course_id=7" >/dev/null
r=$(body)
echo "$r" | grep -q 'limit' && ok "at the cap the server refuses with the limit message" \
  || bad "at the cap the server refuses with the limit message" "$r"
curl -s -b "$JARS/hana" "$BASE_URL/student_dashboard.php" | grep -q "maximum of 5" \
  && ok "the dashboard states the same limit (5)" || bad "the dashboard states the same limit (5)" "text missing"

# --- attendance: CSRF and ownership ---------------------------------------
t=$(csrf tea login.php); [ -n "$t" ] || t=$(csrf tea index.php)
post tea login_process.php "csrf_token=$t&username=teach_abebe&password=pass1234&role=teacher" >/dev/null
[ "$(status_of tea teacher_dashboard.php)" = "200" ] && ok "teacher dashboard renders" || bad "teacher dashboard renders" "$(status_of tea teacher_dashboard.php)"

s=$(post tea mark_attendance.php "schedule_id=1&attendance_date=2026-01-05&attendance[4]=present")
[ "$s" = "400" ] && ok "attendance without a CSRF token is rejected (400)" || bad "attendance without a CSRF token is rejected (400)" "HTTP $s"

tk=$(csrf tea teacher_dashboard.php)
s=$(post tea mark_attendance.php "csrf_token=$tk&schedule_id=1&attendance_date=2026-01-05&attendance[4]=present")
[ "$s" = "302" ] && ok "the owning teacher can mark attendance" || bad "the owning teacher can mark attendance" "HTTP $s"

t2=$(csrf keb login.php); [ -n "$t2" ] || t2=$(csrf keb index.php)
post keb login_process.php "csrf_token=$t2&username=teach_kebede&password=pass1234&role=teacher" >/dev/null
tk2=$(csrf keb teacher_dashboard.php)
s=$(post keb mark_attendance.php "csrf_token=$tk2&schedule_id=1&attendance_date=2026-01-05&attendance[4]=present")
[ "$s" = "403" ] && ok "another teacher cannot mark that class (403)" || bad "another teacher cannot mark that class (403)" "HTTP $s"

# --- admin pages and the enrollment-approval cap ---------------------------
at=$(csrf adm admin_login.php)
[ -n "$at" ] || at=$(csrf adm index.php)
s=$(post adm admin_login_process.php "csrf_token=$at&username=admin&password=admin1234")
[ "$s" = "302" ] && ok "admin login redirects" || bad "admin login redirects" "HTTP $s"

for p in home users courses schedules requests schedule_requests reports; do
  c=$(status_of adm "admin_dashboard.php?page=$p")
  [ "$c" = "200" ] && ok "admin page '$p' renders" || bad "admin page '$p' renders" "HTTP $c"
done

# approving a request for a student already at the cap must be refused
curl -s -b "$JARS/adm" "$BASE_URL/admin_dashboard.php?page=requests" > "$JARS/req_before"
grep -q "stud_hana" "$JARS/req_before" && ok "stud_hana's pending request is listed" || bad "stud_hana's pending request is listed" "not on the page"
atk=$(grep -oE 'name="csrf_token" value="[^"]+' "$JARS/req_before" | head -1 | sed 's/.*"//')
post adm manage_admin_tasks.php "csrf_token=$atk&action=approve_enrollment&request_id=1" >/dev/null
curl -s -b "$JARS/adm" "$BASE_URL/admin_dashboard.php?page=requests" > "$JARS/req_after"
grep -q "stud_hana" "$JARS/req_after" && ok "approval above the cap is refused, request stays pending" \
  || bad "approval above the cap is refused, request stays pending" "the row disappeared"

# a request inside the cap still goes through
atk=$(grep -oE 'name="csrf_token" value="[^"]+' "$JARS/req_after" | head -1 | sed 's/.*"//')
post adm manage_admin_tasks.php "csrf_token=$atk&action=approve_enrollment&request_id=2" >/dev/null
curl -s -b "$JARS/adm" "$BASE_URL/admin_dashboard.php?page=requests" > "$JARS/req_last"
grep -q "stud_selam" "$JARS/req_last" && bad "approval below the cap succeeds" "row still pending" \
  || ok "approval below the cap succeeds"

# --- endpoints that used to be open ---------------------------------------
[ "$(status_of anon 'check_conflict.php?room_id=1&day=Monday&timeslot=09:00-10:00')" = "403" ] \
  && ok "check_conflict.php refuses anonymous callers (403)" || bad "check_conflict.php refuses anonymous callers (403)" "not 403"
[ "$(status_of anon get_student_attendance.php?student_id=1)" = "404" ] \
  && ok "the removed attendance endpoint is gone (404)" || bad "the removed attendance endpoint is gone (404)" "still reachable"

# --- refused deletion is a message, not a 500 ------------------------------
atk=$(csrf adm "admin_dashboard.php?page=users")
s=$(post adm manage_admin_tasks.php "csrf_token=$atk&action=delete_user&user_id=2")
[ "$s" = "302" ] && ok "deleting a teacher who owns courses returns a redirect, not a crash" \
  || bad "deleting a teacher who owns courses returns a redirect, not a crash" "HTTP $s"

# --- secrets are not downloadable (Apache with .htaccess only) ------------
ec=$(status_of anon .env)
[ "$ec" = "403" ] && ok ".env is not downloadable" \
  || warn ".env answered HTTP $ec — expected 403 under Apache with .htaccess (php -S ignores it)"

echo
printf '%d passed, %d failed\n' "$pass" "$fail"
[ "$fail" -eq 0 ]
