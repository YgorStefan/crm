# Roadmap: CRM Apollo Evolution

## Milestones

- ✅ **v1.0 MVP** — Phases 1-7 (shipped 2026-03-26)
- [ ] **v1.1 Correções e Edições** — Phases 8-10 (active)

## Phases

<details>
<summary>✅ v1.0 MVP (Phases 1-7) — SHIPPED 2026-03-26</summary>

- [x] Phase 1: Calendário e Tarefas (2/2 plans) — completed 2026-03-25
- [x] Phase 2: Clientes Corporativos e Consórcio (3/3 plans) — completed 2026-03-25
- [x] Phase 3: Controle Financeiro (Pagamentos) (2/2 plans) — completed 2026-03-25
- [x] Phase 4: Pipeline Dinâmico (2/2 plans) — completed 2026-03-26
- [x] Phase 5: Acesso Rápido (1/1 plan) — completed 2026-03-26
- [x] Phase 6: Módulo de Contatos Frios (3/3 plans) — completed 2026-03-26
- [x] Phase 7: Dashboard de Acompanhamento (2/2 plans) — completed 2026-03-26

Full details: `.planning/milestones/v1.0-ROADMAP.md`

</details>

### v1.1 Correções e Edições

- [x] **Phase 8: Bugs e Melhorias de Contatos Frios** - Corrige falha de rede no drag-and-drop Kanban, erro no modal de contatos frios, localiza meses para pt-BR e adiciona exclusão de mês (completed 2026-03-27)
- [ ] **Phase 9: Calendário — CSRF, Exclusão e Conclusão** - Corrige renovação de CSRF entre edições de tarefas e adiciona exclusão e marcação de concluída
- [ ] **Phase 10: Edição de Histórico de Contatos** - Permite editar entradas existentes de interações e anotações na ficha do cliente

## Phase Details

### Phase 8: Bugs e Melhorias de Contatos Frios
**Goal**: Os módulos de Pipeline Kanban e Contatos Frios funcionam sem erros de rede ou carregamento, meses exibidos em pt-BR e exclusão de mês disponível
**Depends on**: Phase 7 (v1.0 shipped)
**Requirements**: BUG-01, BUG-02, CF-08, CF-09
**Success Criteria** (what must be TRUE):
  1. Usuário arrasta card entre colunas do Kanban e a movimentação persiste sem mensagem de erro
  2. Usuário abre modal de contatos frios de qualquer mês após ter realizado importação CSV na mesma sessão e a lista carrega corretamente
  3. Cards de meses exibem o nome do mês em português (ex: "Março 2026", não "March 2026")
  4. Usuário clica em excluir mês, confirma via window.confirm() e o card desaparece da lista sem recarregar a página
  5. Correções não introduzem regressão nas funcionalidades adjacentes
**Plans**: 3 plans

Plans:
- [x] 08-01-PLAN.md — BUG-02: CSRF middleware aceita header X-CSRF-Token (Kanban fix)
- [x] 08-02-PLAN.md — BUG-01: listJson com try/catch + CF-08: meses em pt-BR
- [x] 08-03-PLAN.md — CF-09: exclusão de mês com botão, confirmação e remoção do DOM

### Phase 9: Calendário — CSRF, Exclusão e Conclusão
**Goal**: Usuário pode editar, excluir e concluir tarefas no calendário sem erros, em qualquer sequência de ações na mesma sessão
**Depends on**: Phase 8
**Requirements**: BUG-03, CAL-10, CAL-11
**Success Criteria** (what must be TRUE):
  1. Usuário edita a primeira tarefa na sessão e depois edita uma segunda tarefa sem receber "Erro ao salvar tarefa" (token CSRF renovado entre mutations)
  2. Usuário exclui uma tarefa pelo calendário e ela desaparece do calendário imediatamente, sem recarregar a página
  3. Usuário marca uma tarefa como concluída e ela exibe risco visual (strikethrough) no calendário/modal
  4. Tarefas concluídas não acionam a flag "em atraso" no badge de notificações; tarefas pendentes continuam acionando normalmente
**Plans**: 2 plans

Plans:
- [x] 09-01-PLAN.md — BUG-03 + CAL-10 + CAL-11: backend — update() e destroy() retornam csrf_token; findForCalendar() inclui done
- [ ] 09-02-PLAN.md — BUG-03 + CAL-10 + CAL-11: frontend — renovação CSRF, botões Excluir/Concluída, strikethrough no calendário

### Phase 10: Edição de Histórico de Contatos
**Goal**: Usuário pode corrigir ou atualizar entradas existentes de histórico na ficha do cliente
**Depends on**: Phase 9
**Requirements**: CLI-10, CLI-11
**Success Criteria** (what must be TRUE):
  1. Usuário edita o texto de uma interação existente na ficha do cliente e a alteração persiste ao recarregar a página
  2. Usuário edita o texto de uma anotação existente na ficha do cliente e a alteração persiste ao recarregar a página
  3. A edição usa CSRF e retorna token renovado, permitindo edições consecutivas sem erro
**Plans**: TBD

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Calendário e Tarefas | v1.0 | 2/2 | Complete | 2026-03-25 |
| 2. Clientes Corporativos e Consórcio | v1.0 | 3/3 | Complete | 2026-03-25 |
| 3. Controle Financeiro (Pagamentos) | v1.0 | 2/2 | Complete | 2026-03-25 |
| 4. Pipeline Dinâmico | v1.0 | 2/2 | Complete | 2026-03-26 |
| 5. Acesso Rápido | v1.0 | 1/1 | Complete | 2026-03-26 |
| 6. Módulo de Contatos Frios | v1.0 | 3/3 | Complete | 2026-03-26 |
| 7. Dashboard de Acompanhamento | v1.0 | 2/2 | Complete | 2026-03-26 |
| 8. Bugs de Pipeline e Contatos Frios | v1.1 | 3/3 | Complete   | 2026-03-27 |
| 9. Calendário — CSRF, Exclusão e Conclusão | v1.1 | 0/2 | Not started | - |
| 10. Edição de Histórico de Contatos | v1.1 | 0/? | Not started | - |

### Phase 11: Importação de Contatos Frios via XLS e XLSX

**Goal:** Usuário pode importar contatos frios a partir de arquivos .xls e .xlsx, além do .csv já suportado
**Requirements**: CF-10
**Depends on:** Phase 10
**Success Criteria** (what must be TRUE):
  1. Upload de arquivo .xls ou .xlsx é aceito pelo sistema sem erro
  2. Contatos importados via XLS/XLSX aparecem corretamente na lista mensal
  3. Importação CSV existente continua funcionando sem regressão
**Plans**: TBD
