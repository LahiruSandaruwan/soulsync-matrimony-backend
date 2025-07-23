# SoulSync Matrimony API Documentation

## Overview

The SoulSync Matrimony API is a comprehensive RESTful API built with Laravel 10 for a global matrimonial platform. It provides all the necessary endpoints for user authentication, profile management, matching algorithms, real-time messaging, payment processing, and administrative functions.

## Base URL

```
Production: https://api.soulsync.com/v1
Development: http://localhost:8000/api/v1
```

## Authentication

The API uses Laravel Sanctum for authentication. Include the bearer token in the Authorization header:

```
Authorization: Bearer {your_token}
```

### Getting a Token

1. Register or login to receive an authentication token
2. Include this token in all subsequent requests
3. Tokens expire based on your configuration (default: never)

## Response Format

All API responses follow this consistent format:

```json
{
  "success": true,
  "message": "Success message",
  "data": {
    // Response data
  },
  "meta": {
    // Pagination or additional metadata
  }
}
```

### Error Response Format

```json
{
  "success": false,
  "message": "Error message",
  "error": "Detailed error description",
  "errors": {
    // Validation errors (for 422 responses)
  }
}
```

## Rate Limiting

API endpoints are rate limited to prevent abuse:

- **General API**: 100 requests per minute
- **Authentication**: 10 attempts per 15 minutes
- **Search**: 30 requests per minute
- **Premium users**: 2x the standard limits

Rate limit headers are included in responses:
- `X-RateLimit-Limit`: Maximum requests allowed
- `X-RateLimit-Remaining`: Requests remaining in window
- `X-RateLimit-Reset`: When the limit resets

## Endpoints

### Authentication

#### POST /auth/register
Register a new user account.

**Request Body:**
```json
{
  "first_name": "John",
  "last_name": "Doe",
  "email": "john.doe@example.com",
  "phone": "+1234567890",
  "password": "password123",
  "password_confirmation": "password123",
  "date_of_birth": "1990-01-15",
  "gender": "male",
  "country_code": "US",
  "terms_accepted": true,
  "privacy_accepted": true,
  "referral_code": "REF123" // Optional
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Account created successfully",
  "data": {
    "user": {
      "id": 1,
      "first_name": "John",
      "last_name": "Doe",
      "email": "john.doe@example.com",
      "date_of_birth": "1990-01-15",
      "age": 33,
      "gender": "male",
      "country_code": "US"
    },
    "token": "1|abc123...",
    "next_steps": [
      {
        "title": "Complete Your Profile",
        "description": "Add photos and personal details",
        "url": "/profile/edit"
      }
    ]
  }
}
```

#### POST /auth/login
Authenticate user and receive access token.

**Request Body:**
```json
{
  "email": "john.doe@example.com",
  "password": "password123",
  "remember_me": true
}
```

#### GET /auth/me
Get authenticated user's profile information.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "first_name": "John",
      "last_name": "Doe",
      "email": "john.doe@example.com",
      "age": 33,
      "is_premium": true,
      "premium_expires_at": "2024-02-15T10:30:00Z",
      "profile_completion": 85,
      "currency": {
        "base": "USD",
        "local": "LKR",
        "exchange_rate": 320.50
      }
    }
  }
}
```

#### POST /auth/logout
Logout user and revoke current token.

### Profile Management

#### GET /profile
Get user's complete profile information.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "basic_info": {
        "first_name": "John",
        "last_name": "Doe",
        "age": 33,
        "gender": "male"
      },
      "profile": {
        "height_cm": 175,
        "weight_kg": 70.5,
        "body_type": "average",
        "complexion": "fair",
        "current_city": "Colombo",
        "current_country": "Sri Lanka",
        "religion": "Buddhist",
        "caste": "Govi",
        "education_level": "Bachelor's",
        "occupation": "Software Engineer",
        "annual_income_usd": 35000,
        "about_me": "Looking for a life partner...",
        "family_details": "Close-knit family..."
      },
      "photos": [
        {
          "id": 1,
          "url": "https://storage.com/photos/photo1.jpg",
          "is_profile_picture": true,
          "is_private": false,
          "status": "approved"
        }
      ],
      "completion_percentage": 85
    },
    "can_edit": true
  }
}
```

#### PUT /profile
Update user profile information.

