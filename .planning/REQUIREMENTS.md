# Requirements: CRM Apollo Evolution — v1.1

**Defined:** 2026-03-26
**Milestone:** v1.1 Correções e Edições
**Core Value:** Visibilidade total e organização do funil de vendas e rotina comercial, garantindo que nenhum follow-up, tarefa ou pagamento de cliente seja esquecido.

## v1.1 Requirements

### Bugs Críticos (BUG)

- [x] **BUG-01**: O sistema carrega a lista de contatos frios corretamente ao abrir o modal de um mês após ter realizado importação CSV na mesma sessão.
- [x] **BUG-02**: O sistema processa o arrastar de cards entre colunas do Pipeline Kanban sem retornar erro de "Falha de rede".
- [x] **BUG-03**: O sistema permite editar múltiplas tarefas no calendário na mesma sessão sem retornar "Erro ao salvar tarefa" na segunda edição (token CSRF renovado corretamente entre mutations).

### Calendário & Tarefas (CAL)

- [x] **CAL-10**: Usuário pode excluir uma tarefa diretamente pelo calendário (via modal de edição ou evento).
- [x] **CAL-11**: Usuário pode marcar uma tarefa como concluída; tarefas concluídas exibem risco visual (strikethrough) e são excluídas do cálculo da flag "em atraso" — tarefas pendentes continuam acionando a flag normalmente.

### Contatos Frios (CF)

- [x] **CF-08**: Nomes dos meses nos cards de contatos frios são exibidos em português (pt-BR), não em inglês gerado pelo MySQL.
- [x] **CF-09**: Usuário pode excluir todos os contatos de um mês clicando no card; o sistema pede confirmação via `window.confirm()` antes de deletar; após confirmação o card desaparece da lista.

### Histórico de Contatos (CLI)

- [x] **CLI-10**: Usuário pode editar uma entrada existente de registro de interação na ficha do cliente.
- [x] **CLI-11**: Usuário pode editar uma entrada existente de anotação na ficha do cliente.

### Importação de Contatos Frios (CF)

- [ ] **CF-10**: Usuário pode importar contatos frios a partir de arquivos `.xls` e `.xlsx` além de `.csv` — mesma interface de upload, suporte a múltiplos formatos.

## Future Requirements

*(Itens identificados mas fora do escopo de v1.1)*

- Aniversários no sistema de notificações (estrutura pronta em CAL-09, aguarda dados)

## Out of Scope

| Feature | Reason |
|---------|--------|
| Integrações em tempo real (WebSockets) | Infraestrutura Hostinger não suporta |
| App mobile | Web responsiva cobre o caso de uso atual |

## Traceability

| REQ-ID | Phase | Status |
|--------|-------|--------|
| BUG-01 | Phase 8 | Complete |
| BUG-02 | Phase 8 | Complete |
| CF-08 | Phase 8 | Complete |
| CF-09 | Phase 8 | Complete |
| BUG-03 | Phase 9 | Complete |
| CAL-10 | Phase 9 | Complete |
| CAL-11 | Phase 9 | Complete |
| CLI-10 | Phase 10 | Complete |
| CLI-11 | Phase 10 | Complete |
| CF-10 | Phase 11 | Pending |
