# Phase 3 password-reset protection

Implemented October 10, 2026. Code and isolated tests are complete. Production migration, enforcement, worker scheduling, and representative-user pilot remain pending. Phase 2 production enforcement is still off. Phase 0 preserved evidence was not modified.

## Proposed policy and behavior

`PASSWORD_RESET_GUARD_MODE` defaults to `off`. `enforce` activates the protected request and completion paths. Invalid values fail rather than silently disabling the control. This setting is independent of `PASSWORD_RESET_ENABLED` and `AUTH_GUARD_MODE`.

Proposed enforcement settings are a **60-second resend cooldown**, **three admitted requests per email address per rolling hour**, and **1,000 admitted requests globally per rolling hour**. These numbers require operational approval and legitimate-volume review before production activation. They are defined in `PasswordResetProtection`, not derived from the incident's attack volume.

Valid email addresses follow the same admission path whether their accounts are active, inactive, or nonexistent. Addresses are trimmed and normalized to lowercase. Limits persist in the shared database across browsers and workers. A denied request does not extend the cooldown, issue a token, invalidate an existing token, enqueue mail, change a password, or lock normal sign-in. Phase 1 supplies source-volume observations; this phase imposes no IP-wide restriction because the proxy identity is still unresolved and employees share an address.

All request outcomes, including limiter failure, return the same generic body and HTTP status following valid CSRF validation. The message explains delivery delay and a one-minute resend wait without revealing account existence. SMTP runs separately, removing the large mail-dependent response timing difference. Exact constant-time behavior is not claimed: database work and contention can still differ. Quantitative timing and legitimate-user acceptance should be checked in staging.

## Token lifecycle and concurrent completion

Tokens remain 32 random bytes, stored as SHA-256 hashes, expiring after 30 minutes. Only an admitted request for an active account replaces earlier unused tokens. Rejected requests preserve those tokens. Issuance, admission accounting, and enqueueing commit together; encryption/database failure rolls them all back.

Completion locks the active user and then revalidates/locks the token. Updating the password, incrementing `session_version`, clearing `must_change_password`, and invalidating all outstanding reset tokens for that account share one database transaction. Concurrent token submissions permit only one password change. Existing protected sessions and trusted-device cookies are invalidated through their established session-version checks. Reset completion does not disable MFA or automatically sign the user in.

User locks precede token locks in issuance and completion. Shared admission locks are acquired in a stable order across workers. New code uses a dedicated nonpersistent PDO connection with native prepares and a two-second lock-wait setting.

The service checks that existing users and token tables use InnoDB and refuses operation otherwise, preventing a false promise of transactional rollback on nontransactional tables.

## Mail queue and outages

The additive `password_reset_outbox` stores an AES-256-GCM encrypted token using the existing application encryption helper. The key is APP_KEY; do not change it during rollout. Email addresses in the outbox are personal data and require normal database access controls. Token values and complete links are excluded from guarded public responses, development-link logs, worker output, and audit metadata.

The CLI worker processes at most 20 jobs by default, configurable from 1 to 100. Workers claim a job transactionally and mark it `sending` before contacting the transport. An expired, consumed, superseded token or changed/inactive account is discarded without mail. Each job receives at most one automatic transport attempt. Accepted transport does not prove inbox delivery.

An exception after transport activity or a crashed worker can have an uncertain delivery outcome. No automatic resend occurs, avoiding duplicate-mail flooding. A stranded `sending` job is not reclaimed; expiry maintenance clears it. Users may make a fresh request after the cooldown and within quota, or contact independently verified support. A permitted later request supersedes earlier links; mail already in transit may therefore contain an obsolete link.

Transport uses the existing SMTP connection/read timeouts. A successful SMTP DATA response counts as acceptance even if QUIT fails. No real email or SMTP account was exercised in implementation tests; provider acceptance, sender configuration, delivery delay, and support handling remain staging checks with designated test recipients.

