# DTS Password-Reset Email Investigation Report

**Report date:** October 10, 2026  
**System:** DTS (`dts.nfa.gov.ph`)  
**Scope:** Read-only review of application code, server email configuration, Apache access logs, and database audit records.  
**Reported concern:** Repeated password-reset emails received despite the recipient not requesting them.

## Executive findings

The reviewed records confirm multiple password-reset requests for account ID **11**, associated with masked email **`ra***@nfa.gov.ph`**, on October 10, 2026. Five request events record SMTP acceptance of the outgoing email. Some requests identify their clients as `python-requests` or `curl`, indicating scripted clients. These client identifiers do not establish the operator's identity or intent.

Four password-reset completion events were also recorded for the same account that day. This merits investigation beyond repeated email delivery: the application records these events after its password-update operation.

The user has not yet confirmed the receiving address or message arrival times. Therefore, the identified account and events cannot yet be conclusively linked to the user's reported messages. The review does not establish account compromise, malicious intent, or who submitted the requests.

## Why the sender is tech.support@nfa.gov.ph

The Apache virtual host for DTS explicitly configures:

| Setting | Reviewed value |
|---|---|
| SMTP host | `smtp.gmail.com` |
| SMTP port | `587` |
| SMTP encryption | `tls` |
| SMTP account | `tech.support@nfa.gov.ph` |
| Password-reset sender | `tech.support@nfa.gov.ph` |
| Password-reset feature | Enabled |

The application uses the configured password-reset sender for both `From` and `Reply-To`. It authenticates to the configured SMTP server to send messages. The recipient comes from the email submitted to Forgot Password, after matching an active user account.

The support address explains the sending identity. It does not identify the person or process requesting the reset.

## Confirmed request timeline

The following timestamps are copied from database audit records. Apache access-log timestamps explicitly use UTC+08:00; database timezone configuration was not independently verified. Small differences between access-log request times and audit times are expected because auditing follows the delivery attempt.

| Date | Audit time | Target account | Recorded client identifier | Audit delivery result |
|---|---|---|---|---|
| October 10, 2026 | 12:51:53 | 11 | `python-requests/2.34.2` | `mail_accepted: true`, SMTP |
| October 10, 2026 | 13:23:43 | 11 | Chrome identifier | `mail_accepted: true`, SMTP |
| October 10, 2026 | 13:46:16 | 11 | `curl/7.88.1` | `mail_accepted: true`, SMTP |
| October 10, 2026 | 14:05:23 | 11 | Chrome identifier | `mail_accepted: true`, SMTP |
| October 10, 2026 | 14:16:03 | 11 | Chrome identifier | `mail_accepted: true`, SMTP |

Audit IDs for these events: **5067, 5088, 5099, 5106, 5277**.

SMTP acceptance means the SMTP server accepted the outgoing message. It does not independently prove inbox delivery or that these are the specific messages reported by the user.

Apache access logs corroborate repeated POST requests to `/auth/forgotPassword`, including scripted clients. Additional requests appear in those logs without corresponding successful delivery events in the reviewed timeline. An HTTP response alone does not prove an email was sent.

## Password-reset completion records

| Date | Audit time | Target account | Recorded client identifier | Audit ID |
|---|---|---|---|---|
| October 10, 2026 | 12:53:08 | 11 | `python-requests/2.34.2` | 5068 |
| October 10, 2026 | 13:25:49 | 11 | Chrome identifier | 5089 |
| October 10, 2026 | 13:47:27 | 11 | `curl/7.88.1` | 5100 |
| October 10, 2026 | 14:05:49 | 11 | Chrome identifier | 5107 |

In the reviewed code, a completion event follows validation of a reset token, password-policy and confirmation checks, the password-update call, and token consumption. The password-update method writes a new password hash, records the change time, and increments the account session version.

These events are evidence that the application recorded completed resets. This review did not compare historical password hashes, independently verify each database update, or establish who supplied the reset tokens. If the account belongs to the reporting user and the resets were unauthorized, the completed resets require prompt administrator investigation.

