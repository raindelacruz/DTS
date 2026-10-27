# DTS Incident Report: 69 New Inactive Accounts

**Report reference:** DTS-REG-20261010-01  
**Report date:** October 10, 2026  
**System:** DTS (`dts.nfa.gov.ph`)  
**Incident category:** Suspicious bulk registration / possible registration abuse  
**Status:** Registration activity confirmed; authorization, operator identity, and intent unresolved  
**Database snapshot time:** October 10, 2026, 15:32:47, as reported by the database  
**Scope:** Read-only investigation; no remediation performed

## Executive summary

A review identified **69 new accounts** created on October 10, 2026, between **12:26:35 and 13:59:15**, a period of **1 hour, 32 minutes, 40 seconds**. Each account has a matching `account_registered` audit event. All 69 currently have role **staff** and status **inactive**.

The activity includes repeated probe-like identifiers, administrator-themed identifiers, and SQL-injection-style text. Sixty-three registration events share a short Chrome 124 client identifier and occurred within approximately 15 minutes. One registration explicitly identifies curl. This pattern is consistent with scripted testing or attempted registration abuse, but authorization and intent have not been established.

The records show **20 inactive-account login rejection events involving 14 of these accounts**. No successful login, MFA success, activation, role-change, or completed password-reset event was found targeting these 69 accounts in the available audit history. This supports that the inactive-account control rejected the observed login attempts; it is not a forensic guarantee that no access occurred through another route.

This report concerns the new registrations. The separate password-reset/MFA activity against existing account 11 and the reported SYSADMIN concern remain separate investigation matters. The evidence does not prove that these registrations were caused by a compromised SYSADMIN session.

## Confirmed scope

| Item | Verified finding |
|---|---|
| New account records | 69 |
| Matching registration events | 69, with 69 distinct target accounts |
| Account IDs | 44–75 and 77–113 |
| Current roles | All staff |
| Current statuses | All inactive |
| First creation | October 10, 12:26:35 |
| Last creation | October 10, 13:59:15 |
| Registration audit-ID span | 4957–5102; includes interleaved unrelated events |
| Recorded registration IP | `192.168.108.254` for all 69 events |
| Observed inactive-account login rejections | 20 events across 14 accounts |
| Successful login or activation audit events for this group | None found |

The account-ID range has a gap at 76. This review did not establish why that ID is absent and does not infer deletion from the gap alone.

Database session and global timezone settings both report `SYSTEM`. The underlying operating-system timezone was not independently verified. Database timestamps are reproduced as stored; Apache access-log timestamps explicitly use UTC+08:00.

## Registration sources and timing

Client identifiers are supplied by the requester and can be changed. They are not reliable proof of a device, person, or geographic source.

| Recorded client identifier | Registrations | First event | Last event |
|---|---:|---|---|
| `Mozilla/5.0 Chrome/124.0` | 63 | 12:29:37 | 12:44:55 |
| Windows Chrome 124 identifier, with AppleWebKit | 2 | 12:26:35 | 13:59:15 |
| Windows Chrome 124 identifier, without AppleWebKit | 3 | 13:21:16 | 13:33:52 |
| `curl/7.88.1` | 1 | 13:00:24 | 13:00:24 |
| **Total** | **69** | **12:26:35** | **13:59:15** |

All timestamps in this table are October 10, 2026. All registration events record the same private IP, `192.168.108.254`. The network topology was not reviewed, so this address cannot identify the operator and may represent an intermediary or shared source.

## Indicators supporting investigation

Representative account identifiers include:

| Account ID | Identifier | Observation |
|---|---|---|
| 44 | `i505probe` | Probe-like naming |
| 49 | `admin` | Administrator-themed name; actual role is staff |
| 50 | `administrator` | Administrator-themed name; actual role is staff |
| 54 | `root` | Privileged-account-themed name; actual role is staff |
| 66 | `superadmin` | Administrator-themed name; actual role is staff |
| 68 | `sysadmin'-- -` | SYSADMIN-related SQL-injection-style text |
| 70 | `admin%00` | Encoded-character probe-like text |
| 71 | `sysadmin%20` | Encoded-character probe-like text |
| 102 | `sysadmin' AND SLEEP(4)-- -` | SQL delay-test-style text |
| 103 | `' OR 1=1-- -` | SQL-injection-style text |
| 109 | `512probe` | Registration identified as curl |
| 110 | `TEST99999` | Test-like naming |

Many additional identifiers share the `i505` prefix. Their naming and registration cadence support a testing/automation hypothesis, but do not establish which tool generated them.

**SQL-like text stored in an account identifier is not proof that SQL injection executed.** The reviewed user-registration model uses bound SQL parameters. Likewise, an account named `admin`, `root`, or `superadmin` does not have administrator permissions merely because of its identifier.

## How the accounts were created

The reviewed public registration handler accepts a POST submission with a valid CSRF token, checks required fields, email syntax, password policy, confirmation, and identifier/email uniqueness, then creates an account.

