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
- [Preferences](#preferences)
- [Search & Browse](#search--browse)
- [User Management](#user-management)
- [Settings](#settings)
- [Two-Factor Authentication](#two-factor-authentication)
- [Interests](#interests)
- [Horoscope](#horoscope)
- [Insights (Premium)](#insights-premium)
- [Admin Panel](#admin-panel)
- [Public Endpoints](#public-endpoints)
- [System](#system)

---

## Authentication

### Register
- **POST** `/api/v1/auth/register`
- **Body:** `{ "email": "user@example.com", "password": "string", "password_confirmation": "string", "first_name": "string", "last_name": "string", "date_of_birth": "YYYY-MM-DD", "gender": "male|female|other", "country_code": "LK", "terms_accepted": true, "privacy_accepted": true }`
- **Response:** `201 Created`

### Login
- **POST** `/api/v1/auth/login`
- **Body:** `{ "email": "user@example.com", "password": "string" }`
- **Response:** `200 OK`

### Social Login
- **POST** `/api/v1/auth/social-login`
- **Body:** `{ "provider": "google|facebook", "token": "social_token" }`
- **Response:** `200 OK`

### Get Current User
- **GET** `/api/v1/auth/me`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Logout
- **POST** `/api/v1/auth/logout`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Logout All Devices
- **POST** `/api/v1/auth/logout-all`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Change Password
- **POST** `/api/v1/auth/change-password`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "current_password": "string", "password": "string", "password_confirmation": "string" }`
- **Response:** `200 OK`

### Delete Account
- **DELETE** `/api/v1/auth/delete-account`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "password": "string" }`
- **Response:** `200 OK`

### Forgot Password
- **POST** `/api/v1/auth/forgot-password`
- **Body:** `{ "email": "user@example.com" }`
- **Response:** `200 OK`

### Reset Password
- **POST** `/api/v1/auth/reset-password`
- **Body:** `{ "token": "reset_token", "email": "user@example.com", "password": "string", "password_confirmation": "string" }`
- **Response:** `200 OK`

---

## Email Verification

### Verify Email
- **GET** `/api/email/verify/{id}/{hash}`
- **Response:** `200 OK` or `400/404/500`

### Check Verification Status
- **GET** `/api/email/verify/{id}/status`
- **Response:** `200 OK`

### Resend Verification Email
- **POST** `/api/v1/email/resend`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK` or `400/429`

### Check Verification Status (Authenticated)
- **GET** `/api/v1/email/check`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

---

## User Profile

### Get Profile
- **GET** `/api/v1/profile`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Update Profile
- **PUT** `/api/v1/profile`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "height_cm": 170, "weight_kg": 65, "body_type": "slim|average|athletic|heavy", "complexion": "very_fair|fair|wheatish|brown|dark", "blood_group": "A+|A-|B+|B-|AB+|AB-|O+|O-", "current_city": "string", "current_state": "string", "current_country": "string", "education_level": "high_school|diploma|bachelors|masters|phd|other", "occupation": "string", "company": "string", "job_title": "string", "annual_income_usd": 50000, "religion": "string", "caste": "string", "mother_tongue": "string", "languages_known": ["en", "si"], "family_type": "nuclear|joint", "family_status": "middle_class|upper_middle_class|rich|affluent", "diet": "vegetarian|non_vegetarian|vegan|jain|occasionally_non_veg", "smoking": "never|occasionally|regularly", "drinking": "never|occasionally|socially|regularly", "hobbies": ["reading", "traveling"], "about_me": "string", "looking_for": "string", "marital_status": "never_married|divorced|widowed|separated", "have_children": false, "children_count": 0, "willing_to_relocate": true, "preferred_locations": ["Colombo", "Kandy"] }`
- **Response:** `200 OK`

### Complete Profile
- **POST** `/api/v1/profile/complete`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ ... }`
- **Response:** `200 OK`

### Get Profile Completion Status
- **GET** `/api/v1/profile/completion-status`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Get Photos
- **GET** `/api/v1/profile/photos`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Upload Photo
- **POST** `/api/v1/profile/photos`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `multipart/form-data` (file, is_primary, is_private)
- **Response:** `201 Created`

### Update Photo
- **PUT** `/api/v1/profile/photos/{photo}`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "is_primary": false, "is_private": false }`
- **Response:** `200 OK`

### Delete Photo
- **DELETE** `/api/v1/profile/photos/{photo}`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Set Photo as Profile Picture
- **POST** `/api/v1/profile/photos/{photo}/set-profile`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Toggle Photo Privacy
- **POST** `/api/v1/profile/photos/{photo}/toggle-private`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

---

## Voice Messaging

### Upload Voice Intro
- **POST** `/api/v1/profile/voice`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `multipart/form-data` (audio file)
- **Response:** `201 Created`

### Get Voice Intro
- **GET** `/api/v1/profile/voice`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Delete Voice Intro
- **DELETE** `/api/v1/profile/voice`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Stream Voice Intro
- **GET** `/api/v1/profile/voice/stream`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK` (audio stream)

### Update Voice Settings
- **PUT** `/api/v1/profile/voice/settings`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "is_public": true, "allow_download": false }`
- **Response:** `200 OK`

---

## Matching & Discovery

### Get Matches
- **GET** `/api/v1/matches`
- **Header:** `Authorization: Bearer {token}`
- **Query:** `?page=1&per_page=20&filter=all`
- **Response:** `200 OK`

### Get Daily Matches
- **GET** `/api/v1/matches/daily`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Get Match Suggestions
- **GET** `/api/v1/matches/suggestions`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Like User
- **POST** `/api/v1/matches/{user}/like`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Super Like User
- **POST** `/api/v1/matches/{user}/super-like`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Dislike User
- **POST** `/api/v1/matches/{user}/dislike`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Block User
- **POST** `/api/v1/matches/{user}/block`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Boost Match
- **POST** `/api/v1/matches/{match}/boost`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Get Who Liked Me
- **GET** `/api/v1/matches/liked-me`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Get Mutual Matches
- **GET** `/api/v1/matches/mutual`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

---

## Chat & Messaging

### Get Conversations
- **GET** `/api/v1/chat/conversations`
- **Header:** `Authorization: Bearer {token}`
- **Query:** `?page=1&per_page=20`
- **Response:** `200 OK`

### Get Specific Conversation
- **GET** `/api/v1/chat/conversations/{conversation}`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Send Message
- **POST** `/api/v1/chat/conversations/{conversation}/messages`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "content": "string", "type": "text|image|voice", "attachment": "file (optional)" }`
- **Response:** `201 Created`

### Update Message
- **PUT** `/api/v1/chat/messages/{message}`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "content": "string" }`
- **Response:** `200 OK`

### Delete Message
- **DELETE** `/api/v1/chat/messages/{message}`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Mark Message as Read
- **POST** `/api/v1/chat/messages/{message}/read`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Block Conversation
- **POST** `/api/v1/chat/conversations/{conversation}/block`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Delete Conversation
- **DELETE** `/api/v1/chat/conversations/{conversation}`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

---

## Notifications

### Get Notifications
- **GET** `/api/v1/notifications`
- **Header:** `Authorization: Bearer {token}`
- **Query:** `?page=1&per_page=20&type=all&category=all&read_status=all`
- **Response:** `200 OK`

### Get Unread Count
- **GET** `/api/v1/notifications/unread-count`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Get Specific Notification
- **GET** `/api/v1/notifications/{notification}`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Mark Notification as Read
- **POST** `/api/v1/notifications/{notification}/read`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Mark All Notifications as Read
- **POST** `/api/v1/notifications/read-all`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Mark Batch Notifications as Read
- **POST** `/api/v1/notifications/batch/read`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "notification_ids": [1, 2, 3] }`
- **Response:** `200 OK`

### Delete Notification
- **DELETE** `/api/v1/notifications/{notification}`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Cleanup Old Notifications
- **POST** `/api/v1/notifications/cleanup`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

---

## Payments & Subscriptions

### Get Subscription Plans
- **GET** `/api/v1/subscription/plans`
- **Response:** `200 OK`

### Get Current Subscription
- **GET** `/api/v1/subscription`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Get Subscription Status
- **GET** `/api/v1/subscription/status`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Get Subscription Features
- **GET** `/api/v1/subscription/features`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Subscribe to Plan
- **POST** `/api/v1/subscription/subscribe`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "plan_type": "basic|premium|platinum", "payment_method": "stripe|paypal|payhere|webxpay", "payment_token": "string", "duration_months": 1, "auto_renewal": true, "billing_details": { ... } }`
- **Response:** `201 Created`

### Cancel Subscription
- **POST** `/api/v1/subscription/cancel`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Reactivate Subscription
- **POST** `/api/v1/subscription/reactivate`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Upgrade Subscription
- **POST** `/api/v1/subscription/upgrade`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "plan_type": "premium|platinum", "payment_method": "stripe|paypal|payhere|webxpay", "payment_token": "string" }`
- **Response:** `200 OK`

### Downgrade Subscription
- **POST** `/api/v1/subscription/downgrade`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "plan_type": "basic|premium" }`
- **Response:** `200 OK`

### Start Trial
- **POST** `/api/v1/subscription/start-trial`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "plan_type": "premium|platinum" }`
- **Response:** `200 OK`

### Get Subscription History
- **GET** `/api/v1/subscription/history`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Verify Payment
- **POST** `/api/v1/subscription/payment/verify`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "payment_id": "string", "payment_method": "stripe|paypal|payhere|webxpay" }`
- **Response:** `200 OK`

### Process Refund
- **POST** `/api/v1/subscription/payment/refund`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "subscription_id": "string", "amount": 1000, "reason": "string" }`
- **Response:** `200 OK`

### Process Renewals
- **POST** `/api/v1/subscription/process-renewals`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

---

## Webhooks

### Health Check
- **GET** `/api/webhooks/health`
- **Response:** `200 OK`

### Test Webhook
- **POST** `/api/webhooks/test`
- **Body:** `{ "test": true }`
- **Response:** `200 OK`

### Stripe Webhook
- **POST** `/api/webhooks/stripe`
- **Header:** `Stripe-Signature: {signature}`
- **Body:** Raw webhook payload
- **Response:** `200 OK` or `400/500`

### PayPal Webhook
- **POST** `/api/webhooks/paypal`
- **Body:** Raw webhook payload
- **Response:** `200 OK` or `400/500`

### PayHere Webhook
- **POST** `/api/webhooks/payhere`
- **Body:** Raw webhook payload
- **Response:** `200 OK` or `400/500`

### WebXPay Webhook
- **POST** `/api/webhooks/webxpay`
- **Body:** Raw webhook payload
- **Response:** `200 OK` or `400/500`

---

## Preferences

### Get Preferences
- **GET** `/api/v1/preferences`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Update Preferences
- **PUT** `/api/v1/preferences`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "age_min": 25, "age_max": 35, "height_min": 150, "height_max": 180, "education_level": ["bachelors", "masters"], "religion": ["Buddhism", "Christianity"], "location_preference": "same_city|same_state|same_country|anywhere", "max_distance_km": 50, "deal_breakers": ["smoking", "drinking"], "preferred_diet": ["vegetarian", "non_vegetarian"] }`
- **Response:** `200 OK`

---

## Search & Browse

### Search Users
- **POST** `/api/v1/search`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "query": "string", "filters": { ... } }`
- **Response:** `200 OK`

### Advanced Search
- **POST** `/api/v1/search/advanced`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "age_range": [25, 35], "location": "Colombo", "education": ["bachelors"], "religion": ["Buddhism"], "marital_status": ["never_married"], "has_children": false, "willing_to_relocate": true }`
- **Response:** `200 OK`

### Get Search Filters
- **GET** `/api/v1/search/filters`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Save Search
- **POST** `/api/v1/search/save-search`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "name": "string", "filters": { ... } }`
- **Response:** `201 Created`

### Get Saved Searches
- **GET** `/api/v1/search/saved-searches`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Browse Users
- **GET** `/api/v1/browse`
- **Header:** `Authorization: Bearer {token}`
- **Query:** `?page=1&per_page=20&filter=all`
- **Response:** `200 OK`

### Browse Premium Profiles
- **GET** `/api/v1/browse/premium`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Browse Recently Joined
- **GET** `/api/v1/browse/recent`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Browse Verified Profiles
- **GET** `/api/v1/browse/verified`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

---

## User Management

### Get User Profile
- **GET** `/api/v1/users/{user}`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Record Profile View
- **POST** `/api/v1/users/{user}/view`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Express Interest
- **POST** `/api/v1/users/{user}/interest`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "message": "string" }`
- **Response:** `200 OK`

### Report User
- **POST** `/api/v1/users/{user}/report`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "reason": "string", "description": "string", "evidence": "string" }`
- **Response:** `200 OK`

### Get User Photos
- **GET** `/api/v1/users/{user}/photos`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Request Photo Access
- **POST** `/api/v1/users/{user}/request-photo-access`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "message": "string" }`
- **Response:** `200 OK`

### Get User Voice
- **GET** `/api/v1/users/{user}/voice`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Stream User Voice
- **GET** `/api/v1/users/{user}/voice/stream`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK` (audio stream)

---

## Settings

### Get Settings
- **GET** `/api/v1/settings`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Update Settings
- **PUT** `/api/v1/settings`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "language": "en|si|ta", "timezone": "Asia/Colombo", "currency": "USD|LKR" }`
- **Response:** `200 OK`

### Update Privacy Settings
- **PUT** `/api/v1/settings/privacy`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "profile_visibility": "public|friends|private", "hide_last_seen": true, "incognito_mode": false, "show_online_status": true }`
- **Response:** `200 OK`

### Update Notification Settings
- **PUT** `/api/v1/settings/notifications`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "email_notifications": true, "sms_notifications": false, "push_notifications": true, "notification_types": { "matches": true, "messages": true, "profile_views": false } }`
- **Response:** `200 OK`

