<?php
// ============================================================
// core/Model.php — Classe Base para todos os Models
// ============================================================
// Todos os Models do sistema (User, Client, Task, etc.) herdam
// desta classe. Ela provê acesso direto ao PDO e métodos
// genéricos que evitam repetição de código (DRY).
//
// Exemplo de herança:
//   class User extends Model { ... }
// ============================================================

namespace Core;

use PDO;

abstract class Model
{
    // Instância PDO compartilhada por todos os models
    protected PDO $db;

    // Nome da tabela no banco (cada model filho deve redefinir esta propriedade)
    // Ex.: protected string $table = 'users';
    protected string $table = '';

    public function __construct()
    {
        // Obtém a conexão singleton ao instanciar qualquer model
        $this->db = Database::getInstance();
    }

    /**
     * Busca um registro pelo ID primário.
     *
     * Usamos prepared statement com parâmetro nomeado (:id) para
     * evitar SQL Injection — o valor nunca é concatenado na string SQL.
     *
     * @param  int   $id  Chave primária do registro
     * @return array|false  Array associativo com o registro, ou false se não encontrado
     */
    public function findById(int $id): array|bool
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
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
}
