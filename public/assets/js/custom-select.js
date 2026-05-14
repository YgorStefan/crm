/**
 * custom-select.js — Substitui <select> nativos por dropdown estilizavel.
 *
 * Auto-init no DOMContentLoaded para todo <select> que NAO tenha:
 *  - atributo `multiple`
 *  - atributo `data-no-custom`
 *  - tipo nativo especial (datetime-local etc nao se aplica)
 *
 * Para inputs criados dinamicamente, chamar `window.CRMSelect.enhance(selectEl)`.
 *
 * Acessibilidade:
 *  - role=combobox no botao trigger
 *  - role=listbox + role=option no dropdown
 *  - aria-expanded, aria-activedescendant, aria-selected
 *  - keyboard: Up/Down/Home/End, Enter/Space, Escape, type-ahead 1 char
 */
(function () {
    'use strict';

    var idCounter = 0;

    function uid() { return 'csel-' + (++idCounter); }

    function enhance(select) {
        if (!select || select.tagName !== 'SELECT') return;
        if (select.dataset.csEnhanced) return;
        if (select.multiple) return;
        if (select.hasAttribute('data-no-custom')) return;

        select.dataset.csEnhanced = '1';

        var wrapper = document.createElement('div');
        wrapper.className = 'cs-wrapper';
        wrapper.style.position = 'relative';

        // Preserva classes do <select> para que o wrapper herde largura
        var selClasses = select.className;
        wrapper.dataset.csOriginalClasses = selClasses;
        // Aplica as classes ao wrapper para preservar largura/grid
        if (selClasses) {
            // Mantemos as classes responsivas no wrapper (sobretudo width/grid)
            selClasses.split(/\s+/).forEach(function (c) {
                if (/^(w-|lg:w-|sm:w-|md:w-|col-|lg:col-|min-w-|max-w-)/.test(c)) {
                    wrapper.classList.add(c);
                }
            });
        }

        // Esconde o select original mas mantem-o no DOM (preserva submit + name)
        select.classList.add('cs-native-hidden');

        var listId = uid();

        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'cs-trigger ' + selClasses;
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-controls', listId);
        trigger.innerHTML = '<span class="cs-label"></span>' +
            '<svg class="cs-chevron" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>';

        var listbox = document.createElement('ul');
        listbox.id = listId;
        listbox.className = 'cs-listbox';
        listbox.setAttribute('role', 'listbox');
        listbox.setAttribute('tabindex', '-1');
        listbox.hidden = true;

        function renderOptions() {
            listbox.innerHTML = '';
            Array.prototype.forEach.call(select.options, function (opt, idx) {
                var li = document.createElement('li');
                li.className = 'cs-option';
                li.setAttribute('role', 'option');
                li.dataset.value = opt.value;
                li.dataset.index = idx;
                li.id = listId + '-opt-' + idx;
                li.textContent = opt.textContent;
                if (opt.disabled) {
                    li.setAttribute('aria-disabled', 'true');
                    li.classList.add('cs-disabled');
                }
                if (opt.selected) {
                    li.setAttribute('aria-selected', 'true');
                    li.classList.add('cs-selected');
                }
                listbox.appendChild(li);
            });
        }

        function syncLabel() {
            var opt = select.options[select.selectedIndex];
            var label = trigger.querySelector('.cs-label');
            if (opt) {
                label.textContent = opt.textContent;
                label.classList.toggle('cs-placeholder', opt.value === '' && select.selectedIndex === 0);
            } else {
                label.textContent = '';
            }
        }

        function setActive(idx) {
            var items = listbox.querySelectorAll('.cs-option');
            items.forEach(function (i) { i.classList.remove('cs-active'); });
            if (idx >= 0 && items[idx]) {
                items[idx].classList.add('cs-active');
                trigger.setAttribute('aria-activedescendant', items[idx].id);
                // Scroll into view
                var lb = listbox;
                var item = items[idx];
                var top = item.offsetTop;
                var bottom = top + item.offsetHeight;
                if (top < lb.scrollTop) lb.scrollTop = top;
                else if (bottom > lb.scrollTop + lb.clientHeight) lb.scrollTop = bottom - lb.clientHeight;
            }
        }

        function findActiveIndex() {
            var items = listbox.querySelectorAll('.cs-option');
            for (var i = 0; i < items.length; i++) if (items[i].classList.contains('cs-active')) return i;
            return -1;
        }

        function open() {
            renderOptions();
            listbox.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            wrapper.classList.add('cs-open');
            setActive(select.selectedIndex >= 0 ? select.selectedIndex : 0);
            document.addEventListener('mousedown', onDocMouseDown, true);
        }

        function close() {
            listbox.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            trigger.removeAttribute('aria-activedescendant');
            wrapper.classList.remove('cs-open');
            document.removeEventListener('mousedown', onDocMouseDown, true);
        }

        function toggle() { listbox.hidden ? open() : close(); }

        function commit(idx) {
            if (idx < 0 || idx >= select.options.length) return;
            if (select.options[idx].disabled) return;
            select.selectedIndex = idx;
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
            syncLabel();
            close();
            trigger.focus();
        }

        function onDocMouseDown(e) {
            if (!wrapper.contains(e.target)) close();
        }

        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            toggle();
        });

        trigger.addEventListener('keydown', function (e) {
            var items = select.options;
            var isOpen = !listbox.hidden;
            switch (e.key) {
                case 'ArrowDown':
                case 'Down':
                    e.preventDefault();
                    if (!isOpen) open();
                    else {
                        var i = findActiveIndex();
                        for (var n = i + 1; n < items.length; n++) {
                            if (!items[n].disabled) { setActive(n); break; }
                        }
                    }
                    break;
                case 'ArrowUp':
                case 'Up':
                    e.preventDefault();
                    if (!isOpen) open();
                    else {
                        var i2 = findActiveIndex();
                        for (var m = i2 - 1; m >= 0; m--) {
                            if (!items[m].disabled) { setActive(m); break; }
                        }
                    }
                    break;
                case 'Home':
                    if (isOpen) { e.preventDefault(); setActive(0); }
                    break;
                case 'End':
                    if (isOpen) { e.preventDefault(); setActive(items.length - 1); }
                    break;
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    if (isOpen) commit(findActiveIndex());
                    else open();
                    break;
                case 'Escape':
                    if (isOpen) { e.preventDefault(); close(); }
                    break;
                case 'Tab':
                    if (isOpen) close();
                    break;
                default:
                    if (e.key && e.key.length === 1 && /\S/.test(e.key)) {
                        // type-ahead
                        var ch = e.key.toLowerCase();
                        if (!isOpen) open();
                        for (var k = 0; k < items.length; k++) {
                            if (!items[k].disabled && (items[k].textContent || '').trim().toLowerCase().charAt(0) === ch) {
                                setActive(k);
                                break;
                            }
                        }
                    }
            }
        });

        listbox.addEventListener('mousemove', function (e) {
            var li = e.target.closest('.cs-option');
            if (!li) return;
            setActive(parseInt(li.dataset.index, 10));
        });

        listbox.addEventListener('click', function (e) {
            var li = e.target.closest('.cs-option');
            if (!li || li.classList.contains('cs-disabled')) return;
            commit(parseInt(li.dataset.index, 10));
        });

        // Sincroniza com mudancas externas no <select>
        select.addEventListener('change', syncLabel);

        // Insere o wrapper antes do select e move o select para dentro
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(trigger);
        wrapper.appendChild(select);
        wrapper.appendChild(listbox);

        syncLabel();
    }

    function enhanceAll(root) {
        (root || document).querySelectorAll('select:not([multiple]):not([data-no-custom])').forEach(enhance);
    }

    window.CRMSelect = { enhance: enhance, enhanceAll: enhanceAll };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { enhanceAll(); });
    } else {
        enhanceAll();
    }
})();
