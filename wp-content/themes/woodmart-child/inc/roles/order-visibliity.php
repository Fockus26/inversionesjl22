<?php
/**
 * ============================
 * ORDER ROLES VISIBILITY LOGIC (v3)
 * ============================
 * Adaptado a la nueva estructura unificada:
 *  - Estados definidos directamente (on_hold => true/false)
 *  - Exclusiones dentro de exclude_status_actions[]
 *  - Flujo de estados en $global_flow
 */

// ===============================
// REGISTRO Y ACCESO A LAS REGLAS
// ===============================

$user_restrictions = [];

function limit_user_permissions($username, $ruleset) {
    global $user_restrictions;
    $user_restrictions[$username] = $ruleset;
}

function get_current_user_ruleset() {
    global $user_restrictions;
    if (current_user_can('administrator')) return null;

    $user = wp_get_current_user();
    foreach ($user->roles as $role) {
        if (isset($user_restrictions[$role])) return $user_restrictions[$role];
    }
    return null;
}

/**
 * Verifica si un estado está visible o accesible según las reglas
 */
function is_status_visible($status, $ruleset) {
    if (empty($ruleset[$status])) return false; // regla false ⇒ no visible

    $excludes = $ruleset['exclude_status_actions'] ?? [];

    // Si el estado está en TODAS las exclusiones ⇒ no visible
    $contexts = ['quick_actions','bulk_actions','order_preview_actions','order_view','order_view_actions','qr_actions'];
    $in_all = true;
    foreach ($contexts as $ctx) {
        if (empty($excludes[$ctx]) || !in_array($status, $excludes[$ctx], true)) {
            $in_all = false;
            break;
        }
    }
    return !$in_all;
}

// Carga del archivo de configuración
require_once __DIR__ . '/config.php';


// ======================================================
// 🧱 BLOQUE 1 – Control de acceso a la página del pedido
// ======================================================

add_action('admin_init', function () {
    global $pagenow;
    if ($pagenow !== 'post.php') return;

    $order_id = $_GET['post'] ?? null;
    if (!$order_id || get_post_type($order_id) !== 'shop_order') return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    $ruleset = get_current_user_ruleset();
    if (!$ruleset) return;

    $status = $order->get_status(); // ej. 'processing'

    // Si el estado no está permitido o está excluido del contexto order_view ⇒ bloquear acceso
    $exclude = $ruleset['exclude_status_actions']['order_view'] ?? [];
    if (!$ruleset[$status] || in_array($status, $exclude, true)) {
        wp_safe_redirect(admin_url('edit.php?post_type=shop_order'));
        exit;
    }
});


// ======================================================
// 🧱 BLOQUE 2 – Estados disponibles en el selector interno
// ======================================================

add_filter('wc_order_statuses', function ($statuses) {
    global $post;
    if (!$post || get_post_type($post) !== 'shop_order') return $statuses;

    $ruleset = get_current_user_ruleset();
    if (!$ruleset) return $statuses;

    $order = wc_get_order($post->ID);
    if (!$order) return $statuses;

    $current = $order->get_status();
    $allowed = ['wc-' . $current];

    $flow = $ruleset['flow'] ?? [];

    foreach ($flow as $to => $from) {
        // Verificar si este flujo aplica desde el estado actual
        if ((is_array($from) && in_array($current, $from, true)) || $from === $current) {
            // Si puede ver el destino y no está excluido de order_view_actions
            $excluded = $ruleset['exclude_status_actions']['order_view_actions'] ?? [];
            if (is_status_visible($to, $ruleset) && !in_array($to, $excluded, true)) {
                $allowed[] = 'wc-' . $to;
            }
        }
    }

    // Filtramos solo los estados permitidos
    $filtered = array_filter($statuses, fn($label, $key) => in_array($key, $allowed, true), ARRAY_FILTER_USE_BOTH);

    return $filtered;
});

/**
 * ============================================================
 * 🧱 BLOQUE 3 – Acciones rápidas (iconos en la lista de pedidos)
 * ============================================================
 */
