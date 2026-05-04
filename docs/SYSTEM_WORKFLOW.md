# Document Tracking System - System Workflow

## 1. Overview

The Document Tracking System is a PHP MVC web application used to encode, route, release, receive, return, correct, re-release, and monitor office documents. It records document metadata, routing destinations, attachments, receiving activity, forwarding instructions, return reasons, attachment replacement history, notifications, and timeline logs.

The current implementation centers on department-based document movement. A document is created by one office, released to one or more routed offices, received by staff, acknowledged or acted on by managers when required, and tracked through the document details page.

Primary implementation references:

- `app/controllers/Documents.php`
- `app/models/Document.php`
- `app/views/documents/view.php`
- `app/views/documents/_form.php`
- `app/controllers/Auth.php`
- `app/controllers/Users.php`
- `app/models/User.php`
- `app/models/Notification.php`
- `dts_db.sql`

## 2. User Roles and Responsibilities

| Role | Responsibilities and Allowed Actions |
|---|---|
| Staff | Register and log in after activation, create documents, edit own department drafts, upload initial attachments, release own department drafts, receive routed documents, return released documents before manager action, upload corrected attachments for returned documents when their department is the releasing office, re-release corrected returned documents, view allowed documents and attachments, update own profile department and email. |
| Manager | View documents allowed to the manager's department, perform manager receipt after staff receipt, clear THRU routes, note CC routes, forward eligible documents for action, view routing and timeline. Managers cannot perform the first staff receipt and cannot return documents. |
| Administrator | Access user management, activate and deactivate users, update user roles, receive notifications for new registrations, and use normal authenticated screens. Administrator cannot deactivate their own account. |
| Custodian | Defined in the `users.role` enum and `User::roles()`, but no distinct custodian-specific workflow was found in the current controllers or views. |

Important role controls:

- All document controller actions require login.
- User management requires the `admin` role.
- Forwarding requires the `manager` role and a parent department.
- Initial receiving and return actions are blocked for managers.
- Role changes are available in the current User Management screen.
- New registrations are saved as `staff` and `inactive`.

## 3. Main System Modules

| Module | Description |
|---|---|
| Authentication | Login, logout, registration, CSRF validation, inactive account blocking. |
| Dashboard | Shows total, pending, completed, and department document counts. Current count labels do not fully align with active document statuses. |
| Document Encoding | Create and edit draft documents with title, particulars, type, optional reference document, THRU, TO, CC, and attachment. |
| Document Lists | Documents, Incoming, and Outgoing list pages with keyword, status, type, and date filters. |
| Document Details | Main action page for release, receive, return, corrected attachment upload, re-release, manager receive, THRU clearance, CC notation, forwarding, routing display, attachment access, return notice, attachment history, and timeline. |
| Routing | Stores document movement in `document_routes` using `THRU`, `TO`, `CC`, and `DELEGATE`. |
| Return and Re-upload | Records returns in `document_returns` and replacement files in `document_attachment_history`. |
| Attachments | Uploads PDF and image attachments, previews attachments, streams source files, supports print-ready PDF view when QR print is enabled. |
| Notifications | Sends in-app notifications to admins, department staff, managers, releasing offices, and returned offices depending on workflow events. |
| User Management | Admin-only activation, deactivation, and role assignment. |
| Profile | Lets users update their own department and email address. |
| Verification | Supports QR/token verification path through `/document/verify/{token}`. QR printing is currently guarded by `ENABLE_QR_PRINT`. |

## 4. End-to-End Workflow

