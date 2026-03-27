---
phase: 08-bugs-e-melhorias-de-contatos-frios
plan: 01
subsystem: auth
tags: [csrf, middleware, php, security, kanban, pipeline]

# Dependency graph
requires:
  - phase: 04-pipeline-dinamico
    provides: Pipeline Kanban com drag-and-drop e JS pipeline.js enviando fetch POST
provides:
  - CsrfMiddleware com suporte a header X-CSRF-Token além de $_POST (BUG-02 corrigido)
affects:
  - Qualquer endpoint protegido por CsrfMiddleware que receba fetch/XHR com JSON body

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "CSRF header fallback: HTTP_X_CSRF_TOKEN tem prioridade sobre $_POST['_csrf_token'] — compatível com fetch/XHR e formulários"

key-files:
  created: []
  modified:
    - core/Middleware/CsrfMiddleware.php

key-decisions:
  - "CsrfMiddleware aceita token via header X-CSRF-Token (prioridade) com fallback para $_POST['_csrf_token'] — backward-compatible com todos os formulários existentes"

patterns-established:
  - "Header HTTP_X_CSRF_TOKEN lido via $_SERVER['HTTP_X_CSRF_TOKEN'] — padrão para futuros endpoints com JSON body"

requirements-completed: [BUG-02]

# Metrics
duration: 5min
completed: 2026-03-26
---

# Phase 08 Plan 01: Bug BUG-02 — CSRF Header Support Summary

**CsrfMiddleware agora aceita token via header X-CSRF-Token além de $_POST, corrigindo o drag-and-drop do Kanban que falhava com "Falha de rede" em requisições JSON**

## Performance

- **Duration:** 5 min
- **Started:** 2026-03-26T00:00:27Z
- **Completed:** 2026-03-26T00:05:27Z
- **Tasks:** 1
- **Files modified:** 1

## Accomplishments

- CsrfMiddleware.php modificado para ler token CSRF do header `X-CSRF-Token` como fonte primária
- Fallback para `$_POST['_csrf_token']` preservado — nenhum formulário existente quebrado
- Bug BUG-02 resolvido: Pipeline Kanban drag-and-drop persiste movimentações sem erro de rede

## Task Commits

Each task was committed atomically:

1. **Task 1: Adicionar suporte a header X-CSRF-Token no CsrfMiddleware** - `42dd914` (fix)

**Plan metadata:** (docs commit — ver abaixo)

## Files Created/Modified

- `core/Middleware/CsrfMiddleware.php` - Adicionado fallback `HTTP_X_CSRF_TOKEN` → `$_POST['_csrf_token']` no método `handle()`

## Decisions Made

- CsrfMiddleware aceita token via header HTTP (prioridade) com fallback para campo POST — garante compatibilidade com fetch/XHR que envia `Content-Type: application/json` (onde `$_POST` fica vazio) e com formulários HTML tradicionais.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Known Stubs

None.

## Next Phase Readiness

- BUG-02 corrigido, Kanban funcional
- Pronto para execução do Plan 08-02 (bug modal contatos frios) e 08-03 (bug CSRF calendário)

---
*Phase: 08-bugs-e-melhorias-de-contatos-frios*
*Completed: 2026-03-26*