**Request Body:**
```json
{
  "profile": {
    "height_cm": 175,
    "current_city": "Kandy",
    "occupation": "Senior Software Engineer",
    "annual_income_usd": 45000,
    "about_me": "Updated bio text",
    "family_details": "Updated family information"
  }
}
```

#### POST /profile/photos
Upload a new profile photo.

**Request (multipart/form-data):**
- `photo`: Image file (max 10MB)
- `is_profile_picture`: boolean
- `is_private`: boolean

**Response (201):**
```json
{
  "success": true,
  "message": "Photo uploaded successfully",
  "data": {
    "photo": {
      "id": 2,
      "url": "https://storage.com/photos/photo2.jpg",
      "is_profile_picture": false,
      "status": "pending_approval",
      "uploaded_at": "2024-01-15T10:30:00Z"
    }
  }
}
```

### Matching & Discovery

#### GET /matches/daily
Get daily match suggestions for the user.

**Query Parameters:**
- `limit`: Number of matches (default: 10, max: 50)
- `min_score`: Minimum compatibility score (0-100)

**Response (200):**
```json
{
  "success": true,
  "data": {
    "matches": [
      {
        "id": 2,
        "first_name": "Jane",
        "age": 28,
        "profile_picture": "https://storage.com/photos/jane.jpg",
        "location": "Colombo, Sri Lanka",
        "compatibility_score": 87.5,
        "matching_factors": ["same_city", "same_religion", "similar_education"],
        "preview": {
          "height_cm": 165,
          "education": "Master's",
          "occupation": "Doctor"
        }
      }
    ],
    "total": 8,
    "last_updated": "2024-01-15T06:00:00Z"
  }
}
```

#### POST /matches/{userId}/like
Like another user's profile.

**Response (200):**
```json
{
  "success": true,
  "message": "Like sent successfully",
  "data": {
    "is_match": false,
    "match_id": null,
    "remaining_likes": 19
  }
}
```

**Mutual Match Response (200):**
```json
{
  "success": true,
  "message": "It's a match! 🎉",
  "data": {
    "is_match": true,
    "match_id": 123,
    "conversation_id": 456,
    "can_message": true
  }
}
```

#### POST /matches/{userId}/super-like
Send a super like to another user.

**Response (200):**
```json
{
  "success": true,
  "message": "Super like sent!",
  "data": {
    "is_match": false,
    "super_likes_remaining": 4
  }
}
```

#### GET /matches/mutual
Get mutual matches where both users liked each other.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "matches": [
      {
        "id": 123,
        "user": {
          "id": 2,
          "first_name": "Jane",
          "profile_picture": "https://storage.com/photos/jane.jpg"
        },
        "compatibility_score": 87.5,
        "matched_at": "2024-01-15T10:30:00Z",
        "conversation_id": 456,
        "last_message": {
          "content": "Hello! Nice to meet you.",
          "sent_at": "2024-01-15T11:00:00Z"
        }
      }
    ]
  }
}
```

#### GET /matches/liked-me (Premium Only)
Get users who liked the current user.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "likes": [
      {
        "id": 124,
        "user": {
          "id": 3,
          "first_name": "Sarah",
          "profile_picture": "https://storage.com/photos/sarah.jpg",
          "age": 26,
          "location": "Kandy, Sri Lanka"
        },
        "action_type": "super_liked",
        "liked_at": "2024-01-15T09:00:00Z",
        "message": "You seem like an amazing person!"
      }
    ],
    "total": 12,
    "new_count": 3
  }
}
```

### Search & Browse

#### POST /search/advanced
Advanced search with multiple criteria.

**Request Body:**
```json
{
  "age_range": {
    "min": 25,
    "max": 35
  },
  "location": {
    "countries": ["Sri Lanka", "India"],
    "states": ["Western Province"],
    "cities": ["Colombo"],
    "max_distance_km": 50
  },
  "education": ["Bachelor's", "Master's", "PhD"],
  "religion": ["Buddhist", "Hindu"],
  "caste": ["Govi", "Karawa"],
  "height_range": {
    "min_cm": 160,
    "max_cm": 180
  },
  "income_range": {
    "min_usd": 20000,
    "max_usd": 100000
  },
  "marital_status": ["never_married"],
  "have_children": false,
  "lifestyle": {
    "diet": ["vegetarian"],
    "smoking": ["never"],
    "drinking": ["never", "occasionally"]
  },
  "sort_by": "compatibility",
  "page": 1,
  "limit": 20
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "results": [
      {
        "id": 5,
        "first_name": "Priya",
        "age": 27,
        "profile_picture": "https://storage.com/photos/priya.jpg",
        "location": "Colombo, Sri Lanka",
        "compatibility_score": 92.3,
        "match_percentage": 89,
        "preview": {
          "education": "Master's in Engineering",
          "occupation": "Software Engineer",
          "height_cm": 162,
          "religion": "Buddhist"
        },
        "online_status": "online",
        "last_active": "2024-01-15T11:45:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 5,
      "total_results": 89,
      "has_more": true
    },
    "filters_applied": {
      "age_range": "25-35",
      "location": "Colombo, Sri Lanka",
      "education": "Bachelor's+",
      "religion": "Buddhist"
    }
  }
}
```

