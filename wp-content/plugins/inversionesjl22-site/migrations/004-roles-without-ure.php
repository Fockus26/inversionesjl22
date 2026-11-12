<?php
/**
 * Takes the roles off User Role Editor Pro: their capabilities are written here, and URE is
 * deactivated. Its only job on the site was the admin menu per role, now includes/roles-menu.php.
 *
 * The roles live in the database (wp_user_roles) and deactivating URE does not touch them; this
 * records what they had on 2026-11-11 so they can be rebuilt: a missing role is created, a
 * missing capability is added back. Capabilities a role has beyond these are left alone:
 * nothing is removed. (loco_admin and YITH POS's caps were in the snapshot; 005 removes them.)
 *
 * URE is deactivated, not deleted (its files and its ure_* options stay): César decides when to
 * delete it. URE and this plugin's menu rules can run together, so the order does not matter.
 */

defined( 'ABSPATH' ) || exit;

return static function ( callable $remember ) {
	$roles = array(
		'storage_manager' => array(
			'name' => 'Storage Manager',
			'caps' => array(
				'assign_shop_order_terms', 'delete_others_shop_orders',
				'delete_private_shop_orders', 'delete_published_shop_orders', 'delete_shop_order',
				'delete_shop_order_terms', 'delete_shop_orders', 'edit_others_shop_orders',
				'edit_private_shop_orders', 'edit_published_shop_orders', 'edit_shop_order',
				'edit_shop_order_terms', 'edit_shop_orders', 'manage_shop_order_terms',
				'manage_woocommerce', 'publish_shop_orders', 'read', 'read_private_shop_orders',
				'read_shop_order',
			),
		),
		'seo_manager'     => array(
			'name' => 'SEO Manager',
			'caps' => array(
				'assign_shop_order_terms', 'delete_others_shop_orders',
				'delete_private_shop_orders', 'delete_published_shop_orders', 'delete_shop_order',
				'delete_shop_order_terms', 'delete_shop_orders', 'edit_others_shop_orders',
				'edit_private_shop_orders', 'edit_published_shop_orders', 'edit_shop_order',
				'edit_shop_order_terms', 'edit_shop_orders', 'manage_shop_order_terms',
				'manage_woocommerce', 'publish_shop_orders', 'read', 'read_private_products',
				'read_private_shop_orders', 'read_product', 'read_shop_order',
			),
		),
	);

	foreach ( $roles as $slug => $def ) {
		$role = get_role( $slug );
		if ( ! $role ) {
			$remember( 'role:' . $slug, null );
			add_role( $slug, $def['name'], array_fill_keys( $def['caps'], true ) );
			continue;
		}
		$missing = array_values( array_diff( $def['caps'], array_keys( array_filter( $role->capabilities ) ) ) );
		if ( $missing ) {
			$remember( 'caps:' . $slug, $role->capabilities );
			foreach ( $missing as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$ure = 'user-role-editor-pro/user-role-editor-pro.php';
	if ( is_plugin_active( $ure ) ) {
		$remember( 'plugin:user-role-editor-pro', 'active' );
		deactivate_plugins( $ure, true );
	}
};
