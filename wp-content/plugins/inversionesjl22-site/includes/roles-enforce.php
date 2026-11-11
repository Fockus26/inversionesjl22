<?php
/**
 * Roles: the server side of the status rules.
 *
 * roles-actions.php only decides which buttons each role sees. Every endpoint behind them took any
 * status from anyone with edit_shop_orders: a storage_manager with a link (or a crafted request)
 * could complete a processing order without printing it, or cancel one. Here each way of changing
 * a status checks the same rules (roles-config.php: the role's flow, the statuses it has off and
 * the per-context exclusions) before the change is made. Administrators are not restricted.
 *
 * Not covered on purpose: the QR scan (qr-scan.php), which is storage_manager's way of completing
 * a printed order and has its own check.
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_admin() ) {
	return;
}

/**
 * Whether the current user may move $order to $to from one of $contexts ('quick_actions',
 * 'order_preview_actions', 'bulk_actions', 'order_view_actions'). Keeping the current status is
 * always allowed; a role without rules (administrator) may do anything.
 */
function inversionesjl22_site_role_can_move( WC_Order $order, string $to, array $contexts ): bool {
	$ruleset = getUserPermissions();
	if ( null === $ruleset ) {
		return true;
	}

	$from = $order->get_status();
	$to   = 0 === strpos( $to, 'wc-' ) ? substr( $to, 3 ) : $to;
	if ( $to === $from ) {
		return true;
	}

	// An order in a status the role has off (storage_manager: on-hold, reserved) is not its to move.
	if ( empty( $ruleset[ $from ] ) ) {
		return false;
	}

	global $global_flow;
	$flow    = $ruleset['flow'] ?? $global_flow;
	$origins = isset( $flow[ $to ] ) ? (array) $flow[ $to ] : array();
	if ( ! in_array( $from, $origins, true ) ) {
		return false;
	}

	foreach ( $contexts as $context ) {
		if ( isStatusAllowedForAction( $to, $context, $ruleset ) ) {
			return true;
		}
	}
	return false;
}

// WooCommerce's own "mark as …" link (admin-ajax.php?action=woocommerce_mark_order_status), the
// one its order preview and list offer. It runs before WooCommerce's handler (priority 10).
add_action(
	'wp_ajax_woocommerce_mark_order_status',
	static function () {
		$order  = wc_get_order( absint( $_GET['order_id'] ?? 0 ) );
		$status = sanitize_key( wp_unslash( $_GET['status'] ?? '' ) );
		if ( $order && '' !== $status && ! inversionesjl22_site_role_can_move( $order, $status, array( 'quick_actions', 'order_preview_actions' ) ) ) {
			wp_die( esc_html__( 'Tu rol no puede hacer este cambio de estado.', 'inversionesjl22-site' ), '', array( 'response' => 403, 'back_link' => true ) );
		}
	},
	1
);

// Bulk "Cambiar estado a …": WooCommerce applies mark_<status> to every selected order. The orders
// the role may not move that way are dropped from the list before it does.
add_filter(
	'woocommerce_bulk_action_ids',
	static function ( $ids, $action, $type = '' ) {
		if ( 'order' !== $type || 0 !== strpos( (string) $action, 'mark_' ) || null === getUserPermissions() ) {
			return $ids;
		}
		$to = substr( $action, 5 );
		return array_values(
			array_filter(
				(array) $ids,
				static function ( $id ) use ( $to ) {
					$order = wc_get_order( $id );
					return $order && inversionesjl22_site_role_can_move( $order, $to, array( 'bulk_actions' ) );
				}
			)
		);
	},
	10,
	3
);

// Saving the order screen: a status the role's selector does not offer keeps the current one.
// Before WooCommerce reads order_status (WC_Meta_Box_Order_Data::save, priority 40).
add_action(
	'woocommerce_process_shop_order_meta',
	static function ( $order_id ) {
		if ( empty( $_POST['order_status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checked the nonce before this hook.
			return;
		}
		$order = wc_get_order( $order_id );
		$to    = wc_clean( wp_unslash( $_POST['order_status'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $order && ! inversionesjl22_site_role_can_move( $order, $to, array( 'order_view_actions' ) ) ) {
			$_POST['order_status'] = 'wc-' . $order->get_status();
		}
	},
	1
);
