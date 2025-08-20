<?php
/**
 * ============================
 * ORDER ROLES VISIBILITY LOGIC
 * ============================
 * Este archivo controla:
 * - Qué pedidos puede ver cada rol.
 * - A qué estados puede cambiar un pedido cada rol.
 * - Qué acciones rápidas, acciones por lote y botones de vista previa están visibles según el rol.
 * - Si el usuario puede ver/descargar la factura (PDF) en cada estado.
 * - Qué pestañas de estado y qué pedidos aparecen en la lista de WooCommerce.
 */

// Array global para almacenar reglas por rol
$user_restrictions = [];

/**
 * Registra un conjunto de permisos/restricciones para un rol específico
 * @param string $username Rol del usuario (p. ej. seo_manager)
 * @param array  $ruleset  Reglas de permisos
 */
function limit_user_permissions($username, $ruleset) {
    global $user_restrictions;
    $user_restrictions[$username] = $ruleset;
}

/**
 * Devuelve el conjunto de reglas (ruleset) del usuario actual
 * - Los administradores no tienen restricciones
 */
function get_current_user_ruleset() {
    global $user_restrictions;
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
 * Determina si un usuario (según ruleset) puede hacer una transición de estado
 * @param string $from Estado actual (sin prefijo)
 * @param string $to   Estado destino (sin prefijo)
 */
function can_transition($from, $to, $ruleset) {
    if (!$ruleset) return false;

    // Mapa que asocia un estado inicial con la clave de permiso
    $transition_map = [
        'on-hold'    => 'to_reserved',
        'reserved'   => 'to_processing',
        'processing' => 'to_impressed',
        'impressed'    => 'to_completed',
    ];

    $key = $transition_map[$from] ?? '';
    return $key && isset($ruleset[$key]) ? $ruleset[$key] : false;
}

// Carga las reglas de permisos desde un archivo de configuración externo
require_once __DIR__ . '/config.php';

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
 * BLOQUE 2 – Filtrar lista de estados disponibles en el selector de estado del pedido
 * Muestra solo el estado actual y aquellos a los que el rol puede transicionar.
 */
add_filter('wc_order_statuses', function ($statuses) {
    global $post;
    if (!$post || get_post_type($post) !== 'shop_order') return $statuses;
    if (current_user_can('administrator')) return $statuses;

    $ruleset = get_current_user_ruleset();
    if (!$ruleset || empty($ruleset['can_view_order'])) return [];

    $order = wc_get_order($post->ID);
    if (!$order) return [];

    $status = 'wc-' . $order->get_status(); // Estado actual con prefijo
    $allowed = [$status]; // Siempre incluir el actual

    // Transiciones permitidas
    if ($status === 'wc-on-hold' && !empty($ruleset['to_reserved'])) {
        $allowed[] = 'wc-reserved';
    }
    // Permitir pasar de "En espera" directamente a "Procesando" si el rol lo tiene permitido
    if ($status === 'wc-on-hold' && !empty($ruleset['to_processing'])) {
        $allowed[] = 'wc-processing';
    }
    if ($status === 'wc-reserved' && !empty($ruleset['to_processing'])) {
        $allowed[] = 'wc-processing';
    }
    if ($status === 'wc-processing' && !empty($ruleset['to_impressed'])) {
        $allowed[] = 'wc-impressed';
    }
    if ($status === 'wc-impressed' && !empty($ruleset['to_completed'])) {
        $allowed[] = 'wc-completed';
    }
    if (in_array($status, ['wc-on-hold', 'wc-reserved', 'wc-processing']) && !empty($ruleset['to_cancelled'])) {
        $allowed[] = 'wc-cancelled';
    }

    // Filtrar $statuses dejando solo los permitidos
    return array_filter($statuses, function ($label, $key) use ($allowed) {
        return in_array($key, $allowed);
    }, ARRAY_FILTER_USE_BOTH);
});

/**
 * BLOQUE 3 – Acciones rápidas (iconos en la lista de pedidos)
 * Muestra solo las acciones a las que el rol tiene permiso.
 */
add_filter('woocommerce_admin_order_actions', function($actions, $order) {
    $status = $order->get_status();
    $id     = $order->get_id();
    
    // Admin: siempre ve todo, pero añade "Completar" si está en impreso
    if (current_user_can('administrator')) {
        if ($status === 'reserved') {
            $actions['processing'] = [
                'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_processing&order_id=$id"), 'mark_order_as_processing'),
                'name'   => __('Procesando', 'woocommerce'),
                'action' => 'processing',
            ];
        }
        if ($status === 'impressed') {
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

    // Elimina todas las acciones por defecto
    unset($actions['reserved'], $actions['processing'], $actions['impressed'], $actions['complete'], $actions['invoice'] );

    // Añade solo las acciones permitidas
    if ($status === 'on-hold' && $ruleset['to_reserved']) {
        $actions['reserved'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_reserved&order_id=$id"), 'mark_order_as_reserved'),
            'name'   => __('Reservado', 'woocommerce'),
            'action' => 'reserved',
        ];
        $actions['processing'] = [
            'url'    => wp_nonce_url(admin_url('admin-ajax.php?action=mark_order_as_processing&order_id=' . $order->get_id()), 'mark_order_as_processing'),
            'name'   => __('Procesando', 'woocommerce'),
            'action' => 'processing'
        ];
    }

    if ($status === 'reserved' && $ruleset['to_processing']) {
        $actions['processing'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_processing&order_id=$id"), 'mark_order_as_processing'),
            'name'   => __('Procesando', 'woocommerce'),
            'action' => 'processing',
        ];
    }

    if ($status === 'processing' && $ruleset['to_impressed']) {
        $actions['impressed'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_impressed&order_id=$id"), 'mark_order_as_impressed'),
            'name'   => __('Impreso', 'woocommerce'),
            'action' => 'impressed',
        ];
    }

    if ($status === 'impressed' && $ruleset['to_completed']) {
        $actions['complete'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_completed&order_id=$id"), 'mark_order_as_completed'),
            'name'   => __('Completar', 'woocommerce'),
            'action' => 'complete',
        ];
    }

    if (in_array($status, ['on-hold', 'reserved', 'processing']) && $ruleset['to_cancelled']) {
        $actions['cancel'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_cancelled&order_id=$id"), 'mark_order_as_cancelled'),
            'name'   => __('Cancelar', 'woocommerce'),
            'action' => 'cancelled',
        ];
    }

    return $actions;
}, 100, 2);

/**
 * BLOQUE 4 – Acciones en la vista previa de pedidos
 * Igual que las acciones rápidas (BLOQUE 3), pero aplicadas al popup de previsualización del pedido.
 * Se muestran solo las acciones permitidas para el rol actual, o todas si es administrador.
 */
add_filter('woocommerce_admin_order_preview_actions', function($actions, $order) {
    $id     = $order->get_id();
    $status = $order->get_status();

    // Si es administrador → todas las acciones sin restricción
    if (current_user_can('administrator')) {
        if ($status === 'impressed') {
            $actions['complete'] = [
                'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_completed&order_id=$id"), 'mark_order_as_completed'),
                'name'   => __('Completar', 'woocommerce'),
                'action' => 'complete',
            ];
        }
        return $actions;
    }

    // Obtener reglas del usuario actual
    $ruleset = get_current_user_ruleset();
    if (!$ruleset) return [];

    // Limpiar acciones originales
    $actions = [];

    if ($status === 'on-hold' && $ruleset['to_reserved']) {
      $actions['reserved'] = [
          'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_reserved&order_id=$id"), 'mark_order_as_reserved'),
          'name'   => __('Reservado', 'woocommerce'),
          'action' => 'reserved',
      ];
      $actions['processing'] = [
          'url'    => wp_nonce_url(admin_url('admin-ajax.php?action=mark_order_as_processing&order_id=' . $order->get_id()), 'mark_order_as_processing'),
          'name'   => __('Procesando', 'woocommerce'),
          'action' => 'processing'
      ];
    }

    if ($status === 'reserved' && $ruleset['to_processing']) {
        $actions['processing'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_processing&order_id=$id"), 'mark_order_as_processing'),
            'name'   => __('Procesando', 'woocommerce'),
            'action' => 'processing',
        ];
    }

    if ($status === 'processing' && $ruleset['to_impressed']) {
        $actions['impressed'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_impressed&order_id=$id"), 'mark_order_as_impressed'),
            'name'   => __('Impreso', 'woocommerce'),
            'action' => 'impressed',
        ];
    }

    if ($status === 'impressed' && $ruleset['to_completed']) {
        $actions['complete'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_completed&order_id=$id"), 'mark_order_as_completed'),
            'name'   => __('Completar', 'woocommerce'),
            'action' => 'complete',
        ];
    }

    if (in_array($status, ['on-hold', 'reserved', 'processing']) && $ruleset['to_cancelled']) {
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
 * Si el estado actual del pedido está en filter_invoice_status para el rol,
 * se elimina la opción de generar factura desde la lista de pedidos.
 */
add_filter('wpo_wcpdf_listing_actions', function($listing_actions, $order) {
    $ruleset = get_current_user_ruleset();
    if (!$ruleset) return $listing_actions;

    // Quitar botón de factura si el estado está bloqueado para el rol
    if (in_array($order->get_status(), $ruleset['filter_invoice_status'])) {
        unset($listing_actions['invoice']);
    }

    return $listing_actions;
}, 10, 2);

/**
 * BLOQUE 6 – Ocultar pestañas de estado en la lista de pedidos
 * Muestra solo los estados que el rol puede ver según sus reglas.
 * También recalcula el contador de la pestaña "Todos" para que sume solo esos estados.
 */
add_filter('views_edit-shop_order', function($views) {
    $ruleset = get_current_user_ruleset();
    if (!$ruleset || current_user_can('administrator')) {
        return $views; // Admin ve todo
    }

    // Lista de estados sin prefijo 'wc-'
    $allowed_plain = [];
    if (!empty($ruleset['to_reserved']))   $allowed_plain[] = 'on-hold';
    if (!empty($ruleset['to_processing'])) $allowed_plain[] = 'reserved';
    if (!empty($ruleset['to_impressed']))  $allowed_plain[] = 'processing';
    if (!empty($ruleset['to_completed']))  $allowed_plain[] = 'impressed';
    if (!empty($ruleset['to_cancelled'])) {
        $allowed_plain[] = 'on-hold';
        $allowed_plain[] = 'reserved';
        $allowed_plain[] = 'processing';
    }
    if (!empty($ruleset['extra_view_status'])) {
        foreach ($ruleset['extra_view_status'] as $st) {
            $allowed_plain[] = $st;
        }
    }
    $allowed_plain = array_unique($allowed_plain);

    // Estados con prefijo 'wc-' para comparar con $views
    $allowed = array_map(fn($s) => 'wc-' . $s, $allowed_plain);

    // Recalcular contador de la pestaña “Todos”
    $counts = wp_count_posts('shop_order');
    if (isset($views['all'])) {
        $sum_all = 0;
        foreach ($allowed as $st) {
            $sum_all += intval($counts->{$st} ?? 0);
        }
        $views['all'] = preg_replace(
            '/\([^\)]*\)/',
            '(' . number_format_i18n($sum_all) . ')',
            $views['all']
        );
    }

    // Eliminar pestañas que no están en la lista de permitidas
    foreach ($views as $key => $html) {
        if ($key === 'all') continue;
        if (!in_array($key, $allowed, true)) {
            unset($views[$key]);
        }
    }

    return $views;
}, 20);

/**
 * BLOQUE 7 – Filtrar acciones por lote según reglas del rol
 * Elimina o deja visibles las opciones de cambio masivo dependiendo del estado actual filtrado.
 * También remueve opciones no relevantes como "Enviar a la papelera".
 */
add_filter('bulk_actions-edit-shop_order', function($bulk_actions) {
    $ruleset = get_current_user_ruleset();
    if (!$ruleset) return $bulk_actions;

    $current_status = $_GET['post_status'] ?? 'all';

    // En la vista "Todos" no se muestran acciones por lote
    if ($current_status === 'all') {
        return [];
    }

    // Acciones permitidas por estado
    $actions_map = [
        'wc-on-hold' => [
            'mark_reserved'   => $ruleset['to_reserved'] ?? false,
            'mark_processing' => $ruleset['to_processing'] ?? false,
            'mark_cancelled'  => $ruleset['to_cancelled'] ?? false,
        ],
        'wc-reserved' => [
            'mark_processing' => $ruleset['to_processing'] ?? false,
            'mark_cancelled'  => $ruleset['to_cancelled'] ?? false,
        ],
        'wc-processing' => [
            'mark_impressed'  => $ruleset['to_impressed'] ?? false,
            'mark_cancelled'  => $ruleset['to_cancelled'] ?? false,
        ],
        'wc-impressed' => [
            'mark_completed'  => $ruleset['to_completed'] ?? false,
        ],
        'all' => [ // Fallback
            'mark_reserved'   => $ruleset['to_reserved'] ?? false,
            'mark_processing' => $ruleset['to_processing'] ?? false,
            'mark_impressed'  => $ruleset['to_impressed'] ?? false,
            'mark_completed'  => $ruleset['to_completed'] ?? false,
            'mark_cancelled'  => $ruleset['to_cancelled'] ?? false,
        ],
    ];

    // Determinar acciones válidas para el estado actual
    $allowed_actions = $actions_map[$current_status] ?? $actions_map['all'];

    $filtered = [];

    // Eliminar acción de factura si el estado actual la tiene bloqueada
    if (
        isset($bulk_actions['invoice']) &&
        in_array(str_replace('wc-', '', $current_status), $ruleset['filter_invoice_status'] ?? [])
    ) {
        unset($bulk_actions['invoice']);
    }

    // Filtrar acciones por las reglas
    foreach ($bulk_actions as $key => $label) {
        $is_allowed = isset($allowed_actions[$key]) && $allowed_actions[$key];

        // Verificar si la acción está bloqueada explícitamente en config
        $is_blocked = in_array($key, $ruleset['filter_bulk_actions'] ?? []);

        if ($is_allowed && !$is_blocked) {
            $filtered[$key] = $label;
        }

        // Mantener acciones no controladas por nosotros (plugins externos)
        if (!isset($actions_map['all'][$key]) && !isset($actions_map[$current_status][$key])) {
            // Pero siempre respetar el filtro de config
            if (!$is_blocked) {
                $filtered[$key] = $label;
            }
        }
    }

    // Eliminar basura
    unset($filtered['trash'], $filtered['mark_on-hold']);

    return $filtered;
}, 20);

/**
 * BLOQUE 8 – Filtrado de pedidos en la consulta principal
 * Limita los pedidos que se muestran en función de los estados que el rol puede ver.
 * Aplica tanto a la vista "Todos" como a vistas de estado específico.
 */
add_action('pre_get_posts', function($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') !== 'shop_order') return;
    if (current_user_can('administrator')) return;

    $ruleset = get_current_user_ruleset();
    if (!$ruleset) return;

    // Construir lista de estados permitidos con prefijo 'wc-'
    $allowed = [];
    if (!empty($ruleset['to_reserved']))   $allowed[] = 'wc-on-hold';
    if (!empty($ruleset['to_processing'])) $allowed[] = 'wc-reserved';
    if (!empty($ruleset['to_impressed']))  $allowed[] = 'wc-processing';
    if (!empty($ruleset['to_completed']))  $allowed[] = 'wc-impressed';
    if (!empty($ruleset['to_cancelled'])) {
        $allowed[] = 'wc-on-hold';
        $allowed[] = 'wc-reserved';
        $allowed[] = 'wc-processing';
    }
    if (!empty($ruleset['extra_view_status'])) {
        foreach ($ruleset['extra_view_status'] as $st) {
            $allowed[] = 'wc-' . $st;
        }
    }
    $allowed = array_unique($allowed);

    // Detectar filtro activo en la URL
    $filter = isset($_GET['post_status']) ? sanitize_key($_GET['post_status']) : 'all';

    if ($filter === 'all' || !$filter) {
        // Vista “Todos” → solo pedidos permitidos
        $query->set('post_status', $allowed);
    } else {
        // Vista de estado específico
        if (in_array($filter, $allowed, true)) {
            $query->set('post_status', [$filter]);
        } else {
            // Estado no permitido → no mostrar resultados
            $query->set('post_status', ['do-not-match']);
        }
    }
}, 20);