<?php
/*
 * WPCodeBox > Order > "Status Transition" (id 47) — PHP, Plugins Loaded.
 *
 * Cambios respecto a la versión anterior:
 * - La validación de pago leía la meta 'payment-status', pero el snippet "Payment Status" guarda '_payment-status'.
 *   En pedidos con envío CONTRA ENTREGA / DOMICILIO / TEALCA la validación fallaba siempre y ningún cambio de
 *   estado desde las acciones rápidas o la vista previa se aplicaba (p. ej. seo_manager: procesando → en espera).
 * - Devolver a "en espera" o cancelar ya no exige elegir Pagado / Por Cobrar.
 */
function orderStatusTransition($action, $status, $note) {
    add_action("wp_ajax_$action", function () use ($action, $status, $note) {
        // Verificamos permisos y nonce de seguridad
        if (!current_user_can('edit_shop_orders') || !check_admin_referer($action)) {
            wp_die(__('No autorizado', 'woocommerce'));
        }

        // Validamos ID del pedido
        $orderId = absint($_GET['order_id'] ?? 0);
        if (!$orderId) {
            wp_die(__('ID de pedido inválido', 'woocommerce'));
        }

        // Obtenemos el pedido
        $order = wc_get_order($orderId);
        if (!$order) {
            wp_die(__('Pedido no encontrado', 'woocommerce'));
        }

        // VALIDACIÓN: Si el método de envío requiere estado de pago, debe estar seleccionado
        $shippingMethods = getPickUpShippingMethods();
        $shippingItems = $order->get_items('shipping');
        $currentMethod = '';
        if (!empty($shippingItems)) {
            $firstItem = reset($shippingItems);
            $currentMethod = $firstItem->get_method_title();
        }
        $requiresPayment = !in_array($status, ['on-hold', 'cancelled'], true);
        if ($requiresPayment && in_array($currentMethod, $shippingMethods)) {
          $paymentStatus = get_post_meta($orderId, '_payment-status', true);
          if (empty($paymentStatus)) {
              // Redirige con parámetro de error
              $url = add_query_arg('statusTransitionAction', 'error', wp_get_referer() ?: admin_url('edit.php?post_type=shop_order'));
              wp_safe_redirect($url);
              exit;
          }
        }

        // Obtener nombre del usuario actual
        $user = wp_get_current_user();
        $username = $user->display_name ?: 'usuario';

        // Actualizamos estado y añadimos nota con el nombre del usuario
        $order->update_status($status, $note . ' (por: ' . $username . ')');

        // Redirigimos al listado de pedidos o a la página previa
        $url = add_query_arg('statusTransitionAction', 'success', wp_get_referer() ?: admin_url('edit.php?post_type=shop_order'));
        wp_safe_redirect($url);
        exit;
    });
}

// ⏸️ En espera
orderStatusTransition(
    'orderToOnHold',
    'on-hold',
    __('Pedido marcado como en espera manualmente.', 'woocommerce')
);

// 📅 Reservado
orderStatusTransition(
    'orderToReserved',
    'reserved',
    __('Pedido marcado como reservado manualmente.', 'woocommerce')
);

// 🔄 Procesando
orderStatusTransition(
    'orderToProcessing',
    'processing',
    __('Pedido marcado como procesando manualmente.', 'woocommerce')
);

// 🖨️ Impreso
orderStatusTransition(
    'orderToImpressed',
    'impressed',
    __('Pedido marcado como impreso manualmente.', 'woocommerce')
);

// ✅ Completado
orderStatusTransition(
    'orderToCompleted',
    'completed',
    __('Pedido marcado como completado manualmente.', 'woocommerce')
);

// ❌ Cancelado
orderStatusTransition(
    'orderToCancelled',
    'cancelled',
    __('Pedido cancelado manualmente.', 'woocommerce')
);
