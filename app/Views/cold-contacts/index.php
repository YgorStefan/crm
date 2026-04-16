<?php
?>

<!-- Cabeçalho da página -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h3 class="text-2xl font-bold text-gray-800">Contatos Frios</h3>
        <p class="text-sm text-gray-500 mt-1">Importação e gestão de listas de prospecção</p>
    </div>
</div>

<!-- Formulário de importação -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-8">
    <h4 class="font-semibold text-gray-700 mb-4">Importar lista</h4>
    <p class="text-sm text-gray-500 mb-4">
        O arquivo deve ter: <strong>coluna A = Nome</strong>, <strong>coluna B = Celular</strong>.
        Header é opcional (será ignorado automaticamente se a primeira linha não contiver número no Celular).
        Formatos aceitos: <strong>.csv, .xls, .xlsx</strong>.
    </p>
    <form method="POST" action="<?= APP_URL ?>/cold-contacts/import" enctype="multipart/form-data">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 items-end mb-4">
            <!-- Tipo de lista -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Tipo de lista <span class="text-red-500">*</span>
                </label>
                <input type="text" name="tipo_lista" required maxlength="100"
                    placeholder="Ex: Lista Webinar Jan"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <!-- Upload do arquivo -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Arquivo CSV/XLSX
                </label>
                <input type="file" name="csv_file" accept=".csv,.xls,.xlsx,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required
                    class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            </div>

            <!-- Telefone enviado -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Final Tel. Enviado
                </label>
                <input type="text" name="telefone_enviado" maxlength="4" placeholder="Ex: 1234"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <!-- Data Mensagem -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Data do Envio
                </label>
                <input type="date" name="data_mensagem"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                class="w-full md:w-auto bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2 rounded-lg text-sm transition-colors">
                Importar contatos
            </button>
        </div>
    </form>
</div>

