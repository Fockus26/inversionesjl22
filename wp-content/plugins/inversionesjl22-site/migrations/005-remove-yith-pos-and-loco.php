<?php
/**
 * Removes what YITH POS left behind (the plugin is no longer installed) and Loco Translate from
 * storage_manager. César's decision, 2026-11-11.
 *
 * - Capabilities: every yith_pos_* / *_yith-pos-* cap and the three POS caps YITH POS adds
 *   (access_woocommerce_pos, manage_woocommerce_pos, manage_woocommerce_point_of_sale), from
 *   every role (on the site: administrator and seo_manager, 56 each); loco_admin from
 *   storage_manager.
 * - Options yith_pos_* (its settings: 27).
 * - Its posts (yith-pos-store, yith-pos-register, yith-pos-receipt: 5) and their meta.
 *
 * Not touched: the yith_pos meta on orders (which store and register an old order was charged
 * at): it is the orders' history.
 *
 * Everything removed is remembered first (caps per role, option values, the posts with their
 * meta), so writing it back undoes this. Idempotent: a second run finds nothing to remove.
 */

defined( 'ABSPATH' ) || exit;

return static function ( callable $remember ) {
	global $wpdb;

	$is_pos_cap = static function ( string $cap ): bool {
		return (bool) preg_match( '/yith[-_]pos|^access_woocommerce_pos$|^manage_woocommerce_pos$|^manage_woocommerce_point_of_sale$/', $cap );
	};

	// Capabilities.
	foreach ( wp_roles()->role_objects as $slug => $role ) {
		$remove = array_values( array_filter( array_keys( $role->capabilities ), $is_pos_cap ) );
		if ( 'storage_manager' === $slug && isset( $role->capabilities['loco_admin'] ) ) {
			$remove[] = 'loco_admin';
		}
		if ( ! $remove ) {
			continue;
		}
		$remember( 'caps:' . $slug, array_intersect_key( $role->capabilities, array_flip( $remove ) ) );
		foreach ( $remove as $cap ) {
			$role->remove_cap( $cap );
		}
	}

	// Options.
	$options = $wpdb->get_col(
		$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'yith_pos_' ) . '%' )
	);
	foreach ( $options as $name ) {
		$remember( 'option:' . $name, get_option( $name ) );
		delete_option( $name );
	}

	// Its posts and their meta.
	$ids = $wpdb->get_col(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ( 'yith-pos-store', 'yith-pos-register', 'yith-pos-receipt' )"
	);
	foreach ( array_map( 'intval', $ids ) as $id ) {
		$remember( 'post:' . $id, array( 'post' => get_post( $id, ARRAY_A ), 'meta' => get_post_meta( $id ) ) );
		wp_delete_post( $id, true );
	}
};
