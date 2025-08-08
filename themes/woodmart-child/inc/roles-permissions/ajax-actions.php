<?php
/**
 * ================================
 * AJAX ACTIONS PARA ROLES Y PERMISOS
 * ================================
 * Aquí definimos las acciones AJAX para manejar
 * las transiciones de estado permitidas según roles,
 * incluyendo seguridad, nonce y validaciones.
 */

/**
 * Verifica nonce y permisos, y cambia el estado del pedido si se permite.
 *
 * @param int    $order_id ID del pedido
 * @param string $new_status Nuevo estado (sin prefijo 'wc-')
 * @param string $cap_key Clave de permiso dentro del ruleset (ej: 'to_processing')
 */
function jl22_handle_order_status_change_ajax($order_id, $new_status, $cap_key) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], "mark_order_as_{$new_status}")) {
        wp_send_json_error('Nonce no válido.');
    }

    if (!$order_id || !is_numeric($order_id)) {
        wp_send_json_error('ID de pedido inválido.');
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Pedido no encontrado.');
    }

    if (!current_user_can('edit_shop_orders')) {
        wp_send_json_error('No tienes permisos para editar pedidos.');
    }

    // Obtener reglas del usuario actual
    $ruleset = get_current_user_ruleset();
    if (!$ruleset || empty($ruleset[$cap_key])) {
        wp_send_json_error('No tienes permiso para realizar esta acción.');
    }

    $current_status = $order->get_status();

    // Validar transición permitida (ejemplo simple, adaptar según necesidades)
    $allowed_transitions = [
        'to_processing' => ['on-hold'],
        'to_impressed'  => ['processing'],
        'to_completed'  => ['impreso'],
        'to_cancelled'  => ['on-hold', 'processing'],
    ];

    if (!isset($allowed_transitions[$cap_key]) || !in_array($current_status, $allowed_transitions[$cap_key])) {
        wp_send_json_error('La transición de estado no es válida.');
    }

    // Cambiar estado
    $order->update_status($new_status, 'Estado cambiado vía AJAX por usuario con permisos.');

    wp_send_json_success("Pedido marcado como {$new_status}.");
}

/**
 * Acción AJAX para marcar como processing
 */
add_action('wp_ajax_mark_order_as_processing', function() {
    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    jl22_handle_order_status_change_ajax($order_id, 'processing', 'to_processing');
});

/**
 * Acción AJAX para marcar como impreso
 */
add_action('wp_ajax_mark_order_as_impreso', function() {
    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    jl22_handle_order_status_change_ajax($order_id, 'impreso', 'to_impressed');
});

/**
 * Acción AJAX para marcar como completed
 */
add_action('wp_ajax_mark_order_as_completed', function() {
    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    jl22_handle_order_status_change_ajax($order_id, 'completed', 'to_completed');
});

/**
 * Acción AJAX para marcar como cancelled
 */
add_action('wp_ajax_mark_order_as_cancelled', function() {
    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    jl22_handle_order_status_change_ajax($order_id, 'cancelled', 'to_cancelled');
});
