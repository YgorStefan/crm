<?php
$contactEmail = $contactEmail ?? '';

$safeAppUrl       = htmlspecialchars(APP_URL, ENT_QUOTES, 'UTF-8');
$safeContactEmail = htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8');
$linkClass        = 'font-medium text-indigo-600 dark:text-indigo-400 underline underline-offset-2 hover:text-indigo-800 dark:hover:text-indigo-300';
$headingClass     = 'font-semibold text-gray-900 dark:text-white mb-1';
?>
<div class="w-full max-w-2xl px-4 py-8 sm:py-12">
    <article class="bg-white dark:bg-zinc-900 rounded-2xl shadow-xl overflow-hidden">

        <header class="bg-indigo-600 dark:bg-indigo-700 px-6 py-5 text-center">
            <h1 class="text-xl font-bold text-white">Política de Privacidade</h1>
            <p class="text-indigo-200 text-xs mt-1"><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></p>
        </header>

        <div class="px-6 sm:px-8 py-6 space-y-5 text-sm leading-relaxed sm:text-base/relaxed text-gray-700 dark:text-zinc-300">

            <section>
                <h2 class="<?= $headingClass ?>">O que é o CRM.</h2>
                <p>Um sistema de gestão de clientes, tarefas e vendas usado por empresas. Cada empresa só acessa os próprios dados.</p>
            </section>

            <section>
                <h2 class="<?= $headingClass ?>">Dados guardados.</h2>
                <p>Nome, e-mail e senha (guardada só como hash) dos usuários, e os clientes, tarefas e interações que a empresa cadastra.</p>
            </section>

            <section>
                <h2 class="<?= $headingClass ?>">Integração com o Google Agenda (opcional).</h2>
                <p>Ao conectar, o CRM recebe o e-mail e o identificador da sua conta Google e um token de acesso, guardado cifrado. O acesso serve para mostrar no calendário do CRM os eventos da sua agenda principal, criar, alterar e remover na agenda os eventos das suas tarefas, e alterar ou remover um evento da agenda quando você pede pelo CRM. O CRM não lê outras agendas nem guarda a descrição dos eventos do Google.</p>
            </section>

            <section>
                <h2 class="<?= $headingClass ?>">O que não fazemos.</h2>
                <p>Não vendemos nem compartilhamos dados com terceiros, não usamos para publicidade e não usamos para treinar modelos de inteligência artificial. O uso e a transferência, para qualquer outro app, de informações recebidas das APIs do Google seguem a
                    <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener noreferrer" class="<?= $linkClass ?>">Política de Dados do Usuário dos Serviços de API do Google</a>, incluindo os requisitos de Uso Limitado.</p>
            </section>

            <section>
                <h2 class="<?= $headingClass ?>">Guarda e remoção.</h2>
                <p>Desconectar apaga os tokens, e os eventos já criados continuam na sua agenda. Você também pode revogar o acesso em
                    <a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener noreferrer" class="<?= $linkClass ?>">myaccount.google.com/permissions</a>.</p>
            </section>

            <section>
                <h2 class="<?= $headingClass ?>">Contato.</h2>
                <?php if ($contactEmail !== ''): ?>
                <p><a href="mailto:<?= $safeContactEmail ?>" class="<?= $linkClass ?>"><?= $safeContactEmail ?></a></p>
                <?php else: ?>
                <p>Fale com o administrador da sua empresa no CRM.</p>
                <?php endif; ?>
            </section>
        </div>

        <footer class="border-t border-gray-100 dark:border-zinc-800 px-6 sm:px-8 py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between text-xs text-gray-500 dark:text-zinc-400">
            <p>Atualizada em 13/11/2026.</p>
            <a href="<?= $safeAppUrl ?>/login" class="<?= $linkClass ?>">Voltar ao login</a>
        </footer>
    </article>
</div>
