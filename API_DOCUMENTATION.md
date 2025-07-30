# SoulSync Matrimony API Documentation

## Table of Contents
- [Authentication](#authentication)
- [Email Verification](#email-verification)
- [User Profile](#user-profile)
- [Matching & Discovery](#matching--discovery)
- [Chat & Messaging](#chat--messaging)
- [Notifications](#notifications)
- [Payments & Subscriptions](#payments--subscriptions)
- [Webhooks](#webhooks)
- [Voice Messaging](#voice-messaging)
- [Social Login](#social-login)
- [Horoscope](#horoscope)
- [System](#system)

---

## Authentication

### Register
- **POST** `/api/auth/register`
- **Body:** `{ "email": "user@example.com", "password": "string", "first_name": "string", "last_name": "string" }`
- **Response:** `201 Created`
```
{
  "success": true,
  "user": { ... },
  "token": "..."
}
```

### Login
- **POST** `/api/auth/login`
- **Body:** `{ "email": "user@example.com", "password": "string" }`
- **Response:** `200 OK`
```
{
  "success": true,
  "user": { ... },
  "token": "..."
}
```

### Logout
- **POST** `/api/auth/logout`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

---

## Email Verification

### Verify Email
- **GET** `/api/email/verify/{id}/{hash}`
- **Response:** `200 OK` or `400/404/500`
```
{
  "success": true,
  "message": "Email verified successfully",
  "user": { ... }
}
```

### Resend Verification Email
- **POST** `/api/email/resend`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK` or `400/429`

### Check Verification Status
- **GET** `/api/email/check`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

---

## User Profile

### Get Profile
- **GET** `/api/user/profile`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Update Profile
- **PUT** `/api/user/profile`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ ... }`
- **Response:** `200 OK`

### Upload Photo
- **POST** `/api/user/photos`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `multipart/form-data` (file, is_primary)
- **Response:** `201 Created`

### Delete Photo
- **DELETE** `/api/user/photos/{id}`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

---

## Matching & Discovery

### Get Matches
- **GET** `/api/matches`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Like/Dislike User
- **POST** `/api/matches/{id}/like`
- **POST** `/api/matches/{id}/dislike`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Browse/Search Users
- **GET** `/api/browse`
- **POST** `/api/browse/search`
- **Header:** `Authorization: Bearer {token}`
- **Body (search):** `{ ... }`
- **Response:** `200 OK`

---

## Chat & Messaging

### Get Conversations
- **GET** `/api/conversations`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Get Messages
- **GET** `/api/conversations/{id}/messages`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Send Message
- **POST** `/api/conversations/{id}/messages`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "content": "string", "type": "text|image|voice", "attachment": "file (optional)" }`
- **Response:** `201 Created`

### Mark Message as Read
- **PUT** `/api/messages/{id}/read`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

---

## Notifications

### Get Notifications
- **GET** `/api/notifications`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Mark Notification as Read
- **PUT** `/api/notifications/{id}/read`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Delete Notification
- **DELETE** `/api/notifications/{id}`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

---

## Payments & Subscriptions

### Get Subscription Plans
- **GET** `/api/subscriptions/plans`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Create Subscription
- **POST** `/api/subscriptions/create`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "plan_id": "string", "payment_method": "stripe|paypal|payhere|webxpay", ... }`
- **Response:** `201 Created`

### Cancel Subscription
- **POST** `/api/subscriptions/cancel`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "subscription_id": "string" }`
- **Response:** `200 OK`

### Get Subscription Status
- **GET** `/api/subscriptions/status`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Payment Webhooks
- **POST** `/api/webhooks/stripe`
- **POST** `/api/webhooks/paypal`
- **POST** `/api/webhooks/payhere`
- **POST** `/api/webhooks/webxpay`
- **Body:** Raw webhook payload
- **Response:** `200 OK` or `400/500`

---

## Voice Messaging

### Send Voice Message
- **POST** `/api/conversations/{id}/messages`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `multipart/form-data` (type: voice, attachment: audio file)
- **Response:** `201 Created`

### Download Voice Message
- **GET** `/api/messages/{id}/download`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK` (audio file)

---

## Social Login

### Google Login
- **POST** `/api/auth/social/google`
- **Body:** `{ "token": "google_id_token" }`
- **Response:** `200 OK`

### Facebook Login
- **POST** `/api/auth/social/facebook`
- **Body:** `{ "token": "facebook_access_token" }`
- **Response:** `200 OK`

---

## Horoscope

### Get Horoscope
- **GET** `/api/horoscope/{sign}`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Get Horoscope Compatibility
- **GET** `/api/horoscope/compatibility?sign1=aries&sign2=leo`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

---

## System

### Health Check
- **GET** `/api/webhooks/health`
- **Response:** `200 OK`

---

## Status Codes
- `200 OK` - Success
- `201 Created` - Resource created
- `400 Bad Request` - Invalid input
- `401 Unauthorized` - Not authenticated
- `403 Forbidden` - Not authorized
- `404 Not Found` - Resource not found
- `409 Conflict` - Duplicate or conflict
- `422 Unprocessable Entity` - Validation error
- `429 Too Many Requests` - Rate limited
- `500 Internal Server Error` - Server error 