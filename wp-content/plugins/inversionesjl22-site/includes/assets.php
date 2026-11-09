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
		if ( ! file_exists( INVERSIONESJL22_SITE_DIR . 'assets/site.css' ) || inversionesjl22_site_from_wpcodebox( 'wp_head', 'styles/menu_inline_css.php' ) ) {
			return;
		}
		wp_enqueue_style( 'inversionesjl22-site', INVERSIONESJL22_SITE_URL . 'assets/site.css', array(), inversionesjl22_site_asset_version( 'assets/site.css' ) );
		wp_print_styles( 'inversionesjl22-site' );
	},
	10
);

// The admin forms' styles (.my-form), in every wp-admin page's head, as WPCodeBox printed them.
add_action(
	'admin_head',
	static function () {
		if ( inversionesjl22_site_from_wpcodebox( 'admin_head', 'snippets/form_inline_css.php' ) ) {
			return;
		}
		wp_enqueue_style( 'inversionesjl22-admin-form', INVERSIONESJL22_SITE_URL . 'assets/css/admin-form.css', array(), inversionesjl22_site_asset_version( 'assets/css/admin-form.css' ) );
		wp_print_styles( 'inversionesjl22-admin-form' );
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
 * Whether a WPCodeBox snippet still has a callback on $hook: one defined in a file of
 * wpcodebox_functionality_plugin whose path contains $file (e.g. 'styles/menu_inline_css.php').
 * For the snippets that declare no function (styles, closures), which the loader's marker
 * functions cannot detect: while the snippet runs, this plugin's copy stays off.
 */
function inversionesjl22_site_from_wpcodebox( string $hook, string $file ): bool {
	global $wp_filter;
	if ( empty( $wp_filter[ $hook ] ) ) {
		return false;
	}
	foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			if ( ! $callback['function'] instanceof Closure ) {
				continue;
			}
			$path = str_replace( '\\', '/', (string) ( new ReflectionFunction( $callback['function'] ) )->getFileName() );
			if ( false !== strpos( $path, 'wpcodebox_functionality_plugin/' ) && false !== strpos( $path, $file ) ) {
				return true;
			}
		}
	}
	return false;
}
