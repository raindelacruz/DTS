# DTS SYSADMIN and Related Activity Review

**Date:** October 10, 2026  
**Method:** Read-only inspection of available local database records, Apache access logs, application logs, and relevant application code. No login attempts, reset requests, test emails, or exploitation tests were performed.  
**Audit coverage:** June 23, 2026 15:54:26 through October 10, 2026 15:26:10; 5,291 audit events at the reviewed snapshot.

## Assessment

The records contain substantial activity consistent with security testing or attempted abuse, including scripted password resets, repeated MFA failures, many new inactive accounts, and requests for potentially sensitive paths. Authorization and operator identity are unknown.

The user's report that SYSADMIN is compromised remains an allegation requiring correlation with the observed symptom and time. The reviewed records do **not independently confirm a successful SYSADMIN takeover**, and they do **not rule one out**. No successful SYSADMIN login, password-reset request, password-reset completion, or audited administrator action was recorded on October 10 in the reviewed snapshot.

The strongest recent account-access evidence concerns account **11**, masked email **`ra***@nfa.gov.ph`**. The prior password-reset report captured an earlier snapshot; this review finds an additional request and completion later that afternoon.

## SYSADMIN findings

SYSADMIN is account **10**, the only current account with role `admin` in the reviewed database. Its current status is active, MFA is enabled, session version is 4, and `password_changed_at` is null. A null change timestamp is a current database value, not proof that the password has never been changed through another mechanism.

| Recorded event | Timestamp | Audit ID | Interpretation |
|---|---|---|---|
| Latest successful password login | October 7, 08:45:09 | 4763 | Recorded password-stage success |
| Corresponding MFA success | October 7, 08:45:26 | 4764 | MFA-stage success was recorded |
| Failed SYSADMIN login | October 10, 12:35:47 | 5006 | No successful authentication recorded for this attempt |
| Failed SYSADMIN login | October 10, 15:26:02 | 5290 | No successful authentication recorded for this attempt |
| Failed SYSADMIN login | October 10, 15:26:10 | 5291 | No successful authentication recorded for this attempt |

There are no SYSADMIN password-reset request/completion events in the available audit history, no retained password-reset token rows for account 10, and no audited profile-email change, password change, administrator password reset, or administrator MFA reset targeting that account.

Historical SYSADMIN activity includes 73 password-stage successful logins, 44 MFA successes, 11 trusted-device uses, 10 MFA failures, and two MFA-enable events. Counts are different event types; they should not be added or treated as complete authenticated sessions. The MFA-enable events occurred June 27 at 22:20:15 and June 28 at 10:05:49 and should be checked against authorized setup activity if those dates are relevant.

### Administrator actions requiring owner confirmation

Across the available history, SYSADMIN performed 24 audited role changes, 26 department changes, and one status change. These are recorded actions, not established unauthorized changes.

One recent sequence worth confirming:

- October 5, 08:42:41: SYSADMIN login from an iPhone client identifier, audit 4660.
- October 5, 08:42:53: MFA success, audit 4661.
- October 5, 08:43:33: account 37 changed from `staff` to `manager`, audit 4662.
- The access-change history records the reason as **“Unit head on Official Travel.”**

The iPhone identifier is not unique to this event; earlier SYSADMIN logins also identify iPhone clients. Its presence alone does not establish compromise. The administrator must confirm whether this sequence and stated reason were authorized.

## Related activity on October 10

| Finding | Confirmed scope | Significance |
|---|---|---|
| Repeated password-reset requests | Account 11: six audited requests; all six record mail acceptance through SMTP | Repeated submissions generated separate accepted delivery attempts |
| Password-reset completions | Account 11: five audited completions | More than email requests; completed reset operations were recorded |
| MFA failures | Account 11: 177 failures from 13:26:34 to 14:16:32 | Consistent with repeated attempts to pass the MFA stage; intent unknown |
| Successful MFA | Account 11: one at 15:25:27 | Authorization requires confirmation by the account owner |
| New accounts | 69 accounts created from 12:26:35 to 13:59:15 | All currently inactive staff accounts; no new administrator account found |
| Failed and blocked logins | 52 failed and five blocked audit events across submitted identifiers | Indicates broader authentication activity; not every identifier maps to an existing account |
| Sensitive-path probes | Requests for environment files, Git paths, logs, and SQL dumps | Demonstrates probing; data exposure is not proven |

Some new account identifiers contain recognizable probe markers or SQL-injection-style text, including a SYSADMIN-related `SLEEP` expression and an `OR 1=1` expression. Stored input strings are not evidence that SQL injection executed successfully. They demonstrate that probe-like values were submitted and stored as account identifiers.

The application records `login_succeeded` before completing MFA. Therefore, the four password-stage login successes for account 11 today must not all be described as full account access. Only one MFA success for that account appears in today's reviewed audit history.