1. A user registers or logs in.
2. If newly registered, the user remains inactive until an administrator activates the account.
3. An active staff user creates a document from the Create Document form.
4. The system validates required fields, routing rules, optional reference document access, and attachment rules.
5. The document is saved as `Draft`, with a generated prefix and `Created` timeline log.
6. The originating department may edit the draft while it remains `Draft`.
7. A non-manager user from the originating department releases the draft.
8. The document status becomes `Released`, a `Released` timeline log is added, and notifications are sent to initial recipients.
9. If a THRU route exists, THRU staff receive first. TO, CC, and DELEGATE recipients wait until THRU is cleared.
10. Receiving staff can either receive the document or return it for attachment issues before manager action.
11. If received, the route is marked `Received`, the timeline records `Received`, managers in that department are notified, and the creator is notified.
12. A manager in the receiving department may perform `Manager Received` after staff receipt.
13. A THRU manager may then perform `Clear THRU`; this makes TO, CC, and DELEGATE recipients eligible.
14. A CC manager may perform `Note CC`.
15. An eligible parent-department manager may forward a received TO or DELEGATE document for action. Forwarding creates or resets routes and stores action-slip instructions.
16. If receiving staff return the document, the document becomes `Returned`, the current route becomes `Returned`, an open return record is created, and the releasing department is notified.
17. The releasing department uploads a corrected attachment with a replacement reason.
18. After a replacement exists, the releasing department re-releases the document.
19. The document becomes `Re-released`, the open return is resolved, the returned route is reset to `Pending`, and the receiving office is notified.
20. The receiving office can receive the corrected document and continue the normal workflow.

## 5. Detailed Workflow by Process

### 5.1 Account Registration and Activation

1. User submits ID number, first name, last name, email, department, password, and confirmation.
2. System validates required fields, email format, unique ID number, unique email, password length, and password confirmation.
3. Account is created with role `staff` and status `inactive`.
4. Active administrators receive a notification.
5. Administrator activates the account from User Management.
6. Activated user can log in.

Exceptions:

- Inactive users cannot log in.
- Invalid credentials are rejected.
- Administrators cannot deactivate their own account.

### 5.2 Document Creation and Encoding

The user opens Create Document and enters:

- Title
- Type
- Particulars
- Optional document reference
- Optional THRU department
- One or more TO departments, unless routing only to an own child division
- Optional CC departments
- Optional own child division for internal routing
- Optional attachment

System behavior:

- Prefix is generated from department code, year, month, and sequence.
- Document status is saved as `Draft`.
- Routes are inserted into `document_routes`.
- Parent department recipients are stored as `THRU`, `TO`, or `CC`.
- The originating department's own child division recipients are stored as `DELEGATE`.
- A `Created` entry is inserted into `document_logs`.

Creation controls:

- Title is required.
- Type is required.
- At least one TO department or one valid own child division is required.
- THRU, TO, and CC routing departments at creation must be parent departments.
- Internal division routing is limited to direct child divisions of the originating department.
- THRU department is removed from TO and CC if duplicated.
- CC departments duplicated in TO are removed from CC.
- Internal child division selections duplicated in THRU, TO, or CC are removed from the internal route list.
- Optional reference document must exist and be visible to the creator's department.
- A document cannot reference itself during draft editing.

### 5.2.1 Origin Department Internal Division Routing

An originating parent department may release a document directly to its own child division during initial document creation and draft editing.

Allowed creation targets:

- Parent departments through the existing THRU, TO, and CC fields.
- The originating department's own child division through the Own Division field.

Disallowed creation targets:

- Child divisions of other parent departments.
- Unrelated offices.
- Missing or invalid department records.

Internal child-division routes use the existing `DELEGATE` route type. This keeps the initial release workflow aligned with the existing forwarding workflow, where a parent department manager delegates a routed document to the parent's own child division.

Release and receiving behavior:

- If the document has a THRU route, THRU recipients are notified first.
- After THRU clearance, TO, CC, and DELEGATE recipients become available.
- If the document has no THRU route, TO, CC, and DELEGATE recipients are notified on release.
- Child division staff receive the document through the normal routed receiving workflow.
- The originating department sees the document as the origin office.
- The child division sees the document through its DELEGATE route.

### 5.3 Draft Editing

Only the originating department can edit a document while it is `Draft`.

Editable fields:

- Title
- Particulars
- Type
- Document reference
- THRU, TO, and CC routing
- Attachment replacement

System behavior:

- Existing THRU, TO, and CC routes are replaced.
- Existing DELEGATE routes created during draft routing are replaced.
- Existing attachment is kept if no new file is uploaded.
- A `Draft Updated` log is added.

