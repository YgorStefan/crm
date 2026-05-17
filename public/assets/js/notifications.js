(function () {
    'use strict';

    class NotificationManager {
        #alerts = [];
        #toasted = new Set();
        #DISMISS_KEY = 'crm.notif_dismissed';

        constructor() {
            this.#bindUI();
            this.#render();
            this.#fetchNotifications();
            setInterval(() => this.#fetchNotifications(), 60000);
        }

        #bindUI() {
            const btnBell  = document.getElementById('btnNotifications');
            const dropdown = document.getElementById('notifDropdown');
            const btnClear = document.getElementById('btnClearNotifs');

            btnBell?.addEventListener('click', e => {
                e.stopPropagation();
                dropdown?.classList.toggle('hidden');
            });
            document.addEventListener('click', () => dropdown?.classList.add('hidden'));
            dropdown?.addEventListener('click', e => e.stopPropagation());

            btnClear?.addEventListener('click', () => {
                this.#dismissAll(this.#alerts.map(a => a.key));
                this.#alerts = [];
                this.#render();
            });
        }

        #getDismissed() {
            try {
                const raw = localStorage.getItem(this.#DISMISS_KEY);
                return new Set(raw ? JSON.parse(raw) : []);
            } catch (e) { return new Set(); }
        }

        #saveDismissed(set) {
            try { localStorage.setItem(this.#DISMISS_KEY, JSON.stringify(Array.from(set))); } catch (e) {}
        }

        #dismiss(key) {
            const set = this.#getDismissed();
            set.add(key);
            this.#saveDismissed(set);
        }

        #dismissAll(keys) {
            const set = this.#getDismissed();
            keys.forEach(k => set.add(k));
            this.#saveDismissed(set);
        }

        #cleanupDismissed(activeKeys) {
            const set = this.#getDismissed();
            const active = new Set(activeKeys);
            const next = new Set();
            set.forEach(k => { if (active.has(k)) next.add(k); });
            if (next.size !== set.size) this.#saveDismissed(next);
        }

        #updateBadge() {
            const badge = document.getElementById('notifBadge');
            if (!badge) return;
            if (this.#alerts.length > 0) {
                badge.textContent = this.#alerts.length > 9 ? '9+' : String(this.#alerts.length);
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }

        #render() {
            const list = document.getElementById('notifList');
            if (!list) return;
            list.innerHTML = '';
            if (this.#alerts.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'px-4 py-3 text-sm text-gray-400 dark:text-zinc-500 text-center';
                empty.textContent = 'Nenhuma notificação';
                list.appendChild(empty);
                this.#updateBadge();
                return;
            }
            this.#alerts.forEach(item => {
                const row = document.createElement('div');
                row.className = 'px-4 py-3 text-sm text-gray-700 dark:text-zinc-300 hover:bg-gray-50 dark:hover:bg-zinc-800/50 flex items-start gap-2';
                const icon = item.type === 'birthday' ? '🎂' : '⏰';
                const msgSpan = document.createElement('span');
                msgSpan.className = 'flex-1 min-w-0 break-words';
                msgSpan.textContent = icon + ' ' + item.message;
                const btnX = document.createElement('button');
                btnX.type = 'button';
                btnX.className = 'flex-shrink-0 text-gray-400 hover:text-red-500 dark:text-zinc-500 dark:hover:text-red-400 transition-colors p-0.5 rounded';
                btnX.setAttribute('aria-label', 'Dispensar');
                btnX.dataset.key = item.key;
                btnX.textContent = '✕';
                btnX.addEventListener('click', () => {
                    this.#dismiss(item.key);
                    this.#alerts = this.#alerts.filter(a => a.key !== item.key);
                    this.#render();
                });
                row.appendChild(msgSpan);
                row.appendChild(btnX);
                list.appendChild(row);
            });
            this.#updateBadge();
        }

        async #fetchNotifications() {
            const appUrl = document.querySelector('meta[name="app-url"]')?.content || '';
            try {
                const resp = await fetch(appUrl + '/api/tasks/upcoming');
                if (!resp.ok) return;
                const data = await resp.json();
                const dismissed = this.#getDismissed();
                this.#cleanupDismissed(data.map(i => i.key));
                this.#alerts = [];
                data.forEach(item => {
                    if (dismissed.has(item.key)) return;
                    this.#alerts.push(item);
                    if (!this.#toasted.has(item.key)) {
                        this.#toasted.add(item.key);
                        window.CRM?.toast?.show(item.message, item.type);
                    }
                });
                this.#render();
            } catch (e) { /* silencia erros de rede */ }
        }
    }

    window.CRM = window.CRM || {};
    window.CRM.notifications = new NotificationManager();
})();
