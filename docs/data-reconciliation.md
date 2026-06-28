# DTS Data Reconciliation Reports

Run:

```powershell
php scripts/reconcile.php
php scripts/reconcile.php --json
php scripts/reconcile.php --stuck-days=14 --overdue-days=1
```

Reports currently include:

- `stuck_documents`: released/received/returned documents with no update for the configured age.
- `duplicate_document_tracking_numbers`: tracking numbers appearing more than once.
- `overdue_action_slips`: non-completed action slips past deadline.
- `duplicate_action_slip_numbers`: action slip numbers appearing more than once.
- `open_document_returns`: unresolved document returns.
- `orphaned_document_assignments`: assignments pointing to missing documents, missing users, or inactive users.
- `orphaned_action_slip_events`: action-slip events pointing to missing slips or actors.

Treat duplicate and orphaned rows as production data-integrity incidents until reviewed. Stuck and overdue rows may be normal workload, but they should have a named business owner.