### Messaging

#### GET /chat/conversations
Get user's chat conversations.

**Query Parameters:**
- `page`: Page number (default: 1)
- `limit`: Items per page (default: 20)
- `status`: Filter by status (active, archived, blocked)

**Response (200):**
```json
{
  "success": true,
  "data": {
    "conversations": [
      {
        "id": 456,
        "other_user": {
          "id": 2,
          "first_name": "Jane",
          "profile_picture": "https://storage.com/photos/jane.jpg",
          "online_status": "online"
        },
        "last_message": {
          "content": "Looking forward to meeting you!",
          "type": "text",
          "sent_at": "2024-01-15T11:30:00Z",
          "sender_id": 2
        },
        "unread_count": 2,
        "status": "active",
        "created_at": "2024-01-15T10:00:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_conversations": 5,
      "has_more": false
    },
    "unread_total": 3
  }
}
```

#### GET /chat/conversations/{conversationId}
Get messages from a specific conversation.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "conversation": {
      "id": 456,
      "status": "active",
      "created_at": "2024-01-15T10:00:00Z"
    },
    "other_user": {
      "id": 2,
      "first_name": "Jane",
      "profile_picture": "https://storage.com/photos/jane.jpg",
      "last_active_at": "2024-01-15T11:45:00Z",
      "is_online": true
    },
    "messages": [
      {
        "id": 789,
        "content": "Hello! Nice to meet you.",
        "type": "text",
        "sender_id": 1,
        "sender_name": "John",
        "status": "read",
        "sent_at": "2024-01-15T10:30:00Z",
        "read_at": "2024-01-15T10:31:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_messages": 25,
      "has_more": true
    }
  }
}
```

#### POST /chat/conversations/{conversationId}/messages
Send a message in a conversation.

**Request Body:**
```json
{
  "content": "Hello! Nice to meet you.",
  "type": "text"
}
```

**For Media Messages (multipart/form-data):**
- `media`: File upload
- `type`: "image", "voice", or "file"
- `content`: Optional caption

**Response (201):**
```json
{
  "success": true,
  "message": "Message sent successfully",
  "data": {
    "message": {
      "id": 790,
      "content": "Hello! Nice to meet you.",
      "type": "text",
      "sender_id": 1,
      "status": "sent",
      "sent_at": "2024-01-15T11:45:00Z"
    }
  }
}
```

### Subscriptions & Payments

#### GET /subscription/plans
Get available subscription plans with pricing.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "plans": [
      {
        "type": "basic",
        "name": "Basic Plan",
        "price_usd": 4.99,
        "price_local": 1597.68,
        "currency": "LKR",
        "duration_months": 1,
        "features": [
          "10 daily matches",
          "Basic search filters",
          "Send messages to matches"
        ],
        "popular": false
      },
      {
        "type": "premium",
        "name": "Premium Plan",
        "price_usd": 9.99,
        "price_local": 3195.36,
        "currency": "LKR",
        "duration_months": 1,
        "features": [
          "50 daily matches",
          "Advanced search filters",
          "See who liked you",
          "Read receipts",
          "Priority customer support"
        ],
        "popular": true
      }
    ],
    "current_plan": "free",
    "exchange_rate": 320.0
  }
}
```

#### POST /subscription/subscribe
Subscribe to a plan.

**Request Body:**
```json
{
  "plan_type": "premium",
  "duration_months": 1,
  "payment_method": "stripe",
  "payment_token": "tok_visa",
  "currency": "USD",
  "coupon_code": "SAVE20"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Subscription activated successfully",
  "data": {
    "subscription": {
      "id": 123,
      "plan_type": "premium",
      "status": "active",
      "amount_paid": 9.99,
      "currency": "USD",
      "starts_at": "2024-01-15T12:00:00Z",
      "expires_at": "2024-02-15T12:00:00Z"
    },
    "features_unlocked": [
      "Advanced search",
      "See who liked you",
      "Priority support"
    ]
  }
}
```

