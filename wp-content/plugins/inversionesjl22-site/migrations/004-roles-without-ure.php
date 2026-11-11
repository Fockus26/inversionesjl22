<?php
/**
 * Takes the roles off User Role Editor Pro: their capabilities are written here, and URE is
 * deactivated. Its only job on the site was the admin menu per role, now includes/roles-menu.php.
 *
 * The roles live in the database (wp_user_roles) and deactivating URE does not touch them; this
 * records what they had on 2026-11-11 so they can be rebuilt: a missing role is created, a
 * missing capability is added back. Capabilities a role has beyond these are left alone:
 * nothing is removed.
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
				'edit_shop_order_terms', 'edit_shop_orders', 'loco_admin',
				'manage_shop_order_terms', 'manage_woocommerce', 'publish_shop_orders', 'read',
				'read_private_shop_orders', 'read_shop_order',
			),
		),
		// The yith-pos caps are from YITH POS, no longer installed: kept as they were.
		'seo_manager'     => array(
			'name' => 'SEO Manager',
			'caps' => array(
				'access_woocommerce_pos', 'assign_shop_order_terms', 'create_yith-pos-receipts',
				'create_yith-pos-registers', 'create_yith-pos-stores', 'delete_others_shop_orders',
				'delete_others_yith-pos-receipts', 'delete_others_yith-pos-registers',
				'delete_others_yith-pos-stores', 'delete_private_shop_orders',
				'delete_private_yith-pos-receipts', 'delete_private_yith-pos-registers',
				'delete_private_yith-pos-stores', 'delete_published_shop_orders',
				'delete_published_yith-pos-receipts', 'delete_published_yith-pos-registers',
				'delete_published_yith-pos-stores', 'delete_shop_order', 'delete_shop_order_terms',
				'delete_shop_orders', 'delete_yith-pos-receipt', 'delete_yith-pos-receipts',
				'delete_yith-pos-register', 'delete_yith-pos-registers', 'delete_yith-pos-store',
				'delete_yith-pos-stores', 'edit_others_shop_orders',
				'edit_others_yith-pos-receipts', 'edit_others_yith-pos-registers',
				'edit_others_yith-pos-stores', 'edit_private_shop_orders',
				'edit_private_yith-pos-receipts', 'edit_private_yith-pos-registers',
				'edit_private_yith-pos-stores', 'edit_published_shop_orders',
				'edit_published_yith-pos-receipts', 'edit_published_yith-pos-registers',
				'edit_published_yith-pos-stores', 'edit_shop_order', 'edit_shop_order_terms',
				'edit_shop_orders', 'edit_yith-pos-receipt', 'edit_yith-pos-receipts',
				'edit_yith-pos-register', 'edit_yith-pos-registers', 'edit_yith-pos-store',
				'edit_yith-pos-stores', 'manage_shop_order_terms', 'manage_woocommerce',
				'manage_woocommerce_point_of_sale', 'manage_woocommerce_pos',
				'publish_shop_orders', 'publish_yith-pos-receipts', 'publish_yith-pos-registers',
				'publish_yith-pos-stores', 'read', 'read_private_products',
				'read_private_shop_orders', 'read_private_yith-pos-receipts',
				'read_private_yith-pos-registers', 'read_private_yith-pos-stores', 'read_product',
				'read_shop_order', 'yith_pos_create_orders', 'yith_pos_create_products',
				'yith_pos_create_users', 'yith_pos_edit_users', 'yith_pos_manage_others_pos',
				'yith_pos_manage_pos', 'yith_pos_manage_pos_options', 'yith_pos_use_pos',
				'yith_pos_view_coupons', 'yith_pos_view_orders', 'yith_pos_view_product_cats',
				'yith_pos_view_products', 'yith_pos_view_reports', 'yith_pos_view_users',
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
