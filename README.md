# SoulSync - Matrimonial Platform Backend

A globally competitive matrimonial platform backend built with Laravel 10, featuring comprehensive user profiles, AI-powered matching, real-time chat, and multi-currency subscription system.

## 🌟 Features

### Core Features
- **User Authentication**: Email/phone/social login with Laravel Sanctum
- **Comprehensive Profiles**: Detailed personal, family, cultural, and lifestyle information
- **AI-Powered Matching**: Intelligent compatibility scoring and suggestions
- **Horoscope Compatibility**: Vedic astrology matching and analysis
- **Real-time Messaging**: Chat system with media support
- **Multi-tier Subscriptions**: Flexible pricing with global currency support
- **Photo Management**: Profile pictures and private albums with moderation
- **Voice Introductions**: Audio profile introductions
- **Advanced Search**: Multiple filters and saved searches
- **Admin Panel**: Comprehensive moderation and management tools

### Global Features
- **Multi-currency Support**: USD base with local currency display (LKR for Sri Lanka)
- **Multi-language**: English, Sinhala, Tamil support
- **Location-based Matching**: GPS and city-based filtering
- **Payment Gateways**: Stripe/PayPal (global), PayHere/WebXPay (Sri Lanka)
- **Exchange Rate Integration**: Real-time currency conversion

## 🛠 Tech Stack

- **Framework**: Laravel 10
- **Database**: MySQL (production) / SQLite (development)
- **Authentication**: Laravel Sanctum
- **Real-time**: Pusher/WebSockets
- **File Storage**: Laravel Storage (AWS S3 ready)
- **Image Processing**: Intervention Image
- **Permissions**: Spatie Laravel Permission
- **API**: RESTful APIs with comprehensive documentation

## 📋 Requirements

- PHP >= 8.1
- Composer
- MySQL 8.0+ (production) or SQLite (development)
- Node.js & NPM (for frontend assets)
- Redis (optional, for caching and queues)

## 🚀 Installation

### 1. Clone and Setup
```bash
git clone <repository-url>
cd matrimony-backend
composer install
cp .env.example .env
php artisan key:generate
```

### 2. Database Configuration

For development (SQLite):
```env
DB_CONNECTION=sqlite
```

For production (MySQL):
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=soulsync_matrimony
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 3. Run Migrations and Seeders
```bash
php artisan migrate
php artisan db:seed
```

### 4. Configure Services

Update `.env` with your service credentials:
```env
# Pusher (Real-time chat)
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=mt1

# Exchange Rate API
EXCHANGE_RATE_API_KEY=your_api_key

# Payment Gateways
STRIPE_KEY=your_stripe_key
STRIPE_SECRET=your_stripe_secret
PAYHERE_MERCHANT_ID=your_payhere_id
PAYHERE_SECRET=your_payhere_secret

# AI/ML Services
OPENAI_API_KEY=your_openai_key
```

### 5. Start Development Server
```bash
php artisan serve
```

## 📊 Database Schema

### Core Tables
- **users**: Extended with matrimonial fields (gender, DOB, preferences, etc.)
- **user_profiles**: Detailed personal, family, and lifestyle information
- **user_preferences**: Partner matching criteria and filters
- **user_photos**: Profile pictures and private album management
- **matches**: AI-generated matches and user interactions
- **conversations & messages**: Real-time chat system
- **subscriptions**: Multi-tier subscription management
- **horoscopes**: Detailed astrological information and compatibility

### Supporting Tables
- **interests**: Hobbies and interest categories
- **notifications**: In-app and push notifications
- **reports**: User reports and moderation
- **roles & permissions**: RBAC system

## 🔐 API Authentication

The API uses Laravel Sanctum for authentication. Include the bearer token in requests:

```http
Authorization: Bearer {your_token}
```

## 📡 API Endpoints

### Authentication
```
POST /api/v1/auth/register - User registration
POST /api/v1/auth/login - User login
POST /api/v1/auth/social-login - Social authentication
GET  /api/v1/auth/me - Get authenticated user
POST /api/v1/auth/logout - Logout user
```

### Profile Management
```
GET  /api/v1/profile - Get user profile
PUT  /api/v1/profile - Update profile
POST /api/v1/profile/photos - Upload photos
GET  /api/v1/profile/completion-status - Profile completion
```

### Matching & Search
```
GET  /api/v1/matches - Get matches
GET  /api/v1/matches/daily - Daily suggestions
POST /api/v1/matches/{user}/like - Like a profile
POST /api/v1/search - Search profiles
POST /api/v1/search/advanced - Advanced search
```

### Messaging
```
GET  /api/v1/chat/conversations - Get conversations
POST /api/v1/chat/conversations/{id}/messages - Send message
POST /api/v1/chat/messages/{id}/read - Mark as read
```

### Subscriptions
```
GET  /api/v1/subscription/plans - Get subscription plans
POST /api/v1/subscription/subscribe - Subscribe to plan
POST /api/v1/subscription/cancel - Cancel subscription
```

## 👥 User Roles

- **super-admin**: Full system access
- **admin**: User management, content moderation
- **moderator**: Photo/profile moderation
- **premium-user**: Premium features enabled
- **user**: Basic platform access
- **suspended**: Limited access

## 💰 Subscription Plans

1. **Free**: Basic features, limited matches
2. **Basic** ($4.99/month): Extended features, more matches
3. **Premium** ($9.99/month): Advanced features, unlimited matches
4. **Platinum** ($19.99/month): All features, priority support

*All prices in USD with local currency conversion*

## 🧪 Testing

### Test Users
After running seeders, you can use these test accounts:

**Admin User:**
- Email: admin@soulsync.com
- Password: password123

**Regular User:**
- Email: test@soulsync.com
- Password: password123

### Running Tests
```bash
php artisan test
```

## 🔧 Configuration

### Permissions
The platform uses role-based permissions for features like:
- Profile viewing/editing
- Photo uploads
- Premium features
- Admin functions

### File Storage
Configure storage in `config/filesystems.php`:
```php
// Local storage (development)
'default' => 'local',

// AWS S3 (production)
'default' => 's3',
```

### Queue Configuration
For background jobs (email, notifications):
```bash
php artisan queue:work
```

## 🚦 Health Check

Check API health:
```bash
curl http://localhost:8000/api/v1/health
```

## 📈 Performance

### Optimization Features
- Database indexing for search performance
- Image optimization and resizing
- Caching for frequent queries
- Queue system for background tasks
- API rate limiting

### Monitoring
- User activity tracking
- Match success analytics
- Subscription metrics
- Performance monitoring

## 🛡 Security

- Input validation and sanitization
- CSRF protection
- SQL injection prevention
- File upload security
- Rate limiting
- Password hashing (bcrypt)
- API authentication (Sanctum)

## 🌐 Internationalization

Supported languages:
- English (en)
- Sinhala (si)
- Tamil (ta)

Add translations in `resources/lang/` directory.

## 📱 Mobile API Ready

All endpoints are designed to work seamlessly with:
- Angular frontend
- React Native mobile app
- Third-party integrations

## 🤝 Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open Pull Request

## 📄 License

This project is proprietary software. All rights reserved.

## 🔗 Related Projects

- **Frontend**: SoulSync Angular Application
- **Mobile**: SoulSync React Native App
- **Admin Panel**: Vue.js Admin Dashboard

## 📞 Support

For technical support or inquiries:
- Email: dev@soulsync.com
- Documentation: [API Docs]
- Issue Tracker: [GitHub Issues]

---

Built with ❤️ for connecting hearts globally 💕
