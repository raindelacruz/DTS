# Standalone Incident Report: 69 New Inactive DTS Accounts

> **Disposition update — October 10, 2026:** The user has confirmed that all 69 accounts in this report are not legitimate. Treat this group as confirmed illegitimate registrations based on that attestation. Retain inactive status and preserve evidence pending an account-retention or cleanup decision. No accounts were modified or deleted. This confirmation does not identify the operator, establish SYSADMIN compromise, or cover the supplemental 70th registration. Findings below describe the original reviewed snapshot; preserved evidence copies remain unchanged.

**Report reference:** DTS-REG-20261010-02  
**Prepared:** October 10, 2026  
**System:** DTS — dts.nfa.gov.ph  
**Category:** Suspicious bulk registration; possible testing or registration abuse  
**Status:** Account creation confirmed; authorization and source operator unverified  
**Scope:** The 69 account records created on October 10, 2026, and their matching registration audit events  
**Handling:** Internal investigation record

## Summary

Sixty-nine new DTS accounts were created between **12:26:35 and 13:59:15 on October 10, 2026**, within **1 hour, 32 minutes, 40 seconds**. All 69 have matching registration audit events and currently remain **inactive**, with role **staff**.

Every matching registration event records source IP **192.168.108.254**. This is a private network address. It does not establish the external originating IP or identify the person responsible; gateway or proxy records are needed for attribution.

The submitted identifiers include repeated probe-like strings, administrator-themed names, and SQL-injection-style expressions. The pattern is consistent with automated testing or attempted abuse. Whether testing was authorized has not been determined. The identifiers do not prove successful SQL injection or SYSADMIN compromise.

## Confirmed incident details

| Detail | Finding |
|---|---|
| Account records | 69 |
| Matching registration audit events | 69 for 69 distinct accounts |
| First account created | Account 44, October 10 at 12:26:35 |
| Last account created | Account 113, October 10 at 13:59:15 |
| Account IDs present | 44–75 and 77–113 |
| Initial/current role | Staff for all 69 |
| Current status | Inactive for all 69 |
| Recorded registration source IP | 192.168.108.254 for all 69 |
| Registration audit IDs | Between 4957 and 5102, with unrelated events interleaved |
| Main client group | 63 registrations identifying as Mozilla/5.0 Chrome/124.0 |
| Main group time window | 12:29:37–12:44:55 |
| Explicit curl client | One registration at 13:00:24 |
| Inactive-account login rejections | 20 recorded events involving 14 of the accounts in the preceding related review |
| Successful login or activation events | None found for this group in the reviewed audit history |

The gap at database ID 76 has not been explained. It does not by itself establish deletion.

## Time interpretation

Creation time comes from the database `users.created_at` field. Registration audit time comes from `security_audit_log.created_at`. Both are shown exactly as stored.

The database session/global timezone settings report `SYSTEM`; the underlying system timezone was not independently verified. Apache access-log timestamps explicitly use UTC+08:00. Do not treat the database times as independently verified UTC timestamps.

## Recorded client identifiers

Each inventory row refers to a client code below. These are the full user-agent strings recorded for the registration event.

| Client code | Registration count | Recorded user-agent |
|---|---:|---|
| C1 | 2 | `Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36` |
| C2 | 63 | `Mozilla/5.0 Chrome/124.0` |
| C3 | 1 | `curl/7.88.1` |
| C4 | 3 | `Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0 Safari/537.36` |

User-agent strings are supplied by clients and can be changed. A browser-like string does not establish a manual browser submission.

## Creation mechanism and observed controls

The application registration handler accepts public POST submissions with a valid CSRF token. It validates required fields, email syntax, password requirements, and identifier/email uniqueness, then explicitly creates the account as **staff/inactive** and writes the registration audit event.

The reviewed identifier check requires a nonempty unique value; no identifier-format restriction was found in that handler. This allows probe-like strings to become account identifiers. The user model inserts values using bound SQL parameters, so stored SQL-style text does not establish SQL execution.

No registration-specific rate limit, cooldown, CAPTCHA, invitation requirement, or email-ownership verification step was found in the reviewed registration flow.

The inactive-account check rejects a login before creating the authenticated session. All 69 currently remain inactive. No activation or role-change events were found for this group in the earlier related review. An identifier such as `admin` or `superadmin` does not grant administrator permissions; those accounts are staff.

## Impact and assessment

**Confirmed impact:** 69 additional inactive account records, associated audit entries, and rejected login activity against some of the accounts.

