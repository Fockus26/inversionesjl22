<?php
/**
 * Deletes the "YITH Activator" plugin (yith-activator 2.0): a tool that fakes the licences of
 * YITH's premium plugins, inactive, installed on the site. Code like that is a common way in for
 * malware, and nothing on the site uses it (the YITH plugins in use are the free ones). César's
 * decision, 2026-11-09.
 *
 * Only while it is inactive: an active one is left alone and the migration fails, so somebody
 * looks first. Deleting files cannot be remembered like an option: the version is recorded,
 * that is all; the backup of the site is the way back.
 */

defined( 'ABSPATH' ) || exit;

return static function ( callable $remember ) {
	$file = 'yith-activator/yith_activator.php';

	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';

	if ( ! file_exists( WP_PLUGIN_DIR . '/' . $file ) ) {
		return; // Idempotent: already gone.
	}
	if ( is_plugin_active( $file ) || is_plugin_active_for_network( $file ) ) {
		throw new RuntimeException( 'yith-activator is active: not deleted, check it first' );
	}

	$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );
	$remember( 'plugin:yith-activator', array( 'version' => $data['Version'] ) );

	// Without SSH this runs on an admin's first wp-admin visit: if the filesystem asks for
	// credentials there, fail and retry on the next run, never print a credentials form.
	if ( ! WP_Filesystem() ) {
		throw new RuntimeException( 'no direct filesystem access to delete yith-activator' );
	}
	$deleted = delete_plugins( array( $file ) );
	if ( true !== $deleted ) {
		throw new RuntimeException(
			'yith-activator not deleted: ' . ( is_wp_error( $deleted ) ? $deleted->get_error_message() : 'unknown' )
		);
	}
};
