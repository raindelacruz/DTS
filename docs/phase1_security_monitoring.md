# Phase 1: Request attribution and security monitoring

## Implemented scope

Phase 1 installs observation-only monitoring for password login, MFA verification, password-reset requests, and registration. It records attempted POST submissions, including invalid submissions, without adding new restrictions to the product flow. Existing login throttling and inactive-account checks continue to operate.

The default `SECURITY_MONITOR_MODE` is `observe`. `off` disables the new observers. Other values are rejected by the monitoring/reporting paths and logged as unavailable; there is no `enforce` mode in Phase 1. Blocking belongs to Phases 2–4 after policy approval.

## Attribution findings and boundary

Read-only network inspection found server address **192.168.108.17** and default gateway **192.168.108.254**. The latter is also the prevalent audit peer. Being the default gateway does not prove that it is an HTTP reverse proxy or that forwarded headers are trustworthy.

No trusted proxy was added. The existing empty `TRUSTED_PROXIES` default remains. Recorded peer addresses cannot currently identify upstream operators; a network administrator must confirm NAT/proxy topology and available gateway logs.

When explicitly configured, proxy trust accepts exact valid IPv4/IPv6 addresses, normalized for equivalent IPv6 representations. It evaluates X-Forwarded-For from right to left and stops at the first untrusted hop. Untrusted peers, malformed chains, oversized headers, and excessive hop counts fall back to the direct peer. Untrusted forwarded-header contents are never written to audit metadata. Forwarded protocol is accepted only from a trusted peer and as one protocol value.

Before setting `TRUSTED_PROXIES`, verify the proxy identity, origin access boundary, and that the proxy overwrites protocol headers and either overwrites the source header or appends the actual client address. Do not trust the gateway merely because it is on a private network. If TLS is terminated upstream, validate secure cookie/HSTS behavior in staging after topology confirmation.

## Shared counters and candidate policy

`security_request_counters` uses an InnoDB primary key for action, scope, keyed bucket identity, window start, and window duration. Each observation atomically increments subject, IP, and global buckets and records its audit event in one transaction. Upserts and row locking serialize independent workers; browser/session changes do not reset the subject bucket. Workers use the database clock and must share the same database, APP_KEY, and policies.

Subjects are normalized and represented by keyed HMACs. No submitted identifier/email, password, OTP, reset token, or full reset link is added to the monitoring metadata. MFA subject buckets use the existing account ID, not the code or browser session. IPs remain present in existing audit fields for authorized incident review.

The following are **candidate observation thresholds**, not approved blocking settings:

| Action | Window | Subject | IP | Global |
|---|---:|---:|---:|---:|
| Password login | 15 minutes | 10 | 500 | 5,000 |
| MFA verification | 5 minutes | 5 | 500 | 5,000 |
| Password reset | 15 minutes | 3 | 200 | 2,000 |
| Registration | 15 minutes | 3 | 100 | 1,000 |

Crossings are recorded when the count becomes greater than the candidate threshold. A shared-IP crossing does not reject unrelated accounts. Fixed windows can allow adjacent-window bursts; these counters provide the observation foundation, not a final cooldown or brute-force defense. Later phases must add the intended challenge/cooldown behavior.

Set `SECURITY_MONITOR_POLICY_JSON` to an object covering exactly `login`, `mfa`, `password_reset`, and `registration`, each with positive integer `window_seconds`, `subject`, `ip`, and `global` fields. Invalid policy configuration is reported as unavailable rather than replaced with unlimited rules.

## Retention and failures

`SECURITY_MONITOR_RETENTION_HOURS` defaults to 48, bounded between 1 and 168. Counter expiry occurs after the window ends plus retention. Each successful observation opportunistically deletes at most 50 expired counters. This limits work per request; after long idle periods or a large backlog, use the CLI cleanup. Counter expiry is not an audit-log deletion policy. Preserve audit records under the organization's retention rules.

On counter/audit failure, the observation transaction rolls back and returns explicit `status=unavailable`, with a sanitized application error log. There is no new user denial in observation mode. Monitoring failures must be investigated; observations are not evidence that an unavailable service allowed an action under an approved limit. Enforcing phases must explicitly defer/reject the sensitive action or use an approved fallback when this state occurs. They must not interpret `blocked=false` in observation mode as an enforcement decision.

