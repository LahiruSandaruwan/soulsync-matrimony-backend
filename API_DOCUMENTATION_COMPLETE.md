# SoulSync Matrimony API - Complete Documentation

## 📋 Table of Contents
- [Overview](#overview)
- [Authentication](#authentication)
- [User Profile](#user-profile)
- [Matching & Discovery](#matching--discovery)
- [Chat & Messaging](#chat--messaging)
- [Notifications](#notifications)
- [Subscriptions & Payments](#subscriptions--payments)
- [Search & Browse](#search--browse)
- [Settings & Preferences](#settings--preferences)
- [Admin Panel](#admin-panel)
- [Webhooks](#webhooks)
- [Public Endpoints](#public-endpoints)
- [Error Handling](#error-handling)
- [Rate Limiting](#rate-limiting)

---

## Overview

### Base URL
- **Development**: `http://localhost:8000/api/v1`
- **Production**: `https://api.soulsync.com/api/v1`

### Authentication
All protected endpoints require a Bearer token:
```http
Authorization: Bearer {your_token}
```

### Response Format
```json
{
  "success": true,
  "data": { ... },
  "message": "Success message"
}
```

---

## Authentication

### Register User
```http
POST /api/v1/auth/register
```

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "first_name": "John",
  "last_name": "Doe",
  "date_of_birth": "1990-01-01",
  "gender": "male",
  "country_code": "LK",
  "terms_accepted": true,
  "privacy_accepted": true
}
```

**Response (201):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "email": "user@example.com",
      "first_name": "John",
      "last_name": "Doe",
      "email_verified_at": null
    },
    "token": "1|abc123..."
  },
  "message": "User registered successfully"
}
```

### Login
```http
POST /api/v1/auth/login
```

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "user": { ... },
    "token": "1|abc123..."
  },
  "message": "Login successful"
}
```

### Get Current User
```http
GET /api/v1/auth/me
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "email": "user@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "profile": { ... },
    "subscription": { ... }
  }
}
```

### Logout
```http
POST /api/v1/auth/logout
```

**Response (200):**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

---

## User Profile

### Get Profile
```http
GET /api/v1/profile
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "user_id": 1,
    "height_cm": 170,
    "weight_kg": 65,
    "body_type": "average",
    "complexion": "fair",
    "blood_group": "A+",
    "current_city": "Colombo",
    "current_state": "Western Province",
    "current_country": "Sri Lanka",
    "education_level": "bachelors",
    "occupation": "Software Engineer",
    "company": "Tech Corp",
    "annual_income_usd": 50000,
    "religion": "Buddhism",
    "mother_tongue": "Sinhala",
    "languages_known": ["en", "si"],
    "family_type": "nuclear",
    "family_status": "middle_class",
    "diet": "non_vegetarian",
    "smoking": "never",
    "drinking": "socially",
    "about_me": "I am a software engineer...",
    "looking_for": "Looking for a life partner...",
    "marital_status": "never_married",
    "have_children": false,
    "willing_to_relocate": true,
    "preferred_locations": ["Colombo", "Kandy"],
    "completion_percentage": 85
  }
}
```

### Update Profile
```http
PUT /api/v1/profile
```

**Request Body:**
```json
{
  "height_cm": 170,
  "weight_kg": 65,
  "body_type": "average",
  "complexion": "fair",
  "blood_group": "A+",
  "current_city": "Colombo",
  "current_state": "Western Province",
  "current_country": "Sri Lanka",
  "education_level": "bachelors",
  "occupation": "Software Engineer",
  "company": "Tech Corp",
  "annual_income_usd": 50000,
  "religion": "Buddhism",
  "mother_tongue": "Sinhala",
  "languages_known": ["en", "si"],
  "family_type": "nuclear",
  "family_status": "middle_class",
  "diet": "non_vegetarian",
  "smoking": "never",
  "drinking": "socially",
  "about_me": "I am a software engineer...",
  "looking_for": "Looking for a life partner...",
  "marital_status": "never_married",
  "have_children": false,
  "willing_to_relocate": true,
  "preferred_locations": ["Colombo", "Kandy"]
}
```

### Get Profile Completion Status
```http
GET /api/v1/profile/completion-status
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "completion_percentage": 85,
    "missing_fields": ["horoscope", "voice_intro"],
    "completed_sections": {
      "basic_info": true,
      "personal_details": true,
      "education_career": true,
      "family_background": false,
      "lifestyle": true,
      "preferences": false
    }
  }
}
```

### Upload Photo
```http
POST /api/v1/profile/photos
Content-Type: multipart/form-data
```

**Request Body:**
```
file: [image file]
is_primary: true
is_private: false
```

**Response (201):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "user_id": 1,
    "file_path": "photos/user_1_photo_1.jpg",
    "is_primary": true,
    "is_private": false,
    "status": "pending",
    "created_at": "2025-01-20T10:00:00Z"
  },
  "message": "Photo uploaded successfully"
}
```

---

## Matching & Discovery

### Get Matches
```http
GET /api/v1/matches?page=1&per_page=20&filter=all
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "matches": [
      {
        "id": 2,
        "user": {
          "id": 2,
          "first_name": "Jane",
          "last_name": "Smith",
          "age": 28,
          "current_city": "Colombo",
          "occupation": "Doctor",
          "profile_photo": "photos/user_2_primary.jpg",
          "compatibility_score": 85
        },
        "match_status": "pending",
        "created_at": "2025-01-20T10:00:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 50,
      "last_page": 3
    }
  }
}
```

### Like User
```http
POST /api/v1/matches/{user_id}/like
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "match_created": true,
    "conversation_id": 1,
    "message": "It's a match! You can now start chatting."
  }
}
```

### Get Daily Matches
```http
GET /api/v1/matches/daily
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "daily_matches": [
      {
        "id": 3,
        "user": { ... },
        "compatibility_score": 90,
        "match_reason": "High compatibility in lifestyle and values"
      }
    ],
    "remaining_matches": 15
  }
}
```

### Get Who Liked Me
```http
GET /api/v1/matches/liked-me
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "likes": [
      {
        "id": 4,
        "user": { ... },
        "liked_at": "2025-01-20T10:00:00Z"
      }
    ]
  }
}
```

---

## Chat & Messaging

### Get Conversations
```http
GET /api/v1/chat/conversations?page=1&per_page=20
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "conversations": [
      {
        "id": 1,
        "user_one_id": 1,
        "user_two_id": 2,
        "other_user": {
          "id": 2,
          "first_name": "Jane",
          "last_name": "Smith",
          "profile_photo": "photos/user_2_primary.jpg",
          "is_online": true
        },
        "last_message": {
          "content": "Hello! How are you?",
          "sender_id": 2,
          "created_at": "2025-01-20T10:00:00Z"
        },
        "unread_count": 2,
        "created_at": "2025-01-20T10:00:00Z"
      }
    ]
  }
}
```

### Get Specific Conversation
```http
GET /api/v1/chat/conversations/{conversation_id}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "user_one_id": 1,
    "user_two_id": 2,
    "other_user": { ... },
    "messages": [
      {
        "id": 1,
        "conversation_id": 1,
        "sender_id": 1,
        "content": "Hi Jane!",
        "type": "text",
        "is_read": true,
        "created_at": "2025-01-20T10:00:00Z"
      }
    ],
    "pagination": { ... }
  }
}
```

### Send Message
```http
POST /api/v1/chat/conversations/{conversation_id}/messages
```

**Request Body:**
```json
{
  "content": "Hello! How are you?",
  "type": "text"
}
```

**Response (201):**
```json
{
  "success": true,
  "data": {
    "id": 2,
    "conversation_id": 1,
    "sender_id": 1,
    "content": "Hello! How are you?",
    "type": "text",
    "is_read": false,
    "created_at": "2025-01-20T10:00:00Z"
  },
  "message": "Message sent successfully"
}
```

---

## Notifications

### Get Notifications
```http
GET /api/v1/notifications?page=1&per_page=20&type=all
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "notifications": [
      {
        "id": 1,
        "user_id": 1,
        "type": "match",
        "title": "New Match!",
        "message": "You have a new match with Jane Smith",
        "data": {
          "match_id": 1,
          "user_id": 2
        },
        "is_read": false,
        "created_at": "2025-01-20T10:00:00Z"
      }
    ],
    "pagination": { ... }
  }
}
```

### Get Unread Count
```http
GET /api/v1/notifications/unread-count
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "unread_count": 5
  }
}
```

### Mark Notification as Read
```http
POST /api/v1/notifications/{notification_id}/read
```

**Response (200):**
```json
{
  "success": true,
  "message": "Notification marked as read"
}
```

---

## Subscriptions & Payments

### Get Subscription Plans
```http
GET /api/v1/subscription/plans
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "plans": [
      {
        "id": 1,
        "name": "Free",
        "type": "free",
        "price_usd": 0,
        "price_lkr": 0,
        "duration_months": 1,
        "features": [
          "Basic matching",
          "Limited daily matches",
          "Basic search"
        ],
        "limits": {
          "daily_matches": 10,
          "messages_per_day": 5,
          "photo_uploads": 3
        }
      },
      {
        "id": 2,
        "name": "Premium",
        "type": "premium",
        "price_usd": 9.99,
        "price_lkr": 3200,
        "duration_months": 1,
        "features": [
          "Unlimited matches",
          "Unlimited messages",
          "Advanced search",
          "See who liked you",
          "Priority support"
        ]
      }
    ],
    "current_currency": "USD",
    "exchange_rate": 320.50
  }
}
```

### Subscribe to Plan
```http
POST /api/v1/subscription/subscribe
```

**Request Body:**
```json
{
  "plan_type": "premium",
  "payment_method": "stripe",
  "payment_token": "tok_visa",
  "duration_months": 1,
  "auto_renewal": true,
  "billing_details": {
    "name": "John Doe",
    "email": "john@example.com",
    "address": "123 Main St",
    "city": "Colombo",
    "country": "Sri Lanka"
  }
}
```

**Response (201):**
```json
{
  "success": true,
  "data": {
    "subscription_id": 1,
    "plan_type": "premium",
    "status": "active",
    "start_date": "2025-01-20T10:00:00Z",
    "end_date": "2025-02-20T10:00:00Z",
    "payment_id": "pi_1234567890"
  },
  "message": "Subscription activated successfully"
}
```

### Get Current Subscription
```http
GET /api/v1/subscription
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "user_id": 1,
    "plan_type": "premium",
    "status": "active",
    "start_date": "2025-01-20T10:00:00Z",
    "end_date": "2025-02-20T10:00:00Z",
    "auto_renewal": true,
    "features": [ ... ]
  }
}
```

---

## Search & Browse

### Search Users
```http
POST /api/v1/search
```

**Request Body:**
```json
{
  "query": "doctor",
  "filters": {
    "age_min": 25,
    "age_max": 35,
    "location": "Colombo",
    "education_level": ["bachelors", "masters"],
    "religion": ["Buddhism", "Christianity"],
    "marital_status": ["never_married"],
    "has_children": false
  }
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "results": [
      {
        "id": 3,
        "first_name": "Sarah",
        "last_name": "Johnson",
        "age": 29,
        "occupation": "Doctor",
        "current_city": "Colombo",
        "profile_photo": "photos/user_3_primary.jpg",
        "compatibility_score": 78
      }
    ],
    "total": 15,
    "pagination": { ... }
  }
}
```

### Browse Users
```http
GET /api/v1/browse?page=1&per_page=20&filter=all
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "users": [
      {
        "id": 4,
        "first_name": "Michael",
        "last_name": "Brown",
        "age": 31,
        "occupation": "Engineer",
        "current_city": "Kandy",
        "profile_photo": "photos/user_4_primary.jpg",
        "compatibility_score": 65,
        "is_premium": true,
        "is_verified": true
      }
    ],
    "pagination": { ... }
  }
}
```

---

## Settings & Preferences

### Get Preferences
```http
GET /api/v1/preferences
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "age_min": 25,
    "age_max": 35,
    "height_min": 150,
    "height_max": 180,
    "education_level": ["bachelors", "masters"],
    "religion": ["Buddhism", "Christianity"],
    "location_preference": "same_city",
    "max_distance_km": 50,
    "deal_breakers": ["smoking", "drinking"],
    "preferred_diet": ["vegetarian", "non_vegetarian"]
  }
}
```

### Update Preferences
```http
PUT /api/v1/preferences
```

**Request Body:**
```json
{
  "age_min": 25,
  "age_max": 35,
  "height_min": 150,
  "height_max": 180,
  "education_level": ["bachelors", "masters"],
  "religion": ["Buddhism", "Christianity"],
  "location_preference": "same_city",
  "max_distance_km": 50,
  "deal_breakers": ["smoking", "drinking"],
  "preferred_diet": ["vegetarian", "non_vegetarian"]
}
```

### Get Settings
```http
GET /api/v1/settings
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "language": "en",
    "timezone": "Asia/Colombo",
    "currency": "USD",
    "privacy": {
      "profile_visibility": "public",
      "hide_last_seen": false,
      "incognito_mode": false,
      "show_online_status": true
    },
    "notifications": {
      "email_notifications": true,
      "sms_notifications": false,
      "push_notifications": true,
      "notification_types": {
        "matches": true,
        "messages": true,
        "profile_views": false
      }
    }
  }
}
```

---

## Public Endpoints

### Get Interests
```http
GET /api/v1/public/interests
```

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Reading",
      "category": "hobbies",
      "description": "Love reading books"
    },
    {
      "id": 2,
      "name": "Traveling",
      "category": "lifestyle",
      "description": "Exploring new places"
    }
  ]
}
```

### Get Countries
```http
GET /api/v1/public/countries
```

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "code": "LK",
      "name": "Sri Lanka"
    },
    {
      "code": "IN",
      "name": "India"
    }
  ]
}
```

### Health Check
```http
GET /api/v1/health
```

**Response (200):**
```json
{
  "status": "ok",
  "message": "SoulSync API is running",
  "version": "1.0.0",
  "timestamp": "2025-01-20T10:00:00Z"
}
```

---

## Error Handling

### Error Response Format
```json
{
  "success": false,
  "message": "Error message",
  "errors": {
    "field_name": ["Validation error message"]
  },
  "error_code": "VALIDATION_ERROR"
}
```

### Common Error Codes
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `429` - Too Many Requests
- `500` - Internal Server Error

### Validation Error Example
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  },
  "error_code": "VALIDATION_ERROR"
}
```

---

## Rate Limiting

### Authentication Endpoints
- **Register**: 5 requests per minute
- **Login**: 10 requests per minute
- **Forgot Password**: 3 requests per hour
- **Reset Password**: 5 requests per hour

### API Endpoints
- **General**: 60 requests per minute
- **File Upload**: 10 requests per minute
- **Search**: 30 requests per minute

### Rate Limit Response
```json
{
  "success": false,
  "message": "Too many requests. Please try again later.",
  "error_code": "RATE_LIMIT_EXCEEDED",
  "retry_after": 60
}
```

---

## WebSocket Events

### Connection
```javascript
// Connect to WebSocket
const echo = new Echo({
  broadcaster: 'pusher',
  key: 'your-pusher-key',
  cluster: 'mt1',
  forceTLS: true
});
```

### Listen for Events
```javascript
// New message
echo.private(`conversation.${conversationId}`)
  .listen('MessageSent', (e) => {
    console.log('New message:', e.message);
  });

