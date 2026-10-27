# DTS Phase 0 Incident Status and Handoff

**Date:** October 10, 2026  
**Status:** Evidence preservation completed; human verification and incident-lead assignment pending  
**Scope:** Phase 0 of phased_security_implementation_plan.md  
**Collection identity:** DTS\Administrator  
**Custody:** Evidence currently resides on the reviewed DTS host. An independent evidence custodian has not been assigned.

## Completed work

- Preserved byte-for-byte live snapshots of Apache access, error, and SSL-request logs, plus the application log. Each copied log matched the captured source prefix by SHA-256 verification. The server was not stopped.
- Exported database records using a repeatable-read consistent snapshot in a read-only transaction, captured at **October 10, 2026, 16:01:23 UTC+08:00**. Database metadata reports system timezone **Asia/Taipei**, with UTC time 08:01:23.
- Preserved user identity/status records, the full available security audit history, access-change history, reset-token lifecycle metadata, login-attempt metadata, trusted-device metadata, selected schema, and registration inventory.
- Excluded password hashes, MFA secrets, reset-token hashes, trusted-device tokens, and session cookies from database exports.
- Preserved 16 reviewed source/report files with matching SHA-256 hashes. Recorded hashes of five configuration sources and preserved selected non-secret settings without exporting credentials or application keys.
- Created an individual account-review register with 70 rows: the original 69 accounts and one supplemental registration. Every row is marked pending authoritative roster verification; no eligibility or authorization is inferred from its identifier.
- Restricted the evidence directory to the collecting Windows administrator, local Administrators, and SYSTEM. Confirmed the directory is covered by the existing Git ignore rule and sits outside the configured public document root. Existing root rules also block storage paths.

## Evidence location

Restricted directory:

`C:/xampp/htdocs/DTS/storage/logs/incident_phase0_20261010_1554/`

| Artifact | Purpose |
|---|---|
| apache_access.log / apache_error.log / apache_ssl_request.log / application.log | Captured log evidence |
| log_capture_records.json | Source paths, UTC collection times, byte counts, hash comparisons, and live-copy method |
| database_capture_metadata.json | Database snapshot time, timezone, capture method, and row counts |
| users_identity_status.json | Account ownership/eligibility review; restricted personal information |
| security_audit_log.json | 5,294 available audit events at snapshot |
| access_change_history.json | 34 access-change history rows |
| password_reset_token_metadata.json | 29 token lifecycle records; no token values/hashes |
| login_attempt_metadata.json | 130 attempt-counter records |
| trusted_device_metadata.json | 177 device lifecycle records; no device tokens |
| registration_inventory.json | 70 October 10 registrations with matching audit source information |
| account_review_register.csv | Individual owner/roster/authorization review and decision fields |
| selected_schema.json | Structure of relevant database tables |
| reviewed_sources/ | Preserved application sources and prior reports |
| source_capture_records.json / configuration_source_hashes.json | Source provenance and integrity information |
| selected_apache_settings.txt / selected_mail_settings.txt / selected_application_defaults.txt | Selected non-secret configuration evidence |
| collection_context.json / evidence_permissions.txt / workspace_status_at_capture.txt | Collection context, restricted permissions, and existing workspace state |
| manifest.json / manifest.sha256 | Artifact SHA-256 inventory and separate manifest digest, generated after collection |
| verification_summary.json | Final verification results, generated before manifest |

The directory name records the start of collection, not the database snapshot time. Raw logs and identity records are sensitive internal evidence. The collector's copy is not an independent, immutable forensic repository.

## Updated incident facts

### Original 69-account group

All **69 original accounts remain present, inactive, and staff**. No account was approved, disabled, deleted, or otherwise changed by this work. Their registration window remains October 10, 12:26:35–13:59:15.

**Subsequent user confirmation (October 10, 2026):** The user confirmed that all 69 original accounts are not legitimate. Their eligibility disposition is now **confirmed illegitimate by user attestation; retain inactive pending evidence-preserving disposition**. This resolves the eligibility question for this original group; it does not identify the operator or establish administrator compromise. No account records were changed. The preserved review register remains unchanged as historical evidence of the collection-time status. The supplemental 70th account is outside this confirmation.

### Supplemental registration

| Detail | Value |
|---|---|
| Account ID | 114 |
| Submitted identifier | Z07a0dfdb |
| Creation/audit time | October 10, 15:52:16, UTC+08:00 |
| Registration audit ID | 5294 |
| Recorded source IP | 192.168.108.254 |
| Client identifier | curl/7.88.1 |
| Current state | Inactive / staff |

