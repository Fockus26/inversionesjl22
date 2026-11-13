<?php
/**
 * Status changes from the order list's quick actions and the order preview: one AJAX action per
 * target status (orderToOnHold, orderToReserved, … orderToCancelled), with nonce and
 * edit_shop_orders, a note with the user's name, and the cash-on-delivery check (Pagado / Por
 * cobrar set first). The roles module decides which of these links each role sees.
 *
 * Moved as is from WPCodeBox › Order › Status Transition (snippet 47); its own change log is kept
 * below.
 */

defined( 'ABSPATH' ) || exit;

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

        // Las reglas del rol también en el servidor (roles-enforce.php), no solo en los botones
        if (function_exists('inversionesjl22_site_role_can_move')
            && !inversionesjl22_site_role_can_move($order, $status, ['quick_actions', 'order_preview_actions'])) {
            wp_die(esc_html__('Tu rol no puede hacer este cambio de estado.', 'inversionesjl22-site'), '', ['response' => 403, 'back_link' => true]);
        }

        // VALIDACIÓN: Si el método de envío requiere estado de pago, debe estar seleccionado
        $shippingMethods = getPickUpShippingMethods();
        $shippingItems = $order->get_items('shipping');
        $currentMethod = '';
        if (!empty($shippingItems)) {
            $firstItem = reset($shippingItems);
            $currentMethod = $firstItem->get_method_title();
        }
        // Which statuses ask for it: the configuration (config.php); before, every one but on-hold and cancelled
        $requiresPayment = !empty(inversionesjl22_site_config()['statuses'][$status]['requires_payment']);
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

// One action per status of the configuration (config.php): orderToOnHold, orderToReserved,
// orderToProcessing, orderToImpressed, orderToCompleted, orderToCancelled.
foreach ( inversionesjl22_site_config()['statuses'] as $inversionesjl22_site_slug => $inversionesjl22_site_status ) {
	orderStatusTransition(
		inversionesjl22_site_status_ajax_action( $inversionesjl22_site_slug ),
		$inversionesjl22_site_slug,
		$inversionesjl22_site_status['note'] ?? sprintf(
			'Pedido marcado como %s manualmente.',
			function_exists( 'mb_strtolower' )
				? mb_strtolower( $inversionesjl22_site_status['label'] ?? $inversionesjl22_site_slug )
				: strtolower( $inversionesjl22_site_status['label'] ?? $inversionesjl22_site_slug )
		)
	);
}
unset( $inversionesjl22_site_slug, $inversionesjl22_site_status );
