---
phase: 09-calend-rio-csrf-exclus-o-e-conclus-o
plan: 01
subsystem: api

tags: [csrf, php, task, calendar, ajax]

# Dependency graph
requires:
  - phase: 08-bugs-e-melhorias-de-contatos-frios
    provides: CsrfMiddleware accepts X-CSRF-Token header and rotates token on every mutation response

provides:
  - TaskController::update() returns csrf_token in AJAX JSON response for token rotation
  - TaskController::destroy() returns JSON for AJAX requests instead of redirect
  - Task::findForCalendar() includes done tasks ordered after pending/in_progress

affects:
  - 09-02 (calendar frontend JS that consumes csrf_token from update/destroy responses and renders done tasks)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "destroy() AJAX gate: same HTTP_X_REQUESTED_WITH pattern as update() — JSON for AJAX, redirect for form"
    - "findForCalendar ORDER BY CASE: status-based sort keeps done tasks visually last without changing WHERE filter"

key-files:
  created: []
  modified:
    - app/Controllers/TaskController.php
    - app/Models/Task.php

key-decisions:
  - "destroy() returns csrf_token on AJAX delete so JS can renew token after task removal — matches update() pattern"
  - "findForCalendar ORDER BY CASE WHEN status='done' THEN 1 ELSE 0 END pushes done tasks after active ones per day without client-side sorting"

patterns-established:
  - "All mutating AJAX responses in TaskController return csrf_token for session-safe multi-edit"

requirements-completed: [BUG-03, CAL-10, CAL-11]

# Metrics
duration: 1min
completed: 2026-03-27
---

# Phase 09 Plan 01: Task Backend — CSRF Rotation, AJAX Delete, Done Tasks in Calendar Summary

**TaskController now rotates CSRF token on every AJAX mutation and destroy() returns JSON; findForCalendar() includes done tasks ordered last via CASE expression**

## Performance

- **Duration:** 1 min
- **Started:** 2026-03-27T15:23:18Z
- **Completed:** 2026-03-27T15:24:03Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- update() AJAX response now includes `csrf_token` via CsrfMiddleware::getToken() — fixes BUG-03 (CSRF not rotated between edits)
- destroy() gained AJAX branch returning JSON {success: true, csrf_token: "..."} — fixes CAL-10 (delete was redirect-only)
- findForCalendar() SQL changed from `NOT IN ('done', 'cancelled')` to `NOT IN ('cancelled')` with ORDER BY CASE sorting done tasks last — fixes CAL-11 (completed tasks invisible in calendar)

## Task Commits

Each task was committed atomically:

1. **Task 1: Return csrf_token in update() and destroy() AJAX responses** - `9aa0e27` (feat)
2. **Task 2: Include done tasks in findForCalendar() with status-based ordering** - `2a7676d` (feat)

## Files Created/Modified
- `app/Controllers/TaskController.php` - update() and destroy() both return {success: true, csrf_token: "..."} for AJAX; redirect for normal form submissions
- `app/Models/Task.php` - findForCalendar() SQL updated: done removed from exclusion, ORDER BY CASE WHEN status='done' THEN 1 ELSE 0 END ASC, due_date ASC added

## Decisions Made
- destroy() returns csrf_token on AJAX delete to match update() pattern — both mutating actions renew the token, enabling continuous editing in a single session without 403 errors
- findForCalendar() ORDER BY CASE sorts done tasks after active tasks (pending/in_progress) within each day so they appear at the bottom of day cells, matching UX expectation that completed tasks are secondary

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Backend contracts established: update() and destroy() both return csrf_token; findForCalendar() returns done tasks
- Phase 09 Plan 02 (calendar frontend JS) can now: rotate CSRF token after save/delete, wire delete button to destroy() endpoint, and render done tasks with visual strike-through
- No blockers

---
*Phase: 09-calend-rio-csrf-exclus-o-e-conclus-o*
*Completed: 2026-03-27*
