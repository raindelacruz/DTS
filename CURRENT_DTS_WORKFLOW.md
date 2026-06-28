# Current DTS Workflow

This document describes the DTS workflow as currently implemented in the codebase. It is based on the PHP controllers, models, views, helper functions, and SQL/schema files present in the project, including the current uncommitted files.

Primary sources reviewed:

- `app/controllers/Documents.php`
- `app/models/Document.php`
- `app/views/documents/*.php`
- `app/controllers/ActionSlips.php`
- `app/models/DepartmentActionSlip.php`
- `app/views/action_slips/*.php`
- `app/controllers/Auth.php`
- `app/controllers/Users.php`
- `app/models/User.php`
- `app/models/Department.php`
- `helpers/auth_helper.php`
- `dts_db.sql`
- `docs/return-reupload-workflow.sql`

## 1. Current User Roles and Permissions

The implemented roles are defined in `User::roles()` and in the `users.role` SQL enum:

| Role | Implemented permissions |
|---|---|
| `admin` | Can access user management, activate/deactivate users, and update roles. Admin can view all department action slips through `DepartmentActionSlip::canUserView()`. Admin is allowed through some action-slip creation validation branches, but the create form is only exposed to managers through `ActionSlips::canCreate()`, so admin action-slip creation is unclear in the UI/controller flow. |
| `manager` | Can create department action slips. Can perform manager-level document actions after staff receipt: manager receive, clear THRU, note CC, forward document, and internal delegation to staff when eligible. Parent-department managers can route/receive/complete action slips at department level. Division managers can receive division action slips, delegate to staff, confirm staff completion, and create action slips to staff. |
| `staff` | Default role for registration. Can create documents, edit own draft documents, release own draft documents, receive incoming documents, return eligible documents, upload corrected attachments for documents returned to their releasing department, re-release after correction, and complete internal document assignments. Staff can start and complete assigned action slips. |
| `custodian` | Listed as a role, but no distinct custodian-specific permissions were found. Because most document actions only block `manager` or require ownership/department visibility, custodian behavior generally falls through like a non-manager role where no explicit role check exists. |

Other user rules:

- Registration always creates a `staff` user with `inactive` status.
- Login is blocked unless `users.status` is `active`.
- Only `admin` can activate/deactivate accounts and change roles.
- Users can update their own department and email from profile.
- All controller modules reviewed call `requireLogin()` except authentication and public verification.

## 2. Current Document Creation Process

Documents are created through `Documents::create()` and `Documents::store()`.

1. The create form loads:
   - parent departments for THRU, TO, and CC routing;
   - child departments of the current user's department for internal `DELEGATE` routing;
   - visible documents for optional reference-document selection;
   - the next prefix generated from the current department code/year/month/sequence.
2. On submit, the controller normalizes:
   - `title`
   - `particulars`
   - `type`
   - optional `reference_document_id`
   - optional single `thru_department_id`
   - multiple `to_department_ids`
   - multiple `cc_department_ids`
   - multiple `delegate_department_ids`
3. Validation requires:
   - title;
   - type;
   - at least one TO department or internal division delegate;
   - referenced document must exist, be visible to the user's department, and not be the current document during edit;
   - THRU is removed from TO and CC if duplicated;
   - CC is removed if also selected as TO;
   - delegate targets are removed if also selected as THRU, TO, or CC;
   - THRU/TO/CC targets must be parent departments;
   - delegate targets must be child departments of the origin/current department.
4. Attachment upload is optional on creation. If present, only PDF, JPG/JPEG, PNG, GIF, and WEBP are accepted, with MIME and extension matching and a configured size limit.
5. `Document::createDocument()`:
   - reserves the next sequence number in `document_sequences`;
   - creates a document with status `Draft`;
   - stores the first TO target as `destination_department_id`, or first delegate target if no TO target exists;
   - inserts `document_routes` rows for THRU, TO, CC, and DELEGATE;
   - writes a `Created` log.

Documents can be edited only while `Draft`, only by their owning/origin department through `findOwnedDraftOrFail()`.

## 3. Current Document Routing Process

Document routing is represented by `document_routes`:

