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
 *     color             a key of inversionesjl22_site_status_palette(): its badge in the orders
 *                       list. None: WooCommerce's own colour.
 *     icon              a key of inversionesjl22_site_status_icons(): the icon of its quick
 *                       action. None: WooCommerce's own, if it has one.
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
 *     qr_scan       whether scanning the invoice's QR completes a printed order and the role's
 *                   screens show the scans (qr-scan.php). A saved role without it: only
 *                   storage_manager, as before it was a setting.
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
				'icon'             => 'printer',
				'core'             => false,
				'requires_payment' => true,
			),
			'on-hold'    => array(
				'action_label' => 'En espera',
				'icon'         => 'clock',
				'core'         => true,
				'note'         => 'Pedido marcado como en espera manualmente.',
			),
			'reserved'   => array(
				'label'            => 'Reservado',
				'action_label'     => 'Reservar',
				'icon'             => 'lock',
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
				'icon'         => 'dismiss',
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
				'qr_scan'   => false,
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
				'qr_scan'   => true,
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
 * Whether a role of the configuration scans the invoice's QR. A role saved before this was a
 * setting has no 'qr_scan': then only storage_manager, the role it was written for.
 */
function inversionesjl22_site_role_scans_qr( string $role ): bool {
	$rules = inversionesjl22_site_config()['roles'][ $role ] ?? null;
	if ( ! is_array( $rules ) || 'administrator' === $role ) {
		return false;
	}
	return array_key_exists( 'qr_scan', $rules ) ? (bool) $rules['qr_scan'] : 'storage_manager' === $role;
}

/**
 * Whether the current user scans the QR: one of their roles has it. Nobody without a session,
 * and not an administrator as such (they never had it: the check was the storage_manager role,
 * which an administrator does not hold unless also given that role).
 */
function inversionesjl22_site_user_can_scan_qr(): bool {
	if ( ! is_user_logged_in() ) {
		return false;
	}
	foreach ( (array) wp_get_current_user()->roles as $role ) {
		if ( inversionesjl22_site_role_scans_qr( (string) $role ) ) {
			return true;
		}
	}
	return false;
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
		// Added to the global origins of each destination, not instead of them.
		foreach ( (array) $config['roles'][ $role ]['flow'] as $to => $origins ) {
			$flow[ $to ] = array_unique( array_merge( (array) ( $flow[ $to ] ?? array() ), (array) $origins ) );
		}
	}
	return array_map(
		static function ( $origins ) {
			return array_values( (array) $origins );
		},
		$flow
	);
}

/**
 * The colours a status can take, key => name, background, text and border. A closed list and
 * not a colour picker: every pair here passes 4.5:1 (the lowest is 6.4:1), and every border 3:1
 * on white. Tones of the site's visual system (design canvas, board Componentes).
 *
 * @return array<string, array{label: string, bg: string, fg: string, border: string}>
 */
function inversionesjl22_site_status_palette(): array {
	return array(
		'peach'  => array( 'label' => __( 'Durazno', 'inversionesjl22-site' ), 'bg' => '#fff1e3', 'fg' => '#8a4513', 'border' => '#c77a3a' ),
		'orange' => array( 'label' => __( 'Naranja', 'inversionesjl22-site' ), 'bg' => '#ffd9b8', 'fg' => '#5a2c0c', 'border' => '#c77a3a' ),
		'yellow' => array( 'label' => __( 'Amarillo', 'inversionesjl22-site' ), 'bg' => '#fbf1c9', 'fg' => '#5a4500', 'border' => '#a8902f' ),
		'green'  => array( 'label' => __( 'Verde', 'inversionesjl22-site' ), 'bg' => '#e6efe9', 'fg' => '#24432f', 'border' => '#6f9a80' ),
		'blue'   => array( 'label' => __( 'Azul', 'inversionesjl22-site' ), 'bg' => '#e3edf7', 'fg' => '#1f3f5c', 'border' => '#6f93b5' ),
		'lilac'  => array( 'label' => __( 'Lila', 'inversionesjl22-site' ), 'bg' => '#ece9f3', 'fg' => '#3d3b52', 'border' => '#8d87a8' ),
		'red'    => array( 'label' => __( 'Rojo', 'inversionesjl22-site' ), 'bg' => '#f8e1da', 'fg' => '#7a2c10', 'border' => '#c0795f' ),
		'grey'   => array( 'label' => __( 'Gris', 'inversionesjl22-site' ), 'bg' => '#f1eeeb', 'fg' => '#4a4959', 'border' => '#8c8494' ),
		'slate'  => array( 'label' => __( 'Oscuro', 'inversionesjl22-site' ), 'bg' => '#4a4959', 'fg' => '#ffffff', 'border' => '#4a4959' ),
	);
}

