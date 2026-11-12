/*
 * The plugin's own select and date picker, in the forms of wp-admin (.my-form: the order's
 * boxes, the Shipping report) and the order preview's customer status.
 *
 * Progressive enhancement: the native <select> / <input type="date"> stays in the form, hidden,
 * and is still what gets submitted. The control on top writes its value and fires "change" on
 * it, so the code that listens there keeps working (payment status, the couriers of a
 * country, the preview's save). Options added later (the couriers, by AJAX) are picked up by a
 * MutationObserver. Without JS the native fields are there as before.
 *
 * Patterns: WAI-ARIA APG "Select-Only Combobox" and "Date Picker Dialog".
 */
(function () {
    'use strict';

    const SELECTORS = '.my-form select, select.jl-customer-status-select';
    const DATES = '.my-form input[type="date"]';
    // Fields only: a <fieldset> holding an invalid field matches :invalid too.
    const FIELDS_INVALID = 'input:invalid, select:invalid, textarea:invalid';
    const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    const DAYS = [['lu', 'lunes'], ['ma', 'martes'], ['mi', 'miércoles'], ['ju', 'jueves'], ['vi', 'viernes'], ['sá', 'sábado'], ['do', 'domingo']];
    let uid = 0;

    function id(prefix) {
        uid += 1;
        return 'jl-' + prefix + '-' + uid;
    }

    /** The <label for> of a native field, with an id to point at. */
    function labelOf(native) {
        const label = native.id ? document.querySelector('label[for="' + CSS.escape(native.id) + '"]') : null;
        if (label && !label.id) {
            label.id = id('label');
        }
        return label;
    }

    function hideNative(native, focusTarget) {
        native.classList.add('jl-native');
        native.setAttribute('tabindex', '-1');
        native.setAttribute('aria-hidden', 'true');
        const label = labelOf(native);
        if (label) {
            // The label points at the hidden field: send its click to the visible control.
            label.addEventListener('click', function (event) {
                event.preventDefault();
                focusTarget.focus();
            });
        }
    }

    /** "Required" on a hidden field: the browser cannot show its bubble, so we do. */
    function handleInvalid(native, wrap, focusTarget, message) {
        const error = document.createElement('p');
        error.className = 'jl-error';
        error.id = id('error');
        error.hidden = true;
        wrap.appendChild(error);
        focusTarget.setAttribute('aria-describedby', error.id);

        native.addEventListener('invalid', function (event) {
            event.preventDefault();
            error.textContent = message;
            error.hidden = false;
            focusTarget.setAttribute('aria-invalid', 'true');
            // Only the first invalid field takes the focus.
            const form = native.form;
            const first = form && form.querySelector(FIELDS_INVALID);
            if (!form || first === native) {
                focusTarget.focus();
            }
        });
        // Cleared when the field becomes valid, also when code fills it without a "change"
        // (the couriers' select gets "Todo el país" by AJAX).
        native.jlRevalidate = function () {
            if (!error.hidden && native.checkValidity()) {
                error.hidden = true;
                focusTarget.removeAttribute('aria-invalid');
            }
        };
        native.addEventListener('change', native.jlRevalidate);
    }

    /**
     * Opens a popup (list, calendar) upward when a scrolling ancestor would cut it (the order
     * preview's modal cuts what goes past its bottom). Not for the window: the page scrolls.
     */
    function placePopup(wrap, popup) {
        wrap.classList.remove('is-up');
        const anchor = wrap.getBoundingClientRect();
        let top = -Infinity;
        let bottom = Infinity;
        for (let el = wrap.parentElement; el && el !== document.body; el = el.parentElement) {
            const cs = getComputedStyle(el);
            if (/(auto|scroll|hidden)/.test(cs.overflowY)) {
                const r = el.getBoundingClientRect();
                top = Math.max(top, r.top);
                bottom = Math.min(bottom, r.bottom);
            }
        }
        const need = popup.offsetHeight + 8;
        const below = bottom - anchor.bottom;
        const above = anchor.top - top;
        if (below < need && above > below) {
            wrap.classList.add('is-up');
        }
    }

    function setNative(native, value) {
        if (native.value === value) {
            return;
        }
        native.value = value;
        native.dispatchEvent(new Event('input', { bubbles: true }));
        native.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /* ------------------------------------------------------------------ select */

    function enhanceSelect(native) {
        if (native.dataset.jlEnhanced || native.multiple) {
            return;
        }
        native.dataset.jlEnhanced = '1';

        const wrap = document.createElement('div');
        wrap.className = 'jl-select';
        native.parentNode.insertBefore(wrap, native);
        wrap.appendChild(native);

        const combo = document.createElement('div');
        const listbox = document.createElement('div');
        const valueText = document.createElement('span');
        combo.className = 'jl-select__button';
        combo.id = id('combo');
        combo.setAttribute('role', 'combobox');
        combo.setAttribute('aria-haspopup', 'listbox');
        combo.setAttribute('aria-expanded', 'false');
        listbox.className = 'jl-select__list';
        listbox.id = id('list');
        listbox.setAttribute('role', 'listbox');
        listbox.setAttribute('tabindex', '-1');
        listbox.hidden = true;
        combo.setAttribute('aria-controls', listbox.id);
        valueText.className = 'jl-select__value';
        combo.appendChild(valueText);
        combo.insertAdjacentHTML('beforeend', '<svg class="jl-select__chevron" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M4 6l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>');
        wrap.appendChild(combo);
        wrap.appendChild(listbox);
        // Polite live region: "Cargando…" while the options load (the focus is usually still on
        // the field that triggered it, so the combobox's own text would go unheard).
        const status = document.createElement('span');
        status.className = 'jl-visually-hidden';
        status.setAttribute('role', 'status');
        wrap.appendChild(status);

        const label = labelOf(native);
        if (label) {
            combo.setAttribute('aria-labelledby', label.id);
            listbox.setAttribute('aria-labelledby', label.id);
        } else if (native.getAttribute('aria-label')) {
            combo.setAttribute('aria-label', native.getAttribute('aria-label'));
            listbox.setAttribute('aria-label', native.getAttribute('aria-label'));
        }
        if (native.required) {
            combo.setAttribute('aria-required', 'true');
        }
        hideNative(native, combo);
        handleInvalid(native, wrap, combo, 'Elige una opción.');

        let options = [];
        let active = -1;
        let open = false;
        let typed = '';
        let typedTimer = 0;

        function build() {
            listbox.textContent = '';
            options = Array.from(native.options).map(function (option, i) {
                const el = document.createElement('div');
                el.className = 'jl-select__option';
                el.id = combo.id + '-opt-' + i;
                el.setAttribute('role', 'option');
                el.textContent = option.textContent.trim();
                el.dataset.value = option.value;
                if (option.disabled) {
                    el.setAttribute('aria-disabled', 'true');
                }
                el.addEventListener('mousedown', function (event) {
                    event.preventDefault(); // keep the focus on the combobox
                });
                el.addEventListener('click', function () {
                    if (!option.disabled) {
                        choose(i);
                        close();
                    }
                });
                listbox.appendChild(el);
                return el;
            });
            sync();
        }

        function sync() {
            const i = native.selectedIndex;
            options.forEach(function (el, j) {
                el.setAttribute('aria-selected', j === i ? 'true' : 'false');
            });
            const text = i >= 0 && native.options[i] ? native.options[i].textContent.trim() : '';
            // Options that depend on another field, on their way (aria-busy on the native select,
            // set by the code that loads them): said in the field and to screen readers.
            const busy = native.getAttribute('aria-busy') === 'true';
            const loadingText = native.dataset.loadingText || 'Cargando…';
            valueText.textContent = busy ? loadingText : (text || '—');
            wrap.classList.toggle('is-loading', busy);
            if (busy) {
                combo.setAttribute('aria-busy', 'true');
                close();
            } else {
                combo.removeAttribute('aria-busy');
            }
            if (status.textContent !== (busy ? loadingText : '')) {
                status.textContent = busy ? loadingText : '';
            }
            // The empty first option ("Selecciona…") reads as a placeholder.
            wrap.classList.toggle('is-placeholder', busy || i < 0 || native.value === '');
            const disabled = busy || native.disabled || native.options.length === 0;
            combo.setAttribute('aria-disabled', disabled ? 'true' : 'false');
            combo.setAttribute('tabindex', disabled ? '-1' : '0');
            if (native.jlRevalidate) {
                native.jlRevalidate();
            }
        }

        function setActive(i) {
            if (!options.length) {
                return;
            }
            active = Math.max(0, Math.min(options.length - 1, i));
            options.forEach(function (el, j) {
                el.classList.toggle('is-active', j === active);
            });
            combo.setAttribute('aria-activedescendant', options[active].id);
            const el = options[active];
            if (el.offsetTop < listbox.scrollTop) {
                listbox.scrollTop = el.offsetTop;
            } else if (el.offsetTop + el.offsetHeight > listbox.scrollTop + listbox.clientHeight) {
                listbox.scrollTop = el.offsetTop + el.offsetHeight - listbox.clientHeight;
            }
        }

        function choose(i) {
            if (i < 0 || !native.options[i] || native.options[i].disabled) {
                return;
            }
            setNative(native, native.options[i].value);
            sync();
        }

        function show() {
            if (open || combo.getAttribute('aria-disabled') === 'true') {
                return;
            }
            sync();
            open = true;
            listbox.hidden = false;
            placePopup(wrap, listbox);
            combo.setAttribute('aria-expanded', 'true');
            wrap.classList.add('is-open');
            setActive(native.selectedIndex >= 0 ? native.selectedIndex : 0);
        }

        function close() {
            if (!open) {
                return;
            }
            open = false;
            listbox.hidden = true;
            combo.setAttribute('aria-expanded', 'false');
            combo.removeAttribute('aria-activedescendant');
            wrap.classList.remove('is-open');
        }

        function typeahead(char) {
            clearTimeout(typedTimer);
            typed += char.toLowerCase();
            typedTimer = setTimeout(function () {
                typed = '';
            }, 600);
            const start = open ? active + 1 : native.selectedIndex + 1;
            const order = options.slice(start).concat(options.slice(0, start));
            const match = order.find(function (el) {
                return el.textContent.toLowerCase().indexOf(typed) === 0 && el.getAttribute('aria-disabled') !== 'true';
            });
            if (!match) {
                return;
            }
            const i = options.indexOf(match);
            if (open) {
                setActive(i);
            } else {
                choose(i);
            }
        }

        combo.addEventListener('click', function () {
            if (open) {
                close();
            } else {
                show();
            }
        });
        combo.addEventListener('blur', close);
        combo.addEventListener('keydown', function (event) {
            const key = event.key;
            if (!open) {
                if (key === 'ArrowDown' || key === 'ArrowUp' || key === 'Enter' || key === ' ') {
                    event.preventDefault();
                    show();
                } else if (key === 'Home' || key === 'End') {
                    event.preventDefault();
                    show();
                    setActive(key === 'Home' ? 0 : options.length - 1);
                } else if (key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
                    typeahead(key);
                }
                return;
            }
            switch (key) {
                case 'ArrowDown':
                    event.preventDefault();
                    if (event.altKey) {
                        return;
                    }
                    setActive(active + 1);
                    break;
                case 'ArrowUp':
                    event.preventDefault();
                    if (event.altKey) {
                        choose(active);
                        close();
                        return;
                    }
                    setActive(active - 1);
                    break;
                case 'Home':
                    event.preventDefault();
                    setActive(0);
                    break;
                case 'End':
                    event.preventDefault();
                    setActive(options.length - 1);
                    break;
                case 'PageDown':
                    event.preventDefault();
                    setActive(active + 10);
                    break;
                case 'PageUp':
                    event.preventDefault();
                    setActive(active - 10);
                    break;
                case 'Enter':
                case ' ':
                    event.preventDefault();
                    choose(active);
                    close();
                    break;
                case 'Escape':
                    event.preventDefault();
                    close();
                    break;
                case 'Tab':
                    choose(active);
                    close();
                    break;
                default:
                    if (key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
                        typeahead(key);
                    }
            }
        });

        // Changes made from outside: options filled by AJAX, disabled, a value set by code.
        new MutationObserver(build).observe(native, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['disabled', 'aria-busy'] });
        native.addEventListener('change', sync);
        combo.addEventListener('focus', sync);
        build();
    }

    /* ------------------------------------------------------------- date picker */

    function parse(value) {
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
        return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null;
    }

    function iso(date) {
        const p = function (n) {
            return String(n).padStart(2, '0');
        };
        return date.getFullYear() + '-' + p(date.getMonth() + 1) + '-' + p(date.getDate());
    }

    /** "11 nov 2026": fits the field; the days in the calendar keep the long form. */
    function shortDate(date) {
        return date.getDate() + ' ' + MONTHS[date.getMonth()].slice(0, 3) + ' ' + date.getFullYear();
    }

    function longDate(date) {
        return date.getDate() + ' de ' + MONTHS[date.getMonth()] + ' de ' + date.getFullYear();
    }

    function addDays(date, n) {
        const d = new Date(date);
        d.setDate(d.getDate() + n);
        return d;
    }

    function addMonths(date, n) {
        const d = new Date(date.getFullYear(), date.getMonth() + n, 1);
        const last = new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();
        d.setDate(Math.min(date.getDate(), last));
        return d;
    }

    function sameDay(a, b) {
        return !!a && !!b && iso(a) === iso(b);
    }

    function enhanceDate(native) {
        if (native.dataset.jlEnhanced) {
            return;
        }
        native.dataset.jlEnhanced = '1';

        const wrap = document.createElement('div');
        wrap.className = 'jl-date';
        native.parentNode.insertBefore(wrap, native);
        wrap.appendChild(native);

        const button = document.createElement('button');
        const valueText = document.createElement('span');
        button.type = 'button';
        button.className = 'jl-date__button';
        button.setAttribute('aria-haspopup', 'dialog');
        valueText.className = 'jl-date__value';
        valueText.id = id('date-value');
        button.appendChild(valueText);
        button.insertAdjacentHTML('beforeend', '<svg class="jl-date__icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3.5" y="5" width="17" height="15.5" rx="3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M3.5 10h17M8 3v4M16 3v4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>');
        wrap.appendChild(button);

        const label = labelOf(native);
        button.setAttribute('aria-labelledby', (label ? label.id + ' ' : '') + valueText.id);
        hideNative(native, button);
        handleInvalid(native, wrap, button, 'Elige una fecha.');

        const dialog = document.createElement('div');
        const title = id('date-title');
        dialog.className = 'jl-date__dialog';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', title);
        dialog.hidden = true;
        dialog.innerHTML =
            '<div class="jl-date__header">' +
            '<button type="button" class="jl-date__nav" data-nav="-1" aria-label="Mes anterior"><svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M10 4l-4 4 4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button>' +
            '<h2 class="jl-date__title" id="' + title + '" aria-live="polite"></h2>' +
            '<button type="button" class="jl-date__nav" data-nav="1" aria-label="Mes siguiente"><svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M6 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button>' +
            '</div>' +
            '<table class="jl-date__grid" role="grid" aria-labelledby="' + title + '"><thead><tr>' +
            DAYS.map(function (d) {
                return '<th scope="col" abbr="' + d[1] + '">' + d[0] + '</th>';
            }).join('') +
            '</tr></thead><tbody></tbody></table>' +
            '<div class="jl-date__footer">' +
            '<button type="button" class="jl-date__link" data-today>Hoy</button>' +
            '<button type="button" class="jl-date__link" data-close>Cerrar</button>' +
            '</div>';
        wrap.appendChild(dialog);

        const heading = dialog.querySelector('.jl-date__title');
        const body = dialog.querySelector('tbody');
        let focusDate = null;

        function bounds(date) {
            const min = parse(native.min);
            const max = parse(native.max);
            return (!min || date >= min) && (!max || date <= max);
        }

        function syncButton() {
            const date = parse(native.value);
            valueText.textContent = date ? shortDate(date) : 'Elegir fecha';
            wrap.classList.toggle('is-placeholder', !date);
        }

        function render() {
            const selected = parse(native.value);
            const today = new Date();
            const first = new Date(focusDate.getFullYear(), focusDate.getMonth(), 1);
            const start = addDays(first, -((first.getDay() + 6) % 7)); // back to Monday
            heading.textContent = MONTHS[focusDate.getMonth()].replace(/^./, function (c) {
                return c.toUpperCase();
            }) + ' ' + focusDate.getFullYear();
            body.textContent = '';
            let day = start;
            for (let w = 0; w < 6; w += 1) {
                const tr = document.createElement('tr');
                for (let d = 0; d < 7; d += 1) {
                    const td = document.createElement('td');
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'jl-date__day';
                    b.textContent = String(day.getDate());
                    b.dataset.date = iso(day);
                    b.setAttribute('aria-label', longDate(day));
                    b.setAttribute('tabindex', sameDay(day, focusDate) ? '0' : '-1');
                    if (day.getMonth() !== focusDate.getMonth()) {
                        b.classList.add('is-outside');
                    }
                    if (sameDay(day, today)) {
                        b.setAttribute('aria-current', 'date');
                    }
                    if (sameDay(day, selected)) {
                        b.setAttribute('aria-pressed', 'true');
                    }
                    if (!bounds(day)) {
                        b.disabled = true;
                    }
                    td.appendChild(b);
                    tr.appendChild(td);
                    day = addDays(day, 1);
                }
                body.appendChild(tr);
            }
        }

        function moveTo(date, focus) {
            focusDate = date;
            render();
            if (focus !== false) {
                const b = body.querySelector('[data-date="' + iso(date) + '"]');
                if (b) {
                    b.focus();
                }
            }
        }

        function openDialog() {
            focusDate = parse(native.value) || new Date();
            dialog.hidden = false;
            wrap.classList.add('is-open');
            moveTo(focusDate, false);
            placePopup(wrap, dialog);
            button.setAttribute('aria-expanded', 'true');
            moveTo(focusDate);
            document.addEventListener('mousedown', outside, true);
        }

        function closeDialog(restoreFocus) {
            if (dialog.hidden) {
                return;
            }
            dialog.hidden = true;
            wrap.classList.remove('is-open');
            button.setAttribute('aria-expanded', 'false');
            document.removeEventListener('mousedown', outside, true);
            if (restoreFocus !== false) {
                button.focus();
            }
        }

        function outside(event) {
            if (!wrap.contains(event.target)) {
                closeDialog(false);
            }
        }

        function pick(date) {
            if (!bounds(date)) {
                return;
            }
            setNative(native, iso(date));
            syncButton();
            closeDialog();
        }

        button.setAttribute('aria-expanded', 'false');
        button.addEventListener('click', function () {
            if (dialog.hidden) {
                openDialog();
            } else {
                closeDialog();
            }
        });

        dialog.addEventListener('click', function (event) {
            const day = event.target.closest('.jl-date__day');
            const nav = event.target.closest('[data-nav]');
            if (day && !day.disabled) {
                pick(parse(day.dataset.date));
            } else if (nav) {
                moveTo(addMonths(focusDate, +nav.dataset.nav), false);
            } else if (event.target.closest('[data-today]')) {
                moveTo(new Date());
            } else if (event.target.closest('[data-close]')) {
                closeDialog();
            }
        });

        dialog.addEventListener('keydown', function (event) {
            const key = event.key;
            if (key === 'Escape') {
                event.preventDefault();
                closeDialog();
                return;
            }
            if (key === 'Tab') {
                // Focus stays in the dialog (aria-modal).
                const focusables = Array.from(dialog.querySelectorAll('button:not([disabled])')).filter(function (b) {
                    return b.getAttribute('tabindex') !== '-1';
                });
                const i = focusables.indexOf(document.activeElement);
                if (event.shiftKey && i <= 0) {
                    event.preventDefault();
                    focusables[focusables.length - 1].focus();
                } else if (!event.shiftKey && i === focusables.length - 1) {
                    event.preventDefault();
                    focusables[0].focus();
                }
                return;
            }
            if (!event.target.classList.contains('jl-date__day')) {
                return;
            }
            const moves = {
                ArrowRight: function (d) { return addDays(d, 1); },
                ArrowLeft: function (d) { return addDays(d, -1); },
                ArrowDown: function (d) { return addDays(d, 7); },
                ArrowUp: function (d) { return addDays(d, -7); },
                Home: function (d) { return addDays(d, -((d.getDay() + 6) % 7)); },
                End: function (d) { return addDays(d, 6 - ((d.getDay() + 6) % 7)); },
                PageUp: function (d) { return event.shiftKey ? addMonths(d, -12) : addMonths(d, -1); },
                PageDown: function (d) { return event.shiftKey ? addMonths(d, 12) : addMonths(d, 1); }
            };
            if (moves[key]) {
                event.preventDefault();
                moveTo(moves[key](focusDate));
            } else if (key === 'Enter' || key === ' ') {
                event.preventDefault();
                pick(focusDate);
            }
        });

        native.addEventListener('change', syncButton);
        syncButton();
    }

    /* -------------------------------------------------------------------- init */

    function scan(root) {
        root.querySelectorAll(SELECTORS).forEach(enhanceSelect);
        root.querySelectorAll(DATES).forEach(enhanceDate);
    }

    function init() {
        scan(document);
        // The order preview is drawn when it opens (a Backbone template).
        new MutationObserver(function (records) {
            records.forEach(function (record) {
                record.addedNodes.forEach(function (node) {
                    if (node.nodeType === 1 && !node.closest('.jl-select, .jl-date')) {
                        scan(node);
                    }
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