| Route type | Meaning in implementation | Target restriction at creation |
|---|---|---|
| `THRU` | A parent department that must receive/clear before TO/CC/DELEGATE recipients can receive. | Parent department only |
| `TO` | Main recipient parent department. Document status becomes `Received` only after all TO routes are received. | Parent department only |
| `CC` | Copy recipient. Manager can note CC after staff receipt and manager acknowledgment. | Parent department only |
| `DELEGATE` | Internal child division route from the origin/forwarding parent department. Also used by forwarding when target is a child department. | Child department of the current parent department |

Release flow:

1. A draft document is released by its creator/origin department.
2. `Document::releaseDocument()` changes document status from `Draft` to `Released`, sets `released_by` and `released_at`, and logs `Released`.
3. Notifications go first to THRU departments if any exist; otherwise to TO/CC/DELEGATE route departments.
4. Visibility for non-origin departments is blocked while the document is `Draft`.
5. If a THRU route exists, TO/CC/DELEGATE route visibility and receipt are gated until THRU is received/cleared.

Forwarding flow:

1. Only managers can forward.
2. The manager's department must have staff handling plus manager acknowledgment.
3. The current department must have a received TO or DELEGATE route, no child delegation already made, or match the legacy destination/status condition.
4. Forward targets must be either another parent department or a child department of the current parent department.
5. Forwarding sets the document status back to `Released`, clears document-level received fields, marks the current department's pending TO/DELEGATE routes as received, and inserts or resets routes:
   - child target under current department becomes `DELEGATE`;
   - parent target becomes `TO`.
6. Forwarding writes action-slip-style instructions into `document_routes.instructions`.

## 4. Current Receiving Process

Staff receipt:

1. Managers are explicitly blocked from `Documents::receive()`.
2. For routed documents:
   - draft documents cannot be received;
   - returned documents cannot be received;
   - non-THRU routes cannot be received until THRU is cleared;
   - the matching route is set to `Received`;
   - a `Received` log is inserted.
3. Document-level status becomes `Received` only when:
   - all TO routes are received; or
   - there are no TO routes and all DELEGATE routes are received.
4. For legacy/non-routed documents:
   - only released/re-released documents can be received;
   - current department must match `destination_department_id`;
   - current department must be a parent department;
   - document status becomes `Received`.
5. After staff receipt, department managers are notified that manager action is required.

Manager receipt:

1. Managers use `Documents::managerReceive()`.
2. It requires that someone else in the manager's department has already handled the document.
3. If not already acknowledged by this manager, it logs `Manager Received`.
4. Manager receipt does not change the document status or route status by itself.

THRU and CC manager actions:

- `clearThru()` requires manager role, a THRU route for the manager's department, and prior manager acknowledgment. It logs `Cleared THRU` and notifies TO/CC/DELEGATE departments.
- `noteCc()` requires manager role, a CC route for the manager's department, and prior manager acknowledgment. It logs `Noted CC`.
- These methods log manager actions but do not directly update `document_routes.status`; the route status has already been set to `Received` by staff receipt.

## 5. Current Delegation Process

Two different delegation concepts exist.

Document route delegation:

- `DELEGATE` route rows represent routing from a parent department to one of its child divisions.
- A document can be initially created with internal division delegates.
- A forwarded document can be delegated to a child division if the forwarding parent manager selects a child department.
- A division receives the delegated document through the same `Documents::receive()` path.

Internal assignment to staff:

- Implemented through `document_assignments`.
- Only a manager can internally delegate, and only when:
  - the manager belongs to a division department;
  - the division has a `DELEGATE` route;
  - that route is cleared/received;
  - the manager has acknowledged the document;
  - the document is not `Returned`.
- The manager selects an active staff user in the same division.
- Existing pending internal assignments for the document/division are cancelled.
- A new `INTERNAL` assignment is inserted with status `Pending`.
- A document log action `Internally Delegated` is recorded.
- Assigned staff can mark the assignment completed; this changes the assignment to `Completed` and logs `Internal Assignment Completed`.
- Completing an internal assignment does not change document status.

## 6. Current Action Slip Process

Department Action Slip is implemented separately from document routing in `ActionSlips` and `DepartmentActionSlip`.

Action slip statuses:

| Status |
|---|
| `Draft` |
| `Released` |
| `Received` |
| `Delegated` |
| `For Action` |
| `Completed` |
| `Returned` |

Creation:

1. Only managers can access action-slip creation.
2. Parent department managers can release to:
   - another parent department; or
   - a division under their own department.
3. Division managers can release only to staff under their own division.
4. Required fields include date, receiving level/target, required action, and valid deadline ordering.
5. Required action must be one of:
   - For initial/signature
   - For appropriate action
   - For meeting attendance
   - For coordination
   - For review/comments
   - For reference/filing
