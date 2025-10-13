<?php
require_once '../backend/includes/auth.php';
$user = requireAuth();
$pageTitle = 'Calendar';
include '../shared/templates/header.php';
?>

<link rel="stylesheet" href="assets/css/calendar.css">

<div class="calendar-container">
    <div class="calendar-header">
        <div class="calendar-title">
            <h1>Calendar</h1>
            <p class="text-muted">Manage your schedule and upcoming events</p>
        </div>
        <div class="calendar-actions">
            <button class="btn btn-secondary" onclick="exportCalendar()">
                <i class="fas fa-download"></i> Export
            </button>
            <?php if ($user['role'] === 'teacher' || $user['role'] === 'admin'): ?>
            <button class="btn btn-primary" onclick="showCreateEventModal()">
                <i class="fas fa-plus"></i> Create Event
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="calendar-controls">
        <div class="view-switcher">
            <button class="view-btn active" data-view="month">Month</button>
            <button class="view-btn" data-view="week">Week</button>
            <button class="view-btn" data-view="day">Day</button>
            <button class="view-btn" data-view="list">List</button>
        </div>
        
        <div class="calendar-nav">
            <button class="btn-icon" onclick="previousPeriod()">
                <i class="fas fa-chevron-left"></i>
            </button>
            <h2 id="currentPeriod"></h2>
            <button class="btn-icon" onclick="nextPeriod()">
                <i class="fas fa-chevron-right"></i>
            </button>
            <button class="btn btn-sm" onclick="goToToday()">Today</button>
        </div>
        
        <div class="event-filters">
            <select id="eventTypeFilter" onchange="filterEvents()">
                <option value="">All Types</option>
                <option value="class">Classes</option>
                <option value="assignment">Assignments</option>
                <option value="quiz">Quizzes</option>
                <option value="exam">Exams</option>
                <option value="meeting">Meetings</option>
                <option value="holiday">Holidays</option>
                <option value="other">Other</option>
            </select>
        </div>
    </div>

    <div class="calendar-main">
        <div class="calendar-sidebar">
            <div class="mini-calendar">
                <div class="mini-calendar-header">
                    <button onclick="previousMonth()" class="btn-icon-sm">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <span id="miniCalendarMonth"></span>
                    <button onclick="nextMonth()" class="btn-icon-sm">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <div id="miniCalendarGrid"></div>
            </div>

            <div class="upcoming-events">
                <h3>Upcoming Events</h3>
                <div id="upcomingEventsList"></div>
            </div>

            <div class="event-legend">
                <h4>Event Types</h4>
                <div class="legend-item">
                    <span class="legend-color" style="background: #3b82f6;"></span>
                    <span>Class</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color" style="background: #ef4444;"></span>
                    <span>Assignment</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color" style="background: #f59e0b;"></span>
                    <span>Quiz</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color" style="background: #8b5cf6;"></span>
                    <span>Exam</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color" style="background: #10b981;"></span>
                    <span>Meeting</span>
                </div>
            </div>
        </div>

        <div class="calendar-content">
            <div id="calendarView"></div>
        </div>
    </div>
</div>

 Create/Edit Event Modal 
<div id="eventModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="eventModalTitle">Create Event</h2>
            <button class="close-btn" onclick="closeEventModal()">&times;</button>
        </div>
        <form id="eventForm" onsubmit="saveEvent(event)">
            <input type="hidden" id="eventId">
            
            <div class="form-group">
                <label for="eventTitle">Title *</label>
                <input type="text" id="eventTitle" required>
            </div>

            <div class="form-group">
                <label for="eventDescription">Description</label>
                <textarea id="eventDescription" rows="3"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="eventType">Type *</label>
                    <select id="eventType" required>
                        <option value="class">Class</option>
                        <option value="meeting">Meeting</option>
                        <option value="exam">Exam</option>
                        <option value="holiday">Holiday</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="eventColor">Color</label>
                    <input type="color" id="eventColor" value="#3b82f6">
                </div>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="eventAllDay">
                    All Day Event
                </label>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="eventStart">Start Date & Time *</label>
                    <input type="datetime-local" id="eventStart" required>
                </div>

                <div class="form-group">
                    <label for="eventEnd">End Date & Time *</label>
                    <input type="datetime-local" id="eventEnd" required>
                </div>
            </div>

            <div class="form-group">
                <label for="eventLocation">Location</label>
                <input type="text" id="eventLocation" placeholder="Room 101, Online, etc.">
            </div>

            <div class="form-group">
                <label for="eventCourse">Course (Optional)</label>
                <select id="eventCourse">
                    <option value="">None</option>
                </select>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEventModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Event</button>
            </div>
        </form>
    </div>
</div>

 Event Details Modal 
<div id="eventDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="eventDetailsTitle"></h2>
            <button class="close-btn" onclick="closeEventDetailsModal()">&times;</button>
        </div>
        <div class="event-details-content">
            <div class="event-detail-row">
                <i class="fas fa-clock"></i>
                <div>
                    <strong>Time</strong>
                    <p id="eventDetailsTime"></p>
                </div>
            </div>
            <div class="event-detail-row" id="eventDetailsLocationRow" style="display: none;">
                <i class="fas fa-map-marker-alt"></i>
                <div>
                    <strong>Location</strong>
                    <p id="eventDetailsLocation"></p>
                </div>
            </div>
            <div class="event-detail-row" id="eventDetailsCourseRow" style="display: none;">
                <i class="fas fa-book"></i>
                <div>
                    <strong>Course</strong>
                    <p id="eventDetailsCourse"></p>
                </div>
            </div>
            <div class="event-detail-row" id="eventDetailsDescRow" style="display: none;">
                <i class="fas fa-align-left"></i>
                <div>
                    <strong>Description</strong>
                    <p id="eventDetailsDescription"></p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeEventDetailsModal()">Close</button>
            <button class="btn btn-danger" onclick="deleteEventConfirm()" id="deleteEventBtn" style="display: none;">
                <i class="fas fa-trash"></i> Delete
            </button>
            <button class="btn btn-primary" onclick="editEvent()" id="editEventBtn" style="display: none;">
                <i class="fas fa-edit"></i> Edit
            </button>
        </div>
    </div>
</div>

<script src="assets/js/calendar.js"></script>

<?php include '../shared/templates/footer.php'; ?>
