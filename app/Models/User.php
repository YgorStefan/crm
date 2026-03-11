<?php
// ============================================================
// app/Models/User.php — Model de Usuários
// ============================================================
// Responsável por TODAS as interações com a tabela `users`.
// Herda os métodos genéricos de Core\Model (findById, delete...).
// ============================================================

namespace App\Models;

use Core\Model;

class User extends Model
{
    // Define a tabela que este model gerencia
    protected string $table = 'users';

    /**
     * Busca um usuário pelo endereço de e-mail.
     * Usado no processo de login para localizar o registro antes
     * de verificar a senha com password_verify().
     *
     * @param  string       $email
     * @return array|false  Dados do usuário ou false se não encontrado
     */
    public function findByEmail(string $email): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM users WHERE email = :email AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        return $stmt->fetch();
    }

    /**
     * Cria um novo usuário no banco de dados.
     * A senha já deve vir com hash aplicado pelo Controller
     * (usando password_hash($senha, PASSWORD_BCRYPT)).
     *
     * @param  array  $data  ['name', 'email', 'password_hash', 'role']
     * @return int    ID do usuário criado
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password_hash, role, avatar)
            VALUES (:name, :email, :password_hash, :role, :avatar)
        ");
        $stmt->execute([
            ':name'          => $data['name'],
            ':email'         => $data['email'],
            ':password_hash' => $data['password_hash'],
            ':role'          => $data['role']   ?? 'seller',
            ':avatar'        => $data['avatar'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualiza os dados de um usuário existente.
     * O campo password_hash só é atualizado se for enviado no array $data.
     *
     * @param  int    $id
     * @param  array  $data  Campos a atualizar
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $fields = ['name', 'email', 'role', 'avatar', 'is_active'];
        $setClauses = [];
        $params = [':id' => $id];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $setClauses[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        // Senha só atualizada se explicitamente fornecida
        if (!empty($data['password_hash'])) {
            $setClauses[] = "password_hash = :password_hash";
            $params[':password_hash'] = $data['password_hash'];
        }

        if (empty($setClauses)) {
            return false;
        }

        $sql = "UPDATE users SET " . implode(', ', $setClauses) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Retorna todos os usuários ativos (para dropdowns de "responsável").
     *
     * @return array
     */
    public function findAllActive(): array
    {
        $stmt = $this->db->query("SELECT id, name, email, role FROM users WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll();
    }
}
