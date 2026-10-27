# DTS Phased Security Implementation Plan

**Prepared:** October 10, 2026  
**Status:** Phase 0 evidence preserved; SYSADMIN's 15:29 login confirmed authorized. Other human verification remains pending. Phase 1 observation foundation implemented. Phases 2–5 application code and isolated tests completed; policy approval, enforcement and pilots pending. Phase 5 identity ownership, external alert routing and infrastructure work remain pending.  
**Objective:** Reduce automated registration abuse, password-reset flooding, and MFA guessing while preserving legitimate employee access.  
**Scope:** Controls identified in the DTS incident reviews and security assessment.

## Planning assumptions

- DTS supports employees who may share a public or private network address.
- Existing accounts, pending registrations, recovery workflows, and administrator access must remain usable.
- New registrations continue to default to inactive/staff until verified.
- The identity and authorization of the observed operator remain unresolved; SYSADMIN compromise is not established by the reviewed records.
- Durations below are indicative working-day ranges, not delivery commitments. Staffing, infrastructure access, testing, and approvals can change them.
- No code, configuration, account, password, or database record is changed by this plan.

## Sequence and estimated scope

| Phase | Scope | Indicative effort | Suggested lead | Dependency |
|---|---|---|---|---|
| 0 | Evidence preservation and incident verification | 1–2 working days | System administrator / incident lead | None |
| 1 | Request attribution, limiter design, and monitoring foundation | 2–4 working days | Application maintainer + network administrator | Initial Phase 0 findings |
| 2 | MFA attempt protection and throttle reliability | 3–5 working days | Application maintainer | Phase 1 |
| 3 | Password-reset abuse controls | 2–4 working days | Application maintainer | Phase 1; production rollout after Phase 2 |
| 4 | Registration abuse controls and verification workflow | 3–5 working days | Application maintainer + account approver | Phase 1; production rollout after Phase 3 |
| 5 | Administrator access, optional infrastructure controls, and sustained monitoring | Separate estimate after infrastructure review | System/network administrators + application maintainer | Stable earlier phases |

Allow additional time for representative-user validation, release observation, and remediation of failures. Phase 5 may become a larger project if it includes directory integration, VPN changes, or phishing-resistant MFA. The core phases do not require a complete application rewrite.

## Phase 0 — Establish facts and preserve evidence

**Execution record:** [Phase 0 incident status and handoff](docs/phase0_incident_status_2026-10-10.md). The technical collection is complete subject to its recorded integrity checks; the overall phase remains open for organizational verification and ownership decisions.

### Planned work

- Preserve relevant account inventory, registration/reset/MFA audit records, access-change history, Apache logs, and available mail/network records under the organization's evidence procedures.
- Identify the operator and authorization of testing during the observed windows.
- Confirm account 11's reset completions and MFA success with its owner; obtain the exact SYSADMIN symptom and time.
- The user confirmed all 69 original registrations are illegitimate on October 10, 2026; their eligibility review is resolved by that attestation. Keep them inactive and decide evidence-preserving retention/cleanup separately. The supplemental 70th registration still requires verification. No account cleanup has been authorized or performed.
- If unauthorized access is substantiated, assign containment and recovery to the responsible administrator, including assessment of sessions, trusted devices, credentials, and MFA enrollment. Treat containment as a separate incident decision rather than waiting for feature development.

### Completion criteria

- Evidence locations, reviewed time windows, ownership, and outstanding attribution questions are documented.
- A responsible incident lead is assigned.
- The account-review and recovery decisions are documented; unresolved questions remain explicit.

### User impact

Most employees see no change. New registrations may remain pending while eligibility is checked. Recovery for a confirmed affected account requires direct coordination with its owner.

## Phase 1 — Establish safe request attribution and control design

**Execution record:** [Phase 1 security monitoring](docs/phase1_security_monitoring.md). The shared counters, attribution safeguards, observation policies, audit-stage distinctions, and reporting are implemented. Proxy-role verification, monitoring-owner assignment, representative legitimate baseline review, and enforcement-policy approval remain operational follow-up items. No new blocking is enabled.

### Planned work

- Map the actual gateway/proxy path and determine what 192.168.108.254 represents.
- Define trusted proxy addresses and forwarded-header handling so external clients cannot choose their recorded source IP.
- Design shared application request counters with atomic updates, bounded retention, and consistent behavior across workers or servers. Do not rely solely on browser sessions or process-local memory.
- Define separate policies for password login, MFA verification, password recovery, and registration.
- Combine account/action limits with secondary IP and global-volume signals. A shared employee IP must not create blanket lockouts for unrelated accounts.
- Define safe behavior when counters are unavailable: monitor/alert and apply a documented fallback or temporary restriction to the affected sensitive action. Avoid silently permitting unlimited attempts.
- Separate audit events for password acceptance, MFA completion, trusted-device acceptance, request limiting, and administrator changes. Do not log passwords, OTPs, reset tokens, or full recovery links.
- Capture baseline legitimate volumes and define thresholds, retention, support messages, and incident-alert recipients with the operational owners.