Restriction:

- Released, received, returned, and re-released documents cannot be edited through the draft edit workflow.

### 5.4 Attachment Upload and Viewing

Initial attachments and corrected attachments use the same upload validation.

Allowed file types:

- PDF
- JPG or JPEG
- PNG
- GIF
- WEBP

Attachment controls:

- Maximum size is 100 MB.
- File MIME type must be allowed.
- File extension must match detected MIME type.
- Uploaded files are renamed to a random hexadecimal filename.
- Attachments are stored under the configured upload root.
- Attachment viewing requires document-view authorization.
- Source files are streamed inline with `X-Content-Type-Options: nosniff`.

### 5.5 Document Release and Routing

Release is allowed when:

- The user is not a manager.
- The document is `Draft`.
- The user's department is the document origin department.

System behavior:

- Document status becomes `Released`.
- `released_by` and `released_at` are set.
- A `Released` timeline log is added.
- If THRU routes exist, THRU recipients are notified first.
- If no THRU route exists, TO, CC, and DELEGATE route recipients are notified, including own child divisions selected during creation.

### 5.6 Receiving and Validation by Receiving Office

Staff receiving is allowed when:

- The user is not a manager.
- The document is `Released` or `Re-released`.
- The user's department has a pending route, or the document is a legacy direct destination for the user's parent department.
- If the route is not THRU, all THRU routing must already be cleared.

System behavior:

- The current route is marked `Received`.
- `received_at` is set on the route.
- A `Received` log is added.
- If all TO routes are received, the document status becomes `Received`.
- Department managers are notified that manager action is required.
- The document creator is notified, unless the creator is the receiving user.

Receiving restrictions:

- Draft documents cannot be received.
- Returned documents cannot be received until corrected and re-released.
- Managers cannot perform staff receive.
- TO, CC, and DELEGATE recipients cannot receive before THRU clearance.
- Legacy direct receipt requires a parent department.

### 5.7 Manager Receipt, THRU Clearance, and CC Notation

Manager receipt is allowed when:

- The user is a manager.
- Another user from the same department has already handled the document.
- The manager has not already acknowledged the document.

THRU clearance is allowed when:

- The user is a manager.
- The manager's department has a THRU route.
- Manager acknowledgement already exists.
- The manager has not already cleared THRU.

CC notation is allowed when:

- The user is a manager.
- The manager's department has a CC route.
- Manager acknowledgement already exists.
- The manager has not already noted CC.

System behavior:

- Manager receipt logs `Manager Received`.
- THRU clearance logs `Cleared THRU` and notifies next TO, CC, and DELEGATE departments.
- CC notation logs `Noted CC`.
- The document creator is notified for manager receipt, THRU clearance, and CC notation.

### 5.8 Forwarding for Action

Forwarding is allowed when:

- The user is a manager.
- The user belongs to a parent department.
- Staff receipt and manager acknowledgement already occurred.
- The current route is TO or DELEGATE and already received, or the document is a legacy direct document marked `Received`.
- The current department has not already delegated to a child department for the document.

Forward form fields:

- One or more target departments
- Urgent flag
- Action type
- Deadline date
- Instruction

Allowed action types:

- For initial/signature
- For meeting attendance
- For coordination
- For review/comments
- For reference/filing
- For appropriate action

Forwarding controls:

- At least one target department is required.
- Target must be another parent department or the current parent's own child department.
- Action type must be one of the allowed values.
- Deadline date is required.
- Instruction is required.

System behavior:

- Document destination becomes the first selected target.
- Document status is set back to `Released`.
- Current pending TO or DELEGATE route is marked `Received`.
- Existing matching target route is reset to `Pending`, or a new route is created.
- Child targets are routed as `DELEGATE`; other parent targets are routed as `TO`.
- A `Forwarded` log is added with target codes and action-slip details.
- Target departments are notified.

### 5.9 Tracking Document Status

Users track documents through:

- Documents list
- Incoming list
- Outgoing list
- Document details page
- Routing panel
- Timeline table
- Attachment history table
- Return alert panel
- Notifications dropdown

