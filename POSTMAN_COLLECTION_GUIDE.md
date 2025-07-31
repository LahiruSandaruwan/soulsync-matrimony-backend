# SoulSync API - Postman Collection Guide

## 📋 Overview

This guide explains how to use the SoulSync API Postman collection for testing and development. The collection includes all API endpoints with pre-configured requests, authentication, and example data.

## 🚀 Quick Start

### 1. Import Collection
1. Open Postman
2. Click "Import" button
3. Select the `SoulSync_API.postman_collection.json` file
4. The collection will be imported with all endpoints organized by category

### 2. Set Up Environment
1. Create a new environment in Postman
2. Add the following variables:

| Variable | Value | Description |
|----------|-------|-------------|
| `base_url` | `http://localhost:8000/api/v1` | API base URL (development) |
| `token` | (leave empty) | Authentication token (auto-filled after login) |
| `user_id` | (leave empty) | Current user ID (auto-filled after login) |
| `conversation_id` | (leave empty) | Conversation ID for chat testing |
| `match_id` | (leave empty) | Match ID for testing |

### 3. Configure Collection Variables
The collection uses these variables automatically:
- `{{base_url}}` - API base URL
- `{{token}}` - Bearer token for authentication
- `{{user_id}}` - Current user ID
- `{{conversation_id}}` - Active conversation ID

## 📚 Collection Structure

### Authentication
- **Register** - Create new user account
- **Login** - Authenticate user and get token
- **Logout** - Logout current user
- **Get Current User** - Get authenticated user details
- **Forgot Password** - Request password reset
- **Reset Password** - Reset password with token

### User Profile
- **Get Profile** - Retrieve user profile
- **Update Profile** - Update profile information
- **Complete Profile** - Complete profile setup
- **Get Completion Status** - Check profile completion percentage

### Photo Management
- **Get Photos** - Get user photos
- **Upload Photo** - Upload new photo
- **Update Photo** - Update photo details
- **Delete Photo** - Delete photo
- **Set Profile Photo** - Set photo as profile picture
- **Toggle Photo Privacy** - Change photo privacy settings

### Matching & Discovery
- **Get Matches** - Get user matches
- **Get Daily Matches** - Get daily match suggestions
- **Get Match Suggestions** - Get AI-powered suggestions
- **Like User** - Like a user profile
- **Super Like User** - Super like a user (premium)
- **Dislike User** - Dislike a user
- **Block User** - Block a user
- **Get Who Liked Me** - See who liked your profile
- **Get Mutual Matches** - Get mutual matches

### Chat & Messaging
- **Get Conversations** - Get all conversations
- **Get Specific Conversation** - Get conversation details
- **Send Message** - Send message in conversation
- **Update Message** - Edit message
- **Delete Message** - Delete message
- **Mark Message as Read** - Mark message as read
- **Block Conversation** - Block conversation
- **Delete Conversation** - Delete conversation

### Notifications
- **Get Notifications** - Get user notifications
- **Get Unread Count** - Get unread notification count
- **Mark as Read** - Mark notification as read
- **Mark All as Read** - Mark all notifications as read
- **Delete Notification** - Delete notification

### Subscriptions & Payments
- **Get Plans** - Get subscription plans
- **Get Current Subscription** - Get user's current subscription
- **Subscribe** - Subscribe to a plan
- **Cancel Subscription** - Cancel subscription
- **Upgrade Subscription** - Upgrade to higher plan
- **Get History** - Get subscription history

### Search & Browse
- **Search Users** - Search for users
- **Advanced Search** - Advanced search with filters
- **Browse Users** - Browse all users
- **Browse Premium** - Browse premium users
- **Browse Verified** - Browse verified users

### Settings & Preferences
- **Get Settings** - Get user settings
- **Update Settings** - Update user settings
- **Get Preferences** - Get matching preferences
- **Update Preferences** - Update matching preferences

### Admin Panel
- **Dashboard** - Admin dashboard
- **Get Users** - Get all users (admin)
- **Update User Status** - Update user status
- **Get Reports** - Get user reports
- **Get Pending Photos** - Get photos pending approval