### Completion criteria

- The identity of the direct proxy/gateway is established, or a documented attribution limitation is retained.
- Thresholds and failure behavior are approved as operational policies before enforcement.
- Concurrent workers share effective counters; restarting a browser or requesting a new challenge does not reset account-wide abuse counters.
- Metrics distinguish attempted actions, rejected requests, SMTP acceptance, and completed authentication.

### User impact

No intentional change during baseline monitoring. Any audit wording changes must remain internal rather than exposing implementation details to users.

## Phase 2 — Protect MFA verification and strengthen login throttling

**Execution record:** [Phase 2 controls, proposed policy, and rollout](docs/phase2_authentication_protection.md). 35 tests passed with no failures or skips, including isolated MariaDB tests and eight concurrent password workers. Production migration and enforcement remain pending policy approval and representative pilot validation.

### Planned work

- Add per-account and per-challenge MFA attempt limits, progressive delays, short-lived challenges, and challenge termination after excessive failures.
- Require restarting authentication after challenge termination, while retaining account-wide failure history so new sessions cannot erase the limit.
- Restrict reuse of accepted OTPs within the relevant verification context and preserve appropriate clock tolerance.
- Review the existing password-login throttle for concurrent access, boundary behavior, distributed attempts, and database-error handling.
- Define alerts for repeated MFA failures after correct password entry. Aggregate notifications to avoid creating a second flooding mechanism.
- Provide a clear retry time and verified support-assisted recovery route. Avoid permanent account lockout triggered solely by hostile failed attempts.
- Review session transitions to ensure MFA state is tied to the current account and authentication attempt, with status/session-version checks at protected routes.

### Completion criteria

- Correct password and MFA still succeed for legitimate users.
- Repeated incorrect OTPs are limited across sessions/challenges.
- MFA success is recorded only after verification or accepted trusted-device validation.
- Shared-IP users can authenticate independently.
- Counter failure follows the agreed safe policy and generates an operational alert.
- Account recovery remains possible under documented administrator verification.

### User impact and rollout

Routine login should remain unchanged. Repeated incorrect codes introduce a delay or restart requirement, with understandable instructions. Pilot with designated accounts before broader enforcement.

## Phase 3 — Control password-reset requests

**Execution record:** [Phase 3 reset protection and rollout](docs/phase3_password_reset_protection.md). Shared cooldown/rolling quotas, encrypted bounded mail queue, and transactional single-use completion are implemented. Production migration, worker scheduling, enforcement-policy approval, and pilot remain pending. Production rollout follows Phase 2 activation.

### Planned work

- Add per-account cooldowns and rolling request quotas, supplemented by source/global limits.
- Apply equivalent generic responses for existing and nonexistent accounts, including limited requests, to reduce account-discovery signals.
- Reuse no secret token in user messages or logs; preserve secure token generation, hashed storage, expiry, and controlled consumption.
- Reject or suppress excessive submissions before generating/replacing tokens or scheduling another email. A throttled submission should not invalidate an already issued valid reset link.
- Preserve a clearly defined single-use recovery lifecycle, including concurrent reset submissions and completion handling.
- Limit challenges and outbound email volume; introduce additional human verification only when risk warrants it.
- Define provider-outage and retry behavior with a bounded delivery process. SMTP acceptance must not be presented internally as proof of inbox delivery.

### Completion criteria

- A legitimate reset completes successfully and invalidates older protected sessions through the existing session-version mechanism.
- Repeated requests respect cooldowns without generating additional mail or invalidating valid links.
- Request flooding does not lock the victim's normal account access.
- Tokens expire and cannot be used for multiple successful resets, including concurrent requests.
- Generic responses do not reveal account existence or bypass cooldowns.
- Recovery remains usable during ordinary email delay and repeated-click scenarios.

### User impact and rollout

Show a clear explanation of the resend wait and support route without revealing whether an account exists. Existing-account users should retain normal sign-in access even when reset requests are limited.

## Phase 4 — Control registration and verify eligibility

**Execution record:** [Phase 4 invitation eligibility, limits and rollout](docs/phase4_registration_protection.md). Invitation-bound eligibility is the prepared staging assumption, pending organizational approval. Shared registration quotas, transactional duplicate prevention, grouped notices and audited approval/rejection are implemented. Production migration and enforcement remain off; rollout follows Phases 2 and 3.

### Planned work

- Define a valid employee-ID format and normalization rules with the account approver. Account for legitimate existing identifiers before enforcing a new rule.
- Limit new registration volume by action, source signals, and overall service load; handle retries without duplicate records.
- Select the organization's eligibility model: invitation, staff-directory verification, or verified employee email plus administrator review. The final choice depends on available records and policy.
- Keep inactive/staff defaults and prevent client-supplied roles/status from changing them.
- Add risk-based human verification as supporting protection, with accessible alternatives.
- Rate-limit or aggregate administrator registration notifications and retain visibility into suspicious submissions.
- Define an evidence-preserving process for reviewing and disposing of confirmed unwanted registrations.

