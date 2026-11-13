<?php
/**
 * The shop's configuration: the order statuses, the flow between them and what each role sees
 * and does. One array, read by the modules that had these rules written in their own code
 * (order-statuses.php, order-transitions.php, roles-config.php, roles-actions.php,
 * roles-menu.php).
 *
 * The array comes from the option inversionesjl22_site_config; while nobody has saved one, it is
 * inversionesjl22_site_default_config(), the rules the site had in code. Nothing writes the
 * option yet: the settings screens will. Deleting it is "back to the defaults".
 */

defined( 'ABSPATH' ) || exit;

const INVERSIONESJL22_SITE_CONFIG_OPTION = 'inversionesjl22_site_config';

/**
 * The rules as they were written in code before this file.
 *
 * - statuses: the statuses the shop works with, by slug (without wc-).
 *     action_label  what its button says ("Reservar").
 *     core          true: WooCommerce's own, not registered here.
 *     label, after  only for the shop's own: its name and the status it follows in the lists.
 *     editable      the order's items can be changed while in it.
 *     note          the order note of a manual change; by default "Pedido marcado como <label>
 *                   manualmente."
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
			'on-hold'    => array(
				'action_label' => 'En espera',
				'core'         => true,
				'note'         => 'Pedido marcado como en espera manualmente.',
			),
			'reserved'   => array(
				'label'        => 'Reservado',
				'action_label' => 'Reservar',
				'core'         => false,
				'after'        => 'on-hold',
				'editable'     => true,
			),
			'processing' => array(
				'action_label' => 'Procesar',
				'core'         => true,
				'note'         => 'Pedido marcado como procesando manualmente.',
			),
			'impressed'  => array(
				'label'        => 'Impreso',
				'action_label' => 'Impreso',
				'core'         => false,
				'after'        => 'processing',
			),
			'completed'  => array(
				'action_label' => 'Completar',
				'core'         => true,
				'note'         => 'Pedido marcado como completado manualmente.',
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
