# Kudos AI Development Orchestrator - API Documentation

## 🚀 Overview

The Kudos AI Development Orchestrator provides a comprehensive RESTful API for integrating with external systems, monitoring development workflows, and managing AI-powered development tasks.

**Base URL:** `http://localhost:8000/api`  
**Current Version:** v1  
**Authentication:** Bearer Token

## 🔐 Authentication

### API Token Authentication
All API endpoints require authentication using Bearer tokens:

```http
Authorization: Bearer your-api-token
```

### Obtaining API Tokens
```bash
# Using Artisan command
php artisan sanctum:token user@example.com

# Using Tinker
php artisan tinker
>>> $user = User::first();
>>> $token = $user->createToken('api-token');
>>> echo $token->plainTextToken;
```

## 📊 Core Endpoints

### System Statistics
Get comprehensive system metrics and status information.

```http
GET /api/v1/stats
```

**Response:**
```json
{
  "tasks": {
    "total": 13,
    "pending": 13,
    "in_progress": 0,
    "completed": 0,
    "failed": 0
  },
  "sprints": {
    "total": 3,
    "active": 1,
    "completed": 0
  },
  "milestones": {
    "total": 35,
    "pending": 35,
    "in_progress": 0,
    "completed": 0,
    "failed": 0
  },
  "ai_logs": {
    "total": 3,
    "today": 3,
    "success_rate": 100.0
  }
}
```

### Health Check
Simple health check endpoint for monitoring.

```http
GET /api/health
```

**Response:**
```json
{
  "status": "ok",
  "timestamp": "2025-09-23T09:00:00Z",
  "version": "1.0.0"
}
```

## 📋 Task Management

### List All Tasks
```http
GET /api/v1/tasks
```

**Query Parameters:**
- `status` (optional): Filter by task status (pending, in_progress, completed, failed)
- `type` (optional): Filter by task type (feature, bug, upgrade)
- `limit` (optional): Number of results per page (default: 20)
- `page` (optional): Page number for pagination

**Example Request:**
```http
GET /api/v1/tasks?status=pending&limit=10&page=1
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Implement User Authentication System",
      "description": "Create a secure user authentication system with JWT tokens",
      "type": "feature",
      "status": "pending",
      "priority": "high",
      "estimated_hours": 16,
      "actual_hours": null,
      "sprint_id": 1,
      "created_at": "2025-09-18T10:00:00Z",
      "updated_at": "2025-09-18T10:00:00Z",
      "milestones_count": 0,
      "completion_percentage": 0
    }
  ],
  "links": {
    "first": "/api/v1/tasks?page=1",
    "last": "/api/v1/tasks?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "per_page": 20,
    "to": 13,
    "total": 13
  }
}
```

### Create New Task
```http
POST /api/v1/tasks
```

**Request Body:**
```json
{
  "title": "Build React Shopping Cart Component",
  "description": "Create a reusable shopping cart component with add/remove items functionality",
  "type": "feature",
  "priority": "medium",
  "estimated_hours": 8,
  "sprint_id": 1,
  "requirements": [
    "Add items to cart",
    "Remove items from cart", 
    "Update item quantities",
    "Calculate total price",
    "Responsive design"
  ]
}
```

**Response:**
```json
{
  "id": 14,
  "title": "Build React Shopping Cart Component",
  "description": "Create a reusable shopping cart component with add/remove items functionality",
  "type": "feature",
  "status": "pending",
  "priority": "medium",
  "estimated_hours": 8,
  "actual_hours": null,
  "sprint_id": 1,
  "created_at": "2025-09-23T09:00:00Z",
  "updated_at": "2025-09-23T09:00:00Z"
}
```

### Get Specific Task
```http
GET /api/v1/tasks/{id}
```

**Response:**
```json
{
  "id": 1,
  "title": "Implement User Authentication System",
  "description": "Create a secure user authentication system with JWT tokens",
  "type": "feature",
  "status": "completed",
  "priority": "high",
  "estimated_hours": 16,
  "actual_hours": 14,
  "sprint_id": 1,
  "created_at": "2025-09-18T10:00:00Z",
  "updated_at": "2025-09-22T15:30:00Z",
  "milestones": [
    {
      "id": 1,
      "title": "PM: Project Planning",
      "agent_type": "pm",
      "status": "completed",
      "order": 1,
      "output": "Sprint planning completed with 5 milestones identified...",
      "execution_time": 1.2,
      "completed_at": "2025-09-18T10:05:00Z"
    }
  ],
  "sprint": {
    "id": 1,
    "name": "Authentication Sprint",
    "status": "active",
    "start_date": "2025-09-18",
    "end_date": "2025-10-02"
  }
}
```