### Completion criteria

- Valid employees can submit and complete the approval process.
- Identifier rules reject unsupported input without rejecting legitimate IDs.
- Repeated automated submissions are constrained before large numbers of records/notifications are created.
- New accounts cannot authenticate while inactive or assign themselves elevated privileges.
- Approval, rejection, status changes, and exceptions have an accountable audit trail.
- Existing users are not forced through re-registration because of new validation rules.

### User impact and rollout

This phase primarily affects new users: they may need an invitation or ownership verification and administrator approval. Pilot with representative departments before enforcement. Preserve a support path for valid employees whose records do not match automatically.

## Phase 5 — Harden administrator access and sustain protection

**Execution record:** [Phase 5 administrator controls and operations runbook](docs/phase5_administrator_security.md). Fresh administrator verification, approved-account/network policy, time-bounded audited emergency access and local alert collection are implemented and covered by 41 passing isolated regression tests. Enforcement defaults off; production migration, named ownership, recovery drills, external alert routing and optional phishing-resistant MFA/VPN/WAF work remain pending.

### Planned work

- Use individually assigned administrator identities and least privilege; retain an audited emergency-access process.
- Evaluate phishing-resistant MFA for administrators and reauthentication before sensitive actions such as MFA reset, password reset, or role changes.
- Evaluate approved-network/VPN restrictions for administrator access against actual remote-work requirements.
- Assess gateway/WAF limits and bot protections as an additional layer. Verify that clients cannot bypass them by reaching the origin directly.
- Build alerts and response procedures for registration bursts, repeated resets, MFA failures, and privilege changes.
- Validate operational owners, support procedures, counter/alert health, and periodic control reviews.

### Completion criteria

- Administrators retain a tested access/recovery path without requiring a shared everyday identity.
- Sensitive administrator actions receive appropriate reauthentication and auditing.
- Network restrictions, if adopted, accommodate authorized off-site administrators.
- Alert routing is tested, and designated staff know how to investigate and respond.
- Limits remain effective through infrastructure changes and monitoring failures are visible.

### User impact

Most employees see no new step. Administrators may need stronger MFA, VPN access, or additional verification for sensitive changes. Infrastructure-dependent measures require their own estimate and change review.

## Threshold policy

Do not choose production thresholds from attack counts alone. Use legitimate baseline volumes, expected shared-network behavior, account sensitivity, and support capacity.

Document each limit's scope, time window, enforcement action, user message, expiry, monitoring, and exception process. MFA thresholds should be substantially tighter than ordinary page-request limits. Prefer short reversible restrictions to prolonged account denial where an attacker can trigger failures.

No numerical limit in this plan is an approved production setting; exact values remain a design decision after baseline review.

## Release, validation, and rollback

For each phase:

1. Prepare a concrete change set and configuration/migration plan in an isolated development environment when implementation is authorized.
2. Validate normal workflows, repeated requests, concurrent workers, shared-IP users, provider/counter outages, and support recovery in staging.
3. Review the change with the operational owner and confirm production release authorization before deployment.
4. Pilot with designated users or accounts where the control supports safe staged enforcement. Maintain existing authentication barriers throughout.
5. Release one control group at a time and monitor request limits, legitimate failures, mail volume, pending registrations, and support reports over a representative business-use period.
6. Expand enforcement only after acceptance criteria pass and observed user friction is acceptable.

Rollback triggers include legitimate users unable to recover/sign in, widespread shared-IP restrictions, counter failures, unexplained mail suppression, or loss of audit visibility.

For rollback, preserve evidence and counters needed for investigation, restore the prior compatible application/configuration, and retain compensating controls for any reopened abuse path. Never roll back by disabling MFA or granting access to inactive accounts. Any data migration must have a tested compatibility/recovery procedure; reverting code alone may be insufficient.

## Project acceptance

The core effort is complete when Phases 1–4 meet their criteria, normal employee workflows are verified, unwanted request volume is controlled, and operational owners can detect and respond to recurrence. Phase 5's optional infrastructure/identity projects may continue under separate scope.

Completion does not mean that all security risks are eliminated or that the original operator has been identified. Incident verification and application hardening have separate outcomes.

## Reference material

- Local assessment: security_control_assessment_2026-10-10.md.
- Registration incident: incident_report_69_accounts_creation_sources_2026-10-10.md.
- Related account activity: sysadmin_related_activity_report_2026-10-10.md.
- [OWASP Authentication guidance](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html).
- [OWASP Password Recovery guidance](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html).
- [OWASP Anti-Automation guidance](https://cheatsheetseries.owasp.org/cheatsheets/Bot_Management_and_Anti-Automation_Cheat_Sheet.html).

This document began as a planning artifact. Authorized implementation progress is recorded under each phase and in its linked report. Production enforcement and operational acceptance remain distinct from application implementation and incident closure.
