<?php
/**
 * ============================
 * ORDER ROLES VISIBILITY LOGIC
 * ============================
 */

// Inicializamos el array global
$user_restrictions = [];

/**
 * Registra restricciones para un rol de usuario
 */
function limit_user_permissions($username, $ruleset) {
    global $user_restrictions;
    $user_restrictions[$username] = $ruleset;
}

/**
 * Obtiene el ruleset del usuario actual
 */
function get_current_user_ruleset() {
    global $user_restrictions;

    // Admin no tiene restricciones
    if (current_user_can('administrator')) return null;

    $user = wp_get_current_user();
    foreach ($user->roles as $role) {
        if (isset($user_restrictions[$role])) {
            return $user_restrictions[$role];
        }
    }
    return null;
}

/**
 * Valida si el usuario puede hacer una transición
 */
function can_transition($from, $to, $ruleset) {
    if (!$ruleset) return false;

    $transition_map = [
        'on-hold'    => 'to_processing',
        'processing' => 'to_impressed',
        'impreso'    => 'to_completed',
    ];

    $key = $transition_map[$from] ?? '';
    return $key && isset($ruleset[$key]) ? $ruleset[$key] : false;
}

// Incluimos la configuración externa
require_once __DIR__ . '/roles-permissions-config.php';

add_action('admin_init', function () {
    global $pagenow;

    if ($pagenow !== 'post.php') return;

    $order_id = $_GET['post'] ?? null;
    if (!$order_id) return;

    // Aseguramos que sea un pedido
    if (get_post_type($order_id) !== 'shop_order') return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    // Los administradores siempre pueden ver
    if (current_user_can('administrator')) return;

    $ruleset = get_current_user_ruleset();
    if (!$ruleset || empty($ruleset['can_view_order'])) {
        wp_redirect(admin_url('edit.php?post_type=shop_order'));
        exit;
    }
});

add_filter('wc_order_statuses', function ($statuses) {
    global $post;
    if (!$post || get_post_type($post) !== 'shop_order') return $statuses;

    if (current_user_can('administrator')) return $statuses;

    $ruleset = get_current_user_ruleset();
    if (!$ruleset || empty($ruleset['can_view_order'])) return [];

    $order = wc_get_order($post->ID);
    if (!$order) return [];

    $status = 'wc-' . $order->get_status(); // el actual
    $allowed = [$status]; // incluir el actual siempre

    // Agregamos solo los estados a los que tiene permiso
    if ($status === 'wc-on-hold' && !empty($ruleset['to_processing'])) {
        $allowed[] = 'wc-processing';
    }

    if ($status === 'wc-processing' && !empty($ruleset['to_impressed'])) {
        $allowed[] = 'wc-impreso';
    }

    if ($status === 'wc-impreso' && !empty($ruleset['to_completed'])) {
        $allowed[] = 'wc-completed';
    }

    if (in_array($status, ['wc-on-hold', 'wc-processing']) && !empty($ruleset['to_cancelled'])) {
        $allowed[] = 'wc-cancelled';
    }

    return array_filter($statuses, function ($label, $key) use ($allowed) {
        return in_array($key, $allowed);
    }, ARRAY_FILTER_USE_BOTH);
});

// === ACCIONES RÁPIDAS ===
add_filter('woocommerce_admin_order_actions', function($actions, $order) {
    $status = $order->get_status();
    $id     = $order->get_id();
    
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
    if (!$ruleset) return $actions;

    unset($actions['complete'], $actions['processing'], $actions['invoice'], $actions['impreso']);

    if ($status === 'on-hold' && $ruleset['to_processing']) {
        $actions['processing'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_processing&order_id=$id"), 'mark_order_as_processing'),
            'name'   => __('Procesando', 'woocommerce'),
            'action' => 'processing',
        ];
    }

    if ($status === 'processing' && $ruleset['to_impressed']) {
        $actions['impreso'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_impreso&order_id=$id"), 'mark_order_as_impreso'),
            'name'   => __('Impreso', 'woocommerce'),
            'action' => 'impreso',
        ];
    }

    if ($status === 'impreso' && $ruleset['to_completed']) {
        $actions['complete'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_completed&order_id=$id"), 'mark_order_as_completed'),
            'name'   => __('Completar', 'woocommerce'),
            'action' => 'complete',
        ];
    }

    if (in_array($status, ['on-hold', 'processing']) && $ruleset['to_cancelled']) {
        $actions['cancel'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_cancelled&order_id=$id"), 'mark_order_as_cancelled'),
            'name'   => __('Cancelar', 'woocommerce'),
            'action' => 'cancelled',
        ];
    }

    return $actions;
}, 100, 2);

// === VISTA PREVIA DE PEDIDOS ===
add_filter('woocommerce_admin_order_preview_actions', function($actions, $order) {
    $id     = $order->get_id();
    $status = $order->get_status();

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

    // Limpiamos las acciones originales
    $actions = [];

    if ($status === 'on-hold' && $ruleset['to_processing']) {
        $actions['processing'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_processing&order_id=$id"), 'mark_order_as_processing'),
            'name'   => __('Procesando', 'woocommerce'),
            'action' => 'processing',
        ];
    }

    if ($status === 'processing' && $ruleset['to_impressed']) {
        $actions['impreso'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_impreso&order_id=$id"), 'mark_order_as_impreso'),
            'name'   => __('Impreso', 'woocommerce'),
            'action' => 'impreso',
        ];
    }

    if ($status === 'impreso' && $ruleset['to_completed']) {
        $actions['complete'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_completed&order_id=$id"), 'mark_order_as_completed'),
            'name'   => __('Completar', 'woocommerce'),
            'action' => 'complete',
        ];
    }

    if (in_array($status, ['on-hold', 'processing']) && $ruleset['to_cancelled']) {
        $actions['cancel'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_cancelled&order_id=$id"), 'mark_order_as_cancelled'),
            'name'   => __('Cancelar', 'woocommerce'),
            'action' => 'cancelled',
        ];
    }

    return $actions;
}, 20, 2);