## Why repeated requests can generate repeated emails

The reviewed Forgot Password flow:

1. Accepts a POST submission with a valid CSRF token.
2. Looks up the submitted email and requires an active account.
3. Creates a new reset token and invalidates earlier unused tokens for that account.
4. Attempts to send one reset email for that submission.
5. Records the request and whether the mail transport accepted the message.

The flow does not require the requester to sign in or know the account password. CSRF validation is present, but it does not verify that the requester owns the submitted email address.

No cooldown, per-account request limit, or IP-based request throttle was found in this flow. The reviewed sending path contains no automatic retry loop. Repeated accepted submissions can therefore generate separate reset emails. The records support repeated requests as an explanation for multiple generated emails; they do not establish whether all reported messages were separate requests or mail-system duplicates.

## Source attribution and limitations

All listed request and completion events record **`192.168.108.254`**, a private network address. The relevant Apache requests show the same address. It may represent a client, gateway, or intermediary; network topology was not verified. It cannot identify the responsible person or external source by itself.

The scripted client identifiers are consistent with testing or automation. They are also client-supplied and can be changed. Authorized testing, accidental submissions, and unauthorized activity remain possibilities; none is established by this review.

Other limitations:

- The receiving email address and message arrival times remain unconfirmed.
- Email headers, message IDs, SMTP-provider delivery logs, and mailbox access logs were not reviewed.
- No reset token, credential, or full reset link is included in this report.
- The reviewed evidence is from the available local DTS server configuration, logs, and database; additional systems may hold relevant evidence.

## Separate configuration observation

The fallback sendmail configuration contains `force_sender=tech.support@nfa.go.ph`, missing the **v** in `gov.ph`. The current application SMTP path bypasses that fallback. This discrepancy does not explain the repeated SMTP request events listed above.

## Recommended follow-up — no changes performed

1. Confirm whether account ID 11 and masked email `ra***@nfa.gov.ph` belong to the reporting user. Match message arrival times against the request timeline.
2. Ask the DTS administrator whether authorized testing, automated checks, or another operator used Forgot Password at the listed times, particularly with Python and curl.
3. Preserve relevant audit records, access logs, original email headers, and SMTP delivery records to support correlation.
4. Identify what `192.168.108.254` represents and consult existing gateway or proxy logs to trace requests where possible.
5. Establish whether the four completed resets were authorized. If not, have the administrator assess account access and recovery using the organization's procedures.
6. Consider request throttling and a cooldown as a future improvement after the incident is assessed. These controls were not implemented during this review.

## Evidence references

| Evidence | Location |
|---|---|
| DTS virtual-host SMTP settings | `C:/xampp/apache/conf/extra/httpd-vhosts.conf`, lines 60–66 |
| Sender and SMTP constants | `app/config/config.php`, lines 81–87 |
| Reset email headers and delivery | `app/controllers/Auth.php`, lines 415–441 and 468–518 |
| Forgot Password request processing | `app/controllers/Auth.php`, lines 638–666 |
| Reset completion processing | `app/controllers/Auth.php`, lines 669–696 |
| Token creation and invalidation | `app/lib/SecurityService.php`, lines 205–215 |
| Account password update | `app/models/User.php`, method `updatePassword()` |
| HTTP request evidence | `C:/xampp/apache/logs/access.log` |
| Request and completion audit evidence | Database table `security_audit_log`, audit IDs listed above |
| Token-history summary | Database table `password_reset_tokens`; 22 tokens recorded for account 11 from June 27 through October 10, 2026 |
| Fallback sender discrepancy | `C:/xampp/sendmail/sendmail.ini`, line 12 |

## Investigation status

Repeated requests and SMTP acceptance are confirmed for the identified account. Completed reset events are also present. Attribution and linkage to the reporting user's messages remain unresolved pending recipient confirmation and operational evidence.

Only this report was created. No application code, configuration, account, password, or database record was changed, and no test email or reset request was sent.
