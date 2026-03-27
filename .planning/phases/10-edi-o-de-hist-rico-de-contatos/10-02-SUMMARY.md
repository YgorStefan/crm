---
phase: 10-edi-o-de-hist-rico-de-contatos
plan: "02"
subsystem: frontend/views
tags: [inline-edit, interactions, notes, csrf, ajax, vanilla-js]
dependency_graph:
  requires: [10-01]
  provides: [CLI-10, CLI-11]
  affects: [app/Views/clients/show.php]
tech_stack:
  added: []
  patterns: [inline-edit-toggle, IIFE-CSRF-AJAX, view/edit state toggle via display:none]
key_files:
  created: []
  modified:
    - app/Views/clients/show.php
decisions:
  - "Cada interação row recebe data-interaction-id para isolar o escopo JS sem IDs globais em conflito"
  - "CLI-10 e CLI-11 usam IIFEs separados cada um com seu próprio let csrfToken — tokens independentes por feature são aceitáveis pois as features operam em sequência (salvar interação não afeta token de notas)"
  - "descEl.textContent usado para re-render pós-save (sem nl2br do lado cliente) — texto simples sem quebras é consistente com a edição via textarea"
metrics:
  duration_minutes: 15
  completed_date: "2026-03-27"
  tasks_completed: 2
  files_modified: 1
---

# Phase 10 Plan 02: Edição Inline de Interações e Notas — Summary

**One-liner:** Inline editing for interaction rows (click-to-edit) and always-visible Notes card with AJAX CSRF-rotated save, both in show.php.

## What Was Built

### Task 1 — CLI-10: Edição inline de interações (commit `75fa716`)

Modified the `foreach ($interactions as $inter)` loop in `app/Views/clients/show.php` to support inline editing:

- Each interaction row now carries `data-interaction-id="<?= $inter['id'] ?>"` for JS targeting
- Two inner states per row: `.inter-view` (default visible) and `.inter-edit` (hidden via `style="display:none"`)
- `.inter-edit` contains: `<select class="inter-edit-type">`, `<input type="datetime-local" class="inter-edit-date">`, `<textarea class="inter-edit-desc">`, `.inter-save-btn`, `.inter-cancel-btn`, `.inter-save-error`
- Delete button receives class `.inter-delete-btn` to allow JS hide/show during edit mode
- Added IIFE script block: `querySelectorAll('[data-interaction-id]')` — click on `.inter-description` activates edit mode; "Cancelar" restores original values without network request; "Salvar" POSTs to `/interactions/{id}/update` with `X-CSRF-Token` header, re-renders row on success, rotates `csrfToken`

### Task 2 — CLI-11: Card de Notas sempre visível + JS (commit `14919fd`)

Replaced the conditional `<?php if (!empty($client['notes'])): ?>` wrapper with an always-rendered card `id="notes-card"`:

- Card structure: header row with "Notas" label + `id="btn-edit-notes"` button always visible
- `id="notes-view"` div: shows `id="notes-text"` paragraph (empty `<p>` when notes is empty, satisfying D-09)
- `id="notes-edit"` div (hidden): `id="notes-textarea"`, `id="notes-save-btn"`, `id="notes-cancel-btn"`, `id="notes-save-error"`
- Added second IIFE script block: click on "Editar Notas" toggles view/edit; "Cancelar" restores; "Salvar" POSTs to `/clients/{clientId}/update-notes` with `X-CSRF-Token` header, updates `notesText.textContent` on success

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None. Both features are fully wired to backend routes from Phase 10-01 (`InteractionController::update()` and `ClientController::updateNotes()`).

## Verification Results

- `php -l app/Views/clients/show.php` — PASSED (no syntax errors)
- `data-interaction-id` present in PHP loop and JS selector — CONFIRMED
- `.inter-save-btn`, `.inter-cancel-btn` present — CONFIRMED
- fetch to `/interactions/` + id + `/update` present — CONFIRMED
- `X-CSRF-Token` header in both fetch calls — CONFIRMED
- `id="btn-edit-notes"`, `id="notes-edit"`, `id="notes-view"` present — CONFIRMED
- fetch to `/clients/` + clientId + `/update-notes` present — CONFIRMED
- `id="notes-card"` always rendered (no conditional wrapper) — CONFIRMED

## Self-Check: PASSED

Files exist:
- `app/Views/clients/show.php` — FOUND (modified)

Commits exist:
- `75fa716` — FOUND (feat(10-02): edição inline de interações CLI-10)
- `14919fd` — FOUND (feat(10-02): card notas sempre visível CLI-11)

## Checkpoint

Task 3 (human-verify): Aprovado pelo usuário em 2026-03-27.
