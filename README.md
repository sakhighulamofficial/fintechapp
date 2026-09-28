# Shield Wallet — Secure Systems Design prototype

A Laravel demonstration wallet with simulated PKR. **Never use with real funds.**

## Requirements and run

PHP 8.3+, Composer, SQLite PDO. From this directory:

```sh
composer install
cp .env.example .env
php artisan key:generate
mkdir -p database && touch database/database.sqlite
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1
```

Open http://127.0.0.1:8000. Seeded accounts: `ali@example.test`, `sara@example.test`, `ayesha@example.test`, all with password `DemoPass!2026`. Change these credentials for any non-local use. Tests: `php artisan test`.

## Desktop demo

Run `php artisan serve --host=127.0.0.1` and, in another terminal, `cd desktop && npm install && npm start`. The Electron window loads the loopback Laravel app. The app and database must be started separately; this is a **desktop demonstration wrapper**, not a distributable standalone binary. Keep the Laravel server bound to loopback. Multiple machines require one shared server and database, HTTPS, and a separate deployment review.

## Security design

| Asset | Weakness and impact | Control and evidence |
|---|---|---|
| Credentials | Stolen passwords allow account takeover | Hashed passwords, server-side session, login throttling; inspect hash and rejected login |
| Wallet data | IDOR reveals another account | Balance and history always derived from authenticated user; modify `wallet_id` in Burp |
| Funds | Forged sender, negative amount, overdraft | Ignore client sender/balance, strict amount validation, conditional debit; tamper with POST |
| Ledger | Partial or duplicate payment | Atomic DB transaction, unique request ID, repeated-request lookup; retry and inspect balances |
| Accountability | Undetected actions | Audit login and transfer outcomes without secrets; query audit table |

### Before/after demonstration

For a safe baseline, create a throwaway teaching branch with one control temporarily removed, use an isolated test database, then return to the secured main branch and repeat the same request. Never enable a deliberately vulnerable route in the final running app. Capture: unauthenticated `/wallet` redirect; a forged `sender_id` transfer that still debits the authenticated sender; a changed `wallet_id` that never returns another user's history; duplicate request that moves money once; and overdraft that moves nothing.

### Design boundaries

No real funds, bank integration, public registration, MFA, password recovery, webhook, KYC, fraud engine, deployment hardening, or backup/restore verification. SQLite is suitable for a local classroom demo; a multi-user deployment should use a shared server/database and test concurrency and operational recovery separately. Audit rows are attributable but not tamper-proof against a database administrator. The idempotency token prevents repeated submitted requests but does not prove human intent.
