let currentDate = new Date()
let currentView = "month"
let events = []
let selectedEventId = null

document.addEventListener("DOMContentLoaded", () => {
  initializeCalendar()
  loadEvents()
  loadUpcomingEvents()
  loadCourses()

  // View switcher
  document.querySelectorAll(".view-btn").forEach((btn) => {
    btn.addEventListener("click", function () {
      document.querySelectorAll(".view-btn").forEach((b) => b.classList.remove("active"))
      this.classList.add("active")
      currentView = this.dataset.view
      renderCalendar()
    })
  })
})

function initializeCalendar() {
  updatePeriodDisplay()
  renderMiniCalendar()
}

function loadEvents() {
  const start = getMonthStart(currentDate)
  const end = getMonthEnd(currentDate)
  const type = document.getElementById("eventTypeFilter").value

  let url = `/backend/api/calendar.php?action=list&start=${start}&end=${end}`
  if (type) url += `&type=${type}`

  fetch(url)
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        events = data.events
        renderCalendar()
        renderMiniCalendar()
      }
    })
    .catch((err) => console.error("Error loading events:", err))
}

function loadUpcomingEvents() {
  fetch("/backend/api/calendar.php?action=upcoming&limit=5")
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        renderUpcomingEvents(data.events)
      }
    })
}

function loadCourses() {
  fetch("/backend/api/courses.php?action=my-courses")
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        const select = document.getElementById("eventCourse")
        data.courses.forEach((course) => {
          const option = document.createElement("option")
          option.value = course.id
          option.textContent = course.title
          select.appendChild(option)
        })
      }
    })
}

function renderCalendar() {
  const container = document.getElementById("calendarView")

  switch (currentView) {
    case "month":
      renderMonthView(container)
      break
    case "week":
      renderWeekView(container)
      break
    case "day":
      renderDayView(container)
      break
    case "list":
      renderListView(container)
      break
  }
}

function renderMonthView(container) {
  const year = currentDate.getFullYear()
  const month = currentDate.getMonth()
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)
  const startDate = new Date(firstDay)
  startDate.setDate(startDate.getDate() - startDate.getDay())

  let html = '<div class="calendar-month">'

  // Day headers
  const days = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"]
  days.forEach((day) => {
    html += `<div class="calendar-day-header">${day}</div>`
  })

  // Calendar days
  const currentDateObj = new Date(startDate)
  for (let i = 0; i < 42; i++) {
    const isCurrentMonth = currentDateObj.getMonth() === month
    const isToday = isSameDay(currentDateObj, new Date())
    const dayEvents = getEventsForDay(currentDateObj)

    html += `<div class="calendar-day ${!isCurrentMonth ? "other-month" : ""} ${isToday ? "today" : ""}">`
    html += `<div class="day-number">${currentDateObj.getDate()}</div>`
    html += '<div class="day-events">'

    dayEvents.slice(0, 3).forEach((event) => {
      const eventType = event.event_type || "other"
      html += `<div class="event-item type-${eventType}" onclick="showEventDetails(${event.id})" style="border-color: ${event.color}">`
      html += `${event.title}`
      html += "</div>"
    })

    if (dayEvents.length > 3) {
      html += `<div class="event-more">+${dayEvents.length - 3} more</div>`
    }

    html += "</div></div>"
    currentDateObj.setDate(currentDateObj.getDate() + 1)
  }

  html += "</div>"
  container.innerHTML = html
}

