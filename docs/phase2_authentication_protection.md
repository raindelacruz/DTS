# Phase 2 authentication protection

Implemented October 10, 2026. Code and isolated database tests are complete; production enforcement and representative-user pilot remain pending. No Phase 2 migration was applied to the production database. Phase 0 evidence remains unchanged.

## Proposed enforcement policy

`AUTH_GUARD_MODE` defaults to `off`. Set `enforce` only after applying migration `20261010_0018_authentication_guard` and approving this policy in staging. Invalid modes reject authentication rather than silently disabling protection. The Phase 1 monitor remains independent.

- Password login: five failed checks per normalized identifier within a rolling-start 15-minute window produce a 15-minute pause. Paused requests do not extend the pause. Successful password checks clear password failure history, never MFA history.
- MFA, including enrollment: account-wide failures survive fresh password logins, browser changes, and new challenges. Delays are 1, 2, 4, and 8 seconds; the fifth failure produces a five-minute pause. A further failure after that pause can produce another five-minute pause until the 15-minute history expires. Paused requests never extend the delay.
- Each challenge expires five minutes after password acceptance and allows at most five evaluated submissions. Account/session-version mismatch or exhaustion requires a fresh password login. Existing pending challenges without the new state must restart after activation.
- Accepted TOTP steps cannot be accepted again for the same account and enrollment secret, including across sessions or failure-window rollover. The current step and adjacent steps retain existing clock tolerance. Codes must contain exactly six digits.
- Limits are account/identifier based. No shared-IP lockout is introduced. Unknown proxy topology therefore does not weaken subject limits.
- A database or locking failure rejects the affected authentication attempt. Existing fully authenticated sessions and trusted-device validation retain their established behavior.

All credential evaluations run while holding the relevant InnoDB subject row lock. There is no separate check/increment race between workers. Secret values and OTPs are not stored in the limiter: subject and enrollment identities use APP_KEY HMACs. Keep APP_KEY stable; changing it resets limiter identity and also affects existing application encryption.

## Operational alerts and recovery

At five failures the application emits a local warning with action, keyed subject, and retry duration. Requests rejected during the pause do not repeat that alert; a later new failure cycle may alert again. Database failures emit sanitized errors. External notification routing and a named response owner remain unconfigured. Collect these local signals through the organization's approved monitoring route.

Users receive a retry duration and instruction to contact their system administrator. Support must verify employee identity through the organization's established independent channel before any credential/MFA recovery. No unauthenticated bypass or automatic permanent lockout was added. This implementation does not invent an unapproved identity-verification procedure.

## Pilot, rollout, and rollback

Apply only the additive Phase 2 migration in an isolated staging database first; review unrelated pending migrations before using a general migration runner. Set `AUTH_GUARD_MODE=enforce` for staging. Confirm actual password-plus-MFA, enrollment, trusted device, shared-office-IP, five-minute expiry, session-version revocation, retry instructions, and outage handling with designated test accounts. Already authenticated users who want to enroll MFA should sign in again to obtain a fresh challenge in enforcement mode.

After the owner approves the proposed numbers, recovery procedure, alert ownership, and representative pilot results, apply this specific migration to production, then activate the environment setting through the approved deployment mechanism. A whole-server enforcement setting requires a designated pilot environment; this version does not provide per-account rollout lists. No Apache configuration, user account, password, trusted device, or production limiter state was changed for Phase 2.

Rollback: set `AUTH_GUARD_MODE=off`; retain the additive table and evidence. This restores the existing password throttle and MFA flow. It also reopens the previously identified MFA guessing risk, so rollback requires operational monitoring and prompt remediation. Never disable MFA or activate inactive accounts as rollback measures.

Rows retain replay history indefinitely; there is at most one MFA row per account and one password row per submitted normalized identifier. Random-identifier flooding can grow this table. Define bounded maintenance for obsolete password-subject rows and gateway resource controls before broad exposure; do not delete recent MFA replay state. Password reset and registration enforcement belong to later phases.

## Validation

The dedicated loopback MariaDB instance and `dts_security_test_phase2` schema exercise failure counting, retry rejection without evaluating credentials, account separation, TOTP reuse rejection, replay persistence at window rollover, eight concurrent password workers (exactly five evaluated failures and three limited requests), and database-table outage rejection. Unit checks cover account/version binding, challenge expiry/budget, fresh-session cleanup, TOTP time tolerance, and malformed codes. No real account login, MFA guessing, registration, or reset email was submitted. Browser-based representative user acceptance remains a release requirement.