### Get Account Stats
- **GET** `/api/v1/settings/stats`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Export Data
- **POST** `/api/v1/settings/export-data`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Deactivate Account
- **POST** `/api/v1/settings/deactivate`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "reason": "string", "feedback": "string" }`
- **Response:** `200 OK`

### Delete Account
- **POST** `/api/v1/settings/delete`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "password": "string", "reason": "string" }`
- **Response:** `200 OK`

---

## Two-Factor Authentication

### Get 2FA Status
- **GET** `/api/v1/2fa/status`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Setup 2FA
- **POST** `/api/v1/2fa/setup`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Verify 2FA Setup
- **POST** `/api/v1/2fa/verify-setup`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "code": "string" }`
- **Response:** `200 OK`

### Disable 2FA
- **POST** `/api/v1/2fa/disable`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "password": "string" }`
- **Response:** `200 OK`

### Generate Recovery Codes
- **POST** `/api/v1/2fa/recovery-codes`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Send 2FA Code
- **POST** `/api/v1/2fa/send-code`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "method": "email|sms" }`
- **Response:** `200 OK`

---

## Interests

### Get Interests
- **GET** `/api/v1/interests`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Update User Interests
- **POST** `/api/v1/interests`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "interests": [1, 2, 3] }`
- **Response:** `200 OK`

