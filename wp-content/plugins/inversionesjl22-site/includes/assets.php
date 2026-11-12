<?php
/**
 * Assets: the version is the file's mtime, not INVERSIONESJL22_SITE_VERSION.
 *
 * Why: with the plugin's fixed version as the cache key, browsers that came back after a
 * deploy kept the old JS until someone remembered to bump the header (InspecChile). The mtime
 * changes with every upload of the file, by SSH or FTPS.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Version string for a file of this plugin, relative to its folder (e.g. 'assets/site.css').
 */
function inversionesjl22_site_asset_version( string $relative ): string {
	$path = INVERSIONESJL22_SITE_DIR . $relative;
	return file_exists( $path ) ? (string) filemtime( $path ) : INVERSIONESJL22_SITE_VERSION;
}

// The compiled CSS is committed (the host has no build step): assets/site.css comes from
// assets/scss/ through the project's `build:css`. Printed in wp_head at priority 10, where
// WPCodeBox printed the styles it replaces: after the theme's stylesheets (wp_print_styles runs
// at 8), so its rules without !important still win over Woodmart's as they did.
add_action(
	'wp_head',
	static function () {
		if ( ! file_exists( INVERSIONESJL22_SITE_DIR . 'assets/site.css' ) ) {
			return;
		}
		wp_enqueue_style( 'inversionesjl22-site', INVERSIONESJL22_SITE_URL . 'assets/site.css', array(), inversionesjl22_site_asset_version( 'assets/site.css' ) );
		wp_print_styles( 'inversionesjl22-site' );
	},
	10
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( file_exists( INVERSIONESJL22_SITE_DIR . 'assets/site.js' ) ) {
			wp_enqueue_script(
				'inversionesjl22-site',
				INVERSIONESJL22_SITE_URL . 'assets/site.js',
				array(),
				inversionesjl22_site_asset_version( 'assets/site.js' ),
				array( 'strategy' => 'defer', 'in_footer' => true )
			);
		}
	}
);

/**
 * Which of the plugin's admin screens this is: 'order' (one order, posts or HPOS storage),
 * 'orders' (the list), 'shipping-report', or '' for any other. WPCodeBox loaded the forms' CSS
 * and the payment-status JS on every wp-admin page; now each goes only where it is used.
 */
function inversionesjl22_site_admin_screen(): string {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen ) {
		return '';
	}
	if ( 'woocommerce_page_shipping-report' === $screen->id ) {
		return 'shipping-report';
	}
	if ( 'shop_order' === $screen->id ) {
		return 'order';
	}
	if ( 'edit-shop_order' === $screen->id ) {
		return 'orders';
	}
	if ( 'woocommerce_page_wc-orders' === $screen->id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only, to route.
		return isset( $_GET['action'] ) && in_array( $_GET['action'], array( 'edit', 'new' ), true ) ? 'order' : 'orders';
	}
	return '';
}

// The forms' styles (.my-form: the order's boxes, the Shipping report) and the order preview's
// customer status box (in the list).
add_action(
	'admin_enqueue_scripts',
	static function () {
		if ( '' === inversionesjl22_site_admin_screen() ) {
			return;
		}
		wp_enqueue_style( 'inversionesjl22-admin-form', INVERSIONESJL22_SITE_URL . 'assets/css/admin-form.css', array(), inversionesjl22_site_asset_version( 'assets/css/admin-form.css' ) );
		// The forms' own select and date picker (on top of the native fields).
		wp_enqueue_script(
			'inversionesjl22-admin-controls',
			INVERSIONESJL22_SITE_URL . 'assets/js/admin-controls.js',
			array(),
			inversionesjl22_site_asset_version( 'assets/js/admin-controls.js' ),
			array( 'in_footer' => true )
		);
	}
);
