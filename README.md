# 🚛 City Courier Web

[![Laravel](https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Firebase](https://img.shields.io/badge/Firebase-FFCA28?style=for-the-badge&logo=firebase&logoColor=black)](https://firebase.google.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=for-the-badge)](https://opensource.org/licenses/MIT)

**City Courier Web** is the powerful administrative backbone and API provider for the City Courier ecosystem. Built with Laravel 13, it provides a premium management experience for administrators and a robust, secure API for courier mobile applications.

---

## ✨ Key Features

### 🏢 Administrative Suite
- **📊 Real-time Dashboard**: Overview of orders, active couriers, and revenue.
- **👥 User & Role Management**: Granular control via Spatie Permissions (Admin, Manager, Staff).
- **🛵 Courier Management**: Verification workflows, status toggling, and performance tracking.
- **📦 Order Orchestration**: Complete lifecycle management from order placement to final delivery.

### 📱 Mobile API (Sanctum Protected)
- **🔐 Multi-Method Auth**: Login via Email, Google, Phone (Firebase OTP), or Registration.
- **📍 Order Tracking**: Endpoints for fetching available, active, and completed orders.
- **⚡ Status Synchronization**: Real-time status updates for the courier mobile app.
- **👤 Profile Management**: Secure profile updates and avatar management.

---

## 🛠️ Technology Stack

- **Framework**: [Laravel 13](https://laravel.com)
- **Authentication**: [Laravel Sanctum](https://laravel.com/docs/sanctum) & [Firebase Auth](https://firebase.google.com/)
- **Authorization**: [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission/v6/introduction)
- **Database**: MySQL / MariaDB
- **Tools**: Vite, Tailwind CSS (for Admin UI), Laravel Pint

---

## 🚀 Getting Started

### Prerequisites
- PHP 8.3+
- Composer
- Node.js & NPM
- MySQL

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/Andrew2509/citycourier-web.git
   cd citycourier-web
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment Setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *Note: Ensure you configure your `DB_*` and `FIREBASE_*` credentials in the `.env` file.*

4. **Database Migrations & Seeding**
   ```bash
   php artisan migrate --seed
   ```

5. **Start the Engines**
   ```bash
   php artisan serve
   # and in another terminal
   npm run dev
   ```

---

## 📚 API Documentation

Interactive API documentation (Redoc) is available at:

```
GET /api/documentation
```

The OpenAPI 3.0 spec lives in [`public/docs/openapi.yaml`](public/docs/openapi.yaml) and is served at `/docs/openapi.yaml`.

Validate the spec after editing it:

```bash
node scripts/validate-openapi.cjs
```

---

## 🔒 Security

This project implements best-of-breed security practices:
- **Sanctum API tokens** for mobile security.
- **Firebase integration** for verified phone/google authentication.
- **Middleware-level protection** for administrative routes.

---

## 📄 License

The City Courier Web is open-sourced software licensed under the [MIT license](LICENSE).

---

<p align="center">
  Developed with ❤️ by <a href="https://github.com/Andrew2509">Andrew2509</a>
</p>