### Update Task
```http
PUT /api/v1/tasks/{id}
```

**Request Body:**
```json
{
  "status": "in_progress",
  "priority": "high",
  "actual_hours": 6
}
```

### Delete Task
```http
DELETE /api/v1/tasks/{id}
```

**Response:**
```json
{
  "message": "Task deleted successfully"
}
```

## 🎯 Milestone Management

### List Milestones
```http
GET /api/v1/milestones
```

**Query Parameters:**
- `task_id` (optional): Filter by task ID
- `agent_type` (optional): Filter by agent type (pm, ba, ux, arch, dev, qa, doc)
- `status` (optional): Filter by status (pending, in_progress, completed, failed)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "task_id": 1,
      "title": "PM: Project Planning and Sprint Setup",
      "agent_type": "pm",
      "status": "completed",
      "order": 1,
      "prompt": "Analyze the task and create a comprehensive project plan...",
      "output": "## Sprint Planning\n\nTask: Implement User Authentication System...",
      "execution_time": 1.25,
      "tokens_used": 450,
      "created_at": "2025-09-18T10:05:00Z",
      "completed_at": "2025-09-18T10:06:15Z",
      "task": {
        "id": 1,
        "title": "Implement User Authentication System",
        "status": "completed"
      }
    }
  ]
}
```

### Get Milestone Details
```http
GET /api/v1/milestones/{id}
```

**Response:**
```json
{
  "id": 1,
  "task_id": 1,
  "title": "PM: Project Planning and Sprint Setup",
  "agent_type": "pm",
  "status": "completed",
  "order": 1,
  "prompt": "Analyze the task requirements and create a comprehensive project plan including sprint planning, milestone breakdown, resource allocation, and timeline estimation.",
  "output": "## Sprint Planning\n\nTask: Implement User Authentication System\n\n### Milestone Breakdown:\n1. PM: Project Planning (1-2 hours)\n2. BA: Requirements Analysis (2-3 hours)\n3. UX: User Interface Design (3-4 hours)\n4. Arch: System Architecture (2-3 hours)\n5. Dev: Implementation (6-8 hours)\n6. QA: Testing & Validation (2-3 hours)\n7. Doc: Documentation (1-2 hours)",
  "execution_time": 1.25,
  "tokens_used": 450,
  "created_at": "2025-09-18T10:05:00Z",
  "completed_at": "2025-09-18T10:06:15Z",
  "task": {
    "id": 1,
    "title": "Implement User Authentication System",
    "description": "Create a secure user authentication system with JWT tokens",
    "status": "completed"
  }
}
```

## 🧠 AI Activity Logs

### List AI Interaction Logs
```http
GET /api/v1/ai-logs
```

**Query Parameters:**
- `agent_type` (optional): Filter by agent type
- `status` (optional): Filter by status (success, error, cache_hit)
- `date_from` (optional): Filter from date (YYYY-MM-DD)
- `date_to` (optional): Filter to date (YYYY-MM-DD)
- `limit` (optional): Number of results per page

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "run_id": "run_abc123def456",
      "agent_type": "dev",
      "status": "success",
      "prompt_tokens": 1250,
      "completion_tokens": 800,
      "total_tokens": 2050,
      "execution_time": 3.45,
      "cost": 0.0205,
      "request_data": {
        "model": "claude-sonnet-4-20250514",
        "temperature": 0.7
      },
      "response_data": {
        "id": "msg_01abc123",
        "model": "claude-sonnet-4-20250514"
      },
      "created_at": "2025-09-23T06:04:10Z"
    }
  ]
}
```

### Get AI Log Details
```http
GET /api/v1/ai-logs/{id}
```

## 📈 System Analytics

### Get Detailed Analytics
```http
GET /api/v1/analytics
```

