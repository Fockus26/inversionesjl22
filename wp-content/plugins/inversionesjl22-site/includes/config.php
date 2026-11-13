<?php
/**
 * The shop's configuration: the order statuses, the flow between them and what each role sees
 * and does. One array, read by the modules that had these rules written in their own code
 * (order-statuses.php, order-transitions.php, roles-config.php, roles-actions.php,
 * roles-menu.php).
 *
 * The array comes from the option inversionesjl22_site_config; while nobody has saved one, it is
 * inversionesjl22_site_default_config(), the rules the site had in code. The settings screens
 * write it (settings-statuses.php). Deleting it is "back to the defaults".
 */

defined( 'ABSPATH' ) || exit;

const INVERSIONESJL22_SITE_CONFIG_OPTION = 'inversionesjl22_site_config';

/**
 * The rules as they were written in code before this file.
 *
 * - statuses: the statuses the shop works with, by slug (without wc-), in the order the status
 *   lists and tabs show them (WooCommerce's other statuses keep their places around them).
 *     action_label      what its button says ("Reservar").
 *     core              true: WooCommerce's own, not registered here.
 *     label             its name. Required for the shop's own; for WooCommerce's, only to
 *                       rename it.
 *     editable          the order's items can be changed while in it.
 *     requires_payment  an order picked up or paid on delivery needs its payment status
 *                       (Pagado / Por cobrar) before it is moved here.
 *     note              the order note of a manual change; by default "Pedido marcado como
 *                       <label> manualmente."
 * - flow: destination => the statuses an order can come from.
 * - roles: per restricted role (administrators are never restricted).
 *     statuses      slug => whether the role sees orders in it.
 *     exclude       context => the destinations (or bulk actions) the role does not get there.
 *                   Contexts: quick_actions, bulk_actions, order_preview_actions, order_view
 *                   (opening the order), order_view_actions (its status selector), qr_actions.
 *     flow          transitions the role has on top of the global flow.
 *     menu, screens, admin_bar   the wp-admin screens it reaches (see roles-menu.php).
 */
function inversionesjl22_site_default_config(): array {
	return array(
		'version'  => 1,
		'statuses' => array(
			'processing' => array(
				'action_label'     => 'Procesar',
				'core'             => true,
				'requires_payment' => true,
				'note'             => 'Pedido marcado como procesando manualmente.',
			),
			'impressed'  => array(
				'label'            => 'Impreso',
				'action_label'     => 'Impreso',
				'core'             => false,
				'requires_payment' => true,
			),
			'on-hold'    => array(
				'action_label' => 'En espera',
				'core'         => true,
				'note'         => 'Pedido marcado como en espera manualmente.',
			),
			'reserved'   => array(
				'label'            => 'Reservado',
				'action_label'     => 'Reservar',
				'core'             => false,
				'editable'         => true,
				'requires_payment' => true,
			),
			'completed'  => array(
				'action_label'     => 'Completar',
				'core'             => true,
				'requires_payment' => true,
				'note'             => 'Pedido marcado como completado manualmente.',
			),
			'cancelled'  => array(
				'action_label' => 'Cancelar',
				'core'         => true,
				'note'         => 'Pedido cancelado manualmente.',
			),
		),
		'flow'     => array(
			'reserved'   => array( 'on-hold' ),
			'processing' => array( 'reserved', 'on-hold' ),
			'impressed'  => array( 'processing' ),
			'completed'  => array( 'impressed' ),
			'cancelled'  => array( 'on-hold', 'reserved', 'processing' ),
		),
		'roles'    => array(
			'seo_manager'     => array(
				'statuses'  => array(
					'on-hold'    => true,
					'reserved'   => true,
					'processing' => true,
					'impressed'  => true,
					'completed'  => true,
					'cancelled'  => true,
				),
				'exclude'   => array(
					'quick_actions'         => array( 'impressed', 'completed', 'cancelled' ),
					'bulk_actions'          => array( 'impressed', 'completed' ),
					'order_preview_actions' => array( 'impressed', 'completed', 'cancelled' ),
					'order_view'            => array( 'cancelled' ),
					'order_view_actions'    => array( 'impressed', 'cancelled' ),
					'qr_actions'            => array( 'impressed', 'completed', 'cancelled' ),
				),
				// It can also send a processing order back to on hold.
				'flow'      => array(
					'on-hold' => array( 'processing' ),
				),
				'menu'      => array(
					'index.php',
					'admin.php?page=asnp-easy-sale-badge',
					'admin.php?page=wc-admin',
					'edit.php?post_type=shop_order',
					'admin.php?page=shipping-report',
					'admin.php?page=mercadopago-settings',
				),
				// Not post-new.php: it gave a 403 with URE too (its "New" link in the bar was dead).
				'screens'   => array( 'post.php?post_type=shop_order' ),
				'admin_bar' => array( 'menu-toggle', 'wp-logo', 'site-name', 'woocommerce-site-visibility-badge' ),
			),
			'storage_manager' => array(
				'statuses'  => array(
					'on-hold'    => false,
					'reserved'   => false,
					'processing' => true,
					'impressed'  => true,
					'completed'  => true,
					'cancelled'  => false,
				),
				'exclude'   => array(
					'quick_actions'         => array( 'completed' ),
					'bulk_actions'          => array( 'completed', 'wuoc_combine' ),
					'order_preview_actions' => array( 'completed' ),
					'order_view'            => array( 'impressed' ),
					'order_view_actions'    => array( 'impressed' ),
					'qr_actions'            => array( 'completed' ),
				),
				'flow'      => array(),
				'menu'      => array(
					'index.php',
					'edit.php?post_type=shop_order',
					'admin.php?page=shipping-report',
				),
				'screens'   => array( 'post.php?post_type=shop_order' ),
				'admin_bar' => array( 'menu-toggle', 'site-name' ),
			),
		),
	);
}

