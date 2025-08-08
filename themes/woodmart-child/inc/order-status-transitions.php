<?php
/**
 * ====================================================
 * AJAX CUSTOM ORDER STATUS ACTIONS
 * ====================================================
 * Maneja las transiciones de estado de pedido vía AJAX
 * desde los botones rápidos en el admin:
 * - Procesando
 * - Impreso (estado personalizado)
 * - Completado
 * - Cancelado
 */

/**
 * Función helper para registrar la acción AJAX que cambia
 * el estado del pedido y añade un comentario de log.
 *
 * @param string $action_name Nombre de la acción AJAX
 * @param string $new_status Nuevo estado del pedido (slug WC)
 * @param string $log_note Comentario que se agrega al pedido
 */
function jl22_register_order_status_transition_action($action_name, $new_status, $log_note) {
    add_action("wp_ajax_$action_name", function () use ($action_name, $new_status, $log_note) {
        // Verificamos permisos y nonce de seguridad
        if (!current_user_can('edit_shop_orders') || !check_admin_referer($action_name)) {
            wp_die(__('No autorizado', 'woocommerce'));
        }

        // Validamos ID del pedido
        $order_id = absint($_GET['order_id'] ?? 0);
        if (!$order_id) {
            wp_die(__('ID de pedido inválido', 'woocommerce'));
        }

        // Obtenemos el pedido
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die(__('Pedido no encontrado', 'woocommerce'));
        }

        // Actualizamos estado y añadimos nota
        $order->update_status($new_status, $log_note);

        // Redirigimos al listado de pedidos o a la página previa
        wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=shop_order'));
        exit;
    });
}

// Registro de transiciones por acción AJAX

// 🔄 Procesando
jl22_register_order_status_transition_action(
    'mark_order_as_processing',
    'processing',
    __('Pedido marcado como procesando manualmente.', 'woocommerce')
);

// 🖨️ Impreso (estado personalizado)
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
