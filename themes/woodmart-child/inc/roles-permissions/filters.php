<?php
/**
 * =========================================
 * FILTROS Y ACCIONES DE ROLES Y PERMISOS
 * =========================================
 * Aquí se manejan los filtros para controlar:
 * - Registro y orden de estados personalizados
 * - Acciones rápidas en lista de pedidos
 * - Acciones rápidas en previsualización
 * - Control de visibilidad de botones (factura PDF)
 * - Filtrado de pestañas de estado en la lista
 * - Acciones por lote (bulk actions)
 * - Filtrado de consulta para mostrar solo pedidos permitidos
 * - Inicialización en admin para roles y permisos
 */

/**
 * BLOQUE 1 – Control de acceso a la página de edición de un pedido
 * Si el rol no tiene permiso para ver pedidos, redirige a la lista.
 */
add_action('admin_init', function () {
    global $pagenow;
    if ($pagenow !== 'post.php') return;

    $order_id = $_GET['post'] ?? null;
    if (!$order_id) return;
    if (get_post_type($order_id) !== 'shop_order') return;

    $order = wc_get_order($order_id);
    if (!$order) return;
    if (current_user_can('administrator')) return;

    $ruleset = get_current_user_ruleset();
    if (!$ruleset || empty($ruleset['can_view_order'])) {
        wp_redirect(admin_url('edit.php?post_type=shop_order'));
        exit;
    }
});

/**
 * BLOQUE 2 - wc_order_statuses
 * Modifica la lista de estados de pedido para agregar o reordenar el estado personalizado 'impreso'.
 */
add_filter('wc_order_statuses', function($order_statuses) {
    // Queremos que 'impreso' aparezca después de 'processing'
    $new_order_statuses = [];

    foreach ($order_statuses as $key => $label) {
        $new_order_statuses[$key] = $label;

        if ($key === 'wc-processing') {
            // Insertamos el estado personalizado
            $new_order_statuses['wc-impreso'] = __('Impreso', 'woocommerce');
        }
    }

    return $new_order_statuses;
}, 10);


/**
 * BLOQUE 3 - woocommerce_admin_order_actions
 * Modifica las acciones rápidas (botones) en la lista de pedidos según estado y permisos del usuario.
 */
add_filter('woocommerce_admin_order_actions', function($actions, $order) {
    $status = $order->get_status();

    // Administradores tienen todas las acciones
    if (current_user_can('administrator')) {
        return $actions;
    }

    $ruleset = get_current_user_ruleset();
    if (!$ruleset) return $actions;

    // Limpiamos acciones originales para controlar completamente
    $actions = [];

    if ($status === 'on-hold' && $ruleset['to_processing']) {
        $actions['processing'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_processing&order_id={$order->get_id()}"), 'mark_order_as_processing'),
            'name'   => __('Procesando', 'woocommerce'),
            'action' => 'processing',
            'icon'   => 'clock',
        ];
    }

    if ($status === 'processing' && $ruleset['to_impressed']) {
        $actions['impreso'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_impreso&order_id={$order->get_id()}"), 'mark_order_as_impreso'),
            'name'   => __('Impreso', 'woocommerce'),
            'action' => 'impreso',
            'icon'   => 'printer',
        ];
    }

    if ($status === 'impreso' && $ruleset['to_completed']) {
        $actions['complete'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_completed&order_id={$order->get_id()}"), 'mark_order_as_completed'),
            'name'   => __('Completar', 'woocommerce'),
            'action' => 'complete',
            'icon'   => 'checkmark',
        ];
    }

    if (in_array($status, ['on-hold', 'processing']) && $ruleset['to_cancelled']) {
        $actions['cancel'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_cancelled&order_id={$order->get_id()}"), 'mark_order_as_cancelled'),
            'name'   => __('Cancelar', 'woocommerce'),
            'action' => 'cancelled',
            'icon'   => 'no', 
        ];
    }

    return $actions;
}, 20, 2);

/**
 * BLOQUE 4 – Acciones en la vista previa de pedidos
 * Controla los botones que aparecen en el popup de previsualización de pedidos,
 * mostrando solo los que el rol puede usar según su reglas.
 */
