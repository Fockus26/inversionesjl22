<?php
/**
 * Combinar pedidos: the "Combinar pedidos" bulk action of the orders list, which replaces the
 * "Ultimate Order Combination" plugin (deactivated by migration 008), and the status
 * "Combinado" its originals end in. The combining itself is in combined-orders-merge.php,
 * loaded only when someone combines.
 *
 * Who combines: whoever can edit orders (edit_shop_orders), except a restricted role whose rules
 * exclude the action from its bulk actions (config.php, 'exclude' › 'bulk_actions' ›
 * 'combine_orders': storage_manager by default), editable in Roles y permisos. 'wuoc_combine',
 * the old plugin's key, still counts as the same entry until migration 008 renames it.
 *
 * "Combinado" (wc-combined) is a system status, not one of the configuration's: it is not in
 * Estados y flujo (its slug is reserved, so no status there can take it), it is in no flow and
 * in no role's matrix. An order only gets there by being combined, and never leaves it: there is
 * no undo, and any change of status of a combined order (the order screen, a bulk or quick
 * action, the QR, the REST API) is refused before it is saved. Its orders are left out of the
 * "Todos" view, the reports and the customer's Mi cuenta, are not paid, get no email, and are
 * neither invoiced nor scanned. Only administrators see them (the restricted roles' lists show
 * the statuses of their flow, which never has this one).
 *
 * wc-wuoc-custom, the old plugin's "Combinado" (its originals passed through it on the way to
 * the trash), is kept registered and treated as the same status, so an order left there by it is
 * not in an unknown status.
 *
 * Also kept from the old plugin, for the orders it combined: their combined orders never reduced
 * stock (the originals did), so they still do not.
 */

defined( 'ABSPATH' ) || exit;

/** The bulk action, and the entry that takes it from a role in its 'exclude' › 'bulk_actions'. */
const INVERSIONESJL22_SITE_COMBINE_ACTION = 'combine_orders';
/** The old plugin's key for the same action and entry (renamed by migration 008). */
const INVERSIONESJL22_SITE_COMBINE_ACTION_OLD = 'wuoc_combine';
/** The status of an order that was combined into another, without wc-. */
const INVERSIONESJL22_SITE_COMBINED_STATUS = 'combined';
/** Meta of the new order: the ids it was made from. */
const INVERSIONESJL22_SITE_COMBINED_FROM = '_inversionesjl22_site_combined_from';
/** Meta of an original: the id of the order it went into. */
const INVERSIONESJL22_SITE_COMBINED_INTO = '_inversionesjl22_site_combined_into';

/**
 * The statuses of a combined order, without wc-: ours and the old plugin's.
 *
 * @return string[]
 */
function inversionesjl22_site_combined_statuses(): array {
	return array( INVERSIONESJL22_SITE_COMBINED_STATUS, 'wuoc-custom' );
}

/**
 * Whether an order (or a status, with or without wc-) is a combined one.
 *
 * @param WC_Order|string $order_or_status
 */
function inversionesjl22_site_is_combined( $order_or_status ): bool {
	$status = $order_or_status instanceof WC_Order ? $order_or_status->get_status() : (string) $order_or_status;
	$status = 0 === strpos( $status, 'wc-' ) ? substr( $status, 3 ) : $status;
	return in_array( $status, inversionesjl22_site_combined_statuses(), true );
}

/**
 * Whether the bulk action is excluded in a list of 'bulk_actions' entries.
 *
 * @param mixed $bulk_actions
 */
function inversionesjl22_site_combine_excluded( $bulk_actions ): bool {
	$bulk_actions = (array) $bulk_actions;
	return in_array( INVERSIONESJL22_SITE_COMBINE_ACTION, $bulk_actions, true ) || in_array( INVERSIONESJL22_SITE_COMBINE_ACTION_OLD, $bulk_actions, true );
}

/**
 * Whether $role may combine, by its rules (not its capabilities). Roles without rules (the
 * administrator) may.
 */
function inversionesjl22_site_role_can_combine( string $role ): bool {
	$rules = inversionesjl22_site_config()['roles'][ $role ] ?? null;
	if ( ! is_array( $rules ) ) {
		return true;
	}
	return ! inversionesjl22_site_combine_excluded( $rules['exclude']['bulk_actions'] ?? array() );
}

/**
 * Whether the current user may combine orders.
 */
