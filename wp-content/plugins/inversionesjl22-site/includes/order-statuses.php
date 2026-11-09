<?php
/**
 * The shop's own order statuses, Reservado (wc-reserved) and Impreso (wc-impressed): their
 * registration, their place in the status list and tabs, editing a reserved order, and their
 * quick, bulk and preview actions.
 *
 * Moved as is from WPCodeBox › Order › Custom Status (snippet 49). The roles module filters the
 * actions added here, so it loads after this one (order of the loader in the main file).
 */

defined( 'ABSPATH' ) || exit;

add_action('init', 'createCustomStatus');
function createCustomStatus() {
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
}

add_filter( 'wc_order_is_editable', 'addProductsInReservedStatus', 10, 2 );
function addProductsInReservedStatus($is_editable, $order) {
    if ( $order->get_status() === 'reserved') {
        $is_editable = true;
    }
    
    return $is_editable;
}

add_filter('wc_order_statuses', 'sortCustomStatus');
function sortCustomStatus($statuses) {
    $sortStatuses = [];
    foreach ($statuses as $key => $label) {
        $sortStatuses[$key] = $label;
        if ($key === 'wc-on-hold') {
            $sortStatuses['wc-reserved'] = 'Reservado';
        }
        if ($key === 'wc-processing') {
            $sortStatuses['wc-impressed'] = 'Impreso';
        }
    }
    return $sortStatuses;
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

add_filter('handle_bulk_actions-edit-shop_order', 'handleBulkActionsCustomStatus', 10, 3);
function handleBulkActionsCustomStatus($redirectTo, $action, $postIds) {
    if ($action === 'mark_reserved') {
        foreach ($postIds as $postId) {
            $order = wc_get_order($postId);
            if ($order && $order->get_status() === 'on-hold') {
                $order->update_status('reserved', __('Marcado como reservado por acción en lote.', 'woocommerce'));
            }
        }
        return add_query_arg('bulk_reserved', count($postIds), $redirectTo);
    }
    if ($action === 'mark_processing') {
        foreach ($postIds as $postId) {
            $order = wc_get_order($postId);
            if ($order && $order->get_status() === 'on-hold') {
                $order->update_status('processing', __('Marcado como procesando por acción en lote.', 'woocommerce'));
            }
        }
        return add_query_arg('bulk_processing', count($postIds), $redirectTo);
    }
    if ($action === 'mark_impressed') {
        foreach ($postIds as $postId) {
            $order = wc_get_order($postId);
            if ($order && $order->get_status() === 'processing') {
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
    $impressedCount = isset($count->{'wc-impressed'}) ? $count->{'wc-impressed'} : 0;
    $reservedCount  = isset($count->{'wc-reserved'}) ? $count->{'wc-reserved'} : 0;

    if ($reservedCount > 0) {
        $class = $current === 'wc-reserved' ? 'current' : '';
        $url = admin_url('edit.php?post_status=wc-reserved&post_type=shop_order');
        $views['wc-reserved'] = sprintf(
            '<li class="wc-reserved"><a href="%s" class="%s">%s <span class="count">(%s)</span></a></li>',
            esc_url($url),
            esc_attr($class),
            __('Reservado', 'woocommerce'),
            intval($reservedCount)
        );
    }
    if ($impressedCount > 0) {
        $class = $current === 'wc-impressed' ? 'current' : '';
        $url = admin_url('edit.php?post_status=wc-impressed&post_type=shop_order');
        $views['wc-impressed'] = sprintf(
            '<li class="wc-impressed"><a href="%s" class="%s">%s <span class="count">(%s)</span></a></li>',
            esc_url($url),
            esc_attr($class),
            __('Impreso', 'woocommerce'),
            intval($impressedCount)
        );
    }

    $newViews = [];
    foreach ($views as $key => $html) {
        $newViews[$key] = $html;
        if ($key === 'wc-on-hold' && isset($views['wc-reserved'])) {
            $newViews['wc-reserved'] = $views['wc-reserved'];
        }
        if ($key === 'wc-processing' && isset($views['wc-impressed'])) {
            $newViews['wc-impressed'] = $views['wc-impressed'];
        }
    }
    return $newViews;
}