The handler explicitly sets role to `staff` and status to `inactive`. It records an `account_registered` event and calls the administrator-notification routine. Administrator notifications could add review workload, but notification counts or successful delivery were not independently verified in this investigation.

The reviewed identifier validation requires a nonempty value and uniqueness; no identifier-format restriction was found in this handler. This explains why probe-like strings can be stored as account identifiers.

No registration-specific cooldown, rate limit, CAPTCHA, invitation requirement, or verified-email ownership step was found in the reviewed registration flow. CSRF validation protects the form submission context; it does not establish that a registration belongs to an authorized employee or prevent an automated client from using the normal form flow.

The records and code support creation through the application's registration flow. The registration audit actor is null, consistent with public registration. There is no evidence here that a SYSADMIN-authenticated action was required to create these records.

## Access-control observations

The login handler checks account status after credential validation and rejects inactive accounts before establishing the authenticated session. The 20 `inactive_account_login` events demonstrate that this rejection path was reached for 14 accounts in this group.

For these 69 accounts, the available audit records contain registration and inactive-account rejection events only when grouped by target account. No activation or role-change events were found, and their current statuses and roles remain inactive/staff.

The reviewed evidence does not show privilege escalation through these new accounts, successful authenticated access by them, or document/data access attributable to them.

## Impact assessment

**Confirmed impact:** 69 additional inactive account records in the database, associated audit entries, and observed unsuccessful login activity against part of the group.

**Potential operational impact:** Increased account-review workload, possible administrator-notification noise, misleading account names, and clutter in the pending-registration population.

**Not established:** Successful access by these accounts, administrator privileges, data extraction, service disruption, successful SQL injection, or a causal connection to SYSADMIN compromise. Application performance and database storage impact were not measured.

The registration pattern merits prompt administrative review. Severity should be assigned under the organization's incident process after determining whether the activity was authorized testing and correlating it with other ongoing account-access concerns.

## Recommended response — not performed

1. Preserve the account list, registration audit records, relevant Apache logs, and existing administrative change history before any cleanup.
2. Establish whether an authorized security test ran during the creation window, especially 12:29–12:45, and identify its approved scope and operator.
3. Confirm legitimate registrations individually. Do not approve probe-like or administrator-themed accounts solely from their submitted names.
4. Trace `192.168.108.254` through existing gateway or proxy records to identify the upstream source where possible.
5. Have the responsible administrator decide how to handle confirmed test or unwanted accounts after evidence preservation. No deletion or status change was performed.
6. Review registration controls as future hardening, including identifier-format rules, request limits, and identity verification appropriate to DTS. No coding or configuration implementation was performed.
7. Correlate the window with the existing account 11 password-reset/MFA investigation and the user's SYSADMIN observation without assuming a common operator.

## Evidence and limitations

Evidence reviewed:

- Database `users`: current role/status and creation timestamps for the 69 accounts.
- Database `security_audit_log`: matching registrations and related account-access/change events.
- Apache access logs: registration and broader request context reviewed during the related investigation.
- `app/controllers/Auth.php`: registration flow, fixed initial role/status, registration auditing, and inactive-account login rejection.
- `app/models/User.php`: parameterized account insertion.
- `helpers/auth_helper.php`: audit recording and source-IP handling.

Authorization and operator identity remain unknown. No email ownership verification, staff-directory verification, browser/session attribution, gateway-log correlation, or server forensic integrity assessment was performed.

Audit writes are handled on a best-effort basis; missing events do not conclusively exclude activity. Direct database changes, audit tampering, unlogged alternate paths, and deleted historical records were not ruled out. Counts and statuses describe the reviewed database snapshot.

No credentials, password hashes, MFA secrets, email addresses, or reset tokens are included in the account appendix.

## Appendix: verified account inventory

All accounts below have current role **staff** and status **inactive**.

