# DTS Full Workflow and UI Logic Audit

Audit date: 2026-05-20  
Scope: current PHP MVC codebase, SQL schema dump, and existing workflow documentation in `C:\xampp\htdocs\DTS`.

## A. Executive Summary

The DTS implementation has two active workflow systems:

- The main document tracking workflow in `app/controllers/Documents.php` and `app/models/Document.php`.
- A separate Department Action Slip workflow in `app/controllers/ActionSlips.php` and `app/models/DepartmentActionSlip.php`.

Several important workflow protections are correctly present: CSRF validation on mutating actions, server-side authorization around most workflow endpoints, draft-only editing, route-based visibility, THRU gating, return/re-upload/re-release handling, and action-slip event logging.

However, the system is not fully aligned or complete. The most important gaps are:

- Document creation is exposed and allowed to every logged-in role, including managers, admins, and custodians, while the detail UI only lets non-managers release drafts. This creates dead-end drafts for manager/admin-created documents.
- The dashboard counts `Pending` and `Completed` document statuses that do not exist in the `documents.status` enum, so dashboard totals are misleading.
- Department Action Slip return and close logic exists in the model/controller, but the UI and `allowedActions()` hard-disable both actions.
- Action Slip creation has partial admin validation support, but `canCreate()` only allows managers, creating unclear and unreachable admin paths.
- The main document workflow and independent action-slip workflow are not integrated. The "Action Slip" shown during document forwarding is serialized text in `document_routes.instructions`, not a real `department_action_slips` record.
- Some backend checks rely on controller-level gates while model methods perform broad state changes if called directly from future code.
- Users can update their own department in profile, which changes document visibility and action eligibility without admin approval.

Overall status: partially implemented and functional for core staff-to-manager document routing, but not yet workflow-complete or consistently role-safe.

## B. Current Workflow Overview

### Main Document Workflow

Implemented in:

- `app/controllers/Documents.php`
- `app/models/Document.php`
- `app/views/documents/_form.php`
- `app/views/documents/view.php`
- `app/views/documents/forward.php`
- Tables: `documents`, `document_routes`, `document_logs`, `document_returns`, `document_attachment_history`, `document_assignments`, `document_sequences`

Observed flow:

1. A logged-in user opens `Documents::create()` and submits to `Documents::store()` (`app/controllers/Documents.php:796`, `app/controllers/Documents.php:827`).
2. Form validation requires title, type, and at least one TO department or internal delegate (`app/controllers/Documents.php:221`).
3. `Document::createDocument()` generates a department prefix, creates a `Draft`, inserts route rows for THRU/TO/CC/DELEGATE, and logs `Created` (`app/models/Document.php:671`).
4. Drafts are editable only through `findOwnedDraftOrFail()` when status is `Draft` and origin department matches the session department (`app/controllers/Documents.php:549`).
5. `Documents::release()` releases owned drafts and notifies THRU first, otherwise TO/CC/DELEGATE recipients (`app/controllers/Documents.php:910`).
6. Staff receive released/re-released documents through `Documents::receive()`; managers are explicitly blocked from staff receipt (`app/controllers/Documents.php:949`).
7. `Document::receiveDocument()` marks the route as `Received`, logs receipt, and sets document status to `Received` only when all primary TO routes are received, or all DELEGATE routes are received when no TO routes exist (`app/models/Document.php:974`).
8. Managers can record manager receipt after another department user has already handled the document (`app/controllers/Documents.php:1222`).
9. Managers can clear THRU or note CC only after manager acknowledgment (`app/controllers/Documents.php:1260`, `app/controllers/Documents.php:1303`).
10. Parent department managers can forward documents after staff receipt plus manager acknowledgment, using action-slip-like fields written into `document_routes.instructions` (`app/controllers/Documents.php:1682`, `app/models/Document.php:1813`).
11. Receiving staff can return a released/re-released document before manager acknowledgment (`app/controllers/Documents.php:1026`, `app/models/Document.php:1084`).
12. Releasing staff can upload a corrected attachment and re-release (`app/controllers/Documents.php:1108`, `app/controllers/Documents.php:1167`, `app/models/Document.php:1236`, `app/models/Document.php:1318`).
13. Division managers can delegate received DELEGATE documents to staff (`app/controllers/Documents.php:1341`, `app/models/Document.php:2050`).
14. Assigned staff can complete internal assignments, which completes only the assignment record and log, not the document itself (`app/controllers/Documents.php:1407`, `app/models/Document.php:2130`).