add_filter('woocommerce_admin_order_preview_actions', function($actions, $order) {
    $id     = $order->get_id();
    $status = $order->get_status();

    // Admin puede ver todas las acciones relevantes
    if (current_user_can('administrator')) {
        if ($status === 'impreso') {
            $actions['complete'] = [
                'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_completed&order_id=$id"), 'mark_order_as_completed'),
                'name'   => __('Completar', 'woocommerce'),
                'action' => 'complete',
            ];
        }
        return $actions;
    }

    $ruleset = get_current_user_ruleset();
    if (!$ruleset) return [];

    // Limpiamos las acciones originales para mostrarlas controladas
    $actions = [];

    if ($status === 'on-hold' && !empty($ruleset['to_processing'])) {
        $actions['processing'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_processing&order_id=$id"), 'mark_order_as_processing'),
            'name'   => __('Procesando', 'woocommerce'),
            'action' => 'processing',
        ];
    }

    if ($status === 'processing' && !empty($ruleset['to_impressed'])) {
        $actions['impreso'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_impreso&order_id=$id"), 'mark_order_as_impreso'),
            'name'   => __('Impreso', 'woocommerce'),
            'action' => 'impreso',
        ];
    }

    if ($status === 'impreso' && !empty($ruleset['to_completed'])) {
        $actions['complete'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_completed&order_id=$id"), 'mark_order_as_completed'),
            'name'   => __('Completar', 'woocommerce'),
            'action' => 'complete',
        ];
    }

    if (in_array($status, ['on-hold', 'processing']) && !empty($ruleset['to_cancelled'])) {
        $actions['cancel'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_cancelled&order_id=$id"), 'mark_order_as_cancelled'),
            'name'   => __('Cancelar', 'woocommerce'),
            'action' => 'cancelled',
        ];
    }

    return $actions;
}, 20, 2);

/**
 * BLOQUE 5 – Control de visibilidad del botón de factura PDF
 * Oculta el botón de factura PDF en la lista de pedidos si el estado
 * está dentro de la lista de estados filtrados para el rol.
 */
add_filter('wpo_wcpdf_listing_actions', function($listing_actions, $order) {
    $ruleset = get_current_user_ruleset();
    if (!$ruleset) return $listing_actions;

    if (in_array($order->get_status(), $ruleset['filter_invoice_status'] ?? [])) {
        unset($listing_actions['invoice']);
    }

    return $listing_actions;
}, 10, 2);

/**
 * BLOQUE 6 – Ocultar pestañas de estado en la lista de pedidos
 * Muestra solo las pestañas (filtros) de estados que el rol tiene permitido ver.
 * Además recalcula el contador de la pestaña "Todos" para mostrar solo los permitidos.
 */
add_filter('views_edit-shop_order', function($views) {
    $ruleset = get_current_user_ruleset();
    if (!$ruleset || current_user_can('administrator')) {
        return $views;
    }

    // Estados permitidos sin prefijo wc-
    $allowed_plain = [];

    if (!empty($ruleset['to_processing'])) $allowed_plain[] = 'on-hold';
    if (!empty($ruleset['to_impressed']))  $allowed_plain[] = 'processing';
    if (!empty($ruleset['to_completed']))  $allowed_plain[] = 'impreso';
    if (!empty($ruleset['to_cancelled'])) {
        // Cancelar puede aplicar a on-hold y processing
        $allowed_plain[] = 'on-hold';
        $allowed_plain[] = 'processing';
    }
    if (!empty($ruleset['extra_view_status'])) {
        foreach ($ruleset['extra_view_status'] as $st) {
            $allowed_plain[] = $st; 
        }
    }

    $allowed_plain = array_unique($allowed_plain);

    // Prefijo wc- para comparar con keys en $views
    $allowed = array_map(fn($s) => 'wc-' . $s, $allowed_plain);

    // Contar pedidos para cada estado permitido
    $counts = wp_count_posts('shop_order');

    // Sumar para "Todos"
    $sum_all = 0;
    foreach ($allowed as $st) {
        $sum_all += intval($counts->{$st} ?? 0);
    }

    // Actualizar contador de "Todos" formateado según locale
    if (isset($views['all'])) {
        $count_text = '(' . number_format_i18n($sum_all) . ')';
        $views['all'] = preg_replace('/\([^\)]*\)/', $count_text, $views['all']);
    }

    // Eliminar pestañas no permitidas
    foreach ($views as $key => $html) {
        if ($key === 'all') continue;
        if (!in_array($key, $allowed, true)) {
            unset($views[$key]);
        }
    }

    return $views;
}, 20);

