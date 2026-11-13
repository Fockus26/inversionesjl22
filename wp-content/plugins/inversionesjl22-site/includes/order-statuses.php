<?php
/**
 * The shop's own order statuses, Reservado (wc-reserved) and Impreso (wc-impressed): their
 * registration, their place in the status list and tabs, editing a reserved order, and their
 * quick, bulk and preview actions.
 *
 * From WPCodeBox › Order › Custom Status (snippet 49). The roles module filters the actions added
 * here, so it loads after this one (order of the loader in the main file).
 *
 * Which statuses, their names, their order in the lists and which one keeps the order editable
 * come from the configuration (config.php), and so do the quick, bulk and preview actions: one
 * per status the flow lets an order move to.
 */

defined( 'ABSPATH' ) || exit;

add_action('init', 'createCustomStatus');
function createCustomStatus() {
    // The shop's own statuses, from the configuration (config.php)
    foreach (inversionesjl22_site_custom_statuses() as $slug => $status) {
        $count = $status['label'] . ' (%s)';
        register_post_status('wc-' . $slug, [
          'label'                     => $status['label'],
          'public'                    => true,
          'exclude_from_search'       => false,
          'show_in_admin_all_list'    => true,
          'show_in_admin_status_list' => true,
          // The array _n_noop() builds; called with a variable it would hide the text from the translation tools
          'label_count'               => ['singular' => $count, 'plural' => $count, 'context' => null, 'domain' => null],
        ]);
    }
}

add_filter( 'wc_order_is_editable', 'addProductsInReservedStatus', 10, 2 );
function addProductsInReservedStatus($is_editable, $order) {
    $statuses = inversionesjl22_site_config()['statuses'];
    if ( ! empty( $statuses[ $order->get_status() ]['editable'] ) ) {
        $is_editable = true;
    }

    return $is_editable;
}

// Puts the shop's statuses in the configuration's order inside $list (keyed by wc-<slug>), where
// the first of them was; the entries of other statuses (pending, refunded, trash…) stay where
// they are. $items are the shop's entries, by wc-<slug>: one missing from $items is left out.
function inversionesjl22_site_place_statuses($list, $items) {
    $managed = [];
    foreach (array_keys(inversionesjl22_site_config()['statuses']) as $slug) {
        $managed['wc-' . $slug] = true;
    }
    $sorted = [];
    $placed = false;
    foreach ($list as $key => $value) {
        if (!isset($managed[$key])) {
            $sorted[$key] = $value;
            continue;
        }
        if (!$placed) {
            $placed = true;
            foreach (array_keys($managed) as $own) {
                if (isset($items[$own])) {
                    $sorted[$own] = $items[$own];
                }
            }
        }
    }
    return $placed ? $sorted : $sorted + array_intersect_key($items, $managed);
}

add_filter('wc_order_statuses', 'sortCustomStatus');
function sortCustomStatus($statuses) {
    $items = [];
    foreach (inversionesjl22_site_config()['statuses'] as $slug => $status) {
        $key = 'wc-' . $slug;
        if (!empty($status['label'])) {
            $items[$key] = $status['label'];        // the shop's own, or one of WooCommerce's renamed
        } elseif (isset($statuses[$key])) {
            $items[$key] = $statuses[$key];
        }
    }
    return inversionesjl22_site_place_statuses($statuses, $items);
}

// The link that moves an order to a status (order-transitions.php), as the order list's quick
// actions and the order preview take it. $class is the button's CSS class: WooCommerce draws the
// icon of its own actions from it, and the configured icon hangs from it (config.php).
function inversionesjl22_site_status_action($order, $to, $class) {
    $ajax = inversionesjl22_site_status_ajax_action($to);
    return [
        'url'    => wp_nonce_url(admin_url('admin-ajax.php?action=' . $ajax . '&order_id=' . $order->get_id()), $ajax),
        'name'   => inversionesjl22_site_status_action_labels()[$to] ?? ucfirst($to),
        'action' => $class,
    ];
}

// Quick actions of the orders list: one per status the flow lets the order move to (config.php).
// The administrator gets these on top of WooCommerce's own; the roles module then rebuilds the
// list for the restricted roles (roles-actions.php).
add_filter('woocommerce_admin_order_actions', 'quickActionsCustomStatus', 10, 2);
function quickActionsCustomStatus($actions, $order) {
    if (!$order instanceof WC_Order) {
        return $actions;
    }
    $next = inversionesjl22_site_flow_destinations()[$order->get_status()] ?? [];
    foreach ($next as $to) {
        $class           = inversionesjl22_site_status_action_class($to);
        $actions[$class] = inversionesjl22_site_status_action($order, $to, $class);
    }
    return $actions;
}

