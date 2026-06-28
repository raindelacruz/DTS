# DTS Concurrency Test Plan

The highest-risk concurrency paths are sequence-number generation and workflow transitions. Run these tests in staging before launch and after any workflow change.

## Action-slip sequence numbers

Goal: prove simultaneous creates do not issue duplicate `slip_number` values.

1. Use one department with at least one active manager.
2. Start 20 parallel create requests with unique subjects.
3. Confirm all requests either succeed with unique slip numbers or fail cleanly without partial records.
4. Run:

   ```sql
   SELECT slip_number, COUNT(*)
   FROM department_action_slips
   GROUP BY slip_number
   HAVING COUNT(*) > 1;
   ```

Expected result: no rows.

## Simultaneous receive/forward

Goal: prove double-clicks and competing users do not create impossible workflow state.

1. Release one document/action slip to a department.
2. Submit receive and forward/delegate actions from two browser sessions at nearly the same time.
3. Confirm the final status has one clear owner and timeline events remain chronological.
4. Run `php scripts/reconcile.php`.

Expected result: no orphaned events, no duplicate numbers, and no contradictory active assignment.

## Double-submit protection

Goal: prove repeated POSTs do not multiply state transitions.

1. Submit a receive/complete form twice using browser refresh or a request replay tool.
2. Confirm the second action is rejected, ignored, or produces a clear user error.
3. Confirm no duplicate active assignment or duplicate completion event was created.

## QR-token generation

Goal: prove concurrent QR token creation remains unique.

1. Open the same document in multiple sessions.
2. Trigger QR generation simultaneously.
3. Confirm every document has exactly one token and all tokens are unique.

Expected result:

```sql
SELECT qr_token, COUNT(*)
FROM documents
WHERE qr_token IS NOT NULL AND qr_token <> ''
GROUP BY qr_token
HAVING COUNT(*) > 1;
```

returns no rows.