add_filter('woocommerce_admin_order_actions', function ($actions, $order) {
    global $global_flow;

    $status = $order->get_status(); // ej. 'processing'
    $id     = $order->get_id();

    $ruleset = get_current_user_ruleset();
    if ($ruleset === null) return $actions; // admin o sin restricciones

    unset(
        $actions['reserved'],
        $actions['processing'],
        $actions['impressed'],
        $actions['complete'],
        $actions['completed'],
        $actions['cancel'],
        $actions['invoice']
    );

    $excluded_quick = $ruleset['exclude_status_actions']['quick_actions'] ?? [];
    $flow           = $ruleset['flow'] ?? $global_flow;

    $labels = [
        'reserved'   => __('Reservar', 'woocommerce'),
        'processing' => __('Procesar', 'woocommerce'),
        'impressed'  => __('Impreso', 'woocommerce'),
        'completed'  => __('Completar', 'woocommerce'),
        'cancelled'  => __('Cancelar', 'woocommerce'),
    ];

    $action_key_map = [
        'completed' => 'complete',
        'cancelled' => 'cancel',
    ];

    // ===== Determinar siguientes estados válidos según el flujo =====
    $next_states = [];
    foreach ($flow as $to => $from) {
        if ((is_array($from) && in_array($status, $from, true)) || ($from === $status)) {
            $next_states[] = $to;
        }
    }

    // ===== Construir acciones rápidas válidas =====
    foreach ($next_states as $next) {
        if (in_array($next, $excluded_quick, true)) continue;

        $action_key = $action_key_map[$next] ?? $next;
        $ajax_action = "mark_order_as_{$next}";

        $actions[$action_key] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action={$ajax_action}&order_id={$id}"), $ajax_action),
            'name'   => $labels[$next] ?? ucfirst($next),
            'action' => $action_key, // esta clase define el icono
        ];
    }

    // ===== Acción especial: Cancelar =====
    if (
        in_array($status, ['on-hold', 'reserved', 'processing'], true)
        && !in_array('cancelled', $excluded_quick, true)
    ) {
        $actions['cancel'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_cancelled&order_id={$id}"), 'mark_order_as_cancelled'),
            'name'   => $labels['cancelled'],
            'action' => 'cancel', // clave para mostrar el icono
        ];
    }

    return $actions;
}, 100, 2);

/**
 * ============================================================
 * 🧱 BLOQUE 4 – Control de visibilidad del botón de factura PDF
 * ============================================================
 */
add_filter('wpo_wcpdf_listing_actions', function ($listing_actions, $order) {
    $ruleset = get_current_user_ruleset();
    if (!$ruleset) return $listing_actions;

    $status = $order->get_status();
    $excluded_qr = $ruleset['exclude_status_actions']['qr_actions'] ?? [];

    // Si el estado está excluido o desactivado → ocultar botón de factura
    if (in_array($status, $excluded_qr, true) || empty($ruleset[$status])) {
        unset($listing_actions['invoice']);
    }

    return $listing_actions;
}, 10, 2);

/**
 * ============================================================
 * 🧱 BLOQUE 5 – Acciones en la vista previa de pedidos
 * ============================================================
 */
add_filter('woocommerce_admin_order_preview_actions', function ($actions, $order) {
    global $global_flow;

    $status = $order->get_status();
    $id     = $order->get_id();

    // Obtener el ruleset actual (si es admin, devuelve null)
    $ruleset = get_current_user_ruleset();
    if ($ruleset === null) return $actions; // Admin: deja las acciones estándar de Woo

    // Limpiamos cualquier acción previa
    $actions = [];

    // Exclusiones específicas para order preview
    $excluded_preview = $ruleset['exclude_status_actions']['order_preview_actions'] ?? [];

    // Obtener flujo (por rol o global)
    $flow = $ruleset['flow'] ?? $global_flow;

    // === Labels de acciones ===
    $labels = [
        'reserved'   => __('Reservar', 'woocommerce'),
        'processing' => __('Procesar', 'woocommerce'),
        'impressed'  => __('Impreso', 'woocommerce'),
        'completed'  => __('Completar', 'woocommerce'),
        'cancelled'  => __('Cancelar', 'woocommerce'),
    ];

    // Mapeo de claves para mantener estilos en Woo
    $action_key_map = [
        'completed' => 'complete',
        'cancelled' => 'cancel',
    ];

    // ===== Determinar próximos estados válidos según flujo =====
    $next_states = [];
    foreach ($flow as $to => $from) {
        if ((is_array($from) && in_array($status, $from, true)) || ($from === $status)) {
            $next_states[] = $to;
        }
    }

    // ===== Construir acciones de vista previa =====
    foreach ($next_states as $next) {
        if (in_array($next, $excluded_preview, true)) continue;

        $action_key  = $action_key_map[$next] ?? $next;
        $ajax_action = "mark_order_as_{$next}";

        $actions[$action_key] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action={$ajax_action}&order_id={$id}"), $ajax_action),
            'name'   => $labels[$next] ?? ucfirst($next),
            'action' => $action_key,
        ];
    }

    // ===== Acción especial: Cancelar =====
    if (
        in_array($status, ['on-hold', 'reserved', 'processing'], true)
        && !in_array('cancelled', $excluded_preview, true)
    ) {
        $actions['cancel'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=mark_order_as_cancelled&order_id={$id}"), 'mark_order_as_cancelled'),
            'name'   => $labels['cancelled'],
            'action' => 'cancel', // clave estándar para icono
        ];
    }

    return $actions;
}, 100, 2);

