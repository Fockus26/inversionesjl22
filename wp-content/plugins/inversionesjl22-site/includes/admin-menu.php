<?php
/**
 * The plugin's own top-level menu in wp-admin, "Inversiones JL22", right below WooCommerce, with
 * every screen of the plugin: Estados y flujo, Roles y permisos, Documentos and Informe de Envíos.
 * Until 2026-11-17 they hung from WooCommerce's menu (César: "que tenga su propio menú").
 *
 * The page slugs are the ones they had (admin.php?page=jl22-statuses…), so saved links keep
 * working and so do the roles' menu lists (roles-menu.php keys a plugin page by its slug only,
 * 'admin.php?page=<slug>', whatever its parent: nothing to migrate). Each screen keeps its
 * capability; its file keeps its callback, its assets and its form handlers.
 *
 * The top-level entry takes the slug of the first screen, so wp-admin does not add a duplicate
 * "Inversiones JL22" submenu, and the capability of the first screen the user can open, so a
 * user who can open only the shipping report (storage_manager, shop managers) gets the menu
 * pointing to it, and one who can open none gets no menu.
 *
 * Loaded after the settings-*.php files and shipping-report/ui.php: their constants and
 * callbacks have to exist when admin_menu runs.
 */

defined( 'ABSPATH' ) || exit;

// The top-level entry: also the parent of every submenu below.
const INVERSIONESJL22_SITE_MENU = 'jl22-statuses';

if ( ! is_admin() ) {
	return;
}

/**
 * The plugin's screens, in the menu's order: slug => title, capability and callback.
 *
 * @return array<string, array{title: string, capability: string, callback: string}>
 */
function inversionesjl22_site_admin_pages(): array {
	return array(
		INVERSIONESJL22_SITE_STATUSES_PAGE  => array(
			'title'      => __( 'Estados y flujo', 'inversionesjl22-site' ),
			'capability' => 'manage_options',
			'callback'   => 'inversionesjl22_site_statuses_screen',
		),
		INVERSIONESJL22_SITE_ROLES_PAGE     => array(
			'title'      => __( 'Roles y permisos', 'inversionesjl22-site' ),
			'capability' => 'manage_options',
			'callback'   => 'inversionesjl22_site_roles_screen',
		),
		INVERSIONESJL22_SITE_DOCUMENTS_PAGE => array(
			'title'      => __( 'Documentos', 'inversionesjl22-site' ),
			'capability' => 'manage_options',
			'callback'   => 'inversionesjl22_site_documents_screen',
		),
		'shipping-report'                   => array(
			'title'      => 'Informe de Envíos',
			'capability' => 'manage_woocommerce',
			'callback'   => 'shippingReportUI',
		),
	);
}

add_action(
	'admin_menu',
	static function () {
		$pages   = inversionesjl22_site_admin_pages();
		$visible = array_filter(
			$pages,
			static function ( array $page ): bool {
				return current_user_can( $page['capability'] );
			}
		);
		if ( ! $visible ) {
			return;
		}
		$first = reset( $visible );

		// No callback here: each screen's is hooked by its own submenu, only for who can open it.
		add_menu_page(
			__( 'Inversiones JL22', 'inversionesjl22-site' ),
			__( 'Inversiones JL22', 'inversionesjl22-site' ),
			$first['capability'],
			INVERSIONESJL22_SITE_MENU,
			'',
			'dashicons-store',
			'55.51'
		);
		foreach ( $pages as $slug => $page ) {
			add_submenu_page(
				INVERSIONESJL22_SITE_MENU,
				$page['title'],
				$page['title'],
				$page['capability'],
				$slug,
				$page['callback']
			);
		}
	}
);

// Right below WooCommerce. WooCommerce reorders the menu itself (menu_order, priority 10: its
// separator, WooCommerce, Productos), so the position above is not enough. menu_order only runs
// with custom_menu_order on, which WooCommerce already turns on.
add_filter( 'custom_menu_order', '__return_true' );
add_filter(
	'menu_order',
	static function ( $order ) {
		if ( ! is_array( $order ) ) {
			return $order;
		}
		$ours = array_search( INVERSIONESJL22_SITE_MENU, $order, true );
		$woo  = array_search( 'woocommerce', $order, true );
		if ( false === $ours || false === $woo ) {
			return $order;
		}
		unset( $order[ $ours ] );
		$order = array_values( $order );
		array_splice( $order, (int) array_search( 'woocommerce', $order, true ) + 1, 0, array( INVERSIONESJL22_SITE_MENU ) );
		return $order;
	},
	20
);

// A body class per screen (jl22-page-<slug>) for the screens' CSS, instead of the one wp-admin
// derives from the parent's title (it was woocommerce_page_shipping-report).
add_filter(
	'admin_body_class',
	static function ( $classes ) {
		global $plugin_page;
		if ( is_string( $plugin_page ) && isset( inversionesjl22_site_admin_pages()[ $plugin_page ] ) ) {
			$classes .= ' jl22-admin jl22-page-' . sanitize_html_class( $plugin_page );
		}
		return $classes;
	}
);
