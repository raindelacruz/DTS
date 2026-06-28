# NFA Document Tracking System
## Manual of Operations and User Manual

## 1. System Overview

The NFA Document Tracking System is a web-based application for recording, routing, receiving, and monitoring office documents. It helps offices keep a visible trail of document movement from creation up to receipt, manager action, and forwarding.

This manual is based on the current implementation in the repository as of March 16, 2026. It reflects the actual screens, access rules, and workflows found in the code.

## 2. Purpose of the System

The system is used to:

- register office documents in a standard format
- assign routing destinations using `THRU`, `TO`, `CC`, and `DELEGATE`
- track which office or department has already acted on a document
- control account access through activation by an administrator
- give managers a separate action step for review, clearance, notation, and forwarding
- keep a timeline of document actions for reference
- notify users about new registrations, document actions, and account activation

## 3. Important Scope Notes Based on Actual Code Logic

- This system is built as a PHP MVC web application running under `http://localhost/DTS/public`.
- Document statuses currently used by the system are `Draft`, `Released`, and `Received`.
- The application has separate screens for `Documents`, `Incoming`, and `Outgoing`.
- The main document details page is the screen used for most actions.
- There is no web screen for editing an existing document after creation.
- There is no web screen for deleting a document.
- There is no password reset or forgot-password feature in the current implementation.
- There is no web screen for changing user roles.
- `Manager` role assignment is partly code-driven.
  Actual code logic: the system automatically sets the role to `manager` for users with ID numbers `000001`, `000002`, and `000006`.
- A user can change only their own department and email address through the profile page.

## 4. User Roles and Permissions

### 4.1 Regular User

A regular user can:

- register for a new account
- log in after activation
- view the dashboard
- create a document
- view documents allowed for the user's department
- release draft documents created by the user's department
- receive documents routed to the user's department when receipt is allowed
- view attachments
- update their own department and email in the profile page
- view and mark notifications as read

Regular users cannot:

- activate or deactivate accounts
- perform manager-only actions
- forward documents for action
- change user roles

### 4.2 Manager

A manager can do most document viewing functions, but manager actions follow stricter rules.

A manager can:

- open documents visible to the manager's department
- perform `Manager Receive` after staff in the same department has already received the document
- perform `Clear THRU` on `THRU` routed items after manager acknowledgement
- perform `Note CC` on `CC` routed items after manager acknowledgement
- forward a document for action, but only if the manager belongs to a parent department and the document is already eligible for forwarding

Important note based on actual code logic:

- managers cannot perform the first staff receipt step
- a manager must wait until another user from the same department has received the document
- managers only see documents from their own originating department or documents already handled by another user in their department

### 4.3 Administrator

An administrator can:

- do all normal logged-in user actions available in the interface
- open `User Management`
- activate inactive users
- deactivate active users

Important note based on actual code logic:

- the administrator cannot deactivate their own account through the interface
- there is no administrator screen to assign or remove roles

## 5. Login and Access Instructions

### 5.1 Accessing the System

Open the system in a web browser using the configured local address:

`http://localhost/DTS/public`

If no route is entered, the application opens the login page.

### 5.2 Logging In

1. Open the login page.
2. Enter your `ID Number`.
3. Enter your `Password`.
4. Click `Login`.

If successful, the system opens the dashboard.

### 5.3 First-Time Registration

1. Open the login page.
2. Click `Register`.
3. Enter your:
   - ID number
   - department
   - first name
   - last name
   - email address
   - password
   - password confirmation
4. Click `Submit Registration`.

Actual code rules:

- ID number is required and must be unique.
- Email address is required and must be valid.
- Department is required.
- Password must be at least 6 characters.
- Password and confirmation must match.
- New accounts are saved as `inactive`.
- New registrations send a notification to active administrators.

### 5.4 Account Activation

After registration, the user cannot log in until an administrator activates the account.

If an inactive account tries to log in, the system shows this effect in plain language:

- the login is blocked
- the user is instructed to wait for administrator verification

## 6. Main Navigation Overview

After login, the left-side menu shows these options:

- `Dashboard`
- `Documents`
- `Outgoing`
- `Incoming`
- `Create Document`
- `My Profile`
- `User Management` for administrators only

The top bar shows:

