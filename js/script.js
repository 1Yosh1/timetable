document.addEventListener('DOMContentLoaded', function() {



    /**
     * Displays a toast notification at the top-right of the screen.
     * @param {string} message The message to display.
     * @param {boolean} isSuccess True for a green success toast, false for a red error toast.
     */
    function showToast(message, isSuccess = true) {
        const toast = document.createElement('div');
        toast.className = `alert-toast ${isSuccess ? 'alert-success' : 'alert-danger'}`;
        toast.setAttribute('role', 'alert');
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => { toast.classList.add('fade-out'); toast.addEventListener('transitionend', () => toast.remove()); }, 5000);
    }

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
        const teacherTabContent = document.getElementById('teacherTabContent');
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

        $('#bookRoomModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget); 
            const roomId = button.data('room-id');
            const roomName = button.data('room-name');
            const day = button.data('day');
            const timeslot = button.data('timeslot');

            const modal = $(this);
            modal.find('#modal-room-name').text(roomName);
            modal.find('#modal-day').text(day);
            modal.find('#modal-timeslot').text(timeslot);
            
            modal.find('#modal-room-id').val(roomId);
            modal.find('#modal-form-day').val(day);
            modal.find('#modal-form-timeslot').val(timeslot);
            modal.find('#modal-course-id').val(''); // Reset dropdown
        });

        $('#bookRoomForm').on('submit', async function(event) {
            event.preventDefault();
            const form = $(this);
            const submitButton = form.find('button[type="submit"]');
            submitButton.prop('disabled', true).text('Booking...');

            const formData = new FormData(this);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            formData.append('csrf_token', csrf);

            try {
                const response = await fetch('manage_teacher_tasks.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    showToast(result.message || 'Room booking request submitted for admin approval!', true);
                    $('#bookRoomModal').modal('hide');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(result.message || 'Failed to book room.', false);
                }
            } catch (error) {
                console.error('Booking Error:', error);
                showToast('A network error occurred during booking.', false);
            } finally {
                submitButton.prop('disabled', false).text('Confirm & Book');
            }
        });

        teacherTabContent.addEventListener('click', async function(event) {
            if (event.target.matches('.delete-announcement-btn')) {
                const button = event.target;
                const announcementId = button.dataset.announcementId;

                if (!confirm('Are you sure you want to delete this announcement?')) {
                    return;
                }

                button.disabled = true;

                const formData = new FormData();
                formData.append('action', 'delete_announcement');
                formData.append('announcement_id', announcementId);
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                formData.append('csrf_token', csrf);

                try {
                    const response = await fetch('manage_teacher_tasks.php', { method: 'POST', body: formData });
                    const result = await response.json();

                    if (result.success) {
                        showToast(result.message, true);
                        button.closest('.announcement-item').remove();
                    } else {
                        showToast(result.message, false);
                        button.disabled = false;
                    }
                } catch (error) {
                    console.error('Delete announcement error:', error);
                    showToast('A network error occurred.', false);
                    button.disabled = false;
                }
            }
        });
    }

const studentDashboard = document.getElementById('studentTabContent');
if (studentDashboard) {
    studentDashboard.addEventListener('click', function(event) {
        const button = event.target;
        if (button.matches('.enroll-btn, .unenroll-btn, .request-approval-btn')) {
            if (button.classList.contains('unenroll-btn')) {
                if (!confirm('Are you sure you want to unenroll from this course?')) {
                    return;
                }
            }
            handleEnrollment(event);
        }
    });
}

async function handleEnrollment(event) {
    const button = event.target;
    const courseId = button.dataset.courseId;
    let action = '';

    if (button.classList.contains('enroll-btn')) action = 'enroll';
    else if (button.classList.contains('unenroll-btn')) action = 'unenroll';
    else if (button.classList.contains('request-approval-btn')) action = 'request_approval';

    if (!action) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const formData = new FormData();
    formData.append('action', action);
    formData.append('course_id', courseId);
    formData.append('csrf_token', csrf);

    button.disabled = true; // Disable button during request

    try {
        const response = await fetch('manage_enrollment.php', { method: 'POST', body: formData });
        const result = await response.json();

        if (result.success) {
            showToast(result.message || 'Action successful!', true);
            if (action === 'enroll') {
                button.textContent = 'Enrolled';
                button.classList.remove('btn-primary');
                button.classList.add('btn-success', 'disabled');
            } else if (action === 'unenroll') {
                button.closest('tr').remove();
            } else if (action === 'request_approval') {
                button.textContent = 'Pending';
                button.classList.add('disabled');
            }
        } else {
            showToast(result.message || 'An error occurred.', false);
            button.disabled = false; // Re-enable button on failure
        }
    } catch (error) {
        console.error('AJAX Error:', error);
        showToast('A network error occurred. Please try again.', false);
        button.disabled = false; // Re-enable button on network error
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

    // The toast that loads with the page (from PHP redirects)
    document.querySelectorAll('.alert-toast').forEach(toast => showToast(toast.textContent, toast.classList.contains('alert-success')));

});