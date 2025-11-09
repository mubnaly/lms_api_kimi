# E-Learning Platform - Laravel API Project Summary

## 🎯 Project Overview

A production-ready, multi-tenant e-learning platform built with Laravel 11, designed for Egyptian market with local payment gateways and free video hosting support.

---

## 🏗️ Architecture

### Tech Stack

-   **Backend**: Laravel 11 (PHP 8.3+)
-   **Database**: MySQL 8+ with multi-tenancy
-   **Cache**: Redis
-   **Queue**: Redis
-   **Authentication**: Laravel Sanctum
-   **Media**: Spatie Media Library
-   **Permissions**: Spatie Laravel Permission
-   **Payments**: Egyptian Gateways (Fawry, PayMob, PayTabs)
-   **Video**: Free platforms (YouTube, Vimeo, DailyMotion)

---

## 📁 Project Structure

```
lms-api/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── AuthController.php
│   │   │   ├── CourseController.php
│   │   │   ├── InstructorController.php
│   │   │   ├── EnrollmentController.php
│   │   │   ├── LessonProgressController.php
│   │   │   ├── ReviewController.php
│   │   │   ├── WishlistController.php
│   │   │   ├── AdminController.php
│   │   │   ├── PaymentCallbackController.php
│   │   │   └── CategoryController.php
│   │   ├── Requests/
│   │   ├── Resources/
│   │   └── Middleware/
│   ├── Models/
│   │   ├── User.php
│   │   ├── Course.php
│   │   ├── Enrollment.php
│   │   ├── LessonProgress.php
│   │   └── Tenant.php
│   ├── Services/
│   │   ├── EgyptianPaymentGatewayService.php
│   │   └── CourseService.php
│   └── Policies/
├── config/
├── database/
├── routes/
└── tests/
```

---

## 🔑 Key Features

### Multi-Tenancy

-   Multiple academies on single codebase
-   Isolated databases per tenant
-   Tenant-specific configurations
-   Central admin panel

### Egyptian Payment Integration

-   **Fawry**: Most popular in Egypt
-   **PayMob**: Modern API with iframe
-   **PayTabs**: Regional support
-   All gateways support EGP currency

### Free Video Hosting

-   YouTube integration
-   Vimeo support
-   DailyMotion compatibility
-   No storage costs for videos

### User Management

-   Student registration/login
-   Instructor application system
-   Admin approval workflows
-   Role-based permissions

### Course Management

-   Multi-section course structure
-   Lesson types (video, document, quiz)
-   Progress tracking
-   Completion certificates

### Reviews & Ratings

-   Student reviews after completion
-   Admin approval for reviews
-   Average rating calculation
-   Review moderation

---

## 🔧 Configuration

### Environment Variables

```env
# Application
APP_NAME="LMS API"
APP_ENV=production
APP_URL=https://api.yourdomain.com

# Database
DB_CONNECTION_CENTRAL=mysql_central
DB_CONNECTION_TENANT=mysql_tenant

# Payment Gateways
PAYMENT_GATEWAY=fawry
FAWRY_MERCHANT_ID=your_merchant_id
FAWRY_SECRET=your_secret
FAWRY_SANDBOX=true

# Multi-tenancy
CENTRAL_DOMAINS=localhost,127.0.0.1,yourdomain.com
```

### Payment Configuration

```php
// config/payment.php
return [
    'default_gateway' => env('PAYMENT_GATEWAY', 'fawry'),

    'gateways' => [
        'fawry' => [
            'enabled' => true,
            'merchant_id' => env('FAWRY_MERCHANT_ID'),
            'secret' => env('FAWRY_SECRET'),
            'base_url' => env('FAWRY_SANDBOX', true)
                ? 'https://atfawry.fawrystaging.com'
                : 'https://www.atfawry.com',
        ],
        // ... other gateways
    ],
];
```

---

## 🚀 API Endpoints

### Authentication

```
POST /api/v1/register
POST /api/v1/login
POST /api/v1/forgot-password
POST /api/v1/reset-password
POST /api/v1/logout
GET  /api/v1/profile
PUT  /api/v1/profile
```

### Courses

```
GET  /api/v1/courses
GET  /api/v1/courses/{slug}
GET  /api/v1/courses/{slug}/content
POST /api/v1/courses/{slug}/enroll
GET  /api/v1/courses/{slug}/reviews
POST /api/v1/courses/{slug}/reviews
POST /api/v1/courses/{slug}/wishlist
```

### Enrollments

```
GET  /api/v1/my-enrollments
GET  /api/v1/enrollments/{id}
PUT  /api/v1/courses/{slug}/lessons/{id}/progress
POST /api/v1/courses/{slug}/lessons/{id}/complete
```

### Instructor (Protected)