- the system name
- the signed-in user's name and role
- a notification button
- `Profile`
- `Logout`

## 7. Dashboard Overview

The dashboard provides a quick summary of document counts.

Displayed summary cards:

- `Total Documents`
- `Pending`
- `Completed`
- `Your Department`

Important note based on actual code logic:

- `Pending` is computed from documents with status `Pending`
- `Completed` is computed from documents with status `Completed`
- the current document workflow in other parts of the code uses `Draft`, `Released`, and `Received`
- this means the dashboard labels do not fully match the active document statuses now used elsewhere in the system

The dashboard also provides buttons for:

- `Documents`
- `Create Document`

## 8. Record Management Workflow

## 8.1 Basic Document Life Cycle

The current system workflow is:

1. Create the document.
2. Save it first as `Draft`.
3. Release the document.
4. Let the receiving department staff receive it.
5. If the route requires manager action, the manager acknowledges it.
6. If the route is `THRU`, the manager clears it.
7. If the route is `CC`, the manager notes it.
8. If allowed, the manager forwards it for further action.
9. The full action trail appears in the timeline log.

## 8.2 Routing Types

The system currently uses four routing types:

- `THRU`
  Used when a document must pass through a department for clearance before other recipients can proceed.

- `TO`
  Main destination department.

- `CC`
  Department that receives the document for notation or reference.

- `DELEGATE`
  Used when a manager forwards a document from a parent department to one of that parent's child departments.

## 8.3 Status Meaning

- `Draft`
  The document has been created but not yet released.

- `Released`
  The document has been sent out and is available for the next allowed receiving office.

- `Received`
  The required receiving step has been completed according to the route logic.

## 9. User Guide

### 9.1 Viewing the Documents List

Open `Documents` to see all documents currently visible to your department.

This list includes:

- documents created by your department
- documents already acted on by your department
- documents routed to your department, subject to routing rules

For each document, the list shows:

- title
- document prefix
- type
- status
- created date
- released date
- a `View Details` button

### 9.2 Searching and Filtering Documents

The `Documents`, `Incoming`, and `Outgoing` screens all include filters.

Available filters:

- `Keyword`
  Searches by document prefix or title.

- `Status`
  Filters by one of the available statuses.

- `Type`
  Filters by available document type values found in the current list.

- `Date From`
  Filters using the created or released date.

- `Date To`
  Filters using the created or released date.

How to use:

1. Open the desired list screen.
2. Enter one or more filter values.
3. Click `Apply`.
4. Use `Reset Filters` to clear the search.

### 9.3 Creating a Document

Open `Create Document` from the menu.

Fields on the form:

- `Title`
- `Type`
- `THRU Department`
- `TO Departments`
- `CC Departments`
- `Attachment`

Steps:

1. Enter the document title.
2. Enter the document type.
3. Select a `THRU Department` if clearance is needed.
4. Select one or more `TO Departments`.
5. Select `CC Departments` if needed.
6. Attach a file if a source document must be opened by users.
7. Click `Create Document`.

Actual code rules:

- at least one `TO` department is required
- if `THRU` is selected, the same department is automatically removed from `TO` and `CC`
- departments duplicated between `TO` and `CC` are removed from `CC`
- all selected routing departments at creation must be parent departments only
- the document is saved with status `Draft`
- the document prefix is generated automatically using department code, year, month, and running sequence number

Example prefix format from actual code logic:

`DEPTCODE-YYYY-MM-001`

### 9.4 Releasing a Draft Document

Release is done from the document details page.

The `Release Document` button appears only when:

- the user is not a manager
- the document is still `Draft`
- the logged-in department is the originating department

Steps:

1. Open the document from `Documents` or `Outgoing`.
2. Review the details.
3. Click `Release Document`.
4. Confirm the action.

System result:

- status changes to `Released`
- the action is added to the timeline log
- notification is sent to the first applicable route departments

### 9.5 Receiving a Document as Staff

Receiving is done from the document details page.

The `Receive Document` button appears only when the current route rules allow it.

Steps:

1. Open the document from `Incoming` or `Documents`.
2. Check the routing panel and any instructions shown.
3. Click `Receive Document`.
4. Confirm the action.

Actual code rules:

- managers cannot perform this step
- `Draft` documents cannot be received
- if the department is under a `THRU` route, it may receive while the route is pending
- `TO`, `CC`, and `DELEGATE` recipients cannot receive until `THRU` has already been cleared
- if there is no route record, only the destination parent department may receive the released document

System result:

- the route status for the receiving department becomes received
- a timeline log entry is created
- department managers are notified that manager action may now be needed
- the document creator may also receive a notification

### 9.6 Viewing Document Details, Routing, and Timeline

Open any visible document and select `View Details`.

The details page shows:

- document prefix
- title
- current status
- type
- created date and time
- released date and time
- attachment button if a file exists
- routing panel for `THRU`, `TO`, `CC`, and `DELEGATE`
- timeline of actions, user names, departments, dates, and remarks

Important note based on actual code logic:

- the buttons in the `Actions` panel appear only when the current role, department, and route state allow the action

### 9.7 File Upload, View, and Download

The current implementation supports file upload during document creation and file opening from the details page.

Supported user actions:

- upload one attachment while creating a document
- open the attachment using `View Attached File`

Important notes based on actual code logic:

- attachments are stored in `public/uploads`
- uploaded file names are prefixed with the current timestamp
- the interface provides a `View Attached File` button
- there is no separate `Download` button in the current interface
- because the file opens through a normal browser link, downloading may still be possible depending on browser behavior, but it is not a separate system command

### 9.8 Viewing Notifications

The top bar contains a notification button.

Users can:

- open recent notifications
- open the linked page for a notification
- mark all notifications as read

Actual code behavior:

- opening a notification marks that notification as read
- `Mark all read` works through a button in the notification menu

### 9.9 Updating My Profile

Open `My Profile` or click `Profile` in the top bar.

Users can update:

- department
- email address

Read-only account information shown on the page:

- name
- ID number
- role
- status

Steps:

1. Open `My Profile`.
2. Select the correct department.
3. Enter a valid email address.
4. Click `Save Profile`.

Actual code rules:

- department is required
- email address is required and must be valid
- the email address must be unique across users

## 10. Administrator Guide

### 10.1 Reviewing Registered Users

Open `User Management`.

This screen shows:

- total users
- active users
- inactive users
- user ID number
- name
- email
- department
- role
- status
- registration date

### 10.2 Activating an Account

Steps:

1. Open `User Management`.
2. Find the inactive user.
3. Click `Activate`.

System result:

- the user status becomes `active`
- the user receives an `Account activated` notification

### 10.3 Deactivating an Account

Steps:

1. Open `User Management`.
2. Find the active user.
3. Click `Deactivate`.

System rule:

- an administrator cannot deactivate their own account through the interface

### 10.4 Current Limits of Administration

The following are documented here to prevent misunderstanding:

- Role assignment is not available in the web interface.
- Department records are not maintained from a web administration screen in this repository.
- There is no user deletion screen.
- There is no audit export screen.

## 11. Manager Guide

### 11.1 Manager Receive

The `Manager Receive` button appears only after another user from the same department has already handled the document.

Steps:

1. Open the document details page.
2. Confirm that staff receipt has already been completed.
3. Click `Manager Receive`.

System result:

- a `Manager Received` entry is added to the timeline
- the document creator is notified

### 11.2 Clear THRU

This action is used only for departments assigned to the `THRU` route.

Steps:

1. Open the document details page.
2. Confirm manager acknowledgement is already complete.
3. Click `Clear THRU`.

System result:

- the timeline records `Cleared THRU`
- the document creator is notified
- the next `TO`, `CC`, or `DELEGATE` departments become eligible for notification and action

### 11.3 Note CC

This action is used only for departments assigned to the `CC` route.

Steps:

1. Open the document details page.
2. Confirm manager acknowledgement is already complete.
3. Click `Note CC`.

System result:

- the timeline records `Noted CC`
- the document creator is notified

### 11.4 Forwarding a Document for Action

Forwarding is done from the document details page by clicking `Forward Document for Action`.

The forwarding form requires:

- one or more target departments
- urgent flag if needed
- action type
- deadline date
- instruction

Allowed action types in the current code:

- `For initial/signature`
- `For meeting attendance`
- `For coordination`
- `For review/comments`
- `For reference/filing`
- `For appropriate action`

