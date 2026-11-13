<?php
/**
 * Roles: the status actions each role gets in wp-admin (selector, quick actions, preview, bulk,
 * status tabs, PDF button).
 *
 * Moved as is from WPCodeBox › Roles › Init (snippet 54), which only ran in wp-admin
 * (WPCodeBox condition "not frontend"): same here. Rules: roles-config.php, which reads them
 * from the configuration (config.php), as the button labels and the AJAX actions here do.
 * Changes since: the order preview filter no longer depends on the global $post (empty in its
 * AJAX request) and skips statuses the role has off, like the quick actions do.
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_admin() ) {
	return;
}

// Acción AJAX (Status Transition) que mueve el pedido a cada estado
function getStatusAjaxAction($status) {
    return inversionesjl22_site_status_ajax_action((string) $status);
}

function isStatusAllowed($status, $ruleset) {
    if (empty($ruleset[$status])) return false;

    $excludes = $ruleset['exclude_status_actions'] ?? [];

    $actions = ['quick_actions','bulk_actions','order_preview_actions','order_view','order_view_actions','qr_actions'];
    $isEachStatusRestricted = true;
    foreach ($actions as $action) {
        if (empty($excludes[$action]) || !in_array($status, $excludes[$action], true)) {
            $isEachStatusRestricted = false;
            break;
        }
    }
    return !$isEachStatusRestricted;
}

function isStatusAllowedForAction($status, $actionType, $ruleset) {
    // 1. regla general: el rol tiene que tener el estado encendido. Uno sin regla (un estado
    //    creado en Estados y flujo al que aún no se le dio permiso) no es suyo: antes solo se
    //    rechazaba el que estaba en false, y el nuevo pasaba como permitido.
    if (empty($ruleset[$status])) {
        return false;
    }

    // 2. regla específica
    $excluded = $ruleset['exclude_status_actions'][$actionType] ?? [];
    if (in_array($status, $excluded, true)) {
        return false;
    }

    return true;
}

// Estados disponibles en el selector interno
add_filter('wc_order_statuses', 'filterOrderViewActionsStatuses');
function filterOrderViewActionsStatuses($statuses) {
    global $post;
    if (get_post_type($post) !== 'shop_order') return $statuses;

    $ruleset = getUserPermissions();
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
            if (isStatusAllowedForAction($to, 'order_view_actions', $ruleset)) {
                $allowed[] = 'wc-' . $to;
            }
        }
    }

    // Filtramos solo los estados permitidos
    $filtered = array_filter($statuses, fn($label, $key) => in_array($key, $allowed, true), ARRAY_FILTER_USE_BOTH);

    return $filtered;
}

// Acciones rápidas (iconos en la lista de pedidos)
add_filter('woocommerce_admin_order_actions', 'filterQuickActionsOrderStatuses', 10, 2);
function filterQuickActionsOrderStatuses($actions, $order) {
    if (!$order instanceof WC_Order) {
        return $actions;
    }
    global $global_flow;

    $status = $order->get_status(); // ej. 'processing'
    $id     = $order->get_id();

    $user = wp_get_current_user();

    $ruleset = getUserPermissions();
    if ($ruleset === null) return $actions; // admin o sin restricciones
    // Every status action that came this far (WooCommerce's and order-statuses.php's, which adds
    // one per destination of the flow, Cancelar included) goes: the role's own are built below.
    foreach (array_keys(inversionesjl22_site_config()['statuses']) as $slug) {
        unset($actions[$slug], $actions[inversionesjl22_site_status_action_class($slug)]);
    }
    unset($actions['invoice']);

    $flow = $ruleset['flow'] ?? $global_flow;

    $labels = inversionesjl22_site_status_action_labels();

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


    $allowed_actions = [];
    $blocked_actions = [];

    // ===== Construir acciones rápidas válidas =====
    foreach ($next_states as $next) {
        $allowed = isStatusAllowedForAction($next, 'quick_actions', $ruleset);

        if (!$allowed) {
            $blocked_actions[$next] = 'blocked_by_rules';
            continue;
        }

        $action_key  = $action_key_map[$next] ?? $next;
        $ajax_action = getStatusAjaxAction($next);

        $actions[$action_key] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action={$ajax_action}&order_id={$id}"), $ajax_action),
            'name'   => $labels[$next] ?? ucfirst($next),
            'action' => $action_key,
        ];

        $allowed_actions[] = $next;
    }

    // ===== Acción especial: Cancelar =====
    if (
        in_array($status, ['on-hold', 'reserved', 'processing'], true)
        && isStatusAllowedForAction('cancelled', 'quick_actions', $ruleset)
    ) {
        $actions['cancel'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=orderToCancelled&order_id={$id}"), 'orderToCancelled'),
            'name'   => $labels['cancelled'],
            'action' => 'cancel', // clave para mostrar el icono
        ];
    }

    return $actions;
}

// Control de visibilidad del botón de factura PDF
add_filter('wpo_wcpdf_listing_actions', 'filterPDFActionsOrderStatuses', 10, 2);
function filterPDFActionsOrderStatuses($listing_actions, $order) {
    global $post;
    if (get_post_type($post) !== 'shop_order') return $listing_actions;

    $ruleset = getUserPermissions();
    if (!$ruleset) return $listing_actions;

    $status = $order->get_status();
    $excluded_qr = $ruleset['exclude_status_actions']['qr_actions'] ?? [];

    // Si el estado está excluido o desactivado → ocultar botón de factura
    if (in_array($status, $excluded_qr, true) || empty($ruleset[$status])) {
        unset($listing_actions['invoice']);
    }

    return $listing_actions;
}

// Acciones en la vista previa de pedidos
add_filter('woocommerce_admin_order_preview_actions', 'filterPreviewActionsOrderStatuses', 10, 2);
function filterPreviewActionsOrderStatuses($actions, $order) {
    // The preview is loaded by AJAX (woocommerce_get_order_details), where there is no global
    // $post: checking it, as the snippet did, returned WooCommerce's own actions to every role
    // (storage_manager could mark a processing order Completed). The order comes as an argument.
    if (!$order instanceof WC_Order) return $actions;
    global $global_flow;

    $status = $order->get_status();
    $id     = $order->get_id();

    // Obtener el ruleset actual (si es admin, devuelve null)
    $ruleset = getUserPermissions();
    if ($ruleset === null) return $actions; // Admin: deja las acciones estándar de Woo

    // Limpiamos cualquier acción previa
    $actions = [];

    // Obtener flujo (por rol o global)
    $flow = $ruleset['flow'] ?? $global_flow;

    // === Labels de acciones ===
    $labels = inversionesjl22_site_status_action_labels();

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
        // Same check as the quick actions: also a status the role has off ('cancelled' => false).
        if (!isStatusAllowedForAction($next, 'order_preview_actions', $ruleset)) continue;

        $action_key  = $action_key_map[$next] ?? $next;
        $ajax_action = getStatusAjaxAction($next);

        $actions[$action_key] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action={$ajax_action}&order_id={$id}"), $ajax_action),
            'name'   => $labels[$next] ?? ucfirst($next),
            'action' => $action_key,
        ];
    }

    // ===== Acción especial: Cancelar =====
    if (
        in_array($status, ['on-hold', 'reserved', 'processing'], true)
        && isStatusAllowedForAction('cancelled', 'order_preview_actions', $ruleset)
    ) {
        $actions['cancel'] = [
            'url'    => wp_nonce_url(admin_url("admin-ajax.php?action=orderToCancelled&order_id={$id}"), 'orderToCancelled'),
            'name'   => $labels['cancelled'],
            'action' => 'cancel', // clave estándar para icono
        ];
    }

    return $actions;
}

// Ocultar pestañas de estado en la lista de pedidos
add_filter('views_edit-shop_order', 'filterTabOrderStatuses');
function filterTabOrderStatuses($views) {
    global $global_flow;

    $ruleset = getUserPermissions();
    if ($ruleset === null) return $views; // admin: no filtrar pestañas

    // === Obtener flujo activo (por rol o global)
    $flow = $ruleset['flow'] ?? $global_flow;

    // === Determinar qué estados están activos o visibles
    $allowed_plain = [];

    // Recorremos los estados destino del flujo
    foreach ($flow as $to => $from) {

        // destino
        if (!isset($ruleset[$to]) || $ruleset[$to] !== false) {
            $allowed_plain[] = $to;
        }

        // orígenes
        $origins = is_array($from) ? $from : [$from];

        foreach ($origins as $f) {
            if (!isset($ruleset[$f]) || $ruleset[$f] !== false) {
                $allowed_plain[] = $f;
            }
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
}

// Filtrado de acciones por lote según el rol
add_filter('bulk_actions-edit-shop_order', 'filterBulkActionsOderStatuses', 20);
function filterBulkActionsOderStatuses($bulk_actions) {
    global $global_flow;

    $screen = get_current_screen();
    if ($screen->post_type !== 'shop_order') {
        return $bulk_actions;
    }

    $ruleset = getUserPermissions();
    if ($ruleset === null) {
        return $bulk_actions;
    }

    $current_status = $_GET['post_status'] ?? 'all';

    // Vista "Todos" → nada
    if ($current_status === 'all') {
        return [];
    }

    $status = str_replace('wc-', '', $current_status);
    $flow   = $ruleset['flow'] ?? $global_flow;

    // ===== calcular next states
    $next_states = [];
    foreach ($flow as $to => $from) {
        if ((is_array($from) && in_array($status, $from, true)) || ($from === $status)) {
            $next_states[] = $to;
        }
    }

    $allowed_actions = [];
    $blocked_actions = [];

    // ===== evaluar permisos (MISMA lógica que quick)
    foreach ($next_states as $to) {

        if ($to === $status) continue;

        $allowed = isStatusAllowedForAction($to, 'bulk_actions', $ruleset);

        if (!$allowed) {
            $blocked_actions[] = $to;
            continue;
        }

        $allowed_actions['mark_' . $to] = true;
    }

    // ===== filtrar acciones
    $filtered = [];

    foreach ($bulk_actions as $key => $label) {

        if (strpos($key, 'mark_') === 0) {
            if (isset($allowed_actions[$key])) {
                $filtered[$key] = $label;
            }
            continue;
        }

        $filtered[$key] = $label;
    }

    unset($filtered['trash']);
    if (!isset($allowed_actions['mark_on-hold'])) {
        unset($filtered['mark_on-hold']);
    }

    return $filtered;
}

// Remover acciones en lote forzadas por plugins
add_action('admin_footer', 'filterBulkActionsPlugins');
function filterBulkActionsPlugins() {
    // Solo en lista de pedidos
    if (get_current_screen()->id !== 'edit-shop_order') {
        return;
    }

    // Obtener reglas del usuario actual
    $ruleset = getUserPermissions();
    if (!$ruleset) {
        return;
    }

    $excluded_bulk = $ruleset['exclude_status_actions']['bulk_actions'] ?? [];

    // Si en el config está bloqueado wuoc_combine → inyectamos el JS
    if (in_array('wuoc_combine', $excluded_bulk)) : ?>
        <script>
        (function () {
            /**
             * Elimina la acción "wuoc_combine" de los select de acciones en lote. El plugin la
             * reinyecta con JS: antes se borraba cada 500 ms para siempre; ahora solo cuando el
             * formulario cambia (MutationObserver).
             */
            function removeWuocAction() {
                document.querySelectorAll('#bulk-action-selector-top option[value="wuoc_combine"], #bulk-action-selector-bottom option[value="wuoc_combine"]')
                    .forEach(function (option) { option.remove(); });
            }

            function watch() {
                removeWuocAction();
                // El formulario entero: el plugin puede añadir la opción o rehacer el select.
                new MutationObserver(removeWuocAction).observe(
                    document.getElementById('posts-filter') || document.body,
                    { childList: true, subtree: true }
                );
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', watch);
            } else {
                watch();
            }
        })();
        </script>
    <?php endif;
}
