<?php /** View: admin/users/edit.php */ ?>
<div class="max-w-lg mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= APP_URL ?>/admin/users" class="text-gray-400 hover:text-gray-600">← Usuários</a>
        <h3 class="text-2xl font-bold text-gray-800">Editar: <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></h3>
    </div>
    <form method="POST" action="<?= APP_URL ?>/admin/users/<?= $user['id'] ?>/update"
          class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <div class="px-6 py-5 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                <input type="text" name="name" required value="<?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                <input type="email" name="email" required value="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nova Senha <span class="text-xs text-gray-400">(deixe em branco para não alterar)</span></label>
                <input type="password" name="password" minlength="<?= MIN_PASSWORD_LENGTH ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Perfil</label>
                <select name="role" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="seller" <?= $user['role'] === 'seller' ? 'selected' : '' ?>>💼 Vendedor</option>
                    <option value="admin"  <?= $user['role'] === 'admin'  ? 'selected' : '' ?>>🔑 Administrador</option>
                    <option value="viewer" <?= $user['role'] === 'viewer' ? 'selected' : '' ?>>👁️ Leitor</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="is_active" value="1"
                       <?= $user['is_active'] ? 'checked' : '' ?>
                       class="w-4 h-4 rounded text-indigo-600">
                <label for="is_active" class="text-sm text-gray-700">Usuário ativo</label>
            </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 flex justify-between gap-3">
            <form method="POST" action="<?= APP_URL ?>/admin/users/<?= $user['id'] ?>/delete"
                  onsubmit="return confirm('Desativar este usuário?')">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="px-4 py-2 border border-red-300 text-red-600 rounded-lg text-sm hover:bg-red-50 transition-colors">
                    Desativar
                </button>
            </form>
            <div class="flex gap-3">
                <a href="<?= APP_URL ?>/admin/users" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-100 transition-colors">Cancelar</a>
                <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm transition-colors">Salvar</button>
            </div>
        </div>
    </form>
</div>
