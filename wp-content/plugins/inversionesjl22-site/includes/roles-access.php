<?php
/**
 * Roles: who can open an order and which orders the list shows, per role.
 *
 * Moved as is from WPCodeBox › Roles › Init Globally (snippet 52). Rules: roles-config.php.
 * Changes since: a status filter the role may not see returns no orders (it returned all of
 * them, see below); a status with no rule (trash, pending…) is blocked without a PHP warning.
 */

defined( 'ABSPATH' ) || exit;

// Control de acceso a la página del pedido
add_action('admin_init', 'authorizeOrderView');
function authorizeOrderView() {
    global $pagenow;
    if ($pagenow !== 'post.php') return;

    $order_id = $_GET['post'] ?? null;
    if (!$order_id || get_post_type($order_id) !== 'shop_order') return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    $ruleset = getUserPermissions();
    if (!$ruleset) return;

    $status = $order->get_status(); // ej. 'processing'

    // Si el estado no está permitido o está excluido del contexto order_view ⇒ bloquear acceso
    $exclude = $ruleset['exclude_status_actions']['order_view'] ?? [];
    if (empty($ruleset[$status]) || in_array($status, $exclude, true)) {
        wp_safe_redirect(admin_url('edit.php?post_type=shop_order'));
        exit;
    }
}

// Filtrado de pedidos en la consulta principal
add_action('pre_get_posts', 'filterSearchOrderStatuses');
function filterSearchOrderStatuses($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') !== 'shop_order') return;

    $ruleset = getUserPermissions();
    if ($ruleset === null) return; // admin o sin restricciones

    global $global_flow;
    $flow = $ruleset['flow'] ?? $global_flow;

    // === 1️⃣ Construir lista de estados visibles según flow + exclusiones
    $allowed = [];

    foreach ($flow as $to => $from) {
        // Solo incluir si está visible (usa la función global)
        if (isStatusAllowed($to, $ruleset)) {
            $allowed[] = 'wc-' . $to;
        }

        // También incluir los estados de origen que sean visibles
        if (is_array($from)) {
            foreach ($from as $f) {
                if (isStatusAllowed($f, $ruleset)) {
                    $allowed[] = 'wc-' . $f;
                }
            }
        } elseif (isStatusAllowed($from, $ruleset)) {
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
            // Estado no permitido → sin resultados. Antes: post_status ['do-not-match'], un
            // estado sin registrar que WP_Query ignora, así que la lista salía sin filtro de
            // estado (storage_manager veía pedidos en espera y de la papelera tecleando
            // ?post_status=…, y seo_manager podía borrar definitivamente desde la papelera).
            $query->set('post_status', $allowed);
            $query->set('post__in', [0]);
        }
    }
}