### Additional account 11 activity after the earlier report

| Event | October 10 timestamp | Audit ID |
|---|---|---|
| SMTP-accepted reset request | 15:23:59 | 5285 |
| Reset completion | 15:24:41 | 5286 |
| Password-stage login success | 15:25:01 | 5287 |
| MFA success | 15:25:27 | 5288 |
| Logout | 15:25:43 | 5289 |

These events identify a Chrome 154 client. Apache logs also show dashboard HTTP 200 responses at 15:25:28 and 15:25:40 after the MFA-success event. This supports that an authenticated session proceeded to the dashboard. It does not identify the operator or establish whether this was unauthorized.

## Other accounts with reset history

The full available audit history contains password-reset events for five accounts:

| Account ID | Requests | Completions | Recorded dates |
|---|---:|---:|---|
| 11 | 23 | 7 | June 27–28, July 13, October 10 |
| 18 | 1 | 1 | July 2 |
| 19 | 2 | 2 | June 29, July 28 |
| 21 | 2 | 2 | August 28, September 15 |
| 32 | 1 | 1 | July 20 |

Some historical request events for account 11 record failed mail acceptance. Request totals are not delivered-email totals. The other four accounts have reset activity on separate dates; no comparable same-day repeated-reset pattern was found for them on October 10. Their reset events should not be classified as incidents without owner confirmation.

A separate historical scripted sequence exists on August 25 at approximately 01:48: account 40 was registered using a `curl/8.5.0` identifier, followed by an `inactive_account_login` event. Apache logs corroborate registration and login-related requests. This is another example of scripted activity, but no successful account access is established by these two audit events.

## Sensitive-path requests do not establish disclosure

October 10 requests include `.env`, `.git/HEAD`, `.git/config`, `.git/index`, `storage/logs`, and database dump paths. Some received HTTP 403. Several others received HTTP 200 with an identical 10,503-byte response.

The application routes unknown paths to the default authentication controller. The repeated response size is consistent with a generic page response, but response bodies were not captured in the available access log and were not independently verified. An HTTP 200 status alone cannot prove a secret file or database dump was served. No credential disclosure or data extraction is confirmed by this review.

## Attribution and evidence limits

Of 5,291 reviewed audit events, 5,274 record `192.168.108.254` and 17 record `::1`. The first address is private; the second is local IPv6 loopback. The prevalence of one private address prevents meaningful attribution from the audit IP alone. Network topology and upstream logs were not reviewed.

The IP helper only accepts forwarded addresses when the remote address is configured as a trusted proxy. The available evidence does not establish whether the recorded private address is a gateway, proxy, shared client, or another component.

User-agent strings are client-supplied and are not reliable personal identifiers. The pattern is consistent with testing or automated probing; it does not establish authorization or malicious intent.

Audit recording catches write errors rather than forcing the underlying operation to fail. An absent audit event is therefore not absolute proof an operation did not occur. Direct database modifications, stolen pre-existing sessions, server-level changes, audit gaps, and tampering were not ruled out. This review is not a forensic integrity verification of the server or database.

Database timestamps are reported as stored. Apache timestamps explicitly use UTC+08:00; the database timezone was not independently verified. Counts describe the reviewed snapshot and may change as the system remains active.

## Follow-up priorities — recommendations only

1. Obtain the precise SYSADMIN symptom and time: unfamiliar login, rejected credentials, changed MFA, or unauthorized administrator action. Correlate that observation with the events above.
2. Confirm whether security testing was authorized on October 10 and August 25, and identify its operator and intended scope.
3. Ask the account 11 owner whether the five reset completions and the 15:25 MFA/dashboard sequence were authorized.
4. Confirm SYSADMIN's October 5 role-change sequence and October 7 login/MFA sequence against the administrator's activity.
5. Preserve existing database audit records, access-change history, Apache logs, mail delivery evidence, and upstream network logs. Have the responsible administrator trace `192.168.108.254` through existing infrastructure records.
6. If unauthorized administrator access is substantiated, have the responsible incident-response staff assess affected sessions, credentials, MFA, accounts, and changes using organizational procedures. No containment or account changes were performed in this review.
7. Consider reset-request cooldowns and MFA attempt limits as future hardening. No implementation was performed.

## Evidence sources

- Database: `users`, `security_audit_log`, `password_reset_tokens`, `user_access_change_history`.
- HTTP evidence: `C:/xampp/apache/logs/access.log`.
- Application evidence: `app/controllers/Auth.php`, `app/lib/SecurityService.php`, `app/models/User.php`, `app/core/App.php`, `helpers/auth_helper.php`.
- Configuration evidence: Apache DTS virtual host, `app/config/config.php`, and rewrite rules.
- Prior snapshot: `password_reset_investigation_report_2026-10-10.md`.

Only this report was created. No application code, server configuration, account, password, or database record was changed.