There is no implemented document archive state. No `Archived` document status or archive endpoint was found.

### Department Action Slip Workflow

Implemented in:

- `app/controllers/ActionSlips.php`
- `app/models/DepartmentActionSlip.php`
- `app/views/action_slips/create.php`
- `app/views/action_slips/index.php`
- `app/views/action_slips/view.php`
- Tables: `department_action_slips`, `department_action_slip_events`, `department_action_slip_sequences`

Observed flow:

1. Managers can open the independent Action Slip form through `ActionSlips::create()` because `canCreate()` returns true only for managers (`app/controllers/ActionSlips.php:123`, `app/controllers/ActionSlips.php:128`).
2. Parent department managers can create slips to another parent department or to a child division (`app/controllers/ActionSlips.php:216`).
3. Division managers can create slips only to staff in their division (`app/controllers/ActionSlips.php:216`).
4. `DepartmentActionSlip::create()` reserves a slip number, inserts the slip with status `Released`, logs `Created` as `Draft`, then logs release as `Released` (`app/models/DepartmentActionSlip.php:267`).
5. Department managers can receive, route to another department, delegate to division, or complete depending on status (`app/controllers/ActionSlips.php:335`).
6. Division managers can receive, delegate to staff, complete division-level slips, or confirm staff completion (`app/controllers/ActionSlips.php:335`).
7. Staff can start and complete assigned action slips (`app/controllers/ActionSlips.php:335`).
8. Event attachments and slip attachments are protected by `canUserView()` before streaming (`app/controllers/ActionSlips.php:550`, `app/controllers/ActionSlips.php:572`).

Return and close are coded but unavailable because `allowedActions()` returns `false` for both (`app/controllers/ActionSlips.php:335`).

## C. Role-Based Access and Behavior Matrix

| Role | Implemented Access | Correctly Restricted? | Concerns |
|---|---|---:|---|
| Staff | Create documents, edit/release own department drafts, receive eligible documents, return before manager receipt, upload corrected attachment, re-release, complete internal assignments, view assigned action slips | Partially | Staff can change own department in profile, affecting visibility and workflow eligibility (`app/controllers/Users.php:86`). |
| Department Manager | Manager receive, clear THRU, note CC, forward, create action slips, receive/route/delegate/complete department action slips | Partially | Managers can create documents from sidebar/form, but cannot release them in UI because draft release is hidden for managers (`app/views/layout/header.php`, `app/views/documents/view.php:128`). |
| Division Manager | Manager receive on handled docs, delegate DELEGATE documents to staff, create action slips to staff, receive/delegate/complete division slips | Partially | Main document forwarding is blocked by `requireParentDepartment()`; this is logical for parent-only forwarding but limits department-to-division-to-staff workflows to DELEGATE routes only (`app/controllers/Documents.php:29`, `app/controllers/Documents.php:1682`). |
| Admin | User management, can view all action slips, can access document creation/listing like any logged-in user | Partially | Admin is allowed by some validation branches but cannot create action slips due to `canCreate()`; admin document actions are not intentionally designed (`app/controllers/ActionSlips.php:123`, `app/controllers/ActionSlips.php:216`). |
| Custodian | Listed as a role and table exists | No | No meaningful custodian-specific behavior found. Custodian falls into non-manager paths for documents and non-staff/non-admin department visibility for action slips (`app/models/User.php:11`, `dts_db.sql`). |
| Releasing/Receiving personnel | Not a separate role; implemented mostly as `staff` | Partially | The requested personnel category is not modeled distinctly. Any staff in a department can perform receiving/releasing if document state permits. |

## D. Form/Button Visibility Matrix

