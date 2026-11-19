# Pending Action By

The action-slip list and details page display the names of people whose action is
currently pending. This is calculated from existing assignments and user/office
records; it does not change database schema, permissions, or completion rules.

- Receipt keeps the recipient's name visible until they submit their work.
- Parallel branches show all current recipients.
- Completed staff output appears under the reviewing unit or division manager.
- Completed unit output appears under the reviewing division manager.
- A reviewer appears as soon as an individual completion is ready, even while
  another recipient is still working. Each person appears once.
- Supervisors waiting for delegated work are omitted until a review or completion
  action is ready. An unfinished department assignment becomes pending again when
  its delegated division assignments are completed or confirmed.
- Pending reviews remain visible even if the overall slip badge says Completed.
- Confirmed and cancelled assignments do not create pending work. A cancelled
  slip displays No pending action.
- Office-only assignments show all active managers of that office. Explicit unit
  recipients retain their recorded identity. Missing, inactive, or ineligible
  named recipients display Responsible person unavailable.
- Slips that predate assignment records use their current recorded recipient.

Names are escaped in both views. The list loads assignment data in a batch after
the existing visibility/pagination query, rather than querying each slip separately.
The list shows each pending person's first name on its own line, replacing the
Current Owner and Assigned Staff columns. Compound first names such as Maria Luisa
are preserved. The details page keeps full names. Both the slip title and the View
button open its details.

Behavioral coverage: `tests/ActionSlipPendingActionTest.php`.
