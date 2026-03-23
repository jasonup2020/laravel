# Laravel User CRUD API with JWT Authentication

API ini menyediakan fitur CRUD untuk User dan relasi dengan Hobi, serta login & register menggunakan JWT authentication.

---

## Fitur

- Register & Login (JWT)
- CRUD User
- Dokumentasi API otomatis menggunakan Scribe
- Proteksi endpoint dengan JWT

---


## Instalasi

Clone repositori:

```bash
git clone git@github.com:erwin-perdana/laravel12-with-jwt.git
cd laravel12-with-jwt
```

Install dependency:

```bash
composer install
npm install && npm run build
```

Salin file .env dan konfigurasi:

```bash
cp .env.example .env
```

Generate key dan JWT secret:

```bash
php artisan key:generate
php artisan jwt:secret
```

## Konfigurasi Database

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_with_jwt
DB_USERNAME=root
DB_PASSWORD=your_password

Migrate database:

```bash
php artisan migrate
```

## Dokumentasi API (Scribe)

Generate Scribe:

```bash
php artisan scribe:generate
```

## Run Project
```bash
composer run dev
```