Local events are `password_reset_guard_processed` (processed/limited/invalid), `password_reset_guard_unavailable`, `password_reset_mail_processed` (accepted/failed/discarded), and the existing `password_reset_completed`. `processed` does not disclose whether a mail job exists. Existing Phase 1 observations continue. These new mail events must be included when reviewing guarded-flow delivery; legacy `password_reset_requested` events describe the old synchronous path. No external notification or response-owner assignment was performed.

## Deployment and maintenance

1. Apply only `20261010_0019_password_reset_protection.php` in isolated staging. Review unrelated pending migrations before running a general migration command. Verify that existing users/token tables use InnoDB; cross-table rollback requires transactional storage.
2. Load the approved application/database environment into the CLI worker without storing credentials in scripts or command arguments. Apache SetEnv does not automatically populate a shell. Pin APP_URL to the approved HTTPS address and keep APP_KEY identical between web and worker.
3. Set `PASSWORD_RESET_GUARD_MODE=enforce` in staging, keep the reset feature enabled, and validate the worker with designated test recipients. Arrange an approved repeating worker run (for example every minute), prevent unnecessary overlapping jobs, and verify queue expiry and failure monitoring. **No Windows scheduled task was installed.**
4. Complete Phase 2's pilot/production activation first, as specified by the phased plan. Approve Phase 3 limits, worker schedule, support verification, monitoring owner, and representative recovery tests. Only then apply this specific migration and activate Phase 3 in production.

```text
php scripts/password_reset_mail.php --help
php scripts/password_reset_mail.php --max=20 --prune
```

`--prune` performs bounded maintenance: at most 1,000 expired pending payloads are cleared, 1,000 admission entries older than the rolling hour are deleted, 1,000 old limiter identities are removed, and 1,000 terminal mail rows expired more than 48 hours ago are deleted. Repeat maintenance for a backlog. It never deletes users, password-reset token records, or audit logs. Old limit rows are only eligible after their admission history has expired. Expired `sending` jobs have an unknown transport result; clearing their payload is not proof they were never sent.

Before relying on delivery, monitor queued/expired counts, overdue `sending` jobs, terminal failures, limiter/database errors, and support reports. Outage requests issue no mail/token while the generic response stays consistent. The protected flow needs a functioning worker; enabling it without scheduling the worker will prevent ordinary reset-mail delivery.

## Rollback

Set `PASSWORD_RESET_GUARD_MODE=off` and stop scheduled worker invocations. Retain additive tables and evidence. Previously issued compatible token hashes remain usable through the earlier reset flow. Queue payloads should be retained securely until their expiry cleanup is completed under the maintenance policy. The previous synchronous request flow and its documented flooding/concurrency risks return; monitoring and corrective follow-up are required. Do not disable MFA, remove session checks, or activate pending accounts as rollback measures.

## Verification

Final validation: **36 tests passed, zero failures, zero skips** against the dedicated loopback MariaDB instance and `dts_security_test_phase3` schema. Tests cover cooldown and rolling quotas, global volume, inactive/nonexistent addresses, preservation of links on rejection, session-version invalidation, encrypted payload cleanup, single-use/expiry, forced post-password-update rollback, and counter/encryption/provider outages.

Eight concurrent request workers produce exactly one token/job; eight concurrent token completions produce exactly one password change; four concurrent delivery workers produce exactly one accepted transport callback. HTTP checks exercise the actual controller and CSRF flow, confirming identical response bodies/statuses for accepted, limited, nonexistent, inactive, and unavailable-counter requests with no exposed development links. Test mail delivery uses callbacks, not real SMTP or PHP mail.

The temporary HTTP server and test database server are stopped after verification. No Phase 3 production migration, mail-worker schedule, account modification, or enforcement activation was performed. Browser-assisted representative-user testing, real approved test-recipient delivery, recovery ownership, and production rollout approval remain operational release requirements.