---

## Horoscope

### Get Horoscope
- **GET** `/api/v1/horoscope`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

### Create Horoscope
- **POST** `/api/v1/horoscope`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "sun_sign": "aries|taurus|gemini|cancer|leo|virgo|libra|scorpio|sagittarius|capricorn|aquarius|pisces", "moon_sign": "string", "birth_time": "HH:MM", "birth_place": "string" }`
- **Response:** `201 Created`

### Update Horoscope
- **PUT** `/api/v1/horoscope`
- **Header:** `Authorization: Bearer {token}`
- **Body:** `{ "sun_sign": "string", "moon_sign": "string", "birth_time": "string", "birth_place": "string" }`
- **Response:** `200 OK`

### Check Compatibility
- **POST** `/api/v1/horoscope/compatibility/{user}`
- **Header:** `Authorization: Bearer {token}`
- **Response:** `200 OK`

---

## Insights (Premium)

### Get Profile Views
- **GET** `/api/v1/insights/profile-views`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `premium`
- **Response:** `200 OK`

### Get Match Analytics
- **GET** `/api/v1/insights/match-analytics`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `premium`
- **Response:** `200 OK`

### Get Compatibility Reports
- **GET** `/api/v1/insights/compatibility-reports`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `premium`
- **Response:** `200 OK`

### Get Profile Optimization
- **GET** `/api/v1/insights/profile-optimization`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `premium`
- **Response:** `200 OK`

---

## Admin Panel

### Dashboard
- **GET** `/api/v1/admin/dashboard`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Response:** `200 OK`

### Get Stats
- **GET** `/api/v1/admin/stats`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Response:** `200 OK`

### Get Users
- **GET** `/api/v1/admin/users`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Query:** `?page=1&per_page=20&status=all&role=all`
- **Response:** `200 OK`

### Get User Analytics
- **GET** `/api/v1/admin/users/analytics`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Response:** `200 OK`

### Export Users
- **POST** `/api/v1/admin/users/export`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Body:** `{ "format": "csv|excel", "filters": { ... } }`
- **Response:** `200 OK`

### Bulk Action on Users
- **POST** `/api/v1/admin/users/bulk-action`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Body:** `{ "action": "suspend|ban|delete", "user_ids": [1, 2, 3] }`
- **Response:** `200 OK`

### Get Specific User
- **GET** `/api/v1/admin/users/{user}`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Response:** `200 OK`

### Update User Status
- **PUT** `/api/v1/admin/users/{user}/status`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Body:** `{ "status": "active|inactive|suspended|banned" }`
- **Response:** `200 OK`

### Update User Profile Status
- **PUT** `/api/v1/admin/users/{user}/profile-status`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Body:** `{ "profile_status": "pending|approved|rejected" }`
- **Response:** `200 OK`

### Suspend User
- **POST** `/api/v1/admin/users/{user}/suspend`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Body:** `{ "reason": "string", "duration_days": 7 }`
- **Response:** `200 OK`

### Ban User
- **POST** `/api/v1/admin/users/{user}/ban`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Body:** `{ "reason": "string" }`
- **Response:** `200 OK`

### Unban User
- **POST** `/api/v1/admin/users/{user}/unban`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Response:** `200 OK`

### Delete User
- **DELETE** `/api/v1/admin/users/{user}`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Response:** `200 OK`

### Get Pending Photos
- **GET** `/api/v1/admin/photos/pending`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Response:** `200 OK`

### Approve Photo
- **POST** `/api/v1/admin/photos/{photo}/approve`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Response:** `200 OK`

### Reject Photo
- **POST** `/api/v1/admin/photos/{photo}/reject`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Body:** `{ "reason": "string" }`
- **Response:** `200 OK`

### Get Reports
- **GET** `/api/v1/admin/reports`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Response:** `200 OK`

### Get Specific Report
- **GET** `/api/v1/admin/reports/{report}`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Response:** `200 OK`

### Update Report Status
- **PUT** `/api/v1/admin/reports/{report}/status`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Body:** `{ "status": "pending|investigating|resolved|dismissed" }`
- **Response:** `200 OK`

### Take Action on Report
- **POST** `/api/v1/admin/reports/{report}/action`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Body:** `{ "action": "warn|suspend|ban", "action_details": "string" }`
- **Response:** `200 OK`

### Get Interests (Admin)
- **GET** `/api/v1/admin/content/interests`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Response:** `200 OK`

### Create Interest
- **POST** `/api/v1/admin/content/interests`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Body:** `{ "name": "string", "category": "string", "description": "string" }`
- **Response:** `201 Created`

### Update Interest
- **PUT** `/api/v1/admin/content/interests/{interest}`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Body:** `{ "name": "string", "category": "string", "description": "string" }`
- **Response:** `200 OK`

### Delete Interest
- **DELETE** `/api/v1/admin/content/interests/{interest}`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Response:** `200 OK`

### Get System Settings
- **GET** `/api/v1/admin/settings`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Response:** `200 OK`

### Update System Settings
- **PUT** `/api/v1/admin/settings`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Body:** `{ "category": "general|security|notifications|payments", "settings": { ... } }`
- **Response:** `200 OK`

### Get System Health
- **GET** `/api/v1/admin/system/health`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Response:** `200 OK`

### Get Revenue Analytics
- **GET** `/api/v1/admin/revenue/analytics`
- **Header:** `Authorization: Bearer {token}`
- **Middleware:** `admin`
- **Response:** `200 OK`

---

## Public Endpoints

### Get Interests (Public)
- **GET** `/api/v1/public/interests`
- **Response:** `200 OK`

### Get Countries
- **GET** `/api/v1/public/countries`
- **Response:** `200 OK`

### Get States
- **GET** `/api/v1/public/states/{country}`
- **Response:** `200 OK`

### Get Cities
- **GET** `/api/v1/public/cities/{state}`
- **Response:** `200 OK`

### Get Subscription Plans (Public)
- **GET** `/api/v1/public/subscription-plans`
- **Response:** `200 OK`

---

## System

### Health Check
- **GET** `/api/v1/health`
- **Response:** `200 OK`
```
{
  "status": "ok",
  "message": "SoulSync API is running",
  "version": "1.0.0",
  "timestamp": "2025-01-20T10:00:00Z"
}
```

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

---

## Authentication

All protected endpoints require a Bearer token in the Authorization header:
```
Authorization: Bearer {your_token_here}
```

## Rate Limiting

The API implements rate limiting on authentication endpoints:
- Register: 5 requests per minute
- Login: 10 requests per minute
- Forgot Password: 3 requests per hour
- Reset Password: 5 requests per hour

## Pagination

List endpoints support pagination with the following query parameters:
- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 20, max: 100)

## File Uploads

File uploads support the following formats:
- **Images**: JPG, JPEG, PNG, GIF (max: 5MB)
- **Audio**: MP3, WAV, M4A (max: 10MB)

## WebSocket Events

Real-time events are available via WebSocket connection:
- `user.online` - User comes online
- `user.offline` - User goes offline
- `message.received` - New message received
- `match.created` - New match created
- `notification.received` - New notification received 