function inversionesjl22_site_can_combine(): bool {
	if ( ! current_user_can( 'edit_shop_orders' ) ) {
		return false;
	}
	$ruleset = function_exists( 'getUserPermissions' ) ? getUserPermissions() : null;
	if ( null === $ruleset ) {
		return true;
	}
	return ! inversionesjl22_site_combine_excluded( $ruleset['exclude_status_actions']['bulk_actions'] ?? array() );
}

/**
 * True while the combining moves its originals to "Combinado": the only change of status into
 * or out of it the guard below lets through. Call with a bool to set it.
 */
function inversionesjl22_site_combining( ?bool $set = null ): bool {
	static $combining = false;
	if ( null !== $set ) {
		$combining = $set;
	}
	return $combining;
}

/*
 * ---------------------------------------------------------------------------------------------
 * The status
 * ---------------------------------------------------------------------------------------------
 */

add_action(
	'init',
	static function () {
		foreach ( inversionesjl22_site_combined_statuses() as $slug ) {
			$label = INVERSIONESJL22_SITE_COMBINED_STATUS === $slug ? _x( 'Combinado', 'Order status', 'inversionesjl22-site' ) : _x( 'Combinado (plugin anterior)', 'Order status', 'inversionesjl22-site' );
			$count = $label . ' (%s)';
			register_post_status(
				'wc-' . $slug,
				array(
					'label'                     => $label,
					'public'                    => false,
					'exclude_from_search'       => true,
					// Like the trash: not in "Todos", in its own tab (which shows when it has orders).
					'show_in_admin_all_list'    => false,
					'show_in_admin_status_list' => true,
					// The array _n_noop() builds (see order-statuses.php).
					'label_count'               => array( 'singular' => $count, 'plural' => $count, 'context' => null, 'domain' => null ),
				)
			);
		}
	}
);

/**
 * In WooCommerce's list of statuses, at the end: an order can only be set to a status of that
 * list (any other becomes "Pendiente de pago"), and the orders list draws its badge from it.
 * The order screen's selector does not offer it (see below).
 */
add_filter(
	'wc_order_statuses',
	static function ( $statuses ) {
		if ( ! is_array( $statuses ) ) {
			return $statuses;
		}
		$statuses[ 'wc-' . INVERSIONESJL22_SITE_COMBINED_STATUS ] = _x( 'Combinado', 'Order status', 'inversionesjl22-site' );
		$statuses['wc-wuoc-custom']                               = _x( 'Combinado (plugin anterior)', 'Order status', 'inversionesjl22-site' );
		return $statuses;
	},
	// After order-statuses.php (10), which puts the shop's statuses in the configuration's order.
	20
);

/**
 * The guard: a combined order keeps its status, and no order is moved to "Combinado" but by the
 * combining. Before WooCommerce saves the change: the status is put back and the transition
 * (stock, emails, notes, the actions of other plugins) never runs.
 */
add_action(
	'woocommerce_before_order_object_save',
	static function ( $order ) {
		if ( ! $order instanceof WC_Order || inversionesjl22_site_combining() || ! $order->get_id() ) {
			return;
		}
		$changes = $order->get_changes();
		if ( ! isset( $changes['status'] ) ) {
			return;
		}
		$data = $order->get_data();
		$from = (string) ( $data['status'] ?? '' );
		$to   = (string) $changes['status'];
		// The trash is not a status change: a combined order can be trashed and restored.
		if ( $from === $to || 'trash' === $from || 'trash' === $to || ( ! inversionesjl22_site_is_combined( $from ) && ! inversionesjl22_site_is_combined( $to ) ) ) {
			return;
		}

		$order->set_status( $from );
		// WC_Order keeps the transition it would run after saving; it has no setter for it.
		$forget = function () {
			$this->status_transition = false;
		};
		Closure::bind( $forget, $order, WC_Order::class )();

		if ( inversionesjl22_site_is_combined( $from ) ) {
			$message = sprintf(
				/* translators: %s: status name */
				__( 'Este pedido se combinó en otro: no cambia de estado (se pidió %s).', 'inversionesjl22-site' ),
				wc_get_order_status_name( $to )
			);
		} else {
			$message = __( 'Un pedido solo pasa a Combinado al combinarlo con la acción en lote «Combinar pedidos».', 'inversionesjl22-site' );
		}
		if ( is_admin() && is_user_logged_in() ) {
			inversionesjl22_site_combine_notice( 'error', $message );
		}
	},
	PHP_INT_MAX
);

