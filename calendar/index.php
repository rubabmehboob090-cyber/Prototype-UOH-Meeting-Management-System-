<?php
/**
 * University Master Meeting & Venue Calendar
 * University Meeting Management System
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/includes/auth_check.php';
require_once APP_ROOT . '/includes/permissions.php';

$pdo = Database::getConnection();
$rooms = $pdo->query("SELECT id, name, building FROM rooms WHERE status != 'unavailable' ORDER BY name ASC")->fetchAll();
$departments = $pdo->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$pageTitle = "University Calendar";
include APP_ROOT . '/includes/header.php';
?>

<!-- FullCalendar CSS and JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-primary mb-1"><i class="bi bi-calendar-range me-2"></i> Master Meeting & Venue Calendar</h4>
        <p class="text-muted mb-0 fs-7">Real-time visual schedule of university meetings, departmental sessions, and room reservations.</p>
    </div>
    <?php if (has_permission('meetings.create')): ?>
        <a href="<?= BASE_URL ?>/meetings/create.php" class="btn btn-primary btn-sm fw-semibold shadow-sm">
            <i class="bi bi-plus-circle me-1"></i> Request Meeting
        </a>
    <?php endif; ?>
</div>

<div class="row g-4 mb-4">
    <!-- Filter Controls -->
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3">
                <div class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <label class="form-label fs-8 text-uppercase fw-bold text-muted mb-1">Filter by Venue / Room</label>
                        <select id="calRoomFilter" class="form-select form-select-sm">
                            <option value="">-- All Rooms & Venues --</option>
                            <?php foreach ($rooms as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= e($r['name']) ?> (<?= e($r['building']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fs-8 text-uppercase fw-bold text-muted mb-1">Filter by Department</label>
                        <select id="calDeptFilter" class="form-select form-select-sm">
                            <option value="">-- All Departments --</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex gap-2 align-items-end pt-3 pt-md-0">
                        <div class="d-flex gap-2 align-items-center fs-8 flex-wrap">
                            <span class="d-inline-flex align-items-center gap-1"><span class="badge bg-primary p-1 rounded-circle" style="width: 10px; height: 10px;"></span> Approved</span>
                            <span class="d-inline-flex align-items-center gap-1"><span class="badge bg-warning p-1 rounded-circle" style="width: 10px; height: 10px;"></span> Pending</span>
                            <span class="d-inline-flex align-items-center gap-1"><span class="badge bg-success p-1 rounded-circle" style="width: 10px; height: 10px;"></span> Completed</span>
                            <span class="d-inline-flex align-items-center gap-1"><span class="badge bg-secondary p-1 rounded-circle" style="width: 10px; height: 10px;"></span> Blocked</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendar Canvas -->
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div id="calendar" style="min-height: 650px;"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const roomFilter = document.getElementById('calRoomFilter');
    const deptFilter = document.getElementById('calDeptFilter');

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
        },
        themeSystem: 'bootstrap5',
        events: function(info, successCallback, failureCallback) {
            const url = new URL('<?= BASE_URL ?>/calendar/events.php', window.location.origin);
            url.searchParams.append('start', info.startStr.split('T')[0]);
            url.searchParams.append('end', info.endStr.split('T')[0]);
            if (roomFilter.value) url.searchParams.append('room_id', roomFilter.value);
            if (deptFilter.value) url.searchParams.append('department_id', deptFilter.value);

            fetch(url)
                .then(res => res.json())
                .then(data => successCallback(data))
                .catch(err => failureCallback(err));
        },
        eventClick: function(info) {
            if (info.event.url) {
                info.jsEvent.preventDefault();
                window.location.href = info.event.url;
            }
        }
    });

    calendar.render();

    roomFilter.addEventListener('change', () => calendar.refetchEvents());
    deptFilter.addEventListener('change', () => calendar.refetchEvents());
});
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
