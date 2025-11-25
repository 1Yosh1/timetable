document.addEventListener('DOMContentLoaded', function() {

    if (document.getElementById('adminTabContent')) {
        $('.edit-user-btn').on('click', function() {
            $('#edit-user-id').val($(this).data('id'));
            $('#edit-username').val($(this).data('username'));
            $('#edit-email').val($(this).data('email'));
            $('#edit-role').val($(this).data('role'));
        });

        $('.edit-course-btn').on('click', function() {
            $('#edit-course-id').val($(this).data('id'));
            $('#edit-course-name').val($(this).data('name'));
            $('#edit-course-description').val($(this).data('description'));
            $('#edit-course-credits').val($(this).data('credits'));
        });
    }

    if (document.getElementById('teacherTabContent')) {
        const roomSearchInput = document.getElementById('roomSearchInput');
        if (roomSearchInput) {
            roomSearchInput.addEventListener('keyup', function() {
                const filter = roomSearchInput.value.toLowerCase();
                const table = document.getElementById('roomBookingTable');
                const rows = table.getElementsByTagName('tr');

                for (let i = 1; i < rows.length; i++) { const roomNameCell = rows[i].getElementsByClassName('room-name')[0];
                    if (roomNameCell) {
                        const roomName = roomNameCell.textContent || roomNameCell.innerText;
                        if (roomName.toLowerCase().indexOf(filter) > -1) {
                            rows[i].style.display = "";
                        } else {
                            rows[i].style.display = "none";
                        }
                    }
                }
            });
        }
    }

const studentDashboard = document.getElementById('studentTabContent');
if (studentDashboard) {
    studentDashboard.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('enroll-btn')) {
            const courseId = e.target.dataset.courseId;
            handleEnrollment('enroll', courseId);
        }
        if (e.target && e.target.classList.contains('unenroll-btn')) {
            if (confirm('Are you sure you want to unenroll from this course?')) {
                const courseId = e.target.dataset.courseId;
                handleEnrollment('unenroll', courseId);
            }
        }
        if (e.target && e.target.classList.contains('request-approval-btn')) {
            const courseId = e.target.dataset.courseId;
            handleEnrollment('request_approval', courseId);
        }
    });
}

async function handleEnrollment(action, courseId) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const formData = new FormData();
    formData.append('action', action);
    formData.append('course_id', courseId);
    formData.append('csrf_token', csrf);

    try {
        const response = await fetch('manage_enrollment.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            if(action === 'request_approval') {
                alert('Your request has been sent to the administrator for approval.');
                document.querySelector(`[data-course-id="${courseId}"]`).disabled = true;
                document.querySelector(`[data-course-id="${courseId}"]`).textContent = 'Pending';
            } else {
                location.reload();}
        } else {
            alert('An error occurred: ' + (result.message || 'Unknown error.'));
        }
    } catch (error) {
        console.error('AJAX Error:', error);
        alert('A network error occurred. Please try again.');
    }
}

    const deleteForms = document.querySelectorAll('.delete-form');
    if (deleteForms.length > 0) {
        deleteForms.forEach(form => {
            form.addEventListener('submit', function(event) {
                if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                    event.preventDefault();
                }
            });
        });
    }

    function debounce(fn, ms=250) {
        let t; return (...args) => { clearTimeout(t); t = setTimeout(()=>fn(...args), ms); };
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    async function apiFetch(url, options = {}) {
        const merged = Object.assign({
            headers: { 'X-CSRF-Token': csrfToken }
        }, options);
        return fetch(url, merged);
    }

    async function runConflictCheck() {
        const roomId = document.querySelector('select[name="room_id"]')?.value;
        const day = document.querySelector('select[name="day_of_week"]')?.value;
        const timeslot = document.querySelector('select[name="timeslot"]')?.value;
        const warningEl = document.getElementById('conflict-warning');
        if (!warningEl) return;
        if (!roomId || !day || !timeslot) { warningEl.innerHTML = ''; return; }

        try {
            const resp = await apiFetch(`check_conflict.php?room_id=${roomId}&day=${encodeURIComponent(day)}&timeslot=${encodeURIComponent(timeslot)}`);
            const data = await resp.json();
            warningEl.innerHTML = data.conflict
                ? `⚠️ Conflict! This slot is booked for <strong>${data.course}</strong>.`
                : '';
        } catch (e) {
            console.error('Conflict check error', e);
        }
    }
    const debouncedConflict = debounce(runConflictCheck, 300);

    if (document.getElementById('adminTabContent')) {
        document.querySelectorAll('.schedule-input').forEach(el => {
            el.addEventListener('change', debouncedConflict);
        });
    }

});