// Not in the reports nor in WooCommerce Analytics: their sales are the new order's.
add_filter(
	'woocommerce_analytics_excluded_order_statuses',
	static function ( $statuses ) {
		return is_array( $statuses ) ? array_values( array_unique( array_merge( $statuses, inversionesjl22_site_combined_statuses() ) ) ) : $statuses;
	}
);
add_filter(
	'woocommerce_reports_order_statuses',
	static function ( $statuses ) {
		return is_array( $statuses ) ? array_values( array_diff( $statuses, inversionesjl22_site_combined_statuses() ) ) : $statuses;
	}
);

// Not in the customer's Mi cuenta › Pedidos: the customer sees the new order (as when the
// originals went to the trash).
add_filter(
	'woocommerce_my_account_my_orders_query',
	static function ( $args ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}
		$all = empty( $args['status'] ) || 'any' === $args['status'] ? array_keys( wc_get_order_statuses() ) : (array) $args['status'];
		$out = array();
		foreach ( $all as $status ) {
			if ( ! inversionesjl22_site_is_combined( (string) $status ) ) {
				$out[] = $status;
			}
		}
		$args['status'] = $out;
		return $args;
	}
);

/**
 * Restoring a combined original from the trash (HPOS, which runs this action; orders as posts:
 * the filter below). Two things go wrong otherwise:
 *  - its status: WordPress restores to "draft" unless a filter brings back the previous one;
 *    WooCommerce's does, but on this site ATUM's runs after it and leaves "draft" (every order,
 *    not only these). A combined original is put back in "Combinado", as it was;
 *  - its sales: WooCommerce adds the sales of a restored order whose sales are not counted
 *    (wc_update_total_sales_counts), and an original's are not counted on purpose (they are the
 *    new order's): that callback is left out for it.
 * Told apart by its link to the new order, not by its status (which may already be "draft").
 */
add_action(
	'woocommerce_untrash_order',
	static function ( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || ! $order->get_meta( INVERSIONESJL22_SITE_COMBINED_INTO ) ) {
			return;
		}
		if ( false !== has_action( 'woocommerce_untrash_order', 'wc_update_total_sales_counts' ) ) {
			remove_action( 'woocommerce_untrash_order', 'wc_update_total_sales_counts' );
			add_action(
				'woocommerce_untrash_order',
				static function () {
					add_action( 'woocommerce_untrash_order', 'wc_update_total_sales_counts' );
				},
				11
			);
		}
		if ( ! inversionesjl22_site_is_combined( $order ) ) {
			inversionesjl22_site_combining( true );
			try {
				$order->set_status( INVERSIONESJL22_SITE_COMBINED_STATUS, __( 'Restaurado de la papelera: vuelve a Combinado.', 'inversionesjl22-site' ) );
				$order->save();
			} finally {
				inversionesjl22_site_combining( false );
			}
		}
	},
	9
);

// The same for orders as posts, where WordPress restores and woocommerce_untrash_order does not
// run: the status it restores to, after ATUM's filter (10).
add_filter(
	'wp_untrash_post_status',
	static function ( $status, $post_id, $previous ) {
		return 'shop_order' === get_post_type( $post_id ) && inversionesjl22_site_is_combined( (string) $previous ) ? $previous : $status;
	},
	99,
	3
);

// The old plugin's combined orders: no stock reduction (their originals reduced it).
add_filter(
	'woocommerce_can_reduce_order_stock',
	static function ( $can, $order ) {
		if ( $can && $order instanceof WC_Order && $order->get_meta( '_wuoc_cloned_order' ) ) {
			return false;
		}
		return $can;
	},
	10,
	2
);

/**
 * Leaves a notice for the orders list's (or the order screen's) next load, for this user.
 */
function inversionesjl22_site_combine_notice( string $type, string $message, string $link = '' ): void {
	set_transient( 'inversionesjl22_site_combine_notice_' . get_current_user_id(), compact( 'type', 'message', 'link' ), MINUTE_IN_SECONDS );
}

if ( ! is_admin() ) {
	return;
}

/*
 * ---------------------------------------------------------------------------------------------
 * The order screen
 * ---------------------------------------------------------------------------------------------
 */

/**
 * The order the order screen shows (orders as posts or HPOS), or null elsewhere.
 */