| Database account ID | Submitted account identifier | Creation timestamp |
|---|---|---|
| 44 | `i505probe` | 2026-10-10 12:26:35 |
| 45 | `i505m6559` | 2026-10-10 12:29:37 |
| 46 | `i505a606564` | 2026-10-10 12:29:42 |
| 47 | `i505b606564` | 2026-10-10 12:29:44 |
| 48 | `i505n6665` | 2026-10-10 12:31:24 |
| 49 | `admin` | 2026-10-10 12:31:28 |
| 50 | `administrator` | 2026-10-10 12:31:47 |
| 51 | `000001` | 2026-10-10 12:31:48 |
| 52 | `0001` | 2026-10-10 12:31:49 |
| 53 | `1` | 2026-10-10 12:31:50 |
| 54 | `root` | 2026-10-10 12:31:51 |
| 55 | `nfa` | 2026-10-10 12:31:52 |
| 56 | `test` | 2026-10-10 12:31:53 |
| 57 | `user` | 2026-10-10 12:31:54 |
| 58 | `it` | 2026-10-10 12:31:56 |
| 59 | `ictsd` | 2026-10-10 12:31:57 |
| 60 | `icts` | 2026-10-10 12:31:58 |
| 61 | `doc` | 2026-10-10 12:31:59 |
| 62 | `dts` | 2026-10-10 12:32:02 |
| 63 | `records` | 2026-10-10 12:32:04 |
| 64 | `record` | 2026-10-10 12:32:05 |
| 65 | `staff` | 2026-10-10 12:32:06 |
| 66 | `superadmin` | 2026-10-10 12:32:07 |
| 67 | `admin'-- -` | 2026-10-10 12:32:27 |
| 68 | `sysadmin'-- -` | 2026-10-10 12:32:28 |
| 69 | `admin"-- -` | 2026-10-10 12:32:31 |
| 70 | `admin%00` | 2026-10-10 12:32:32 |
| 71 | `sysadmin%20` | 2026-10-10 12:32:39 |
| 72 | `xniaaz7l` | 2026-10-10 12:32:40 |
| 73 | `eunewgd6` | 2026-10-10 12:32:43 |
| 74 | `4khuldei` | 2026-10-10 12:32:44 |
| 75 | `s2lsv98o` | 2026-10-10 12:32:45 |
| 77 | `9tckbwn5` | 2026-10-10 12:32:47 |
| 78 | `psdbk2w6` | 2026-10-10 12:32:48 |
| 79 | `i505flash` | 2026-10-10 12:34:30 |
| 80 | `i505vsta6942` | 2026-10-10 12:36:03 |
| 81 | `i505vuse6942` | 2026-10-10 12:36:05 |
| 82 | `i505vacc6942` | 2026-10-10 12:36:08 |
| 83 | `i505vis_6942` | 2026-10-10 12:36:10 |
| 84 | `i505vact6942` | 2026-10-10 12:36:12 |
| 85 | `i505vver6942` | 2026-10-10 12:36:15 |
| 86 | `i505vema6942` | 2026-10-10 12:36:25 |
| 87 | `i505vapp6942` | 2026-10-10 12:36:30 |
| 88 | `i505upgba9sd` | 2026-10-10 12:37:22 |
| 89 | `i505uphtovmd` | 2026-10-10 12:37:27 |
| 90 | `i505upolsp6m` | 2026-10-10 12:37:30 |
| 91 | `i505uphlbtna` | 2026-10-10 12:37:44 |
| 92 | `i505up0m4lzx` | 2026-10-10 12:37:47 |
| 93 | `i505up6hk4hd` | 2026-10-10 12:37:57 |
| 94 | `i505up9zxrfe` | 2026-10-10 12:38:00 |
| 95 | `i505up57bj8a` | 2026-10-10 12:38:02 |
| 96 | `i505upa50y0y` | 2026-10-10 12:38:03 |
| 97 | `i505up3kyg4e` | 2026-10-10 12:38:05 |
| 98 | `i505up9vh8f5` | 2026-10-10 12:38:06 |
| 99 | `i505up5smwxk` | 2026-10-10 12:38:10 |
| 100 | `i505upcxdurh` | 2026-10-10 12:38:12 |
| 101 | `i505ups0skjm` | 2026-10-10 12:38:17 |
| 102 | `sysadmin' AND SLEEP(4)-- -` | 2026-10-10 12:39:22 |
| 103 | `' OR 1=1-- -` | 2026-10-10 12:39:24 |
| 104 | `x','y` | 2026-10-10 12:39:26 |
| 105 | `x'-- -` | 2026-10-10 12:39:27 |
| 106 | `i505probe'-- -` | 2026-10-10 12:39:30 |
| 107 | `i505lk1zm6k` | 2026-10-10 12:40:09 |
| 108 | `i505plus` | 2026-10-10 12:44:55 |
| 109 | `512probe` | 2026-10-10 13:00:24 |
| 110 | `TEST99999` | 2026-10-10 13:21:16 |
| 111 | `1001` | 2026-10-10 13:21:40 |
| 112 | `528001` | 2026-10-10 13:33:52 |
| 113 | `900731` | 2026-10-10 13:59:15 |

## Related reports

- [SYSADMIN and Related Activity Review](sysadmin_related_activity_report_2026-10-10.md)
- [Password-Reset Email Investigation Report](password_reset_investigation_report_2026-10-10.md)

## Investigation actions

Database inspection used read-only transactions and SELECT queries. Only this incident-report file was created. No application code, configuration, account status, role, password, or database record was changed, and no registration or login test was submitted.

# Subsequent disposition update — October 10, 2026

The user confirmed that all 69 original inactive accounts are not legitimate. Their eligibility review is resolved by user attestation. Retain them inactive and preserve related evidence pending a separate retention/cleanup decision. No account modification or deletion was performed. Operator attribution, other account activity and the supplemental 70th registration remain separate questions. The report below records the earlier investigation snapshot.

