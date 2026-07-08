import { execFileSync } from 'node:child_process';
import path from 'node:path';

/**
 * Roda uma vez antes de toda a suíte E2E. Garante, via PHP+PDO (mesma
 * conexão usada pela aplicação — sem depender do cliente `mysql`), os
 * usuários fixos que os specs esperam encontrar:
 *   - admin@crm.local  → sem password_must_change (login direto nos specs gerais)
 *   - forced@crm.local → COM password_must_change (spec dedicado de troca obrigatória)
 * Assume que o schema já foi importado e migrado (ver README / CI workflow).
 */
export default function globalSetup(): void {
  const seedPath = path.join(__dirname, 'seed.php');
  execFileSync('php', [seedPath], { stdio: 'inherit' });
}