### Public Endpoints
- **Get Interests** - Get all interests
- **Get Countries** - Get countries list
- **Get States** - Get states for country
- **Get Cities** - Get cities for state
- **Health Check** - API health status

## 🔐 Authentication Flow

### 1. Register New User
```http
POST {{base_url}}/auth/register
Content-Type: application/json

{
  "email": "test@example.com",
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

### 2. Login
```http
POST {{base_url}}/auth/login
Content-Type: application/json

{
  "email": "test@example.com",
  "password": "password123"
}
```

### 3. Extract Token
After login, the response will include a token. Use Postman's "Tests" tab to automatically extract it:

```javascript
// Tests tab in Postman
if (pm.response.code === 200) {
    const response = pm.response.json();
    if (response.data && response.data.token) {
        pm.environment.set("token", response.data.token);
        pm.environment.set("user_id", response.data.user.id);
    }
}
```

### 4. Use Token
All subsequent requests will automatically include the token in the Authorization header.

## 🧪 Testing Workflows

### Complete User Journey Test

#### 1. User Registration & Profile Setup
1. **Register** - Create new account
2. **Login** - Authenticate
3. **Get Profile** - Check initial profile
4. **Update Profile** - Fill in profile details
5. **Upload Photo** - Add profile photo
6. **Get Completion Status** - Verify profile completion

#### 2. Matching & Discovery
1. **Get Matches** - View available matches
2. **Get Daily Matches** - Check daily suggestions
3. **Like User** - Like a profile
4. **Get Who Liked Me** - Check who liked you
5. **Get Mutual Matches** - View mutual matches

#### 3. Chat & Communication
1. **Get Conversations** - View conversations
2. **Send Message** - Send first message
3. **Get Specific Conversation** - View conversation details
4. **Mark Message as Read** - Mark as read

#### 4. Subscription & Premium Features
1. **Get Plans** - View subscription options
2. **Subscribe** - Subscribe to premium plan
3. **Get Current Subscription** - Verify subscription
4. **Get Who Liked Me** - Test premium feature

### Admin Testing Workflow

#### 1. Admin Authentication
1. **Login** with admin credentials
2. **Get Dashboard** - Access admin dashboard
3. **Get Users** - View all users

#### 2. User Management
1. **Get Specific User** - View user details
2. **Update User Status** - Change user status
3. **Get Reports** - View user reports

#### 3. Content Moderation
1. **Get Pending Photos** - View photos for approval
2. **Approve/Reject Photos** - Moderate content

## 📝 Request Examples

### Profile Update
```http
PUT {{base_url}}/profile
Authorization: Bearer {{token}}
Content-Type: application/json

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
  "about_me": "I am a software engineer passionate about technology and innovation.",
  "looking_for": "Looking for a life partner who shares similar values and goals.",
  "marital_status": "never_married",
  "have_children": false,
  "willing_to_relocate": true,
  "preferred_locations": ["Colombo", "Kandy"]
}
```

### Photo Upload
```http
POST {{base_url}}/profile/photos
Authorization: Bearer {{token}}
Content-Type: multipart/form-data

file: [Select image file]
is_primary: true
is_private: false
```

### Send Message
```http
POST {{base_url}}/chat/conversations/{{conversation_id}}/messages
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "content": "Hello! I really liked your profile. Would you like to chat?",
  "type": "text"
}
```

### Search Users
```http
POST {{base_url}}/search
Authorization: Bearer {{token}}
Content-Type: application/json

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

## 🔧 Environment Setup for Different Scenarios

### Development Environment
```json
{
  "base_url": "http://localhost:8000/api/v1",
  "token": "",
  "user_id": "",
  "conversation_id": "",
  "match_id": ""
}
```

### Staging Environment
```json
{
  "base_url": "https://staging-api.soulsync.com/api/v1",
  "token": "",
  "user_id": "",
  "conversation_id": "",
  "match_id": ""
}
```

### Production Environment
```json
{
  "base_url": "https://api.soulsync.com/api/v1",
  "token": "",
  "user_id": "",
  "conversation_id": "",
  "match_id": ""
}
```

## 🧪 Test Scripts

### Automatic Token Extraction
Add this to the "Tests" tab of login request:

