<?php
/**
 * Manejo de transiciones de estado para pedidos WooCommerce
 * Archivo: order-status/transitions.php
 */

/**
 * Cambia el estado del pedido de forma segura, verificando nonce y permisos
 *
 * @param int    $order_id ID del pedido
 * @param string $new_status Nuevo estado con prefijo wc- (ej. wc-impreso)
 * @param string $capability Capacidad requerida para cambiar a este estado
 */
function jl22_change_order_status($order_id, $new_status, $capability) {
    if (!current_user_can($capability)) {
        wp_send_json_error('No tienes permiso para cambiar el estado');
        wp_die();
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Pedido no encontrado');
        wp_die();
    }

    $order->update_status($new_status);
    wp_send_json_success('Estado cambiado correctamente');
    wp_die();
}

/**
 * Acción AJAX para marcar pedido como Processing
 */
function jl22_mark_order_as_processing() {
    check_admin_referer('mark_order_as_processing');

    $order_id = intval($_GET['order_id'] ?? 0);
    jl22_change_order_status($order_id, 'wc-processing', 'edit_shop_orders');
}
add_action('wp_ajax_mark_order_as_processing', 'jl22_mark_order_as_processing');

/**
 * Acción AJAX para marcar pedido como Impreso
 */
function jl22_mark_order_as_impreso() {
    check_admin_referer('mark_order_as_impreso');

    $order_id = intval($_GET['order_id'] ?? 0);
    jl22_change_order_status($order_id, 'wc-impreso', 'edit_shop_orders');
}
add_action('wp_ajax_mark_order_as_impreso', 'jl22_mark_order_as_impreso');

/**
 * Acción AJAX para marcar pedido como Completed
 */
function jl22_mark_order_as_completed() {
    check_admin_referer('mark_order_as_completed');

    $order_id = intval($_GET['order_id'] ?? 0);
    jl22_change_order_status($order_id, 'wc-completed', 'edit_shop_orders');
}
add_action('wp_ajax_mark_order_as_completed', 'jl22_mark_order_as_completed');

/**
 * Acción AJAX para marcar pedido como Cancelled
 */
function jl22_mark_order_as_cancelled() {
    check_admin_referer('mark_order_as_cancelled');

    $order_id = intval($_GET['order_id'] ?? 0);
    jl22_change_order_status($order_id, 'wc-cancelled', 'edit_shop_orders');
}
add_action('wp_ajax_mark_order_as_cancelled', 'jl22_mark_order_as_cancelled');

/**
 * Filtro para validar transiciones de estado permitidas según reglas del usuario
 * Se usa para evitar que roles sin permiso cambien estados directamente
 *
 * @param bool   $allowed Si la transición está permitida
 * @param string $from Estado actual sin prefijo
 * @param string $to Estado destino sin prefijo
 * @param WC_Order $order Objeto del pedido
 * @return bool
 */
function jl22_validate_order_status_transition($allowed, $from, $to, $order) {
    if (current_user_can('administrator')) {
        return true;
    }

    // Obtener reglas del usuario actual
    $ruleset = get_current_user_ruleset();
    if (!$ruleset) {
        return false;
    }

    // Mapa de transiciones con claves de permiso
    $transition_map = [
        'on-hold'    => 'to_processing',
        'processing' => 'to_impressed',
        'impreso'    => 'to_completed',
    ];

    $key = $transition_map[$from] ?? '';

    if (!$key) {
        return false;
    }

    return !empty($ruleset[$key]);
}
add_filter('woocommerce_valid_order_statuses_for_payment_complete', 'jl22_validate_order_status_transition', 10, 4);
