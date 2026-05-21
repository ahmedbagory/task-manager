# Mobile API (Sanctum) - Postman Examples

Base URL:

```
http://127.0.0.1:8000
```

Common headers:

```
Accept: application/json
Authorization: Bearer <SANCTUM_TOKEN>
```

## 1) Login

Request:

```
POST /api/login
Content-Type: application/json

{
  "email": "employee@example.com",
  "password": "password",
  "device_name": "postman"
}
```

Success response:

```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "token": "1|xxxxxxxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer",
    "user": {
      "id": 5,
      "name": "Employee User",
      "email": "employee@example.com"
    }
  }
}
```

## 2) My Tasks List

Request:

```
GET /api/my-tasks?status=assigned&per_page=10
```

Success response:

```json
{
  "success": true,
  "message": "Tasks fetched successfully.",
  "data": {
    "tasks": [
      {
        "id": 10,
        "task_number": "TASK-20260519-0001",
        "title": "Broken AC in reception",
        "description": "AC leaking water",
        "reported_by_phone": "+201234567890",
        "location": "Reception",
        "source": "manual",
        "status": {
          "value": "assigned",
          "label": "Assigned"
        },
        "priority": {
          "value": "high",
          "label": "High"
        }
      }
    ]
  },
  "meta": {
    "pagination": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 10,
      "total": 1
    }
  }
}
```

## 3) Accept Task

Request:

```
POST /api/my-tasks/{task_id}/accept
```

Success response:

```json
{
  "success": true,
  "message": "Task accepted successfully.",
  "data": {
    "task": {
      "id": 10,
      "task_number": "TASK-20260519-0001",
      "status": {
        "value": "assigned",
        "label": "Assigned"
      }
    }
  }
}
```

## 4) Add Comment

Request:

```
POST /api/my-tasks/{task_id}/comment
Content-Type: application/json

{
  "comment": "Started investigation."
}
```

## 5) Reject Task

Request:

```
POST /api/my-tasks/{task_id}/reject
Content-Type: application/json

{
  "reason": "Need another team with special tools."
}
```

Validation error response:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "reason": [
      "The reason field is required."
    ]
  }
}
```

## 6) Upload Attachment

Request:

```
POST /api/my-tasks/{task_id}/attachments
Content-Type: multipart/form-data

attachment: <file>
```

Allowed formats:

- jpg
- jpeg
- png
- webp
- pdf
- doc
- docx
- xls
- xlsx

Max file size:

- 10 MB
