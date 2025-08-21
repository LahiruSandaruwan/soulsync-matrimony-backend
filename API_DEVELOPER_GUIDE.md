# SoulSync Matrimony API - Developer Guide

## Overview
This guide provides comprehensive documentation for developers working with the SoulSync Matrimony API. The API follows RESTful principles and uses Laravel Sanctum for authentication.

## Table of Contents
1. [API Basics](#api-basics)
2. [Authentication](#authentication)
3. [Error Handling](#error-handling)
4. [Rate Limiting](#rate-limiting)
5. [API Endpoints](#api-endpoints)
6. [WebSocket Events](#websocket-events)
7. [File Uploads](#file-uploads)
8. [Pagination](#pagination)
9. [Filtering & Searching](#filtering--searching)
10. [Testing](#testing)
11. [SDKs & Examples](#sdks--examples)

## API Basics

### Base URL
- **Development**: `http://localhost:8000/api/v1`
- **Production**: `https://api.soulsync.com/api/v1`

### Content Type
All API requests should include:
```
Content-Type: application/json
Accept: application/json
```

### Response Format
All API responses follow this structure:
```json
{
  "success": true|false,
  "message": "Human readable message",
  "data": {}, // Response data (when applicable)
  "errors": {}, // Validation errors (when applicable)
  "meta": {} // Additional metadata (pagination, etc.)
}
```

## Authentication

### Sanctum Token Authentication
The API uses Laravel Sanctum for authentication. Include the bearer token in all authenticated requests:

```
Authorization: Bearer your_access_token_here
```

### Login Flow
```javascript
// 1. Register or Login
POST /auth/register
POST /auth/login

// Response includes token
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": { /* user object */ },
    "token": "sanctum_token_here",
    "token_type": "Bearer"
  }
}

// 2. Use token in subsequent requests
GET /auth/me
Authorization: Bearer sanctum_token_here
```

### Authentication Endpoints

#### Register
```http
POST /auth/register
```

**Request Body:**
```json
{
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "date_of_birth": "1995-01-15",
  "gender": "male",
  "country_code": "US",
  "terms_accepted": true,
  "privacy_accepted": true
}
```

#### Login
```http
POST /auth/login
```

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "password123",
  "remember_me": true,
  "device_name": "iPhone 14"
}
```

#### Get Current User
```http
GET /auth/me
Authorization: Bearer {token}
```

#### Logout
```http
POST /auth/logout
Authorization: Bearer {token}
```

## Error Handling

### HTTP Status Codes
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `429` - Too Many Requests
- `500` - Internal Server Error

### Error Response Format
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required"],
    "password": ["The password must be at least 8 characters"]
  }
}
```

### Common Error Scenarios

#### Authentication Required (401)
```json
{
  "success": false,
  "message": "Unauthenticated",
  "error": "Token not provided or invalid"
}
```

#### Validation Failed (422)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

#### Rate Limit Exceeded (429)
```json
{
  "success": false,
  "message": "Too many requests. Please try again later.",
  "retry_after": 60
}
```

## Rate Limiting

### Default Limits
- **Authentication endpoints**: 5 requests per minute
- **General API endpoints**: 60 requests per minute
- **File upload endpoints**: 10 requests per minute

### Rate Limit Headers
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
Retry-After: 3600
```

## API Endpoints

### User Profile Management

#### Get Profile
```http
GET /profile
Authorization: Bearer {token}
```

#### Update Profile
```http
PUT /profile
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "bio": "Loving, caring person looking for soulmate",
  "occupation": "Software Engineer",
  "education_level": "bachelors",
  "height": 175,
  "religion": "christian",
  "smoking": "never",
  "drinking": "socially"
}
```

### Photo Management

#### Upload Photo
```http
POST /profile/photos
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Form Data:**
```
photo: [file]
is_profile_picture: true|false
is_private: true|false
caption: "Photo caption"
```

#### Get Photos
```http
GET /profile/photos
Authorization: Bearer {token}
```

#### Delete Photo
```http
DELETE /profile/photos/{photo_id}
Authorization: Bearer {token}
```

### Matching & Search

#### Get Daily Matches
```http
GET /matches/daily
Authorization: Bearer {token}

Query Parameters:
- limit: number (default: 10)
- offset: number (default: 0)
```

#### Like a User
```http
POST /matches/{user_id}/like
Authorization: Bearer {token}
```

#### Super Like a User
```http
POST /matches/{user_id}/super-like
Authorization: Bearer {token}
```

#### Search Users
```http
POST /search
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "min_age": 25,
  "max_age": 35,
  "gender": "female",
  "country": "US",
  "education_level": "bachelors",
  "religion": "christian",
  "page": 1,
  "limit": 20
}
```

### Chat & Messaging

#### Get Conversations
```http
GET /chat/conversations
Authorization: Bearer {token}

Query Parameters:
- page: number (default: 1)
- limit: number (default: 20)
```

#### Get Messages
```http
GET /chat/conversations/{conversation_id}
Authorization: Bearer {token}

Query Parameters:
- page: number (default: 1)
- limit: number (default: 50)
```

#### Send Message
```http
POST /chat/conversations/{conversation_id}/messages
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "message": "Hello! How are you?",
  "type": "text", // text, image, voice, video
  "attachment_url": "https://example.com/image.jpg" // optional
}
```

### Video Calls

#### Initiate Video Call
```http
POST /video-calls/initiate
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "callee_id": 123,
  "conversation_id": 456 // optional
}
```

#### Accept Video Call
```http
POST /video-calls/{call_id}/accept
Authorization: Bearer {token}
```

#### End Video Call
```http
POST /video-calls/{call_id}/end
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "reason": "normal" // normal, network_issue, technical_issue, user_ended
}
```

### Subscriptions

#### Get Subscription Plans
```http
GET /subscription/plans
```

#### Subscribe to Plan
```http
POST /subscription/subscribe
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "plan_type": "premium",
  "payment_method": "stripe",
  "payment_token": "stripe_token_here",
  "duration_months": 1,
  "auto_renewal": true
}
```

#### Cancel Subscription
```http
POST /subscription/cancel
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "reason": "Too expensive",
  "immediate": false
}
```

### Notifications

#### Get Notifications
```http
GET /notifications
Authorization: Bearer {token}

Query Parameters:
- page: number (default: 1)
- limit: number (default: 20)
- unread_only: boolean (default: false)
```

#### Mark as Read
```http
POST /notifications/{notification_id}/read
Authorization: Bearer {token}
```

#### Mark All as Read
```http
POST /notifications/read-all
Authorization: Bearer {token}
```

## WebSocket Events

### Connection
```javascript
// Using Laravel Echo with Pusher
const echo = new Echo({
    broadcaster: 'pusher',
    key: 'your-pusher-key',
    cluster: 'mt1',
    forceTLS: true,
    auth: {
        headers: {
            Authorization: `Bearer ${token}`
        }
    }
});
```

### Channel Subscriptions

#### Private User Channel
```javascript
echo.private(`user.${userId}`)
    .listen('MessageReceived', (e) => {
        console.log('New message:', e.message);
    })
    .listen('MatchFound', (e) => {
        console.log('New match:', e.match);
    })
    .listen('VideoCallIncoming', (e) => {
        console.log('Incoming call:', e.call);
    });
```

#### Conversation Channel
```javascript
echo.private(`conversation.${conversationId}`)
    .listen('MessageSent', (e) => {
        console.log('Message sent:', e.message);
    })
    .listen('TypingStarted', (e) => {
        console.log('User typing:', e.user);
    })
    .listen('TypingStopped', (e) => {
        console.log('User stopped typing:', e.user);
    });
```

### Event Types

#### Message Events
- `MessageReceived` - New message received
- `MessageRead` - Message marked as read
- `TypingStarted` - User started typing
- `TypingStopped` - User stopped typing

#### Match Events
- `MatchFound` - Mutual match found
- `UserLiked` - Someone liked your profile
- `UserSuperLiked` - Someone super-liked your profile

#### Call Events
- `VideoCallIncoming` - Incoming video call
- `VideoCallAccepted` - Call was accepted
- `VideoCallEnded` - Call ended

#### System Events
- `UserOnline` - User came online
- `UserOffline` - User went offline
- `NotificationReceived` - New notification

## File Uploads

### Image Upload
```http
POST /profile/photos
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Constraints:**
- Maximum file size: 5MB
- Allowed formats: JPEG, PNG, GIF, WebP
- Minimum dimensions: 400x400 pixels
- Maximum dimensions: 2048x2048 pixels

### Voice Message Upload
```http
POST /chat/conversations/{conversation_id}/messages
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Form Data:**
```
type: voice
voice_file: [audio file]
duration: 30 // seconds
```

**Constraints:**
- Maximum file size: 10MB
- Allowed formats: MP3, WAV, M4A
- Maximum duration: 60 seconds

## Pagination

### Standard Pagination
```json
{
  "success": true,
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100,
    "from": 1,
    "to": 20
  }
}
```

### Cursor Pagination (for real-time data)
```json
{
  "success": true,
  "data": [...],
  "meta": {
    "next_cursor": "eyJpZCI6MTIzfQ==",
    "has_more": true
  }
}
```

## Filtering & Searching

### Search Parameters
Most list endpoints support filtering:

```http
GET /search?min_age=25&max_age=35&gender=female&religion=christian&page=1
```

### Advanced Search
```http
POST /search/advanced
```

**Request Body:**
```json
{
  "filters": {
    "age_range": [25, 35],
    "height_range": [160, 180],
    "education_levels": ["bachelors", "masters"],
    "religions": ["christian", "catholic"],
    "countries": ["US", "CA"],
    "languages": ["en", "es"],
    "interests": ["travel", "photography", "cooking"]
  },
  "sort": {
    "field": "last_active_at",
    "direction": "desc"
  },
  "pagination": {
    "page": 1,
    "limit": 20
  }
}
```

## Testing

### Unit Tests
```bash
# Run all tests
php artisan test

# Run specific test
php artisan test tests/Feature/AuthenticationTest.php

# Run with coverage
php artisan test --coverage
```

### API Testing with Postman
Import the provided Postman collection:
```
SoulSync_API.postman_collection.json
```

### Example Test Scripts

#### JavaScript (Jest)
```javascript
const axios = require('axios');

describe('Authentication API', () => {
  test('should login with valid credentials', async () => {
    const response = await axios.post('/api/v1/auth/login', {
      email: 'test@example.com',
      password: 'password123'
    });
    
    expect(response.status).toBe(200);
    expect(response.data.success).toBe(true);
    expect(response.data.data.token).toBeDefined();
  });
});
```

#### Python (pytest)
```python
import requests

def test_login():
    response = requests.post('http://localhost:8000/api/v1/auth/login', json={
        'email': 'test@example.com',
        'password': 'password123'
    })
    
    assert response.status_code == 200
    assert response.json()['success'] == True
    assert 'token' in response.json()['data']
```

### Load Testing
```bash
# Using Apache Bench
ab -n 1000 -c 10 -H "Authorization: Bearer {token}" \
   http://localhost:8000/api/v1/matches/daily

# Using Artillery
artillery quick --count 10 --num 100 \
   --header "Authorization: Bearer {token}" \
   http://localhost:8000/api/v1/matches/daily
```

## SDKs & Examples

### JavaScript/TypeScript SDK
```typescript
class SoulSyncAPI {
  private baseURL: string;
  private token: string | null = null;

  constructor(baseURL: string) {
    this.baseURL = baseURL;
  }

  setToken(token: string) {
    this.token = token;
  }

  private async request(endpoint: string, options: RequestInit = {}) {
    const url = `${this.baseURL}${endpoint}`;
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(this.token && { Authorization: `Bearer ${this.token}` }),
      ...options.headers,
    };

    const response = await fetch(url, { ...options, headers });
    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.message || 'API request failed');
    }

    return data;
  }

  async login(email: string, password: string) {
    const data = await this.request('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    });
    
    this.setToken(data.data.token);
    return data;
  }

  async getProfile() {
    return this.request('/profile');
  }

  async getDailyMatches(limit = 10) {
    return this.request(`/matches/daily?limit=${limit}`);
  }

  async likeUser(userId: number) {
    return this.request(`/matches/${userId}/like`, { method: 'POST' });
  }
}

// Usage
const api = new SoulSyncAPI('https://api.soulsync.com/api/v1');
await api.login('user@example.com', 'password');
const matches = await api.getDailyMatches();
```

### PHP SDK
```php
class SoulSyncAPI {
    private $baseURL;
    private $token;

    public function __construct($baseURL) {
        $this->baseURL = $baseURL;
    }

    public function setToken($token) {
        $this->token = $token;
    }

    private function request($endpoint, $method = 'GET', $data = null) {
        $url = $this->baseURL . $endpoint;
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        
        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            throw new Exception($data['message'] ?? 'API request failed');
        }

        return $data;
    }

    public function login($email, $password) {
        $data = $this->request('/auth/login', 'POST', [
            'email' => $email,
            'password' => $password,
        ]);
        
        $this->setToken($data['data']['token']);
        return $data;
    }

    public function getProfile() {
        return $this->request('/profile');
    }

    public function getDailyMatches($limit = 10) {
        return $this->request("/matches/daily?limit={$limit}");
    }
}
```

## Webhook Integration

### Setting Up Webhooks
Configure webhook URLs in your environment:

```env
STRIPE_WEBHOOK_URL=https://api.soulsync.com/api/webhooks/stripe
PAYPAL_WEBHOOK_URL=https://api.soulsync.com/api/webhooks/paypal
```

### Webhook Events

#### Payment Success
```json
{
  "event": "payment.succeeded",
  "data": {
    "user_id": 123,
    "subscription_id": 456,
    "amount": 9.99,
    "currency": "USD",
    "payment_method": "stripe",
    "transaction_id": "stripe_tx_123"
  }
}
```

#### Profile Verification
```json
{
  "event": "profile.verified",
  "data": {
    "user_id": 123,
    "verification_type": "photo",
    "verified_at": "2024-01-15T10:30:00Z"
  }
}
```

## Best Practices

### API Usage
1. **Always include proper error handling**
2. **Implement exponential backoff for retries**
3. **Cache responses when appropriate**
4. **Use pagination for large datasets**
5. **Validate user input before sending to API**

### Security
1. **Never expose API tokens in client-side code**
2. **Use HTTPS in production**
3. **Implement proper CORS policies**
4. **Validate and sanitize all input**
5. **Monitor for unusual API usage patterns**

### Performance
1. **Use cursor pagination for real-time feeds**
2. **Implement client-side caching**
3. **Compress request/response bodies**
4. **Use WebSockets for real-time features**
5. **Optimize image uploads (compression, thumbnails)**

## Support & Resources

### Documentation
- [Laravel Documentation](https://laravel.com/docs)
- [Laravel Sanctum](https://laravel.com/docs/sanctum)
- [Pusher Documentation](https://pusher.com/docs)

### Community
- [GitHub Issues](https://github.com/your-repo/soulsync-matrimony/issues)
- [Discord Server](https://discord.gg/soulsync)
- [Stack Overflow](https://stackoverflow.com/questions/tagged/soulsync)

### Contact
- **Technical Support**: tech@soulsync.com
- **API Questions**: api@soulsync.com
- **Emergency**: +1-555-SOULSYNC

---

*This documentation is for SoulSync Matrimony API v1.0. Last updated: January 2024*