function renderWeekView(container) {
  const currentDate = calendarState.currentDate;
  const startOfWeek = new Date(currentDate);
  startOfWeek.setDate(currentDate.getDate() - currentDate.getDay());
  
  const weekEvents = calendarState.events.filter(event => {
    const eventDate = new Date(event.start_date || event.start_time);
    return eventDate >= startOfWeek && eventDate < new Date(startOfWeek.getTime() + 7 * 24 * 60 * 60 * 1000);
  });
  
  let html = `
    <div class="calendar-week">
      <div class="week-header">
        <div class="week-nav">
          <button onclick="navigateWeek(-1)" class="btn btn-sm">
            <i class="fas fa-chevron-left"></i> Previous Week
          </button>
          <h3>Week of ${formatDate(startOfWeek)}</h3>
          <button onclick="navigateWeek(1)" class="btn btn-sm">
            Next Week <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
      <div class="week-grid">
        <div class="time-column"></div>
  `;
  
  // Add day headers
  for (let i = 0; i < 7; i++) {
    const dayDate = new Date(startOfWeek.getTime() + i * 24 * 60 * 60 * 1000);
    const isToday = dayDate.toDateString() === new Date().toDateString();
    const isCurrentMonth = dayDate.getMonth() === currentDate.getMonth();
    
    html += `
      <div class="day-column ${isToday ? 'today' : ''} ${!isCurrentMonth ? 'other-month' : ''}">
        <div class="day-header">
          <div class="day-name">${dayDate.toLocaleDateString('en', { weekday: 'short' })}</div>
          <div class="day-number">${dayDate.getDate()}</div>
        </div>
        <div class="day-events" data-date="${dayDate.toISOString().split('T')[0]}">
    `;
    
    // Add events for this day
    const dayEvents = weekEvents.filter(event => {
      const eventDate = new Date(event.start_date || event.start_time);
      return eventDate.toDateString() === dayDate.toDateString();
    });
    
    dayEvents.forEach(event => {
      const startTime = new Date(event.start_time || event.start_date).toLocaleTimeString('en', { hour: '2-digit', minute: '2-digit' });
      const endTime = event.end_time ? new Date(event.end_time).toLocaleTimeString('en', { hour: '2-digit', minute: '2-digit' }) : '';
      
      html += `
        <div class="event-item ${event.type}" onclick="editEvent(${event.id})" title="${event.title}">
          <div class="event-time">${startTime}${endTime ? '-' + endTime : ''}</div>
          <div class="event-title">${event.title}</div>
        </div>
      `;
    });
    
    html += `
        </div>
      </div>
    `;
  }
  
  html += `
      </div>
      <div class="week-footer">
        <button onclick="createEvent()" class="btn btn-primary btn-sm">
          <i class="fas fa-plus"></i> Add Event
        </button>
      </div>
    </div>
  `;
  
  container.innerHTML = html;
  addEventListeners();
}
}

function renderDayView(container) {
  const currentDate = calendarState.currentDate;
  const dayEvents = calendarState.events.filter(event => {
    const eventDate = new Date(event.start_date || event.start_time);
    return eventDate.toDateString() === currentDate.toDateString();
  });
  
  const isToday = currentDate.toDateString() === new Date().toDateString();
  
  let html = `
    <div class="calendar-day-view">
      <div class="day-header">
        <div class="day-nav">
          <button onclick="navigateDay(-1)" class="btn btn-sm">
            <i class="fas fa-chevron-left"></i> Previous Day
          </button>
          <div class="day-title">
            <h3>${currentDate.toLocaleDateString('en', { 
              weekday: 'long', 
              year: 'numeric', 
              month: 'long', 
              day: 'numeric' 
            })}</h3>
            ${isToday ? '<span class="today-badge">Today</span>' : ''}
          </div>
          <button onclick="navigateDay(1)" class="btn btn-sm">
            Next Day <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
      
      <div class="day-timeline">
        <div class="timeline-sidebar">
          ${Array.from({length: 24}, (_, i) => `
            <div class="timeline-hour">${i.toString().padStart(2, '0')}:00</div>
          `).join('')}
        </div>
        
        <div class="timeline-content">
          <div class="timeline-grid">
            ${Array.from({length: 24}, (_, i) => `
              <div class="timeline-hour-row" data-hour="${i}"></div>
            `).join('')}
            
            ${dayEvents.map(event => {
              const startTime = new Date(event.start_time || event.start_date);
              const endTime = new Date(event.end_time || event.start_time);
              const duration = (endTime - startTime) / (1000 * 60 * 60); // hours
              const top = startTime.getHours() * 60 + startTime.getMinutes();
              
              return `
                <div class="timeline-event ${event.type}" 
                     style="top: ${top}px; height: ${duration * 60}px;"
                     onclick="editEvent(${event.id})"
                     title="${event.title}">
                  <div class="event-content">
                    <div class="event-time">
                      ${startTime.toLocaleTimeString('en', { hour: '2-digit', minute: '2-digit' })}
                      ${endTime > startTime ? '-' + endTime.toLocaleTimeString('en', { hour: '2-digit', minute: '2-digit' }) : ''}
                    </div>
                    <div class="event-title">${event.title}</div>
                    ${event.description ? `<div class="event-description">${event.description}</div>` : ''}
                  </div>
                </div>
              `;
            }).join('')}
          </div>
        </div>
      </div>
      
      <div class="day-footer">
        <div class="events-summary">
          <span class="events-count">${dayEvents.length} event${dayEvents.length !== 1 ? 's' : ''}</span>
        </div>
        <div class="day-actions">
          <button onclick="createEvent()" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Event
          </button>
          <button onclick="viewFullDay()" class="btn btn-secondary btn-sm">
            <i class="fas fa-list"></i> List View
          </button>
        </div>
      </div>
    </div>
  `;
  
  container.innerHTML = html;
  addEventListeners();
}
}

