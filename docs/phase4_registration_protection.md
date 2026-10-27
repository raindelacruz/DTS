# Phase 4 registration protection

Implemented October 10, 2026. Code and isolated tests are complete. Production migration, eligibility approval, enforcement, invitation operations and representative-user pilot remain pending. Phase 2 and Phase 3 production enforcement remain off. Phase 0 preserved evidence and the original inactive accounts were not modified.

## Eligibility decision prepared for staging

The prepared eligibility model is **administrator-issued invitations followed by administrator approval**. No authoritative staff-directory connection, approved employee-ID pattern, or final organizational eligibility choice was supplied. Invitation eligibility is the stated implementation assumption, not a claim that policy approval has occurred. Email verification and directory integration were not implemented as alternate modes.

`REGISTRATION_GUARD_MODE` defaults to `off`. Enforcement additionally requires `REGISTRATION_ELIGIBILITY_MODEL=invitation`; absent or unsupported policy values refuse protected registration rather than silently opening it. This explicit setting is required before activation. Existing registration behavior remains when the guard is off.

An active MFA-enabled administrator may be recorded as the issuer by an authorized server operator. The operator verifies the employee independently using the approved roster or established organizational process, then issues a code bound to the exact employee ID, normalized email, and department. The CLI uses existing privileged application environment access; supplying an administrator ID is an audit attribution assertion, not password/MFA authentication of that account. Restrict CLI execution, database credentials and invitation distribution to authorized operators. The web approval route retains authenticated administrator/MFA/session checks.

Invitation possession proves possession of a secret issued through this workflow; it does not independently prove email ownership or employment. Those facts must be verified before issuance and approval. Distribute codes through an approved existing channel after identity verification. No email, message, directory request, or real invitation was sent or issued during implementation.

Codes are 32 random bytes, stored only as SHA-256 hashes, single-use and expiring after 48 hours by default (configurable at issuance from 1 to 168 hours). New issuance revokes outstanding unused invitations for the same ID or email. Operators can revoke unused invitations with an audit note. Public forms accept codes by POST; codes are not placed in URLs, echoed back in forms, stored in form state, or included in audit/exception metadata. The CLI intentionally displays a newly issued code once to its authorized operator; do not redirect that output into general logs or shared files.

This model places human eligibility verification at invitation issuance and approval, with support-assisted invitations as the accessible alternative. No CAPTCHA provider, widget, or third-party service was added. If the owner chooses email verification or directory eligibility instead, that is a policy and implementation change before rollout.

## Identifier and registration controls

Employee-ID spelling and case are preserved; outer whitespace is trimmed. IDs must fit the existing 50-character field and contain no control characters. No numeric-only pattern or NFA domain restriction was invented. Bound invitation details prevent arbitrary uninvited IDs from creating accounts. Existing accounts are not renamed or forced to re-register.

`REGISTRATION_ID_PATTERN` is an optional owner-approved PCRE expression. A match must cover the entire ID, even if the configured pattern omits anchors. Invalid patterns refuse the action. Leave it unset until the approver has reviewed legitimate existing formats and established a support path for exceptions. Do not impose a speculative format on employees.

Proposed limits are **five evaluated submissions per employee ID, email, and invitation code per rolling 15 minutes**, supplemented by **100 evaluated submissions globally per rolling hour**. These are candidate settings requiring legitimate-volume review and policy approval. They are defined in `RegistrationProtection` and are independent of Phase 1's observation thresholds. Structural/password/CSRF validation failures do not create accounts and are monitored by existing request observations; the new quota applies to submissions reaching eligibility evaluation.

Counters use keyed HMAC identities and shared InnoDB transactions across browsers and workers. Global locking precedes ordered subject locking. Limited requests roll back new limiter identities, so rotating identifiers during a global denial cannot grow the subject table. They do not prolong quota windows. Source signals remain available through Phase 1, but no shared-IP denial is imposed while proxy identity is unresolved.

Valid invitations still create **inactive staff accounts**, regardless of posted role/status. Duplicate retries cannot create additional users or replace the original password. Existing unique ID/email constraints are required and checked. Used, expired, revoked or mismatched invitations cannot authorize a different registration. Issuance/registration database failures are handled without creating a partial account.

Global admission limits can temporarily affect unrelated new registrants during sustained abuse; existing employee login is unaffected by these registration counters. Operators should investigate sustained global denial and arrange verified assistance rather than opening public registration as a workaround. This is not a complete network denial-of-service defense; edge resource limits and log-capacity ownership remain operational work.

## Approval, rejection and administrator notifications

The existing User Management status form shows the protected registration's review state and latest review note. An administrator must provide a 10–500-byte verification/rejection note before changing a protected account's status. First approval checks that account ID, email and department still match the verified invitation. Approval activates the account; rejection/suspension leaves it inactive. Each review increments `session_version`, including rejection, invalidating protected sessions/trusted cookies through established checks.

