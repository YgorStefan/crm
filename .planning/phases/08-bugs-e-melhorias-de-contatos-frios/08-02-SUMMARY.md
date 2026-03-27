---
phase: 08-bugs-e-melhorias-de-contatos-frios
plan: 02
subsystem: cold-contacts
tags: [bug-fix, localization, json-safety, php]
dependency_graph:
  requires: []
  provides: [BUG-01-fix, CF-08-ptbr-months]
  affects: [app/Controllers/ColdContactController.php, app/Models/ColdContact.php]
tech_stack:
  added: []
  patterns: [try/catch Throwable para JSON endpoints, mapeamento pt-BR hardcoded em PHP]
key_files:
  created: []
  modified:
    - app/Controllers/ColdContactController.php
    - app/Models/ColdContact.php
decisions:
  - "try/catch \\Throwable em listJson garante JSON válido mesmo em exceptions PHP — evita que HTML de erro polua a resposta AJAX"
  - "month_label calculado em PHP com array hardcoded — independente do locale MySQL do Hostinger (inglês por padrão)"
metrics:
  duration_minutes: 2
  completed_date: "2026-03-27"
  tasks_completed: 2
  files_modified: 2
---

# Phase 08 Plan 02: BUG-01 + CF-08 — listJson try/catch e meses pt-BR Summary

**One-liner:** try/catch \\Throwable em listJson garante JSON sempre válido (BUG-01) e meses pt-BR via array PHP hardcoded independente do locale MySQL (CF-08).

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | Proteger listJson com try/catch — BUG-01 | b9051f0 | app/Controllers/ColdContactController.php |
| 2 | Nomes de meses em pt-BR via mapeamento PHP — CF-08 | 3762129 | app/Models/ColdContact.php, app/Controllers/ColdContactController.php |

## What Was Built

### Task 1 — BUG-01: listJson sempre retorna JSON válido

O método `listJson()` não tinha proteção contra exceptions. Se o model falhasse (ex: logo após uma importação CSV quando a sessão PDO estava em estado inesperado), o PHP emitia HTML de erro antes do `Content-Type: application/json`, resultando em resposta mista que o `JSON.parse()` do frontend rejeitava com "Erro ao carregar contatos".

Solução: envolver o corpo executável de `listJson` em `try { ... } catch (\Throwable $e)`. Em erro, retorna HTTP 500 + `{"contacts": [], "error": "Erro ao carregar contatos."}`.

### Task 2 — CF-08: Nomes de meses em pt-BR

`findMonthSummaries()` usava `DATE_FORMAT(imported_at, '%M %Y') AS month_label` que retorna nomes em inglês no Hostinger (locale MySQL padrão = inglês). Cards exibiam "March 2026" em vez de "Março 2026".

Solução em duas partes:
1. **Model**: removido `DATE_FORMAT(..., '%M %Y') AS month_label` — query agora retorna apenas `mes_ano` (YYYY-MM) e `total`.
2. **Controller**: `index()` itera sobre os resultados e adiciona `month_label` usando array PHP hardcoded com os 12 meses em pt-BR. Ex: `"Março 2026"`.

A view (`app/Views/cold-contacts/index.php`) não foi alterada — já consumia `$s['month_label']` diretamente.

## Decisions Made

| Decision | Rationale |
|----------|-----------|
| try/catch \\Throwable (não Exception) | Captura errors PHP 7+ além de exceptions — mais robusto para ambiente compartilhado |
| month_label em PHP, não MySQL | Locale MySQL no Hostinger é inglês — PHP é sempre consistente independente de configuração de servidor |
| Array de meses hardcoded (não setlocale) | setlocale() é não-thread-safe e depende do locale do SO do servidor — array explícito é determinístico |

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None — todos os dados são reais (banco MySQL).

## Self-Check: PASSED

Files modified:
- FOUND: /c/Users/Ygor/crm/app/Controllers/ColdContactController.php
- FOUND: /c/Users/Ygor/crm/app/Models/ColdContact.php

Commits:
- FOUND: b9051f0 (fix(08-02): proteger listJson com try/catch — BUG-01)
- FOUND: 3762129 (feat(08-02): nomes de meses em pt-BR via mapeamento PHP — CF-08)
