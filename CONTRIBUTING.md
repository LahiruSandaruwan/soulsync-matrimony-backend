# Contributing to SoulSync Matrimony Backend

Thank you for your interest in contributing to the SoulSync Matrimony Backend! This document provides guidelines and information for contributors.

## 🚀 Quick Start

### Prerequisites
- PHP >= 8.1
- Composer
- MySQL 8.0+ or SQLite
- Git
- Basic knowledge of Laravel framework

### Development Setup
1. Fork the repository
2. Clone your fork: `git clone https://github.com/your-username/soulsync-matrimony-backend.git`
3. Install dependencies: `composer install`
4. Copy environment file: `cp .env.example .env`
5. Generate application key: `php artisan key:generate`
6. Configure database in `.env`
7. Run migrations: `php artisan migrate`
8. Seed database: `php artisan db:seed`
9. Start development server: `php artisan serve`

## 📋 Development Guidelines

### Code Style & Standards

#### PHP/Laravel Standards
- Follow **PSR-12** coding standards
- Use **Laravel conventions** for naming and structure
- Follow **SOLID principles**
- Write **self-documenting code** with clear variable and function names

#### Naming Conventions
```php
// Controllers: PascalCase, singular
class UserController extends Controller

// Models: PascalCase, singular
class User extends Model

// Methods: camelCase
public function getUserProfile()

// Variables: camelCase
$userProfile = $user->profile;

// Constants: UPPER_SNAKE_CASE
const MAX_FILE_SIZE = 5242880;

// Database tables: snake_case, plural
users, user_profiles, user_photos

// Database columns: snake_case
first_name, date_of_birth, profile_photo
```

#### File Structure
```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── AuthController.php
│   │   │   ├── ProfileController.php
│   │   │   └── Admin/
│   │   │       └── UserController.php
│   │   ├── Requests/
│   │   └── Middleware/
│   └── Resources/
├── Models/
├── Services/
├── Events/
├── Listeners/
├── Jobs/
└── Notifications/
```

### API Development Standards

#### Response Format
All API responses should follow this format:
```php
// Success Response
return response()->json([
    'success' => true,
    'data' => $data,
    'message' => 'Success message'
], 200);

// Error Response
return response()->json([
    'success' => false,
    'message' => 'Error message',
    'errors' => $errors, // for validation errors
    'error_code' => 'ERROR_CODE'
], 400);
```

#### Controller Structure
```php
class ProfileController extends Controller
{
    public function show(Request $request)
    {
        try {
            $profile = $request->user()->profile;
            
            return response()->json([
                'success' => true,
                'data' => $profile,
                'message' => 'Profile retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile',
                'error_code' => 'PROFILE_RETRIEVAL_ERROR'
            ], 500);
        }
    }
}
```

#### Request Validation
```php
// Create dedicated Request classes for complex validation
class UpdateProfileRequest extends FormRequest
{
    public function rules()
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date|before:today',
            'gender' => 'required|in:male,female,other',
            'height_cm' => 'nullable|integer|min:100|max:250',
            'weight_kg' => 'nullable|integer|min:30|max:200',
        ];
    }

    public function messages()
    {
        return [
            'date_of_birth.before' => 'Date of birth must be in the past.',
            'height_cm.min' => 'Height must be at least 100 cm.',
        ];
    }
}
```

### Database Standards

#### Migration Guidelines
```php
// Use descriptive migration names
2025_01_20_000001_create_users_table.php
2025_01_20_000002_add_matrimonial_fields_to_users_table.php

// Migration structure
public function up()
{
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('email')->unique();
        $table->string('first_name');
        $table->string('last_name');
        $table->enum('gender', ['male', 'female', 'other']);
        $table->date('date_of_birth');
        $table->timestamps();
        
        // Add indexes for frequently queried columns
        $table->index(['gender', 'date_of_birth']);
    });
}
```

#### Model Relationships
```php
class User extends Authenticatable
{
    // Define relationships clearly
    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function photos()
    {
        return $this->hasMany(UserPhoto::class);
    }

    public function matches()
    {
        return $this->hasMany(UserMatch::class, 'user_one_id')
                   ->orWhere('user_two_id', $this->id);
    }
}
```

### Testing Standards

#### Test Structure
```php
class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_get_profile()
    {
        // Arrange
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->get('/api/v1/profile');

        // Assert
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'user_id',
                        'first_name',
                        'last_name'
                    ]
                ]);
    }
}
```

#### Test Naming
- Use descriptive test method names
- Follow the pattern: `test_[what]_[when]_[expected_result]`
- Example: `test_user_cannot_access_others_profile()`

#### Test Coverage Requirements
- **Controllers**: 100% coverage
- **Services**: 100% coverage
- **Models**: 90% coverage
- **Middleware**: 100% coverage

### Security Standards

#### Input Validation
- Always validate and sanitize user input
- Use Laravel's built-in validation
- Implement custom validation rules when needed
- Sanitize output to prevent XSS

#### Authentication & Authorization
```php
// Use middleware for protection
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/admin/users', [AdminUserController::class, 'index']);
});

// Check permissions in controllers
public function updateUser(Request $request, User $user)
{
    if (!$request->user()->can('update', $user)) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized',
            'error_code' => 'UNAUTHORIZED'
        ], 403);
    }
}
```

