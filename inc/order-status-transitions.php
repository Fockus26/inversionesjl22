<?php
/**
 * ====================================================
 * AJAX CUSTOM ORDER STATUS ACTIONS
 * ====================================================
 * Esto maneja las transiciones de estado por botón rápido:
 * - Procesando
 * - Impreso
 * - Completado
 * - Cancelado
 */

function jl22_register_order_status_transition_action($action_name, $new_status, $log_note) {
    add_action("wp_ajax_$action_name", function () use ($action_name, $new_status, $log_note) {
        if (!current_user_can('edit_shop_orders') || !check_admin_referer($action_name)) {
            wp_die(__('No autorizado', 'woocommerce'));
        }

        $order_id = absint($_GET['order_id'] ?? 0);
        if (!$order_id) {
            wp_die(__('ID de pedido inválido', 'woocommerce'));
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die(__('Pedido no encontrado', 'woocommerce'));
        }

        $order->update_status($new_status, $log_note);
        wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=shop_order'));
        exit;
    });
}

// 🔄 Procesando
jl22_register_order_status_transition_action(
    'mark_order_as_processing',
    'processing',
    __('Pedido marcado como procesando manualmente.', 'woocommerce')
);

// 🖨️ Impreso
jl22_register_order_status_transition_action(
    'mark_order_as_impreso',
    'impreso',
    __('Pedido marcado como impreso manualmente.', 'woocommerce')
);

// ✅ Completado
jl22_register_order_status_transition_action(
    'mark_order_as_completed',
    'completed',
    __('Pedido marcado como completado manualmente.', 'woocommerce')
);

// ❌ Cancelado
jl22_register_order_status_transition_action(
    'mark_order_as_cancelled',
    'cancelled',
    __('Pedido cancelado manualmente.', 'woocommerce')
);


// 🔄 Procesando
jl22_register_order_status_transition_action(
    'mark_order_as_processing',
    'processing',
    __('Pedido marcado como procesando manualmente.', 'woocommerce')
);

// 🖨️ Impreso
jl22_register_order_status_transition_action(
    'mark_order_as_impreso',
    'impreso',
    __('Pedido marcado como impreso manualmente.', 'woocommerce')
);

// ✅ Completado
jl22_register_order_status_transition_action(
    'mark_order_as_completed',
    'completed',
    __('Pedido marcado como completado manualmente.', 'woocommerce')
);

// ❌ Cancelado
jl22_register_order_status_transition_action(
    'mark_order_as_cancelled',
    'cancelled',
    __('Pedido cancelado manualmente.', 'woocommerce')
);
