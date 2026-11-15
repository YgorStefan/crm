<?php
$safeAppUrl   = htmlspecialchars(APP_URL, ENT_QUOTES, 'UTF-8');
$linkClass    = 'font-medium text-indigo-600 dark:text-indigo-400 underline underline-offset-2 hover:text-indigo-800 dark:hover:text-indigo-300';
$headingClass = 'font-semibold text-gray-900 dark:text-white mb-1';
?>
<div class="w-full max-w-2xl px-4 py-8 sm:py-12">
    <article class="bg-white dark:bg-zinc-900 rounded-2xl shadow-xl overflow-hidden">

        <header class="bg-indigo-600 dark:bg-indigo-700 px-6 py-5 text-center">
            <?php // o nome e o da tela de consentimento do google, que a verificacao da marca confere. ?>
            <h1 class="text-xl font-bold text-white">CRM</h1>
            <p class="text-indigo-200 text-xs mt-1">Clientes, tarefas e vendas da sua equipe</p>
        </header>

        <div class="px-6 sm:px-8 py-6 space-y-5 text-sm leading-relaxed sm:text-base/relaxed text-gray-700 dark:text-zinc-300">

            <section>
                <h2 class="<?= $headingClass ?>">O que é o CRM.</h2>
                <p>Um sistema de gestão de clientes para equipes de vendas. Cada empresa tem o próprio espaço e só acessa os próprios dados, e cada pessoa entra com o e-mail e a senha que o administrador da empresa cadastra.</p>
            </section>

            <section>
                <h2 class="<?= $headingClass ?>">O que ele faz.</h2>
                <p>Guarda os clientes com o histórico de cada contato, mostra o funil de vendas com a etapa de cada negócio, organiza as tarefas da equipe num calendário com lembretes, importa listas de contatos frios, busca empresas para prospectar e acompanha as vendas do mês.</p>
            </section>

            <section>
                <h2 class="<?= $headingClass ?>">Integração com o Google Agenda (opcional).</h2>
                <p>Quem quiser conecta a própria conta Google pelo botão "Conectar Google Agenda", no calendário do CRM. O CRM pede uma permissão só, que o Google mostra como "Ver, criar, mudar e excluir eventos nas suas agendas do Google". Com ela, as suas tarefas viram eventos na sua agenda principal e acompanham o que você muda no CRM, e a data e o título que você muda no Google voltam para a tarefa. Os eventos da sua agenda principal também aparecem no calendário do CRM, onde você pode alterar ou excluir um evento seu.</p>
                <p class="mt-2">Para parar, use "Desconectar" no calendário do CRM ou revogue o acesso em
                    <a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener noreferrer" class="<?= $linkClass ?>">myaccount.google.com/permissions</a>.</p>
            </section>

            <section>
                <h2 class="<?= $headingClass ?>">Privacidade.</h2>
                <p>O que o CRM guarda, inclusive o que recebe do Google, e com quem compartilha está na
                    <a href="<?= $safeAppUrl ?>/privacidade" class="<?= $linkClass ?>">Política de Privacidade</a>.</p>
            </section>

            <section lang="en">
                <h2 class="<?= $headingClass ?>">About this app.</h2>
                <p>CRM is a customer relationship manager for sales teams: clients, sales pipeline, follow-up tasks and a task calendar. Connecting Google Calendar is optional. When users connect it, the app creates, updates and deletes one event in their primary calendar for each of their tasks, shows the events of that calendar in the CRM calendar, and lets them edit or delete their own events from the CRM. Google user data is handled as described in the Privacy Policy linked above.</p>
            </section>
        </div>

        <footer class="border-t border-gray-100 dark:border-zinc-800 px-6 sm:px-8 py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between text-xs text-gray-500 dark:text-zinc-400">
            <a href="<?= $safeAppUrl ?>/privacidade" class="<?= $linkClass ?>">Política de Privacidade</a>
            <a href="<?= $safeAppUrl ?>/login" class="<?= $linkClass ?>">Entrar</a>
        </footer>
    </article>
</div>