### Horoscope

#### GET /horoscope
Get user's horoscope information.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "horoscope": {
      "birth_date": "1990-01-15",
      "birth_time": "14:30:00",
      "birth_place": "Colombo, Sri Lanka",
      "zodiac_sign": "Capricorn",
      "moon_sign": "Taurus",
      "nakshatra": "Rohini",
      "ascendant": "Leo",
      "manglik": false,
      "guna_milan_score": 28,
      "compatibility_factors": [
        "Strong Venus placement",
        "Compatible moon signs"
      ]
    },
    "has_horoscope": true,
    "summary": {
      "personality": "Practical and ambitious",
      "compatibility": "Best with earth and water signs",
      "lucky_numbers": [2, 6, 9],
      "lucky_colors": ["Blue", "Green"]
    }
  }
}
```

#### POST /horoscope
Add or update horoscope information.

**Request Body:**
```json
{
  "birth_date": "1990-01-15",
  "birth_time": "14:30",
  "birth_place": "Colombo, Sri Lanka",
  "birth_coordinates": {
    "latitude": 6.9271,
    "longitude": 79.8612
  },
  "zodiac_sign": "Capricorn",
  "moon_sign": "Taurus",
  "nakshatra": "Rohini",
  "manglik_status": "no"
}
```

#### POST /horoscope/compatibility/{userId}
Check horoscope compatibility with another user.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "compatibility_score": 78.5,
    "guna_milan_score": 28,
    "detailed_analysis": {
      "varna": 4,
      "vasya": 2,
      "tara": 3,
      "yoni": 4,
      "graha_maitri": 5,
      "gana": 6,
      "bhakoot": 0,
      "nadi": 8
    },
    "summary": "Good compatibility with some considerations",
    "recommendations": [
      "Mars placement needs attention",
      "Consider performing remedies"
    ]
  }
}
```

### Admin Endpoints

#### GET /admin/dashboard
Get admin dashboard statistics.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "users": {
      "total": 10540,
      "active": 8932,
      "premium": 1205,
      "new_today": 45
    },
    "activity": {
      "daily_logins": 2341,
      "matches_made": 156,
      "messages_sent": 4523,
      "new_registrations": 45
    },
    "revenue": {
      "total_monthly": 12580.50,
      "new_subscriptions": 23,
      "churned_users": 8
    },
    "moderation": {
      "pending_photos": 45,
      "pending_reports": 12,
      "flagged_profiles": 8
    }
  }
}
```

#### GET /admin/users
Get users list for admin management.

**Query Parameters:**
- `page`: Page number
- `limit`: Items per page
- `status`: Filter by status
- `search`: Search by name or email

**Response (200):**
```json
{
  "success": true,
  "data": {
    "users": [
      {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe",
        "email": "john.doe@example.com",
        "status": "active",
        "profile_status": "approved",
        "is_premium": true,
        "created_at": "2024-01-01T10:00:00Z",
        "last_active_at": "2024-01-15T11:30:00Z",
        "profile_completion": 85
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 425,
      "total_users": 10540
    }
  }
}
```

## Error Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created successfully |
| 400 | Bad request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not found |
| 422 | Validation error |
| 429 | Rate limit exceeded |
| 500 | Server error |

## Webhooks

### Payment Webhooks

The API supports webhooks for payment gateway events:

#### Stripe Webhook
```
POST /webhooks/stripe
```

#### PayHere Webhook
```
POST /webhooks/payhere
```

### Event Types
- `payment.succeeded`
- `payment.failed`
- `subscription.created`
- `subscription.cancelled`

## SDKs and Libraries

- **JavaScript/TypeScript**: Coming soon
- **React Native**: Coming soon
- **PHP**: Use standard HTTP client
- **Python**: Use requests library

## Support

For API support and questions:
- Email: dev@soulsync.com
- Documentation: https://docs.soulsync.com
- Status Page: https://status.soulsync.com

## Changelog

### Version 1.0.0 (2024-01-15)
- Initial API release
- Complete authentication system
- Profile management
- Matching algorithms
- Real-time messaging
- Payment processing
- Admin panel
- Horoscope compatibility 