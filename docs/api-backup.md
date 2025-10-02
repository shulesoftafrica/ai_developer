# API Documentation - Kudos Orchestrator

This document provides comprehensive API documentation for the Kudos Orchestrator system.

## 📋 Table of Contents

- [Authentication](#authentication)
- [Dashboard API](#dashboard-api)
- [Tasks API](#tasks-api)
- [Milestones API](#milestones-api)
- [AI Logs API](#ai-logs-api)
- [Queue Management API](#queue-management-api)
- [System API](#system-api)
- [Error Handling](#error-handling)
- [Rate Limiting](#rate-limiting)

## 🔐 Authentication

The API uses Laravel Sanctum for authentication. Include the API token in the Authorization header:

```http
Authorization: Bearer {your-api-token}
```

### Generate API Token

```bash
php artisan tinker
>>> $user = User::first();
>>> $token = $user->createToken('api-token');
>>> echo $token->plainTextToken;
```

## 📊 Dashboard API

### Get Dashboard Statistics

Returns overall system statistics and metrics.

**Endpoint:** `GET /api/dashboard/stats`

**Response:**
```json
{
  "tasks": {
    "total": 156,
    "pending": 12,
    "in_progress": 8,
    "completed": 120,
    "failed": 16
  },
  "milestones": {
    "total": 45,
    "pending": 5,
    "in_progress": 3,
    "completed": 32,
    "failed": 5
  },
  "ai_activity": {
    "total_requests": 1234,
    "success_rate": 94.5,
    "avg_response_time": 2.3,
    "recent_activity": 23
  },
  "system": {
    "queue_size": 7,
    "active_workers": 3,
    "memory_usage": "512MB",
    "uptime": "2d 14h 32m"
  }
}
```

### Get Recent Tasks

Returns recent task activity with pagination.

**Endpoint:** `GET /api/dashboard/tasks`

**Parameters:**
- `limit` (optional): Number of tasks to return (default: 10)
- `status` (optional): Filter by task status
- `type` (optional): Filter by task type

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Implement user authentication",
      "type": "feature",
      "status": "completed",
      "priority": "high",
      "assigned_agent": "dev",
      "created_at": "2024-01-15T10:30:00Z",
      "updated_at": "2024-01-15T14:45:00Z",
      "completion_percentage": 100
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 156,
    "last_page": 16
  }
}
```

### Get AI Activity Logs

Returns recent AI agent activity and performance metrics.

**Endpoint:** `GET /api/dashboard/ai-logs`

**Parameters:**
- `limit` (optional): Number of logs to return (default: 20)
- `agent_type` (optional): Filter by agent type
- `status` (optional): Filter by status (success/error)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "agent_type": "dev",
      "action": "generate_code",
      "status": "success",
      "input_tokens": 1250,
      "output_tokens": 890,
      "response_time": 2.3,
      "created_at": "2024-01-15T10:30:00Z",
      "task_id": 1,
      "run_id": "run_abc123"
    }
  ],
  "success_rate": 94.5,
  "average_response_time": 2.1,
  "total_requests": 1234
}
```

### Get Analytics Data

Returns time-series analytics data for charts and graphs.

**Endpoint:** `GET /api/dashboard/analytics`

**Parameters:**
- `period` (optional): Time period (7d, 30d, 90d) - default: 7d
- `metric` (optional): Specific metric to retrieve

**Response:**
```json
{
  "tasks_over_time": [
    {
      "date": "2024-01-10",
      "completed": 12,
      "failed": 2,
      "in_progress": 5
    }
  ],
  "ai_performance": [
    {
      "date": "2024-01-10",
      "success_rate": 95.2,
      "avg_response_time": 2.1,
      "total_requests": 45
    }
  ],
  "queue_metrics": [
    {
      "timestamp": "2024-01-10T10:00:00Z",
      "queue_size": 12,
      "processing_rate": 8.5
    }
  ]
}
```

## 📋 Tasks API

### List Tasks

**Endpoint:** `GET /api/tasks`

**Parameters:**
- `page` (optional): Page number for pagination
- `per_page` (optional): Items per page (default: 15)
- `status` (optional): Filter by status
- `type` (optional): Filter by type
- `priority` (optional): Filter by priority
- `search` (optional): Search in title and description

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Implement user authentication",
      "description": "Add login/logout functionality with JWT tokens",
      "type": "feature",
      "status": "completed",
      "priority": "high",
      "assigned_agent": "dev",
      "sprint_id": 1,
      "created_at": "2024-01-15T10:30:00Z",
      "updated_at": "2024-01-15T14:45:00Z",
      "due_date": "2024-01-20T00:00:00Z",
      "estimated_hours": 16,
      "actual_hours": 14,
      "completion_percentage": 100,
      "milestones": [
        {
          "id": 1,
          "title": "Design authentication flow",
          "status": "completed"
        }
      ]
    }
  ],
  "links": {
    "first": "/api/tasks?page=1",
    "last": "/api/tasks?page=10",
    "prev": null,
    "next": "/api/tasks?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "per_page": 15,
    "to": 15,
    "total": 150
  }
}
```

### Get Task Details

**Endpoint:** `GET /api/tasks/{id}`

**Response:**
```json
{
  "id": 1,
  "title": "Implement user authentication",
  "description": "Add login/logout functionality with JWT tokens",
  "type": "feature",
  "status": "completed",
  "priority": "high",
  "assigned_agent": "dev",
  "sprint_id": 1,
  "created_at": "2024-01-15T10:30:00Z",
  "updated_at": "2024-01-15T14:45:00Z",
  "due_date": "2024-01-20T00:00:00Z",
  "estimated_hours": 16,
  "actual_hours": 14,
  "completion_percentage": 100,
  "milestones": [
    {
      "id": 1,
      "title": "Design authentication flow",
      "description": "Create wireframes and user flow",
      "status": "completed",
      "assigned_agent": "ux",
      "created_at": "2024-01-15T10:30:00Z",
      "completed_at": "2024-01-15T12:15:00Z"
    }
  ],
  "ai_logs": [
    {
      "id": 1,
      "agent_type": "dev",
      "action": "generate_code",
      "status": "success",
      "created_at": "2024-01-15T11:00:00Z"
    }
  ]
}
```

### Create Task

**Endpoint:** `POST /api/tasks`

**Request Body:**
```json
{
  "title": "Implement user registration",
  "description": "Add user registration with email verification",
  "type": "feature",
  "priority": "medium",
  "sprint_id": 1,
  "due_date": "2024-01-25",
  "estimated_hours": 12
}
```

**Response:**
```json
{
  "id": 2,
  "title": "Implement user registration",
  "description": "Add user registration with email verification",
  "type": "feature",
  "status": "pending",
  "priority": "medium",
  "assigned_agent": null,
  "sprint_id": 1,
  "created_at": "2024-01-16T09:00:00Z",
  "updated_at": "2024-01-16T09:00:00Z",
  "due_date": "2024-01-25T00:00:00Z",
  "estimated_hours": 12,
  "actual_hours": 0,
  "completion_percentage": 0
}
```

### Update Task

**Endpoint:** `PUT /api/tasks/{id}`

**Request Body:**
```json
{
  "title": "Implement user registration with OAuth",
  "priority": "high",
  "estimated_hours": 16
}
```

### Delete Task

**Endpoint:** `DELETE /api/tasks/{id}`

**Response:**
```json
{
  "message": "Task deleted successfully"
}
```

## 🎯 Milestones API

### List Milestones

**Endpoint:** `GET /api/milestones`

**Parameters:**
- `task_id` (optional): Filter by task ID
- `status` (optional): Filter by status
- `agent_type` (optional): Filter by assigned agent

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Design authentication flow",
      "description": "Create wireframes and user flow",
      "status": "completed",
      "assigned_agent": "ux",
      "task_id": 1,
      "order": 1,
      "created_at": "2024-01-15T10:30:00Z",
      "updated_at": "2024-01-15T12:15:00Z",
      "completed_at": "2024-01-15T12:15:00Z",
      "estimated_hours": 4,
      "actual_hours": 2.5
    }
  ]
}
```

### Get Milestone Details

**Endpoint:** `GET /api/milestones/{id}`

### Create Milestone

**Endpoint:** `POST /api/milestones`

**Request Body:**
```json
{
  "title": "Write unit tests",
  "description": "Create comprehensive unit tests for authentication",
  "task_id": 1,
  "assigned_agent": "qa",
  "order": 3,
  "estimated_hours": 6
}
```

### Update Milestone

**Endpoint:** `PUT /api/milestones/{id}`

### Delete Milestone

**Endpoint:** `DELETE /api/milestones/{id}`

## 🤖 AI Logs API

### List AI Logs

**Endpoint:** `GET /api/ai-logs`

**Parameters:**
- `agent_type` (optional): Filter by agent type
- `status` (optional): Filter by status
- `task_id` (optional): Filter by task ID
- `run_id` (optional): Filter by run ID
- `date_from` (optional): Filter from date (YYYY-MM-DD)
- `date_to` (optional): Filter to date (YYYY-MM-DD)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "agent_type": "dev",
      "action": "generate_code",
      "prompt": "Generate authentication middleware for Laravel",
      "response": "<?php\n\nnamespace App\\Http\\Middleware;...",
      "status": "success",
      "input_tokens": 1250,
      "output_tokens": 890,
      "response_time": 2.34,
      "task_id": 1,
      "milestone_id": 2,
      "run_id": "run_abc123",
      "created_at": "2024-01-15T11:00:00Z"
    }
  ]
}
```

### Get AI Log Details

**Endpoint:** `GET /api/ai-logs/{id}`

### AI Performance Metrics

**Endpoint:** `GET /api/ai-logs/metrics`

**Parameters:**
- `period` (optional): Time period (1h, 24h, 7d, 30d)
- `agent_type` (optional): Specific agent type

**Response:**
```json
{
  "success_rate": 94.5,
  "average_response_time": 2.1,
  "total_requests": 1234,
  "total_tokens_used": 1456789,
  "cost_estimate": 45.67,
  "by_agent": {
    "dev": {
      "requests": 456,
      "success_rate": 96.1,
      "avg_response_time": 2.3
    },
    "qa": {
      "requests": 234,
      "success_rate": 91.2,
      "avg_response_time": 1.8
    }
  }
}
```

## ⚡ Queue Management API

### Queue Status

**Endpoint:** `GET /api/queue/status`

**Response:**
```json
{
  "queues": {
    "default": {
      "size": 7,
      "running": 2,
      "failed": 0
    },
    "ai-processing": {
      "size": 3,
      "running": 1,
      "failed": 0
    }
  },
  "workers": {
    "active": 3,
    "total": 5,
    "memory_usage": "256MB"
  },
  "failed_jobs": 2,
  "processed_jobs_today": 156
}
```

### Queue Jobs

**Endpoint:** `GET /api/queue/jobs`

**Parameters:**
- `queue` (optional): Specific queue name
- `status` (optional): Job status (pending, processing, completed, failed)

**Response:**
```json
{
  "data": [
    {
      "id": "job_123",
      "queue": "default",
      "job": "App\\Jobs\\ProcessNewTask",
      "status": "processing",
      "attempts": 1,
      "created_at": "2024-01-15T11:00:00Z",
      "started_at": "2024-01-15T11:01:00Z",
      "payload": {
        "task_id": 5
      }
    }
  ]
}
```

### Retry Failed Jobs

**Endpoint:** `POST /api/queue/retry`

**Request Body:**
```json
{
  "job_ids": ["job_123", "job_456"]
}
```

### Clear Queue

**Endpoint:** `DELETE /api/queue/{queue_name}`

## 🔧 System API

### System Status

**Endpoint:** `GET /api/system/status`

**Response:**
```json
{
  "status": "healthy",
  "version": "1.0.0",
  "environment": "production",
  "uptime": "2d 14h 32m",
  "services": {
    "database": {
      "status": "healthy",
      "response_time": 0.05
    },
    "redis": {
      "status": "healthy",
      "memory_usage": "45MB"
    },
    "queue": {
      "status": "healthy",
      "active_workers": 3
    },
    "ai_service": {
      "status": "healthy",
      "last_request": "2024-01-15T11:05:00Z"
    }
  }
}
```

### System Metrics

**Endpoint:** `GET /api/system/metrics`

**Response:**
```json
{
  "server": {
    "cpu_usage": 23.5,
    "memory_usage": {
      "used": "2.1GB",
      "total": "8GB",
      "percentage": 26.25
    },
    "disk_usage": {
      "used": "45GB",
      "total": "100GB",
      "percentage": 45.0
    }
  },
  "application": {
    "active_sessions": 12,
    "cache_hit_rate": 89.5,
    "average_response_time": 0.15
  },
  "database": {
    "connections": 8,
    "queries_per_second": 45.2,
    "slow_queries": 2
  }
}
```

### Health Check

**Endpoint:** `GET /api/health`

**Response:**
```json
{
  "status": "ok",
  "timestamp": "2024-01-15T11:05:00Z",
  "checks": {
    "database": "ok",
    "redis": "ok",
    "queue": "ok",
    "storage": "ok"
  }
}
```

## ❌ Error Handling

All API endpoints return consistent error responses:

### Error Response Format

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "title": ["The title field is required."],
      "priority": ["The priority field must be one of: low, medium, high."]
    }
  },
  "meta": {
    "timestamp": "2024-01-15T11:05:00Z",
    "request_id": "req_abc123"
  }
}
```

### HTTP Status Codes

- `200` - Success
- `201` - Created
- `204` - No Content
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `429` - Too Many Requests
- `500` - Internal Server Error

### Common Error Codes

- `VALIDATION_ERROR` - Request validation failed
- `AUTHENTICATION_REQUIRED` - API token required
- `PERMISSION_DENIED` - Insufficient permissions
- `RESOURCE_NOT_FOUND` - Requested resource doesn't exist
- `RATE_LIMIT_EXCEEDED` - Too many requests
- `AI_SERVICE_ERROR` - AI service unavailable
- `QUEUE_ERROR` - Queue processing error

## 🚦 Rate Limiting

API endpoints are rate limited to ensure fair usage:

### Rate Limits

- **Authentication endpoints**: 5 requests per minute
- **Standard API endpoints**: 60 requests per minute
- **AI-related endpoints**: 30 requests per minute
- **Bulk operations**: 10 requests per minute

### Rate Limit Headers

Responses include rate limit information:

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1642248000
```

### Rate Limit Exceeded Response

```json
{
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Too many requests. Please try again later.",
    "details": {
      "limit": 60,
      "remaining": 0,
      "reset_at": "2024-01-15T11:10:00Z"
    }
  }
}
```

## 📚 SDK Examples

### PHP SDK Example

```php
use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'https://your-domain.com/api/',
    'headers' => [
        'Authorization' => 'Bearer ' . $apiToken,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ]
]);

