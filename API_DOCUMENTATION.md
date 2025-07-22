# SoulSync Matrimonial API Documentation

## Base URL
- **Development**: `http://localhost:8000/api/v1`
- **Production**: `https://api.soulsync.com/api/v1`

## Authentication
All protected endpoints require a Bearer token in the Authorization header:
```
Authorization: Bearer {token}
```

## Response Format
All API responses follow this structure:
```json
{
  "success": true|false,
  "message": "Response message",
  "data": {},
  "errors": {}
}
```

## Public Endpoints (No Authentication Required)

### Health Check
- **GET** `/health`
- Returns API status

### Authentication
- **POST** `/auth/register` - User registration
- **POST** `/auth/login` - User login
- **POST** `/auth/social-login` - Social media login
- **POST** `/auth/forgot-password` - Send password reset email
- **POST** `/auth/reset-password` - Reset password with token

### Public Data
- **GET** `/public/interests` - Get all interests
- **GET** `/public/countries` - Get all countries
- **GET** `/public/states/{country}` - Get states for country
- **GET** `/public/cities/{state}` - Get cities for state
- **GET** `/public/subscription-plans` - Get subscription plans

## Protected Endpoints (Authentication Required)

### User Authentication
- **GET** `/auth/me` - Get current user
- **POST** `/auth/logout` - Logout
- **POST** `/auth/logout-all` - Logout from all devices
- **POST** `/auth/change-password` - Change password
- **POST** `/auth/verify-email` - Verify email

### Profile Management
- **GET** `/profile` - Get user profile
- **PUT** `/profile` - Update profile
- **POST** `/profile/complete` - Mark profile as complete
- **GET** `/profile/completion-status` - Get completion status

### Photo Management
- **GET** `/profile/photos` - Get user photos
- **POST** `/profile/photos` - Upload photo
- **PUT** `/profile/photos/{photo}` - Update photo
- **DELETE** `/profile/photos/{photo}` - Delete photo
- **POST** `/profile/photos/{photo}/set-profile` - Set as profile picture
- **POST** `/profile/photos/{photo}/toggle-private` - Toggle privacy

### Voice Introduction
- **POST** `/profile/voice` - Upload voice intro
- **GET** `/profile/voice` - Get voice intro
- **DELETE** `/profile/voice` - Delete voice intro
- **GET** `/profile/voice/stream` - Stream voice intro
- **PUT** `/profile/voice/settings` - Update voice settings

### Preferences
- **GET** `/preferences` - Get user preferences
- **PUT** `/preferences` - Update preferences

### Horoscope
- **GET** `/horoscope` - Get user horoscope
- **POST** `/horoscope` - Create horoscope
- **PUT** `/horoscope` - Update horoscope
- **POST** `/horoscope/compatibility/{user}` - Check compatibility

### Matching & Search
- **GET** `/matches` - Get matches
- **GET** `/matches/daily` - Get daily matches
- **GET** `/matches/suggestions` - Get suggestions
- **POST** `/matches/{user}/like` - Like a user
- **POST** `/matches/{user}/super-like` - Super like a user
- **POST** `/matches/{user}/dislike` - Dislike a user
- **POST** `/matches/{user}/block` - Block a user
- **GET** `/matches/liked-me` - Who liked me
- **GET** `/matches/mutual` - Mutual matches

### Search & Browse
- **POST** `/search` - Basic search
- **POST** `/search/advanced` - Advanced search
- **GET** `/search/filters` - Get search filters
- **POST** `/search/save-search` - Save search criteria
- **GET** `/search/saved-searches` - Get saved searches

### Browse Profiles
- **GET** `/browse` - Browse all profiles
- **GET** `/browse/premium` - Browse premium profiles
- **GET** `/browse/recent` - Recently joined profiles
- **GET** `/browse/verified` - Verified profiles

### User Profiles (Others)
- **GET** `/users/{user}` - View user profile
- **POST** `/users/{user}/view` - Record profile view
- **POST** `/users/{user}/interest` - Express interest
- **POST** `/users/{user}/report` - Report user
- **GET** `/users/{user}/photos` - Get user photos
- **POST** `/users/{user}/request-photo-access` - Request photo access
- **GET** `/users/{user}/voice` - Get user voice intro
- **GET** `/users/{user}/voice/stream` - Stream user voice

### Chat & Messaging
- **GET** `/chat/conversations` - Get conversations
- **GET** `/chat/conversations/{conversation}` - Get conversation
- **POST** `/chat/conversations/{conversation}/messages` - Send message
- **PUT** `/chat/messages/{message}` - Update message
- **DELETE** `/chat/messages/{message}` - Delete message
- **POST** `/chat/messages/{message}/read` - Mark as read
- **POST** `/chat/conversations/{conversation}/block` - Block conversation
- **DELETE** `/chat/conversations/{conversation}` - Delete conversation

### Subscriptions
- **GET** `/subscription` - Get current subscription
- **GET** `/subscription/plans` - Get subscription plans
- **POST** `/subscription/subscribe` - Subscribe to plan
- **POST** `/subscription/cancel` - Cancel subscription
- **GET** `/subscription/history` - Get subscription history
- **POST** `/subscription/payment/verify` - Verify payment

### Notifications
- **GET** `/notifications` - Get notifications
- **POST** `/notifications/{notification}/read` - Mark as read
- **POST** `/notifications/read-all` - Mark all as read
- **DELETE** `/notifications/{notification}` - Delete notification
- **GET** `/notifications/unread-count` - Get unread count