/**
 * ============================================================
 * 🧱 BLOQUE 6 – Ocultar pestañas de estado en la lista de pedidos
 * ============================================================
 */
add_filter('views_edit-shop_order', function ($views) {
    global $global_flow;

    $ruleset = get_current_user_ruleset();
    if ($ruleset === null) return $views; // admin: no filtrar pestañas

    // === Obtener flujo activo (por rol o global)
    $flow = $ruleset['flow'] ?? $global_flow;

    // === Determinar qué estados están activos o visibles
    $allowed_plain = [];

    // Recorremos los estados destino del flujo
    foreach ($flow as $to => $from) {
        // Si no está permitido directamente en el ruleset, omitir
        if (isset($ruleset[$to]) && $ruleset[$to] === false) continue;

        // Comprobamos si el estado es visible según exclusiones globales
        if (is_status_visible($to, $ruleset)) {
            $allowed_plain[] = $to;
        }

        // También incluimos los estados de origen si aplican (por ejemplo, on-hold)
        if (is_array($from)) {
            foreach ($from as $f) {
                if (is_status_visible($f, $ruleset)) {
                    $allowed_plain[] = $f;
                }
            }
        } elseif (is_status_visible($from, $ruleset)) {
            $allowed_plain[] = $from;
        }
    }

    $allowed_plain = array_unique($allowed_plain);
    $allowed_wc    = array_map(fn($s) => 'wc-' . $s, $allowed_plain);

    // === Recalcular el contador de "Todos"
    $counts = wp_count_posts('shop_order');
    if (isset($views['all'])) {
        $sum_all = 0;
        foreach ($allowed_plain as $st) {
            $wc_key   = 'wc-' . $st;
            $plain_key = $st;
            $value = 0;

            if (isset($counts->{$wc_key})) {
                $value = intval($counts->{$wc_key});
            } elseif (isset($counts->{$plain_key})) {
                $value = intval($counts->{$plain_key});
            }
            $sum_all += $value;
        }

        $views['all'] = preg_replace(
            '/\([^\)]*\)/',
            '(' . number_format_i18n($sum_all) . ')',
            $views['all']
        );
    }

    // === Filtrar pestañas visibles
    foreach ($views as $key => $html) {
        if ($key === 'all') continue;
        if (!in_array($key, $allowed_wc, true)) {
            unset($views[$key]);
        }
    }

    return $views;
}, 20);

/**
 * ============================================================
 * 🧱 BLOQUE 7 – Filtrado de pedidos en la consulta principal
 * ============================================================
 */
add_action('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') !== 'shop_order') return;

    $ruleset = get_current_user_ruleset();
    if ($ruleset === null) return; // admin o sin restricciones

    global $global_flow;
    $flow = $ruleset['flow'] ?? $global_flow;

    // === 1️⃣ Construir lista de estados visibles según flow + exclusiones
    $allowed = [];

    foreach ($flow as $to => $from) {
        // Solo incluir si está visible (usa la función global)
        if (is_status_visible($to, $ruleset)) {
            $allowed[] = 'wc-' . $to;
        }

        // También incluir los estados de origen que sean visibles
        if (is_array($from)) {
            foreach ($from as $f) {
                if (is_status_visible($f, $ruleset)) {
                    $allowed[] = 'wc-' . $f;
                }
            }
        } elseif (is_status_visible($from, $ruleset)) {
            $allowed[] = 'wc-' . $from;
        }
    }

    $allowed = array_unique($allowed);

    // === 2️⃣ Detectar el filtro actual de estado en la URL
    $filter = isset($_GET['post_status']) ? sanitize_key($_GET['post_status']) : 'all';

    // === 3️ Aplicar los filtros de visibilidad
    if ($filter === 'all' || !$filter) {
        // Vista “Todos”: mostrar solo los estados permitidos
        $query->set('post_status', $allowed);
    } else {
        // Vista de estado específico
        if (in_array($filter, $allowed, true)) {
            $query->set('post_status', [$filter]);
        } else {
            // Estado no permitido → sin resultados
            $query->set('post_status', ['do-not-match']);
        }
    }
}, 20);