// Get tasks
$response = $client->get('tasks', [
    'query' => [
        'status' => 'pending',
        'per_page' => 20
    ]
]);

$tasks = json_decode($response->getBody(), true);
```

### JavaScript SDK Example

```javascript
const axios = require('axios');

const api = axios.create({
    baseURL: 'https://your-domain.com/api/',
    headers: {
        'Authorization': `Bearer ${apiToken}`,
        'Content-Type': 'application/json'
    }
});

// Create task
const task = await api.post('tasks', {
    title: 'New feature',
    description: 'Implement new feature',
    type: 'feature',
    priority: 'high'
});
```

### Python SDK Example

```python
import requests

class KudosAPI:
    def __init__(self, base_url, api_token):
        self.base_url = base_url
        self.headers = {
            'Authorization': f'Bearer {api_token}',
            'Content-Type': 'application/json'
        }
    
    def get_tasks(self, **params):
        response = requests.get(
            f'{self.base_url}/tasks',
            headers=self.headers,
            params=params
        )
        return response.json()
    
    def create_task(self, task_data):
        response = requests.post(
            f'{self.base_url}/tasks',
            headers=self.headers,
            json=task_data
        )
        return response.json()

# Usage
api = KudosAPI('https://your-domain.com/api', 'your-api-token')
tasks = api.get_tasks(status='pending')
```

## 🔗 Webhooks

The system supports webhooks for real-time notifications:

### Webhook Events

- `task.created` - New task created
- `task.updated` - Task status changed
- `task.completed` - Task completed
- `milestone.completed` - Milestone completed
- `ai.request.completed` - AI request finished
- `queue.job.failed` - Queue job failed

### Webhook Payload Example

```json
{
  "event": "task.completed",
  "timestamp": "2024-01-15T11:05:00Z",
  "data": {
    "task": {
      "id": 1,
      "title": "Implement user authentication",
      "status": "completed",
      "completion_percentage": 100
    }
  },
  "meta": {
    "webhook_id": "wh_abc123",
    "delivery_id": "del_xyz789"
  }
}
```

This comprehensive API documentation covers all endpoints and functionality of the Kudos Orchestrator system.