| UI Control | File | Visible When | Backend Gate | Assessment |
|---|---|---|---|---|
| Create Document nav/sidebar | `app/views/layout/header.php` | Every logged-in user | `Documents::store()` has no role check | Risky. Managers/admin/custodians can create drafts. |
| Create New Document button | `app/views/documents/index.php:39` | Every document-list viewer | No role check | Same exposure risk. |
| Edit Draft | `app/views/documents/view.php:128` | Non-manager, `Draft`, origin department | `findOwnedDraftOrFail()` | Mostly correct, but mismatched with unrestricted creation. |
| Release Document | `app/views/documents/view.php:130` | Non-manager, `Draft`, origin department | `findOwnedDraftOrFail()` | Mostly correct, but manager-created drafts dead-end. |
| Receive Document | `app/views/documents/view.php:136` | Non-manager, released/re-released, route/destination eligible | `Documents::receive()` blocks managers and validates route/destination | Good. |
| Return Document | `app/views/documents/view.php:143` | `canCurrentStaffReturnDocument()` | `Documents::returnDocument()` repeats gate | Good. |
| Upload Corrected Attachment | `app/views/documents/view.php:168` | Releasing department staff with open return and `Returned` status | `Documents::uploadCorrectedAttachment()` repeats gate | Good. |
| Re-release Document | `app/views/documents/view.php:184` | Corrected attachment exists | `Documents::reRelease()` repeats gate | Good. |
| Manager Receive | `app/views/documents/view.php:195` | Manager, staff handled, not acknowledged | `Documents::managerReceive()` | Mostly good. |
| Clear THRU | `app/views/documents/view.php:202` | Manager acknowledged THRU route | `Documents::clearThru()` | Good. |
| Note CC | `app/views/documents/view.php:209` | Manager acknowledged CC route | `Documents::noteCc()` | Good. |
| Delegate to Staff | `app/views/documents/view.php:216` | Division manager on cleared DELEGATE route | `Documents::delegateToStaff()` | Good for division flow. |
| Complete Internal Assignment | `app/views/documents/view.php:240` | Assigned staff | `Documents::completeInternalAssignment()` | Good, but does not complete document. |
| Forward Document | `app/views/documents/view.php:252` | Parent manager, acknowledged, route cleared, no child delegation | `Documents::forward()` plus `ensureForwardableDocument()` | Mostly good. |
| New Action Slip | `app/views/action_slips/index.php:30` | `can_create`, manager only | `ActionSlips::store()` manager only | Good for managers, but admin path is inconsistent. |
| Action Slip Return | `app/views/action_slips/view.php:169` | Never, because `allowedActions()['return']` is false | Controller also checks false | Incomplete/dead feature. |
| Action Slip Close | `app/views/action_slips/view.php:162` | Never, because `allowedActions()['close']` is false | Controller also checks false | Incomplete/dead feature. |

## E. Routing Scenario Validation Table

| Scenario | Implemented? | Evidence | Assessment |
|---|---:|---|---|
| External > Department > Department > Division > Staff | Partial | Independent Action Slip supports Department route, Division delegate, Staff delegate (`app/controllers/ActionSlips.php:376`, `app/controllers/ActionSlips.php:397`, `app/controllers/ActionSlips.php:427`) | Works only in independent action slips, not as a linked document workflow. |
| External > Department > Division > Staff | Partial | Action Slip Department Manager to Division, Division Manager to Staff | Works in action slips. Main documents can create DELEGATE route directly to own division but not arbitrary external department-to-division unless forwarded by parent manager. |
| External > Division > Staff | Partial | Division managers can create action slips only to staff (`app/controllers/ActionSlips.php:216`) | Works for independent action slips. Main document direct external-to-division depends on document creator choosing DELEGATE under own parent. |
| Department > Department | Yes | Document TO routes and Action Slip routeDepartment | Implemented with parent department validation. |
| Department > Division | Yes | Document DELEGATE routes and Action Slip delegateDivision | Implemented for child divisions only. |
| Division > Staff | Yes | Document internal assignment and Action Slip delegateStaff | Implemented. |
| Delegation from Department Manager to Division Manager | Yes | Document DELEGATE route and Action Slip delegateDivision | Implemented. |
| Further delegation from Division Manager to Staff | Yes | `Documents::delegateToStaff()`, `ActionSlips::delegateStaff()` | Implemented. |
| Direct send where managers release and receive without staff | Partial/No | Action Slips allow managers to create/release and receiving managers to receive. Main documents explicitly block manager receive (`app/controllers/Documents.php:949`) | Works for independent action slips only. Not for main documents. |
| Circular route prevention | Partial | Action Slip prevents routing to same department; documents validate forward targets but allow existing route reset (`app/controllers/ActionSlips.php:376`, `app/controllers/Documents.php:1682`) | No route graph/cycle tracking beyond local checks. |

## F. Status Transition Validation Table

### Main Documents

