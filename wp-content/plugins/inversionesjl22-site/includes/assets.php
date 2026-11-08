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

add_action(
	'wp_enqueue_scripts',
	static function () {
		// The compiled CSS is committed (the host has no build step): assets/site.css comes
		// from assets/scss/ through the project's `build:css`.
		if ( file_exists( INVERSIONESJL22_SITE_DIR . 'assets/site.css' ) ) {
			wp_enqueue_style( 'inversionesjl22-site', INVERSIONESJL22_SITE_URL . 'assets/site.css', array(), inversionesjl22_site_asset_version( 'assets/site.css' ) );
		}
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
