# Debug Report: Forgot Password

## 1. Executive Summary

The forgot-password feature is currently disabled in `app/controllers/Auth.php` by a local `$disabled = true` gate inside `Auth::forgotPassword()`. Even if the feature is re-enabled, the available evidence shows the most recent operational failure is not the reset-token logic itself, but SMTP authentication failure during password-reset email delivery.

The application uses a custom PHP MVC stack, a custom authentication controller, a custom `SecurityService` for password-reset tokens, and a custom socket-based SMTP sender. No third-party mailer package is configured in `composer.json`.

Most likely production impact when enabled:

- Users can submit a forgot-password request.
- The application can create a reset token for an active account.
- Email delivery fails during SMTP authentication.
- The user still receives the generic response saying reset instructions were sent, while no email is delivered.

## 2. Root Cause

Primary root cause:

The SMTP server rejects authentication for the configured SMTP account. The application log contains repeated SMTP `535-5.7.8 Username and Password not accepted` responses from the password-reset mail path.

Secondary historical issue:

Earlier log entries show `auth/forgotPassword` POST requests failing because the application could not establish a database connection. This appears separate from the later SMTP failures and should be treated as a historical or environmental availability issue unless it is still recurring.

Current feature state:

The feature is intentionally disabled in code:

```php
$disabled = true;
if (!$disabled && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
```

This prevents the reset-token and email path from running while disabled.

## 3. Affected Files

- `app/controllers/Auth.php`
  - `forgotPassword()`
  - `resetPassword()`
  - `sendPasswordResetInstructions()`
  - `sendSmtpMessage()`
  - `passwordResetHeaders()`

- `app/lib/SecurityService.php`
  - `createPasswordReset()`
  - `findValidPasswordReset()`
  - `consumePasswordReset()`
  - `audit()`

- `app/models/User.php`
  - `findByEmail()`
  - `updatePassword()`

- `app/config/config.php`
  - password reset sender and SMTP constants
  - application URL and environment constants

- `app/views/auth/forgot_password.php`
  - forgot-password request form/view

- `app/views/auth/reset_password.php`
  - reset-password form/view

- `storage/logs/app.log`
  - production evidence of database and SMTP failures

## 4. Functional Call Flow

Forgot-password request flow when enabled:

1. Browser submits POST to `/auth/forgotPassword`.
2. Router resolves request through `public/index.php` -> `app/init.php` -> `App` -> `Auth::forgotPassword()`.
3. `Auth::forgotPassword()` validates CSRF.
4. Submitted email is normalized and validated.
5. `User::findByEmail()` loads the user record.
6. If the user exists and has active status, `SecurityService::createPasswordReset()`:
   - invalidates previous unused reset tokens for the user;
   - creates a new random token;
   - stores only the token hash in `password_reset_tokens`;
   - sets expiration to 30 minutes.
7. `Auth::forgotPassword()` builds `/auth/resetPassword/{token}`.
8. `Auth::sendPasswordResetInstructions()` sends the reset URL:
   - if `SMTP_HOST` is configured, it calls custom `sendSmtpMessage()`;
   - otherwise, it falls back to PHP `mail()`.
9. `SecurityService::audit()` writes a `password_reset_requested` event with delivery metadata.
10. User sees a generic response regardless of whether the email address exists.

Reset-password completion flow:

1. Browser opens `/auth/resetPassword/{token}`.
2. `Auth::resetPassword()` calls `SecurityService::findValidPasswordReset()`.
3. If token hash exists, is unused, and is not expired, the reset form is shown.
4. On POST, CSRF and password policy are validated.
5. `User::updatePassword()` writes the new hashed password and increments session version.
6. `SecurityService::consumePasswordReset()` marks the token as used.
7. `SecurityService::audit()` writes `password_reset_completed`.
8. User is redirected to login.

## 5. Configuration Files

Relevant configuration file:

- `app/config/config.php`

Relevant constants, values intentionally not disclosed:

- `APP_ENV`
- `URLROOT`
- `SITENAME`
- `PASSWORD_RESET_FROM`
- `SMTP_HOST`
- `SMTP_PORT`
- `SMTP_USER`
- `SMTP_PASS`
- `SMTP_ENCRYPTION`
- `APP_KEY`
- `DB_HOST`
- `DB_USER`
- `DB_PASS`
- `DB_NAME`

Important configuration behavior:

- In production, several required environment variables are enforced.
- SMTP is enabled when `SMTP_HOST` is non-empty.
- If `SMTP_HOST` is empty, the application falls back to PHP `mail()`.
- Reset URLs are built using `URLROOT`, so this must match the production public base URL.

## 6. Database Tables Involved

Primary tables:

- `users`
  - stores email, status, password hash, session version, and account metadata.

- `password_reset_tokens`
  - stores reset token hashes, expiry timestamps, and used timestamps.
  - defined in `database/migrations/20260623_0002_p1_security_schema.php`.

- `security_audit_log`
  - records password reset requested/completed events and delivery metadata.
  - defined in `database/migrations/20260623_0002_p1_security_schema.php`.

Related security tables:

- `login_attempts`
  - not directly part of reset email delivery, but part of the authentication security schema.

- `mfa_trusted_devices`
  - not directly part of forgot-password delivery, but part of the broader authentication system.

## 7. Logs Reviewed

Reviewed:

