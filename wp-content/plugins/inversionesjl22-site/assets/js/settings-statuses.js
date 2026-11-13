/**
 * WooCommerce › Estados y flujo (includes/settings-statuses.php).
 *
 * - Ordenar los estados: flechas (teclado y táctil) y arrastrar por el asa (ratón). El orden que
 *   se guarda es el de las tarjetas en el formulario.
 * - Plegar el panel de edición de cada estado (sin JS van todos abiertos).
 * - "+ Añadir": muestra los destinos sin marcar de "Puede pasar a" (sin JS se ven todos).
 * - Confirmación antes de restaurar los valores iniciales.
 */
(function () {
    'use strict';

    var root = document.querySelector('.jl-settings');
    var list = root && root.querySelector('[data-jl-sortable]');
    if (!list) {
        return;
    }
    var text = window.inversionesjl22SettingsStatuses || {};
    var live = root.querySelector('[data-jl-live]');

    root.classList.add('has-js');

    function cards() {
        return Array.prototype.slice.call(list.querySelectorAll('[data-jl-status]'));
    }

    // ------------------------------------------------------------ orden

    function refreshMoves() {
        var all = cards();
        all.forEach(function (card, index) {
            card.querySelector('[data-jl-move="up"]').disabled = index === 0;
            card.querySelector('[data-jl-move="down"]').disabled = index === all.length - 1;
        });
    }

    function announce(card) {
        var all = cards();
        if (!live || !text.moved) {
            return;
        }
        live.textContent = text.moved
            .replace('%1$s', card.getAttribute('data-name'))
            .replace('%2$d', all.indexOf(card) + 1)
            .replace('%3$d', all.length);
    }

    list.addEventListener('click', function (event) {
        var button = event.target.closest('[data-jl-move]');
        if (!button) {
            return;
        }
        var card = button.closest('[data-jl-status]');
        var up = button.getAttribute('data-jl-move') === 'up';
        var other = up ? card.previousElementSibling : card.nextElementSibling;
        if (!other) {
            return;
        }
        list.insertBefore(up ? card : other, up ? other : card);
        refreshMoves();
        // La flecha pulsada puede haber quedado deshabilitada (primera o última posición).
        (button.disabled ? card.querySelector('[data-jl-move]:not(:disabled)') : button).focus();
        announce(card);
    });

    var dragged = null;

    list.addEventListener('dragstart', function (event) {
        var handle = event.target.closest && event.target.closest('[data-jl-handle]');
        if (!handle) {
            event.preventDefault();
            return;
        }
        dragged = handle.closest('[data-jl-status]');
        dragged.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', dragged.getAttribute('data-name'));
        event.dataTransfer.setDragImage(dragged, 20, 20);
    });

    list.addEventListener('dragover', function (event) {
        if (!dragged) {
            return;
        }
        event.preventDefault();
        var over = event.target.closest('[data-jl-status]');
        if (!over || over === dragged) {
            return;
        }
        var box = over.getBoundingClientRect();
        var after = event.clientY > box.top + box.height / 2;
        list.insertBefore(dragged, after ? over.nextElementSibling : over);
    });

    list.addEventListener('drop', function (event) {
        if (dragged) {
            event.preventDefault();
        }
    });

    list.addEventListener('dragend', function () {
        if (!dragged) {
            return;
        }
        dragged.classList.remove('is-dragging');
        refreshMoves();
        announce(dragged);
        dragged = null;
    });

    refreshMoves();

    // ------------------------------------------------------------ panel de edición

    function setOpen(card, open) {
        var toggle = card.querySelector('[data-jl-toggle]');
        var panel = card.querySelector('[data-jl-panel]');
        panel.hidden = !open;
        card.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.querySelector('[data-jl-toggle-text]').textContent = open ? text.close : text.edit;
    }

    cards().forEach(function (card) {
        setOpen(card, false);
    });

    list.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-jl-toggle]');
        if (toggle) {
            setOpen(toggle.closest('[data-jl-status]'), toggle.getAttribute('aria-expanded') !== 'true');
        }
    });

    // Un campo obligatorio vacío dentro de un panel plegado: el navegador no puede enfocarlo
    // para avisar. Se abre el panel antes.
    root.querySelector('#jl-statuses-form').addEventListener('invalid', function (event) {
        var card = event.target.closest('[data-jl-status]');
        if (card) {
            setOpen(card, true);
        }
    }, true);

    // ------------------------------------------------------------ "puede pasar a"

    function refreshNext(group) {
        var add = group.querySelector('[data-jl-add]');
        var final = group.querySelector('[data-jl-final]');
        var boxes = Array.prototype.slice.call(group.querySelectorAll('input[type="checkbox"]'));
        var checked = boxes.filter(function (box) { return box.checked; }).length;
        var adding = group.classList.contains('is-adding');
        add.hidden = adding || checked === boxes.length;
        add.setAttribute('aria-expanded', adding ? 'true' : 'false');
        if (final) {
            final.hidden = checked > 0 || adding;
        }
    }

    Array.prototype.forEach.call(root.querySelectorAll('[data-jl-next]'), function (group) {
        refreshNext(group);
        group.addEventListener('change', function () {
            refreshNext(group);
        });
        group.querySelector('[data-jl-add]').addEventListener('click', function () {
            group.classList.add('is-adding');
            refreshNext(group);
            var first = group.querySelector('input[type="checkbox"]:not(:checked)');
            if (first) {
                first.focus();
            }
        });
    });

    // ------------------------------------------------------------ restaurar

    var reset = root.querySelector('[data-jl-confirm="reset"]');
    if (reset) {
        reset.addEventListener('submit', function (event) {
            if (text.confirmReset && !window.confirm(text.confirmReset)) {
                event.preventDefault();
            }
        });
    }
})();
