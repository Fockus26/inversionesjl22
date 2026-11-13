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
 * come from the configuration (config.php). The administrator's quick, bulk and preview actions
 * below are still written status by status.
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

add_filter('woocommerce_admin_order_actions', 'quickActionsCustomStatus', 10, 2);
function quickActionsCustomStatus($actions, $order) {
    $order_id = $order->get_id();
    $status = $order->get_status();

    if ($status === 'on-hold') {
        $actions['reserved'] = [
            'url'       => wp_nonce_url(admin_url('admin-ajax.php?action=orderToReserved&order_id=' . $order_id), 'orderToReserved'),
            'name'      => __('Reservado', 'woocommerce'),
            'action'    => 'reserved'
        ];
        $actions['processing'] = [
            'url'    => wp_nonce_url(admin_url('admin-ajax.php?action=orderToProcessing&order_id=' . $order_id), 'orderToProcessing'),
            'name'   => __('Procesando', 'woocommerce'),
            'action' => 'processing'
        ];
    }
    if ($status === 'reserved') {
        $actions['processing'] = [
            'url'    => wp_nonce_url(admin_url('admin-ajax.php?action=orderToProcessing&order_id=' . $order_id), 'orderToProcessing'),
            'name'   => __('Procesando', 'woocommerce'),
            'action' => 'processing'
        ];
    }
    if ($status === 'processing') {
        $actions['impressed'] = [
            'url'       => wp_nonce_url(admin_url('admin-ajax.php?action=orderToImpressed&order_id=' . $order_id), 'orderToImpressed'),
            'name'      => __('Impreso', 'woocommerce'),
            'action'    => 'impressed'
        ];
    }
    if ($status === 'impressed') {
        $actions['complete'] = [ 
            'url'    => wp_nonce_url(admin_url('admin-ajax.php?action=orderToCompleted&order_id=' . $order_id), 'orderToCompleted'),
            'name'   => __('Completado', 'woocommerce'),
            'action' => 'complete' 
        ];
    }
    return $actions;
}

add_filter('bulk_actions-edit-shop_order', 'bulkActionsCustomStatus');
function bulkActionsCustomStatus($actions) {
    $status = $_GET['post_status'] ?? '';  
  
    if (current_user_can('administrator')) {
        $actions['mark_reserved']   = __('Cambiar estado a reservado', 'woocommerce');
        $actions['mark_processing'] = __('Cambiar estado a procesando', 'woocommerce');
        $actions['mark_impressed']  = __('Cambiar estado a impreso', 'woocommerce');
        $actions['mark_completed']  = __('Cambiar estado a completado', 'woocommerce');
        return $actions;
    }

    if ($status === 'wc-on-hold') {
        $actions['mark_reserved'] = __('Cambiar estado a reservado', 'woocommerce');
        $actions['mark_processing'] = __('Cambiar estado a procesando', 'woocommerce'); 
    }

    if ($status === 'wc-reserved') {
        $actions['mark_processing'] = __('Cambiar estado a procesando', 'woocommerce');
    }

    if ($status === 'wc-processing') {
        $actions['mark_impressed'] = __('Cambiar estado a impreso', 'woocommerce');
    }

    if ($status === 'wc-impressed') {
        $actions['mark_completed'] = __('Cambiar estado a completado', 'woocommerce');
    }

    return $actions;
}

// Reglas del rol también en este lote (roles-enforce.php); sin el módulo de roles, como antes
function inversionesjl22_site_bulk_allowed($order, $to) {
    return !function_exists('inversionesjl22_site_role_can_move')
        || inversionesjl22_site_role_can_move($order, $to, ['bulk_actions']);
}

add_filter('handle_bulk_actions-edit-shop_order', 'handleBulkActionsCustomStatus', 10, 3);
function handleBulkActionsCustomStatus($redirectTo, $action, $postIds) {
    if ($action === 'mark_reserved') {
        foreach ($postIds as $postId) {
            $order = wc_get_order($postId);
            if ($order && $order->get_status() === 'on-hold' && inversionesjl22_site_bulk_allowed($order, 'reserved')) {
                $order->update_status('reserved', __('Marcado como reservado por acción en lote.', 'woocommerce'));
            }
        }
        return add_query_arg('bulk_reserved', count($postIds), $redirectTo);
    }
    if ($action === 'mark_processing') {
        foreach ($postIds as $postId) {
            $order = wc_get_order($postId);
            if ($order && $order->get_status() === 'on-hold' && inversionesjl22_site_bulk_allowed($order, 'processing')) {
                $order->update_status('processing', __('Marcado como procesando por acción en lote.', 'woocommerce'));
            }
        }
        return add_query_arg('bulk_processing', count($postIds), $redirectTo);
    }
    if ($action === 'mark_impressed') {
        foreach ($postIds as $postId) {
            $order = wc_get_order($postId);
            if ($order && $order->get_status() === 'processing' && inversionesjl22_site_bulk_allowed($order, 'impressed')) {
                $order->update_status('impressed', __('Marcado como impreso por acción en lote.', 'woocommerce'));
            }
        }
        return add_query_arg('bulk_impressed', count($postIds), $redirectTo);
    }
    return $redirectTo;
}

add_filter('woocommerce_admin_order_preview_actions', 'previewActionsCustomStatus', 10, 2);
function previewActionsCustomStatus($actions, $order) {
    $status = $order->get_status();

    if ($status === 'on-hold') {
        $actions['reserved'] = [
            'url'    => wp_nonce_url(admin_url('admin-ajax.php?action=orderToReserved&order_id=' . $order->get_id()), 'orderToReserved'),
            'name'   => __('Reservado', 'woocommerce'),
            'action' => ''
        ];
    }

    if ($status === 'reserved') {
        $actions['processing'] = [
            'url'    => wp_nonce_url(admin_url('admin-ajax.php?action=orderToProcessing&order_id=' . $order->get_id()), 'orderToProcessing'),
            'name'   => __('Procesando', 'woocommerce'),
            'action' => ''
        ];
    }

    if ($status === 'processing') {
        $actions['impressed'] = [
            'url'    => wp_nonce_url(admin_url('admin-ajax.php?action=orderToImpressed&order_id=' . $order->get_id()), 'orderToImpressed'),
            'name'   => __('Impreso', 'woocommerce'),
            'action' => ''
        ];
    }

    if ($status === 'impressed') {
        $actions['completed'] = [
            'url'    => wp_nonce_url(admin_url('admin-ajax.php?action=orderToCompleted&order_id=' . $order->get_id()), 'orderToCompleted'),
            'name'   => __('Completado', 'woocommerce'),
            'action' => ''
        ];
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
                '<li class="%s"><a href="%s" class="%s">%s <span class="count">(%s)</span></a></li>',
                esc_attr($key),
                esc_url(admin_url('edit.php?post_status=' . $key . '&post_type=shop_order')),
                esc_attr($current === $key ? 'current' : ''),
                esc_html($status['label']),
                intval($quantity)
            );
        }
    }
    return inversionesjl22_site_place_statuses($views, $tabs);
}
