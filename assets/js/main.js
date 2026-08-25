/**
 * Master JavaScript Client Controller
 * University Meeting Management System (UoH-MMS)
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Setup CSRF Token for all AJAX Fetch Requests
    const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfTokenMeta ? csrfTokenMeta.getAttribute('content') : '';

    window.appFetch = async (url, options = {}) => {
        options.headers = options.headers || {};
        if (csrfToken) {
            options.headers['X-CSRF-TOKEN'] = csrfToken;
        }
        return fetch(url, options);
    };

    // 2. Real-Time Meeting Conflict Detector
    const meetingForm = document.getElementById('meetingForm');
    const conflictBox = document.getElementById('conflictAlertContainer');
    const conflictList = document.getElementById('conflictMessagesList');

    if (meetingForm && conflictBox) {
        const checkConflictFields = ['meeting_date', 'start_time', 'end_time', 'room_id'];
        
        const triggerConflictCheck = debounce(async () => {
            const formData = new FormData(meetingForm);
            const date = formData.get('meeting_date');
            const start = formData.get('start_time');
            const end = formData.get('end_time');
            const roomId = formData.get('room_id');

            // Collect selected participants
            const participantCheckboxes = document.querySelectorAll('input[name="participants[]"]:checked');
            const participantIds = Array.from(participantCheckboxes).map(cb => cb.value);

            const ignoreMeetingId = formData.get('meeting_id') || '';

            if (!date || !start || !end) {
                conflictBox.classList.add('d-none');
                return;
            }

            try {
                const res = await window.appFetch(window.CHECK_CONFLICT_URL || '../meetings/check_conflicts.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        meeting_date: date,
                        start_time: start,
                        end_time: end,
                        room_id: roomId,
                        participant_ids: participantIds,
                        ignore_meeting_id: ignoreMeetingId
                    })
                });

                const data = await res.json();
                if (data.has_conflict && data.messages && data.messages.length > 0) {
                    conflictList.innerHTML = data.messages.map(m => `<li>${escapeHtml(m)}</li>`).join('');
                    conflictBox.classList.remove('d-none');
                } else {
                    conflictBox.classList.add('d-none');
                    conflictList.innerHTML = '';
                }
            } catch (err) {
                console.warn('Conflict check request failed:', err);
            }
        }, 400);

        checkConflictFields.forEach(fieldName => {
            const field = meetingForm.querySelector(`[name="${fieldName}"]`);
            if (field) {
                field.addEventListener('change', triggerConflictCheck);
                field.addEventListener('input', triggerConflictCheck);
            }
        });

        document.querySelectorAll('input[name="participants[]"]').forEach(cb => {
            cb.addEventListener('change', triggerConflictCheck);
        });
    }

    // 3. Dynamic Action Items Row Adder (for Minutes of Meeting)
    const addActionItemBtn = document.getElementById('addActionItemRowBtn');
    const actionItemsContainer = document.getElementById('actionItemsContainer');

    if (addActionItemBtn && actionItemsContainer) {
        addActionItemBtn.addEventListener('click', () => {
            const index = actionItemsContainer.children.length;
            const row = document.createElement('div');
            row.className = 'row g-2 mb-2 action-item-row align-items-center';
            row.innerHTML = `
                <div class="col-md-5">
                    <input type="text" name="action_items[${index}][task]" class="form-control form-control-sm" placeholder="Action item description..." required>
                </div>
                <div class="col-md-4">
                    <input type="text" name="action_items[${index}][assignee]" class="form-control form-control-sm" placeholder="Responsible Person / Dept" required>
                </div>
                <div class="col-md-2">
                    <input type="date" name="action_items[${index}][deadline]" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-row-btn" title="Remove">&times;</button>
                </div>
            `;
            actionItemsContainer.appendChild(row);

            row.querySelector('.remove-row-btn').addEventListener('click', () => {
                row.remove();
            });
        });
    }
});

// Helper: Debounce
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Helper: HTML Escaper
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
