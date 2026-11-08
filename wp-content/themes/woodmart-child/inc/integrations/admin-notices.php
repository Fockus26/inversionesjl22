<?php
/**
 * ===========================================
 * ADMIN NOTICES PERSONALIZADOS JL22
 * ===========================================
 * Muestra avisos visuales en el admin de WooCommerce
 * según el resultado de las acciones AJAX de transición
 * de estado de pedidos.
 * - Si jl22_notice=error: fondo rojo, mensaje de validación.
 * - Si jl22_notice=success: fondo verde, mensaje de éxito.
 * - Los estilos se aplican inline para máxima compatibilidad.
 */
add_action('admin_notices', function() {
    if (isset($_GET['jl22_notice'])) {
        if ($_GET['jl22_notice'] === 'error') {
            echo '<div class="notice notice-error is-dismissible" style="background-color: #ef5350 !important; color: #fbd7d9 !important; font-weight: bold !important;"><p>Debes seleccionar Pagado o Por Cobrar en el pedido antes de cambiar el estado.</p></div>';
        }
        if ($_GET['jl22_notice'] === 'success') {
            echo '<div class="notice notice-success is-dismissible" style="background-color: #ccead0 !important;"><p>El estado del pedido se cambió correctamente.</p></div>';
        }
    }
});