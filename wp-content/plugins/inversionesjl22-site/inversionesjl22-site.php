<?php
/**
 * Plugin Name:       Inversiones JL22 — Tienda
 * Description:       Estados y flujo de los pedidos, permisos por rol, facturas e informe de envíos en PDF y cierre de pedidos por QR, configurables desde su propio menú en wp-admin.
 * Version:           1.0.0
 * Requires at least: 7.1
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 11.2
 * WC tested up to:   11.2
 * Author:            Cesar Leon
 * Author URI:        https://github.com/Fockus26
 * Text Domain:       inversionesjl22-site
 */

defined( 'ABSPATH' ) || exit;

define( 'INVERSIONESJL22_SITE_VERSION', '1.0.0' );
define( 'INVERSIONESJL22_SITE_FILE', __FILE__ );
define( 'INVERSIONESJL22_SITE_DIR', plugin_dir_path( __FILE__ ) );
define( 'INVERSIONESJL22_SITE_URL', plugin_dir_url( __FILE__ ) );

// One file per feature. Listed by hand, not globbed: the order is explicit and a stray file
// left on the server by a deploy is never loaded.
require_once INVERSIONESJL22_SITE_DIR . 'includes/assets.php';
require_once INVERSIONESJL22_SITE_DIR . 'includes/acf-json.php';
require_once INVERSIONESJL22_SITE_DIR . 'includes/migrations.php';

/*
 * The features moved from WPCodeBox (context/plans/adoption.md), loaded at the end of
 * plugins_loaded, in the order WPCodeBox loaded its snippets: hooks on the same priority run in
 * that order, and some depend on it (the roles filters rebuild the actions order-statuses.php
 * adds).
 *
 * WPCodeBox is off on every site since 2026-11-11, so the per-module checks that let both run
 * side by side are gone. One check stays: if its snippets come back (someone turns WPCodeBox on
 * again, the old way back), their functions are already declared and loading these files would
 * be a fatal error. Then none of them loads and the snippets do the work, as before the move.
 */
add_action(
	'plugins_loaded',
	static function () {
		if ( function_exists( 'paymentStatusUI' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( current_user_can( 'activate_plugins' ) ) {
						echo '<div class="notice notice-warning"><p>' . esc_html__( 'WPCodeBox está activo: Inversiones JL22 — Tienda no carga sus módulos para no duplicarlos. Apaga WPCodeBox para usar los del plugin.', 'inversionesjl22-site' ) . '</p></div>';
					}
				}
			);
			return;
		}
		$modules = array(
			'config',
			'pdf',
			'clear-cart',
			'helpers',
			'payment-status',
			'change-shipping-method',
			'combined-orders',
			'order-statuses',
			'order-transition-notices',
			'shipping-report/fetch',
			'roles-config',
			'shipping-report/init',
			'roles-actions',
			'roles-access',
			'roles-enforce',
			'roles-menu',
			'settings-statuses',
			'settings-roles',
			'settings-documents',
			'shipping-report/pdf',
			'customer-status',
			'shipping-report/pdf-ui',
			'shipping-report/ui',
			'admin-menu',
			'order-transitions',
			'qr-scan',
			'invoice-pdf',
			'documents-preview',
			'invoice-customer',
			'sku-generator',
			'og-image',
		);
		foreach ( $modules as $module ) {
			require_once INVERSIONESJL22_SITE_DIR . 'includes/' . $module . '.php';
		}
	},
	PHP_INT_MAX
);
