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

        // VALIDACIÓN: Si el método de envío requiere estado de pago, debe estar seleccionado
        $shipping_methods = [
            'CONTRA ENTREGA EFECTIVO',
            'DOMICILIO CUCUTA',
            'DOMICILIO SAN CRISTOBAL',
            'ENTREGAS TEALCA'
        ];
        $shipping_items = $order->get_items('shipping');
        $current_method = '';
        if (!empty($shipping_items)) {
            $first_item = reset($shipping_items);
            $current_method = $first_item->get_method_title();
        }
        if (in_array($current_method, $shipping_methods)) {
          $status_pago = get_post_meta($order_id, '_jl_pago_status', true);
          if (empty($status_pago)) {
              // Redirige con parámetro de error
              $redirect_url = add_query_arg('jl22_notice', 'error', wp_get_referer() ?: admin_url('edit.php?post_type=shop_order'));
              wp_safe_redirect($redirect_url);
              exit;
          }
        }

        // Obtener nombre del usuario actual
        $user = wp_get_current_user();
        $user_name = $user->display_name ?: 'usuario';

        // Actualizamos estado y añadimos nota con el nombre del usuario
        $order->update_status($new_status, $log_note . ' (por: ' . $user_name . ')');

        // Redirigimos al listado de pedidos o a la página previa
        $redirect_url = add_query_arg('jl22_notice', 'success', wp_get_referer() ?: admin_url('edit.php?post_type=shop_order'));
        wp_safe_redirect($redirect_url);
        exit;
    });
}

// Registro de transiciones por acción AJAX

// 📅 Reservado
jl22_register_order_status_transition_action(
    'mark_order_as_reserved',
    'reserved',
    __('Pedido marcado como reservado manualmente.', 'woocommerce')
);


// 🔄 Procesando
jl22_register_order_status_transition_action(
    'mark_order_as_processing',
    'processing',
    __('Pedido marcado como procesando manualmente.', 'woocommerce')
);

// 🖨️ Impreso (estado personalizado)
jl22_register_order_status_transition_action(
    'mark_order_as_impressed',
    'impressed',
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