/**
 * The configuration in use: the saved one, or the defaults. A saved one that is not an array, or
 * lacks one of the three sections, falls back to the default for it, so a broken option never
 * leaves the roles without rules.
 */
function inversionesjl22_site_config(): array {
	static $config = null;
	static $hooked = false;

	if ( ! $hooked ) {
		$hooked = true;
		$reset  = static function () use ( &$config ) {
			$config = null;
		};
		foreach ( array( 'add_option_', 'update_option_', 'delete_option_' ) as $hook ) {
			add_action( $hook . INVERSIONESJL22_SITE_CONFIG_OPTION, $reset );
		}
	}

	if ( null === $config ) {
		$config = inversionesjl22_site_default_config();
		$saved  = get_option( INVERSIONESJL22_SITE_CONFIG_OPTION );
		if ( is_array( $saved ) ) {
			foreach ( array( 'statuses', 'flow', 'roles' ) as $section ) {
				if ( isset( $saved[ $section ] ) && is_array( $saved[ $section ] ) ) {
					$config[ $section ] = $saved[ $section ];
				}
			}
		}
	}
	return $config;
}

/**
 * The statuses the shop registers itself (Reservado, Impreso), slug => settings.
 */
function inversionesjl22_site_custom_statuses(): array {
	return array_filter(
		inversionesjl22_site_config()['statuses'],
		static function ( $status ) {
			return empty( $status['core'] ) && ! empty( $status['label'] );
		}
	);
}

/**
 * WooCommerce's own order statuses the shop does not work with, and the other post statuses a
 * new status must not be named after.
 *
 * @return string[] slugs without wc-
 */
function inversionesjl22_site_reserved_status_slugs(): array {
	return array( 'pending', 'refunded', 'failed', 'checkout-draft', 'draft', 'trash', 'auto-draft', 'publish', 'private', 'inherit', 'future', 'any', 'all' );
}

/**
 * WooCommerce's name for one of its statuses the shop works with, whatever the shop calls it.
 */
function inversionesjl22_site_core_status_label( string $slug ): string {
	// Not wc_get_order_status_name(): it runs the filter that renames them.
	$core = array(
		'on-hold'    => _x( 'On hold', 'Order status', 'woocommerce' ),
		'processing' => _x( 'Processing', 'Order status', 'woocommerce' ),
		'completed'  => _x( 'Completed', 'Order status', 'woocommerce' ),
		'cancelled'  => _x( 'Cancelled', 'Order status', 'woocommerce' ),
	);
	return $core[ $slug ] ?? ucfirst( $slug );
}