6. Action-slip attachments may be PDF, image files, DOC/DOCX, or XLS/XLSX.
7. The slip row is inserted with status `Released`, while events log both `Created` with new status `Draft` and the release event with new status `Released`.

Action routing:

- Department manager can receive a department-level released slip.
- Department manager can route to another parent department after status `Received` or `Returned`.
- Department manager can delegate to one child division after status `Received` or `Returned`.
- Division manager can receive a released/delegated division slip.
- Division manager can delegate to staff after status `Received` or `Returned`.
- Staff can start assigned work, moving status to `For Action`.
- Staff can complete work, moving status to `Completed`.
- Division manager can confirm a staff-completed slip; status remains `Completed`, division assignment is cleared.
- Department manager can complete department-level work; status becomes `Completed`.
- Department or division managers can return certain released/delegated slips when they are not the sender and no staff is assigned.

The action-slip `close` action exists in code and model, but `allowedActions()` always sets `close` to `false`, so it is not currently reachable through the normal UI/action gate.

## 7. Current Document Statuses and Status Transitions

Document statuses are implemented in the database/model as:

| Status | Meaning in current implementation |
|---|---|
| `Draft` | Created but not released. Editable by owning/origin department. Not visible to route recipients. |
| `Released` | Released to route recipients or forwarding targets. Receivable unless blocked by THRU or return rules. |
| `Received` | Document-level primary receipt completed. For routed documents, set only after all TO routes are received, or all DELEGATE routes if no TO exists. |
| `Returned` | Returned by receiving staff to the releasing department for attachment correction. |
| `Re-released` | Returned document corrected and released again. Receivable like `Released`. |

Document route statuses:

| Route status | Meaning |
|---|---|
| `Pending` | Route is not yet received. |
| `Received` | Route was received by staff. |
| `Returned` | Latest route was returned before receipt. |

Document return statuses:

| Return status | Meaning |
|---|---|
| `Open` | A document return is awaiting corrected attachment and re-release. |
| `Resolved` | Corrected attachment was uploaded and document was re-released. |

Document internal assignment statuses:

| Assignment status | Meaning |
|---|---|
| `Pending` | Staff assignment is active. |
| `Completed` | Assigned staff marked it complete. |
| `Cancelled` | Superseded by another internal assignment for the same document/division. |

## 8. Current Return/Re-upload Process

Return:

1. Only non-manager receiving staff can return documents.
2. Return is blocked if the department manager has already received/acknowledged the document.
3. Return is blocked if the document is already `Returned`.
4. Only documents in `Released` or `Re-released` can be returned.
5. For route-based documents, the latest route for the department must still be `Pending`.
6. Non-THRU routes can only return after THRU is cleared.
7. Required return inputs:
   - return reason;
   - return remarks/details;
   - optional attachment issue from allowed values: Incorrect attachment, Missing page, Wrong file, Unreadable file.
8. Return changes document status to `Returned`.
9. If there is a route, that route status changes to `Returned`.
10. A `document_returns` row is created with status `Open`.
11. A `Returned` document log is inserted.

Corrected upload:

1. Only non-manager staff in the releasing department from the open return can resolve it.
2. Document status must be `Returned`.
3. Replacement reason is required.
4. Corrected attachment is required and uses the document attachment validation rules.
5. `replaceReturnedAttachment()` updates the document attachment and writes `document_attachment_history`.
6. The open return remains `Open` after upload.

Re-release:

1. Only non-manager staff in the releasing department from the open return can re-release.
2. A replacement attachment record for the open return is required.
3. Document status becomes `Re-released`.
4. Document-level received fields are cleared.
5. If the return was tied to a route, that route is reset to `Pending`.
6. The return row is marked `Resolved`.
7. A `Re-released` log is inserted.

## 9. Current Restrictions or Validations

General:

- Most mutating actions require POST and CSRF validation.
- Login requires active account status.
- Safe redirects are implemented for notification redirects.

Document creation/edit:

- Title and type are required.
- At least one TO department or delegate division is required.
- THRU/TO/CC must be parent departments.
- Delegate targets must be child divisions of the origin/current department.
- A referenced document must be visible to the user's department and cannot be itself.
- Only draft documents can be edited.
- Attachments are limited to PDF/JPG/JPEG/PNG/GIF/WEBP, MIME-checked, extension-checked, and size-limited.

