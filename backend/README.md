# Content Monetization Platform API

Laravel backend for a paid-article platform with three roles:

- `admin` manages moderation, commissions, withdrawals, and analytics
- `author` creates paid articles, earns credits, and requests withdrawals
- `reader` buys credits and unlocks articles

## What’s implemented

- Sanctum token auth with OTP-style email verification flow
- Multi-role users with wallet balances
- Wallet ledger and transaction history
- Paid articles with preview/full-content separation
- Article approval workflow for admins
- Commission splitting on unlocks
- Timed article unlock records
- Author withdrawal requests with admin approve/reject flow
- Razorpay order creation, client confirmation, and webhook verification hooks
- Admin dashboard summary endpoints
- Database notifications for admin alerts

## Local setup

1. Start MySQL in XAMPP so Laravel can connect on `127.0.0.1:3306`.

2. Create the database if needed:

```bash
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS new_articles_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

3. Install dependencies:

```bash
composer install
```

4. Run migrations and seed local accounts:

```bash
php artisan migrate:fresh --seed
```

5. Start the API:

```bash
php artisan serve
```

## Test suite

```bash
php artisan test
```

The current suite covers:

- registration, OTP verification, and login
- article unlock commission splitting
- withdrawal rejection and wallet restoration

## Seeded local accounts

- Admin: `admin@example.com` / `password`
- Author: `naina.sharma@example.com` / `password`
- Reader: `aarav.mehta@example.com` / `password`

## Key environment variables

- `DB_DATABASE=new_articles_platform`
- `CREDITS_PER_RUPEE=1`
- `MIN_PURCHASE_CREDITS=50`
- `MIN_WITHDRAWAL_CREDITS=100`
- `DEFAULT_ARTICLE_ACCESS_HOURS=24`
- `RAZORPAY_KEY_ID=`
- `RAZORPAY_KEY_SECRET=`
- `RAZORPAY_WEBHOOK_SECRET=`

## Main endpoints

- `POST /api/auth/register`
- `POST /api/auth/verify-otp`
- `POST /api/auth/login`
- `GET /api/articles`
- `GET /api/articles/{slug}`
- `POST /api/articles/{slug}/unlock`
- `GET /api/wallet`
- `POST /api/wallet/purchase-orders`
- `POST /api/payments/razorpay/confirm`
- `POST /api/payments/razorpay/webhook`
- `POST /api/withdrawals`
- `GET /api/admin/dashboard`
- `POST /api/admin/articles/{slug}/approve`
- `POST /api/admin/withdrawals/{id}/approve`

## Notes

- OTP codes are returned as `debug_code` in API responses while `APP_DEBUG=true`.
- `/` and `/up` return JSON so the backend stays usable in this Windows workspace without Blade view compilation.
- The Flutter client is not scaffolded in this backend folder yet; this API is ready for a mobile app to consume.