// "Cambiar estado a …" in the bulk actions: for the administrator, every status the flow leads
// to; for the other roles, the ones the current tab's status leads to (the roles module then
// keeps the ones the role may use).
add_filter('bulk_actions-edit-shop_order', 'bulkActionsCustomStatus');
function bulkActionsCustomStatus($actions) {
    $flow = inversionesjl22_site_flow();
    if (current_user_can('administrator')) {
        $targets = array_keys($flow);
    } else {
        $status  = isset($_GET['post_status']) ? sanitize_key(wp_unslash($_GET['post_status'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only, to choose the list.
        $status  = 0 === strpos($status, 'wc-') ? substr($status, 3) : $status;
        $targets = inversionesjl22_site_flow_destinations()[$status] ?? [];
    }

    $lower = function_exists('mb_strtolower') ? 'mb_strtolower' : 'strtolower';
    foreach ($targets as $to) {
        /* translators: %s: status name, in lower case */
        $actions['mark_' . $to] = sprintf(__('Cambiar estado a %s', 'inversionesjl22-site'), $lower(inversionesjl22_site_status_label($to)));
    }
    return $actions;
}

// Reglas del rol también en este lote (roles-enforce.php); sin el módulo de roles, como antes
function inversionesjl22_site_bulk_allowed($order, $to) {
    return !function_exists('inversionesjl22_site_role_can_move')
        || inversionesjl22_site_role_can_move($order, $to, ['bulk_actions']);
}

// The bulk change itself, for the orders the flow lets move to that status. WooCommerce runs its
// own handler for mark_<status> first and moves every selected order the role may move
// (roles-enforce.php filters them); this one covers the rest of the cases and keeps the count
// in the URL.
add_filter('handle_bulk_actions-edit-shop_order', 'handleBulkActionsCustomStatus', 10, 3);
function handleBulkActionsCustomStatus($redirectTo, $action, $postIds) {
    if (0 !== strpos((string) $action, 'mark_')) {
        return $redirectTo;
    }
    $to      = substr($action, 5);
    $origins = inversionesjl22_site_flow()[$to] ?? null;
    if (null === $origins || !isset(inversionesjl22_site_config()['statuses'][$to])) {
        return $redirectTo;
    }

    $lower = function_exists('mb_strtolower') ? 'mb_strtolower' : 'strtolower';
    foreach ((array) $postIds as $postId) {
        $order = wc_get_order($postId);
        if ($order && in_array($order->get_status(), $origins, true) && inversionesjl22_site_bulk_allowed($order, $to)) {
            /* translators: %s: status name, in lower case */
            $order->update_status($to, sprintf(__('Marcado como %s por acción en lote.', 'inversionesjl22-site'), $lower(inversionesjl22_site_status_label($to))));
        }
    }
    return add_query_arg('bulk_' . $to, count((array) $postIds), $redirectTo);
}

// The order preview: a button per status the flow lets the order move to, unless WooCommerce's
// own "Cambiar estado" group already offers it (Procesando and Completado, for some statuses).
add_filter('woocommerce_admin_order_preview_actions', 'previewActionsCustomStatus', 10, 2);
function previewActionsCustomStatus($actions, $order) {
    if (!$order instanceof WC_Order) {
        return $actions;
    }
    $own  = $actions['status']['actions'] ?? [];
    $next = inversionesjl22_site_flow_destinations()[$order->get_status()] ?? [];
    foreach ($next as $to) {
        $class = inversionesjl22_site_status_action_class($to);
        if (isset($own[$class]) || isset($own[$to])) {
            continue;
        }
        $actions[$class] = inversionesjl22_site_status_action($order, $to, $class);
    }
    return $actions;
}

add_filter('views_edit-shop_order', 'viewsCustomStatus');
function viewsCustomStatus($views) {
    global $wp_query;
    $current = isset($_GET['post_status']) ? $_GET['post_status'] : '';
    $count = wp_count_posts('shop_order');

    // The shop's own statuses and the renamed ones get their tab here, when they have orders;
    // the rest keep WordPress' tab. All of them in the configuration's order.
    $tabs = [];
    foreach (inversionesjl22_site_config()['statuses'] as $slug => $status) {
        $key      = 'wc-' . $slug;
        $quantity = isset($count->{$key}) ? $count->{$key} : 0;
        if (empty($status['label'])) {
            if (isset($views[$key])) {
                $tabs[$key] = $views[$key];
            }
            continue;
        }
        if ($quantity > 0) {
            $tabs[$key] = sprintf(
                // Only the link: WordPress wraps each view in its own <li class="wc-…">
                '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                esc_url(admin_url('edit.php?post_status=' . $key . '&post_type=shop_order')),
                esc_attr($current === $key ? 'current' : ''),
                esc_html($status['label']),
                intval($quantity)
            );
        }
    }
    return inversionesjl22_site_place_statuses($views, $tabs);
}