Visibility is department-based:

- Origin departments can see their own documents.
- Departments that have logged activity on a document can see it.
- Routed departments can see released documents according to route rules.
- TO, CC, and DELEGATE route visibility waits for THRU clearance when a THRU route exists.
- Managers have a narrower view: their own originating department documents or documents already handled by another user in their department.

## 6. Document Status Definitions

| Status | Meaning | Trigger | Allowed Next Action | Responsible User/Office |
|---|---|---|---|---|
| Draft | Document has been encoded but not released. | Created through document form. | Edit draft, upload/replace draft attachment, release document. | Non-manager user from originating department. |
| Released | Document has been released to routed recipients. | Release draft, or manager forwarding sets document back to Released. | Receiving staff may receive or return; manager action follows after staff receipt. | Routed receiving office staff; later receiving office manager. |
| Received | Required TO or legacy destination receipt is complete. In routed documents, route-level receipt may occur before document-level status becomes Received. | Staff receives all TO routes, or legacy direct destination receives. | Manager acknowledgement, THRU clearance, CC notation, or forwarding if eligible. | Receiving staff and then manager. |
| Returned | Receiving office returned the document because of attachment issue or missing/incorrect attachment details. | Staff submits Return Document form. | Releasing department uploads corrected attachment, then re-releases. | Receiving office staff returns; releasing office staff resolves. |
| Re-released | Returned document was corrected and sent back to the returning office. | Releasing department re-releases after corrected attachment upload. | Returned receiving office can receive again or continue route processing. | Releasing office staff, then receiving office staff. |

Route-level statuses:

| Status | Meaning | Trigger | Allowed Next Action | Responsible User/Office |
|---|---|---|---|---|
| Pending | Route is waiting for action. | Route created, reset by forwarding, or reset after re-release. | Receive or return when route rules allow. | Routed receiving office staff. |
| Received | Route has been received or cleared. For THRU, this also acts as THRU clearance for later recipients. | Staff receive or forwarding marks current pending route received. | Manager action, next route action, or forwarding. | Receiving office staff or forwarding manager process. |
| Returned | Route was returned before receipt. | Staff returns a pending routed document. | Re-release resets this route to Pending. | Receiving office staff returns; releasing office resolves. |

Return-record statuses:

| Status | Meaning | Trigger | Allowed Next Action | Responsible User/Office |
|---|---|---|---|---|
| Open | Return is unresolved. | Document is returned. | Upload corrected attachment, then re-release. | Releasing office staff. |
| Resolved | Return was addressed and re-released. | Re-release succeeds. | Receiving office handles corrected document. | Releasing office staff and returned receiving office. |

## 7. Return and Re-upload Workflow

Return is designed for incorrect, missing, unreadable, or otherwise wrong attachments.

1. Receiving staff opens the document details page.
2. If the document is `Released` or `Re-released`, and the route is pending and available to that department, the Return Document form appears.
3. Staff enters a reason for return.
4. Staff optionally selects an attachment issue:
   - Incorrect attachment
   - Missing page
   - Wrong file
   - Unreadable file
5. Staff enters required remarks/details.
6. System sets the document status to `Returned`.
7. If a route exists, the latest route for the returning department is set to `Returned`.
8. System creates an open `document_returns` record.
9. System logs `Returned`.
10. Releasing department staff are notified.
11. Releasing department staff open the returned document.
12. They upload a corrected attachment and enter a replacement reason.
13. System updates the active attachment and records `document_attachment_history`.
14. System logs `Attachment Replaced`.
15. Re-release becomes available after a replacement exists for the open return.
16. Releasing department staff re-release the document.
17. System sets document status to `Re-released`.
18. System resets the returned route to `Pending`, if the return was tied to a route.
19. System resolves the open return with `resolved_at` and `resolved_by`.
20. System logs `Re-released`.
21. Returned receiving department staff are notified and can receive the corrected document.

Return controls:

