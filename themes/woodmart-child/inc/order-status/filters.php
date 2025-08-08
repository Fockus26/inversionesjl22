<?php
/**
 * Filtros específicos para el manejo de estados de pedido personalizados
 * Archivo: order-status/filters.php
 */

/**
 * Añade el estado personalizado 'impreso' a los filtros de estado en la lista de pedidos admin
 */
add_filter('woocommerce_order_statuses', function ($order_statuses) {
    if (!isset($order_statuses['wc-impreso'])) {
        // Insertar 'Impreso' después de 'processing'
        $new_order_statuses = [];

        foreach ($order_statuses as $key => $label) {
            $new_order_statuses[$key] = $label;

            if ($key === 'wc-processing') {
                $new_order_statuses['wc-impreso'] = __('Impreso', 'woocommerce');
            }
        }

        return $new_order_statuses;
    }
    return $order_statuses;
});

/**
 * Agrega el estado 'impreso' a la lista de estados visibles en filtros y reportes
 */
add_filter('wc_order_statuses', function ($statuses) {
    if (!isset($statuses['wc-impreso'])) {
        $statuses['wc-impreso'] = __('Impreso', 'woocommerce');
    }
    return $statuses;
});

/**
 * Permite que el estado personalizado 'impreso' pueda aparecer en filtros de la admin
 */
add_filter('bulk_actions-edit-shop_order', function ($bulk_actions) {
    // Agregar una acción masiva para marcar como 'impreso'
    $bulk_actions['mark_impreso'] = __('Marcar como Impreso', 'woocommerce');
    return $bulk_actions;
});

/**
 * Asegura que la búsqueda y filtros por estado incluyan 'impreso'
 */
add_filter('request', function ($vars) {
    if (isset($vars['post_type']) && $vars['post_type'] === 'shop_order') {
        if (!empty($vars['post_status'])) {
            if ($vars['post_status'] === 'impreso') {
                $vars['post_status'] = 'wc-impreso';
            }
        }
    }
    return $vars;
});