```javascript
if (pm.response.code === 200) {
    const response = pm.response.json();
    if (response.data && response.data.token) {
        pm.environment.set("token", response.data.token);
        pm.environment.set("user_id", response.data.user.id);
        console.log("Token extracted and saved");
    }
}
```

### Response Validation
Add this to validate API responses:

```javascript
pm.test("Response is successful", function () {
    pm.response.to.have.status(200);
});

pm.test("Response has correct structure", function () {
    const response = pm.response.json();
    pm.expect(response).to.have.property('success');
    pm.expect(response).to.have.property('data');
    pm.expect(response.success).to.be.true;
});
```

### Error Handling Test
```javascript
pm.test("Error response structure", function () {
    if (pm.response.code >= 400) {
        const response = pm.response.json();
        pm.expect(response).to.have.property('success');
        pm.expect(response).to.have.property('message');
        pm.expect(response.success).to.be.false;
    }
});
```

## 📊 Data Validation

### Required Fields Check
```javascript
pm.test("Required fields present", function () {
    const response = pm.response.json();
    if (response.data) {
        pm.expect(response.data).to.have.property('id');
        pm.expect(response.data).to.have.property('created_at');
        pm.expect(response.data).to.have.property('updated_at');
    }
});
```

### Data Type Validation
```javascript
pm.test("Data types are correct", function () {
    const response = pm.response.json();
    if (response.data) {
        pm.expect(response.data.id).to.be.a('number');
        pm.expect(response.data.created_at).to.be.a('string');
        pm.expect(response.data.updated_at).to.be.a('string');
    }
});
```

## 🔄 Collection Runner

### Run Complete Test Suite
1. Open Collection Runner
2. Select the SoulSync API collection
3. Choose environment
4. Set iteration count
5. Run tests

### Test Specific Scenarios
Create folders in the collection for different test scenarios:
- **Authentication Tests**
- **Profile Management Tests**
- **Matching Tests**
- **Chat Tests**
- **Payment Tests**
- **Admin Tests**

## 📱 Frontend Integration Testing

### Angular Service Testing
Use the collection to test your Angular services:

```typescript
// Test authentication service
this.authService.login(credentials).subscribe(
  response => {
    // Verify response matches Postman collection
    expect(response.success).toBe(true);
    expect(response.data.token).toBeDefined();
  }
);
```

### Real-time Features Testing
Test WebSocket connections alongside API calls:
1. Make API call to send message
2. Verify WebSocket event is received
3. Check real-time updates

## 🚨 Common Issues & Solutions

### Authentication Issues
- **Token expired**: Re-run login request
- **Invalid token**: Check token format in Authorization header
- **Missing token**: Ensure login was successful

### File Upload Issues
- **File too large**: Check file size limits
- **Invalid format**: Ensure file is image type
- **Missing file**: Check multipart/form-data format

### Rate Limiting
- **429 errors**: Wait before retrying
- **Too many requests**: Reduce request frequency

## 📈 Performance Testing

### Load Testing
Use Postman's Collection Runner with multiple iterations to test:
- API response times
- Concurrent request handling
- Database performance
- File upload performance

### Stress Testing
- Send multiple requests simultaneously
- Test with large file uploads
- Monitor server resources

## 🔍 Debugging

### Enable Console Logging
```javascript
// Add to Tests tab
console.log("Request URL:", pm.request.url);
console.log("Request Headers:", pm.request.headers);
console.log("Response Body:", pm.response.text());
```

### Check Response Headers
```javascript
pm.test("Check response headers", function () {
    pm.expect(pm.response.headers.get("Content-Type")).to.include("application/json");
    pm.expect(pm.response.headers.get("Cache-Control")).to.exist;
});
```

## 📚 Additional Resources

### Documentation Links
- [API Documentation](./API_DOCUMENTATION_COMPLETE.md)
- [Contributing Guidelines](./CONTRIBUTING.md)
- [Backend README](./README.md)

### External Resources
- [Postman Learning Center](https://learning.postman.com/)
- [Laravel API Testing](https://laravel.com/docs/testing)
- [REST API Best Practices](https://restfulapi.net/)

---

This Postman collection provides comprehensive testing capabilities for the SoulSync API. Use it to validate endpoints, test workflows, and ensure your frontend integration works correctly. 