- Managers cannot return documents.
- A department cannot return after its manager has already performed manager action.
- Already returned documents cannot be returned again until resolved.
- Only released or re-released documents can be returned.
- Only pending routes can be returned.
- Corrected attachment upload is allowed only by the releasing department from the open return record.
- Re-release requires a replacement attachment history record for the open return.

## 8. System Controls and Validations

Authentication and request controls:

- Most actions require an authenticated session.
- POST actions use CSRF validation.
- Session ID is regenerated on successful login.
- Logout requires POST and CSRF.
- Safe redirect checks are used for notification redirects.

Document field controls:

- Title is required.
- Type is required.
- At least one TO department is required.
- Particulars are optional.
- Document reference is optional but must be visible to the user's department.
- Draft edit cannot reference the same document.

Routing controls:

- Creation THRU, TO, and CC routing is limited to parent departments.
- Creation internal division routing is limited to direct child divisions of the originating department and is stored as `DELEGATE`.
- THRU cannot also remain in TO or CC.
- CC cannot duplicate TO.
- Internal child division selections cannot duplicate THRU, TO, or CC.
- THRU must be received or cleared before TO, CC, and DELEGATE receipt.
- Forwarding targets are limited to another parent department or the manager's own child department.
- Parent department is required for forwarding.

Attachment controls:

- Attachment is optional at document creation.
- Corrected attachment is required before re-release.
- Allowed MIME types are PDF, JPEG, PNG, GIF, and WEBP.
- Maximum attachment size is 100 MB.
- Extension must match detected content type.
- Stored filename is randomized.
- Attachment viewing requires document access authorization.

Permission controls:

- Only origin department can edit or release its drafts.
- Managers cannot release drafts in the current UI condition.
- Managers cannot perform initial receive.
- Staff cannot perform manager receive, THRU clearance, CC notation, or forwarding.
- Return and corrected upload are staff workflows.
- Admin-only screens control activation, deactivation, and role update.

Notification controls:

- New registration notifies active admins.
- Release notifies initial route recipients.
- Staff receipt notifies department managers and document creator.
- THRU clearance notifies the creator and next route recipients.
- Return notifies releasing department staff.
- Re-release notifies the returned department staff.
- Role update and account activation notify affected users.

## 9. Activity Log / Audit Trail

The system records document activity in `document_logs`. Current implemented log actions include:

| Action | Logged When |
|---|---|
| Created | Document is first encoded. |
| Draft Updated | Origin department edits a draft. |
| Released | Origin department releases a draft. |
| Received | Staff receives a route or legacy destination document. |
| Returned | Receiving staff returns a released or re-released document. |
| Attachment Replaced | Releasing department uploads corrected attachment for an open return. |
| Re-released | Releasing department sends corrected document again. |
| Manager Received | Manager acknowledges document after staff receipt. |
| Cleared THRU | THRU manager clears the document. |
| Noted CC | CC manager notes the document. |
| Forwarded | Manager forwards document with action-slip details. |

Each log stores:

- Document ID
- Action
- Acting user
- Acting department
- Remarks
- Timestamp

Attachment replacements are also tracked in `document_attachment_history`, including:

- Document ID
- Return ID
- Old filename
- New filename
- Uploading user
- Upload date
- Replacement reason
- Active attachment flag

Return records are tracked in `document_returns`, including:

- Returning user and department
- Releasing department
- Return reason
- Attachment issue
- Remarks
- Open or resolved status
- Returned and resolved timestamps

## 10. Exception Scenarios