/**
 * The name of a status: its own, or WooCommerce's for one of its statuses that was not renamed.
 */
function inversionesjl22_site_status_label( string $slug ): string {
	$status = inversionesjl22_site_config()['statuses'][ $slug ] ?? array();
	return empty( $status['label'] ) ? inversionesjl22_site_core_status_label( $slug ) : (string) $status['label'];
}

/**
 * Where each status can go, the flow read from the origin's side: origin => destinations, both
 * in the statuses' order.
 *
 * @return array<string, string[]>
 */
function inversionesjl22_site_flow_destinations(): array {
	$config = inversionesjl22_site_config();
	$next   = array_fill_keys( array_keys( $config['statuses'] ), array() );
	foreach ( array_keys( $config['statuses'] ) as $to ) {
		foreach ( (array) ( $config['flow'][ $to ] ?? array() ) as $from ) {
			if ( isset( $next[ $from ] ) ) {
				$next[ $from ][] = $to;
			}
		}
	}
	return $next;
}

/**
 * What a status is in the flow: 'entry' (nothing leads to it), 'final' (it leads nowhere),
 * 'required' (some final status can only be reached through it: Procesando, on the way to
 * Completado) or 'optional' (every final status it leads to can also be reached around it:
 * Reservado).
 */
function inversionesjl22_site_status_kind( string $slug ): string {
	$next    = inversionesjl22_site_flow_destinations();
	$origins = (array) ( inversionesjl22_site_config()['flow'][ $slug ] ?? array() );
	if ( empty( $next[ $slug ] ) ) {
		return 'final';
	}
	if ( ! $origins ) {
		return 'entry';
	}

	// The statuses reached from $from without going through $without.
	$reach = static function ( array $from, string $without ) use ( $next ): array {
		$seen  = array();
		$stack = $from;
		while ( $stack ) {
			$current = (string) array_pop( $stack );
			if ( $current === $without || isset( $seen[ $current ] ) ) {
				continue;
			}
			$seen[ $current ] = true;
			foreach ( $next[ $current ] ?? array() as $to ) {
				$stack[] = $to;
			}
		}
		return $seen;
	};

	$entries = array();
	foreach ( array_keys( $next ) as $status ) {
		if ( empty( inversionesjl22_site_config()['flow'][ $status ] ) ) {
			$entries[] = (string) $status;
		}
	}
	$around = $reach( $entries, $slug );
	foreach ( array_keys( $reach( array( $slug ), '' ) ) as $status ) {
		if ( empty( $next[ $status ] ) && ! isset( $around[ $status ] ) ) {
			return 'required';
		}
	}
	return 'optional';
}

/**
 * What each status' button says, slug => label.
 */
function inversionesjl22_site_status_action_labels(): array {
	$labels = array();
	foreach ( inversionesjl22_site_config()['statuses'] as $slug => $status ) {
		$labels[ $slug ] = $status['action_label'] ?? ( $status['label'] ?? ucfirst( $slug ) );
	}
	return $labels;
}

/**
 * The admin-ajax action that moves an order to a status (order-transitions.php):
 * on-hold → orderToOnHold. '' for a status the shop does not work with.
 */
function inversionesjl22_site_status_ajax_action( string $slug ): string {
	if ( ! isset( inversionesjl22_site_config()['statuses'][ $slug ] ) ) {
		return '';
	}
	return 'orderTo' . str_replace( ' ', '', ucwords( str_replace( array( '-', '_' ), ' ', $slug ) ) );
}

/**
 * The flow of a role: the global one plus its own transitions. Without a role, the global one.
 *
 * @return array<string, string[]> destination => origins
 */
function inversionesjl22_site_flow( string $role = '' ): array {
	$config = inversionesjl22_site_config();
	$flow   = $config['flow'];
	if ( '' !== $role && ! empty( $config['roles'][ $role ]['flow'] ) ) {
		$flow = array_merge( $flow, $config['roles'][ $role ]['flow'] );
	}
	return array_map(
		static function ( $origins ) {
			return array_values( (array) $origins );
		},
		$flow
	);
}