**Potential impact:** Administrative review workload, registration-notification noise, misleading account names, and clutter among pending registrations. Notification counts and performance impact were not independently measured.

**Not established:** Successful access through these accounts, privilege escalation, data extraction, service disruption, successful SQL injection, or a causal link to the reported SYSADMIN concern.

Treat this as suspicious registration activity requiring administrative verification. Final severity and incident disposition depend on whether an authorized test explains the submissions and whether other evidence establishes unauthorized access.

## Recommended follow-up — no action performed

1. Preserve this account inventory, matching audit records, Apache logs, and available upstream network logs before cleanup.
2. Confirm whether security testing was authorized during the creation window, particularly 12:29–12:45.
3. Identify what 192.168.108.254 represents and use existing gateway/proxy logs to trace the upstream source.
4. Verify each registration against the organization's staff records before approving an account.
5. Have the responsible administrator decide how to handle verified test or unwanted registrations after preserving evidence.
6. Assess future registration controls, including request limits, identifier validation, and suitable identity verification. No implementation was performed.

## Complete account inventory

Every row below has current role **staff** and status **inactive**. Names, email addresses, credentials, password hashes, and tokens are excluded. The source IP is the value recorded in the matching registration audit event, not a verified external originating address.

| Account ID | Submitted identifier | Account creation time | Recorded source IP | Audit ID | Registration audit time | Client code |
|---|---|---|---|---|---|---|
| 44 | `i505probe` | 2026-10-10 12:26:35 | 192.168.108.254 | 4957 | 2026-10-10 12:26:35 | C1 |
| 45 | `i505m6559` | 2026-10-10 12:29:37 | 192.168.108.254 | 4967 | 2026-10-10 12:29:37 | C2 |
| 46 | `i505a606564` | 2026-10-10 12:29:42 | 192.168.108.254 | 4968 | 2026-10-10 12:29:42 | C2 |
| 47 | `i505b606564` | 2026-10-10 12:29:44 | 192.168.108.254 | 4970 | 2026-10-10 12:29:44 | C2 |
| 48 | `i505n6665` | 2026-10-10 12:31:24 | 192.168.108.254 | 4973 | 2026-10-10 12:31:24 | C2 |
| 49 | `admin` | 2026-10-10 12:31:28 | 192.168.108.254 | 4975 | 2026-10-10 12:31:28 | C2 |
| 50 | `administrator` | 2026-10-10 12:31:47 | 192.168.108.254 | 4976 | 2026-10-10 12:31:47 | C2 |
| 51 | `000001` | 2026-10-10 12:31:48 | 192.168.108.254 | 4977 | 2026-10-10 12:31:48 | C2 |
| 52 | `0001` | 2026-10-10 12:31:49 | 192.168.108.254 | 4978 | 2026-10-10 12:31:49 | C2 |
| 53 | `1` | 2026-10-10 12:31:50 | 192.168.108.254 | 4979 | 2026-10-10 12:31:50 | C2 |
| 54 | `root` | 2026-10-10 12:31:51 | 192.168.108.254 | 4980 | 2026-10-10 12:31:51 | C2 |
| 55 | `nfa` | 2026-10-10 12:31:52 | 192.168.108.254 | 4981 | 2026-10-10 12:31:52 | C2 |
| 56 | `test` | 2026-10-10 12:31:53 | 192.168.108.254 | 4982 | 2026-10-10 12:31:53 | C2 |
| 57 | `user` | 2026-10-10 12:31:54 | 192.168.108.254 | 4983 | 2026-10-10 12:31:54 | C2 |
| 58 | `it` | 2026-10-10 12:31:56 | 192.168.108.254 | 4984 | 2026-10-10 12:31:56 | C2 |
| 59 | `ictsd` | 2026-10-10 12:31:57 | 192.168.108.254 | 4985 | 2026-10-10 12:31:57 | C2 |
| 60 | `icts` | 2026-10-10 12:31:58 | 192.168.108.254 | 4986 | 2026-10-10 12:31:58 | C2 |
| 61 | `doc` | 2026-10-10 12:31:59 | 192.168.108.254 | 4987 | 2026-10-10 12:31:59 | C2 |
| 62 | `dts` | 2026-10-10 12:32:02 | 192.168.108.254 | 4988 | 2026-10-10 12:32:02 | C2 |
| 63 | `records` | 2026-10-10 12:32:04 | 192.168.108.254 | 4989 | 2026-10-10 12:32:04 | C2 |
| 64 | `record` | 2026-10-10 12:32:05 | 192.168.108.254 | 4990 | 2026-10-10 12:32:05 | C2 |
| 65 | `staff` | 2026-10-10 12:32:06 | 192.168.108.254 | 4991 | 2026-10-10 12:32:06 | C2 |
| 66 | `superadmin` | 2026-10-10 12:32:07 | 192.168.108.254 | 4992 | 2026-10-10 12:32:07 | C2 |
| 67 | `admin'-- -` | 2026-10-10 12:32:27 | 192.168.108.254 | 4993 | 2026-10-10 12:32:27 | C2 |
| 68 | `sysadmin'-- -` | 2026-10-10 12:32:28 | 192.168.108.254 | 4994 | 2026-10-10 12:32:28 | C2 |
| 69 | `admin"-- -` | 2026-10-10 12:32:31 | 192.168.108.254 | 4995 | 2026-10-10 12:32:31 | C2 |
| 70 | `admin%00` | 2026-10-10 12:32:32 | 192.168.108.254 | 4996 | 2026-10-10 12:32:32 | C2 |
| 71 | `sysadmin%20` | 2026-10-10 12:32:39 | 192.168.108.254 | 4997 | 2026-10-10 12:32:39 | C2 |
| 72 | `xniaaz7l` | 2026-10-10 12:32:40 | 192.168.108.254 | 4998 | 2026-10-10 12:32:40 | C2 |
| 73 | `eunewgd6` | 2026-10-10 12:32:43 | 192.168.108.254 | 4999 | 2026-10-10 12:32:43 | C2 |
| 74 | `4khuldei` | 2026-10-10 12:32:44 | 192.168.108.254 | 5000 | 2026-10-10 12:32:44 | C2 |
| 75 | `s2lsv98o` | 2026-10-10 12:32:45 | 192.168.108.254 | 5001 | 2026-10-10 12:32:45 | C2 |
| 77 | `9tckbwn5` | 2026-10-10 12:32:47 | 192.168.108.254 | 5002 | 2026-10-10 12:32:47 | C2 |
| 78 | `psdbk2w6` | 2026-10-10 12:32:48 | 192.168.108.254 | 5003 | 2026-10-10 12:32:48 | C2 |
| 79 | `i505flash` | 2026-10-10 12:34:30 | 192.168.108.254 | 5004 | 2026-10-10 12:34:30 | C2 |
| 80 | `i505vsta6942` | 2026-10-10 12:36:03 | 192.168.108.254 | 5009 | 2026-10-10 12:36:03 | C2 |
| 81 | `i505vuse6942` | 2026-10-10 12:36:05 | 192.168.108.254 | 5011 | 2026-10-10 12:36:05 | C2 |
| 82 | `i505vacc6942` | 2026-10-10 12:36:08 | 192.168.108.254 | 5013 | 2026-10-10 12:36:08 | C2 |
| 83 | `i505vis_6942` | 2026-10-10 12:36:10 | 192.168.108.254 | 5015 | 2026-10-10 12:36:10 | C2 |
| 84 | `i505vact6942` | 2026-10-10 12:36:12 | 192.168.108.254 | 5017 | 2026-10-10 12:36:12 | C2 |
| 85 | `i505vver6942` | 2026-10-10 12:36:15 | 192.168.108.254 | 5019 | 2026-10-10 12:36:15 | C2 |
| 86 | `i505vema6942` | 2026-10-10 12:36:25 | 192.168.108.254 | 5022 | 2026-10-10 12:36:25 | C2 |
| 87 | `i505vapp6942` | 2026-10-10 12:36:30 | 192.168.108.254 | 5025 | 2026-10-10 12:36:30 | C2 |
| 88 | `i505upgba9sd` | 2026-10-10 12:37:22 | 192.168.108.254 | 5028 | 2026-10-10 12:37:22 | C2 |
| 89 | `i505uphtovmd` | 2026-10-10 12:37:27 | 192.168.108.254 | 5029 | 2026-10-10 12:37:27 | C2 |
| 90 | `i505upolsp6m` | 2026-10-10 12:37:30 | 192.168.108.254 | 5030 | 2026-10-10 12:37:30 | C2 |
| 91 | `i505uphlbtna` | 2026-10-10 12:37:44 | 192.168.108.254 | 5031 | 2026-10-10 12:37:44 | C2 |
| 92 | `i505up0m4lzx` | 2026-10-10 12:37:47 | 192.168.108.254 | 5032 | 2026-10-10 12:37:47 | C2 |
| 93 | `i505up6hk4hd` | 2026-10-10 12:37:57 | 192.168.108.254 | 5033 | 2026-10-10 12:37:57 | C2 |
| 94 | `i505up9zxrfe` | 2026-10-10 12:38:00 | 192.168.108.254 | 5034 | 2026-10-10 12:38:00 | C2 |
| 95 | `i505up57bj8a` | 2026-10-10 12:38:02 | 192.168.108.254 | 5035 | 2026-10-10 12:38:02 | C2 |
| 96 | `i505upa50y0y` | 2026-10-10 12:38:03 | 192.168.108.254 | 5036 | 2026-10-10 12:38:03 | C2 |
| 97 | `i505up3kyg4e` | 2026-10-10 12:38:05 | 192.168.108.254 | 5037 | 2026-10-10 12:38:05 | C2 |
| 98 | `i505up9vh8f5` | 2026-10-10 12:38:06 | 192.168.108.254 | 5038 | 2026-10-10 12:38:06 | C2 |
| 99 | `i505up5smwxk` | 2026-10-10 12:38:10 | 192.168.108.254 | 5039 | 2026-10-10 12:38:10 | C2 |
| 100 | `i505upcxdurh` | 2026-10-10 12:38:12 | 192.168.108.254 | 5040 | 2026-10-10 12:38:12 | C2 |
| 101 | `i505ups0skjm` | 2026-10-10 12:38:17 | 192.168.108.254 | 5041 | 2026-10-10 12:38:17 | C2 |
| 102 | `sysadmin' AND SLEEP(4)-- -` | 2026-10-10 12:39:22 | 192.168.108.254 | 5052 | 2026-10-10 12:39:22 | C2 |
| 103 | `' OR 1=1-- -` | 2026-10-10 12:39:24 | 192.168.108.254 | 5053 | 2026-10-10 12:39:24 | C2 |
| 104 | `x','y` | 2026-10-10 12:39:26 | 192.168.108.254 | 5054 | 2026-10-10 12:39:26 | C2 |
| 105 | `x'-- -` | 2026-10-10 12:39:27 | 192.168.108.254 | 5055 | 2026-10-10 12:39:27 | C2 |
| 106 | `i505probe'-- -` | 2026-10-10 12:39:30 | 192.168.108.254 | 5056 | 2026-10-10 12:39:30 | C2 |
| 107 | `i505lk1zm6k` | 2026-10-10 12:40:09 | 192.168.108.254 | 5057 | 2026-10-10 12:40:09 | C2 |
| 108 | `i505plus` | 2026-10-10 12:44:55 | 192.168.108.254 | 5066 | 2026-10-10 12:44:55 | C2 |
| 109 | `512probe` | 2026-10-10 13:00:24 | 192.168.108.254 | 5082 | 2026-10-10 13:00:24 | C3 |
| 110 | `TEST99999` | 2026-10-10 13:21:16 | 192.168.108.254 | 5086 | 2026-10-10 13:21:16 | C4 |
| 111 | `1001` | 2026-10-10 13:21:40 | 192.168.108.254 | 5087 | 2026-10-10 13:21:40 | C4 |
| 112 | `528001` | 2026-10-10 13:33:52 | 192.168.108.254 | 5097 | 2026-10-10 13:33:52 | C4 |
| 113 | `900731` | 2026-10-10 13:59:15 | 192.168.108.254 | 5102 | 2026-10-10 13:59:15 | C1 |

## Evidence sources and limitations

The inventory was generated by a read-only join of `users` and `security_audit_log`, matching `account_registered` events by target account ID. The result contains exactly 69 rows and 69 distinct accounts.

Supporting evidence reviewed during this investigation includes:

- `app/controllers/Auth.php`: registration defaults, validation, audit events, and inactive-account rejection.
- `app/models/User.php`: account insertion using bound parameters.
- `helpers/auth_helper.php`: source-IP handling and best-effort audit recording.
- `C:/xampp/apache/logs/access.log`: registration/request context.
- Available account-access and administrative-change audit history.

The review did not establish authorization, identify the operator, capture request payloads, correlate upstream network records, or verify server/database forensic integrity. Missing audit events cannot conclusively exclude direct database changes, unlogged activity, or tampering. Counts and current account states describe the reviewed snapshot.

## Work performed

Only this standalone report was created. No code, server configuration, account, password, status, role, or database record was changed. No registration, login, or reset test was submitted.