/**
 * ============================================================
 * 🧱 BLOQUE 8 – Filtrado de acciones por lote según el rol
 * ============================================================
 */
add_filter('bulk_actions-edit-shop_order', function ($bulk_actions) {
    global $global_flow;

    $ruleset = get_current_user_ruleset();
    if ($ruleset === null) return $bulk_actions; // admin o sin restricciones

    $current_status = $_GET['post_status'] ?? 'all';
    $flow           = $ruleset['flow'] ?? $global_flow;

    // === Exclusiones desde la nueva estructura
    $excluded_bulk = $ruleset['exclude_status_actions']['bulk_actions'] ?? [];
    $excluded_qr   = $ruleset['exclude_status_actions']['qr_actions'] ?? [];

    // === 1️⃣ Vista "Todos": no mostrar acciones masivas
    if ($current_status === 'all') {
        return [];
    }

    // === 2️⃣ Normalizar estado actual (quitar prefijo wc-)
    $status = str_replace('wc-', '', $current_status);

    // === 3️⃣ Determinar siguientes estados válidos según el flujo
    $next_states = [];
    foreach ($flow as $to => $from) {
        if ((is_array($from) && in_array($status, $from, true)) || ($from === $status)) {
            $next_states[] = $to;
        }
    }

    // === 4️⃣ Construir mapa dinámico de acciones válidas (evitar mismo estado)
    $allowed_actions = [];
    foreach ($next_states as $to) {
        // evitar ofrecer transición al mismo estado
        if ($to === $status) continue;

        // Si el estado es visible y no está excluido en bulk → permitir acción
        if (is_status_visible($to, $ruleset) && !in_array($to, $excluded_bulk, true)) {
            $allowed_actions['mark_' . $to] = true;
        }
    }

    // === 5️⃣ Acción especial: Cancelar (válida en ciertos estados)
    if (
        in_array($status, ['on-hold', 'reserved', 'processing'], true)
        && !in_array('cancelled', $excluded_bulk, true)
    ) {
        $allowed_actions['mark_cancelled'] = true;
    }

    // === 6️⃣ Filtrar las acciones de WooCommerce según permisos
    $filtered = [];
    foreach ($bulk_actions as $key => $label) {
        // Si la acción está explícitamente excluida por estado → saltarla
        $is_excluded = false;
        foreach ($excluded_bulk as $excluded_state) {
            if (strpos($key, $excluded_state) !== false) {
                $is_excluded = true;
                break;
            }
        }
        if ($is_excluded) {
            continue;
        }

        // Si es una acción de tipo "mark_*" (cambio de estado), permitir solo si está en allowed_actions
        if (strpos($key, 'mark_') === 0) {
            if (isset($allowed_actions[$key]) && $allowed_actions[$key]) {
                $filtered[$key] = $label;
            }
            // si no está permitida, la descartamos
            continue;
        }

        // Acciones externas / no mark_*: mantener salvo que estén excluidas
        $filtered[$key] = $label;
    }

    // === 7️⃣ Quitar acción de factura si el estado actual está bloqueado por qr_actions
    if (isset($filtered['invoice']) && in_array($status, $excluded_qr, true)) {
        unset($filtered['invoice']);
    }

    // === 8️⃣ Limpieza final
    unset($filtered['trash'], $filtered['mark_on-hold']);

    return $filtered;
}, 20);

/**
 * ============================================================
 * 🧱 BLOQUE 9 – Remover acciones en lote forzadas por plugins
 * ============================================================
 */
add_action('admin_footer', function () {
    // Solo en lista de pedidos
    if (get_current_screen()->id !== 'edit-shop_order') {
        return;
    }

    // Obtener reglas del usuario actual
    $ruleset = get_current_user_ruleset();
    if (!$ruleset) {
        return;
    }

    $excluded_bulk = $ruleset['exclude_status_actions']['bulk_actions'] ?? [];

    // Si en el config está bloqueado wuoc_combine → inyectamos el JS
    if (in_array('wuoc_combine', $excluded_bulk)) : ?>
        <script>
        jQuery(document).ready(function($) {
            /**
             * Elimina la acción "wuoc_combine" de los select de acciones en lote
             * - Se ejecuta al cargar
             * - Se repite cada 500ms porque el plugin la reinyecta con JS
             */
            function removeWuocAction(){
                $('select#bulk-action-selector-top option[value="wuoc_combine"]').remove();
                $('select#bulk-action-selector-bottom option[value="wuoc_combine"]').remove();
            }

            removeWuocAction();               // primera pasada
            setInterval(removeWuocAction,500); // reintentos por AJAX
        });
        </script>
    <?php endif;
});
