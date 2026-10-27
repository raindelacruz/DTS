# DTS Security Control Assessment

**Date:** October 10, 2026  
**Scope:** Registration, password recovery, password login, MFA, account activation, session controls, and evidence attribution relevant to the observed activity.  
**Method:** Static review of available local code/configuration and previously verified audit/access-log evidence. No attack simulation or live test requests were performed.  
**Change status:** No implementation or remediation performed.

## Assessment

**Yes: the reviewed application is susceptible to the observed bulk-registration, reset-request flooding, and repeated MFA-guessing activity.** Missing application request limits are supported by code review and by processed requests in the available records. This does not establish that the activity was unauthorized or that an attacker took over SYSADMIN.

The application also has meaningful controls: new registrations are forced to inactive/staff, inactive accounts are rejected at login, password login has throttling, reset tokens are random and expire, protected sessions check account status/session version, and administrator access requires MFA. These controls limit impact but do not prevent the observed public-endpoint abuse.

Ratings below are qualitative review priorities, not CVSS scores or a full penetration-test result. External gateway/WAF controls were not assessed.

## Findings

| Finding | Assessment | Review priority | Evidence |
|---|---|---|---|
| Bulk account creation | Application accepts repeated public registrations without a registration-specific request limit | Medium | 69 matching account/registration events; all remain inactive/staff |
| Reset-email flooding | No per-account cooldown or request throttle in Forgot Password | Medium | Six SMTP-accepted requests for account 11 on October 10 |
| Repeated MFA guesses | MFA verification endpoint has no attempt counter, delay, or lockout in the reviewed flow | High | 177 MFA failures against account 11 on October 10 |
| Probe-like identifiers | Identifier validation permits any nonempty unique string in the reviewed handler | Medium | Administrator-themed and SQL-style identifiers stored in inactive staff accounts |
| Login throttling dependency | Database errors in the throttle check return “not blocked” | Medium, conditional | SecurityService catches throttle-check failures and returns false |
| Incident attribution | Most audit events record one private IP; login-stage success is logged before MFA | Medium operational concern | Limited operator attribution; password success must be distinguished from full authentication |

### 1. Bulk registration is insufficiently controlled

The public registration handler validates CSRF, required inputs, email syntax, password requirements, and identifier/email uniqueness. It does not establish that the requester is an authorized employee or owns the supplied email. No registration-specific rate limit, cooldown, CAPTCHA, invitation requirement, or verified-email step was found in that flow.

A client can use the normal form flow repeatedly with distinct input values. CSRF validation does not by itself prevent this automation. The 69 registrations demonstrate that the application processed repeated submissions, although the identity and authorization of the submitter remain unknown.