| From | Action | To | Implemented In | Assessment |
|---|---|---|---|---|
| none | Create | Draft | `Document::createDocument()` (`app/models/Document.php:671`) | Correct. |
| Draft | Edit | Draft | `Document::updateDraftDocument()` (`app/models/Document.php:851`) | Correct. |
| Draft | Release | Released | `Document::releaseDocument()` (`app/models/Document.php:1772`) | Correct when invoked through controller, but model does not constrain current status itself. |
| Released/Re-released | Receive THRU | Document remains Released/Re-released; route Received | `Document::receiveDocument()` (`app/models/Document.php:974`) | Correct. |
| Released/Re-released | Receive TO | Maybe Received | `Document::receiveDocument()` | Correct for all-TO-received condition. |
| Released/Re-released | Receive DELEGATE without TO | Maybe Received | `Document::receiveDocument()` | Correct for all-DELEGATE-received when no TO exists. |
| Released/Re-released | Return | Returned | `Document::returnDocument()` (`app/models/Document.php:1084`) | Correct through controller. |
| Returned | Upload correction | Returned | `Document::replaceReturnedAttachment()` (`app/models/Document.php:1236`) | Correct. |
| Returned | Re-release | Re-released | `Document::reReleaseReturnedDocument()` (`app/models/Document.php:1318`) | Correct. |
| Received | Forward | Released | `Document::forwardDocument()` (`app/models/Document.php:1813`) | Correct through manager gate. |
| Any | Archive | N/A | Not found | Missing. |

### Department Action Slips

| From | Action | To | Implemented In | Assessment |
|---|---|---|---|---|
| none | Create | Released | `DepartmentActionSlip::create()` (`app/models/DepartmentActionSlip.php:267`) | Functional, but event log says Created -> Draft while row is immediately Released. |
| Released | Receive Department | Received | `receiveByDepartment()` (`app/models/DepartmentActionSlip.php:660`) | Correct. |
| Received/Returned | Route Department | Released | `routeToDepartment()` (`app/models/DepartmentActionSlip.php:665`) | Correct. |
| Received/Returned | Delegate Division | Delegated | `delegateToDivision()` (`app/models/DepartmentActionSlip.php:691`) | Correct. |
| Released/Delegated | Receive Division | Received | `receiveByDivision()` (`app/models/DepartmentActionSlip.php:717`) | Correct. |
| Received/Returned | Delegate Staff | Delegated | `delegateToStaff()` (`app/models/DepartmentActionSlip.php:722`) | Correct. |
| Released/Delegated | Staff start | For Action | `startStaffAction()` (`app/models/DepartmentActionSlip.php:743`) | Correct. |
| For Action/Received | Staff complete | Completed | `completeByStaff()` (`app/models/DepartmentActionSlip.php:748`) | Correct. |
| Completed by staff | Division confirm | Completed | `confirmByDivisionManager()` (`app/models/DepartmentActionSlip.php:768`) | Correct. |
| Received/For Action/Returned | Department complete | Completed | `completeByDepartmentManager()` (`app/models/DepartmentActionSlip.php:789`) | Correct. |
| Several | Return | Returned | `returnSlip()` (`app/models/DepartmentActionSlip.php:808`) | Dead/unreachable from UI/controller because action flag is false. |
| Several | Close | Completed | `closeSlip()` (`app/models/DepartmentActionSlip.php:838`) | Dead/unreachable from UI/controller because action flag is false. |

## G. Issues Found