function renderListView(container) {
  const sortedEvents = [...events].sort((a, b) => new Date(a.start_datetime) - new Date(b.start_datetime))

  let html = '<div class="calendar-list">'
  let currentDateStr = ""

  sortedEvents.forEach((event) => {
    const eventDate = new Date(event.start_datetime)
    const dateStr = eventDate.toLocaleDateString("en-US", {
      weekday: "long",
      year: "numeric",
      month: "long",
      day: "numeric",
    })

    if (dateStr !== currentDateStr) {
      if (currentDateStr) html += "</div>"
      html += `<div class="list-date-group">`
      html += `<div class="list-date-header">${dateStr}</div>`
      currentDateStr = dateStr
    }

    const timeStr = eventDate.toLocaleTimeString("en-US", {
      hour: "numeric",
      minute: "2-digit",
    })

    html += `<div class="list-event-item" onclick="showEventDetails(${event.id})">`
    html += `<div class="list-event-title">${event.title}</div>`
    html += `<div class="list-event-meta">`
    html += `<span><i class="fas fa-clock"></i> ${timeStr}</span>`
    if (event.course_title) {
      html += `<span><i class="fas fa-book"></i> ${event.course_title}</span>`
    }
    html += `</div></div>`
  })

  if (currentDateStr) html += "</div>"
  html += "</div>"

  container.innerHTML = html || '<p class="text-center text-muted">No events found</p>'
}

function renderMiniCalendar() {
  const year = currentDate.getFullYear()
  const month = currentDate.getMonth()
  const firstDay = new Date(year, month, 1)
  const startDate = new Date(firstDay)
  startDate.setDate(startDate.getDate() - startDate.getDay())

  document.getElementById("miniCalendarMonth").textContent = currentDate.toLocaleDateString("en-US", {
    month: "long",
    year: "numeric",
  })

  let html = ""
  const days = ["S", "M", "T", "W", "T", "F", "S"]
  days.forEach((day) => {
    html += `<div class="mini-day" style="font-weight: 600; cursor: default;">${day}</div>`
  })

  const currentDateObj = new Date(startDate)
  for (let i = 0; i < 42; i++) {
    const isToday = isSameDay(currentDateObj, new Date())
    const hasEvents = getEventsForDay(currentDateObj).length > 0

    html += `<div class="mini-day ${isToday ? "today" : ""} ${hasEvents ? "has-event" : ""}">`
    html += currentDateObj.getDate()
    html += "</div>"

    currentDateObj.setDate(currentDateObj.getDate() + 1)
  }

  document.getElementById("miniCalendarGrid").innerHTML = html
}

