# Documentation Gap Analysis and Current Code Map

## Purpose of This File

This document compares the repository's previous markdown documentation with the current codebase as of March 16, 2026. It is intended to explain what changed and to point readers to the updated manual.

Reference manual: `docs/manual-of-operations.md`

## Current Repository Map

```text
C:\xampp\htdocs\DTS
|-- app/
|   |-- config/
|   |   `-- config.php
|   |-- controllers/
|   |   |-- Auth.php
|   |   |-- Dashboard.php
|   |   |-- Documents.php
|   |   |-- Notifications.php
|   |   `-- Users.php
|   |-- core/
|   |   |-- App.php
|   |   |-- Controller.php
|   |   `-- Database.php
|   |-- models/
|   |   |-- Department.php
|   |   |-- Document.php
|   |   |-- Notification.php
|   |   `-- User.php
|   |-- views/
|   |   |-- auth/
|   |   |   |-- login.php
|   |   |   `-- register.php
|   |   |-- dashboard/
|   |   |   `-- index.php
|   |   |-- documents/
|   |   |   |-- create.php
|   |   |   |-- forward.php
|   |   |   |-- incoming.php
|   |   |   |-- index.php
|   |   |   |-- outgoing.php
|   |   |   |-- show.php
|   |   |   `-- view.php
|   |   |-- layout/
|   |   |   |-- footer.php
|   |   |   `-- header.php
|   |   `-- users/
|   |       |-- index.php
|   |       `-- profile.php
|   `-- init.php
|-- docs/
|   |-- directory-map-and-code-review.md
|   `-- manual-of-operations.md
|-- helpers/
|   `-- auth_helper.php
`-- public/
    |-- .htaccess
    |-- index.php
    |-- test.php
    |-- assets/
    `-- uploads/
```

## Summary of Documentation Gaps Found

### Outdated Items in the Previous Markdown

1. The previous file described `DashboardController.php` and `DocumentsController.php` as active or duplicate controllers.
   Result: documented but not currently implemented.

2. The previous file said controller duplication created routing ambiguity.
   Result: no longer accurate in the current tree because only `Dashboard.php` and `Documents.php` remain.

3. The previous file focused on code review concerns but did not describe the actual user workflows now present in the system.
   Missing from prior documentation:
   - user registration
   - inactive-account approval flow
   - admin user activation/deactivation
   - notification dropdown and mark-all-read action
   - separate document list screens for all, incoming, and outgoing
   - routing types `THRU`, `TO`, `CC`, and `DELEGATE`
   - manager-only actions such as `Manager Receive`, `Clear THRU`, `Note CC`, and forwarding with action instructions

4. The previous file used a repository map that no longer matched the actual folder contents.
   Result: inaccurate and replaced with the current map above.

### Current Implementation Details That Needed Documentation

1. New accounts are always registered as `user` and `inactive`.

2. Only `admin` users can activate or deactivate accounts in the web interface.

3. The system currently recognizes a separate `manager` role, but there is no role-management screen in the UI.
   Actual code logic:
   the `User` model auto-assigns `manager` to ID numbers `000001`, `000002`, and `000006`.

4. Document routing is stored in the `document_routes` table and supports four route types:
   `THRU`, `TO`, `CC`, and `DELEGATE`.

5. Document creation requires at least one `TO` department.

6. At document creation time, routing is limited to parent departments only.

7. Managers cannot perform the initial staff receipt action.
   Actual code logic:
   staff must receive the document first before a manager can acknowledge it.

8. Forwarding is restricted.
   Actual code logic:
   only managers in parent departments can forward, and only to another parent department or to one of their own child departments.

9. The profile page lets users update only their department and email address.
   Name, role, and status are read-only in the UI.

## Documentation Notes

- `app/views/documents/show.php` exists but is a legacy placeholder page and is not the main document details screen.
- `public/test.php` is present in the repository but is not part of the user-facing workflow.
- The updated end-user and administrator instructions are in `docs/manual-of-operations.md`.
