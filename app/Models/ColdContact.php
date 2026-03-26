<?php
// ============================================================
// app/Models/ColdContact.php — Model de Contatos Frios
// ============================================================

namespace App\Models;

use Core\Model;

class ColdContact extends Model
{
    protected string $table = 'cold_contacts';

    /**
     * Retorna contatos agrupados por mês/ano de importação.
     * Cada linha: year_month (YYYY-MM), month_label (Ex: "Março 2026"), total
     * Ordenado do mais recente para o mais antigo.
     */
    public function findMonthSummaries(): array
    {
        $sql = "
            SELECT
                DATE_FORMAT(imported_at, '%Y-%m')         AS mes_ano,
                DATE_FORMAT(imported_at, '%M %Y')         AS month_label,
                COUNT(*)                                   AS total
            FROM cold_contacts
            GROUP BY DATE_FORMAT(imported_at, '%Y-%m'), DATE_FORMAT(imported_at, '%M %Y')
            ORDER BY DATE_FORMAT(imported_at, '%Y-%m') DESC
        ";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Busca contatos de um mês específico (YYYY-MM) com filtros opcionais.
     * Filtro dia: filtra por dia do campo data_mensagem (1-31).
     * Filtro phone: busca parcial no campo phone do contato.
     * Ordenado por id ASC (ordem de importação).
     */
    public function findByMonth(string $yearMonth, array $filters = []): array
    {
        $sql = "
            SELECT *
            FROM cold_contacts
            WHERE DATE_FORMAT(imported_at, '%Y-%m') = :year_month
        ";
        $params = [':year_month' => $yearMonth];

        if (!empty($filters['dia'])) {
            $sql .= " AND DAY(data_mensagem) = :dia";
            $params[':dia'] = (int) $filters['dia'];
        }

        if (!empty($filters['telefone_enviado'])) {
            $sql .= " AND telefone_enviado LIKE :telefone_enviado";
            $params[':telefone_enviado'] = '%' . $filters['telefone_enviado'] . '%';
        }

        $sql .= " ORDER BY id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Insere um contato frio.
     * Retorna o ID inserido.
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO cold_contacts (phone, name, tipo_lista, telefone_enviado, data_mensagem, imported_at)
            VALUES (:phone, :name, :tipo_lista, :telefone_enviado, :data_mensagem, NOW())
        ");
        $stmt->execute([
            ':phone'            => $data['phone'],
            ':name'             => $data['name'],
            ':tipo_lista'       => $data['tipo_lista'],
            ':telefone_enviado' => $data['telefone_enviado'] ?? null,
            ':data_mensagem'    => $data['data_mensagem']    ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualiza campos editáveis de um contato.
     * Campos editáveis: phone, name, telefone_enviado, data_mensagem (D-15).
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE cold_contacts
            SET phone            = :phone,
                name             = :name,
                telefone_enviado = :telefone_enviado,
                data_mensagem    = :data_mensagem
            WHERE id = :id
        ");
        $stmt->execute([
            ':phone'            => $data['phone'],
            ':name'             => $data['name'],
            ':telefone_enviado' => $data['telefone_enviado'] ?? null,
            ':data_mensagem'    => $data['data_mensagem']    ?? null,
            ':id'               => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Deleta um contato pelo ID.
     */
    public function destroy(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM cold_contacts WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Atualiza telefone_enviado em lote para os IDs fornecidos.
     * Retorna número de linhas afetadas.
     */
    public function bulkSetTelefoneEnviado(array $ids, string $telefone): int
    {
        if (empty($ids)) return 0;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "UPDATE cold_contacts SET telefone_enviado = ? WHERE id IN ($placeholders)"
        );
        $stmt->execute(array_merge([$telefone], array_map('intval', $ids)));
        return $stmt->rowCount();
    }

    /**
     * Alias findByMonth para exportação — mesma query, usada pelo export endpoint.
     */
    public function findForExport(string $yearMonth, array $filters = []): array
    {
        return $this->findByMonth($yearMonth, $filters);
    }

    /**
     * Retorna estatísticas semanais de importação e abordagem das últimas $weeks semanas.
     * Estrutura: ['semanas' => ['dd/MM', ...], 'listas' => ['Todos' => [...], 'NomeLista' => [...], ...]]
     * Cada lista tem 'importados' e 'abordados', arrays de $weeks inteiros (zeros incluídos).
     */
    public function weeklyStats(int $weeks = 4): array
    {
        // 1. Calcular o inicio da semana atual (segunda-feira)
        $today  = new \DateTimeImmutable('today');
        $monday = $today->modify('monday this week');

        // 2. Montar array de semanas (mais antiga primeiro)
        $semanas = [];
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $inicio    = $monday->modify("-{$i} weeks");
            $semanas[] = ['start' => $inicio->format('Y-m-d'), 'label' => $inicio->format('d/m')];
        }

        $desde = $semanas[0]['start'];

        // 3. Buscar importados agrupados por semana e tipo_lista
        $stmtImp = $this->db->prepare("
            SELECT
                DATE_FORMAT(DATE_SUB(imported_at, INTERVAL WEEKDAY(imported_at) DAY), '%Y-%m-%d') AS week_start,
                tipo_lista,
                COUNT(*) AS total
            FROM cold_contacts
            WHERE imported_at >= :desde
            GROUP BY week_start, tipo_lista
        ");
        $stmtImp->execute([':desde' => $desde]);
        $rowsImportados = $stmtImp->fetchAll();

        // 4. Buscar abordados (data_mensagem IS NOT NULL) agrupados por semana e tipo_lista
        $stmtAbord = $this->db->prepare("
            SELECT
                DATE_FORMAT(DATE_SUB(data_mensagem, INTERVAL WEEKDAY(data_mensagem) DAY), '%Y-%m-%d') AS week_start,
                tipo_lista,
                COUNT(*) AS total
            FROM cold_contacts
            WHERE data_mensagem IS NOT NULL
              AND data_mensagem >= :desde
            GROUP BY week_start, tipo_lista
        ");
        $stmtAbord->execute([':desde' => $desde]);
        $rowsAbordados = $stmtAbord->fetchAll();

        // 5. Buscar tipo_lista distintos
        $stmtTipos = $this->db->query("
            SELECT DISTINCT tipo_lista FROM cold_contacts WHERE tipo_lista IS NOT NULL AND tipo_lista != '' ORDER BY tipo_lista ASC
        ");
        $tiposLista = $stmtTipos->fetchAll(\PDO::FETCH_COLUMN);

        // 6. Montar estrutura de retorno
        $labels = array_column($semanas, 'label');

        $listas = ['Todos' => ['importados' => [], 'abordados' => []]];
        foreach ($tiposLista as $tipo) {
            $listas[$tipo] = ['importados' => [], 'abordados' => []];
        }

        // Preencher zeros para cada semana
        foreach ($semanas as $sem) {
            $listas['Todos']['importados'][] = 0;
            $listas['Todos']['abordados'][]  = 0;
            foreach ($tiposLista as $tipo) {
                $listas[$tipo]['importados'][] = 0;
                $listas[$tipo]['abordados'][]  = 0;
            }
        }

        // Mapa de indices de semana: ['YYYY-MM-DD' => indice]
        $semanaIdx = array_flip(array_column($semanas, 'start'));

        // Aplicar dados de importados
        foreach ($rowsImportados as $row) {
            $idx = $semanaIdx[$row['week_start']] ?? null;
            if ($idx === null) continue;
            $listas['Todos']['importados'][$idx] += (int) $row['total'];
            if (isset($listas[$row['tipo_lista']])) {
                $listas[$row['tipo_lista']]['importados'][$idx] = (int) $row['total'];
            }
        }

        // Aplicar dados de abordados
        foreach ($rowsAbordados as $row) {
            $idx = $semanaIdx[$row['week_start']] ?? null;
            if ($idx === null) continue;
            $listas['Todos']['abordados'][$idx] += (int) $row['total'];
            if (isset($listas[$row['tipo_lista']])) {
                $listas[$row['tipo_lista']]['abordados'][$idx] = (int) $row['total'];
            }
        }

        return ['semanas' => $labels, 'listas' => $listas];
    }
}
