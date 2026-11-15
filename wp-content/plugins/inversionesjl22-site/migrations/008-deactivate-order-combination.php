<?php
/**
 * Deactivates "Ultimate Order Combination" (woo-ultimate-order-combination): the plugin combines
 * orders itself (includes/combined-orders.php, the "Combinar pedidos" bulk action). The old one
 * was slow on every wp-admin request (get_plugins() and upgrade.php on each load) and on each
 * combination (a query over all the order meta for every meta key of every order).
 *
 * Also renames, in the saved configuration, the roles' 'wuoc_combine' entry (the old plugin's
 * bulk action, which Roles y permisos took from storage_manager) to 'combine_orders', the
 * action's own key. Until this runs the code reads both, so nobody gets the action in between.
 *
 * Deactivated, not deleted: its files, its wuoc_* options and the meta it left on the orders it
 * combined stay. Undo: `wp plugin activate woo-ultimate-order-combination` (both bulk actions then
 * show, the old "Combine Selected Orders" and "Combinar pedidos": use one) and, for the roles,
 * the configuration this migration kept in inversionesjl22_site_migration_008 › previous. It
 * runs once; run again on a site where it already ran, it changes nothing.
 *
 * Skipped (it throws, the version does not advance, it is retried on the next visit) if the
 * plugin's own combining is not loaded (WPCodeBox active): the shop would have no way to combine.
 */

defined( 'ABSPATH' ) || exit;

return static function ( callable $remember ) {
	if ( ! function_exists( 'inversionesjl22_site_can_combine' ) ) {
		throw new RuntimeException( 'la combinación propia no está cargada: Ultimate Order Combination sigue activo' );
	}

	$config = get_option( INVERSIONESJL22_SITE_CONFIG_OPTION );
	if ( is_array( $config ) && isset( $config['roles'] ) && is_array( $config['roles'] ) ) {
		$renamed = $config;
		foreach ( $renamed['roles'] as $role => $rules ) {
			$bulk = $rules['exclude']['bulk_actions'] ?? null;
			if ( ! is_array( $bulk ) || ! in_array( INVERSIONESJL22_SITE_COMBINE_ACTION_OLD, $bulk, true ) ) {
				continue;
			}
			$out = array();
			foreach ( $bulk as $entry ) {
				$entry = INVERSIONESJL22_SITE_COMBINE_ACTION_OLD === $entry ? INVERSIONESJL22_SITE_COMBINE_ACTION : $entry;
				if ( ! in_array( $entry, $out, true ) ) {
					$out[] = $entry;
				}
			}
			$renamed['roles'][ $role ]['exclude']['bulk_actions'] = $out;
		}
		if ( $renamed !== $config ) {
			$remember( 'option:' . INVERSIONESJL22_SITE_CONFIG_OPTION, $config );
			update_option( INVERSIONESJL22_SITE_CONFIG_OPTION, $renamed );
		}
	}

	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$plugin = 'woo-ultimate-order-combination/index.php';
	if ( is_plugin_active( $plugin ) ) {
		$remember( 'plugin:woo-ultimate-order-combination', 'active' );
		deactivate_plugins( $plugin, true );
	}
};
