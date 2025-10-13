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
  // Simplified week view
  container.innerHTML = '<div class="calendar-week"><p>Week view coming soon</p></div>'
}

function renderDayView(container) {
  // Simplified day view
  container.innerHTML = '<div class="calendar-day-view"><p>Day view coming soon</p></div>'
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
