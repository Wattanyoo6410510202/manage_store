# Retention Return Date and Reminder

## Goal

For each milestone that has a retention deduction, allow the user to record the planned retention-return date, show the remaining days on project cards, and warn from the project-list page when a return is due within five days or is overdue. The existing refund confirmation remains the action that marks the retention as returned.

The reminder is intentionally page-based: it runs when `projects.php` is opened. No cron job, background worker, external notification channel, or alert on unrelated pages is added.

## Data model

Add a migration named `add_retention_return_fields.sql` with two nullable fields on `project_milestones`:

- `retention_return_due_date DATE NULL` — the planned date selected by the user.
- `retention_returned_at DATETIME NULL` — set when the user confirms that the retention was returned.

The existing `retention_amount` remains the source of whether a deduction exists. A milestone is considered outstanding when `retention_amount > 0`, `retention_return_due_date` is not null, and `retention_returned_at` is null. The due date is preserved after confirmation so the card and history can show what was planned.

## Create and edit flow

`add_milestone.php` and `edit_milestone.php` add a date input beside the retention controls. The input is visible/enabled only when the retention checkbox is active and is required when retention is enabled. When retention is disabled, the submitted date is cleared.

`api/save_milestone.php` and `api/update_milestone_full.php` validate the date as `Y-m-d`, persist it, and reject an enabled retention deduction without a valid date. The API remains authoritative: it still recalculates the retention amount from the project value and does not trust the hidden browser amount. Updating a milestone does not change the existing VAT/WHT calculation.

If an existing milestone is edited with retention enabled, it starts/continues an outstanding return schedule and clears a previous returned timestamp. Disabling retention clears the schedule fields. This prevents a newly added deduction from inheriting an old “returned” state.

## Project-list card and reminder

`projects.php` selects the nearest outstanding return date for each project. The grid card in the supplied reference image shows a compact line below the project end date:

- outstanding: `คืนเงินประกัน: DD/MM/YYYY · เหลืออีก N วัน`
- overdue: `คืนเงินประกัน: DD/MM/YYYY · เกินกำหนด N วัน`
- returned: `คืนเงินประกันแล้ว: DD/MM/YYYY`
- no retention schedule: no extra line

The card uses the nearest outstanding milestone when a project has multiple schedules. The table view is not expanded beyond the requested card treatment.

On page load, the server renders outstanding schedules due on or before today + five days (including overdue items) as JSON. JavaScript checks `localStorage` and displays one SweetAlert per browser per calendar day, containing one row per schedule with:

- project name (the job name requested)
- milestone name
- due date
- `เหลืออีก N วัน` or `เกินกำหนด N วัน`

The warning is only shown when there is at least one matching schedule. If localStorage is unavailable, the alert still works for the current page load.

## Confirmation flow

The existing `refundRetention` confirmation on `detail_project.php` is retained and restricted to paid milestones with an outstanding retention amount. A returned milestone shows a non-actionable “คืนเงินประกันแล้ว” status and the recorded return date.

`api/update_refund_milestone.php` verifies that a retention amount exists, clears the retention and other-deduction amounts using its current calculation behavior, sets `retention_returned_at = NOW()`, and keeps the due date and note. Repeated confirmation is rejected instead of silently changing the record again.

## Error handling and access

Existing project-management authorization is reused for creating, editing, and confirming returns. Invalid dates return a 422 response with a Thai validation message. A missing milestone returns 404. The page-level reminder is read-only and follows the same project visibility rules as `projects.php`, including inspection-only access.

## Testing

Add focused tests before implementation:

1. PHP smoke coverage for date validation/state transitions and the migration/API field usage.
2. Node behavior coverage for countdown labels, overdue labels, the five-day reminder boundary, project/milestone names in the alert payload, and the once-per-day localStorage guard.
3. Extend the existing milestone layout smoke test to verify the return-date field is present in the retention card.
4. Run PHP lint on all modified PHP files and the repository test suite. Existing unrelated test failures will be reported separately.
