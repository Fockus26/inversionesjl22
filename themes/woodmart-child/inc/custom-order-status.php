<?php
/**
 * Registro y personalización del estado de pedido personalizado "Impreso"
**/

// 1. Registrar el nuevo estado de pedido "impreso" en WooCommerce
//    - Se registra como "wc-impreso"
//    - Será visible en el admin, en las búsquedas y en la lista de estados
add_action('init', function() {
    register_post_status('wc-impreso', [
        'label'                     => 'Impreso',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Impreso (%s)', 'Impreso (%s)'),
    ]);
});

// 2. Añadir "Impreso" justo después de "Procesando" en la lista de estados de pedido
add_filter('wc_order_statuses', function($order_statuses) {
    $new_order_statuses = [];
    foreach ($order_statuses as $key => $label) {
        $new_order_statuses[$key] = $label;
        if ($key === 'wc-processing') {
            $new_order_statuses['wc-impreso'] = 'Impreso';
        }
    }
    return $new_order_statuses;
});

// 3. Agregar botón de acción rápida en la lista de pedidos para cambiar de "Procesando" a "Impreso"
add_filter('woocommerce_admin_order_actions', function($actions, $order) {
    if ($order->get_status() === 'processing') {
        $actions['impreso'] = [
            'url'       => wp_nonce_url(admin_url('admin-ajax.php?action=mark_order_as_impreso&order_id=' . $order->get_id()), 'mark_order_as_impreso'),
            'name'      => __('Impreso', 'woocommerce'),
            'action'    => 'impreso' // Clase CSS para ícono
        ];
    }
    return $actions;
}, 100, 2);

// 4. Estilos CSS para los íconos de los botones rápidos y color del estado "impreso"
add_action('admin_head', function() {
    echo '<style>
        /* Botones de acción rápida */
        .wc-action-button-impreso::after,
        .wc-action-button-cancelled::after {
            display: inline-block;
            font-family: Dashicons;
            margin-left: 5px;
            vertical-align: middle;
        }

        .wc-action-button-impreso::after {
            content: "\f109"; /* Ícono de impresora */
        }

        .wc-action-button-cancelled::after {
            content: "\f182"; /* Ícono de cancelación */
        }

        /* Color especial para el estado impreso */
        .status-impreso,
        .order-status.status-impreso {
            background-color: #fbbf12 !important;
            color: #000 !important;
        }
    </style>';
});

// 5. Añadir acción por lote "Cambiar estado a Impreso" después de "Procesando"
add_filter('bulk_actions-edit-shop_order', function($bulk_actions) {
    $new_actions = [];
    $inserted = false;
    foreach ($bulk_actions as $key => $label) {
        $new_actions[$key] = $label;
        if ($key === 'mark_processing') {
            $new_actions['mark_impreso'] = 'Cambiar estado a Impreso';
            $inserted = true;
        }
    }
    if (!$inserted) {
        $new_actions['mark_impreso'] = 'Cambiar estado a Impreso';
    }
    return $new_actions;
});

// 6. Lógica para procesar la acción por lote y actualizar pedidos a "Impreso"
add_filter('handle_bulk_actions-edit-shop_order', function($redirect_to, $action, $post_ids) {
    if ($action !== 'mark_impreso') return $redirect_to;
    foreach ($post_ids as $post_id) {
        $order = wc_get_order($post_id);
        if ($order && $order->get_status() === 'processing') {
            $order->update_status('impreso', __('Marcado como impreso por acción en lote.', 'woocommerce'));
        }
    }
    return add_query_arg('bulk_impreso', count($post_ids), $redirect_to);
}, 10, 3);

// 7. Mostrar mensaje en el panel admin cuando se marquen pedidos como "Impreso" en lote
add_action('admin_notices', function() {
    if (!empty($_REQUEST['bulk_impreso'])) {
        printf('<div id="message" class="updated fade"><p>%s pedidos marcados como Impreso.</p></div>', intval($_REQUEST['bulk_impreso']));
    }
});

// 8. Agregar botón "Impreso" en la vista previa del pedido (sin ícono)
add_filter('woocommerce_admin_order_preview_actions', function($actions, $order) {
    if ($order->get_status() === 'processing') {
        $new_actions = [];
        foreach ($actions as $key => $action) {
            $new_actions[$key] = $action;
            if ($key === 'processing') {
                $new_actions['impreso'] = [
                    'url'    => wp_nonce_url(admin_url('admin-ajax.php?action=mark_order_as_impreso&order_id=' . $order->get_id()), 'mark_order_as_impreso'),
                    'name'   => __('Impreso', 'woocommerce'),
                    'action' => ''
                ];
            }
        }
        if (!isset($new_actions['impreso'])) {
            $new_actions['impreso'] = [
                'url'    => wp_nonce_url(admin_url('admin-ajax.php?action=mark_order_as_impreso&order_id=' . $order->get_id()), 'mark_order_as_impreso'),
                'name'   => __('Impreso', 'woocommerce'),
                'action' => ''
            ];
        }
        return $new_actions;
    }
    return $actions;
}, 20, 2);

// 9. Mostrar el filtro de estado "Impreso" en la barra de filtros (subsubsub) del listado de pedidos
add_filter('views_edit-shop_order', function($views) {
    global $wp_query;
    $current = isset($_GET['post_status']) ? $_GET['post_status'] : '';
    $impreso_count = wp_count_posts('shop_order')->{"wc-impreso"} ?? 0;

    // Agregar enlace al filtro si hay pedidos en este estado
    if ($impreso_count > 0) {
        $class = $current === 'wc-impreso' ? 'current' : '';
        $url = admin_url('edit.php?post_status=wc-impreso&post_type=shop_order');
        $views['wc-impreso'] = sprintf(
            '<li class="wc-impreso"><a href="%s" class="%s">Impreso <span class="count">(%s)</span></a></li>',
            esc_url($url),
            esc_attr($class),
            intval($impreso_count)
        );
    }

    // Reordenar para que aparezca después de "Procesando"
    if (isset($views['wc-impreso']) && isset($views['wc-processing'])) {
        $new_views = [];
        foreach ($views as $key => $html) {
            $new_views[$key] = $html;
            if ($key === 'wc-processing') {
                $new_views['wc-impreso'] = $views['wc-impreso'];
            }
        }
        $views = $new_views;
    }
    return $views;
});