/**
 * The icons a status can take: Dashicons, which wp-admin already loads. Key = the icon's
 * Dashicons name (class dashicons-<key>) => its name for people and its glyph (wp-includes/css/
 * dashicons.css).
 *
 * @return array<string, array{label: string, glyph: string}>
 */
function inversionesjl22_site_status_icons(): array {
	return array(
		'clock'        => array( 'label' => __( 'Reloj', 'inversionesjl22-site' ), 'glyph' => 'f469' ),
		'hourglass'    => array( 'label' => __( 'Reloj de arena', 'inversionesjl22-site' ), 'glyph' => 'f18c' ),
		'lock'         => array( 'label' => __( 'Candado', 'inversionesjl22-site' ), 'glyph' => 'f160' ),
		'calendar-alt' => array( 'label' => __( 'Calendario', 'inversionesjl22-site' ), 'glyph' => 'f508' ),
		'update'       => array( 'label' => __( 'En curso', 'inversionesjl22-site' ), 'glyph' => 'f463' ),
		'printer'      => array( 'label' => __( 'Impresora', 'inversionesjl22-site' ), 'glyph' => 'f193' ),
		'archive'      => array( 'label' => __( 'Caja', 'inversionesjl22-site' ), 'glyph' => 'f480' ),
		'products'     => array( 'label' => __( 'Paquete', 'inversionesjl22-site' ), 'glyph' => 'f312' ),
		'cart'         => array( 'label' => __( 'Carrito', 'inversionesjl22-site' ), 'glyph' => 'f174' ),
		'car'          => array( 'label' => __( 'Vehículo', 'inversionesjl22-site' ), 'glyph' => 'f16b' ),
		'airplane'     => array( 'label' => __( 'Avión', 'inversionesjl22-site' ), 'glyph' => 'f15f' ),
		'location'     => array( 'label' => __( 'Ubicación', 'inversionesjl22-site' ), 'glyph' => 'f230' ),
		'money-alt'    => array( 'label' => __( 'Dinero', 'inversionesjl22-site' ), 'glyph' => 'f18e' ),
		'yes'          => array( 'label' => __( 'Visto', 'inversionesjl22-site' ), 'glyph' => 'f147' ),
		'saved'        => array( 'label' => __( 'Listo', 'inversionesjl22-site' ), 'glyph' => 'f15e' ),
		'dismiss'      => array( 'label' => __( 'Cancelar', 'inversionesjl22-site' ), 'glyph' => 'f153' ),
		'warning'      => array( 'label' => __( 'Aviso', 'inversionesjl22-site' ), 'glyph' => 'f534' ),
		'flag'         => array( 'label' => __( 'Bandera', 'inversionesjl22-site' ), 'glyph' => 'f227' ),
		'star-filled'  => array( 'label' => __( 'Estrella', 'inversionesjl22-site' ), 'glyph' => 'f155' ),
		'tag'          => array( 'label' => __( 'Etiqueta', 'inversionesjl22-site' ), 'glyph' => 'f323' ),
	);
}

/**
 * The CSS class WooCommerce gives a status' quick action in the orders list: its slug, but
 * "complete" and "cancel" for those two (the keys roles-actions.php and WooCommerce use).
 */
function inversionesjl22_site_status_action_class( string $slug ): string {
	$map = array(
		'completed' => 'complete',
		'cancelled' => 'cancel',
	);
	return $map[ $slug ] ?? $slug;
}

/**
 * The orders list's CSS for the statuses' colours and icons: the badge of each status with a
 * colour, and the glyph of each quick action with an icon. WooCommerce draws its own for the
 * ones without (and left the shop's actions as an empty button: their glyphs were in
 * assets/css/admin-orders.css until they became a setting).
 */
function inversionesjl22_site_status_css(): string {
	$palette = inversionesjl22_site_status_palette();
	$icons   = inversionesjl22_site_status_icons();
	$css     = '';
	foreach ( inversionesjl22_site_config()['statuses'] as $slug => $status ) {
		$class = sanitize_html_class( (string) $slug );
		$color = $status['color'] ?? '';
		$icon  = $status['icon'] ?? '';
		if ( '' === $class ) {
			continue;
		}
		if ( isset( $palette[ $color ] ) ) {
			$css .= sprintf( '.order-status.status-%1$s{background:%2$s;color:%3$s}', $class, $palette[ $color ]['bg'], $palette[ $color ]['fg'] );
		}
		if ( isset( $icons[ $icon ] ) ) {
			// The font too: WooCommerce's own actions (processing, complete) use its icon font.
			$css .= sprintf(
				'.wp-list-table .column-wc_actions a.button.%1$s::after{font-family:Dashicons;content:"\\%2$s"}',
				sanitize_html_class( inversionesjl22_site_status_action_class( (string) $slug ) ),
				$icons[ $icon ]['glyph']
			);
		}
	}
	return $css;
}