<!-- Cards Mensais -->
<div>
    <h4 class="font-semibold text-gray-700 mb-4">Importações por mês</h4>

    <?php if (empty($summaries)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
            <p class="text-gray-400 text-sm">Nenhuma lista importada ainda. Use o formulário acima para importar seu
                primeiro CSV.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <?php foreach ($summaries as $s): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h5 class="font-semibold text-gray-800 capitalize">
                            <?= htmlspecialchars($s['month_label'], ENT_QUOTES, 'UTF-8') ?>
                        </h5>
                    </div>
                    <div class="px-5 py-4 flex items-center justify-between gap-2">
                        <div>
                            <p class="text-3xl font-bold text-indigo-600"><?= (int) $s['total'] ?></p>
                            <p class="text-xs text-gray-500 mt-0.5">contato(s)</p>
                        </div>
                        <div class="flex flex-col gap-2">
                            <!-- Botão abre modal -->
                            <button type="button"
                                class="btn-open-modal bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-medium px-3 py-2 rounded-lg text-sm transition-colors"
                                data-year-month="<?= htmlspecialchars($s['mes_ano'], ENT_QUOTES, 'UTF-8') ?>"
                                data-month-label="<?= htmlspecialchars($s['month_label'], ENT_QUOTES, 'UTF-8') ?>">
                                Ver lista
                            </button>
                            <!-- Botão excluir mês -->
                            <button type="button"
                                class="btn-delete-month bg-red-50 hover:bg-red-100 text-red-600 font-medium px-3 py-2 rounded-lg text-sm transition-colors"
                                data-year-month="<?= htmlspecialchars($s['mes_ano'], ENT_QUOTES, 'UTF-8') ?>"
                                data-month-label="<?= htmlspecialchars($s['month_label'], ENT_QUOTES, 'UTF-8') ?>">
                                Excluir
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de listagem de contatos -->
<div id="modalColdContacts"
    class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col">

        <!-- Header da modal -->
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
            <h4 id="modalTitle" class="font-semibold text-gray-800 text-lg">Contatos do mês</h4>
            <button id="btnCloseModal"
                class="text-gray-400 hover:text-gray-600 text-2xl font-bold leading-none">&times;</button>
        </div>

        <!-- Filtros e exportação -->
        <div class="px-6 py-3 border-b border-gray-100 flex flex-col sm:flex-row gap-3 items-end flex-shrink-0">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Filtrar por dia (1-31)</label>
                <input type="number" id="filterDia" min="1" max="31" placeholder="Dia"
                    class="w-24 px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Filtrar por tel. enviado</label>
                <input type="text" id="filterTelEnviado" placeholder="Ex: 1234"
                    class="w-32 px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>
            <button id="btnApplyFilter"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-1.5 rounded-lg text-sm transition-colors">
                Filtrar
            </button>
            <button id="btnClearFilter"
                class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium px-4 py-1.5 rounded-lg text-sm transition-colors">
                Limpar
            </button>
            <div class="flex-1"></div>
            <button id="btnExportCsv"
                class="bg-green-600 hover:bg-green-700 text-white font-medium px-4 py-1.5 rounded-lg text-sm transition-colors">
                Exportar CSV
            </button>
        </div>

        <!-- Barra de ação em lote -->
        <div id="bulkBar"
            class="hidden px-6 py-2 bg-indigo-50 border-b border-indigo-100 flex flex-wrap items-center gap-3 flex-shrink-0">
            <span id="bulkCount" class="text-sm font-medium text-indigo-700"></span>
            
            <label class="text-sm text-gray-600 font-medium">Tel:</label>
            <input type="text" id="bulkTelEnviado" maxlength="4" placeholder="Ex: 1234"
                title="Deixe em branco para manter, ou preencha para alterar em todos"
                class="w-20 px-2 py-1 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                
            <label class="text-sm text-gray-600 font-medium ml-2">Data:</label>
            <input type="date" id="bulkDataMensagem"
                title="Deixe em branco para manter, ou preencha para alterar em todos"
                class="w-32 px-2 py-1 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">

            <button id="btnBulkSave"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-1 rounded-lg text-sm transition-colors ml-auto">
                Atualizar Marcados
            </button>
            <button id="btnBulkCancel" class="text-gray-500 hover:text-gray-700 text-sm font-medium">
                Cancelar
            </button>
        </div>

        <!-- Corpo da modal: tabela de contatos -->
        <div id="modalBody" class="flex-1 overflow-y-auto overflow-x-auto px-6 py-4">
            <p class="text-gray-400 text-sm text-center">Carregando...</p>
        </div>

        <!-- Footer da modal: total visível -->
        <div class="px-6 py-3 border-t border-gray-100 flex-shrink-0">
            <p id="modalTotal" class="text-xs text-gray-500"></p>
        </div>
    </div>
</div>

<!-- Injeção do CSRF token e appUrl para o JS -->
<script>
    window.CSRF_TOKEN = '<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>';
    window.APP_URL = '<?= APP_URL ?>';
</script>

<!-- SheetJS para conversão client-side de XLS/XLSX para CSV -->
<script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>

<script>
    // JS sem biblioteca de modal
    (function () {
        const modal = document.getElementById('modalColdContacts');
        const modalBody = document.getElementById('modalBody');
        const modalTitle = document.getElementById('modalTitle');
        const modalTotal = document.getElementById('modalTotal');
        const filterDia = document.getElementById('filterDia');
        const filterTelEnv = document.getElementById('filterTelEnviado');
        const bulkBar = document.getElementById('bulkBar');
        const bulkCount = document.getElementById('bulkCount');
        const bulkTelEnviado = document.getElementById('bulkTelEnviado');
        const bulkDataMensagem = document.getElementById('bulkDataMensagem');

        let currentYearMonth = '';
        let currentMonthLabel = '';

        // ----- Abrir modal ao clicar no card -----
        document.querySelectorAll('.btn-open-modal').forEach(function (btn) {
            btn.addEventListener('click', function () {
                currentYearMonth = this.dataset.yearMonth;
                currentMonthLabel = this.dataset.monthLabel;
                modalTitle.textContent = currentMonthLabel.charAt(0).toUpperCase() + currentMonthLabel.slice(1);
                filterDia.value = '';
                filterTelEnv.value = '';
                hideBulkBar();
                modal.classList.remove('hidden');
                loadContacts();
            });
        });

        // ----- Excluir mês ao clicar no botão Excluir -----
        document.querySelectorAll('.btn-delete-month').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const yearMonth = this.dataset.yearMonth;
                const monthLabel = this.dataset.monthLabel;
                if (!window.confirm('Excluir todos os contatos de ' + monthLabel + '? Esta ação não pode ser desfeita.')) {
                    return;
                }
                const card = this.closest('.bg-white.rounded-xl');
                try {
                    const resp = await fetch(window.APP_URL + '/cold-contacts/month/' + encodeURIComponent(yearMonth) + '/delete', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-CSRF-TOKEN': window.CSRF_TOKEN,
                        },
                        body: '_csrf_token=' + encodeURIComponent(window.CSRF_TOKEN),
                        credentials: 'same-origin',
                    });
                    let data = null;
                    try { data = await resp.json(); } catch (_) {}
                    if (!resp.ok) {
                        if (resp.status === 403) {
                            alert('Sessão expirada ou token inválido. Recarregue a página e tente novamente.');
                        } else {
                            const detail = data && data.debug_error ? '\n\nDetalhe: ' + data.debug_error : '';
                            alert('Erro ao excluir mês (HTTP ' + resp.status + ').' + detail);
                        }
                        return;
                    }
                    if (data && data.success) {
                        if (data.csrf_token) window.CSRF_TOKEN = data.csrf_token;
                        if (card) card.remove();
                    } else {
                        alert('Erro ao excluir: ' + (data.error || 'Tente novamente.'));
                    }
                } catch (e) {
                    alert('Não foi possível excluir o mês. Verifique sua conexão ou recarregue a página e tente novamente.');
                }
            });
        });

        // ----- Fechar modal -----
        document.getElementById('btnCloseModal').addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
        function closeModal() { modal.classList.add('hidden'); hideBulkBar(); }

        // ----- Filtros -----
        document.getElementById('btnApplyFilter').addEventListener('click', loadContacts);
        document.getElementById('btnClearFilter').addEventListener('click', function () {
            filterDia.value = '';
            filterTelEnv.value = '';
            loadContacts();
        });

        // ----- Exportar CSV -----
        document.getElementById('btnExportCsv').addEventListener('click', function () {
            const params = buildParams();
            window.location.href = window.APP_URL + '/cold-contacts/export?month=' + encodeURIComponent(currentYearMonth) + params;
        });

        function buildParams() {
            let p = '';
            const dia = filterDia.value.trim();
            const telEnv = filterTelEnv.value.trim();
            if (dia) p += '&dia=' + encodeURIComponent(dia);
            if (telEnv) p += '&telefone_enviado=' + encodeURIComponent(telEnv);
            return p;
        }

        // ----- Barra de seleção múltipla -----
        function hideBulkBar() {
            bulkBar.classList.add('hidden');
            bulkTelEnviado.value = '';
            bulkDataMensagem.value = '';
        }

        function updateBulkBar() {
            const checked = document.querySelectorAll('.row-check:checked');
            if (checked.length === 0) {
                hideBulkBar();
            } else {
                bulkCount.textContent = checked.length + ' contato(s) selecionado(s)';
                bulkBar.classList.remove('hidden');
            }
        }

        document.getElementById('btnBulkCancel').addEventListener('click', function () {
            document.querySelectorAll('.row-check').forEach(function (cb) { cb.checked = false; });
            hideBulkBar();
        });

        document.getElementById('btnBulkSave').addEventListener('click', async function () {
            const tel = bulkTelEnviado.value.trim();
            const dataMsg = bulkDataMensagem.value.trim();
            
            if (tel && !/^\d{1,4}$/.test(tel)) {
                alert('Tel. enviado deve ser numérico com até 4 dígitos.');
                return;
            }
            if (!tel && !dataMsg) {
                alert('Preencha ao menos um dos campos para atualizar em lote.');
                return;
            }

            const ids = Array.from(document.querySelectorAll('.row-check:checked')).map(function (cb) { return cb.dataset.id; });
            if (ids.length === 0) return;

            const formData = new FormData();
            formData.append('_csrf_token', window.CSRF_TOKEN);
            formData.append('telefone_enviado', tel);
            formData.append('data_mensagem', dataMsg);
            ids.forEach(function (id) { formData.append('ids[]', id); });

            try {
                const resp = await fetch(window.APP_URL + '/cold-contacts/bulk-update', {
                    method: 'POST', body: formData, credentials: 'same-origin'
                });
                const data = await resp.json();
                if (data.success) {
                    if (data.csrf_token) window.CSRF_TOKEN = data.csrf_token;
                    hideBulkBar();
                    loadContacts();
                } else {
                    alert('Erro: ' + (data.error || 'Tente novamente.'));
                }
            } catch (e) {
                alert('Erro de rede. Tente novamente.');
            }
        });

        // ----- Carregar lista via AJAX -----
        async function loadContacts() {
            modalBody.innerHTML = '<p class="text-gray-400 text-sm text-center py-8">Carregando...</p>';
            const params = buildParams();
            const url = window.APP_URL + '/cold-contacts/list?month=' + encodeURIComponent(currentYearMonth) + params;

            try {
                const resp = await fetch(url, { credentials: 'same-origin' });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                const data = await resp.json();
                renderTable(data.contacts || []);
            } catch (e) {
                modalBody.innerHTML = '<p class="text-red-500 text-sm text-center py-8">Erro ao carregar contatos. Tente novamente.</p>';
            }
        }

        // ----- Renderizar tabela de contatos -----
        function renderTable(contacts) {
            modalTotal.textContent = contacts.length + ' contato(s) exibido(s)';

            if (contacts.length === 0) {
                modalBody.innerHTML = '<p class="text-gray-400 text-sm text-center py-8">Nenhum contato encontrado com os filtros aplicados.</p>';
                return;
            }

            let html = '<table class="w-full text-sm">';
            html += '<thead><tr class="border-b border-gray-200">';
            html += '<th class="py-2 px-2"><input type="checkbox" id="checkAll" class="rounded"></th>';
            html += '<th class="text-left py-2 px-2 text-xs font-semibold text-gray-500 uppercase">Celular</th>';
            html += '<th class="text-left py-2 px-2 text-xs font-semibold text-gray-500 uppercase">Nome</th>';
            html += '<th class="text-left py-2 px-2 text-xs font-semibold text-gray-500 uppercase">Tipo de lista</th>';
            html += '<th class="text-left py-2 px-2 text-xs font-semibold text-gray-500 uppercase">Tel. enviado</th>';
            html += '<th class="text-left py-2 px-2 text-xs font-semibold text-gray-500 uppercase">Data mensagem</th>';
            html += '<th class="py-2 px-2"></th>';
            html += '</tr></thead>';
            html += '<tbody id="contactsTableBody">';

            contacts.forEach(function (c) {
                html += renderRow(c, false);
            });

            html += '</tbody></table>';
            modalBody.innerHTML = html;

            // Selecionar todos
            document.getElementById('checkAll').addEventListener('change', function () {
                document.querySelectorAll('.row-check').forEach(function (cb) { cb.checked = this.checked; }, this);
                updateBulkBar();
            });

            // Delegate events para edição, deleção e checkboxes
            document.getElementById('contactsTableBody').addEventListener('click', handleTableClick);
            document.getElementById('contactsTableBody').addEventListener('change', function (e) {
                if (e.target.classList.contains('row-check')) updateBulkBar();
            });
        }

        function renderRow(c, editMode) {
            const id = c.id;
            if (editMode) {
                return '<tr data-id="' + id + '" class="border-b border-gray-100 bg-indigo-50">' +
                    '<td class="py-2 px-2"></td>' +
                    '<td class="py-2 px-2"><input class="w-full border border-gray-300 rounded px-2 py-1 text-xs" name="phone" value="' + esc(c.phone) + '"></td>' +
                    '<td class="py-2 px-2"><input class="w-full border border-gray-300 rounded px-2 py-1 text-xs" name="name" value="' + esc(c.name) + '"></td>' +
                    '<td class="py-2 px-2 text-gray-500 text-xs">' + esc(c.tipo_lista) + '</td>' +
                    '<td class="py-2 px-2"><input class="w-16 border border-gray-300 rounded px-2 py-1 text-xs" name="telefone_enviado" maxlength="4" value="' + esc(c.telefone_enviado || '') + '"></td>' +
                    '<td class="py-2 px-2"><input type="date" class="border border-gray-300 rounded px-2 py-1 text-xs" name="data_mensagem" value="' + esc(c.data_mensagem || '') + '"></td>' +
                    '<td class="py-2 px-2 whitespace-nowrap">' +
                    '<button class="btn-save text-green-600 hover:text-green-800 font-medium text-xs mr-2" data-id="' + id + '">Salvar</button>' +
                    '<button class="btn-cancel text-gray-500 hover:text-gray-700 font-medium text-xs" data-id="' + id + '">Cancelar</button>' +
                    '</td>' +
                    '</tr>';
            }
            return '<tr data-id="' + id + '" class="border-b border-gray-100 hover:bg-gray-50">' +
                '<td class="py-2 px-2"><input type="checkbox" class="row-check rounded" data-id="' + id + '"></td>' +
                '<td class="py-2 px-2 text-gray-800">' + esc(c.phone) + '</td>' +
                '<td class="py-2 px-2 text-gray-800">' + esc(c.name) + '</td>' +
                '<td class="py-2 px-2 text-gray-500 text-xs">' + esc(c.tipo_lista) + '</td>' +
                '<td class="py-2 px-2 text-gray-500 text-xs">' + esc(c.telefone_enviado || '\u2014') + '</td>' +
                '<td class="py-2 px-2 text-gray-500 text-xs">' + (c.data_mensagem ? formatDate(c.data_mensagem) : '\u2014') + '</td>' +
                '<td class="py-2 px-2 whitespace-nowrap">' +
                '<button class="btn-edit text-indigo-600 hover:text-indigo-800 font-medium text-xs mr-2" data-id="' + id + '" data-contact=\'' + JSON.stringify(c).replace(/'/g, '&#39;') + '\'>Editar</button>' +
                '<button class="btn-delete text-red-500 hover:text-red-700 font-medium text-xs" data-id="' + id + '">Excluir</button>' +
                '</td>' +
                '</tr>';
        }

        function handleTableClick(e) {
            const btn = e.target;
            const id = btn.dataset.id;
            if (!id) return;

            if (btn.classList.contains('btn-edit')) {
                const contact = JSON.parse(btn.dataset.contact);
                const row = document.querySelector('#contactsTableBody tr[data-id="' + id + '"]');
                if (row) row.outerHTML = renderRow(contact, true);
                document.getElementById('contactsTableBody').addEventListener('click', handleTableClick);

            } else if (btn.classList.contains('btn-cancel')) {
                loadContacts();

            } else if (btn.classList.contains('btn-save')) {
                const row = document.querySelector('#contactsTableBody tr[data-id="' + id + '"]');
                const data = new FormData();
                data.append('_csrf_token', window.CSRF_TOKEN);
                data.append('phone', row.querySelector('[name="phone"]').value);
                data.append('name', row.querySelector('[name="name"]').value);
                data.append('telefone_enviado', row.querySelector('[name="telefone_enviado"]').value);
                data.append('data_mensagem', row.querySelector('[name="data_mensagem"]').value);
                saveContact(id, data);

            } else if (btn.classList.contains('btn-delete')) {
                if (!confirm('Excluir este contato?')) return;
                deleteContact(id);
            }
        }

        async function saveContact(id, formData) {
            try {
                const resp = await fetch(window.APP_URL + '/cold-contacts/' + id + '/update', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                });
                const data = await resp.json();
                if (data.success) {
                    if (data.csrf_token) window.CSRF_TOKEN = data.csrf_token;
                    loadContacts();
                } else {
                    alert('Erro ao salvar: ' + (data.error || 'Tente novamente.'));
                }
            } catch (e) {
                alert('Erro de rede ao salvar.');
            }
        }

        async function deleteContact(id) {
            const formData = new FormData();
            formData.append('_csrf_token', window.CSRF_TOKEN);
            try {
                const resp = await fetch(window.APP_URL + '/cold-contacts/' + id + '/delete', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                });
                const data = await resp.json();
                if (data.success) {
                    if (data.csrf_token) window.CSRF_TOKEN = data.csrf_token;
                    loadContacts();
                } else {
                    alert('Erro ao excluir: ' + (data.error || 'Tente novamente.'));
                }
            } catch (e) {
                alert('Erro de rede ao excluir.');
            }
        }

        function esc(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function formatDate(dateStr) {
            // Converte YYYY-MM-DD para DD/MM/YYYY
            if (!dateStr) return '\u2014';
            const parts = dateStr.split('-');
            if (parts.length === 3) return parts[2] + '/' + parts[1] + '/' + parts[0];
            return dateStr;
        }
    })();