| Priority | Issue | Evidence | Impact |
|---|---|---|---|
| Critical | Users can change their own department, affecting visibility and permissions. | `Users::updateProfile()` updates `department_id` and session department (`app/controllers/Users.php:86`). | A user can move into another office and gain document/action access without admin approval. |
| High | Document creation is role-unrestricted but release/edit UI is non-manager only. | `Documents::create()`/`store()` lack role checks (`app/controllers/Documents.php:796`, `app/controllers/Documents.php:827`); release UI requires `!$isManager` (`app/views/documents/view.php:128`). | Managers/admins/custodians can create drafts they may not be able to progress normally. |
| High | Dashboard counts invalid statuses. | Dashboard calls `countByStatus('Pending')` and `countByStatus('Completed')` (`app/controllers/Dashboard.php:10`), but document enum is `Draft`, `Released`, `Received`, `Returned`, `Re-released` (`dts_db.sql`). | Misleading dashboard metrics. |
| High | Action Slip return and close workflows are implemented but disabled. | `allowedActions()` returns `'close' => false` and `'return' => false` (`app/controllers/ActionSlips.php:335`). | Return/close workflow is dead despite UI forms and model methods existing. |
| High | Independent Action Slip is not linked to main documents. | Document forwarding stores action-slip-like fields in route instructions (`app/models/Document.php:326`, `app/models/Document.php:1813`); independent slips use separate tables. | Traceability is split; document action slips are not true action-slip records. |
| Medium | Admin Action Slip creation is internally inconsistent. | `canCreate()` manager-only (`app/controllers/ActionSlips.php:123`), but `validateCreateValues()` has admin branch (`app/controllers/ActionSlips.php:216`). | Unclear intended admin capability and unreachable validation paths. |
| Medium | Custodian role has no clear workflow. | Role exists in `User::roles()` and SQL enum, but no custodian-specific gates found (`app/models/User.php:11`). | Role exposure is ambiguous and may inherit unintended staff-like document behavior. |
| Medium | Main document model methods are broad and rely on controller checks. | `Document::releaseDocument()` updates by id without status/owner condition (`app/models/Document.php:1772`). | Future callers could bypass workflow constraints if not careful. |
| Medium | Action Slip upload validation checks extension but not MIME/content. | `ActionSlips::handleUpload()` allows extensions only (`app/controllers/ActionSlips.php:630`). | Higher attachment risk than document uploads, which validate MIME. |
| Low | Some UI explanatory text is overly broad. | Documents list says users can release/receive/clear/note/forward based on role (`app/views/documents/index.php:44`). | Users may expect actions that are not relevant to their role. |
| Low | Action Slip "start staff" button says "Receive". | `app/views/action_slips/view.php:122`. | Staff action semantics are confusing. |

## H. Missing or Incomplete Features

- No archive workflow or archive status was found for documents or action slips.
- No explicit Department Manager versus Division Manager role value exists; both are `manager`, inferred by whether their department has a `parent_id`.
- No Releasing/Receiving personnel role exists; this is implemented as generic `staff`.
- Department Action Slip return and close are incomplete/dead.
- Main documents do not have a final "completed" document status. Internal staff completion completes only `document_assignments`, not the document.
- Independent Action Slip records do not reference `documents.id`, so action slips cannot be reliably tied back to tracked documents.
- Direct manager-to-manager release/receive exists in independent Action Slips but not in main Documents.
- There is no explicit anti-cycle route history rule for documents or action slips beyond same-department prevention in one action-slip route path.

## I. Illogical or Risky UI/Workflow Exposure

- The sidebar exposes Create Document to all roles (`app/views/layout/header.php`), but the detail page hides draft edit/release from managers (`app/views/documents/view.php:128`).
- The dashboard exposes "Create Document" to all roles (`app/views/dashboard/index.php`), again without matching backend role intent.
- `ActionSlips::create()` catches all throwables and reports "could not open" even for unauthorized users (`app/controllers/ActionSlips.php:128`), which can mask permission intent.
- Action Slip forms for return/close exist in the view, but cannot appear because action flags are permanently false (`app/views/action_slips/view.php:162`, `app/views/action_slips/view.php:169`).
- `Documents::show()` narrows manager visibility to origin department or documents already handled by another department user (`app/controllers/Documents.php:324`). This is intentional for staff-first handling, but can surprise managers who expect to see routed documents before staff receipt.

## J. Security and Permission Concerns

- Critical: self-service department changes in `Users::updateProfile()` can change authorization context immediately (`app/controllers/Users.php:86`).
- Hidden buttons are mostly backed by server checks for document actions. This is good.
- Attachment streaming for documents uses `authorizeDocumentViewOrFail()` before reading files (`app/controllers/Documents.php:1540`, `app/controllers/Documents.php:1583`). This is good.
- Action Slip attachment streaming uses `canUserView()` and path normalization/realpath checks (`app/controllers/ActionSlips.php:550`, `app/controllers/ActionSlips.php:665`). This is good.
- CSRF checks are consistently used in mutating endpoints through `validateCsrfOrFail()` or `requireValidCsrfPost()`. This is good.
- `ActionSlips::handleUpload()` does not verify MIME type or content signature (`app/controllers/ActionSlips.php:630`).
- `Verification::verify()` provides QR-token document metadata. Its `isAuthorizedViewer()` allows released/received/re-released records but not drafts/returned documents (`app/controllers/Verification.php:17`). This is reasonable, but still exposes metadata to anyone with token.

