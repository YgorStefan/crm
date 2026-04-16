<?php

namespace Core;

use PDO;

abstract class Model
{
    // Instância PDO compartilhada por todos os models
    protected PDO $db;

    // Nome da tabela no banco (cada model filho deve redefinir esta propriedade)
    protected string $table = '';

    // Modelos globais (tabelas sem tenant_id) devem sobrescrever com true
    protected bool $isGlobal = false;

    public function __construct()
    {
        // Obtém a conexão singleton ao instanciar qualquer model
        $this->db = Database::getInstance();
    }

    /**
     * Busca um registro pelo ID primário, com escopo de tenant para modelos não-globais.
     *
     * @param  int   $id  Chave primária do registro
     * @return array|false  Array associativo com o registro, ou false se não encontrado
     * @throws \RuntimeException  Se chamado sem contexto de tenant em modelo não-global
     */
    public function findById(int $id): array|bool
    {
        if ($this->isGlobal) {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            return $stmt->fetch();
        }
        if (!isset($_SESSION['tenant_id'])) {
            throw new \RuntimeException('findById() called without tenant context on non-global model');
        }
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE id = :id AND tenant_id = :tenant_id LIMIT 1"
        );
        $stmt->execute([':id' => $id, ':tenant_id' => (int) $_SESSION['tenant_id']]);
        return $stmt->fetch();
    }

    /**
     * Retorna todos os registros da tabela.
     *
     * @return array  Array de arrays associativos (pode ser vazio)
     */
    public function findAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll();
    }

    /**
     * Deleta um registro pelo ID.
     *
     * @param  int  $id  Chave primária do registro a ser removido
     * @return bool  true se deletou ao menos 1 linha, false caso contrário
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Retorna o ID gerado pelo último INSERT executado.
     * Útil logo após chamar um método create() em um model filho.
     *
     * @return string  ID como string (compatível com IDs grandes)
     */
    public function lastInsertId(): string
    {
        return $this->db->lastInsertId();
    }

    /**
     * Retorna o tenant_id da sessão atual.
     *
     * @return int
     */
    protected function currentTenantId(): int
    {
        return (int) ($_SESSION['tenant_id'] ?? 0);
    }
}
