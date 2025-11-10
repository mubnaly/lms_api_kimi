<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p># 🎓 E-Learning Platform API - Professional Edition

[![Laravel](https://img.shields.io/badge/Laravel-11.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

A production-ready, multi-tenant e-learning platform API built with Laravel 11, optimized for the Egyptian market with local payment gateways and free video hosting support.

---

## 📋 Table of Contents

-   [Features](#-features)
-   [Tech Stack](#-tech-stack)
-   [Installation](#-installation)
-   [Configuration](#-configuration)
-   [API Documentation](#-api-documentation)
-   [Testing](#-testing)
-   [Deployment](#-deployment)
-   [Contributing](#-contributing)

---

## ✨ Features

### Core Features

-   ✅ **Multi-Tenancy**: Isolated databases per academy
-   ✅ **Role-Based Access**: Student, Instructor, Admin roles
-   ✅ **Course Management**: Complete CRUD with sections & lessons
-   ✅ **Progress Tracking**: Real-time lesson progress
-   ✅ **Certificates**: Auto-generated PDF certificates
-   ✅ **Reviews & Ratings**: Course review system
-   ✅ **Wishlist**: Save courses for later
-   ✅ **Coupons**: Flexible discount system

### Payment Integration

-   💳 **Fawry**: Most popular in Egypt
-   💳 **PayMob**: Modern API with iframe
-   💳 **PayTabs**: Regional support
-   💳 All gateways support EGP currency

### Video Hosting

-   🎥 **YouTube**: Free hosting with embed support
-   🎥 **Vimeo**: Professional video platform
-   🎥 **DailyMotion**: Alternative free hosting

---

## 🛠️ Tech Stack

| Component          | Technology                |
| ------------------ | ------------------------- |
| **Backend**        | Laravel 11 (PHP 8.3+)     |
| **Database**       | MySQL 8.0+                |
| **Cache**          | Redis 6.0+                |
| **Queue**          | Redis with Horizon        |
| **Authentication** | Laravel Sanctum           |
| **Media**          | Spatie Media Library      |
| **Permissions**    | Spatie Laravel Permission |
| **Multi-Tenancy**  | Stancl Tenancy            |
| **PDF Generation** | DomPDF                    |

---

## 📦 Installation

### Prerequisites

-   PHP 8.3 or higher
-   MySQL 8.0 or higher
-   Redis 6.0 or higher
-   Composer 2.x
-   Node.js 18+ (for assets)

### Quick Start

```bash
# Clone repository
git clone https://github.com/yourusername/lms-api.git
cd lms-api

# Install dependencies
composer install
npm install && npm run build

# Environment setup
cp .env.example .env
php artisan key:generate

# Database setup
php artisan migrate --seed

# Storage link
php artisan storage:link

# Cache optimization
php artisan optimize

# Start development server
php artisan serve
```

### Default Credentials

| Role           | Email               | Password |
| -------------- | ------------------- | -------- |
| **Admin**      | admin@lms.test      | password |
| **Instructor** | instructor@lms.test | password |
| **Student**    | student@lms.test    | password |

---

## ⚙️ Configuration

### Environment Variables

```env
# Application
APP_NAME="LMS API"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lms_db
DB_USERNAME=root
DB_PASSWORD=

# Cache & Queue
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
QUEUE_CONNECTION=redis

# Payment Gateway
PAYMENT_GATEWAY=fawry
FAWRY_MERCHANT_ID=your_merchant_id
FAWRY_SECRET=your_secret_key
FAWRY_SANDBOX=true

PAYMOB_API_KEY=your_api_key
PAYMOB_INTEGRATION_ID=your_integration_id
PAYMOB_HMAC_SECRET=your_hmac_secret

PAYTABS_PROFILE_ID=your_profile_id
PAYTABS_SERVER_KEY=your_server_key
```

### Payment Configuration

Edit `config/payment.php`:

```php
return [
    'default_gateway' => env('PAYMENT_GATEWAY', 'fawry'),
    'currency' => env('PAYMENT_CURRENCY', 'EGP'),

    'gateways' => [
        'fawry' => [
            'enabled' => env('FAWRY_ENABLED', true),
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

## 📚 API Documentation

### Base URL

```
http://localhost:8000/api/v1
```

### Authentication

All authenticated endpoints require a Bearer token:

```http
Authorization: Bearer {your_token}
```

### Available Endpoints

#### Authentication

```http
POST   /api/v1/register
POST   /api/v1/login
POST   /api/v1/logout
GET    /api/v1/profile
PUT    /api/v1/profile
```

#### Courses

```http
GET    /api/v1/courses
GET    /api/v1/courses/{slug}
POST   /api/v1/courses (Instructor)
PUT    /api/v1/courses/{slug} (Instructor)
DELETE /api/v1/courses/{slug} (Instructor)
```

#### Enrollments

```http
POST   /api/v1/courses/{slug}/enroll
GET    /api/v1/my-enrollments
PUT    /api/v1/courses/{slug}/lessons/{id}/progress
```

#### Certificates (NEW)

```http
GET    /api/v1/certificates
GET    /api/v1/certificates/{enrollment}
GET    /api/v1/certificates/{enrollment}/download
POST   /api/v1/certificates/verify
```

#### Analytics (NEW)

```http
GET    /api/v1/analytics/student/dashboard
GET    /api/v1/analytics/instructor/dashboard
```

#### Search (NEW)

```http
GET    /api/v1/search?q={query}
POST   /api/v1/search/filter
```

### Example Requests

#### Login

```bash
curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "student@lms.test",
    "password": "password"
  }'
```

#### Get Courses

```bash
curl -X GET http://localhost:8000/api/v1/courses \
  -H "Accept: application/json"
```

#### Enroll in Course

```bash
curl -X POST http://localhost:8000/api/v1/courses/complete-laravel-11-mastery/enroll \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "coupon_code": "WELCOME50"
  }'
```

---

## 🧪 Testing

### Run All Tests

```bash
php artisan test
```

### Run Specific Test Suite

```bash
php artisan test --filter=CourseTest
```

### Run with Coverage

```bash
php artisan test --coverage
```

### Example Test

```php
public function test_student_can_enroll_in_course()
{
    $user = User::factory()->student()->create();
    $this->actingAs($user);

    $course = Course::factory()->published()->create(['price' => 0]);

    $response = $this->postJson("/api/v1/courses/{$course->slug}/enroll");

    $response->assertStatus(201)
        ->assertJsonPath('data.enrollment.payment_status', 'completed');
}
```

---

## 🚀 Deployment

### Server Requirements

-   Ubuntu 20.04+ or similar
-   PHP 8.3+ with required extensions
-   MySQL 8.0+
-   Redis 6.0+
-   Nginx or Apache
-   Supervisor (for queues)

### Deployment Steps

```bash
# 1. Clone repository
git clone https://github.com/yourusername/lms-api.git
cd lms-api

# 2. Install dependencies
composer install --no-dev --optimize-autoloader

# 3. Configure environment
cp .env.production .env
php artisan key:generate

# 4. Run migrations
php artisan migrate --force

# 5. Cache optimization
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Set permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# 7. Start queue workers
php artisan queue:restart

# 8. Restart services
sudo systemctl restart php8.3-fpm nginx redis
```

### Supervisor Configuration

```ini
[program:lms-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/lms-api/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/lms-api/storage/logs/worker.log
```

---

## 🔒 Security Features

### Implemented

-   ✅ Laravel Sanctum Authentication
-   ✅ Rate Limiting on all endpoints
-   ✅ CSRF Protection
-   ✅ SQL Injection Prevention
-   ✅ XSS Protection
-   ✅ Input Validation
-   ✅ Password Hashing (bcrypt)
-   ✅ Secure Token Storage

### Best Practices

```php
// Always validate input
$validated = $request->validate([
    'email' => 'required|email',
    'password' => 'required|min:8',
]);

// Use policy-based authorization
Gate::authorize('update', $course);

// Hash sensitive data
$user->password = Hash::make($password);
```

---

## 📊 Performance Optimization

### Caching Strategy

```php
// Course search results
Cache::remember('courses_search_' . md5($filters), 3600, fn() => $query);

// User profiles
Cache::remember("user_profile_{$userId}", 300, fn() => $data);

// Analytics
Cache::remember("course_{$courseId}_analytics", 3600, fn() => $analytics);
```

### Database Optimization

-   Indexed frequently queried columns
-   Eager loading relationships
-   Query optimization
-   Database connection pooling

---

## 📱 Flutter Integration

### Required Packages

```yaml
dependencies:
    dio: ^5.4.0
    flutter_secure_storage: ^9.0.0
    provider: ^6.1.1
    cached_network_image: ^3.3.0
```

### API Service Example

```dart
class ApiService {
  final Dio _dio = Dio(BaseOptions(
    baseUrl: 'http://localhost:8000/api/v1',
    headers: {'Accept': 'application/json'},
  ));

  Future<Response> login(String email, String password) async {
    return _dio.post('/login', data: {
      'email': email,
      'password': password,
    });
  }

  Future<Response> getCourses({Map<String, dynamic>? filters}) async {
    return _dio.get('/courses', queryParameters: filters);
  }
}
```

---

## 🤝 Contributing

We welcome contributions! Please follow these steps:

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add amazing feature'`
4. Push to branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

### Code Style

-   Follow PSR-12 coding standards
-   Use PHP-CS-Fixer for formatting
-   Write comprehensive PHPDoc comments
-   Include unit tests for new features

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 🆘 Support

-   **Email**: support@lms.test
-   **Documentation**: [https://docs.lms.test](https://docs.lms.test)
-   **Issues**: [GitHub Issues](https://github.com/yourusername/lms-api/issues)

---

## 🙏 Acknowledgments

-   Laravel Framework
-   Spatie Packages
-   Stancl Tenancy
-   Egyptian Payment Gateways

---

**Built with ❤️ for Egyptian educational institutions**

© 2025 LMS API. All rights reserved.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

-   [Simple, fast routing engine](https://laravel.com/docs/routing).
-   [Powerful dependency injection container](https://laravel.com/docs/container).
-   Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
-   Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
-   Database agnostic [schema migrations](https://laravel.com/docs/migrations).
-   [Robust background job processing](https://laravel.com/docs/queues).
-   [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

-   **[Vehikl](https://vehikl.com/)**
-   **[Tighten Co.](https://tighten.co)**
-   **[WebReinvent](https://webreinvent.com/)**
-   **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
-   **[64 Robots](https://64robots.com)**
-   **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
-   **[Cyber-Duck](https://cyber-duck.co.uk)**
-   **[DevSquad](https://devsquad.com/hire-laravel-developers)**
-   **[Jump24](https://jump24.co.uk)**
-   **[Redberry](https://redberry.international/laravel/)**
-   **[Active Logic](https://activelogic.com)**
-   **[byte5](https://byte5.de)**
-   **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