/**
 * BLOQUE 7 – Filtrar acciones por lote según reglas
 * Muestra solo las acciones masivas permitidas según el estado filtrado
 * y el conjunto de reglas del usuario.
 */
add_filter('bulk_actions-edit-shop_order', function($bulk_actions) {
    $ruleset = get_current_user_ruleset();
    if (!$ruleset) return $bulk_actions;

    $current_status = $_GET['post_status'] ?? 'all';

    // Si está en "Todos", no mostrar acciones masivas
    if ($current_status === 'all') {
        return [];
    }

    // Definición de permisos para acciones masivas según estado
    $actions_map = [
        'wc-on-hold' => [
            'mark_processing' => $ruleset['to_processing'] ?? false,
            'mark_cancelled'  => $ruleset['to_cancelled'] ?? false,
        ],
        'wc-processing' => [
            'mark_impreso'    => $ruleset['to_impressed'] ?? false,
            'mark_cancelled'  => $ruleset['to_cancelled'] ?? false,
        ],
        'wc-impreso' => [
            'mark_completed'  => $ruleset['to_completed'] ?? false,
        ],
        'all' => [
            'mark_processing' => $ruleset['to_processing'] ?? false,
            'mark_impreso'    => $ruleset['to_impressed'] ?? false,
            'mark_completed'  => $ruleset['to_completed'] ?? false,
            'mark_cancelled'  => $ruleset['to_cancelled'] ?? false,
        ],
    ];

    $allowed_actions = $actions_map[$current_status] ?? $actions_map['all'];

    $filtered = [];

    // Ocultar factura si el estado está filtrado para ello
    if (
        isset($bulk_actions['invoice']) &&
        in_array(str_replace('wc-', '', $current_status), $ruleset['filter_invoice_status'] ?? [])
    ) {
        unset($bulk_actions['invoice']);
    }

    foreach ($bulk_actions as $key => $label) {
        if (isset($allowed_actions[$key]) && $allowed_actions[$key]) {
            $filtered[$key] = $label;
        }
        // Preservar acciones no controladas (exportar, plugins, etc)
        if (!isset($actions_map['all'][$key]) && !isset($actions_map[$current_status][$key])) {
            $filtered[$key] = $label;
        }
    }

    // Remover basura habitual
    unset($filtered['trash'], $filtered['mark_on-hold']);

    return $filtered;
}, 20);

/**
 * BLOQUE 8 – Filtro pre_get_posts
 * Limita la consulta de pedidos a solo los estados permitidos para el rol,
 * tanto en vista “Todos” como en filtros específicos.
 */
add_action('pre_get_posts', function($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') !== 'shop_order') return;
    if (current_user_can('administrator')) return;

    $ruleset = get_current_user_ruleset();
    if (!$ruleset) return;

    // Construir lista permitida con prefijo wc-
    $allowed = [];
    if (!empty($ruleset['to_processing'])) $allowed[] = 'wc-on-hold';
    if (!empty($ruleset['to_impressed']))  $allowed[] = 'wc-processing';
    if (!empty($ruleset['to_completed']))  $allowed[] = 'wc-impreso';
    if (!empty($ruleset['to_cancelled'])) {
        $allowed[] = 'wc-on-hold';
        $allowed[] = 'wc-processing';
    }
    if (!empty($ruleset['extra_view_status'])) {
        foreach ($ruleset['extra_view_status'] as $st) {
            $allowed[] = 'wc-' . $st;
        }
    }
    $allowed = array_unique($allowed);

    $filter = isset($_GET['post_status']) ? sanitize_key($_GET['post_status']) : 'all';

    if ($filter === 'all' || !$filter) {
        $query->set('post_status', $allowed);
    } else {
        if (in_array($filter, $allowed, true)) {
            $query->set('post_status', [$filter]);
        } else {
            // No permitido → ningún resultado
            $query->set('post_status', ['do-not-match']);
        }
    }
}, 20);