<?php
/**
 * Registro de estados personalizados de pedido para WooCommerce
 * Archivo: order-status/custom-status.php
 */

/**
 * Añade el estado personalizado 'impreso' a la lista de estados de WooCommerce
 *
 * @param array $order_statuses Estados actuales
 * @return array Estados modificados con el estado personalizado agregado
 */
function jl22_register_custom_order_statuses($order_statuses) {
    $order_statuses['wc-impreso'] = _x('Impreso', 'Order status', 'woocommerce');
    return $order_statuses;
}
add_filter('woocommerce_register_shop_order_post_statuses', 'jl22_register_custom_order_statuses');

/**
 * Registra el estado personalizado 'impreso' en WordPress como un post status válido
 */
function jl22_add_custom_order_status() {
    register_post_status('wc-impreso', [
        'label'                     => _x('Impreso', 'Order status', 'woocommerce'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Impreso <span class="count">(%s)</span>', 'Impresos <span class="count">(%s)</span>', 'woocommerce'),
    ]);
}
add_action('init', 'jl22_add_custom_order_status');

/**
 * Añade el nuevo estado personalizado 'impreso' al listado de estados visibles en el admin
 *
 * @param array $order_statuses Estados actuales
 * @return array Estados modificados para mostrarse en la lista de pedidos admin
 */
function jl22_add_custom_status_to_order_list($order_statuses) {
    $new_order_statuses = [];

    // Insertamos el estado personalizado justo después de 'processing'
    foreach ($order_statuses as $key => $label) {
        $new_order_statuses[$key] = $label;

        if ($key === 'wc-processing') {
            $new_order_statuses['wc-impreso'] = _x('Impreso', 'Order status', 'woocommerce');
        }
    }

    return $new_order_statuses;
}
add_filter('wc_order_statuses', 'jl22_add_custom_status_to_order_list');