Steps:

1. Open the document details page.
2. Click `Forward Document for Action`.
3. Select one or more target departments.
4. Mark `Urgent` if needed.
5. Select the action type.
6. Enter the deadline date.
7. Enter the instruction.
8. Click `Forward Document for Action`.

Actual code rules:

- only managers can forward
- only parent departments can perform the forward action
- the manager must already have acknowledged the document
- the current department must be eligible to forward under route logic
- a parent department may forward only to another parent department or to its own child department
- forwarding creates a route instruction that is shown later to the recipient

System result:

- the document status is set back to `Released`
- the new route is stored
- a `Forwarded` entry is added to the timeline
- recipient departments receive notifications

## 12. Common Issues and Error Handling

The current implementation mainly uses direct warning or stop messages when rules are not met. The following are the common operational issues users may encounter.

### 12.1 Invalid Credentials

Cause:

- wrong ID number or password

Result:

- login is denied with `Invalid credentials.`

### 12.2 Inactive Account

Cause:

- account registration is complete but not yet approved

Result:

- login is denied until an administrator activates the account

### 12.3 File Upload Failed

Cause:

- the server could not move the uploaded file to the uploads folder

Result:

- document creation stops with `File upload failed.`

### 12.4 Missing TO Department

Cause:

- no `TO` department was selected during document creation

Result:

- creation stops because at least one `TO` department is required

### 12.5 Routing Not Allowed

Cause:

- selected departments do not meet the routing rules

Examples from actual code:

- creation route includes a non-parent department
- forward target is neither another parent department nor the current parent's child department

### 12.6 THRU Not Yet Cleared

Cause:

- a `TO`, `CC`, or `DELEGATE` recipient tries to receive before `THRU` clearance is complete

Result:

- receipt is blocked

### 12.7 Manager Action Not Yet Allowed

Cause:

- a manager tries to act before staff receipt

Result:

- manager action is blocked

### 12.8 Access Denied or Unauthorized Action

Cause:

- the user's role, department, or document state does not match the required rule

Result:

- the action is blocked with an access or authorization message

## 13. Features Documented Previously but Not Currently Implemented

The previous markdown in this repository described or implied some items that are not part of the current implementation.

- `DashboardController.php`
  Documented previously but not currently implemented.

- `DocumentsController.php`
  Documented previously but not currently implemented.

- Controller duplication as an active routing problem
  Documented previously but not currently implemented in the present code tree.

Also note these common expectations are not currently implemented in code:

- password reset
- document editing after creation
- document deletion
- role management in the web interface
- dedicated download button for attachments

## 14. Glossary of Important Terms

- `Administrator`
  User who can activate and deactivate accounts through the `User Management` page.

- `Attachment`
  File uploaded when creating a document and opened later from the document details page.

- `CC`
  Department copied in the routing process for notation or reference.

- `Child Department`
  Department with a `parent_id` under a parent department.

- `DELEGATE`
  Route type used when forwarding from a parent department to one of its child departments.

- `Department`
  Office or organizational unit assigned to a user or a document route.

- `Document Prefix`
  Automatically generated tracking code based on department code and sequence.

- `Draft`
  Document created in the system but not yet released.

- `Incoming`
  List of documents currently routed into the logged-in department.

- `Manager Receive`
  Manager acknowledgement step performed after staff in the same department has already received the document.

- `Outgoing`
  List of documents created or sent by the logged-in department.

- `Parent Department`
  Department with no `parent_id`. Parent departments have additional routing privileges in the current system.

- `Released`
  Document status showing the item has already been sent out for routing or receipt.

- `Received`
  Document status showing a required receipt step has been completed.

- `THRU`
  Route type requiring a department to clear a document before the next recipients may proceed.

- `Timeline`
  Historical log of document actions, users, departments, dates, and remarks.

- `TO`
  Primary route destination for a document.

## 15. Final Operational Notes

For day-to-day office use, staff should focus on:

- creating complete and correct routing information
- releasing documents only when ready
- receiving documents promptly
- checking the details page before taking any action
- using the timeline and routing panels as the official on-screen movement history

For administration, the main priority is:

- prompt activation of verified users
- proper review of inactive account requests
- awareness that role assignment is not managed from the current web interface
