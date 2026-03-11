<?php /** View: admin/users/create.php */ ?>
<div class="max-w-lg mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= APP_URL ?>/admin/users" class="text-gray-400 hover:text-gray-600">← Usuários</a>
        <h3 class="text-2xl font-bold text-gray-800">Novo Usuário</h3>
    </div>
    <form method="POST" action="<?= APP_URL ?>/admin/users/store"
          class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <div class="px-6 py-5 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome <span class="text-red-500">*</span></label>
                <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">E-mail <span class="text-red-500">*</span></label>
                <input type="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Senha <span class="text-red-500">*</span> <span class="text-xs text-gray-400">(mín. <?= MIN_PASSWORD_LENGTH ?> caracteres)</span></label>
                <input type="password" name="password" required minlength="<?= MIN_PASSWORD_LENGTH ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Perfil</label>
                <select name="role" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="seller">💼 Vendedor</option>
                    <option value="admin">🔑 Administrador</option>
                    <option value="viewer">👁️ Leitor</option>
                </select>
            </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3">
            <a href="<?= APP_URL ?>/admin/users" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-100 transition-colors">Cancelar</a>
            <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm transition-colors">Criar Usuário</button>
        </div>
    </form>
</div>