Reviews record actor, decision, time, invitation reference and reason in the same transaction as the status change and audit event. Audit failure rolls the change back. Later legitimate administrative department/email changes do not require matching the original invitation again after initial approval. Administrator role changes remain governed by the existing authorized role-management flow; invitation submissions cannot request elevated roles.

New protected registrations create at most one grouped in-app registration notice per active administrator per 15 minutes. The notice directs administrators to User Management rather than generating one notification for each applicant. Retried submissions do not send additional notices. Registration, invitation consumption, review creation, grouped notice and account-registration audit commit together. No external notification destination was configured.

Existing accounts without a protected-registration record retain their established status-management workflow. This includes the original incident's inactive accounts: no automatic eligibility inference, approval, deletion, migration into invitation review, or bulk cleanup occurred. They remain subject to the separately preserved incident review register.

Events include `registration_invitation_issued`, `registration_invitation_revoked`, `registration_invitation_rejected`, `registration_limited`, `registration_duplicate_suppressed`, `account_registered` with invitation/pending-review metadata, and `registration_reviewed`. Existing administrator status/role/department auditing remains. Verification notes must reference the approved evidence source without including passwords, invitation codes, or unnecessary personal records. Assign collection and response ownership before relying on these local signals.

## Operator commands and maintenance

Use the existing approved application/database environment. Apache SetEnv does not automatically populate a CLI environment. Do not commit credentials, employee input files, or displayed invitation codes. Issue/revoke accept JSON through standard input to avoid placing secrets in command arguments.

```text
php scripts/registration_invitations.php --help
php scripts/registration_invitations.php issue
php scripts/registration_invitations.php revoke
php scripts/registration_invitations.php prune
```

For `issue`, supply `administrator_id`, `id_number`, `email`, `department_id`, `reason`, and optionally `hours`. For `revoke`, supply `administrator_id`, `invitation_id`, and `reason`. Eligibility/verification notes are mandatory for these administrative decisions. Keep a confidential delivery record through the organization's approved process; the application never stores the plaintext code for retrieval.

`prune` removes at most 1,000 attempt entries older than an hour and 1,000 limiter identities untouched for more than two hours. It never deletes accounts, invitations, reviews, audit logs or incident evidence. Repeat for a backlog through approved maintenance. No scheduler was installed.

For unwanted accounts, retain inactive status, verify each record against authoritative eligibility/testing information, preserve relevant evidence, and record the administrator's decision before any separately authorized disposition. Protected registrations can be rejected through the status form. No destructive bulk-disposal tool was added.

## Rollout and rollback

1. Apply only `20261010_0020_registration_protection.php` to isolated staging; review unrelated pending migrations before running a general migration runner. Verify the expected InnoDB tables and unique ID/email indexes. The service refuses nontransactional account/notification/audit tables or missing uniqueness guarantees.
2. Set `REGISTRATION_GUARD_MODE=enforce` and explicitly select `REGISTRATION_ELIGIBILITY_MODEL=invitation` in staging. Keep the optional ID pattern unset until approved.
3. Pilot issuer verification/distribution, legitimate ID formats, ordinary form submission, duplicate clicks, inaccessible/missing/expired invitations, shared-office-IP users, approval/rejection notes, status/role authorization, grouped notifications and outages. Have the account approver define eligibility evidence, escalation/exception handling, notification ownership and support capacity.
4. Complete the earlier Phase 2 and Phase 3 production rollouts before Phase 4. Approve this model and limits, then apply this specific migration and activate enforcement through the approved production release mechanism.

No Phase 4 production migration, environment change, invitation issuance or account-status change was performed. Broad enforcement is not enabled merely by deploying these files.

Rollback sets `REGISTRATION_GUARD_MODE=off`. Preserve additive tables, review evidence and account status. The old public-registration and per-registration notification behavior return, along with the previously identified abuse risk; protected-review enforcement also stops. Review pending protected accounts manually through the approved incident/eligibility process. Never activate inactive accounts or remove authentication checks as rollback measures.

## Verification

**39 tests passed, zero failures, zero skips**, using the dedicated loopback MariaDB instance and `dts_security_test_phase4`. The suite includes the earlier-phase controls plus invitation binding, normalization, optional ID-policy failure, explicit eligibility configuration, expiry/revocation/reissuance, forced inactive/staff defaults, shared-IP independence, duplicate suppression, rolling/global limits, bounded cleanup and secret-free auditing.

Eight concurrent registration workers produce one account, four suppressed duplicates and three limited submissions. Notification volume remains grouped. Tests force audit failures after account insertion and review changes and confirm rollback, including preservation of invitation usability and account status. Public HTTP tests validate CSRF, protected form rendering, duplicate submission, inability of inactive accounts to authenticate, denial of unauthenticated activation, absence of redisplayed invitation codes, and failure without account creation during limiter outage.

Test fixtures and temporary HTTP sessions are removed. The temporary HTTP and database services are stopped after validation. No real user signup, invitation, activation, deletion, notification or email was performed. Representative browser/user acceptance, issuer-channel verification, ownership assignments and production activation remain operational release requirements.
