<?php
/**
 * Roles: which wp-admin screens storage_manager and seo_manager can open, and the menu and
 * admin bar they see. Replaces User Role Editor Pro's "admin menu access" module (the only one
 * the site used), so URE can go.
 *
 * Both roles have manage_woocommerce, so without this they reach WooCommerce's settings, PDF
 * Invoices, Multi Currency… Hiding the menu is not enough: a screen not listed below sends
 * them to the dashboard even when typed in the address bar, as URE did.
 *
 * Allowlists, not blocklists (URE blocked 28 items for seo_manager): a plugin installed
 * tomorrow stays out of these roles' reach until it is added here. The lists are what each
 * role saw with URE on (.qa/menu-con-ure.json, 2026-11-11), minus Loco Translate for
 * storage_manager (César, 2026-11-11). They live in the configuration (config.php), with the
 * rest of each role's rules.
 *
 * Keys: 'index.php', 'profile.php'… for a core screen; 'admin.php?page=<slug>' for a plugin
 * page; '<screen>.php?post_type=<type>' for edit.php, post.php and post-new.php.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Screens per role. 'menu': shown in the side menu and openable; 'screens': openable without a
 * menu entry; 'admin_bar': the admin bar's top-level nodes kept (my-account and the search,
 * on the right, always stay).
 *
 * @return array<string, array{menu: string[], screens: string[], admin_bar: string[]}>
 */
function inversionesjl22_site_role_screens(): array {
	$screens = array();
	foreach ( inversionesjl22_site_config()['roles'] as $role => $rules ) {
		$screens[ $role ] = array(
			'menu'      => array_values( (array) ( $rules['menu'] ?? array() ) ),
			'screens'   => array_values( (array) ( $rules['screens'] ?? array() ) ),
			'admin_bar' => array_values( (array) ( $rules['admin_bar'] ?? array() ) ),
		);
	}
	return $screens;
}

/**
 * The rules for the current user, or null when nothing is restricted (administrators, shop
 * managers, any other role). A user with both a restricted role and another one gets the
 * first restricted role's rules, as getUserPermissions() does for the order rules.
 */
function inversionesjl22_site_current_role_screens(): ?array {
	if ( ! is_user_logged_in() || current_user_can( 'administrator' ) ) {
		return null;
	}
	$rules = inversionesjl22_site_role_screens();
	foreach ( (array) wp_get_current_user()->roles as $role ) {
		if ( isset( $rules[ $role ] ) ) {
			return $rules[ $role ];
		}
	}
	return null;
}

/**
 * The link of a menu entry, as wp-admin builds its href (menu-header.php).
 */
function inversionesjl22_site_menu_link( string $slug, string $parent = '' ): string {
	if ( false !== strpos( $slug, '.php' ) ) {
		return $slug;
	}
	if ( '' !== $parent && false !== strpos( $parent, '.php' ) && 0 !== strpos( $parent, 'admin.php' ) ) {
		return $parent . ( false === strpos( $parent, '?' ) ? '?' : '&' ) . 'page=' . $slug;
	}
	return 'admin.php?page=' . $slug;
}

/**
 * The key of the screen being requested, in the same shape as the lists; '' for requests
 * that are not a screen (admin.php?action=…, which runs its own capability checks).
 */
function inversionesjl22_site_requested_screen(): string {
	global $pagenow;

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read only, to route.
	if ( 'admin.php' === $pagenow ) {
		if ( empty( $_GET['page'] ) ) {
			return '';
		}
		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		// wc-admin's sections (Marketing, Analytics…) are the same page with a path.
		if ( 'wc-admin' === $page && ! empty( $_GET['path'] ) && '/' !== $_GET['path'] ) {
			return 'admin.php?page=wc-admin&path=' . sanitize_text_field( wp_unslash( $_GET['path'] ) );
		}
		return 'admin.php?page=' . $page;
	}

	if ( in_array( $pagenow, array( 'edit.php', 'post-new.php' ), true ) ) {
		$type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : 'post';
		return $pagenow . '?post_type=' . $type;
	}

	if ( 'post.php' === $pagenow ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- post.php checks its own nonce.
		$id = absint( $_REQUEST['post'] ?? ( $_POST['post_ID'] ?? 0 ) );
		return 'post.php?post_type=' . ( $id ? (string) get_post_type( $id ) : '' );
	}
	// phpcs:enable

	return (string) $pagenow;
}

// A screen off the role's lists → the dashboard (what URE did). On admin_menu, just before
// the menu is trimmed below: wp-admin/menu.php checks access right after admin_menu and would
// answer a 403 for the entries removed, and admin_init comes later.
add_action(
	'admin_menu',
	static function () {
		global $pagenow;
		if ( wp_doing_ajax() || in_array( $pagenow, array( 'admin-ajax.php', 'admin-post.php', 'async-upload.php' ), true ) ) {
			return;
		}
		$rules = inversionesjl22_site_current_role_screens();
		if ( null === $rules ) {
			return;
		}
		$screen = inversionesjl22_site_requested_screen();
		if ( '' === $screen || in_array( $screen, array_merge( $rules['menu'], $rules['screens'] ), true ) ) {
			return;
		}
		wp_safe_redirect( admin_url( 'index.php' ) );
		exit;
	},
	PHP_INT_MAX - 1
);

// The side menu: only the role's entries. A top-level entry stays while one of its submenus
// does; wp-admin then links it to its first remaining submenu (storage's "WooCommerce" →
// Pedidos).
add_action(
	'admin_menu',
	static function () {
		global $menu, $submenu;
		$rules = inversionesjl22_site_current_role_screens();
		if ( null === $rules ) {
			return;
		}
		$allowed = $rules['menu'];

		foreach ( (array) $submenu as $parent => $items ) {
			foreach ( $items as $i => $item ) {
				if ( ! in_array( inversionesjl22_site_menu_link( (string) $item[2], (string) $parent ), $allowed, true ) ) {
					unset( $submenu[ $parent ][ $i ] );
				}
			}
			if ( empty( $submenu[ $parent ] ) ) {
				unset( $submenu[ $parent ] );
			}
		}

		foreach ( (array) $menu as $i => $item ) {
			$slug = (string) $item[2];
			if ( ! empty( $submenu[ $slug ] ) ) {
				continue;
			}
			if ( 0 === strpos( $slug, 'separator' ) || ! in_array( inversionesjl22_site_menu_link( $slug ), $allowed, true ) ) {
				unset( $menu[ $i ] );
			}
		}
	},
	PHP_INT_MAX
);

// The admin bar: only the role's top-level nodes. The command palette goes too: it lists
// every screen the role's capabilities reach, not the ones above.
add_action(
	'admin_bar_menu',
	static function ( WP_Admin_Bar $bar ) {
		$rules = inversionesjl22_site_current_role_screens();
		if ( null === $rules ) {
			return;
		}
		$keep = array_merge( $rules['admin_bar'], array( 'top-secondary' ) );
		foreach ( (array) $bar->get_nodes() as $node ) {
			if ( empty( $node->parent ) && ! in_array( $node->id, $keep, true ) ) {
				$bar->remove_node( $node->id );
			}
		}
	},
	PHP_INT_MAX
);