Document receipt/routing:

- Managers cannot perform staff receipt.
- Managers can only forward after staff receipt plus manager acknowledgment.
- Forward targets for parent departments must be another parent department or own child department.
- Non-THRU recipients cannot receive until THRU is cleared.
- Manager-only THRU/CC actions require prior manager acknowledgment.
- Internal staff delegation requires a division manager on a received DELEGATE route.

Action slips:

- Only managers can create through the exposed controller gate.
- Parent managers can release to departments or own divisions only.
- Division managers can release to own staff only.
- Required action must be in the fixed option list.
- Deadline cannot be earlier than action-slip date.
- Action-slip attachments are extension-limited and size-limited, but unlike document uploads, the action-slip upload code checks extension rather than MIME/content match.

Visibility:

- Document visibility is department-based, route/log-based, and blocked for non-origin departments while status is `Draft`.
- Manager document visibility is narrower than staff visibility: managers see origin-department documents or documents already handled by another user in their department.
- Action-slip visibility is admin-all, staff assigned/event-related, and manager/other roles by department/event involvement.

## 10. Role-Based Routing Matrix

| Workflow area | Staff | Manager, parent department | Manager, division | Admin | Custodian |
|---|---:|---:|---:|---:|---:|
| Register account | Yes, as default inactive role | N/A | N/A | N/A | N/A |
| Activate users / assign roles | No | No | No | Yes | No |
| Create document | Yes, unless role-specific UI later blocks it; no controller role restriction | Yes, no explicit controller block | Yes, no explicit controller block | Yes, no explicit controller block | Yes, no explicit controller block |
| Edit document | Own/origin draft only | Own/origin draft only | Own/origin draft only | Own/origin draft only | Own/origin draft only |
| Release document | Own/origin draft only | Own/origin draft only | Own/origin draft only | Own/origin draft only | Own/origin draft only |
| Receive document | Yes, if department/route eligible | No | No | Yes if not manager and otherwise eligible | Yes if otherwise eligible |
| Manager receive document | No | Yes, after staff handling | Yes, after staff handling | No | No |
| Clear THRU | No | Yes, with THRU route and manager acknowledgment | Yes, with THRU route and manager acknowledgment | No | No |
| Note CC | No | Yes, with CC route and manager acknowledgment | Yes, with CC route and manager acknowledgment | No | No |
| Forward document | No | Yes, if forwardable | Not unless department is parent; `requireParentDepartment()` blocks child divisions | No | No |
| Return document | Yes, if receiving staff and eligible | No | No | Possible if non-manager and route/department eligible, but not role-targeted | Possible if non-manager and route/department eligible |
| Upload corrected attachment | Yes, if in releasing department for open return | No | No | Possible if non-manager and department eligible | Possible if non-manager and department eligible |
| Re-release returned document | Yes, if in releasing department and replacement exists | No | No | Possible if non-manager and department eligible | Possible if non-manager and department eligible |
| Internal document assignment | Assigned staff can complete | Only division managers can assign after DELEGATE receipt; parent managers cannot assign internally under current gate | Yes, if DELEGATE route eligible | No | No |
| Create action slip | No | Yes | Yes, to staff only | UI/controller gate says no, despite partial validation branch | No |
| Route action slip to department | No | Yes, when eligible | No | No normal action gate | No |
| Delegate action slip to division | No | Yes, when eligible | No | No normal action gate | No |
| Delegate action slip to staff | No | No | Yes, when eligible | No normal action gate | No |
| Start/complete assigned action slip | Yes, if assigned | No | No | No normal action gate | No |

## 11. Status Transition Table

### Documents