function inversionesjl22_site_combine_screen_order(): ?WC_Order {
	global $pagenow, $post;
	$id = 0;
	if ( 'post.php' === $pagenow && $post instanceof WP_Post && 'shop_order' === $post->post_type ) {
		$id = (int) $post->ID;
	} elseif ( 'admin.php' === $pagenow && isset( $_GET['page'], $_GET['id'] ) && 'wc-orders' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only, to know the order shown.
		$id = absint( $_GET['id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	$order = $id ? wc_get_order( $id ) : null;
	return $order instanceof WC_Order ? $order : null;
}

/**
 * The status selector of the order screen: a combined order offers only its own status (it
 * cannot leave it), any other order never offers "Combinado". Only while the screen is drawn.
 */
add_action(
	'add_meta_boxes',
	static function () {
		add_filter(
			'wc_order_statuses',
			static function ( $statuses ) {
				$order = is_array( $statuses ) && ! doing_action( 'woocommerce_process_shop_order_meta' ) ? inversionesjl22_site_combine_screen_order() : null;
				if ( ! $order ) {
					return $statuses;
				}
				if ( inversionesjl22_site_is_combined( $order ) ) {
					$own = 'wc-' . $order->get_status();
					return isset( $statuses[ $own ] ) ? array( $own => $statuses[ $own ] ) : $statuses;
				}
				foreach ( inversionesjl22_site_combined_statuses() as $slug ) {
					unset( $statuses[ 'wc-' . $slug ] );
				}
				return $statuses;
			},
			PHP_INT_MAX
		);
	}
);

/**
 * The link between the orders, under the status in the order screen: the originals of a
 * combined order, or the order an original went into.
 */
add_action(
	'woocommerce_admin_order_data_after_order_details',
	static function ( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$into = (int) $order->get_meta( INVERSIONESJL22_SITE_COMBINED_INTO );
		$from = array_filter( array_map( 'absint', (array) $order->get_meta( INVERSIONESJL22_SITE_COMBINED_FROM ) ) );
		if ( ! $into && ! $from ) {
			return;
		}
		$link = static function ( int $id ): string {
			$linked = wc_get_order( $id );
			if ( ! $linked instanceof WC_Order ) {
				/* translators: %d: order id */
				return esc_html( sprintf( __( '#%d (ya no existe)', 'inversionesjl22-site' ), $id ) );
			}
			return sprintf( '<a href="%s">#%s</a>', esc_url( $linked->get_edit_order_url() ), esc_html( $linked->get_order_number() ) );
		};
		echo '<p class="form-field form-field-wide jl22-combined-link">';
		if ( $into ) {
			echo '<strong>' . esc_html__( 'Combinado en el pedido:', 'inversionesjl22-site' ) . '</strong> ' . $link( $into ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped above.
			echo '<br>' . esc_html__( 'Sus productos, su inventario y su cobro pasaron a ese pedido. Este no cambia de estado.', 'inversionesjl22-site' );
		} else {
			echo '<strong>' . esc_html__( 'Combinado a partir de:', 'inversionesjl22-site' ) . '</strong> ' . implode( ', ', array_map( $link, $from ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped above.
		}
		echo '</p>';
	}
);

/*
 * ---------------------------------------------------------------------------------------------
 * The orders list
 * ---------------------------------------------------------------------------------------------
 */

/**
 * Whether the orders list shows the "Combinado" tab.
 */
function inversionesjl22_site_combine_in_combined_view(): bool {
	$status = isset( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only, to choose the actions.
	if ( '' === $status && isset( $_GET['status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- HPOS list.
		$status = sanitize_key( wp_unslash( $_GET['status'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	return '' !== $status && inversionesjl22_site_is_combined( $status );
}

/**
 * Adds the action to the list's bulk actions. After roles-actions.php (priority 20), which
 * empties the list in the "Todos" view for restricted roles: the old plugin's action showed in
 * every view. In the "Combinado" tab no action changes anything (the guard refuses it), so
 * none is offered but the trash.
 */
function inversionesjl22_site_combine_bulk_action( $actions ) {
	if ( ! is_array( $actions ) ) {
		return $actions;
	}
	if ( inversionesjl22_site_combine_in_combined_view() ) {
		return array_intersect_key( $actions, array( 'trash' => true ) );
	}
	if ( inversionesjl22_site_can_combine() ) {
		$actions[ INVERSIONESJL22_SITE_COMBINE_ACTION ] = __( 'Combinar pedidos', 'inversionesjl22-site' );
	}
	return $actions;
}
add_filter( 'bulk_actions-edit-shop_order', 'inversionesjl22_site_combine_bulk_action', 30 );
add_filter( 'bulk_actions-woocommerce_page_wc-orders', 'inversionesjl22_site_combine_bulk_action', 30 );

/**
 * Runs the action. WordPress (edit.php) and WooCommerce (HPOS list) check the list's nonce before
 * calling this; it is checked again here, with the capability, because this filter is public.
 *
 * @param string $redirect Where the list goes back to.
 * @param string $action   The bulk action asked.
 * @param int[]  $ids      The ticked orders.
 * @param string $nonce    The list's nonce action.
 */
function inversionesjl22_site_combine_handle( $redirect, $action, $ids, $nonce ) {
	if ( INVERSIONESJL22_SITE_COMBINE_ACTION !== $action ) {
		return $redirect;
	}
	check_admin_referer( $nonce );
	if ( ! inversionesjl22_site_can_combine() ) {
		inversionesjl22_site_combine_notice( 'error', __( 'Tu rol no puede combinar pedidos.', 'inversionesjl22-site' ) );
		return $redirect;
	}

	require_once __DIR__ . '/combined-orders-merge.php';
	$result = inversionesjl22_site_combine_orders( array_map( 'absint', (array) $ids ) );
	if ( is_wp_error( $result ) ) {
		inversionesjl22_site_combine_notice( 'error', $result->get_error_message() );
		return $redirect;
	}

	$from = array_filter( array_map( 'wc_get_order', (array) $result->get_meta( INVERSIONESJL22_SITE_COMBINED_FROM ) ) );
	inversionesjl22_site_combine_notice(
		'success',
		sprintf(
			/* translators: 1: order numbers, 2: new order number, 3: status */
			__( 'Se combinaron %1$s en el pedido #%2$s (%3$s). Los originales quedaron en Combinado.', 'inversionesjl22-site' ),
			inversionesjl22_site_combine_numbers( $from ),
			$result->get_order_number(),
			wc_get_order_status_name( $result->get_status() )
		),
		$result->get_edit_order_url()
	);
	return $redirect;
}
add_filter(
	'handle_bulk_actions-edit-shop_order',
	static function ( $redirect, $action, $ids ) {
		return inversionesjl22_site_combine_handle( $redirect, $action, $ids, 'bulk-posts' );
	},
	10,
	3
);
add_filter(
	'handle_bulk_actions-woocommerce_page_wc-orders',
	static function ( $redirect, $action, $ids ) {
		return inversionesjl22_site_combine_handle( $redirect, $action, $ids, 'bulk-orders' );
	},
	10,
	3
);

// The badge of "Combinado" in the orders list, with the others' colours (config.php).
add_action(
	'admin_enqueue_scripts',
	static function () {
		if ( ! wp_style_is( 'inversionesjl22-admin-orders', 'enqueued' ) || ! function_exists( 'inversionesjl22_site_status_palette' ) ) {
			return;
		}
		$color = inversionesjl22_site_status_palette()['lilac'];
		$css   = '';
		foreach ( inversionesjl22_site_combined_statuses() as $slug ) {
			$css .= sprintf( '.order-status.status-%1$s{background:%2$s;color:%3$s}', sanitize_html_class( $slug ), $color['bg'], $color['fg'] );
		}
		wp_add_inline_style( 'inversionesjl22-admin-orders', $css );
	},
	// After assets.php (10), which enqueues the handle.
	20
);

add_action(
	'admin_notices',
	static function () {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'edit-shop_order', 'woocommerce_page_wc-orders', 'shop_order' ), true ) ) {
			return;
		}
		$key    = 'inversionesjl22_site_combine_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}
		delete_transient( $key );
		$type = 'success' === ( $notice['type'] ?? '' ) ? 'success' : 'error';
		?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible" role="<?php echo 'error' === $type ? 'alert' : 'status'; ?>">
			<p>
				<?php echo esc_html( $notice['message'] ); ?>
				<?php if ( ! empty( $notice['link'] ) ) : ?>
					<a href="<?php echo esc_url( $notice['link'] ); ?>"><?php esc_html_e( 'Abrir el pedido combinado', 'inversionesjl22-site' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}
);