#### File Upload Security
```php
// Validate file uploads
$request->validate([
    'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120'
]);

// Store files securely
$path = $request->file('photo')->store('photos', 'private');
```

## 🔄 Git Workflow

### Branch Naming
- **Feature**: `feature/user-profile-management`
- **Bug Fix**: `fix/photo-upload-error`
- **Hotfix**: `hotfix/security-vulnerability`
- **Refactor**: `refactor/auth-controller`

### Commit Messages
Follow **Conventional Commits** format:
```
type(scope): description

[optional body]

[optional footer]
```

#### Types
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

#### Examples
```
feat(auth): add two-factor authentication

- Add 2FA setup endpoint
- Add 2FA verification endpoint
- Add recovery codes generation

Closes #123
```

```
fix(profile): resolve photo upload validation error

- Fix file size validation
- Add proper error messages
- Update tests

Fixes #456
```

### Pull Request Process

1. **Create Feature Branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make Changes**
   - Write code following standards
   - Add tests for new functionality
   - Update documentation if needed

3. **Test Your Changes**
   ```bash
   php artisan test
   php artisan test --coverage
   ```

4. **Commit Changes**
   ```bash
   git add .
   git commit -m "feat(scope): description"
   ```

5. **Push to Your Fork**
   ```bash
   git push origin feature/your-feature-name
   ```

6. **Create Pull Request**
   - Use the PR template
   - Describe changes clearly
   - Link related issues
   - Request reviews from maintainers

### PR Template
```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Unit tests added/updated
- [ ] Integration tests added/updated
- [ ] Manual testing completed

## Checklist
- [ ] Code follows style guidelines
- [ ] Self-review completed
- [ ] Documentation updated
- [ ] Tests added/updated
- [ ] No breaking changes

## Related Issues
Closes #123
```

## 🧪 Testing Guidelines

### Running Tests
```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test --filter ProfileApiTest

# Run with coverage
php artisan test --coverage

# Run tests in parallel
php artisan test --parallel
```

### Test Data
Use factories for consistent test data:
```php
// Create test user
$user = User::factory()->create();

// Create user with profile
$user = User::factory()
    ->has(UserProfile::factory())
    ->create();

// Create user with specific attributes
$user = User::factory()->create([
    'email' => 'test@example.com',
    'gender' => 'female'
]);
```

### API Testing
```php
public function test_user_can_login()
{
    $user = User::factory()->create([
        'password' => Hash::make('password123')
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password123'
    ]);

    $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user',
                    'token'
                ]
            ]);
}
```

## 📚 Documentation Standards

### Code Documentation
```php
/**
 * Get user profile with completion status
 *
 * @param Request $request
 * @return JsonResponse
 * @throws \Exception
 */
public function show(Request $request): JsonResponse
{
    // Implementation
}
```

### API Documentation
- Document all endpoints in API documentation
- Include request/response examples
- Specify authentication requirements
- Document error responses

### README Updates
- Update README.md for new features
- Add setup instructions for new dependencies
- Update API documentation links

## 🔧 Development Tools

### Recommended IDE Setup
- **PHPStorm** or **VS Code** with PHP extensions
- **Laravel IDE Helper** for better autocomplete
- **PHP CS Fixer** for code formatting
- **PHPStan** for static analysis

### Code Quality Tools
```bash
# Install tools
composer require --dev friendsofphp/php-cs-fixer
composer require --dev phpstan/phpstan

# Format code
./vendor/bin/php-cs-fixer fix

# Static analysis
./vendor/bin/phpstan analyse
```

### Pre-commit Hooks
Set up pre-commit hooks to:
- Run PHP CS Fixer
- Run PHPStan
- Run tests
- Check commit message format

## 🚨 Common Issues & Solutions

### Database Issues
```bash
# Reset database
php artisan migrate:fresh --seed

# Clear cache
php artisan cache:clear
php artisan config:clear
```

### Permission Issues
```bash
# Fix storage permissions
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
```

### Composer Issues
```bash
# Clear composer cache
composer clear-cache

# Reinstall dependencies
rm -rf vendor/
composer install
```

## 📞 Getting Help

### Communication Channels
- **GitHub Issues**: For bug reports and feature requests
- **GitHub Discussions**: For questions and general discussion
- **Email**: dev@soulsync.com for urgent issues

### Before Asking for Help
1. Check existing issues and discussions
2. Search documentation
3. Try to reproduce the issue
4. Provide detailed information:
   - Error messages
   - Steps to reproduce
   - Environment details
   - Code examples

## 🎯 Contribution Areas

### High Priority
- Bug fixes
- Security improvements
- Performance optimizations
- Test coverage improvements

### Medium Priority
- New features
- API enhancements
- Documentation improvements
- Code refactoring

### Low Priority
- UI/UX improvements (frontend)
- Minor optimizations
- Additional test cases

## 📄 License

By contributing to this project, you agree that your contributions will be licensed under the same license as the project.

## 🙏 Recognition

Contributors will be recognized in:
- Project README
- Release notes
- Contributor hall of fame

Thank you for contributing to SoulSync Matrimony Backend! 🚀 