| From | Action | To | Implemented by | Notes |
|---|---|---|---|---|
| none | Create document | `Draft` | `Document::createDocument()` | Also creates route rows and `Created` log. |
| `Draft` | Edit draft | `Draft` | `Document::updateDraftDocument()` | Only draft documents. |
| `Draft` | Release | `Released` | `Document::releaseDocument()` | Sets released fields and logs `Released`. |
| `Released` / `Re-released` | Staff receives THRU route | document remains `Released`/`Re-released`; route becomes `Received` | `Document::receiveDocument()` | THRU route receipt opens later recipients. |
| `Released` / `Re-released` | Staff receives TO route | maybe `Received` | `Document::receiveDocument()` | Document becomes `Received` when all TO routes received. |
| `Released` / `Re-released` | Staff receives DELEGATE route with no TO routes | maybe `Received` | `Document::receiveDocument()` | Document becomes `Received` when all delegate routes received and no TO route exists. |
| `Released` / `Re-released` | Staff receives CC route | document usually unchanged | `Document::receiveDocument()` | CC receipt marks route/log; document-level status is driven by TO/DELEGATE primary receipt. |
| `Released` / `Re-released` | Return before receipt | `Returned` | `Document::returnDocument()` | Route becomes `Returned` if route-based. |
| `Returned` | Upload corrected attachment | `Returned` | `Document::replaceReturnedAttachment()` | Attachment/history/log updated; return remains open. |
| `Returned` | Re-release | `Re-released` | `Document::reReleaseReturnedDocument()` | Route reset to `Pending` if applicable; return becomes `Resolved`. |
| `Received` | Forward | `Released` | `Document::forwardDocument()` | Forwarding manager route/actions required; received fields cleared. |

### Document Routes

| From | Action | To |
|---|---|---|
| none | Create/release/forward route | `Pending` |
| `Pending` | Staff receive route | `Received` |
| `Pending` | Staff return document on route | `Returned` |
| `Returned` | Re-release returned document | `Pending` |
| `Pending` TO/DELEGATE for forwarding department | Forward onward | `Received` |

### Department Action Slips

| From | Action | To |
|---|---|---|
| none | Create row | `Released` |
| event only | Created event | `Draft` in event log |
| `Released` | Department receives | `Received` |
| `Received` / `Returned` | Route to department | `Released` |
| `Received` / `Returned` | Delegate to division | `Delegated` |
| `Released` / `Delegated` | Division receives | `Received` |
| `Received` / `Returned` | Division delegates to staff | `Delegated` |
| `Released` / `Delegated` | Staff starts | `For Action` |
| `For Action` / `Received` | Staff completes | `Completed` |
| `Completed` by assigned staff | Division confirms | `Completed` |
| `Received` / `For Action` / `Returned` / `Completed` | Department completes | `Completed` |
| `Released` / `Delegated` | Manager returns | `Returned` |

## 12. Step-by-Step Workflow Per Role

### Staff

1. Register; wait for admin activation.
2. Create a document with routing and optional attachment.
3. Edit while the document is still `Draft`.
4. Release the document.
5. Receive incoming documents for the user's department, unless blocked by THRU.
6. Return eligible documents before manager acknowledgment, if attachment/details are wrong.
7. If the user's department is the releasing department for an open return, upload a corrected attachment and re-release.
8. Complete internal assignments delegated by a division manager.
9. For action slips, start assigned staff work and complete it with optional completion attachment.

### Manager, Parent Department

1. View documents that originated in the department or have already been handled by staff in the department.
2. After staff receipt, record manager receipt.
3. If the department is THRU, clear THRU.
4. If the department is CC, note CC.
5. If the department is a received TO/DELEGATE recipient and has no child delegation, forward to another parent department or own child division with action instructions.
6. Create department action slips to another parent department or own division.
7. Receive department-level action slips.
8. Route action slips to another parent department, delegate them to own divisions, complete them, or return eligible ones.

### Manager, Division

1. View documents after staff in the division has handled them or where the division is origin.
2. After division staff receipt on a DELEGATE route, record manager receipt.
3. Internally delegate the document to active staff in the same division.
4. Receive division-level action slips.
5. Delegate action slips to active staff in the same division.
6. Confirm staff-completed action slips.
7. Create action slips directly to staff in the same division.

### Admin

1. Activate/deactivate users.
2. Change user roles.
3. View all action slips.
4. Admin has no explicit document-administration workflow beyond whatever non-manager document paths allow through department/ownership checks.

### Custodian

1. Custodian is defined as a role but has no dedicated workflow.
2. Where controllers only distinguish manager/non-manager, custodian follows non-manager behavior subject to department, route, and ownership checks.
3. No custodian-specific view or action was found.

## 13. End-to-End Workflow Scenarios

### Scenario A: Basic parent department TO routing

1. Staff creates document with one or more TO parent departments.
2. Document is saved as `Draft`.
3. Staff releases it; status becomes `Released`.
4. Recipient staff receives it.
5. Route becomes `Received`.
6. If all TO routes are received, document status becomes `Received`.
7. Recipient manager records manager receipt.
8. Recipient parent manager can forward if all forwarding validations pass.