</script>

<script>
(function () {
    var importForm = document.querySelector('form[action*="cold-contacts/import"]');
    if (!importForm) return;

    var fileInput = importForm.querySelector('input[name="csv_file"]');
    if (!fileInput) return;

    importForm.addEventListener('submit', function (e) {
        var file = fileInput.files[0];
        if (!file) return; // deixa o browser validar 'required'

        var ext = file.name.split('.').pop().toLowerCase();
        if (ext !== 'xls' && ext !== 'xlsx') return; // CSV: submit nativo sem interceptação

        e.preventDefault();

        if (typeof XLSX === 'undefined') {
            alert('Erro: biblioteca de leitura de planilhas não carregou. Verifique sua conexão e recarregue a página.');
            return;
        }

        var reader = new FileReader();
        reader.onload = function (ev) {
            try {
                var workbook = XLSX.read(ev.target.result, { type: 'array' });
                var sheetName = workbook.SheetNames[0];
                if (!sheetName) throw new Error('Planilha vazia ou sem abas.');
                var sheet = workbook.Sheets[sheetName];
                // Converte para CSV com separador vírgula (backend autodetecta)
                var csvString = XLSX.utils.sheet_to_csv(sheet, { FS: ',', RS: '\n' });

                // Converte para File UTF-8 e envia via fetch (evita problemas de DataTransfer API)
                var csvBlob = new Blob([new TextEncoder().encode(csvString)], { type: 'text/csv;charset=utf-8' });
                var csvFile = new File([csvBlob], file.name.replace(/\.(xls|xlsx)$/i, '.csv'), { type: 'text/csv' });

                var fd = new FormData();
                fd.append('_csrf_token', window.CSRF_TOKEN || '');
                fd.append('tipo_lista', (importForm.querySelector('[name="tipo_lista"]') || {value: ''}).value);
                fd.append('telefone_enviado', (importForm.querySelector('[name="telefone_enviado"]') || {value: ''}).value);
                fd.append('data_mensagem', (importForm.querySelector('[name="data_mensagem"]') || {value: ''}).value);
                fd.append('csv_file', csvFile);

                fetch(importForm.action, { method: 'POST', body: fd, redirect: 'follow' })
                    .then(function () {
                        window.location.href = (window.APP_URL || '') + '/cold-contacts';
                    })
                    .catch(function () {
                        alert('Erro ao enviar o arquivo. Tente novamente.');
                    });
            } catch (err) {
                alert('Erro ao ler o arquivo: ' + (err.message || 'Formato inválido. Use .csv, .xls ou .xlsx com coluna A = Nome, coluna B = Celular.'));
            }
        };
        reader.onerror = function () {
            alert('Não foi possível ler o arquivo. Tente novamente.');
        };
        reader.readAsArrayBuffer(file);
    });
})();
</script>