**Response:**
```json
{
  "tasks_by_status": {
    "pending": 13,
    "in_progress": 0,
    "completed": 0,
    "failed": 0
  },
  "tasks_by_type": {
    "feature": 10,
    "bug": 2,
    "upgrade": 1
  },
  "tasks_by_priority": {
    "low": 3,
    "medium": 5,
    "high": 4,
    "critical": 1
  },
  "milestones_by_agent": [
    {
      "agent_type": "pm",
      "count": 5,
      "avg_execution_time": 1.2,
      "success_rate": 100.0
    },
    {
      "agent_type": "dev",
      "count": 8,
      "avg_execution_time": 15.6,
      "success_rate": 87.5
    }
  ],
  "ai_performance": {
    "total_requests": 35,
    "success_rate": 100.0,
    "avg_response_time": 4.2,
    "total_tokens_used": 125000,
    "estimated_cost": 12.50
  },
  "timeline": [
    {
      "date": "2025-09-23",
      "tasks_created": 3,
      "tasks_completed": 0,
      "milestones_completed": 8,
      "ai_requests": 15
    }
  ],
  "productivity_metrics": {
    "avg_task_completion_time": 14.5,
    "avg_milestones_per_task": 7.2,
    "code_generation_accuracy": 94.3
  }
}
```

## 🏃‍♀️ Sprint Management

### List Sprints
```http
GET /api/v1/sprints
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Authentication Sprint",
      "description": "Implement user authentication and authorization features",
      "status": "active",
      "start_date": "2025-09-18",
      "end_date": "2025-10-02",
      "tasks_count": 5,
      "completed_tasks": 1,
      "total_estimated_hours": 64,
      "total_actual_hours": 22,
      "created_at": "2025-09-18T09:00:00Z"
    }
  ]
}
```

### Create Sprint
```http
POST /api/v1/sprints
```

**Request Body:**
```json
{
  "name": "Payment Integration Sprint",
  "description": "Implement payment processing and billing features",
  "start_date": "2025-10-03",
  "end_date": "2025-10-17",
  "goals": [
    "Integrate Stripe payment gateway",
    "Implement subscription billing",
    "Add payment history tracking"
  ]
}
```

## 🔄 Queue Management

### Get Queue Status
```http
GET /api/v1/queue/status
```

**Response:**
```json
{
  "queues": {
    "default": {
      "pending": 5,
      "processing": 2,
      "failed": 0
    },
    "high": {
      "pending": 1,
      "processing": 1,
      "failed": 0
    }
  },
  "workers": {
    "active": 4,
    "total": 4
  },
  "recent_jobs": [
    {
      "id": "job_123",
      "queue": "default",
      "name": "App\\Jobs\\ProcessNewTask",
      "status": "completed",
      "processed_at": "2025-09-23T09:15:00Z"
    }
  ]
}
```

### Retry Failed Jobs
```http
POST /api/v1/queue/retry
```

**Request Body:**
```json
{
  "job_ids": ["job_123", "job_456"]
}
```

## 🚨 Error Handling

All error responses follow a consistent format:

### Validation Errors (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "title": ["The title field is required."],
    "type": ["The selected type is invalid."]
  }
}
```

### Not Found (404)
```json
{
  "message": "Task not found."
}
```

### Unauthorized (401)
```json
{
  "message": "Unauthenticated."
}
```

### Server Error (500)
```json
{
  "message": "Server Error",
  "error": "Internal server error occurred."
}
```

## 📊 Status Codes

| Code | Description |
|------|-------------|
| 200  | Success |
| 201  | Created |
| 204  | No Content |
| 400  | Bad Request |
| 401  | Unauthorized |
| 403  | Forbidden |
| 404  | Not Found |
| 422  | Validation Error |
| 429  | Rate Limited |
| 500  | Internal Server Error |

## 🔄 Rate Limiting

API endpoints are rate-limited to ensure system stability:

- **General endpoints**: 60 requests per minute per IP
- **Task creation**: 10 requests per minute per user
- **Analytics**: 30 requests per minute per user
- **AI-related endpoints**: 20 requests per minute per user

Rate limit headers are included in responses:
```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1695465600
```

## 📝 Example Usage

### JavaScript/Node.js Example
```javascript
const axios = require('axios');

class KudosOrchestrator {
  constructor(baseURL, apiToken) {
    this.client = axios.create({
      baseURL: baseURL,
      headers: {
        'Authorization': `Bearer ${apiToken}`,
        'Content-Type': 'application/json'
      }
    });
  }

  async createTask(taskData) {
    try {
      const response = await this.client.post('/api/v1/tasks', taskData);
      return response.data;
    } catch (error) {
      console.error('Error creating task:', error.response.data);
      throw error;
    }
  }

  async getTaskStatus(taskId) {
    const response = await this.client.get(`/api/v1/tasks/${taskId}`);
    return response.data;
  }

  async getSystemStats() {
    const response = await this.client.get('/api/v1/stats');
    return response.data;
  }