| Scenario | Expected Current Behavior |
|---|---|
| Inactive user attempts login | Login is blocked with inactive account message. |
| Invalid credentials | Login is rejected. |
| Non-admin opens User Management | User is redirected with access denied message. |
| Admin deactivates own account | Action is blocked. |
| Duplicate registration ID or email | Registration is rejected. |
| Missing document title or type | Create or edit form is rejected. |
| Missing TO department and missing internal division | Create or edit form is rejected. |
| THRU removes all TO recipients and no internal division remains | Validation requires at least one routable recipient. |
| Non-parent department selected in THRU, TO, or CC during creation routing | Validation rejects the route. |
| Child division of another department selected for internal creation routing | Validation rejects the route. |
| Own child division selected during creation routing | Route is saved as DELEGATE and follows normal release, THRU clearance, receiving, return, and tracking rules. |
| Invalid reference document | Validation rejects the reference. |
| Draft receive attempt | Receive is blocked. |
| Returned document receive attempt | Receive is blocked until corrected and re-released. |
| TO, CC, or DELEGATE receives before THRU clearance | Receive is blocked. |
| Manager tries staff receive | Receive is blocked. |
| Staff tries manager action | Manager action is blocked. |
| Manager tries forward before staff receipt and manager acknowledgement | Forwarding is blocked. |
| Forward target is invalid | Forwarding is blocked. |
| Receiving staff returns after manager action | Return button/action is blocked. |
| Returned document is returned again | Return is blocked. |
| Releasing department tries re-release before corrected attachment upload | Re-release is blocked. |
| Non-releasing department tries corrected upload or re-release | Action is blocked. |
| Attachment exceeds 100 MB | Upload is rejected. |
| Attachment MIME type is not allowed | Upload is rejected. |
| Attachment extension does not match MIME type | Upload is rejected. |
| Missing attachment file on disk | Attachment view/source action reports attachment not found. |
| Unauthorized document or attachment access | Access is denied. |
| GET request used for POST-only actions | Request is rejected through request method or CSRF validation. |

## 11. Gaps / Items for Confirmation

1. Dashboard status counts use `Pending` and `Completed`, but the active document status enum uses `Draft`, `Released`, `Received`, `Returned`, and `Re-released`. Dashboard totals may be misleading.

2. The `custodian` role exists in the user role list and database enum, but no custodian-specific permissions or workflows were found.

3. The document `type` database enum lists fixed values, but the current form uses a free-text input. This may cause database errors if users enter a type outside the enum values.

4. Attachment upload during document creation is optional, but the return/re-upload workflow is specifically about attachment issues. It should be confirmed whether documents may be released without any attachment.

5. The return workflow is limited to staff before manager action. It should be confirmed whether managers should be able to return documents after review.

6. `Received` is used both as a document status and as a route status. Route-level receipt can happen before the whole document is complete, so users may need clearer distinction between route receipt and document completion.

7. THRU clearance is represented by a THRU route status of `Received` plus a manager `Cleared THRU` log. The route status itself does not have a separate `Cleared` value.

8. CC notation is represented in the UI based on route receipt and manager logs, but the route status remains `Received`, not a distinct `Noted` status.

9. Re-release resets only the returned route tied to the open return record. If multiple routes or departments are involved, confirm whether only the returning route should reopen.

10. Forwarding sets the document status to `Released` and can reset existing routes. Confirm whether forwarded documents should preserve a separate forwarded status or remain under `Released`.

11. The return form allows custom free-text return reason plus optional predefined attachment issue. Confirm whether return reasons should be standardized.

12. There is no explicit delete workflow for documents, routes, returns, or attachments in the inspected implementation.

13. There is no explicit final completion status beyond `Received`. Confirm whether the business process needs a final closed/completed state after all manager actions and forwarded actions are done.

14. QR print and verification support exists, but QR printing is guarded by `ENABLE_QR_PRINT` and appears temporarily disabled in comments. Confirm whether QR verification is part of the active production workflow.

15. The model auto-creates or alters schema at runtime for routing, return tables, return statuses, particulars, references, and QR token. Confirm whether production deployment should rely on runtime schema changes or managed migrations.

16. User role assignment is available in the current User Management screen, while older notes describe no role-management screen. Current documentation should follow the current code and view.

17. Manager document visibility is narrower than general department visibility. Managers see their own originating department documents or documents already handled by another user in their department. Confirm whether managers should see all routed incoming documents before staff receipt.

18. Attachments are renamed and old filenames are retained in attachment history only for corrected replacements. Initial upload history is not recorded in `document_attachment_history`.

19. There is no separate audit trail for user account status changes or role changes beyond notifications to affected users.

20. There is no explicit validation that the selected department during registration or profile update exists, only that the posted department ID is greater than zero.
