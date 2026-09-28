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

## New account and risk workflow

Customers can sign up with a zero balance, look up a registered email and confirm a beneficiary, remove their own beneficiaries, hide the on-screen balance, view a paginated statement, and open owner-scoped receipts. Seeded users are not automatically beneficiaries. There is no customer top-up flow; seed balances are demonstration funds.

Transfers over PKR 50,000, to a beneficiary less than 24 hours old, or from a device without an established trust signal are recorded as **pending** and move no funds. Pending request IDs cannot later be replayed into a completed transfer. **FIDO2 registration/assertion verification, device enrollment, and a separate risk reviewer are not implemented.** There is deliberately no endpoint that approves these requests. The current application does not establish a trusted device in normal operation, so all normal UI transfers are held. This is a fail-closed partial implementation, not a completed banking workflow. The `trusted_device` session flag used by unit tests is a test fixture, not a user-facing trust mechanism. Do not turn it on without verified device enrollment and revocation.

Lecture 2 defense layers: identity (password hashing, throttling, sessions; FIDO2 outstanding); application/API (CSRF, ownership, validation, idempotency); data (integer paisa, atomic debit/credit, scoped reads); infrastructure (loopback demo; HTTPS and secret management required for remote use); monitoring (audit events and pending risk records; external alerting outstanding); recovery (database rollback on failed transfers; backups and restore drills outstanding). A session cookie is preferable to JWT in this server-rendered app: JWT does not itself add security and would require safe token storage, expiry, revocation, and scope enforcement.

For Burp, test ownership by changing beneficiary and receipt IDs, tamper with `sender_id` and amount, replay the same request ID, and verify the pending risk row and unchanged balances. For packet capture, use a separate HTTPS deployment; the loopback HTTP demo is **not encrypted**. A desktop wrapper does not supply transport encryption.
