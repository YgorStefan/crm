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
// rodar duas vezes so reenvia a mesma descricao.
// so roda depois do deploy do codigo que poe o link: antes dele, o envio manda
// a descricao sem o link e zera a marca. o patch leva o status, como toda edicao
// do crm, entao evento apagado no google desde a ultima abertura do calendario
// do dono, com a tarefa ainda aberta, volta para a agenda.
$marcadas = $pdo->exec("
    UPDATE tasks
       SET google_sync_pending = google_sync_pending + 1,
           google_changed_fields = google_changed_fields | 2
     WHERE google_event_id IS NOT NULL
       AND client_id IS NOT NULL
       AND status IN ('pending', 'in_progress')
       AND due_date >= CURDATE()
");

echo "Migration 023: {$marcadas} tarefas abertas com cliente marcadas para reenviar a descrição ao Google.\n";
echo "Migration 023 concluída.\n";
