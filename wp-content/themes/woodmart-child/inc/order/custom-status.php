<?php
/**
 * Registro y personalización del estado de pedido personalizado "Impreso"
**/

// 1. Registrar el nuevo estado de pedido "impreso" en WooCommerce
//    - Se registra como "wc-impressed"
//    - Será visible en el admin, en las búsquedas y en la lista de estados
add_action('init', function() {
    register_post_status('wc-impressed', [
      'label'                     => 'Impreso',
      'public'                    => true,
      'exclude_from_search'       => false,
      'show_in_admin_all_list'    => true,
      'show_in_admin_status_list' => true,
      'label_count'               => _n_noop('Impreso (%s)', 'Impreso (%s)'),
    ]);
    register_post_status('wc-reserved', [
      'label'                     => 'Reservado',
      'public'                    => true,
      'exclude_from_search'       => false,
      'show_in_admin_all_list'    => true,
      'show_in_admin_status_list' => true,
      'label_count'               => _n_noop('Reservado (%s)', 'Reservado (%s)'),
    ]);
});

// 2. Añadir "Reservardo" después de "En espera" y "Impreso" después de "Procesando" en la lista de estados de pedido
add_filter('wc_order_statuses', function($order_statuses) {
    $new_order_statuses = [];
    foreach ($order_statuses as $key => $label) {
        $new_order_statuses[$key] = $label;
        if ($key === 'wc-on-hold') {
            $new_order_statuses['wc-reserved'] = 'Reservado';
        }
        if ($key === 'wc-processing') {
            $new_order_statuses['wc-impressed'] = 'Impreso';
        }
    }
    return $new_order_statuses;
});

// 3. Agregar botón de acción rápida para cambiar de "En espera" a "Reservado" y de "Procesando" a "Impreso"
add_filter('woocommerce_admin_order_actions', function($actions, $order) {
    if ($order->get_status() === 'on-hold') {
        $actions['reserved'] = [
            'url'       => wp_nonce_url(admin_url('admin-ajax.php?action=mark_order_as_reserved&order_id=' . $order->get_id()), 'mark_order_as_reserved'),
            'name'      => __('Reservado', 'woocommerce'),
            'action'    => 'reserved'
        ];
        $actions['processing'] = [
            'url'    => wp_nonce_url(admin_url('admin-ajax.php?action=mark_order_as_processing&order_id=' . $order->get_id()), 'mark_order_as_processing'),
            'name'   => __('Procesando', 'woocommerce'),
            'action' => 'processing'
        ];
    }
    if ($order->get_status() === 'processing') {
        $actions['impressed'] = [
            'url'       => wp_nonce_url(admin_url('admin-ajax.php?action=mark_order_as_impressed&order_id=' . $order->get_id()), 'mark_order_as_impressed'),
            'name'      => __('Impreso', 'woocommerce'),
            'action'    => 'impressed'
        ];
    }
    return $actions;
}, 100, 2);

// 4. Estilos CSS para los íconos de los botones rápidos y color del estado "impreso"
add_action('admin_head', function() {
    echo '<style>
        /* Botones de acción rápida */
        td .wc-action-button-reserved::after
        td .wc-action-button-impressed::after,
        td .wc-action-button-cancelled::after {
            display: inline-block;
            font-family: Dashicons;
            margin-left: 5px;
            vertical-align: middle;
        }

        td .wc-action-button-reserved::after {
          content: "\f155";
        }

        td .wc-action-button-impressed::after {
            content: "\f109"; /* Ícono de impresora */
        }

        td .wc-action-button-cancelled::after {
            content: "\f182"; /* Ícono de cancelación */
        }
        
        .status-reserved,
        .order-status.status-reserved {
            background-color: #6c0303 !important;
            color: #fff !important;
        }

        .status-impressed,
        .order-status.status-impressed {
            background-color: #fbbf12 !important;
            color: #000 !important;
        }
    </style>';
});

// 5. Añadir acción por lote "Cambiar estado a Reservado" y "Cambiar estado a Impreso" según el estado de la vista
add_filter('bulk_actions-edit-shop_order', function($bulk_actions) {
    $current_status = $_GET['post_status'] ?? '';  
  
  // Si el usuario es administrador, mostrar todas las acciones en cualquier estado
    if (current_user_can('administrator')) {
        $bulk_actions['mark_reserved']   = __('Cambiar estado a reservado', 'woocommerce');
        $bulk_actions['mark_processing'] = __('Cambiar estado a procesando', 'woocommerce');
        $bulk_actions['mark_impressed']  = __('Cambiar estado a impreso', 'woocommerce');
        $bulk_actions['mark_completed']  = __('Cambiar estado a completado', 'woocommerce');
        return $bulk_actions;
    }
    
    // Copia las acciones existentes
    $new_actions = $bulk_actions;

    // Si estamos en la vista de "En espera"
    if ($current_status === 'wc-on-hold') {
        $new_actions['mark_reserved'] = __('Cambiar estado a reservado', 'woocommerce');
        $new_actions['mark_processing'] = __('Cambiar estado a procesando', 'woocommerce'); 
    }

    // Si estamos en la vista de "Reservado"
    if ($current_status === 'wc-reserved') {
        $new_actions['mark_processing'] = __('Cambiar estado a procesando', 'woocommerce');
    }

    // Si estamos en la vista de "Procesando"
    if ($current_status === 'wc-processing') {
        $new_actions['mark_impressed'] = __('Cambiar estado a impreso', 'woocommerce');
    }

    // Si estamos en la vista de "Impreso"
    if ($current_status === 'wc-impressed') {
        $new_actions['mark_completed'] = __('Cambiar estado a completado', 'woocommerce');
    }

    return $new_actions;
});

