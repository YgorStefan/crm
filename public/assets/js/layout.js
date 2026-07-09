(function () {
    'use strict';

    class LayoutManager {
        #isMini = false;
        #sidebar = null;
        #mainContent = null;

        constructor() {
            this.#sidebar = document.getElementById('sidebar');
            this.#mainContent = document.getElementById('mainContent');
            try {
                const stored = localStorage.getItem('sidebar');
                if (stored !== null) {
                    this.#isMini = stored === 'mini';
                } else {
                    this.#isMini = window.innerWidth < 1280;
                }
            } catch (e) {
                console.debug('localStorage indisponível no construtor:', e);
                this.#isMini = window.innerWidth < 1280;
            }
            this.#initClock();
            this.#initTheme();
            this.#initSidebar();
            this.#initFlash();
        }

        applyTheme(isDark) {
            isDark
                ? document.documentElement.classList.add('dark')
                : document.documentElement.classList.remove('dark');
            try {
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
            } catch (e) {
                console.debug('Erro ao salvar tema:', e);
            }
            document.getElementById('themeToggle')?.setAttribute('aria-checked', String(isDark));
            document.dispatchEvent(new CustomEvent('themeChange', { detail: { dark: isDark } }));
        }

        #initClock() {
            const el = document.getElementById('clock');
            if (!el) return;
            const update = () => {
                const now = new Date();
                el.textContent = now.toLocaleDateString('pt-BR') + ' ' +
                    now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            };
            update();
            setInterval(update, 60000);
        }

        #initTheme() {
            const toggle = document.getElementById('themeToggle');
            if (!toggle) return;
            toggle.setAttribute('aria-checked', String(document.documentElement.classList.contains('dark')));
            toggle.addEventListener('click', () =>
                this.applyTheme(!document.documentElement.classList.contains('dark'))
            );
            document.addEventListener('keydown', e => {
                if (e.ctrlKey && e.shiftKey && e.key === 'L') {
                    e.preventDefault();
                    this.applyTheme(!document.documentElement.classList.contains('dark'));
                }
            });
        }

        #setSidebarMini(mini) {
            this.#isMini = mini;
            const s = this.#sidebar;
            const m = this.#mainContent;
            if (mini) {
                s.classList.add('sidebar-mini');
                m.classList.remove('lg:ml-56');
                m.classList.add('lg:ml-16');
            } else {
                s.classList.remove('sidebar-mini');
                m.classList.remove('lg:ml-16');
                m.classList.add('lg:ml-56');
            }
            try {
                localStorage.setItem('sidebar', mini ? 'mini' : 'expanded');
            } catch (e) {
                console.debug('Erro ao salvar sidebar:', e);
            }
            setTimeout(() => window.dispatchEvent(new Event('resize')), 320);
        }

        #openMobile() {
            this.#sidebar.classList.remove('-translate-x-full');
            this.#sidebar.classList.add('translate-x-0');
            document.getElementById('sidebarBackdrop')?.classList.remove('hidden');
        }

        #closeMobile() {
            this.#sidebar.classList.remove('translate-x-0');
            this.#sidebar.classList.add('-translate-x-full');
            document.getElementById('sidebarBackdrop')?.classList.add('hidden');
        }

        #initSidebar() {
            const s = this.#sidebar;
            const m = this.#mainContent;
            const backdrop    = document.getElementById('sidebarBackdrop');
            const collapseBtn = document.getElementById('sidebarCollapseBtn');
            const toggleBtn   = document.getElementById('sidebarToggle');
            const closeBtn    = document.getElementById('closeSidebarBtn');

            if (!s) return;

            if (window.innerWidth >= 1024) {
                s.classList.remove('-translate-x-full');
                s.classList.add('translate-x-0');
                this.#setSidebarMini(this.#isMini);
            }

            collapseBtn?.addEventListener('click', () => this.#setSidebarMini(!this.#isMini));

            toggleBtn?.addEventListener('click', () => {
                if (window.innerWidth >= 1024) {
                    this.#setSidebarMini(!this.#isMini);
                } else {
                    s.classList.contains('-translate-x-full') ? this.#openMobile() : this.#closeMobile();
                }
            });

            closeBtn?.addEventListener('click', () => this.#closeMobile());
            backdrop?.addEventListener('click', () => this.#closeMobile());

            window.addEventListener('resize', () => {
                if (window.innerWidth >= 1024) {
                    s.classList.remove('-translate-x-full');
                    s.classList.add('translate-x-0');
                    backdrop?.classList.add('hidden');
                    if (this.#isMini) {
                        m.classList.remove('lg:ml-56');
                        m.classList.add('lg:ml-16');
                    } else {
                        m.classList.remove('lg:ml-16');
                        m.classList.add('lg:ml-56');
                    }
                } else {
                    m.classList.remove('lg:ml-56', 'lg:ml-16');
                }
            });
        }

        #initFlash() {
            setTimeout(() => document.getElementById('flashMsg')?.remove(), 5000);
        }
    }

    window.CRM = window.CRM || {};
    window.CRM.layout = new LayoutManager();
})();