function renderUpcomingEvents(upcomingEvents) {
  const container = document.getElementById("upcomingEventsList")

  if (upcomingEvents.length === 0) {
    container.innerHTML = '<p class="text-muted">No upcoming events</p>'
    return
  }

  let html = ""
  upcomingEvents.forEach((event) => {
    const eventDate = new Date(event.start_datetime)
    const timeStr = eventDate.toLocaleString("en-US", {
      month: "short",
      day: "numeric",
      hour: "numeric",
      minute: "2-digit",
    })

    html += `<div class="upcoming-event-item" onclick="showEventDetails(${event.id})" style="border-color: ${event.color}">`
    html += `<div class="upcoming-event-title">${event.title}</div>`
    html += `<div class="upcoming-event-time">${timeStr}</div>`
    html += "</div>"
  })

  container.innerHTML = html
}

function getEventsForDay(date) {
  return events.filter((event) => {
    const eventStart = new Date(event.start_datetime)
    return isSameDay(eventStart, date)
  })
}

function isSameDay(date1, date2) {
  return (
    date1.getFullYear() === date2.getFullYear() &&
    date1.getMonth() === date2.getMonth() &&
    date1.getDate() === date2.getDate()
  )
}

function getMonthStart(date) {
  return new Date(date.getFullYear(), date.getMonth(), 1).toISOString().split("T")[0]
}

function getMonthEnd(date) {
  return new Date(date.getFullYear(), date.getMonth() + 1, 0).toISOString().split("T")[0]
}

function updatePeriodDisplay() {
  const periodText = currentDate.toLocaleDateString("en-US", {
    month: "long",
    year: "numeric",
  })
  document.getElementById("currentPeriod").textContent = periodText
}

function previousPeriod() {
  currentDate.setMonth(currentDate.getMonth() - 1)
  updatePeriodDisplay()
  loadEvents()
}

function nextPeriod() {
  currentDate.setMonth(currentDate.getMonth() + 1)
  updatePeriodDisplay()
  loadEvents()
}

function goToToday() {
  currentDate = new Date()
  updatePeriodDisplay()
  loadEvents()
}

function previousMonth() {
  currentDate.setMonth(currentDate.getMonth() - 1)
  renderMiniCalendar()
}

function nextMonth() {
  currentDate.setMonth(currentDate.getMonth() + 1)
  renderMiniCalendar()
}

function filterEvents() {
  loadEvents()
}

function showCreateEventModal() {
  document.getElementById("eventModalTitle").textContent = "Create Event"
  document.getElementById("eventForm").reset()
  document.getElementById("eventId").value = ""
  document.getElementById("eventModal").style.display = "flex"
}

function closeEventModal() {
  document.getElementById("eventModal").style.display = "none"
}

function saveEvent(e) {
  e.preventDefault()

  const eventData = {
    id: document.getElementById("eventId").value,
    title: document.getElementById("eventTitle").value,
    description: document.getElementById("eventDescription").value,
    event_type: document.getElementById("eventType").value,
    start_datetime: document.getElementById("eventStart").value,
    end_datetime: document.getElementById("eventEnd").value,
    location: document.getElementById("eventLocation").value,
    course_id: document.getElementById("eventCourse").value || null,
    is_all_day: document.getElementById("eventAllDay").checked,
    color: document.getElementById("eventColor").value,
  }

  const method = eventData.id ? "PUT" : "POST"

  fetch("/backend/api/calendar.php", {
    method: method,
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(eventData),
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        closeEventModal()
        loadEvents()
        loadUpcomingEvents()
        alert(data.message)
      } else {
        alert("Error: " + data.error)
      }
    })
    .catch((err) => {
      console.error("Error saving event:", err)
      alert("Failed to save event")
    })
}

