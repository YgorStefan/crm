---
phase: 09-calend-rio-csrf-exclus-o-e-conclus-o
plan: 02
subsystem: ui
tags: [fullcalendar, csrf, vanilla-js, ajax, tailwindcss]

requires:
  - phase: 09-01
    provides: "update() and destroy() return csrf_token; findForCalendar() includes done tasks with extendedProps.status; DELETE route registered"

provides:
  - "let csrfToken renewed from data.csrf_token after every save/delete/toggle mutation (BUG-03 fixed)"
  - "btnDeleteTask: confirm + fetch POST /tasks/{id}/delete + calendar.getEventById(id).remove() without page reload (CAL-10)"
  - "btnToggleDone: fetch POST /tasks/{id}/update with status=done|pending + refetchEvents (CAL-11)"
  - "taskActionBtns visible only when editing existing task (task_id filled)"
  - "eventDidMount applies line-through and opacity:0.6 to done tasks in calendar"
  - "dayMaxEvents:false — no '+N mais' collapse in calendar day cells"

affects: [calendar, tasks-ui, csrf-session]

tech-stack:
  added: []
  patterns:
    - "csrfToken as let variable updated from JSON response — same pattern as cold-contacts window.CSRF_TOKEN"
    - "calendar.getEventById(id).remove() for optimistic DOM removal without refetch"
    - "dataset.nextStatus on toggle button tracks next state client-side"
    - "style=display:none!important on action div overridden by .style.display='flex' in JS for edit mode"

key-files:
  created: []
  modified:
    - app/Views/tasks/index.php

key-decisions:
  - "taskActionBtns hidden via inline style!important; JS overrides with .style.display='flex' — avoids CSS specificity issues with Tailwind"
  - "Delete removes event via calendar.getEventById(id).remove() for immediate DOM feedback without full refetch"
  - "Toggle done uses refetchEvents() (not remove+add) because done tasks need visual decoration applied by eventDidMount"
  - "dayMaxEvents:false prevents FullCalendar from collapsing events — all tasks visible regardless of count"

patterns-established:
  - "Pattern: modal action buttons hidden at HTML level, shown in JS edit handler — no flicker"
  - "Pattern: CSRF token renewed from every mutation JSON response, stored in let variable"

requirements-completed: [BUG-03, CAL-10, CAL-11]

duration: 1min
completed: 2026-03-27
---

# Phase 09 Plan 02: Calendario CSRF + Excluir + Concluir Summary

**CSRF token rotation after each save/delete/toggle, Excluir/Concluida modal buttons, and eventDidMount strikethrough for done tasks in FullCalendar**

## Performance

- **Duration:** 1 min
- **Started:** 2026-03-27T15:25:18Z
- **Completed:** 2026-03-27T15:27:37Z
- **Tasks:** 2
- **Files modified:** 1

## Accomplishments

- Fixed BUG-03: csrfToken is now `let` and renewed from `data.csrf_token` after every AJAX mutation — editing multiple tasks in the same session no longer produces CSRF errors
- Implemented CAL-10: Excluir button in task modal sends POST to /tasks/{id}/delete, removes event from calendar via `calendar.getEventById(id).remove()` without page reload, csrf_token renewed
- Implemented CAL-11: Concluida/Reabrir toggle button updates task status (done/pending), calendar refetches and `eventDidMount` applies line-through + opacity:0.6 to done tasks
- Added `dayMaxEvents: false` so all calendar events are shown without the "+N mais" overflow collapse

## Task Commits

Each task was committed atomically:

1. **Task 1: Renovacao de CSRF + botoes Excluir e Concluida/Reabrir no modal** - `52fe755` (feat)
2. **Task 2: FullCalendar — strikethrough em tarefas done + dayMaxEvents: false** - `4cc6f2c` (feat)

## Files Created/Modified

- `app/Views/tasks/index.php` — Modal footer restructured with taskActionBtns div; csrfToken changed to let with renewal; openEditTaskModal shows action buttons and sets toggle state; openNewTaskModal hides action buttons; btnDeleteTask and btnToggleDone event handlers added; FullCalendar config extended with dayMaxEvents:false and eventDidMount

## Decisions Made

- Used `style="display:none!important"` on taskActionBtns in HTML and overridden with `.style.display='flex'` in JS to reliably show/hide without Tailwind specificity conflicts
- Delete handler uses `calendar.getEventById(id).remove()` for immediate optimistic removal (no refetch needed since event is gone from server)
- Toggle done uses `calendar.refetchEvents()` because eventDidMount must run on the re-rendered event to apply strikethrough styling
- No client-side `eventOrder` needed — backend ORDER BY CASE pushes done tasks after pending ones in feed JSON

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 09 complete: CSRF bug fixed, delete and done-toggle features shipped
- Done tasks excluded from overdue/upcoming counters (verified in Plan 01 backend — findOverdue, findUpcoming, countPending already filter by status != 'done')
- Phase 11 (Importacao de Contatos Frios via XLS e XLSX) can proceed independently

---
*Phase: 09-calend-rio-csrf-exclus-o-e-conclus-o*
*Completed: 2026-03-27*