Threshold warnings are emitted once per bucket/window when the crossing occurs. Audit events and application logs are local signals; no email/Slack notification or monitoring-owner assignment has been performed. Configure collection and response ownership operationally before relying on these signals. Repeated outages can create repeated error messages and require log-capacity monitoring.

## Event semantics

| Event | Meaning |
|---|---|
| `security_request_observed` | A monitored request was counted and audited; it was not necessarily accepted by the endpoint |
| `security_threshold_observed` | A candidate bucket threshold was crossed; no new rejection occurred |
| `security_request_rejected` | Existing endpoint validation, feature status, or authentication checks rejected a request; includes action/reason |
| `security_request_processed` | A generic reset response was returned without a delivery attempt; no account existence is exposed to the requester |
| `password_accepted` | Active-account password validation succeeded; MFA may still be pending |
| `login_succeeded` | Legacy event retained, now tagged `stage=password_only`; do not double count with `password_accepted` |
| `authentication_completed` | Authentication completed via password-only, TOTP, trusted device, or initial MFA enrollment, as indicated by `method` |
| Existing `mfa_failed`, `login_failed`, `login_blocked`, `inactive_account_login` | Distinct failed/rejected stages |
| Existing `password_reset_requested` | Delivery attempt result; `mail_accepted` means transport acceptance, not inbox delivery |
| Existing administrator-change events | Role/status/department/password/MFA changes, retained with source context |

New audit metadata includes a generated request ID, peer IP, source interpretation, and forwarded-header status for correlation. Request IDs are generated by the application, not accepted from request headers. Historical rows remain unchanged.

## Reporting and cleanup

Run CLI commands with the existing approved database/application environment loaded. Apache SetEnv values do not automatically populate a separate shell; do not paste credentials into commands or commit them to files.

```text
php scripts/security_monitor.php --hours=24 --json
php scripts/security_monitor.php --hours=720 --json
php scripts/security_monitor.php --prune --hours=24 --json
```

Reporting is read-only unless `--prune` is supplied. A prune run removes at most 10,000 expired counter rows, never audit events or accounts. Repeat when a backlog remains, or schedule it using the organization's existing maintenance process. No scheduled task was installed.

The baseline output distinguishes observed attempts, candidate crossings, delivery acceptance, and authentication outcomes. Historical counts include unverified activity and the October 10 incident/testing window; they are not an approved legitimate baseline. Observe representative business periods and have the designated owner approve thresholds, retention, support messages, alert destinations, and unavailable-service policy before enforcement.

## Installation and rollback

The additive migration is `20261010_0017_security_request_monitor`. Only this migration was applied to the current database; unrelated pending migrations were not run. It creates the counter table and leaves existing account, audit, reset, and login-counter records intact.

For a future deployment, apply this migration before enabling/loading the request observers. Use the standard migration runner only after reviewing all pending migrations, or have the DBA apply this specific migration through the approved release process.

For rollback, set `SECURITY_MONITOR_MODE=off` using the existing deployment mechanism. Preserve audit events and the counter table for review. The Phase 1 table is additive and can remain when older code is restored. Do not disable existing MFA or login controls. Changing proxy trust requires separate network validation; no proxy settings were changed here.

## Validation and limitations

Tests exercise spoofed/invalid proxy chains, IPv6 normalization, explicit failures, transaction ownership, invalid policies, shared-IP subject separation, privacy, atomic audit/counter rollback, bounded expiry cleanup, and four independent PHP workers totaling 80 requests with no lost increments. Tests use a separate local MariaDB instance and dedicated test schema; they do not submit real registrations, reset emails, MFA guesses, or login attempts.

An initial fixture-only run on the application database left two empty test tables because the application account lacks DROP. All fixture rows were removed. The known residual names are `security_test_9784e41a4ec1_audit` and `security_test_9784e41a4ec1_counters`; they are not referenced by the application and contain no rows. Their removal requires an authorized DBA. Tests now reject production schema names before creating fixtures.

SYSADMIN's October 10 **15:29:28** login and trusted-device acceptance were confirmed authorized by the user. That confirmation does not establish authorization of the other probing, registrations, or account 11 resets.

Phase 1's code and observation foundation can operate while proxy attribution, monitoring-owner assignment, and approval of enforcement policies remain open. Phase 0's finalized evidence files and manifest were not modified.