The current day's total is **70**. Earlier reports remain preserved as historical snapshots of the original 69-account group. The supplemental registration's operator and authorization are unknown.

### SYSADMIN — material update to earlier snapshots

The fresh audit history contains:

| Event | Time, October 10 | Audit ID |
|---|---|---|
| Password-stage login success for SYSADMIN / account 10 | 15:29:28 | 5292 |
| Trusted-device MFA acceptance for account 10 | 15:29:28 | 5293 |

Both record source IP 192.168.108.254 and a Windows Chrome 154 client identifier. Trusted-device acceptance is distinct from entering a fresh MFA code. These records are later than the cutoff of the earlier SYSADMIN report; its statement about no successful login that day must not be extended past its original snapshot.

SYSADMIN is currently active/admin with MFA enabled, session version 4, last login 15:29:28, and a null password-change timestamp. No record above establishes that the login was unauthorized. Owner confirmation is required. No SYSADMIN password reset or MFA reset was performed during this work.

**Subsequent user confirmation:** The user confirmed that SYSADMIN's October 10, 15:29:28 login was authorized. Audits 5292 and 5293 are therefore treated as authorized activity. This confirmation does not resolve the other account/reset/testing questions. The finalized Phase 0 evidence package was not modified.

### Existing account 11

The reviewed October 10 counts remain six reset requests, five reset completions, 177 MFA failures, and one MFA success at 15:25:27. Whether these actions were authorized remains unresolved. SMTP-provider records and owner confirmation are needed to link request events to the reported messages conclusively.

## Decisions and unresolved work

| Item | Current position | Required input / responsible role |
|---|---|---|
| Incident lead | Not assigned; do not treat the collection identity as a designated incident lead | User/organization must name the accountable lead |
| Evidence custodian and retention | Local restricted collection available; independent custody/retention unconfirmed | Incident lead / evidence custodian |
| October 10 testing authorization | Unknown | Test sponsor and operator; approved scope and schedule |
| August 25 scripted activity authorization | Unknown | Test sponsor and operator |
| Original 69-account eligibility | Individually pending; keep existing inactive status | Account approver with authoritative staff records |
| Account 114 eligibility/authorization | Pending; keep existing inactive status | Account approver and test operator |
| Account 11 resets and MFA success | Owner confirmation pending | Account owner and incident lead |
| SYSADMIN 15:29 login authorization | Confirmed authorized by the user | Confirmation recorded above |
| Other SYSADMIN symptoms or disputed activity | Unspecified; no takeover conclusion established | SYSADMIN owner and incident lead |
| Containment/recovery | No recovery action initiated; unauthorized activity not yet established | Incident lead / responsible administrator following verification |
| Source attribution | Private address cannot identify operator | Network administrator with gateway/proxy logs |

Questions requesting these inputs have been presented to the user. No messages were sent to other people or external services.

## Evidence gaps and collection limits

- No sendmail log files were found in the reviewed sendmail directory. Provider-side SMTP delivery logs, mailbox access logs, and original email headers are not available in the workspace.
- No upstream gateway/proxy logs or authoritative employee roster were available for correlation.
- Live file copies capture each file at its collection time. Log snapshots and database snapshots are not simultaneous; later appended log bytes are not included.
- SHA-256 establishes consistency of the captured artifacts, not independent authenticity of historical records or absence of host compromise.
- No forensic disk image, memory capture, deleted-record recovery, or external chain-of-custody witness was obtained.
- Windows Administrators and SYSTEM retain access; evidence is not immutable. An independent custodian should retain a separately verified copy under organizational procedures.
- Existing workspace modifications were recorded and left untouched.

## Completion boundary

**Final integrity result:** All 13 checks passed. The manifest inventories 42 artifacts totaling 82,482,338 bytes, excluding the manifest and its separate digest. The review register contains 69 original accounts plus one supplemental account. All preserved log copies and source copies match their recorded SHA-256 values.

**Manifest SHA-256:** `0BB9C7E5D57DDD3F77C715A56F6F0880A5108556271A1962433E071CF0EC81A9`.

The finalized collection contains the handoff document as it stood before final verification; this current status document records the subsequent successful result. The manifest and captured files were not altered after finalization. The collection remains writable by its permitted administrators and is not immutable.

The technical preservation and review-preparation portion of Phase 0 is complete, with the final manifest and verification checks passed. **Phase 0 as a whole remains open** until the incident lead is named and account-owner, testing-authorization, and roster-review decisions are documented.

No application code, server configuration, account, password, MFA setting, or database record was changed. The only security permission change applies to the newly created evidence directory. No attack simulations, registrations, reset emails, login attempts, or service restarts were performed.
