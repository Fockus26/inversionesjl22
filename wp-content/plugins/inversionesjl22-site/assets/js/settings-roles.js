/**
 * WooCommerce › Roles y permisos (includes/settings-roles.php).
 *
 * - Al marcar o desmarcar "Ver los pedidos" de un estado, enciende o apaga el resto de su fila
 *   (sin JS hay que guardar para que la fila se encienda).
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

    root.addEventListener('submit', function (event) {
        var message = event.target.getAttribute('data-jl-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });
})();
