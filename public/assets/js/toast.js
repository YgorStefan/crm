(function () {
    'use strict';

    class ToastManager {
        #container = null;

        #ensureContainer() {
            if (this.#container) return this.#container;
            this.#container = document.getElementById('crm-toast-container');
            if (!this.#container) {
                this.#container = document.createElement('div');
                this.#container.id = 'crm-toast-container';
                this.#container.style.cssText =
                    'position:fixed;bottom:1rem;right:1rem;z-index:9999;' +
                    'display:flex;flex-direction:column-reverse;gap:0.5rem;' +
                    'pointer-events:auto;max-width:calc(100vw - 2rem);';
                document.body.appendChild(this.#container);
            }
            return this.#container;
        }

        show(message, type, opts) {
            opts = opts || {};
            const bgColors = {
                task:     '#4338ca',
                birthday: '#db2777',
                success:  '#16a34a',
                error:    '#dc2626',
                info:     '#374151',
            };
            const container = this.#ensureContainer();
            const toast = document.createElement('div');
            toast.style.cssText =
                'background-color:' + (bgColors[type] || '#374151') + ';' +
                'color:#fff;padding:0.75rem 1rem;border-radius:0.75rem;' +
                'box-shadow:0 10px 15px -3px rgba(0,0,0,0.1),0 4px 6px -2px rgba(0,0,0,0.05);' +
                'font-size:0.875rem;line-height:1.25rem;max-width:20rem;' +
                'display:flex;align-items:flex-start;gap:0.5rem;pointer-events:auto;';

            const msg = document.createElement('span');
            msg.style.cssText = 'flex:1 1 0%;min-width:0;word-break:break-word;';
            msg.textContent = message;

            const btnX = document.createElement('button');
            btnX.type = 'button';
            btnX.setAttribute('aria-label', 'Dispensar');
            btnX.style.cssText =
                'flex-shrink:0;background:transparent;border:0;cursor:pointer;' +
                'color:rgba(255,255,255,0.75);font-size:1.125rem;line-height:1;' +
                'font-weight:700;padding:0 0.25rem;margin:-0.125rem -0.25rem 0 0;';
            btnX.textContent = '×';
            btnX.addEventListener('mouseenter', () => { btnX.style.color = '#fff'; });
            btnX.addEventListener('mouseleave', () => { btnX.style.color = 'rgba(255,255,255,0.75)'; });

            let removed = false;
            const removeToast = () => {
                if (removed) return;
                removed = true;
                toast.remove();
            };
            btnX.addEventListener('click', e => { e.stopPropagation(); removeToast(); });

            toast.appendChild(msg);
            toast.appendChild(btnX);
            container.appendChild(toast);

            const ttl = typeof opts.duration === 'number' ? opts.duration : 8000;
            if (ttl > 0) setTimeout(removeToast, ttl);
            return removeToast;
        }
    }

    window.CRM = window.CRM || {};
    window.CRM.toast = new ToastManager();
    // retrocompatibilidade: views que chamam window.crmToast() continuam funcionando
    window.crmToast = window.CRM.toast.show.bind(window.CRM.toast);
})();