### Scenario B: THRU before TO/CC/DELEGATE

1. Staff creates document with THRU and TO/CC/DELEGATE routes.
2. Staff releases it; notification goes to THRU first.
3. THRU staff receives the route.
4. THRU manager records manager receipt.
5. THRU manager clears THRU.
6. TO/CC/DELEGATE departments are notified and can now receive.
7. TO receipt can eventually move document status to `Received`.
8. CC manager may note the CC after staff receipt and manager acknowledgment.

### Scenario C: Return and corrected re-release

1. Staff releases document.
2. Receiving staff finds an attachment issue before manager receipt.
3. Receiving staff submits return reason, attachment issue, and details.
4. Document becomes `Returned`; route becomes `Returned` if route-based; an open return is created.
5. Staff in the releasing department uploads a corrected attachment with replacement reason.
6. Staff re-releases the document.
7. Document becomes `Re-released`, open return becomes `Resolved`, and returned route resets to `Pending`.
8. Receiving staff can receive the document again.

### Scenario D: Parent-to-division document delegation and internal staff assignment

1. Parent manager forwards a received document to a child division, creating/resetting a `DELEGATE` route.
2. Division staff receives the delegated route.
3. Division manager records manager receipt.
4. Division manager delegates internally to an active staff member.
5. Assigned staff completes the internal assignment.
6. Document status is not changed by the internal assignment completion.

### Scenario E: Department action slip to division to staff

1. Parent manager creates action slip and releases it to a division.
2. Slip status is `Released`.
3. Division manager receives it; status becomes `Received`.
4. Division manager delegates it to staff; status becomes `Delegated`.
5. Staff starts work; status becomes `For Action`.
6. Staff completes work; status becomes `Completed`.
7. Division manager can confirm completion; status remains `Completed`.

### Scenario F: Department action slip routed between departments

1. Parent manager creates action slip to another parent department.
2. Receiving department manager receives it; status becomes `Received`.
3. Receiving department manager can route it to another parent department; status becomes `Released`.
4. New receiving department manager can receive and continue the same pattern.

## 14. Gaps, Inconsistencies, and Unclear Behavior

1. `custodian` is defined as a role but no custodian-specific workflow or permissions were found.
2. Document creation/release is not role-restricted in the controller; any logged-in role may reach these actions if the UI/link and ownership rules allow it.
3. Admin can view all action slips in the model, but normal action-slip creation uses `canCreate()`, which returns true only for managers. The validation method has an admin branch, but the form/action gate does not expose admin creation.
4. The dashboard counts `Pending` and `Completed` document statuses, but document statuses are `Draft`, `Released`, `Received`, `Returned`, and `Re-released`; `Pending` and `Completed` are not document statuses in the current schema.
5. `Document::updateDraftDocument()` contains duplicated `remarks` in the INSERT column list, which appears to make draft update logging SQL invalid.
6. `Document::returnDocument()` contains duplicated `return_reason` in the INSERT column list but only one placeholder/value sequence, which appears to make document return SQL invalid.
7. `ActionSlips::close()` and `DepartmentActionSlip::closeSlip()` exist, but `allowedActions()` always sets `close` to `false`, so closing is not reachable through the normal action gate.
8. Action-slip creation inserts the row as `Released` but logs a `Created` event with `new_status = Draft`; `Draft` exists as a status constant but there is no normal saved draft workflow for action slips.
9. Manager document visibility is dependent on another user in the department already logging activity, which means managers may not see incoming routed documents until staff receives/handles them.
10. `managerReceive()` only writes a log; it does not update document or route status. This is consistent with current code but may be ambiguous in the UI because it sounds like receipt.
11. THRU manager clearance also only writes a log. Actual THRU route status is set to `Received` by staff receipt.
12. Forwarding marks the current department's pending TO/DELEGATE routes as `Received` before creating/resetting next routes, but it does not create a separate final/closed document status.
13. Internal document assignment completion does not change route or document status.
14. Action-slip upload validation checks file extension and size, but not MIME/content match like document upload does.
15. Department/profile changes are self-service for all users; because document visibility and permissions use `$_SESSION['department_id']`, changing profile department can affect access after update.
16. Public QR verification exists, but QR printing/token generation is guarded by `ENABLE_QR_PRINT`; comments indicate QR printing is temporarily disabled.