### Settings
- **GET** `/settings` - Get settings
- **PUT** `/settings` - Update settings
- **PUT** `/settings/privacy` - Update privacy settings
- **PUT** `/settings/notifications` - Update notification settings
- **POST** `/settings/deactivate` - Deactivate account
- **POST** `/settings/delete` - Delete account
- **GET** `/settings/stats` - Get account stats
- **POST** `/settings/export-data` - Export user data

### Two-Factor Authentication
- **GET** `/2fa/status` - Get 2FA status
- **POST** `/2fa/setup` - Setup 2FA
- **POST** `/2fa/verify-setup` - Verify 2FA setup
- **POST** `/2fa/disable` - Disable 2FA
- **POST** `/2fa/recovery-codes` - Generate recovery codes
- **POST** `/2fa/send-code` - Send verification code

### Interests
- **GET** `/interests` - Get all interests
- **POST** `/interests` - Update user interests

### Premium Features (Insights)
- **GET** `/insights/profile-views` - Get profile view analytics
- **GET** `/insights/match-analytics` - Get match analytics
- **GET** `/insights/compatibility-reports` - Get compatibility reports
- **GET** `/insights/profile-optimization` - Get profile optimization tips

## Admin Endpoints (Role-based Access)

### Dashboard
- **GET** `/admin/dashboard` - Get dashboard data
- **GET** `/admin/stats` - Get admin statistics

### User Management
- **GET** `/admin/users` - Get all users
- **GET** `/admin/users/{user}` - Get user details
- **PUT** `/admin/users/{user}/status` - Update user status
- **PUT** `/admin/users/{user}/profile-status` - Update profile status
- **POST** `/admin/users/{user}/suspend` - Suspend user
- **POST** `/admin/users/{user}/ban` - Ban user
- **POST** `/admin/users/{user}/unban` - Unban user
- **DELETE** `/admin/users/{user}` - Delete user

### Photo Moderation
- **GET** `/admin/photos/pending` - Get pending photos
- **POST** `/admin/photos/{photo}/approve` - Approve photo
- **POST** `/admin/photos/{photo}/reject` - Reject photo

### Reports Management
- **GET** `/admin/reports` - Get all reports
- **GET** `/admin/reports/{report}` - Get report details
- **PUT** `/admin/reports/{report}/status` - Update report status
- **POST** `/admin/reports/{report}/action` - Take action on report

### Content Management
- **GET** `/admin/content/interests` - Get interests
- **POST** `/admin/content/interests` - Create interest
- **PUT** `/admin/content/interests/{interest}` - Update interest
- **DELETE** `/admin/content/interests/{interest}` - Delete interest

### System Settings
- **GET** `/admin/settings` - Get system settings
- **PUT** `/admin/settings` - Update system settings

## Webhook Endpoints

### Payment Webhooks
- **POST** `/webhooks/stripe` - Stripe webhook
- **POST** `/webhooks/payhere` - PayHere webhook
- **POST** `/webhooks/webxpay` - WebXPay webhook

## Error Codes

- **200** - Success
- **201** - Created
- **400** - Bad Request
- **401** - Unauthorized
- **403** - Forbidden
- **404** - Not Found
- **422** - Validation Error
- **429** - Rate Limit Exceeded
- **500** - Internal Server Error

## Rate Limiting

- **General API**: 60 requests per minute
- **Authentication**: 5 attempts per minute per IP
- **Premium users**: 2x rate limit boost

## CORS Configuration

The API supports CORS for frontend development:
- **Allowed Origins**: `*` (development) / specific domains (production)
- **Allowed Methods**: `GET, POST, PUT, DELETE, OPTIONS`
- **Allowed Headers**: `*`

## WebSocket Events (Real-time)

### Chat Events
- `message.sent` - New message received
- `user.online` - User came online
- `user.offline` - User went offline
- `conversation.typing` - User is typing

### Match Events
- `match.found` - New match found
- `match.liked` - Someone liked your profile
- `match.mutual` - Mutual match created

### Notification Events
- `notification.new` - New notification received
- `notification.read` - Notification was read

## Test Accounts

### Admin Account
- **Email**: admin@soulsync.com
- **Password**: password123
- **Role**: super-admin

### Regular User Account
- **Email**: test@soulsync.com
- **Password**: password123
- **Role**: user

## Environment Variables Required

```env
# App Configuration
APP_NAME=SoulSync
APP_URL=http://localhost:8000
APP_KEY=your_app_key

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=soulsync_matrimony
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Laravel Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost:3000,localhost:4200

# Pusher (Real-time)
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=mt1

# Payment Gateways
STRIPE_KEY=your_stripe_key
STRIPE_SECRET=your_stripe_secret
PAYHERE_MERCHANT_ID=your_merchant_id
PAYHERE_SECRET=your_payhere_secret

# Firebase FCM
FCM_SERVER_KEY=your_fcm_server_key
FIREBASE_PROJECT_ID=your_project_id

# Twilio SMS
TWILIO_SID=your_twilio_sid
TWILIO_AUTH_TOKEN=your_auth_token
TWILIO_FROM=your_phone_number

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email
MAIL_PASSWORD=your_password
```

## Getting Started

1. **Clone the repository**
2. **Install dependencies**: `composer install`
3. **Setup environment**: `cp .env.example .env`
4. **Generate key**: `php artisan key:generate`
5. **Run migrations**: `php artisan migrate`
6. **Seed database**: `php artisan db:seed`
7. **Start server**: `php artisan serve`
8. **Test API**: `curl http://localhost:8000/api/v1/health`

The API is now ready for frontend development! 🚀 