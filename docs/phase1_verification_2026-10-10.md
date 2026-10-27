# Phase 1 verification and rollout record

**Date:** October 10, 2026  
**Result:** Observation foundation implemented; no new request blocking enabled.

## Changes applied

- Added the shared monitoring-counter table through migration `20261010_0017_security_request_monitor`; only this new migration was applied and recorded.
- Connected observation hooks to login, MFA, password-reset, and registration POST requests.
- Added subject/IP/global candidate policies, privacy-preserving bucket hashes, transactionally recorded observations, threshold warnings, and bounded expired-counter cleanup.
- Added explicit password-stage and authentication-completion events, existing-validation rejection events, request correlation, and source interpretation metadata.
- Hardened forwarded-header parsing; no addresses were added to the trusted-proxy list.
- Added read-only CLI reports and an explicit expired-counter cleanup option.

## Checks completed

| Check | Result |
|---|---|
| Existing and new test suite | 32 passed, zero failed, zero skipped with isolated database checks enabled |
| PHP syntax | Passed for all 13 changed/new PHP files |
| Shared-counter concurrency | Four independent PHP workers; 80 requests; all three buckets recorded 80 increments |
| Atomic failure | Missing fixture audit table caused counter increments to roll back |
| Privacy | Submitted subject values were absent from observation metadata; exception detail was excluded from failure reporting |
| Shared-IP separation | Separate accounts retained independent subject counters |
| Retention | Cleanup obeyed its batch bound and retained unexpired rows |
| Proxy boundaries | Untrusted, injected-leftmost, malformed, oversized, and IPv6-equivalent address cases passed |
| Isolated HTTP endpoint checks | Login 422; registration/MFA/reset 303 for existing rejection paths |
| Endpoint observation/outcome correlation | Four observed requests matched four rejection events, with generated request IDs |
| Endpoint payload privacy | Neither the fake submitted email nor invalid CSRF token appeared in fixture audit metadata |
| Current database reporting | Mode observe; monitoring table ready; no observed requests/counter rows at capture |
| Temporary services | Isolated PHP and MariaDB servers stopped; temporary server data/session directories removed |

The endpoint checks used a separate loopback PHP server and the dedicated `dts_security_test_phase1` database on a separate MariaDB instance. No real-user credentials, registrations, reset deliveries, or MFA guesses were submitted. The current database was queried read-only for reporting after the additive migration.

An initial fixture run used two random test tables in the application database. Its tests completed, but DROP cleanup was denied. Both tables were emptied, and subsequent tests were moved to the isolated instance. The known empty table names are documented in phase1_security_monitoring.md for authorized DBA cleanup. Production schema names are now refused by the database-test entry point.

## Baseline artifacts

- `phase1_security_baseline_2026-10-10.json`: preceding 720 hours, including unverified historical activity.
- `phase1_security_current_2026-10-10.json`: preceding 24 hours and current monitoring readiness.

Neither historical report is a verified legitimate-user baseline. There were no new live monitoring observations at capture; the isolated tests demonstrate the hooks rather than manufacturing live audit events.

## Operational follow-up

- Confirm whether default gateway 192.168.108.254 is also an HTTP/TLS proxy and validate its header handling before granting trust.
- Assign a monitoring owner and route local threshold/failure signals through the organization's monitoring process.
- Observe representative legitimate business activity and approve policies, retention, support messages, and unavailable-service handling before Phases 2–4 enforce limits.
- Resolve the remaining Phase 0 authorization/eligibility questions. SYSADMIN's 15:29 login was confirmed authorized by the user.
- Arrange authorized DBA cleanup of the two empty fixture tables.

Existing account access, passwords, MFA enrollment, and the finalized Phase 0 evidence package were not changed. Unrelated pre-existing workspace modifications were left intact. For operational details and rollback, see phase1_security_monitoring.md.
