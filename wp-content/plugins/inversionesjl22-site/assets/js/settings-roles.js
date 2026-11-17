/**
 * Inversiones JL22 › Roles y permisos (includes/settings-roles.php).
 *
 * - Al marcar o desmarcar "Ver los pedidos" de un estado, enciende o apaga el resto de su fila
 *   (sin JS hay que guardar para que la fila se encienda).
 * - «Pantallas que ve en el menú»: búsqueda por nombre, grupos plegables con el contador
 *   «n de m marcadas». Es solo presentación: las casillas siguen siendo menu[] y se envían
 *   aunque estén ocultas o plegadas.
 * - Confirmación antes de restaurar los valores iniciales y de eliminar un rol.
 */
(function () {
    'use strict';

    var root = document.querySelector('.jl-settings');
    if (!root) {
        return;
    }
    root.classList.add('has-js');

    root.addEventListener('change', function (event) {
        var see = event.target.closest('[data-jl-see]');
        var row = see && see.closest('[data-jl-row]');
        if (!row) {
            return;
        }
        row.classList.toggle('is-off', !see.checked);
        Array.prototype.forEach.call(row.querySelectorAll('[data-jl-cell]'), function (cell) {
            cell.disabled = !see.checked;
        });
    });

    var text = window.inversionesjl22SettingsRoles || {};

    function format(template, values) {
        return values.reduce(function (out, value, i) {
            return out.replace('%' + (i + 1) + '$d', value).replace('%d', value);
        }, template);
    }

    Array.prototype.forEach.call(root.querySelectorAll('[data-jl-menu-picker]'), function (picker) {
        var groups = Array.prototype.slice.call(picker.querySelectorAll('[data-jl-menu-group]'));
        var search = picker.querySelector('[data-jl-menu-search]');
        var query = picker.querySelector('[data-jl-menu-query]');
        var status = picker.querySelector('[data-jl-menu-status]');
        var empty = picker.querySelector('[data-jl-menu-empty]');
        var wasOpen = null; // lo que estaba abierto antes de buscar

        function normalize(value) {
            return value.toLowerCase().normalize('NFD').replace(/\p{M}/gu, '').trim();
        }

        function refreshCount(group) {
            var boxes = group.querySelectorAll('input[type="checkbox"]');
            var on = group.querySelectorAll('input[type="checkbox"]:checked').length;
            var count = group.querySelector('[data-jl-menu-count]');
            count.textContent = format(text.count || '%1$d de %2$d marcadas', [on, boxes.length]);
            group.classList.toggle('has-checked', on > 0);
        }

        function filter() {
            var needle = normalize(query.value);
            var matches = 0;
            if (needle && wasOpen === null) {
                wasOpen = groups.map(function (group) { return group.open; });
            }
            groups.forEach(function (group, index) {
                var groupName = normalize(group.querySelector('.jl-menu-group__name').textContent);
                var groupHit = needle !== '' && groupName.indexOf(needle) !== -1;
                var shown = 0;
                Array.prototype.forEach.call(group.querySelectorAll('.jl-option'), function (option) {
                    var hit = needle === '' || groupHit || normalize(option.textContent).indexOf(needle) !== -1;
                    option.hidden = !hit;
                    shown += hit ? 1 : 0;
                });
                group.hidden = shown === 0;
                matches += shown;
                if (needle) {
                    group.open = shown > 0;
                } else if (wasOpen) {
                    group.open = wasOpen[index];
                }
            });
            if (!needle) {
                wasOpen = null;
            }
            empty.hidden = matches > 0;
            status.textContent = needle ? (matches ? format(text.results || '%d', [matches]) : (text.none || '')) : '';
        }

        // Sin JS no hay búsqueda: el campo nace oculto.
        search.hidden = false;
        query.addEventListener('input', filter);
        query.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault(); // Enter en la búsqueda no guarda el formulario
            }
        });
        picker.addEventListener('change', function (event) {
            var group = event.target.closest('[data-jl-menu-group]');
            if (group) {
                refreshCount(group);
            }
        });
        groups.forEach(refreshCount);
    });

    root.addEventListener('submit', function (event) {
        var message = event.target.getAttribute('data-jl-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });
})();
