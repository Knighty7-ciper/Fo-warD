# Forward LMS API Documentation

## Base URL
```
http://forward.local/backend/api
```

## Authentication

All authenticated endpoints require a valid session cookie.

### Headers
```
Content-Type: application/json
Cookie: FORWARD_SESSION=<session_id>
```

## Auth Endpoints

### POST /auth/login.php
Login user

**Request:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "redirect_url": "/frontend/student/dashboard.php"
  }
}
```

### POST /auth/register.php
Register new user

**Request:**
```json
{
  "email": "user@example.com",
  "password": "password123",
  "confirm_password": "password123",
  "first_name": "John",
  "last_name": "Doe",
  "role": "student",
  "captcha": "ABC123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "redirect_url": "/frontend/student/dashboard.php"
  }
}
```

### GET /auth/logout.php
Logout user

**Response:**
Redirects to homepage

## Course Endpoints

### GET /courses/list.php
Get all published courses

**Query Parameters:**
- `page` (optional): Page number, default 1
- `limit` (optional): Items per page, default 10
- `search` (optional): Search term

**Response:**
```json
{
  "success": true,
  "data": {
    "courses": [
      {
        "id": "uuid",
        "title": "Course Title",
        "description": "Course description",
        "teacher_name": "John Doe",
        "price": 49.99,
        "thumbnail_url": "/path/to/image.jpg"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 5,
      "total_items": 50
    }
  }
}
```

### POST /courses/create.php
Create new course (Teacher only)

**Request:**
```json
{
  "title": "Course Title",
  "description": "Course description",
  "price": 49.99,
  "status": "draft",
  "thumbnail_url": "/path/to/image.jpg"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Course created successfully",
  "data": {
    "course_id": "uuid"
  }
}
```

### POST /courses/enroll.php
Enroll in course (Student only)

**Request:**
```json
{
  "course_id": "uuid"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Enrolled successfully",
  "data": {
    "enrollment_id": "uuid"
  }
}
```

## Certificate Endpoints

### POST /certificates/issue.php
Issue certificate (Teacher/Admin only)

**Request:**
```json
{
  "student_id": "uuid",
  "course_id": "uuid"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Certificate issued successfully",
  "data": {
    "certificate_id": "uuid",
    "certificate_number": "FWRD-2025-ABC123",
    "blockchain_hash": "hash_value",
    "download_url": "/path/to/certificate.pdf"
  }
}
```

### GET /certificates/download.php
Download certificate

**Query Parameters:**
- `certificate_id`: UUID of certificate

**Response:**
PDF file download

## Schedule Endpoints

### GET /schedule/availability.php
Get teacher availability

**Query Parameters:**
- `teacher_id`: UUID of teacher
- `date`: Date in YYYY-MM-DD format

**Response:**
```json
{
  "success": true,
  "data": {
    "schedules": [
      {
        "id": "uuid",
        "title": "Office Hours",
        "start_time": "2025-10-06T10:00:00Z",
        "end_time": "2025-10-06T11:00:00Z",
        "status": "scheduled",
        "available_slots": 5
      }
    ]
  }
}
```

### POST /schedule/book.php
Book a schedule (Student only)

**Request:**
```json
{
  "schedule_id": "uuid"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Schedule booked successfully",
  "data": {
    "booking_id": "uuid",
    "meeting_url": "https://meeting.url"
  }
}
```

## Reward Endpoints

### GET /rewards/balance.php
Get student reward balance

**Response:**
```json
{
  "success": true,
  "data": {
    "total_points": 500,
    "recent_rewards": [
      {
        "points": 50,
        "reason": "Course completion",
        "awarded_at": "2025-10-01T12:00:00Z"
      }
    ]
  }
}
```

### POST /rewards/redeem.php
Redeem reward points (Student only)

**Request:**
```json
{
  "points": 100,
  "reward_type": "course_discount",
  "description": "10% off next course"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Reward redeemed successfully",
  "data": {
    "redemption_id": "uuid",
    "remaining_balance": 400
  }
}
```

## WebRTC Signaling

### POST /live-class/signal.php
WebRTC signaling for live classes

**Request:**
```json
{
  "type": "offer|answer|ice-candidate",
  "data": {},
  "room_id": "uuid"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "type": "answer",
    "data": {}
  }
}
```

## Error Responses

All endpoints return errors in this format:

```json
{
  "success": false,
  "error": "Error message description"
}
```

### Common Error Codes
- `400` - Bad Request (invalid parameters)
- `401` - Unauthorized (not logged in)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found (resource doesn't exist)
- `405` - Method Not Allowed (wrong HTTP method)
- `500` - Internal Server Error

## Rate Limiting

No rate limiting implemented in current version.

## Versioning

Current API version: v1.0.0

Future versions will be accessed via:
```
/backend/api/v2/endpoint
```