  async monitorTask(taskId, callback) {
    const checkStatus = async () => {
      const task = await this.getTaskStatus(taskId);
      callback(task);
      
      if (task.status === 'completed' || task.status === 'failed') {
        return;
      }
      
      setTimeout(checkStatus, 5000); // Check every 5 seconds
    };
    
    checkStatus();
  }
}

// Usage
const orchestrator = new KudosOrchestrator('http://localhost:8000', 'your-api-token');

// Create a new task
orchestrator.createTask({
  title: 'Build User Profile Component',
  description: 'Create a user profile management component',
  type: 'feature',
  priority: 'medium',
  estimated_hours: 6
}).then(task => {
  console.log('Task created:', task);
  
  // Monitor the task progress
  orchestrator.monitorTask(task.id, (task) => {
    console.log(`Task ${task.id}: ${task.status} (${task.completion_percentage}%)`);
  });
});
```

### Python Example
```python
import requests
import time
from typing import Dict, Any

class KudosOrchestrator:
    def __init__(self, base_url: str, api_token: str):
        self.base_url = base_url
        self.headers = {
            'Authorization': f'Bearer {api_token}',
            'Content-Type': 'application/json'
        }
    
    def create_task(self, task_data: Dict[str, Any]) -> Dict[str, Any]:
        response = requests.post(
            f'{self.base_url}/api/v1/tasks',
            json=task_data,
            headers=self.headers
        )
        response.raise_for_status()
        return response.json()
    
    def get_task_status(self, task_id: int) -> Dict[str, Any]:
        response = requests.get(
            f'{self.base_url}/api/v1/tasks/{task_id}',
            headers=self.headers
        )
        response.raise_for_status()
        return response.json()
    
    def get_system_stats(self) -> Dict[str, Any]:
        response = requests.get(
            f'{self.base_url}/api/v1/stats',
            headers=self.headers
        )
        response.raise_for_status()
        return response.json()
    
    def monitor_task(self, task_id: int, check_interval: int = 5):
        while True:
            task = self.get_task_status(task_id)
            print(f"Task {task['id']}: {task['status']} ({task['completion_percentage']}%)")
            
            if task['status'] in ['completed', 'failed']:
                break
                
            time.sleep(check_interval)

# Usage
orchestrator = KudosOrchestrator('http://localhost:8000', 'your-api-token')

# Create and monitor a task
task = orchestrator.create_task({
    'title': 'Implement Search Functionality',
    'description': 'Add full-text search to the application',
    'type': 'feature',
    'priority': 'high',
    'estimated_hours': 12
})

print(f"Created task: {task['id']}")
orchestrator.monitor_task(task['id'])
```

### cURL Examples

#### Get System Statistics
```bash
curl -X GET "http://localhost:8000/api/v1/stats" \
  -H "Authorization: Bearer your-api-token" \
  -H "Accept: application/json"
```

#### Create a New Task
```bash
curl -X POST "http://localhost:8000/api/v1/tasks" \
  -H "Authorization: Bearer your-api-token" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "API Integration Task",
    "description": "Integrate with third-party payment API",
    "type": "feature",
    "priority": "high",
    "estimated_hours": 8
  }'
```

#### Get Task with Milestones
```bash
curl -X GET "http://localhost:8000/api/v1/tasks/1" \
  -H "Authorization: Bearer your-api-token" \
  -H "Accept: application/json"
```

#### Get Analytics Data
```bash
curl -X GET "http://localhost:8000/api/v1/analytics" \
  -H "Authorization: Bearer your-api-token" \
  -H "Accept: application/json"
```

## 🧪 Testing the API

### Health Check
```bash
curl -X GET "http://localhost:8000/api/health" \
  -H "Accept: application/json"
```

### Test Authentication
```bash
curl -X GET "http://localhost:8000/api/v1/stats" \
  -H "Authorization: Bearer invalid-token" \
  -H "Accept: application/json"
```

### Load Testing Example
```bash
# Using Apache Bench
ab -n 100 -c 10 -H "Authorization: Bearer your-api-token" \
   http://localhost:8000/api/v1/stats

# Using curl in a loop
for i in {1..10}; do
  curl -s -o /dev/null -w "%{http_code} %{time_total}s\n" \
    -H "Authorization: Bearer your-api-token" \
    http://localhost:8000/api/v1/stats
done
```

---

## 📞 Support

For API support and questions:
- **Documentation**: [Main README](../README.md)
- **Issues**: GitHub Issues
- **Email**: support@kudos-orchestrator.dev

**API Version**: 1.0  
**Last Updated**: September 23, 2025