// === CONTROL DE VISIBILIDAD DE FACTURA ===
add_filter('wpo_wcpdf_listing_actions', function($listing_actions, $order) {
    $ruleset = get_current_user_ruleset();
    if (!$ruleset) return $listing_actions;

    if (in_array($order->get_status(), $ruleset['filter_invoice_status'])) {
        unset($listing_actions['invoice']);
    }

    return $listing_actions;
}, 10, 2);

// === OCULTAR FILTROS DE ESTADO EN LA LISTA DE PEDIDOS ===
add_filter('views_edit-shop_order', function($views) {
    $ruleset = get_current_user_ruleset();
    if (!$ruleset || current_user_can('administrator')) {
        return $views;
    }

    // Estados permitidos sin prefijo
    $allowed_plain = [];
    if (!empty($ruleset['to_processing'])) $allowed_plain[] = 'on-hold';
    if (!empty($ruleset['to_impressed']))  $allowed_plain[] = 'processing';
    if (!empty($ruleset['to_completed']))  $allowed_plain[] = 'impreso';
    if (!empty($ruleset['to_cancelled'])) {
        $allowed_plain[] = 'on-hold';
        $allowed_plain[] = 'processing';
    }
    $allowed_plain = array_unique($allowed_plain);

    // Valores con prefijo para comparar claves de $views
    $allowed = array_map(function($s){ return 'wc-' . $s; }, $allowed_plain);

    // Contar pedidos para cada estado
    $counts = wp_count_posts('shop_order');
    $sum_all = 0;
    foreach ($allowed as $st) {
        $sum_all += intval( $counts->{$st} ?? 0 );
    }

    // Reconstruir pestaña “Todos” con conteo correcto y formateado
    if (isset($views['all'])) {
        $sum_all = 0;
        foreach ($allowed as $st) {
            $sum_all += intval( $counts->{$st} ?? 0 );
        }
        // Formateamos según locale (p. ej. "1.234" o "1,234")
        $count_text = '(' . number_format_i18n($sum_all) . ')';
        // Reemplazamos TODO lo que haya entre paréntesis
        $views['all'] = preg_replace('/\([^\)]*\)/', $count_text, $views['all']);
    }


    // Remover pestañas no permitidas
    foreach ($views as $key => $html) {
        if ($key === 'all') continue;
        // $key como 'wc-processing', 'wc-impreso', etc.
        if (!in_array($key, $allowed, true)) {
            unset($views[$key]);
        }
    }

    return $views;
}, 20);

// === FILTRAR ACCIONES POR LOTE SEGÚN REGLAS DEL USUARIO ===
add_filter('bulk_actions-edit-shop_order', function($bulk_actions) {
    $ruleset = get_current_user_ruleset();
    if (!$ruleset) return $bulk_actions;

    $current_status = $_GET['post_status'] ?? 'all';

    // Si está en "Todos", no mostramos ninguna acción por lote
    if ($current_status === 'all') {
        return [];
    }

    // Mapa completo de acciones válidas según estado y reglas
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

    // Usar acciones para el estado actual o por defecto las de 'all'
    $allowed_actions = $actions_map[$current_status] ?? $actions_map['all'];

    $filtered = [];

    if (
        isset($bulk_actions['invoice']) &&
        in_array(str_replace('wc-', '', $current_status), $ruleset['filter_invoice_status'] ?? [])
    ) {
        unset($bulk_actions['invoice']);
    }

    foreach ($bulk_actions as $key => $label) {
        // Si está permitido en el estado actual, lo dejamos
        if (isset($allowed_actions[$key]) && $allowed_actions[$key]) {
            $filtered[$key] = $label;
        }

        // Preservar acciones que no controlamos (como exportar, plugins, etc.)
        if (!isset($actions_map['all'][$key]) && !isset($actions_map[$current_status][$key])) {
            $filtered[$key] = $label;
        }
    }

    if (isset($filtered['trash'])) {
        unset($filtered['trash']);
    }

    if (isset($filtered['mark_on-hold'])) {
        unset($filtered['mark_on-hold']);
    }

    return $filtered;
}, 20);

add_action('pre_get_posts', function($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') !== 'shop_order') return;
    if (current_user_can('administrator')) return;

    $ruleset = get_current_user_ruleset();
    if (!$ruleset) return;

    // Construir lista de estados permitidos (con prefijo wc-)
    $allowed = [];
    if (!empty($ruleset['to_processing'])) $allowed[] = 'wc-on-hold';
    if (!empty($ruleset['to_impressed']))  $allowed[] = 'wc-processing';
    if (!empty($ruleset['to_completed']))  $allowed[] = 'wc-impreso';
    if (!empty($ruleset['to_cancelled'])) {
        $allowed[] = 'wc-on-hold';
        $allowed[] = 'wc-processing';
    }
    $allowed = array_unique($allowed);

    // ¿Qué filtro está activo?
    $filter = isset($_GET['post_status']) ? sanitize_key($_GET['post_status']) : 'all';

    if ($filter === 'all' || !$filter) {
        // Vista “Todos”: solo los permitidos
        $query->set('post_status', $allowed);
    } else {
        // Vista específica: solo si ese estado está permitido
        $target = $filter;
        if (in_array($target, $allowed, true)) {
            $query->set('post_status', [$target]);
        } else {
            // no permitidos → ningún resultado
            $query->set('post_status', ['do-not-match']);
        }
    }
}, 20);