// 6. Lógica para procesar la acción por lote y actualizar pedidos a "Reservar" o "Impreso"
add_filter('handle_bulk_actions-edit-shop_order', function($redirect_to, $action, $post_ids) {
    if ($action === 'mark_reserved') {
        foreach ($post_ids as $post_id) {
            $order = wc_get_order($post_id);
            if ($order && $order->get_status() === 'on-hold') {
                $order->update_status('reserved', __('Marcado como reservado por acción en lote.', 'woocommerce'));
            }
        }
        return add_query_arg('bulk_reserved', count($post_ids), $redirect_to);
    }
    if ($action === 'mark_processing') {
        foreach ($post_ids as $post_id) {
            $order = wc_get_order($post_id);
            if ($order && $order->get_status() === 'on-hold') {
                $order->update_status('processing', __('Marcado como procesando por acción en lote.', 'woocommerce'));
            }
        }
        return add_query_arg('bulk_processing', count($post_ids), $redirect_to);
    }
    if ($action === 'mark_impressed') {
        foreach ($post_ids as $post_id) {
            $order = wc_get_order($post_id);
            if ($order && $order->get_status() === 'processing') {
                $order->update_status('impressed', __('Marcado como impreso por acción en lote.', 'woocommerce'));
            }
        }
        return add_query_arg('bulk_impressed', count($post_ids), $redirect_to);
    }
    return $redirect_to;
}, 10, 3);

// 7. Mostrar mensaje en el panel admin cuando se marquen pedidos como "Reservar" o "Impreso" en lote
add_action('admin_notices', function() {
    if (!empty($_REQUEST['bulk_reserved'])) {
        printf('<div id="message" class="updated fade"><p>%s pedidos marcados como Reservado.</p></div>', intval($_REQUEST['bulk_reserved']));
    }
    if (!empty($_REQUEST['bulk_impressed'])) {
        printf('<div id="message" class="updated fade"><p>%s pedidos marcados como Impreso.</p></div>', intval($_REQUEST['bulk_impressed']));
    }
});

// 8. Agregar botón "Impreso" y "Reservado" en la vista previa del pedido (sin ícono)
add_filter('woocommerce_admin_order_preview_actions', function($actions, $order) {
    $status = $order->get_status();
    $new_actions = $actions;

    // Botón para pasar de "En espera" a "Reservado"
    if ($status === 'on-hold') {
        $new_actions['reserved'] = [
            'url'    => wp_nonce_url(admin_url('admin-ajax.php?action=mark_order_as_reserved&order_id=' . $order->get_id()), 'mark_order_as_reserved'),
            'name'   => __('Reservado', 'woocommerce'),
            'action' => ''
        ];
    }

    // Botón para pasar de "Reservado" a "Procesando"
    if ($status === 'reserved') {
        $new_actions['processing'] = [
            'url'    => wp_nonce_url(admin_url('admin-ajax.php?action=mark_order_as_processing&order_id=' . $order->get_id()), 'mark_order_as_processing'),
            'name'   => __('Procesando', 'woocommerce'),
            'action' => ''
        ];
    }

    // Botón para pasar de "Procesando" a "Impreso"
    if ($status === 'processing') {
        $new_actions['impressed'] = [
            'url'    => wp_nonce_url(admin_url('admin-ajax.php?action=mark_order_as_impressed&order_id=' . $order->get_id()), 'mark_order_as_impressed'),
            'name'   => __('Impreso', 'woocommerce'),
            'action' => ''
        ];
    }

    return $new_actions;
}, 20, 2);

// 9. Mostrar el filtro de estado "Impreso" y "Reservado" en la barra de filtros del listado de pedidos
add_filter('views_edit-shop_order', function($views) {
    global $wp_query;
    $current = isset($_GET['post_status']) ? $_GET['post_status'] : '';
    $impreso_count = wp_count_posts('shop_order')->{"wc-impressed"} ?? 0;
    $reserved_count = wp_count_posts('shop_order')->{"wc-reserved"} ?? 0;

    // Agregar enlace al filtro si hay pedidos en este estado
    if ($reserved_count > 0) {
        $class = $current === 'wc-reserved' ? 'current' : '';
        $url = admin_url('edit.php?post_status=wc-reserved&post_type=shop_order');
        $views['wc-reserved'] = sprintf(
            '<li class="wc-reserved"><a href="%s" class="%s">%s <span class="count">(%s)</span></a></li>',
            esc_url($url),
            esc_attr($class),
            __('Reservado', 'woocommerce'),
            intval($reserved_count)
        );
    }
    if ($impreso_count > 0) {
        $class = $current === 'wc-impressed' ? 'current' : '';
        $url = admin_url('edit.php?post_status=wc-impressed&post_type=shop_order');
        $views['wc-impressed'] = sprintf(
            '<li class="wc-impressed"><a href="%s" class="%s">%s <span class="count">(%s)</span></a></li>',
            esc_url($url),
            esc_attr($class),
            __('Impreso', 'woocommerce'),
            intval($impreso_count)
        );
    }

    // Reordenar para que aparezcan después de sus respectivos estados
    $new_views = [];
    foreach ($views as $key => $html) {
        $new_views[$key] = $html;
        if ($key === 'wc-on-hold' && isset($views['wc-reserved'])) {
            $new_views['wc-reserved'] = $views['wc-reserved'];
        }
        if ($key === 'wc-processing' && isset($views['wc-impressed'])) {
            $new_views['wc-impressed'] = $views['wc-impressed'];
        }
    }
    return $new_views;
});