```
POST /api/v1/courses
PUT  /api/v1/courses/{slug}
DELETE /api/v1/courses/{slug}
POST /api/v1/courses/{slug}/sections
POST /api/v1/sections/{id}/lessons
GET  /api/v1/courses/{slug}/analytics
```

### Admin (Protected)

```
GET  /api/v1/admin/pending-instructors
POST /api/v1/admin/instructors/{id}/approve
GET  /api/v1/admin/pending-courses
POST /api/v1/admin/courses/{slug}/approve
GET  /api/v1/admin/analytics/overview
```

---

## 💳 Payment Flow

1. **Enrollment Request**

    ```
    POST /api/v1/courses/{slug}/enroll
    Body: {"coupon_code": "WELCOME50"}
    ```

2. **Payment Processing**

    - Free courses: Auto-completed
    - Paid courses: Gateway URL returned
    - User redirected to payment page

3. **Payment Verification**

    - Webhook receives callback
    - Payment verified via API
    - Enrollment marked as completed

4. **Return to App**
    - Deep link to Flutter app
    - Success/failure status
    - Enrollment details

---

## 📊 Caching Strategy

### Implemented Caching

```php
// Course search results
Cache::remember('courses_search_' . md5($filters), 3600, fn() => $query);

// User profiles
Cache::remember("user_profile_{$userId}", 300, fn() => $data);

// Payment tokens
Cache::remember('paymob_token', 3500, fn() => $token);

// Course analytics
Cache::remember("course_{$courseId}_analytics", 3600, fn() => $analytics);
```

---

## 🔒 Security Features

### Authentication

-   Laravel Sanctum tokens
-   Token expiration handling
-   Rate limiting on login

### Authorization

-   Policy-based permissions
-   Role-based access control
-   Resource-based policies

### Data Protection

-   Input validation
-   SQL injection prevention
-   XSS protection
-   CSRF tokens

---

## 🧪 Testing

### Test Structure

```
tests/
├── Feature/
│   ├── AuthTest.php
│   ├── CourseTest.php
│   ├── EnrollmentTest.php
│   └── PaymentTest.php
└── Unit/
    ├── Services/
    └── Models/
```

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=FullFlowTest

# Run with coverage
php artisan test --coverage
```

---

## 🚀 Deployment

### Server Requirements

-   PHP 8.3+
-   MySQL 8.0+
-   Redis 6.0+
-   Nginx/Apache
-   Supervisor (for queues)

### Deployment Steps

```bash
# 1. Install dependencies
composer install --no-dev --optimize-autoloader

# 2. Run migrations
php artisan migrate --force

# 3. Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Set permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# 5. Restart services
sudo systemctl restart php8.3-fpm nginx redis
```

---

## 📱 Flutter Integration

### Required Packages

```yaml
dependencies:
    dio: ^5.4.0
    flutter_secure_storage: ^9.0.0
    provider: ^6.1.1
    cached_network_image: ^3.3.0
    webview_flutter: ^4.4.0
    flutter_dotenv: ^5.1.0
```

### API Service Example

```dart
class ApiService {
  final Dio _dio = Dio();
  final FlutterSecureStorage _storage = FlutterSecureStorage();

  Future<Response> getCourses({Map<String, dynamic>? filters}) async {
    final token = await _storage.read(key: 'auth_token');

    return _dio.get(
      '/courses',
      queryParameters: filters,
      options: Options(headers: {
        'Authorization': 'Bearer $token',
        'X-Tenant-ID': FlavorConfig.instance!.tenantId,
      }),
    );
  }
}
```

---

## 🔧 Common Issues & Solutions

### 1. Undefined Method 'delete'

**Error**: `Undefined method 'delete'.intelephense(P1013)`

**Solution**: Use correct Sanctum method:

```php
// ❌ Wrong
$user->currentAccessToken()->delete();

// ✅ Correct
$user->currentAccessToken()->delete();
```

### 2. Payment Gateway Errors

**Solution**: Check gateway configuration:

```bash
php artisan config:clear
php artisan config:cache
```

### 3. Cache Issues

**Solution**: Clear all caches:

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## 📚 Documentation

### API Documentation

-   Postman collection: `/docs/postman-collection.json`
-   Swagger: Available at `/api/documentation`
-   OpenAPI 3.0 spec: `/docs/openapi.yaml`

### Code Documentation

-   PHPDoc comments throughout codebase
-   Type hints on all methods
-   Comprehensive PHPDoc blocks

---

## 🤝 Contributing

1. Fork the repository
2. Create feature branch: `git checkout -b feature-name`
3. Commit changes: `git commit -m 'Add feature'`
4. Push to branch: `git push origin feature-name`
5. Submit pull request

---

## 📄 License

This project is licensed under the MIT License.

---

## 🆘 Support

For support, email: support@lms.test or open an issue on GitHub.

---

**Built with ❤️ for Egyptian educational institutions**