function showEventDetails(eventId) {
  fetch(`/backend/api/calendar.php?id=${eventId}`)
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        const event = data.event
        selectedEventId = eventId

        document.getElementById("eventDetailsTitle").textContent = event.title

        const startDate = new Date(event.start_datetime)
        const endDate = new Date(event.end_datetime)
        document.getElementById("eventDetailsTime").textContent =
          `${startDate.toLocaleString()} - ${endDate.toLocaleString()}`

        if (event.location) {
          document.getElementById("eventDetailsLocation").textContent = event.location
          document.getElementById("eventDetailsLocationRow").style.display = "flex"
        } else {
          document.getElementById("eventDetailsLocationRow").style.display = "none"
        }

        if (event.course_title) {
          document.getElementById("eventDetailsCourse").textContent = event.course_title
          document.getElementById("eventDetailsCourseRow").style.display = "flex"
        } else {
          document.getElementById("eventDetailsCourseRow").style.display = "none"
        }

        if (event.description) {
          document.getElementById("eventDetailsDescription").textContent = event.description
          document.getElementById("eventDetailsDescRow").style.display = "flex"
        } else {
          document.getElementById("eventDetailsDescRow").style.display = "none"
        }

        // Show edit/delete buttons if user is creator
        if (!event.is_deadline) {
          document.getElementById("editEventBtn").style.display = "inline-block"
          document.getElementById("deleteEventBtn").style.display = "inline-block"
        } else {
          document.getElementById("editEventBtn").style.display = "none"
          document.getElementById("deleteEventBtn").style.display = "none"
        }

        document.getElementById("eventDetailsModal").style.display = "flex"
      }
    })
}

function closeEventDetailsModal() {
  document.getElementById("eventDetailsModal").style.display = "none"
  selectedEventId = null
}

function editEvent() {
  if (!selectedEventId) return

  fetch(`/backend/api/calendar.php?id=${selectedEventId}`)
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        const event = data.event

        document.getElementById("eventModalTitle").textContent = "Edit Event"
        document.getElementById("eventId").value = event.id
        document.getElementById("eventTitle").value = event.title
        document.getElementById("eventDescription").value = event.description || ""
        document.getElementById("eventType").value = event.event_type
        document.getElementById("eventStart").value = event.start_datetime.replace(" ", "T")
        document.getElementById("eventEnd").value = event.end_datetime.replace(" ", "T")
        document.getElementById("eventLocation").value = event.location || ""
        document.getElementById("eventCourse").value = event.course_id || ""
        document.getElementById("eventAllDay").checked = event.is_all_day == 1
        document.getElementById("eventColor").value = event.color

        closeEventDetailsModal()
        document.getElementById("eventModal").style.display = "flex"
      }
    })
}

function deleteEventConfirm() {
  if (!selectedEventId) return

  if (confirm("Are you sure you want to delete this event?")) {
    fetch("/backend/api/calendar.php", {
      method: "DELETE",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: selectedEventId }),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          closeEventDetailsModal()
          loadEvents()
          loadUpcomingEvents()
          alert(data.message)
        } else {
          alert("Error: " + data.error)
        }
      })
  }
}

function exportCalendar() {
  window.location.href = "/backend/api/calendar.php?action=export"
}

// Week navigation functions
function navigateWeek(direction) {
  calendarState.currentDate.setDate(calendarState.currentDate.getDate() + (direction * 7));
  const container = document.querySelector('.calendar-week-container') || document.querySelector('.calendar-content');
  if (container) {
    renderWeekView(container);
  }
}

// Day navigation functions
function navigateDay(direction) {
  calendarState.currentDate.setDate(calendarState.currentDate.getDate() + direction);
  const container = document.querySelector('.calendar-day-container') || document.querySelector('.calendar-content');
  if (container) {
    renderDayView(container);
  }
}

// View full day function
function viewFullDay() {
  const container = document.querySelector('.calendar-content');
  if (container) {
    renderListView(container);
  }
}

// Helper function to format date
function formatDate(date) {
  return date.toLocaleDateString('en', { 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric' 
  });
}
