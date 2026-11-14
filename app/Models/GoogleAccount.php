<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;

// a chave primaria e user_id, sem coluna id: os findById e delete herdados do
// Core\Model nao servem para esta tabela.
class GoogleAccount extends Model
{
    protected string $table = 'google_accounts';

    public function findForUser(int $userId): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM google_accounts WHERE user_id = :user_id AND tenant_id = :tenant_id LIMIT 1'
        );
        $stmt->execute([':user_id' => $userId, ':tenant_id' => $this->currentTenantId()]);
        return $stmt->fetch();
    }

    /**
     * a mesma regra do upsertConnected, lida antes da troca do code: o papel e
     * o ativo saem do banco, porque a sessao de quem foi rebaixado ou
     * desativado continua dizendo o papel antigo.
     */
    public function userCanConnect(int $userId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM users
             WHERE id = :user_id AND tenant_id = :tenant_id
               AND is_active = 1 AND role IN ('admin', 'seller')"
        );
        $stmt->execute([':user_id' => $userId, ':tenant_id' => $this->currentTenantId()]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * outro usuario do tenant tem a mesma conta google ativa ou revogada. a
     * desconectada fica de fora, porque quem a tinha ja a soltou. o for update
     * vale dentro da transacao de quem chama e segura as contas do tenant ate o
     * fim dela, para duas conexoes ao mesmo tempo nao passarem as duas.
     */
    public function subUsedByOtherUser(int $userId, string $sub): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM google_accounts
             WHERE tenant_id = :tenant_id AND google_sub = :sub AND user_id <> :user_id
               AND status IN ('active', 'revoked')
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute([':tenant_id' => $this->currentTenantId(), ':sub' => $sub, ':user_id' => $userId]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * @return bool  false quando o usuario nao pode conectar e nada foi gravado
     */
    public function upsertConnected(
        int $userId,
        string $email,
        string $sub,
        string $encRefresh,
        string $encAccess,
        string $expiresAt,
    ): bool {
        // o on duplicate key nao aceita where, entao o tenant e conferido na
        // origem: so entra usuario do tenant da sessao, e a chave e o user_id.
        // o papel e o ativo tambem saem do banco, porque a sessao de quem foi
        // rebaixado ou desativado continua dizendo o papel antigo.
        $stmt = $this->db->prepare(
            "INSERT INTO google_accounts
                (user_id, tenant_id, google_email, google_sub, refresh_token, access_token,
                 access_token_expires_at, status)
             SELECT u.id, u.tenant_id, :email, :sub, :refresh, :access, :expires, 'active'
             FROM users u
             WHERE u.id = :user_id AND u.tenant_id = :tenant_id
               AND u.is_active = 1 AND u.role IN ('admin', 'seller')
             ON DUPLICATE KEY UPDATE
                google_email = :email_upd, google_sub = :sub_upd,
                refresh_token = :refresh_upd, access_token = :access_upd,
                access_token_expires_at = :expires_upd,
                status = 'active', connected_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([
            ':email'       => $email,
            ':sub'         => $sub,
            ':refresh'     => $encRefresh,
            ':access'      => $encAccess,
            ':expires'     => $expiresAt,
            ':user_id'     => $userId,
            ':tenant_id'   => $this->currentTenantId(),
            ':email_upd'   => $email,
            ':sub_upd'     => $sub,
            ':refresh_upd' => $encRefresh,
            ':access_upd'  => $encAccess,
            ':expires_upd' => $expiresAt,
        ]);

        // o token cifrado muda a cada conexao, porque o iv e aleatorio, entao a
        // linha existente sempre muda e zero linhas so acontece sem usuario apto.
        return $stmt->rowCount() > 0;
    }

    /**
     * so grava se a linha ainda guarda o refresh_token cifrado que a renovacao
     * leu: uma conta google conectada no meio do pedido tem outro texto
     * cifrado, porque o iv e aleatorio, e nao recebe o token da conta antiga.
     */
    public function storeAccessToken(int $userId, ?string $readRefresh, string $encAccess, string $expiresAt): void
    {
        // so conta ativa: uma renovacao que termina depois do desconectar nao
        // pode devolver credencial a uma conta ja limpa.
        $stmt = $this->db->prepare(
            "UPDATE google_accounts SET access_token = :access, access_token_expires_at = :expires
             WHERE user_id = :user_id AND tenant_id = :tenant_id AND status = 'active'
               AND refresh_token <=> :read_refresh"
        );
        $stmt->execute([
            ':access'       => $encAccess,
            ':expires'      => $expiresAt,
            ':user_id'      => $userId,
            ':tenant_id'    => $this->currentTenantId(),
            ':read_refresh' => $readRefresh,
        ]);
    }

    /**
     * o markRevoked da renovacao, que so revoga a conta cujo refresh_token
     * cifrado ainda e o lido, pelo mesmo motivo do storeAccessToken. o <=>
     * casa tambem o refresh_token nulo, que a falha ao decifrar revoga.
     */
    public function markRevokedIfRefreshToken(int $userId, ?string $readRefresh): void
    {
        $stmt = $this->db->prepare(
            "UPDATE google_accounts SET status = 'revoked'
             WHERE user_id = :user_id AND tenant_id = :tenant_id AND status = 'active'
               AND refresh_token <=> :read_refresh"
        );
        $stmt->execute([
            ':user_id'      => $userId,
            ':tenant_id'    => $this->currentTenantId(),
            ':read_refresh' => $readRefresh,
        ]);
    }

    public function markRevoked(int $userId): void
    {
        // conta desconectada nao volta a aparecer como revogada, o que pediria
        // reconexao a quem escolheu sair.
        $stmt = $this->db->prepare(
            "UPDATE google_accounts SET status = 'revoked'
             WHERE user_id = :user_id AND tenant_id = :tenant_id AND status = 'active'"
        );
        $stmt->execute([':user_id' => $userId, ':tenant_id' => $this->currentTenantId()]);
    }

    public function markDisconnected(int $userId): void
    {
        // o google_sub fica, porque a proxima conexao compara com ele para
        // saber se os vinculos das tarefas ainda sao desta conta.
        $stmt = $this->db->prepare(
            "UPDATE google_accounts
             SET refresh_token = NULL, access_token = NULL, access_token_expires_at = NULL,
                 status = 'disconnected'
             WHERE user_id = :user_id AND tenant_id = :tenant_id"
        );
        $stmt->execute([':user_id' => $userId, ':tenant_id' => $this->currentTenantId()]);
    }

    public function acquireDrain(int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE google_accounts SET drain_until = NOW() + INTERVAL 15 SECOND
             WHERE user_id = :user_id AND tenant_id = :tenant_id
               AND (drain_until IS NULL OR drain_until < NOW())"
        );
        $stmt->execute([':user_id' => $userId, ':tenant_id' => $this->currentTenantId()]);
        return $stmt->rowCount() === 1;
    }

    public function releaseDrain(int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE google_accounts SET drain_until = NULL WHERE user_id = :user_id AND tenant_id = :tenant_id'
        );
        $stmt->execute([':user_id' => $userId, ':tenant_id' => $this->currentTenantId()]);
    }

    /** @return list<int> */
    public function userIdsWithWork(): array
    {
        $tenantId = $this->currentTenantId();
        // o filtro de tarefa repete o de Task::pendingForUser, senao o envio
        // travaria contas que nao tem nada que ele mande.
        $stmt = $this->db->prepare(
            "SELECT ga.user_id
             FROM google_accounts ga
             JOIN users u ON u.id = ga.user_id AND u.tenant_id = :tenant_u
             WHERE ga.tenant_id = :tenant_ga
               AND ga.status = 'active'
               AND u.is_active = 1
               AND (
                   EXISTS (
                       SELECT 1 FROM tasks t
                       WHERE t.tenant_id = :tenant_t
                         AND t.assigned_to = ga.user_id
                         AND t.google_sync_pending > 0
                         AND (t.google_event_id IS NOT NULL OR t.due_date >= CURDATE())
                   )
                   OR EXISTS (
                       SELECT 1 FROM google_event_deletions d
                       WHERE d.tenant_id = :tenant_d AND d.user_id = ga.user_id
                   )
               )
             ORDER BY ga.user_id"
        );
        $stmt->execute([
            ':tenant_u'  => $tenantId,
            ':tenant_ga' => $tenantId,
            ':tenant_t'  => $tenantId,
            ':tenant_d'  => $tenantId,
        ]);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }
}