- `storage/logs/app.log`

Relevant entries found:

- `2026-06-27 08:39:39`
  - URL: `auth/forgotPassword`
  - Method: POST
  - Error: database connection failed through `app/core/Database.php`.

- `2026-06-27 08:44:11`
  - URL: `auth/forgotPassword`
  - Method: POST
  - Error: database connection failed through `app/core/Database.php`.

- `2026-06-27 09:54:31` through `2026-06-27 15:01:18`
  - Action: `auth.forgotPassword.mail`
  - Error: SMTP command failed with `535-5.7.8 Username and Password not accepted`.
  - Source: `app/controllers/Auth.php`.

No SMTP usernames, passwords, reset tokens, or email addresses were disclosed in this report.

## 8. Evidence Supporting the Diagnosis

Code evidence:

- `Auth::forgotPassword()` contains a hard disable flag:
  - `app/controllers/Auth.php`, around line 638.

- Reset-token creation is implemented and reachable only when the disable flag allows POST handling:
  - `SecurityService::createPasswordReset()`
  - `app/lib/SecurityService.php`, around line 205.

- Email delivery uses SMTP when `SMTP_HOST` is configured:
  - `Auth::sendPasswordResetInstructions()`
  - `app/controllers/Auth.php`, around line 426.

- SMTP authentication is attempted when `SMTP_USER` is configured:
  - `Auth::sendSmtpMessage()`
  - `app/controllers/Auth.php`, around line 468.

- The SMTP sender uses `AUTH LOGIN` and sends the configured username and password to the SMTP server.

Log evidence:

- Repeated application log entries show the SMTP server responding with `535-5.7.8 Username and Password not accepted`.
- The failures are logged under `auth.forgotPassword.mail`, confirming they occur during password-reset email delivery.

Conclusion:

The forgot-password feature was disabled as a mitigation, but the underlying operational blocker is SMTP authentication failure. Database availability should also be verified because earlier failures show `auth/forgotPassword` could not connect to the database at least twice.

## 9. Recommended Code Change

Recommended approach:

Do not hard-code the temporary disable flag inside the controller. Replace it with an explicit configuration flag, and improve delivery failure handling so production support can distinguish "token created but email rejected" from "request accepted and mail sent".

Recommended changes:

1. Add a configuration constant:

```php
define('PASSWORD_RESET_ENABLED', filter_var(getenv('PASSWORD_RESET_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN));
```

2. Replace the local controller flag:

```php
$disabled = true;
if (!$disabled && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
```

with:

```php
if (PASSWORD_RESET_ENABLED && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
```

3. Keep the generic user-facing response to avoid account enumeration.

4. Log mail delivery failures with sanitized context only:

```php
reportException($e, [
    'action' => 'auth.forgotPassword.mail',
    'user_id' => $user->id,
    'email_hash' => hash('sha256', $email),
    'transport' => SMTP_HOST !== '' ? 'smtp' : 'mail'
]);
```

5. Consider returning `false` rather than throwing for expected SMTP rejection cases, while still logging them. This avoids treating provider authentication rejection as an unhandled application exception.

6. Verify SMTP configuration outside the application before enabling the feature:

- SMTP host
- port
- encryption mode
- username
- password or app password
- provider security policy
- sender address permitted by the SMTP account

7. Only re-enable password reset in production after one successful end-to-end test with a controlled test account.

## 10. Regression Test Checklist

Configuration checks:

- `APP_ENV` is set correctly for production.
- `URLROOT` points to the real production HTTPS URL.
- `APP_KEY` is stable and not the development default.
- `PASSWORD_RESET_FROM` is a sender accepted by the SMTP provider.
- `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, and `SMTP_ENCRYPTION` are valid for the provider.
- `PASSWORD_RESET_ENABLED` is explicitly controlled by environment.

Database checks:

- `users` table contains active users with valid email addresses.
- `password_reset_tokens` table exists.
- `security_audit_log` table exists.
- The database user can insert/update/select `password_reset_tokens`.
- The database user can insert `security_audit_log`.

Functional tests:

- GET `/auth/forgotPassword` renders without error.
- POST `/auth/forgotPassword` with unknown email returns the generic response.
- POST `/auth/forgotPassword` with inactive user returns the same generic response.
- POST `/auth/forgotPassword` with active user creates one valid reset token.
- A second forgot-password request invalidates prior unused tokens for that user.
- Reset email is delivered to the active user's mailbox.
- Reset link opens `/auth/resetPassword/{token}`.
- Expired token is rejected.
- Used token is rejected.
- Invalid token is rejected.
- Valid token accepts a password matching policy.
- Password reset consumes token.
- Password reset increments session version.
- User can log in with the new password.
- User cannot log in with the old password.

Security checks:

- Response text does not reveal whether an email exists.
- Logs do not contain reset tokens.
- Logs do not contain SMTP credentials.
- Logs do not contain raw email addresses if policy requires hashing.
- CSRF protection is enforced on forgot-password and reset-password POST requests.
- Reset tokens expire after 30 minutes.

Production rollout checks:

- Keep feature disabled until SMTP authentication succeeds in a controlled test.
- Enable via environment/configuration only.
- Monitor `storage/logs/app.log` during first production test.
- Confirm a `password_reset_requested` audit event is written.
- Confirm a `password_reset_completed` audit event is written after successful reset.