Likely consequences include pending-account clutter and administrator review/notification workload. Resource exhaustion is a potential consequence at larger scale; no service disruption was established here. OWASP classifies mass account creation for misuse as an automated threat. [OWASP OAT-019](https://owasp.github.io/www-project-automated-threats-to-web-applications/assets/oats/EN/OAT-019_Account_Creation.html)

The inactive/staff default is an effective separate barrier in the observed records: these registrations did not create administrator accounts, and 20 login events involving 14 new accounts reached the inactive-account rejection path.

### 2. Forgot Password permits repeated email and token generation

For an active account, each valid reset-request submission generates a token and attempts email delivery. No cooldown or per-account request limit was found. The mail sender contains no automatic retry loop; separate processed submissions explain separate accepted delivery attempts in the reviewed evidence.

Each new reset invalidates earlier unused reset tokens. Repeated requests can therefore produce unwanted emails and interfere with a legitimate user's earlier reset link. This is a potential recovery-disruption effect; the user's actual recovery attempts were not traced.

The system retains useful recovery controls: generic request responses, 32 random bytes per token, hashed token storage, a 30-minute expiry, and token consumption after completion. Those controls make token guessing difficult and limit token lifetime. They do not limit request flooding. OWASP recommends protection against excessive automated reset submissions, including per-account limits. [OWASP Forgot Password Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html)

A reset request alone does not change a password. A completion requires a valid reset token and accepted new password. The five recorded completions for account 11 need owner/administrator confirmation; this assessment does not establish how the tokens were obtained or whether those completions were unauthorized.

### 3. MFA lacks protection against sustained online guessing

The MFA handler checks an active account session, requires MFA to be enabled, validates CSRF, verifies a TOTP, and records failure. It does not apply the password-login throttle to MFA attempts and has no separate attempt limit, increasing delay, or challenge termination in the reviewed code.

The TOTP verifier uses six digits and accepts the current 30-second interval plus the adjacent intervals. This tolerance supports clock differences but makes request limiting especially relevant. The 177 failures show repeated verification attempts were processed; they do not prove a successful guess.

This is a high-priority authentication weakness because repeated guessing can continue after a requester reaches the password-authenticated MFA stage. Access to that stage is a prerequisite; the finding does not mean an unauthenticated requester can automatically access any account. OWASP's MFA testing guidance specifically addresses OTP brute force and rate limiting. [OWASP MFA Testing Guide](https://owasp.org/www-project-web-security-testing-guide/latest/4-Web_Application_Security_Testing/04-Authentication_Testing/11-Testing_Multi-Factor_Authentication)

Only one MFA success for account 11 appears in the October 10 snapshot, at 15:25:27. Its association with the earlier failures does not establish that brute force succeeded; that later login may have been legitimate.

### 4. Identifier validation permits misleading and probe-like registrations

The registration handler checks the identifier for presence and uniqueness but does not enforce a documented employee-ID format. Consequently, names such as admin/root and SQL-style test strings can be stored as account identifiers.

This can confuse review and account identification. It does not itself grant permissions. The reviewed insertion and user lookup methods use bound SQL parameters; the presence of SQL-like text is not proof of SQL injection. A full SQL-injection assessment across other application queries was outside scope.

### 5. Password login has throttling, with a conditional failure gap

The code tracks failed password attempts by identifier/IP, identifier across IPs, and an IP-wide bucket. Default constants are five identifier attempts, 50 IP-wide attempts, and a 900-second lockout; these are code defaults, not independently verified effective runtime values. Blocked-login audit events show that throttling operated for some observed requests.

If the throttle query fails, the check logs an error and returns false, allowing the password-login path to proceed rather than enforcing a block. This creates a conditional gap if the throttling table/query fails while user lookup remains available. It does not prove the throttle failed during the incident, and a complete database outage would also affect authentication.

Password-login limits do not protect registration, reset requests, or MFA verification. They must not be treated as coverage for all authentication-related endpoints.

### 6. Attribution and authentication audit semantics need care

The reviewed snapshot contains 5,274 audit events from 192.168.108.254 and 17 from ::1. A shared private address cannot identify the originating person. The IP helper trusts forwarded addresses only when the remote address matches configured trusted proxies; gateway topology and upstream logs were not reviewed.

The application records login_succeeded before MFA is satisfied. Analysts must look for MFA success or trusted-device acceptance before calling a password-stage success a fully authenticated login. Audit writes are best effort, so missing events cannot conclusively rule out an operation.

## Controls that limit impact

- Public registration fixes role to staff and status to inactive; the requester cannot select an administrator role in this handler.
- Inactive status is checked before establishing a login session.
- Protected routes using requireLogin refresh database account state, check session version and timeouts, and enforce MFA when enabled.
- Administrator routes reviewed require authenticated access and administrator role checks.
- Password changes increment session version, invalidating older protected sessions on their next validation.
- Password hashes use PHP password_hash/password_verify rather than plaintext storage.
- MFA setup refuses replacement through that setup route when MFA is already enabled.
- Reset tokens use secure randomness, hashed storage, expiry, and consumption.
- CSRF checks exist, and session cookies use HttpOnly/SameSite; Secure depends on detected transport.

These observations are scoped to the reviewed routes and code. They are not certification that every route or deployment control is secure.

## Claims the evidence does not support

- SYSADMIN takeover: three failed login attempts were recorded on October 10, with no successful login or password-reset event for SYSADMIN that day in the reviewed snapshot. A compromise through another mechanism has not been ruled out.
- Successful SQL injection: stored SQL-style identifiers do not establish SQL execution.
- Sensitive-file disclosure: several probes received HTTP 200 with the same 10,503-byte response, consistent with a generic routed page. Without response bodies, these statuses do not prove file disclosure.
- Successful MFA brute force: repeated failures and a later success do not establish that guessing produced the success.
- Data theft or service denial: neither was established in this review.

## Recommended priorities — assessment only

1. Protect MFA verification against sustained guessing with limits tied to the account/challenge and suitable delays or challenge termination.
2. Apply reset-request cooldowns and per-account request limits, with monitoring for repeated delivery and recovery disruption.
3. Control public registration volume and verify registration eligibility/identity before approval.
4. Enforce the organization's intended account-identifier format and review the 69 pending registrations before approval.
5. Review throttle failure behavior and reliability; verify effective deployed settings and any upstream controls.
6. Improve operator attribution using approved proxy/network records, and distinguish password-stage success from completed MFA authentication in incident analysis.
7. Confirm whether the observed testing was authorized and whether existing account 11's resets/MFA success were legitimate. Preserve incident evidence before administrative cleanup.

No attack requests, account changes, security configuration changes, or code changes were performed. Only this assessment document was created.

## Local evidence references

- Registration: app/controllers/Auth.php, register(), and app/models/User.php, register().
- Recovery: app/controllers/Auth.php, forgotPassword()/resetPassword(), and app/lib/SecurityService.php, reset-token methods.
- MFA: app/controllers/Auth.php, mfa()/mfaSetup(), and app/lib/TotpService.php, verify().
- Password throttling: app/lib/SecurityService.php, isLoginBlocked()/recordLoginFailure(), and app/config/config.php.
- Protected sessions and audit handling: helpers/auth_helper.php.
- Incident records: incident_report_69_accounts_creation_sources_2026-10-10.md and sysadmin_related_activity_report_2026-10-10.md.
