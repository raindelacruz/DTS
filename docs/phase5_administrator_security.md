# Phase 5: administrator security and operations

Prepared October 10, 2026. Application changes and isolated verification are complete. Production enforcement, identity approval, external alert routing and infrastructure controls remain pending. Existing accounts and incident evidence were preserved.

## Implemented behavior

When enabled, administrator sessions require MFA and an explicitly approved account ID. Optional IPv4/IPv6 network restrictions use the established trusted-proxy request context. No administrator identities or network ranges have been selected automatically.

Role, department, account-status, administrator password changes and MFA resets require the acting administrator's own password and a fresh TOTP. Verification is bound to the action, target, actor and session version, expires after five minutes, and permits at most five evaluations per challenge. Shared Phase 2 password/MFA attempt controls prevent issuing new challenges from resetting limits. Accepted TOTP counters cannot be reused. Password and MFA recovery actions also require a reason.

Authorization audit insertion must succeed before the action proceeds. This record proves authorization intent; it does not prove the subsequent change completed. Existing completion audits remain separate legacy operations and are not in one transaction with authorization. Responders should reconcile authorization records with completion records and current account state.

A separately approved emergency account may receive access for at most 24 hours, with an authorization reference and an audit event once per session/configuration. It still requires an active administrator account, MFA, and any configured network restriction. This is not an MFA bypass or recovery of a lost MFA secret.

