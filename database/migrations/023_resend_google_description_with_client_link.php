<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

$pdo = Core\Database::getInstance();

// o evento passou a levar o link da ficha do cliente, e o que ja estava no
// google so ganha o link quando a descricao vai de novo. marca so a descricao
// (bit 2 de google_changed_fields), para o patch nao desfazer titulo nem data
// que mudaram la. a fila envia na proxima abertura do calendario de cada dono.
// cliente arquivado fica de fora, porque o evento dele nao leva o link.
//
// ao contrario das outras, esta roda depois do deploy do codigo que poe o link:
// antes dele, o envio manda a descricao sem o link e zera a marca, e o runner
// nao repete migration registrada. se isso acontecer, apague a linha da 023 em
// _migrations e rode de novo, o que so reenvia a mesma descricao.
//
// o patch leva o status, como toda edicao do crm, e a marca impede a leitura
// de notar a remocao, entao evento apagado no google desde a ultima abertura do
// calendario do dono, com a tarefa ainda aberta, volta para a agenda. abrir o
// calendario do crm logo antes de rodar fecha essa janela.
$marcadas = $pdo->exec("
    UPDATE tasks t
      JOIN clients c ON c.id = t.client_id AND c.tenant_id = t.tenant_id AND c.is_active = 1
       SET t.google_sync_pending = t.google_sync_pending + 1,
           t.google_changed_fields = t.google_changed_fields | 2
     WHERE t.google_event_id IS NOT NULL
       AND t.status IN ('pending', 'in_progress')
       AND t.due_date >= CURDATE()
");

echo "Migration 023: {$marcadas} tarefas abertas com cliente marcadas para reenviar a descrição ao Google.\n";
echo "Migration 023 concluída.\n";