// New match
echo.private(`user.${userId}`)
  .listen('MatchCreated', (e) => {
    console.log('New match:', e.match);
  });

// New notification
echo.private(`user.${userId}`)
  .listen('NotificationSent', (e) => {
    console.log('New notification:', e.notification);
  });

// User online/offline
echo.private(`user.${userId}`)
  .listen('UserStatusChanged', (e) => {
    console.log('User status:', e.status);
  });
```

---

## Frontend Integration Examples

### Angular Service Example
```typescript
@Injectable({
  providedIn: 'root'
})
export class ApiService {
  private apiUrl = environment.apiUrl;
  private token = localStorage.getItem('token');

  constructor(private http: HttpClient) {}

  // Authentication
  login(credentials: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/auth/login`, credentials);
  }

  // Get profile
  getProfile(): Observable<any> {
    return this.http.get(`${this.apiUrl}/profile`);
  }

  // Update profile
  updateProfile(data: any): Observable<any> {
    return this.http.put(`${this.apiUrl}/profile`, data);
  }

  // Get matches
  getMatches(page: number = 1): Observable<any> {
    return this.http.get(`${this.apiUrl}/matches?page=${page}`);
  }

  // Send message
  sendMessage(conversationId: number, content: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/chat/conversations/${conversationId}/messages`, {
      content,
      type: 'text'
    });
  }
}
```

### HTTP Interceptor for Authentication
```typescript
@Injectable()
export class AuthInterceptor implements HttpInterceptor {
  intercept(req: HttpRequest<any>, next: HttpHandler): Observable<HttpEvent<any>> {
    const token = localStorage.getItem('token');
    
    if (token) {
      req = req.clone({
        setHeaders: {
          Authorization: `Bearer ${token}`
        }
      });
    }
    
    return next.handle(req);
  }
}
```

---

## Testing

### Test Credentials
- **Admin**: `admin@soulsync.com` / `password123`
- **User**: `test@soulsync.com` / `password123`

### Postman Collection
Import the provided Postman collection for testing all endpoints.

### Test Environment
- **Base URL**: `http://localhost:8000/api/v1`
- **Database**: SQLite (test)
- **File Storage**: Local

---

This documentation covers all major endpoints and features. For detailed implementation examples and additional endpoints, refer to the Postman collection and backend source code. 