Ordinary employee workflows receive no Phase 5 verification prompt. Administrators receive an extra password/TOTP prompt for the five sensitive actions. TOTP remains susceptible to phishing; phishing-resistant authenticators such as WebAuthn/passkeys require a separate integration. See [OWASP MFA guidance](https://cheatsheetseries.owasp.org/cheatsheets/Multifactor_Authentication_Cheat_Sheet.html) and [reauthentication guidance](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html).

## Configuration and release prerequisites

These controls default off. Set configuration in the application's approved environment, never by publishing credentials in reports or source files.

| Setting | Required value or format |
|---|---|
| `ADMIN_SECURITY_MODE` | `off` (default), or `enforce` |
| `AUTH_GUARD_MODE` | Must be `enforce` before administrator enforcement |
| `ADMIN_PERSONAL_ACCOUNT_IDS` | Nonempty JSON array of approved positive integer account IDs |
| `ADMIN_ALLOWED_NETWORKS` | Optional JSON array of exact IPs or IPv4/IPv6 CIDRs; empty means no additional network restriction |
| `ADMIN_EMERGENCY_ACCOUNT` | Optional JSON object containing `user_id`, integer UNIX `expires_at`, and `authorization_reference` (10–200 characters) |

The emergency ID cannot also be a personal ID. A listed ID is an organizational ownership assertion, not proof that an account is individually assigned. Approve named owners and least-privilege roles before supplying values. Do not assume SYSADMIN is compromised: the reported 15:29 login was confirmed authorized, and shared-account ownership still needs review.

Release sequence:

1. Verify earlier phase migrations, approved policies and successful pilots. Apply additive migration `20261010_0021_security_operational_alerts.php` through the established migration process in staging first. It is not applied to production.
2. Identify at least two individually assigned administrators with tested MFA and recovery arrangements. Test approved office and remote network access, including any proxy configuration. Do not guess trusted-proxy or VPN ranges.
3. Test all five sensitive actions, wrong-password/TOTP limits, expired/replayed challenges, session revocation, audit outages and emergency expiry in staging.
4. Have the operations owner approve account IDs, any network policy, emergency authorization procedure, proposed alert thresholds and routing. Enable the pilot configuration only after a verified recovery path exists.
5. Observe administrator failures and support requests before expanding the policy. Keep incident verification separate from deployment acceptance.

No VPN, gateway/WAF, origin-access restriction, passkey service, scheduler or external alert destination was installed. Their assessment must include remote-user requirements and direct-origin bypass. Do not consider this application's network check a deployed VPN or WAF.

## Monitoring runbook

Run from an approved CLI environment with the same intended application key, database connection and policy settings as the web application. Apache environment settings do not automatically populate a scheduled CLI process.

```powershell
C:\xampp\php\php.exe scripts/security_alerts.php --help
C:\xampp\php\php.exe scripts/security_alerts.php
C:\xampp\php\php.exe scripts/security_alerts.php --collect
```

The default command reads a consistent snapshot without persisting alerts. `--collect` writes local alert records and requires migration 0021. Collection deduplicates each rule/subject in a fixed 15-minute bucket; a continuing condition may produce another record in the next bucket. Records are retained without automatic deletion or acknowledgement. Command errors exit unsuccessfully; missing schemas for enabled controls produce explicit critical readiness findings. A failed scheduled process cannot alert through itself: the scheduler must independently monitor exit status and freshness.

Proposed rules evaluate a sliding 15-minute window, except queue-age/readiness checks:

| Signal | Proposed trigger |
|---|---|
| MFA failures / administrator verification failures | 5 per account / acting administrator |
| Legacy reset requests / recent queued resets | 3 per account |
| Registration burst | 10 total |
| Privileged account change | Any recorded completed change |
| Emergency administrator access | Any recorded use; critical |
| Reset protection unavailable | Any recorded occurrence; critical |
| Reset request limits | 10 total limited requests |
| Queue health | Any expired queued item (critical), or sending claim older than 5 minutes |
| Administrator readiness | Active administrator without MFA; critical |
| SYSADMIN identity | Ownership-review notice, not a compromise finding |

Recent queued-reset detection approximates issuance time from the fixed 30-minute token expiry. It is a queue indicator, not a complete historical request count. Rules rely on their respective audit events and schemas; enabling collection alone does not enable earlier protections. Snapshot output omits passwords, reset tokens and email addresses, but contains account IDs and security events; restrict access accordingly.

Assign a primary security operator and backup before scheduling collection. Approve a cadence (for example, every minute in staging), persistence retention, and an external delivery mechanism. The current CLI reports `external_delivery: not_configured`; local collection does not send email or messages. Test external delivery and acknowledgement using synthetic staging incidents before declaring alert routing operational.

## Response and recovery

1. Record the rule, database time, subject account, request IDs and relevant authorization/completion events. Preserve evidence before changes; do not edit the Phase 0 evidence directory.
2. Verify the named account owner's activity independently. A burst or SYSADMIN notice alone does not establish compromise. Source addresses must be interpreted using verified proxy/gateway configuration.
3. For suspected account takeover, use an unaffected authorized administrator to follow approved status/session-revocation and credential/MFA recovery procedures. Obtain and record recovery reasons. Do not expose temporary passwords or TOTP secrets in an alert.
4. For mail/registration abuse, inspect earlier-phase counters, queue health, provider responses and review records. Avoid repeated mail retries while investigating ambiguous delivery; Phase 3 intentionally avoids automatic retransmission of claimed messages.
5. For missing schemas, failed commands or audit outages, escalate to the application/database owner and restore health. Do not bypass failed verification by disabling MFA or activating unverified registrations.
6. Close an alert only after the owner verifies the explanation, containment and legitimate-user access. Maintain the response record outside the current collector, which has no acknowledgement workflow.

Keep a second verified administrator available. If emergency access is needed, obtain independent authorization, record the reference, set a short explicit expiry, test the account's existing MFA and approved network path, and review every use. If all administrator MFA credentials are lost, this feature cannot recover them; use a separately approved and tested identity-recovery procedure.

Review account ownership, emergency configuration, network ranges, threshold volumes and alert delivery regularly and after infrastructure changes. Roll back administrator enforcement to the previously tested compatible configuration if authorized administrators cannot operate; retain MFA and compensating protection, preserve logs/counters, and document the reopened exposure. The additive alert table can remain for evidence. No automatic account removal or deletion of the 69 incident accounts is included.

## Verification evidence and limits

The complete isolated regression suite passed **41 tests, 0 failures, 0 skipped** on a separate loopback MariaDB instance and dedicated `dts_security_test_phase5` schema. Tests cover network matching, personal/emergency policies, challenge binding/expiry, account-shared limits, TOTP replay, strict authorization-audit failure, alert readiness/deduplication and queue conditions.

HTTP tests demonstrate that trusted-device login alone cannot change a role; fresh verification permits the change, a reused challenge is rejected, and authorization-audit failure prevents the change. Fixture accounts and mocked mail stayed in the isolated environment. No production migration, administrator-policy enforcement, account change or external notification was performed for Phase 5.

Application implementation is ready for policy review and staging rollout. Phase 5 operational completion still requires verified identity ownership, recovery drills, production acceptance and tested alert routing. Optional phishing-resistant MFA and network/WAF projects remain separate outstanding work.