## K. Database/Relationship Concerns

- `document_returns` and `document_attachment_history` are created in runtime schema code without foreign keys, while `docs/return-reupload-workflow.sql` includes stronger foreign keys. The dump `dts_db.sql` includes no foreign key constraints for these two tables.
- `department_action_slips.status` is `varchar(80)`, not an enum. This allows invalid statuses unless every write path is controlled.
- `department_action_slips` has no `document_id` reference, so independent action slips cannot be tied to documents.
- `documents.status` has no `Completed` or `Archived` status, while UI/dashboard language implies pending/completed work.
- `custodians` table exists but is unused by workflow logic.
- `document_routes` stores one status per route but not a specific receiving user except through logs. This is traceable through `document_logs`, but not relationally enforced.
- `document_logs` stores action strings as free text, so status/action consistency depends on application code.

## L. Recommended Fixes

1. Restrict or formalize document creation by role.
   - If only staff should create/release documents, hide Create Document for managers/admin/custodians and enforce this in `Documents::create()` and `Documents::store()`.
   - If managers/admins should create documents, update the draft action UI and backend policy so they can edit/release or delegate draft preparation logically.

2. Lock down profile department changes.
   - Remove self-service department changes or require admin approval.
   - Keep self-service email update if needed.

3. Fix dashboard status metrics.
   - Replace `Pending`/`Completed` with actual document statuses or add separate action-slip/assignment metrics.

4. Decide whether Action Slip return/close are real features.
   - If yes, implement `allowedActions()` rules and expose UI.
   - If no, remove dead controller/model/view paths.

5. Link action slips to documents.
   - Add nullable `document_id` to `department_action_slips`.
   - When forwarding a document for action, create a real action slip or explicitly label it as route instructions, not an Action Slip.

6. Add a document completion/archiving policy.
   - Define whether completion is per assignment, per route, or per document.
   - Add `Completed` and/or `Archived` statuses only if they are actually part of the business workflow.

7. Harden model-level transitions.
   - Add status/owner/department checks into update queries where feasible, not only in controllers.

8. Improve Action Slip upload validation.
   - Mirror document upload MIME checks for PDFs/images and add safe validation for Office files.

9. Clarify role taxonomy.
   - Either split `manager` into department/division manager roles, or document and enforce parent/child-department inference everywhere.
   - Remove or implement `custodian`.

## M. Priority Level per Issue

| Priority | Items |
|---|---|
| Critical | Self-service department changes affecting permissions. |
| High | Unrestricted document creation causing dead-end drafts; invalid dashboard metrics; disabled Action Slip return/close; document forwarding "Action Slip" not linked to independent action-slip records. |
| Medium | Admin/custodian role ambiguity; model transition hardening; Action Slip upload MIME validation; lack of document completion/archive state. |
| Low | Confusing labels/instructions; minor UI wording mismatches. |

## N. Suggested Implementation Plan

1. Permission baseline
   - Remove or restrict profile department editing.
   - Add explicit role checks to document create/store/edit/release according to intended business policy.
   - Hide sidebar/dashboard actions that the role cannot perform.

2. Workflow correctness
   - Fix dashboard counts to match actual statuses.
   - Define document finalization: no completion, assignment completion only, document completion, or archive.
   - Add missing status transitions only after the workflow is decided.

3. Action Slip alignment
   - Decide whether independent Action Slips are separate from documents or should be document-linked.
   - If linked, add `document_id`, create slips from document forwarding, and display the same record in both modules.
   - Enable or remove return/close.

4. Security hardening
   - Add model-level guarded updates.
   - Add MIME/content validation to Action Slip uploads.
   - Add foreign keys for return/history tables in the main schema dump.

5. UI cleanup
   - Make role-specific navigation.
   - Replace broad instructions with current-user action guidance.
   - Rename confusing buttons, especially Action Slip staff "Receive" versus "Start Action".

6. Regression checks
   - Test each route manually by role: staff, parent manager, division manager, admin, custodian.
   - Test URL tampering for every POST endpoint.
   - Test return/re-upload/re-release on routed and legacy documents.
   - Test THRU gating before and after clearance.

