document.addEventListener('DOMContentLoaded', function() {

    // --- Admin Dashboard Functionality ---
    if (document.getElementById('adminTabContent')) {
        // Populates the Edit User modal with existing data
        $('.edit-user-btn').on('click', function() {
            $('#edit-user-id').val($(this).data('id'));
            $('#edit-username').val($(this).data('username'));
            $('#edit-email').val($(this).data('email'));
            $('#edit-role').val($(this).data('role'));
        });

        // Populates the Edit Course modal with existing data
        $('.edit-course-btn').on('click', function() {
            $('#edit-course-id').val($(this).data('id'));
            $('#edit-course-name').val($(this).data('name'));
            $('#edit-course-description').val($(this).data('description'));
            $('#edit-course-credits').val($(this).data('credits'));
        });

        // Live search/AJAX check for schedule conflicts
        $('.schedule-input').on('change', async function() {
            const roomId = $('select[name="room_id"]').val();
            const day = $('select[name="day_of_week"]').val();
            const timeslot = $('select[name="timeslot"]').val();
            const warningEl = $('#conflict-warning');
            
            if (!roomId || !day || !timeslot) {
                warningEl.html('');
                return;
            }
            
            try {
                const response = await fetch(`check_conflict.php?room_id=${roomId}&day=${day}&timeslot=${timeslot}`);
                const data = await response.json();
                
                if (data.conflict) {
                    warningEl.html(`⚠️ Conflict! This slot is booked for <strong>${data.course}</strong>.`);
                } else {
                    warningEl.html('');
                }
            } catch (error) {
                console.error('Error checking for conflicts:', error);
            }
        });
    }

    // --- Teacher Dashboard Functionality ---
    if (document.getElementById('teacherTabContent')) {
        // Live search for the room booking table
        const roomSearchInput = document.getElementById('roomSearchInput');
        if (roomSearchInput) {
            roomSearchInput.addEventListener('keyup', function() {
                const filter = roomSearchInput.value.toLowerCase();
                const table = document.getElementById('roomBookingTable');
                const rows = table.getElementsByTagName('tr');

                for (let i = 1; i < rows.length; i++) { // Start at 1 to skip table header
                    const roomNameCell = rows[i].getElementsByClassName('room-name')[0];
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

// --- Student Dashboard AJAX Functionality ---
const studentDashboard = document.getElementById('studentTabContent');
if (studentDashboard) {
    studentDashboard.addEventListener('click', function(e) {
        // Handle Enroll Button
        if (e.target && e.target.classList.contains('enroll-btn')) {
            const courseId = e.target.dataset.courseId;
            handleEnrollment('enroll', courseId);
        }
        // Handle Unenroll Button
        if (e.target && e.target.classList.contains('unenroll-btn')) {
            if (confirm('Are you sure you want to unenroll from this course?')) {
                const courseId = e.target.dataset.courseId;
                handleEnrollment('unenroll', courseId);
            }
        }
        // Handle Request Approval Button
        if (e.target && e.target.classList.contains('request-approval-btn')) {
            const courseId = e.target.dataset.courseId;
            handleEnrollment('request_approval', courseId);
        }
    });
}

// Reusable async function to handle all enrollment actions via AJAX
async function handleEnrollment(action, courseId) {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('course_id', courseId);

    try {
        const response = await fetch('manage_enrollment.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            if(action === 'request_approval') {
                alert('Your request has been sent to the administrator for approval.');
                // Optionally, disable the button after request
                document.querySelector(`[data-course-id="${courseId}"]`).disabled = true;
                document.querySelector(`[data-course-id="${courseId}"]`).textContent = 'Pending';
            } else {
                location.reload(); // Reload page to show other changes
            }
        } else {
            alert('An error occurred: ' + (result.message || 'Unknown error.'));
        }
    } catch (error) {
        console.error('AJAX Error:', error);
        alert('A network error occurred. Please try again.');
    }
}

    // --- General Functionality for All Dashboards ---
    // General confirmation for any form with the 'delete-form' class
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

});